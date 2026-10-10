<?php
/**
 * Favicon Handler
 *
 * Dynamically serves or blocks favicon.ico based on client config flag.
 * Routed via .htaccess: RewriteRule ^favicon\.ico$ favicon_handler.php [L]
 *
 * When hide_company_logo = true  → returns 204 No Content (no favicon shown)
 * When hide_company_logo = false → serves the real favicon.ico file
 */

// Determine CLIENT_ID from environment or constant
$client_id = 'default';
if (defined('CLIENT_ID')) {
    $client_id = CLIENT_ID;
} elseif (getenv('CLIENT_ID')) {
    $client_id = getenv('CLIENT_ID');
} elseif (file_exists(__DIR__ . '/admin/application/config/constants.php')) {
    // Try to read CLIENT_ID from constants file
    $constants_content = file_get_contents(__DIR__ . '/admin/application/config/constants.php');
    if (preg_match("/define\s*\(\s*['\"]CLIENT_ID['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)/", $constants_content, $matches)) {
        $client_id = $matches[1];
    }
}

// Load client config JSON
$config_file = __DIR__ . '/config/clients/' . $client_id . '.json';
$hide_logo = false;

if (file_exists($config_file)) {
    $config = json_decode(file_get_contents($config_file), true);
    if (json_last_error() === JSON_ERROR_NONE && isset($config['flags']['hide_company_logo'])) {
        $hide_logo = (bool) $config['flags']['hide_company_logo'];
    }
}

if ($hide_logo) {
    // Serve a 1x1 transparent GIF — browser treats it as a valid favicon (invisible)
    // Using 204 causes browsers to fall back to the server's default favicon
    header('Content-Type: image/x-icon');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    // 1x1 transparent GIF (43 bytes)
    echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
    exit;
}

// Serve the real favicon.ico
$favicon_path = __DIR__ . '/favicon.ico';
if (file_exists($favicon_path)) {
    header('Content-Type: image/x-icon');
    header('Content-Length: ' . filesize($favicon_path));
    header('Cache-Control: public, max-age=86400');
    readfile($favicon_path);
} else {
    http_response_code(404);
}
exit;
