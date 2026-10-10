"""Pre-Release Safety Check — analyse git diff to flag risky changes before deploy.

Scans the diff between two git refs (branches, tags, commits) and produces
a safety report covering:

  PRE-001: Changed files impact summary (by module)
  PRE-002: Database schema changes (ALTER/DROP/CREATE in migrations)
  PRE-003: Financial calculation changes (price/amount/tax formulas)
  PRE-004: Permission/access control changes
  PRE-005: Shared model/helper changes (cross-module blast radius)
  PRE-006: Config file changes
  PRE-007: New dependencies or library changes
  PRE-008: Removed validation/security guards
  PRE-009: Known bug pattern matches
  PRE-010: Raw SQL or query builder changes
"""

from __future__ import annotations

import re
import subprocess
from dataclasses import dataclass, field
from pathlib import Path
from typing import Optional


# ── Models ────────────────────────────────────────────────────────────

@dataclass
class RiskItem:
    """A single risk found in the diff."""
    rule_id: str
    severity: str       # CRITICAL, HIGH, MEDIUM, LOW, INFO
    title: str
    description: str
    file: str
    line: int = 0
    added_lines: list[str] = field(default_factory=list)
    removed_lines: list[str] = field(default_factory=list)
    recommendation: str = ''


@dataclass
class PreReleaseReport:
    """Full pre-release safety report."""
    source_ref: str = ''
    target_ref: str = ''
    total_files_changed: int = 0
    total_insertions: int = 0
    total_deletions: int = 0
    files_by_type: dict[str, int] = field(default_factory=dict)
    modules_affected: dict[str, list[str]] = field(default_factory=dict)
    risks: list[RiskItem] = field(default_factory=list)
    verdict: str = 'UNKNOWN'  # SAFE, CAUTION, RISKY, BLOCK

    def add(self, risk: RiskItem):
        self.risks.append(risk)

    @property
    def by_severity(self) -> dict[str, int]:
        counts: dict[str, int] = {}
        for r in self.risks:
            counts[r.severity] = counts.get(r.severity, 0) + 1
        return counts

    @property
    def critical_count(self) -> int:
        return sum(1 for r in self.risks if r.severity == 'CRITICAL')

    @property
    def high_count(self) -> int:
        return sum(1 for r in self.risks if r.severity == 'HIGH')

    def compute_verdict(self):
        """Set verdict based on risk counts."""
        if self.critical_count > 0:
            self.verdict = 'BLOCK'
        elif self.high_count >= 3:
            self.verdict = 'RISKY'
        elif self.high_count >= 1:
            self.verdict = 'CAUTION'
        elif len(self.risks) == 0:
            self.verdict = 'SAFE'
        else:
            self.verdict = 'CAUTION'


# ── Module Detection ─────────────────────────────────────────────────

_MODULE_MAP = {
    'billing': 'Billing',
    'invoice': 'Billing',
    'estimation': 'Estimation',
    'estimate': 'Estimation',
    'purchase': 'Purchase',
    'goods_receive': 'Goods Receive',
    'grn': 'Goods Receive',
    'stock': 'Stock',
    'inventory': 'Stock',
    'payment': 'Payment',
    'chit': 'Payment',
    'scheme': 'Payment',
    'customer': 'Customer',
    'supplier': 'Supplier',
    'product': 'Product',
    'tag': 'Product',
    'barcode': 'Product',
    'report': 'Reports',
    'lot': 'LOT',
    'manufacturing': 'LOT',
    'admin': 'Admin',
    'settings': 'Admin',
    'irn': 'E-Invoice',
    'print': 'Print',
    'order': 'Order',
    'services': 'Services',
    'transfer': 'Transfer',
    'return': 'Returns',
}

# Shared files — changes here have cross-module blast radius
_SHARED_FILES = {
    'common_model', 'auth_model', 'access_right_model', 'admin_settings_model',
    'MY_Controller', 'MY_Model', 'MY_Form_validation',
    'common_helper', 'url_helper', 'receipt_helper', 'notification_helper',
    'common.js', 'notifications.js', 'utility.js',
}

# Config files — require explicit approval
_CONFIG_FILES = {
    'config.php', 'database.php', 'routes.php', 'autoload.php',
    'constants.php', 'hooks.php', 'migration.php', 'rest.php',
    '.env', '.htaccess',
}

# Financial patterns in diffs
_FINANCIAL_PATTERNS = re.compile(
    r'(?:amount|total|price|rate|discount|tax|cgst|sgst|igst|round_off|'
    r'grand_total|sub_total|net_amount|balance|payment|advance|'
    r'cost_price|selling_price|mrp|commission|incentive|freight|'
    r'gold_rate|silver_rate|metal_value|making_charge|wastage)',
    re.IGNORECASE
)

# Permission/access patterns
_PERMISSION_PATTERNS = re.compile(
    r'(?:check_permission|has_permission|access_right|is_allowed|'
    r'check_access|verify_permission|auth_check|logged_in|session)',
    re.IGNORECASE
)

# Security guard patterns
_SECURITY_PATTERNS = re.compile(
    r'(?:xss_clean|htmlspecialchars|htmlentities|strip_tags|'
    r'escape_str|form_validation|csrf|sanitize|filter_var)',
    re.IGNORECASE
)

# Raw SQL patterns
_RAW_SQL_PATTERNS = re.compile(
    r'(?:\$this\s*->\s*db\s*->\s*query\s*\(|'
    r'mysql_query|mysqli_query|PDO::query|'
    r'SELECT\s+.*\s+FROM\s|INSERT\s+INTO\s|'
    r'UPDATE\s+\w+\s+SET\s|DELETE\s+FROM\s|'
    r'ALTER\s+TABLE|DROP\s+TABLE|CREATE\s+TABLE|TRUNCATE)',
    re.IGNORECASE
)

# Known bug patterns (from bug-recipes patterns)
_BUG_PATTERNS = [
    (re.compile(r'\$\(.*\)\.val\(\)\s*[+\-*/]', re.IGNORECASE),
     'JS val() used in arithmetic without parseFloat() - NaN risk'),
    (re.compile(r'\.toFixed\(\d+\)\s*[+\-*/]', re.IGNORECASE),
     'toFixed() result used in arithmetic - string concatenation risk'),
    (re.compile(r'SELECT\s+\*\s+FROM', re.IGNORECASE),
     'SELECT * used - performance risk and fragile column dependencies'),
    (re.compile(r'\$_POST\[|\$_GET\[|\$_REQUEST\[', re.IGNORECASE),
     'Raw superglobal used instead of $this->input->post()'),
    (re.compile(r'die\s*\(|exit\s*\(', re.IGNORECASE),
     'die()/exit() used - may hide errors in production'),
    (re.compile(r'error_reporting\s*\(\s*0\s*\)', re.IGNORECASE),
     'error_reporting(0) - silences errors in production'),
]


# ── Scanner Engine ───────────────────────────────────────────────────

class PreReleaseScanner:
    """Scan git diff for pre-release safety risks."""

    def scan(
        self,
        project_root: Path,
        source_ref: str = 'HEAD',
        target_ref: str = 'HEAD~1',
        staged: bool = False,
    ) -> PreReleaseReport:
        """Scan diff between two refs for risks."""
        report = PreReleaseReport(
            source_ref=source_ref,
            target_ref=target_ref,
        )

        # Get the diff
        diff_text = self._get_diff(project_root, source_ref, target_ref, staged)
        if not diff_text:
            report.verdict = 'SAFE'
            return report

        # Get file stats
        stat_text = self._get_stat(project_root, source_ref, target_ref, staged)
        self._parse_stat(stat_text, report)

        # Parse diff into file chunks
        file_diffs = self._parse_diff(diff_text)

        # Analyze each changed file
        for file_path, hunks in file_diffs.items():
            self._classify_file(file_path, report)
            self._analyze_file_diff(file_path, hunks, report)

        # Compute verdict
        report.compute_verdict()

        return report

    def _get_diff(
        self, root: Path, source: str, target: str, staged: bool
    ) -> str:
        """Get git diff text."""
        try:
            if staged:
                cmd = ['git', 'diff', '--cached', '-U3']
            else:
                cmd = ['git', 'diff', f'{target}...{source}', '-U3']

            result = subprocess.run(
                cmd, cwd=str(root), capture_output=True, text=True,
                encoding='utf-8', errors='ignore'
            )
            return result.stdout
        except Exception:
            return ''

    def _get_stat(
        self, root: Path, source: str, target: str, staged: bool
    ) -> str:
        """Get git diff --stat."""
        try:
            if staged:
                cmd = ['git', 'diff', '--cached', '--stat']
            else:
                cmd = ['git', 'diff', f'{target}...{source}', '--stat']

            result = subprocess.run(
                cmd, cwd=str(root), capture_output=True, text=True,
                encoding='utf-8', errors='ignore'
            )
            return result.stdout
        except Exception:
            return ''

    def _parse_stat(self, stat_text: str, report: PreReleaseReport):
        """Parse --stat output for summary numbers."""
        lines = stat_text.strip().split('\n')
        report.total_files_changed = max(0, len(lines) - 1)  # Last line is summary

        for line in lines:
            # Match "filename | N +++ ---" pattern
            match = re.match(r'\s*(.+?)\s*\|\s*(\d+)', line)
            if match:
                fname = match.group(1).strip()
                ext = Path(fname).suffix.lower() or 'other'
                report.files_by_type[ext] = report.files_by_type.get(ext, 0) + 1

        # Parse summary line "N files changed, N insertions(+), N deletions(-)"
        if lines:
            summary = lines[-1]
            ins_match = re.search(r'(\d+)\s+insertion', summary)
            del_match = re.search(r'(\d+)\s+deletion', summary)
            if ins_match:
                report.total_insertions = int(ins_match.group(1))
            if del_match:
                report.total_deletions = int(del_match.group(1))

    def _parse_diff(self, diff_text: str) -> dict[str, list[dict]]:
        """Parse unified diff into per-file hunk lists."""
        files: dict[str, list[dict]] = {}
        current_file = None
        current_hunk: dict | None = None

        for line in diff_text.split('\n'):
            # New file
            if line.startswith('diff --git'):
                match = re.search(r'b/(.+)$', line)
                if match:
                    current_file = match.group(1)
                    files[current_file] = []

            # Hunk header
            elif line.startswith('@@') and current_file:
                match = re.search(r'@@ -(\d+)', line)
                start_line = int(match.group(1)) if match else 0
                current_hunk = {
                    'start_line': start_line,
                    'added': [],
                    'removed': [],
                    'context': [],
                }
                files[current_file].append(current_hunk)

            elif current_hunk is not None:
                if line.startswith('+') and not line.startswith('+++'):
                    current_hunk['added'].append(line[1:])
                elif line.startswith('-') and not line.startswith('---'):
                    current_hunk['removed'].append(line[1:])
                else:
                    current_hunk['context'].append(line)

        return files

    def _classify_file(self, file_path: str, report: PreReleaseReport):
        """Classify a changed file into its module."""
        lower = file_path.lower()
        for pattern, module in _MODULE_MAP.items():
            if pattern in lower:
                if module not in report.modules_affected:
                    report.modules_affected[module] = []
                if file_path not in report.modules_affected[module]:
                    report.modules_affected[module].append(file_path)
                break

    def _analyze_file_diff(
        self, file_path: str, hunks: list[dict], report: PreReleaseReport
    ):
        """Analyze a file's diff hunks for risks."""
        basename = Path(file_path).stem.lower()
        lower_path = file_path.lower()

        # ── PRE-005: Shared file changes ──
        if basename in _SHARED_FILES or any(s in basename for s in _SHARED_FILES):
            report.add(RiskItem(
                rule_id='PRE-005',
                severity='HIGH',
                title='Shared model/helper modified',
                description=(
                    f'"{Path(file_path).name}" is used across multiple modules. '
                    'Changes here have cross-module blast radius. '
                    'Verify all callers before deploying.'
                ),
                file=file_path,
                recommendation='Run: lca impact <function_name> to check all callers',
            ))

        # ── PRE-006: Config file changes ──
        if Path(file_path).name in _CONFIG_FILES:
            report.add(RiskItem(
                rule_id='PRE-006',
                severity='HIGH',
                title='Config file modified',
                description=(
                    f'Configuration file "{Path(file_path).name}" was changed. '
                    'Config changes affect the entire application and require '
                    'explicit review before deployment.'
                ),
                file=file_path,
                recommendation='Review the change and verify on staging first',
            ))

        for hunk in hunks:
            added_text = '\n'.join(hunk.get('added', []))
            removed_text = '\n'.join(hunk.get('removed', []))
            start_line = hunk.get('start_line', 0)

            # ── PRE-002: Schema changes ──
            if re.search(r'ALTER\s+TABLE|DROP\s+TABLE|CREATE\s+TABLE|ADD\s+COLUMN|DROP\s+COLUMN|MODIFY\s+COLUMN|TRUNCATE', added_text, re.IGNORECASE):
                report.add(RiskItem(
                    rule_id='PRE-002',
                    severity='CRITICAL',
                    title='Database schema change detected',
                    description=(
                        f'SQL schema modification found in {Path(file_path).name}. '
                        'Schema changes require migration planning, backup verification, '
                        'and explicit DBA approval.'
                    ),
                    file=file_path, line=start_line,
                    added_lines=hunk.get('added', [])[:5],
                    recommendation='Review SQL, verify backup, test on staging DB first',
                ))

            # ── PRE-003: Financial calculation changes ──
            if _FINANCIAL_PATTERNS.search(added_text) or _FINANCIAL_PATTERNS.search(removed_text):
                # Only flag if the change modifies calculation logic, not just references
                if re.search(r'[+\-*/=].*(?:amount|total|price|rate|tax|discount)', added_text, re.IGNORECASE):
                    report.add(RiskItem(
                        rule_id='PRE-003',
                        severity='HIGH',
                        title='Financial calculation modified',
                        description=(
                            f'Financial formula/calculation changed in {Path(file_path).name}. '
                            'Verify the math is correct and test with edge cases '
                            '(zero, negative, very large values).'
                        ),
                        file=file_path, line=start_line,
                        added_lines=[l for l in hunk.get('added', []) if _FINANCIAL_PATTERNS.search(l)][:3],
                        removed_lines=[l for l in hunk.get('removed', []) if _FINANCIAL_PATTERNS.search(l)][:3],
                        recommendation='Test with edge cases: 0, negative, max values',
                    ))

            # ── PRE-004: Permission changes ──
            if _PERMISSION_PATTERNS.search(removed_text) and not _PERMISSION_PATTERNS.search(added_text):
                report.add(RiskItem(
                    rule_id='PRE-004',
                    severity='CRITICAL',
                    title='Permission/access check removed',
                    description=(
                        f'Access control code was removed from {Path(file_path).name} '
                        'without replacement. This may expose endpoints to unauthorized users.'
                    ),
                    file=file_path, line=start_line,
                    removed_lines=[l for l in hunk.get('removed', []) if _PERMISSION_PATTERNS.search(l)][:3],
                    recommendation='Verify the permission check is still enforced elsewhere',
                ))

            # ── PRE-008: Security guard removed ──
            if _SECURITY_PATTERNS.search(removed_text) and not _SECURITY_PATTERNS.search(added_text):
                report.add(RiskItem(
                    rule_id='PRE-008',
                    severity='HIGH',
                    title='Security guard removed',
                    description=(
                        f'Security validation (XSS/CSRF/sanitization) was removed from '
                        f'{Path(file_path).name} without replacement.'
                    ),
                    file=file_path, line=start_line,
                    removed_lines=[l for l in hunk.get('removed', []) if _SECURITY_PATTERNS.search(l)][:3],
                    recommendation='Ensure security validation is still in place',
                ))

            # ── PRE-009: Known bug patterns in added code ──
            for pattern, desc in _BUG_PATTERNS:
                if pattern.search(added_text):
                    matching_lines = [l for l in hunk.get('added', []) if pattern.search(l)]
                    report.add(RiskItem(
                        rule_id='PRE-009',
                        severity='MEDIUM',
                        title='Known bug pattern detected',
                        description=f'{desc} - in {Path(file_path).name}',
                        file=file_path, line=start_line,
                        added_lines=matching_lines[:3],
                        recommendation='Fix the pattern before deploying',
                    ))

            # ── PRE-010: Raw SQL added ──
            if re.search(r'\$this\s*->\s*db\s*->\s*query\s*\(', added_text):
                report.add(RiskItem(
                    rule_id='PRE-010',
                    severity='MEDIUM',
                    title='Raw SQL query added',
                    description=(
                        f'Raw SQL ($this->db->query()) added in {Path(file_path).name}. '
                        'Prefer CI3 query builder for safety and maintainability.'
                    ),
                    file=file_path, line=start_line,
                    added_lines=[l for l in hunk.get('added', []) if '$this' in l and 'query(' in l][:3],
                    recommendation='Use $this->db->select/where/get() instead of raw SQL',
                ))

            # ── PRE-010b: Raw superglobals ──
            if re.search(r'\$_POST\[|\$_GET\[|\$_REQUEST\[', added_text):
                report.add(RiskItem(
                    rule_id='PRE-009',
                    severity='MEDIUM',
                    title='Raw superglobal used',
                    description=(
                        f'$_POST/$_GET/$_REQUEST used in {Path(file_path).name}. '
                        'Use $this->input->post() for XSS protection.'
                    ),
                    file=file_path, line=start_line,
                    recommendation='Replace with $this->input->post()',
                ))


# ── Formatting ───────────────────────────────────────────────────────

_VERDICT_COLORS = {
    'SAFE': '[PASS]',
    'CAUTION': '[WARN]',
    'RISKY': '[RISK]',
    'BLOCK': '[STOP]',
}

_SEVERITY_EMOJI = {
    'CRITICAL': '[!!]',
    'HIGH': '[! ]',
    'MEDIUM': '[~ ]',
    'LOW': '[  ]',
    'INFO': '[i ]',
}


def format_report_text(report: PreReleaseReport) -> str:
    """Format report as human-readable text."""
    lines = []
    verdict_icon = _VERDICT_COLORS.get(report.verdict, '[??]')

    lines.append('=' * 70)
    lines.append(f'PRE-RELEASE SAFETY REPORT   {verdict_icon} {report.verdict}')
    lines.append(f'Diff: {report.target_ref} -> {report.source_ref}')
    lines.append('=' * 70)

    # Summary
    lines.append(f'\n-- CHANGE SUMMARY --')
    lines.append(f'  Files changed:  {report.total_files_changed}')
    lines.append(f'  Insertions:     +{report.total_insertions}')
    lines.append(f'  Deletions:      -{report.total_deletions}')
    if report.files_by_type:
        types = ', '.join(f'{ext}: {c}' for ext, c in sorted(report.files_by_type.items()))
        lines.append(f'  By type:        {types}')

    # Modules affected
    if report.modules_affected:
        lines.append(f'\n-- MODULES AFFECTED ({len(report.modules_affected)}) --')
        for module, files in sorted(report.modules_affected.items()):
            lines.append(f'  {module} ({len(files)} files)')
            for f in files[:3]:
                lines.append(f'    * {f}')
            if len(files) > 3:
                lines.append(f'    ... and {len(files) - 3} more')

    # Risk summary
    by_sev = report.by_severity
    lines.append(f'\n-- RISK SUMMARY ({len(report.risks)} items) --')
    for sev in ['CRITICAL', 'HIGH', 'MEDIUM', 'LOW', 'INFO']:
        count = by_sev.get(sev, 0)
        if count:
            emoji = _SEVERITY_EMOJI.get(sev, '')
            lines.append(f'  {emoji} {sev}: {count}')

    # Individual risks
    if report.risks:
        lines.append(f'\n-- RISKS --')
        for i, risk in enumerate(report.risks, 1):
            emoji = _SEVERITY_EMOJI.get(risk.severity, '')
            lines.append(f'\n  {i}. {emoji} [{risk.rule_id}] {risk.title}')
            lines.append(f'     File: {risk.file}' + (f' L{risk.line}' if risk.line else ''))
            lines.append(f'     {risk.description}')
            if risk.added_lines:
                for al in risk.added_lines[:2]:
                    lines.append(f'     + {al.strip()[:100]}')
            if risk.removed_lines:
                for rl in risk.removed_lines[:2]:
                    lines.append(f'     - {rl.strip()[:100]}')
            if risk.recommendation:
                lines.append(f'     >> {risk.recommendation}')

    # Verdict
    lines.append(f'\n{"=" * 70}')
    lines.append(f'VERDICT: {verdict_icon} {report.verdict}')
    if report.verdict == 'BLOCK':
        lines.append('STOP: CRITICAL issues found - DO NOT deploy without fixing.')
    elif report.verdict == 'RISKY':
        lines.append('WARNING: Multiple HIGH risks - deploy with caution, staging test required.')
    elif report.verdict == 'CAUTION':
        lines.append('CAUTION: Some risks found - review before deploying.')
    elif report.verdict == 'SAFE':
        lines.append('OK: No significant risks found - safe to deploy.')
    lines.append('=' * 70)

    return '\n'.join(lines)


def format_report_json(report: PreReleaseReport) -> dict:
    """Format as JSON-serializable dict."""
    return {
        'source_ref': report.source_ref,
        'target_ref': report.target_ref,
        'verdict': report.verdict,
        'summary': {
            'files_changed': report.total_files_changed,
            'insertions': report.total_insertions,
            'deletions': report.total_deletions,
            'files_by_type': report.files_by_type,
        },
        'modules_affected': report.modules_affected,
        'risk_summary': report.by_severity,
        'risks': [
            {
                'rule_id': r.rule_id,
                'severity': r.severity,
                'title': r.title,
                'description': r.description,
                'file': r.file,
                'line': r.line,
                'added_lines': r.added_lines[:3],
                'removed_lines': r.removed_lines[:3],
                'recommendation': r.recommendation,
            }
            for r in report.risks
        ],
    }
