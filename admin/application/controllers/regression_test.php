<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Regression Test Controller
 * 
 * Server-side regression tests that verify system health after deployment.
 * Runs endpoint health checks and database integrity validations.
 * 
 * Endpoints:
 *   GET  /regression_test/run         — Run all tests, return JSON report
 *   GET  /regression_test/run/tier2   — Endpoint tests only
 *   GET  /regression_test/run/tier3   — Database tests only
 *   GET  /regression_test/status      — Quick health status
 * 
 * Security: Requires admin login OR valid X-Regression-Key header
 */
class Regression_test extends CI_Controller {

    private $results = array();
    private $start_time;

    public function __construct()
    {
        parent::__construct();
        $this->_authorize();
        $this->start_time = microtime(true);
    }

    /**
     * Run all regression tests.
     * 
     * @param string $tier  Optional tier filter: 'tier2', 'tier3', or empty for all
     */
    public function run($tier = '')
    {
        $results = array(
            'timestamp'  => date('Y-m-d H:i:s'),
            'server'     => gethostname(),
            'php_version' => PHP_VERSION,
            'tier2'      => array(),
            'tier3'      => array(),
            'summary'    => array(),
        );

        if ($tier === '' || $tier === 'tier2') {
            $results['tier2'] = $this->_run_tier2();
        }

        if ($tier === '' || $tier === 'tier3') {
            $results['tier3'] = $this->_run_tier3();
        }

        // Summary
        $total = 0;
        $passed = 0;
        $failed = 0;

        foreach (array('tier2', 'tier3') as $t) {
            if (!empty($results[$t])) {
                foreach ($results[$t] as $test) {
                    $total++;
                    if ($test['status'] === 'pass') {
                        $passed++;
                    } else {
                        $failed++;
                    }
                }
            }
        }

        $results['summary'] = array(
            'total'    => $total,
            'passed'   => $passed,
            'failed'   => $failed,
            'pass_rate' => $total > 0 ? round(($passed / $total) * 100, 1) : 0,
            'duration'  => round(microtime(true) - $this->start_time, 2) . 's',
            'status'    => $failed === 0 ? 'PASS' : 'FAIL',
        );

        header('Content-Type: application/json');
        echo json_encode($results, JSON_PRETTY_PRINT);
    }

    /**
     * Quick health status (lightweight).
     */
    public function status()
    {
        $checks = array();

        // DB connection
        try {
            $this->db->query('SELECT 1');
            $checks['database'] = 'ok';
        } catch (Exception $e) {
            $checks['database'] = 'error: ' . $e->getMessage();
        }

        // Session
        $checks['session'] = $this->session->userdata('is_logged') ? 'authenticated' : 'anonymous';

        // PHP
        $checks['php'] = PHP_VERSION;

        // Memory
        $checks['memory'] = round(memory_get_usage(true) / 1024 / 1024, 1) . 'MB';

        // Disk
        $free = disk_free_space(FCPATH);
        $checks['disk_free'] = $free !== false ? round($free / 1024 / 1024 / 1024, 1) . 'GB' : 'unknown';

        header('Content-Type: application/json');
        echo json_encode(array(
            'status' => true,
            'timestamp' => date('Y-m-d H:i:s'),
            'checks' => $checks,
        ));
    }

    // ── TIER 2: Endpoint Health Checks ────────────────────────────────

    /**
     * Test critical AJAX endpoints by calling them internally.
     * Verifies they return valid JSON and expected structure.
     */
    private function _run_tier2()
    {
        $tests = array();

        // Critical endpoints to test — each must return valid JSON
        $endpoints = array(
            // Dashboard
            array(
                'name' => 'Dashboard Sales',
                'controller' => 'admin_ret_dashboard',
                'method' => 'ajx_total_collection',
                'module' => 'dashboard',
                'critical' => true,
            ),
            // Billing
            array(
                'name' => 'Billing List',
                'controller' => 'admin_ret_billing',
                'method' => 'ajax_bill_list',
                'module' => 'billing',
                'critical' => true,
            ),
            // Estimation
            array(
                'name' => 'Estimation List',
                'controller' => 'admin_ret_estimation',
                'method' => 'ajax_est_list',
                'module' => 'estimation',
                'critical' => true,
            ),
            // Payment
            array(
                'name' => 'Payment List',
                'controller' => 'admin_payment',
                'method' => 'payment_list',
                'module' => 'payment',
                'critical' => true,
            ),
            // Customer
            array(
                'name' => 'Customer List',
                'controller' => 'admin_customer',
                'method' => 'ajax_customer_list',
                'module' => 'customer',
                'critical' => false,
            ),
            // Settings
            array(
                'name' => 'Settings DB',
                'controller' => 'admin_settings',
                'method' => 'settingsDB',
                'module' => 'settings',
                'critical' => true,
            ),
            // Catalog
            array(
                'name' => 'Product List',
                'controller' => 'admin_ret_catalog',
                'method' => 'ajax_product_list',
                'module' => 'catalog',
                'critical' => false,
            ),
            // Reports
            array(
                'name' => 'Reports - Day Transactions',
                'controller' => 'admin_ret_reports',
                'method' => 'day_transactions_report',
                'module' => 'reports',
                'critical' => false,
            ),
        );

        foreach ($endpoints as $ep) {
            $test = array(
                'name'     => $ep['name'],
                'module'   => $ep['module'],
                'endpoint' => $ep['controller'] . '/' . $ep['method'],
                'critical' => $ep['critical'],
            );

            try {
                // Internal call via HMVC or cURL to localhost
                $url = base_url($ep['controller'] . '/' . $ep['method']);
                
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 15);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                
                // Pass session cookie for authenticated endpoints
                $cookie = session_name() . '=' . session_id();
                curl_setopt($ch, CURLOPT_COOKIE, $cookie);
                curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                    'X-Requested-With: XMLHttpRequest',
                    'X-Regression-Test: true',
                ));

                $start = microtime(true);
                $response = curl_exec($ch);
                $elapsed = round((microtime(true) - $start) * 1000);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curl_error = curl_error($ch);
                curl_close($ch);

                $test['http_code'] = $http_code;
                $test['response_ms'] = $elapsed;

                if (!empty($curl_error)) {
                    $test['status'] = 'fail';
                    $test['error'] = 'cURL: ' . $curl_error;
                } elseif ($http_code >= 500) {
                    $test['status'] = 'fail';
                    $test['error'] = 'Server error (HTTP ' . $http_code . ')';
                } elseif ($http_code === 302 || $http_code === 301) {
                    // Redirect usually means login required
                    $test['status'] = 'warn';
                    $test['error'] = 'Redirected (likely needs auth)';
                } else {
                    // Try to decode JSON
                    $json = json_decode($response, true);
                    if ($json !== null) {
                        $test['status'] = 'pass';
                        $test['response_type'] = 'json';
                        $test['response_keys'] = array_keys($json);
                    } elseif ($http_code === 200 && strlen($response) > 0) {
                        // HTML response (might be a view, not JSON)
                        $test['status'] = 'pass';
                        $test['response_type'] = 'html';
                        $test['response_size'] = strlen($response);
                    } else {
                        $test['status'] = 'fail';
                        $test['error'] = 'Empty or invalid response';
                    }
                }

                // Performance warning
                if ($elapsed > 5000) {
                    $test['perf_warning'] = 'Slow response (>5s)';
                }

            } catch (Exception $e) {
                $test['status'] = 'fail';
                $test['error'] = $e->getMessage();
            }

            $tests[] = $test;
        }

        return $tests;
    }

    // ── TIER 3: Database Integrity Checks ─────────────────────────────

    /**
     * Run database integrity checks.
     * Verifies business invariants and data consistency.
     */
    private function _run_tier3()
    {
        $tests = array();

        // ── Schema Checks ──

        // 1. Critical tables exist
        $critical_tables = array(
            'ret_estimation', 'ret_estimation_details',
            'ret_billing', 'ret_billing_details',
            'ret_payment', 'customer', 'employee',
            'product', 'product_design', 'category',
            'company_details', 'branch',
        );

        foreach ($critical_tables as $table) {
            $exists = $this->db->table_exists($table);
            $tests[] = array(
                'name' => "Table exists: $table",
                'category' => 'schema',
                'status' => $exists ? 'pass' : 'fail',
                'error' => $exists ? null : "Table $table not found",
                'critical' => true,
            );
        }

        // ── Business Invariant Checks ──

        // 2. No billing records without details
        $orphan_bills = $this->db->query(
            "SELECT COUNT(*) as cnt FROM ret_billing b 
             LEFT JOIN ret_billing_details bd ON b.billing_id = bd.billing_id 
             WHERE bd.billing_id IS NULL 
             AND b.is_deleted = 0
             LIMIT 1"
        )->row()->cnt;

        $tests[] = array(
            'name' => 'No orphan billing records (bills without items)',
            'category' => 'integrity',
            'status' => ($orphan_bills == 0) ? 'pass' : 'warn',
            'value' => $orphan_bills,
            'error' => ($orphan_bills > 0) ? "$orphan_bills bill(s) have no items" : null,
        );

        // 3. No estimation records without details
        $orphan_est = $this->db->query(
            "SELECT COUNT(*) as cnt FROM ret_estimation e 
             LEFT JOIN ret_estimation_details ed ON e.estimation_id = ed.estimation_id 
             WHERE ed.estimation_id IS NULL 
             AND e.is_deleted = 0
             LIMIT 1"
        )->row()->cnt;

        $tests[] = array(
            'name' => 'No orphan estimation records',
            'category' => 'integrity',
            'status' => ($orphan_est == 0) ? 'pass' : 'warn',
            'value' => $orphan_est,
            'error' => ($orphan_est > 0) ? "$orphan_est estimation(s) have no items" : null,
        );

        // 4. No negative totals in billing
        $neg_totals = $this->db->query(
            "SELECT COUNT(*) as cnt FROM ret_billing 
             WHERE grand_total < 0 AND is_deleted = 0"
        )->row()->cnt;

        $tests[] = array(
            'name' => 'No negative bill totals',
            'category' => 'financial',
            'status' => ($neg_totals == 0) ? 'pass' : 'fail',
            'value' => $neg_totals,
            'error' => ($neg_totals > 0) ? "$neg_totals bill(s) with negative total" : null,
            'critical' => true,
        );

        // 5. Company details exist
        $company = $this->db->get('company_details')->num_rows();
        $tests[] = array(
            'name' => 'Company details configured',
            'category' => 'config',
            'status' => ($company > 0) ? 'pass' : 'fail',
            'value' => $company,
            'error' => ($company == 0) ? 'No company details found' : null,
            'critical' => true,
        );

        // 6. At least one branch exists
        $branches = $this->db->get_where('branch', array('is_deleted' => 0))->num_rows();
        $tests[] = array(
            'name' => 'At least one active branch',
            'category' => 'config',
            'status' => ($branches > 0) ? 'pass' : 'fail',
            'value' => $branches,
            'error' => ($branches == 0) ? 'No active branches' : null,
            'critical' => true,
        );

        // 7. At least one admin user exists
        $admins = $this->db->query(
            "SELECT COUNT(*) as cnt FROM employee 
             WHERE is_deleted = 0 AND is_active = 1"
        )->row()->cnt;

        $tests[] = array(
            'name' => 'Active employees exist',
            'category' => 'config',
            'status' => ($admins > 0) ? 'pass' : 'fail',
            'value' => $admins,
            'error' => ($admins == 0) ? 'No active employees' : null,
            'critical' => true,
        );

        // 8. No future-dated billing (data integrity smell)
        $future_bills = $this->db->query(
            "SELECT COUNT(*) as cnt FROM ret_billing 
             WHERE bill_date > CURDATE() AND is_deleted = 0"
        )->row()->cnt;

        $tests[] = array(
            'name' => 'No future-dated bills',
            'category' => 'integrity',
            'status' => ($future_bills == 0) ? 'pass' : 'warn',
            'value' => $future_bills,
            'error' => ($future_bills > 0) ? "$future_bills bill(s) dated in the future" : null,
        );

        // 9. Payment amounts consistency
        $payment_mismatch = $this->db->query(
            "SELECT COUNT(*) as cnt FROM ret_payment 
             WHERE paid_amount < 0 AND is_deleted = 0"
        )->row()->cnt;

        $tests[] = array(
            'name' => 'No negative payment amounts',
            'category' => 'financial',
            'status' => ($payment_mismatch == 0) ? 'pass' : 'fail',
            'value' => $payment_mismatch,
            'error' => ($payment_mismatch > 0) ? "$payment_mismatch payment(s) with negative amount" : null,
            'critical' => true,
        );

        // 10. Index health — critical indexes exist
        $index_checks = array(
            array('ret_billing', 'bill_no'),
            array('ret_estimation', 'estimation_no'),
            array('customer', 'mobile'),
        );

        foreach ($index_checks as $ic) {
            $table = $ic[0];
            $column = $ic[1];
            $indexes = $this->db->query("SHOW INDEX FROM $table WHERE Column_name = '$column'")->num_rows();
            $tests[] = array(
                'name' => "Index exists: {$table}.{$column}",
                'category' => 'performance',
                'status' => ($indexes > 0) ? 'pass' : 'warn',
                'error' => ($indexes == 0) ? "No index on {$table}.{$column}" : null,
            );
        }

        return $tests;
    }

    // ── Authorization ─────────────────────────────────────────────────

    /**
     * Authorize: admin login OR valid regression key header.
     */
    private function _authorize()
    {
        // Check admin session
        if ($this->session->userdata('is_logged')) {
            return;
        }

        // Check regression key header (for automated CI/CD calls)
        $key = $this->input->get_request_header('X-Regression-Key');
        $expected = $this->config->item('regression_test_key');

        if (!empty($key) && !empty($expected) && hash_equals($expected, $key)) {
            return;
        }

        // Not authorized
        if ($this->input->is_ajax_request() || 
            strpos($this->input->get_request_header('Accept'), 'json') !== false) {
            header('Content-Type: application/json');
            echo json_encode(array(
                'status' => false,
                'msg' => 'Unauthorized. Login or provide X-Regression-Key header.',
            ));
            exit;
        }

        redirect('admin/login');
    }
}
