#!/bin/bash
# =============================================================================
# RUNNER .ENV SETUP
# Creates the .env file the e2e-tests.yml workflow reads from
# $HOME/runner-etail-src/.env. Pass values as flags — no manual editing.
#
# Usage:
#   sudo bash setup-runner-env.sh \
#     --username admin \
#     --password 'REAL_PASSWORD' \
#     --db-user retaillmx_admin \
#     --db-password 'REAL_DB_PASSWORD' \
#     --db-name retail_dev
#
# Required:  --username --password
# Optional (defaults shown):
#   --user github-runner         Linux user the .env is written for
#   --base-url ''                blank = workflow auto-picks by environment
#   --db-host 127.0.0.1
#   --db-port 3307
#   --db-user retaillmx_admin
#   --db-password ''
#   --db-name retail_dev
#   --headless true
#   --browser chromium
# =============================================================================

RED='\033[0;31m'; GREEN='\033[0;32m'; NC='\033[0m'

USER_NAME="github-runner"
BASE_URL=""
E2E_USERNAME=""
E2E_PASSWORD=""
HEADLESS="true"
BROWSER="chromium"
DB_HOST="127.0.0.1"
DB_PORT="3307"
DB_USER="retaillmx_admin"
DB_PASSWORD=""
DB_NAME="retail_dev"

usage() {
  echo "Usage: sudo bash setup-runner-env.sh --username <user> --password <pass> [options]"
  echo ""
  echo "Required:"
  echo "  --username <val>       E2E_USERNAME (admin login for tests)"
  echo "  --password <val>       E2E_PASSWORD"
  echo ""
  echo "Optional:"
  echo "  --user <val>           Linux user to own the file (default: github-runner)"
  echo "  --base-url <val>       Target site URL (default: blank, auto-picked per environment)"
  echo "  --db-host <val>        (default: 127.0.0.1)"
  echo "  --db-port <val>        (default: 3307)"
  echo "  --db-user <val>        (default: retaillmx_admin)"
  echo "  --db-password <val>    (default: blank)"
  echo "  --db-name <val>        (default: retail_dev)"
  echo "  --headless <true|false> (default: true)"
  echo "  --browser <val>        (default: chromium)"
  exit 1
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --user) USER_NAME="$2"; shift 2 ;;
    --base-url) BASE_URL="$2"; shift 2 ;;
    --username) E2E_USERNAME="$2"; shift 2 ;;
    --password) E2E_PASSWORD="$2"; shift 2 ;;
    --headless) HEADLESS="$2"; shift 2 ;;
    --browser) BROWSER="$2"; shift 2 ;;
    --db-host) DB_HOST="$2"; shift 2 ;;
    --db-port) DB_PORT="$2"; shift 2 ;;
    --db-user) DB_USER="$2"; shift 2 ;;
    --db-password) DB_PASSWORD="$2"; shift 2 ;;
    --db-name) DB_NAME="$2"; shift 2 ;;
    -h|--help) usage ;;
    *) echo -e "${RED}Unknown argument: $1${NC}"; usage ;;
  esac
done

if [ -z "$E2E_USERNAME" ] || [ -z "$E2E_PASSWORD" ]; then
  echo -e "${RED}--username and --password are required.${NC}"
  usage
fi

if [ "$EUID" -ne 0 ]; then
  echo -e "${RED}Run this as root (sudo).${NC}"
  exit 1
fi

USER_HOME=$(eval echo "~${USER_NAME}")
ENV_DIR="${USER_HOME}/runner-etail-src"
ENV_FILE="${ENV_DIR}/.env"

mkdir -p "$ENV_DIR"
cat > "$ENV_FILE" <<EOF
BASE_URL=${BASE_URL}
E2E_USERNAME=${E2E_USERNAME}
E2E_PASSWORD=${E2E_PASSWORD}
HEADLESS=${HEADLESS}
BROWSER=${BROWSER}
DB_HOST=${DB_HOST}
DB_PORT=${DB_PORT}
DB_USER=${DB_USER}
DB_PASSWORD=${DB_PASSWORD}
DB_NAME=${DB_NAME}
SCREENSHOT_ON_FAILURE=true
VIDEO_ON_FAILURE=true
TRACE_ON_FAILURE=true
EOF

chown "$USER_NAME:$USER_NAME" "$ENV_FILE"
chmod 600 "$ENV_FILE"

echo -e "${GREEN}✓ Wrote ${ENV_FILE}${NC}"
echo "  BASE_URL=${BASE_URL:-<blank, auto-picked>}"
echo "  E2E_USERNAME=${E2E_USERNAME}"
echo "  E2E_PASSWORD=****** (hidden)"
echo "  DB_HOST=${DB_HOST}  DB_PORT=${DB_PORT}  DB_USER=${DB_USER}  DB_NAME=${DB_NAME}"
