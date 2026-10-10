<?php
$data = $tag; 

$product_name = "";
if(isset($data['product_name']) && $data['product_name'] != '' && $data['calculation_based_on'] != 3 && $data['calculation_based_on'] != 4) {
    $product_name = "^FO376,92^A0N,24,24^FD".substr($data['product_name'], 0, 11)."^FS". "\r\n\r\n";
}

elseif(isset($data['product_name']) && $data['product_name'] != '' && $data['calculation_based_on'] == 3 || $data['calculation_based_on'] == 4) {
    $product_name = "^FO375,92^A0N,24,24^FD".substr($data['product_name'], 0, 11)."^FS". "\r\n\r\n";
}


$grs_wt ="";
if(isset($data['gross_wt']) && $data['gross_wt'] > 0 && $data['calculation_based_on'] != 3) {
    $grs_wt = "^FO95,120^A0N,25,25^FDG.Wt: ".$data['gross_wt']." Gm^FS"."\r\n\r\n" ;
}

$tag_code ="";
if(isset($data['tag_code']) && $data['tag_code'] > 0 && $data['calculation_based_on'] != 3) {
    $tag_code = "^FO376,58^A0N,30,30^FD ".$data['tag_code']."^FS"."\r\n\r\n" ;
}

else if(isset($data['tag_code']) && $data['tag_code'] > 0 && $data['calculation_based_on'] == 3) {
    $tag_code = "^FO375,58^A0N,30,30^FD ".$data['tag_code']."^FS"."\r\n\r\n" ;
}



$printer_code = '^XA' .
                "^PW520".
                "^LL150".
                "^LH0,0".

                "^FO20,15".
                "^A0N,20,20".
                "^FDVEDA^FS".

                "^FO20,30".
                "^BY1.6,1,38".
                "^BCN,38,N,N,N".
                '^FD'.$data['tag_code'].'^FS'."\r\n" .

                "^FO95,90".
                "^A0N,25,25".
                '^FD'.$data['tag_code'].'^FS'."\r\n" .

                $grs_wt.

                $tag_code.

                $product_name.

                '^XZ'. "\r\n" ;

echo $printer_code;
