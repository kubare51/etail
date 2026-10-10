#!/usr/bin/env python3
"""CI3 Smoke Test Runner — automated verification for CodeIgniter 3 projects.

Three test tiers:
  Tier 1: Static Analysis (no server needed)
    - PHP syntax validation (php -l)
    - Required file existence checks
    - Controller↔Model↔View consistency
    - CI3 naming convention enforcement
    
  Tier 2: Endpoint Health (requires running server)
    - AJAX endpoint availability
    - Response format validation (JSON with status/msg)
    - HTTP status code checks
    
  Tier 3: Data Integrity (requires database)
    - Table existence verification
    - Required column checks
    - Foreign key consistency

Usage:
    python smoke_tests.py --tier 1 --project-root /path/to/project
    python smoke_tests.py --tier 2 --base-url http://localhost/admin
    python smoke_tests.py --tier 1 --changed-only --diff-base origin/Retail_1.1.1.0001
    python smoke_tests.py --all --project-root /path/to/project --base-url http://localhost/admin
"""

import argparse
import json
import os
import subprocess
import sys
import time
import urllib.request
import urllib.error
from collections import defaultdict
from dataclasses import dataclass, field
from pathlib import Path


# ── Data Classes ───────────────────────────────────────────────────────

@dataclass
class TestResult:
    name: str
    tier: int
    passed: bool
    message: str
    file: str = ""
    line: int = 0
    duration_ms: float = 0


@dataclass
class TestReport:
    results: list[TestResult] = field(default_factory=list)
    start_time: float = 0
    end_time: float = 0
    
    @property
    def total(self) -> int:
        return len(self.results)
    
    @property
    def passed(self) -> int:
        return sum(1 for r in self.results if r.passed)
    
    @property
    def failed(self) -> int:
        return sum(1 for r in self.results if not r.passed)
    
    @property
    def duration_s(self) -> float:
        return self.end_time - self.start_time
    
    def add(self, result: TestResult):
        self.results.append(result)


# ── Tier 1: Static Analysis ───────────────────────────────────────────

def run_tier1(project_root: Path, changed_files: list[Path] | None = None,
              php_path: str = "php") -> TestReport:
    """Run static analysis tests (no server needed)."""
    report = TestReport(start_time=time.time())
    
    controllers_dir = project_root / "admin" / "application" / "controllers"
    models_dir = project_root / "admin" / "application" / "models"
    views_dir = project_root / "admin" / "application" / "views"
    
    # ── 1.1 PHP Syntax Validation ──
    if changed_files:
        php_files = [f for f in changed_files if f.suffix == ".php" and f.exists()]
    else:
        php_files = list(controllers_dir.rglob("*.php")) + list(models_dir.rglob("*.php"))
    
    for php_file in php_files:
        t = time.time()
        try:
            result = subprocess.run(
                [php_path, "-l", str(php_file)],
                capture_output=True, text=True, timeout=10,
            )
            duration = (time.time() - t) * 1000
            
            if result.returncode == 0:
                report.add(TestResult(
                    name=f"PHP Syntax: {php_file.name}",
                    tier=1, passed=True,
                    message="No syntax errors",
                    file=str(php_file),
                    duration_ms=duration,
                ))
            else:
                error_msg = result.stdout.strip() or result.stderr.strip()
                report.add(TestResult(
                    name=f"PHP Syntax: {php_file.name}",
                    tier=1, passed=False,
                    message=f"Syntax error: {error_msg}",
                    file=str(php_file),
                    duration_ms=duration,
                ))
        except FileNotFoundError:
            report.add(TestResult(
                name=f"PHP Syntax: {php_file.name}",
                tier=1, passed=False,
                message=f"PHP binary not found at '{php_path}'",
                file=str(php_file),
            ))
            break  # No point checking more files
        except Exception as e:
            report.add(TestResult(
                name=f"PHP Syntax: {php_file.name}",
                tier=1, passed=False,
                message=str(e),
                file=str(php_file),
            ))
    
    # ── 1.2 Controller Naming Convention ──
    if controllers_dir.exists():
        for ctrl_file in controllers_dir.glob("*.php"):
            if ctrl_file.name.startswith("_") or ctrl_file.name == "index.html":
                continue
            
            # CI3 rule: filename must match class name
            expected_class = ctrl_file.stem
            try:
                content = ctrl_file.read_text(encoding="utf-8", errors="ignore")
                
                # Check for class declaration
                import re
                class_match = re.search(
                    r'class\s+(\w+)\s+extends\s+', content
                )
                
                if class_match:
                    actual_class = class_match.group(1)
                    # CI3 allows case-insensitive match (ucfirst convention)
                    if actual_class.lower() == expected_class.lower():
                        report.add(TestResult(
                            name=f"CI3 Naming: {ctrl_file.name}",
                            tier=1, passed=True,
                            message=f"Class '{actual_class}' matches filename",
                            file=str(ctrl_file),
                        ))
                    else:
                        report.add(TestResult(
                            name=f"CI3 Naming: {ctrl_file.name}",
                            tier=1, passed=False,
                            message=f"Class '{actual_class}' doesn't match filename '{expected_class}'",
                            file=str(ctrl_file),
                        ))
                else:
                    report.add(TestResult(
                        name=f"CI3 Naming: {ctrl_file.name}",
                        tier=1, passed=False,
                        message="No class declaration found",
                        file=str(ctrl_file),
                    ))
            except Exception as e:
                report.add(TestResult(
                    name=f"CI3 Naming: {ctrl_file.name}",
                    tier=1, passed=False,
                    message=str(e),
                    file=str(ctrl_file),
                ))
    
    # ── 1.3 View File References ──
    # Check that loaded views actually exist
    if controllers_dir.exists() and views_dir.exists():
        import re
        view_pattern = re.compile(
            r"""\$this\s*->\s*load\s*->\s*view\s*\(\s*['"]([^'"]+)['"]""",
            re.MULTILINE,
        )
        
        check_files = changed_files if changed_files else list(controllers_dir.rglob("*.php"))
        php_check_files = [f for f in check_files if f.suffix == ".php"]
        
        for ctrl_file in php_check_files[:50]:  # Limit to prevent slow runs
            try:
                content = ctrl_file.read_text(encoding="utf-8", errors="ignore")
                views_loaded = view_pattern.findall(content)
                
                for view_name in set(views_loaded):
                    view_path = views_dir / f"{view_name}.php"
                    if not view_path.exists():
                        # Try without .php extension (CI3 auto-appends)
                        if not (views_dir / view_name).exists():
                            report.add(TestResult(
                                name=f"View Exists: {view_name}",
                                tier=1, passed=False,
                                message=f"View '{view_name}' loaded by {ctrl_file.name} but file not found",
                                file=str(ctrl_file),
                            ))
            except Exception:
                pass
    
    # ── 1.4 Model Load References ──
    # Check that loaded models exist as files
    if controllers_dir.exists() and models_dir.exists():
        import re
        model_pattern = re.compile(
            r"""\$this\s*->\s*load\s*->\s*model\s*\(\s*['"]([^'"]+)['"]""",
            re.MULTILINE,
        )
        
        check_files = changed_files if changed_files else list(controllers_dir.rglob("*.php"))
        php_check_files = [f for f in check_files if f.suffix == ".php"]
        
        for ctrl_file in php_check_files[:50]:
            try:
                content = ctrl_file.read_text(encoding="utf-8", errors="ignore")
                models_loaded = model_pattern.findall(content)
                
                for model_name in set(models_loaded):
                    # CI3 model path: can be 'model_name' or 'dir/model_name'
                    model_path = models_dir / f"{model_name}.php"
                    if not model_path.exists():
                        # Try lowercase
                        model_path_lower = models_dir / f"{model_name.lower()}.php"
                        if not model_path_lower.exists():
                            report.add(TestResult(
                                name=f"Model Exists: {model_name}",
                                tier=1, passed=False,
                                message=f"Model '{model_name}' loaded by {ctrl_file.name} but file not found",
                                file=str(ctrl_file),
                            ))
            except Exception:
                pass
    
    # ── 1.5 Duplicate Function Detection (in changed files) ──
    if changed_files:
        import re
        for fpath in changed_files:
            if fpath.suffix != ".php" or not fpath.exists():
                continue
            try:
                content = fpath.read_text(encoding="utf-8", errors="ignore")
                func_pattern = re.compile(
                    r'(?:public|private|protected)?\s*function\s+(\w+)\s*\(',
                    re.MULTILINE,
                )
                functions = func_pattern.findall(content)
                
                seen = {}
                for func_name in functions:
                    if func_name in seen:
                        report.add(TestResult(
                            name=f"Duplicate Function: {func_name}",
                            tier=1, passed=False,
                            message=f"Function '{func_name}' defined multiple times in {fpath.name}",
                            file=str(fpath),
                        ))
                    seen[func_name] = True
            except Exception:
                pass
    
    report.end_time = time.time()
    return report


# ── Tier 2: Endpoint Health Checks ────────────────────────────────────

def run_tier2(base_url: str, endpoints: list[dict] | None = None,
              timeout: int = 10, session_cookie: str = "") -> TestReport:
    """Run endpoint health checks (requires running server).
    
    Args:
        base_url: Base URL (e.g., http://localhost/admin)
        endpoints: List of {"path": "/controller/method", "method": "GET/POST"}
        timeout: Request timeout in seconds
        session_cookie: Optional session cookie for auth
    """
    report = TestReport(start_time=time.time())
    
    if endpoints is None:
        # Default CI3 health check endpoints
        endpoints = [
            {"path": "/", "method": "GET", "expect": "html"},
            {"path": "/login", "method": "GET", "expect": "html"},
        ]
    
    for ep in endpoints:
        path = ep.get("path", "/")
        method = ep.get("method", "GET")
        expect = ep.get("expect", "json")
        url = f"{base_url.rstrip('/')}{path}"
        
        t = time.time()
        try:
            req = urllib.request.Request(url, method=method)
            if session_cookie:
                req.add_header("Cookie", session_cookie)
            req.add_header("X-Requested-With", "XMLHttpRequest")  # Mark as AJAX
            
            response = urllib.request.urlopen(req, timeout=timeout)
            duration = (time.time() - t) * 1000
            status = response.status
            body = response.read().decode("utf-8", errors="ignore")
            
            # Check response
            if status == 200:
                if expect == "json":
                    try:
                        data = json.loads(body)
                        # CI3 standard: {"status": true/false, "msg": "..."}
                        if isinstance(data, dict) and "status" in data:
                            report.add(TestResult(
                                name=f"Endpoint: {method} {path}",
                                tier=2, passed=True,
                                message=f"HTTP 200, valid JSON (status={data.get('status')})",
                                duration_ms=duration,
                            ))
                        else:
                            report.add(TestResult(
                                name=f"Endpoint: {method} {path}",
                                tier=2, passed=True,
                                message=f"HTTP 200, JSON response (non-standard format)",
                                duration_ms=duration,
                            ))
                    except json.JSONDecodeError:
                        report.add(TestResult(
                            name=f"Endpoint: {method} {path}",
                            tier=2, passed=False,
                            message=f"HTTP 200 but response is not valid JSON",
                            duration_ms=duration,
                        ))
                else:
                    report.add(TestResult(
                        name=f"Endpoint: {method} {path}",
                        tier=2, passed=True,
                        message=f"HTTP {status}, {len(body)} bytes",
                        duration_ms=duration,
                    ))
            else:
                report.add(TestResult(
                    name=f"Endpoint: {method} {path}",
                    tier=2, passed=False,
                    message=f"HTTP {status} (expected 200)",
                    duration_ms=duration,
                ))
                
        except urllib.error.HTTPError as e:
            duration = (time.time() - t) * 1000
            report.add(TestResult(
                name=f"Endpoint: {method} {path}",
                tier=2, passed=False,
                message=f"HTTP {e.code}: {e.reason}",
                duration_ms=duration,
            ))
        except urllib.error.URLError as e:
            duration = (time.time() - t) * 1000
            report.add(TestResult(
                name=f"Endpoint: {method} {path}",
                tier=2, passed=False,
                message=f"Connection failed: {e.reason}",
                duration_ms=duration,
            ))
        except Exception as e:
            duration = (time.time() - t) * 1000
            report.add(TestResult(
                name=f"Endpoint: {method} {path}",
                tier=2, passed=False,
                message=str(e),
                duration_ms=duration,
            ))
    
    report.end_time = time.time()
    return report


# ── Tier 3: Data Integrity ────────────────────────────────────────────

def run_tier3(db_config: dict) -> TestReport:
    """Run database integrity checks.
    
    Args:
        db_config: {"host", "user", "password", "database", "port"}
    """
    report = TestReport(start_time=time.time())
    
    try:
        import mysql.connector
    except ImportError:
        report.add(TestResult(
            name="DB Connection",
            tier=3, passed=False,
            message="mysql-connector-python not installed",
        ))
        report.end_time = time.time()
        return report
    
    try:
        conn = mysql.connector.connect(
            host=db_config.get("host", "localhost"),
            user=db_config.get("user", "root"),
            password=db_config.get("password", ""),
            database=db_config.get("database", "retail_dev"),
            port=db_config.get("port", 3306),
            connect_timeout=5,
        )
        cursor = conn.cursor()
        
        report.add(TestResult(
            name="DB Connection",
            tier=3, passed=True,
            message=f"Connected to {db_config.get('database', 'retail_dev')}",
        ))
        
        # ── 3.1 Critical Table Existence ──
        critical_tables = [
            "ret_estimation", "ret_estimation_items",
            "ret_billing", "ret_billing_items",
            "ret_payment", "ret_payment_items",
            "customer", "employee", "company_profile",
            "ret_taging", "ret_stock",
            "ret_purchase_order", "ret_purchase_order_items",
        ]
        
        cursor.execute("SHOW TABLES")
        existing_tables = {row[0].lower() for row in cursor.fetchall()}
        
        for table in critical_tables:
            if table.lower() in existing_tables:
                report.add(TestResult(
                    name=f"Table Exists: {table}",
                    tier=3, passed=True,
                    message="Table exists",
                ))
            else:
                report.add(TestResult(
                    name=f"Table Exists: {table}",
                    tier=3, passed=False,
                    message=f"Critical table '{table}' missing from database",
                ))
        
        # ── 3.2 Required Columns ──
        column_checks = {
            "ret_estimation": ["est_id", "est_no", "customer_id", "status"],
            "ret_billing": ["bill_id", "bill_no", "customer_id", "status"],
            "customer": ["customer_id", "name"],
        }
        
        for table, required_cols in column_checks.items():
            if table.lower() not in existing_tables:
                continue
            try:
                cursor.execute(f"DESCRIBE `{table}`")
                actual_cols = {row[0].lower() for row in cursor.fetchall()}
                
                for col in required_cols:
                    if col.lower() in actual_cols:
                        report.add(TestResult(
                            name=f"Column: {table}.{col}",
                            tier=3, passed=True,
                            message="Column exists",
                        ))
                    else:
                        report.add(TestResult(
                            name=f"Column: {table}.{col}",
                            tier=3, passed=False,
                            message=f"Required column '{col}' missing from '{table}'",
                        ))
            except Exception as e:
                report.add(TestResult(
                    name=f"Column check: {table}",
                    tier=3, passed=False,
                    message=str(e),
                ))
        
        # ── 3.3 Orphaned Records ──
        orphan_checks = [
            {
                "name": "Billing→Estimation",
                "query": """
                    SELECT COUNT(*) FROM ret_billing b 
                    LEFT JOIN ret_estimation e ON b.est_id = e.est_id 
                    WHERE b.est_id IS NOT NULL AND b.est_id != 0 AND e.est_id IS NULL
                """,
            },
        ]
        
        for check in orphan_checks:
            try:
                cursor.execute(check["query"])
                count = cursor.fetchone()[0]
                if count == 0:
                    report.add(TestResult(
                        name=f"Orphan Check: {check['name']}",
                        tier=3, passed=True,
                        message="No orphaned records",
                    ))
                else:
                    report.add(TestResult(
                        name=f"Orphan Check: {check['name']}",
                        tier=3, passed=False,
                        message=f"{count} orphaned records found",
                    ))
            except Exception as e:
                report.add(TestResult(
                    name=f"Orphan Check: {check['name']}",
                    tier=3, passed=False,
                    message=str(e),
                ))
        
        cursor.close()
        conn.close()
        
    except Exception as e:
        report.add(TestResult(
            name="DB Connection",
            tier=3, passed=False,
            message=f"Connection failed: {e}",
        ))
    
    report.end_time = time.time()
    return report


# ── Report Formatting ──────────────────────────────────────────────────

def format_report_markdown(report: TestReport, tier_name: str = "") -> str:
    """Format test report as markdown."""
    output = f"## 🧪 Smoke Test Results"
    if tier_name:
        output += f" — {tier_name}"
    output += "\n\n"
    
    # Summary
    emoji = "✅" if report.failed == 0 else "❌"
    output += f"{emoji} **{report.passed}/{report.total} passed** "
    output += f"({report.failed} failed) in {report.duration_s:.1f}s\n\n"
    
    # Failed tests first
    if report.failed > 0:
        output += "### ❌ Failed Tests\n\n"
        output += "| Test | Message |\n|---|---|\n"
        for r in report.results:
            if not r.passed:
                file_info = f" ({Path(r.file).name})" if r.file else ""
                output += f"| {r.name}{file_info} | {r.message} |\n"
        output += "\n"
    
    # Passed tests (collapsed)
    if report.passed > 0:
        output += "<details>\n"
        output += f"<summary>✅ Passed Tests ({report.passed})</summary>\n\n"
        output += "| Test | Time |\n|---|---|\n"
        for r in report.results:
            if r.passed:
                time_str = f"{r.duration_ms:.0f}ms" if r.duration_ms else "—"
                output += f"| {r.name} | {time_str} |\n"
        output += "\n</details>\n\n"
    
    return output


def format_report_json(report: TestReport) -> dict:
    """Format test report as JSON."""
    return {
        "total": report.total,
        "passed": report.passed,
        "failed": report.failed,
        "duration_seconds": round(report.duration_s, 2),
        "results": [
            {
                "name": r.name,
                "tier": r.tier,
                "passed": r.passed,
                "message": r.message,
                "file": r.file,
                "duration_ms": round(r.duration_ms, 1),
            }
            for r in report.results
        ],
    }


# ── Endpoint Config Loader ────────────────────────────────────────────

def load_endpoint_config(config_path: Path) -> list[dict]:
    """Load endpoint test configuration from JSON file."""
    if not config_path.exists():
        return []
    
    try:
        data = json.loads(config_path.read_text())
        return data.get("endpoints", [])
    except Exception:
        return []


# ── Main ───────────────────────────────────────────────────────────────

def main():
    parser = argparse.ArgumentParser(description="CI3 Smoke Test Runner")
    parser.add_argument("--tier", type=int, choices=[1, 2, 3],
                       help="Run specific tier (1=static, 2=endpoints, 3=database)")
    parser.add_argument("--all", action="store_true", help="Run all tiers")
    parser.add_argument("--project-root", help="Project root directory")
    parser.add_argument("--base-url", help="Base URL for endpoint tests")
    parser.add_argument("--php-path", default="php", help="Path to PHP binary")
    parser.add_argument("--changed-only", action="store_true",
                       help="Only test changed files")
    parser.add_argument("--diff-base", help="Git ref for changed file detection")
    parser.add_argument("--endpoint-config", help="JSON file with endpoint test config")
    parser.add_argument("--db-host", default="localhost")
    parser.add_argument("--db-user", default="root")
    parser.add_argument("--db-password", default="")
    parser.add_argument("--db-name", default="retail_dev")
    parser.add_argument("--db-port", type=int, default=3306)
    parser.add_argument("--output", help="Output markdown file")
    parser.add_argument("--json-output", help="Output JSON file")
    parser.add_argument("--fail-on-error", action="store_true",
                       help="Exit with code 1 if any test fails")
    
    args = parser.parse_args()
    
    reports: list[tuple[str, TestReport]] = []
    
    # Determine changed files
    changed_files = None
    if args.changed_only and args.diff_base and args.project_root:
        project_root = Path(args.project_root).resolve()
        try:
            result = subprocess.run(
                ["git", "diff", "--name-only", "--diff-filter=ACMR", args.diff_base],
                cwd=str(project_root), capture_output=True, text=True, timeout=10,
            )
            if result.returncode == 0:
                changed_files = [
                    project_root / f.strip()
                    for f in result.stdout.strip().split("\n")
                    if f.strip() and f.strip().endswith(".php")
                ]
        except Exception:
            pass
    
    # Tier 1: Static Analysis
    if args.tier == 1 or args.all:
        if not args.project_root:
            print("Error: --project-root required for Tier 1", file=sys.stderr)
            sys.exit(1)
        
        project_root = Path(args.project_root).resolve()
        print(f"🧪 Tier 1: Static Analysis ({project_root.name})...", file=sys.stderr)
        
        report = run_tier1(project_root, changed_files, args.php_path)
        reports.append(("Tier 1: Static Analysis", report))
        
        print(f"   {report.passed}/{report.total} passed ({report.failed} failed) "
              f"in {report.duration_s:.1f}s", file=sys.stderr)
    
    # Tier 2: Endpoint Health
    if args.tier == 2 or args.all:
        if not args.base_url:
            print("Error: --base-url required for Tier 2", file=sys.stderr)
            if not args.all:
                sys.exit(1)
        else:
            print(f"🧪 Tier 2: Endpoint Health ({args.base_url})...", file=sys.stderr)
            
            endpoints = None
            if args.endpoint_config:
                endpoints = load_endpoint_config(Path(args.endpoint_config))
            
            report = run_tier2(args.base_url, endpoints)
            reports.append(("Tier 2: Endpoint Health", report))
            
            print(f"   {report.passed}/{report.total} passed ({report.failed} failed) "
                  f"in {report.duration_s:.1f}s", file=sys.stderr)
    
    # Tier 3: Data Integrity
    if args.tier == 3 or args.all:
        print(f"🧪 Tier 3: Data Integrity ({args.db_name})...", file=sys.stderr)
        
        report = run_tier3({
            "host": args.db_host,
            "user": args.db_user,
            "password": args.db_password,
            "database": args.db_name,
            "port": args.db_port,
        })
        reports.append(("Tier 3: Data Integrity", report))
        
        print(f"   {report.passed}/{report.total} passed ({report.failed} failed) "
              f"in {report.duration_s:.1f}s", file=sys.stderr)
    
    if not reports:
        print("Error: Specify --tier or --all", file=sys.stderr)
        sys.exit(1)
    
    # Combine output
    total_passed = sum(r.passed for _, r in reports)
    total_failed = sum(r.failed for _, r in reports)
    total_tests = sum(r.total for _, r in reports)
    
    markdown = ""
    for tier_name, report in reports:
        markdown += format_report_markdown(report, tier_name)
    
    # Summary
    emoji = "✅" if total_failed == 0 else "❌"
    summary = f"\n---\n{emoji} **Overall: {total_passed}/{total_tests} passed**\n"
    markdown += summary
    
    # Output
    if args.output:
        Path(args.output).write_text(markdown, encoding="utf-8")
        print(f"\nReport: {args.output}", file=sys.stderr)
    else:
        print(markdown)
    
    if args.json_output:
        json_data = {
            "total": total_tests,
            "passed": total_passed,
            "failed": total_failed,
            "tiers": [
                {"name": name, **format_report_json(report)}
                for name, report in reports
            ],
        }
        Path(args.json_output).write_text(json.dumps(json_data, indent=2))
    
    # Exit code
    if args.fail_on_error and total_failed > 0:
        sys.exit(1)


if __name__ == "__main__":
    main()
