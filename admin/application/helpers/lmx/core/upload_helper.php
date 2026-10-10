<?php defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * ============================================================
 * LMX Upload Helper — Standardized File Uploads
 * ============================================================
 * Single Responsibility: Handle file/image uploads securely.
 *
 * @version  1.0.0
 * @created  2026-07-01
 * ============================================================
 */

/**
 * Handle a file upload securely.
 *
 * @param  object &$CI        CodeIgniter instance
 * @param  string $field      Form input field name
 * @param  string $path       Upload directory path (relative to FCPATH)
 * @param  string $allowed    Allowed types (e.g., 'gif|jpg|png|jpeg')
 * @param  int    $max_kb     Maximum file size in KB
 * @return array  ['success' => bool, 'file_name' => string, 'error' => string]
 */
function lmx_upload(&$CI, $field, $path, $allowed = 'gif|jpg|png|jpeg', $max_kb = 2048)
{
    // Ensure directory exists
    $upload_path = FCPATH . ltrim($path, '/');
    if (!is_dir($upload_path)) {
        if (!mkdir($upload_path, 0777, true)) {
            return ['success' => false, 'error' => 'Failed to create upload directory.'];
        }
    }

    $config = [
        'upload_path'   => $upload_path,
        'allowed_types' => $allowed,
        'max_size'      => $max_kb,
        'encrypt_name'  => true, // Always encrypt to avoid naming collisions and traversal
        'file_ext_tolower' => true
    ];

    $CI->load->library('upload', $config);
    // Re-initialize in case of multiple uploads in same request
    $CI->upload->initialize($config);

    if (!$CI->upload->do_upload($field)) {
        return [
            'success' => false,
            'error'   => strip_tags($CI->upload->display_errors())
        ];
    }

    $data = $CI->upload->data();
    return [
        'success'   => true,
        'file_name' => $data['file_name'],
        'error'     => ''
    ];
}
