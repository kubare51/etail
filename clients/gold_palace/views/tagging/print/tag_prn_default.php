<?php
$data = $tag;

// echo"<pre>";print_r($data);exit;

$tag_code_val = "";
if (isset($data['tag_code']) && $data['tag_code'] != '') {
    $tag_code_val = $data['tag_code'];
}

$product_name_val = "";
if (isset($data['product_name']) && $data['product_name'] != '') {
    $product_name_val = $data['product_name'];
}

$sub_design_name_val = "";
if (isset($data['sub_design_name']) && $data['sub_design_name'] != '') {
    // Fallback to sub_design_name just in case
    $sub_design_name_val = $data['sub_design_name'];
}

$huid_val = "";
if(isset($data['hu_id1']) && $data['hu_id1'] != '' && $data['hu_id1'] != '-') {
    $huid_val = $data['hu_id1'];
}
elseif(isset($data['hu_id2']) && $data['hu_id2'] != '' && $data['hu_id2'] != '-') {
    $huid_val = $data['hu_id2'];
}


$size_val = "";
if (isset($data['size']) && $data['size'] != '') {
    $size_val = $data['size'];
}

$grs_wt_val = "";
if (isset($data['gross_wt']) && $data['gross_wt'] > 0) {
    $grs_wt_val = number_format((float)$data['gross_wt'], 3, '.', '');
}

$net_wt_val = "";
if (isset($data['net_wt']) && $data['net_wt'] > 0) {
    $net_wt_val = number_format((float)$data['net_wt'], 3, '.', '');
}

$stn_wt_val = "";
if ((isset($data['stn_wt']) && $data['stn_wt'] > 0) || (isset($data['stn_amount']) && $data['stn_amount'] > 0)) {
    if (isset($data['stn_wt'])) {
        $stn_wt_val = number_format((float)$data['stn_wt'], 3, '.', '');
    }
}

$stn_wt_float = (float)$stn_wt_val;

$mrp_val = "";
if (isset($data['sales_value']) && $data['sales_value'] > 0) {
    $mrp_val = number_format((float)$data['sales_value'], 0, '.', '');
}

$short_code_val = "";
if (isset($data['short_code']) && $data['short_code'] != '') {
    $short_code_val = $data['short_code'];
}

$dia_wt_val = "";
if (isset($data['dia_wt']) && $data['dia_wt'] > 0) {
    $dia_wt_val = number_format((float)$data['dia_wt'], 3, '.', '');
}

$cert_no_val = "";
if (isset($data['cert_no']) && $data['cert_no'] != '') {
    $cert_no_val = $data['cert_no'];
}

$is_diamond = false;
if ((isset($data['dia_wt']) && $data['dia_wt'] > 0) || (isset($data['dia_amount']) && $data['dia_amount'] > 0)) {
    $is_diamond = true;
}

$printer_code = "";

if ($is_diamond) {

    $product_name = "";
    if ($product_name_val != "") {
        $product_name = 'TEXT 508,125,"ROMAN.TTF",180,1,9,"' . $product_name_val . '"' . "\r\n";
    }

    $grs_wt = 'TEXT 775,125,"ROMAN.TTF",180,1,9,"G.WT:' . $grs_wt_val . '"' . "\r\n";
    $mrp = 'TEXT 775,100,"ROMAN.TTF",180,1,9,"MRP:' . $mrp_val . '"' . "\r\n";
    
    $cents = "";
    if ($dia_wt_val != "") {
        $cents = 'TEXT 775,75,"ROMAN.TTF",180,1,9,"CENTS:' . $dia_wt_val . 'C"' . "\r\n";
    }

    $tag_code = 'TEXT 775,45,"ROMAN.TTF",180,1,9,"' . $tag_code_val . '"' . "\r\n";
    $store_code = 'TEXT 556,70,"0",270,10,10,"' . $short_code_val . '"' . "\r\n";
    $qrcode = 'QRCODE 508,90,L,3,A,180,M2,S7,"' . $tag_code_val . '"' . "\r\n";

    $design_name = "";
    if ($sub_design_name_val != "") {
        $design_name = 'TEXT 428,94,"ROMAN.TTF",180,1,9,"' . $sub_design_name_val . '"' . "\r\n";
    }

    $cert_no = "";
    if ($cert_no_val != "") {
        $cert_no = 'TEXT 428,45,"ROMAN.TTF",180,1,9,"CN:' . $cert_no_val . '"' . "\r\n";
    }

    $printer_code = "SIZE 97.5 mm, 14 mm\r\n" .
                    "DIRECTION 0,0\r\n" .
                    "REFERENCE 0,0\r\n" .
                    "OFFSET 0 mm\r\n" .
                    "SET PEEL OFF\r\n" .
                    "SET CUTTER OFF\r\n" .
                    "SET PARTIAL_CUTTER OFF\r\n" .
                    "SET TEAR ON\r\n" .
                    "CLS\r\n" .
                    "CODEPAGE 1252\r\n" .
                    $product_name .
                    $grs_wt .
                    $mrp .
                    $cents .
                    $tag_code .
                    $store_code .
                    $qrcode .
                    $design_name .
                    $cert_no .
                    "PRINT 1,1\r\n";

} elseif($data['metal_id'] == 1) {

    if ($stn_wt_float > 0) {
        // Gold with stone weight variables
        $product_name = "";
        if ($product_name_val != "") {
            $product_name = 'TEXT 508,125,"ROMAN.TTF",180,1,9,"' . $product_name_val . '"' . "\r\n";
        }

        $grs_wt = 'TEXT 775,125,"ROMAN.TTF",180,1,9,"G.WT:' . $grs_wt_val . '"' . "\r\n";
        $stn_wt = 'TEXT 775,100,"ROMAN.TTF",180,1,9,"S.WT:' . $stn_wt_val . '"' . "\r\n";
        $net_wt = 'TEXT 775,75,"ROMAN.TTF",180,1,9,"N.WT:' . $net_wt_val . '"' . "\r\n";
        
        $tag_code = 'TEXT 775,45,"ROMAN.TTF",180,1,9,"' . $tag_code_val . '"' . "\r\n";
        $store_code = 'TEXT 556,70,"0",270,10,10,"' . $short_code_val . '"' . "\r\n";
        $qrcode = 'QRCODE 508,90,L,3,A,180,M2,S7,"' . $tag_code_val . '"' . "\r\n";

        $design_name = "";
        if ($sub_design_name_val != "") {
            $design_name = 'TEXT 428,94,"ROMAN.TTF",180,1,9,"' . $sub_design_name_val . '"' . "\r\n";
        }

        $size = "";
        if ($size_val != "") {
            $size = 'TEXT 350,45,"ROMAN.TTF",180,1,9,"SZ:' . $size_val . '"' . "\r\n";
        }

        $printer_code = "SIZE 97.5 mm, 14 mm\r\n" .
                        "DIRECTION 0,0\r\n" .
                        "REFERENCE 0,0\r\n" .
                        "OFFSET 0 mm\r\n" .
                        "SET PEEL OFF\r\n" .
                        "SET CUTTER OFF\r\n" .
                        "SET PARTIAL_CUTTER OFF\r\n" .
                        "SET TEAR ON\r\n" .
                        "CLS\r\n" .
                        "CODEPAGE 1252\r\n" .
                        $product_name .
                        $grs_wt .
                        $stn_wt .
                        $net_wt .
                        $tag_code .
                        $store_code .
                        $qrcode .
                        $design_name .
                        $size .
                        "PRINT 1,1\r\n";

    } else {
        // Gold weight variables
        $product_name = "";
        if ($product_name_val != "") {
            $product_name = 'TEXT 775,125,"ROMAN.TTF",180,1,9,"' . $product_name_val . '"' . "\r\n";
        }

        $grs_wt = 'TEXT 775,90,"ROMAN.TTF",180,1,9,"WT:' . $grs_wt_val . '"' . "\r\n";
        $tag_code = 'TEXT 775,55,"ROMAN.TTF",180,1,9,"' . $tag_code_val . '"' . "\r\n";
        $store_code = 'TEXT 556,70,"0",270,10,10,"' . $short_code_val . ' "' . "\r\n";
        $qrcode = 'QRCODE 508,120,L,3,A,180,M2,S7,"' . $tag_code_val . '"' . "\r\n";

        $design_name = "";
        if ($sub_design_name_val != "") {
            $design_name = 'TEXT 428,94,"ROMAN.TTF",180,1,9,"' . $sub_design_name_val . '"' . "\r\n";
        }

        $huid = "";
        if ($huid_val != "") {
            $huid = 'TEXT 508,45,"ROMAN.TTF",180,1,9,"' . $huid_val . '"' . "\r\n";
        }

        $size = "";
        if ($size_val != "") {
            $size = 'TEXT 350,45,"ROMAN.TTF",180,1,9,"SZ:' . $size_val . '"' . "\r\n";
        }

        $printer_code = "SIZE 97.5 mm, 14 mm\r\n" .
                        "DIRECTION 0,0\r\n" .
                        "REFERENCE 0,0\r\n" .
                        "OFFSET 0 mm\r\n" .
                        "SET PEEL OFF\r\n" .
                        "SET CUTTER OFF\r\n" .
                        "SET PARTIAL_CUTTER OFF\r\n" .
                        "SET TEAR ON\r\n" .
                        "CLS\r\n" .
                        "CODEPAGE 1252\r\n" .
                        $product_name .
                        $grs_wt .
                        $tag_code .
                        $store_code .
                        $qrcode .
                        $design_name .
                        $huid .
                        $size .
                        "PRINT 1,1\r\n";
    }

} elseif($data['metal_id'] == 2 || $data['metal_id'] == 3) {

    $is_mrp = (isset($data['calculation_based_on']) && ($data['calculation_based_on'] == 3 || $data['calculation_based_on'] == 4));

    if ($is_mrp) {
        if ($grs_wt_val != "") {
            // Silver MRP with weight
            $product_name = "";
            if ($product_name_val != "") {
                $product_name = 'TEXT 775,125,"ROMAN.TTF",180,1,9,"' . $product_name_val . '"' . "\r\n";
            }

            $mrp = 'TEXT 775,98,"ROMAN.TTF",180,1,9,"MRP:' . $mrp_val . '"' . "\r\n";
            $grs_wt = 'TEXT 775,73,"ROMAN.TTF",180,1,9,"G.WT:' . $grs_wt_val . '"' . "\r\n";
            $tag_code = 'TEXT 775,45,"ROMAN.TTF",180,1,9,"' . $tag_code_val . '"' . "\r\n";
            $store_code = 'TEXT 556,70,"0",270,10,10,"' . $short_code_val . ' "' . "\r\n";
            $qrcode = 'QRCODE 508,100,L,3,A,180,M2,S7,"' . $tag_code_val . '"' . "\r\n";

            $design_name = "";
            if ($sub_design_name_val != "") {
                $design_name = 'TEXT 428,94,"ROMAN.TTF",180,1,9,"' . $sub_design_name_val . '"' . "\r\n";
            }

            $size = "";
            if ($size_val != "") {
                $size = 'TEXT 350,45,"ROMAN.TTF",180,1,9,"SZ:' . $size_val . '"' . "\r\n";
            }

            $printer_code = "SIZE 97.5 mm, 14 mm\r\n" .
                            "DIRECTION 0,0\r\n" .
                            "REFERENCE 0,0\r\n" .
                            "OFFSET 0 mm\r\n" .
                            "SET PEEL OFF\r\n" .
                            "SET CUTTER OFF\r\n" .
                            "SET PARTIAL_CUTTER OFF\r\n" .
                            "SET TEAR ON\r\n" .
                            "CLS\r\n" .
                            "CODEPAGE 1252\r\n" .
                            $product_name .
                            $mrp .
                            $grs_wt .
                            $tag_code .
                            $store_code .
                            $qrcode .
                            $design_name .
                            $size .
                            "PRINT 1,1\r\n";
        } else {
            // silver MRP
            $product_name = "";
            if ($product_name_val != "") {
                $product_name = 'TEXT 775,125,"ROMAN.TTF",180,1,9,"' . $product_name_val . '"' . "\r\n";
            }

            $mrp = 'TEXT 775,90,"ROMAN.TTF",180,1,9,"MRP:' . $mrp_val . '"' . "\r\n";
            $tag_code = 'TEXT 775,55,"ROMAN.TTF",180,1,9,"' . $tag_code_val . '"' . "\r\n";
            $store_code = 'TEXT 556,70,"0",270,10,10,"' . $short_code_val . '"' . "\r\n";
            $qrcode = 'QRCODE 508,100,L,3,A,180,M2,S7,"' . $tag_code_val . '"' . "\r\n";

            $design_name = "";
            if ($sub_design_name_val != "") {
                $design_name = 'TEXT 428,94,"ROMAN.TTF",180,1,9,"' . $sub_design_name_val . '"' . "\r\n";
            }

            $size = "";
            if ($size_val != "") {
                $size = 'TEXT 350,45,"ROMAN.TTF",180,1,9,"SZ:' . $size_val . '"' . "\r\n";
            }

            $printer_code = "SIZE 97.5 mm, 14 mm\r\n" .
                            "DIRECTION 0,0\r\n" .
                            "REFERENCE 0,0\r\n" .
                            "OFFSET 0 mm\r\n" .
                            "SET PEEL OFF\r\n" .
                            "SET CUTTER OFF\r\n" .
                            "SET PARTIAL_CUTTER OFF\r\n" .
                            "SET TEAR ON\r\n" .
                            "CLS\r\n" .
                            "CODEPAGE 1252\r\n" .
                            $product_name .
                            $mrp .
                            $tag_code .
                            $store_code .
                            $qrcode .
                            $design_name .
                            $size .
                            "PRINT 1,1\r\n";
        }
    } else {
        // Silver weight
        $product_name = "";
        if ($product_name_val != "") {
            $product_name = 'TEXT 775,125,"ROMAN.TTF",180,1,9,"' . $product_name_val . '"' . "\r\n";
        }

        $grs_wt = 'TEXT 775,90,"ROMAN.TTF",180,1,9,"WT:' . $grs_wt_val . '"' . "\r\n";
        $tag_code = 'TEXT 775,55,"ROMAN.TTF",180,1,9,"' . $tag_code_val . '"' . "\r\n";
        $store_code = 'TEXT 556,70,"0",270,10,10,"' . $short_code_val . ' "' . "\r\n";
        $qrcode = 'QRCODE 508,120,L,3,A,180,M2,S7,"' . $tag_code_val . '"' . "\r\n";

        $design_name = "";
        if ($sub_design_name_val != "") {
            $design_name = 'TEXT 428,94,"ROMAN.TTF",180,1,9,"' . $sub_design_name_val . '"' . "\r\n";
        }

        $size = "";
        if ($size_val != "") {
            $size = 'TEXT 350,45,"ROMAN.TTF",180,1,9,"SZ:' . $size_val . '"' . "\r\n";
        }

        $printer_code = "SIZE 97.5 mm, 14 mm\r\n" .
                        "DIRECTION 0,0\r\n" .
                        "REFERENCE 0,0\r\n" .
                        "OFFSET 0 mm\r\n" .
                        "SET PEEL OFF\r\n" .
                        "SET CUTTER OFF\r\n" .
                        "SET PARTIAL_CUTTER OFF\r\n" .
                        "SET TEAR ON\r\n" .
                        "CLS\r\n" .
                        "CODEPAGE 1252\r\n" .
                        $product_name .
                        $grs_wt .
                        $tag_code .
                        $store_code .
                        $qrcode .
                        $design_name .
                        $size .
                        "PRINT 1,1\r\n";
    }

}

echo $printer_code;
?>
