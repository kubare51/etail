<?php
$data = $tag;

$tag_code = isset($data['tag_code']) ? $data['tag_code'] : '';

// Design Name (or fallback to product_name)
$design_name = "";
if (isset($data['design_name']) && trim($data['design_name']) != '') {
    $design_name = trim($data['design_name']);
} elseif (isset($data['product_name']) && trim($data['product_name']) != '') {
    $design_name = trim($data['product_name']);
}

// Gross Weight
$gross_wt_val = (isset($data['gross_wt']) && $data['gross_wt'] > 0) ? number_format((float)$data['gross_wt'], 3, '.', '') : '0.000';

// Net Weight
$net_wt_val = (isset($data['net_wt']) && $data['net_wt'] > 0) ? number_format((float)$data['net_wt'], 3, '.', '') : '0.000';

// Raw Purity extraction
$raw_purity = "";
if (isset($data['purity_name']) && trim($data['purity_name']) != '') {
    $raw_purity = trim($data['purity_name']);
} elseif (isset($data['display_purity']) && trim($data['display_purity']) != '') {
    $raw_purity = trim($data['display_purity']);
} elseif (isset($data['purity']) && trim($data['purity']) != '') {
    $raw_purity = trim($data['purity']);
}

// Format Purity as per specification (92.5 for Silver, 22kt / 18kt for Gold)
$purity_val = $raw_purity;
$is_silver = isset($data['metal_id']) && ($data['metal_id'] == 2 || $data['metal_id'] == 3);

if ($is_silver) {
    $p_num = (float)preg_replace('/[^0-9.]/', '', $raw_purity);
    if ($p_num == 92.5 || $p_num == 925 || $p_num == 92.50) {
        $purity_val = "92.5";
    }
} else {
    $p_num = (float)preg_replace('/[^0-9.]/', '', $raw_purity);
    if ($p_num == 91.6 || $p_num == 91.60 || $p_num == 916 || $p_num == 22) {
        $purity_val = "22kt";
    } elseif ($p_num == 75.0 || $p_num == 75.00 || $p_num == 750 || $p_num == 18) {
        $purity_val = "18kt";
    } elseif ($p_num == 99.9 || $p_num == 99.90 || $p_num == 999 || $p_num == 24) {
        $purity_val = "24kt";
    } else {
        if (preg_match('/^(\d+)\s*(?:kt|kt\.|k|carat)$/i', $raw_purity, $m)) {
            $purity_val = $m[1] . "kt";
        } elseif (preg_match('/^(\d+)$/', $raw_purity, $m) && ((int)$m[1] <= 24)) {
            $purity_val = $m[1] . "kt";
        }
    }
}

$printer_code = "";

if ($is_silver) {
    // 2) Silver metal scenario
    $printer_code = "CT~~CD,CC^~CT\r\n" .
                    "^XATA000JSN^LT0^MNW^MTT^PON^PMN^LH0,0^JMA^PR5,5~SD15^JUS^LRN^CI0^XZ\r\n" .
                    "^XA\r\n" .
                    "^MMT\r\n" .
                    "^PW735\r\n" .
                    "^LL0120\r\n" .
                    "^LS0\r\n" .
                    "^FT510,100^BQN,2,3\r\n" .
                    "^FDMA," . $tag_code . "^FS\r\n" .
                    "^FT500,28^A0N,20,22^FH\\^FD" . $tag_code . "^FS\r\n" .
                    "^FT220,28^A0N,20,22^FH\\^FDGwt : " . $gross_wt_val . "G^FS\r\n" .
                    "^FT350,56^A0N,19,19^FH\\^FD" . $design_name . "^FS\r\n" .
                    "^FT400,28^A0N,19,19^FH\\^FD" . $purity_val . "^FS\r\n" .
                    "^FT30ss,80^A0N,21,21^FH\\^FD^FS\r\n" .
                    "^FO422,8\r\n" .
                    "^PQ1,0,1,Y^XZ\r\n";
} else {
    // 1) Retail / Wholesale scenario

    // DAmt - Total Diamond Amount
    $damt_str = "";
    if (isset($data['dia_amount']) && $data['dia_amount'] > 0) {
        $damt_str = "^FT645,70^A0N,16,16^FH\\^FDDAmt : Rs." . number_format((float)$data['dia_amount'], 0, '.', '') . "^FS\r\n";
    } else {
        $damt_str = "^FT645,70^A0N,16,16^FH\\^FD^FS\r\n";
    }

    // DWt - Total Diamond Weight (in carats)
    $dwt_str = "";
    if (isset($data['dia_wt']) && $data['dia_wt'] > 0) {
        $dwt_str = "^FT663,88^A0N,16,16^FH\\^FDDWt: " . number_format((float)$data['dia_wt'], 3, '.', '') . "Ct^FS\r\n";
    } else {
        $dwt_str = "^FT663,88^A0N,16,16^FH\\^FD^FS\r\n";
    }

    // Swt - Stone Weight (in gram conversion) / Stone Amount
    $stn_wt_val = (isset($data['stn_wt']) && $data['stn_wt'] > 0) ? number_format((float)$data['stn_wt'], 3, '.', '') : '';
    $stn_amt_val = (isset($data['stn_amount']) && $data['stn_amount'] > 0) ? number_format((float)$data['stn_amount'], 0, '.', '') : '';

    // If multiple stone items are present in stone_details, calculate sum with gram conversion
    if (isset($data['stone_details']) && is_array($data['stone_details']) && count($data['stone_details']) > 0) {
        $tot_stn_wt_g = 0;
        $tot_stn_amt = 0;
        foreach ($data['stone_details'] as $stn) {
            $st_wt = (float)(isset($stn['wt']) ? $stn['wt'] : (isset($stn['stn_wt']) ? $stn['stn_wt'] : 0));
            $uom = isset($stn['uom_id']) ? $stn['uom_id'] : 0;
            if ($uom == 6) { // Carats -> divide by 5 to convert to Grams
                $tot_stn_wt_g += ($st_wt / 5);
            } else {
                $tot_stn_wt_g += $st_wt;
            }
            $tot_stn_amt += (float)(isset($stn['amount']) ? $stn['amount'] : (isset($stn['stn_amount']) ? $stn['stn_amount'] : 0));
        }
        if ($tot_stn_wt_g > 0) {
            $stn_wt_val = number_format($tot_stn_wt_g, 3, '.', '');
        }
        if ($tot_stn_amt > 0) {
            $stn_amt_val = number_format($tot_stn_amt, 0, '.', '');
        }
    }

    $swt_text = "";
    if ($stn_wt_val != '' && $stn_amt_val != '') {
        $swt_text = "Swt : " . $stn_wt_val . " / Rs. " . $stn_amt_val . "/-";
    } elseif ($stn_wt_val != '') {
        $swt_text = "Swt : " . $stn_wt_val;
    } elseif ($stn_amt_val != '') {
        $swt_text = "Swt : Rs. " . $stn_amt_val . "/-";
    }

    $swt_str = "^FT220,81^A0N,19,19^FH\\^FD" . $swt_text . "^FS\r\n";

    // O.No - Whatever is entered in narration field should be printed here
    $ono_val = "";
    if (isset($data['narration']) && trim($data['narration']) != '' && trim($data['narration']) != '-') {
        $ono_val = trim($data['narration']);
    } elseif (isset($data['order_no']) && trim($data['order_no']) != '' && trim($data['order_no']) != '-') {
        $ono_val = trim($data['order_no']);
    } elseif (isset($data['remarks']) && trim($data['remarks']) != '' && trim($data['remarks']) != '-') {
        $ono_val = trim($data['remarks']);
    }

    $ono_str = "";
    if ($ono_val != '') {
        if (preg_match('/^O\.?No/i', $ono_val)) {
            $ono_str = "^FT220,106^A0N,19,19^FH\\^FD" . $ono_val . "^FS\r\n";
        } else {
            $ono_str = "^FT220,106^A0N,19,19^FH\\^FDO.No : " . $ono_val . "^FS\r\n";
        }
    } else {
        $ono_str = "^FT220,106^A0N,19,19^FH\\^FD^FS\r\n";
    }

    $printer_code = "CT~~CD,CC^~CT\r\n" .
                    "^XATA000JSN^LT0^MNW^MTT^PON^PMN^LH0,0^JMA^PR5,5~SD15^JUS^LRN^CI0^XZ\r\n" .
                    "^XA\r\n" .
                    "^MMT\r\n" .
                    "^PW735\r\n" .
                    "^LL0120\r\n" .
                    "^LS0\r\n" .
                    "^FT510,100^BQN,2,3\r\n" .
                    "^FDMA," . $tag_code . "^FS\r\n" .
                    "^FT500,28^A0N,20,22^FH\\^FD" . $tag_code . "^FS\r\n" .
                    $damt_str .
                    $dwt_str .
                    "^FT220,28^A0N,20,22^FH\\^FDGwt : " . $gross_wt_val . "G^FS\r\n" .
                    "^FT350,56^A0N,19,19^FH\\^FD" . $design_name . "^FS\r\n" .
                    "^FT400,28^A0N,19,19^FH\\^FD" . $purity_val . "^FS\r\n" .
                    "^FT220,56^A0N,20,22^FH\\^FDNwt : " . $net_wt_val . "G^FS\r\n" .
                    $swt_str .
                    $ono_str .
                    "^FT30ss,80^A0N,21,21^FH\\^FD^FS\r\n" .
                    "^FO422,8\r\n" .
                    "^PQ1,0,1,Y^XZ\r\n";
}

echo $printer_code;
