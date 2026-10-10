<?php
$data = $tag; 

$mrp = "";
$gwt = "";

    if((isset($data['sales_mode']) && $data['sales_mode'] == 1 && isset($data['sales_value']) && $data['sales_value'] != "" ) || $data['calculation_based_on'] == 4) {
        $sales_value = round($data['sales_value'], 0);
        $mrp = '^fo260,95^a0n,20,24^fdMRP: ' . round($sale_value, 0) . '^fs' . "\r\n";
    } else {
        $gwt = '^fo260,12^a0n,20,24^fdG.Wt:' . $data['gross_wt'] . '^fs' . "\r\n";
    }

    // Net Weight
    $net_wt = "";
    if ($data['stn_wt'] > 0 || $data['dia_wt'] > 0) {
        $net_wt = '^fo260,62^a0n,20,24^fdN.Wt: ' . $data['net_wt'] . '^fs' . "\r\n";
    }

    // Size
    $size = "";
    if ($data['size'] > 0) {
        $size = '^fo50,35^a0n,18,20^fdSZ:' . $data['size'] . '^fs' . "\r\n";
    }

    // HUID
    $huid1 = "";
    if (trim($data['hu_id']) != "" && trim($data['hu_id']) != "-") {
        $huid1 = '^fo410,52^a0r,18,20^fd' . $data['hu_id'] . '^fs' . "\r\n";
    }

    $huid2 = "";
    if (trim($data['hu_id2']) != "" && trim($data['hu_id2']) != "-") {
        $huid2 = '^fo430,52^a0r,18,20^fd' . $data['hu_id2'] . '^fs' . "\r\n";
    }

    // Purity 
	if ($data['purity_name'] == 91.600 || $data['purity_name'] == 916 || $data['purity_name'] == 91.6) {
		$purity = "22KT";
	}
	else if ($data['purity_name'] == 75.000 || $data['purity_name'] == 750 || $data['purity_name'] == 75) {
		$purity = "18KT";
	}


    // Stone Details
    $stone_details = "";

    if ($data['stn_wt'] > 0 && $data['dia_wt'] == 0) {
        $stone_details = '^fo260,35^a0n,20,24^fdS.Wt: ' . $data['stn_wt'] . '^FS' . "\r\n";
    }

    if ($data['dia_wt'] > 0 && $data['stn_wt'] == 0) {
        $stone_details = '^fo260,35^a0n,20,24^fdD.Wt: ' . $data['dia_wt'] . '^FS' . "\r\n";
    }

    if ($data['dia_wt'] > 0 && $data['stn_wt'] > 0) {
        $stone_details =
            '^fo260,35^a0n,20,24^fdD.Wt: ' . $data['dia_wt'] . '^FS' . "\r\n" .
            '^fo260,35^a0n,20,24^fdD.Wt: ' . $data['stn_wt'] . '^FS' . "\r\n";
    }

    // Stone Amount
    $stone_amount = "";

    if ($data['stn_amount'] > 0 && $data['dia_amount'] == 0) {
        $stone_amount = '^fo260,95^a0n,20,24^fdAMT: ' . $data['stn_amount'] . '^FS' . "\r\n";
    }

    if ($data['dia_amount'] > 0 && $data['stn_amount'] == 0) {
        $stone_amount = '^fo260,95^a0n,20,24^fdAMT: ' . $data['dia_amount'] . '^FS' . "\r\n";
    }

    // Printer Code Output
    $printer_code =
        '^xa' . "\r\n" .
        '^lh001,00' . "\r\n" .
        '^MD+05' . "\r\n" .

        '^fo50,12^a0n,19,22^fd' . $data['tag_code'] . '^fs' . "\r\n" .

        $gwt . "\r\n" .

        $stone_details .

        '^fo50,95^a0n,20,24^fd' . $data['design_name'] . '^fs' . "\r\n" .

        '^fo678,100^a0n,18,20^fd,^fs' . "\r\n" .

        '^fo100,98^a0r,5,5^fd^fs' . "\r\n" .

        '^fo170,8^BQN,2.7,2.7^FDLA,' . $data['tag_code'] . '^fs' . "\r\n" .

        $size .

        '^fo50,65^a0n,18,20^fd' . $data['product_name'] . '^fs' . "\r\n" .

        '^fo125,149^a0n,22,22^fd^fs' . "\r\n" .

        $net_wt .
        $stone_amount .

        $huid1 .
        $huid2 .
        $mrp .

        '^fo405,18^a0n,20,24^fd' . $purity . '^fs' . "\r\n" .

        '^fo125,192^a0n,22,22^fd^fs' . "\r\n" .

        '^pq1' . "\r\n" .
        '^XZ' . "\r\n";

echo $printer_code;