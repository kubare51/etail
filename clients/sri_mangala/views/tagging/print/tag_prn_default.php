<?php
$data = $tag;

$tag_code = "";
if(isset($data['tag_code']) && $data['tag_code'] != '') {
    $tag_code = $data['tag_code'];
}
// echo"<pre>";print_r($data);exit;

$product_name = "";
if(isset($data['product_name']) && $data['product_name'] != '') {
    $product_name = "^FO20,50^A0N,20,20^FD".$data['product_name']."^FS"."\r\n\r\n";
}

$design_name = "";
if(isset($data['design_name']) && $data['design_name'] != '') {
    $design_name = "^FO20,80^A0N,20,20^FD".$data['design_name']."^FS"."\r\n\r\n";
}


// VA Product
if($data['calculation_based_on'] != 3 && $data['calculation_based_on'] != 4) {

    $grs_wt = "";
    if(isset($data['gross_wt']) && $data['gross_wt'] > 0) {
        $grs_wt = "^FO225,25^A0N,18,18^FDGW:".$data['gross_wt']."GM^FS"."\r\n\r\n";
    }

    $purity = "";
    if(isset($data['purity_name']) && $data['purity_name'] != '') {
        $purity_val = is_numeric($data['purity_name']) ? number_format((float)$data['purity_name'], 1, '.', '') : $data['purity_name'];
        $purity = "^FO170,60^A0N,18,18^FD".$purity_val."^FS"."\r\n\r\n";
    }

    $dia_wt = "";
    if((isset($data['dia_wt']) && $data['dia_wt'] > 0) || (isset($data['dia_amount']) && $data['dia_amount'] > 0)) {
        $dia_wt = "^FO225,45^A0N,18,18^FDDia:".$data['dia_wt']."".$data['dia_uom_short_code']."^FS"."\r\n\r\n";
    }

    $stn_wt = "";
    if((isset($data['stn_wt']) && $data['stn_wt'] > 0) || (isset($data['stn_amount']) && $data['stn_amount'] > 0)) {
        $stn_wt = "^FO225,70^A0N,18,18^FDStn:".$data['stn_wt']."".$data['stuom_short_code']."^FS"."\r\n\r\n";
    }

    $printer_code = '^XA' .
                    "^PW750" .
                    "^LL150" .
                    "^LH0,0" .
                    "^FO20,10^A0N,25,25^FD".$tag_code."^FS" . "\r\n\r\n" .
                    $product_name .
                    $design_name .
                    $purity .
                    $grs_wt .
                    $dia_wt .
                    $stn_wt .
                    // QR Code & Border
                    "^FO350,20^BQN,2,3" .
                    "^FDLA,".$tag_code."^FS" . "\r\n\r\n" .
                    '^XZ' . "\r\n" ;

}

// MRP Product
else {


    $grs_wt = "";
    if(isset($data['gross_wt']) && $data['gross_wt'] > 0) {
        $grs_wt = "^FO225,25^A0N,18,18^FDGW:".$data['gross_wt']."GM^FS"."\r\n\r\n";
    }

    $purity = "";
    if(isset($data['purity_name']) && $data['purity_name'] != '') {
        $purity_val = is_numeric($data['purity_name']) ? number_format((float)$data['purity_name'], 1, '.', '') : $data['purity_name'];
        $purity = "^FO170,60^A0N,18,18^FD".$purity_val."^FS"."\r\n\r\n";
    }

    $mrp = "";
    if(isset($data['sales_value']) && $data['sales_value'] > 0) {
        $mrp_val = number_format((float)$data['sales_value'], 0, '.', '');
        $mrp = "^FO225,10^A0N,20,20^FDRs:".$mrp_val."^FS "."\r\n\r\n";
    }



    $printer_code = '^XA' .
                    "^PW750" .
                    "^LL150" .
                    "^LH0,0" .
                    "^FO20,10^A0N,25,25^FD".$tag_code."^FS" . "\r\n\r\n" .
                    $product_name .
                    $design_name .
                    $purity .
                    $grs_wt .
                    $mrp .
                    // QR Code & Border
                    "^FO350,20^BQN,2,3" .
                    "^FDLA,".$tag_code."^FS" . "\r\n\r\n" .
                    '^XZ' . "\r\n" ;

}

echo $printer_code;
