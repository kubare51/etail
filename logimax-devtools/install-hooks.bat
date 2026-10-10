@echo off
REM ══════════════════════════════════════════════════════════════
REM Install Git Hooks — Logimax DevTools
REM ══════════════════════════════════════════════════════════════
REM Copies canonical hooks from logimax-devtools/hooks/ to .git/hooks/
REM Run from repo root: logimax-devtools\install-hooks.bat
REM ══════════════════════════════════════════════════════════════

echo.
echo   Installing Git hooks...
echo.

set "REPO_ROOT=%~dp0.."
set "HOOK_SRC=%~dp0hooks"
set "HOOK_DST=%REPO_ROOT%\.git\hooks"

if not exist "%HOOK_DST%" (
    echo   Error: .git/hooks not found. Are you in the repo root?
    exit /b 1
)

if not exist "%HOOK_SRC%" (
    echo   Error: hooks source directory not found at %HOOK_SRC%
    exit /b 1
)

REM Copy hooks
for %%f in ("%HOOK_SRC%\*") do (
    copy /Y "%%f" "%HOOK_DST%\" >nul 2>&1
    echo   Installed: %%~nxf
)

echo.
echo   All hooks installed successfully!
echo.
echo   Active hooks:
echo     pre-commit    — 15 quality checks (security, debug, code quality)
echo     pre-commit.ps1 — PowerShell version (auto-selected on Windows)
echo     commit-msg    — Enforces type(module): description format
echo.
echo   Bypass any hook: git commit --no-verify
echo.
