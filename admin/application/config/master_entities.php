<?php defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * ============================================================
 * LMX Master Entities Configuration
 * ============================================================
 * This file defines the configuration for all simple master screens.
 * It is LAZY-LOADED by the master engine — NOT auto-loaded globally.
 *
 * @version 1.0.0
 * @created 2026-07-01
 * ============================================================
 */

$config['master_entities'] = [

    // 1. Theme Master
    'theme' => [
        'label'      => 'Theme',
        'table'      => 'ret_theme',
        'pk'         => 'id_theme',
        'status_col' => 'theme_status',
        
        // DataTables fetch method
        'list_method' => 'ajax_gettheme',

        // Example check: Cannot deactivate Theme if in use by an active Design
        'in_use_check' => [
            ['table' => 'ret_design_master', 'column' => 'theme', 'active_only' => true, 'status_column' => 'design_status']
        ],

        // Validation Rules
        'rules' => [
            'theme_name' => [
                'label'      => 'Theme Name',
                'required'   => true,
                'rule'       => 'nameField',
                'max_length' => 75,
                'unique'     => ['table' => 'ret_theme'] // pk/exclude_id handled by engine
            ],
            'theme_code' => [
                'label'      => 'Short Name',
                'required'   => true,
                'rule'       => 'shortCode',
                'max_length' => 45,
                'unique'     => ['table' => 'ret_theme', 'column' => 'theme_code']
            ],
            'description' => [
                'label'      => 'Description',
                'required'   => false,
                'rule'       => 'remarks',
                'max_length' => 255
            ]
        ],

        // Input Mapping: DB Column => [Input Type, Sanitize Rule]
        'inputs' => [
            'theme_name'  => ['string', 'nameField'],
            'theme_code'  => ['string', 'shortCode'],
            'theme_desc'  => ['string', 'remarks'] // Was description, actual DB column is theme_desc
        ],

        // Views (Assumes generic views unless overridden),
    ],

    // 2. Making Type Master
    'making_type' => [
        'label'      => 'Making Type',
        'table'      => 'ret_making_type',
        'pk'         => 'mak_id', // Found in delete case
        'status_col' => 'mak_status', // Found in update_status case
        'list_method' => 'ajax_get_makingtype',

        'rules' => [
            'mak_name' => [
                'label'      => 'Making Type',
                'required'   => true,
                'rule'       => 'nameField',
                'max_length' => 75,
                'unique'     => ['table' => 'ret_making_type']
            ],
            'mak_short_code' => [
                'label'      => 'Short Code',
                'required'   => true,
                'rule'       => 'shortCode',
                'max_length' => 45,
                'unique'     => ['table' => 'ret_making_type', 'column' => 'mak_short_code']
            ]
        ],

        'inputs' => [
            'mak_name'       => ['string', 'nameField'],
            'mak_short_code' => ['string', 'shortCode']
        ],
    ],

    // 3. Hook Master
    'hook' => [
        'label'      => 'Hook',
        'table'      => 'ret_hook_type',
        'pk'         => 'hook_id',
        'status_col' => 'hook_status',
        'list_method' => 'ajax_gethook',

        // Example check: Cannot deactivate Hook if in use by an active Design
        'in_use_check' => [
            ['table' => 'ret_design_master', 'column' => 'hook_type', 'active_only' => true, 'status_column' => 'design_status'],
            ['table' => 'customerorderdetails', 'column' => 'hook_type', 'active_only' => false] // Orders shouldn't have active dependencies missing
        ],

        'rules' => [
            'hook_name' => [
                'label'      => 'Hook',
                'required'   => true,
                'rule'       => 'nameField',
                'max_length' => 75,
                'min_length' => 2,
                'security_blacklist' => true,
                'unique'     => ['table' => 'ret_hook_type']
            ],
            'hook_short_code' => [
                'label'      => 'Short Code',
                'required'   => true,
                'rule'       => 'shortCode',
                'max_length' => 45,
                'min_length' => 1,
                'security_blacklist' => true,
                'unique'     => ['table' => 'ret_hook_type', 'column' => 'hook_short_code']
            ]
        ],

        'inputs' => [
            'hook_name'       => ['string', 'nameField'],
            'hook_short_code' => ['string', 'shortCode']
        ],
    ],
    'color' => [
        'label'      => 'Color',
        'table'      => 'ret_color',
        'pk'         => 'id_color',
        'status_col' => 'status',
        'list_method'=> 'ajax_getcolor',
        'access_path'=> 'color/list',
        'in_use_check' => [
            ['table' => 'ret_quality_code', 'column' => 'color_id']
        ],
        'rules' => [
            'color' => [
                'label'      => 'Color',
                'required'   => true,
                'rule'       => 'nameField',
                'max_length' => 75,
                'min_length' => 2,
                'security_blacklist' => true,
                'unique'     => ['table' => 'ret_color']
            ],
            'description' => [
                'label'      => 'Description',
                'required'   => false,
                'rule'       => 'remarks',
                'max_length' => 255
            ],
        ],
        'inputs' => [
            'color' => ['string', 'nameField'],
            'description' => ['string', 'remarks'],
        ],
    ],

    'cut' => [
        'label'      => 'Cut',
        'table'      => 'ret_cut',
        'pk'         => 'id_cut',
        'status_col' => 'status',
        'list_method'=> 'ajax_getcut',
        'access_path'=> 'cut/list',
        'in_use_check' => [
            ['table' => 'ret_quality_code', 'column' => 'cut_id']
        ],
        'rules' => [
            'cut' => [
                'label'      => 'Cut',
                'required'   => true,
                'rule'       => 'nameField',
                'max_length' => 75,
                'unique'     => ['table' => 'ret_cut']
            ],
            'description' => [
                'label'      => 'Description',
                'required'   => false,
                'rule'       => 'remarks',
                'max_length' => 255
            ],
        ],
        'inputs' => [
            'cut' => ['string', 'nameField'],
            'description' => ['string', 'remarks'],
        ],
    ],

    'clarity' => [
        'label'      => 'Clarity',
        'table'      => 'ret_clarity',
        'pk'         => 'id_clarity',
        'status_col' => 'status',
        'list_method'=> 'ajax_getclarity',
        'access_path'=> 'clarity/list',
        'in_use_check' => [
            ['table' => 'ret_quality_code', 'column' => 'clarity_id']
        ],
        'rules' => [
            'clarity' => [
                'label'      => 'Clarity',
                'required'   => true,
                'rule'       => 'nameField',
                'max_length' => 75,
                'unique'     => ['table' => 'ret_clarity']
            ],
            'description' => [
                'label'      => 'Description',
                'required'   => false,
                'rule'       => 'remarks',
                'max_length' => 255
            ],
        ],
        'inputs' => [
            'clarity' => ['string', 'nameField'],
            'description' => ['string', 'remarks'],
        ],
    ],

    'uom' => [
        'label'      => 'UOM',
        'table'      => 'ret_uom',
        'pk'         => 'uom_id',
        'status_col' => 'uom_status',
        'list_method'=> 'ajax_getUOM',
        'in_use_check' => [
            ['table' => 'ret_product_master', 'column' => 'uom_id'],
            ['table' => 'ret_taging_stone', 'column' => 'uom_id']
        ],
        'rules' => [
            'uom_name' => [
                'label'      => 'Uom Name',
                'required'   => true,
                'rule'       => 'nameField',
                'max_length' => 75,
                'unique'     => ['table' => 'ret_uom']
            ],
            'uom_short_code' => [
                'label'      => 'Uom Short Code',
                'required'   => true,
                'rule'       => 'shortCode',
                'max_length' => 75
            ],
        ],
        'inputs' => [
            'uom_name' => ['string', 'nameField'],
            'uom_short_code' => ['string', 'shortCode'],
        ],
    ],

    'screw' => [
        'label'      => 'Screw',
        'table'      => 'ret_screw_type',
        'pk'         => 'screw_id',
        'status_col' => 'screw_status',
        'list_method'=> 'ajax_getscrew',
        'in_use_check' => [
            ['table' => 'ret_design_master', 'column' => 'screw_type'],
        ],
        'rules' => [
            'screw_name' => [
                'label'      => 'Screw Name',
                'required'   => true,
                'rule'       => 'nameField',
                'max_length' => 75,
                'unique'     => ['table' => 'ret_screw_type']
            ],
            'screw_short_code' => [
                'label'      => 'Screw Short Code',
                'required'   => true,
                'rule'       => 'shortCode',
                'max_length' => 75
            ],
        ],
        'inputs' => [
            'screw_name' => ['string', 'nameField'],
            'screw_short_code' => ['string', 'shortCode'],
        ],
    ],

    'financial_year' => [
        'label'      => 'Financial Year',
        'table'      => 'ret_financial_year',
        'pk'         => 'fin_id',
        'status_col' => 'fin_status',
        // No key_field: exclusivity is GLOBAL across the whole table, not
        // grouped by any column — the system relies on exactly one active
        // financial year existing at a time (see MODULE_DEPENDENCIES.md /
        // Catalog_Inventory outbound contracts). Confirmed via live testing
        // 2026-07-16 that this was NOT enforced post-migration — Add and
        // Edit could both leave 2+ rows simultaneously active.
        'exclusive_active' => ['key_field' => null],
        // NOTE: list_method deliberately omitted. ajax_get_financial_year_List()
        // aliases fin_year_name -> fin_code and concatenates fin_year_from/to
        // into a single fin_year string — incompatible with this config's field
        // names. _master_generic_list()'s plain SELECT * already returns the
        // right shape, so it's used instead (unlike the other 9 masters with
        // list_method set, whose legacy queries were checked and confirmed to
        // already return column names matching their configs).
        // Referenced everywhere by fin_year_code (a business key, e.g. "FY
        // 24-25"), never by fin_id — match_column tells the dependency check
        // to compare against the record's own fin_year_code instead of its PK.
        // ret_grn_entry and ret_stock_issue store it under a differently-named
        // column (grn_fin_year_code / fin_year) — same value, just not the
        // common name the other 9 tables use.
        'in_use_check' => [
            ['table' => 'customer', 'column' => 'fin_year_code', 'match_column' => 'fin_year_code'],
            ['table' => 'customerorder', 'column' => 'fin_year_code', 'match_column' => 'fin_year_code'],
            ['table' => 'ret_billing', 'column' => 'fin_year_code', 'match_column' => 'fin_year_code'],
            ['table' => 'ret_estimation', 'column' => 'fin_year_code', 'match_column' => 'fin_year_code'],
            ['table' => 'ret_grn_entry', 'column' => 'grn_fin_year_code', 'match_column' => 'fin_year_code'],
            ['table' => 'ret_issue_receipt', 'column' => 'fin_year_code', 'match_column' => 'fin_year_code'],
            ['table' => 'ret_karigar', 'column' => 'fin_year_code', 'match_column' => 'fin_year_code'],
            ['table' => 'ret_karigar_metal_issue', 'column' => 'fin_year_code', 'match_column' => 'fin_year_code'],
            ['table' => 'ret_purchase_order', 'column' => 'fin_year_code', 'match_column' => 'fin_year_code'],
            ['table' => 'ret_service_bill', 'column' => 'fin_year_code', 'match_column' => 'fin_year_code'],
            ['table' => 'ret_stock_issue', 'column' => 'fin_year', 'match_column' => 'fin_year_code'],
        ],
        'cross_field_rules' => [
            ['type' => 'date_range', 'from' => 'fin_year_from', 'to' => 'fin_year_to', 'message' => 'Start date must be before end date'],
        ],
        'rules' => [
            'fin_year_name' => [
                'label'      => 'Fin Year',
                'required'   => true,
                'rule'       => 'nameField',
                'max_length' => 75,
                'security_blacklist' => true,
                'unique'     => ['table' => 'ret_financial_year']
            ],
            'fin_year_code' => [
                'label'      => 'Fin Year Code',
                'required'   => true,
                'rule'       => 'shortCode',
                'max_length' => 4
            ],
            'fin_year_from' => [
                'label'      => 'Start Date',
                'required'   => true,
            ],
            'fin_year_to' => [
                'label'      => 'End Date',
                'required'   => true,
            ],
        ],
        'inputs' => [
            'fin_year_name' => ['string', 'nameField'],
            'fin_year_code' => ['string', 'shortCode'],
            'fin_year_from' => ['date', ''],
            'fin_year_to' => ['date', ''],
        ],
    ],

    'metal_type' => [
        'label'      => 'Metal Type',
        'table'      => 'ret_old_metal_type',
        'pk'         => 'id_metal_type',
        // I-GMSJ-005: added 2026-07-23 (database/migrations/20260723_000001_*)
        // — this master had no status column, so Delete always failed with a
        // generic error instead of deactivating like every other master.
        'status_col' => 'status',
        'in_use_check' => [
            ['table' => 'ret_old_metal_melting_details', 'column' => 'id_metal_type'],
            ['table' => 'ret_old_metal_pocket_details', 'column' => 'id_metal_type']
        ],
        'dropdowns' => [
            'id_metal' => [
                'table'  => 'metal',
                'pk'     => 'id_metal',
                'label'  => 'metal',
                'filter' => ['metal_status' => 1],
            ],
        ],
        'rules' => [
            'metal_type' => [
                'label'      => 'Type Name',
                'required'   => true,
                'rule'       => 'nameField',
                'max_length' => 75,
                'unique'     => ['table' => 'ret_old_metal_type']
            ],
            'id_metal' => [
                'label'      => 'Metal',
                'required'   => true,
                'fk_check'   => ['table' => 'metal', 'pk' => 'id_metal', 'status_col' => 'metal_status']
            ],
        ],
        'inputs' => [
            'metal_type' => ['string', 'nameField'],
            'id_metal'   => ['int', 'numeric'],
        ],
    ],

    'stock_issue_type' => [
        'label'      => 'Stock Issue Type',
        'table'      => 'ret_stock_issue_types',
        'pk'         => 'id_stock_issue_type',
        'status_col' => 'status',
        'in_use_check' => [
            ['table' => 'ret_stock_issue', 'column' => 'issue_type'],
            ['table' => 'ret_issue_receipt', 'column' => 'issue_type'],
        ],
        'rules' => [
            'name' => [
                'label'      => 'Issue Type',
                'required'   => true,
                'rule'       => 'nameField',
                'max_length' => 75,
                'unique'     => ['table' => 'ret_stock_issue_types']
            ],
            'is_remove_from_stock' => [
                'label'      => 'Is Remove From Stock',
                'required'   => true,
            ],
        ],
        'inputs' => [
            'name' => ['string', 'nameField'],
            // I-GMSJ-005: DB column is tinyint(1) meaning 1=Remove From Stock,
            // 0=No (see column comment) — was validated as free text
            // (nameField), so any numeric value (e.g. 100) was accepted.
            // Field's own label already says "Remove From Stock" — options are
            // just the Yes/No answer to that question.
            'is_remove_from_stock' => ['int', 'numeric', ['options' => [[1, 'Yes'], [0, 'No']]]],
        ],
    ],

    'repair_master' => [
        'label'      => 'Repair Master',
        'table'      => 'ret_repair_master',
        'pk'         => 'id_repair_master',
        'status_col' => 'status',
        'in_use_check' => [
            ['table' => 'customerorderdetails', 'column' => 'id_repair_master']
        ],
        'rules' => [
            'name' => [
                'label'      => 'Repair Type',
                'required'   => true,
                'rule'       => 'nameField',
                'max_length' => 75,
                'unique'     => ['table' => 'ret_repair_master']
            ],
        ],
        'inputs' => [
            'name' => ['string', 'nameField'],
        ],
    ],

    'product_division' => [
        'label'      => 'Product Division',
        'table'      => 'ret_product_division',
        'pk'         => 'id_pro_division',
        'status_col' => 'status',
        'access_path'=> 'product_division/list',
        'in_use_check' => [
            ['table' => 'ret_lot_inwards', 'column' => 'product_division'],
            ['table' => 'ret_taging', 'column' => 'product_division'],
        ],
        'rules' => [
            'div_value' => [
                'label'      => 'Product Division',
                'required'   => true,
                'rule'       => 'nameField',
                'max_length' => 75,
                'unique'     => ['table' => 'ret_product_division']
            ],
        ],
        'inputs' => [
            'div_value' => ['string', 'nameField'],
        ],
    ],

    'shape' => [
        'label'      => 'Shape',
        'table'      => 'ret_shape',
        'pk'         => 'shape_id',
        'status_col' => 'status',
        'list_method'=> 'ajax_getshape',
        'in_use_check' => [
            ['table' => 'ret_quality_code', 'column' => 'shape_id']
        ],
        'rules' => [
            'name' => [
                'label'      => 'Name',
                'required'   => true,
                'rule'       => 'nameField',
                'max_length' => 75,
                'unique'     => ['table' => 'ret_shape']
            ],
            'description' => [
                'label'      => 'Description',
                'required'   => false,
                'rule'       => 'remarks',
                'max_length' => 255
            ],
        ],
        'inputs' => [
            'name' => ['string', 'nameField'],
            'description' => ['string', 'remarks'],
        ],
    ],

    'ret_collection' => [
        'label'      => 'Collection',
        'table'      => 'ret_collection_master',
        'pk'         => 'id_collection',
        'status_col' => 'status',
        'in_use_check' => [
            ['table' => 'ret_tag_collection_mapping', 'column' => 'id_collection_master'],
        ],
        'rules' => [
            'collection_name' => [
                'label'      => 'Collection Name',
                'required'   => true,
                'rule'       => 'nameField',
                'max_length' => 75,
                'unique'     => ['table' => 'ret_collection_master']
            ],
        ],
        'inputs' => [
            'collection_name' => ['string', 'nameField'],
        ],
    ],

    'ret_pay_device' => [
        'label'      => 'Pay Device',
        'table'      => 'ret_bill_pay_device',
        'pk'         => 'id_device',
        'status_col' => 'status',
        'in_use_check' => [
            ['table' => 'ret_pos_device_list', 'column' => 'id_device'],
            ['table' => 'web_registered_devices', 'column' => 'id_device']
        ],
        'rules' => [
            'device_name' => [
                'label'      => 'Device Name',
                'required'   => true,
                'rule'       => 'nameField',
                'max_length' => 75,
                'unique'     => ['table' => 'ret_bill_pay_device']
            ],
            'device_type' => [
                'label'      => 'Device Type',
                'required'   => true,
            ],
        ],
        'inputs' => [
            'device_name' => ['string', 'nameField'],
            // DB column is int, not varchar — a fixed 1/0 radio (Wallet/Bank)
            // in the legacy form (catalog_master.js's get_activeAttributes-
            // style radio read), not free text. Casting as string+nameField
            // let digits slip through by accident (nameField's regex allows
            // 0-9) rather than actually validating the allowed set.
            'device_type' => ['int', 'numeric', ['options' => [[1, 'Wallet'], [0, 'Bank']]]],
        ],
    ],

    'ret_account' => [
        'label'      => 'Account',
        'table'      => 'ret_account_head',
        'pk'         => 'id_acc_head',
        'status_col' => 'status',
        'in_use_check' => [
            ['table' => 'ret_issue_receipt', 'column' => 'id_acc_head']
        ],
        'rules' => [
            'name' => [
                'label'      => 'Account Head',
                'required'   => true,
                'rule'       => 'nameField',
                'max_length' => 75,
                'unique'     => ['table' => 'ret_account_head']
            ],
        ],
        'inputs' => [
            'name' => ['string', 'nameField'],
        ],
    ],

    'ret_crdr_ledger' => [
        'label'      => 'CR/DR Ledger',
        'table'      => 'ret_crdr_ledger',
        'pk'         => 'id_crdr_ledger',
        'status_col' => 'status',
        'in_use_check' => [
            ['table' => 'ret_crdr_note', 'column' => 'id_cr_dr_ledger'],
        ],
        'rules' => [
            'ledger_name' => [
                'label'      => 'Ledger Name',
                'required'   => true,
                'rule'       => 'nameField',
                'max_length' => 75,
                'min_length' => 2,
                'security_blacklist' => true,
                'unique'     => ['table' => 'ret_crdr_ledger']
            ],
        ],
        'inputs' => [
            'ledger_name' => ['string', 'nameField'],
        ],
    ],

    'ret_delivery' => [
        'label'      => 'Delivery',
        'table'      => 'ret_sale_delivery',
        'pk'         => 'id_sale_delivery',
        // I-GMSJ-005: added 2026-07-23 (database/migrations/20260723_000002_*)
        // — this master had no status column, so Delete always failed with a
        // generic error instead of deactivating like every other master.
        'status_col' => 'status',
        'in_use_check' => [
            ['table' => 'ret_stock_issue', 'column' => 'id_sale_delivery']
        ],
        'rules' => [
            'name' => [
                'label'      => 'Name',
                'required'   => true,
                'rule'       => 'nameField',
                'max_length' => 75,
                'unique'     => ['table' => 'ret_sale_delivery']
            ],
        ],
        'inputs' => [
            'name' => ['string', 'nameField'],
        ],
    ],

    'ret_section' => [
        'label'      => 'Section',
        'table'      => 'ret_section',
        'pk'         => 'id_section',
        'status_col' => 'status',
        'in_use_check' => [
            ['table' => 'ret_product_master', 'column' => 'id_section'],
            ['table' => 'ret_taging', 'column' => 'id_section'],
            ['table' => 'ret_nontag_item', 'column' => 'id_section']
        ],
        'rules' => [
            'section_name' => [
                'label'      => 'Section Name',
                'required'   => true,
                'rule'       => 'nameField',
                'max_length' => 75,
                'unique'     => ['table' => 'ret_section']
            ],
            'section_short_code' => [
                'label'      => 'Section Code',
                'required'   => true,
                'rule'       => 'shortCode',
                'max_length' => 75
            ],
            'is_home_bill_counter' => [
                'label'      => 'Is Home Bill Counter',
                'required'   => false,
                'rule'       => 'nameField',
                'max_length' => 75
            ],
        ],
        'inputs' => [
            'section_name' => ['string', 'nameField'],
            'section_short_code' => ['string', 'shortCode'],
            'is_home_bill_counter' => ['string', 'nameField'],
        ],
    ],

    // ==================== Tier 2: Dropdowns + Numeric/Checkbox ====================

    'material' => [
        'label'      => 'Material',
        'table'      => 'ret_material',
        'pk'         => 'material_id',
        'status_col' => 'material_status',
        'in_use_check' => [
            ['table' => 'ret_taging_other_materials', 'column' => 'material_id'],
            ['table' => 'ret_material_rate', 'column' => 'material_id']
        ],
        'rules' => [
            'material_name' => [
                'label'      => 'Material Name',
                'required'   => true,
                'rule'       => 'nameField',
                'max_length' => 100,
                'unique'     => ['table' => 'ret_material', 'column' => 'material_name', 'pk' => 'material_id']
            ],
            'material_code' => [
                'label'      => 'Material Code',
                'required'   => true,
                'rule'       => 'shortCode',
                'max_length' => 10,
                'unique'     => ['table' => 'ret_material', 'column' => 'material_code', 'pk' => 'material_id']
            ],
        ],
        'inputs' => [
            'material_name' => ['string', 'nameField'],
            'material_code' => ['string', 'shortCode'],
        ],
    ],

    'purity' => [
        'label'      => 'Purity',
        'table'      => 'ret_purity',
        'pk'         => 'id_purity',
        'status_col' => 'status',
        'access_path'=> 'purity/list',
        'in_use_check' => [
            ['table' => 'ret_lot_inwards', 'column' => 'id_purity'],
            ['table' => 'customerorderdetails', 'column' => 'id_purity'],
            ['table' => 'scheme', 'column' => 'id_purity']
        ],
        'rules' => [
            'purity' => [
                'label'      => 'Purity Value',
                'required'   => true,
                'min_val'    => 0.01,
                'max_val'    => 100,
                'unique'     => ['table' => 'ret_purity', 'column' => 'purity', 'pk' => 'id_purity']
            ],
            'description' => [
                'label'      => 'Description',
                'required'   => false,
                'rule'       => 'remarks',
                'max_length' => 255
            ],
        ],
        'inputs' => [
            'purity'      => ['float', 'numeric'],
            'description' => ['string', 'remarks'],
        ],
    ],

    'stone' => [
        'label'      => 'Stone',
        'table'      => 'ret_stone',
        'pk'         => 'stone_id',
        'status_col' => 'stone_status',
        'in_use_check' => [
            ['table' => 'ret_taging_stone', 'column' => 'stone_id'],
            ['table' => 'ret_billing_item_stones', 'column' => 'stone_id'],
            ['table' => 'ret_design_stone', 'column' => 'stone_id']
        ],
        'dropdowns' => [
            'uom_id' => [
                'table'  => 'ret_uom',
                'pk'     => 'uom_id',
                'label'  => 'uom_name',
                'filter' => ['uom_status' => 1],
            ],
        ],
        'rules' => [
            'stone_name' => [
                'label'      => 'Stone Name',
                'required'   => true,
                'rule'       => 'nameField',
                'max_length' => 100,
                'unique'     => ['table' => 'ret_stone', 'column' => 'stone_name', 'pk' => 'stone_id']
            ],
            'stone_code' => [
                'label'      => 'Stone Code',
                'required'   => true,
                'rule'       => 'shortCode',
                'max_length' => 100,
                'unique'     => ['table' => 'ret_stone', 'column' => 'stone_code', 'pk' => 'stone_id']
            ],
            'uom_id' => [
                'label'      => 'Unit of Measure',
                'required'   => false,
                'fk_check'   => ['table' => 'ret_uom', 'pk' => 'uom_id', 'status_col' => 'uom_status']
            ],
            'stone_type' => [
                'label'      => 'Stone Type',
                'required'   => false,
            ],
            'is_certificate_req' => [
                'label'      => 'Certificate Required',
                'required'   => false,
            ],
            'is_4c_req' => [
                'label'      => '4C Assessment Required',
                'required'   => false,
            ],
        ],
        'inputs' => [
            'stone_name'           => ['string', 'nameField'],
            'stone_code'           => ['string', 'shortCode'],
            'uom_id'               => ['int', 'numeric'],
            'stone_type'           => ['int', '', ['options' => [[0, 'Unset'], [1, 'Diamond'], [2, 'Gem Stones'], [3, 'Others']]]],
            'is_certificate_req'   => ['bool', '', 'checkbox'],
            'is_4c_req'            => ['bool', '', 'checkbox'],
        ],
    ],

    'metal' => [
        'label'      => 'Metal',
        'table'      => 'metal',
        'pk'         => 'id_metal',
        'status_col' => 'metal_status',
        'in_use_check' => [
            ['table' => 'ret_category', 'column' => 'id_metal'],
            ['table' => 'ret_metal_stock', 'column' => 'id_metal'],
            ['table' => 'scheme', 'column' => 'id_metal']
        ],
        'dropdowns' => [
            'tgrp_id' => [
                'table'  => 'ret_taxgroupmaster',
                'pk'     => 'tgrp_id',
                'label'  => 'tgrp_name',
                'filter' => ['tgrp_status' => 1],
            ],
        ],
        'rules' => [
            'metal' => [
                'label'      => 'Metal Name',
                'required'   => true,
                'rule'       => 'nameField',
                'max_length' => 45,
                'unique'     => ['table' => 'metal', 'column' => 'metal', 'pk' => 'id_metal']
            ],
            'metal_code' => [
                'label'      => 'Metal Code',
                'required'   => true,
                'rule'       => 'shortCode',
                'max_length' => 45,
                'unique'     => ['table' => 'metal', 'column' => 'metal_code', 'pk' => 'id_metal']
            ],
            'tgrp_id' => [
                'label'      => 'Tax Group',
                'required'   => false,
                'fk_check'   => ['table' => 'ret_taxgroupmaster', 'pk' => 'tgrp_id', 'status_col' => 'tgrp_status']
            ],
            'display_name' => [
                'label'      => 'Display Name',
                'required'   => false,
                'rule'       => 'nameField',
                'max_length' => 100
            ],
            'show_chit' => [
                'label'      => 'Show in Chit',
                'required'   => false,
            ],
        ],
        'inputs' => [
            'metal'        => ['string', 'nameField'],
            'metal_code'   => ['string', 'shortCode'],
            'tgrp_id'      => ['int', 'numeric'],
            'display_name' => ['string', 'nameField'],
            'show_chit'    => ['bool', '', 'checkbox'],
        ],
    ],

    'size' => [
        'label'      => 'Size',
        'table'      => 'ret_size',
        'pk'         => 'id_size',
        'status_col' => 'active',
        'access_path'=> 'admin_ret_catalog/ret_size/list',
        // I-GMSJ-005: the controller method is ret_size(), not size() — every
        // list/add/edit/delete AJAX call master_list.php builds from the bare
        // entity key ("size") 404'd (no such method), leaving the DataTable
        // stuck on "Processing" forever. url_key overrides just the URL
        // segment while the entity key elsewhere stays 'size'.
        'url_key'    => 'ret_size',
        'in_use_check' => [
            ['table' => 'ret_design_master', 'column' => 'id_size']
        ],
        'dropdowns' => [
            'id_product' => [
                'table'  => 'ret_product_master',
                'pk'     => 'pro_id',
                'label'  => 'product_name',
                'filter' => ['product_status' => 1],
            ],
        ],
        'rules' => [
            'value' => [
                'label'      => 'Size Value',
                'required'   => true,
                'rule'       => 'nameField',
                'max_length' => 255,
                // Same size value legitimately reused across different products
                // (e.g. "36" for both Ring and Bangle sizing) — scope to this product.
                'unique'     => ['table' => 'ret_size', 'column' => 'value', 'extra_where_field' => 'id_product']
            ],
            'name' => [
                'label'      => 'Unit/Label',
                'required'   => true,
                'rule'       => 'nameField',
                'max_length' => 20
            ],
            'id_product' => [
                'label'      => 'Product',
                'required'   => false,
                'fk_check'   => ['table' => 'ret_product_master', 'pk' => 'pro_id', 'status_col' => 'product_status']
            ],
        ],
        'inputs' => [
            'value'      => ['string', 'nameField'],
            'name'       => ['string', 'nameField'],
            'id_product' => ['int', 'numeric'],
        ],
    ],

    // ==================== Tier 2 (remaining) ====================

    'tag' => [
        'label'      => 'Tag Type',
        'table'      => 'ret_tag_type_master',
        'pk'         => 'tag_id',
        'status_col' => 'tag_status',
        // NOTE: no in_use_check — tag_id is a near-universal column name across
        // 30+ transactional tables (physical tag records, not this master),
        // too ambiguous to safely wire without reading each table's actual FK
        // semantics. Left unprotected rather than risk a false-positive block.
        'rules' => [
            'tag_name' => [
                'label'      => 'Tag Name',
                'required'   => true,
                'rule'       => 'nameField',
                'max_length' => 95,
                'unique'     => ['table' => 'ret_tag_type_master']
            ],
        ],
        'inputs' => [
            'tag_name' => ['string', 'nameField'],
        ],
    ],

    'diamond' => [
        'label'      => 'Diamond Master',
        'table'      => 'ret_quality_code',
        'pk'         => 'quality_id',
        'status_col' => 'status',
        'in_use_check' => [
            ['table' => 'ret_taging', 'column' => 'quality_id'],
            ['table' => 'ret_purchase_order_items', 'column' => 'quality_id'],
        ],
        // ret_quality_code has a genuine DB-level unique index ("quality") on
        // this exact 4-column combination — without this check, submitting a
        // clarity/color/cut/shape combination that already exists (under a
        // different code) fell through to a raw, unhandled SQL duplicate-key
        // error page instead of a clean validation message.
        'composite_unique' => [
            [
                'table'   => 'ret_quality_code',
                'columns' => ['cut_id', 'color_id', 'clarity_id', 'shape_id'],
                'message' => 'This Clarity / Color / Cut / Shape combination already exists for another code',
            ],
        ],
        'dropdowns' => [
            'clarity_id' => [
                'table'  => 'ret_clarity',
                'pk'     => 'id_clarity',
                'label'  => 'clarity',
                'filter' => ['status' => 1],
            ],
            'color_id' => [
                'table'  => 'ret_color',
                'pk'     => 'id_color',
                'label'  => 'color',
                'filter' => ['status' => 1],
            ],
            'cut_id' => [
                'table'  => 'ret_cut',
                'pk'     => 'id_cut',
                'label'  => 'cut',
                'filter' => ['status' => 1],
            ],
            'shape_id' => [
                'table'  => 'ret_shape',
                'pk'     => 'shape_id',
                'label'  => 'name',
                'filter' => ['status' => 1],
            ],
        ],
        'rules' => [
            'code' => [
                'label'      => 'Code',
                'required'   => true,
                'rule'       => 'shortCode',
                'max_length' => 12,
                'unique'     => ['table' => 'ret_quality_code']
            ],
            'clarity_id' => [
                'label'      => 'Clarity',
                'required'   => true,
                'fk_check'   => ['table' => 'ret_clarity', 'pk' => 'id_clarity', 'status_col' => 'status']
            ],
            'color_id' => [
                'label'      => 'Color',
                'required'   => true,
                'fk_check'   => ['table' => 'ret_color', 'pk' => 'id_color', 'status_col' => 'status']
            ],
            'cut_id' => [
                'label'      => 'Cut',
                'required'   => true,
                'fk_check'   => ['table' => 'ret_cut', 'pk' => 'id_cut', 'status_col' => 'status']
            ],
            'shape_id' => [
                'label'      => 'Shape',
                'required'   => true,
                'fk_check'   => ['table' => 'ret_shape', 'pk' => 'shape_id', 'status_col' => 'status']
            ],
        ],
        'inputs' => [
            'code'       => ['string', 'shortCode'],
            'clarity_id' => ['int', 'numeric'],
            'color_id'   => ['int', 'numeric'],
            'cut_id'     => ['int', 'numeric'],
            'shape_id'   => ['int', 'numeric'],
        ],
    ],

    'material_rate' => [
        'label'      => 'Material Rate',
        'table'      => 'ret_material_rate',
        'pk'         => 'mat_rate_id',
        'status_col' => 'status',
        'dropdowns' => [
            'material_id' => [
                'table'  => 'ret_material',
                'pk'     => 'material_id',
                'label'  => 'material_name',
                'filter' => ['material_status' => 1],
            ],
        ],
        'rules' => [
            'material_id' => [
                'label'      => 'Material',
                'required'   => true,
                'fk_check'   => ['table' => 'ret_material', 'pk' => 'material_id', 'status_col' => 'material_status'],
                // A material shouldn't get two rates dated the same day —
                // scope uniqueness to this effective_date.
                'unique'     => ['table' => 'ret_material_rate', 'column' => 'material_id', 'extra_where_field' => 'effective_date']
            ],
            'mat_rate' => [
                'label'      => 'Rate',
                'required'   => true,
                'min_val'    => 0.01,
            ],
            'effective_date' => [
                'label'      => 'Effective Date',
                'required'   => true,
            ],
        ],
        'inputs' => [
            'material_id'    => ['int', 'numeric'],
            'mat_rate'       => ['float', 'numeric'],
            'effective_date' => ['date', ''],
        ],
    ],

    'old_metal_cat' => [
        'label'      => 'Old Metal Category',
        'table'      => 'ret_old_metal_category',
        'pk'         => 'id_old_metal_cat',
        'status_col' => 'is_active',
        'in_use_check' => [
            ['table' => 'ret_purchase_items_log', 'column' => 'id_old_metal_cat'],
        ],
        // id_metal isn't user-submitted — it's denormalized from the selected
        // metal type's own metal reference, matching legacy behavior exactly.
        'derive' => [
            [
                'field'           => 'id_metal',
                'from_field'      => 'id_old_metal_type',
                'source_table'    => 'ret_old_metal_type',
                'source_pk'       => 'id_metal_type',
                'source_value_col'=> 'id_metal',
            ],
        ],
        'dropdowns' => [
            'id_old_metal_type' => [
                'table'  => 'ret_old_metal_type',
                'pk'     => 'id_metal_type',
                'label'  => 'metal_type',
                // I-GMSJ-005: Metal Type gained a status column 2026-07-23 —
                // exclude inactive ones from the dropdown, same as every
                // other status-bearing FK source.
                'filter' => ['status' => 1],
            ],
        ],
        'rules' => [
            'old_metal_cat' => [
                'label'      => 'Category Name',
                'required'   => true,
                'rule'       => 'nameField',
                'max_length' => 25,
                'unique'     => ['table' => 'ret_old_metal_category']
            ],
            'id_old_metal_type' => [
                'label'      => 'Metal Type',
                'required'   => true,
                'fk_check'   => ['table' => 'ret_old_metal_type', 'pk' => 'id_metal_type', 'status_col' => 'status']
            ],
            'hsn_code' => [
                'label'      => 'HSN Code',
                'required'   => true,
                // HSN codes are digits only — was previously unrestricted
                // format-wise (int cast still allowed e.g. "-5" to slip
                // through as -5). 'rule' still drives keystroke filtering
                // (digits-only as you type); 'pattern' layers the actual GST
                // HSN shape on top (codes are specifically 4, 6, or 8 digits,
                // not any digit string) — the escape hatch for a restriction
                // no shared named rule expresses.
                'rule'            => 'wholeNumber',
                'pattern'         => '^(\d{4}|\d{6}|\d{8})$',
                'pattern_message' => 'must be a 4, 6, or 8 digit HSN code',
            ],
            'old_metal_perc' => [
                'label'      => 'Percentage',
                'required'   => true,
                'min_val'    => 0,
                'max_val'    => 100,
            ],
            'old_metal_discount' => [
                'label'      => 'Discount',
                'required'   => true,
                'min_val'    => 0,
                'max_val'    => 100,
            ],
            'touch_from' => [
                'label'      => 'Touch From',
                'required'   => true,
                'min_val'    => 0,
                'max_val'    => 100,
            ],
            'touch_to' => [
                'label'      => 'Touch To',
                'required'   => true,
                'min_val'    => 0,
                'max_val'    => 100,
            ],
        ],
        'cross_field_rules' => [
            ['type' => 'range_order', 'from' => 'touch_from', 'to' => 'touch_to', 'message' => 'Touch From must not exceed Touch To'],
        ],
        'inputs' => [
            'old_metal_cat'      => ['string', 'nameField'],
            'id_old_metal_type'  => ['int', 'numeric'],
            'hsn_code'           => ['int', 'numeric'],
            'old_metal_perc'     => ['float', 'numeric'],
            'old_metal_discount' => ['float', 'numeric'],
            'touch_from'         => ['float', 'numeric'],
            'touch_to'           => ['float', 'numeric'],
        ],
    ],

    'ret_metalpurity' => [
        'label'      => 'Metal Purity Rate Master',
        'table'      => 'ret_metal_purity_rate',
        'pk'         => 'id_metal_purity_rate',
        // I-GMSJ-005: added 2026-07-23 (database/migrations/20260723_000003_*)
        // — this master had no status column (legacy never exposed a status
        // toggle for it either), so Delete always failed with a generic
        // error instead of deactivating like every other master.
        'status_col' => 'status',
        'dropdowns' => [
            'id_metal' => [
                'table'  => 'metal',
                'pk'     => 'id_metal',
                'label'  => 'metal',
                'filter' => ['metal_status' => 1],
            ],
            'id_purity' => [
                'table'  => 'ret_purity',
                'pk'     => 'id_purity',
                'label'  => 'purity',
                'filter' => ['status' => 1],
            ],
        ],
        // I-GMSJ-005: rate_field is NOT a numeric rate amount — legacy's
        // Rate select2 (catalog_master.js Metalrate()/#RatePurityid, backed
        // by admin_ret_catalog/MetalRates -> MetalRatesModel()) stores the
        // NAME of a metal_rates column (e.g. "goldrate_22ct"), so Estimation/
        // Billing can look up this purity's live rate at
        // metal_rates.<rate_field> on the day of the transaction. The prior
        // migration modeled it as a free numeric field, which both hid the
        // real selection UI ("Metal Rate selection is missing... an amount
        // edit field is displayed") and let users overwrite it with an
        // arbitrary number that isn't a valid column name, breaking those
        // downstream lookups. market_rate_field always mirrors rate_field on
        // save (legacy's addmodel()/editmodel() write the same submitted
        // value to both columns) — not user-edited, so it's mirrored rather
        // than exposed as its own field.
        'mirror_fields' => ['market_rate_field' => 'rate_field'],
        'rules' => [
            'id_metal' => [
                'label'      => 'Metal',
                'required'   => true,
                'fk_check'   => ['table' => 'metal', 'pk' => 'id_metal', 'status_col' => 'metal_status']
            ],
            'id_purity' => [
                'label'      => 'Purity',
                'required'   => true,
                'fk_check'   => ['table' => 'ret_purity', 'pk' => 'id_purity', 'status_col' => 'status'],
                // Composite uniqueness: this metal+purity combination must be
                // unique together, matching the legacy addmodel() duplicate
                // check — id_purity alone repeats legitimately across metals.
                'unique'     => ['table' => 'ret_metal_purity_rate', 'column' => 'id_purity', 'extra_where_field' => 'id_metal']
            ],
            'rate_field' => [
                'label'      => 'Metal Rate',
                'required'   => true,
            ],
        ],
        'inputs' => [
            'id_metal'   => ['int', 'numeric'],
            'id_purity'  => ['int', 'numeric'],
            // Same fixed set MetalRatesModel() queries from metal_rates'
            // INFORMATION_SCHEMA.COLUMNS today — kept as a static list here
            // (matching the stock_issue_type fixed-select precedent) since
            // these column names are schema, not data, and don't change
            // without a migration.
            'rate_field' => ['string', '', ['options' => [
                ['goldrate_22ct', 'Gold Rate 22K'],
                ['goldrate_24ct', 'Gold Rate 24K'],
                ['goldrate_18ct', 'Gold Rate 18K'],
                ['goldrate_14ct', 'Gold Rate 14K'],
                ['goldrate_9ct', 'Gold Rate 9K'],
                ['coin_gold_22ct', 'Coin Gold 22K'],
                ['market_gold_18ct', 'Market Gold Rate 18K'],
                ['market_gold_20ct', 'Market Gold Rate 20K'],
                ['market_gold_995', 'Market Gold Rate 995'],
                ['silverrate_1gm', 'Silver Rate (1g)'],
                ['silverrate_1kg', 'Silver Rate (1kg)'],
                ['mjdmasilverrate_1gm', 'MJDMA Silver Rate (1g)'],
                ['mjdmasilverrate_999', 'MJDMA Silver Rate 999'],
                ['platinum_1g', 'Platinum Rate (1g)'],
            ]]],
        ],
    ],

    'branch_floor' => [
        'label'      => 'Floor',
        'table'      => 'ret_branch_floor',
        'pk'         => 'floor_id',
        'status_col' => 'floor_status',
        // A floor with counters under it can't be deactivated — legacy never
        // enforced this, but leaving it unchecked orphans ret_branch_floor_counter
        // rows (their floor_id would point at an inactive/meaningless floor).
        'in_use_check' => [
            ['table' => 'ret_branch_floor_counter', 'column' => 'floor_id'],
        ],
        'dropdowns' => [
            'branch_id' => [
                'table'  => 'branch',
                'pk'     => 'id_branch',
                'label'  => 'name',
                'filter' => ['active' => 1],
            ],
        ],
        'rules' => [
            'branch_id' => [
                'label'      => 'Branch',
                'required'   => true,
                'fk_check'   => ['table' => 'branch', 'pk' => 'id_branch', 'status_col' => 'active']
            ],
            'floor_name' => [
                'label'      => 'Floor Name',
                'required'   => true,
                'rule'       => 'nameField',
                // Same floor name legitimately reused across different branches —
                // scope uniqueness to this branch only.
                'unique'     => ['table' => 'ret_branch_floor', 'column' => 'floor_name', 'extra_where_field' => 'branch_id']
            ],
            'floor_short_code' => [
                'label'      => 'Short Code',
                'required'   => true,
                'rule'       => 'shortCode',
            ],
        ],
        'inputs' => [
            'branch_id'        => ['int', 'numeric'],
            'floor_name'       => ['string', 'nameField'],
            'floor_short_code' => ['string', 'shortCode'],
        ],
    ],

    'floor_counter' => [
        'label'      => 'Counter',
        'table'      => 'ret_branch_floor_counter',
        'pk'         => 'counter_id',
        'status_col' => 'counter_status',
        // Live transactional tables reference counter_id directly (billing,
        // orders, payments, cash collection) — block deactivation while any
        // of these still point at the counter.
        'in_use_check' => [
            ['table' => 'customerorder', 'column' => 'counter_id'],
            ['table' => 'payment', 'column' => 'counter_id'],
            ['table' => 'ret_billing', 'column' => 'counter_id'],
            ['table' => 'ret_cash_collection', 'column' => 'counter_id'],
            ['table' => 'ret_issue_receipt', 'column' => 'counter_id'],
            ['table' => 'ret_service_bill', 'column' => 'counter_id'],
        ],
        'dropdowns' => [
            'floor_id' => [
                'table'  => 'ret_branch_floor',
                'pk'     => 'floor_id',
                'label'  => 'floor_name',
                'filter' => ['floor_status' => 1],
            ],
        ],
        'rules' => [
            'floor_id' => [
                'label'      => 'Floor',
                'required'   => true,
                'fk_check'   => ['table' => 'ret_branch_floor', 'pk' => 'floor_id', 'status_col' => 'floor_status']
            ],
            'counter_name' => [
                'label'      => 'Counter Name',
                'required'   => true,
                'rule'       => 'nameField',
                // Same counter name legitimately reused across different floors —
                // scope uniqueness to this floor only.
                'unique'     => ['table' => 'ret_branch_floor_counter', 'column' => 'counter_name', 'extra_where_field' => 'floor_id']
            ],
            'counter_short_code' => [
                'label'      => 'Short Code',
                'required'   => true,
                'rule'       => 'shortCode',
            ],
        ],
        'inputs' => [
            'floor_id'           => ['int', 'numeric'],
            'counter_name'       => ['string', 'nameField'],
            'counter_short_code' => ['string', 'shortCode'],
        ],
    ],

    'ret_qc_cancel_reason' => [
        'label'      => 'Qc Cancel Reason',
        'table'      => 'ret_qc_cancel_reason',
        'pk'         => 'id_cancel',
        'status_col' => 'status',
        'in_use_check' => [
            ['table' => 'ret_po_qc_issue_process', 'column' => 'id_reason'],
        ],
        // Legacy uppercased every reason on save (strtoupper) — kept so new
        // rows stay consistent with existing data.
        'transforms' => ['cancel_reason' => 'strtoupper'],
        'rules' => [
            'cancel_reason' => [
                'label'      => 'Cancel Reason',
                'required'   => true,
                'rule'       => 'nameField',
                'min_length' => 2,
                'security_blacklist' => true,
                'unique'     => ['table' => 'ret_qc_cancel_reason', 'column' => 'cancel_reason']
            ],
        ],
        // Explicit 'textarea' widget override (3rd slot) — cancel_reason is
        // free-text narration, not a short name; a single-line <input> made it
        // awkward to read/write a real reason. Validation rule is unchanged.
        'inputs' => [
            'cancel_reason' => ['string', 'nameField', 'textarea'],
        ],
    ],

    'tax' => [
        'label'      => 'Tax',
        'table'      => 'ret_taxmaster',
        'pk'         => 'tax_id',
        'status_col' => 'tax_status',
        // Tax Group Items reference a tax by tgi_taxcode — block deactivation
        // while any tax group still uses this tax.
        'in_use_check' => [
            ['table' => 'ret_taxgroupitems', 'column' => 'tgi_taxcode'],
        ],
        'rules' => [
            'tax_name' => [
                'label'      => 'Tax Name',
                'required'   => true,
                'rule'       => 'nameField',
                // tax_name has a genuine UNIQUE index at the DB level
                'unique'     => ['table' => 'ret_taxmaster', 'column' => 'tax_name']
            ],
            'tax_code' => [
                'label'      => 'Tax Code',
                'required'   => true,
                'rule'       => 'shortCode',
            ],
            'tax_percentage' => [
                'label'      => 'Tax Percentage',
                'required'   => true,
                'min_val'    => 0,
                'max_val'    => 100,
            ],
        ],
        'inputs' => [
            'tax_name'       => ['string', 'nameField'],
            'tax_code'       => ['string', 'shortCode'],
            'tax_percentage' => ['float', 'numeric'],
        ],
    ],

    'tgrp' => [
        'label'      => 'Tax Group',
        'table'      => 'ret_taxgroupmaster',
        'pk'         => 'tgrp_id',
        'status_col' => 'tgrp_status',
        // Products/categories/metal reference a tax group directly — block
        // deactivation while any of these still use it.
        'in_use_check' => [
            ['table' => 'ret_product_master', 'column' => 'tgrp_id'],
            ['table' => 'ret_category', 'column' => 'tgrp_id'],
            ['table' => 'metal', 'column' => 'tgrp_id'],
            ['table' => 'ret_supplier_rate_cut', 'column' => 'tgrp_id'],
        ],
        'rules' => [
            'tgrp_name' => [
                'label'      => 'Tax Group Name',
                'required'   => true,
                'rule'       => 'nameField',
                // tgrp_name has a genuine UNIQUE index at the DB level
                'unique'     => ['table' => 'ret_taxgroupmaster', 'column' => 'tgrp_name']
            ],
        ],
        'inputs' => [
            'tgrp_name' => ['string', 'nameField'],
        ],
        // Parent/detail: each tax group is a named bundle of tax components.
        // Legacy behavior was delete-all-then-reinsert on every save (no
        // external table references tgi_sno, confirmed via schema search),
        // so the engine's diff-merge naturally reduces to the same result.
        // NOTE: no child_table['in_use_check'] — re-verified (exact + partial
        // column-name search across the whole schema) that nothing references
        // tgi_sno; a removed tax-component row is genuinely safe to hard-delete,
        // not an oversight.
        'child_table' => [
            'table'      => 'ret_taxgroupitems',
            'fk_column'  => 'tgi_tgrpcode',
            'pk'         => 'tgi_sno',
            'post_key'   => 'tgi',
            'label'      => 'Tax Component',
            'min_rows'   => 1,
            // Same tax shouldn't be added twice within one group — it would
            // silently double-apply that tax's rate. Checked in-memory across
            // the submitted rows since the whole component list resubmits every save.
            'unique_within_parent' => 'tgi_taxcode',
            'dropdowns' => [
                'tgi_taxcode' => [
                    'table'  => 'ret_taxmaster',
                    'pk'     => 'tax_id',
                    'label'  => 'tax_name',
                    'filter' => ['tax_status' => 1],
                ],
            ],
            'rules' => [
                'tgi_taxcode' => [
                    'label'    => 'Tax',
                    'required' => true,
                    'fk_check' => ['table' => 'ret_taxmaster', 'pk' => 'tax_id', 'status_col' => 'tax_status'],
                ],
                'tgi_calculation' => [
                    'label'    => 'Calculation',
                    'required' => true,
                ],
                'tgi_type' => [
                    'label'    => 'Add/Sub',
                    'required' => true,
                ],
            ],
            'inputs' => [
                'tgi_taxcode'     => ['int', 'numeric'],
                'tgi_calculation' => ['int', 'numeric', ['options' => [[1, 'Base value'], [2, 'Arrived value']]]],
                'tgi_type'        => ['int', 'numeric', ['options' => [[1, '+'], [2, '-']]]],
            ],
        ],
    ],

    'attribute' => [
        'label'      => 'Attribute',
        'table'      => 'ret_attribute',
        'pk'         => 'attr_id',
        'status_col' => 'attr_status',
        // Products/tags reference an attribute (and specific values of it)
        // directly — block deactivation while either still uses it.
        'in_use_check' => [
            ['table' => 'ret_design_attributes', 'column' => 'attr_id'],
            ['table' => 'ret_tagging_attributes', 'column' => 'attr_id'],
        ],
        // Legacy uppercased the name on save (strtoupper) — kept so new rows
        // stay consistent with existing data.
        'transforms' => ['attr_name' => 'strtoupper'],
        'rules' => [
            'attr_name' => [
                'label'      => 'Attribute Name',
                'required'   => true,
                'rule'       => 'nameField',
                'unique'     => ['table' => 'ret_attribute', 'column' => 'attr_name']
            ],
        ],
        'inputs' => [
            'attr_name' => ['string', 'nameField'],
        ],
        // Parent/detail: each attribute (e.g. "Color") has a list of values
        // (e.g. "Red", "Blue"). Unlike Tax Group, ret_attribute_values.attr_val_id
        // IS referenced elsewhere (ret_design_attributes, ret_tagging_attributes
        // point at a specific value) — the engine's diff-merge-by-pk save is
        // what makes this safe to migrate at all; delete+reinsert would silently
        // dangle those references on every edit. in_use_check here blocks
        // REMOVING a value row (real hard delete, since child rows have no
        // status column to soft-delete) while it's still referenced —
        // masters never hard-delete, and the same rule applies to a value row
        // that's already tagged on real inventory.
        'child_table' => [
            'table'      => 'ret_attribute_values',
            'fk_column'  => 'attr_id',
            'pk'         => 'attr_val_id',
            'post_key'   => 'attr_val',
            'label'      => 'Value',
            'min_rows'   => 1,
            // No two values within the same attribute should be identical
            // (e.g. two "Red" rows under "Color") — checked in-memory across
            // the submitted rows since the whole value list resubmits every save.
            'unique_within_parent' => 'attr_val',
            'in_use_check' => [
                ['table' => 'ret_design_attributes', 'column' => 'attr_val_id'],
                ['table' => 'ret_tagging_attributes', 'column' => 'attr_val_id'],
            ],
            'rules' => [
                'attr_val' => [
                    'label'      => 'Value',
                    'required'   => true,
                    'rule'       => 'nameField',
                ],
            ],
            'inputs' => [
                'attr_val' => ['string', 'nameField'],
            ],
        ],
    ],

    'diamond_rate' => [
        'label'      => 'Diamond Rate Master',
        'table'      => 'ret_diamond_rate',
        'pk'         => 'rate_id',
        'status_col' => 'rate_status',
        // effective_date isn't a form field in legacy — it's always today() on
        // every save (add and edit).
        'set_today'  => ['effective_date'],
        'dropdowns' => [
            'quality_code_id' => [
                'table'  => 'ret_quality_code',
                'pk'     => 'quality_id',
                'label'  => 'code',
                'filter' => ['status' => 1],
            ],
        ],
        'rules' => [
            'quality_code_id' => [
                'label'      => 'Quality Code',
                'required'   => true,
                'fk_check'   => ['table' => 'ret_quality_code', 'pk' => 'quality_id', 'status_col' => 'status'],
                // quality_code_id has a genuine UNIQUE index at the DB level —
                // only one rate row can ever exist per quality code (legacy's
                // "deactivate previous rate" logic on this table is unreachable
                // dead code precisely because of this constraint; nothing to
                // preserve there).
                'unique'     => ['table' => 'ret_diamond_rate', 'column' => 'quality_code_id'],
            ],
        ],
        'inputs' => [
            'quality_code_id' => ['int', 'numeric'],
        ],
        // Parent/detail: cent-rate ranges for this quality's diamond rate.
        // Nothing external references id_cents_rate, so diff-merge safely
        // reduces to legacy's delete+reinsert behavior.
        // NOTE: no child_table['in_use_check'] — re-verified (exact + partial
        // column-name search) that nothing references id_cents_rate; billing/
        // tagging look up the applicable rate dynamically by carat+quality at
        // calculation time, they don't store a reference to a specific range
        // row. A removed range row is genuinely safe to hard-delete.
        'child_table' => [
            'table'      => 'ret_diamond_cent_rates',
            'fk_column'  => 'id_rate_id',
            'pk'         => 'id_cents_rate',
            'post_key'   => 'cent_rates',
            'label'      => 'Cent Rate Range',
            'min_rows'   => 1,
            'rules' => [
                'from_cent' => ['label' => 'From Cent', 'required' => true, 'min_val' => 0],
                'to_cent'   => ['label' => 'To Cent', 'required' => true, 'min_val' => 0],
                'rate'      => ['label' => 'Rate', 'required' => true, 'min_val' => 0],
            ],
            'inputs' => [
                'from_cent' => ['float', 'numeric'],
                'to_cent'   => ['float', 'numeric'],
                'rate'      => ['float', 'numeric'],
            ],
            'cross_field_rules' => [
                ['type' => 'range_order', 'from' => 'from_cent', 'to' => 'to_cent', 'message' => 'From Cent must not exceed To Cent'],
            ],
            // Two cent-rate ranges for the same quality code shouldn't overlap
            // (e.g. 0-50 and 30-80) — which rate applies would become ambiguous.
            // Checked in-memory across the submitted rows since the whole range
            // list resubmits every save.
            'no_overlap' => ['from_field' => 'from_cent', 'to_field' => 'to_cent'],
        ],
    ],

    'selling_diamond_rate' => [
        'label'      => 'Selling Diamond Rate Master',
        'table'      => 'ret_selling_diamond_rate',
        'pk'         => 'selling_rate_id',
        'status_col' => 'rate_status',
        'set_today'  => ['effective_date'],
        // Unlike Diamond Rate Master, quality_code_id has NO unique constraint
        // here — multiple historical rows per quality code are expected, only
        // one active at a time. exclusive_active deactivates any sibling
        // row(s) sharing the key before a new Add (or a status-toggle
        // reactivation) goes active — matching legacy's intent.
        // NOTE: legacy's Add case had a real bug here — it deactivated the
        // previous rate in ret_diamond_rate (the OTHER master's table) instead
        // of ret_selling_diamond_rate, almost certainly copy-paste from
        // diamond_rate()'s save case. Fixed here rather than preserved: this
        // engine correctly targets config['table'] (ret_selling_diamond_rate).
        'exclusive_active' => ['key_field' => 'quality_code_id'],
        'dropdowns' => [
            'quality_code_id' => [
                'table'  => 'ret_quality_code',
                'pk'     => 'quality_id',
                'label'  => 'code',
                'filter' => ['status' => 1],
            ],
        ],
        'rules' => [
            'quality_code_id' => [
                'label'      => 'Quality Code',
                'required'   => true,
                'fk_check'   => ['table' => 'ret_quality_code', 'pk' => 'quality_id', 'status_col' => 'status'],
            ],
        ],
        'inputs' => [
            'quality_code_id' => ['int', 'numeric'],
        ],
        // NOTE: no child_table['in_use_check'] — re-verified (exact + partial
        // column-name search) that nothing references id_selling_cents_rate,
        // same reasoning as Diamond Rate Master's cent_rates above. A removed
        // range row is genuinely safe to hard-delete.
        'child_table' => [
            'table'      => 'ret_selling_diamond_cent_rates',
            'fk_column'  => 'id_selling_rate_id',
            'pk'         => 'id_selling_cents_rate',
            'post_key'   => 'cent_rates',
            'label'      => 'Cent Rate Range',
            'min_rows'   => 1,
            'rules' => [
                'from_cent' => ['label' => 'From Cent', 'required' => true, 'min_val' => 0],
                'to_cent'   => ['label' => 'To Cent', 'required' => true, 'min_val' => 0],
                'rate'      => ['label' => 'Rate', 'required' => true, 'min_val' => 0],
            ],
            'inputs' => [
                'from_cent' => ['float', 'numeric'],
                'to_cent'   => ['float', 'numeric'],
                'rate'      => ['float', 'numeric'],
            ],
            'cross_field_rules' => [
                ['type' => 'range_order', 'from' => 'from_cent', 'to' => 'to_cent', 'message' => 'From Cent must not exceed To Cent'],
            ],
        ],
    ],

];
