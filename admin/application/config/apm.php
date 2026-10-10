<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

/*
|--------------------------------------------------------------------------
| APM Configuration
|--------------------------------------------------------------------------
|
| Controls the Performance Monitoring system.
| Edit this file to toggle monitoring per module.
|
*/

/*
|--------------------------------------------------------------------------
| Master Switch
|--------------------------------------------------------------------------
| Set to FALSE to disable APM entirely (zero overhead).
*/
$config['apm_enabled'] = TRUE;

/*
|--------------------------------------------------------------------------
| Monitoring Mode
|--------------------------------------------------------------------------
| 'all'  — Monitor every controller EXCEPT those in apm_skip list
| 'only' — Monitor ONLY the controllers in apm_only list
|
| Use 'all' for broad monitoring (recommended for dev/staging)
| Use 'only' for targeted monitoring (recommended for production)
*/
$config['apm_mode'] = 'all';

/*
|--------------------------------------------------------------------------
| Skip List (used when apm_mode = 'all')
|--------------------------------------------------------------------------
| Controllers to EXCLUDE from monitoring.
| The APM dashboard is always excluded automatically.
|
| Example: array('admin_apm', 'admin_assets', 'admin_cron')
*/
$config['apm_skip'] = array(
    'admin_apm',
);

/*
|--------------------------------------------------------------------------
| Include List (used when apm_mode = 'only')
|--------------------------------------------------------------------------
| ONLY these controllers will be monitored. Everything else is ignored.
| Useful for focusing on specific modules you want to optimize.
|
| Example: array('admin_ret_billing', 'admin_ret_stock', 'admin_dashboard')
*/
$config['apm_only'] = array(
    'admin_ret_billing',
    'admin_ret_stock',
    'admin_dashboard',
    'admin_ret_dashboard',
    'admin_ret_reports',
    'admin_nl_reports',
    'admin_ret_purchase',
);

/*
|--------------------------------------------------------------------------
| Sampling Rate
|--------------------------------------------------------------------------
| Percentage of requests to log (1-100).
| 100 = every request (full visibility, more DB writes)
|  50 = every other request
|  10 = 1 in 10 requests (lightweight, good for production)
|   1 = 1 in 100 requests (minimal overhead, trend analysis only)
*/
$config['apm_sample_rate'] = 100;

/*
|--------------------------------------------------------------------------
| Slow Request Threshold (milliseconds)
|--------------------------------------------------------------------------
| Requests slower than this are flagged as "slow" in the dashboard.
| Default: 3000 (3 seconds)
*/
$config['apm_slow_threshold_ms'] = 3000;

/*
|--------------------------------------------------------------------------
| Skip AJAX Requests
|--------------------------------------------------------------------------
| Set to TRUE to only monitor full page loads (ignore AJAX/XHR calls).
| Set to FALSE to monitor everything including AJAX.
*/
$config['apm_skip_ajax'] = FALSE;

/*
|--------------------------------------------------------------------------
| Data Retention (days)
|--------------------------------------------------------------------------
| How many days of data to keep. Older records are purged by the
| Admin_apm::purge() method. Set to 0 to keep everything (not recommended).
*/
$config['apm_retention_days'] = 30;

/*
|--------------------------------------------------------------------------
| Email Alerting
|--------------------------------------------------------------------------
| Send email alerts when performance thresholds are breached.
| Checked via: /admin_apm/check_alerts (call from cron every 5 min)
|
| Thresholds use the last 15 minutes of data.
*/
$config['apm_alerts_enabled'] = TRUE;

// Recipients (comma-separated)
$config['apm_alert_recipients'] = 'admin@logimax.in';

// From address
$config['apm_alert_from'] = 'apm@logimax.in';
$config['apm_alert_from_name'] = 'Logimax APM';

// Cooldown: minimum minutes between alerts of the same type
// Prevents email flooding during sustained issues
$config['apm_alert_cooldown_minutes'] = 30;

// ── Thresholds ──

// Alert when P95 response time exceeds this (milliseconds)
$config['apm_alert_p95_ms'] = 5000;

// Alert when error rate exceeds this (percentage)
$config['apm_alert_error_rate_pct'] = 5;

// Alert when N+1 queries detected in more than X requests (last 15 min)
$config['apm_alert_n1_count'] = 3;

// Alert when average queries per request exceeds this
$config['apm_alert_avg_queries'] = 50;

// Alert when 5xx server errors exceed this count (last 15 min)
$config['apm_alert_5xx_count'] = 5;


/* End of file apm.php */
/* Location: ./application/config/apm.php */
