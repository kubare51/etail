# Contributing to eTail v3

## First-Time Setup

After cloning or pulling this repo for the first time, run:

```bash
setup-dev.bat
```

This configures git hooks that enforce code quality standards.

## Git Hooks (What They Do)

| Hook | When | What It Checks |
|---|---|---|
| **pre-commit** | Before every commit | PHP syntax errors, Python syntax errors |
| **commit-msg** | After writing commit message | Message format: `type(module): description` |
| **pre-push** | Before pushing | Blocks direct push to PRODUCTION, QA, support |
| **post-merge** | After `git pull` | Auto-reindex LCA if PHP/JS files changed |

### Hook Configuration

Each hook can be enabled or disabled in `.githooks/config`:

```ini
# .githooks/config
PRE_COMMIT=off      # Change to 'on' to enable
COMMIT_MSG=off      # Change to 'on' to enable
PRE_PUSH=off        # Change to 'on' to enable
POST_MERGE=on       # Non-blocking, on by default
```

**Current status:** All enforcement hooks start **OFF**. TL enables them per the rollout plan:

| Phase | Hook | When to Enable |
|---|---|---|
| Phase 1 | `PRE_COMMIT=on` | After team is informed about syntax checks |
| Phase 2 | `COMMIT_MSG=on` | After team adopts commit message format |
| Phase 3 | `PRE_PUSH=on` | After team is using PR workflow |

Only TL should change `.githooks/config`. Changes go through a PR like any other code.

### Commit Message Format

Every commit message must follow this format:

```
<type>(<module>): <description>
```

**Types:** `fix`, `feat`, `refactor`, `style`, `perf`, `docs`, `chore`, `test`

**Examples:**
```
fix(billing): correct discount calculation for tagged items
feat(reports): add section-wise stock filter by tag type
refactor(estimation): extract packet lookup into service
docs(lca): update README with new tools
```

### Branch Rules

| Branch | Push Allowed? | How to Update |
|---|---|---|
| `PRODUCTION` | ❌ No | PR only |
| `support` | ❌ No | PR only |
| `QA` | ❌ No | PR only |
| `Retail_*` | ❌ No | PR only |
| `feature/*` | ✅ Yes | Push freely |
| `bugfix/*` | ✅ Yes | Push freely |
| `hotfix/*` | ✅ Yes | Push freely |

### Promotion Order

Code must be promoted through environments in this order:

```
feature/xxx → Retail_1.1.1.0001 (Dev) → QA → support → PRODUCTION
```

Never skip a level. Exception: `hotfix/*` branches can PR directly to PRODUCTION.

## Bypassing Hooks (Emergency Only)

```bash
git commit --no-verify    # Skip pre-commit + commit-msg
git push --no-verify      # Skip pre-push
```

Use sparingly. Your TL will ask why.

## LCA Tools

Static analysis tools are in `logimax-devtools/backend/lca_core/`. Run:

```bash
cd logimax-devtools/backend/lca_core
python lca_cli.py --list     # List all tools
python test_lca.py -v         # Run tests
```

See `logimax-devtools/backend/lca_core/README.md` for full documentation.

## APM Dashboard

Performance monitoring dashboard at:

```
{base_url}/admin_apm/dashboard
```

Tracks endpoint timing, slow queries, N+1 patterns, and error rates.
