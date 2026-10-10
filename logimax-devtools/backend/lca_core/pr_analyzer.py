#!/usr/bin/env python3
"""PR Intelligence Analyzer — Lightweight code analysis for CI/CD pipelines.

Analyzes changed files in a PR without needing a full index. Uses tree-sitter
for per-file analysis (security scan, metrics, smell detection) and produces
a markdown report suitable for posting as a PR comment.

Usage:
    python pr_analyzer.py --files file1.php file2.js --project-root /path/to/project
    python pr_analyzer.py --diff-base origin/Retail_1.1.1.0001 --project-root /path/to/project
"""

import argparse
import json
import os
import subprocess
import sys
import time
from collections import Counter, defaultdict
from pathlib import Path

# Add LCA to path
sys.path.insert(0, str(Path(__file__).parent))

from lca.analysis.security import scan_file
from lca.parsers.php import PHPParser
from lca.parsers.javascript import JavaScriptParser


# ── Configuration ──────────────────────────────────────────────────────

# Thresholds for flagging issues
THRESHOLDS = {
    "cyclomatic_complexity": 25,     # SonarQube "very complex"
    "cognitive_complexity": 30,
    "lines_of_code": 200,
    "max_nesting_depth": 6,
    "param_count": 8,
}

# Security severity that blocks merge
BLOCKING_SEVERITY = "CRITICAL"


# ── Analysis Functions ─────────────────────────────────────────────────

def get_changed_files(diff_base: str, project_root: Path) -> list[Path]:
    """Get list of changed files from git diff."""
    try:
        result = subprocess.run(
            ["git", "diff", "--name-only", "--diff-filter=ACMR", diff_base],
            cwd=str(project_root), capture_output=True, text=True, timeout=10,
        )
        if result.returncode != 0:
            print(f"Warning: git diff failed: {result.stderr}", file=sys.stderr)
            return []
        
        files = []
        for line in result.stdout.strip().split("\n"):
            if line.strip():
                fpath = project_root / line.strip()
                if fpath.exists() and fpath.suffix in (".php", ".js"):
                    files.append(fpath)
        return files
    except Exception as e:
        print(f"Warning: {e}", file=sys.stderr)
        return []


def analyze_security(files: list[Path]) -> dict:
    """Run security scan on changed PHP files."""
    php_files = [f for f in files if f.suffix == ".php"]
    if not php_files:
        return {"findings": [], "summary": {}, "critical_count": 0}
    
    all_findings = []
    for fpath in php_files:
        try:
            result = scan_file(fpath)
            all_findings.extend(result.findings)
        except Exception:
            pass
    
    severity_counts = Counter(f.severity for f in all_findings)
    rule_counts = Counter(f.rule_id for f in all_findings)
    
    return {
        "findings": all_findings,
        "summary": dict(severity_counts),
        "rules": dict(rule_counts),
        "critical_count": severity_counts.get("CRITICAL", 0),
        "high_count": severity_counts.get("HIGH", 0),
    }


def analyze_complexity(files: list[Path]) -> dict:
    """Compute metrics for changed files and flag violations."""
    php_parser = PHPParser()
    js_parser = JavaScriptParser()
    
    results = []
    violations = []
    
    for fpath in files:
        try:
            parser = php_parser if fpath.suffix == ".php" else js_parser
            if not parser.can_parse(fpath):
                continue
            
            functions, calls = parser.parse_file(fpath)
            
            for func in functions:
                m = func.metrics
                if not m:
                    continue
                
                func_info = {
                    "name": func.name,
                    "file": fpath.name,
                    "line": func.line,
                    "cc": m.get("cyclomatic_complexity", 0),
                    "cog": m.get("cognitive_complexity", 0),
                    "loc": m.get("lines_of_code", 0),
                    "params": m.get("param_count", 0),
                    "nesting": m.get("max_nesting_depth", 0),
                }
                results.append(func_info)
                
                # Check for threshold violations
                for metric, threshold in THRESHOLDS.items():
                    value = m.get(metric, 0) or 0
                    if value > threshold:
                        violations.append({
                            "function": func.name,
                            "file": fpath.name,
                            "line": func.line,
                            "metric": metric,
                            "value": value,
                            "threshold": threshold,
                        })
        except Exception:
            pass
    
    return {
        "functions": results,
        "violations": violations,
        "total_functions": len(results),
        "violation_count": len(violations),
    }


def analyze_file_stats(files: list[Path]) -> dict:
    """Compute basic stats about changed files."""
    php_files = [f for f in files if f.suffix == ".php"]
    js_files = [f for f in files if f.suffix == ".js"]
    
    total_lines = 0
    file_details = []
    
    for fpath in files:
        try:
            content = fpath.read_text(encoding="utf-8", errors="ignore")
            lines = len(content.split("\n"))
            total_lines += lines
            file_details.append({
                "name": fpath.name,
                "language": "PHP" if fpath.suffix == ".php" else "JavaScript",
                "lines": lines,
                "path": str(fpath),
            })
        except Exception:
            pass
    
    return {
        "php_count": len(php_files),
        "js_count": len(js_files),
        "total_files": len(files),
        "total_lines_changed": total_lines,
        "files": sorted(file_details, key=lambda f: f["lines"], reverse=True),
    }


# ── Report Generation ──────────────────────────────────────────────────

def generate_report(
    file_stats: dict,
    security: dict,
    complexity: dict,
    files: list[Path],
) -> tuple[str, bool]:
    """Generate markdown PR report. Returns (markdown, should_block)."""
    
    should_block = False
    
    # ── Header ──
    report = "## 🔍 PR Intelligence Report\n\n"
    
    # ── Overview ──
    report += f"**Files analyzed:** {file_stats['total_files']} "
    report += f"({file_stats['php_count']} PHP, {file_stats['js_count']} JS)\n\n"
    
    # ── Security ──
    critical = security.get("critical_count", 0)
    high = security.get("high_count", 0)
    total_sec = len(security.get("findings", []))
    
    if critical > 0:
        report += f"### 🚨 Security — {critical} CRITICAL\n\n"
        should_block = True
    elif high > 0:
        report += f"### ⚠️ Security — {high} HIGH\n\n"
    elif total_sec > 0:
        report += f"### 🔒 Security — {total_sec} findings\n\n"
    else:
        report += "### ✅ Security — No findings\n\n"
    
    if total_sec > 0:
        report += "| Severity | Count |\n|---|---|\n"
        for sev in ["CRITICAL", "HIGH", "MEDIUM", "LOW"]:
            count = security.get("summary", {}).get(sev, 0)
            if count > 0:
                emoji = "🚨" if sev == "CRITICAL" else "⚠️" if sev == "HIGH" else "ℹ️"
                report += f"| {emoji} {sev} | {count} |\n"
        report += "\n"
        
        # Show critical findings detail
        critical_findings = [f for f in security.get("findings", [])
                            if f.severity == "CRITICAL"]
        if critical_findings:
            report += "<details>\n<summary>🚨 Critical findings (click to expand)</summary>\n\n"
            for f in critical_findings[:10]:
                report += f"- **[{f.rule_id}]** `{f.function}` ({Path(f.file).name}:L{f.line})\n"
                report += f"  ```\n  {f.snippet[:120]}\n  ```\n"
                report += f"  → {f.recommendation}\n\n"
            report += "</details>\n\n"
    
    # ── Complexity ──
    violations = complexity.get("violations", [])
    
    if violations:
        report += f"### ⚡ Complexity — {len(violations)} threshold violations\n\n"
        report += "| Function | Metric | Value | Threshold | File |\n|---|---|---|---|---|\n"
        
        # Group by function to avoid duplicates
        seen = set()
        for v in violations[:20]:
            key = f"{v['function']}:{v['metric']}"
            if key in seen:
                continue
            seen.add(key)
            
            emoji = "🔴" if v["value"] > v["threshold"] * 2 else "🟡"
            metric_name = v["metric"].replace("_", " ").title()
            report += f"| {emoji} {v['function']} | {metric_name} | **{v['value']}** | {v['threshold']} | {v['file']} |\n"
        
        report += "\n"
    else:
        report += "### ✅ Complexity — All within thresholds\n\n"
    
    # ── File Summary ──
    report += "<details>\n<summary>📁 Files analyzed</summary>\n\n"
    report += "| File | Language | Lines |\n|---|---|---|\n"
    for f in file_stats.get("files", []):
        report += f"| {f['name']} | {f['language']} | {f['lines']} |\n"
    report += "\n</details>\n\n"
    
    # ── Footer ──
    if should_block:
        report += "> [!CAUTION]\n"
        report += f"> **{critical} CRITICAL security finding(s) detected.** "
        report += "Fix these before merging.\n\n"
    
    report += "---\n"
    report += "*Powered by Logimax Code Analyzer (LCA) — automated PR intelligence*\n"
    
    return report, should_block


# ── Main ───────────────────────────────────────────────────────────────

def main():
    parser = argparse.ArgumentParser(description="PR Intelligence Analyzer")
    parser.add_argument("--files", nargs="*", help="Files to analyze")
    parser.add_argument("--diff-base", help="Git ref to diff against (e.g., origin/Retail_1.1.1.0001)")
    parser.add_argument("--project-root", required=True, help="Project root directory")
    parser.add_argument("--output", help="Output file (default: stdout)")
    parser.add_argument("--json-output", help="JSON output file for machine consumption")
    parser.add_argument("--fail-on-critical", action="store_true",
                       help="Exit with code 1 if CRITICAL security findings found")
    
    args = parser.parse_args()
    project_root = Path(args.project_root).resolve()
    
    # Get files to analyze
    if args.files:
        files = [Path(f) for f in args.files if Path(f).exists()]
    elif args.diff_base:
        files = get_changed_files(args.diff_base, project_root)
    else:
        print("Error: Provide --files or --diff-base", file=sys.stderr)
        sys.exit(1)
    
    if not files:
        print("No files to analyze.", file=sys.stderr)
        # Write minimal report
        report = "## 🔍 PR Intelligence Report\n\nNo analyzable files (PHP/JS) changed.\n"
        if args.output:
            Path(args.output).write_text(report)
        else:
            print(report)
        sys.exit(0)
    
    print(f"Analyzing {len(files)} files...", file=sys.stderr)
    t_start = time.time()
    
    # Run analyses
    file_stats = analyze_file_stats(files)
    security = analyze_security(files)
    complexity = analyze_complexity(files)
    
    t_elapsed = time.time() - t_start
    print(f"Analysis complete in {t_elapsed:.1f}s", file=sys.stderr)
    
    # Generate report
    report, should_block = generate_report(file_stats, security, complexity, files)
    
    # Output
    if args.output:
        Path(args.output).write_text(report, encoding="utf-8")
        print(f"Report written to {args.output}", file=sys.stderr)
    else:
        print(report)
    
    # JSON output for programmatic consumption
    if args.json_output:
        json_data = {
            "files_analyzed": file_stats["total_files"],
            "security_critical": security.get("critical_count", 0),
            "security_high": security.get("high_count", 0),
            "security_total": len(security.get("findings", [])),
            "complexity_violations": complexity.get("violation_count", 0),
            "should_block": should_block,
            "analysis_time_seconds": round(t_elapsed, 1),
        }
        Path(args.json_output).write_text(json.dumps(json_data, indent=2))
    
    # Exit code
    if args.fail_on_critical and should_block:
        print(f"\n❌ BLOCKED: {security.get('critical_count', 0)} CRITICAL findings", file=sys.stderr)
        sys.exit(1)
    
    sys.exit(0)


if __name__ == "__main__":
    main()
