#!/bin/bash
# =============================================================================
# GITHUB ACTIONS SELF-HOSTED RUNNER — HOST SETUP
#
# Run this ON the runner EC2 (172.16.11.224), as root/sudo, via the jump host:
#   sudo bash setup-github-runner.sh \
#     --url https://github.com/Logimax-Technologies \
#     --token <REGISTRATION_TOKEN> \
#     --label etail-new \
#     --count 6
#
# What it does:
#   1. Installs the shared toolchain every workflow assumes: git, curl,
#      openssl, jq, unzip, rsync, PHP 7.4 + extensions + Composer,
#      Python3 + venv/pip + uv, Node.js 20.
#   2. Downloads the GitHub Actions runner and registers N independent
#      instances (queue depth = N) under a dedicated `github-runner` user,
#      each installed as its own systemd service so they survive reboot.
#   3. Leaves ~/runner-etail-src/.env EMPTY — copy the real one from the
#      old runner by hand (it holds live credentials; this script must not
#      contain or generate secrets).
#
# Labels: pass --label etail-new for the first (isolated) provisioning pass
# per the migration plan's Part 0. Add the `self-hosted` label afterwards
# from the GitHub UI (Settings -> Actions -> Runners -> runner -> Labels)
# once validated — no need to re-run this script for that step.
# NOTE: GitHub automatically adds the "self-hosted" label to every
# self-hosted runner regardless of custom labels — a custom --label does
# NOT hide this runner from existing runs-on: self-hosted workflows. True
# isolation (repo-restricted runner groups) needs GitHub Team/Enterprise.
# Make sure the real .env is copied and the toolchain checks out before
# leaving the services running for any length of time.
#
# Registration tokens expire in ~1 hour — fetch a fresh one right before
# running this script (org: Settings -> Actions -> Runners -> New runner;
# enterprise: same path under the enterprise settings).
#
# RESILIENCE: this script intentionally does NOT use `set -e`. A single
# flaky package (e.g. a mid-migration PPA) must not prevent the script
# from reaching runner registration, which is the step that actually
# matters. Each stage checks its own outcome, logs a clear OK/WARN, and
# keeps going; everything that needs attention is listed in the summary
# printed at the end.
# =============================================================================

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

WARNINGS=()
warn() { echo -e "${YELLOW}⚠ $1${NC}"; WARNINGS+=("$1"); }
ok()   { echo -e "${GREEN}✓ $1${NC}"; }

echo -e "${BLUE}"
echo "╔═══════════════════════════════════════════════════════════════╗"
echo "║     LOGIMAX - GitHub Actions Self-Hosted Runner Setup          ║"
echo "╚═══════════════════════════════════════════════════════════════╝"
echo -e "${NC}"

# =============================================================================
# ARGUMENTS
# =============================================================================
RUNNER_URL=""
RUNNER_TOKEN=""
RUNNER_LABEL="etail-new"
INSTANCE_COUNT=6
SERVICE_USER="github-runner"
INSTALL_BASE="/opt/actions-runner"
RUNNER_VERSION=""          # empty => auto-detect latest release below
RUNNER_VERSION_FALLBACK="2.321.0"

while [[ $# -gt 0 ]]; do
  case "$1" in
    --url) RUNNER_URL="$2"; shift 2 ;;
    --token) RUNNER_TOKEN="$2"; shift 2 ;;
    --label) RUNNER_LABEL="$2"; shift 2 ;;
    --count) INSTANCE_COUNT="$2"; shift 2 ;;
    --user) SERVICE_USER="$2"; shift 2 ;;
    --install-base) INSTALL_BASE="$2"; shift 2 ;;
    --runner-version) RUNNER_VERSION="$2"; shift 2 ;;
    *) echo -e "${RED}Unknown argument: $1${NC}"; exit 1 ;;
  esac
done

if [ -z "$RUNNER_URL" ] || [ -z "$RUNNER_TOKEN" ]; then
  echo -e "${RED}Usage: sudo bash setup-github-runner.sh --url <org-or-enterprise-url> --token <REGISTRATION_TOKEN> [--label etail-new] [--count 6]${NC}"
  exit 1
fi

if [ "$EUID" -ne 0 ]; then
  echo -e "${RED}Run this as root (sudo) — it installs OS packages and a systemd service.${NC}"
  exit 1
fi

# =============================================================================
# 1. SERVICE USER
# =============================================================================
echo -e "${YELLOW}[1/5] Creating service user '${SERVICE_USER}'...${NC}"
if ! id -u "$SERVICE_USER" >/dev/null 2>&1; then
  if useradd --create-home --shell /bin/bash "$SERVICE_USER"; then
    ok "Created user ${SERVICE_USER}"
  else
    warn "Could not create user ${SERVICE_USER} — fix this before continuing (registration will fail without it)"
  fi
else
  ok "User ${SERVICE_USER} already exists"
fi
RUNNER_HOME=$(eval echo "~${SERVICE_USER}")

# =============================================================================
# 2. TOOLCHAIN (git, curl, openssl, jq, unzip, rsync, PHP 7.4, Composer,
#    Python3 + uv, Node.js 20) — every step here is best-effort; failures are
#    recorded but do not stop the script.
# =============================================================================
echo -e "${YELLOW}[2/5] Installing toolchain...${NC}"

export DEBIAN_FRONTEND=noninteractive
apt-get update -y || warn "apt-get update failed — package installs below may use a stale index"

apt-get install -y --no-install-recommends \
  git curl wget openssl jq unzip rsync ca-certificates \
  software-properties-common apt-transport-https lsb-release gnupg2 \
  build-essential \
  || warn "Base package install (git/curl/jq/rsync/etc.) reported errors — check output above"

# --- PHP 7.4 + extensions -----------------------------------------------
# Known issue: the ondrej/php PPA occasionally serves an inconsistent index
# while it migrates package hosts — one or more extensions resolve to a
# newer php7.4-common than what's actually installable ("Depends:
# php7.4-common (= X) but Y is to be installed"). A plain retry with a
# refreshed index does NOT reliably fix this (confirmed on a live run) —
# what does is telling apt it's allowed to move any of these packages'
# versions in either direction (--allow-downgrades) so it can settle on
# whatever mutually-consistent version set is actually available, instead
# of rigidly insisting on the newest candidate for each package.
add-apt-repository -y ppa:ondrej/php || warn "Could not add ppa:ondrej/php — PHP 7.4 install will likely fail"
apt-get update -y || true

PHP_PACKAGES="php7.4-common php7.4-cli php7.4-mysql php7.4-curl php7.4-gd php7.4-mbstring php7.4-xml php7.4-zip php7.4-bcmath"

# Confirmed root cause on a live run: the base image ships
# libapache2-mod-php7.4, pinned to an older php7.4-common than the CLI
# extensions need. Removing it doesn't work cleanly — a `php7.4`
# meta-package depends on SOME way to run PHP (mod_php, fpm, or cgi), so
# apt refuses the removal rather than leave that unsatisfied. This is a
# runner, not a web server, so instead of fighting that, just let the
# module upgrade to the SAME version as everything else by including it
# explicitly in the install set below.
if dpkg -l libapache2-mod-php7.4 2>/dev/null | grep -q '^ii'; then
  PHP_PACKAGES="libapache2-mod-php7.4 $PHP_PACKAGES"
fi

# --allow-downgrades: lets apt settle the php7.4-common version-pin
# mismatch above. --allow-change-held-packages: the base image's PHP
# packages come pre-marked as held (confirmed on a live run — apt refuses
# `-y` alone with "Held packages were changed"), so this is needed from
# the first attempt, not just as a fallback.
if apt-get install -y --no-install-recommends --allow-downgrades --allow-change-held-packages $PHP_PACKAGES; then
  ok "PHP 7.4 + extensions installed"
else
  echo -e "${YELLOW}  Retrying with a refreshed package index...${NC}"
  apt-get update -y || true
  if apt-get install -y --no-install-recommends --allow-downgrades --allow-change-held-packages $PHP_PACKAGES; then
    ok "PHP 7.4 + extensions installed (after retry)"
  else
    warn "PHP 7.4 extensions failed to install even after retry — php -l may still work if php7.4-cli was already present, but mysql/curl/gd/mbstring/xml/zip/bcmath extensions may be missing. Last resort: apt-get remove -y php7.4 libapache2-mod-php7.4 (drops the unused meta-package + Apache module together, since this is a runner not a web server), then retry: apt-get install -y --allow-downgrades --allow-change-held-packages $PHP_PACKAGES"
  fi
fi

# Composer: prefer the Ubuntu repo package — it comes from the SAME mirror
# that already worked for git/php/etc., so it needs no extra egress. Only
# fall back to the getcomposer.org curl installer (network-dependent, and
# known to hang with no timeout if that host is firewalled) if apt doesn't
# have it. Every network call below is wrapped in `timeout` so a stalled
# connection warns and moves on instead of freezing the whole script.
if ! command -v composer >/dev/null 2>&1; then
  if apt-get install -y --no-install-recommends composer; then
    ok "Composer installed (via apt)"
  elif command -v php >/dev/null 2>&1 && timeout 30 bash -c 'curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer'; then
    ok "Composer installed (via getcomposer.org installer)"
  else
    warn "Composer install failed or timed out (apt package unavailable and getcomposer.org unreachable/blocked?) — install manually once network/PHP is sorted"
  fi
else
  ok "Composer already present"
fi

# --- Python 3 + venv/pip + uv --------------------------------------------
# used by e2e-tests, lca-check, impact-analysis, pr-intelligence, pr-auto-body
apt-get install -y --no-install-recommends python3 python3-venv python3-pip \
  || warn "python3/venv/pip install reported errors"

if ! command -v uv >/dev/null 2>&1; then
  if timeout 60 bash -c 'curl -LsSf https://astral.sh/uv/install.sh | env UV_INSTALL_DIR="/usr/local/bin" sh'; then
    ok "uv installed"
  else
    warn "uv install failed or timed out (astral.sh unreachable?) — retry manually: curl -LsSf https://astral.sh/uv/install.sh | sh"
  fi
else
  ok "uv already present"
fi

# --- Node.js 20 LTS -------------------------------------------------------
# every actions/checkout, github-script, setup-*, and action-send-mail step
# is a Node action
if ! command -v node >/dev/null 2>&1; then
  if timeout 60 bash -c 'curl -fsSL https://deb.nodesource.com/setup_20.x | bash -' && apt-get install -y --no-install-recommends nodejs; then
    ok "Node.js installed ($(node --version))"
  else
    warn "Node.js install via NodeSource failed or timed out — falling back to the Ubuntu repo's nodejs package (older version, but functional)"
    apt-get install -y --no-install-recommends nodejs || warn "Fallback nodejs install also failed"
  fi
else
  ok "Node.js already present ($(node --version)) — leaving as-is"
fi

echo ""
echo -e "${BLUE}Toolchain summary:${NC}"
echo "   git:      $(timeout 5 git --version 2>/dev/null || echo 'MISSING')"
echo "   php:      $(timeout 5 php -v 2>/dev/null | head -1 || echo 'MISSING')"
echo "   composer: $(timeout 5 composer --version 2>/dev/null || echo 'MISSING')"
echo "   python3:  $(timeout 5 python3 --version 2>/dev/null || echo 'MISSING')"
echo "   uv:       $(timeout 5 uv --version 2>/dev/null || echo 'MISSING')"
echo "   node:     $(timeout 5 node --version 2>/dev/null || echo 'MISSING')"

# =============================================================================
# 3. CREDENTIALS FILE PLACEHOLDER (e2e-tests.yml reads this path)
# =============================================================================
echo ""
echo -e "${YELLOW}[3/5] Preparing ~/runner-etail-src/.env placeholder...${NC}"
sudo -u "$SERVICE_USER" mkdir -p "${RUNNER_HOME}/runner-etail-src"
if [ ! -f "${RUNNER_HOME}/runner-etail-src/.env" ]; then
  cat <<'EOF' > "${RUNNER_HOME}/runner-etail-src/.env"
# ⚠️ PLACEHOLDER — copy the real .env from the OLD runner (same filename,
# same location) before enabling this box for e2e-tests / smoke-tests.
# This file is intentionally NOT generated by setup-github-runner.sh —
# it holds live credentials and must be transferred by hand.
EOF
  chown "$SERVICE_USER:$SERVICE_USER" "${RUNNER_HOME}/runner-etail-src/.env"
  chmod 600 "${RUNNER_HOME}/runner-etail-src/.env"
fi
ok "Placeholder ready at ${RUNNER_HOME}/runner-etail-src/.env — remember to copy the real one"

# =============================================================================
# 4. DOWNLOAD RUNNER PACKAGE (once, shared by all instances)
# =============================================================================
echo ""
if [ -z "$RUNNER_VERSION" ]; then
  LATEST=$(curl -s --max-time 15 https://api.github.com/repos/actions/runner/releases/latest 2>/dev/null | grep -o '"tag_name": *"v[^"]*"' | head -1 | grep -o '[0-9][0-9.]*')
  if [ -n "$LATEST" ]; then
    RUNNER_VERSION="$LATEST"
  else
    RUNNER_VERSION="$RUNNER_VERSION_FALLBACK"
    warn "Could not auto-detect latest actions/runner release — using pinned fallback v${RUNNER_VERSION_FALLBACK}. Pass --runner-version to override."
  fi
fi
echo -e "${YELLOW}[4/5] Downloading GitHub Actions runner v${RUNNER_VERSION}...${NC}"
mkdir -p "$INSTALL_BASE"
RUNNER_TARBALL="actions-runner-linux-x64-${RUNNER_VERSION}.tar.gz"
if [ -f "${INSTALL_BASE}/${RUNNER_TARBALL}" ]; then
  ok "Runner tarball already downloaded"
elif curl -fL --max-time 180 -o "${INSTALL_BASE}/${RUNNER_TARBALL}" \
    "https://github.com/actions/runner/releases/download/v${RUNNER_VERSION}/${RUNNER_TARBALL}"; then
  ok "Downloaded runner v${RUNNER_VERSION}"
else
  echo -e "${RED}✗ Failed to download the runner package — cannot register any instances. Check the version (--runner-version) and network egress to github.com, then re-run.${NC}"
  rm -f "${INSTALL_BASE}/${RUNNER_TARBALL}"
  exit 1
fi

# =============================================================================
# 5. REGISTER + INSTALL N RUNNER INSTANCES AS SYSTEMD SERVICES
#    Each instance is independent — one failing does not stop the rest.
# =============================================================================
echo ""
echo -e "${YELLOW}[5/5] Registering ${INSTANCE_COUNT} runner instance(s) with label '${RUNNER_LABEL}'...${NC}"

HOSTTAG=$(hostname -s 2>/dev/null || echo "ec2")
REGISTERED=0

for i in $(seq 1 "$INSTANCE_COUNT"); do
  INSTANCE_DIR="${INSTALL_BASE}/instance-${i}"
  INSTANCE_NAME="etail-${HOSTTAG}-${i}"

  echo -e "\n${BLUE}━━━ Instance ${i}: ${INSTANCE_NAME} ━━━${NC}"

  if [ -d "$INSTANCE_DIR" ] && [ -f "${INSTANCE_DIR}/.runner" ]; then
    ok "Already configured — skipping registration"
    REGISTERED=$((REGISTERED + 1))
    continue
  fi

  mkdir -p "$INSTANCE_DIR"
  if ! tar xzf "${INSTALL_BASE}/${RUNNER_TARBALL}" -C "$INSTANCE_DIR"; then
    warn "Instance ${i}: failed to extract runner tarball — skipping"
    continue
  fi
  chown -R "$SERVICE_USER:$SERVICE_USER" "$INSTANCE_DIR"

  if ! sudo -u "$SERVICE_USER" bash -c "cd '$INSTANCE_DIR' && ./config.sh \
      --url '$RUNNER_URL' \
      --token '$RUNNER_TOKEN' \
      --name '$INSTANCE_NAME' \
      --labels '$RUNNER_LABEL' \
      --work '_work' \
      --unattended \
      --replace"; then
    warn "Instance ${i}: registration failed (token expired? fetch a fresh one and re-run just this instance) — skipping"
    continue
  fi

  if ! (cd "$INSTANCE_DIR" && ./svc.sh install "$SERVICE_USER"); then
    warn "Instance ${i}: registered with GitHub but systemd service install failed — check manually"
    continue
  fi
  if ! (cd "$INSTANCE_DIR" && ./svc.sh start); then
    warn "Instance ${i}: service installed but failed to start — check: systemctl status actions.runner.*.${INSTANCE_NAME}.service"
    continue
  fi

  ok "${INSTANCE_NAME} registered and started"
  REGISTERED=$((REGISTERED + 1))
done

# =============================================================================
# SUMMARY
# =============================================================================
echo -e "\n${GREEN}"
echo "╔═══════════════════════════════════════════════════════════════╗"
echo "║  DONE — ${REGISTERED}/${INSTANCE_COUNT} instance(s) registered with label: ${RUNNER_LABEL}"
echo "╚═══════════════════════════════════════════════════════════════╝"
echo -e "${NC}"

if [ "${#WARNINGS[@]}" -gt 0 ]; then
  echo -e "${YELLOW}Needs attention (script kept going, but check these):${NC}"
  for w in "${WARNINGS[@]}"; do
    echo -e "  ${YELLOW}- ${w}${NC}"
  done
  echo ""
fi

echo "Next steps:"
echo "  1. Verify instances show 'Idle' in GitHub -> Settings -> Actions -> Runners."
echo "  2. Copy the REAL .env from the old runner to:"
echo "       ${RUNNER_HOME}/runner-etail-src/.env"
echo "  3. Test in isolation (pin a scratch workflow's runs-on to '${RUNNER_LABEL}')."
echo "  4. Once validated, add the 'self-hosted' label to these runners from the"
echo "     GitHub UI (no re-run needed) so they join the live pool."
echo "  5. Check service status any time with:"
echo "       sudo systemctl status actions.runner.*.service"

if [ "$REGISTERED" -eq 0 ]; then
  echo -e "\n${RED}No instances registered — nothing will show up in GitHub yet. Fix the warnings above and re-run.${NC}"
  exit 1
fi
