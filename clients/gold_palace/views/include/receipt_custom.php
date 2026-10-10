<!doctype html>
<html>
	<head>
		<meta charset="utf-8">
		<title>Receipt</title>
		<link rel="stylesheet" href="<?php echo base_url();?>assets/css/cus_receipt.css">
		<!--	<link rel="stylesheet" href="<?php echo base_url();?>assets/css/receipt_temp.css">-->
		<style>
			.PDF_CusReceipt, .PDF_CusReceipt * { line-height: 1.2; }
			.PDF_CusReceipt .meta th, .PDF_CusReceipt .meta td { padding: 1px 0; }
			.PDF_CusReceipt hr { margin: 3px 0; }
		</style>

	</head>
	
	<body class="margin">
	<div class="PDF_CusReceipt">
	    <div class="" style="font-weight: 400 !important; font-size:16px !important;margin-top:-12px;" align="center">
						
			</div>
			<?php
			if (!function_exists('gp_ordinal')) {
				function gp_ordinal($n) {
					$n = (int)$n;
					if ($n % 100 >= 11 && $n % 100 <= 13) return $n.'th';
					$suffix = array(1 => 'st', 2 => 'nd', 3 => 'rd');
					return $n.(isset($suffix[$n % 10]) ? $suffix[$n % 10] : 'th');
				}
			}
			if (!function_exists('gp_inr_format')) {
				function gp_inr_format($amount) {
					$amount = (float)$amount;
					$decimals = ($amount == floor($amount)) ? 0 : 2;
					$parts = explode('.', number_format($amount, $decimals, '.', ''));
					$last3 = strlen($parts[0]) > 3 ? substr($parts[0], -3) : $parts[0];
					$rest  = strlen($parts[0]) > 3 ? substr($parts[0], 0, -3) : '';
					if ($rest !== '') {
						$last3 = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest).','.$last3;
					}
					return $last3.(isset($parts[1]) ? '.'.$parts[1] : '');
				}
			}

			 $total_installment=$records[0]['total_installments'];
            //  $maturitydate = date('d-m-Y', strtotime("+".$total_installment." months", strtotime($records_sch['start_date'])));

			if (!empty($records[0]['maturity_date'])) {

				$maturitydate = date('d-m-Y', strtotime($records[0]['maturity_date']));
			} else {

				$total_installment = $records[0]['total_installments'];
					$maturitydate = date('d-m-Y', strtotime("+" . $records[0]['maturity_days'] . " days" , strtotime($records[0]['start_date'])));

					$maturitydate = date('d-m-Y', strtotime("+" . $total_installment . " months", strtotime($records[0]['start_date']))); 
				if($scheme['maturity_type'] == 2){
					$maturitydate = date('d-m-Y', strtotime("+" . $records[0]['maturity_days']+ $records[0]['closing_maturity_days'] . " days", strtotime($records[0]['start_date'])));

				}
				else{
					$maturitydate = date('d-m-Y', strtotime("+" .$records[0]['closing_maturity_days'] . " days", strtotime($records[0]['start_date'])));
				} 
			}
		
            ?>
			<span style="float:right;"><?php echo 'Print taken on : '.date("d-m-Y H:i:s");?></span><br/>
		<div class="" align="center">
		    <?php
		    // dompdf cannot fetch http:// URLs (remote disabled) — embed the logo as a data URI.
		    // Client asset first, core asset as fallback; skip the tag entirely if missing.
		    $gp_repo_root = realpath(FCPATH . '..') . DIRECTORY_SEPARATOR;
		    $gp_client_id = defined('CLIENT_ID') ? CLIENT_ID : 'default';
		    $gp_logo = $gp_repo_root . 'clients' . DIRECTORY_SEPARATOR . $gp_client_id . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . 'receiptLogo.png';
		    if (!is_file($gp_logo)) {
		        $gp_logo = FCPATH . 'assets' . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . 'receiptLogo.png';
		    }
		    if (is_file($gp_logo)) { ?>
		    <table style="width:100%; margin-bottom:2px;" cellpadding="0" cellspacing="0">
		        <tr>
		            <td align="center" style="text-align:center;">
		                <img src="data:image/png;base64,<?php echo base64_encode(file_get_contents($gp_logo)); ?>" style="height:54px;">
		            </td>
		        </tr>
		    </table>
		    <?php } ?>
		
               	<?php echo !empty($comp_name['company_name']) && isset($comp_name['company_name']) ?strtoupper($comp_name['company_name']):'' ; ?><br/>				
			<?php echo empty($comp_details['company_name']) ? '' :$comp_details['company_name'] ; ?><br/>				
				<?php echo empty($comp_details['address1']) ? '' :$comp_details['address1'].','; ?>				
				<?php echo empty($comp_details['address1']) ? '' :$comp_details['address2'].','; ?><br/>
				<?php echo empty($comp_details['city'] && $comp_details['pincode']) ? '' :$comp_details['city'].' - '.$comp_details['pincode'].'.'; ?><br/>
			</div>
			<span style="float:left;">
			    <?php echo 'Maturity Date : '.$maturitydate;?>

			</span>
			<br/>
			<div class="" style="font-weight: 400 !important; font-size:15px !important;" align="center">
				<?php echo $records[0]['scheme_name'];?><hr>				
			</div>
			<div>
			<table class="meta" style="width: 100%" align="center">
				<tr>
					<th><span >MS no</span></th>
					<td>
					    <span>
					        <?php echo $records[0]['scheme_acc_number']; ?>
					    
					   </span>
					 </td>
				</tr>
				<tr>
					<th><span >Receipt No</span></th>
					<td>
					    <span >
					    <?php echo $records[0]['receipt_no']; ?>
					     
					    </span>
					 </td>
				</tr>
				<?php if ($this->session->userdata('enable_old_sch_acc_no') == 1) { ?>
					<tr>
						<th><span >Old A/c No</span></th>
						<td>
							<span>
								<?php echo $records[0]['old_scheme_acc_number']; ?>
							
						</span>
						</td>
					</tr>	
				<?php } ?>
				<tr>
					<!-- <th><span >Installment No</span></th> -->
					 <th><span >Installment (Month/Day)</span></th>
					<td><span><?php echo gp_ordinal($records[0]['installment_no']).' Installment - Rs.'.gp_inr_format($records[0]['payment_amount']); ?></span></td>
				</tr>
				<tr>
					<th><span ><?php echo $records[0]['metal_name']; ?> Rate</span></th>
					<td><span><?php echo $records[0]['metal_rate']; ?></span></td>
				</tr>
				<tr>
					<th><span >Paid Amount</span></th>
					<td><span><?php echo "Rs ".$records[0]['payment_amount']; ?></span></td>
				</tr>
			<?php if($records[0]['scheme_type'] != 0 && $records[0]['flexible_sch_type'] != 1 && $records[0]['flexible_sch_type'] != 6 ){?>
				<tr>
					<th><span > Paid Weight</span></th>
					<td><span><?php echo formatMetalWeight($records[0]['metal_weight']); ?></span></td>
				</tr> 
				<tr>
					<th><span >Running Weight</span></th>
					<td><span><?php echo formatMetalWeight($records[0]['total_weight']).' g'; ?></span></td>
				</tr>
			<?php }?>
	
				<tr>
					<th><span >Mode</span></th>
					<!--<td><span><?php echo $records[0]['payment_mode']; ?></span></td>-->
					<td>
					    <?php if($records[0]['multi_modes'] == ''){ ?>
    					    <span><?php echo $records[0]['payment_mode']; ?></span>
    					    <br/>
					        <span><?php echo number_format((float)($records[0]['payment_amount']-$records[0]['discountAmt'].' INR '),2, '.', ''); ?></span>
					    <?php }else{ ?>
					        <span><?php echo $records[0]['multi_modes']; ?></span>
					    <?php }?>
					 </td>
				</tr>
				<tr>
					<th><span >Mobile</span></th>
					<td><span><?php echo $records[0]['mobile']; ?></span></td>
				</tr>
				<tr>
					<th><span >Payment Date</span></th>
					<td><span><?php echo $records[0]['date_payment']; ?></span></td>
				</tr>
				<tr>
					<th><span >Emp Code</span></th>
					<td><span><?php echo $records[0]['login_employee']; ?></span></td>
				</tr>
			</table><hr>
			</div>
			<div>
				<?php if($records[0]['due_type']=='D') { ?>
					<p align="left">Paid by <?php echo $comp_details['company_name']; ?> for <br/>Mr/Mrs. <?php echo $records[0]['firstname'].(isset($records[0]['firstname'])?', ':''); ?></p>
					<div align="left"><span data-prefix><?php echo $comp_details['currency_symbol']; ?> </span><span style="font-weight: 400 !important; font-size:16px !important;"><?php echo $records[0]['payment_amount']; ?></span></div>
				<?php }else{ ?>
					<p align="left" style="margin-top:-5px;">Received with thanks from <br/>Mr/Mrs. <?php echo $records[0]['firstname'].(isset($records[0]['firstname'])?', ':''); ?></p>
					<div align="left">

						<span ><?php echo $comp_details['currency_symbol']; ?> </span><span style="font-weight: 400 !important; font-size:16px !important;"><?php echo $records[0]['payment_amount']; ?></span>
</div>
				<!-- </tr></span> -->
				<?php } ?>
				
				<p style="font-size:12px !important;margin-top:-2px;"><?php echo ucwords($records[0]['amount_in_words']); ?> Only</p>
			<!--	<div style="float:right">For <?php echo empty($comp_details['company_name']) && !isset($comp_details['company_name']) ? $comp_details['name'] :$comp_details['company_name']; ?></div><br/><br/>
				<div style="float:right">Signature</div>-->
			</div>
		</div>
		<script type="text/javascript"> 
		this.print(); 
		</script> 
	</body>
	
</html>