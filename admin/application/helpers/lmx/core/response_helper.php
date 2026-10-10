<?php defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * ============================================================
 * LMX Response Helper — Standard JSON Responses
 * ============================================================
 * Single Responsibility: Format and output AJAX responses.
 * Includes backwards-compatible legacy format for unmigrated JS.
 *
 * @version  1.0.0
 * @created  2026-07-01
 * ============================================================
 */

// ─── New Standard Format ─────────────────────────────────────
// Use for new code or when JS handler is also updated.
// Format: {status: bool, message: "...", class: "success|danger", data: ...}

/**
 * Standard success response.
 */
function lmx_success($message, $data = null)
{
    $r = ['status' => true, 'message' => $message, 'class' => 'success', 'title' => 'Success'];
    if ($data !== null) $r['data'] = $data;
    echo json_encode($r);
}

/**
 * Standard error response.
 */
function lmx_error($message, $errors = [])
{
    $r = ['status' => false, 'message' => $message, 'class' => 'danger', 'title' => 'Error'];
    if (!empty($errors)) $r['errors'] = $errors;
    echo json_encode($r);
}

/**
 * Validation error response (multiple errors).
 */
function lmx_validation_error($errors)
{
    lmx_error(implode('. ', $errors), $errors);
}


// ─── Legacy Format ───────────────────────────────────────────
// Use when JS handler has NOT been updated yet.
// Matches the exact format existing catalog_master.js expects.
// Format: {message: "...", class: "success|danger", title: "..."}

/**
 * Legacy success response.
 */
function lmx_legacy_success($message, $title = '')
{
    echo json_encode(['message' => $message, 'class' => 'success', 'title' => $title]);
}

/**
 * Legacy error response.
 */
function lmx_legacy_error($message, $title = '')
{
    echo json_encode(['message' => $message, 'class' => 'danger', 'title' => $title]);
}


// ─── Redirect Responses ──────────────────────────────────────
// For delete/status_toggle operations that use server-side redirect.

/**
 * Set flashdata and redirect on success.
 */
function lmx_flash_success(&$CI, $message, $title, $redirect_to)
{
    $CI->session->set_flashdata('chit_alert', [
        'message' => $message, 'class' => 'success', 'title' => $title
    ]);
    redirect($redirect_to);
}

/**
 * Set flashdata and redirect on error.
 */
function lmx_flash_error(&$CI, $message, $title, $redirect_to)
{
    $CI->session->set_flashdata('chit_alert', [
        'message' => $message, 'class' => 'danger', 'title' => $title
    ]);
    redirect($redirect_to);
}


// ─── DataTable Responses ─────────────────────────────────────

/**
 * Standard list response for DataTables.
 */
function lmx_list_response($data, $access = [])
{
    echo json_encode(['responseData' => $data, 'access' => $access]);
}
