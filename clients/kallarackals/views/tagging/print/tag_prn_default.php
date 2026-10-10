<?php
$data = $tag; 

$tag_code = isset($data['tag_code']) ? $data['tag_code'] : '';

// Determine if this is a diamond/stone item
$has_stones = (isset($data['stn_wt']) && $data['stn_wt'] > 0) || (isset($data['dia_wt']) && $data['dia_wt'] > 0);

// --- Gross Weight ---
$gross_wt = "";
if(isset($data['gross_wt']) && $data['gross_wt'] > 0) {
    $gross_wt = "^fo340,0^a0n,17,21^fdG:".$data['gross_wt']."^fs". "\r\n\r\n";
}

// --- Stone / Diamond Weight (Diamond only) ---
$stn_dia = "";
if($has_stones) {
    $stn_part = isset($data['stn_wt']) && $data['stn_wt'] > 0 ? "S:".number_format($data['stn_wt'], 3) : "";
    $dia_wt_ct = isset($data['dia_wt']) && $data['dia_wt'] > 0 ? number_format($data['dia_wt'], 3) : 0;
    $dia_part = $dia_wt_ct > 0 ? "D:".$dia_wt_ct : "";
    $separator = ($stn_part != "" && $dia_part != "") ? "/" : "";
    $stn_dia = "^fo340,22^a0n,17,21^fd".$stn_part.$separator.$dia_part."^fs". "\r\n\r\n";
}

// --- Size ---
$size = "";
if(isset($data['size']) && $data['size'] != '') {
    $size = "^fo128,22^a0n,17,21^fdSZ:".$data['size']."^fs". "\r\n\r\n";
}

// --- Sub Design (2 chars) ---
$sub_design = "";
if(isset($data['sub_design_name']) && $data['sub_design_name'] != '') {
    $sub_design = "^fo230,22^a0n,17,21^fd".substr($data['sub_design_name'], 0, 2)."^fs". "\r\n\r\n";
}

// --- Product Name ---
$product_name = "";
if(isset($data['product_name']) && $data['product_name'] != '') {
    $product_name = "^fo128,45^a0n,17,21^fd".substr($data['product_name'], 0, 15)."^fs". "\r\n\r\n";
}

// --- MC (Making Charge) ---
$mc = "";
if(isset($data['tag_mc_value']) && $data['tag_mc_value'] > 0) {
    $mc_value = round($data['tag_mc_value'], 0);
    $mc_type = isset($data['tag_mc_type']) ? $data['tag_mc_type'] : '';
    $mc_suffix = ($mc_type == 1) ? "/P" : (($mc_type == 2) ? "/G" : (($mc_type == 3) ? "%" : ""));
    $mc = "^fo128,66^a0n,17,21^fdMC:".$mc_value.$mc_suffix."^fs". "\r\n\r\n";
}

// --- Wastage ---
$wastage = "";
if(isset($data['retail_max_wastage_percent']) && $data['retail_max_wastage_percent'] > 0) {
    $wastage_value = round($data['retail_max_wastage_percent'], 0);
    $wastage = "^fo220,66^a0n,17,21^fdWst:".$wastage_value."%^fs". "\r\n\r\n";
}

// --- Net Weight (Diamond only) ---
$net_wt = "";
if($has_stones && isset($data['net_wt']) && $data['net_wt'] > 0) {
    $net_wt = "^fo340,45^a0n,17,21^fdN:".$data['net_wt']."^fs". "\r\n\r\n";
}

// --- Purity ---
$purity = "";
if(isset($data['purity_name']) && $data['purity_name'] != '') {
    $purity = "^fo470,0^a0n,17,21^fd".$data['purity_name']."^fs". "\r\n\r\n";
}

// --- Stone Amount Encoded (Diamond only) ---
$stn_amount = "";
if($has_stones && isset($data['stn_amount']) && $data['stn_amount'] > 0) {
    $stn_amt_rounded = round($data['stn_amount'], 0);
    $stn_amt_str = strval($stn_amt_rounded);
    $stn_amt_alpha = strtr($stn_amt_str, '1234567890', 'ABCDEFGHIK');
    $stn_amount = "^fo340,70^a0n,17,21^fd".$stn_amt_alpha."^fs". "\r\n\r\n";
}

$printer_code = '^xa'."\r\n".
                '^lh001,00'."\r\n".
                '^MD+05'."\r\n\r\n".

                '^fo128,0^a0n,17,21^fd'.$tag_code.'^fs'. "\r\n\r\n" .

                $gross_wt.

                $stn_dia.

                '^fo678,100^a0n,18,20^fd,^fs'. "\r\n\r\n" .

                '^fo270,0^BQN,2,2^FDLA,'.$tag_code.'^fs'. "\r\n\r\n" .

                $size.

                $sub_design.

                $product_name.

                $mc.

                $wastage.

                '^fo125,149^a0n,22,25^fd^fs'. "\r\n\r\n" .

                $net_wt.

                $purity.

                $stn_amount.

                '^fo470,33^BQN,2,2^FDLA,'.$tag_code.'^fs'. "\r\n\r\n" .

                '^fo125,192^a0n,22,22^fd^fs'. "\r\n" .
                '^pq1'. "\r\n" .
                '^XZ'. "\r\n" ;

echo $printer_code;
