<html><head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
		<title>Estimation</title>
		<link rel="stylesheet" href="<?php echo base_url();?>assets/css/estimation_2.css">
		<style type="text/css">
			@page {
				size:75mm 600mm;
			}

			/* ---- Gold Palace layout overrides (must follow estimation_2.css) ----

			   dompdf implements the page gutter as a margin on the <html> frame.
			   Core estimation_2.css does:

			       body, html { margin:0 !important; margin-left:-5px !important; }

			   which therefore wipes that gutter out entirely and drags the receipt
			   7.5pt PAST the left edge of the paper — that is what made the sections
			   look unaligned. Re-declare it here as one even gutter on all four
			   sides. !important is required to outrank the core rule; this block is
			   loaded after estimation_2.css so it wins the tie. */
			html {
				margin:3mm !important;
			}

			body {
				margin:0 !important;
				padding:0 !important;
			}

			/* Core gives .PDFReceipt padding:15px + margin:0 8px — a second,
			   competing gutter. Zero it so the html margin above is the only one. */
			.PDFReceipt {
				margin:0 !important;
				padding:0 !important;
				width:100%;
			}

			.printable {
				width:100%;
			}

			/* <h3> carries a large UA default margin, which made the gap above the
			   title much bigger than the 3mm gutter used on the sides. */
			.printable h3 {
				margin:0;
				padding:0;
			}
			.header{
				margin: top 10px !important;
			}

			/* Every section — header, item table, old estimate, summary, footer —
			   sits in this one wrapper, so all of them start and end on the same
			   left/right edge. */
			.section {
				width:100%;
				margin:0;
				padding:0;
			}

			/* Shared column grid — the item table, the old estimate table and the
			   summary table all use it, so they line up column-for-column. */
			.grid {
				width:100%;
				table-layout:fixed;
				border-collapse:collapse;
				margin:0;
			}

			.grid th,
			.grid td {
				padding-top:3px;
				padding-bottom:3px;
				padding-left:0;
				padding-right:0;
				word-wrap:break-word;
				vertical-align:top;
			}

			/* dompdf ignores <colgroup>/<col> widths entirely (verified against
			   dompdf 1.0.2 — every variant collapses to equal columns). It does
			   honour a width set on a real cell, so each grid opens with the
			   zero-height $grid_sizer row instead. That also lets the summary table,
			   whose own rows are colspan-only and conditionally rendered, inherit
			   exactly the same column boundaries. */
			.grid tr.sizer td {
				padding:0;
				height:0;
				line-height:0;
				font-size:0;
				border:none;
			}

			/* Stone breakdown lines are sub-detail of the item above them. At 12px
			   "1.500g @ 100/-" needs 62.2pt but its two columns only give 60.6pt, so
			   it broke onto a second line; 11px brings it to 57.0pt. */
			.grid tr.detail td {
				font-size:11px;
			}

			/* Consistent vertical rhythm between the sections. Set as margin-bottom
			   on every section rather than ".section + .section" — dompdf does not
			   implement the adjacent-sibling combinator, so that rule silently does
			   nothing (verified against dompdf 1.0.2). */
			.section {
				margin-bottom:6px;
			}
		</style>
</head>
<?php
/**
 * Gold Palace override of estimation/print/est_print_2
 *
 * Deviations from core (admin/application/views/estimation/print/est_print_2.php):
 *   1. Rates block also prints the Gold 9K rate (goldrate_9ct).
 *   2. Sales item rows show the V.A weight in grams instead of the wastage
 *      percentage (core switched to "%" once net_wt >= 8).
 */
function moneyFormatIndia($num) {
	return preg_replace("/(\d+?)(?=(\d\d)+(\d)(?!\d))(\.\d+)?/i", "$1,", $num);
}
$tax_perc = "";

// Single source of truth for the 5-column grid. Emitted as the first row of
// every .grid table so they all share one set of column boundaries.
//
// Widths are derived from the widest real content each column has to hold,
// measured with dompdf's own font metrics at 12px (= 9pt) sans-serif:
//
//   col 1  DESC    "TOTAL[12]" bold      46.00pt
//   col 2  GWT     "15.000"              27.52pt
//   col 3  NWT/LWT "15.000"              27.52pt
//   col 4  VA      "NT.WT" bold header   28.49pt
//   col 5  AMOUNT  "22,34,951.86" bold   52.54pt
//
// 16% gave col 1 only 31.3pt, which is why "TOTAL[n]" and the "NT.WT" header
// were breaking onto a second line.
$grid_sizer = '<tr class="sizer">'
	. '<td style="width:25%"></td>'
	. '<td style="width:15%"></td>'
	. '<td style="width:15%"></td>'
	. '<td style="width:16%"></td>'
	. '<td style="width:29%"></td>'
	. '</tr>';

?>
<body>

	<div class="PDFReceipt">
		<div class="printable">

			<div class="section header heading">

				<h3>Estimate : <?php echo $estimation['esti_no'];?></h3>

			</div>

			<div class="section top_head">
				
				<div class="cus_name heading">

					<span><?php echo $estimation['customer_name'].($estimation['village_name']!='' ? ' / '.$estimation['village_name']:'').' / '.$estimation['mobile']; ?> </span>

				</div>

				<div class="top_info">

					<div class="rates">
						
						<div class="rows">
							<div class="left_col">Gold 22K : </div> <div class="right_col"> <?php echo number_format($estimation['goldrate_22ct'],2,'.','') ?></div>
						</div>
						
						<div class="rows">
							<div class="left_col">Gold 18K : </div> <div class="right_col"> <?php echo number_format($estimation['goldrate_18ct'],2,'.','') ?></div>
						</div>

						<?php if(isset($estimation['goldrate_9ct']) && $estimation['goldrate_9ct'] > 0){ ?>
						<div class="rows">
							<div class="left_col">Gold 9K &nbsp; : </div> <div class="right_col"> <?php echo number_format($estimation['goldrate_9ct'],2,'.','') ?></div>
						</div>
						<?php } ?>

						<div class="rows">
							<div class="left_col">Silver &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; : </div> <div class="right_col"> <?php echo number_format($estimation['silverrate_1gm'],2,'.','') ?></div>
						</div>
						
					</div>

					<div class="estimation_datetime">

						<div class="rows">
							<div class="left_col">Est No : </div> <div class="right_col"> <?php echo $estimation['esti_no'] ?></div>
						</div>
						
						<div class="rows">
							<div class="left_col">Date &nbsp;&nbsp; : </div> <div class="right_col"> <?php echo date('d-m-Y', strtotime($estimation['estimation_datetime'])); ?></div>
						</div>
						
						<div class="rows">
							<div class="left_col">Time &nbsp;&nbsp; : </div> <div class="right_col"> <?php echo date('h:i:s A', strtotime($estimation['estimation_datetime'])); ?></div>
						</div>
						
					</div>

				</div>
			</div>
			<?php if(sizeof($est_other_item['item_details'])){?>

			<div class="section">

				<table class="grid">

					<thead>

					<?php echo $grid_sizer; ?>

					<tr>
						<td colspan="5" class="_dashed"></td>
					</tr>

					<tr class="heading">

						<th class="alignLeft">DESC</th>

						<th class="alignRight">GWT</th>

						<th class="alignRight">NWT</th>

						<th class="alignRight">VA</th>

						<th class="alignRight">AMOUNT</th>

					</tr>

					<tr><td colspan="5"></td></tr>

					<tr>
						<td colspan="5" class="_dashed"></td>
					</tr>

					</thead>

					<tbody>

				<?php

					$tot_payable=0;

					$tot_purchase=0;

					$market_rate_cost=0;

					$total_wt=0;

					$total_gwt=0;

					$net_wt=0;

					$total_piece=0;

					$total_net_wt=0;

					$tag_net_wt=0;

					$making_charge=0;

					$total_tax=0;

					$sub_total=0;





					$paid_advance   =0;

					$paid_weight    =0;

					$wt_amt         =0;

					$tot_adv_paid   =0;

					$chit_amount    =0;

					$total_chit_amount    =0;

					if(sizeof($est_other_item['advance_details'])>0)

					{

						foreach($est_other_item['advance_details'] as $advance)

						{

								$paid_advance+=$advance['paid_advance'];

								$paid_weight+=$advance['paid_weight'];

								$wt_amt+=($advance['paid_weight']);

						}

						$tot_adv_paid=number_format(($paid_advance+$wt_amt),2,'.','');

					}



					if(sizeof($est_other_item['chit_details'])>0)

					{

						foreach($est_other_item['chit_details'] as $advance)

						{

								$chit_amount+=$advance['utl_amount'];

						}

					}





					$item_no = 1;

					foreach($est_other_item['item_details'] as $items){

						

					$making_charge=0;



					$stone_price=0;



					$stone_weight=0;



					$stone_piece=0;



					$charge_price=0;



					$tag_other_itm_amount=0;



					$certification_cost=0;



					$tag_other_itm_grs_weight=0;



					$total_piece+=$items['piece'];



					$total_gwt+=$items['gross_wt'];



					$total_wt+=$items['net_wt'];



					$tot_payable+=$items['item_cost'];



					$market_rate_cost+=$items['market_rate_cost'];



					$payable_without_tax = $items['item_cost']-$items['item_total_tax'];

					$sub_total += $payable_without_tax;



					$total_tax += $items['item_total_tax'];



					if($items['is_partial']==1 && $items['isTagsplitted']==0)

					{

						$net_wt+=$items['net_wt'];

						$tag_net_wt+=$items['tag_net_wt'];

					}

					// default — covers calculation_based_on == 3 (flat / piece rate)
					$wast_wgt=0;

					if($items['calculation_based_on']==0)

					{

					$wast_wgt=number_format((($items['gross_wt']) * ($items['wastage_percent']/100)),2,'.','');

					

					$making_charge=($items['mc_type']==2 ? $items['gross_wt']*$items['mc_value'] : $items['mc_value']*$items['piece']);

					

					}else if($items['calculation_based_on']==1)

					{

						$wast_wgt=number_format((($items['net_wt']) * ($items['wastage_percent']/100)),2,'.','');

						

						$making_charge=($items['mc_type']==2 ? $items['net_wt']*$items['mc_value'] : $items['mc_value']*$items['piece']);

						

					}else if($items['calculation_based_on']==2)

					{

						$wast_wgt=number_format((($items['net_wt']) * ($items['wastage_percent']/100)),3,'.','');

						

						$making_charge=($items['mc_type']==2 ? $items['gross_wt']*$items['mc_value'] : $items['mc_value']*1);

						

					}

					$wastage_value = round($wast_wgt * $items['est_rate_per_grm'],2);

					foreach($items['stone_details'] as $stone)

					{

						//$stone+=$stone['stone_name'];

						$stone_price+=$stone['price'];

						$stone_weight+=$stone['wt'];

						$stone_piece+=$stone['pieces'];

						$certification_cost+=$stone['certification_cost'];

					}



					foreach($items['charges'] as $charge)

					{

						$charge_price+=$charge['amount'];

					}



					foreach($items['other_metal_details'] as $other_metal_details)

					{

						$tag_other_itm_grs_weight+=$other_metal_details['tag_other_itm_grs_weight'];

						$tag_other_itm_amount+=$other_metal_details['tag_other_itm_amount'];

					}

					$huid = "";

					if ($items['hu_id'] != "-" && $items['hu_id'] != "") {

						$huid = $huid . $items['hu_id'];

					}

					if ($items['hu_id'] != "-" && $items['hu_id'] != "" && $items['hu_id2'] != "-" && $items['hu_id2'] != "") {

						$huid = $huid . ",";

					}

					if ($items['hu_id2'] != "-" && $items['hu_id2'] != "") {

						$huid = $huid . $items['hu_id2'];
						
					}




					?>

					<tr>
						<td colspan="5" class="item_name heading">
						
						<?php echo $item_no; ?>)
						
						<?php //echo ($items['purname'] != "" ? "(". $items['purname'].")" : ""); ?>
						
							<?php echo substr($items['product_name'],0,10)." ".substr($items['sub_design_name'],0,10); 
							if($items['calculation_based_on'] != 3) { ?> @ <?php echo $items['est_rate_per_grm']; } ?>
						</td>
					</tr>
					
						<?php if ($items['tag_id'] != "" && $items['tag_id'] != 0 && $items['tag_code'] != "") { ?>
					<tr>
						<td class="alignLeft" colspan="5"><b><?php echo $items['tag_code']; ?></b></td>
					</tr>
					<?php } ?>
					<tr>

						
					<td class="alignLeft">

							<?php // Gold Palace: no wastage % here — V.A is printed as weight in the VA column
							echo $items['sales_mode'] == 1 ? "PCS : ".$items['piece'] : "" ?>
						</td>

						<td class="alignRight"><?php echo $items['gross_wt'] != 0 ? number_format($items['gross_wt'],3,'.','').($items['pro_uom']!=''?'-'.$items['pro_uom']:'') : "";?></td>
						
						<td class="alignRight"><?php echo $items['net_wt'] != 0 ? number_format($items['net_wt'],3,'.','').($items['pro_uom']!=''?'-'.$items['pro_uom']:'') : "";?></td>
						
						<td class="alignRight">
							<?php
								// Gold Palace: always print V.A as weight (grams), never as a percentage
								echo $wast_wgt > 0 ? number_format($wast_wgt, 3, '.', '')."g" : "";
							?>
						</td>
						
						<td class="alignRight"><?php echo moneyFormatIndia(number_format($items['item_cost']-$charge_price-$items['item_total_tax']-$tag_other_itm_amount-$stone_price,2,'.',''));?></td>
						
					</tr>

					<?php if(sizeof($items['other_metal_details']) > 0) { ?>

					<?php 
						$oth_va_amt =0;
						$oth_mc_va =0;
						$oth_mc_va_pg =0;
						foreach ($items['other_metal_details'] as $other_metal_details) {

								// other metal calc
								$oth_va_amt =number_format(($other_metal_details['tag_other_itm_grs_weight']*$other_metal_details['tag_other_itm_wastage'])/100,2,'.','') ;
								$oth_mc_va = number_format(($other_metal_details['tag_other_itm_mc']+$oth_va_amt),2,'.','');

								$oth_mc_va_pg = number_format($oth_mc_va/$other_metal_details['tag_other_itm_grs_weight'],2,'.',''); ?>

						<tr>

							<td colspan="5"><?php echo $other_metal_details['metal_name'].' - (Rs. '.(moneyFormatIndia($other_metal_details['tag_other_itm_rate'])).'/GM)' ?></td>

						</tr>

						<tr>
							<td></td>

							<td class="alignRight"><?php echo $other_metal_details['tag_other_itm_grs_weight'] ?></td>

							<td class="alignRight"><?php echo $other_metal_details['tag_other_itm_grs_weight'] ?></td>

							<td class="alignRight"><?php echo number_format($oth_mc_va_pg,2,'.','') ?></td>

							<td class="alignRight"><?php echo moneyFormatIndia(number_format($other_metal_details['tag_other_itm_amount'],2,'.','')) ?></td>

						</tr>

					<?php } } ?>
					
					<?php if(sizeof($items['stone_details']) > 0) { ?>

					<?php foreach($items['stone_details'] as $stone) { 

						?>

								<tr class="detail">

								<td colspan="2" style="text-transform:uppercase;"><?php echo $stone['stone_name'] ?></td>

								<td colspan="2"><?php echo number_format($stone['wt'], 3, '.', '') . $stone['uom_short_code'] . ' @ ' . $stone['rate_per_gram'] . '/-' ?></td>

								<td class="alignRight"><?php echo moneyFormatIndia($stone['price']) ?></td>

								</tr>
						

					<?php } } ?>

					<?php if(($items['est_stone_wt']!=$items['tag_stone_wt'])&& $items['tag_id'] && ($items['isTagsplitted']==0) ){ ?>

						<tr>
							<td colspan="5">PARTLY STONE(<?php echo number_format($items['tag_stone_wt'],3,'.','').'-'.number_format($items['est_stone_wt'],3,'.','')?>): <?php echo number_format(($items['tag_stone_wt']-$items['est_stone_wt']),3,'.','')?></td>
						</tr>
						<?php } ?>


					<?php if(sizeof($items['charges']) > 0) { ?>

					<?php foreach($items['charges'] as $charge) { 

						?>

						<tr>

							<td colspan="4" style="text-transform:uppercase;"><?php echo $charge['code_charge'].' Charges' ?></td>

							<td class="alignRight"><?php echo moneyFormatIndia(number_format($charge['amount'],2,'.',''));?></td>

						</tr>

					<?php } } ?>

					<?php if($certification_cost>0){?>

					<tr>

						<td colspan="4">CERTIFICATION CHARGE</td>

						<td class="alignRight"><?php echo moneyFormatIndia(number_format($certification_cost,2,'.',''));?></td>

					</tr>

					<?php }?>

					<tr style="display:none">

						<td colspan="2"><?php echo $items['tgrp_name'];?></td>

						<td></td>

						<td></td>

						<td class="alignRight"><?php echo moneyFormatIndia(number_format($items['item_total_tax'],2,'.',''));?></td>

					</tr>

					<?php if($items['tag_net_wt']!=0 && ($items['tag_net_wt']-$items['net_wt'] != 0) && $items['isTagsplitted']==0){?>

					<tr>

						<td colspan="5">PARTLY (<?php echo number_format($items['tag_net_wt'],3,'.','').'-'.number_format($items['net_wt'],3,'.','')?>): <?php echo number_format(($items['tag_net_wt']-$items['net_wt']),3,'.','');?></td>

					</tr>

					<?php }

					if($huid != "") { ?>

					<tr>

						<td colspan="5"><?php echo "HUID: " . $huid ?></td>

					</tr>

					<?php } ?>

					<tr><td colspan="5"></td></tr>

					<tr><td colspan="5"></td></tr>

        			<?php $item_no ++;  } ?>

					<tr>
						<td colspan="5" class="_dashed"></td>
					</tr>

					<tr><td colspan="5"></td></tr>

					<tr>
					
						<td class="alignLeft" colspan="2">SUB TOTAL</td>
						
						<td></td>
						
						<td class="alignRight" colspan="2"><?php echo moneyFormatIndia(number_format($sub_total,2,'.',''));?></td>
						
					</tr>

					<?php if(($comp_details['id_country'] == $estimation['id_country']) || $estimation['id_country'] == ''){ 
						
						if(($comp_details['id_state'] == $estimation['id_state']) || $estimation['id_country'] == '') { ?>
					<tr>

						<td colspan="2">CGST <?php echo $items['tax_percentage'] / 2 ?>%</td>

						<td></td> 

						<td class="alignRight" colspan="2"><?php echo moneyFormatIndia(number_format($total_tax/2,2,'.',''));?></td>

					</tr>

					<tr>

						<td colspan="2">SGST <?php echo $items['tax_percentage'] / 2 ?>%</td>

						<td></td>

						<td class="alignRight" colspan="2"><?php echo moneyFormatIndia(number_format($total_tax/2,2,'.',''));?></td>

					</tr>

					<?php } else { ?>

						<tr>

						<td colspan="2">IGST <?php echo $items['tax_percentage']?>%</td>

						<td></td>

						<td class="alignRight" colspan="2"><?php echo moneyFormatIndia(number_format($total_tax,2,'.',''));?></td>

					</tr>

					<?php }
					}?>

					<tr><td colspan="5"></td></tr>

					<tr>
					
						<td colspan="5" class="_dashed"></td>
						
					</tr>

					<tr><td colspan="5"></td></tr>

					<tr class="rowClass heading">

						<td class="alignLeft" >TOTAL[<?php echo $total_piece;?>]</td>
						
						<td class="alignRight"><?php echo $total_gwt != 0 ? number_format($total_gwt,3,'.','') : "";?></td>
						
						<td class="alignRight"><?php echo $total_wt != 0 ? number_format($total_wt,3,'.','') : "";?></td>
						
						<td class="alignRight" colspan="2"><?php echo moneyFormatIndia(number_format($tot_payable,2,'.','')); ?></td>
					
					</tr>

					<tr><td colspan="5"></td></tr>

					<tr>

						<td colspan="5" class="_dashed"></td>

					</tr>

					<?php /* if($tot_adv_paid>0){?>

					<tr class="heading" style="font-weight:bold;">

						<td colspan="2">SUB TOTAL</td>

						<td></td>

						<td class="alignRight" colspan="2">

						<?php echo moneyFormatIndia(number_format($tot_payable,2,'.','')); ?></td>

					</tr>

					<tr>

						<td colspan="5" class="_dashed"></td>

					</tr>

					<tr>

						<td >Adv Paid</td>

						<td></td>

						<td></td>

						<td class="alignRight" colspan="2"><?php echo moneyFormatIndia(number_format($tot_adv_paid,2,'.',''));?></td>

					</tr>

					<tr>

						<td colspan="5" class="_dashed"></td>

					</tr>

					<tr class="heading" style="font-weight:bold;">

						<td class="alignLeft"><?php echo number_format($total_gwt,3,'.','');?></td>

						<td class="alignRight"><?php echo number_format($total_wt,3,'.','');?></td>

						<td class="alignRight"><?php echo $total_piece;?></td>

						<td class="alignRight" colspan="2">

						<?php echo moneyFormatIndia(number_format($tot_payable-$tot_adv_paid,2,'.','')); ?></td>

					</tr>

					<tr>

						<td colspan="5" class="_dashed"></td>

					</tr>


					<?php } */ ?>

					
					<?php if($chit_amount>0){?>

						<tr>

							<td colspan="5" class="_dashed"></td>

						</tr>

						<?php 

						$i =1;

						$total_weight_amount        =0;

						$total_wastage_amount       =0;

						$total_making_charge_amount =0;

						$total_chit_amount_scheme   =0;

						$closing_add_chgs   =0;

						$additional_benefits   =0;

						foreach($est_other_item['chit_details'] as $chit)

						{

							if($chit['scheme_type']==0)

							{

								$total_chit_amount_scheme+=$chit['utl_amount'];

							}

							

							$weight_amount = 0;

							$wastage_amount = 0;

							$mc_amount = 0;

							// Determine if this is an amount scheme (is_weight=0) using the same logic as ret_billing.js
							$is_weight = 1;
							if($chit['new_scheme_type'] == 0) {
								$is_weight = 0;
							} else if($chit['new_scheme_type'] == 3) {
								if(in_array($chit['flexible_sch_type'], [1, 6]) || ($chit['flexible_sch_type'] == 2 && $chit['wgt_convert'] == 2)) {
									$is_weight = 0;
								} else if(in_array($chit['flexible_sch_type'], [3, 4, 5, 7, 8]) || ($chit['flexible_sch_type'] == 2 && in_array($chit['wgt_convert'], [0, 1]))) {
									$is_weight = 1;
								}
							} else if(in_array($chit['new_scheme_type'], [1, 2])) {
								$is_weight = 1;
							}

							if($chit['scheme_type']==2 || $chit['scheme_type']==3 || $chit['scheme_type']==1)

							{?>

								<tr>

									<td colspan="5"><b><?php echo $i.'. ACC NO - '.$chit['scheme_acc_number'];?></b></td>

								</tr>

							<?php 

								if($chit['closing_weight']>0 || $chit['utl_amount']>0)

								{
                                    
                                    if($chit['closing_weight']>0){
                                        
    									$total_weight_amount+= number_format($chit['closing_weight']*($chit['rate_per_gram'] > 0 ? $chit['rate_per_gram'] :$estimation['goldrate_22ct']),2,'.','');
    
    									$weight_amount = number_format($chit['closing_weight']*($chit['rate_per_gram'] > 0 ? $chit['rate_per_gram'] :$estimation['goldrate_22ct']),2,'.','');
										$weight_amount1 = $weight_amount - $chit['additional_benefits'];
    									
    									?>
    								  <tr>

										<td colspan="2">Saved Weight</td>

										<td class="alignCenter"><?php echo $chit['closing_weight'];?></td>

										<td></td>

										<td class="alignRight"><?php echo moneyFormatIndia($weight_amount1);?></td>

									</tr>	
							  <?php   }else{
								        $weight_amount= $chit['utl_amount'] - $chit['closing_benefits'];
								        $total_weight_amount+= $chit['utl_amount'] - $chit['closing_benefits'];
								        ?>
								        <tr>

										<td colspan="2"><?php echo $chit['scheme_acc_number']; ?></td>

										<td class="alignCenter"></td>

										<td></td>

										<td class="alignRight"><?php echo moneyFormatIndia($weight_amount);?></td>

									</tr>
								    <?php }	?>

									

								<?php }?>

								

								<?php 

								if($chit['wastage_per']>0)

								{

									$total_wastage_amount+= number_format($chit['savings_in_wastage']*($chit['rate_per_gram'] > 0 ? $chit['rate_per_gram'] :$estimation['goldrate_22ct']),2,'.','');

									$wastage_amount = number_format($chit['savings_in_wastage']*($chit['rate_per_gram'] > 0 ? $chit['rate_per_gram'] :$estimation['goldrate_22ct']),2,'.','');

								?>

									<tr>

										<td colspan="2">Saved V.A(<?php echo $chit['wastage_per'].'%' ?>)</td>

										<td class="alignCenter"><?php echo $chit['savings_in_wastage'];?></td>

										<td></td>

										<td class="alignRight"><?php echo moneyFormatIndia($wastage_amount);?></td>

									</tr>

								<?php }

								?>

								<?php 

								if($chit['closing_benefits']>0 && $is_weight == 0)

								{

								?>

									<tr>

										<td colspan="3">Closing Benefit</td>

										<td></td>

										<td class="alignRight"><?php echo moneyFormatIndia(number_format($chit['closing_benefits'],2,'.',''));?></td>

									</tr>

								<?php } ?>

								

								<?php 

								if($chit['additional_benefits']>0)

								{

									$additional_benefits = $chit['additional_benefits'];

								?>

									<tr>

										<td colspan="2">Benefits</td>

										<td class="alignCenter"></td>

										<td></td>

										<td class="alignRight"><?php echo moneyFormatIndia($chit['additional_benefits']);?></td>

									</tr>

									

								<?php }

								?>

								

								<?php 

								if($chit['closing_add_chgs']>0)

								{

									$closing_add_chgs = $chit['closing_add_chgs'];

								?>

									<tr>

										<td colspan="2">Deduction</td>

										<td class="alignCenter"></td>

										<td></td>

										<td class="alignRight"><?php echo moneyFormatIndia($chit['closing_add_chgs']);?></td>

									</tr>

									

								<?php }

								?>

								

							<?php $i++; } else if($is_weight == 0){ ?>

								<tr>

									<td colspan="2"><?php echo $i.'. ACC NO - '.$chit['scheme_acc_number'];?></td>

									<td></td>

									<td></td>

									<td class="alignRight"><?php echo moneyFormatIndia(number_format($chit['utl_amount'] - $chit['closing_benefits'],2,'.',''));?></td>

								</tr>

								<?php if($chit['closing_benefits']>0 && $is_weight == 0) { ?>

									<tr>

										<td colspan="3">Closing Benefit</td>

										<td></td>

										<td class="alignRight"><?php echo moneyFormatIndia(number_format($chit['closing_benefits'],2,'.',''));?></td>

									</tr>

								<?php } ?>

						<?php $i++; }?>

						<tr>

							<td colspan="5" class="_dashed"></td>

						</tr>

						<?php } ?>

						<?php 

							$total_chit_amount = number_format($total_weight_amount+$total_making_charge_amount+$total_wastage_amount+$total_chit_amount_scheme-$closing_add_chgs,2,'.','')

						?>

						<tr class="heading" style="font-weight:bold;">

							<td class="alignLeft" colspan="2">SUB TOTAL</td>

							<td></td>

							<td></td>

							<td class="alignRight"><?php echo moneyFormatIndia(number_format($total_chit_amount,2,'.','')); ?></td>

						</tr>

					<?php }?>

					</tbody>

				</table>

			</div>

			<?php } ?>

			<?php if(sizeof($est_other_item['old_matel_details'])>0) { ?>

			<div class="section">

				<div class="old_estimate heading">

					OLD ESTIMATE

				</div>

				<table class="grid">

					<thead>

					<?php echo $grid_sizer; ?>

					<tr>

						<td colspan="5" class="_dashed"></td>

					</tr>

				<tr><td colspan="5"></td></tr>

				<tr class="heading">

					<th class="alignLeft">DESC</th>

					<th class="alignRight">GWT</th>

					<th class="alignRight">LWT</th>

					<th class="alignRight">NT.WT</th>

					<th class="alignRight">AMOUNT</th>

					</tr>

					<tr><td colspan="5"></td></tr>

					<tr>

						<td colspan="5" class="_dashed"></td>

					</tr>

					</thead>

					<tbody>

					<?php 

					$gross_wt=0;

					$total_va=0;

					$amount=0;

					$old_item = 1;

					foreach($est_other_item['old_matel_details'] as $data){

					$gross_wt+=$data['gross_wt'];

					$total_va+=$data['wastage_wt'];

					$amount+=$data['amount'];

					$stone_total = 0;

					 if(sizeof($data['stone_details']) > 0) {

						foreach($data['stone_details'] as $oldstn) { 

							$stone_total += $oldstn['price'];
							
						} }

					?>

					<tr>
					
						<td colspan="5"></td>
					
					</tr>

					<tr class="item_name heading">
						
						<td colspan="5"><?php echo $old_item.") ".$data['old_metal_type']." @ ".$data['rate_per_gram']; ?></td>
					
					</tr>

					<tr>

						<td></td>

						<td class="alignRight"><?php echo $data['gross_wt'];?></td>

						<td class="alignRight">
							<?php 
							if($data['id_category'] == 2 && $data['wast_type'] == 1) {

								echo $data['wastage_percent'];

							} else {
							
								echo number_format($data['wastage_wt'] + $data['dust_wt'] + $data['stone_wt'],3,'.','');

							}
							?>
						
						</td>

						<td class="alignRight"><?php echo $data['net_wt'];?></td>

						<td class="alignRight"><?php echo moneyFormatIndia(number_format($data['amount']-$stone_total,2,'.',''));?></td>

					</tr>

					<?php if($data['dust_wt'] != 0 || $data['stone_wt'] != 0) { ?>

					<tr>

						<td colspan="2">DUST / STN WT</td>

						<td colspan="3"><?php echo $data['dust_wt']." / ".$data['stone_wt']; ?></td>

					</tr>

					<?php } ?>

					<?php if(sizeof($data['stone_details']) > 0) { ?>

					<?php foreach($data['stone_details'] as $stone) { 

						?>

						<tr class="detail">

						<td colspan="2" style="text-transform:uppercase;"><?php echo $stone['stone_name'] ?></td>

						<td colspan="2"><?php echo number_format($stone['wt'], 3, '.', '') . $stone['uom_short_code'] . ' @ ' . $stone['rate_per_gram'] . '/-' ?></td>

						<td class="alignRight"><?php echo moneyFormatIndia($stone['price']) ?></td>

						</tr>

					<?php } } ?>

					<?php $old_item++;  } ?>
					
					<tr>
						<td colspan="5" class="_dashed"></td>

					</tr>

					<tr><td colspan="5"></td></tr>

					<tr class="rowClass heading">

						<td></td>

						<td class="alignRight"><?php echo number_format($gross_wt,3,'.','');?></td>

						<td class="alignRight"></td>

						<td class="alignRight" colspan="2"><?php

						$tot_purchase = number_format($amount,2,'.','');

						echo moneyFormatIndia($tot_purchase);

						?>

						</td>

					</tr>

					<tr><td colspan="5"></td></tr>

					<tr>

						<td colspan="5" class="_dashed"></td>

					</tr>

					</tbody>

				</table>

			</div>

			<?php } ?>

			<?php
				$count = 0;
				if ($tot_payable != 0) { $count++; }
				if ($tot_purchase != 0) { $count++; }
				if ($total_chit_amount != 0) { $count++; }
				if ($tot_adv_paid != 0) { $count++; }
			?>

			<?php if($count >= 2) { ?>

			<div class="section">

				<table class="grid">

					<tbody>

					<?php echo $grid_sizer; ?>

						<?php if($tot_payable!=0){?>

						<tr>

							<td class="alignLeft" colspan="2">Sales</td>

							<td class="alignRight" colspan="3"><?php echo moneyFormatIndia(number_format(($tot_payable),2,'.',''));?></td>

						</tr>

						<?php }?>

						<?php if($tot_purchase!=0){?>

						<tr>

							<td class="alignLeft" colspan="2">Purchase</td>

							<td class="alignRight" colspan="3"><?php echo moneyFormatIndia(number_format(($tot_purchase),2,'.',''));?></td>

						</tr>

						<?php }?>

						<?php if($total_chit_amount!=0){?>

						<tr>

							<td class="alignLeft" colspan="2">CHIT</td>

							<td class="alignRight" colspan="3"><?php echo moneyFormatIndia(number_format(($total_chit_amount),2,'.',''));?></td>

						</tr>

						<?php }?>

						<?php if($tot_adv_paid!=0){?>

						<tr>

							<td class="alignLeft" colspan="2">ADVANCE</td>

							<td class="alignRight" colspan="3"><?php echo moneyFormatIndia(number_format(($tot_adv_paid),2,'.',''));?></td>

						</tr>

						<?php }?>

						<tr>

							<td colspan="5" class="_dashed"></td>

						</tr>

						<tr><td colspan="5"></td></tr>

						<tr class="heading" style="font-weight:bold;">

							<td class="alignLeft" colspan="2">Grand Total</td>

							<td class="alignRight" colspan="3"><?php echo moneyFormatIndia(number_format(($tot_payable-$tot_purchase-$total_chit_amount-$tot_adv_paid),2,'.',''));?></td>

						</tr>

						<tr><td colspan="5"></td></tr>

						<tr>

							<td colspan="5" class="_dashed"></td>

						</tr>

					</tbody>

				</table>

			</div>

			<?php } ?>

			<div class="section emp_details" style="text-transform:uppercase;">

				<label>EMP : <?php echo $estimation['emp_code'].' / '.$estimation['emp_name'].' / '.date('h:i A', strtotime(date('d-m-Y H:i:s')));?></label>

			</div>

			<div class="section">

				<p class="alignCenter finalTotal heading">Total: Rs. <?php echo moneyFormatIndia(number_format((round($tot_payable-$tot_purchase-$total_chit_amount-$tot_adv_paid)),2,'.',''));?></p>

			</div>

			<p>&nbsp;</p>

			<p>&nbsp;</p>

			<p>&nbsp;</p>

			<p>&nbsp;</p>

			<p>.&nbsp;</p>

		</div>
	</div>

</body>
</html>
