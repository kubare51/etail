"""Guard Scanner — detect missing safety checks in business logic code.

Unlike the security scanner (SQL injection, XSS), the guard scanner catches
**business logic safety gaps** that lead to calculation bugs, data corruption,
and silent failures:

JS Guards:
  GRD-JS-001: Arithmetic on form values without parseFloat/Number
  GRD-JS-002: Currency result without toFixed(2)
  GRD-JS-003: parseFloat/Number result used without isNaN check
  GRD-JS-004: AJAX call without error handler
  GRD-JS-005: Hardcoded URL in AJAX call (should use base_url)
  GRD-JS-006: Division without zero-check

PHP Guards:
  GRD-PHP-001: Multi-table write without transaction wrap
  GRD-PHP-002: UPDATE/DELETE without WHERE clause
  GRD-PHP-003: Missing numeric cast on form input used in arithmetic
  GRD-PHP-004: Empty catch block (silent failure)
  GRD-PHP-005: die()/exit() without log_message()

Cross Guards:
  GRD-X-001: Function with formulas but no validation on inputs (from index)
"""

from __future__ import annotations

import re
from dataclasses import dataclass, field
from pathlib import Path
from typing import Optional


# ── Models ────────────────────────────────────────────────────────────

@dataclass
class GuardViolation:
    """A single missing safety check."""
    rule_id: str
    severity: str       # CRITICAL, HIGH, MEDIUM, LOW
    title: str
    description: str
    file: str
    line: int
    function: str
    snippet: str = ''
    recommendation: str = ''


@dataclass
class GuardReport:
    """Results of a guard scan."""
    files_scanned: int = 0
    violations: list[GuardViolation] = field(default_factory=list)
    by_rule: dict[str, int] = field(default_factory=dict)
    by_severity: dict[str, int] = field(default_factory=dict)
    by_file: dict[str, int] = field(default_factory=dict)

    def add(self, v: GuardViolation):
        self.violations.append(v)
        self.by_rule[v.rule_id] = self.by_rule.get(v.rule_id, 0) + 1
        self.by_severity[v.severity] = self.by_severity.get(v.severity, 0) + 1
        fname = Path(v.file).name
        self.by_file[fname] = self.by_file.get(fname, 0) + 1

    @property
    def total(self) -> int:
        return len(self.violations)

    @property
    def critical_count(self) -> int:
        return self.by_severity.get('CRITICAL', 0)

    @property
    def high_count(self) -> int:
        return self.by_severity.get('HIGH', 0)


# ── Skip patterns ────────────────────────────────────────────────────

_SKIP_DIRS = {
    '.git', 'node_modules', 'vendor', '__pycache__', 'logs', 'cache',
    'libraries', 'third_party', 'fonts', 'images', 'uploads',
}

_JS_EXTENSIONS = {'.js', '.jsx', '.mjs'}
_PHP_EXTENSIONS = {'.php', '.phtml', '.inc'}


# ── Guard Scanner Engine ──────────────────────────────────────────────

class GuardScanner:
    """Scan code for missing safety checks in business logic."""

    def scan_directory(
        self,
        root: Path,
        module_filter: Optional[str] = None,
    ) -> GuardReport:
        """Scan a directory tree for guard violations."""
        report = GuardReport()

        for file_path in self._iter_files(root):
            if module_filter:
                if module_filter.lower() not in str(file_path).lower():
                    continue

            try:
                violations = self.scan_file(file_path)
                if violations:
                    for v in violations:
                        report.add(v)
                report.files_scanned += 1
            except Exception:
                pass  # Skip unreadable files

        return report

    def scan_file(self, file_path: Path) -> list[GuardViolation]:
        """Scan a single file for guard violations."""
        suffix = file_path.suffix.lower()

        try:
            content = file_path.read_text(encoding='utf-8', errors='ignore')
        except Exception:
            return []

        lines = content.split('\n')
        violations: list[GuardViolation] = []
        file_str = str(file_path)

        if suffix in _JS_EXTENSIONS:
            violations.extend(self._scan_js(lines, file_str))
        elif suffix in _PHP_EXTENSIONS:
            violations.extend(self._scan_php(lines, file_str))

        return violations

    def _iter_files(self, root: Path):
        """Iterate over scannable files."""
        for item in root.rglob('*'):
            if any(skip in item.parts for skip in _SKIP_DIRS):
                continue
            if item.is_file() and item.suffix.lower() in (_JS_EXTENSIONS | _PHP_EXTENSIONS):
                yield item

    # ── JavaScript Guards ─────────────────────────────────────────────

    def _scan_js(self, lines: list[str], file: str) -> list[GuardViolation]:
        violations: list[GuardViolation] = []
        current_func = 'GLOBAL'
        in_ajax = False
        ajax_has_error = False
        ajax_start_line = 0
        brace_depth = 0

        # Pre-compile patterns
        re_func = re.compile(r'function\s+(\w+)')
        re_arrow_func = re.compile(r'(?:const|let|var)\s+(\w+)\s*=\s*(?:function|\()')
        re_val_arith = re.compile(
            r"""
            (?:\.val\(\)|\.text\(\)|\.html\(\))   # jQuery value getter
            \s*[\+\-\*\/]                           # arithmetic operator
            """, re.VERBOSE
        )
        re_arith_val = re.compile(
            r"""
            [\+\-\*\/]\s*                           # arithmetic operator
            (?:\$\(|jQuery\().*?\.val\(\)            # jQuery value getter
            """, re.VERBOSE
        )
        re_missing_parse = re.compile(
            r"""
            (?<!parseFloat\()                       # NOT wrapped in parseFloat
            (?<!parseInt\()                          # NOT wrapped in parseInt
            (?<!Number\()                            # NOT wrapped in Number
            \.val\(\)\s*[\+\-\*\/]                  # .val() used in arithmetic
            """, re.VERBOSE
        )
        re_currency_no_fixed = re.compile(
            r"""
            (?:total|amount|price|cost|rate|tax|gst|discount|net|gross|
               balance|payment|credit|debit|charge|fee|value|sum|
               grand_total|sub_total|subtotal)
            \s*=\s*                                 # assignment
            [^;]*[\+\-\*\/]                         # has arithmetic
            """, re.VERBOSE | re.IGNORECASE
        )
        re_to_fixed = re.compile(r'\.toFixed\(')
        re_ajax = re.compile(r'\$\.ajax\s*\(|\$\.post\s*\(|\$\.get\s*\(')
        re_ajax_error = re.compile(r'error\s*:|\.fail\s*\(|\.catch\s*\(')
        re_hardcoded_url = re.compile(
            r"""
            url\s*:\s*                              # url property
            ['"]                                    # string start
            /\w+                                    # starts with /path
            """, re.VERBOSE
        )
        re_base_url = re.compile(r'base_url|BASE_URL|baseUrl|site_url')
        re_divide = re.compile(r'(\w+)\s*/\s*(\w+)')
        re_divide_check = re.compile(r'if\s*\(.*(?:==\s*0|!=\s*0|>\s*0|<\s*0)')
        re_isnan = re.compile(r'isNaN\s*\(')
        re_parsefloat = re.compile(r'(?:parseFloat|parseInt|Number)\s*\(')

        for i, line in enumerate(lines):
            stripped = line.strip()
            lineno = i + 1

            # Skip comments
            if stripped.startswith('//') or stripped.startswith('/*') or stripped.startswith('*'):
                continue

            # Track function context
            m = re_func.search(stripped)
            if m:
                current_func = m.group(1)
            else:
                m = re_arrow_func.search(stripped)
                if m:
                    current_func = m.group(1)

            # GRD-JS-001: .val() in arithmetic without parseFloat
            if '.val()' in stripped and re_missing_parse.search(stripped):
                violations.append(GuardViolation(
                    rule_id='GRD-JS-001',
                    severity='HIGH',
                    title='Arithmetic on form value without parseFloat()',
                    description=(
                        'jQuery .val() returns a string. Using it directly in arithmetic '
                        'causes string concatenation instead of numeric addition.'
                    ),
                    file=file, line=lineno, function=current_func,
                    snippet=stripped[:120],
                    recommendation='Wrap with parseFloat($(selector).val()) or Number()',
                ))

            # GRD-JS-002: Currency variable assigned without toFixed
            if re_currency_no_fixed.search(stripped) and not re_to_fixed.search(stripped):
                # Check next 2 lines for toFixed
                next_lines = '\n'.join(lines[i:i+3])
                if not re_to_fixed.search(next_lines):
                    violations.append(GuardViolation(
                        rule_id='GRD-JS-002',
                        severity='MEDIUM',
                        title='Currency calculation without toFixed(2)',
                        description=(
                            'Financial variable assigned from arithmetic without toFixed(2). '
                            'This can produce floating-point artifacts like 10.00000000001.'
                        ),
                        file=file, line=lineno, function=current_func,
                        snippet=stripped[:120],
                        recommendation='Apply .toFixed(2) on the final result',
                    ))

            # GRD-JS-003: parseFloat without isNaN guard
            if re_parsefloat.search(stripped) and '=' in stripped:
                # Check surrounding 5 lines for isNaN
                context = '\n'.join(lines[max(0, i-2):i+5])
                if not re_isnan.search(context):
                    violations.append(GuardViolation(
                        rule_id='GRD-JS-003',
                        severity='MEDIUM',
                        title='parseFloat/Number without isNaN guard',
                        description=(
                            'parseFloat() on user input can return NaN which propagates '
                            'through all subsequent calculations silently.'
                        ),
                        file=file, line=lineno, function=current_func,
                        snippet=stripped[:120],
                        recommendation='Add: if (isNaN(value)) value = 0;',
                    ))

            # GRD-JS-004: AJAX without error handler
            if re_ajax.search(stripped):
                in_ajax = True
                ajax_has_error = False
                ajax_start_line = lineno

            if in_ajax:
                if re_ajax_error.search(stripped):
                    ajax_has_error = True
                # Track brace depth to detect end of AJAX block
                brace_depth += stripped.count('{') - stripped.count('}')
                if brace_depth <= 0 and lineno > ajax_start_line:
                    if not ajax_has_error:
                        violations.append(GuardViolation(
                            rule_id='GRD-JS-004',
                            severity='MEDIUM',
                            title='AJAX call without error handler',
                            description=(
                                'AJAX call has no error/fail/catch handler. '
                                'Network failures will silently leave the user waiting.'
                            ),
                            file=file, line=ajax_start_line, function=current_func,
                            snippet=lines[ajax_start_line - 1].strip()[:120],
                            recommendation='Add error: function(xhr) { ... } to $.ajax()',
                        ))
                    in_ajax = False
                    brace_depth = 0

            # GRD-JS-005: Hardcoded URL in AJAX
            if re_hardcoded_url.search(stripped) and not re_base_url.search(stripped):
                violations.append(GuardViolation(
                    rule_id='GRD-JS-005',
                    severity='LOW',
                    title='Hardcoded URL in AJAX call',
                    description=(
                        'AJAX URL is hardcoded instead of using base_url variable. '
                        'This breaks when the app moves to a different subdirectory.'
                    ),
                    file=file, line=lineno, function=current_func,
                    snippet=stripped[:120],
                    recommendation='Use base_url + "/controller/method" instead',
                ))

            # GRD-JS-006: Division without zero check
            if '/' in stripped and '/*' not in stripped and '//' not in stripped:
                m = re_divide.search(stripped)
                if m and m.group(2) not in ('100', '2', '12', '1000', 'length', 'PI'):
                    context_above = '\n'.join(lines[max(0, i-3):i])
                    if not re_divide_check.search(context_above) and m.group(2) not in ('null', 'undefined', 'true', 'false'):
                        # Only flag if divisor looks like a variable (not a constant)
                        divisor = m.group(2)
                        if divisor[0].isalpha() and not divisor.isupper():
                            violations.append(GuardViolation(
                                rule_id='GRD-JS-006',
                                severity='MEDIUM',
                                title='Division without zero-check',
                                description=(
                                    f'Division by variable "{divisor}" without checking for zero. '
                                    'This can produce Infinity or NaN.'
                                ),
                                file=file, line=lineno, function=current_func,
                                snippet=stripped[:120],
                                recommendation=f'Add: if ({divisor} != 0) before division',
                            ))

        return violations

    # ── PHP Guards ────────────────────────────────────────────────────

    def _scan_php(self, lines: list[str], file: str) -> list[GuardViolation]:
        violations: list[GuardViolation] = []
        current_func = 'GLOBAL'
        current_class = ''

        # Track transaction state
        has_trans_start = False
        write_tables: list[tuple[int, str]] = []  # (line, table)
        func_start_line = 0

        re_class = re.compile(r'class\s+(\w+)')
        re_func = re.compile(r'(?:public|private|protected)?\s*function\s+(\w+)')
        re_insert = re.compile(r"""\$this\s*->\s*db\s*->\s*insert\s*\(\s*['"](\w+)['"]""")
        re_update = re.compile(r"""\$this\s*->\s*db\s*->\s*update\s*\(\s*['"](\w+)['"]""")
        re_delete = re.compile(r"""\$this\s*->\s*db\s*->\s*delete\s*\(\s*['"](\w+)['"]""")
        re_trans_start = re.compile(r'trans_(?:start|begin)\s*\(')
        re_trans_end = re.compile(r'trans_(?:complete|commit|rollback)\s*\(')
        re_update_no_where = re.compile(
            r"""\$this\s*->\s*db\s*->\s*(?:update|delete)\s*\("""
        )
        re_where = re.compile(r'->\s*where\s*\(')
        re_empty_catch = re.compile(r'catch\s*\([^)]*\)\s*\{\s*\}')
        re_die_exit = re.compile(r'\b(?:die|exit)\s*\(')
        re_log_message = re.compile(r'log_message\s*\(')
        re_numeric_cast = re.compile(r'(?:intval|floatval|\(int\)|\(float\))')
        re_post_input = re.compile(r"""\$this\s*->\s*input\s*->\s*post\s*\(\s*['"](\w+)['"]""")
        re_arith_ops = re.compile(r'[\+\-\*\/]')

        for i, line in enumerate(lines):
            stripped = line.strip()
            lineno = i + 1

            # Skip comments
            if stripped.startswith('//') or stripped.startswith('#') or stripped.startswith('/*') or stripped.startswith('*'):
                continue

            # Track class
            m = re_class.search(stripped)
            if m:
                current_class = m.group(1)

            # Track function boundaries
            m = re_func.search(stripped)
            if m:
                # Check previous function for multi-table write without transaction
                if len(write_tables) >= 2 and not has_trans_start:
                    table_names = ', '.join(t[1] for t in write_tables[:5])
                    violations.append(GuardViolation(
                        rule_id='GRD-PHP-001',
                        severity='HIGH',
                        title='Multi-table write without transaction',
                        description=(
                            f'{len(write_tables)} table writes ({table_names}) in '
                            f'{current_class}::{current_func}() without trans_start()/trans_complete(). '
                            'A failure mid-write leaves the database in an inconsistent state.'
                        ),
                        file=file, line=func_start_line, function=f'{current_class}::{current_func}',
                        recommendation='Wrap with $this->db->trans_start() / $this->db->trans_complete()',
                    ))

                # Reset tracking for new function
                current_func = m.group(1)
                func_start_line = lineno
                has_trans_start = False
                write_tables = []

            # Track transaction usage
            if re_trans_start.search(stripped):
                has_trans_start = True

            # Track DB writes
            for pattern, op in [(re_insert, 'INSERT'), (re_update, 'UPDATE'), (re_delete, 'DELETE')]:
                m = pattern.search(stripped)
                if m:
                    write_tables.append((lineno, f'{op}:{m.group(1)}'))

            # GRD-PHP-002: UPDATE/DELETE without WHERE
            if re_update_no_where.search(stripped):
                # Check this line and previous 3 lines for ->where()
                context = '\n'.join(lines[max(0, i-3):i+1])
                if not re_where.search(context):
                    violations.append(GuardViolation(
                        rule_id='GRD-PHP-002',
                        severity='CRITICAL',
                        title='UPDATE/DELETE without WHERE clause',
                        description=(
                            'Database UPDATE or DELETE without a preceding ->where() call. '
                            'This will affect ALL rows in the table.'
                        ),
                        file=file, line=lineno,
                        function=f'{current_class}::{current_func}' if current_class else current_func,
                        snippet=stripped[:120],
                        recommendation='Add ->where() before ->update() or ->delete()',
                    ))

            # GRD-PHP-003: Post input in arithmetic without numeric cast
            post_inputs = re_post_input.findall(stripped)
            if post_inputs and re_arith_ops.search(stripped) and not re_numeric_cast.search(stripped):
                violations.append(GuardViolation(
                    rule_id='GRD-PHP-003',
                    severity='HIGH',
                    title='Form input in arithmetic without numeric cast',
                    description=(
                        f'$this->input->post() value used in arithmetic without intval/floatval cast. '
                        'Non-numeric input will produce unexpected results.'
                    ),
                    file=file, line=lineno,
                    function=f'{current_class}::{current_func}' if current_class else current_func,
                    snippet=stripped[:120],
                    recommendation='Wrap with floatval($this->input->post("field")) or intval()',
                ))

            # GRD-PHP-004: Empty catch block
            if re_empty_catch.search(stripped) or (
                'catch' in stripped and i + 1 < len(lines) and lines[i + 1].strip() == '}'
            ):
                violations.append(GuardViolation(
                    rule_id='GRD-PHP-004',
                    severity='MEDIUM',
                    title='Empty catch block (silent failure)',
                    description=(
                        'Exception is caught but not logged or handled. '
                        'Errors will disappear silently, making debugging impossible.'
                    ),
                    file=file, line=lineno,
                    function=f'{current_class}::{current_func}' if current_class else current_func,
                    snippet=stripped[:120],
                    recommendation="Add log_message('error', $e->getMessage()) inside the catch block",
                ))

            # GRD-PHP-005: die/exit without log_message
            if re_die_exit.search(stripped):
                context = '\n'.join(lines[max(0, i-3):i+1])
                if not re_log_message.search(context):
                    violations.append(GuardViolation(
                        rule_id='GRD-PHP-005',
                        severity='LOW',
                        title='die()/exit() without logging',
                        description=(
                            'Script termination without log_message(). '
                            'In production, the error cause will be untraceable.'
                        ),
                        file=file, line=lineno,
                        function=f'{current_class}::{current_func}' if current_class else current_func,
                        snippet=stripped[:120],
                        recommendation="Add log_message('error', ...) before die()/exit()",
                    ))

        # Check last function in file
        if len(write_tables) >= 2 and not has_trans_start:
            table_names = ', '.join(t[1] for t in write_tables[:5])
            violations.append(GuardViolation(
                rule_id='GRD-PHP-001',
                severity='HIGH',
                title='Multi-table write without transaction',
                description=(
                    f'{len(write_tables)} table writes ({table_names}) in '
                    f'{current_class}::{current_func}() without trans_start()/trans_complete().'
                ),
                file=file, line=func_start_line,
                function=f'{current_class}::{current_func}' if current_class else current_func,
                recommendation='Wrap with $this->db->trans_start() / $this->db->trans_complete()',
            ))

        return violations


# ── Rule Catalog ──────────────────────────────────────────────────────

GUARD_RULES = {
    'GRD-JS-001': {'title': 'Arithmetic without parseFloat', 'severity': 'HIGH', 'lang': 'js'},
    'GRD-JS-002': {'title': 'Currency without toFixed(2)', 'severity': 'MEDIUM', 'lang': 'js'},
    'GRD-JS-003': {'title': 'parseFloat without isNaN', 'severity': 'MEDIUM', 'lang': 'js'},
    'GRD-JS-004': {'title': 'AJAX without error handler', 'severity': 'MEDIUM', 'lang': 'js'},
    'GRD-JS-005': {'title': 'Hardcoded AJAX URL', 'severity': 'LOW', 'lang': 'js'},
    'GRD-JS-006': {'title': 'Division without zero-check', 'severity': 'MEDIUM', 'lang': 'js'},
    'GRD-PHP-001': {'title': 'Multi-table write without transaction', 'severity': 'HIGH', 'lang': 'php'},
    'GRD-PHP-002': {'title': 'UPDATE/DELETE without WHERE', 'severity': 'CRITICAL', 'lang': 'php'},
    'GRD-PHP-003': {'title': 'Form input in arithmetic without cast', 'severity': 'HIGH', 'lang': 'php'},
    'GRD-PHP-004': {'title': 'Empty catch block', 'severity': 'MEDIUM', 'lang': 'php'},
    'GRD-PHP-005': {'title': 'die/exit without logging', 'severity': 'LOW', 'lang': 'php'},
}
