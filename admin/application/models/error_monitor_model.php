<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Error Monitor Model
 * 
 * Handles error logging, deduplication, and retrieval for the error monitoring system.
 * Uses error_hash (MD5 of type+file+line+message) for deduplication — if the same
 * error occurs again, we increment occurrence_count instead of creating a new row.
 */
class Error_monitor_model extends CI_Model {

    private $table = 'error_log';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Log an error with deduplication.
     * If the same error_hash exists, increment occurrence_count and update last_seen.
     * Otherwise, insert a new row.
     *
     * @param array $data Error data
     * @return int|bool Inserted/updated row ID or false
     */
    public function log_error($data)
    {
        // Flush any lingering active record query builder state from prior erroring query
        $this->db->flush_cache();
        if (property_exists($this->db, 'ar_where')) {
            $this->db->ar_where = array();
            $this->db->ar_select = array();
            $this->db->ar_from = array();
            $this->db->ar_join = array();
            $this->db->ar_like = array();
            $this->db->ar_groupby = array();
            $this->db->ar_orderby = array();
            $this->db->ar_wherein = array();
        }

        // Build hash for deduplication: same error type + file + line + message = same error
        $hash_input = ($data['error_type'] ?? '') . '|' . ($data['file'] ?? '') . '|' 
                     . ($data['line'] ?? '') . '|' . ($data['message'] ?? '');
        $error_hash = md5($hash_input);

        // Check if this error already exists
        $existing = $this->db->where('error_hash', $error_hash)
                             ->where('resolved_at IS NULL')
                             ->get($this->table)
                             ->row();

        if ($existing) {
            // Increment occurrence count and update last_seen
            $this->db->where('id', $existing->id)
                     ->set('occurrence_count', 'occurrence_count + 1', FALSE)
                     ->set('last_seen', date('Y-m-d H:i:s'))
                     ->set('url', $data['url'] ?? $existing->url)
                     ->set('user_id', $data['user_id'] ?? $existing->user_id)
                     ->update($this->table);

            return $existing->id;
        }

        // Determine severity based on error type
        $severity = $this->_classify_severity($data['error_type'] ?? '', $data['message'] ?? '');

        // Insert new error
        $now = date('Y-m-d H:i:s');
        $insert_data = array(
            'error_hash'       => $error_hash,
            'error_type'       => $data['error_type'] ?? 'php_error',
            'severity'         => $severity,
            'message'          => mb_substr($data['message'] ?? '', 0, 65535),
            'file'             => mb_substr($data['file'] ?? '', 0, 500),
            'line'             => (int)($data['line'] ?? 0),
            'stack_trace'      => mb_substr($data['stack_trace'] ?? '', 0, 65535),
            'url'              => mb_substr($data['url'] ?? '', 0, 1000),
            'controller'       => mb_substr($data['controller'] ?? '', 0, 100),
            'method'           => mb_substr($data['method'] ?? '', 0, 100),
            'user_id'          => $data['user_id'] ?? null,
            'user_agent'       => mb_substr($data['user_agent'] ?? '', 0, 500),
            'ip_address'       => mb_substr($data['ip_address'] ?? '', 0, 45),
            'request_data'     => mb_substr($data['request_data'] ?? '', 0, 65535),
            'occurrence_count' => 1,
            'first_seen'       => $now,
            'last_seen'        => $now,
        );

        $this->db->insert($this->table, $insert_data);
        return $this->db->insert_id();
    }

    /**
     * Get recent errors with optional filters.
     *
     * @param array $filters ['type', 'severity', 'resolved', 'search', 'hours']
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function get_errors($filters = array(), $limit = 50, $offset = 0)
    {
        $this->db->from($this->table);

        if (!empty($filters['type'])) {
            $this->db->where('error_type', $filters['type']);
        }

        if (!empty($filters['severity'])) {
            $this->db->where('severity', $filters['severity']);
        }

        if (isset($filters['resolved'])) {
            if ($filters['resolved']) {
                $this->db->where('resolved_at IS NOT NULL');
            } else {
                $this->db->where('resolved_at IS NULL');
            }
        }

        if (!empty($filters['search'])) {
            $search = $this->db->escape_like_str($filters['search']);
            $this->db->group_start()
                     ->like('message', $search)
                     ->or_like('file', $search)
                     ->or_like('controller', $search)
                     ->group_end();
        }

        if (!empty($filters['hours'])) {
            $since = date('Y-m-d H:i:s', strtotime("-{$filters['hours']} hours"));
            $this->db->where('last_seen >=', $since);
        }

        if (!empty($filters['controller'])) {
            $this->db->where('controller', $filters['controller']);
        }

        $this->db->order_by('last_seen', 'DESC');
        $this->db->limit($limit, $offset);

        return $this->db->get()->result();
    }

    /**
     * Get error count by filters (for pagination).
     */
    public function count_errors($filters = array())
    {
        $this->db->from($this->table);

        if (!empty($filters['type'])) {
            $this->db->where('error_type', $filters['type']);
        }
        if (!empty($filters['severity'])) {
            $this->db->where('severity', $filters['severity']);
        }
        if (isset($filters['resolved']) && !$filters['resolved']) {
            $this->db->where('resolved_at IS NULL');
        }

        return $this->db->count_all_results();
    }

    /**
     * Get dashboard summary stats.
     *
     * @param int $hours Lookback period in hours (default 24)
     * @return array
     */
    public function get_dashboard_stats($hours = 24)
    {
        $since = date('Y-m-d H:i:s', strtotime("-{$hours} hours"));

        // Total unresolved
        $total_unresolved = $this->db->where('resolved_at IS NULL')
                                     ->count_all_results($this->table);

        // New in period
        $new_in_period = $this->db->where('first_seen >=', $since)
                                  ->count_all_results($this->table);

        // Occurrences in period (sum of occurrence increments)
        $this->db->select('SUM(occurrence_count) as total_occurrences')
                 ->where('last_seen >=', $since);
        $occ = $this->db->get($this->table)->row();
        $occurrences = (int)($occ->total_occurrences ?? 0);

        // By severity
        $by_severity = array();
        $this->db->select('severity, COUNT(*) as cnt')
                 ->where('resolved_at IS NULL')
                 ->group_by('severity');
        foreach ($this->db->get($this->table)->result() as $row) {
            $by_severity[$row->severity] = (int)$row->cnt;
        }

        // By type
        $by_type = array();
        $this->db->select('error_type, COUNT(*) as cnt')
                 ->where('resolved_at IS NULL')
                 ->group_by('error_type');
        foreach ($this->db->get($this->table)->result() as $row) {
            $by_type[$row->error_type] = (int)$row->cnt;
        }

        // Top offenders (most occurrences)
        $top_errors = $this->db->select('id, error_type, severity, message, file, line, controller, occurrence_count, last_seen')
                               ->where('resolved_at IS NULL')
                               ->order_by('occurrence_count', 'DESC')
                               ->limit(10)
                               ->get($this->table)
                               ->result();

        // Hourly trend (last 24h)
        $trend = array();
        $this->db->select("DATE_FORMAT(last_seen, '%Y-%m-%d %H:00:00') as hour, COUNT(*) as cnt")
                 ->where('last_seen >=', $since)
                 ->group_by('hour')
                 ->order_by('hour', 'ASC');
        foreach ($this->db->get($this->table)->result() as $row) {
            $trend[] = array('hour' => $row->hour, 'count' => (int)$row->cnt);
        }

        return array(
            'total_unresolved'  => $total_unresolved,
            'new_in_period'     => $new_in_period,
            'total_occurrences' => $occurrences,
            'by_severity'       => $by_severity,
            'by_type'           => $by_type,
            'top_errors'        => $top_errors,
            'hourly_trend'      => $trend,
            'period_hours'      => $hours,
        );
    }

    /**
     * Mark an error as resolved.
     */
    public function resolve_error($id, $user_id = null, $notes = '')
    {
        $this->db->where('id', (int)$id)
                 ->update($this->table, array(
                     'resolved_at' => date('Y-m-d H:i:s'),
                     'resolved_by' => $user_id,
                     'notes'       => $notes,
                 ));

        return $this->db->affected_rows() > 0;
    }

    /**
     * Mark error as acknowledged (stop alerting but don't resolve).
     */
    public function acknowledge_error($id)
    {
        $this->db->where('id', (int)$id)
                 ->update($this->table, array('is_acknowledged' => 1));

        return $this->db->affected_rows() > 0;
    }

    /**
     * Bulk resolve errors older than N days.
     */
    public function auto_resolve_old($days = 30)
    {
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $this->db->where('resolved_at IS NULL')
                 ->where('last_seen <', $cutoff)
                 ->update($this->table, array(
                     'resolved_at' => date('Y-m-d H:i:s'),
                     'notes'       => "Auto-resolved: no occurrence in {$days} days",
                 ));

        return $this->db->affected_rows();
    }

    /**
     * Check if error_log table exists. Auto-creates if missing.
     * This makes the error monitor self-bootstrapping — no manual SQL needed.
     */
    public function table_exists()
    {
        if ($this->db->table_exists($this->table)) {
            return true;
        }

        // Auto-create the table
        return $this->_create_table();
    }

    /**
     * Create the error_log table if it doesn't exist.
     * 
     * @return bool True if created successfully
     */
    private function _create_table()
    {
        $sql = "CREATE TABLE IF NOT EXISTS `{$this->table}` (
            `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `error_hash` VARCHAR(32) NOT NULL COMMENT 'MD5 hash for deduplication',
            `error_type` ENUM('php_fatal','php_error','php_warning','php_exception','js_error','js_ajax','db_error') NOT NULL,
            `severity` ENUM('critical','high','medium','low') NOT NULL DEFAULT 'medium',
            `message` TEXT NOT NULL,
            `file` VARCHAR(500) DEFAULT NULL,
            `line` INT(11) DEFAULT NULL,
            `stack_trace` TEXT DEFAULT NULL,
            `url` VARCHAR(1000) DEFAULT NULL,
            `controller` VARCHAR(100) DEFAULT NULL,
            `method` VARCHAR(100) DEFAULT NULL,
            `user_id` INT(11) DEFAULT NULL,
            `user_agent` VARCHAR(500) DEFAULT NULL,
            `ip_address` VARCHAR(45) DEFAULT NULL,
            `request_data` TEXT DEFAULT NULL,
            `occurrence_count` INT(11) NOT NULL DEFAULT 1,
            `first_seen` DATETIME NOT NULL,
            `last_seen` DATETIME NOT NULL,
            `resolved_at` DATETIME DEFAULT NULL,
            `resolved_by` INT(11) DEFAULT NULL,
            `notes` TEXT DEFAULT NULL,
            `is_acknowledged` TINYINT(1) NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_error_hash` (`error_hash`),
            KEY `idx_type` (`error_type`),
            KEY `idx_severity` (`severity`),
            KEY `idx_last_seen` (`last_seen`),
            KEY `idx_resolved` (`resolved_at`),
            KEY `idx_controller` (`controller`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

        try {
            $this->db->query($sql);
            return $this->db->table_exists($this->table);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Classify error severity based on type and message content.
     */
    private function _classify_severity($type, $message)
    {
        // Fatal errors are always critical
        if ($type === 'php_fatal' || $type === 'php_exception') {
            return 'critical';
        }

        // DB errors are high
        if ($type === 'db_error') {
            return 'high';
        }

        // JS errors — check for critical keywords
        $msg_lower = strtolower($message);
        $critical_keywords = array('cannot read', 'undefined is not', 'fatal', 'out of memory');
        foreach ($critical_keywords as $kw) {
            if (strpos($msg_lower, $kw) !== false) {
                return 'high';
            }
        }

        // AJAX failures
        if ($type === 'js_ajax') {
            if (strpos($msg_lower, '500') !== false || strpos($msg_lower, '503') !== false) {
                return 'high';
            }
            return 'medium';
        }

        // PHP warnings
        if ($type === 'php_warning') {
            // Undefined index in financial context = high
            if (strpos($msg_lower, 'undefined index') !== false || strpos($msg_lower, 'undefined variable') !== false) {
                return 'medium';
            }
            return 'low';
        }

        return 'medium';
    }
}
