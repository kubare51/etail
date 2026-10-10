<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * PerformanceHook — Automatic APM for every request
 * 
 * Reads config from application/config/apm.php for:
 * - Master on/off toggle
 * - Mode: 'all' (exclude list) or 'only' (include list)
 * - Per-module skip/include lists
 * - Sampling rate
 * - AJAX filtering
 * 
 * Uses post_controller_constructor + shutdown closure with independent
 * mysqli connection (bypasses CI lifecycle issues).
 */
class PerformanceHook {

    /**
     * Called at post_controller_constructor
     */
    public function init() {
        $CI =& get_instance();

        // ── Load APM config ──
        $CI->config->load('apm', TRUE, TRUE);
        $apm = $CI->config->item('apm');

        // Master switch
        $enabled = isset($apm['apm_enabled']) ? $apm['apm_enabled'] : TRUE;
        if (!$enabled) return;

        // Need DB and router
        if (!isset($CI->db) || !is_object($CI->db)) return;
        if (!isset($CI->router) || !is_object($CI->router)) return;

        $controller = $CI->router->fetch_class();
        $method     = $CI->router->fetch_method();
        $uri        = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';

        // Always skip APM dashboard itself
        if (stripos($controller, 'admin_apm') !== false) return;

        // Skip static assets
        if (preg_match('/\.(css|js|png|jpg|gif|ico|woff|ttf|svg)$/i', $uri)) return;

        // ── AJAX filter ──
        $is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) 
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
        $skip_ajax = isset($apm['apm_skip_ajax']) ? $apm['apm_skip_ajax'] : FALSE;
        if ($skip_ajax && $is_ajax) return;

        // ── Module filtering ──
        $mode = isset($apm['apm_mode']) ? $apm['apm_mode'] : 'all';
        $ctrl_lower = strtolower($controller);

        if ($mode === 'only') {
            // Only monitor controllers in the include list
            $only = isset($apm['apm_only']) ? $apm['apm_only'] : array();
            $only = array_map('strtolower', $only);
            if (!in_array($ctrl_lower, $only)) return;
        } else {
            // Monitor everything except controllers in skip list
            $skip = isset($apm['apm_skip']) ? $apm['apm_skip'] : array();
            $skip = array_map('strtolower', $skip);
            if (in_array($ctrl_lower, $skip)) return;
        }

        // ── Sampling ──
        $rate = isset($apm['apm_sample_rate']) ? (int)$apm['apm_sample_rate'] : 100;
        if ($rate < 100 && mt_rand(1, 100) > $rate) return;

        // ── Capture DB credentials for independent shutdown connection ──
        $dbhost = isset($CI->db->hostname) ? $CI->db->hostname : '127.0.0.1';
        if ($dbhost === 'localhost') {
            $dbhost = '127.0.0.1';
        }
        $dbuser = isset($CI->db->username) ? $CI->db->username : 'root';
        $dbpass = isset($CI->db->password) ? $CI->db->password : '';
        $dbname = isset($CI->db->database) ? $CI->db->database : '';

        $dbport = 3306;
        if (strpos($dbhost, ':') !== false) {
            list($dbhost, $dbport) = explode(':', $dbhost, 2);
            $dbport = (int)$dbport;
        }

        // ── Context captured now ──
        $hm   = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';
        $ajax = $is_ajax ? 1 : 0;
        $ip   = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
        $ua   = isset($_SERVER['HTTP_USER_AGENT']) ? mb_substr($_SERVER['HTTP_USER_AGENT'], 0, 500) : '';

        $uid = null;
        if (isset($CI->session) && is_object($CI->session)) {
            try {
                $u = $CI->session->userdata('uid');
                if ($u) $uid = (int)$u;
            } catch (Exception $e) {}
        }

        $start = isset($_SERVER['REQUEST_TIME_FLOAT'])
            ? (float)$_SERVER['REQUEST_TIME_FLOAT']
            : microtime(true);

        $db = $CI->db;

        // ── Register shutdown to insert after controller finishes ──
        register_shutdown_function(function() use (
            $dbhost, $dbport, $dbuser, $dbpass, $dbname,
            $controller, $method, $uri, $hm, $ajax, $ip, $ua, $uid, $start, $db
        ) {
            $elapsed_ms = round((microtime(true) - $start) * 1000);
            $peak_kb    = round(memory_get_peak_usage() / 1024);

            // DB query profiling
            $qcount = 0; $qtime = 0; $slow_ms = 0; $slow_sql = null;
            // N+1 detection: normalize queries to fingerprints, count duplicates
            $n1_detected = 0; $n1_count = 0; $n1_pattern = null; $n1_total_ms = 0;
            if (is_object($db) && isset($db->queries) && is_array($db->queries)) {
                $qcount = count($db->queries);
                $fingerprints = array(); // fingerprint => [count, total_ms, sample_sql]
                if (isset($db->query_times) && is_array($db->query_times)) {
                    foreach ($db->query_times as $i => $t) {
                        $ms = round($t * 1000);
                        $qtime += $ms;
                        if ($ms > $slow_ms) {
                            $slow_ms  = $ms;
                            $slow_sql = isset($db->queries[$i]) ? mb_substr($db->queries[$i], 0, 2000) : null;
                        }
                        // Build fingerprint: strip numeric/string literals to detect same-shape queries
                        if (isset($db->queries[$i])) {
                            $fp = preg_replace("/\d+/", '?', $db->queries[$i]);       // numbers → ?
                            $fp = preg_replace("/'[^']*'/", '?', $fp);                // 'strings' → ?
                            $fp = preg_replace('/"[^"]*"/', '?', $fp);                // "strings" → ?
                            $fp = preg_replace('/\s+/', ' ', trim($fp));              // normalize whitespace
                            if (!isset($fingerprints[$fp])) {
                                $fingerprints[$fp] = array('count' => 0, 'total_ms' => 0, 'sample' => $db->queries[$i]);
                            }
                            $fingerprints[$fp]['count']++;
                            $fingerprints[$fp]['total_ms'] += $ms;
                        }
                    }
                }
                // Find worst N+1 pattern (≥10 identical query shapes)
                foreach ($fingerprints as $fp => $info) {
                    if ($info['count'] >= 10 && $info['count'] > $n1_count) {
                        $n1_detected = 1;
                        $n1_count    = $info['count'];
                        $n1_total_ms = $info['total_ms'];
                        $n1_pattern  = mb_substr($info['sample'], 0, 2000);
                    }
                }
            }

            $status = http_response_code();
            if ($status === false) $status = 200;

            // Capture response body size (bytes)
            $response_size = 0;
            $headers = headers_list();
            foreach ($headers as $h) {
                if (stripos($h, 'Content-Length:') === 0) {
                    $response_size = (int)trim(substr($h, 15));
                    break;
                }
            }
            // Fallback: use output buffer length if Content-Length not set
            if ($response_size === 0) {
                $response_size = (int)ob_get_length();
            }

            $err = null;
            $le  = error_get_last();
            if ($le && in_array($le['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR))) {
                $err = mb_substr($le['message'] . ' in ' . $le['file'] . ':' . $le['line'], 0, 1000);
            }

            // Independent mysqli insert
            $conn = @new mysqli($dbhost, $dbuser, $dbpass, $dbname, $dbport);
            if ($conn->connect_error) return;

            $sql = "INSERT INTO `ret_request_log` 
                    (`request_date`,`request_time`,`controller`,`method`,`uri`,`http_method`,
                     `response_time_ms`,`memory_peak_kb`,`db_query_count`,`db_query_time_ms`,
                     `slowest_query_ms`,`slowest_query_sql`,`http_status`,`is_ajax`,
                     `user_id`,`ip_address`,`user_agent`,`error_message`,
                     `n1_detected`,`n1_query_count`,`n1_total_ms`,`n1_pattern_sql`,`response_size_bytes`)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

            $stmt = $conn->prepare($sql);
            if (!$stmt) { $conn->close(); return; }

            $rd = date('Y-m-d');
            $rt = date('Y-m-d H:i:s');
            $ctrl = mb_substr($controller, 0, 100);
            $meth = mb_substr($method, 0, 100);
            $u = mb_substr($uri, 0, 500);

            $stmt->bind_param('ssssssiiiiiisiiisssiiisi',
                $rd, $rt, $ctrl, $meth, $u, $hm,
                $elapsed_ms, $peak_kb, $qcount, $qtime,
                $slow_ms, $slow_sql, $status, $ajax,
                $uid, $ip, $ua, $err,
                $n1_detected, $n1_count, $n1_total_ms, $n1_pattern, $response_size
            );

            @$stmt->execute();
            $stmt->close();

            // ── Auto-purge: 1% chance per request, delete old records ──
            if (mt_rand(1, 100) === 1) {
                $retention = 30; // default days
                try {
                    $CI_inner =& get_instance();
                    if (isset($CI_inner->config)) {
                        $CI_inner->config->load('apm', TRUE, TRUE);
                        $apm_cfg = $CI_inner->config->item('apm');
                        if (isset($apm_cfg['apm_retention_days']) && (int)$apm_cfg['apm_retention_days'] > 0) {
                            $retention = (int)$apm_cfg['apm_retention_days'];
                        }
                    }
                } catch (Exception $e) {}
                $cutoff = date('Y-m-d', strtotime("-{$retention} days"));
                $conn->query("DELETE FROM `ret_request_log` WHERE `request_date` < '{$cutoff}' LIMIT 5000");
            }

            $conn->close();
        });
    }
}
