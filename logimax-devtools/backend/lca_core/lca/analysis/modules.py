"""Centralized module detection for CI3 codebases.

Detects which module a file belongs to based on:
  1. Filename prefix patterns (admin_ret_*, admin_*, ret_*_model)
  2. Directory-based detection (views/billing/*, js/billing*)
  3. Keyword fallback for edge cases

This module is the single source of truth for module detection.
All other modules should import detect_module from here.
"""

from __future__ import annotations

import re

# ---- Module Registry ---------------------------------------------------
# Maps a canonical module name to the controller/model base names that
# belong to it.  Patterns are matched against the bare filename (no path,
# no extension, lowercased).
#
# Order matters: first match wins.  Put longer/more-specific patterns first.

_FILE_MODULE_MAP: list[tuple[str, list[str]]] = [
    # --- Financial modules (critical) ---
    ('billing', [
        'admin_ret_billing', 'ret_billing_model', 'billing_helper',
        'billing', 'admin_invoice_builder',
    ]),
    ('estimation', [
        'admin_ret_estimation', 'admin_ret_estimation_konika',
        'ret_estimation_model', 'estimation_model', 'estimation',
    ]),
    ('purchase', [
        'admin_ret_purchase', 'admin_ret_purchase_approval',
        'ret_purchase_model', 'purchase_model', 'purchase',
        'admin_ret_supp_catalog', 'supplier',
    ]),
    ('payment', [
        'admin_payment', 'payment_model', 'ret_payment_model',
        'payment', 'receipt',
    ]),
    ('scheme', [
        'admin_scheme', 'crm_mas_scheme', 'scheme_model', 'scheme',
    ]),

    # --- Inventory & Stock modules ---
    ('stock', [
        'admin_ret_stock_issue', 'admin_ret_tag_parts',
        'admin_ret_tagging', 'admin_stock_age_master',
        'ret_stock_model', 'stock_model', 'stock', 'tag_',
    ]),
    ('inventory', [
        'admin_ret_other_inventory', 'admin_ret_lot',
        'admin_ret_metal_process', 'admin_opening_master',
        'inventory_model', 'ret_lot_model', 'inventory', 'inv_',
    ]),
    ('catalog', [
        'admin_catalog', 'admin_ret_catalog', 'catalog_model',
        'catalog',
    ]),

    # --- Transfer modules ---
    ('branch_transfer', [
        'admin_ret_brntransfer', 'brn_transfer_model', 'brntransfer',
    ]),
    ('sales_transfer', [
        'admin_ret_sales_transfer', 'sales_transfer',
    ]),
    ('section_transfer', [
        'admin_ret_section_transfer', 'section_transfer',
    ]),

    # --- Order & Booking ---
    ('order', [
        'admin_ret_order', 'orderaccept', 'order_model',
        'admin_adv_booking', 'order',
    ]),

    # --- Customer ---
    ('customer', [
        'admin_customer', 'customer_model', 'cust_',
        'refer_customer', 'kyc', 'customer',
    ]),

    # --- Employee & Wallet ---
    ('employee', [
        'admin_employee', 'admin_emp_incentive', 'employee_model',
        'employee',
    ]),
    ('wallet', [
        'admin_wallet', 'sync_wallet_api', 'wallet_model', 'wallet',
    ]),

    # --- Reports & Dashboard ---
    ('reports', [
        'admin_reports', 'admin_ret_reports', 'report_tester',
        'collection_report', 'test_cash_flow', 'report', 'rpt_',
    ]),
    ('dashboard', [
        'admin_dashboard', 'admin_ret_dashboard',
        'admin_ret_dashboard_api', 'admin_ret_dashboard_api_backup',
        'dashboard',
    ]),

    # --- GST ---
    ('gst', [
        'admin_irn_settings', 'gst_model', 'gst', 'gstr', 'hsn',
        'admin_pan_verification',
    ]),

    # --- Services ---
    ('services', [
        'admin_services', 'admin_services_27_10_25',
        'admin_ret_services', 'khimji_services',
        'sync_erp_services', 'intra_sync_services',
        'videoshopping_appt', 'services',
    ]),

    # --- Settings & Config ---
    ('settings', [
        'admin_settings', 'admin_manage', 'admin_print_template',
        'admin_pos', 'print_designer', 'setting', 'config',
    ]),

    # --- Auth & Access ---
    ('auth', [
        'admin_apm', 'access_right', 'auth_model',
        'auth', 'login', 'override_status',
    ]),

    # --- Communication ---
    ('communication', [
        'admin_usersms', 'admin_mails', 'sms_model', 'mail',
    ]),

    # --- Import/Export ---
    ('import_export', [
        'admin_import_export', 'importcontroller',
        'import', 'export',
    ]),

    # --- Gift & Voucher ---
    ('gift_voucher', [
        'admin_gift_vocuher', 'gift_voucher', 'voucher',
    ]),

    # --- EDA ---
    ('eda', [
        'admin_ret_eda', 'eda',
    ]),

    # --- Chit ---
    ('chit', [
        'chit_admin', 'chit_transaction', 'chitapi', 'chit',
    ]),

    # --- Tasks ---
    ('task', [
        'admin_ret_task', 'task_model', 'task',
    ]),

    # --- Wishlist ---
    ('wishlist', [
        'admin_ret_wishlist', 'wishlist',
    ]),

    # --- Knowledge Base ---
    ('knowledge_base', [
        'admin_knowledge_base', 'knowledge',
    ]),

    # --- Sync & API ---
    ('sync', [
        'sync_api', 'sync_erp_api', 'sktm_syncapi',
        'tally_app_api', 'admin_rateapi',
        'admin_app_api', 'admin_app_api_backup',
    ]),

    # --- Agent ---
    ('agent', [
        'admin_agent', 'agent',
    ]),

    # --- Common / Shared ---
    ('common', [
        'common_model', 'common',
    ]),
]

# Pre-build a lookup for exact base-name matches (fastest path)
_EXACT_LOOKUP: dict[str, str] = {}
for _mod, _patterns in _FILE_MODULE_MAP:
    for _pat in _patterns:
        _EXACT_LOOKUP[_pat] = _mod

# Critical (financial) modules
CRITICAL_MODULES = {
    'billing', 'estimation', 'purchase', 'payment', 'inventory',
    'gst', 'stock', 'scheme',
}


def detect_module(filepath: str) -> str:
    """Detect which module a file belongs to.

    Args:
        filepath: Relative or absolute file path.

    Returns:
        Module name (e.g., 'billing', 'estimation') or 'other'.
    """
    # Normalize: lowercase, forward slashes, get basename without extension
    fp = filepath.lower().replace('\\', '/')
    basename = fp.rsplit('/', 1)[-1]  # filename only
    basename_no_ext = basename.rsplit('.', 1)[0] if '.' in basename else basename

    # 1. Exact match on base filename (fastest)
    if basename_no_ext in _EXACT_LOOKUP:
        return _EXACT_LOOKUP[basename_no_ext]

    # 2. Directory-based detection (views/billing/*, js/billing*)
    #    e.g., "admin/application/views/billing/edit.php" -> "billing"
    parts = fp.split('/')
    if 'views' in parts:
        idx = parts.index('views')
        if idx + 1 < len(parts) and parts[idx + 1] not in ('admin', 'inc', 'layout', 'common', 'errors'):
            view_dir = parts[idx + 1]
            # Try to match view directory to a module
            for mod, patterns in _FILE_MODULE_MAP:
                for pat in patterns:
                    if pat in view_dir or view_dir in pat:
                        return mod

    # 3. Substring keyword fallback (for model/helper files not in exact map)
    for mod, patterns in _FILE_MODULE_MAP:
        for pat in patterns:
            # Only use keyword-style patterns (short ones like 'billing', 'stock')
            if len(pat) >= 4 and pat in basename_no_ext:
                return mod

    return 'other'


def is_critical_module(module_name: str) -> bool:
    """Check if a module is financial/critical."""
    return module_name in CRITICAL_MODULES
