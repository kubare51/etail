<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Stock Report - Thermal Print</title>
    <style>
        @page {
            margin: 0px;
        }
        html {
            margin: 0px;
            padding: 0px;
        }
        body {
            font-family: Arial, "Helvetica Neue", Helvetica, sans-serif;
            font-size: 10px;
            color: #000;
            margin: 16px 16px 16px 16px;
            padding: 0px;
        }
        .thermal-container {
            width: 100%;
        }
        .meta-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
            font-size: 9.5px;
        }
        .meta-table td {
            border: none !important;
            padding: 0 !important;
        }
        .thermal-header {
            text-align: center;
            margin-bottom: 8px;
            margin-top: 4px;
        }
        .thermal-company {
            font-size: 12.5px;
            font-weight: bold;
            text-transform: uppercase;
            line-height: 1.2;
        }
        .thermal-subtitle-shop {
            font-size: 10.5px;
            font-weight: bold;
            text-transform: uppercase;
            line-height: 1.2;
        }
        .thermal-branch {
            font-size: 11.5px;
            font-weight: bold;
            text-transform: uppercase;
            line-height: 1.2;
            margin-top: 2px;
        }
        .thermal-group-info {
            font-size: 9.5px;
            margin-top: 5px;
            line-height: 1.3;
        }
        .thermal-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
            font-size: 9.5px;
            page-break-inside: avoid !important;
        }
        .thermal-table tr, .thermal-table td, .thermal-table th {
            page-break-inside: avoid !important;
        }
        .thermal-table th, 
        .thermal-table td {
            border: 1px solid #000;
            padding: 3px 5px;
            word-break: break-word;
        }
        .thermal-table th {
            font-weight: bold;
            text-align: left;
        }
        .col-pcs {
            text-align: right !important;
            width: 30%;
        }
        .total-row td {
            font-weight: bold;
            border-top: 2px solid #000 !important;
            border-bottom: 2px solid #000 !important;
        }
        .text-left { text-align: left; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .bold { font-weight: bold; }
    </style>
</head>
<body>

    <div class="thermal-container">

        <!-- Top meta: print date/time (left) and report tag (right) -->
        <table class="meta-table">
            <tr>
                <td style="text-align: left;"><?php echo date('n/j/y, g:i A'); ?></td>
                <td style="text-align: right;">Tag Report</td>
            </tr>
        </table>

        <!-- Header: company, branch, grouping, report period -->
        <div class="thermal-header">
            <div class="thermal-company"><?php echo !empty($company_name) ? strtoupper($company_name) : 'GPJ GOLD PALACE'; ?></div>
            <div class="thermal-subtitle-shop">JEWELLERY</div>
            <?php if (!empty($branch_name)) { ?>
                <div class="thermal-branch"><?php echo strtoupper($branch_name); ?></div>
            <?php } ?>

            <div class="thermal-group-info">
                <strong>Tag Report Group By <?php echo !empty($group_label) ? $group_label : 'Product'; ?></strong>
                <?php if (!empty($dt_range)) { ?>
                    <br><span>Group - <?php echo str_replace(' - ', ' to ', $dt_range); ?></span>
                <?php } else { ?>
                    <br><span>Group - <?php echo date('d/m/Y'); ?></span>
                <?php } ?>
                <?php if (isset($list_by) && $list_by == 1) { ?>
                    <br><span>Tag Items Only</span>
                <?php } elseif (isset($list_by) && $list_by == 2) { ?>
                    <br><span>Non Tag Items Only</span>
                <?php } ?>
            </div>
        </div>

        <!-- Closing stock: product name and number of pieces -->
        <table class="thermal-table">
            <thead>
                <tr>
                    <th class="text-left">Product Group</th>
                    <th class="col-pcs">Tags</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)) { ?>
                    <tr>
                        <td colspan="2" class="text-center">No stock found for the selected filters.</td>
                    </tr>
                <?php } else {
                    foreach ($rows as $product_name => $pcs) { ?>
                        <tr>
                            <td class="text-left"><?php echo strtoupper($product_name); ?></td>
                            <td class="col-pcs"><?php echo (float) $pcs == (int) $pcs ? (int) $pcs : number_format($pcs, 3, '.', ''); ?></td>
                        </tr>
                    <?php }
                } ?>
            </tbody>
            <tfoot>
                <tr class="total-row">
                    <td class="text-left bold">Total</td>
                    <td class="col-pcs bold"><?php echo (float) $total_pcs == (int) $total_pcs ? (int) $total_pcs : number_format($total_pcs, 3, '.', ''); ?></td>
                </tr>
            </tfoot>
        </table>

    </div>

</body>
</html>
