"""Client Profile Detector - scan codebase to identify client configuration.

Detects which modules, features, and integrations a client deployment uses
by analyzing code patterns, config files, view files, route definitions,
and database table references.

Output: A structured client profile showing:
  - Active modules (billing, estimation, purchase, stock, etc.)
  - Feature flags (GST, multi-branch, multi-company, POS, e-invoice, etc.)
  - Integrations (Razorpay, Tally, WhatsApp, SMS, email)
  - Customizations (custom controllers, overridden views)
  - Database footprint (table counts by module)
  - Print templates (bill, estimation, receipt, etc.)
"""

from __future__ import annotations

import re
from dataclasses import dataclass, field
from pathlib import Path
from typing import Optional


# ── Models ────────────────────────────────────────────────────────────

@dataclass
class ModuleInfo:
    """A detected module in the client deployment."""
    name: str
    active: bool = False
    controllers: list[str] = field(default_factory=list)
    models: list[str] = field(default_factory=list)
    views: list[str] = field(default_factory=list)
    js_files: list[str] = field(default_factory=list)
    tables: list[str] = field(default_factory=list)
    routes: list[str] = field(default_factory=list)
    line_count: int = 0


@dataclass
class FeatureFlag:
    """A detected feature toggle or capability."""
    name: str
    detected: bool = False
    evidence: list[str] = field(default_factory=list)
    confidence: str = 'LOW'  # LOW, MEDIUM, HIGH


@dataclass
class Integration:
    """A detected external integration."""
    name: str
    type: str  # payment, accounting, communication, api
    detected: bool = False
    evidence: list[str] = field(default_factory=list)


@dataclass
class ClientProfile:
    """Complete client deployment profile."""
    project_root: str = ''
    scan_date: str = ''
    modules: dict[str, ModuleInfo] = field(default_factory=dict)
    features: dict[str, FeatureFlag] = field(default_factory=dict)
    integrations: dict[str, Integration] = field(default_factory=dict)
    print_templates: list[str] = field(default_factory=list)
    custom_files: list[str] = field(default_factory=list)
    total_controllers: int = 0
    total_models: int = 0
    total_views: int = 0
    total_js_files: int = 0
    total_php_lines: int = 0
    total_js_lines: int = 0

    @property
    def active_modules(self) -> list[str]:
        return [m.name for m in self.modules.values() if m.active]

    @property
    def active_features(self) -> list[str]:
        return [f.name for f in self.features.values() if f.detected]

    @property
    def active_integrations(self) -> list[str]:
        return [i.name for i in self.integrations.values() if i.detected]


# ── Module Definitions ───────────────────────────────────────────────

# Map: module_name -> (controller_patterns, model_patterns, table_prefixes, js_patterns)
_MODULE_DEFS = {
    'Billing': {
        'controllers': ['billing', 'invoice', 'bill_print'],
        'models': ['ret_billing_model', 'billing_model'],
        'tables': ['ret_billing', 'ret_billing_items', 'ret_billing_tax'],
        'js': ['ret_billing', 'billing'],
    },
    'Estimation': {
        'controllers': ['estimation', 'estimate'],
        'models': ['ret_estimation_model', 'estimation_model'],
        'tables': ['ret_estimation', 'ret_estimation_items'],
        'js': ['ret_estimation', 'estimation'],
    },
    'Purchase': {
        'controllers': ['purchase', 'purchase_order', 'purchase_approval'],
        'models': ['ret_purchase_model', 'purchase_model', 'ret_purchase_approval_model'],
        'tables': ['ret_purchase_order', 'ret_purchase_order_items'],
        'js': ['ret_purchase', 'purchase'],
    },
    'Goods Receive': {
        'controllers': ['goods_receive', 'grn'],
        'models': ['ret_goods_receive_model'],
        'tables': ['ret_goods_receive', 'ret_goods_receive_items'],
        'js': ['ret_goods_receive', 'goods_receive'],
    },
    'Stock': {
        'controllers': ['stock', 'stock_issue', 'stock_transfer', 'inventory'],
        'models': ['ret_stock_model', 'ret_stock_issue_model', 'ret_sales_transfer_model'],
        'tables': ['ret_stock', 'ret_stock_issue', 'ret_stock_transfer'],
        'js': ['ret_stock', 'stock_issue', 'stock_transfer'],
    },
    'Payment / Chit': {
        'controllers': ['payment', 'chit', 'chit_transaction', 'scheme'],
        'models': ['payment_model', 'chit_model', 'chit_transaction_model'],
        'tables': ['ret_payment', 'chit_scheme', 'scheme_account'],
        'js': ['ret_payment', 'chit', 'scheme'],
    },
    'Customer': {
        'controllers': ['customer', 'customer_master'],
        'models': ['customer_model', 'ret_customer_model'],
        'tables': ['ret_customer', 'customer'],
        'js': ['customer'],
    },
    'Supplier': {
        'controllers': ['supplier'],
        'models': ['supplier_model', 'ret_supplier_model'],
        'tables': ['ret_supplier', 'supplier'],
        'js': ['supplier'],
    },
    'Product / Tag': {
        'controllers': ['product', 'tag', 'barcode', 'tag_master'],
        'models': ['product_model', 'ret_product_model', 'tag_model'],
        'tables': ['ret_product', 'ret_tag', 'product', 'tag_master'],
        'js': ['product', 'tag', 'barcode'],
    },
    'Reports': {
        'controllers': ['reports', 'report', 'ret_reports', 'analytics'],
        'models': ['ret_reports_model', 'reports_model'],
        'tables': [],
        'js': ['reports', 'ret_reports'],
    },
    'LOT / Manufacturing': {
        'controllers': ['lot', 'manufacturing', 'work_order', 'job_work'],
        'models': ['lot_model', 'manufacturing_model', 'job_work_model'],
        'tables': ['lot', 'lot_items', 'work_order', 'job_work'],
        'js': ['lot', 'manufacturing', 'job_work'],
    },
    'Accounting / Ledger': {
        'controllers': ['ledger', 'accounting', 'journal', 'daybook'],
        'models': ['ledger_model', 'accounting_model'],
        'tables': ['ret_ledger', 'ledger_entry', 'journal'],
        'js': ['ledger', 'daybook'],
    },
    'Sales Return': {
        'controllers': ['sales_return', 'return'],
        'models': ['ret_sales_return_model', 'sales_return_model'],
        'tables': ['ret_sales_return', 'ret_sales_return_items'],
        'js': ['sales_return'],
    },
    'Purchase Return': {
        'controllers': ['purchase_return'],
        'models': ['ret_purchase_return_model'],
        'tables': ['ret_purchase_return'],
        'js': ['purchase_return'],
    },
    'E-Commerce': {
        'controllers': ['ecommerce', 'online_store', 'web_order'],
        'models': ['ecommerce_model', 'web_order_model'],
        'tables': ['web_order', 'ecommerce'],
        'js': ['ecommerce', 'online_store'],
    },
    'Admin / Settings': {
        'controllers': ['admin', 'settings', 'admin_settings'],
        'models': ['admin_settings_model', 'access_right_model'],
        'tables': ['settings', 'access_right', 'employee', 'branch'],
        'js': ['admin_settings'],
    },
    'Print Designer': {
        'controllers': ['print_designer', 'receipt_designer'],
        'models': ['print_designer_model'],
        'tables': ['print_template', 'receipt_template'],
        'js': ['print_designer'],
    },
}

# ── Feature Detection Patterns ───────────────────────────────────────

_FEATURE_PATTERNS = {
    'GST (Tax)': {
        'code': [r'cgst|sgst|igst|gst_rate|gst_amount|hsn_code|gstin'],
        'files': ['gst', 'tax'],
        'confidence': 'HIGH',
    },
    'E-Invoice (IRN)': {
        'code': [r'irn|e_invoice|einvoice|generateIRN|cancelIRN|ack_no'],
        'files': ['irn', 'e_invoice', 'einvoice'],
        'confidence': 'HIGH',
    },
    'E-Way Bill': {
        'code': [r'eway|e_way|ewaybill|eway_bill_no'],
        'files': ['eway', 'e_way'],
        'confidence': 'HIGH',
    },
    'Multi-Branch': {
        'code': [r'id_branch|branch_id|login_branches|branch_wise'],
        'files': [],
        'confidence': 'MEDIUM',
    },
    'Multi-Company': {
        'code': [r'id_company|company_settings|company_wise'],
        'files': [],
        'confidence': 'MEDIUM',
    },
    'POS Mode': {
        'code': [r'pos_mode|is_pos|pos_billing|pos_counter|pos_print'],
        'files': ['pos'],
        'confidence': 'HIGH',
    },
    'Barcode / RFID': {
        'code': [r'barcode|rfid|tag_no|scan_tag|barcode_print'],
        'files': ['barcode', 'rfid'],
        'confidence': 'MEDIUM',
    },
    'HUID (Hallmark)': {
        'code': [r'huid|hallmark|HUID|hallmark_uid'],
        'files': ['huid', 'hallmark'],
        'confidence': 'HIGH',
    },
    'Metal Exchange': {
        'code': [r'old_gold|exchange|metal_exchange|exchange_weight|old_silver'],
        'files': ['exchange', 'old_gold'],
        'confidence': 'MEDIUM',
    },
    'Approval Workflow': {
        'code': [r'approval|approve_status|pending_approval|approved_by'],
        'files': ['approval'],
        'confidence': 'MEDIUM',
    },
    'Notifications (Push)': {
        'code': [r'fcm|firebase|push_notification|notification_token'],
        'files': ['notification', 'fcm'],
        'confidence': 'HIGH',
    },
    'SMS Integration': {
        'code': [r'send_sms|sms_api|sms_template|msg91|textlocal|twilio'],
        'files': ['sms'],
        'confidence': 'HIGH',
    },
    'WhatsApp': {
        'code': [r'whatsapp|wati|whatsapp_api|wa_template'],
        'files': ['whatsapp', 'wati'],
        'confidence': 'HIGH',
    },
    'Email': {
        'code': [r'send_email|email_template|smtp|mailer|phpmailer'],
        'files': ['email', 'mailer'],
        'confidence': 'MEDIUM',
    },
    'Credit Collection': {
        'code': [r'credit_collection|credit_bill|credit_amount|outstanding'],
        'files': ['credit'],
        'confidence': 'MEDIUM',
    },
    'Order Booking': {
        'code': [r'order_booking|book_order|order_advance|order_delivery'],
        'files': ['order'],
        'confidence': 'MEDIUM',
    },
    'Weight Machine': {
        'code': [r'weight_machine|scale_port|serial_port|com_port|weighing'],
        'files': ['weight_machine', 'scale'],
        'confidence': 'HIGH',
    },
    'Tally Integration': {
        'code': [r'tally|tally_export|tally_xml|tally_sync'],
        'files': ['tally'],
        'confidence': 'HIGH',
    },
    'Razorpay': {
        'code': [r'razorpay|rzp_key|razorpay_order|razorpay_payment'],
        'files': ['razorpay'],
        'confidence': 'HIGH',
    },
    'Cashfree': {
        'code': [r'cashfree|cf_api|cashfree_order'],
        'files': ['cashfree'],
        'confidence': 'HIGH',
    },
    'QR Code': {
        'code': [r'qr_code|qrcode|generate_qr|upi_qr'],
        'files': ['qr', 'qrcode'],
        'confidence': 'MEDIUM',
    },
    'Financial Year': {
        'code': [r'fin_year|financial_year|fy_start|fy_end|receipt_year'],
        'files': [],
        'confidence': 'MEDIUM',
    },
    'Godown / Warehouse': {
        'code': [r'godown|warehouse|id_godown|godown_name'],
        'files': ['godown', 'warehouse'],
        'confidence': 'HIGH',
    },
    'Counter Management': {
        'code': [r'counter|billing_counter|counter_name|counter_id'],
        'files': ['counter'],
        'confidence': 'MEDIUM',
    },
}

# ── Integration Definitions ──────────────────────────────────────────

_INTEGRATION_DEFS = {
    'Razorpay': {
        'type': 'payment',
        'patterns': [r'razorpay|rzp_key_id|Razorpay\\\\Api'],
    },
    'Cashfree': {
        'type': 'payment',
        'patterns': [r'cashfree|CASHFREE|cf_api'],
    },
    'Tally': {
        'type': 'accounting',
        'patterns': [r'tally_export|tally_xml|tally.*sync'],
    },
    'Firebase (FCM)': {
        'type': 'communication',
        'patterns': [r'firebase|fcm_token|google.*messaging'],
    },
    'SMS Gateway': {
        'type': 'communication',
        'patterns': [r'msg91|textlocal|twilio|send_sms.*api'],
    },
    'WhatsApp (WATI)': {
        'type': 'communication',
        'patterns': [r'wati|whatsapp.*api|wa_template'],
    },
    'BIS / Hallmark API': {
        'type': 'api',
        'patterns': [r'bis_api|hallmark.*api|huid.*api'],
    },
    'E-Invoice (NIC)': {
        'type': 'api',
        'patterns': [r'einvoice.*api|irn.*generate|NIC.*api|gsp.*api'],
    },
    'E-Way Bill API': {
        'type': 'api',
        'patterns': [r'eway.*api|ewb.*generate'],
    },
}

# ── Skip dirs ────────────────────────────────────────────────────────

_SKIP_DIRS = {
    '.git', 'node_modules', 'vendor', '__pycache__', 'logs', 'cache',
    'libraries', 'third_party', 'fonts', 'images', 'uploads',
    'logimax-devtools', 'knowledge_brain', 'bug_report_AI',
}


# ── Scanner Engine ───────────────────────────────────────────────────

class ClientProfileScanner:
    """Scan codebase to build a client deployment profile."""

    def scan(self, root: Path) -> ClientProfile:
        """Scan project root and build complete profile."""
        from datetime import datetime

        profile = ClientProfile(
            project_root=str(root),
            scan_date=datetime.now().isoformat(),
        )

        # Initialize modules
        for name in _MODULE_DEFS:
            profile.modules[name] = ModuleInfo(name=name)

        # Initialize features
        for name in _FEATURE_PATTERNS:
            profile.features[name] = FeatureFlag(name=name)

        # Initialize integrations
        for name, defn in _INTEGRATION_DEFS.items():
            profile.integrations[name] = Integration(name=name, type=defn['type'])

        # Phase 1: Scan file structure
        self._scan_file_structure(root, profile)

        # Phase 2: Scan code content for feature/integration detection
        self._scan_code_content(root, profile)

        # Phase 3: Detect print templates
        self._scan_print_templates(root, profile)

        # Phase 4: Detect routes
        self._scan_routes(root, profile)

        # Mark modules as active based on evidence
        self._determine_active_modules(profile)

        return profile

    def _scan_file_structure(self, root: Path, profile: ClientProfile):
        """Phase 1: Scan directory tree for controllers, models, views, JS."""
        admin_app = root / 'admin' / 'application'

        # Controllers
        ctrl_dir = admin_app / 'controllers'
        if ctrl_dir.exists():
            for f in ctrl_dir.rglob('*.php'):
                profile.total_controllers += 1
                name = f.stem.lower()
                try:
                    lines = f.read_text(encoding='utf-8', errors='ignore').count('\n')
                    profile.total_php_lines += lines
                except Exception:
                    lines = 0

                for mod_name, defn in _MODULE_DEFS.items():
                    if any(pat in name for pat in defn['controllers']):
                        profile.modules[mod_name].controllers.append(f.name)
                        profile.modules[mod_name].line_count += lines

        # Models
        model_dir = admin_app / 'models'
        if model_dir.exists():
            for f in model_dir.rglob('*.php'):
                profile.total_models += 1
                name = f.stem.lower()
                try:
                    lines = f.read_text(encoding='utf-8', errors='ignore').count('\n')
                    profile.total_php_lines += lines
                except Exception:
                    lines = 0

                for mod_name, defn in _MODULE_DEFS.items():
                    if any(pat in name for pat in defn['models']):
                        profile.modules[mod_name].models.append(f.name)
                        profile.modules[mod_name].line_count += lines

        # Views
        view_dir = admin_app / 'views'
        if view_dir.exists():
            for f in view_dir.rglob('*.php'):
                profile.total_views += 1
                name = str(f.relative_to(view_dir)).lower().replace('\\', '/')

                for mod_name, defn in _MODULE_DEFS.items():
                    if any(pat in name for pat in defn['controllers']):
                        profile.modules[mod_name].views.append(
                            str(f.relative_to(view_dir))
                        )

        # JS files
        js_dir = root / 'admin' / 'assets' / 'js'
        if js_dir.exists():
            for f in js_dir.rglob('*.js'):
                profile.total_js_files += 1
                name = f.stem.lower()
                try:
                    lines = f.read_text(encoding='utf-8', errors='ignore').count('\n')
                    profile.total_js_lines += lines
                except Exception:
                    lines = 0

                for mod_name, defn in _MODULE_DEFS.items():
                    if any(pat in name for pat in defn.get('js', [])):
                        profile.modules[mod_name].js_files.append(f.name)

    def _scan_code_content(self, root: Path, profile: ClientProfile):
        """Phase 2: Scan PHP/JS content for feature flags and integrations."""
        admin_app = root / 'admin' / 'application'

        # Combine all feature patterns into one big regex per feature
        feature_regexes = {}
        for feat_name, defn in _FEATURE_PATTERNS.items():
            combined = '|'.join(defn['code'])
            feature_regexes[feat_name] = re.compile(combined, re.IGNORECASE)

        integration_regexes = {}
        for int_name, defn in _INTEGRATION_DEFS.items():
            combined = '|'.join(defn['patterns'])
            integration_regexes[int_name] = re.compile(combined, re.IGNORECASE)

        # Table references
        table_regex = re.compile(
            r"""(?:from|join|into|update|insert\s+into)\s+[`'"]?(\w+)[`'"]?""",
            re.IGNORECASE
        )

        scan_dirs = [
            admin_app / 'controllers',
            admin_app / 'models',
            root / 'admin' / 'assets' / 'js',
        ]

        for scan_dir in scan_dirs:
            if not scan_dir.exists():
                continue
            for f in scan_dir.rglob('*'):
                if f.suffix.lower() not in ('.php', '.js'):
                    continue
                if any(skip in f.parts for skip in _SKIP_DIRS):
                    continue

                try:
                    content = f.read_text(encoding='utf-8', errors='ignore')
                except Exception:
                    continue

                rel_path = str(f.relative_to(root))

                # Check features
                for feat_name, regex in feature_regexes.items():
                    matches = regex.findall(content)
                    if matches:
                        feat = profile.features[feat_name]
                        if len(feat.evidence) < 5:  # Cap evidence
                            feat.evidence.append(f'{rel_path}: {len(matches)} matches')
                        feat.detected = True
                        feat.confidence = _FEATURE_PATTERNS[feat_name]['confidence']

                # Check integrations
                for int_name, regex in integration_regexes.items():
                    matches = regex.findall(content)
                    if matches:
                        integ = profile.integrations[int_name]
                        if len(integ.evidence) < 5:
                            integ.evidence.append(f'{rel_path}: {len(matches)} matches')
                        integ.detected = True

                # Extract table references for module mapping
                if f.suffix.lower() == '.php':
                    tables = table_regex.findall(content)
                    for table in tables:
                        table_lower = table.lower()
                        for mod_name, defn in _MODULE_DEFS.items():
                            if any(t in table_lower for t in defn['tables']):
                                if table_lower not in profile.modules[mod_name].tables:
                                    profile.modules[mod_name].tables.append(table_lower)

    def _scan_print_templates(self, root: Path, profile: ClientProfile):
        """Phase 3: Detect available print templates."""
        # Check config files for template definitions
        config_dir = root / 'admin' / 'application' / 'config'
        if config_dir.exists():
            for f in config_dir.glob('*.php'):
                if f.stem.lower() in ('bill', 'estimation', 'receipt', 'delivery', 'packing'):
                    profile.print_templates.append(f.stem)

        # Check views for print-related files
        view_dir = root / 'admin' / 'application' / 'views'
        if view_dir.exists():
            for f in view_dir.rglob('*print*.php'):
                rel = str(f.relative_to(view_dir)).replace('\\', '/')
                if rel not in profile.print_templates:
                    profile.print_templates.append(rel)

            for f in view_dir.rglob('*receipt*.php'):
                rel = str(f.relative_to(view_dir)).replace('\\', '/')
                if rel not in profile.print_templates:
                    profile.print_templates.append(rel)

    def _scan_routes(self, root: Path, profile: ClientProfile):
        """Phase 4: Scan routes.php for registered routes."""
        routes_file = root / 'admin' / 'application' / 'config' / 'routes.php'
        if not routes_file.exists():
            return

        try:
            content = routes_file.read_text(encoding='utf-8', errors='ignore')
        except Exception:
            return

        route_regex = re.compile(
            r"""\$route\s*\[\s*['"]([^'"]+)['"]\s*\]""",
            re.IGNORECASE
        )
        for match in route_regex.findall(content):
            route = match.lower()
            for mod_name, defn in _MODULE_DEFS.items():
                if any(pat in route for pat in defn['controllers']):
                    profile.modules[mod_name].routes.append(match)

    def _determine_active_modules(self, profile: ClientProfile):
        """Mark modules as active based on accumulated evidence."""
        for mod_name, mod in profile.modules.items():
            # Active if: has at least 1 controller AND 1 model
            if mod.controllers and mod.models:
                mod.active = True
            # Also active if: has 2+ tables AND (controller OR model)
            elif len(mod.tables) >= 2 and (mod.controllers or mod.models):
                mod.active = True


# ── Formatting ───────────────────────────────────────────────────────

def format_profile_text(profile: ClientProfile) -> str:
    """Format profile as human-readable text."""
    lines = []
    lines.append('=' * 70)
    lines.append('CLIENT PROFILE REPORT')
    lines.append(f'Project: {profile.project_root}')
    lines.append(f'Scanned: {profile.scan_date}')
    lines.append('=' * 70)

    # Codebase stats
    lines.append('\n-- CODEBASE STATS --')
    lines.append(f'  Controllers: {profile.total_controllers}')
    lines.append(f'  Models:      {profile.total_models}')
    lines.append(f'  Views:       {profile.total_views}')
    lines.append(f'  JS Files:    {profile.total_js_files}')
    lines.append(f'  PHP Lines:   {profile.total_php_lines:,}')
    lines.append(f'  JS Lines:    {profile.total_js_lines:,}')

    # Active modules
    lines.append(f'\n-- ACTIVE MODULES ({len(profile.active_modules)}) --')
    for mod_name in sorted(profile.active_modules):
        mod = profile.modules[mod_name]
        lines.append(
            f'  [+] {mod_name:<25} '
            f'C:{len(mod.controllers)} M:{len(mod.models)} '
            f'V:{len(mod.views)} JS:{len(mod.js_files)} '
            f'T:{len(mod.tables)} ({mod.line_count:,} lines)'
        )

    inactive = [m.name for m in profile.modules.values() if not m.active]
    if inactive:
        lines.append(f'\n-- INACTIVE MODULES ({len(inactive)}) --')
        for name in sorted(inactive):
            lines.append(f'  [-] {name}')

    # Features
    active_feats = [f for f in profile.features.values() if f.detected]
    lines.append(f'\n-- FEATURES DETECTED ({len(active_feats)}) --')
    for feat in sorted(active_feats, key=lambda f: f.name):
        lines.append(f'  [+] {feat.name:<30} [{feat.confidence}]')
        for ev in feat.evidence[:2]:
            lines.append(f'      > {ev}')

    inactive_feats = [f for f in profile.features.values() if not f.detected]
    if inactive_feats:
        lines.append(f'\n-- FEATURES NOT DETECTED ({len(inactive_feats)}) --')
        for feat in sorted(inactive_feats, key=lambda f: f.name):
            lines.append(f'  [-] {feat.name}')

    # Integrations
    active_ints = [i for i in profile.integrations.values() if i.detected]
    if active_ints:
        lines.append(f'\n-- INTEGRATIONS ({len(active_ints)}) --')
        for integ in sorted(active_ints, key=lambda i: i.name):
            lines.append(f'  [+] {integ.name:<25} [{integ.type}]')
            for ev in integ.evidence[:2]:
                lines.append(f'      > {ev}')

    # Print templates
    if profile.print_templates:
        lines.append(f'\n-- PRINT TEMPLATES ({len(profile.print_templates)}) --')
        for tmpl in sorted(profile.print_templates):
            lines.append(f'  * {tmpl}')

    lines.append('\n' + '=' * 70)
    return '\n'.join(lines)


def format_profile_json(profile: ClientProfile) -> dict:
    """Format profile as JSON-serializable dict."""
    return {
        'project_root': profile.project_root,
        'scan_date': profile.scan_date,
        'stats': {
            'controllers': profile.total_controllers,
            'models': profile.total_models,
            'views': profile.total_views,
            'js_files': profile.total_js_files,
            'php_lines': profile.total_php_lines,
            'js_lines': profile.total_js_lines,
        },
        'modules': {
            name: {
                'active': mod.active,
                'controllers': mod.controllers,
                'models': mod.models,
                'views_count': len(mod.views),
                'js_files': mod.js_files,
                'tables': mod.tables,
                'routes': mod.routes,
                'line_count': mod.line_count,
            }
            for name, mod in profile.modules.items()
        },
        'features': {
            name: {
                'detected': feat.detected,
                'confidence': feat.confidence,
                'evidence': feat.evidence,
            }
            for name, feat in profile.features.items()
        },
        'integrations': {
            name: {
                'type': integ.type,
                'detected': integ.detected,
                'evidence': integ.evidence,
            }
            for name, integ in profile.integrations.items()
        },
        'print_templates': profile.print_templates,
    }
