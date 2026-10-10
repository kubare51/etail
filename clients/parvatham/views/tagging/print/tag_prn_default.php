<?php
// Variables ending in _zpl hold a ready-to-emit ZPL fragment (empty = slot not printed).
// All others hold a plain sanitised value still to be wrapped in a ZPL field.

$data = $tag;

// ^ and ~ are ZPL command introducers; this format uses no ^FH\ escaping, so strip
// them out of anything sourced from the database.
$zpl_reserved = array('^', '~');

$tag_code = isset($data['tag_code']) ? str_replace($zpl_reserved, '', trim($data['tag_code'])) : '';

// --- Line 1: product name -------------------------------------------------
// Capped at 12 chars so it does not run into the branch code at x=470.
$product_name = '';
if(isset($data['product_name']) && trim($data['product_name']) != '') {
    $product_name = substr(str_replace($zpl_reserved, '', trim($data['product_name'])), 0, 12);
}

// --- Line 2: gross weight -------------------------------------------------
$gross_wt = isset($data['gross_wt']) ? number_format((float)$data['gross_wt'], 3, '.', '') : '0.000';

// --- Middle slot: diamond weight (diamond tags only) ----------------------
$dia_wt_zpl = '';
if(isset($data['dia_wt']) && (float)$data['dia_wt'] > 0) {
    $dia_uom = (isset($data['dia_uom_short_code']) && trim($data['dia_uom_short_code']) != '')
                ? str_replace($zpl_reserved, '', trim($data['dia_uom_short_code']))
                : 'CT';
    $dia_wt = number_format((float)$data['dia_wt'], 3, '.', '');
    $dia_wt_zpl = '^FT430,60^A0N,20,17^FDD:'.$dia_wt.$dia_uom.'^FS'. "\r\n";
}

// --- Right slot: MRP > HUID > MC per gram > design name -------------------
// calculation_based_on 3 = Fixed Rate, 4 = Fixed Rate based on Weight (both are MRP products)
$is_mrp_product = isset($data['calculation_based_on'])
                  && ($data['calculation_based_on'] == 3 || $data['calculation_based_on'] == 4);

// hu_id1 then hu_id2. Never hu_id — that is a CONCAT_WS of every HUID on the tag
// and returns the literal '-' when empty.
$huid = '';
if(isset($data['hu_id1']) && trim($data['hu_id1']) != '' && trim($data['hu_id1']) != '-') {
    $huid = str_replace($zpl_reserved, '', trim($data['hu_id1']));
} elseif(isset($data['hu_id2']) && trim($data['hu_id2']) != '' && trim($data['hu_id2']) != '-') {
    $huid = str_replace($zpl_reserved, '', trim($data['hu_id2']));
}

$right_slot_text = '';
if($is_mrp_product && isset($data['sales_value']) && (float)$data['sales_value'] > 0) {
    $right_slot_text = 'MRP:'.number_format((float)$data['sales_value'], 0, '.', '');
} elseif($huid != '') {
    $right_slot_text = 'HD:'.$huid;
} elseif(isset($data['tag_mc_type']) && $data['tag_mc_type'] == 2
         && isset($data['tag_mc_value']) && (float)$data['tag_mc_value'] > 0) {
    // tag_mc_type per the tagging form: 1 = Per Piece, 2 = Per Gram, 3 = % On Price
    $right_slot_text = 'MC:'.number_format((float)$data['tag_mc_value'], 0, '.', '').'/g';
} elseif(isset($data['design_name']) && trim($data['design_name']) != '') {
    $right_slot_text = str_replace($zpl_reserved, '', trim($data['design_name']));
}

$right_slot_zpl = '';
if($right_slot_text != '') {
    $right_slot_zpl = '^FT520,30^A0N,23,19^FD'.$right_slot_text.'^FS'. "\r\n";
}

// --- Size (ret_size.value is a string, e.g. "10.5" or "10inch") -----------
$size_zpl = '';
if(isset($data['size']) && trim($data['size']) != '') {
    $size = str_replace($zpl_reserved, '', trim($data['size']));
    $size_zpl = '^FT520,60^A0N,23,19^FDSize:'.$size.'^FS'. "\r\n";
}

// --- Tag date as YYMMDD (model hands it over as d-m-Y) --------------------
$tag_date_zpl = '';
if(isset($data['tag_datetime']) && trim($data['tag_datetime']) != '') {
    $tag_date = DateTime::createFromFormat('d-m-Y', trim($data['tag_datetime']));
    if($tag_date !== false) {
        $tag_date_zpl = '^FT520,90^A0N,23,19^FD'.$tag_date->format('ymd').'^FS'. "\r\n";
    }
}

// --- Branch short code (the "SPJ" slot) -----------------------------------
$branch_code_zpl = '';
if(isset($data['brnc_scode']) && trim($data['brnc_scode']) != '') {
    $branch_code = str_replace($zpl_reserved, '', trim($data['brnc_scode']));
    $branch_code_zpl = '^FT470,30^A0N,25,20^FD'.$branch_code.'^FS'. "\r\n";
}

$printer_code = '^XA~~CD,CC^~CT~'. "\r\n" .
                '^XATA000JSN^LT0^MNW^MTT^PON^PMN^LH0,0^JMA^PR5,5~SD15^JUS^LRN^CI0^XZ'. "\r\n" .
                '^XA'. "\r\n" .
                '^PON'. "\r\n" .
                '^MMT'. "\r\n" .
                '^PW735'. "\r\n" .
                '^LL0120'. "\r\n" .
                '^LS0'. "\r\n" .

                '^FT612,99^BQN,2,3s'. "\r\n" .
                '^FDMA,'.$tag_code.'^FS'. "\r\n" .

                '^FT338,30^A0N,25,20^FD'.$product_name.'^FS'. "\r\n" .
                '^FT338,60^A0N,25,20^FDWT:'.$gross_wt.'^FS'. "\r\n" .

                $dia_wt_zpl.

                '^FT338,90^A0N,25,20^FD'.$tag_code.'^FS'. "\r\n" .

                $right_slot_zpl.
                $size_zpl.
                $tag_date_zpl.
                $branch_code_zpl.

                '^PQ1,0,1,Y'. "\r\n" .
                '^XZ'. "\r\n" ;

echo $printer_code;
