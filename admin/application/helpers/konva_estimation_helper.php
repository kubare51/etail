<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

// `use Dompdf\Dompdf;` is file-scoped in PHP — it does NOT make `new DOMPDF()` resolve
// application-wide just because the calling controller has this import. This file needs
// its own copy (mirrors the top of admin_ret_estimation.php / admin_ret_billing.php).
require_once(APPPATH . 'libraries/dompdf/autoload.inc.php');
use Dompdf\Dompdf;

// ═══════════════════════════════════════════════════════════════════════════
// Konva V2 Canvas rendering — Estimation module
// Thin, module-specific wrapper around the shared rendering engine in
// konva_receipt_helper.php (kept there since billing's own Konva tier depends
// on the same engine — find_konva_template(), render_konva_template(),
// konva_resolve_paper_dims_mm()).
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Render the active Estimation Konva Canvas template and stream it as a PDF.
 * Always exits the request — either after a successful PDF stream, or after
 * printing the "template not configured" page when no active Konva template
 * exists for category 16.
 *
 * @param array    $combined_data  Flat estimation bill_data (mapped + merged), same
 *                                 shape passed to print_template_model->render(16, ...)
 * @param int|null $branch_id
 */
function render_estimation_konva_pdf($combined_data, $branch_id = null)
{
    $CI =& get_instance();
    $CI->load->helper('konva_receipt');

    // Category-based resolution (template_category = 16, latest active row with saved
    // konva_json) — no fixed `code` group is enforced, so any active Konva-designed
    // Estimation template is picked up regardless of what its `code` field is named.
    $konva_tpl = find_konva_template(16, $branch_id);

    if (!$konva_tpl) {
        echo konva_template_not_configured_page('estimation', 'Estimation Copy');
        exit;
    }

    $rendered = render_konva_template($konva_tpl['id_template'], $combined_data);
    if ($rendered === false || trim(strip_tags($rendered)) === '') {
        echo konva_template_not_configured_page('estimation', 'Estimation Copy');
        exit;
    }

    // Stream as a PDF (like the HTML/mustache tier and the legacy view) instead of raw
    // HTML — the browser-native window.print() dialog the Konva HTML triggers is skipped
    // since DOMPDF doesn't execute <script>; paper size is resolved from the template row.
    $orientation = $konva_tpl['page_orientation'] ?? 'portrait';
    list($pw_mm, $ph_mm) = konva_resolve_paper_dims_mm($konva_tpl['paper_size'] ?? 'A4', $orientation);
    $mm_to_pt = 2.83464567;

    $CI->load->helper(array('dompdf', 'file'));
    $dompdf = new DOMPDF();
    $dompdf->load_html($rendered);
    $dompdf->set_paper(array(0, 0, $pw_mm * $mm_to_pt, $ph_mm * $mm_to_pt), $orientation);
    $dompdf->render();
    while (ob_get_level()) ob_end_clean();
    $dompdf->stream("estimation.pdf", array('Attachment' => 0));
    exit;
}
