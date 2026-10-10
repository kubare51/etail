# ══════════════════════════════════════════════════════════════
# Pre-commit Hook — eTail v3 / Logimax ERP (PowerShell)
# ══════════════════════════════════════════════════════════════
# Runs before every commit. Checks STAGED content only.
# Pure regex-based — no external dependencies, runs in ~1 second.
#
# BLOCKERS (8): syntax, raw superglobals, SQL injection, XSS in views,
#               destructive SQL, unsafe functions, merge conflicts, secrets
# WARNINGS (7): debug dumps, bare exit, SELECT *, console.log,
#               hardcoded URLs, N+1 patterns, AJAX without error handler
#
# Install:  logimax-devtools\install-hooks.bat
# Bypass:   git commit --no-verify
# ══════════════════════════════════════════════════════════════

Write-Host ""
Write-Host "  +==========================================+" -ForegroundColor DarkCyan
Write-Host "  |  Pre-commit Quality Gate                 |" -ForegroundColor Cyan
Write-Host "  +==========================================+" -ForegroundColor DarkCyan
Write-Host ""

$errors = 0
$warnings = 0
$errorDetails = @()
$warnDetails = @()

function Add-Error($check, $file, $fix) {
    $script:errors++
    $script:errorDetails += @{ Check=$check; File=$file; Fix=$fix }
}

function Add-Warning($check, $file, $fix) {
    $script:warnings++
    $script:warnDetails += @{ Check=$check; File=$file; Fix=$fix }
}

# Get staged files
$stagedPHP = @(git diff --cached --name-only --diff-filter=ACM | Where-Object { $_ -match '\.php$' })
$stagedJS  = @(git diff --cached --name-only --diff-filter=ACM | Where-Object { $_ -match '\.js$' -and $_ -notmatch 'node_modules|vendor' })
$allStaged = @(git diff --cached --name-only --diff-filter=ACM)

# ══════════════════════════════════════════════════════════════
# UNIVERSAL: Merge conflict markers (any file type)
# ══════════════════════════════════════════════════════════════
foreach ($file in $allStaged) {
    if (-not (Test-Path $file)) { continue }
    $diff = git diff --cached -- $file | Where-Object { $_ -match '^\+' -and $_ -notmatch '^\+\+\+' }
    $conflicts = $diff | Where-Object { $_ -match '(^[\+]?(<{7}|={7}|>{7}))' }
    if ($conflicts) {
        Add-Error "MERGE CONFLICT" (Split-Path $file -Leaf) "Resolve conflict markers before committing"
    }
}

# ══════════════════════════════════════════════════════════════
# PHP CHECKS
# ══════════════════════════════════════════════════════════════
if ($stagedPHP.Count -gt 0) {
    Write-Host "  PHP files: $($stagedPHP.Count)" -ForegroundColor Yellow

    foreach ($file in $stagedPHP) {
        if (-not (Test-Path $file)) { continue }

        $diff = git diff --cached -- $file | Where-Object { $_ -match '^\+' -and $_ -notmatch '^\+\+\+' }
        if (-not $diff) { continue }
        $contentStr = ($diff -join "`n")

        $basename = Split-Path $file -Leaf
        $isView = $file -match 'views/'

        # ── BLOCKER 1: PHP syntax ──
        $null = php -l $file 2>&1
        if ($LASTEXITCODE -ne 0) {
            Add-Error "SYNTAX" $basename "PHP parse error"
        }

        # ── BLOCKER 2: Raw superglobals ──
        $rawSuper = $diff | Where-Object { $_ -match '\$_(GET|POST|REQUEST|COOKIE)\s*\[' }
        if ($rawSuper) {
            Add-Error "SECURITY" $basename "Raw `$_GET/`$_POST - use `$this->input->post()"
        }

        # ── BLOCKER 3: SQL injection ──
        $sqlInj = $diff | Where-Object { $_ -match '\$this->db->query\s*\(\s*["''].*\$' }
        if ($sqlInj) {
            Add-Error "SQL INJECTION" $basename "Variable in raw query - use query bindings"
        }

        # ── BLOCKER 4: XSS in views ──
        if ($isView) {
            $xss = $diff | Where-Object { $_ -match 'echo\s+\$(?!this)' -and $_ -notmatch 'htmlspecialchars|json_encode' }
            if ($xss) {
                Add-Error "XSS RISK" $basename "Unescaped echo in view - use htmlspecialchars()"
            }
        }

        # ── BLOCKER 5: Destructive SQL without WHERE ──
        $truncate = $diff | Where-Object { $_ -match 'TRUNCATE\s+(TABLE\s+)?\w' }
        if ($truncate) {
            Add-Error "DESTRUCTIVE SQL" $basename "TRUNCATE detected - requires explicit approval"
        }
        $deleteNoWhere = $diff | Where-Object { $_ -match 'DELETE\s+FROM' -and $_ -notmatch 'WHERE' }
        if ($deleteNoWhere) {
            Add-Error "DESTRUCTIVE SQL" $basename "DELETE without WHERE - will wipe entire table"
        }

        # ── BLOCKER 6: Unsafe functions ──
        $unsafe = $diff | Where-Object { $_ -match '\b(eval|exec|system|passthru|shell_exec|popen)\s*\(' }
        if ($unsafe) {
            $func = ($unsafe[0] | Select-String -Pattern '\b(eval|exec|system|passthru|shell_exec|popen)\s*\(').Matches[0].Value
            Add-Error "UNSAFE FUNCTION" $basename "$func - command injection risk"
        }

        # ── BLOCKER 8: Hardcoded credentials ──
        if ($file -notmatch '(config|\.env|example|sample|test|migration)') {
            $cred = $diff | Where-Object { $_ -match '(?i)(password|secret|api_key|token|private_key)\s*[=:]\s*["''][^"'']{8,}' }
            if ($cred) {
                Add-Error "HARDCODED SECRET" $basename "Possible credential in source - use config/env"
            }
        }

        # ── WARNING 9: Debug dumps ──
        $debug = $diff | Where-Object { $_ -match '\b(var_dump|print_r|dd)\s*\(' }
        if ($debug) {
            Add-Warning "DEBUG CODE" $basename "Remove var_dump()/print_r()"
        }

        # ── WARNING 10: Bare die/exit ──
        $dieExit = $diff | Where-Object { $_ -match '\b(die|exit)\s*\(' -and $_ -notmatch 'log_message' }
        if ($dieExit) {
            Add-Warning "BARE EXIT" $basename "Add log_message() before die()/exit()"
        }

        # ── WARNING 11: SELECT * ──
        $selectStar = $diff | Where-Object { $_ -match '(?i)select\s+\*\s+from' }
        if ($selectStar) {
            Add-Warning "SELECT *" $basename "Specify exact columns"
        }

        # ── WARNING 14: N+1 query pattern ──
        $hasLoop = $diff | Where-Object { $_ -match '(foreach|for|while)\s*\(' }
        $hasQuery = $diff | Where-Object { $_ -match '\$this->db->(get|query|where|select|insert|update|delete)' }
        if ($hasLoop -and $hasQuery) {
            Add-Warning "N+1 PATTERN" $basename "Query inside loop - use batch query with where_in()"
        }
    }
    Write-Host ""
}

# ══════════════════════════════════════════════════════════════
# JAVASCRIPT CHECKS
# ══════════════════════════════════════════════════════════════
if ($stagedJS.Count -gt 0) {
    Write-Host "  JS files: $($stagedJS.Count)" -ForegroundColor Yellow

    foreach ($file in $stagedJS) {
        if (-not (Test-Path $file)) { continue }

        $diff = git diff --cached -- $file | Where-Object { $_ -match '^\+' -and $_ -notmatch '^\+\+\+' }
        if (-not $diff) { continue }

        $basename = Split-Path $file -Leaf

        # ── WARNING 12: console.log ──
        $console = $diff | Where-Object { $_ -match 'console\.(log|debug|info)\(' }
        if ($console) {
            Add-Warning "CONSOLE.LOG" $basename "Remove debug logging"
        }

        # ── WARNING 13: Hardcoded AJAX URLs ──
        $hardcodedUrl = $diff | Where-Object { $_ -match "url:\s*['\"]/(admin|api)/" }
        if ($hardcodedUrl) {
            Add-Warning "HARDCODED URL" $basename "Use base_url variable"
        }

        # ── WARNING 15: $.ajax without error handler ──
        $ajaxCalls = @($diff | Where-Object { $_ -match '\$\.ajax\s*\(' })
        $ajaxErrors = @($diff | Where-Object { $_ -match 'error\s*:' })
        if ($ajaxCalls.Count -gt 0 -and $ajaxErrors.Count -eq 0) {
            Add-Warning "AJAX NO ERROR" $basename "Add error: handler to `$.ajax() calls"
        }
    }
    Write-Host ""
}

# ══════════════════════════════════════════════════════════════
# PYTHON CHECKS (syntax only)
# ══════════════════════════════════════════════════════════════
$stagedPY = @(git diff --cached --name-only --diff-filter=ACM | Where-Object { $_ -match '\.py$' })
if ($stagedPY.Count -gt 0) {
    Write-Host "  Python files: $($stagedPY.Count)" -ForegroundColor Yellow
    foreach ($file in $stagedPY) {
        if (Test-Path $file) {
            python -m py_compile $file 2>&1 | Out-Null
            if ($LASTEXITCODE -ne 0) {
                Add-Error "SYNTAX" (Split-Path $file -Leaf) "Python parse error"
            }
        }
    }
    Write-Host ""
}

# ══════════════════════════════════════════════════════════════
# RESULT
# ══════════════════════════════════════════════════════════════
Write-Host "  +==========================================+" -ForegroundColor DarkCyan

if ($errors -gt 0) {
    Write-Host "  |  BLOCKED: $errors error(s), $warnings warning(s)" -ForegroundColor Red
    Write-Host "  +==========================================+" -ForegroundColor DarkCyan
    Write-Host ""
    foreach ($e in $errorDetails) {
        Write-Host "  $($e.Check): $($e.File)" -ForegroundColor Red
        Write-Host "     -> $($e.Fix)" -ForegroundColor Gray
    }
    if ($warnings -gt 0) {
        Write-Host ""
        foreach ($w in $warnDetails) {
            Write-Host "  $($w.Check): $($w.File)" -ForegroundColor Yellow
            Write-Host "     -> $($w.Fix)" -ForegroundColor Gray
        }
    }
    Write-Host ""
    Write-Host "  Fix errors before committing." -ForegroundColor Gray
    Write-Host "  Bypass (not recommended): git commit --no-verify" -ForegroundColor DarkGray
    Write-Host ""
    exit 1
} elseif ($warnings -gt 0) {
    Write-Host "  |  PASSED: $warnings warning(s)" -ForegroundColor Yellow
    Write-Host "  +==========================================+" -ForegroundColor DarkCyan
    Write-Host ""
    foreach ($w in $warnDetails) {
        Write-Host "  $($w.Check): $($w.File)" -ForegroundColor Yellow
        Write-Host "     -> $($w.Fix)" -ForegroundColor Gray
    }
    Write-Host ""
    exit 0
} else {
    Write-Host "  |  All checks passed!" -ForegroundColor Green
    Write-Host "  +==========================================+" -ForegroundColor DarkCyan
    Write-Host ""
    exit 0
}
