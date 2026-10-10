<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Error Monitor Controller
 * 
 * Handles client-side error logging (JS errors, AJAX failures) and
 * provides a dashboard for viewing all captured errors.
 * 
 * Endpoints:
 *   - log_client_error: Receives JS error reports (no auth required for error capture)
 *   - dashboard: Error monitoring dashboard (requires admin login)
 *   - ajax_errors: AJAX endpoint for DataTables error list
 *   - resolve: Mark an error as resolved
 *   - acknowledge: Acknowledge an error
 *   - ajax_stats: Dashboard statistics
 */
class Error_monitor extends CI_Controller {

    public function __construct()
    {
        parent::__construct();
        $this->load->model('error_monitor_model');
    }

    /**
     * Receive client-side error reports.
     * 
     * Accepts JSON POST from error_monitor.js.
     * No session auth required — errors can occur on login page.
     * Rate-limited by IP to prevent abuse.
     */
    public function log_client_error()
    {
        // Only accept POST
        if ($this->input->method() !== 'post') {
            echo json_encode(array('status' => false, 'msg' => 'POST required'));
            return;
        }

        // Rate limiting: max 20 errors per IP per minute
        // Using a simple file-based rate limiter (no extra DB table needed)
        $ip = $this->input->ip_address();
        $rate_key = md5('error_rate_' . $ip);
        $rate_file = APPPATH . 'cache/error_rate_' . $rate_key;
        
        if (file_exists($rate_file)) {
            $rate_data = json_decode(file_get_contents($rate_file), true);
            if ($rate_data && $rate_data['count'] >= 20 && (time() - $rate_data['start']) < 60) {
                echo json_encode(array('status' => false, 'msg' => 'Rate limited'));
                return;
            }
            if ($rate_data && (time() - $rate_data['start']) >= 60) {
                $rate_data = array('count' => 0, 'start' => time());
            }
        } else {
            $rate_data = array('count' => 0, 'start' => time());
        }
        $rate_data['count']++;
        @file_put_contents($rate_file, json_encode($rate_data));

        // Parse JSON body
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);

        if (!$data || !isset($data['error_type'])) {
            // Fallback: try standard POST
            $data = array(
                'error_type'  => $this->input->post('error_type') ?: 'js_error',
                'message'     => $this->input->post('message') ?: 'Unknown',
                'file'        => $this->input->post('file') ?: '',
                'line'        => $this->input->post('line') ?: 0,
                'stack_trace' => $this->input->post('stack_trace') ?: '',
                'url'         => $this->input->post('url') ?: '',
            );
        }

        // Sanitize
        $error_data = array(
            'error_type'  => in_array($data['error_type'], array('js_error', 'js_ajax'))
                             ? $data['error_type'] : 'js_error',
            'message'     => $this->security->xss_clean(substr($data['message'] ?? '', 0, 65535)),
            'file'        => $this->security->xss_clean(substr($data['file'] ?? '', 0, 500)),
            'line'        => (int)($data['line'] ?? 0),
            'stack_trace' => substr($data['stack_trace'] ?? '', 0, 65535),
            'url'         => $this->security->xss_clean(substr($data['url'] ?? '', 0, 1000)),
            'ip_address'  => $ip,
            'user_agent'  => substr($this->input->user_agent(), 0, 500),
        );

        // Add user context if logged in
        if ($this->session->userdata('is_logged')) {
            $error_data['user_id'] = $this->session->userdata('login_user_id');
        }

        // Log to database
        if ($this->error_monitor_model->table_exists()) {
            $this->error_monitor_model->log_error($error_data);
            echo json_encode(array('status' => true, 'msg' => 'Logged'));
        } else {
            echo json_encode(array('status' => false, 'msg' => 'Error table not created'));
        }
    }

    /**
     * Error monitoring dashboard.
     * Requires admin login.
     */
    public function dashboard()
    {
        $this->_require_login();

        if (!$this->error_monitor_model->table_exists()) {
            show_error('Error monitoring table not created. Run the migration first: admin/application/migrations/create_error_log_table.sql');
            return;
        }

        $data['main_content'] = 'error_monitor/dashboard';
        $this->load->view('layout/template', $data);
    }

    /**
     * AJAX: Get error list for DataTables.
     */
    public function ajax_errors()
    {
        $this->_require_login();

        $filters = array(
            'type'       => $this->input->get('type'),
            'severity'   => $this->input->get('severity'),
            'resolved'   => $this->input->get('resolved') !== null 
                           ? (bool)$this->input->get('resolved') : false,
            'search'     => $this->input->get('search'),
            'hours'      => $this->input->get('hours') ?: 24,
            'controller' => $this->input->get('controller'),
        );

        $limit = (int)($this->input->get('length') ?: 50);
        $offset = (int)($this->input->get('start') ?: 0);

        $errors = $this->error_monitor_model->get_errors($filters, $limit, $offset);
        $total = $this->error_monitor_model->count_errors($filters);

        echo json_encode(array(
            'status'          => true,
            'draw'            => (int)$this->input->get('draw'),
            'recordsTotal'    => $total,
            'recordsFiltered' => $total,
            'data'            => $errors,
        ));
    }

    /**
     * AJAX: Get dashboard statistics.
     */
    public function ajax_stats()
    {
        $this->_require_login();

        $hours = (int)($this->input->get('hours') ?: 24);
        $stats = $this->error_monitor_model->get_dashboard_stats($hours);

        echo json_encode(array('status' => true, 'data' => $stats));
    }

    /**
     * AJAX: Resolve an error.
     */
    public function resolve()
    {
        $this->_require_login();

        $id = (int)$this->input->post('id');
        $notes = $this->input->post('notes') ?: '';
        $user_id = $this->session->userdata('login_user_id');

        $result = $this->error_monitor_model->resolve_error($id, $user_id, $notes);

        echo json_encode(array(
            'status' => $result,
            'msg'    => $result ? 'Error resolved' : 'Failed to resolve',
        ));
    }

    /**
     * AJAX: Acknowledge an error.
     */
    public function acknowledge()
    {
        $this->_require_login();

        $id = (int)$this->input->post('id');
        $result = $this->error_monitor_model->acknowledge_error($id);

        echo json_encode(array(
            'status' => $result,
            'msg'    => $result ? 'Error acknowledged' : 'Failed to acknowledge',
        ));
    }

    /**
     * AJAX: Auto-resolve old errors.
     */
    public function auto_resolve()
    {
        $this->_require_login();

        $days = (int)($this->input->post('days') ?: 30);
        $count = $this->error_monitor_model->auto_resolve_old($days);

        echo json_encode(array(
            'status' => true,
            'msg'    => "{$count} errors auto-resolved (no occurrence in {$days} days)",
        ));
    }

    /**
     * Require admin login.
     */
    private function _require_login()
    {
        if (!$this->session->userdata('is_logged')) {
            if ($this->input->is_ajax_request()) {
                echo json_encode(array('status' => false, 'msg' => 'Not logged in'));
                exit;
            }
            redirect('admin/login');
        }
    }
}
