<!doctype html>
<html>

<head>
	<meta charset="utf-8">
	<title>Receipt</title>
	<link rel="stylesheet" href="<?php echo base_url(); ?>assets/css/receipt.css">
	<!--	<link rel="stylesheet" href="<?php echo base_url(); ?>assets/css/receipt_temp.css">-->
</head>

<body style="margin-top: 20px;">
	<span class="PDFReceipt">
		<table style="width: 100%; border: none; border-collapse: collapse; margin-bottom: 15px;">
			<tr>
				<td style="width: 50%; vertical-align: middle; border: none; padding: 0;">
					<?php
					$logo_path = FCPATH . 'assets/img/receipt_logo.png';
					if (file_exists($logo_path)) { ?>
						<img alt="" style="height:100px;" src="<?php echo base_url(); ?>assets/img/receipt_logo.png?<?php echo time(); ?>">
					<?php } else { ?>
						<div style="height:100px; line-height:100px;">
							<span style="font-size:24px; font-weight:bold; color:#333; letter-spacing:2px;">
								<?php echo isset($comp_details['name']) ? strtoupper($comp_details['name']) : ''; ?>
							</span>
						</div>
					<?php } ?>
				</td>
				<td style="width: 50%; vertical-align: middle; text-align: right; border: none; padding: 0;">
					<div class="address" align="right" style="border-top: none; padding-top: 0; font-size: 13px; line-height: 1.5;">
						<?php if (!empty($paid_branch['name'])) { ?>
							<span style="font-weight: bold; font-size: 15px; color: #222; display: block; margin-bottom: 4px;">
								<?php echo strtoupper($paid_branch['name']); ?>
							</span>
						<?php } ?>
						<?php echo ($paid_branch['address1'] != '') ? $paid_branch['address1'] . ' ,' : ''; ?>
						<?php echo ($paid_branch['address2'] != '') ? $paid_branch['address2'] . ' ,'  : ''; ?>
						<?php echo ($paid_branch['city'] != '') ? $paid_branch['city'] . '  ' . $paid_branch['pincode'] . ' ,'  : ''; ?>
						<?php echo ($paid_branch['state'] != '') ? $paid_branch['state'] . ' ,'  : ''; ?>
						<?php echo ($paid_branch['country'] != '') ? $paid_branch['country'] . ' .'  : ''; ?>
						<?php if ($paid_branch['phone'] != '') { ?><p style="margin: 4px 0 0 0; font-size: 13px; height: auto;">Phone : <?php echo $paid_branch['phone']; ?></p><?php } ?>
						<?php if ($paid_branch['mobile'] != '') { ?><p style="margin: 4px 0 0 0; font-size: 13px; height: auto;">Mobile : <?php echo $paid_branch['mobile']; ?></p><?php } ?>
					</div>
				</td>
			</tr>
		</table>

		<div class="heading">Receipt</div>
		<table style="width: 100%; border: none; border-collapse: collapse;">
			<tr>
				<td style="width: 54%; vertical-align: top; border: none; padding: 0 5px 0 0;">
					<table class="meta" style="width: 100%">
						<tr>
							<th><span>Customer</span></th>
							<td><span><?php echo $records[0]['firstname'] . ' ' . $records[0]['lastname']; ?></span></td>
						</tr>
						<tr>
							<th><span>Address</span></th>
							<td><span><?php
								$addr_parts = array();
								if ($records[0]['address1'] != '') $addr_parts[] = $records[0]['address1'];
								if ($records[0]['address2'] != '') $addr_parts[] = $records[0]['address2'];
								if ($records[0]['address3'] != '') $addr_parts[] = $records[0]['address3'];
								if ($records[0]['city'] != '') $addr_parts[] = $records[0]['city'] . ' ' . $records[0]['pincode'];
								if ($records[0]['state'] != '') $addr_parts[] = $records[0]['state'];
								if ($records[0]['country'] != '') $addr_parts[] = $records[0]['country'];
								echo implode(', ', $addr_parts);
							?></span></td>
						</tr>
						<tr>
							<th><span>Mobile</span></th>
							<td><span><?php echo $records[0]['mobile']; ?></span></td>
						</tr>
					</table>
				</td>
				<td style="width: 46%; vertical-align: top; border: none; padding: 0 0 0 5px;">
					<table class="meta" style="width: 100%">
						<tr>
							<?php
							if ($records[0]['receipt_no'] == '' || $records[0]['receipt_no'] == NULL) { ?>
								<th><span>Acc No</span></th>
								<td><span><?php echo $records[0]['id_payment']; ?></span></td>
							<?php } else { ?>
								<th><span>Receipt #</span></th>
								<td><span><?php echo $records[0]['receipt_no']; ?></span></td>
							<?php } ?>
						</tr>
						<tr>
							<th><span>Date</span></th>
							<td><span><?php echo $records[0]['date_payment']; ?></span></td>
						</tr>
						<tr>
							<th><span>Mode</span></th>
							<td><span><?php echo $records[0]['payment_mode']; ?></span></td>
						</tr>
						<tr>
							<th><span>Group code</span></th>
							<td><span><?php echo $records[0]['sch_code']; ?></span></td>
						</tr>
						<tr>
							<th><span>A/c No</span></th>
							<td><span><?php echo $records[0]['scheme_acc_number']; ?></span></td>
						</tr>
						<tr>
							<th><span>A/c Name</span></th>
							<td><span><?php echo $records[0]['account_name']; ?></span></td>
						</tr>
						<tr>
							<th><span>Paid Ins</span></th>
							<td><span><?php echo ($records[0]['paid_installments'] . '/' . $records[0]['total_installments']); ?></span></td>
						</tr>
						<tr>
							<th><span>Mobile</span></th>
							<td><span><?php echo $records[0]['mobile']; ?></span></td>
						</tr>
					</table>
				</td>
			</tr>
		</table>
		<?php $show_wgt = $records_sch['is_Weight'] == 1 ? TRUE : FALSE; ?>
		<table class="inventory" style="margin-top: 15px;">
			<!--<thead>-->
			<tr>
				<!--<th><span contenteditable>Item</span></th>-->
				<th><span contenteditable>Description</span></th>
				<?php if ($show_wgt) { ?>
					<th><span contenteditable>Gold Rate</span></th>
					<th><span contenteditable>Weight</span></th>
				<?php } ?>
					<th><span contenteditable>Amount</span></th>
				<?php if ($show_wgt) { ?>
					<th><span contenteditable>Total Weight</span></th>
				<?php } ?>
				<th><span contenteditable>Total Amount</span></th>
			</tr>
			<!--</thead>
				<tbody>-->
			<tr>
				<!--<td><span contenteditable>Front End Consultation</span></td>-->
				<td><span contenteditable><?php echo $records[0]['scheme_name']; ?></span></td>
				<?php if ($show_wgt) { ?>
					<td style="text-align: right"><?php echo ($records[0]['scheme_type'] == '0' ? '-' : ($records[0]['metal_rate'] != '-' ? $comp_details['currency_symbol'] . ' ' . $records[0]['metal_rate'] : '-')); ?></td>
					<td style="text-align: right"><span><?php echo ($records[0]['scheme_type'] == '0' ? '-' : $records[0]['metal_weight'] . ' g'); ?></span></td>

				<?php } ?>
				<td style="text-align: right"><span data-prefix><?php echo $comp_details['currency_symbol']; ?> </span><span><?php echo $records[0]['payment_amount']; ?></span></td>
				<?php
				if ($show_wgt) { ?>

					<td style="text-align: right"><span><?php echo ($records[0]['scheme_type'] == '0' ? '-' : $records['total_weight']  . ' g') ?></span></td>
				<?php } ?>
				<td>
					<span style="text-align: right"><?php echo $comp_details['currency_symbol']; ?> <span><?php echo $records['total_amt'] ?></span>
				</td>
			</tr>
			<tr>
				<td colspan="<?php echo ($show_wgt ? 6 : 2) ?>"><?php echo "Rupees " . ucwords($records[0]['amount_in_words']); ?> Only</td>
			</tr>
			<?php if ($records[0]['due_type'] == 'D') { ?>
				<tr>
					<td colspan="4" align="center" style="color: #ff0000;">Paid by <?php echo $comp_details['company_name']; ?></td>
				</tr>
			<?php } ?>
			<!--</tbody>-->
		</table>
		<p></p>
		<table class="balance" style="width: 50%;">
			<?php if ($records[0]['add_charges'] > 0) { ?>
				<tr>
					<th><span><?php echo  $records[0]['payment_type'] == 'Payu Checkout' ? $records[0]['charge_head'] : 'Additional Charge' ?></span></th>
					<td><span data-prefix><?php echo $comp_details['currency_symbol']; ?> </span><span><?php echo $records[0]['add_charges']; ?></span></td>
				</tr>
			<?php } ?>

			<?php if ($records[0]['gst'] > 0) { ?>
				<tr>
					<th><span>GST <?php echo $records[0]['gst'] . ' %'; ?></span></th>
					<td style="text-transform: uppercase;"><span data-prefix><?php echo $comp_details['currency_symbol']; ?> </span>
						<span>
							<?php
							$gst_amt = $records[0]['gst_amount'];
							echo $gst_amt;
							/*$gst_calc = 0;
    					$gst_amt = 0;
    					if($records[0]['gst'] > 0){
    						if($records[0]['gst_type'] == 1 ){
    							$gst_calc = $records[0]['payment_amount']*($records[0]['gst']/100);
    							$gst_amt = $gst_calc;
    							echo number_format($gst_calc,'2','.','');
    						}
    						else{
    							$gst_calc = $records[0]['payment_amount']-($records[0]['payment_amount']*(100/(100+$records[0]['gst'])));
    							echo number_format($gst_calc,'2','.','');
    						}						
    					}
    					else{
    						echo '-';
    					}*/

							?></span><br />

						<?php foreach ($gstSplitup as $data) {
							if ($data['type'] != NULL && $records[0]['gst'] > 0) {
								if (($records[0]['id_state'] == $comp_details['id_state']) || ($records[0]['id_state'] == '' || $records[0]['id_state'] == NULL)) {
									if ($data['type'] == 0) { //$data['type'] -> same or different state flag
										$calc_amt = $gst_amt / 2;
										echo '<br/><div >' . $data['splitup_name'] . '(' . $data['percentage'] . '%)   - ' . $comp_details['currency_symbol'] . ' ' . number_format($calc_amt, "2", ".", "") . '</div> ';
									}
								} else {
									if ($data['type'] == 1) {
										$calc_amt = $gst_amt / 2;
										echo '<br/><div >' . $data['splitup_name'] . '(' . $data['percentage'] . '%)   - ' . $comp_details['currency_symbol'] . ' ' . number_format($calc_amt, "2", ".", "") . '</div> ';
									}
								}
							}
						} ?>

					</td>
				</tr>
			<?php } ?>
			<tr>
				<th><span>Payment Mode </span></th>
				<td><span><?php echo $records[0]['multi_modes']; ?></span></td>
				<!-- <td><span data-prefix><?php echo $comp_details['currency_symbol']; ?> </span><span ><?php echo number_format($records[0]['payment_amount'] + ($records[0]['gst_type'] == 1 ? $gst_amt : 0) + $records[0]['add_charges'], "2", ".", ""); ?></span></td> -->
			</tr>
			<tr style="display: none;">
				<th><span>Next Due</span></th>
				<td><span><?php echo $records[0]['next_due']; ?></span></td>
			</tr>
			<?php if ($records[0]['maturity_date'] != null) { ?>
				<tr>
					<th><span>Maturity</span></th>
					<td><span><?php echo $records[0]['maturity_date']; ?></span></td>
				</tr>
			<?php } ?>
		</table>
		<p></p>

		<aside>
			<div>
				<p class="txtAckowlege"></p>
				<!-- <p class="txtAckowlege">This is system generated receipt, keep this as an acknowledgement.</p> -->
			</div>
			<div class="signature-container">
				<div class="signature-row">
					<p class="customer-sign">CUSTOMER SIGN</p>
					<div class="company-block">
						<!--<p class="company-name">FOR WIN GOLD LLP</p>-->
						<p class="company-name ">FOR <?php echo isset($records[0]['payment_branch']) ? $records[0]['payment_branch'] : 'All Branch' ?></p>
					</div>
				</div>
			</div>
			<div>
				<p class="employeeId">generated by : <?php echo ($records[0]['login_emp_name']) ?></p>
			</div>


		</aside>
	</span>
</body>

</html>