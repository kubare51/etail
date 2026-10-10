<html>
<head>
    <meta charset="utf-8">
    <title><?php echo ($print_type == 3) ? 'Company Copy' : 'Sales Transfer'; ?> - <?php echo $print_data['header']['bill_no']; ?></title>
    <link rel="stylesheet" href="<?php echo base_url(); ?>assets/css/bt_ack.css">
    <style type="text/css">
        @page {
            size: A4;
            margin: 30px 0px;
        }
        body, html {
            margin: 0;
            padding: 0;
            font-family: sans-serif;
            font-size: 11px;
        }
        span {
            display: inline-block;
        }
        .PDFReceipt {
            display: block;
            padding-top:40px;
            padding-left: 50px;
            padding-right: 50px;
        }
        /* Custom adaptations to match the Branch Transfer Receipt styles */
        table#pp {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        table#pp th {
            font-weight: bold;
            font-size: 11px !important;
            padding: 5px 4px;
            border: none;
        }
        table#pp td {
            font-size: 11px !important;
            padding: 5px 4px;
            border: none;
        }
        /* Override dashed widths from bt_ack.css to prevent horizontal overflow */
        .detail_dashed, .sumamry_dashed, .item_dashed, .old_sumamry_dashed {
            width: 100% !important;
            margin: 0 !important;
            border-top: 1px dashed black !important;
        }
        .no-print {
            margin-bottom: 15px;
            text-align: center;
        }
        @media print {
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>

<?php
    $h = $print_data['header'];
    $items = $print_data['items'];
    $print_type = isset($print_type) ? $print_type : 2; // 1 = Summary, 2 = Detailed
    $typeLabels = array(1 => 'Tagged', 2 => 'Non-Tagged', 3 => 'Old Gold', 4 => 'Sales Return', 5 => 'Partly Sold');

    // Purchase Rate/VA/MC formatter — renders '' for NULL/missing/non-numeric so the
    // print never shows NaN/undefined/null (purchase items always print '')
    if (!function_exists('st_purchase_fmt')) {
        function st_purchase_fmt($item, $key, $applicable)
        {
            if (!$applicable || !isset($item[$key]) || $item[$key] === '' || $item[$key] === null || !is_numeric($item[$key])) {
                return '';
            }
            return number_format((float)$item[$key], 2);
        }
    }
    $billTypeLabel = ($h['bill_type'] == 14) ? 'SALES RETURN TRANSFER INVOICE' : 'DEEMED SALES TRANSFER INVOICE';

    if (!function_exists('moneyFormatIndia')) {
        function moneyFormatIndia($num)
        {
            return preg_replace("/(\d+?)(?=(\d\d)+(\d)(?!\d))(\.\d+)?/i", "$1,", $num);
        }
    }

    // Group items for Summary Print
    if ($print_type == 1) {
        $grouped_items = array();
        foreach ($items as $item) {
            $key = $item['product_name'] . '_' . ($item['hsn_code'] ?: '') . '_' . $item['salesTransType'];
            if (!isset($grouped_items[$key])) {
                $grouped_items[$key] = array(
                    'salesTransType' => $item['salesTransType'],
                    'product_name' => $item['product_name'],
                    'hsn_code' => $item['hsn_code'],
                    'piece' => 0,
                    'gross_wt' => 0,
                    'net_wt' => 0,
                    'taxable' => 0,
                    'total_sgst' => 0,
                    'total_cgst' => 0,
                    'total_igst' => 0,
                    'item_cost' => 0,
                    'rates_sum' => 0,
                    'rates_count' => 0
                );
            }
            $taxable = $item['item_cost'] - $item['item_total_tax'];
            $grouped_items[$key]['piece'] += $item['piece'];
            $grouped_items[$key]['gross_wt'] += $item['gross_wt'];
            $grouped_items[$key]['net_wt'] += $item['net_wt'];
            $grouped_items[$key]['taxable'] += $taxable;
            $grouped_items[$key]['total_sgst'] += $item['total_sgst'];
            $grouped_items[$key]['total_cgst'] += $item['total_cgst'];
            $grouped_items[$key]['total_igst'] += $item['total_igst'];
            $grouped_items[$key]['item_cost'] += $item['item_cost'];
            $grouped_items[$key]['rates_sum'] += floatval($item['rate_per_grm']);
            $grouped_items[$key]['rates_count']++;
        }
    }

    $isIntra = ($h['from_state'] == $h['to_state']);
?>

<div class="PDFReceipt">
    <!-- Metadata Block with correct padding and aligned nested table -->
    <table class="meta-table" style="width:100%; border-collapse:collapse; margin-top:10px;">
        <tr>
            <!-- Left Side Details: Consignor / Consignee Address in Aligned Table -->
            <td style="width:35%; vertical-align:top; padding-right:15px;">
                <table style="width:100%; border-collapse:collapse; font-size:11px; line-height:1.4;">
                    <tr>
                        <td style="width:18%; text-align:left; font-weight:bold; padding:2px 0; vertical-align:top;">GSTIN</td>
                        <td style="width:5%; text-align:center; padding:2px 0; vertical-align:top;">:</td>
                        <td style="width:77%; text-align:left; padding:2px 0; vertical-align:top; white-space:nowrap;"><?php echo $h['from_gst'] ?: ''; ?></td>
                    </tr>
                    <tr>
                        <td style="text-align:left; font-weight:bold; padding:2px 0; vertical-align:top;">FROM</td>
                        <td style="text-align:center; padding:2px 0; vertical-align:top;">:</td>
                        <td style="text-align:left; padding:2px 0; vertical-align:top;">
                            <b><?php echo strtoupper($h['from_branch_name']); ?></b><br>
                            <span style="font-size:10px; font-weight:normal;"><?php echo strtoupper($h['from_address']); ?></span>
                        </td>
                    </tr>
                    <!-- <tr>
                        <td colspan="3" style="padding:4px 0;"></td>
                    </tr> -->
                    <tr>
                        <td style="text-align:left; font-weight:bold; padding:2px 0; vertical-align:top;">TO</td>
                        <td style="text-align:center; padding:2px 0; vertical-align:top;">:</td>
                        <td style="text-align:left; padding:2px 0; vertical-align:top;">
                            <b><?php echo strtoupper($h['to_branch_name']); ?></b><br>
                            <span style="font-size:10px; font-weight:normal;"><?php echo strtoupper($h['to_address']); ?></span>
                        </td>
                    </tr>
                    <tr>
                        <td style="text-align:left; font-weight:bold; padding:2px 0; vertical-align:top;">GSTIN</td>
                        <td style="text-align:center; padding:2px 0; vertical-align:top;">:</td>
                        <td style="text-align:left; padding:2px 0; vertical-align:top; white-space:nowrap;"><?php echo $h['to_gst'] ?: ''; ?></td>
                    </tr>
                </table>
            </td>
            
            <!-- Middle Logo Column -->
            <td style="width:30%; text-align:center; vertical-align:middle;">
                &nbsp;
            </td>
            
            <!-- Right Side Details: Aligned Grid of Metadata Values -->
            <td style="width:35%; vertical-align:top;">
                <table style="width:90%; margin-left:auto; border-collapse:collapse; font-size:11px; line-height:1.4;">
                    <tr>
                        <td style="width:45%; text-align:left; font-weight:bold; padding:2px 0;">INVOICE NO</td>
                        <td style="width:10%; text-align:center; padding:2px 0;">:</td>
                        <td style="width:45%; text-align:left; padding:2px 0; white-space:nowrap;"><?php echo !empty($invoice_no) ? $invoice_no : ($h['bill_no'] ?: ''); ?></td>
                    </tr>
                    <tr>
                        <td style="width:45%; text-align:left; font-weight:bold; padding:2px 0;">TRANS ID</td>
                        <td style="width:10%; text-align:center; padding:2px 0;">:</td>
                        <td style="width:45%; text-align:left; padding:2px 0; white-space:nowrap;"><?php echo $h['bill_id']; ?></td>
                    </tr>
                    <tr>
                        <td style="text-align:left; font-weight:bold; padding:2px 0;">CODE</td>
                        <td style="text-align:center; padding:2px 0;">:</td>
                        <td style="text-align:left; padding:2px 0; white-space:nowrap;"><?php echo $h['bill_no']; ?></td>
                    </tr>
                    <tr>
                        <td style="text-align:left; font-weight:bold; padding:2px 0;">DATE</td>
                        <td style="text-align:center; padding:2px 0;">:</td>
                        <td style="text-align:left; padding:2px 0; white-space:nowrap;"><?php echo $h['bill_date']; ?></td>
                    </tr>
                    <tr>
                        <td style="text-align:left; font-weight:bold; padding:2px 0;">TIME</td>
                        <td style="text-align:center; padding:2px 0;">:</td>
                        <td style="text-align:left; padding:2px 0; white-space:nowrap;"><?php echo date("h:i A", strtotime($h['created_time'])); ?></td>
                    </tr>

                    <tr>
                        <td style="text-align:left; font-weight:bold; padding:2px 0;">SILVER</td>
                        <td style="text-align:center; padding:2px 0;">:</td>
                        <td style="text-align:left; padding:2px 0; white-space:nowrap;"><?php echo number_format(floatval($h['silverrate_1gm']), 2, '.', '') . '/Gm'; ?></td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- Receipt Center Header Title -->
    <div align="center" style="margin-top:40px; margin-bottom:20px;">
        <h2 style="font-size:12px; margin:0;">
            <?php 
            if ($print_type == 1) {
                echo 'INTER STATE TRANSFER - ISSUE VOUCHER
';
            } else if ($print_type == 3) {
                echo 'INTER STATE TRANSFER - ISSUE VOUCHER
';
            } else {
                echo 'INTER STATE TRANSFER - ISSUE VOUCHER
';
            }
            ?>
        </h2>
    </div>

    <!-- Items Table layout matching branch transfer style -->
    <table id="pp" class="table text-center">
        <thead>
            <?php if ($print_type == 1) { ?>
                <!-- Summary Header Columns -->
                <tr style="font-weight:bold; text-transform:uppercase;">
                    <th width="7%;" style="text-align:left;">S.NO</th>
                    <th width="38%;" style="text-align:left;">ITEMS</th>
                    <th width="12%;" style="text-align:right;">PCS</th>
                    <th width="13%;" style="text-align:right;">GWT</th>
                    <th width="15%;" style="text-align:right;">RATE</th>
                    <th width="15%;" style="text-align:right;">AMOUNT</th>
                </tr>
            <?php } else { ?>
                <!-- Detailed Header Columns -->
                <tr style="font-weight:bold; text-transform:uppercase;">
                    <th width="5%;" style="text-align:left;">S.NO</th>
                    <th width="25%;" style="text-align:left;">ITEMS</th>
                    <th width="15%;" style="text-align:left;">TAG CODE</th>
                    <th width="5%;" style="text-align:right;">PCS</th>
                    <th width="10%;" style="text-align:right;">GWT</th>
                    <th width="8%;" style="text-align:right;">VA/G</th>
                    <th width="8%;" style="text-align:right;">MC/G</th>
                    <th width="12%;" style="text-align:right;">RATE</th>
                    <th width="12%;" style="text-align:right;">AMOUNT</th>
                </tr>
            <?php } ?>
        </thead>
        <tbody>
            <tr>
                <td colspan="<?php echo ($print_type == 1) ? 6 : 9; ?>" style="padding:0;">
                    <hr class="<?php echo ($print_type == 1) ? 'sumamry_dashed' : 'detail_dashed'; ?>">
                </td>
            </tr>
            <?php
                $totPcs = 0; $totGross = 0; $totNet = 0; $totTaxable = 0;
                $totSgst = 0; $totCgst = 0; $totIgst = 0; $totCost = 0;
                $hsnBreakup = array();
                $i = 1;

                if ($print_type == 1) {
                    // Grouped Summary Layout Items
                    foreach ($grouped_items as $item) {
                        $totPcs     += $item['piece'];
                        $totGross   += $item['gross_wt'];
                        $totNet     += $item['net_wt'];
                        $totTaxable += $item['taxable'];
                        $totSgst    += $item['total_sgst'];
                        $totCgst    += $item['total_cgst'];
                        $totIgst    += $item['total_igst'];
                        $totCost    += $item['item_cost'];

                        $hsn = $item['hsn_code'] ?: '';
                        if (!isset($hsnBreakup[$hsn])) {
                            $hsnBreakup[$hsn] = array('taxable' => 0, 'sgst' => 0, 'cgst' => 0, 'igst' => 0, 'total' => 0);
                        }
                        $hsnBreakup[$hsn]['taxable'] += $item['taxable'];
                        $hsnBreakup[$hsn]['sgst']    += $item['total_sgst'];
                        $hsnBreakup[$hsn]['cgst']    += $item['total_cgst'];
                        $hsnBreakup[$hsn]['igst']    += $item['total_igst'];
                        $hsnBreakup[$hsn]['total']   += $item['item_cost'];

                        $avgRate = $item['rates_count'] > 0 ? ($item['rates_sum'] / $item['rates_count']) : 0;
                        $typeLabel = isset($typeLabels[$item['salesTransType']]) ? $typeLabels[$item['salesTransType']] : 'Tagged';
                ?>
                    <tr>
                        <td style="text-align:left;"><?php echo $i; ?></td>
                        <td style="text-align:left;"><?php echo $item['product_name'] . ' (' . $typeLabel . ')'; ?></td>
                        <td style="text-align:right;"><?php echo $item['piece']; ?></td>
                        <td style="text-align:right;"><?php echo number_format($item['gross_wt'], 3, '.', ''); ?></td>
                        <td style="text-align:right;"><?php echo moneyFormatIndia(number_format($avgRate, 2, '.', '')); ?></td>
                        <td style="text-align:right;"><?php echo moneyFormatIndia(number_format($item['taxable'], 2, '.', '')); ?></td>
                    </tr>
                <?php
                        $i++;
                    }
                } else {
                    // Detailed Layout Items
                    foreach ($items as $item) {
                        $transType = $item['salesTransType'] ?: 1;
                        $typeLabel = isset($typeLabels[$transType]) ? $typeLabels[$transType] : 'Tagged';
                        $taxable = $item['item_cost'] - $item['item_total_tax'];

                        $totPcs     += $item['piece'];
                        $totGross   += $item['gross_wt'];
                        $totNet     += $item['net_wt'];
                        $totTaxable += $taxable;
                        $totSgst    += $item['total_sgst'];
                        $totCgst    += $item['total_cgst'];
                        $totIgst    += $item['total_igst'];
                        $totCost    += $item['item_cost'];

                        $hsn = $item['hsn_code'] ?: '';
                        if (!isset($hsnBreakup[$hsn])) {
                            $hsnBreakup[$hsn] = array('taxable' => 0, 'sgst' => 0, 'cgst' => 0, 'igst' => 0, 'total' => 0);
                        }
                        $hsnBreakup[$hsn]['taxable'] += $taxable;
                        $hsnBreakup[$hsn]['sgst']    += $item['total_sgst'];
                        $hsnBreakup[$hsn]['cgst']    += $item['total_cgst'];
                        $hsnBreakup[$hsn]['igst']    += $item['total_igst'];
                        $hsnBreakup[$hsn]['total']   += $item['item_cost'];

                        $description = $item['product_name'];
                        if (!empty($item['design_name'])) {
                            $description .= ' (' . $item['design_name'] . ')';
                        }
                ?>
                    <tr>
                        <td style="text-align:left;"><?php echo $i; ?></td>
                        <td style="text-align:left;"><?php echo $description; ?></td>
                        <td style="text-align:left;"><?php echo $item['tag_code'] ?: ''; ?></td>
                        <td style="text-align:right;"><?php echo $item['piece']; ?></td>
                        <td style="text-align:right;"><?php echo number_format($item['gross_wt'], 3, '.', ''); ?></td>
                        <td style="text-align:right;"><?php echo $item['wastage_percent'] > 0 ? number_format($item['wastage_percent'], 2, '.', '') . '%' : ''; ?></td>
                        <td style="text-align:right;"><?php echo $item['mc_value'] > 0 ? (number_format($item['mc_value'], 2, '.', '') . ($item['mc_type'] == 2 ? '/G' : '/P')) : ''; ?></td>
                        <td style="text-align:right;"><?php echo moneyFormatIndia(number_format($item['rate_per_grm'], 2, '.', '')); ?></td>
                        <td style="text-align:right;"><?php echo moneyFormatIndia(number_format($taxable, 2, '.', '')); ?></td>
                    </tr>
                <?php
                        $i++;
                    }
                }
            ?>
            <tr>
                <td colspan="<?php echo ($print_type == 1) ? 6 : 9; ?>" style="padding:0;">
                    <hr class="<?php echo ($print_type == 1) ? 'sumamry_dashed' : 'detail_dashed'; ?>">
                </td>
            </tr>
            <!-- Main Total Row -->
            <tr style="font-weight:bold;">
                <td style="text-align:left;">TOTAL</td>
                <td style="text-align:left;"></td>
                <?php if ($print_type != 1) { ?>
                    <td style="text-align:left;"></td>
                <?php } ?>
                <td style="text-align:right;"><?php echo $totPcs; ?></td>
                <td style="text-align:right;"><?php echo number_format($totGross, 3, '.', ''); ?></td>
                <?php if ($print_type != 1) { ?>
                    <td></td>
                    <td></td>
                <?php } ?>
                <td style="text-align:right;"></td>
                <td style="text-align:right;"><?php echo moneyFormatIndia(number_format($totTaxable, 2, '.', '')); ?></td>
            </tr>
            <tr>
                <td colspan="<?php echo ($print_type == 1) ? 6 : 9; ?>" style="padding:0;">
                    <hr class="<?php echo ($print_type == 1) ? 'sumamry_dashed' : 'detail_dashed'; ?>">
                </td>
            </tr>
            
            <!-- Summary Tax Calculations -->
            <?php if ($totTaxable > 0) { ?>
                <tr style="font-weight: bold;">
                    <td colspan="<?php echo ($print_type == 1) ? 3 : 6; ?>" style="border:none;"></td>
                    <td style="text-align:left; padding:3px; border:none; white-space:nowrap;">SUB TOTAL</td>
                    <td style="text-align:right; padding:3px; border:none;">Rs</td>
                    <td style="text-align:right; padding:3px; border:none;"><?php echo moneyFormatIndia(number_format((float)$totTaxable, 2, '.', '')); ?></td>
                </tr>
            <?php } ?>
            <?php if ($isIntra) { ?>
                <?php if ($totCgst > 0) { ?>
                    <tr style="font-weight: bold;">
                        <td colspan="<?php echo ($print_type == 1) ? 3 : 6; ?>" style="border:none;"></td>
                        <td style="text-align:left; padding:3px; border:none; white-space:nowrap;">CGST (1.5%)</td>
                        <td style="text-align:right; padding:3px; border:none;">Rs</td>
                        <td style="text-align:right; padding:3px; border:none;"><?php echo moneyFormatIndia(number_format((float)$totCgst, 2, '.', '')); ?></td>
                    </tr>
                <?php } ?>
                <?php if ($totSgst > 0) { ?>
                    <tr style="font-weight: bold;">
                        <td colspan="<?php echo ($print_type == 1) ? 3 : 6; ?>" style="border:none;"></td>
                        <td style="text-align:left; padding:3px; border:none; white-space:nowrap;">SGST (1.5%)</td>
                        <td style="text-align:right; padding:3px; border:none;">Rs</td>
                        <td style="text-align:right; padding:3px; border:none;"><?php echo moneyFormatIndia(number_format((float)$totSgst, 2, '.', '')); ?></td>
                    </tr>
                <?php } ?>
            <?php } else { ?>
                <?php if ($totIgst > 0) { ?>
                    <tr style="font-weight: bold;">
                        <td colspan="<?php echo ($print_type == 1) ? 3 : 6; ?>" style="border:none;"></td>
                        <td style="text-align:left; padding:3px; border:none; white-space:nowrap;">IGST (3%)</td>
                        <td style="text-align:right; padding:3px; border:none;">Rs</td>
                        <td style="text-align:right; padding:3px; border:none;"><?php echo moneyFormatIndia(number_format((float)$totIgst, 2, '.', '')); ?></td>
                    </tr>
                <?php } ?>
            <?php } ?>

            <?php
                // Display-only round-off: Total is shown rounded to the nearest rupee
                // (standard round-half-up); the underlying $totCost / stored bill amount
                // stays untouched everywhere else. Round Off row makes the gap explicit.
                $roundedTotCost = round($totCost);
                $roundOff = $roundedTotCost - $totCost;
            ?>
            <?php if ($roundOff != 0) { ?>
                <tr style="font-weight: bold;">
                    <td colspan="<?php echo ($print_type == 1) ? 3 : 6; ?>" style="border:none;"></td>
                    <td style="text-align:left; padding:3px; border:none; white-space:nowrap;">Round Off</td>
                    <td style="text-align:right; padding:3px; border:none;">Rs</td>
                    <td style="text-align:right; padding:3px; border:none;"><?php echo ($roundOff >= 0 ? '' : '-') . moneyFormatIndia(number_format(abs($roundOff), 2, '.', '')); ?></td>
                </tr>
            <?php } ?>

            <!-- Subtotal Divider line -->
            <tr>
                <td colspan="<?php echo ($print_type == 1) ? 5 : 8; ?>" style="border:none;"></td>
                <td style="padding:0; border:none;">
                    <hr class="item_dashed">
                </td>
            </tr>

            <!-- Total Row -->
            <tr style="font-weight: bold;">
                <td colspan="<?php echo ($print_type == 1) ? 3 : 6; ?>" style="border:none;"></td>
                <td style="text-align:left; padding:3px; border:none; white-space:nowrap;">Total</td>
                <td style="text-align:right; padding:3px; border:none;">Rs</td>
                <td style="text-align:right; padding:3px; border:none; font-size:11px;"><?php echo moneyFormatIndia(number_format((float)$roundedTotCost, 2, '.', '')); ?></td>
            </tr>
            
            <!-- Total Divider line -->
            <tr>
                <td colspan="<?php echo ($print_type == 1) ? 5 : 8; ?>" style="border:none;"></td>
                <td style="padding:0; border:none;">
                    <hr class="item_dashed">
                </td>
            </tr>
            
            <!-- Due Amount Row -->
            <tr style="font-weight: bold;">
                <td colspan="<?php echo ($print_type == 1) ? 3 : 6; ?>" style="border:none;"></td>
                <td style="text-align:left; padding:3px; border:none; white-space:nowrap;">DUE AMOUNT</td>
                <td style="text-align:right; padding:3px; border:none;">Rs</td>
                <td style="text-align:right; padding:3px; border:none;"><?php echo moneyFormatIndia(number_format((float)$totCost, 2, '.', '')); ?></td>
            </tr>
        </tbody>
    </table>

    <!-- Amount in Words -->
    <div style="margin-top: 15px; margin-bottom: 15px; padding-left: 5px;">
        <div style="font-size:11px; font-style:italic;">
            <span>(<?php echo ucwords(convert_number_to_words($totCost)); ?> Only)</span>
        </div>
    </div>

    <!-- HSN Summary Table -->
    <div style="margin-top:20px; width: 65%; padding-left: 5px;">
        <div style="font-weight:bold; text-transform:uppercase; border-bottom:1px solid #000; padding-bottom:3px; font-size:10px; margin-bottom:5px;">HSN Summary</div>
        <table id="pp" style="width:100%;">
            <thead>
                <tr style="font-weight:bold;">
                    <th style="text-align:left; font-size:9px; padding:3px 2px;">HSN Code</th>
                    <th style="text-align:right; font-size:9px; padding:3px 2px;">Taxable</th>
                    <?php if ($isIntra) { ?>
                        <th style="text-align:right; font-size:9px; padding:3px 2px;">CGST</th>
                        <th style="text-align:right; font-size:9px; padding:3px 2px;">SGST</th>
                    <?php } else { ?>
                        <th style="text-align:right; font-size:9px; padding:3px 2px;">IGST</th>
                    <?php } ?>
                    <th style="text-align:right; font-size:9px; padding:3px 2px;">Total</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <th colspan="<?php echo $isIntra ? 5 : 4; ?>" style="padding:0;">
                        <hr class="item_dashed">
                    </th>
                </tr>
                <?php foreach ($hsnBreakup as $hsn => $vals) { ?>
                    <tr>
                        <td style="text-align:left; font-size:9px; padding:3px 2px;"><strong><?php echo $hsn; ?></strong></td>
                        <td style="text-align:right; font-size:9px; padding:3px 2px;"><?php echo moneyFormatIndia(number_format($vals['taxable'], 2, '.', '')); ?></td>
                        <?php if ($isIntra) { ?>
                            <td style="text-align:right; font-size:9px; padding:3px 2px;"><?php echo moneyFormatIndia(number_format($vals['cgst'], 2, '.', '')); ?></td>
                            <td style="text-align:right; font-size:9px; padding:3px 2px;"><?php echo moneyFormatIndia(number_format($vals['sgst'], 2, '.', '')); ?></td>
                        <?php } else { ?>
                            <td style="text-align:right; font-size:9px; padding:3px 2px;"><?php echo moneyFormatIndia(number_format($vals['igst'], 2, '.', '')); ?></td>
                        <?php } ?>
                        <td style="text-align:right; font-size:9px; padding:3px 2px;"><strong><?php echo moneyFormatIndia(number_format($vals['total'], 2, '.', '')); ?></strong></td>
                    </tr>
                <?php } ?>
                <tr>
                    <th colspan="<?php echo $isIntra ? 5 : 4; ?>" style="padding:0;">
                        <hr class="item_dashed">
                    </th>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Remarks -->
    <?php if ($h['remark'] != '' && strlen($h['remark']) > 1) { ?>
        <div style="font-weight:bold; margin-top:20px; padding-left:5px;">
            REMARK &nbsp;: <?php echo htmlspecialchars($h['remark']); ?>
        </div>
    <?php } ?>

    <!-- Signature Footer matching branch transfer signature block -->
    <br><br><br>
    <div class="row" style="text-transform:uppercase; padding-left:50px; display:inline-block; width:100%; font-size:11px; font-weight:bold;">
        <label>Transfered by</label>
        <label style="margin-left:25%;">Verified by</label>
        <label style="margin-left:30%;">Received by</label>
    </div>
</div>

</body>
</html>
