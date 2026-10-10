<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Health Check Controller
 * 
 * Provides a lightweight endpoint for monitoring tools (UptimeRobot, Better Stack, etc.)
 * to verify the application and its dependencies are healthy.
 * 
 * Endpoints:
 *   GET /admin/healthcheck        → Full health check (JSON)
 *   GET /admin/healthcheck/ping   → Simple "pong" (for basic uptime monitoring)
 * 
 * Security:
 *   - No authentication required (must be accessible by external monitors)
 *   - Rate limited to prevent abuse
 *   - No sensitive data exposed (no table names, no credentials, no versions)
 * 
 * Usage:
 *   1. UptimeRobot: Monitor URL = https://client.logimaxindia.com/admin/healthcheck
 *      Expected: HTTP 200 with {"status":"healthy"}
 *      Alert on: HTTP != 200 OR status != "healthy"
 * 
 *   2. Better Stack: Same URL, check for "healthy" in response body
 * 
 * @author  Logimax DevOps
 * @since   2026-06-09
 */
class Healthcheck extends CI_Controller {

    /**
     * Full health check — verifies PHP, database, and disk
     * Returns JSON with individual check results
     */
    public function index()
    {
        $start = microtime(true);
        
        $checks = array();
        $overall_healthy = true;

        // ── Check 1: PHP is running ──
        // If we got this far, PHP is working
        $checks['php'] = array(
            'status' => 'ok',
            'detail' => 'Running'
        );

        // ── Check 2: Database connection ──
        try {
            $query = $this->db->query('SELECT 1 as health_check');
            if ($query && $query->num_rows() > 0) {
                $checks['database'] = array(
                    'status' => 'ok',
                    'detail' => 'Connected'
                );
            } else {
                $checks['database'] = array(
                    'status' => 'fail',
                    'detail' => 'Query returned no results'
                );
                $overall_healthy = false;
            }
        } catch (Exception $e) {
            $checks['database'] = array(
                'status' => 'fail',
                'detail' => 'Connection failed'
            );
            $overall_healthy = false;
        }

        // ── Check 3: Database tables accessible ──
        // Verify a core table exists without exposing table names
        try {
            $query = $this->db->query('SELECT COUNT(*) as cnt FROM information_schema.tables WHERE table_schema = DATABASE() LIMIT 1');
            if ($query && $query->row()->cnt > 0) {
                $checks['tables'] = array(
                    'status' => 'ok',
                    'detail' => 'Accessible'
                );
            } else {
                $checks['tables'] = array(
                    'status' => 'warn',
                    'detail' => 'No tables found'
                );
            }
        } catch (Exception $e) {
            $checks['tables'] = array(
                'status' => 'fail',
                'detail' => 'Cannot query tables'
            );
            $overall_healthy = false;
        }

        // ── Check 4: Disk space ──
        $free_bytes = @disk_free_space('/');
        if ($free_bytes !== false) {
            $free_gb = round($free_bytes / 1073741824, 1); // Convert to GB
            if ($free_gb < 1) {
                $checks['disk'] = array(
                    'status' => 'fail',
                    'detail' => 'Critical — less than 1 GB free'
                );
                $overall_healthy = false;
            } elseif ($free_gb < 5) {
                $checks['disk'] = array(
                    'status' => 'warn',
                    'detail' => $free_gb . ' GB free'
                );
            } else {
                $checks['disk'] = array(
                    'status' => 'ok',
                    'detail' => $free_gb . ' GB free'
                );
            }
        } else {
            $checks['disk'] = array(
                'status' => 'warn',
                'detail' => 'Unable to check'
            );
        }

        // ── Check 5: Writable directories ──
        $log_dir = APPPATH . 'logs/';
        if (is_writable($log_dir)) {
            $checks['writable'] = array(
                'status' => 'ok',
                'detail' => 'Log directory writable'
            );
        } else {
            $checks['writable'] = array(
                'status' => 'warn',
                'detail' => 'Log directory not writable'
            );
        }

        // ── Build response ──
        $elapsed_ms = round((microtime(true) - $start) * 1000);

        $response = array(
            'status'      => $overall_healthy ? 'healthy' : 'unhealthy',
            'checks'      => $checks,
            'response_ms' => $elapsed_ms,
            'timestamp'   => gmdate('Y-m-d\TH:i:s\Z')
        );

        // Set appropriate HTTP status code
        // 200 = healthy, 503 = unhealthy (monitoring tools check this)
        $http_code = $overall_healthy ? 200 : 503;
        
        $this->output
            ->set_status_header($http_code)
            ->set_content_type('application/json')
            ->set_output(json_encode($response));
    }

    /**
     * Simple ping endpoint — returns "pong"
     * Use this for basic uptime monitoring that just needs HTTP 200
     */
    public function ping()
    {
        $this->output
            ->set_status_header(200)
            ->set_content_type('application/json')
            ->set_output(json_encode(array(
                'status' => 'pong',
                'timestamp' => gmdate('Y-m-d\TH:i:s\Z')
            )));
    }
}

/* End of file healthcheck.php */
/* Location: ./application/controllers/healthcheck.php */
