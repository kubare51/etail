<html>

<head>
	<meta charset="utf-8">
	<title>Tax Invoice</title>
	<style>
		body {
			font-size: 12px;
		}

		table {
			width: 100%;
		}

		table,
		th,
		td {
			padding: 1px;
			border: 1px solid black;
			border-collapse: collapse;
		}

		.header_label {
			height: 30px !important;
			vertical-align: top;
			max-height: 10px !important;
		}

		.alignRight {
			text-align: right;
		}

		.rateborders {
			border-top: none;
			border-bottom: none;
			height: 20px;
			text-align: right;
			font-size: 13px;
		}

		.descheading {
			text-align: center;
			font-weight: bold;
			height: 30px;
			font-size: 12px;
			border-top: none;
		}

		#commodity_display tbody td {
			border-top-style: none;
			border-bottom-style: none;
		}

		.company_details {
			font-size: 11px;
		}
	</style>
</head>

<body>
	<div style="text-align: center">
		<span style="padding-left: 10px; font-weight: bold; font-size: 16px;">Tax Invoice</span>
	</div>

	<?php
	$to_branch_details = isset($consignee) ? $consignee : array();
	$seller_name = strtoupper(isset($comp_details['company_name']) ? $comp_details['company_name'] : '');
	$invoice_no = ($billing['branch_code'] != '' ? $billing['branch_code'] . '/' : '') . ($billing['fin_year_code'] != '' ? $billing['fin_year_code'] . '/' : '') . $billing['bill_no'];
	?>

	<table height="170px">
		<tr>
			<td width="49%" rowspan="2">
				<div class="company_name"><b><?php echo $seller_name; ?></b></div>
				<div class="company_details">(HO) <?php echo $comp_details['address1']; ?></div>
				<div class="company_details"><?php echo $comp_details['address2']; ?></div>
				<div class="company_details"><?php echo $comp_details['city']; ?>.</div>
				<?php echo ($comp_details['email'] ? '<div>Email : ' . $comp_details['email'] . ' </div>' : ''); ?>
				<?php echo ($comp_details['gst_number'] ? '<div>GST No : ' . $comp_details['gst_number'] . ' </div>' : ''); ?>
				<div><?php echo $comp_details['state']; ?></div>
			</td>
			<td class="header_label" width="25%">
				Invoice No<br>
				<span><b><?php echo $invoice_no; ?></b></span>
			</td>
			<td class="header_label" width="25%">
				Dated<br>
				<span><b><?php echo $billing['bill_date']; ?></b></span>
			</td>
		</tr>
		<tr>
			<td class="header_label">Delivery Note</td>
			<td class="header_label">Mode/Terms of Payment</td>
		</tr>
		<tr>
			<td width="50%" rowspan="2">
				<label>Consignee (Ship To)</label><br>
				<div style="display: inline-block;">
					<div class="company_name"><b><?php echo $seller_name; ?></b></div>
					<div><?php echo $to_branch_details['address1']; ?> <?php echo $to_branch_details['city']; ?></div>
					<div><?php echo $to_branch_details['address2']; ?></div>
					<?php echo (!empty($to_branch_details['email']) ? '<div>Email : ' . $to_branch_details['email'] . ' </div>' : ''); ?>
					<?php echo (!empty($to_branch_details['gst_number']) ? '<div>GST No : ' . $to_branch_details['gst_number'] . ' </div>' : ''); ?>
					<div><?php echo $to_branch_details['state']; ?></div>
				</div>
			</td>
			<td class="header_label">Reference No. &amp; Date.</td>
			<td class="header_label">Other Ref</td>
		</tr>
		<tr>
			<td class="header_label">Buyer Order No</td>
			<td class="header_label"></td>
		</tr>
	</table>

	<table height="170px">
		<tr>
			<td width="50%" valign="top" rowspan="3">
				<label>Buyer (Bill To)</label><br>
				<div class="company_name"><b><?php echo $seller_name; ?></b></div>
				<div><?php echo $to_branch_details['address1']; ?> <?php echo $to_branch_details['city']; ?></div>
				<div><?php echo $to_branch_details['address2']; ?></div>
				<?php echo (!empty($to_branch_details['email']) ? '<div>Email : ' . $to_branch_details['email'] . ' </div>' : ''); ?>
				<?php echo (!empty($to_branch_details['gst_number']) ? '<div>GST No : ' . $to_branch_details['gst_number'] . ' </div>' : ''); ?>
				<div><?php echo $to_branch_details['state']; ?></div>
			</td>
			<td class="header_label" width="25%">Dispatch Doc No</td>
			<td class="header_label" width="25%">Delivery Note Date</td>
		</tr>
		<tr>
			<td class="header_label">Dispatch Through</td>
			<td class="header_label">Destination<br><b><?php echo $to_branch_details['city']; ?></b></td>
		</tr>
		<tr>
			<td colspan="2" style="border-bottom: none; height: 100px; vertical-align: top; text-align: left;">Terms of Delivery</td>
		</tr>
	</table>

	<?php
	$item_details = isset($item_details) ? $item_details : array();
	$i = 1;
	$total_pcs = 0;
	$total_wt = 0;
	$total_nwt = 0;
	$total_item_cost = 0;
	$total_taxable_amt = 0;
	$cgst_cost = 0;
	$sgst_cost = 0;
	$igst_cost = 0;
	$tax_percentage = 0;
	?>
	<table id="commodity_display">
		<tr>
			<th class="descheading" style="border-left: none;">S.NO</th>
			<th class="descheading">DESCRIPTION OF GOODS</th>
			<th class="descheading">HSN/SAC</th>
			<th class="descheading">PCS(NOS)</th>
			<th class="descheading">GWT(GMS)<br></th>
			<th class="descheading">NWT(GMS)<br></th>
			<th class="descheading">WT(CTS)<br></th>
			<th class="descheading">RATE<br></th>
			<th class="descheading">AMOUNT(RS)<br></th>
		</tr>
		<?php foreach ($item_details as $row) :
			$row_pcs = floatval($row['piece']);
			$row_gwt = floatval($row['gross_wt']);
			$row_nwt = floatval($row['net_wt']);
			$row_item_cost = floatval($row['item_cost']);
			$row_item_tax = floatval($row['item_total_tax']);
			$taxable_amt = $row_item_cost - $row_item_tax;
			$rate = ($row_gwt > 0) ? ($taxable_amt / $row_gwt) : 0;

			$total_pcs += $row_pcs;
			$total_wt += $row_gwt;
			$total_nwt += $row_nwt;
			$total_item_cost += $row_item_cost;
			$total_taxable_amt += $taxable_amt;
			$cgst_cost += floatval($row['total_cgst']);
			$sgst_cost += floatval($row['total_sgst']);
			$igst_cost += floatval($row['total_igst']);
			$tax_percentage = $row['tax_percentage'];
		?>
			<tr>
				<td class="alignRight" style="text-align: center; border-left: none;"><?php echo $i; ?></td>
				<td class="alignRight" style="text-align: left;">&nbsp;<?php echo $row['category_name']; ?></td>
				<td><?php echo $row['hsn_code']; ?></td>
				<td class="alignRight"><?php echo $row_pcs; ?></td>
				<td class="alignRight"><?php echo moneyFormatIndia(number_format($row_gwt, 3, '.', '')); ?></td>
				<td class="alignRight"><?php echo moneyFormatIndia(number_format($row_nwt, 3, '.', '')); ?></td>
				<td class="alignRight"></td>
				<td class="alignRight"><?php echo moneyFormatIndia(number_format($rate, 2, '.', '')); ?></td>
				<td class="alignRight"><?php echo moneyFormatIndia(number_format($taxable_amt, 2, '.', '')); ?></td>
			</tr>
		<?php $i++;
		endforeach; ?>

		<tr style="font-weight: bold;">
			<td class="rateborders" style="border-left: none;"></td>
			<td class="rateborders"></td>
			<td class="rateborders"></td>
			<td class="rateborders"></td>
			<td class="rateborders"></td>
			<td class="rateborders"></td>
			<td class="rateborders"></td>
			<td class="rateborders"></td>
			<td class="rateborders" style="border: 1px solid;"><?php echo moneyFormatIndia(number_format($total_taxable_amt, 2, '.', '')); ?></td>
		</tr>

		<?php if ($cgst_cost > 0) : ?>
			<tr>
				<td class="rateborders"></td>
				<td class="alignRight" style="white-space: nowrap;">CGST</td>
				<td></td>
				<td></td>
				<td></td>
				<td></td>
				<td></td>
				<td></td>
				<td class="alignRight"><?php echo moneyFormatIndia(number_format($cgst_cost, 2, '.', '')); ?></td>
			</tr>
			<tr>
				<td class="rateborders"></td>
				<td class="alignRight" style="white-space: nowrap;">SGST</td>
				<td></td>
				<td></td>
				<td></td>
				<td></td>
				<td></td>
				<td></td>
				<td class="alignRight"><?php echo moneyFormatIndia(number_format($sgst_cost, 2, '.', '')); ?></td>
			</tr>
		<?php elseif ($igst_cost > 0) : ?>
			<tr>
				<td class="rateborders"></td>
				<td class="alignRight" style="white-space: nowrap;">Output - IGST <?php echo number_format($tax_percentage, 0, '.', '') . '%'; ?></td>
				<td></td>
				<td></td>
				<td></td>
				<td></td>
				<td></td>
				<td></td>
				<td class="alignRight"><?php echo moneyFormatIndia(number_format($igst_cost, 2, '.', '')); ?></td>
			</tr>
		<?php endif; ?>

		<tr>
			<td class="rateborders" style="border-top: 1px solid #000; border-left: none;"></td>
			<td class="rateborders" style="border-top: 1px solid #000;"></td>
			<td class="rateborders" style="border-top: 1px solid #000;"><b>TOTAL</b></td>
			<td class="rateborders" style="border-top: 1px solid #000;"><b><?php echo $total_pcs; ?></b></td>
			<td class="rateborders" style="border-top: 1px solid #000;"><b><?php echo moneyFormatIndia(number_format($total_wt, 3, '.', '')) . ' Gms'; ?></b></td>
			<td class="rateborders" style="border-top: 1px solid #000;"><b><?php echo moneyFormatIndia(number_format($total_nwt, 3, '.', '')) . ' Gms'; ?></b></td>
			<td class="rateborders" style="border-top: 1px solid #000;"></td>
			<td class="rateborders" style="border-top: 1px solid #000;"></td>
			<td class="rateborders" style="border-top: 1px solid #000;"><b><?php echo moneyFormatIndia(number_format($total_item_cost, 2, '.', '')); ?></b></td>
		</tr>
	</table>

	<?php $amt_in_words_total = $this->ret_billing_model->no_to_words(number_format($total_item_cost, 2, '.', '')); ?>

	<table height="50px" style="border-bottom: none;">
		<tr>
			<td style="border-right: none; border-bottom: none; vertical-align: top;">
				Amount Chargable (in words)<br>
				<b>INR <?php echo $amt_in_words_total; ?></b>
				<?php if ($billing['bill_type'] != 14 && !empty($billing['irnno'])) : ?>
					<div class="row"><b>IRN : <?php echo $billing['irnno']; ?></b></div>
				<?php endif; ?>
			</td>
			<td style="border-left: none; border-bottom: none; text-align: right;">E &amp; O E</td>
		</tr>
	</table>

	<table height="100px" style="border-top-style: none;">
		<tr>
			<td style="border-top: none; border-right: none;"></td>
			<td width="50%" style="border: none; vertical-align: bottom;">
				<b>No E-way bill is required to be generated as per the Goods covered under this Invoice are exempted as per serial NO.4/5 to the Annexure to Rule 138(14) of the CGST Rule</b>
			</td>
			<td width="50%">
				<div style="text-align: right;"><b><?php echo $seller_name; ?></b></div>
				<div style="height: 55px;"></div>
				<div style="text-align: right;">Authority Signatory</div>
			</td>
		</tr>
	</table>

	<div style="text-align: center; margin-top: 2px;">This is a Computer Generated Document</div>
</body>

</html>
<script type="text/javascript">
	window.onload = function() {
		window.onafterprint = function() {
			window.close();
		};
		window.print();
	};
</script>
