<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Customer Validation Helper
 * -------------------------
 * Centralizes ALL server-side validation for customer CRUD operations.
 * Called from Admin_customer::cus_post() BEFORE any DB operation.
 *
 * Fixes: BUG-C02, BUG-C05, BUG-C06, BUG-H03, BUG-H04, BUG-H05,
 *        BUG-H06, BUG-M01, BUG-M03, BUG-M06, BUG-M07
 *
 * @author  QA Audit Remediation — Sprint 1
 * @date    2026-06-02
 */

/**
 * Validate customer form data server-side.
 *
 * @param  object      $CI           Controller instance (for form_validation, input, db)
 * @param  string      $mode         'add' or 'edit'
 * @param  int|null    $id_customer  Customer ID (for edit-mode uniqueness checks)
 * @return array       ['valid' => bool, 'errors' => string[]]
 */
function validate_customer_data(&$CI, $mode = 'add', $id_customer = null)
{
    $CI->load->library('form_validation');
    $CI->form_validation->set_error_delimiters('', '');

    // ===================================================================
    // REQUIRED FIELDS
    // ===================================================================
    $CI->form_validation->set_rules('customer[firstname]', 'First Name',
        'required|trim|min_length[2]|max_length[100]');

    $CI->form_validation->set_rules('customer[mobile]', 'Mobile',
        'required|exact_length[10]|numeric');

    $CI->form_validation->set_rules('customer[address1]', 'Address Line 1',
        'required|trim|max_length[200]');

    $CI->form_validation->set_rules('customer[country]', 'Country',
        'required|numeric|greater_than[0]');

    $CI->form_validation->set_rules('customer[state]', 'State',
        'required|numeric|greater_than[0]');

    $CI->form_validation->set_rules('customer[city]', 'City',
        'required|numeric|greater_than[0]');

    $CI->form_validation->set_rules('customer[pincode]', 'Pincode',
        'required|exact_length[6]|numeric');

    $CI->form_validation->set_rules('customer[gender]', 'Gender',
        'required|in_list[0,1,3]'); // 0=Male, 1=Female, 3=Other

    $CI->form_validation->set_rules('customer[email]', 'E-Mail',
        'required|trim|max_length[100]|valid_email');

    // ===================================================================
    // OPTIONAL BUT FORMAT-VALIDATED FIELDS
    // ===================================================================
    $CI->form_validation->set_rules('customer[lastname]', 'Last Name',
        'trim|max_length[100]');

    // Phone: validated manually below to allow empty values (CI3 numeric rejects empty)
    // $CI->form_validation->set_rules('customer[phone]', 'Phone', 'permit_empty|trim|max_length[15]|numeric');

    // Opening balance: validated manually below
    // $CI->form_validation->set_rules('customer[opening_balance_amount]', 'Opening Balance', 'permit_empty|trim|numeric|greater_than_equal_to[0]');

    $CI->form_validation->set_rules('customer[nominee_name]', 'Nominee Name',
        'trim|max_length[100]');

    $CI->form_validation->set_rules('customer[comments]', 'Comments',
        'trim|max_length[500]');

    $CI->form_validation->set_rules('customer[address2]', 'Address Line 2',
        'trim|max_length[200]');

    $CI->form_validation->set_rules('customer[address3]', 'Address Line 3',
        'trim|max_length[200]');

    $CI->form_validation->set_rules('customer[username]', 'Username',
        'trim|max_length[50]');

    // ===================================================================
    // CONDITIONAL: Company type (cus_type=2) requires GST
    // ===================================================================
    $cus = $CI->input->post('customer');

    if (isset($cus['cus_type']) && $cus['cus_type'] == 2) {
        $CI->form_validation->set_rules('customer[gst_number]', 'GST Number',
            'required|exact_length[15]|regex_match[/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/]');
    }

    // ===================================================================
    // RUN CI3 FORM VALIDATION
    // ===================================================================
    $errors = array();

    if ($CI->form_validation->run() === FALSE) {
        $all_errors = validation_errors(' ', "\n");
        if (!empty($all_errors)) {
            foreach (explode("\n", trim($all_errors)) as $err) {
                $err = trim($err);
                if (!empty($err)) {
                    $errors[] = $err;
                }
            }
        }
    }

    // ===================================================================
    // CUSTOM VALIDATIONS (beyond form_validation library)
    // ===================================================================

    if (empty($cus['email']) || trim($cus['email']) === '') {
        $errors[] = 'The E-Mail field is required.';
    } elseif (!filter_var(trim($cus['email']), FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'The E-Mail field must contain a valid email address.';
    }
    if (!empty($cus['phone']) && !ctype_digit(trim($cus['phone']))) {
        $errors[] = 'The Phone field must contain only numbers.';
    }
    if ($cus['opening_balance_amount'] !== '' && $cus['opening_balance_amount'] !== null && (!is_numeric($cus['opening_balance_amount']) || $cus['opening_balance_amount'] < 0)) {
        $errors[] = 'The Opening Balance must be a number >= 0.';
    }
    if (!empty($cus['nominee_mobile']) && (!ctype_digit($cus['nominee_mobile']) || strlen($cus['nominee_mobile']) > 10)) {
        $errors[] = 'The Nominee Mobile field must contain only numbers (max 10 digits).';
    }

    // --- Date of Birth: must be >= 18 years old, not in future ---
    if (!empty($cus['date_of_birth'])) {
        $dob_str = str_replace('/', '-', $cus['date_of_birth']);
        $dob_ts  = strtotime($dob_str);

        if ($dob_ts === false) {
            $errors[] = 'Invalid date of birth format.';
        } else {
            $dob   = date('Y-m-d', $dob_ts);
            $today = date('Y-m-d');

            if ($dob > $today) {
                $errors[] = 'Date of birth cannot be in the future.';
            }

            $age = (int) floor((strtotime($today) - strtotime($dob)) / (365.25 * 24 * 3600));
            if ($age < 18) {
                $errors[] = 'Customer must be at least 18 years old. Calculated age: ' . $age . '.';
            }
        }
    }

    // --- Wedding date must be after DOB ---
    if (!empty($cus['date_of_wed']) && !empty($cus['date_of_birth'])) {
        $dob_str = str_replace('/', '-', $cus['date_of_birth']);
        $wed_str = str_replace('/', '-', $cus['date_of_wed']);
        $dob_ts  = strtotime($dob_str);
        $wed_ts  = strtotime($wed_str);

        if ($dob_ts !== false && $wed_ts !== false) {
            if ($wed_ts <= $dob_ts) {
                $errors[] = 'Wedding date must be after date of birth.';
            }

            $age_at_wed = (int) floor(($wed_ts - $dob_ts) / (365.25 * 24 * 3600));
            if ($age_at_wed < 18) {
                $errors[] = 'Customer must be at least 18 years old at date of wedding.';
            }
        }
    }

    // --- Married status (2) requires wedding date ---
    if (isset($cus['marital_status']) && $cus['marital_status'] == 2 && empty($cus['date_of_wed'])) {
        $errors[] = 'Wedding date is required when marital status is Married.';
    }

    // --- Wedding date should not be present for Single status ---
    if (isset($cus['marital_status']) && $cus['marital_status'] == 1 && !empty($cus['date_of_wed'])) {
        $errors[] = 'Wedding date should not be provided when marital status is Single.';
    }

    // ===================================================================
    // PASSWORD VALIDATION
    // ===================================================================
    $passwd = isset($cus['passwd']) ? $cus['passwd'] : '';
    if ($mode === 'add') {
        if (empty($passwd)) {
            $errors[] = 'Password is required.';
        }
    }
    if (!empty($passwd)) {
        if (strlen($passwd) < 8) {
            $errors[] = 'Password must be at least 8 characters long.';
        }
    }

    return array(
        'valid'  => empty($errors),
        'errors' => $errors
    );
}

/**
 * Sanitize a string for safe HTML storage.
 *
 * @param  string|null $value
 * @return string|null
 */
function sanitize_customer_field($value)
{
    if ($value === null || $value === '') {
        return $value;
    }
    return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
}

// PHP mirror of validation.js RULES + PATTERNS (keep in sync)
// JS PATTERNS reference:
//   nameField         : /[^A-Za-z0-9\s_\-]/g   max:50
//   lettersOnly       : /[^A-Za-z]/g            max:50
//   textOnly          : /[^A-Za-z\s]/g           max:50
//   alphanumeric      : /[^A-Za-z0-9\s]/g       max:30
//   alphanumericStrict: /[^A-Za-z0-9_\-]/g      max:30
//   codeField         : /[^A-Za-z0-9]/g         max:10
//   numericOnly       : /[^0-9]/g               max:15
//   decimal           : /[^0-9.]/g              max:15
//   masterNameWithSpace: /[^A-Za-z0-9\s\-\.\/]/g max:100
// =============================================================================

$VALIDATION_RULES = array(

    'lettersOnly'         => array('regex' => '/[^A-Za-z]/',                     'max' => 50,  'msg' => 'Only letters are allowed. No numbers, spaces or special characters'),
    'nameField'           => array('regex' => '/[^A-Za-z0-9\s_\-]/',             'max' => 50,  'msg' => 'Only letters, numbers, spaces, underscore (_) and hyphen (-) are allowed'),
    'textOnly'            => array('regex' => '/[^A-Za-z\s]/',                   'max' => 50,  'msg' => 'Only letters and spaces are allowed'),
    'alphanumeric'        => array('regex' => '/[^A-Za-z0-9\s]/',                'max' => 30,  'msg' => 'Only letters, numbers and spaces are allowed'),
    'alphanumericStrict'  => array('regex' => '/[^A-Za-z0-9_\-]/',               'max' => 30,  'msg' => 'Only letters, numbers, underscore and hyphen are allowed'),
    'codeField'           => array('regex' => '/[^A-Za-z0-9]/',                  'max' => 10,  'msg' => 'Only letters and numbers are allowed'),
    'alphanumericNoSpace' => array('regex' => '/[^A-Za-z0-9]/',                  'max' => 50,  'msg' => 'Only letters and numbers are allowed (no spaces or special characters)'),
    'shortCode'           => array('regex' => '/[^A-Za-z0-9]/',                  'max' => 5,   'msg' => 'Only letters and numbers are allowed (max 5 characters)'),
    'productCode'         => array('regex' => '/[^A-Za-z0-9]/',                  'max' => 3,   'msg' => 'Only letters and numbers are allowed (max 3 characters)'),
    'numericOnly'         => array('regex' => '/[^0-9]/',                        'max' => 15,  'msg' => 'Only numbers are allowed'),
    'decimal'             => array('regex' => '/[^0-9.]/',                       'max' => 15,  'msg' => 'Only numbers and decimal point are allowed'),
    'hsnCode'             => array('regex' => '/[^0-9]/',                        'max' => 8,   'msg' => 'Only numbers allowed (max 8 digits)'),
    'maxMarkup'           => array('regex' => '/[^0-9]/',                        'max' => 3,   'msg' => 'Only numbers are allowed'),
    'minMarkup'           => array('regex' => '/[^0-9]/',                        'max' => 3,   'msg' => 'Only numbers are allowed'),
    'noOfPieces'          => array('regex' => '/[^0-9]/',                        'max' => 5,   'msg' => 'Only numbers are allowed'),
    'chargeValue'         => array('regex' => '/[^0-9.]/',                       'max' => 6,   'msg' => 'Only numbers and decimal point are allowed (max 6 digits)'),
    'pincode'             => array('regex' => '/[^0-9]/',                        'max' => 6,   'msg' => 'Only numbers are allowed for pincode'),
    'email'               => array('regex' => '/[^A-Za-z0-9@._\-]/',             'max' => 100, 'msg' => 'Invalid characters in email'),
    'phone'               => array('regex' => '/[^0-9+\-\s]/',                   'max' => 15,  'msg' => 'Only numbers, +, - and spaces are allowed'),
    'gstNumber'           => array('regex' => '/[^A-Za-z0-9]/',                  'max' => 15,  'msg' => 'Only letters and numbers are allowed for GST'),
    'panNumber'           => array('regex' => '/[^A-Za-z0-9]/',                  'max' => 10,  'msg' => 'Only letters and numbers are allowed for PAN'),
    'address'             => array('regex' => '/[^A-Za-z0-9\s,.\-\/#\(\)]/',     'max' => 200, 'msg' => 'Special characters are not allowed in address'),
    'description'         => array('regex' => null,                              'max' => 300, 'msg' => ''),
    'generalText'         => array('regex' => '/[^A-Za-z0-9\s,.\-\/\(\)&\'"]/', 'max' => 200, 'msg' => 'Some special characters are not allowed'),
    'taxName'             => array('regex' => '/[^A-Za-z0-9\s%_\-]/',            'max' => 30,  'msg' => 'Only letters, numbers, spaces, %, underscore (_) and hyphen (-) are allowed'),
    'taxCode'             => array('regex' => '/[^A-Za-z0-9\s%_\-]/',            'max' => 10,  'msg' => 'Only letters, numbers, spaces, %, underscore (_) and hyphen (-) are allowed'),
    'taxPercentage'       => array('regex' => '/[^0-9.%]/',                      'max' => 10,  'msg' => 'Only numbers, decimal point and % are allowed'),
    'masterNameWithSpace' => array('regex' => '/[^A-Za-z0-9\s\-\.\/]/',          'max' => 100, 'msg' => 'Only letters, numbers, spaces, hyphen (-), dot (.) and slash (/) are allowed. Characters like quotes, semicolons, < > are not permitted'),
    'categoryName'        => array('regex' => '/[^A-Za-z0-9\s_\-]/',             'max' => 50,  'msg' => 'Only letters, numbers, spaces, underscore (_) and hyphen (-) are allowed'),
    'metalName'           => array('regex' => '/[^A-Za-z0-9\s_\-]/',             'max' => 50,  'msg' => 'Only letters, numbers, spaces, underscore (_) and hyphen (-) are allowed'),
    'metalCode'           => array('regex' => '/[^A-Za-z0-9]/',                  'max' => 10,  'msg' => 'Only letters and numbers are allowed'),
    'shapeName'           => array('regex' => '/[^A-Za-z0-9\s_\-]/',             'max' => 50,  'msg' => 'Only letters, numbers, spaces, underscore (_) and hyphen (-) are allowed'),
    'productName'         => array('regex' => '/[^A-Za-z0-9\s_\-]/',             'max' => 50,  'msg' => 'Only letters, numbers, spaces, underscore (_) and hyphen (-) are allowed'),
    // Supplier Catalog specific rules
    'supplierSize'        => array('regex' => '/[^0-9]/',                        'max' => 10,  'msg' => 'Only numbers are allowed (max 10 digits)'),
    'supplierWeight'      => array('regex' => '/[^0-9.]/',                       'max' => 10,  'msg' => 'Only numbers and decimal point are allowed (max 10 characters, e.g. 3000.000)'),
);

// Sanitize field value using named rule
function sanitize_by_rule($value, $rule_name)
{
    global $VALIDATION_RULES;

    if ($value === null) {
        return '';
    }

    $value = trim($value);
    $rule  = isset($VALIDATION_RULES[$rule_name]) ? $VALIDATION_RULES[$rule_name] : null;

    if ($rule === null) {
        return $value;
    }

    if (!empty($rule['regex'])) {
        $value = preg_replace($rule['regex'], '', $value);
    }

    if (!empty($rule['max'])) {
        $value = substr($value, 0, $rule['max']);
    }

    return $value;
}

// Validate any single POST field using a named rule
function validate_master_field(&$CI, $post_key, $rule_name, $label, $required = true, $min_length = 2)
{
    global $VALIDATION_RULES;

    $errors    = array();
    $raw_value = $CI->input->post($post_key);

    // Required check
    if ($required && ($raw_value === false || trim($raw_value) === '')) {
        return array('valid' => false, 'errors' => array($label . ' is required.'));
    }

    // Optional and empty — OK
    if (!$required && ($raw_value === false || trim($raw_value) === '')) {
        return array('valid' => true, 'errors' => array());
    }

    // =========================================================================
    // SECURITY LAYER 1 — CI3 built-in XSS clean (strips encoded attacks)
    // Handles: HTML entities, encoded payloads, attribute injections
    // =========================================================================
    $raw_value = $CI->security->xss_clean($raw_value);

    $value = trim($raw_value);
    $rule  = isset($VALIDATION_RULES[$rule_name]) ? $VALIDATION_RULES[$rule_name] : null;

    if ($rule === null) {
        return array('valid' => true, 'errors' => array());
    }

    // Minimum length
    if ($min_length > 0 && strlen($value) < $min_length) {
        $errors[] = $label . ' must be at least ' . $min_length . ' characters long.';
    }

    // Maximum length (mirrors validation.js RULES[x].max)
    if (!empty($rule['max']) && strlen($value) > $rule['max']) {
        $errors[] = $label . ' must not exceed ' . $rule['max'] . ' characters.';
    }

    // Character whitelist (mirrors validation.js RULES[x].regex)
    // NOTE: This inherently blocks SQL chars (' " ; -- #) and XSS chars (< > &)
    if (!empty($rule['regex']) && preg_match($rule['regex'], $value)) {
        $msg = !empty($rule['msg']) ? $rule['msg'] : 'Invalid characters in ' . $label;
        $errors[] = $label . ': ' . $msg . '.';
    }

    // =========================================================================
    // SECURITY LAYER 2 — SQL Injection pattern detection
    // Defence-in-depth: CI3 Active Record auto-escapes all values, but we
    // reject suspicious patterns at the validation layer for a clear error msg.
    // =========================================================================
    $sql_patterns = array(
        '--',           // SQL comment
        '/*',           // SQL block comment
        '*/',           // SQL block comment close
        'xp_',          // SQL Server extended procs
        'UNION ',       'union ',
        'SELECT ',      'select ',
        'INSERT ',      'insert ',
        'UPDATE ',      'update ',
        'DELETE ',      'delete ',
        'DROP ',        'drop ',
        'TRUNCATE ',    'truncate ',
        'ALTER ',       'alter ',
        'EXEC(',        'exec(',
        'EXECUTE(',     'execute(',
        'CAST(',        'cast(',
        'CONVERT(',     'convert(',
        'CHAR(',        'char(',
        'DECLARE ',     'declare ',
        'BENCHMARK(',   'benchmark(',
        'SLEEP(',       'sleep(',
        'LOAD_FILE(',   'load_file(',
        'INTO OUTFILE', 'into outfile',
    );
    $lower_val = strtolower($value);
    foreach ($sql_patterns as $pattern) {
        if (strpos(strtolower($lower_val), strtolower($pattern)) !== false) {
            $errors[] = $label . ' contains invalid content (possible SQL injection attempt).';
            break;
        }
    }

    // =========================================================================
    // SECURITY LAYER 3 — XSS pattern detection
    // Catches encoded/obfuscated payloads that pass the whitelist regex
    // =========================================================================
    $xss_patterns = array(
        '<script',      '</script',
        'javascript:',
        'onload=',      'onerror=',   'onclick=',
        'onmouseover=', 'onfocus=',   'onblur=',
        '<iframe',      '</iframe',
        '<object',      '<embed',
        '<form',        '<input',
        'eval(',        'expression(',
        'alert(',       'confirm(',   'prompt(',
        'document.cookie', 'document.write',
        'window.location',
        '&#',           // HTML entity injection
        '%3c',          '%3e',        // URL-encoded < >
        'vbscript:',
    );
    foreach ($xss_patterns as $pattern) {
        if (strpos($lower_val, strtolower($pattern)) !== false) {
            $errors[] = $label . ' contains invalid content (XSS not allowed).';
            break;
        }
    }

    return array(
        'valid'  => empty($errors),
        'errors' => $errors,
    );
}


// MODULE-SPECIFIC VALIDATORS

// Validate QC Cancel Reason: nameField rule
function validate_cancel_reason_data(&$CI, $mode = 'add', $id = null)
{
    // Add modal: 'reason_name' | Edit modal: 'cancel_reason'
    $field_name = ($mode === 'add') ? 'reason_name' : 'cancel_reason';
    return validate_master_field($CI, $field_name, 'nameField', 'Cancel Reason', true, 2);
}

// Sanitize master name field using nameField rule
function sanitize_master_name_with_space($value)
{
    return sanitize_by_rule($value, 'nameField');
}

// Validate Repair Master: nameField rule
function validate_repair_master_data(&$CI, $mode = 'add', $id = null)
{
    // Add modal: POST name="repair" | Edit modal: POST name="name"
    $field_name = ($mode === 'add') ? 'repair' : 'name';
    return validate_master_field($CI, $field_name, 'nameField', 'Repair Name', true, 2);
}

// Validate Diamond Clarity: nameField rule
function validate_clarity_data(&$CI, $mode = 'add', $id = null)
{
    // Both Add and Edit modal POST: name="clarity"
    return validate_master_field($CI, 'clarity', 'nameField', 'Diamond Clarity', true, 2);
}

// Validate Color: nameField rule
function validate_color_data(&$CI, $mode = 'add', $id = null)
{
    // Both Add modal (name="color") and Edit modal (name="color") use same POST key
    return validate_master_field($CI, 'color', 'nameField', 'Color Name', true, 2);
}

// Validate Floor Counter: counter_name (nameField) + counter_short_code (shortCode)
function validate_floor_counter_data(&$CI, $mode = 'add', $id = null)
{
    // Add modal: name="counter_name"    | Edit modal: name="ed_counter_name"
    $name_field = ($mode === 'add') ? 'counter_name' : 'ed_counter_name';
    // Add modal: name="counter_short_code" | Edit modal: name="ed_counter_short_code"
    $code_field = ($mode === 'add') ? 'counter_short_code' : 'ed_counter_short_code';

    $name_result = validate_master_field($CI, $name_field, 'nameField', 'Counter Name', true, 2);
    if (!$name_result['valid']) {
        return $name_result;
    }

    $code_result = validate_master_field($CI, $code_field, 'shortCode', 'Counter Short Code', true, 1);
    if (!$code_result['valid']) {
        return $code_result;
    }

    return array('valid' => true, 'errors' => array());
}

// Validate Diamond Shape: nameField rule
function validate_shape_data(&$CI, $mode = 'add', $id = null)
{
    // Both Add and Edit use POST key 'name'
    return validate_master_field($CI, 'name', 'nameField', 'Diamond Shape', true, 2);
}

// Validate Diamond Cut: nameField rule
function validate_cut_data(&$CI, $mode = 'add', $id = null)
{
    // Both Add and Edit use POST key 'cut'
    return validate_master_field($CI, 'cut', 'nameField', 'Diamond Cut', true, 2);
}

// Validate Branch Floor: floor_name (nameField) + floor_short_code (shortCode)
function validate_branch_floor_data(&$CI, $mode = 'add', $id = null)
{
    // Both Add and Edit use same POST key: 'floor_name'
    $name_result = validate_master_field($CI, 'floor_name', 'nameField', 'Floor Name', true, 2);
    if (!$name_result['valid']) {
        return $name_result;
    }

    // Both Add and Edit use same POST key: 'floor_short_code'
    $code_result = validate_master_field($CI, 'floor_short_code', 'shortCode', 'Floor Short Code', true, 1);
    if (!$code_result['valid']) {
        return $code_result;
    }

    return array('valid' => true, 'errors' => array());
}

// Validate Making Type: mak_name (nameField) + mak_short_code (shortCode)
function validate_making_type_data(&$CI, $mode = 'add', $id = null)
{
    // Both Add and Edit use same POST key: 'mak_name'
    $name_result = validate_master_field($CI, 'mak_name', 'nameField', 'Making Type Name', true, 2);
    if (!$name_result['valid']) {
        return $name_result;
    }

    // Both Add and Edit use same POST key: 'mak_short_code'
    $code_result = validate_master_field($CI, 'mak_short_code', 'shortCode', 'Making Type Short Code', true, 1);
    if (!$code_result['valid']) {
        return $code_result;
    }

    return array('valid' => true, 'errors' => array());
}

// Validate Material: material_name (nameField) + material_code (codeField)
function validate_material_data(&$CI, $mode = 'add', $id = null)
{
    // Both Add and Edit use same POST key: 'material_name'
    $name_result = validate_master_field($CI, 'material_name', 'nameField', 'Material Name', true, 2);
    if (!$name_result['valid']) {
        return $name_result;
    }

    // Both Add and Edit use same POST key: 'material_code'
    $code_result = validate_master_field($CI, 'material_code', 'codeField', 'Material Code', true, 1);
    if (!$code_result['valid']) {
        return $code_result;
    }

    return array('valid' => true, 'errors' => array());
}

// Validate Hook Type: hook_name (nameField) + hook_short_code (shortCode)
function validate_hook_data(&$CI, $mode = 'add', $id = null)
{
    // Both Add and Edit use same POST key: 'hook_name'
    $name_result = validate_master_field($CI, 'hook_name', 'nameField', 'Hook Name', true, 2);
    if (!$name_result['valid']) {
        return $name_result;
    }

    // Both Add and Edit use same POST key: 'hook_short_code'
    $code_result = validate_master_field($CI, 'hook_short_code', 'shortCode', 'Hook Short Code', true, 1);
    if (!$code_result['valid']) {
        return $code_result;
    }

    return array('valid' => true, 'errors' => array());
}

// =============================================================================
// PURITY VALIDATOR — use for purity master fields
// =============================================================================

/**
 * Validate Purity master fields using decimal and nameField rules.
 * Enforces decimal format validation on purity and nameField rule on description.
 *
 * Usage:
 *   $validation = validate_purity_data($this, 'add');
 *   $validation = validate_purity_data($this, 'edit', $id);
 *
 * @param  object  $CI          Controller instance
 * @param  string  $mode        'add' or 'edit'
 * @param  int|null $id         Purity ID (optional)
 * @return array   ['valid' => bool, 'errors' => string[]]
 */
function validate_purity_data(&$CI, $mode = 'add', $id = null)
{
    $purity_result = validate_master_field($CI, 'purity', 'decimal', 'Purity', true, 1);
    if (!$purity_result['valid']) {
        return $purity_result;
    }

    $desc_result = validate_master_field($CI, 'description', 'nameField', 'Description', false, 2);
    if (!$desc_result['valid']) {
        return $desc_result;
    }

    return array('valid' => true, 'errors' => array());
}

// Validate Screw Type: screw_name (nameField) + screw_short_code (shortCode)
function validate_screw_data(&$CI, $mode = 'add', $id = null)
{
    $name_result = validate_master_field($CI, 'screw_name', 'nameField', 'Screw Name', true, 2);
    if (!$name_result['valid']) {
        return $name_result;
    }

    $code_result = validate_master_field($CI, 'screw_short_code', 'shortCode', 'Screw Short Code', true, 1);
    if (!$code_result['valid']) {
        return $code_result;
    }

    return array('valid' => true, 'errors' => array());
}

// Validate Stone: stone_name (nameField) + stone_code (codeField)
function validate_stone_data(&$CI, $mode = 'add', $id = null)
{
    $name_result = validate_master_field($CI, 'stone_name', 'nameField', 'Stone Name', true, 2);
    if (!$name_result['valid']) {
        return $name_result;
    }

    $code_result = validate_master_field($CI, 'stone_code', 'codeField', 'Stone Code', true, 1);
    if (!$code_result['valid']) {
        return $code_result;
    }

    return array('valid' => true, 'errors' => array());
}

// Validate Tag Type: nameField rule
function validate_tag_data(&$CI, $mode = 'add', $id = null)
{
    return validate_master_field($CI, 'tag_name', 'nameField', 'Tag Name', true, 2);
}

// Validate Tax: tax_name (taxName), tax_code (taxCode), tax_percentage (decimal)
function validate_tax_data(&$CI, $mode = 'add', $id = null)
{
    $name_result = validate_master_field($CI, 'tax_name', 'taxName', 'Tax Name', true, 2);
    if (!$name_result['valid']) {
        return $name_result;
    }

    $code_result = validate_master_field($CI, 'tax_code', 'taxCode', 'Tax Code', true, 1);
    if (!$code_result['valid']) {
        return $code_result;
    }

    $pct_result = validate_master_field($CI, 'tax_percentage', 'taxPercentage', 'Tax Percentage', true, 1);
    if (!$pct_result['valid']) {
        return $pct_result;
    }

    return array('valid' => true, 'errors' => array());
}

// Validate UOM: uom_name (nameField) + uom_short_code (shortCode)
function validate_uom_data(&$CI, $mode = 'add', $id = null)
{
    $name_result = validate_master_field($CI, 'uom_name', 'nameField', 'UOM Name', true, 2);
    if (!$name_result['valid']) {
        return $name_result;
    }

    $code_result = validate_master_field($CI, 'uom_short_code', 'shortCode', 'UOM Short Code', true, 1);
    if (!$code_result['valid']) {
        return $code_result;
    }

    return array('valid' => true, 'errors' => array());
}

// Validate Old Metal Type: metal_type text field (nameField)
function validate_metal_type_data(&$CI, $mode = 'add', $id = null)
{
    $field = ($mode === 'add') ? 'metal_type' : 'ed_metal_type';
    $result = validate_master_field($CI, $field, 'nameField', 'Metal Type', true, 2);
    if (!$result['valid']) {
        return $result;
    }

    // Duplicate check — case-insensitive
    $value = trim($CI->input->post($field));
    $CI->db->where('LOWER(metal_type) = ' . $CI->db->escape(strtolower($value)), NULL, FALSE);
    if ($mode === 'edit' && !empty($id)) {
        $CI->db->where('id_metal_type !=', $id);
    } elseif ($mode === 'edit') {
        $edit_id = $CI->input->post('id_metal_type');
        if (!empty($edit_id)) {
            $CI->db->where('id_metal_type !=', $edit_id);
        }
    }
    $dup_count = $CI->db->count_all_results('ret_old_metal_type');
    if ($dup_count > 0) {
        return array('valid' => false, 'errors' => array('Metal Type "' . $value . '" already exists.'));
    }

    return array('valid' => true, 'errors' => array());
}

// Validate Theme: theme_name (nameField) + themecode/themeshortcode (shortCode) + themedesc optional
function validate_theme_data(&$CI, $mode = 'add', $id = null)
{
    $name_result = validate_master_field($CI, 'theme_name', 'nameField', 'Theme Name', true, 2);
    if (!$name_result['valid']) {
        return $name_result;
    }

    // Add modal: JS sends 'themecode' | Edit modal: JS sends 'themeshortcode'
    $code_field = ($mode === 'add') ? 'themecode' : 'themeshortcode';
    $code_result = validate_master_field($CI, $code_field, 'shortCode', 'Theme Code', true, 1);
    if (!$code_result['valid']) {
        return $code_result;
    }

    $desc_result = validate_master_field($CI, 'themedesc', 'masterNameWithSpace', 'Theme Description', false, 0);
    if (!$desc_result['valid']) {
        return $desc_result;
    }

    return array('valid' => true, 'errors' => array());
}

// Validate Delivery Location: name (nameField)
function validate_delivery_data(&$CI, $mode = 'add', $id = null)
{
    return validate_master_field($CI, 'name', 'nameField', 'Delivery Location', true, 2);
}

// Validate Section: section_name (nameField) + short_code (shortCode)
function validate_section_data(&$CI, $mode = 'add', $id = null)
{
    $name_result = validate_master_field($CI, 'section_name', 'nameField', 'Section Name', true, 2);
    if (!$name_result['valid']) {
        return $name_result;
    }

    $code_result = validate_master_field($CI, 'short_code', 'shortCode', 'Section Short Code', true, 1);
    if (!$code_result['valid']) {
        return $code_result;
    }

    return array('valid' => true, 'errors' => array());
}

// Validate Size Units: unit (nameField) — Add uses POST key 'unit', Update uses 'units'
function validate_ret_size_data(&$CI, $mode = 'add', $id = null)
{
    $field_name = ($mode === 'add') ? 'unit' : 'units';
    $result = validate_master_field($CI, $field_name, 'nameField', 'Units', true, 1);
    if (!$result['valid']) {
        return $result;
    }
    // Units field max: 20 chars (shorter than nameField's global 50)
    $value = trim($CI->input->post($field_name));
    if (strlen($value) > 20) {
        return array('valid' => false, 'errors' => array('Units must not exceed 20 characters.'));
    }
    return array('valid' => true, 'errors' => array());
}

// Validate Old Metal Category: old_metal_cat (nameField), old_metal_perc (taxPercentage), old_metal_discount (taxPercentage optional)
function validate_old_metal_cat_data(&$CI, $mode = 'add', $id = null)
{
    $name_field = ($mode === 'add') ? 'old_metal_cat' : 'ed_old_metal_cat';
    $perc_field = ($mode === 'add') ? 'old_metal_perc' : 'ed_old_metal_perc';
    $disc_field = ($mode === 'add') ? 'old_metal_discount' : 'ed_old_metal_discount';

    $name_result = validate_master_field($CI, $name_field, 'nameField', 'Old Metal Category Name', true, 2);
    if (!$name_result['valid']) {
        return $name_result;
    }

    $perc_result = validate_master_field($CI, $perc_field, 'taxPercentage', 'Percentage', true, 1);
    if (!$perc_result['valid']) {
        return $perc_result;
    }

    $disc_result = validate_master_field($CI, $disc_field, 'taxPercentage', 'Discount', false, 0);
    if (!$disc_result['valid']) {
        return $disc_result;
    }

    return array('valid' => true, 'errors' => array());
}

// Validate Charges: charge_name (nameField), charge_code (nameField), value_charge (decimal), charge_tax (taxPercentage)
function validate_charges_data(&$CI, $mode = 'add', $id = null)
{
    // Add: charge_name | Update: name_charge
    $name_field = ($mode === 'add') ? 'charge_name' : 'name_charge';
    $name_result = validate_master_field($CI, $name_field, 'nameField', 'Charge Name', true, 2);
    if (!$name_result['valid']) {
        return $name_result;
    }

    // Add: charge_code | Update: charge_code
    $code_result = validate_master_field($CI, 'charge_code', 'nameField', 'Charge Code', true, 1);
    if (!$code_result['valid']) {
        return $code_result;
    }

    // Add: value_charge | Update: value_charge
    $value_result = validate_master_field($CI, 'value_charge', 'decimal', 'Charge Value', true, 1);
    if (!$value_result['valid']) {
        return $value_result;
    }

    // Add: charge_tax | Update: charge_tax
    $tax_result = validate_master_field($CI, 'charge_tax', 'taxPercentage', 'Charge Tax(%)', false, 0);
    if (!$tax_result['valid']) {
        return $tax_result;
    }

    return array('valid' => true, 'errors' => array());
}

// Validate Attribute: attr_name (nameField) + attr_val[] values (nameField)
// Validate Category: name (categoryName), cat_code (codeField), hsn_code (hsnCode)
function validate_category_data(&$CI, $mode = 'add', $id = null)
{
    $name_result = validate_master_field($CI, 'name', 'categoryName', 'Category Name', true, 2);
    if (!$name_result['valid']) {
        return $name_result;
    }

    $code_result = validate_master_field($CI, 'cat_code', 'codeField', 'Category Code', true, 1);
    if (!$code_result['valid']) {
        return $code_result;
    }

    $hsn_result = validate_master_field($CI, 'hsn_code', 'hsnCode', 'HSN Code', false, 0);
    if (!$hsn_result['valid']) {
        return $hsn_result;
    }

    return array('valid' => true, 'errors' => array());
}


function validate_attribute_data(&$CI, $mode = 'add', $id = null)
{
    global $VALIDATION_RULES;

    $errors = array();
    $attr = $CI->input->post('attr');

    // attr_name — required, nameField rule
    $attr_name = isset($attr['attr_name']) ? trim($attr['attr_name']) : '';
    if ($attr_name === '') {
        return array('valid' => false, 'errors' => array('Attribute Name is required.'));
    }

    // XSS clean
    $attr_name = $CI->security->xss_clean($attr_name);
    $attr_name = trim($attr_name);

    $rule = isset($VALIDATION_RULES['nameField']) ? $VALIDATION_RULES['nameField'] : array('regex' => '/[^A-Za-z0-9\s_\-]/', 'max' => 50, 'msg' => 'Only letters, numbers, spaces, underscore (_) and hyphen (-) are allowed');

    if (strlen($attr_name) < 2) {
        $errors[] = 'Attribute Name must be at least 2 characters long.';
    }
    if (!empty($rule['max']) && strlen($attr_name) > $rule['max']) {
        $errors[] = 'Attribute Name must not exceed ' . $rule['max'] . ' characters.';
    }
    if (!empty($rule['regex']) && preg_match($rule['regex'], $attr_name)) {
        $errors[] = 'Attribute Name: ' . $rule['msg'] . '.';
    }

    // SQL injection check
    $sql_patterns = array('--','/*','*/','xp_','UNION ','union ','SELECT ','select ','INSERT ','insert ','UPDATE ','update ','DELETE ','delete ','DROP ','drop ','TRUNCATE ','truncate ','ALTER ','alter ','EXEC(','exec(','EXECUTE(','execute(','CAST(','cast(','CONVERT(','convert(','CHAR(','char(','DECLARE ','declare ','BENCHMARK(','benchmark(','SLEEP(','sleep(','LOAD_FILE(','load_file(','INTO OUTFILE','into outfile');
    $lower_name = strtolower($attr_name);
    foreach ($sql_patterns as $pattern) {
        if (strpos($lower_name, strtolower($pattern)) !== false) {
            $errors[] = 'Attribute Name contains invalid content (possible SQL injection attempt).';
            break;
        }
    }

    // XSS pattern check
    $xss_patterns = array('<script','</script','javascript:','onload=','onerror=','onclick=','onmouseover=','onfocus=','onblur=','<iframe','</iframe','<object','<embed','<form','<input','eval(','expression(','alert(','confirm(','prompt(','document.cookie','document.write','window.location','&#','%3c','%3e','vbscript:');
    foreach ($xss_patterns as $pattern) {
        if (strpos($lower_name, strtolower($pattern)) !== false) {
            $errors[] = 'Attribute Name contains invalid content (XSS not allowed).';
            break;
        }
    }

    if (!empty($errors)) {
        return array('valid' => false, 'errors' => $errors);
    }

    // Duplicate name check — case-insensitive
    $CI->db->where('LOWER(attr_name) = ' . $CI->db->escape(strtolower($attr_name)), NULL, FALSE);
    $CI->db->where('attr_status', 1);
    if ($mode === 'edit' && !empty($id)) {
        $CI->db->where('attr_id !=', $id);
    }
    $dup_count = $CI->db->count_all_results('ret_attribute');
    if ($dup_count > 0) {
        return array('valid' => false, 'errors' => array('Attribute Name "' . $attr_name . '" already exists. Please enter a different name.'));
    }

    // Validate each attribute value
    if (isset($attr['attr_val']) && is_array($attr['attr_val'])) {
        foreach ($attr['attr_val'] as $idx => $val) {
            $val = trim($CI->security->xss_clean($val));
            if ($val === '') {
                $errors[] = 'Attribute Value #' . ($idx + 1) . ' is required.';
                continue;
            }
            if (!empty($rule['max']) && strlen($val) > $rule['max']) {
                $errors[] = 'Attribute Value #' . ($idx + 1) . ' must not exceed ' . $rule['max'] . ' characters.';
            }
            if (!empty($rule['regex']) && preg_match($rule['regex'], $val)) {
                $errors[] = 'Attribute Value #' . ($idx + 1) . ': ' . $rule['msg'] . '.';
            }
            $lower_val = strtolower($val);
            foreach ($sql_patterns as $pattern) {
                if (strpos($lower_val, strtolower($pattern)) !== false) {
                    $errors[] = 'Attribute Value #' . ($idx + 1) . ' contains invalid content (possible SQL injection attempt).';
                    break;
                }
            }
            foreach ($xss_patterns as $pattern) {
                if (strpos($lower_val, strtolower($pattern)) !== false) {
                    $errors[] = 'Attribute Value #' . ($idx + 1) . ' contains invalid content (XSS not allowed).';
                    break;
                }
            }
        }
    }

    return array(
        'valid'  => empty($errors),
        'errors' => $errors,
    );
}

// Validate Product Division: masterNameWithSpace rule (allows dot)
function validate_product_division_data(&$CI, $mode = 'add', $id = null)
{
    return validate_master_field($CI, 'div_value', 'masterNameWithSpace', 'Product Division', true, 2);
}

// Validate Stock Issue Type: nameField rule
function validate_stock_issue_type_data(&$CI, $mode = 'add', $id = null)
{
    return validate_master_field($CI, 'stock_name', 'nameField', 'Stock Name', true, 2);
}

// Validate CR/DR Ledger: nameField rule
function validate_crdr_ledger_data(&$CI, $mode = 'add', $id = null)
{
    return validate_master_field($CI, 'name', 'nameField', 'Ledger Name', true, 2);
}

// Validate Diamond Quality: code from quality[] POST array
function validate_diamond_quality_data(&$CI, $mode = 'add', $id = null)
{
    global $VALIDATION_RULES;

    $quality = $CI->input->post('quality');
    $code = isset($quality['code']) ? trim($quality['code']) : '';

    if ($code === '') {
        return array('valid' => false, 'errors' => array('Quality Code is required.'));
    }

    // XSS clean
    $code = $CI->security->xss_clean($code);
    $code = trim($code);

    $rule = isset($VALIDATION_RULES['codeField']) ? $VALIDATION_RULES['codeField'] : array('regex' => '/[^A-Za-z0-9]/', 'max' => 10, 'msg' => 'Only letters and numbers are allowed');
    // Override: Diamond Quality allows spaces, hyphens, underscores
    $rule['regex'] = '/[^A-Za-z0-9\s\-_]/';
    $rule['msg'] = 'Only letters, numbers, spaces, hyphens and underscores are allowed';
    $errors = array();

    if (!empty($rule['max']) && strlen($code) > $rule['max']) {
        $errors[] = 'Quality Code must not exceed ' . $rule['max'] . ' characters.';
    }
    if (!empty($rule['regex']) && preg_match($rule['regex'], $code)) {
        $errors[] = 'Quality Code: ' . $rule['msg'] . '.';
    }

    // SQL injection check
    $sql_patterns = array('--','/*','*/','xp_','UNION ','union ','SELECT ','select ','INSERT ','insert ','UPDATE ','update ','DELETE ','delete ','DROP ','drop ','TRUNCATE ','truncate ','ALTER ','alter ','EXEC(','exec(','EXECUTE(','execute(','CAST(','cast(','CONVERT(','convert(','CHAR(','char(','DECLARE ','declare ','BENCHMARK(','benchmark(','SLEEP(','sleep(','LOAD_FILE(','load_file(','INTO OUTFILE','into outfile');
    $lower_val = strtolower($code);
    foreach ($sql_patterns as $pattern) {
        if (strpos($lower_val, strtolower($pattern)) !== false) {
            $errors[] = 'Quality Code contains invalid content (possible SQL injection attempt).';
            break;
        }
    }

    // XSS pattern check
    $xss_patterns = array('<script','</script','javascript:','onload=','onerror=','onclick=','onmouseover=','onfocus=','onblur=','<iframe','</iframe','<object','<embed','<form','<input','eval(','expression(','alert(','confirm(','prompt(','document.cookie','document.write','window.location','&#','%3c','%3e','vbscript:');
    foreach ($xss_patterns as $pattern) {
        if (strpos($lower_val, strtolower($pattern)) !== false) {
            $errors[] = 'Quality Code contains invalid content (XSS not allowed).';
            break;
        }
    }

    return array('valid' => empty($errors), 'errors' => $errors);
}

// Validate Wastage Discount: 3 decimal fields
function validate_wastage_discount_data(&$CI, $mode = 'add', $id = null)
{
    $from_field = ($mode === 'add') ? 'from_wastage' : 'from_range';
    $to_field   = ($mode === 'add') ? 'to_wastage' : 'to_range';
    $val_field  = ($mode === 'add') ? 'disc_value' : 'value';

    $from_result = validate_master_field($CI, $from_field, 'decimal', 'From Wastage', true, 1);
    if (!$from_result['valid']) {
        return $from_result;
    }

    $to_result = validate_master_field($CI, $to_field, 'decimal', 'To Wastage', true, 1);
    if (!$to_result['valid']) {
        return $to_result;
    }

    $val_result = validate_master_field($CI, $val_field, 'decimal', 'Discount Value', true, 1);
    if (!$val_result['valid']) {
        return $val_result;
    }

    return array('valid' => true, 'errors' => array());
}

// Validate Pay Device: nameField rule
function validate_pay_device_data(&$CI, $mode = 'add', $id = null)
{
    return validate_master_field($CI, 'device_name', 'nameField', 'Device Name', true, 2);
}

// Validate Account Head: nameField rule
function validate_account_head_data(&$CI, $mode = 'add', $id = null)
{
    return validate_master_field($CI, 'name', 'nameField', 'Account Head Name', true, 2);
}

// Sanitize and validate a free-text value (xss_clean + SQL injection + XSS pattern check)
function sanitize_and_validate_text(&$CI, $value, $label, $max_length = 200)
{
    $errors = array();

    if ($value === null || $value === false || trim($value) === '') {
        return array('valid' => true, 'errors' => array(), 'clean' => '');
    }

    // Layer 1: CI3 xss_clean
    $value = $CI->security->xss_clean($value);
    $value = trim($value);

    // Max length
    if ($max_length > 0 && strlen($value) > $max_length) {
        $errors[] = $label . ' must not exceed ' . $max_length . ' characters.';
    }

    // Layer 2: SQL Injection pattern detection
    $sql_patterns = array('--','/*','*/','xp_','UNION ','union ','SELECT ','select ','INSERT ','insert ','UPDATE ','update ','DELETE ','delete ','DROP ','drop ','TRUNCATE ','truncate ','ALTER ','alter ','EXEC(','exec(','EXECUTE(','execute(','CAST(','cast(','CONVERT(','convert(','CHAR(','char(','DECLARE ','declare ','BENCHMARK(','benchmark(','SLEEP(','sleep(','LOAD_FILE(','load_file(','INTO OUTFILE','into outfile');
    $lower_val = strtolower($value);
    foreach ($sql_patterns as $pattern) {
        if (strpos($lower_val, strtolower($pattern)) !== false) {
            $errors[] = $label . ' contains invalid content (possible SQL injection attempt).';
            break;
        }
    }

    // Layer 3: XSS pattern detection
    $xss_patterns = array('<script','</script','javascript:','onload=','onerror=','onclick=','onmouseover=','onfocus=','onblur=','<iframe','</iframe','<object','<embed','<form','<input','eval(','expression(','alert(','confirm(','prompt(','document.cookie','document.write','window.location','&#','%3c','%3e','vbscript:');
    foreach ($xss_patterns as $pattern) {
        if (strpos($lower_val, strtolower($pattern)) !== false) {
            $errors[] = $label . ' contains invalid content (XSS not allowed).';
            break;
        }
    }

    return array('valid' => empty($errors), 'errors' => $errors, 'clean' => $value);
}

// Validate a numeric POST value with optional min/max range
function validate_numeric_range(&$CI, $post_key, $label, $min = null, $max = null)
{
    $errors = array();
    $raw = $CI->input->post($post_key);

    if ($raw === false || trim($raw) === '') {
        return array('valid' => false, 'errors' => array($label . ' is required.'));
    }

    if (!is_numeric($raw)) {
        return array('valid' => false, 'errors' => array($label . ' must be a valid number.'));
    }

    $val = floatval($raw);
    if ($min !== null && $val < $min) {
        $errors[] = $label . ' must be at least ' . $min . '.';
    }
    if ($max !== null && $val > $max) {
        $errors[] = $label . ' must not exceed ' . $max . '.';
    }

    return array('valid' => empty($errors), 'errors' => $errors);
}

// Validate Process Master: process_name from process[] POST array (nameField rule)
function validate_process_master_data(&$CI, $mode = 'add', $id = null)
{
    global $VALIDATION_RULES;

    $process = $CI->input->post('process');
    $process_name = isset($process['process_name']) ? trim($process['process_name']) : '';

    if ($process_name === '') {
        return array('valid' => false, 'errors' => array('Process Name is required.'));
    }

    // Security Layer 1 — CI3 XSS clean
    $process_name = $CI->security->xss_clean($process_name);
    $process_name = trim($process_name);

    $rule = isset($VALIDATION_RULES['nameField']) ? $VALIDATION_RULES['nameField'] : array('regex' => '/[^A-Za-z0-9\s_\-]/', 'max' => 50, 'msg' => 'Only letters, numbers, spaces, underscore (_) and hyphen (-) are allowed');
    $errors = array();

    if (strlen($process_name) < 2) {
        $errors[] = 'Process Name must be at least 2 characters long.';
    }
    if (!empty($rule['max']) && strlen($process_name) > $rule['max']) {
        $errors[] = 'Process Name must not exceed ' . $rule['max'] . ' characters.';
    }
    if (!empty($rule['regex']) && preg_match($rule['regex'], $process_name)) {
        $errors[] = 'Process Name: ' . $rule['msg'] . '.';
    }

    // Security Layer 2 — SQL injection pattern detection
    $sql_patterns = array('--','/*','*/','xp_','UNION ','union ','SELECT ','select ','INSERT ','insert ','UPDATE ','update ','DELETE ','delete ','DROP ','drop ','TRUNCATE ','truncate ','ALTER ','alter ','EXEC(','exec(','EXECUTE(','execute(','CAST(','cast(','CONVERT(','convert(','CHAR(','char(','DECLARE ','declare ','BENCHMARK(','benchmark(','SLEEP(','sleep(','LOAD_FILE(','load_file(','INTO OUTFILE','into outfile');
    $lower_val = strtolower($process_name);
    foreach ($sql_patterns as $pattern) {
        if (strpos($lower_val, strtolower($pattern)) !== false) {
            $errors[] = 'Process Name contains invalid content (possible SQL injection attempt).';
            break;
        }
    }

    // Security Layer 3 — XSS pattern detection
    $xss_patterns = array('<script','</script','javascript:','onload=','onerror=','onclick=','onmouseover=','onfocus=','onblur=','<iframe','</iframe','<object','<embed','<form','<input','eval(','expression(','alert(','confirm(','prompt(','document.cookie','document.write','window.location','&#','%3c','%3e','vbscript:');
    foreach ($xss_patterns as $pattern) {
        if (strpos($lower_val, strtolower($pattern)) !== false) {
            $errors[] = 'Process Name contains invalid content (XSS not allowed).';
            break;
        }
    }

    return array('valid' => empty($errors), 'errors' => $errors);
}

// =============================================================================
// SUPPLIER CATALOG — Weight/Size field validation
// =============================================================================

/**
 * Validate Supplier Catalog weight and size fields.
 * - Size: numbers only, max 10 digits (optional field)
 * - Weight: decimal format (e.g. 3000.000), max 10 chars, required
 * - From Weight: decimal format, max 10 chars, required
 * - To Weight: decimal format, max 10 chars, required
 *
 * @param  object  $CI   Controller instance
 * @return array   ['valid' => bool, 'errors' => string[]]
 */
function validate_supplier_catalog_weight_data(&$CI)
{
    $errors = array();

    // Size — optional, numbers only, max 10 digits
    $size_result = validate_master_field($CI, 'size', 'supplierSize', 'Size', false, 0);
    if (!$size_result['valid']) {
        $errors = array_merge($errors, $size_result['errors']);
    }

    // Weight — required, decimal format, max 10 chars
    $weight_result = validate_master_field($CI, 'weight', 'supplierWeight', 'Weight', true, 1);
    if (!$weight_result['valid']) {
        $errors = array_merge($errors, $weight_result['errors']);
    }

    // From Weight — required, decimal format, max 10 chars
    $from_weight_result = validate_master_field($CI, 'from_weight', 'supplierWeight', 'From Weight', true, 1);
    if (!$from_weight_result['valid']) {
        $errors = array_merge($errors, $from_weight_result['errors']);
    }

    // To Weight — required, decimal format, max 10 chars
    $to_weight_result = validate_master_field($CI, 'to_weight', 'supplierWeight', 'To Weight', true, 1);
    if (!$to_weight_result['valid']) {
        $errors = array_merge($errors, $to_weight_result['errors']);
    }

    // Cross-field: From Weight <= Weight
    if (empty($errors)) {
        $weight      = floatval($CI->input->post('weight'));
        $from_weight = floatval($CI->input->post('from_weight'));
        $to_weight   = floatval($CI->input->post('to_weight'));

        if ($from_weight > $weight) {
            $errors[] = 'From Weight should be less than or equal to Weight.';
        }
        if ($to_weight < $weight) {
            $errors[] = 'To Weight should be greater than or equal to Weight.';
        }
    }

    return array('valid' => empty($errors), 'errors' => $errors);
}

// Validate Stone Discount: stone_type (required int), min_disc (decimal 0-100), max_disc (decimal 0-100)
function validate_stone_discount_data(&$CI, $mode = 'add', $id = null)
{
    $errors = array();

    // Stone type — required select field
    $stone_type = $CI->input->post('stone_type');
    if (empty($stone_type) || !is_numeric($stone_type) || (int)$stone_type <= 0) {
        return array('valid' => false, 'errors' => array('Stone Type is required.'));
    }

    // Min Disc % — required, decimal only, 0-100 range
    $min_result = validate_master_field($CI, 'min_disc', 'decimal', 'Min Disc %', true, 1);
    if (!$min_result['valid']) {
        return $min_result;
    }

    // Max Disc % — required, decimal only, 0-100 range
    $max_result = validate_master_field($CI, 'max_disc', 'decimal', 'Max Disc %', true, 1);
    if (!$max_result['valid']) {
        return $max_result;
    }

    // Range check: 0-100
    $min_val = (float)$CI->input->post('min_disc');
    $max_val = (float)$CI->input->post('max_disc');

    if ($min_val < 0 || $min_val > 100) {
        $errors[] = 'Min Disc % must be between 0 and 100.';
    }
    if ($max_val < 0 || $max_val > 100) {
        $errors[] = 'Max Disc % must be between 0 and 100.';
    }
    // Cross-field: min <= max
    if (empty($errors) && $min_val > $max_val) {
        $errors[] = 'Min Disc % must be less than or equal to Max Disc %.';
    }

    return array('valid' => empty($errors), 'errors' => $errors);
}

// Validate Tax Group: tgrp[tgrp_name] (taxName rule)
// NOTE: tgrp uses nested POST keys (tgrp[tgrp_name]), so we cannot use
// validate_master_field() which expects flat POST keys. We extract the
// value from the nested array and apply the same 3 security layers manually.
function validate_taxgroup_data(&$CI, $mode = 'add', $id = null)
{
    global $VALIDATION_RULES;

    $errors = array();
    $tgrp   = $CI->input->post('tgrp');
    $value  = isset($tgrp['tgrp_name']) ? trim($tgrp['tgrp_name']) : '';

    // Required check
    if ($value === '') {
        return array('valid' => false, 'errors' => array('Tax Group Name is required.'));
    }

    // Security Layer 1 — CI3 XSS clean
    $value = $CI->security->xss_clean($value);
    $value = trim($value);

    $rule = isset($VALIDATION_RULES['taxName']) ? $VALIDATION_RULES['taxName'] : null;

    if ($rule) {
        // Min length
        if (strlen($value) < 2) {
            $errors[] = 'Tax Group Name must be at least 2 characters long.';
        }
        // Max length
        if (!empty($rule['max']) && strlen($value) > $rule['max']) {
            $errors[] = 'Tax Group Name must not exceed ' . $rule['max'] . ' characters.';
        }
        // Character whitelist
        if (!empty($rule['regex']) && preg_match($rule['regex'], $value)) {
            $errors[] = 'Tax Group Name: ' . $rule['msg'] . '.';
        }
    }

    // Security Layer 2 — SQL injection patterns
    $sql_patterns = array(
        '--', '/*', '*/', 'xp_',
        'UNION ', 'union ', 'SELECT ', 'select ',
        'INSERT ', 'insert ', 'UPDATE ', 'update ',
        'DELETE ', 'delete ', 'DROP ', 'drop ',
        'TRUNCATE ', 'truncate ', 'ALTER ', 'alter ',
        'EXEC(', 'exec(', 'EXECUTE(', 'execute(',
    );
    $lower_val = strtolower($value);
    foreach ($sql_patterns as $pattern) {
        if (strpos($lower_val, strtolower($pattern)) !== false) {
            $errors[] = 'Tax Group Name contains invalid content (possible SQL injection attempt).';
            break;
        }
    }

    // Security Layer 3 — XSS patterns
    $xss_patterns = array(
        '<script', '</script', 'javascript:', 'onload=', 'onerror=',
        'onclick=', '<iframe', '<object', '<embed', 'eval(',
        'alert(', 'document.cookie', '&#', '%3c', '%3e',
    );
    foreach ($xss_patterns as $pattern) {
        if (strpos($lower_val, strtolower($pattern)) !== false) {
            $errors[] = 'Tax Group Name contains invalid content (XSS not allowed).';
            break;
        }
    }

    return array('valid' => empty($errors), 'errors' => $errors);
}
