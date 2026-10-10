<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Admin APM Dashboard Controller
 * 
 * Provides real-time backend performance monitoring:
 * - Request throughput & response times
 * - Slow endpoints (P95/P99)
 * - DB query analysis
 * - Error rate tracking
 * - Memory usage trends
 * - User activity heatmap
 * 
 * @author  Logimax APM
 * @since   2026-05-03
 */
class Admin_apm extends CI_Controller {

    const VIEW_FOLDER = 'apm/';
    const TABLE       = 'ret_request_log';

    // Methods accessible without login (cron/CLI)  
    private $_no_auth = array('check_alerts');

    public function __construct() {
        parent::__construct();
        // Auth check — skip for cron-callable methods
        $method = $this->router->fetch_method();
        if (!in_array($method, $this->_no_auth)) {
            if (!$this->session->userdata('uid')) {
                redirect('admin/login');
            }
        }
    }

    /**
     * Main APM dashboard
     */
    public function index() {
        $data['page_title'] = 'Application Performance Monitor';
        $this->load->view(self::VIEW_FOLDER . 'dashboard', $data);
    }

    /**
     * JSON API — returns all APM data for the dashboard
     * 
     * Query params:
     *   range  = today | 7d | 30d | custom
     *   from   = Y-m-d (when range=custom)
     *   to     = Y-m-d (when range=custom)
     *   module = specific controller filter (optional)
     */
    public function api() {
        header('Content-Type: application/json');

        // ── Date Range ──
        $range = $this->input->get('range') ?: 'today';
        $from  = date('Y-m-d');
        $to    = date('Y-m-d');

        switch ($range) {
            case '7d':
                $from = date('Y-m-d', strtotime('-7 days'));
                break;
            case '30d':
                $from = date('Y-m-d', strtotime('-30 days'));
                break;
            case 'custom':
                $from = $this->input->get('from') ?: $from;
                $to   = $this->input->get('to')   ?: $to;
                break;
        }

        $module = $this->input->get('module') ?: null;

        try {
            $result = [
                'status'     => 'ok',
                'range'      => ['from' => $from, 'to' => $to],
                'overview'   => $this->_getOverview($from, $to, $module),
                'throughput' => $this->_getThroughput($from, $to, $module),
                'slow_endpoints' => $this->_getSlowEndpoints($from, $to, $module),
                'slow_queries'   => $this->_getSlowQueries($from, $to, $module),
                'n_plus_one'     => $this->_getNPlusOne($from, $to, $module),
                'errors'     => $this->_getErrors($from, $to, $module),
                'memory_hogs'=> $this->_getMemoryHogs($from, $to, $module),
                'response_distribution' => $this->_getResponseDistribution($from, $to, $module),
                'top_users'  => $this->_getTopUsers($from, $to),
                'hourly_heatmap' => $this->_getHourlyHeatmap($from, $to, $module),
                'percentiles' => $this->_getPercentiles($from, $to, $module),
                'bloated_responses' => $this->_getBloatedResponses($from, $to, $module),
            ];
        } catch (Exception $e) {
            $result = ['status' => 'error', 'message' => $e->getMessage()];
        }

        echo json_encode($result);
    }

    // ──────────────────────────────────────────────
    //  Private data methods
    // ──────────────────────────────────────────────

    /**
     * High-level summary stats
     */
    private function _getOverview($from, $to, $module = null) {
        $where = $this->_buildWhere($from, $to, $module);

        $sql = "SELECT 
            COUNT(*) AS total_requests,
            ROUND(AVG(response_time_ms)) AS avg_response_ms,
            MAX(response_time_ms) AS max_response_ms,
            ROUND(AVG(memory_peak_kb)) AS avg_memory_kb,
            MAX(memory_peak_kb) AS max_memory_kb,
            ROUND(AVG(db_query_count), 1) AS avg_queries,
            MAX(db_query_count) AS max_queries,
            ROUND(AVG(db_query_time_ms)) AS avg_db_time_ms,
            SUM(CASE WHEN http_status >= 400 THEN 1 ELSE 0 END) AS error_count,
            SUM(CASE WHEN http_status >= 500 THEN 1 ELSE 0 END) AS server_error_count,
            SUM(CASE WHEN response_time_ms > 3000 THEN 1 ELSE 0 END) AS slow_requests,
            SUM(is_ajax) AS ajax_requests,
            COUNT(DISTINCT controller) AS unique_controllers,
            COUNT(DISTINCT user_id) AS unique_users
        FROM " . self::TABLE . " WHERE {$where}";

        $row = $this->db->query($sql)->row_array();
        
        // Calculate P95 (two-step: get count, then offset)
        $cnt_sql = "SELECT COUNT(*) AS cnt FROM " . self::TABLE . " WHERE {$where}";
        $cnt_row = $this->db->query($cnt_sql)->row();
        $total_cnt = $cnt_row ? (int)$cnt_row->cnt : 0;
        $p95_offset = (int)floor($total_cnt * 0.95);
        
        $row['p95_response_ms'] = 0;
        if ($total_cnt > 0 && $p95_offset < $total_cnt) {
            $p95_sql = "SELECT response_time_ms FROM " . self::TABLE . " 
                WHERE {$where} ORDER BY response_time_ms ASC 
                LIMIT 1 OFFSET {$p95_offset}";
            $p95 = $this->db->query($p95_sql)->row();
            $row['p95_response_ms'] = $p95 ? (int)$p95->response_time_ms : 0;
        }

        // Uptime: % of requests with status < 500
        $total = (int)($row['total_requests'] ?? 0);
        $server_errors = (int)($row['server_error_count'] ?? 0);
        $row['uptime_pct'] = $total > 0 ? round(($total - $server_errors) / $total * 100, 2) : 100;

        // Error rate
        $errors = (int)($row['error_count'] ?? 0);
        $row['error_rate_pct'] = $total > 0 ? round($errors / $total * 100, 2) : 0;

        // ── Week-over-week trend comparison ──
        // Calculate the same-length period before the current range
        $from_ts = strtotime($from);
        $to_ts   = strtotime($to);
        $range_days = max(1, (int)(($to_ts - $from_ts) / 86400) + 1);
        $prev_to   = date('Y-m-d', $from_ts - 86400); // day before current start
        $prev_from = date('Y-m-d', strtotime("-{$range_days} days", $from_ts));

        $prev_where = $this->_buildWhere($prev_from, $prev_to, $module);
        $prev_sql = "SELECT 
            COUNT(*) AS total_requests,
            ROUND(AVG(response_time_ms)) AS avg_response_ms,
            SUM(CASE WHEN http_status >= 400 THEN 1 ELSE 0 END) AS error_count,
            ROUND(AVG(db_query_count), 1) AS avg_queries
        FROM " . self::TABLE . " WHERE {$prev_where}";

        $prev = $this->db->query($prev_sql)->row_array();
        $prev_total = (int)($prev['total_requests'] ?? 0);

        // Calculate % change (positive = worse for response/errors, better for requests)
        $row['trend'] = [
            'prev_from' => $prev_from,
            'prev_to'   => $prev_to,
            'prev_requests' => $prev_total,
            'response_change_pct' => ($prev_total > 0 && $prev['avg_response_ms'] > 0)
                ? round(((int)$row['avg_response_ms'] - (int)$prev['avg_response_ms']) / (int)$prev['avg_response_ms'] * 100, 1)
                : null,
            'request_change_pct' => ($prev_total > 0)
                ? round(($total - $prev_total) / $prev_total * 100, 1)
                : null,
            'error_change_pct' => ($prev_total > 0 && (int)$prev['error_count'] > 0)
                ? round(($errors - (int)$prev['error_count']) / (int)$prev['error_count'] * 100, 1)
                : null,
            'query_change_pct' => ($prev_total > 0 && $prev['avg_queries'] > 0)
                ? round(((float)$row['avg_queries'] - (float)$prev['avg_queries']) / (float)$prev['avg_queries'] * 100, 1)
                : null,
        ];

        return $row;
    }

    /**
     * Requests per minute over time (for timeline chart)
     */
    private function _getThroughput($from, $to, $module = null) {
        $where = $this->_buildWhere($from, $to, $module);
        
        // If single day, group by hour; otherwise by day
        if ($from === $to) {
            $sql = "SELECT 
                DATE_FORMAT(request_time, '%H:00') AS period,
                COUNT(*) AS requests,
                ROUND(AVG(response_time_ms)) AS avg_ms,
                MAX(response_time_ms) AS max_ms,
                SUM(CASE WHEN http_status >= 400 THEN 1 ELSE 0 END) AS errors
            FROM " . self::TABLE . " WHERE {$where}
            GROUP BY DATE_FORMAT(request_time, '%H')
            ORDER BY period";
        } else {
            $sql = "SELECT 
                request_date AS period,
                COUNT(*) AS requests,
                ROUND(AVG(response_time_ms)) AS avg_ms,
                MAX(response_time_ms) AS max_ms,
                SUM(CASE WHEN http_status >= 400 THEN 1 ELSE 0 END) AS errors
            FROM " . self::TABLE . " WHERE {$where}
            GROUP BY request_date
            ORDER BY request_date";
        }

        return $this->db->query($sql)->result_array();
    }

    /**
     * Slowest endpoints by avg response time
     */
    private function _getSlowEndpoints($from, $to, $module = null) {
        $where = $this->_buildWhere($from, $to, $module);

        $sql = "SELECT 
            controller, method,
            COUNT(*) AS hits,
            ROUND(AVG(response_time_ms)) AS avg_ms,
            MAX(response_time_ms) AS max_ms,
            ROUND(AVG(db_query_count), 1) AS avg_queries,
            ROUND(AVG(memory_peak_kb)) AS avg_memory_kb
        FROM " . self::TABLE . " WHERE {$where}
        GROUP BY controller, method
        HAVING COUNT(*) >= 2
        ORDER BY avg_ms DESC
        LIMIT 20";

        return $this->db->query($sql)->result_array();
    }

    /**
     * Slowest individual queries captured
     */
    private function _getSlowQueries($from, $to, $module = null) {
        $where = $this->_buildWhere($from, $to, $module);

        $sql = "SELECT 
            controller, method, slowest_query_ms, slowest_query_sql,
            request_time, response_time_ms, db_query_count
        FROM " . self::TABLE . " 
        WHERE {$where} AND slowest_query_ms > 100 AND slowest_query_sql IS NOT NULL
        ORDER BY slowest_query_ms DESC
        LIMIT 15";

        return $this->db->query($sql)->result_array();
    }

    /**
     * Requests with HTTP errors (4xx, 5xx)
     */
    private function _getErrors($from, $to, $module = null) {
        $where = $this->_buildWhere($from, $to, $module);

        // Error breakdown by status code
        $by_status = $this->db->query("SELECT 
            http_status, COUNT(*) AS count
        FROM " . self::TABLE . " WHERE {$where} AND http_status >= 400
        GROUP BY http_status ORDER BY count DESC")->result_array();

        // Recent errors
        $recent = $this->db->query("SELECT 
            controller, method, uri, http_status, error_message, 
            request_time, response_time_ms, user_id
        FROM " . self::TABLE . " WHERE {$where} AND http_status >= 400
        ORDER BY request_time DESC LIMIT 20")->result_array();

        return [
            'by_status' => $by_status,
            'recent'    => $recent,
        ];
    }

    /**
     * Endpoints consuming the most memory
     */
    private function _getMemoryHogs($from, $to, $module = null) {
        $where = $this->_buildWhere($from, $to, $module);

        $sql = "SELECT 
            controller, method,
            COUNT(*) AS hits,
            ROUND(AVG(memory_peak_kb)) AS avg_kb,
            MAX(memory_peak_kb) AS max_kb,
            ROUND(AVG(response_time_ms)) AS avg_ms
        FROM " . self::TABLE . " WHERE {$where}
        GROUP BY controller, method
        HAVING COUNT(*) >= 2
        ORDER BY avg_kb DESC
        LIMIT 15";

        return $this->db->query($sql)->result_array();
    }

    /**
     * Response time distribution (histogram buckets)
     */
    private function _getResponseDistribution($from, $to, $module = null) {
        $where = $this->_buildWhere($from, $to, $module);

        $sql = "SELECT 
            SUM(CASE WHEN response_time_ms < 100 THEN 1 ELSE 0 END) AS 'under_100ms',
            SUM(CASE WHEN response_time_ms >= 100 AND response_time_ms < 300 THEN 1 ELSE 0 END) AS '100_300ms',
            SUM(CASE WHEN response_time_ms >= 300 AND response_time_ms < 500 THEN 1 ELSE 0 END) AS '300_500ms',
            SUM(CASE WHEN response_time_ms >= 500 AND response_time_ms < 1000 THEN 1 ELSE 0 END) AS '500_1000ms',
            SUM(CASE WHEN response_time_ms >= 1000 AND response_time_ms < 3000 THEN 1 ELSE 0 END) AS '1_3s',
            SUM(CASE WHEN response_time_ms >= 3000 THEN 1 ELSE 0 END) AS 'over_3s'
        FROM " . self::TABLE . " WHERE {$where}";

        return $this->db->query($sql)->row_array();
    }

    /**
     * Most active users
     */
    private function _getTopUsers($from, $to) {
        $where = $this->_buildWhere($from, $to);

        $sql = "SELECT 
            r.user_id, 
            IFNULL(CONCAT(e.firstname, ' ', IFNULL(e.lastname, '')), CONCAT('User #', r.user_id)) AS user_name,
            IFNULL(e.emp_code, '-') AS emp_code,
            COUNT(*) AS requests,
            ROUND(AVG(r.response_time_ms)) AS avg_ms,
            MAX(r.response_time_ms) AS max_ms
        FROM " . self::TABLE . " r
        LEFT JOIN employee e ON r.user_id = e.id_employee
        WHERE {$where} AND r.user_id IS NOT NULL
        GROUP BY r.user_id, user_name, emp_code
        ORDER BY requests DESC
        LIMIT 10";

        return $this->db->query($sql)->result_array();
    }

    /**
     * Hourly request heatmap (hour × day-of-week)
     */
    private function _getHourlyHeatmap($from, $to, $module = null) {
        $where = $this->_buildWhere($from, $to, $module);

        $sql = "SELECT 
            HOUR(request_time) AS hour,
            DAYOFWEEK(request_time) AS dow,
            COUNT(*) AS requests,
            ROUND(AVG(response_time_ms)) AS avg_ms
        FROM " . self::TABLE . " WHERE {$where}
        GROUP BY HOUR(request_time), DAYOFWEEK(request_time)
        ORDER BY dow, hour";

        return $this->db->query($sql)->result_array();
    }

    /**
     * N+1 query patterns — endpoints with repeated identical query shapes
     */
    private function _getNPlusOne($from, $to, $module = null) {
        $where = $this->_buildWhere($from, $to, $module);

        // Summary: how many requests had N+1 issues
        $summary_sql = "SELECT 
            COUNT(*) AS total_n1_requests,
            ROUND(AVG(n1_query_count)) AS avg_repeat_count,
            MAX(n1_query_count) AS max_repeat_count,
            ROUND(AVG(n1_total_ms)) AS avg_wasted_ms,
            SUM(n1_total_ms) AS total_wasted_ms
        FROM " . self::TABLE . " WHERE {$where} AND n1_detected = 1";

        $summary = $this->db->query($summary_sql)->row_array();

        // Worst offenders: endpoints with most N+1 occurrences
        $offenders_sql = "SELECT 
            controller, method,
            COUNT(*) AS n1_occurrences,
            ROUND(AVG(n1_query_count)) AS avg_repeats,
            MAX(n1_query_count) AS max_repeats,
            ROUND(AVG(n1_total_ms)) AS avg_wasted_ms,
            ROUND(AVG(response_time_ms)) AS avg_response_ms,
            ROUND(AVG(db_query_count)) AS avg_total_queries
        FROM " . self::TABLE . " 
        WHERE {$where} AND n1_detected = 1
        GROUP BY controller, method
        ORDER BY n1_occurrences DESC
        LIMIT 15";

        $offenders = $this->db->query($offenders_sql)->result_array();

        // Recent N+1 samples with actual SQL
        $samples_sql = "SELECT 
            controller, method, n1_query_count, n1_total_ms, 
            n1_pattern_sql, request_time, response_time_ms, db_query_count
        FROM " . self::TABLE . " 
        WHERE {$where} AND n1_detected = 1 AND n1_pattern_sql IS NOT NULL
        ORDER BY n1_query_count DESC
        LIMIT 10";

        $samples = $this->db->query($samples_sql)->result_array();

        return [
            'summary'   => $summary,
            'offenders' => $offenders,
            'samples'   => $samples,
        ];
    }

    /**
     * Purge old request logs 
     * Call via: /admin_apm/purge?days=30
     */
    public function purge() {
        header('Content-Type: application/json');
        $days = (int)($this->input->get('days') ?: 30);
        $cutoff = date('Y-m-d', strtotime("-{$days} days"));
        
        $this->db->where('request_date <', $cutoff);
        $deleted = $this->db->delete(self::TABLE);
        $affected = $this->db->affected_rows();

        echo json_encode([
            'status'  => 'ok',
            'deleted' => $affected,
            'cutoff'  => $cutoff,
        ]);
    }

    /**
     * A9: Endpoint-level drill-down
     * /admin_apm/endpoint_detail?controller=admin_billing&method=save&range=today
     */
    public function endpoint_detail() {
        header('Content-Type: application/json');

        $controller = $this->input->get('controller');
        $method = $this->input->get('method');
        if (!$controller) {
            echo json_encode(['status' => 'error', 'msg' => 'controller param required']);
            return;
        }

        $range = $this->input->get('range') ?: 'today';
        $from = date('Y-m-d');
        $to = $from;
        switch ($range) {
            case '7d': $from = date('Y-m-d', strtotime('-7 days')); break;
            case '30d': $from = date('Y-m-d', strtotime('-30 days')); break;
        }

        $where = $this->_buildWhere($from, $to);
        $where .= " AND controller = '{$this->db->escape_str($controller)}'";
        if ($method) {
            $where .= " AND method = '{$this->db->escape_str($method)}'";
        }

        // Summary stats for this endpoint
        $summary = $this->db->query("SELECT
            COUNT(*) AS total_requests,
            ROUND(AVG(response_time_ms)) AS avg_ms,
            MAX(response_time_ms) AS max_ms,
            ROUND(AVG(memory_peak_kb)) AS avg_memory_kb,
            ROUND(AVG(db_query_count), 1) AS avg_queries,
            ROUND(AVG(response_size_bytes)) AS avg_response_bytes,
            SUM(CASE WHEN http_status >= 400 THEN 1 ELSE 0 END) AS errors,
            SUM(CASE WHEN n1_detected = 1 THEN 1 ELSE 0 END) AS n1_count
        FROM " . self::TABLE . " WHERE {$where}")->row_array();

        // Hourly trend for this endpoint
        $hourly = $this->db->query("SELECT
            DATE_FORMAT(request_time, '%Y-%m-%d %H:00') AS hour,
            COUNT(*) AS requests,
            ROUND(AVG(response_time_ms)) AS avg_ms,
            MAX(response_time_ms) AS max_ms,
            SUM(CASE WHEN http_status >= 400 THEN 1 ELSE 0 END) AS errors
        FROM " . self::TABLE . " WHERE {$where}
        GROUP BY DATE_FORMAT(request_time, '%Y-%m-%d %H')
        ORDER BY hour DESC LIMIT 72")->result_array();

        // Recent requests (last 20)
        $recent = $this->db->query("SELECT
            request_time, response_time_ms, http_status, db_query_count,
            memory_peak_kb, response_size_bytes, n1_detected, is_ajax,
            user_id, error_message
        FROM " . self::TABLE . " WHERE {$where}
        ORDER BY request_time DESC LIMIT 20")->result_array();

        echo json_encode([
            'status' => 'ok',
            'endpoint' => $controller . ($method ? '/' . $method : ''),
            'range' => ['from' => $from, 'to' => $to],
            'summary' => $summary,
            'hourly' => $hourly,
            'recent' => $recent,
        ]);
    }

    /**
     * A7: Export APM data as CSV
     * /admin_apm/export_csv?range=7d&module=admin_billing
     */
    public function export_csv() {
        $range = $this->input->get('range') ?: 'today';
        $from = date('Y-m-d');
        $to = $from;
        switch ($range) {
            case '7d': $from = date('Y-m-d', strtotime('-7 days')); break;
            case '30d': $from = date('Y-m-d', strtotime('-30 days')); break;
            case 'custom':
                $from = $this->input->get('from') ?: $from;
                $to   = $this->input->get('to')   ?: $to;
                break;
        }

        $module = $this->input->get('module') ?: null;
        $where = $this->_buildWhere($from, $to, $module);

        $rows = $this->db->query("SELECT
            request_date, request_time, controller, method, uri, http_method,
            response_time_ms, memory_peak_kb, db_query_count, db_query_time_ms,
            slowest_query_ms, http_status, is_ajax, user_id, response_size_bytes,
            n1_detected, n1_query_count, error_message
        FROM " . self::TABLE . " WHERE {$where}
        ORDER BY request_time DESC
        LIMIT 10000")->result_array();

        $filename = 'apm_export_' . $from . '_to_' . $to . '.csv';
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $out = fopen('php://output', 'w');
        if (!empty($rows)) {
            fputcsv($out, array_keys($rows[0]));
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
        }
        fclose($out);
    }

    // ──────────────────────────────────────────────
    //  Alerting
    // ──────────────────────────────────────────────

    /**
     * Check APM thresholds and send email alerts if breached
     * 
     * Call via cron every 5 min:
     *   curl -s http://localhost/index.php/admin_apm/check_alerts
     * 
     * Uses last 15 minutes of data. Respects cooldown to prevent flooding.
     */
    public function check_alerts() {
        header('Content-Type: application/json');

        $this->config->load('apm', TRUE, TRUE);
        $apm = $this->config->item('apm');

        if (empty($apm['apm_alerts_enabled'])) {
            echo json_encode(['status' => 'disabled']);
            return;
        }

        $recipients = isset($apm['apm_alert_recipients']) ? $apm['apm_alert_recipients'] : '';
        if (empty($recipients)) {
            echo json_encode(['status' => 'error', 'msg' => 'No recipients configured']);
            return;
        }

        // Last 15 minutes
        $now = date('Y-m-d H:i:s');
        $ago = date('Y-m-d H:i:s', strtotime('-15 minutes'));
        $today = date('Y-m-d');

        // Get stats for the window
        $stats = $this->db->query("SELECT 
            COUNT(*) AS total,
            ROUND(AVG(response_time_ms)) AS avg_ms,
            SUM(CASE WHEN http_status >= 500 THEN 1 ELSE 0 END) AS server_errors,
            SUM(CASE WHEN http_status >= 400 THEN 1 ELSE 0 END) AS all_errors,
            ROUND(AVG(db_query_count), 1) AS avg_queries,
            SUM(CASE WHEN n1_detected = 1 THEN 1 ELSE 0 END) AS n1_requests
        FROM " . self::TABLE . " 
        WHERE request_date = '{$today}' 
          AND request_time BETWEEN '{$ago}' AND '{$now}'")->row_array();

        $total = (int)($stats['total'] ?? 0);
        if ($total < 5) {
            // Not enough data to make judgements
            echo json_encode(['status' => 'ok', 'msg' => 'Insufficient data', 'requests' => $total]);
            return;
        }

        // P95 calculation
        $p95_offset = (int)floor($total * 0.95);
        $p95_row = $this->db->query("SELECT response_time_ms FROM " . self::TABLE . " 
            WHERE request_date = '{$today}' AND request_time BETWEEN '{$ago}' AND '{$now}'
            ORDER BY response_time_ms ASC LIMIT 1 OFFSET {$p95_offset}")->row();
        $p95_ms = $p95_row ? (int)$p95_row->response_time_ms : 0;

        $error_rate = $total > 0 ? round((int)$stats['all_errors'] / $total * 100, 2) : 0;

        // ── Check thresholds ──
        $alerts = [];
        $cooldown = isset($apm['apm_alert_cooldown_minutes']) ? (int)$apm['apm_alert_cooldown_minutes'] : 30;

        // P95 response time
        $threshold_p95 = isset($apm['apm_alert_p95_ms']) ? (int)$apm['apm_alert_p95_ms'] : 5000;
        if ($p95_ms > $threshold_p95) {
            $alerts[] = [
                'type' => 'slow_p95',
                'title' => '🐌 Slow Response Time',
                'detail' => "P95 response time is {$p95_ms}ms (threshold: {$threshold_p95}ms)",
                'value' => $p95_ms,
            ];
        }

        // Error rate
        $threshold_err = isset($apm['apm_alert_error_rate_pct']) ? (float)$apm['apm_alert_error_rate_pct'] : 5;
        if ($error_rate > $threshold_err) {
            $alerts[] = [
                'type' => 'high_error_rate',
                'title' => '❌ High Error Rate',
                'detail' => "Error rate is {$error_rate}% ({$stats['all_errors']}/{$total} requests) — threshold: {$threshold_err}%",
                'value' => $error_rate,
            ];
        }

        // 5xx server errors
        $threshold_5xx = isset($apm['apm_alert_5xx_count']) ? (int)$apm['apm_alert_5xx_count'] : 5;
        if ((int)$stats['server_errors'] > $threshold_5xx) {
            $alerts[] = [
                'type' => 'server_errors',
                'title' => '🔥 Server Errors Spike',
                'detail' => "{$stats['server_errors']} 5xx errors in last 15 min (threshold: {$threshold_5xx})",
                'value' => (int)$stats['server_errors'],
            ];
        }

        // N+1 queries
        $threshold_n1 = isset($apm['apm_alert_n1_count']) ? (int)$apm['apm_alert_n1_count'] : 3;
        if ((int)$stats['n1_requests'] > $threshold_n1) {
            $alerts[] = [
                'type' => 'n1_queries',
                'title' => '🔄 N+1 Query Spike',
                'detail' => "{$stats['n1_requests']} requests with N+1 patterns in last 15 min (threshold: {$threshold_n1})",
                'value' => (int)$stats['n1_requests'],
            ];
        }

        // Average queries per request
        $threshold_qpr = isset($apm['apm_alert_avg_queries']) ? (int)$apm['apm_alert_avg_queries'] : 50;
        if ((float)$stats['avg_queries'] > $threshold_qpr) {
            $alerts[] = [
                'type' => 'high_query_count',
                'title' => '📊 High Query Count',
                'detail' => "Average {$stats['avg_queries']} queries/request (threshold: {$threshold_qpr})",
                'value' => (float)$stats['avg_queries'],
            ];
        }

        // ── Send alerts (respecting cooldown) ──
        $sent = [];
        $skipped = [];
        $lock_dir = APPPATH . 'cache/apm_alerts/';
        if (!is_dir($lock_dir)) @mkdir($lock_dir, 0755, true);

        foreach ($alerts as $alert) {
            $lock_file = $lock_dir . $alert['type'] . '.lock';
            
            // Check cooldown
            if (file_exists($lock_file)) {
                $last_sent = (int)file_get_contents($lock_file);
                if ((time() - $last_sent) < ($cooldown * 60)) {
                    $skipped[] = $alert['type'];
                    continue;
                }
            }

            // Send email
            if ($this->_sendAlertEmail($alert, $stats, $apm)) {
                file_put_contents($lock_file, time());
                $sent[] = $alert['type'];
            }
        }

        echo json_encode([
            'status' => 'ok',
            'window' => ['from' => $ago, 'to' => $now],
            'requests' => $total,
            'alerts_triggered' => count($alerts),
            'alerts_sent' => $sent,
            'alerts_cooldown' => $skipped,
        ]);
    }

    /**
     * Send a single APM alert email
     */
    private function _sendAlertEmail($alert, $stats, $apm) {
        $this->load->library('email');

        $from = isset($apm['apm_alert_from']) ? $apm['apm_alert_from'] : 'apm@logimax.in';
        $from_name = isset($apm['apm_alert_from_name']) ? $apm['apm_alert_from_name'] : 'Logimax APM';
        $recipients = $apm['apm_alert_recipients'];

        $this->email->initialize([
            'protocol'  => 'smtp',
            'smtp_host' => 'localhost',
            'smtp_port' => 25,
            'mailtype'  => 'html',
            'charset'   => 'utf-8',
        ]);

        $this->email->from($from, $from_name);
        $this->email->to($recipients);
        $this->email->subject('⚠️ APM Alert: ' . $alert['title']);

        $body = '
        <div style="font-family:Inter,Arial,sans-serif;max-width:600px;margin:0 auto;background:#1a1d27;color:#e4e6eb;border-radius:12px;overflow:hidden">
            <div style="background:#e74c3c;padding:16px 24px;color:#fff">
                <h2 style="margin:0;font-size:18px">' . $alert['title'] . '</h2>
            </div>
            <div style="padding:24px">
                <p style="font-size:15px;margin:0 0 16px;color:#e4e6eb">' . $alert['detail'] . '</p>
                <table style="width:100%;border-collapse:collapse;font-size:13px;color:#8b8fa3">
                    <tr><td style="padding:6px 0">Total Requests (15 min)</td><td style="text-align:right;font-weight:600;color:#e4e6eb">' . $stats['total'] . '</td></tr>
                    <tr><td style="padding:6px 0">Avg Response</td><td style="text-align:right;font-weight:600;color:#e4e6eb">' . $stats['avg_ms'] . 'ms</td></tr>
                    <tr><td style="padding:6px 0">5xx Errors</td><td style="text-align:right;font-weight:600;color:' . ((int)$stats['server_errors'] > 0 ? '#e74c3c' : '#00b894') . '">' . $stats['server_errors'] . '</td></tr>
                    <tr><td style="padding:6px 0">N+1 Requests</td><td style="text-align:right;font-weight:600;color:#e4e6eb">' . $stats['n1_requests'] . '</td></tr>
                    <tr><td style="padding:6px 0">Avg Queries/Req</td><td style="text-align:right;font-weight:600;color:#e4e6eb">' . $stats['avg_queries'] . '</td></tr>
                </table>
                <div style="margin-top:20px;padding-top:16px;border-top:1px solid #2a2e3a;font-size:11px;color:#8b8fa3">
                    Sent at ' . date('Y-m-d H:i:s') . ' · Cooldown: ' . (isset($apm['apm_alert_cooldown_minutes']) ? $apm['apm_alert_cooldown_minutes'] : 30) . ' min
                </div>
            </div>
        </div>';

        $this->email->message($body);

        return $this->email->send(FALSE);
    }

    // ──────────────────────────────────────────────
    //  Helpers
    // ──────────────────────────────────────────────

    private function _buildWhere($from, $to, $module = null) {
        $where = "request_date BETWEEN '{$this->db->escape_str($from)}' AND '{$this->db->escape_str($to)}'";
        if ($module) {
            $where .= " AND controller = '{$this->db->escape_str($module)}'";
        }
        return $where;
    }

    /**
     * A8: Full percentile breakdown (P50, P75, P90, P95, P99)
     */
    private function _getPercentiles($from, $to, $module = null) {
        $where = $this->_buildWhere($from, $to, $module);

        $cnt = $this->db->query("SELECT COUNT(*) AS cnt FROM " . self::TABLE . " WHERE {$where}")->row();
        $total = $cnt ? (int)$cnt->cnt : 0;

        if ($total < 10) {
            return ['total' => $total, 'p50' => 0, 'p75' => 0, 'p90' => 0, 'p95' => 0, 'p99' => 0];
        }

        $percentiles = [];
        foreach ([50, 75, 90, 95, 99] as $p) {
            $offset = (int)floor($total * ($p / 100));
            if ($offset >= $total) $offset = $total - 1;
            $row = $this->db->query("SELECT response_time_ms FROM " . self::TABLE . "
                WHERE {$where} ORDER BY response_time_ms ASC
                LIMIT 1 OFFSET {$offset}")->row();
            $percentiles['p' . $p] = $row ? (int)$row->response_time_ms : 0;
        }

        $percentiles['total'] = $total;
        return $percentiles;
    }

    /**
     * A6: Bloated AJAX responses (large payloads)
     */
    private function _getBloatedResponses($from, $to, $module = null) {
        $where = $this->_buildWhere($from, $to, $module);

        return $this->db->query("SELECT
            controller, method, 
            COUNT(*) AS requests,
            ROUND(AVG(response_size_bytes)) AS avg_bytes,
            MAX(response_size_bytes) AS max_bytes,
            ROUND(AVG(response_size_bytes) / 1024, 1) AS avg_kb,
            ROUND(MAX(response_size_bytes) / 1024, 1) AS max_kb
        FROM " . self::TABLE . " WHERE {$where}
            AND response_size_bytes > 0
        GROUP BY controller, method
        HAVING avg_bytes > 51200
        ORDER BY avg_bytes DESC
        LIMIT 15")->result_array();
    }
}
