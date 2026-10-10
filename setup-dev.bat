@echo off
:: ============================================
:: Developer Setup Script — eTail v3
:: ============================================
:: Run this ONCE after cloning the repo.
:: Sets up git hooks, verifies environment.
:: ============================================

echo.
echo  ============================================
echo   eTail v3 Developer Setup
echo  ============================================
echo.

:: ── 1. Configure git hooks ──────────────────
echo  [1/3] Setting up git hooks...
git config core.hooksPath .githooks
if %ERRORLEVEL% EQU 0 (
    echo        OK — Git hooks loaded from .githooks/
) else (
    echo        FAIL — Could not configure git hooks
)

:: ── 2. Verify PHP ───────────────────────────
echo  [2/3] Checking PHP...
where php >nul 2>&1
if %ERRORLEVEL% EQU 0 (
    for /f "tokens=*" %%i in ('php -r "echo PHP_VERSION;"') do set PHP_VER=%%i
    echo        OK — PHP %PHP_VER%
) else (
    echo        WARNING — PHP not found in PATH
)

:: ── 3. Verify Python ────────────────────────
echo  [3/3] Checking Python...
where python >nul 2>&1
if %ERRORLEVEL% EQU 0 (
    for /f "tokens=*" %%i in ('python --version 2^>^&1') do set PY_VER=%%i
    echo        OK — %PY_VER%
) else (
    echo        WARNING — Python not found in PATH (LCA tools won't work)
)

echo.
echo  ============================================
echo   Setup Complete!
echo  ============================================
echo.
echo   What's now active:
echo     - Pre-commit  : PHP/Python syntax checks on every commit
echo     - Commit-msg  : Enforces format: type(module): description
echo     - Pre-push    : Blocks direct push to PRODUCTION/QA/support
echo     - Post-merge  : Auto LCA reindex after pull
echo.
echo   Commit message format:
echo     fix(billing): correct discount calculation
echo     feat(reports): add section-wise stock filter
echo     refactor(estimation): extract packet lookup
echo.
echo   Allowed types: fix, feat, refactor, style, perf, docs, chore, test
echo.
echo   To bypass hooks (emergency only):
echo     git commit --no-verify
echo     git push --no-verify
echo.
pause
