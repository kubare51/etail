<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

// Text-metric constants, calibrated against Chrome's rendering of the print HTML
// (Arial, border-collapse tables). PHP estimates the height of every row it emits and
// then DECLARES that height on the <tr>, so the browser honours it as a minimum — the
// two engines only diverge when an estimate comes in UNDER the real content height.
// Keep these in sync with any change to the table cell CSS in _konva_render_table().
if (!defined('KONVA_LINE_HEIGHT_RATIO')) define('KONVA_LINE_HEIGHT_RATIO', 1.15); // Arial `line-height: normal`
if (!defined('KONVA_BOLD_SAFETY')) define('KONVA_BOLD_SAFETY', 1.05);            // header/total rows render bold

/**
 * Konva V2 Canvas Receipt Renderer
 * ─────────────────────────────────
 * Converts a saved Konva stage JSON into printable HTML,
 * substituting {{field}} placeholders with real bill data.
 *
 * Called when  ret_settings.template_based = 3
 */

/**
 * Evaluate a condition variable as truthy/falsy.
 * Falsy: undefined, null, empty string, '0', 0, false, '0.00'
 * Truthy: 1, >1, any non-empty string, non-zero number
 */
function _konva_is_truthy($value)
{
    if ($value === null || $value === '' || $value === false) return false;
    if ($value === 0 || $value === '0') return false;
    if ($value === '0.00') return false;
    return true;
}

/**
 * Evaluate a compound condition expression against bill data.
 * Supports:
 *   - Single variable:  "has_return_items"
 *   - OR expression:    "has_return_items||has_sales_items"  (true if ANY is truthy)
 *   - AND expression:   "has_return_items&&has_sales_items"  (true only if ALL are truthy)
 * Note: mixing || and && in one expression is not supported — use one operator type.
 * @return bool  true if the condition is met (element should show)
 */
function _konva_eval_condition($condExpr, $bill_data)
{
    $condExpr = trim($condExpr);
    if ($condExpr === '') return true; // no condition = always show

    // OR expression: var1||var2||var3 — each part may itself be an AND group
    // (a&&b||c evaluates with AND binding tighter, via recursion)
    if (strpos($condExpr, '||') !== false) {
        $parts = explode('||', $condExpr);
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') continue;
            if (_konva_eval_condition($part, $bill_data)) return true; // any truthy part = pass
        }
        return false; // none were truthy
    }

    // AND expression: var1&&var2&&var3
    if (strpos($condExpr, '&&') !== false) {
        $parts = explode('&&', $condExpr);
        foreach ($parts as $part) {
            $key = trim($part);
            if ($key === '') continue;
            $val = isset($bill_data[$key]) ? $bill_data[$key] : null;
            if (!_konva_is_truthy($val)) return false; // any falsy = fail
        }
        return true; // all were truthy
    }

    // Single variable (original behavior)
    $val = isset($bill_data[$condExpr]) ? $bill_data[$condExpr] : null;
    return _konva_is_truthy($val);
}

/**
 * Resolve/override conditionVar for chit adjustment vs pre-close elements on the canvas
 */
function _konva_get_node_condition($attrs)
{
    $condVar = isset($attrs['conditionVar']) ? $attrs['conditionVar'] : '';
    if ($condVar === 'has_chit_items') {
        $id = isset($attrs['id']) ? (string)$attrs['id'] : '';
        $text = isset($attrs['text']) ? (string)$attrs['text'] : '';
        $tableConfig = isset($attrs['tableConfig']) ? $attrs['tableConfig'] : '';
        $tableConfigStr = is_array($tableConfig) ? json_encode($tableConfig) : (string)$tableConfig;
        
        // If it's a pre-close element
        if (strpos($id, 'pre_close') !== false || strpos($id, 'preclose') !== false ||
            strpos(strtolower($text), 'pre-close') !== false || strpos(strtolower($text), 'preclose') !== false ||
            strpos($tableConfigStr, 'chit_pre_close') !== false) {
            return 'is_chit_preclose';
        }
        
        // If it's a chit adjustment element
        if (strpos($id, 'chit_adjustment') !== false || 
            strpos(strtolower($text), 'chit adjustment') !== false || 
            strpos($tableConfigStr, 'chit_adjustment') !== false) {
            return 'has_chit_adj';
        }
    }
    return $condVar;
}

/**
 * Width of a string in px, using Arial/Helvetica advance widths (units per 1000 em).
 *
 * A single "average char width" ratio is not good enough here: it under-measures
 * uppercase text badly (e.g. "CREDIT CARD RECEIVED" is really ~134px at 11px, but a
 * 0.52-em average says ~114px). That difference decides whether a cell wraps, and an
 * UNDER-estimate is the one error this renderer cannot absorb — it makes PHP reserve
 * less height than the browser uses, and trailing content lands inside the table.
 */
function _konva_text_width_px($text, $font_size_px, $bold = false)
{
    static $w = null;
    if ($w === null) {
        $w = [
            ' '=>278,'!'=>278,'"'=>355,'#'=>556,'$'=>556,'%'=>889,'&'=>667,'\''=>191,
            '('=>333,')'=>333,'*'=>389,'+'=>584,','=>278,'-'=>333,'.'=>278,'/'=>278,
            '0'=>556,'1'=>556,'2'=>556,'3'=>556,'4'=>556,'5'=>556,'6'=>556,'7'=>556,'8'=>556,'9'=>556,
            ':'=>278,';'=>278,'<'=>584,'='=>584,'>'=>584,'?'=>556,'@'=>1015,
            'A'=>667,'B'=>667,'C'=>722,'D'=>722,'E'=>667,'F'=>611,'G'=>778,'H'=>722,'I'=>278,
            'J'=>500,'K'=>667,'L'=>556,'M'=>833,'N'=>722,'O'=>778,'P'=>667,'Q'=>778,'R'=>722,
            'S'=>667,'T'=>611,'U'=>722,'V'=>667,'W'=>944,'X'=>667,'Y'=>667,'Z'=>611,
            '['=>278,'\\'=>278,']'=>278,'^'=>469,'_'=>556,'`'=>333,
            'a'=>556,'b'=>556,'c'=>500,'d'=>556,'e'=>556,'f'=>278,'g'=>556,'h'=>556,'i'=>222,
            'j'=>222,'k'=>500,'l'=>222,'m'=>833,'n'=>556,'o'=>556,'p'=>556,'q'=>556,'r'=>333,
            's'=>500,'t'=>278,'u'=>556,'v'=>500,'w'=>722,'x'=>500,'y'=>500,'z'=>500,
            '{'=>334,'|'=>260,'}'=>334,'~'=>584,
        ];
    }

    $units = 0;
    $len = strlen($text);
    for ($i = 0; $i < $len; $i++) {
        $ch = $text[$i];
        $units += isset($w[$ch]) ? $w[$ch] : 556; // unknown/multibyte byte → average
    }

    $px = ($units / 1000.0) * $font_size_px;
    return $bold ? ($px * KONVA_BOLD_SAFETY) : $px;
}

/**
 * Estimate how many lines a cell's text wraps to.
 *
 * Mirrors CSS `overflow-wrap: normal` (the default the table cells render under):
 * lines break at WHITESPACE ONLY. A single word wider than the column does NOT get
 * broken — it overflows the cell and still occupies exactly one line. Modelling it as
 * a hard break (as this used to) inflates a narrow column like "NET.WT" to six lines
 * and massively overstates the header height.
 */
function _konva_estimate_wrapped_lines($text, $width_px, $font_size_px, $bold = false)
{
    $text = trim(strip_tags((string)$text));
    if ($text === '') return 1;

    $width_px = max(1.0, (float)$width_px);
    $font_size_px = max(1.0, (float)$font_size_px);

    $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
    if (empty($words)) return 1;

    $space_w = _konva_text_width_px(' ', $font_size_px, $bold);
    $lines = 1;
    $line_w = 0.0;
    foreach ($words as $word) {
        $word_w = _konva_text_width_px($word, $font_size_px, $bold);
        $sep = ($line_w > 0) ? $space_w : 0.0;

        if ($line_w > 0 && ($line_w + $sep + $word_w) > $width_px) {
            $lines++;              // push the word to a new line
            $line_w = $word_w;     // an over-wide word just overflows; still one line
        } else {
            $line_w += $sep + $word_w;
        }
    }

    return max(1, $lines);
}

/**
 * Build table layout metrics. Row min height honors designer-adjusted group height.
 */
/**
 * Drop table columns whose per-column condition resolves falsy at print time.
 * Column config key: 'cond' — a flag variable name (e.g. "is_weight_scheme"),
 * evaluated by _konva_eval_condition() (supports var1&&var2 / var1||var2).
 * Columns without 'cond' always render, so pre-existing templates are unaffected.
 * Must be applied to $cfg BEFORE layout metrics / pagination / rendering so all
 * passes agree on the same column set.
 */
function _konva_filter_cond_columns($cfg, $bill_data)
{
    foreach (array('columns', 'subRowColumns') as $key) {
        if (empty($cfg[$key]) || !is_array($cfg[$key])) continue;
        $cfg[$key] = array_values(array_filter($cfg[$key], function ($c) use ($bill_data) {
            if (empty($c['cond'])) return true;
            return _konva_eval_condition(trim((string)$c['cond'], "{} \t"), $bill_data);
        }));
    }
    return $cfg;
}

function _konva_get_table_layout_metrics($attrs, $cfg, $px_per_mm)
{
    // Font fallbacks below MUST mirror _konva_render_table() exactly — any drift makes PHP
    // reserve a row height the browser does not honour.
    $fontSize = isset($cfg['bodySize']) ? (float)$cfg['bodySize'] : 11.0;
    $pad = isset($cfg['cellPadding']) ? (int)$cfg['cellPadding'] : 2;
    $baseRowH = $fontSize + $pad * 2 + 2;

    $hasSubRows = !empty($cfg['hasSubRows']);
    $subFontSize = isset($cfg['subBodySize']) ? (float)$cfg['subBodySize'] : $fontSize;
    $baseSubRowH = $hasSubRows ? ($subFontSize + $pad * 2 + 2) : 0;

    $hasHeader = ($cfg['showHeader'] ?? true) !== false;
    $hasFooter = !empty($cfg['showFooter']);
    $sampleRows = (isset($attrs['customType']) && $attrs['customType'] === 'static-table')
        ? (isset($cfg['recordSize']) ? max(1, (int)$cfg['recordSize']) : 3)
        : 1;

    $rowHMin = $baseRowH;
    $subRowHMin = $baseSubRowH;

    // If user manually resized the table group in designer, keep that span as minimum row height.
    // NOTE: the header/footer terms deliberately use $baseRowH, not the wrapped print heights.
    // This is the inverse of what the DESIGNER canvas draws (it lays the header out as a single
    // line), so it recovers the row height the user actually sized against. Feeding the taller
    // wrapped print header back in here would shrink every body row and silently restyle bills.
    $canvasGroupH = isset($attrs['height']) ? (float)$attrs['height'] : 0.0;
    if ($canvasGroupH > 0 && $sampleRows > 0) {
        $fixedParts = ($hasHeader ? $baseRowH : 0) + ($hasFooter ? $baseRowH : 0) + ($hasSubRows ? ($sampleRows * $baseSubRowH) : 0);
        $availableForMainRows = $canvasGroupH - $fixedParts;
        if ($availableForMainRows > 0) {
            $designerMainRowH = $availableForMainRows / $sampleRows;
            if ($designerMainRowH > $rowHMin) {
                $rowHMin = $designerMainRowH;
            }
        }
    }

    $cols = $cfg['columns'] ?? [];
    $cols = array_values(array_filter($cols, function($c) { return empty($c['hidden']); }));
    $subCols = [];
    if ($hasSubRows && !empty($cfg['subRowColumns'])) {
        $subCols = array_values(array_filter($cfg['subRowColumns'], function($c) { return empty($c['hidden']); }));
    }

    // Header labels wrap in narrow columns ("GRS.WT", "Description"...). Estimating the
    // header as a single row understates the table height on EVERY chunk, which in turn
    // understates the flow cursor and makes trailing content land inside the table.
    // These fallbacks MUST match _konva_render_table() exactly (headerSize/footerSize default
    // to 11, NOT to bodySize). When they drifted apart, PHP measured an 11px header as if it
    // were 10px, decided "CGST Rate" fit on one line when the browser wrapped it to two, and
    // under-reserved the header row by 9px.
    $headerFontSize = isset($cfg['headerSize']) ? (float)$cfg['headerSize'] : 11.0;
    $footerFontSize = isset($cfg['footerSize']) ? (float)$cfg['footerSize'] : 11.0;

    // Computed unconditionally: continuation chunks repeat the header row even when the
    // template hides it on the first chunk, so callers gate on their own show/hide flag.
    // Header cells render bold by default; the totals row is always bold.
    $headerHPx = _konva_estimate_static_row_height_px($cols, 'header', 'headerColspan', $headerFontSize, $pad, $rowHMin, true);
    $footerHPx = max($rowHMin, ($footerFontSize * KONVA_LINE_HEIGHT_RATIO) + $pad * 2 + 2);

    return [
        'bodyFontSize' => $fontSize,
        'subFontSize'  => $subFontSize,
        'headerFontSize' => $headerFontSize,
        'footerFontSize' => $footerFontSize,
        'cellPad'      => $pad,
        'rowHMinPx'    => $rowHMin,
        'subRowHMinPx' => $subRowHMin,
        'headerHPx'    => $headerHPx,
        'footerHPx'    => $footerHPx,
        'hasHeader'    => $hasHeader,
        'hasFooter'    => $hasFooter,
        'hasSubRows'   => $hasSubRows,
        'sampleRows'   => $sampleRows,
        'columns'      => $cols,
        'subRowColumns'=> $subCols,
        'rowHMinMm'    => $rowHMin / $px_per_mm,
        'subRowHMinMm' => $subRowHMin / $px_per_mm,
        'headerHMm'    => $headerHPx / $px_per_mm,
        'footerHMm'    => $footerHPx / $px_per_mm,
    ];
}

/**
 * Estimate the rendered height of a static (header/footer) table row, honouring
 * per-column colspan and text wrapping.
 */
function _konva_estimate_static_row_height_px($cols, $text_key, $colspan_key, $font_size_px, $cell_pad_px, $row_min_px, $bold = false)
{
    if (empty($cols)) return $row_min_px;

    $n = count($cols);
    $max_lines = 1;
    $skip = 0;
    foreach ($cols as $ci => $col) {
        if ($skip > 0) { $skip--; continue; }

        $cs = min((int)($col[$colspan_key] ?? 1), $n - $ci);
        if ($cs < 1) $cs = 1;
        if ($cs > 1) $skip = $cs - 1;

        $span_w = (float)($col['width'] ?? 100);
        for ($si = 1; $si < $cs; $si++) {
            $span_w += (float)($cols[$ci + $si]['width'] ?? 100);
        }

        $usable_w = max(8.0, $span_w - ($cell_pad_px * 2));
        $lines = _konva_estimate_wrapped_lines((string)($col[$text_key] ?? ''), $usable_w, $font_size_px, $bold);
        if ($lines > $max_lines) $max_lines = $lines;
    }

    $content_h = ($max_lines * ($font_size_px * KONVA_LINE_HEIGHT_RATIO)) + ($cell_pad_px * 2) + 2;
    return max($row_min_px, $content_h);
}

/**
 * THE shared table-height calculation.
 *
 * Every downstream position — the page a chunk lands on, and the flow cursor that
 * positions ALL content after the table (totals, payment lines, amount-in-words,
 * tax summary) — is derived from this one function. Anything that renders inside a
 * table chunk must be accounted for here, or trailing content will overlap the table.
 */
function _konva_estimate_chunk_height_px($chunk_items, $layout, $bill_data, $is_first_chunk, $is_last_chunk)
{
    // Continuation chunks always repeat the header row.
    $show_header = $is_first_chunk ? $layout['hasHeader'] : true;
    $h = $show_header ? $layout['headerHPx'] : 0;

    if (empty($chunk_items)) {
        $h += $layout['rowHMinPx']; // "No records" placeholder row
    } else {
        foreach ($chunk_items as $itm) {
            $h += _konva_estimate_table_row_height_px(
                $itm,
                $layout['columns'],
                $bill_data,
                $layout['bodyFontSize'],
                $layout['cellPad'],
                $layout['rowHMinPx']
            );
            if ($layout['hasSubRows'] && !empty($itm['_sub_rows'])) {
                foreach ($itm['_sub_rows'] as $subItem) {
                    $h += _konva_estimate_table_subrow_height_px(
                        $subItem,
                        $layout['columns'],
                        $layout['subRowColumns'],
                        $bill_data,
                        $layout['subFontSize'],
                        $layout['cellPad'],
                        $layout['subRowHMinPx']
                    );
                }
            }
        }
    }

    if ($is_last_chunk && $layout['hasFooter']) {
        $h += $layout['footerHPx'];
    }

    // border-collapse leaves a 1px outer border on the table box.
    return $h + 1;
}

/**
 * THE shared row-distribution calculation: split table rows into per-page chunks.
 *
 * Pass 1 (delta pre-calc) and Pass 2 (render) MUST agree on this exactly. They used to
 * carry separate copies of this loop, and when only one copy was corrected the two passes
 * disagreed about where the table ended — which is what put trailing content inside it.
 */
function _konva_build_table_chunks($tbl_items, $layout, $bill_data, $px_per_mm, $usable_h_page1, $usable_h_page2plus, $table_starts_on_page2)
{
    $header_first_mm = $layout['hasHeader'] ? $layout['headerHMm'] : 0;
    $header_cont_mm  = $layout['headerHMm']; // continuation chunks always repeat the header
    $footer_mm       = $layout['hasFooter'] ? $layout['footerHMm'] : 0;

    $chunks          = [];
    $current_chunk   = [];
    $current_chunk_h = $header_first_mm;
    $is_first_page   = !$table_starts_on_page2;
    $count           = count($tbl_items);

    foreach ($tbl_items as $idx => $itm) {
        $item_h = _konva_estimate_table_row_height_px(
            $itm,
            $layout['columns'],
            $bill_data,
            $layout['bodyFontSize'],
            $layout['cellPad'],
            $layout['rowHMinPx']
        ) / $px_per_mm;

        if ($layout['hasSubRows'] && !empty($itm['_sub_rows'])) {
            foreach ($itm['_sub_rows'] as $subItem) {
                $item_h += _konva_estimate_table_subrow_height_px(
                    $subItem,
                    $layout['columns'],
                    $layout['subRowColumns'],
                    $bill_data,
                    $layout['subFontSize'],
                    $layout['cellPad'],
                    $layout['subRowHMinPx']
                ) / $px_per_mm;
            }
        }

        $usable_h = $is_first_page ? $usable_h_page1 : $usable_h_page2plus;
        // Only the final row has to leave room for the totals row.
        $footer_need = ($idx === $count - 1) ? $footer_mm : 0;

        if (($current_chunk_h + $item_h + $footer_need) > $usable_h && !empty($current_chunk)) {
            $chunks[]        = $current_chunk;
            $current_chunk   = [$itm];
            $current_chunk_h = $header_cont_mm + $item_h;
            $is_first_page   = false;
        } else {
            $current_chunk[] = $itm;
            $current_chunk_h += $item_h;
        }
    }

    if (!empty($current_chunk)) $chunks[] = $current_chunk;
    if (empty($chunks))         $chunks[] = [];

    return $chunks;
}

/**
 * Estimate actual row height from content while respecting designer minimum row span.
 */
function _konva_estimate_table_row_height_px($item, $cols, $bill_data, $font_size_px, $cell_pad_px, $row_min_px)
{
    if (empty($cols)) return $row_min_px;

    $max_lines = 1;
    $skip = 0;
    foreach ($cols as $ci => $col) {
        if ($skip > 0) { $skip--; continue; }

        $bcs = min((int)($col['bodyColspan'] ?? 1), count($cols) - $ci);
        if ($bcs < 1) $bcs = 1;
        if ($bcs > 1) $skip = $bcs - 1;

        $span_w = (float)($col['width'] ?? 100);
        for ($si = 1; $si < $bcs; $si++) {
            $span_w += (float)($cols[$ci + $si]['width'] ?? 100);
        }

        $is_last_col = ($ci + $bcs >= count($cols));
        $field = $col['field'] ?? '';
        $col_decimal = isset($col['decimalMode']) && $col['decimalMode'] !== '' ? (int)$col['decimalMode'] : -1;
        $cellVal = _konva_resolve_field($field, $item, $bill_data, $is_last_col, $col_decimal);

        // noWrap columns always render one line (CSS forces it) — the estimate must
        // agree, or the reserved row height stops matching what DOMPDF actually draws.
        if (!empty($col['noWrap'])) continue;

        $usable_w = max(8.0, $span_w - ($cell_pad_px * 2));
        $lines = _konva_estimate_wrapped_lines($cellVal, $usable_w, $font_size_px);
        if ($lines > $max_lines) $max_lines = $lines;
    }

    $content_h = ($max_lines * ($font_size_px * KONVA_LINE_HEIGHT_RATIO)) + ($cell_pad_px * 2) + 2;
    return max($row_min_px, $content_h);
}

/**
 * Estimate sub-row height from sub-row content with minimum designer span.
 */
function _konva_estimate_table_subrow_height_px($sub_item, $cols, $sub_cols, $bill_data, $font_size_px, $cell_pad_px, $row_min_px)
{
    if (empty($cols) || empty($sub_cols)) return $row_min_px;

    $max_lines = 1;
    foreach ($cols as $ci => $col) {
        $subCol = isset($sub_cols[$ci]) ? $sub_cols[$ci] : null;
        if (!$subCol) continue;

        $field = $subCol['field'] ?? '';
        if ($field === '') continue;
        // noWrap sub-columns always render one line (CSS forces it) — see the matching
        // guard in _konva_estimate_table_row_height_px() for why this must agree.
        if (!empty($subCol['noWrap'])) continue;
        $col_decimal = isset($subCol['decimalMode']) && $subCol['decimalMode'] !== '' ? (int)$subCol['decimalMode'] : -1;
        $cellVal = _konva_resolve_field($field, $sub_item, $bill_data, false, $col_decimal);

        $col_w = (float)($col['width'] ?? 100);
        $usable_w = max(8.0, $col_w - ($cell_pad_px * 2));
        $lines = _konva_estimate_wrapped_lines($cellVal, $usable_w, $font_size_px);
        if ($lines > $max_lines) $max_lines = $lines;
    }

    $content_h = ($max_lines * ($font_size_px * KONVA_LINE_HEIGHT_RATIO)) + ($cell_pad_px * 2) + 2;
    return max($row_min_px, $content_h);
}

/**
 * Resolve a print_templates.paper_size + page_orientation pair to [width_mm, height_mm].
 * Shared by render_konva_template() (for the @page CSS / absolute positioning) and by
 * callers that need to feed DOMPDF an explicit custom paper size for the same template.
 *
 * @param  string $paper   e.g. 'A4', 'Thermal-58mm', '80mm'
 * @param  string $orient  'portrait' or 'landscape'
 * @return array           [width_mm, height_mm]
 */
function konva_resolve_paper_dims_mm($paper, $orient = 'portrait')
{
    $papers  = [
        'A4'=>[210,297], 'A3'=>[297,420], 'A5'=>[148,210],
        'Letter'=>[216,279], '48mm'=>[48,210], '53mm'=>[53,210], '58mm'=>[58,210], '80mm'=>[80,297],
    ];
    // The designer UI saves thermal sizes with a 'Thermal-' prefix (e.g. 'Thermal-58mm',
    // see konva-designer.js PAPER_SIZES) — normalize to the bare '58mm'/'80mm' keys above
    // so both naming styles resolve to the same dimensions instead of silently falling back to A4.
    $paper_lookup_key = (strpos($paper, 'Thermal-') === 0) ? substr($paper, strlen('Thermal-')) : $paper;
    $dims = isset($papers[$paper_lookup_key]) ? $papers[$paper_lookup_key] : $papers['A4'];
    $pw = $dims[0]; $ph = $dims[1];
    if ($orient === 'landscape') { $tmp=$pw; $pw=$ph; $ph=$tmp; }
    return [$pw, $ph];
}

/**
 * Main entry: render a Konva V2 template for a given bill
 *
 * @param  int    $template_id   print_templates.id_template
 * @param  array  $bill_data     Combined/mapped bill data (flat key=>value + 'items' array, etc.)
 * @return string|false          Full HTML page ready for echo / DomPDF, or false on failure
 */
function render_konva_template($template_id, $bill_data)
{
    $CI =& get_instance();
    $CI->load->model('print_template_model');

    $template = $CI->print_template_model->get_by_id($template_id);
    if (!$template) return false;

    // The Konva JSON lives inside gjs_data
    $gjs = json_decode($template['gjs_data'], true);
    if (!$gjs) return false;


    $konva_json_str = isset($gjs['konva_json']) ? $gjs['konva_json'] : null;
    if (!$konva_json_str) return false;

    $stage = is_string($konva_json_str) ? json_decode($konva_json_str, true) : $konva_json_str;
    if (!$stage) return false;

    // Paper dimensions
    $paper   = $template['paper_size'] ?? 'A4';
    $orient  = $template['page_orientation'] ?? 'portrait';
    list($pw, $ph) = konva_resolve_paper_dims_mm($paper, $orient);
    // Custom stationery (e.g. chit passbook): honor the row's explicit dimensions
    // (mm) when present. Guarded — named-size templates (billing) are untouched,
    // and 'Custom' rows without dimensions keep the historical A4 fallback.
    if ($paper === 'Custom'
        && (float)($template['paper_width'] ?? 0) > 0
        && (float)($template['paper_height'] ?? 0) > 0) {
        $pw = (float)$template['paper_width'];
        $ph = (float)$template['paper_height'];
        if ($orient === 'landscape') { $tmp=$pw; $pw=$ph; $ph=$tmp; }
    }

    // Conversion constant — MUST be defined before any px<->mm math
    $px_per_mm = 3.7795;

    // Extract print margins (from gjs_data or DB columns)
    $mt = 0; $mr = 0; $mb = 0; $ml = 0;
    if (isset($gjs['margins'])) {
        $mt = (float)($gjs['margins']['top'] ?? 0);
        $mr = (float)($gjs['margins']['right'] ?? 0);
        $mb = (float)($gjs['margins']['bottom'] ?? 0);
        $ml = (float)($gjs['margins']['left'] ?? 0);
    } elseif (isset($template['margin_top'])) {
        $mt = (float)$template['margin_top'];
        $mr = (float)$template['margin_right'];
        $mb = (float)$template['margin_bottom'];
        $ml = (float)$template['margin_left'];
    }

    // Extract zone heights (mm and px). If not set, default to 0 (no zones).
    $zone_header_mm = 0;
    $zone_footer_mm = 0;
    $zone_header_px = 0;
    $zone_footer_px = 0;
    if (isset($gjs['zones'])) {
        $zone_header_mm = (float)($gjs['zones']['header_height'] ?? 0);
        $zone_footer_mm = (float)($gjs['zones']['footer_height'] ?? 0);
        $zone_header_px = $zone_header_mm * $px_per_mm;
        $zone_footer_px = $zone_footer_mm * $px_per_mm;
    }

    // Extract zone repeat settings (default: every_page for backward compatibility)
    $zone_header_repeat = 'every_page';
    $zone_footer_repeat = 'every_page';
    if (isset($gjs['zones'])) {
        $zone_header_repeat = isset($gjs['zones']['header_repeat']) ? $gjs['zones']['header_repeat'] : 'every_page';
        $zone_footer_repeat = isset($gjs['zones']['footer_repeat']) ? $gjs['zones']['footer_repeat'] : 'every_page';
    }

    // THE page boundary for body content — the top of the declared footer zone.
    // Row chunking and element pagination MUST use the same value. They used to disagree by
    // the bottom margin (the chunker reserved mb + footer_zone, the paginator only footer_zone),
    // so the chunker packed for a shorter page than the paginator laid out against.
    $body_bottom_limit_mm = $ph - $mb - $zone_footer_mm - 2;

    // Walk the Konva tree -> produce HTML blocks
    // Margins are applied by offsetting every element's position (top/left) during rendering
    // This is necessary because position:absolute ignores CSS padding/margin on parent containers
    $elements_html = '';
    $all_elements = [];  // Collects elements with positions for page-break calculation
    $base_offset = ['top' => $mt, 'left' => $ml]; // mm offset for print margins

    // ── Layout tracing (OFF by default) ──
    // Append ?konva_pagedebug=1 to a print URL to get the pagination decisions (chunk heights,
    // flow cursor, page overflows) as an HTML comment at the end of the document. Nothing is
    // emitted and nothing is logged unless explicitly requested.
    $KDBG = !empty($GLOBALS['KONVA_PAGEDEBUG']) || !empty($_GET['konva_pagedebug']);
    $kdbg = [];

    // ── Resolve computed variables (formulas stored in DB) ──
    $computed_vars = $CI->print_template_model->get_computed_vars($template_id);
    if (!empty($computed_vars)) {
        // Build a scalar-only flat map for the expression parser.
        // $bill_data may contain arrays (items, billing, etc.) that would break str_replace in the tokenizer.
        $scalar_data = [];
        foreach ($bill_data as $k => $v) {
            if (is_scalar($v) || is_null($v)) {
                $scalar_data[$k] = (string)$v;
            }
        }

        // Multiple passes to allow computed vars that reference other computed vars
        for ($pass = 0; $pass < 3; $pass++) {
            foreach ($computed_vars as $cv) {
                $formula = $cv['formula'];
                // Strip {{}} wrappers from formula to get raw expression
                // e.g. "{{sub_total}} - {{discount}}" → "sub_total - discount"
                $expr = preg_replace('/\{\{(\w+)\}\}/', '$1', $formula);
                $result = _tpl_eval_expr($expr, $scalar_data);
                if ($result !== null) {
                    // Indian digit grouping, same as every mapped money variable — a computed
                    // total sitting next to mapped totals must not print 1,818,602.30 beside
                    // 18,60,602.30.
                    $formatted = function_exists('moneyFormatIndia')
                        ? moneyFormatIndia(number_format($result, 2, '.', ''))
                        : number_format($result, 2);
                    $bill_data[$cv['var_name']] = $formatted;
                    $scalar_data[$cv['var_name']] = (string)$result; // Feed forward raw for next pass math
                }
            }
        }
    }

    // Stash template ID in bill_data so table renderer can evaluate computed vars per-row
    $bill_data['_template_id'] = $template_id;

    if (isset($stage['children'])) {
        foreach ($stage['children'] as $layer_idx => $layer_data) {
            if (!isset($layer_data['children'])) continue;
            // Skip the guideLayer (2nd layer) — it only contains UI guides, not content
            if ($layer_idx > 0) continue;

            $nodes = $layer_data['children'];

            // ── PASS 0: Stage dimensions for footer zone classification ──
            // Footer zone height is strictly the declared value from the designer.
            // No dynamic expansion — body content near the footer must NOT be
            // pulled into the footer zone (e.g. amount_in_words).
            $stageH_px = isset($stage['attrs']['height']) ? (float)$stage['attrs']['height'] : ($ph * $px_per_mm);
            $mt_px = $mt * $px_per_mm;
            $mb_px = $mb * $px_per_mm;

            // ── PASS 1: Compute hidden zones by walking nodes sorted by Y ──
            // Strategy: sort all renderable nodes by Y position, then identify
            // contiguous blocks where ALL conditional elements are hidden.
            // Non-conditional elements sandwiched between hidden conditional
            // elements are absorbed into the zone.

            // First, build a sorted list with visibility info
            $node_info = []; // [{y, isConditional, isHidden, idx}]
            foreach ($nodes as $idx => $node) {
                $attrs = isset($node['attrs']) ? $node['attrs'] : [];
                $cls   = isset($node['className']) ? $node['className'] : '';

                // Skip non-renderable nodes
                if ($cls === 'Transformer') continue;
                $nm = isset($attrs['name']) ? $attrs['name'] : '';
                if ($nm === '_marginGuide' || $nm === '_pageBreak' || $nm === '_zoneGuide') continue;

                if ($cls === 'Line' && isset($attrs['dash']) && isset($attrs['stroke'])) {
                    if ($attrs['stroke'] === '#38bdf8' || $attrs['stroke'] === '#6366f1') continue;
                }
                if ($cls === 'Rect' && $nm === '_marginGuide') continue;

                // Get node Y position
                $nodeY = floatval($attrs['y'] ?? 0);
                // For lines, add the point offset
                if ($cls === 'Line') {
                    $pts = $attrs['points'] ?? [];
                    if (count($pts) >= 4) {
                        $nodeY += min($pts[1], $pts[3]);
                    }
                }

                $condVar = _konva_get_node_condition($attrs);
                $isConditional = !empty($condVar);
                $isHidden = false;
                if ($isConditional) {
                    $isHidden = !_konva_eval_condition($condVar, $bill_data);
                }

                // Calculate element height for proper zone sizing
                $elemH = 20; // default for Text-like elements
                if ($cls === 'Group') {
                    // Table group — compute designed height from tableConfig
                    $tCfgStr = isset($attrs['tableConfig']) ? $attrs['tableConfig'] : '';
                    if (!empty($tCfgStr)) {
                        $tCfg = is_string($tCfgStr) ? json_decode($tCfgStr, true) : $tCfgStr;
                        if ($tCfg) {
                            $fs = isset($tCfg['bodySize']) ? $tCfg['bodySize'] : (isset($tCfg['fontSize']) ? $tCfg['fontSize'] : 11);
                            $rH = $fs + 4 * 2 + 4; // pad=4, same as designer
                            $sR = 1; // data-table uses 1 sample row in designer
                            $ct = isset($attrs['customType']) ? $attrs['customType'] : '';
                            if ($ct === 'static-table') $sR = isset($tCfg['recordSize']) ? $tCfg['recordSize'] : 3;
                            $hdr = (isset($tCfg['showHeader']) && $tCfg['showHeader'] !== false) ? $rH : 0;
                            $ftr = (!empty($tCfg['showFooter'])) ? $rH : 0;
                            $elemH = $hdr + $sR * $rH + $ftr;
                        }
                    }
                } elseif ($cls === 'Text') {
                    $elemH = floatval($attrs['fontSize'] ?? 12) + 8;
                } elseif ($cls === 'Rect') {
                    $elemH = floatval($attrs['height'] ?? 20);
                } elseif ($cls === 'Image') {
                    $elemH = floatval($attrs['height'] ?? 30);
                } elseif ($cls === 'Line') {
                    $pts = $attrs['points'] ?? [];
                    if (count($pts) >= 4) {
                        $elemH = abs($pts[3] - $pts[1]) + 2;
                    } else {
                        $elemH = 2;
                    }
                }

                $node_info[] = [
                    'y' => $nodeY,
                    'cls' => $cls,
                    'height' => $elemH,
                    'isConditional' => $isConditional,
                    'isHidden' => $isHidden,
                    'isVisible' => $isConditional && !$isHidden,
                    'idx' => $idx
                ];
            }

            // Sort by Y position
            usort($node_info, function($a, $b) {
                if ($a['y'] == $b['y']) return 0;
                return ($a['y'] < $b['y']) ? -1 : 1;
            });

            // Walk sorted nodes to identify hidden zones
            // A hidden zone starts at the first hidden conditional node and extends
            // until we hit a non-conditional node that is NOT followed by another
            // hidden conditional node, or a visible conditional node.
            $merged_zones = [];
            $zone_start = null;
            $last_hidden_bottom = null; // Y + height of the last hidden element
            $pending_non_conditional = []; // non-conditional nodes between hidden conditionals

            for ($ni = 0; $ni < count($node_info); $ni++) {
                $info = $node_info[$ni];

                if ($info['isHidden']) {
                    // Hidden conditional element
                    if ($zone_start === null) {
                        $zone_start = $info['y'];
                    }
                    // Track the bottom edge (Y + height) of this hidden element
                    $bottom = $info['y'] + $info['height'];
                    if ($last_hidden_bottom === null || $bottom > $last_hidden_bottom) {
                        $last_hidden_bottom = $bottom;
                    }
                    // Absorb any pending non-conditional nodes
                    $pending_non_conditional = [];
                } elseif ($info['isVisible']) {
                    // Visible conditional element — ends any open zone
                    if ($zone_start !== null) {
                        // End the zone exactly at the next visible element's Y.
                        // This collapses the hidden element's space AND any whitespace 
                        // between it and the next visible element, effectively making
                        // the next element "occupy" the space of the removed one.
                        $zone_end = $info['y'];
                        if ($zone_end > $zone_start) {
                            $merged_zones[] = ['yStart' => $zone_start, 'yEnd' => $zone_end];
                        }
                        $zone_start = null;
                        $last_hidden_bottom = null;
                        $pending_non_conditional = [];
                    }
                } else {
                    // Non-conditional element
                    if ($zone_start !== null) {
                        // Only Line elements (separators) can be absorbed into the hidden zone.
                        // Text, Rect, Group = real content = zone boundary.
                        if ($info['cls'] === 'Line') {
                            // Check if there's another hidden conditional element after this line
                            $has_more_hidden = false;
                            for ($nj = $ni + 1; $nj < count($node_info); $nj++) {
                                if ($node_info[$nj]['isHidden']) {
                                    // Only absorb if next hidden is within reasonable distance
                                    if ($node_info[$nj]['y'] - $info['y'] < 200) {
                                        $has_more_hidden = true;
                                    }
                                    break;
                                }
                                if ($node_info[$nj]['isVisible']) {
                                    break;
                                }
                                // If next is non-conditional Text/Group, stop looking
                                if ($node_info[$nj]['cls'] !== 'Line') {
                                    break;
                                }
                            }

                            if ($has_more_hidden) {
                                // This separator line is sandwiched — absorb it
                                $pending_non_conditional[] = $info;
                            } else {
                                // Zone ends at this line
                                $merged_zones[] = ['yStart' => $zone_start, 'yEnd' => $info['y']];
                                $zone_start = null;
                                $last_hidden_bottom = null;
                                $pending_non_conditional = [];
                            }
                        } else {
                            // Non-Line content element (Text/Rect/Group) — end the zone here
                            // Cap at this element's Y so the zone doesn't extend into its space
                            $zone_end = $info['y'];
                            if ($zone_end > $zone_start) {
                                $merged_zones[] = ['yStart' => $zone_start, 'yEnd' => $zone_end];
                            }
                            $zone_start = null;
                            $last_hidden_bottom = null;
                            $pending_non_conditional = [];
                        }
                    }
                }
            }
            // Close any open zone at end of nodes
            if ($zone_start !== null && $last_hidden_bottom !== null) {
                $merged_zones[] = ['yStart' => $zone_start, 'yEnd' => $last_hidden_bottom];
            }

            // ── PASS 1.7: Pre-compute text wrap overflow shifts ──
            // Scan text nodes with wrap=word/char, estimate how many lines the
            // substituted text will occupy, and build wrap_deltas similar to
            // table_deltas. These are applied during Pass 2 rendering so that
            // elements below a wrapped text are shifted down server-side (PHP),
            // making positioning deterministic — no JS measurement needed.
            // NOTE: Must run BEFORE Pass 1.5 so table page-splitting accounts
            // for text wrap pushing tables further down the page.
            $wrap_deltas = []; // [{nodeY, designH_px, estimatedH_px, delta_px}]
            foreach ($nodes as $wn) {
                $wa = isset($wn['attrs']) ? $wn['attrs'] : [];
                $wcls = isset($wn['className']) ? $wn['className'] : '';
                if ($wcls !== 'Text') continue;
                $wwrap = $wa['wrap'] ?? 'none';
                if ($wwrap !== 'word' && $wwrap !== 'char') continue;
                if (!isset($wa['width']) || !isset($wa['height'])) continue;
                
                // Skip hidden conditional elements
                $wcv = _konva_get_node_condition($wa);
                if (!empty($wcv) && !_konva_eval_condition($wcv, $bill_data)) continue;
                
                $wText = _konva_substitute($wa['text'] ?? '', $bill_data);
                if (empty(trim($wText))) continue;
                
                $wFontSize = floatval($wa['fontSize'] ?? 14);
                $wLineH    = floatval($wa['lineHeight'] ?? 1);
                $wWidth    = floatval($wa['width']);
                $wDesignH  = floatval($wa['height']);
                $wNodeY    = floatval($wa['y'] ?? 0);
                
                // Estimate text width using average character width heuristic.
                // For most Latin/Arial fonts at a given size, avg char width ≈ fontSize × 0.55.
                // We use a slightly conservative factor to avoid underestimating.
                $avgCharW = $wFontSize * 0.52;
                $charsPerLine = max(1, floor($wWidth / $avgCharW));
                
                // Count lines by simulating word-wrap: split text into words,
                // accumulate widths per line, break when line exceeds $wWidth.
                $words = preg_split('/\s+/', trim($wText));
                $lines = 1;
                $lineLen = 0;
                $spaceW = $avgCharW * 0.6; // space character is narrower
                foreach ($words as $word) {
                    $wordW = mb_strlen($word) * $avgCharW;
                    if ($lineLen > 0 && ($lineLen + $spaceW + $wordW) > $wWidth) {
                        $lines++;
                        $lineLen = $wordW;
                    } else {
                        $lineLen += ($lineLen > 0 ? $spaceW : 0) + $wordW;
                    }
                }
                
                // Estimated height = lines × fontSize × lineHeight
                $estimatedH_px = $lines * $wFontSize * $wLineH;
                
                if ($estimatedH_px > $wDesignH + 2) { // overflow threshold: 2px
                    $wrap_deltas[] = [
                        'nodeY'       => $wNodeY,
                        'designH_px'  => $wDesignH,
                        'estimatedH_px' => $estimatedH_px,
                        'delta_px'    => $estimatedH_px - $wDesignH
                    ];
                }
            }
            // Sort by Y position (top-to-bottom)
            usort($wrap_deltas, function($a, $b) { return $a['nodeY'] <=> $b['nodeY']; });

            // ── PASS 1.5: Table Delta Pre-calculation with Page-Splitting ──
            $table_deltas = [];
            $table_meta = [];
            for ($ni = 0; $ni < count($node_info); $ni++) {
                $info = $node_info[$ni];
                if ($info['isHidden']) continue;
                $node_idx = $info['idx'];
                $node_data = $nodes[$node_idx];
                $node_cls = $node_data['className'] ?? '';
                $node_ct = $node_data['attrs']['customType'] ?? '';
                if ($node_cls !== 'Group' || ($node_ct !== 'data-table' && $node_ct !== 'static-table')) continue;

                $tblAttrs = $node_data['attrs'] ?? [];
                $tblCfgStr = $tblAttrs['tableConfig'] ?? '';
                if (empty($tblCfgStr)) continue;
                $tblCfg = is_string($tblCfgStr) ? json_decode($tblCfgStr, true) : $tblCfgStr;
                if (!$tblCfg) continue;
                $tblCfg = _konva_filter_cond_columns($tblCfg, $bill_data);

                $tblType = $tblCfg['type'] ?? 'items';
                $tblItems = _konva_get_table_items($tblType, $bill_data);

                $layout = _konva_get_table_layout_metrics($tblAttrs, $tblCfg, $px_per_mm);
                $rowH = $layout['rowHMinPx'];
                $subRowH = $layout['subRowHMinPx'];
                $hasHeader = $layout['hasHeader'];
                $hasFooter = $layout['hasFooter'];
                $hasSubRows = $layout['hasSubRows'];
                $rowH_mm = $layout['rowHMinMm'];
                $headerH_mm = $layout['headerHMm'];

                $tableY = $info['y'];

                // Calculate cumulative collapse zones shift up to this table
                $y_shift_px = 0;
                foreach ($merged_zones as $zone) {
                    if ($zone['yEnd'] <= $tableY) {
                        $y_shift_px += ($zone['yEnd'] - $zone['yStart']);
                    }
                }
                $y_shift_mm = $y_shift_px / $px_per_mm;

                // Calculate previous table deltas that shift this table down
                $y_table_delta_px = 0;
                foreach ($table_deltas as $td) {
                    $tableDesignBottom = $td['tableY'] + $td['designTimeH'];
                    if ($tableY > $tableDesignBottom) {
                        $y_table_delta_px += $td['delta'];
                    }
                }
                $y_table_delta_mm = $y_table_delta_px / $px_per_mm;

                // Calculate wrap overflow shifts that push this table down
                $y_wrap_shift_px_tbl = 0;
                foreach ($wrap_deltas as $wd) {
                    $wrapDesignBottom = $wd['nodeY'] + $wd['designH_px'];
                    if ($tableY > $wrapDesignBottom) {
                        $y_wrap_shift_px_tbl += $wd['delta_px'];
                    }
                }
                $y_wrap_shift_mm_tbl = $y_wrap_shift_px_tbl / $px_per_mm;

                $tableTopAbsMm = ($tableY / $px_per_mm) + $base_offset['top'] - $y_shift_mm + $y_table_delta_mm + $y_wrap_shift_mm_tbl;
                $usableH_page1 = $body_bottom_limit_mm - $tableTopAbsMm;
                $usableH_page2plus = $body_bottom_limit_mm - ($mt + $zone_header_mm);

                $min_required_h = $headerH_mm + 2 * $rowH_mm;
                $table_starts_on_page2 = false;
                if ($usableH_page1 < $min_required_h) {
                    $table_starts_on_page2 = true;
                }

                $chunks = _konva_build_table_chunks(
                    $tblItems, $layout, $bill_data, $px_per_mm,
                    $usableH_page1, $usableH_page2plus, $table_starts_on_page2
                );

                $page_content_top_mm = $mt + $zone_header_mm;
                $total_chunks = count($chunks);
                $current_page_idx = $table_starts_on_page2 ? 1 : 0;
                $running_gaps_px = 0;
                $prev_chunk_end_relative = null;

                foreach ($chunks as $chunk_idx => $chunk_items) {
                    if ($chunk_idx === 0) {
                        $chunkTopMm = $table_starts_on_page2 ? ($ph + $page_content_top_mm) : $tableTopAbsMm;
                        if ($table_starts_on_page2) {
                            $gap_mm = ($ph + $page_content_top_mm) - $tableTopAbsMm;
                            $running_gaps_px += $gap_mm * $px_per_mm;
                        }
                    } else {
                        $chunkTopMm = $current_page_idx * $ph + $page_content_top_mm;
                        if ($prev_chunk_end_relative !== null) {
                            $gap_mm = ($ph - $prev_chunk_end_relative) + $page_content_top_mm;
                            $running_gaps_px += $gap_mm * $px_per_mm;
                        }
                    }

                    $chunkH_px = _konva_estimate_chunk_height_px(
                        $chunk_items,
                        $layout,
                        $bill_data,
                        $chunk_idx === 0,
                        $chunk_idx === $total_chunks - 1
                    );
                    $chunkH_mm = $chunkH_px / $px_per_mm;

                    $prev_chunk_end_relative = ($chunk_idx === 0 && !$table_starts_on_page2)
                                             ? ($tableTopAbsMm - ($current_page_idx * $ph) + $chunkH_mm)
                                             : ($page_content_top_mm + $chunkH_mm);

                    $current_page_idx++;
                }

                // Calculate total actual height
                $totalActualH_px = 0;
                foreach ($chunks as $chunk_idx => $chunk_items) {
                    $totalActualH_px += _konva_estimate_chunk_height_px(
                        $chunk_items,
                        $layout,
                        $bill_data,
                        $chunk_idx === 0,
                        $chunk_idx === $total_chunks - 1
                    );
                }

                $designSampleRows = $layout['sampleRows'];
                $designTimeH = $layout['headerHPx']
                             + $designSampleRows * $rowH
                             + ($hasSubRows ? ($designSampleRows * $subRowH) : 0)
                             + $layout['footerHPx'];

                $totalDelta = $totalActualH_px - $designTimeH + $running_gaps_px;

                $table_deltas[] = [
                    'tableY'      => $tableY,
                    'designTimeH' => $designTimeH,
                    'actualH'     => $totalActualH_px + $running_gaps_px,
                    'delta'       => $totalDelta
                ];
                $table_meta[] = [
                    'tableY'  => $tableY,
                    'actualH' => $totalActualH_px + $running_gaps_px,
                    '_only_meta' => true
                ];
            }

            // ── PASS 2: Render each node at DESIGN position ──
            foreach ($nodes as $node) {
                $attrs = isset($node['attrs']) ? $node['attrs'] : [];
                $customType = isset($attrs['customType']) ? $attrs['customType'] : '';

                $cls = isset($node['className']) ? $node['className'] : '';
                $nodeY = floatval($attrs['y'] ?? 0);
                if ($cls === 'Line') {
                    $pts = $attrs['points'] ?? [];
                    if (count($pts) >= 4) {
                        $nodeY += min($pts[1], $pts[3]);
                    }
                }

                // Skip rendering if conditional and false
                $condVar = _konva_get_node_condition($attrs);
                if (!empty($condVar)) {
                    if (!_konva_eval_condition($condVar, $bill_data)) {
                        continue;
                    }
                } else {
                    // Skip unconditional elements inside hidden zones
                    foreach ($merged_zones as $zone) {
                        if ($nodeY >= $zone['yStart'] && $nodeY < $zone['yEnd']) {
                            continue 2;
                        }
                    }
                }

                // Calculate cumulative Y-shift from COLLAPSE zones only
                $y_shift_px = 0;
                foreach ($merged_zones as $zone) {
                    if ($zone['yEnd'] <= $nodeY) {
                        $y_shift_px += ($zone['yEnd'] - $zone['yStart']);
                    }
                }
                $y_shift_mm = $y_shift_px / $px_per_mm;
                
                // Calculate cumulative Y-shift from WRAP overflows (push DOWN)
                // Elements below a wrapped text's designed bottom get shifted down
                // by the estimated overflow delta.
                $y_wrap_shift_px = 0;
                foreach ($wrap_deltas as $wd) {
                    $wrapDesignBottom = $wd['nodeY'] + $wd['designH_px'];
                    if ($nodeY > $wrapDesignBottom) {
                        $y_wrap_shift_px += $wd['delta_px'];
                    }
                }
                $y_wrap_shift_mm = $y_wrap_shift_px / $px_per_mm;
                
                $node_offset = [
                    'top'  => $base_offset['top'] - $y_shift_mm + $y_wrap_shift_mm,
                    'left' => $base_offset['left']
                ];

                // If this is a table, run page-splitting logic
                if ($cls === 'Group' && ($customType === 'data-table' || $customType === 'static-table')) {
                    $tblAttrs = $node['attrs'] ?? [];
                    $tblCfgStr = $tblAttrs['tableConfig'] ?? '';
                    if (!empty($tblCfgStr)) {
                        $tblCfg = is_string($tblCfgStr) ? json_decode($tblCfgStr, true) : $tblCfgStr;
                        if ($tblCfg) {
                            $tblCfg = _konva_filter_cond_columns($tblCfg, $bill_data);
                            $tblType = $tblCfg['type'] ?? 'items';
                            $tblItems = _konva_get_table_items($tblType, $bill_data);

                            $layout = _konva_get_table_layout_metrics($tblAttrs, $tblCfg, $px_per_mm);
                            $rowH = $layout['rowHMinPx'];
                            $subRowH = $layout['subRowHMinPx'];
                            $hasHeader = $layout['hasHeader'];
                            $hasFooter = $layout['hasFooter'];
                            $hasSubRows = $layout['hasSubRows'];
                            $rowH_mm = $layout['rowHMinMm'];
                            $headerH_mm = $layout['headerHMm'];

                            // Compute tableTopAbsMm (including previous table deltas)
                            $y_table_delta_px = 0;
                            foreach ($table_deltas as $td) {
                                $tableDesignBottom = $td['tableY'] + $td['designTimeH'];
                                if ($nodeY > $tableDesignBottom) {
                                    $y_table_delta_px += $td['delta'];
                                }
                            }
                            $y_table_delta_mm = $y_table_delta_px / $px_per_mm;
                            
                            $tableTopAbsMm = ($nodeY / $px_per_mm) + $base_offset['top'] - $y_shift_mm + $y_table_delta_mm + $y_wrap_shift_mm;
                            $usableH_page1 = $body_bottom_limit_mm - $tableTopAbsMm;
                            $usableH_page2plus = $body_bottom_limit_mm - ($mt + $zone_header_mm);
                            
                            $min_required_h = $headerH_mm + 2 * $rowH_mm;
                            $table_starts_on_page2 = false;
                            if ($usableH_page1 < $min_required_h) {
                                $table_starts_on_page2 = true;
                            }
                            
                            $chunks = _konva_build_table_chunks(
                                $tblItems, $layout, $bill_data, $px_per_mm,
                                $usableH_page1, $usableH_page2plus, $table_starts_on_page2
                            );

                            if ($KDBG) {
                                $rowDbg = [];
                                foreach ($tblItems as $ri => $itm) {
                                    if ($ri > 2) break;
                                    $rowDbg[] = round(_konva_estimate_table_row_height_px($itm, $layout['columns'], $bill_data, $layout['bodyFontSize'], $layout['cellPad'], $layout['rowHMinPx']), 1);
                                }
                                $kdbg[] = ['stage'=>'LAYOUT_METRICS','type'=>($tblCfg['type']??''),'bodyFont'=>$layout['bodyFontSize'],'headerFont'=>$layout['headerFontSize'],'pad'=>$layout['cellPad'],'rowHMinPx'=>round($layout['rowHMinPx'],1),'headerHPx'=>round($layout['headerHPx'],1),'footerHPx'=>round($layout['footerHPx'],1),'canvasGroupH'=>($tblAttrs['height']??null),'sampleRows'=>$layout['sampleRows'],'firstRowsPx'=>$rowDbg,'usableP1'=>round($usableH_page1,2),'usableP2'=>round($usableH_page2plus,2),'chunkSizes'=>array_map('count',$chunks)];
                            }

                            $total_chunks = count($chunks);
                            $current_page_idx = $table_starts_on_page2 ? 1 : 0;
                            $prev_chunk_end_relative = null;
                            
                            foreach ($chunks as $chunk_idx => $chunk_items) {
                                if ($chunk_idx === 0) {
                                    $chunkTopMm = $table_starts_on_page2 ? ($ph + ($mt + $zone_header_mm)) : $tableTopAbsMm;
                                } else {
                                    $chunkTopMm = $current_page_idx * $ph + ($mt + $zone_header_mm);
                                }
                                
                                $showHeaderOverride = true;
                                if ($chunk_idx === 0) {
                                    $showHeaderOverride = $hasHeader;
                                }
                                $showFooterOverride = ($chunk_idx === $total_chunks - 1) ? $hasFooter : false;
                                
                                $chunk_offset = [
                                    'top'  => $chunkTopMm - ($attrs['y'] ?? 0) / $px_per_mm,
                                    'left' => $node_offset['left']
                                ];
                                
                                $chunkHtml = _konva_render_table($attrs, $bill_data, $px_per_mm, $chunk_offset, $chunk_items, $showHeaderOverride, $showFooterOverride);
                                
                                if ($chunk_idx > 0) {
                                    $chunkHtml = preg_replace(
                                        '/data-design-h="[\d.]+"/',
                                        'data-design-h="0"',
                                        $chunkHtml,
                                        1
                                    );
                                }
                                
                                $chunkH_px = _konva_estimate_chunk_height_px(
                                    $chunk_items,
                                    $layout,
                                    $bill_data,
                                    $chunk_idx === 0,
                                    $chunk_idx === $total_chunks - 1
                                );
                                $chunkH_mm = $chunkH_px / $px_per_mm;

                                // Tag chunk HTML with data-node-y and data-php-delta
                                // php-delta must reflect the table delta PHP already applied
                                // to tableTopAbsMm so JS only adds the correction (real - estimated)
                                $chunkHtml = preg_replace(
                                    '/^(<div\s)/',
                                    '<div data-node-y="' . round($nodeY, 2) . '" data-php-delta="' . round($y_table_delta_px, 2) . '" ',
                                    $chunkHtml,
                                    1
                                );

                                
                                $all_elements[] = [
                                    'html'                     => $chunkHtml,
                                    'topMm'                    => $chunkTopMm,
                                    'heightMm'                 => $chunkH_mm,
                                    'repeat'                   => false,
                                    'footerZone'               => false,
                                    'offsetFromCanvasBottomPx' => 0,
                                    'nodeY'                    => $nodeY,
                                    // Flow metadata consumed by PASS 3's shared cursor
                                    'isTableChunk'             => true,
                                    'chunkIdx'                 => $chunk_idx,
                                    'chunkCount'               => $total_chunks,
                                    'designHpx'                => (float)($attrs['height'] ?? 0),
                                ];
                                if ($KDBG) {
                                    $kdbg[] = ['stage'=>'PASS2_TABLE_CHUNK','tableType'=>($tblCfg['type']??''),'chunkIdx'=>$chunk_idx,'totalChunks'=>$total_chunks,'pageIdx'=>$current_page_idx,'nodeY_px'=>round($nodeY,1),'chunkTopMm'=>round($chunkTopMm,2),'chunkH_mm'=>round($chunkH_mm,2),'chunkBottomMm'=>round($chunkTopMm+$chunkH_mm,2)];
                                }
                                
                                $prev_chunk_end_relative = ($chunk_idx === 0 && !$table_starts_on_page2)
                                                         ? ($tableTopAbsMm - ($current_page_idx * $ph) + $chunkH_mm)
                                                         : ($base_offset['top'] + $chunkH_mm);
                                
                                $current_page_idx++;
                            }
                            
                            continue; // Skip the default node rendering block
                        }
                    }
                }

                $rendered_html = _konva_render_node($node, $bill_data, $px_per_mm, $node_offset);
                if (!empty($rendered_html)) {
                    // Compute approximate table delta for PAGE-BREAK estimation
                    $y_table_delta_px = 0;
                    foreach ($table_deltas as $td) {
                        $tableDesignBottom = $td['tableY'] + $td['designTimeH'];
                        if ($nodeY > $tableDesignBottom) {
                            $y_table_delta_px += $td['delta'];
                        }
                    }
                    $y_table_delta_mm = $y_table_delta_px / $px_per_mm;
                    
                    // effectiveTopMm includes table delta AND wrap overflow for page-break calc
                    $effectiveTopMm = ($nodeY / $px_per_mm) + $base_offset['top'] - $y_shift_mm + $y_table_delta_mm + $y_wrap_shift_mm;

                    // Inject the adjusted position into the element's CSS top
                    if ($y_table_delta_mm > 0.01 || $y_wrap_shift_mm > 0.01) {
                        $rendered_html = preg_replace(
                            '/top:\s*([\d.]+)mm/',
                            'top:' . round($effectiveTopMm, 2) . 'mm',
                            $rendered_html,
                            1
                        );
                    }

                    // For page-break estimation, use approx table height.
                    //
                    // Konva text nodes are auto-height and usually carry no `height` attr. The old
                    // blind 20px fallback gave every such node ~5.3mm of phantom height (they really
                    // render ~2.7mm, and empty spacer nodes render ~0). Summed over a totals block
                    // that lands near a page boundary, the phantom height overflows the limit and
                    // forces an extra, nearly-empty page. Derive the height from the content instead.
                    $nodeH_px = floatval($attrs['height'] ?? 0);
                    if ($nodeH_px <= 0) {
                        if ($cls === 'Text') {
                            $plain = trim(strip_tags($rendered_html));
                            // Spacer / empty conditional value — occupies no flow height.
                            $nodeH_px = ($plain === '') ? 0 : (floatval($attrs['fontSize'] ?? 12) * 1.2);
                        } elseif ($cls === 'Line') {
                            // Divider rules: a Line is only as tall as its stroke.
                            $nodeH_px = max(1.0, floatval($attrs['strokeWidth'] ?? 1));
                        } else {
                            $nodeH_px = 20; // unknown node with no declared height
                        }
                    }
                    if ($customType === 'data-table' || $customType === 'static-table') {
                        foreach ($table_meta as $tz) {
                            if (isset($tz['tableY']) && abs($tz['tableY'] - $nodeY) < 2) {
                                $nodeH_px = $tz['actualH'];
                                break;
                            }
                        }
                    }
                    // For wrapped text, use estimated height from Pass 1.7
                    $nodeWrap = $attrs['wrap'] ?? 'none';
                    if ($nodeWrap === 'word' || $nodeWrap === 'char') {
                        foreach ($wrap_deltas as $wd) {
                            if (abs($wd['nodeY'] - $nodeY) < 2) {
                                $nodeH_px = $wd['estimatedH_px'];
                                break;
                            }
                        }
                    }
                    $nodeH_mm = $nodeH_px / $px_per_mm;

                    // ── Zone classification ──
                    $isRepeat    = !empty($attrs['repeatEveryPage']);
                    $isFooterZone = false;
                    $offsetFromCanvasBottomPx = 0;

                    if ($zone_header_px > 0 || $zone_footer_px > 0) {
                        $stageH_px = isset($stage['attrs']['height']) ? (float)$stage['attrs']['height'] : ($ph * $px_per_mm);
                        // Stage-relative Y coordinates
                        $stageY = $nodeY + $base_offset['top'] * $px_per_mm;
                        
                        if (!$isRepeat && $zone_header_px > 0 && $stageY < ($zone_header_px + $mt_px)) {
                            $isRepeat = ($zone_header_repeat === 'every_page'); // header zone → repeat based on setting
                        } elseif ($zone_footer_px > 0 && $stageY >= ($stageH_px - $mb_px - $zone_footer_px)) {
                            $isFooterZone = true; // footer zone → pin to page bottom at margin line
                            // Absolute offset from the stage bottom
                            // Offset from the margin line (not page edge)
                            $offsetFromCanvasBottomPx = ($stageH_px - $mb_px) - $stageY;
                        }
                    }

                    // Tag the HTML with data-node-y for JS reflow, and data-php-delta
                    $rendered_html = preg_replace(
                        '/^(<div\s)/',
                        '<div data-node-y="' . round($nodeY, 2) . '" data-php-delta="' . round($y_table_delta_px, 2) . '" ' . ($isFooterZone ? 'data-footer-zone="1" ' : ''),
                        $rendered_html,
                        1
                    );

                    $all_elements[] = [
                        'html'                     => $rendered_html,
                        'topMm'                    => $effectiveTopMm,
                        'heightMm'                 => $nodeH_mm,
                        'repeat'                   => $isRepeat,
                        'footerZone'               => $isFooterZone,
                        'offsetFromCanvasBottomPx' => $isFooterZone ? $offsetFromCanvasBottomPx : 0,
                        'nodeY'                    => $nodeY,
                        'isTableChunk'             => false,
                        'chunkIdx'                 => 0,
                        'chunkCount'               => 1,
                        'designHpx'                => floatval($attrs['height'] ?? 0),
                    ];
                    if ($KDBG) {
                        $snip = trim(preg_replace('/\s+/', ' ', strip_tags($rendered_html)));
                        $kdbg[] = ['stage'=>'PASS2_NODE','nodeY_px'=>round($nodeY,1),'effectiveTopMm'=>round($effectiveTopMm,2),'heightMm'=>round($nodeH_mm,2),'bottomMm'=>round($effectiveTopMm+$nodeH_mm,2),'repeat'=>$isRepeat?1:0,'footerZone'=>$isFooterZone?1:0,'text'=>mb_substr($snip,0,45)];
                    }
                }
            }
        }
    }

    // ══════════════════════════════════════════════════════
    // Pass 3: Auto Page-Break & Repeat-Every-Page Assembly
    // ══════════════════════════════════════════════════════
    
    // DEBUG: Output reflow data as HTML comments when requested
    if (!empty($_GET['debug_reflow'])) {
        echo '<!-- REFLOW_DEBUG:' . json_encode([
            'table_deltas' => $table_deltas,
            'wrap_deltas'  => $wrap_deltas,
            'merged_zones' => $merged_zones,
            'elements_count' => count($all_elements)
        ]) . ' -->';
        echo '<!-- ELEMENT_DUMP:';
        foreach ($all_elements as $ei => $el) {
            $snippet = '';
            // Extract identifying text
            if (preg_match('/data-table-type="([^"]+)"/', $el['html'], $m)) {
                $snippet = 'TABLE:' . $m[1];
            } elseif (preg_match('/>([^<]{1,50})/', strip_tags($el['html'], '<div>'), $m)) {
                $snippet = trim(strip_tags($m[0]));
            }
            // Extract actual CSS top from HTML
            $cssTop = '?';
            if (preg_match('/top:\s*([\d.]+)mm/', $el['html'], $m)) {
                $cssTop = $m[1];
            }
            echo "\n  [$ei] nodeY=" . round($el['nodeY'], 1)
                . " topMm=" . round($el['topMm'], 2)
                . " hMm=" . round($el['heightMm'], 2)
                . " cssTop=" . $cssTop
                . " R=" . ($el['repeat'] ? 'Y' : 'N')
                . " F=" . ($el['footerZone'] ? 'Y' : 'N')
                . " => " . substr($snippet, 0, 60);
        }
        echo "\n -->";
    }
    
    $pageH_mm = $ph; // full page height for positioning

    // Sort elements by their effective top position
    usort($all_elements, function($a, $b) {
        return $a['topMm'] <=> $b['topMm'];
    });

    // Separate elements into 3 buckets:
    //   $repeat_elements   → header zone + explicit repeatEveryPage → appear on every page at designed Y
    //   $footer_elements   → footer zone → pinned to BOTTOM of LAST page only
    //   $normal_elements   → body content → paginated normally
    $repeat_elements = [];
    $footer_elements = [];
    $normal_elements = [];
    foreach ($all_elements as $el) {
        if (!empty($el['footerZone'])) {
            $footer_elements[] = $el;
        } elseif ($el['repeat']) {
            $repeat_elements[] = $el;
        } else {
            $normal_elements[] = $el;
        }
    }

    // Calculate the maximum bottom extent of repeat elements within a single page.
    // Body content on page 2+ must start AFTER this position to avoid overlapping
    // with repeated header elements, even if no header zone is declared.
    $repeat_max_bottom_mm = 0;
    foreach ($repeat_elements as $rEl) {
        $rTopOnPage = fmod($rEl['topMm'], $ph);
        $rBottom = $rTopOnPage + $rEl['heightMm'];
        if ($rBottom > $repeat_max_bottom_mm) {
            $repeat_max_bottom_mm = $rBottom;
        }
    }
    // Page 2+ content start: the greater of (margin + header zone) or (repeat elements bottom + gap)
    $page2_content_start_mm = max($mt + $zone_header_mm, $repeat_max_bottom_mm + 2);

    // ══════════════════════════════════════════════════════════════════════
    // PASS 3: Canvas-space normal flow with ONE shared cursor
    // ══════════════════════════════════════════════════════════════════════
    //
    // Everything after a table (sub-total, tax block, payment lines, amount-in-words)
    // is positioned from a single running cursor that is only ever advanced by the
    // *actual* rendered extent of the element before it. No element computes its own
    // Y independently, so a table that grows or splits cannot leave stale gaps behind.
    //
    // Spacing intent is read from the DESIGNER CANVAS (nodeY), not from `topMm`.
    // `topMm` is polluted by two independent accumulators (hidden-zone collapse and
    // table-growth delta) applied with different predicates, which can reorder
    // elements relative to how they were drawn. Canvas coordinates cannot reorder.
    //
    // Gap model:
    //   text  → next : top-to-top canvas distance   (anchor = nodeY)
    //   table → next : canvas distance below the table box, applied from the table's
    //                  true rendered bottom          (anchor = nodeY + designH)
    // In both cases the hidden-zone height lying between the two anchors is removed,
    // so collapsed conditional blocks close up without a global accumulator.

    // Canvas height (px) of the hidden zones lying strictly between two canvas Ys.
    $hidden_between_px = function ($fromY, $toY) use ($merged_zones) {
        if ($toY <= $fromY) return 0.0;
        $sum = 0.0;
        foreach ($merged_zones as $z) {
            $lo = max($fromY, (float)$z['yStart']);
            $hi = min($toY, (float)$z['yEnd']);
            if ($hi > $lo) $sum += ($hi - $lo);
        }
        return $sum;
    };

    // Order by how the template was drawn. Chunks of one table stay in chunk order.
    usort($normal_elements, function ($a, $b) {
        if (abs($a['nodeY'] - $b['nodeY']) > 0.01) {
            return $a['nodeY'] <=> $b['nodeY'];
        }
        return ($a['chunkIdx'] ?? 0) <=> ($b['chunkIdx'] ?? 0);
    });

    $pages       = [];
    $currentPage = 0;
    $bottomLimit = $body_bottom_limit_mm;
    $MIN_SEP_MM  = 0.5;   // never let two elements touch

    $pageStartFor = function ($p) use ($mt, $page2_content_start_mm) {
        return ($p === 0) ? $mt : $page2_content_start_mm;
    };

    $cursor        = $pageStartFor(0);  // lowest occupied Y on the current page
    $anchorCanvas  = null;              // canvas Y the next gap is measured FROM
    $anchorRel     = null;              // rendered Y that gap is applied FROM
    $lastRowCanvas = null;              // for side-by-side detection
    $lastRowRelTop = null;
    $pages[0]      = [];

    foreach ($normal_elements as $el) {
        $nodeY    = (float)$el['nodeY'];
        $elH      = max(0.0, (float)$el['heightMm']);
        $isChunk  = !empty($el['isTableChunk']);
        $isCont   = $isChunk && (int)$el['chunkIdx'] > 0;
        $isSameRow = false;

        // A continuation chunk is, by definition, the top of a fresh page.
        if ($isCont) {
            $currentPage++;
            if (!isset($pages[$currentPage])) $pages[$currentPage] = [];
            $cursor        = $pageStartFor($currentPage);
            $relativeTop   = $cursor;
            $lastRowCanvas = null;
            $lastRowRelTop = null;
        } else {
            // Same designed row as the previous element? Share its top (label/value pairs).
            $isSameRow = ($lastRowCanvas !== null && abs($nodeY - $lastRowCanvas) < 3);

            if ($isSameRow) {
                $relativeTop = $lastRowRelTop;
            } elseif ($anchorCanvas === null) {
                // First body element on the first page: keep its designed position.
                $designedTop = ($nodeY / $px_per_mm) + $mt
                             - ($hidden_between_px(0, $nodeY) / $px_per_mm);
                $relativeTop = max($pageStartFor($currentPage), $designedTop);
            } else {
                $gapPx = $nodeY - $anchorCanvas - $hidden_between_px($anchorCanvas, $nodeY);
                if ($gapPx < 0) $gapPx = 0;
                $designedTop = $anchorRel + ($gapPx / $px_per_mm);
                $relativeTop = max($designedTop, $cursor + $MIN_SEP_MM);
            }

            // Does not fit? Flow to the TOP of the next page — no inherited gap,
            // otherwise a stale designed offset wastes most of the new page.
            if (($relativeTop + $elH) > $bottomLimit && $relativeTop > $pageStartFor($currentPage)) {
                if ($KDBG) {
                    $snip = trim(preg_replace('/\s+/', ' ', strip_tags($el['html'])));
                    $kdbg[] = ['stage'=>'PASS3_OVERFLOW_TO_NEXT_PAGE','fromPage'=>$currentPage+1,'toPage'=>$currentPage+2,'triedTop'=>round($relativeTop,2),'wouldEnd'=>round($relativeTop+$elH,2),'bottomLimit'=>round($bottomLimit,2),'nodeY_px'=>round($nodeY,1),'text'=>mb_substr($snip,0,45)];
                }
                $currentPage++;
                if (!isset($pages[$currentPage])) $pages[$currentPage] = [];
                $cursor        = $pageStartFor($currentPage);
                $relativeTop   = $cursor;
                $lastRowCanvas = null;
                $lastRowRelTop = null;
            }
        }

        $elementBottom = $relativeTop + $elH;

        $newHtml = preg_replace(
            '/top:\s*-?[\d.]+mm/',
            'top:' . round($relativeTop, 2) . 'mm',
            $el['html'],
            1
        );
        if ($currentPage > 0) {
            $newHtml = preg_replace('/^(<div\s)/', '<div data-page="' . ($currentPage + 1) . '" ', $newHtml, 1);
        }
        $pages[$currentPage][] = $newHtml;

        if ($KDBG) {
            $snip = trim(preg_replace('/\s+/', ' ', strip_tags($el['html'])));
            $kdbg[] = ['stage'=>'PASS3_PLACED','page'=>$currentPage+1,'relTop'=>round($relativeTop,2),'bottom'=>round($elementBottom,2),'h'=>round($elH,2),'cursorBefore'=>round($cursor,2),'nodeY_px'=>round($nodeY,1),'chunk'=>$isChunk?(int)$el['chunkIdx']:'-','text'=>mb_substr($snip,0,45)];
        }

        // Advance the ONE cursor. Everything after this element flows from here.
        if ($elementBottom > $cursor) $cursor = $elementBottom;

        // A table anchors the next gap at its canvas bottom / its real rendered bottom.
        if ($isChunk) {
            $anchorCanvas  = $nodeY + (float)($el['designHpx'] ?? 0);
            $anchorRel     = $elementBottom;
            $lastRowCanvas = null;
            $lastRowRelTop = null;
        } elseif (!$isSameRow) {
            $anchorCanvas  = $nodeY;
            $anchorRel     = $relativeTop;
            $lastRowCanvas = $nodeY;
            $lastRowRelTop = $relativeTop;
        }
    }

    // Build final HTML with page breaks
    $totalPages = count($pages);
    
    $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Print</title>';
    $html .= '<style>';
    $html .= '@page { size: '.$pw.'mm '.$ph.'mm; margin: 0; }';
    $html .= '* { box-sizing: border-box; margin:0; padding:0; }';
    $html .= 'html,body { width:'.$pw.'mm; position:relative; font-family:Arial,sans-serif; }';
    $html .= '.kv-page { width:'.$pw.'mm; min-height:'.$ph.'mm; position:relative; overflow:visible; }';
    $html .= '@media print { html,body { width:'.$pw.'mm; margin:0; padding:0; } .kv-page { overflow:hidden; max-height:'.$ph.'mm; page-break-after:always; page-break-inside:avoid; } .kv-page:last-child { page-break-after:auto; } }';
    $html .= '</style></head><body>';

    for ($p = 0; $p < $totalPages; $p++) {
        $html .= '<div class="kv-page">';
        
        // Add repeat-every-page elements to every page.
        // Tagged data-repeat-zone so the JS overlap guard leaves them alone — they are
        // pinned at their designed Y and are not part of the body flow.
        if ($p > 0 && !empty($repeat_elements)) {
            foreach ($repeat_elements as $rEl) {
                // Clone the element with adjusted top for this page
                $cloneHtml = $rEl['html'];
                $newTop = $rEl['topMm']; // same relative position on each page (page div is position:relative)
                $cloneHtml = preg_replace(
                    '/top:\s*[\d.]+mm/',
                    'top:' . round($newTop, 2) . 'mm',
                    $cloneHtml,
                    1
                );
                $cloneHtml = preg_replace('/^(<div\s)/', '<div data-repeat-zone="1" ', $cloneHtml, 1);
                $html .= $cloneHtml;
            }
        } else if ($p === 0) {
            // First page: repeat elements are already included in their original position
            foreach ($repeat_elements as $rEl) {
                $html .= preg_replace('/^(<div\s)/', '<div data-repeat-zone="1" ', $rEl['html'], 1);
            }
        }
        
        // Add page-specific elements
        if (isset($pages[$p])) {
            foreach ($pages[$p] as $elHtml) {
                $html .= $elHtml;
            }
        }

        // ── Footer zone: pin based on footer_repeat setting ──
        // 'every_page' → footer on all pages; 'last_page_only' → footer on last page only
        $show_footer_on_this_page = false;
        if (!empty($footer_elements)) {
            if ($zone_footer_repeat === 'every_page') {
                $show_footer_on_this_page = true;
            } elseif ($p === $totalPages - 1) {
                $show_footer_on_this_page = true; // last page only
            }
        }
        if ($show_footer_on_this_page) {
            foreach ($footer_elements as $fEl) {
                // offsetFromCanvasBottomPx = distance from (stageH - margin_bottom) to the element's Y
                // This was computed accurately during zone classification (line 853)
                $offsetFromBottomMm = $fEl['offsetFromCanvasBottomPx'] / $px_per_mm;
                $pinnedTopMm = ($ph - $mb) - $offsetFromBottomMm;
                
                $fHtml = preg_replace(
                    '/top:\s*-?[\d.]+mm/',
                    'top:' . round($pinnedTopMm, 2) . 'mm',
                    $fEl['html'],
                    1
                );
                $html .= $fHtml;
            }
        }

        $html .= '</div>';
    }
    
    // ── JS Post-Render Overlap Guard ──
    // PHP now owns layout: PASS 3 flows every element from one cursor that is advanced by
    // the measured extent of the element before it. This pass does NOT re-derive positions
    // (the old canvas-delta correction did, and it double-counted PHP's flow — pushing the
    // sub-total ~250mm down, clean off the page). It only measures what the browser actually
    // produced and pushes content DOWN if a real height overran PHP's estimate, so residual
    // font/rounding drift can never render as overlapping text. It runs on EVERY page.
    $html .= '<script>';
    $html .= '(function(){';
    $html .= 'var pxPerMm=3.7795, MIN_SEP=0.5;';
    $html .= 'document.querySelectorAll(".kv-page").forEach(function(pg){';
    $html .= '  var els=[];';
    $html .= '  pg.querySelectorAll(":scope > div[data-node-y]").forEach(function(el){';
    $html .= '    if(el.dataset.footerZone)return;';
    $html .= '    if(el.dataset.repeatZone)return;';           // repeated header block is not in the flow
    $html .= '    var r=el.getBoundingClientRect();';
    $html .= '    if(r.height<=0)return;';
    $html .= '    els.push({el:el,top:parseFloat(el.style.top)||0,h:r.height/pxPerMm,l:r.left,r:r.right});';
    $html .= '  });';
    $html .= '  els.sort(function(a,b){return a.top-b.top;});';
    $html .= '  var shift=0,placed_rects=[],prevTop=null,prevPlaced=null;';
    $html .= '  els.forEach(function(it){';
    $html .= '    var placed;';
    $html .= '    if(prevTop!==null&&Math.abs(it.top-prevTop)<0.3){';
    $html .= '      placed=prevPlaced;';                       // same designed row (label/value pair)
    $html .= '    }else{';
    $html .= '      placed=it.top+shift;';
    $html .= '      var need=-1e9;';
    // Only elements that actually share horizontal space can collide.
    $html .= '      placed_rects.forEach(function(p){';
    $html .= '        if(Math.min(p.r,it.r)-Math.max(p.l,it.l)>1){';
    $html .= '          if(p.bottom+MIN_SEP>need)need=p.bottom+MIN_SEP;';
    $html .= '        }';
    $html .= '      });';
    $html .= '      if(placed<need){var extra=need-placed;shift+=extra;placed+=extra;}';
    $html .= '    }';
    $html .= '    if(Math.abs(placed-it.top)>0.01){it.el.style.top=placed.toFixed(2)+"mm";}';
    $html .= '    prevTop=it.top;prevPlaced=placed;';
    $html .= '    placed_rects.push({l:it.l,r:it.r,bottom:placed+it.h});';
    $html .= '  });';
    $html .= '});';

    // ── Pass 3: Text Wrap Reflow ──
    // When text elements have auto-wrap enabled, they can grow beyond their designed
    // ── Pass 3 (TEXT WRAP) — DISABLED: now handled server-side by PHP Pass 1.7 ──
    // PHP pre-computes text wrap overflow deltas and shifts elements during rendering,
    // making positioning deterministic. JS-side measurement was causing inconsistent
    // gaps between single-page and multi-page prints due to browser rendering timing.
    // The PHP-side estimation uses character-width heuristics which are consistent
    // across all bills regardless of content volume or page count.

    // ── Debug: Dump all element positions for visual inspection ──
    // Activated by ?debug_positions=1 in the URL
    $html .= 'if(window.location.search.indexOf("debug_positions")>-1){';
    $html .= '  var dbg=document.createElement("div");';
    $html .= '  dbg.style.cssText="position:fixed;top:0;right:0;width:450px;max-height:100vh;overflow:auto;background:#fff;border:2px solid red;z-index:99999;font:11px monospace;padding:8px;";';
    $html .= '  var txt="<b>ELEMENT POSITION DEBUG</b><br>";';
    // Dump all data-node-y elements
    $html .= '  document.querySelectorAll("div[data-node-y]").forEach(function(el){';
    $html .= '    var top=el.style.top;';
    $html .= '    var vis=(el.offsetWidth>0||el.offsetHeight>0)?"VIS":"HID";';
    $html .= '    var tbl=el.dataset.tableType||"";';
    $html .= '    var inner=el.innerText.trim().substring(0,40);';
    $html .= '    var color=(tbl==="payment_methods")?"red":"black";';
    $html .= '    txt+="<span style=\\"color:"+color+"\\">top="+top+" "+vis+" tbl="+tbl+" "+inner+"</span><br>";';
    $html .= '  });';
    // Also dump data-design-h elements (tables)
    $html .= '  document.querySelectorAll("div[data-design-h]").forEach(function(el){';
    $html .= '    if(el.dataset.nodeY)return;'; // skip if already in the above loop
    $html .= '    var top=el.style.top;';
    $html .= '    var vis=(el.offsetWidth>0||el.offsetHeight>0)?"VIS":"HID";';
    $html .= '    var tbl=el.dataset.tableType||"";';
    $html .= '    var inner=el.innerText.trim().substring(0,40);';
    $html .= '    var color=(tbl==="payment_methods")?"red":"black";';
    $html .= '    txt+="<span style=\\"color:"+color+"\\">top="+top+" "+vis+" DTBL="+tbl+" "+inner+"</span><br>";';
    $html .= '  });';
    $html .= '  dbg.innerHTML=txt;';
    $html .= '  document.body.appendChild(dbg);';
    // Don't print when debugging
    $html .= '} else {';

    // Auto-trigger print dialog after reflow completes
    $html .= 'setTimeout(function(){window.print();},150);';
    $html .= '}';

    $html .= '})();';
    $html .= '</script>';
    $html .= '</body></html>';

    if ($KDBG && !empty($kdbg)) {
        $dbg_json = json_encode($kdbg, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $html .= "\n<!-- KONVA_PAGEDEBUG_START\n" . $dbg_json . "\nKONVA_PAGEDEBUG_END -->\n";
    }

    return $html;
}

/**
 * Render a single Konva node to absolutely-positioned HTML
 * $offset = ['top' => mm, 'left' => mm] — print margin offset applied to coordinates
 */
function _konva_render_node($node, $bill_data, $px_per_mm, $offset = ['top'=>0,'left'=>0])
{
    $className = isset($node['className']) ? $node['className'] : '';
    $attrs     = isset($node['attrs']) ? $node['attrs'] : [];
    $customType = isset($attrs['customType']) ? $attrs['customType'] : '';
    $name = isset($attrs['name']) ? $attrs['name'] : '';

    // Skip Transformer nodes
    if ($className === 'Transformer') return '';

    // Skip margin guide lines and zone guide shapes
    if ($name === '_marginGuide' || $name === '_pageBreak' || $name === '_zoneGuide') return '';
    // Also skip any dashed guide-colored lines (old or new guide colors)
    if ($className === 'Line' && isset($attrs['dash']) && isset($attrs['stroke'])) {
        if ($attrs['stroke'] === '#38bdf8' || $attrs['stroke'] === '#6366f1') return '';
    }
    // Skip Rect overlays from margin or zone areas
    if ($className === 'Rect' && ($name === '_marginGuide' || $name === '_zoneGuide')) return '';


    // ── Conditional Visibility ──
    // Supports: single var, OR (var1||var2), AND (var1&&var2)
    // Elements without conditionVar always render.
    $condVar = _konva_get_node_condition($attrs);
    if (!empty($condVar)) {
        if (!_konva_eval_condition($condVar, $bill_data)) {
            return ''; // condition not met — skip this element
        }
    }

    $offT = $offset['top'];
    $offL = $offset['left'];

    // ── GROUP (could be a data-table) ──
    if ($className === 'Group') {
        if ($customType === 'data-table' || $customType === 'static-table') {
            return _konva_render_table($attrs, $bill_data, $px_per_mm, $offset);
        }
        // Generic group — render children
        $html = '';
        if (isset($node['children'])) {
            foreach ($node['children'] as $child) {
                $html .= _konva_render_node($child, $bill_data, $px_per_mm, $offset);
            }
        }
        return $html;
    }

    // ── SPACER (invisible positioning block) ──
    if ($customType === 'spacer') {
        $x = ($attrs['x'] ?? 0) / $px_per_mm + $offL;
        $y = ($attrs['y'] ?? 0) / $px_per_mm + $offT;
        $w = ($attrs['width'] ?? 100) / $px_per_mm;
        $h = ($attrs['height'] ?? 30) / $px_per_mm;
        return '<div style="position:absolute; left:'.round($x,2).'mm; top:'.round($y,2).'mm; '
            . 'width:'.round($w,2).'mm; height:'.round($h,2).'mm;"></div>';
    }

    // ── TEXT ──
    if ($className === 'Text') {
        $x  = ($attrs['x'] ?? 0) / $px_per_mm + $offL;
        $y  = ($attrs['y'] ?? 0) / $px_per_mm + $offT;
        $w  = isset($attrs['width']) ? ($attrs['width'] / $px_per_mm) : 'auto';
        $h  = isset($attrs['height']) ? ($attrs['height'] / $px_per_mm) : null;
        $text = $attrs['text'] ?? '';
        $fontSize   = $attrs['fontSize'] ?? 14;
        $fontFamily  = $attrs['fontFamily'] ?? 'Arial';
        $fontStyle   = $attrs['fontStyle'] ?? 'normal';
        $fill        = $attrs['fill'] ?? '#000';
        $align       = $attrs['align'] ?? 'left';
        $textDecoration = $attrs['textDecoration'] ?? '';
        $textCase       = $attrs['textCase'] ?? 'none';
        $lineHeight     = $attrs['lineHeight'] ?? 1;  // Konva default is 1
        $wrap           = $attrs['wrap'] ?? 'none';

        // Substitute variables — apply node-level decimal override if set
        $node_decimal = isset($attrs['decimalMode']) && $attrs['decimalMode'] !== '' ? (int)$attrs['decimalMode'] : -1;
        $text = _konva_substitute($text, $bill_data, false, $node_decimal);

        // Convert font-size from px (96dpi canvas) to pt for DomPDF accuracy
        // 1px at 96dpi = 0.75pt (72dpi print standard)
        $fontSizePt = round($fontSize * 0.75, 1);

        $wStyle = is_numeric($w) ? 'width:'.round($w,2).'mm;' : '';
        // Set height if available to prevent overflow into adjacent elements, but allow it to grow if wrapping is enabled
        if ($wrap === 'word' || $wrap === 'char') {
            $hStyle = ($h !== null) ? 'min-height:'.round($h,2).'mm; height:auto; overflow:visible;' : '';
        } else {
            $hStyle = ($h !== null) ? 'height:'.round($h,2).'mm; overflow:hidden;' : '';
        }
        $fw = (strpos($fontStyle, 'bold') !== false) ? 'font-weight:bold;' : '';
        $fi = (strpos($fontStyle, 'italic') !== false) ? 'font-style:italic;' : '';
        $td = ($textDecoration === 'underline') ? 'text-decoration:underline;' : '';
        $tc = ($textCase && $textCase !== 'none') ? 'text-transform:'.$textCase.';' : '';

        // Detect if this is a single-line text (no newlines and has a width)
        $hasNewlines = strpos($text, "\n") !== false;
        if ($wrap === 'word' || $wrap === 'char') {
            $ws = 'white-space:normal; word-wrap:break-word;';
        } else {
            $ws = (!$hasNewlines && is_numeric($w)) ? 'white-space:nowrap;' : '';
        }

        // Tag wrapped text elements with data-wrap and data-design-h so the JS reflow
        // can measure actual browser height and push elements below when text overflows.
        // NOTE: data-node-y is added separately by the Pass 2 tagging (line ~858) using
        // raw canvas px coordinates — do NOT duplicate it here.
        $wrapAttr = '';
        if ($wrap === 'word' || $wrap === 'char') {
            $designHMm = isset($attrs['height']) ? round(floatval($attrs['height']) / $px_per_mm, 2) : 0;
            $wrapAttr = ' data-wrap="1" data-design-h="' . $designHMm . '"';
        }

        return '<div' . $wrapAttr . ' style="position:absolute; left:'.round($x,2).'mm; top:'.round($y,2).'mm; '
            . $wStyle . $hStyle
            . 'font-size:'.$fontSizePt.'pt; font-family:'.$fontFamily.'; '
            . $fw . $fi . $td . $tc . $ws
            . 'color:'.$fill.'; text-align:'.$align.'; line-height:'.$lineHeight.'; overflow:visible;">'
            . nl2br(htmlspecialchars($text))
            . '</div>';
    }

    // ── RECT ──
    if ($className === 'Rect') {
        $x = ($attrs['x'] ?? 0) / $px_per_mm + $offL;
        $y = ($attrs['y'] ?? 0) / $px_per_mm + $offT;
        $w = ($attrs['width'] ?? 100) / $px_per_mm;
        $h = ($attrs['height'] ?? 100) / $px_per_mm;
        $fill   = $attrs['fill'] ?? 'transparent';
        $stroke = $attrs['stroke'] ?? '#000';
        $sw     = $attrs['strokeWidth'] ?? 1;

        return '<div style="position:absolute; left:'.round($x,2).'mm; top:'.round($y,2).'mm; '
            . 'width:'.round($w,2).'mm; height:'.round($h,2).'mm; '
            . 'background:'.$fill.'; border:'.$sw.'px solid '.$stroke.'; box-sizing:border-box;"></div>';
    }

    // ── LINE ──
    if ($className === 'Line') {
        $points = $attrs['points'] ?? [];
        if (count($points) >= 4) {
            // Line's x/y is its canvas position; points are relative offsets
            $nodeX = floatval($attrs['x'] ?? 0);
            $nodeY_line = floatval($attrs['y'] ?? 0);

            $x1 = ($nodeX + $points[0]) / $px_per_mm + $offL;
            $y1 = ($nodeY_line + $points[1]) / $px_per_mm + $offT;
            $x2 = ($nodeX + $points[2]) / $px_per_mm + $offL;
            $y2 = ($nodeY_line + $points[3]) / $px_per_mm + $offT;

            $stroke = $attrs['stroke'] ?? '#000';
            $sw     = $attrs['strokeWidth'] ?? 1;
            // Non-empty `dash` array (e.g. [4,4]) → dashed rule. Guide-colored dashed lines
            // are already filtered out above this function, so any dash here is real content.
            $lineStyle = !empty($attrs['dash']) ? 'dashed' : 'solid';

            // Determine line orientation and render appropriately
            $lx = min($x1, $x2);
            $ly = min($y1, $y2);
            $lw = abs($x2 - $x1);
            $lh = abs($y2 - $y1);

            if ($lh < 0.3) {
                // Horizontal line
                $lh = 0;
                return '<div style="position:absolute; left:'.round($lx,2).'mm; top:'.round($ly,2).'mm; '
                    . 'width:'.round(max($lw, 0.3),2).'mm; height:0; '
                    . 'border-top:'.$sw.'px '.$lineStyle.' '.$stroke.';"></div>';
            } elseif ($lw < 0.3) {
                // Vertical line
                $lw = 0;
                return '<div style="position:absolute; left:'.round($lx,2).'mm; top:'.round($ly,2).'mm; '
                    . 'width:0; height:'.round(max($lh, 0.3),2).'mm; '
                    . 'border-left:'.$sw.'px '.$lineStyle.' '.$stroke.';"></div>';
            } else {
                // Diagonal line — approximate with a rotated div
                $angle = atan2($y2 - $y1, $x2 - $x1) * 180 / M_PI;
                $length = sqrt($lw * $lw + $lh * $lh);
                return '<div style="position:absolute; left:'.round($x1,2).'mm; top:'.round($y1,2).'mm; '
                    . 'width:'.round($length,2).'mm; height:0; '
                    . 'border-top:'.$sw.'px '.$lineStyle.' '.$stroke.'; '
                    . 'transform-origin:0 0; transform:rotate('.round($angle,2).'deg);"></div>';
            }
        }
    }
    // ── IMAGE ──
    // Supports QR codes, company logos, and other image elements.
    // The image source is stored in imageSrc or dataKey attr, which can be:
    //   1. A variable name (e.g., "qr_code_base64") → resolved from $bill_data
    //   2. A direct URL or data URI
    if ($className === 'Image') {
        $x = ($attrs['x'] ?? 0) / $px_per_mm + $offL;
        $y = ($attrs['y'] ?? 0) / $px_per_mm + $offT;
        $w = ($attrs['width'] ?? 80) / $px_per_mm;
        $h = ($attrs['height'] ?? 80) / $px_per_mm;
        $customType = $attrs['customType'] ?? '';

        $src = '';

        // ── QR Code: generate dynamically using qrUrlPath ──
        if ($customType === 'qrcode') {
            $bill_id = $bill_data['bill_id'] ?? '';
            $qrUrlPath = $attrs['qrUrlPath'] ?? 'printbill';

            // Priority 1: E-Invoice QR (already base64-encoded by the e-invoice system)
            $is_eda = $bill_data['is_eda'] ?? 0;
            $irnno  = $bill_data['irnno'] ?? '';
            $bqr    = $bill_data['qr_code_image'] ?? '';
            if ($is_eda && !empty($irnno) && !empty($bqr)) {
                $src = 'data:image/png;base64,' . $bqr;
            } elseif ($bill_id) {
                // Priority 2: Generate bill QR with custom URL path
                $CI_qr = &get_instance();
                $CI_qr->load->library('phpqrcode/qrlib');
                $qr_dir = FCPATH . 'bill_qrcode';
                if (!is_dir($qr_dir)) mkdir($qr_dir, 0777, TRUE);

                $qr_content = base_url() . "index.php/admin_app_api/" . $qrUrlPath . "/" . $bill_id;
                $qr_file = $qr_dir . '/' . $bill_id . '_' . $qrUrlPath . '.png';
                QRcode::png($qr_content, $qr_file);

                if (file_exists($qr_file)) {
                    $src = 'data:image/png;base64,' . base64_encode(file_get_contents($qr_file));
                }
            }
        } else {
            // ── Regular Image: resolve imageSrc ──
            $src = $attrs['imageSrc'] ?? $attrs['dataKey'] ?? $attrs['src'] ?? '';

            // Three resolution modes:
            // 1. Variable name (no slashes, no colons) → look up in $bill_data
            // 2. Root-relative file path (has slashes, no colon) → convert to base64 via FCPATH
            // 3. Full URL or data URI (has colon) → use as-is
            if ($src && !preg_match('/[\/\\\\:]/', $src)) {
                $src = $bill_data[$src] ?? '';
            } elseif ($src && strpos($src, ':') === false) {
                $abs_path = FCPATH . ltrim($src, '/\\');
                if (file_exists($abs_path)) {
                    $mime = mime_content_type($abs_path);
                    $src = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($abs_path));
                } else {
                    $src = '';
                }
            }
        }

        if (empty($src)) return '';

        return '<div style="position:absolute; left:'.round($x,2).'mm; top:'.round($y,2).'mm; '
            . 'width:'.round($w,2).'mm; height:'.round($h,2).'mm;">'
            . '<img src="'.htmlspecialchars($src).'" style="width:100%; height:100%; object-fit:contain;" />'
            . '</div>';
    }

    return '';
}

/**
 * Render a data-table Group into an HTML <table>
 */
function _konva_render_table($attrs, $bill_data, $px_per_mm, $offset = ['top'=>0,'left'=>0], $items_override = null, $show_header_override = null, $show_footer_override = null)
{
    $cfgStr = isset($attrs['tableConfig']) ? $attrs['tableConfig'] : '';
    if (!$cfgStr) return '';

    $cfg = is_string($cfgStr) ? json_decode($cfgStr, true) : $cfgStr;
    if (!$cfg) return '';
    $cfg = _konva_filter_cond_columns($cfg, $bill_data);

    $cols  = $cfg['columns'] ?? [];
    $type  = $cfg['type'] ?? 'items';
    // Filter out hidden columns
    $cols = array_values(array_filter($cols, function($c) { return empty($c['hidden']); }));
    if (empty($cols)) return '';

    // Position (with margin offset)
    $x = ($attrs['x'] ?? 0) / $px_per_mm + $offset['left'];
    $y = ($attrs['y'] ?? 0) / $px_per_mm + $offset['top'];

    // Styling
    $bw = ($cfg['borderWidth'] ?? 1) . 'px';
    $headerBg    = $cfg['headerBg'] ?? '#fff';
    $headerColor = $cfg['headerColor'] ?? '#000';
    $headerFont  = $cfg['headerFont'] ?? 'Arial';
    $headerSize  = $cfg['headerSize'] ?? 11;
    $bodyFont    = $cfg['bodyFont'] ?? 'Arial';
    $bodySize    = $cfg['bodySize'] ?? 11;
    $bodyColor   = $cfg['bodyColor'] ?? '#333';
    $footerFont  = $cfg['footerFont'] ?? 'Arial';
    $footerSize  = $cfg['footerSize'] ?? 11;
    $cellPad     = isset($cfg['cellPadding']) ? intval($cfg['cellPadding']) : 2;
    $tblMargin   = isset($cfg['tableMargin']) ? intval($cfg['tableMargin']) : 0;

    // Border flags
    $showOuterBorder  = $cfg['showOuterBorder'] ?? true;
    $showVLines       = $cfg['showVLines'] ?? true;
    $showSideLines    = $cfg['showSideLines'] ?? true;
    $showHLinesHeader = $cfg['showHLinesHeader'] ?? true;
    $showHLinesBody   = $cfg['showHLinesBody'] ?? true;
    $showHLinesFooter = $cfg['showHLinesFooter'] ?? true;
    // CSS border-style ('solid' default — unchanged for existing templates). Estimation's
    // legacy print (est_print_2.php / estimation_2.css) uses 'dashed' rules throughout.
    $borderStyle      = $cfg['borderStyle'] ?? 'solid';

    // Calculate total width
    $totalW = 0;
    foreach ($cols as $c) $totalW += ($c['width'] ?? 100);
    $totalW_mm = $totalW / $px_per_mm;

    // Resolve item data based on table type (or use pre-sliced override for page splitting)
    $items = ($items_override !== null) ? $items_override : _konva_get_table_items($type, $bill_data);

    // ── Evaluate computed variables per-item row ──
    // Formulas like "mc / gross_wt" reference item-level fields, not bill scalars.
    // Inject the computed result into each item so _konva_resolve_field finds it.
    $CI =& get_instance();
    $tpl_id = isset($bill_data['_template_id']) ? $bill_data['_template_id'] : 0;
    if ($tpl_id > 0) {
        $cv_list = $CI->print_template_model->get_computed_vars($tpl_id);
        if (!empty($cv_list) && !empty($items)) {
            foreach ($items as &$itm) {
                // Build per-row scalar map: item fields + bill-level scalars
                $row_data = [];
                foreach ($itm as $rk => $rv) {
                    if (is_scalar($rv) || is_null($rv)) {
                        $row_data[$rk] = str_replace(',', '', (string)$rv);
                    }
                }
                foreach ($bill_data as $bk => $bv) {
                    if (!isset($row_data[$bk]) && (is_scalar($bv) || is_null($bv))) {
                        $row_data[$bk] = str_replace(',', '', (string)$bv);
                    }
                }
                // Evaluate each computed var in the row context
                foreach ($cv_list as $cv) {
                    $expr = preg_replace('/\{\{(\w+)\}\}/', '$1', $cv['formula']);
                    $result = _tpl_eval_expr($expr, $row_data);
                    if ($result !== null) {
                        $itm[$cv['var_name']] = number_format((float)$result, 2);
                        $row_data[$cv['var_name']] = (string)$result;
                    }
                }
            }
            unset($itm); // break reference
        }
    }

    $layout = _konva_get_table_layout_metrics($attrs, $cfg, $px_per_mm);
    $hasHeader = ($show_header_override !== null) ? (bool)$show_header_override : (($cfg['showHeader'] ?? true) !== false);
    $hasFooter = ($show_footer_override !== null) ? (bool)$show_footer_override : !empty($cfg['showFooter']);
    // Header condition (tableConfig.headerCond, e.g. passbook 'show_headers'):
    // when set and falsy, the header row keeps its height — rows below must stay
    // on their ruled lines on pre-printed stationery — but prints no ink
    // (blank labels, no background, no borders).
    $blankHeader = false;
    if (!empty($cfg['headerCond'])) {
        $blankHeader = !_konva_eval_condition(trim((string)$cfg['headerCond'], "{} \t"), $bill_data);
    }
    $rowMinPx = $layout['rowHMinPx'];
    $subRowMinPx = $layout['subRowHMinPx'];
    $designSampleRows = $layout['sampleRows'];
    // Declared on the <tr> so the browser reproduces exactly the height the pagination
    // reserved for these rows. Same source as _konva_estimate_chunk_height_px().
    $headerHPx = $layout['headerHPx'];
    $footerHPx = $layout['footerHPx'];
    $designH = ($hasHeader ? $headerHPx : 0)
        + ($designSampleRows * $rowMinPx)
        + ($layout['hasSubRows'] ? ($designSampleRows * $subRowMinPx) : 0)
        + ($hasFooter ? $footerHPx : 0);

    // Suppress conditional tables when no data exists, but render a hidden container
    // so the JS reflow can measure it as 0px and correctly apply collapse shifts.
    if ($type === 'tax_detail_breakdown' && empty($items)) {
        return '<div data-design-h="'.round($designH, 2).'" data-table-type="'.$type.'" data-node-y="'.round($attrs['y'] ?? 0, 2).'" style="position:absolute; left:'.round($x,2).'mm; top:'.round($y,2).'mm; width:'.round($totalW_mm,2).'mm; display:none;">'
            . '<table style="display:none;"></table>'
            . '</div>';
    }

    $conditional_types = [
        'items', 'sales', 'old_gold', 'purchase', 'return', 'exchange', 'stone_details', 'karigar',
        'advance_amount', 'order_advance', 'previous_advance', 'chit_adjustment', 'receipt_adjustment',
        'repair_order', 'credit_collection', 'chit_pre_close', 'payment_methods', 'payment_breakdown',
        'order_delivery'
    ];
    if (in_array($type, $conditional_types) && empty($items)) {
        return '<div data-design-h="'.round($designH, 2).'" data-table-type="'.$type.'" data-node-y="'.round($attrs['y'] ?? 0, 2).'" style="position:absolute; left:'.round($x,2).'mm; top:'.round($y,2).'mm; width:'.round($totalW_mm,2).'mm; display:none;">'
            . '<table style="display:none;"></table>'
            . '</div>';
    }

    // Build border style helpers
    $borderNone = 'border:none;';
    $borderV = $showVLines ? 'border-left:'.$bw.' '.$borderStyle.' #000; border-right:'.$bw.' '.$borderStyle.' #000;' : '';
    $cellBorderV = $showVLines ? 'border-left:'.$bw.' '.$borderStyle.' #000;' : '';
    $outerBorder = $showOuterBorder ? 'border:'.$bw.' '.$borderStyle.' #000;' : '';

    // Start HTML (apply tableMargin if set)
    $marginStyle = $tblMargin > 0 ? 'margin:'.$tblMargin.'px;' : '';
    $designH = ($hasHeader ? $rowMinPx : 0)
        + ($designSampleRows * $rowMinPx)
        + ($layout['hasSubRows'] ? ($designSampleRows * $subRowMinPx) : 0)
        + ($hasFooter ? $rowMinPx : 0);
    // Use canvas group height — matches what user designed around
    $canvasGroupH = floatval($attrs['height'] ?? 0);
    if ($canvasGroupH > 0) {
        $designH = $canvasGroupH;
    }

    $html = '<div data-design-h="'.round($designH, 2).'" data-table-type="'.$type.'" style="position:absolute; left:'.round($x,2).'mm; top:'.round($y,2).'mm; width:'.round($totalW_mm,2).'mm; '.$marginStyle.'">';
    $html .= '<table style="width:100%; border-collapse:collapse; table-layout:fixed; '.($showOuterBorder ? 'border:'.$bw.' '.$borderStyle.' #000;' : '').'">';

    // Emit <colgroup> so column widths are enforced even when header is hidden
    $html .= '<colgroup>';
    foreach ($cols as $col) {
        $wPx = $col['width'] ?? 100;
        $html .= '<col style="width:'.$wPx.'px;">';
    }
    $html .= '</colgroup>';


    // ── HEADER ── (default to true if not explicitly false; suppressed on continuation pages)
    $showHeader = $hasHeader;
    if ($showHeader !== false) {
        $html .= '<thead><tr style="background:'.($blankHeader ? 'transparent' : $headerBg).'; color:'.$headerColor.'; height:'.round($headerHPx,2).'px;">';
        $skipH = 0;
        foreach ($cols as $i => $col) {
            if ($skipH > 0) { $skipH--; continue; }
            $align = $col['headerAlign'] ?? 'center';
            $wPx   = $col['width'] ?? 100;
            $hcs   = min(intval($col['headerColspan'] ?? 1), count($cols) - $i);
            if ($hcs < 1) $hcs = 1;

            // Accumulate width of spanned columns
            $spanW = $wPx;
            for ($si = 1; $si < $hcs; $si++) {
                $spanW += ($cols[$i + $si]['width'] ?? 100);
            }
            if ($hcs > 1) $skipH = $hcs - 1;

            $bStyle = '';
            if (!$blankHeader) {
                if ($showVLines && $i > 0) $bStyle .= 'border-left:'.$bw.' '.$borderStyle.' #000;';
                if ($showHLinesHeader) $bStyle .= 'border-top:'.$bw.' '.$borderStyle.' #000; border-bottom:'.$bw.' '.$borderStyle.' #000;';
                if (!$showSideLines && ($i === 0)) $bStyle .= 'border-left:none;';
                if (!$showSideLines && ($i + $hcs >= count($cols))) $bStyle .= 'border-right:none;';
            }

            // Per-column B/I/U styles for header (bold defaults to true — th is inherently bold)
            $hBiuStyle = '';
            if (($col['headerBold'] ?? true)) $hBiuStyle .= 'font-weight:bold;';
            if (!empty($col['headerItalic'])) $hBiuStyle .= 'font-style:italic;';
            if (!empty($col['headerUnderline'])) $hBiuStyle .= 'text-decoration:underline;';

            $csAttr = $hcs > 1 ? ' colspan="'.$hcs.'"' : '';
            $html .= '<th'.$csAttr.' style="padding:'.$cellPad.'px; min-height:'.round($headerHPx,2).'px; text-align:'.$align.'; '
                . 'font-family:'.$headerFont.'; font-size:'.$headerSize.'px; '
                . 'width:'.$spanW.'px; '.$bStyle . $hBiuStyle . '">'
                . ($blankHeader ? '' : htmlspecialchars($col['header'] ?? ''))
                . '</th>';
        }
        $html .= '</tr></thead>';
    }

    // ── BODY ──
    $hasSubRows     = $layout['hasSubRows'];
    $subRowCols     = [];
    $subBodyFont    = $bodyFont;
    $subBodySize    = $bodySize;
    $subBodyColor   = $cfg['subBodyColor'] ?? $bodyColor;
    if ($hasSubRows && !empty($cfg['subRowColumns'])) {
        $subRowCols = array_values(array_filter($cfg['subRowColumns'], function($c) { return empty($c['hidden']); }));
        $subBodyFont  = $cfg['subBodyFont'] ?? $bodyFont;
        $subBodySize  = $cfg['subBodySize'] ?? $bodySize;
        $subBodyColor = $cfg['subBodyColor'] ?? $bodyColor;
    }

    $html .= '<tbody>';
    // ── Exchange reference row ──
    if (in_array($type, ['exchange', 'return']) && !empty($bill_data['exchange_ref_bill_no'])) {
        $html .= '<tr style="font-weight:bold; text-transform:uppercase;">'
            . '<td colspan="'.count($cols).'" style="padding:'.$cellPad.'px; font-family:'.$bodyFont.'; font-size:'.$bodySize.'px;">'
            . 'Exchange (Refer sale bill no : ' . htmlspecialchars($bill_data['exchange_ref_bill_no']) . ')'
            . '</td></tr>';
    }
    if (empty($items)) {
        $html .= '<tr style="height:'.round($rowMinPx,2).'px;"><td colspan="'.count($cols).'" style="padding:'.$cellPad.'px; min-height:'.round($rowMinPx,2).'px; text-align:center; color:#999;">No records</td></tr>';
    } else {
        foreach ($items as $rowIdx => $item) {
            $mainRowH = _konva_estimate_table_row_height_px(
                $item,
                $cols,
                $bill_data,
                $layout['bodyFontSize'],
                $layout['cellPad'],
                $rowMinPx
            );
            // ── Main item row ──
            $html .= '<tr style="height:'.round($mainRowH,2).'px;">';
            $skipB = 0;
            foreach ($cols as $ci => $col) {
                if ($skipB > 0) { $skipB--; continue; }
                $field = $col['field'] ?? '';
                $align = $col['align'] ?? 'left';
                $bcs   = min(intval($col['bodyColspan'] ?? 1), count($cols) - $ci);
                if ($bcs < 1) $bcs = 1;

                $is_last_col = ($ci + $bcs >= count($cols));
                // Parse column-level decimal override: '' → -1 (auto), '0'/'1'/'2'/'3' → integer
                $col_decimal = isset($col['decimalMode']) && $col['decimalMode'] !== '' ? (int)$col['decimalMode'] : -1;
                $cellVal = _konva_resolve_field($field, $item, $bill_data, $is_last_col, $col_decimal);

                if ($bcs > 1) $skipB = $bcs - 1;

                $bStyle = '';
                if ($showVLines && $ci > 0) $bStyle .= 'border-left:'.$bw.' '.$borderStyle.' #000;';
                if ($showHLinesBody && $rowIdx < count($items)-1) $bStyle .= 'border-bottom:1px '.$borderStyle.' #ccc;';
                if (!$showSideLines && ($ci === 0)) $bStyle .= 'border-left:none;';
                if (!$showSideLines && ($ci + $bcs >= count($cols))) $bStyle .= 'border-right:none;';

                // Per-column B/I/U styles for data
                $dBiuStyle = '';
                if (!empty($col['bold'])) $dBiuStyle .= 'font-weight:bold;';
                if (!empty($col['italic'])) $dBiuStyle .= 'font-style:italic;';
                if (!empty($col['underline'])) $dBiuStyle .= 'text-decoration:underline;';
                // Opt-in: force single-line rendering regardless of column width. Row-height
                // estimation and DOMPDF's actual column-width layout can disagree on wrapping,
                // which is unsafe for callers already truncating text to a fixed length
                // expecting one line (row height becomes unpredictable otherwise).
                if (!empty($col['noWrap'])) $dBiuStyle .= 'white-space:nowrap;overflow:hidden;';

                $csAttr = $bcs > 1 ? ' colspan="'.$bcs.'"' : '';
                $html .= '<td'.$csAttr.' style="padding:'.$cellPad.'px; min-height:'.round($mainRowH,2).'px; text-align:'.$align.'; '
                    . 'font-family:'.$bodyFont.'; font-size:'.$bodySize.'px; '
                    . 'color:'.$bodyColor.'; '.$bStyle . $dBiuStyle . '">'
                    . htmlspecialchars($cellVal)
                    . '</td>';
            }
            $html .= '</tr>';

            // ── Sub-rows (stone details etc.) ──
            if ($hasSubRows && !empty($subRowCols) && !empty($item['_sub_rows'])) {
                foreach ($item['_sub_rows'] as $subItem) {
                    $subRowH = _konva_estimate_table_subrow_height_px(
                        $subItem,
                        $cols,
                        $subRowCols,
                        $bill_data,
                        $layout['subFontSize'],
                        $layout['cellPad'],
                        $subRowMinPx
                    );
                    $html .= '<tr style="height:'.round($subRowH,2).'px;">';
                    foreach ($cols as $ci => $col) {
                        $subCol = isset($subRowCols[$ci]) ? $subRowCols[$ci] : null;
                        $subField = $subCol ? ($subCol['field'] ?? '') : '';
                        $subAlign = $subCol ? ($subCol['align'] ?? 'left') : 'left';

                        $subVal = '';
                        if (!empty($subField)) {
                            $is_last_col = ($ci === count($cols) - 1);
                            // Sub-row columns can have their own decimalMode
                            $sub_decimal = isset($subCol['decimalMode']) && $subCol['decimalMode'] !== '' ? (int)$subCol['decimalMode'] : -1;
                            $subVal = _konva_resolve_field($subField, $subItem, $bill_data, $is_last_col, $sub_decimal);
                        }

                        $bStyle = '';
                        if ($showVLines && $ci > 0) $bStyle .= 'border-left:'.$bw.' '.$borderStyle.' #000;';
                        if (!$showSideLines && ($ci === 0)) $bStyle .= 'border-left:none;';
                        if (!$showSideLines && ($ci === count($cols)-1)) $bStyle .= 'border-right:none;';
                        if ($subCol && !empty($subCol['noWrap'])) $bStyle .= 'white-space:nowrap;overflow:hidden;';

                        $html .= '<td style="padding:'.$cellPad.'px; min-height:'.round($subRowH,2).'px; text-align:'.$subAlign.'; '
                            . 'font-family:'.$subBodyFont.'; font-size:'.$subBodySize.'px; '
                            . 'color:'.$subBodyColor.'; '.$bStyle.'">'
                            . htmlspecialchars($subVal)
                            . '</td>';
                    }
                    $html .= '</tr>';
                }
            }
        }
    }
    $html .= '</tbody>';

    // ── FOOTER ── (suppressed on non-final pages during split)
    if ($hasFooter) {
        $footerLabel = $cfg['footerLabel'] ?? 'Total';

        $html .= '<tfoot><tr style="background:#f5f5f5; font-weight:bold; height:'.round($footerHPx,2).'px;">';

        // Per-column footer colspan rendering
        $skipF = 0;
        for ($fi = 0; $fi < count($cols); $fi++) {
            if ($skipF > 0) { $skipF--; continue; }
            $col = $cols[$fi];
            $fcs = min(intval($col['footerColspan'] ?? 1), count($cols) - $fi);
            if ($fcs < 1) $fcs = 1;
            if ($fcs > 1) $skipF = $fcs - 1;

            $footerField = $col['footerField'] ?? '';
            $align = $col['footerAlign'] ?? $col['align'] ?? 'right';
            $fVal = '';

            if (!empty($footerField)) {
                $is_last_col = ($fi + $fcs >= count($cols));
                // Footer uses the same column's decimalMode as the body cells
                $ftr_decimal = isset($col['decimalMode']) && $col['decimalMode'] !== '' ? (int)$col['decimalMode'] : -1;
                $fVal = _konva_resolve_field($footerField, [], $bill_data, $is_last_col, $ftr_decimal);
            }

            $fBStyle = '';
            if ($showVLines && $fi > 0) $fBStyle .= 'border-left:'.$bw.' '.$borderStyle.' #000;';
            if ($showHLinesFooter) $fBStyle .= 'border-top:'.$bw.' '.$borderStyle.' #000; border-bottom:'.$bw.' '.$borderStyle.' #000;';
            if (!$showSideLines && ($fi === 0)) $fBStyle .= 'border-left:none;';
            if (!$showSideLines && ($fi + $fcs >= count($cols))) $fBStyle .= 'border-right:none;';

            // Per-column B/I/U styles for footer (bold defaults to true — footer row is bold)
            $fBiuStyle = '';
            if (($col['footerBold'] ?? true)) $fBiuStyle .= 'font-weight:bold;';
            if (!empty($col['footerItalic'])) $fBiuStyle .= 'font-style:italic;';
            if (!empty($col['footerUnderline'])) $fBiuStyle .= 'text-decoration:underline;';

            $csAttr = $fcs > 1 ? ' colspan="'.$fcs.'"' : '';
            $html .= '<td'.$csAttr.' style="padding:'.$cellPad.'px; min-height:'.round($footerHPx,2).'px; text-align:'.$align.'; '
                . 'font-family:'.$footerFont.'; font-size:'.$footerSize.'px; '
                . $fBStyle . $fBiuStyle . '">'
                . htmlspecialchars($fVal)
                . '</td>';
        }
        $html .= '</tr></tfoot>';
    }

    $html .= '</table></div>';
    return $html;
}

/**
 * Get the correct item array based on table type
 */
function _konva_get_table_items($type, $bill_data)
{
    switch ($type) {
        case 'items':
        case 'sales':
            return $bill_data['sales_items'] ?? $bill_data['items'] ?? [];
        case 'old_gold':
            return $bill_data['old_metal_items'] ?? [];
        case 'stone_details':
            return $bill_data['stone_items'] ?? [];
        case 'exchange':
        case 'return':
            return $bill_data['return_items'] ?? [];
        case 'karigar':
            return $bill_data['transfer_items'] ?? [];
        case 'tax_summary':
            // Build a simple tax summary from mapped data
            $rows = [];
            if (!empty($bill_data['cgst_amount']) && $bill_data['cgst_amount'] !== '0.00') {
                $rows[] = ['description' => 'CGST', 'amount' => $bill_data['cgst_amount']];
            }
            if (!empty($bill_data['sgst_amount']) && $bill_data['sgst_amount'] !== '0.00') {
                $rows[] = ['description' => 'SGST', 'amount' => $bill_data['sgst_amount']];
            }
            if (!empty($bill_data['igst_amount']) && $bill_data['igst_amount'] !== '0.00') {
                $rows[] = ['description' => 'IGST', 'amount' => $bill_data['igst_amount']];
            }
            return $rows;
        // -- Template 369 Table Types --
        // Advance Amount table: date + amount rows from advance_details
        // Matches bill_format_2.php lines 2527-2638
        case 'advance_amount':
            return $bill_data['advance_details'] ?? [];
        // Order Advance Amount table: date + rate + weight + amount rows from order_adj
        // Matches bill_format_2.php lines 3478-3544
        case 'order_advance':
            return $bill_data['order_advance_entries'] ?? [];
        // Previously paid advances on the same order (bill_type=5 advance receipt)
        // Matches bill_format_2.php lines 2519-2629
        case 'previous_advance':
            return $bill_data['previous_advance_entries'] ?? [];
        // Chit Adjustment table: sno + ref_no + amount rows from chit_details
        // Matches bill_format_2.php lines 2466-2507
        case 'chit_adjustment':
            return $bill_data['chit_general_items'] ?? [];
        // Receipt Adjustment table: receipt_no, date, amounts from receiptDetails
        // Matches bill_format_2.php lines 3559-3596
        case 'receipt_adjustment':
            return $bill_data['receipt_adjustment_items'] ?? [];
        // Order Delivery Items table: same as sales items but for bill_type=5
        // Matches bill_format_2.php lines 1639-1700
        case 'order_delivery':
            return $bill_data['order_delivery_items'] ?? $bill_data['sales_items'] ?? [];
        // Repair Order Details table: sno, description, weight, completed_wt, amount
        // Matches bill_format_2.php lines 2259-2280
        case 'repair_order':
            return $bill_data['repair_order_items'] ?? [];
        // Credit Collection History: label, date, amount
        // Matches bill_format_2.php lines 2418-2430
        case 'credit_collection':
            return $bill_data['credit_collection_history'] ?? [];
        // Chit Pre-Close Items: sno, ref_no, amount (bill_type=10 variant)
        // Matches bill_format_2.php lines 680-698
        case 'chit_pre_close':
            return $bill_data['chit_pre_close_items'] ?? [];
        // Purchase Items: purchase_sno, description, qty, gross_wt, net_wt, rate, amount
        // Matches bill_format_2.php lines 2098-2130
        case 'purchase':
            return $bill_data['purchase_items'] ?? [];
        // Tax Detail Breakdown: HSN-wise tax split table
        // Matches bill_format_2.php lines 2688-2730
        case 'tax_detail_breakdown':
            return $bill_data['tax_detail_items'] ?? [];
        // Chit passbook installment rows (categories 18/19) — mapped by
        // tpl_map_passbook_data_to_template() as 'payment' (also aliased to items).
        case 'payment':
            return $bill_data['payment'] ?? $bill_data['items'] ?? [];
        case 'payment_methods':
            return $bill_data['payment_methods'] ?? [];
        case 'payment_breakdown':
            return $bill_data['payment_breakdown'] ?? [];
        default:
            return $bill_data['items'] ?? [];
    }
}

/**
 * Resolve a {{field}} from an item row, falling back to bill_data
 */
/**
 * Clean a numeric value for display:
 *  - Format as money (with 2 decimal places using Indian formatting) if it represents money
 *  - Strip trailing zeros after decimal point otherwise (833.00 → 833, 15.500 → 15.5)
 *  - If the result is pure zero (0, 0.00, 0.000) → return empty string
 *  - Non-numeric strings pass through unchanged
 */
function _konva_is_money_field($key)
{
    $key = trim(strtolower($key));
    if ($key === '') return false;

    // Exact list of money keys
    $money_keys = [
        'sub_total', 'subtotal', 'sgst_amount', 'cgst_amount', 'igst_amount',
        'sgst', 'cgst', 'igst', 'round_off', 'handling_charges',
        'grand_total', 'net_amount', 'net_payable', 'net_total', 'exchange_amount',
        'paid_amount', 'total_paid', 'balance_amount', 'due_amount', 'cash_received',
        'cash_amount', 'card_amount', 'upi_amount', 'cheque_amount',
        'pay_cash', 'pay_cheque', 'pay_card', 'pay_credit_card', 'pay_debit_card',
        'pay_rtgs', 'pay_imps', 'pay_neft', 'pay_upi', 'pay_due', 'pay_advance_adj', 'pay_advance_adjustment',
        'total_mc_amount', 'total_taxable', 'discount_amount', 'tcs_amount', 'tds_amount',
        'sales_sub_total', 'sales_total_amount', 'total_sales_amount',
        'total_old_amount', 'total_old_metal_amount', 'purchase_total_amount', 'purchase_cash_paid',
        'total_return_amount', 'total_return_amount_with_gst', 'return_sub_total',
        'total_return_cgst', 'total_return_sgst', 'total_return_igst', 'total_return_tax',
        'sales_return_total', 'total_transfer_amount',
        'advance_adj', 'advance_adjustment', 'advance_deposit', 'order_advance_adj', 'chit_adj', 'receipt_adj',
        'chit_general_total', 'chit_pre_close_total', 'repair_order_total',
        'receipt_adj_total_amount', 'receipt_adj_total_utilized', 'receipt_adj_total_refund', 'receipt_adj_total_balance',
        'credit_balance_amount', 'credit_collection_total',
        'order_amount', 'od_total_amount', 'od_approx_total', 'ord_approx_balance', 'apx_balance_amount',
        'rate_benefit', 'chit_benefit', 'chit_benefit_before_gst', 'scheme_benefit',
        'apx_scheme_discount', 'chit_general_payable_total', 'chit_general_rate_benefit_total',
        'item_total', 'total_va_content', 'sales_total_item_total', 'total_stone_amount',
        'amount', 'old_metal_amount', 'return_amount', 'stone_amount', 'return_stone_amount',
        'old_metal_stone_amount', 'cgst_amt', 'sgst_amt', 'igst_amt', 'mc_amt', 'taxable_amount',
        'discount', 'va_discount_amount', 'bill_discount', 'tot_amount', 'net_amt', 'sales_amount',
        'cash', 'card', 'cheque', 'upi', 'pay_credit', 'outstanding_amount',
        'total_gst_amount', 'cgst_total', 'sgst_total', 'igst_total', 'total_tax_amount',
        'total_taxable_amount', 'total_gst', 'tax_amount', 'vat_amount', 'vat_amt', 'tax_amt',
        'gross_amount', 'total_mc', 'total_va', 'total_stone', 'other_charges_amount',
        'handling_charges_amount', 'total_pure_wt_amount', 'pure_wt_amount', 'pure_amount',
        'rate_benefit_amount', 'discount_amt', 'va_discount_amt', 'bill_discount_amt',
        'mc_amount', 'va_amount', 'net_amount_with_tax', 'rate', 'old_metal_rate'
    ];

    if (in_array($key, $money_keys, true)) {
        return true;
    }

    // Pattern match: if key contains amount/amt/total/rate/etc., but not weight/qty/pcs
    if (preg_match('/(amount|amt|total|rate|price|cgst|sgst|igst|gst|tax|discount|charges|mc)/', $key)) {
        if (!preg_match('/(weight|wt|qty|pcs|pieces|count|percent|%)/', $key)) {
            return true;
        }
    }

    return false;
}

/**
 * Check if a field name represents a weight value (needs 3 decimal places).
 */
function _konva_is_weight_field($key)
{
    $key = trim(strtolower($key));
    if ($key === '') return false;

    $weight_keys = [
        'gross_wt', 'net_wt', 'stone_wt', 'dia_wt', 'diamond_wt',
        'old_metal_gross_wt', 'old_metal_net_wt', 'old_metal_wt',
        'repair_wt', 'completed_wt', 'pure_wt', 'melting_wt',
        'total_gross_wt', 'total_net_wt', 'total_stone_wt', 'total_dia_wt',
        'sales_total_gross_wt', 'sales_total_net_wt',
        'return_gross_wt', 'return_net_wt', 'return_stone_wt',
        'old_gross_wt', 'old_net_wt'
    ];

    if (in_array($key, $weight_keys, true)) {
        return true;
    }

    // Pattern match: if key ends with _wt or contains weight
    if (preg_match('/(_wt|_weight|gross_wt|net_wt)$/', $key)) {
        return true;
    }

    return false;
}

function _konva_clean_number($value, $is_money = false, $is_weight = false, $decimal_override = -1)
{
    if (is_array($value)) return '';
    $s = trim((string)$value);
    if ($s === '') return '';

    // Only process strings that look like formatted numbers (digits, commas, decimal)
    // e.g. "1,23,456.78", "17.000", "0.00", "833.00", "-0.00"
    if (!preg_match('/^-?[\d,]+\.?\d*$/', $s)) return $value;

    $raw = str_replace(',', '', $s);
    $val_float = floatval($raw);

    // If result is zero (0, -0, or empty after stripping), return blank
    if ($val_float == 0.0) {
        return '';
    }

    // Explicit decimal override set by the column's decimalMode setting
    // Values: 0, 1, 2, 3 → fixed decimal places; -1 → use auto logic below
    if ($decimal_override >= 0) {
        // A decimalMode setting fixes the number of decimals — it must not also switch the
        // digit grouping to western on money fields (NET AMOUNT printed 1,833,602.30 next to
        // an 18,60,602.30 sub total).
        if ($is_money && function_exists('moneyFormatIndia')) {
            return moneyFormatIndia(number_format($val_float, (int)$decimal_override, '.', ''));
        }
        return number_format($val_float, (int)$decimal_override);
    }

    if ($is_money) {
        if (function_exists('moneyFormatIndia')) {
            $formatted = sprintf("%.2f", $val_float);
            return moneyFormatIndia($formatted);
        } else {
            return number_format($val_float, 2);
        }
    }

    // Weight fields: always show 3 decimal places
    if ($is_weight) {
        return sprintf("%.3f", $val_float);
    }

    // Strip trailing zeros after decimal point
    if (strpos($s, '.') !== false) {
        $s = rtrim($s, '0');  // 833.00 → 833.  |  15.500 → 15.5
        $s = rtrim($s, '.');  // 833.  → 833
    }

    return $s;
}

function _konva_resolve_field($field, $item, $bill_data, $force_money = false, $decimal_override = -1)
{
    if (empty($field)) return '';

    // Static literal: values wrapped in single quotes render as-is (e.g. 'Total' → Total)
    $trimmed = trim($field);
    if (strlen($trimmed) >= 2 && $trimmed[0] === "'" && substr($trimmed, -1) === "'") {
        return substr($trimmed, 1, -1);
    }

    // If it's a {{variable}} pattern
    if (preg_match('/^\{\{(\w+)\}\}$/', trim($field), $m)) {
        $key = $m[1];
        $is_money = $force_money || _konva_is_money_field($key);
        $is_weight = _konva_is_weight_field($key);
        // First check item row
        if (isset($item[$key])) return _konva_clean_number($item[$key], $is_money, $is_weight, $decimal_override);
        // Then check bill_data
        if (isset($bill_data[$key])) {
            $v = $bill_data[$key];
            return is_array($v) ? '' : _konva_clean_number($v, $is_money, $is_weight, $decimal_override);
        }
        return '';
    }

    // Bare field name without {{}} (e.g. "item_total" instead of "{{item_total}}")
    // — the designer may store column fields without braces
    $bare = trim($field);
    if (preg_match('/^\w+$/', $bare)) {
        $is_money = $force_money || _konva_is_money_field($bare);
        $is_weight = _konva_is_weight_field($bare);
        if (isset($item[$bare])) return _konva_clean_number($item[$bare], $is_money, $is_weight, $decimal_override);
        if (isset($bill_data[$bare])) {
            $v = $bill_data[$bare];
            return is_array($v) ? '' : _konva_clean_number($v, $is_money, $is_weight, $decimal_override);
        }
        // Not found in any data source — return empty, not the literal name
        return '';
    }

    // Could be a mix: "INV-{{bill_no}}"
    return _konva_substitute($field, array_merge($bill_data, $item), $force_money);
}

/**
 * Replace all {{key}} placeholders in a string
 */
function _konva_substitute($text, $data, $force_money = false, $decimal_override = -1)
{
    if (!$data || !is_string($text)) return $text;

    return preg_replace_callback('/\{\{(.+?)\}\}/', function($matches) use ($data, $force_money, $decimal_override) {
        $key = trim($matches[1]);
        if (isset($data[$key])) {
            $v = $data[$key];
            $is_money = $force_money || _konva_is_money_field($key);
            $is_weight = _konva_is_weight_field($key);
            return is_array($v) ? '' : _konva_clean_number($v, $is_money, $is_weight, $decimal_override);
        }
        // Variable not found in data — show empty instead of raw {{placeholder}}
        return '';
    }, $text);
}

/**
 * Auto-sum a column from item rows (for footer)
 */
function _konva_auto_sum($col, $items)
{
    $field = $col['field'] ?? '';
    if (empty($field)) return '';

    // Extract key from {{key}}
    if (preg_match('/^\{\{(\w+)\}\}$/', trim($field), $m)) {
        $key = $m[1];
    } else {
        return '';
    }

    // Check if this column is numeric-summable
    $numericFields = ['amount','qty','pcs','gross_wt','net_wt','rate','mc',
        'taxable_amount','cgst_amt','sgst_amt','igst_amt',
        'va_discount_amount','discount','bill_discount','item_total',
        'old_metal_amount','return_amount',
        'stone_pieces','stone_wt','stone_amount',
        'return_stone_wt','return_stone_amount',
        'old_metal_stone_wt','old_metal_stone_amount','dust_wt','old_metal_dust_wt'];

    if (!in_array($key, $numericFields)) return '';

    $sum = 0;
    foreach ($items as $item) {
        if (isset($item[$key])) {
            $v = str_replace(',', '', $item[$key]);
            $n = floatval($v);
            if (!is_nan($n)) $sum += $n;
        }
    }

    // Format based on field type
    if (in_array($key, ['qty','pcs'])) return number_format($sum, 0);
    if (_konva_is_weight_field($key)) return number_format($sum, 3);
    return number_format($sum, 2);
}

/**
 * Standalone page shown when a print flow has no usable template.
 *
 * Printing must never silently fall back to a different template — a bill printed from
 * the wrong layout looks correct and gets handed to a customer. Say so instead.
 *
 * @param  string $code   Template group code that was expected (e.g. 'billing')
 * @param  string $label  Human name of the print flow (e.g. 'Bill Invoice')
 * @return string HTML
 */
function konva_template_not_configured_page($code, $label = 'this document')
{
    $code  = htmlspecialchars($code, ENT_QUOTES);
    $label = htmlspecialchars($label, ENT_QUOTES);

    $html  = '<!DOCTYPE html><html><head><meta charset="utf-8">';
    $html .= '<title>Print Template Not Configured</title>';
    $html .= '<style>';
    $html .= 'body{margin:0;padding:40px;font-family:Arial,Helvetica,sans-serif;background:#f4f6f9;color:#333;}';
    $html .= '.card{max-width:640px;margin:40px auto;background:#fff;border:1px solid #ddd;border-top:4px solid #f39c12;border-radius:3px;padding:28px 32px;}';
    $html .= 'h2{margin:0 0 14px;font-size:20px;color:#c87f0a;}';
    $html .= 'p{line-height:1.6;margin:0 0 12px;}';
    $html .= 'ol{line-height:1.8;padding-left:20px;margin:0 0 16px;}';
    $html .= 'code{background:#f4f4f4;padding:2px 6px;border-radius:3px;font-size:13px;}';
    $html .= '.muted{color:#888;font-size:12px;margin-top:18px;}';
    $html .= '@media print{.noprint{display:none;}}';
    $html .= '</style></head><body><div class="card">';
    $html .= '<h2>Configuration needed for print template</h2>';
    $html .= '<p>No active print template is configured for <strong>' . $label . '</strong>.</p>';
    $html .= '<p>To fix this:</p><ol>';
    $html .= '<li>Go to <strong>Print Templates</strong>.</li>';
    $html .= '<li>Make sure a template exists with the code <code>' . $code . '</code>.</li>';
    $html .= '<li>Click <strong>Activate</strong> on the version you want to print with.</li>';
    $html .= '</ol>';
    $html .= '<p class="muted">The latest <strong>active</strong> template with code <code>' . $code . '</code> is used. '
           . 'Templates with any other code are not used for this document.</p>';
    $html .= '</div></body></html>';

    return $html;
}

/**
 * Find the active V2 Konva template for a given bill type / branch
 *
 * @param  int         $bill_type_id
 * @param  int|null    $branch_id
 * @param  string|null $only_code  When given, resolution is RESTRICTED to this template
 *                                 group `code` — the latest ACTIVE row in that group, or
 *                                 false. No category/global fallback is attempted, so the
 *                                 caller can tell the user the group is not configured
 *                                 instead of silently printing some other template.
 *                                 Billing passes 'billing'.
 * @return array|false  Template row or false
 */
function find_konva_template($bill_type_id, $branch_id = null, $only_code = null)
{
    $CI =& get_instance();
    $CI->load->model('print_template_model');

    // bill_type directly maps to template_category (numeric IDs)
    $category = (int)$bill_type_id;

    // Strategy (when $only_code is NOT set):
    // 1. Try exact category match (bill_type → template_category)
    // 2. Try global templates (code = 'global') — applies to all bill types
    // 3. Fallback to Common (category 0)

    $branch_filter = "";
    if ($branch_id) {
        $branch_filter = " AND (id_branch = " . (int)$branch_id . " OR id_branch IS NULL)";
    }
    $order = ($branch_id ? "id_branch DESC, " : "") . "id_template DESC";

    // Restricted lookup: this code group only. Latest active version wins.
    if (!empty($only_code)) {
        $sql = "SELECT * FROM print_templates WHERE code = " . $CI->db->escape($only_code)
             . " AND is_active = 1" . $branch_filter
             . " ORDER BY $order LIMIT 1";
        $template = $CI->db->query($sql)->row_array();
        if ($template) {
            $gjs = json_decode($template['gjs_data'], true);
            if ($gjs && isset($gjs['konva_json'])) {
                return $template;
            }
        }
        return false; // deliberate: no fallback to global/category
    }

    // 1. Exact category match
    $sql = "SELECT * FROM print_templates WHERE template_category = " . (int)$category
         . " AND is_active = 1" . $branch_filter
         . " ORDER BY $order LIMIT 1";
    $query = $CI->db->query($sql);
    $template = $query->row_array();
    if ($template) {
        $gjs = json_decode($template['gjs_data'], true);
        if ($gjs && isset($gjs['konva_json'])) {
            return $template;
        }
    }

    // 2. Global template (code starts with 'global_') — matches any bill type
    $sql = "SELECT * FROM print_templates WHERE code = 'global'"
         . " AND is_active = 1" . $branch_filter
         . " ORDER BY $order LIMIT 1";
    $query = $CI->db->query($sql);
    $template = $query->row_array();
    if ($template) {
        $gjs = json_decode($template['gjs_data'], true);
        if ($gjs && isset($gjs['konva_json'])) {
            return $template;
        }
    }

    // 3. Fallback to Common (category 0)
    if ($category !== 0) {
        $sql = "SELECT * FROM print_templates WHERE template_category = 0"
             . " AND is_active = 1" . $branch_filter
             . " ORDER BY $order LIMIT 1";
        $query = $CI->db->query($sql);
        $template = $query->row_array();
        if ($template) {
            $gjs = json_decode($template['gjs_data'], true);
            if ($gjs && isset($gjs['konva_json'])) {
                return $template;
            }
        }
    }

    return false;
}
