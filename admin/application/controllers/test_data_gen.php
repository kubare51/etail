<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Test Data Generator
 * 
 * Seeds development databases with realistic test data for all modules.
 * Used for onboarding new developers, resetting test environments,
 * and reproducing bugs that need specific data patterns.
 * 
 * Endpoints:
 *   GET  /test_data_gen/index        — Dashboard showing data counts
 *   POST /test_data_gen/seed         — Generate test data
 *   POST /test_data_gen/seed/module  — Generate for specific module
 *   POST /test_data_gen/cleanup      — Remove test data (is_test_data = 1)
 * 
 * Security: ONLY runs when ENVIRONMENT = 'development' or 'testing'
 *           Blocked on production. Period.
 */
class Test_data_gen extends CI_Controller {

    private $test_marker = 'TEST_DATA';
    private $counts = array();

    public function __construct()
    {
        parent::__construct();
        $this->_block_production();
    }

    /**
     * Dashboard — show current test data counts.
     */
    public function index()
    {
        $counts = array(
            'customers'       => $this->_count_test_data('customer'),
            'products'        => $this->_count_test_data('product'),
            'estimations'     => $this->_count_test_data('ret_estimation'),
            'billings'        => $this->_count_test_data('ret_billing'),
            'payments'        => $this->_count_test_data('ret_payment'),
        );

        header('Content-Type: application/json');
        echo json_encode(array(
            'status'      => true,
            'environment' => ENVIRONMENT,
            'counts'      => $counts,
            'msg'         => 'Test data generator ready. POST to /test_data_gen/seed to generate.',
        ), JSON_PRETTY_PRINT);
    }

    /**
     * Seed test data.
     * 
     * @param string $module  Optional: specific module ('customer', 'product', 'estimation', etc.)
     */
    public function seed($module = '')
    {
        if ($this->input->method() !== 'post') {
            echo json_encode(array('status' => false, 'msg' => 'POST required'));
            return;
        }

        $count = (int) ($this->input->post('count') ?: 10);
        $count = min($count, 100); // Cap at 100 per call

        $this->db->trans_start();

        $results = array();

        try {
            // Get required references
            $branch = $this->db->get_where('branch', array('is_deleted' => 0))->row();
            $company = $this->db->get('company_details')->row();

            if (!$branch || !$company) {
                echo json_encode(array('status' => false, 'msg' => 'Need at least 1 branch and company details'));
                return;
            }

            if ($module === '' || $module === 'customer') {
                $results['customers'] = $this->_seed_customers($count, $branch);
            }

            if ($module === '' || $module === 'product') {
                $results['products'] = $this->_seed_products($count, $branch);
            }

            if ($module === '' || $module === 'estimation') {
                $results['estimations'] = $this->_seed_estimations($count, $branch, $company);
            }

            if ($module === '' || $module === 'billing') {
                $results['billings'] = $this->_seed_billings($count, $branch, $company);
            }

            $this->db->trans_complete();

            if ($this->db->trans_status() === false) {
                echo json_encode(array('status' => false, 'msg' => 'Transaction failed'));
                return;
            }

            echo json_encode(array(
                'status'  => true,
                'msg'     => 'Test data generated successfully',
                'results' => $results,
            ), JSON_PRETTY_PRINT);

        } catch (Exception $e) {
            $this->db->trans_rollback();
            echo json_encode(array('status' => false, 'msg' => $e->getMessage()));
        }
    }

    /**
     * Remove all test data (where remarks = 'TEST_DATA').
     */
    public function cleanup()
    {
        if ($this->input->method() !== 'post') {
            echo json_encode(array('status' => false, 'msg' => 'POST required'));
            return;
        }

        $this->db->trans_start();

        $tables = array(
            'ret_billing_details', 'ret_billing',
            'ret_estimation_details', 'ret_estimation',
            'ret_payment', 'customer', 'product',
        );

        $deleted = array();
        foreach ($tables as $table) {
            if ($this->db->table_exists($table)) {
                // Check if remarks column exists
                if ($this->db->field_exists('remarks', $table)) {
                    $this->db->where('remarks', $this->test_marker);
                    $this->db->delete($table);
                    $deleted[$table] = $this->db->affected_rows();
                }
            }
        }

        $this->db->trans_complete();

        echo json_encode(array(
            'status'  => true,
            'msg'     => 'Test data cleaned up',
            'deleted' => $deleted,
        ), JSON_PRETTY_PRINT);
    }

    // ── Data Generators ──────────────────────────────────────────────

    /**
     * Generate test customers.
     */
    private function _seed_customers($count, $branch)
    {
        $first_names = array('Raj', 'Priya', 'Amit', 'Sunita', 'Vikram', 'Neha', 'Arjun', 'Deepa', 'Karan', 'Meera',
                            'Suresh', 'Kavitha', 'Rahul', 'Anita', 'Mohan', 'Lakshmi', 'Arun', 'Divya', 'Sanjay', 'Rekha');
        $last_names = array('Kumar', 'Sharma', 'Patel', 'Singh', 'Reddy', 'Nair', 'Gupta', 'Jain', 'Verma', 'Pillai',
                           'Iyer', 'Menon', 'Das', 'Rao', 'Desai', 'Shah', 'Mehta', 'Bose', 'Sen', 'Mishra');
        $cities = array('Chennai', 'Mumbai', 'Delhi', 'Bangalore', 'Hyderabad', 'Kolkata', 'Pune', 'Ahmedabad', 'Jaipur', 'Surat');

        $created = 0;
        for ($i = 0; $i < $count; $i++) {
            $first = $first_names[array_rand($first_names)];
            $last = $last_names[array_rand($last_names)];
            $city = $cities[array_rand($cities)];

            $data = array(
                'first_name'  => $first,
                'last_name'   => $last,
                'mobile'      => '9' . str_pad(mt_rand(100000000, 999999999), 9, '0', STR_PAD_LEFT),
                'email'       => strtolower($first . '.' . $last . mt_rand(1, 999)) . '@test.com',
                'address'     => mt_rand(1, 999) . ' Test Street',
                'city'        => $city,
                'branch_id'   => $branch->branch_id,
                'is_deleted'  => 0,
                'is_active'   => 1,
                'remarks'     => $this->test_marker,
                'created_at'  => date('Y-m-d H:i:s'),
            );

            $this->db->insert('customer', $data);
            $created++;
        }

        return array('created' => $created);
    }

    /**
     * Generate test products.
     */
    private function _seed_products($count, $branch)
    {
        // Get existing categories
        $categories = $this->db->get_where('category', array('is_deleted' => 0))->result();
        if (empty($categories)) {
            return array('created' => 0, 'error' => 'No categories found');
        }

        $designs = array('Ring', 'Chain', 'Necklace', 'Bangle', 'Bracelet', 'Earring', 'Pendant', 'Anklet');
        $metals = array('Gold', 'Silver', 'Platinum');
        $purities = array('916', '750', '585', '999');

        $created = 0;
        for ($i = 0; $i < $count; $i++) {
            $cat = $categories[array_rand($categories)];
            $design = $designs[array_rand($designs)];
            $metal = $metals[array_rand($metals)];

            $gross_weight = round(mt_rand(200, 5000) / 100, 2);
            $net_weight = round($gross_weight * (mt_rand(90, 98) / 100), 2);

            $data = array(
                'product_name'  => "TEST-$design-" . mt_rand(1000, 9999),
                'category_id'   => $cat->category_id,
                'gross_weight'   => $gross_weight,
                'net_weight'     => $net_weight,
                'metal_type'     => $metal,
                'purity'         => $purities[array_rand($purities)],
                'branch_id'      => $branch->branch_id,
                'is_deleted'     => 0,
                'remarks'        => $this->test_marker,
                'created_at'     => date('Y-m-d H:i:s'),
            );

            $this->db->insert('product', $data);
            $created++;
        }

        return array('created' => $created);
    }

    /**
     * Generate test estimations with detail rows.
     */
    private function _seed_estimations($count, $branch, $company)
    {
        $customers = $this->db->select('customer_id, first_name, last_name')
                              ->where('is_deleted', 0)
                              ->where('is_active', 1)
                              ->limit(50)
                              ->get('customer')
                              ->result();

        if (empty($customers)) {
            return array('created' => 0, 'error' => 'No customers — seed customers first');
        }

        $created = 0;
        for ($i = 0; $i < $count; $i++) {
            $customer = $customers[array_rand($customers)];
            $item_count = mt_rand(1, 5);
            $grand_total = 0;

            // Create estimation header
            $est_no = 'TEST-EST-' . date('ymd') . '-' . str_pad($i + 1, 4, '0', STR_PAD_LEFT);

            $est_data = array(
                'estimation_no'  => $est_no,
                'customer_id'    => $customer->customer_id,
                'branch_id'      => $branch->branch_id,
                'estimation_date' => date('Y-m-d', strtotime('-' . mt_rand(0, 30) . ' days')),
                'status'         => 1,
                'grand_total'    => 0, // Updated after items
                'is_deleted'     => 0,
                'remarks'        => $this->test_marker,
                'created_at'     => date('Y-m-d H:i:s'),
            );

            $this->db->insert('ret_estimation', $est_data);
            $est_id = $this->db->insert_id();

            // Create detail rows
            for ($j = 0; $j < $item_count; $j++) {
                $weight = round(mt_rand(100, 3000) / 100, 2);
                $rate = mt_rand(4000, 7000);
                $amount = round($weight * $rate, 2);
                $grand_total += $amount;

                $detail = array(
                    'estimation_id'  => $est_id,
                    'product_name'   => 'Test Item ' . ($j + 1),
                    'gross_weight'   => $weight,
                    'net_weight'     => round($weight * 0.95, 2),
                    'rate'           => $rate,
                    'amount'         => $amount,
                    'remarks'        => $this->test_marker,
                );

                $this->db->insert('ret_estimation_details', $detail);
            }

            // Update grand total
            $this->db->where('estimation_id', $est_id);
            $this->db->update('ret_estimation', array('grand_total' => round($grand_total, 2)));

            $created++;
        }

        return array('created' => $created, 'with_items' => true);
    }

    /**
     * Generate test billings with detail rows.
     */
    private function _seed_billings($count, $branch, $company)
    {
        $customers = $this->db->select('customer_id')
                              ->where('is_deleted', 0)
                              ->where('is_active', 1)
                              ->limit(50)
                              ->get('customer')
                              ->result();

        if (empty($customers)) {
            return array('created' => 0, 'error' => 'No customers — seed customers first');
        }

        $created = 0;
        for ($i = 0; $i < $count; $i++) {
            $customer = $customers[array_rand($customers)];
            $item_count = mt_rand(1, 4);
            $grand_total = 0;

            $bill_no = 'TEST-BIL-' . date('ymd') . '-' . str_pad($i + 1, 4, '0', STR_PAD_LEFT);

            $bill_data = array(
                'bill_no'       => $bill_no,
                'customer_id'   => $customer->customer_id,
                'branch_id'     => $branch->branch_id,
                'bill_date'     => date('Y-m-d', strtotime('-' . mt_rand(0, 30) . ' days')),
                'bill_type'     => 1,
                'grand_total'   => 0,
                'is_deleted'    => 0,
                'remarks'       => $this->test_marker,
                'created_at'    => date('Y-m-d H:i:s'),
            );

            $this->db->insert('ret_billing', $bill_data);
            $bill_id = $this->db->insert_id();

            for ($j = 0; $j < $item_count; $j++) {
                $weight = round(mt_rand(100, 3000) / 100, 2);
                $rate = mt_rand(4000, 7000);
                $amount = round($weight * $rate, 2);
                $grand_total += $amount;

                $detail = array(
                    'billing_id'    => $bill_id,
                    'product_name'  => 'Test Product ' . ($j + 1),
                    'gross_weight'  => $weight,
                    'net_weight'    => round($weight * 0.95, 2),
                    'rate'          => $rate,
                    'amount'        => $amount,
                    'remarks'       => $this->test_marker,
                );

                $this->db->insert('ret_billing_details', $detail);
            }

            $this->db->where('billing_id', $bill_id);
            $this->db->update('ret_billing', array('grand_total' => round($grand_total, 2)));

            $created++;
        }

        return array('created' => $created, 'with_items' => true);
    }

    // ── Helpers ──────────────────────────────────────────────────────

    private function _count_test_data($table)
    {
        if (!$this->db->table_exists($table)) return 0;
        if (!$this->db->field_exists('remarks', $table)) return 0;

        return (int) $this->db->where('remarks', $this->test_marker)
                              ->count_all_results($table);
    }

    /**
     * HARD BLOCK: Never run on production.
     */
    private function _block_production()
    {
        if (ENVIRONMENT === 'production') {
            header('Content-Type: application/json');
            echo json_encode(array(
                'status' => false,
                'msg'    => '🚨 BLOCKED: Test data generation is disabled in production.',
            ));
            exit;
        }

        // Additional safety: check for production indicators
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if (strpos($host, '.com') !== false && strpos($host, 'localhost') === false) {
            header('Content-Type: application/json');
            echo json_encode(array(
                'status' => false,
                'msg'    => '🚨 BLOCKED: Detected production-like hostname. Refusing to generate test data.',
            ));
            exit;
        }
    }
}
