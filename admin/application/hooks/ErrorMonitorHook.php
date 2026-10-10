<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Error Monitor Hook
 * 
 * Captures PHP errors, exceptions, and fatal errors at runtime.
 * Logs them to the error_log database table via Error_monitor_model.
 * 
 * Registered as a pre_system hook so it captures errors from the earliest point.
 * Does NOT interfere with CI3's own error handling — it adds monitoring on top.
 */
class ErrorMonitorHook {

    /**
     * Initialize error capture handlers.
     * Called by CI3 hook system at pre_system.
     */
    public function capture()
    {
        // Set custom error handler (captures warnings, notices, etc.)
        set_error_handler(array($this, 'handleError'));

        // Set custom exception handler (uncaught exceptions)
        set_exception_handler(array($this, 'handleException'));

        // Register shutdown function (catches fatal errors that bypass handlers)
        register_shutdown_function(array($this, 'handleShutdown'));
    }

    /**
     * Handle PHP errors (warnings, notices, etc.)
     * 
     * @param int $errno Error level
     * @param string $errstr Error message
     * @param string $errfile File where error occurred
     * @param int $errline Line number
     * @return bool False to continue CI3's error handling
     */
    public function handleError($errno, $errstr, $errfile, $errline)
    {
        // Skip suppressed errors (@ operator)
        if (error_reporting() === 0) {
            return false;
        }

        // Skip minor notices in production to avoid log flooding
        if ($errno === E_NOTICE || $errno === E_STRICT || $errno === E_DEPRECATED) {
            return false;
        }

        // Classify error type
        $type = 'php_warning';
        if ($errno === E_ERROR || $errno === E_CORE_ERROR || $errno === E_COMPILE_ERROR) {
            $type = 'php_fatal';
        }

        $this->_log_to_db(array(
            'error_type'  => $type,
            'message'     => $errstr,
            'file'        => $errfile,
            'line'        => $errline,
            'stack_trace' => $this->_get_trace(),
        ));

        // Return false so CI3's own error handler still runs
        return false;
    }

    /**
     * Handle uncaught exceptions.
     */
    public function handleException($exception)
    {
        $this->_log_to_db(array(
            'error_type'  => 'php_exception',
            'message'     => $exception->getMessage(),
            'file'        => $exception->getFile(),
            'line'        => $exception->getLine(),
            'stack_trace' => $exception->getTraceAsString(),
        ));

        // Re-throw for CI3's handler to display error page
        // In PHP 7+, we can't re-throw from exception handler, 
        // so just call CI's error display
        if (function_exists('_exception_handler')) {
            _exception_handler(E_ERROR, $exception->getMessage(), 
                              $exception->getFile(), $exception->getLine());
        }
    }

    /**
     * Handle fatal errors on shutdown.
     * This catches errors that set_error_handler can't (E_ERROR, E_PARSE, etc.)
     */
    public function handleShutdown()
    {
        $error = error_get_last();

        if ($error !== null && in_array($error['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR))) {
            $this->_log_to_db(array(
                'error_type'  => 'php_fatal',
                'message'     => $error['message'],
                'file'        => $error['file'],
                'line'        => $error['line'],
                'stack_trace' => 'Fatal error on shutdown — no stack trace available',
            ));
        }
    }

    /**
     * Log error to database using Error_monitor_model.
     * Wrapped in try-catch to NEVER cause secondary errors.
     *
     * @param array $data Error data
     */
    private function _log_to_db($data)
    {
        try {
            // Check if CI is available (may not be during early startup)
            $CI =& get_instance();
            if (!$CI || !isset($CI->db) || !is_object($CI->db)) {
                return;
            }

            // Add request context
            $data['url'] = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
            $data['ip_address'] = $CI->input->ip_address();
            $data['user_agent'] = isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 500) : '';

            // Get controller/method from router
            if (isset($CI->router)) {
                $data['controller'] = $CI->router->fetch_class();
                $data['method'] = $CI->router->fetch_method();
            }

            // Get logged-in user ID from session
            if (isset($CI->session)) {
                $data['user_id'] = $CI->session->userdata('login_user_id');
            }

            // Sanitize request data (remove passwords, tokens)
            $post_data = $CI->input->post();
            if ($post_data) {
                $sanitize_keys = array('password', 'pass', 'pwd', 'token', 'csrf', 'secret', 'key');
                foreach ($sanitize_keys as $key) {
                    if (isset($post_data[$key])) {
                        $post_data[$key] = '[REDACTED]';
                    }
                }
                $data['request_data'] = json_encode($post_data);
            }

            // Load model and log
            if (!isset($CI->error_monitor_model)) {
                $CI->load->model('error_monitor_model');
            }

            // Quick check: does the table exist? (cached after first check)
            static $table_exists = null;
            if ($table_exists === null) {
                $table_exists = $CI->error_monitor_model->table_exists();
            }

            if ($table_exists) {
                $CI->error_monitor_model->log_error($data);
            }

        } catch (Exception $e) {
            // Silently fail — error monitoring must NEVER cause errors itself
            // Optionally log to file as fallback
            @error_log('[ErrorMonitor] Failed to log: ' . $e->getMessage());
        }
    }

    /**
     * Get formatted stack trace.
     */
    private function _get_trace()
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
        $output = array();

        // Skip first 2 frames (this method + handleError)
        for ($i = 2; $i < count($trace); $i++) {
            $frame = $trace[$i];
            $file = isset($frame['file']) ? basename($frame['file']) : '[internal]';
            $line = isset($frame['line']) ? $frame['line'] : '?';
            $func = isset($frame['function']) ? $frame['function'] : '?';
            $class = isset($frame['class']) ? $frame['class'] . '::' : '';
            $output[] = "#{$i} {$file}:{$line} {$class}{$func}()";
        }

        return implode("\n", $output);
    }
}
