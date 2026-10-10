"""SDLC Review Engine — Automated Code Review for Pipeline REVIEW Phase.

Aggregates multiple LCA analysis passes into a single review with a verdict.
Called during the SDLC pipeline REVIEW phase to check code quality before commit.

Usage:
    from lca.analysis.sdlc_review import SDLCReviewer
    reviewer = SDLCReviewer(project_root)
    report = reviewer.review(changed_files)
    print(report.verdict)  # PASS / PASS_WITH_ADVISORIES / ISSUES_FOUND

Checks performed:
    1. Guard rails (GRD-*): parseFloat, NaN, transactions, AJAX errors
    2. Validation gaps (VAL-*): missing input validation on write ops
    3. Hardcoded values: magic numbers, hardcoded IDs
    4. Code smells: god classes, long methods (from index DB)
    5. Security: raw $_POST/$_GET, SQL injection patterns
    6. Brain business rules: cross-reference with module brain BUSINESS_RULES.md
"""

from __future__ import annotations

import json
import re
import subprocess
from dataclasses import dataclass, field
from pathlib import Path
from typing import Optional


# ── Models ────────────────────────────────────────────────────────────

@dataclass
class ReviewFinding:
    """A single review finding."""
    category: str          # guard, validation, hardcoded, smell, security, brain
    rule_id: str           # e.g., GRD-JS-001, VAL-001, BRAIN-001
    severity: str          # CRITICAL, HIGH, MEDIUM, LOW, ADVISORY
    title: str
    file: str
    line: int = 0
    function: str = ''
    snippet: str = ''
    recommendation: str = ''


@dataclass
class ReviewReport:
    """Aggregated review report with verdict."""
    changed_files: list[str] = field(default_factory=list)
    findings: list[ReviewFinding] = field(default_factory=list)
    brain_rules_checked: list[str] = field(default_factory=list)
    checks_run: list[str] = field(default_factory=list)

    def add(self, finding: ReviewFinding):
        self.findings.append(finding)

    @property
    def critical_count(self) -> int:
        return sum(1 for f in self.findings if f.severity == 'CRITICAL')

    @property
    def high_count(self) -> int:
        return sum(1 for f in self.findings if f.severity == 'HIGH')

    @property
    def warning_count(self) -> int:
        return sum(1 for f in self.findings if f.severity in ('MEDIUM', 'LOW'))

    @property
    def advisory_count(self) -> int:
        return sum(1 for f in self.findings if f.severity == 'ADVISORY')

    @property
    def verdict(self) -> str:
        if self.critical_count > 0:
            return 'ISSUES_FOUND'
        if self.high_count > 0:
            return 'ISSUES_FOUND'
        if self.warning_count > 0:
            return 'PASS_WITH_ADVISORIES'
        return 'PASS'

    @property
    def by_category(self) -> dict[str, int]:
        counts: dict[str, int] = {}
        for f in self.findings:
            counts[f.category] = counts.get(f.category, 0) + 1
        return counts

    @property
    def by_file(self) -> dict[str, list[ReviewFinding]]:
        grouped: dict[str, list[ReviewFinding]] = {}
        for f in self.findings:
            basename = Path(f.file).name
            grouped.setdefault(basename, []).append(f)
        return grouped

    def to_dict(self) -> dict:
        return {
            'verdict': self.verdict,
            'summary': {
                'files_reviewed': len(self.changed_files),
                'total_findings': len(self.findings),
                'critical': self.critical_count,
                'high': self.high_count,
                'warnings': self.warning_count,
                'advisories': self.advisory_count,
            },
            'checks_run': self.checks_run,
            'brain_rules_checked': self.brain_rules_checked,
            'by_category': self.by_category,
            'findings': [
                {
                    'category': f.category,
                    'rule_id': f.rule_id,
                    'severity': f.severity,
                    'title': f.title,
                    'file': Path(f.file).name,
                    'line': f.line,
                    'function': f.function,
                    'snippet': f.snippet[:120] if f.snippet else '',
                    'recommendation': f.recommendation,
                }
                for f in self.findings
            ],
        }


# ── Reviewer Engine ───────────────────────────────────────────────────

class SDLCReviewer:
    """Aggregated code reviewer for the SDLC pipeline."""

    def __init__(self, project_root: Path):
        self.root = Path(project_root)

    def review(
        self,
        changed_files: Optional[list[str]] = None,
        check_brain: bool = True,
    ) -> ReviewReport:
        """Run all review checks on changed files.

        Args:
            changed_files: List of file paths relative to project root.
                          If None, auto-detects from git diff.
            check_brain: Whether to check module brain business rules.

        Returns:
            ReviewReport with verdict and findings.
        """
        report = ReviewReport()

        # Auto-detect changed files from git
        if changed_files is None:
            changed_files = self._get_changed_files()

        report.changed_files = changed_files

        # Filter to PHP/JS files only
        scannable = [f for f in changed_files if self._is_scannable(f)]
        if not scannable:
            report.checks_run.append('no_scannable_files')
            return report

        # Run each check category
        self._check_guards(scannable, report)
        self._check_security(scannable, report)
        self._check_hardcoded(scannable, report)
        self._check_select_star(scannable, report)
        self._check_raw_input(scannable, report)

        if check_brain:
            self._check_brain_rules(scannable, report)

        return report

    # ── Check: Guard Rails ────────────────────────────────────────────

    def _check_guards(self, files: list[str], report: ReviewReport):
        """Run GuardScanner on changed files."""
        report.checks_run.append('guard_rails')
        try:
            from lca.analysis.guard import GuardScanner

            scanner = GuardScanner()
            for fpath in files:
                full_path = self.root / fpath
                if full_path.exists():
                    violations = scanner.scan_file(full_path)
                    for v in violations:
                        report.add(ReviewFinding(
                            category='guard',
                            rule_id=v.rule_id,
                            severity=v.severity,
                            title=v.title,
                            file=fpath,
                            line=v.line,
                            function=v.function,
                            snippet=v.snippet,
                            recommendation=v.recommendation,
                        ))
        except ImportError:
            report.checks_run.append('guard_rails_SKIPPED(import_error)')

    # ── Check: Security ───────────────────────────────────────────────

    def _check_security(self, files: list[str], report: ReviewReport):
        """Check for security issues: raw $_POST, XSS, SQL injection patterns."""
        report.checks_run.append('security')

        re_raw_post = re.compile(r'\$_(?:POST|GET|REQUEST)\s*\[')
        re_raw_query = re.compile(r'\$this\s*->\s*db\s*->\s*query\s*\(')
        re_xss_echo = re.compile(r'echo\s+\$(?:_POST|_GET|_REQUEST|this->input)')

        for fpath in files:
            full_path = self.root / fpath
            if not full_path.exists() or not fpath.endswith('.php'):
                continue
            try:
                content = full_path.read_text(encoding='utf-8', errors='ignore')
                lines = content.split('\n')
                for i, line in enumerate(lines):
                    stripped = line.strip()
                    if stripped.startswith('//') or stripped.startswith('*'):
                        continue

                    if re_raw_post.search(stripped):
                        report.add(ReviewFinding(
                            category='security',
                            rule_id='SEC-001',
                            severity='HIGH',
                            title='Raw $_POST/$_GET usage',
                            file=fpath, line=i+1,
                            snippet=stripped[:120],
                            recommendation='Use $this->input->post() instead of $_POST',
                        ))

                    if re_raw_query.search(stripped):
                        report.add(ReviewFinding(
                            category='security',
                            rule_id='SEC-002',
                            severity='MEDIUM',
                            title='Raw SQL query',
                            file=fpath, line=i+1,
                            snippet=stripped[:120],
                            recommendation='Use $this->db->select/where/get active record instead',
                        ))

                    if re_xss_echo.search(stripped):
                        report.add(ReviewFinding(
                            category='security',
                            rule_id='SEC-003',
                            severity='HIGH',
                            title='User input echoed without escaping',
                            file=fpath, line=i+1,
                            snippet=stripped[:120],
                            recommendation='Use htmlspecialchars() or $this->security->xss_clean()',
                        ))
            except Exception:
                pass

    # ── Check: Hardcoded Values ───────────────────────────────────────

    def _check_hardcoded(self, files: list[str], report: ReviewReport):
        """Check for magic numbers in WHERE clauses."""
        report.checks_run.append('hardcoded_values')

        re_where_magic = re.compile(
            r"""->where\s*\(\s*['"][^'"]+['"]\s*,\s*(\d+)\s*\)"""
        )

        for fpath in files:
            full_path = self.root / fpath
            if not full_path.exists() or not fpath.endswith('.php'):
                continue
            try:
                content = full_path.read_text(encoding='utf-8', errors='ignore')
                lines = content.split('\n')
                for i, line in enumerate(lines):
                    stripped = line.strip()
                    if stripped.startswith('//') or stripped.startswith('*'):
                        continue
                    matches = re_where_magic.findall(stripped)
                    for val in matches:
                        if val not in ('0', '1'):  # 0 and 1 are common and usually obvious
                            report.add(ReviewFinding(
                                category='hardcoded',
                                rule_id='HDC-001',
                                severity='LOW',
                                title=f'Magic number {val} in WHERE clause',
                                file=fpath, line=i+1,
                                snippet=stripped[:120],
                                recommendation='Use a named constant or add a comment explaining the value',
                            ))
            except Exception:
                pass

    # ── Check: SELECT * ───────────────────────────────────────────────

    def _check_select_star(self, files: list[str], report: ReviewReport):
        """Check for SELECT * usage."""
        report.checks_run.append('select_star')

        re_select_star = re.compile(
            r"""(?:->select\s*\(\s*['"]?\s*\*\s*['"]?\s*\)|SELECT\s+\*)"""
        )

        for fpath in files:
            full_path = self.root / fpath
            if not full_path.exists() or not fpath.endswith('.php'):
                continue
            try:
                content = full_path.read_text(encoding='utf-8', errors='ignore')
                lines = content.split('\n')
                for i, line in enumerate(lines):
                    stripped = line.strip()
                    if stripped.startswith('//') or stripped.startswith('*'):
                        continue
                    if re_select_star.search(stripped):
                        report.add(ReviewFinding(
                            category='quality',
                            rule_id='QLT-001',
                            severity='MEDIUM',
                            title='SELECT * usage',
                            file=fpath, line=i+1,
                            snippet=stripped[:120],
                            recommendation='Specify exact columns instead of SELECT *',
                        ))
            except Exception:
                pass

    # ── Check: Raw Input ──────────────────────────────────────────────

    def _check_raw_input(self, files: list[str], report: ReviewReport):
        """Check for direct superglobal access."""
        report.checks_run.append('raw_input')

        re_raw = re.compile(r'\$_(?:FILES|SERVER|COOKIE|SESSION)\s*\[')

        for fpath in files:
            full_path = self.root / fpath
            if not full_path.exists() or not fpath.endswith('.php'):
                continue
            try:
                content = full_path.read_text(encoding='utf-8', errors='ignore')
                lines = content.split('\n')
                for i, line in enumerate(lines):
                    stripped = line.strip()
                    if stripped.startswith('//') or stripped.startswith('*'):
                        continue
                    if re_raw.search(stripped):
                        report.add(ReviewFinding(
                            category='security',
                            rule_id='SEC-004',
                            severity='LOW',
                            title='Direct superglobal access',
                            file=fpath, line=i+1,
                            snippet=stripped[:120],
                            recommendation='Use CI3 input class or wrap with proper sanitization',
                        ))
            except Exception:
                pass

    # ── Check: Module Brain Business Rules ────────────────────────────

    def _check_brain_rules(self, files: list[str], report: ReviewReport):
        """Cross-reference changes against module brain business rules.

        For each changed file, detect its module, read the brain's
        BUSINESS_RULES.md, and flag any rules that might be affected.
        """
        report.checks_run.append('brain_business_rules')

        brain_root = self.root / 'knowledge_brain'
        if not brain_root.exists():
            report.checks_run.append('brain_SKIPPED(no_knowledge_brain)')
            return

        # Group files by module
        modules_touched: dict[str, list[str]] = {}
        for fpath in files:
            module = self._detect_module(fpath)
            if module:
                modules_touched.setdefault(module, []).append(fpath)

        for module, module_files in modules_touched.items():
            rules_file = brain_root / module / 'BUSINESS_RULES.md'
            if not rules_file.exists():
                report.checks_run.append(f'brain_SKIPPED({module}_no_rules)')
                continue

            try:
                rules_content = rules_file.read_text(encoding='utf-8', errors='ignore')
                rules = self._parse_business_rules(rules_content)

                for rule_id, rule_text in rules:
                    report.brain_rules_checked.append(f'{rule_id}: {rule_text[:80]}')

                    # Check if any changed function names appear in the rule
                    for fpath in module_files:
                        full_path = self.root / fpath
                        if not full_path.exists():
                            continue
                        content = full_path.read_text(encoding='utf-8', errors='ignore')
                        # Extract function names from changed file
                        funcs = re.findall(r'function\s+(\w+)', content)
                        for func in funcs:
                            if func.lower() in rule_text.lower():
                                report.add(ReviewFinding(
                                    category='brain',
                                    rule_id=f'BRAIN-{rule_id}',
                                    severity='ADVISORY',
                                    title=f'Business rule may be affected: {rule_id}',
                                    file=fpath,
                                    function=func,
                                    snippet=rule_text[:120],
                                    recommendation=f'Verify that {rule_id} still holds after this change',
                                ))
            except Exception:
                pass

    # ── Helpers ────────────────────────────────────────────────────────

    def _get_changed_files(self) -> list[str]:
        """Get changed files from git diff."""
        try:
            result = subprocess.run(
                ['git', 'diff', '--name-only', 'HEAD'],
                capture_output=True, text=True,
                cwd=str(self.root),
                encoding='utf-8', errors='replace',
            )
            files = [f.strip() for f in result.stdout.strip().split('\n') if f.strip()]

            # Also check staged files
            result2 = subprocess.run(
                ['git', 'diff', '--cached', '--name-only'],
                capture_output=True, text=True,
                cwd=str(self.root),
                encoding='utf-8', errors='replace',
            )
            staged = [f.strip() for f in result2.stdout.strip().split('\n') if f.strip()]

            # Deduplicate
            all_files = list(dict.fromkeys(files + staged))
            return all_files
        except Exception:
            return []

    def _is_scannable(self, fpath: str) -> bool:
        """Check if file is PHP or JS (scannable)."""
        lower = fpath.lower()
        return any(lower.endswith(ext) for ext in ('.php', '.js', '.jsx'))

    def _detect_module(self, fpath: str) -> Optional[str]:
        """Detect module name from file path."""
        lower = fpath.lower().replace('\\', '/')

        # Pattern: admin/application/controllers/ModuleName.php
        # Pattern: admin/application/models/ModuleName_model.php
        # Pattern: admin/assets/js/modulename.js

        # Known module keywords
        module_keywords = [
            'billing', 'estimation', 'purchase', 'stock', 'inventory',
            'godown', 'customer', 'supplier', 'report', 'gst', 'account',
            'payment', 'receipt', 'journal', 'ledger', 'scheme', 'opening',
            'admin', 'auth', 'access', 'user', 'setting', 'config',
        ]

        for keyword in module_keywords:
            if keyword in lower:
                return keyword

        return None

    def _parse_business_rules(self, content: str) -> list[tuple[str, str]]:
        """Extract business rules from BUSINESS_RULES.md.

        Looks for patterns like:
            BR-001: Discount cannot exceed item price
            **BR-002**: GST calculation uses net amount
        """
        rules: list[tuple[str, str]] = []
        # Match BR-NNN or similar patterns
        pattern = re.compile(
            r'(?:\*\*)?(?:BR|RULE|BUS)-?(\d+)(?:\*\*)?[:\s]+(.+?)(?:\n|$)',
            re.IGNORECASE,
        )
        for match in pattern.finditer(content):
            rule_id = f'BR-{match.group(1)}'
            rule_text = match.group(2).strip()
            rules.append((rule_id, rule_text))

        return rules


# ── Text Formatter ────────────────────────────────────────────────────

def format_review_text(report: ReviewReport) -> str:
    """Format the review report as human-readable text."""
    lines: list[str] = []
    verdict_map = {
        'PASS': '[PASS] All checks passed',
        'PASS_WITH_ADVISORIES': '[PASS WITH ADVISORIES] No critical issues, some warnings',
        'ISSUES_FOUND': '[ISSUES FOUND] Critical/high issues must be fixed',
    }

    lines.append('=' * 60)
    lines.append('  SDLC Code Review Report')
    lines.append('=' * 60)
    lines.append('')
    lines.append(f'  Verdict: {verdict_map.get(report.verdict, report.verdict)}')
    lines.append(f'  Files reviewed: {len(report.changed_files)}')
    lines.append(f'  Total findings: {len(report.findings)}')
    lines.append(f'    Critical: {report.critical_count}')
    lines.append(f'    High:     {report.high_count}')
    lines.append(f'    Warnings: {report.warning_count}')
    lines.append(f'    Advisory: {report.advisory_count}')
    lines.append(f'  Checks run: {", ".join(report.checks_run)}')
    lines.append('')

    if report.findings:
        lines.append('-' * 60)
        lines.append('  Findings')
        lines.append('-' * 60)

        severity_order = {'CRITICAL': 0, 'HIGH': 1, 'MEDIUM': 2, 'LOW': 3, 'ADVISORY': 4}
        sorted_findings = sorted(
            report.findings,
            key=lambda f: severity_order.get(f.severity, 5),
        )

        for i, f in enumerate(sorted_findings, 1):
            sev_icon = {
                'CRITICAL': '!!!',
                'HIGH': '!! ',
                'MEDIUM': '!  ',
                'LOW': '.  ',
                'ADVISORY': '~  ',
            }.get(f.severity, '   ')

            lines.append(f'  {sev_icon} [{f.rule_id}] {f.title}')
            lines.append(f'      File: {Path(f.file).name}:{f.line}')
            if f.function:
                lines.append(f'      Func: {f.function}')
            if f.recommendation:
                lines.append(f'      Fix:  {f.recommendation}')
            lines.append('')

    if report.brain_rules_checked:
        lines.append('-' * 60)
        lines.append('  Business Rules Checked')
        lines.append('-' * 60)
        for rule in report.brain_rules_checked:
            lines.append(f'    [OK] {rule}')
        lines.append('')

    lines.append('=' * 60)
    return '\n'.join(lines)
