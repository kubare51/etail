<?php defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * ============================================================
 * LMX Autoload Helper — Core Layer Entry Point
 * ============================================================
 * Loads all LMX core helpers. 
 * Include this file to gain access to the entire core framework.
 *
 * @version  1.0.0
 * @created  2026-07-01
 * ============================================================
 */

$core_dir = dirname(__FILE__) . '/core/';

// Load all core helpers
require_once $core_dir . 'input_helper.php';
require_once $core_dir . 'crud_helper.php';
require_once $core_dir . 'transaction_helper.php';
require_once $core_dir . 'audit_helper.php';
require_once $core_dir . 'response_helper.php';
require_once $core_dir . 'validation_helper.php';
require_once $core_dir . 'access_helper.php';
require_once $core_dir . 'format_helper.php';
require_once $core_dir . 'dropdown_helper.php';
require_once $core_dir . 'upload_helper.php';
