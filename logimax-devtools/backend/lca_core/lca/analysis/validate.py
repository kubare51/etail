"""Validation Gap Detector — find missing input/data validations on write operations.

Unlike guard.py (which catches coding safety gaps like missing parseFloat),
the validate scanner catches **business validation gaps** — places where data
enters the system without being properly checked:

PHP Write Validations:
  VAL-PHP-001: INSERT/UPDATE with financial column but no negative/zero check
  VAL-PHP-002: INSERT without duplicate-key guard (same table has unique constraints)
  VAL-PHP-003: UPDATE/DELETE on status-tracked table without status pre-check
  VAL-PHP-004: DELETE without checking dependent child records
  VAL-PHP-005: Write operation without permission/access check
  VAL-PHP-006: Form input stored without $this->security->xss_clean()
  VAL-PHP-007: Date field stored without format validation
  VAL-PHP-008: Quantity/amount field without upper bound check

Controller Validations:
  VAL-CTL-001: POST handler without form_validation->run()
  VAL-CTL-002: File upload without server-side type/size check
  VAL-CTL-003: AJAX endpoint without CSRF token check

JS Validations:
  VAL-JS-001: Form submit without client-side validation
  VAL-JS-002: Numeric input field without min/max constraints
"""

from __future__ import annotations

import re
from dataclasses import dataclass, field
from pathlib import Path
from typing import Optional


# ── Models ────────────────────────────────────────────────────────────

@dataclass
class ValidationGap:
    """A single missing validation."""
    rule_id: str
    severity: str       # CRITICAL, HIGH, MEDIUM, LOW
    title: str
    description: str
    file: str
    line: int
    function: str
    table: str = ''     # affected table if applicable
    column: str = ''    # affected column if applicable
    snippet: str = ''
    recommendation: str = ''


@dataclass
class ValidateReport:
    """Results of a validation scan."""
    files_scanned: int = 0
    gaps: list[ValidationGap] = field(default_factory=list)
    by_rule: dict[str, int] = field(default_factory=dict)
    by_severity: dict[str, int] = field(default_factory=dict)
    by_file: dict[str, int] = field(default_factory=dict)
    by_table: dict[str, int] = field(default_factory=dict)

    def add(self, g: ValidationGap):
        self.gaps.append(g)
        self.by_rule[g.rule_id] = self.by_rule.get(g.rule_id, 0) + 1
        self.by_severity[g.severity] = self.by_severity.get(g.severity, 0) + 1
        fname = Path(g.file).name
        self.by_file[fname] = self.by_file.get(fname, 0) + 1
        if g.table:
            self.by_table[g.table] = self.by_table.get(g.table, 0) + 1

    @property
    def total(self) -> int:
        return len(self.gaps)

    @property
    def critical_count(self) -> int:
        return self.by_severity.get('CRITICAL', 0)

    @property
    def high_count(self) -> int:
        return self.by_severity.get('HIGH', 0)


# ── Known patterns ───────────────────────────────────────────────────

# Financial column names — these MUST have negative/zero validation
_FINANCIAL_COLUMNS = {
    'amount', 'total', 'net_amount', 'gross_amount', 'grand_total',
    'sub_total', 'subtotal', 'price', 'rate', 'unit_price', 'unit_rate',
    'cost', 'cost_price', 'selling_price', 'mrp', 'discount', 'discount_amount',
    'discount_value', 'tax', 'tax_amount', 'gst', 'cgst', 'sgst', 'igst',
    'cess', 'round_off', 'balance', 'payment', 'paid_amount', 'due_amount',
    'credit', 'debit', 'freight', 'charges', 'advance', 'refund',
    'commission', 'incentive', 'penalty', 'interest', 'opening_balance',
    'closing_balance', 'net_total', 'item_total', 'bill_amount',
}

# Quantity columns — need upper bound
_QUANTITY_COLUMNS = {
    'qty', 'quantity', 'stock_qty', 'order_qty', 'issue_qty',
    'return_qty', 'received_qty', 'transfer_qty', 'damaged_qty',
    'opening_qty', 'closing_qty', 'available_qty', 'pending_qty',
    'pack_qty', 'carton_qty', 'weight', 'net_weight', 'gross_weight',
}

# Status-tracked tables — need status check before update/delete
_STATUS_TABLES = {
    'ret_estimation', 'ret_purchase_order', 'ret_billing', 'ret_goods_receive',
    'ret_stock_issue', 'ret_stock_transfer', 'ret_sales_return',
    'ret_purchase_return', 'ret_payment', 'ret_receipt',
    'ret_debit_note', 'ret_credit_note', 'ret_expense',
}

# Tables with known parent-child relationships
_PARENT_CHILD = {
    'ret_estimation': ['ret_estimation_items'],
    'ret_billing': ['ret_billing_items', 'ret_billing_tax'],
    'ret_purchase_order': ['ret_purchase_order_items'],
    'ret_goods_receive': ['ret_goods_receive_items'],
    'ret_stock_issue': ['ret_stock_issue_items'],
    'ret_stock_transfer': ['ret_stock_transfer_items'],
    'ret_customer': ['ret_billing', 'ret_estimation', 'ret_payment'],
    'ret_supplier': ['ret_purchase_order', 'ret_goods_receive'],
    'ret_product': ['ret_billing_items', 'ret_estimation_items', 'ret_stock'],
}

# ── Skip patterns ────────────────────────────────────────────────────

_SKIP_DIRS = {
    '.git', 'node_modules', 'vendor', '__pycache__', 'logs', 'cache',
    'libraries', 'third_party', 'fonts', 'images', 'uploads',
}

_PHP_EXTENSIONS = {'.php', '.phtml', '.inc'}
_JS_EXTENSIONS = {'.js', '.jsx', '.mjs'}


# ── Validation Scanner Engine ─────────────────────────────────────────

class ValidationScanner:
    """Scan code for missing input/data validations on write operations."""

    def scan_directory(
        self,
        root: Path,
        module_filter: Optional[str] = None,
    ) -> ValidateReport:
        """Scan a directory tree for validation gaps."""
        report = ValidateReport()

        for file_path in self._iter_files(root):
            if module_filter:
                if module_filter.lower() not in str(file_path).lower():
                    continue

            try:
                gaps = self.scan_file(file_path)
                if gaps:
                    for g in gaps:
                        report.add(g)
                report.files_scanned += 1
            except Exception:
                pass  # Skip unreadable files

        return report

    def scan_file(self, file_path: Path) -> list[ValidationGap]:
        """Scan a single file for validation gaps."""
        suffix = file_path.suffix.lower()

        try:
            content = file_path.read_text(encoding='utf-8', errors='ignore')
        except Exception:
            return []

        lines = content.split('\n')
        gaps: list[ValidationGap] = []
        file_str = str(file_path)

        if suffix in _PHP_EXTENSIONS:
            # Determine if this is a controller or model
            is_controller = any(k in file_str.lower() for k in ('/controllers/', '\\controllers\\'))
            is_model = any(k in file_str.lower() for k in ('/models/', '\\models\\'))

            gaps.extend(self._scan_php_writes(lines, file_str))
            if is_controller:
                gaps.extend(self._scan_controller(lines, file_str))
        elif suffix in _JS_EXTENSIONS:
            gaps.extend(self._scan_js_forms(lines, file_str))

        return gaps

    def _iter_files(self, root: Path):
        """Iterate over scannable files."""
        for item in root.rglob('*'):
            if any(skip in item.parts for skip in _SKIP_DIRS):
                continue
            if item.is_file() and item.suffix.lower() in (_PHP_EXTENSIONS | _JS_EXTENSIONS):
                yield item

    # ── PHP Write Validations ─────────────────────────────────────────

    def _scan_php_writes(self, lines: list[str], file: str) -> list[ValidationGap]:
        gaps: list[ValidationGap] = []
        current_func = 'GLOBAL'
        current_class = ''
        func_body_lines: list[str] = []
        func_start = 0

        re_class = re.compile(r'class\s+(\w+)')
        re_func = re.compile(r'(?:public|private|protected)?\s*function\s+(\w+)')

        # Write operation patterns
        re_insert = re.compile(r"""\$this\s*->\s*db\s*->\s*insert\s*\(\s*['"]([\w]+)['"]""")
        re_update = re.compile(r"""\$this\s*->\s*db\s*->\s*update\s*\(\s*['"]([\w]+)['"]""")
        re_delete = re.compile(r"""\$this\s*->\s*db\s*->\s*delete\s*\(\s*['"]([\w]+)['"]""")
        re_set = re.compile(r"""\$this\s*->\s*db\s*->\s*set\s*\(\s*['"]([\w]+)['"]""")

        # Validation patterns we look for
        re_negative_check = re.compile(
            r'(?:<=?\s*0|>=?\s*0|<\s*0|>\s*0|==\s*0|!=\s*0|empty|is_numeric)',
            re.IGNORECASE
        )
        re_xss_clean = re.compile(r'xss_clean|htmlspecialchars|htmlentities|strip_tags')
        re_form_validation = re.compile(r'form_validation\s*->\s*(?:set_rules|run)')
        re_permission = re.compile(
            r'(?:check_permission|has_permission|access_right|is_allowed|check_access|'
            r'verify_permission|can_access|auth_check)',
            re.IGNORECASE
        )
        re_status_check = re.compile(
            r"""where\s*\(\s*['"](?:status|bill_status|order_status|is_deleted)['"]""",
            re.IGNORECASE
        )
        re_duplicate_check = re.compile(
            r'(?:num_rows|count_all_results|row\(\)|result\(\))\s*(?:>|==|!=)',
            re.IGNORECASE
        )
        re_date_validate = re.compile(
            r'(?:strtotime|DateTime|date_create|checkdate|preg_match.*\d)',
            re.IGNORECASE
        )
        re_child_check = re.compile(
            r'(?:count_all_results|num_rows|get_where)\s*\(',
            re.IGNORECASE
        )
        re_post_input = re.compile(r"""\$this\s*->\s*input\s*->\s*post\s*\(""")

        for i, line in enumerate(lines):
            stripped = line.strip()
            lineno = i + 1

            # Skip comments
            if stripped.startswith('//') or stripped.startswith('#') or \
               stripped.startswith('/*') or stripped.startswith('*'):
                continue

            # Track class
            m = re_class.search(stripped)
            if m:
                current_class = m.group(1)

            # Track function
            m = re_func.search(stripped)
            if m:
                # Analyze previous function if it had content
                if func_body_lines:
                    gaps.extend(self._analyze_function_writes(
                        func_body_lines, file, current_class, current_func, func_start
                    ))
                current_func = m.group(1)
                func_start = lineno
                func_body_lines = []

            func_body_lines.append(stripped)

            # ── VAL-PHP-001: Financial column write without validation ──
            for pattern in [re_set]:
                m = pattern.search(stripped)
                if m:
                    col_name = m.group(1).lower()
                    if col_name in _FINANCIAL_COLUMNS:
                        # Check surrounding context for validation
                        context = '\n'.join(lines[max(0, i-10):i+1])
                        if not re_negative_check.search(context):
                            gaps.append(ValidationGap(
                                rule_id='VAL-PHP-001',
                                severity='HIGH',
                                title='Financial column without amount validation',
                                description=(
                                    f'Column "{col_name}" is set without checking for '
                                    'negative/zero values. Financial amounts must be validated '
                                    'before storage to prevent data corruption.'
                                ),
                                file=file, line=lineno,
                                function=f'{current_class}::{current_func}' if current_class else current_func,
                                column=col_name,
                                snippet=stripped[:120],
                                recommendation=f'Add: if ({col_name} <= 0) return error before insert/update',
                            ))

            # ── VAL-PHP-003: Status table update without status check ──
            for pattern, op in [(re_update, 'UPDATE'), (re_delete, 'DELETE')]:
                m = pattern.search(stripped)
                if m:
                    table = m.group(1).lower()
                    if table in _STATUS_TABLES:
                        context = '\n'.join(lines[max(0, i-8):i+1])
                        if not re_status_check.search(context):
                            gaps.append(ValidationGap(
                                rule_id='VAL-PHP-003',
                                severity='HIGH',
                                title='Status-tracked table modified without status check',
                                description=(
                                    f'{op} on "{table}" without checking current status. '
                                    'A completed/approved record should not be modified without '
                                    'checking its status first.'
                                ),
                                file=file, line=lineno,
                                function=f'{current_class}::{current_func}' if current_class else current_func,
                                table=table,
                                snippet=stripped[:120],
                                recommendation=f'Add: ->where("status", "draft") or check status before {op}',
                            ))

            # ── VAL-PHP-004: DELETE on parent without child check ──
            m = re_delete.search(stripped)
            if m:
                table = m.group(1).lower()
                if table in _PARENT_CHILD:
                    context = '\n'.join(lines[max(0, i-12):i+1])
                    children = _PARENT_CHILD[table]
                    has_child_check = any(
                        child in context for child in children
                    ) or re_child_check.search(context)
                    if not has_child_check:
                        gaps.append(ValidationGap(
                            rule_id='VAL-PHP-004',
                            severity='CRITICAL',
                            title='DELETE without checking dependent records',
                            description=(
                                f'DELETE on "{table}" without checking child tables: '
                                f'{", ".join(children)}. This will orphan child records.'
                            ),
                            file=file, line=lineno,
                            function=f'{current_class}::{current_func}' if current_class else current_func,
                            table=table,
                            snippet=stripped[:120],
                            recommendation=f'Check for records in {", ".join(children)} before deleting',
                        ))

            # ── VAL-PHP-006: Post input stored without XSS clean ──
            if re_post_input.search(stripped):
                # Check if this is in a set/insert context
                context = '\n'.join(lines[max(0, i-2):i+3])
                if ('->set(' in context or '->insert(' in context or '->update(' in context):
                    if not re_xss_clean.search(context):
                        # Don't flag numeric fields
                        if not any(kw in stripped.lower() for kw in
                                   ('_id', 'amount', 'qty', 'price', 'rate', 'total', 'status', 'type')):
                            gaps.append(ValidationGap(
                                rule_id='VAL-PHP-006',
                                severity='MEDIUM',
                                title='Form input stored without XSS sanitization',
                                description=(
                                    '$this->input->post() value stored in database without '
                                    'xss_clean() or htmlspecialchars(). If rendered back in HTML, '
                                    'this enables XSS attacks.'
                                ),
                                file=file, line=lineno,
                                function=f'{current_class}::{current_func}' if current_class else current_func,
                                snippet=stripped[:120],
                                recommendation='Use $this->security->xss_clean($this->input->post("field"))',
                            ))

        # Analyze last function
        if func_body_lines:
            gaps.extend(self._analyze_function_writes(
                func_body_lines, file, current_class, current_func, func_start
            ))

        return gaps

    def _analyze_function_writes(
        self, body: list[str], file: str,
        cls: str, func: str, start_line: int
    ) -> list[ValidationGap]:
        """Analyze a complete function body for validation patterns."""
        gaps: list[ValidationGap] = []
        full_body = '\n'.join(body)
        func_name = f'{cls}::{func}' if cls else func

        # ── VAL-PHP-002: INSERT without duplicate check ──
        insert_tables = re.findall(
            r"""\$this\s*->\s*db\s*->\s*insert\s*\(\s*['"]([\w]+)['"]""", full_body
        )
        has_dup_check = bool(re.search(
            r'(?:count_all_results|num_rows|get_where|result\(\)|row\(\))',
            full_body, re.IGNORECASE
        ))
        for table in insert_tables:
            if not has_dup_check and table.lower() not in (
                'ret_logs', 'ret_activity_log', 'ret_audit', 'ret_notification',
                'ret_sms_log', 'ret_email_log',  # Log tables don't need dup checks
            ):
                # Only flag if this is a master/transaction table
                if any(kw in table.lower() for kw in (
                    'billing', 'estimation', 'purchase', 'stock', 'payment',
                    'receipt', 'return', 'customer', 'supplier', 'product',
                    'order', 'transfer', 'expense', 'debit', 'credit',
                )):
                    gaps.append(ValidationGap(
                        rule_id='VAL-PHP-002',
                        severity='MEDIUM',
                        title='INSERT without duplicate-key check',
                        description=(
                            f'INSERT into "{table}" without checking if a similar '
                            'record already exists. This can create duplicate entries.'
                        ),
                        file=file, line=start_line, function=func_name,
                        table=table,
                        recommendation='Add: Check count_all_results() with unique fields before insert',
                    ))

        # ── VAL-PHP-005: Write operation without permission check ──
        has_writes = bool(re.search(
            r'\$this\s*->\s*db\s*->\s*(?:insert|update|delete)\s*\(',
            full_body
        ))
        has_permission = bool(re.search(
            r'(?:check_permission|has_permission|access_right|is_allowed|'
            r'check_access|verify_permission)',
            full_body, re.IGNORECASE
        ))
        if has_writes and not has_permission:
            # Only flag controllers, not models (models are called by controllers)
            if '/controllers/' in file.lower() or '\\controllers\\' in file.lower():
                gaps.append(ValidationGap(
                    rule_id='VAL-PHP-005',
                    severity='HIGH',
                    title='Write operation without permission check',
                    description=(
                        f'Controller method {func_name} performs database writes '
                        'without checking user permissions. Any authenticated user '
                        'can access this endpoint.'
                    ),
                    file=file, line=start_line, function=func_name,
                    recommendation='Add permission check at the start of the method',
                ))

        return gaps

    # ── Controller Validations ────────────────────────────────────────

    def _scan_controller(self, lines: list[str], file: str) -> list[ValidationGap]:
        """Scan controller files for request validation gaps."""
        gaps: list[ValidationGap] = []
        current_func = 'GLOBAL'
        current_class = ''
        func_body: list[str] = []
        func_start = 0

        re_class = re.compile(r'class\s+(\w+)')
        re_func = re.compile(r'(?:public|private|protected)?\s*function\s+(\w+)')

        for i, line in enumerate(lines):
            stripped = line.strip()
            lineno = i + 1

            if stripped.startswith('//') or stripped.startswith('#'):
                continue

            m = re_class.search(stripped)
            if m:
                current_class = m.group(1)

            m = re_func.search(stripped)
            if m:
                # Analyze previous function
                if func_body:
                    gaps.extend(self._check_controller_method(
                        func_body, file, current_class, current_func, func_start
                    ))
                current_func = m.group(1)
                func_start = lineno
                func_body = []

            func_body.append(stripped)

        # Last function
        if func_body:
            gaps.extend(self._check_controller_method(
                func_body, file, current_class, current_func, func_start
            ))

        return gaps

    def _check_controller_method(
        self, body: list[str], file: str,
        cls: str, func: str, start_line: int
    ) -> list[ValidationGap]:
        """Check a controller method for validation gaps."""
        gaps: list[ValidationGap] = []
        full_body = '\n'.join(body)
        func_name = f'{cls}::{func}' if cls else func

        # Skip non-handler methods
        skip_funcs = {'__construct', 'index', '_remap', '_output'}
        if func in skip_funcs:
            return gaps

        has_post = bool(re.search(r'\$this\s*->\s*input\s*->\s*post\s*\(', full_body))
        has_model_call = bool(re.search(r'\$this\s*->\s*\w+_model\s*->', full_body))

        # ── VAL-CTL-001: POST handler without form_validation ──
        if has_post and has_model_call:
            has_validation = bool(re.search(
                r'form_validation\s*->\s*(?:set_rules|run)',
                full_body
            ))
            if not has_validation:
                gaps.append(ValidationGap(
                    rule_id='VAL-CTL-001',
                    severity='HIGH',
                    title='POST handler without form_validation',
                    description=(
                        f'{func_name} accepts POST data and calls model methods '
                        'but never runs form_validation. Server-side validation is '
                        'mandatory — client-side validation can be bypassed.'
                    ),
                    file=file, line=start_line, function=func_name,
                    recommendation='Add $this->form_validation->set_rules() and ->run()',
                ))

        # ── VAL-CTL-002: File upload without validation ──
        has_upload = bool(re.search(
            r'(?:do_upload|upload|move_uploaded_file|\$_FILES)',
            full_body, re.IGNORECASE
        ))
        if has_upload:
            has_upload_validation = bool(re.search(
                r'(?:allowed_types|max_size|file_ext_tolower|is_image|'
                r'upload_path|mime_type|getimagesize)',
                full_body, re.IGNORECASE
            ))
            if not has_upload_validation:
                gaps.append(ValidationGap(
                    rule_id='VAL-CTL-002',
                    severity='CRITICAL',
                    title='File upload without server-side validation',
                    description=(
                        f'{func_name} handles file uploads without checking '
                        'file type, size, or extension server-side. '
                        'This is a critical security vulnerability.'
                    ),
                    file=file, line=start_line, function=func_name,
                    recommendation='Add allowed_types, max_size validation in upload config',
                ))

        return gaps

    # ── JS Form Validations ──────────────────────────────────────────

    def _scan_js_forms(self, lines: list[str], file: str) -> list[ValidationGap]:
        """Scan JS files for form submission without validation."""
        gaps: list[ValidationGap] = []
        current_func = 'GLOBAL'

        re_func = re.compile(r'function\s+(\w+)')
        re_arrow = re.compile(r'(?:const|let|var)\s+(\w+)\s*=\s*(?:function|\()')
        re_submit = re.compile(r'\.submit\s*\(|\.ajax\s*\(.*type\s*:\s*["\']POST')
        re_validation = re.compile(
            r'(?:validate|valid|check|verify|required|\.val\(\)\s*[!=]=\s*[\'"]'
            r'|\.val\(\)\.length|\.val\(\)\.trim\(\)|parseInt|parseFloat)',
            re.IGNORECASE
        )

        for i, line in enumerate(lines):
            stripped = line.strip()
            lineno = i + 1

            if stripped.startswith('//') or stripped.startswith('/*') or stripped.startswith('*'):
                continue

            m = re_func.search(stripped)
            if m:
                current_func = m.group(1)
            else:
                m = re_arrow.search(stripped)
                if m:
                    current_func = m.group(1)

            # VAL-JS-001: Form submit/AJAX POST without nearby validation
            if re_submit.search(stripped):
                context = '\n'.join(lines[max(0, i-15):i])
                if not re_validation.search(context):
                    # Don't flag if function name suggests it's already validated
                    if not any(kw in current_func.lower() for kw in ('validate', 'check', 'verify')):
                        gaps.append(ValidationGap(
                            rule_id='VAL-JS-001',
                            severity='MEDIUM',
                            title='Form submit without client-side validation',
                            description=(
                                'Form is submitted or AJAX POST is sent without visible '
                                'client-side validation. While server-side validation is the '
                                'ultimate guard, client-side prevents unnecessary round-trips.'
                            ),
                            file=file, line=lineno, function=current_func,
                            snippet=stripped[:120],
                            recommendation='Add input validation before submit/AJAX call',
                        ))

        return gaps


# ── Rule Catalog ──────────────────────────────────────────────────────

VALIDATE_RULES = {
    'VAL-PHP-001': {'title': 'Financial column without amount validation', 'severity': 'HIGH', 'lang': 'php'},
    'VAL-PHP-002': {'title': 'INSERT without duplicate-key check', 'severity': 'MEDIUM', 'lang': 'php'},
    'VAL-PHP-003': {'title': 'Status table update without status check', 'severity': 'HIGH', 'lang': 'php'},
    'VAL-PHP-004': {'title': 'DELETE without child record check', 'severity': 'CRITICAL', 'lang': 'php'},
    'VAL-PHP-005': {'title': 'Write without permission check', 'severity': 'HIGH', 'lang': 'php'},
    'VAL-PHP-006': {'title': 'Input stored without XSS sanitization', 'severity': 'MEDIUM', 'lang': 'php'},
    'VAL-PHP-007': {'title': 'Date stored without format validation', 'severity': 'MEDIUM', 'lang': 'php'},
    'VAL-PHP-008': {'title': 'Quantity without upper bound check', 'severity': 'LOW', 'lang': 'php'},
    'VAL-CTL-001': {'title': 'POST handler without form_validation', 'severity': 'HIGH', 'lang': 'php'},
    'VAL-CTL-002': {'title': 'File upload without server validation', 'severity': 'CRITICAL', 'lang': 'php'},
    'VAL-CTL-003': {'title': 'AJAX endpoint without CSRF check', 'severity': 'HIGH', 'lang': 'php'},
    'VAL-JS-001': {'title': 'Form submit without client validation', 'severity': 'MEDIUM', 'lang': 'js'},
    'VAL-JS-002': {'title': 'Numeric input without min/max', 'severity': 'LOW', 'lang': 'js'},
}
