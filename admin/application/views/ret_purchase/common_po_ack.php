<html><head>

		<meta charset="utf-8">

		<title>Vendor Acknowledgement - <?php echo $order['pur_no'];?> </title>

		<link rel="stylesheet" href="<?php echo base_url();?>assets/css/order_ack.css">

		<style type="text/css">

		body, html {

		margin-bottom:0

		}

		 span { display: inline-block; }

		 .order_details ,.order_items

		 {
		        border: 0.1px solid black;

		        border-collapse: collapse;

         }

         figure {

              display: inline-block;

            }

	</style>

</head><body>

<?php
/**
 * Common PO — Vendor Acknowledgement (CPO-06)
 *
 * Letterhead, stylesheet, classes and DOMPDF render match vendor_ack.php exactly.
 * Only the item table differs: bullion lines carry actual weights and a fixed rate
 * instead of weight-range buckets.
 *
 * Every figure below is printed straight from customerorderdetails as written by
 * _save_common_po(). Nothing is recalculated here — a print template that redoes the
 * arithmetic is the usual cause of print-vs-save mismatches (see CLAUDE.md).
 */
$RATE_TYPE = array(1 => 'FIXED RATE', 2 => 'UNFIXED RATE', 3 => 'MIXED RATE');
$STATUS    = array(0 => 'Open', 1 => 'Advance Paid', 2 => 'Received', 3 => 'Billed', 4 => 'Closed', 5 => 'Cancelled');

$grand_pcs = 0; $grand_gross = 0; $grand_stone = 0; $grand_net = 0; $grand_pure = 0;
$grand_taxable = 0; $grand_tax = 0; $grand_total = 0;
$has_unfixed = FALSE;
?>

<span class="PDFReceipt">

			 <div style="width: 100%; text-transform:uppercase;font-size: 11px !important;" align="center;">

			        <div style="display: inline-block; width: 100%;">

                          <img alt=""  src="<?php echo base_url();?>assets/img/logimax.png" ><br>

    				</div>

			    </div><br>

			    <div style="width: 100%; text-transform:uppercase;font-size: 11px !important;">

    				<div style="display: inline-block; width: 30%;font-size: 11px;">

    				      <div style="text-align: left; width:100%;height: 18px; ">

        					<div style="width: 50%; display: inline-block"> Order Date &nbsp;&nbsp; : &nbsp; </div>

        					<div style="width: 50%; display: inline-block; margin-top: -2px"> <?php echo $order['order_date']; ?></div>

        				  </div>

        				  <div style="text-align: left; width:100%;height: 18px; ">

        					<div style="width: 50%; display: inline-block"> PO NO &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; : &nbsp; </div>

        					<div style="width: 50%; display: inline-block; margin-top: -2px"> <?php echo $order['pur_no']; ?></div>

        				  </div>

        				  <div style="text-align: left; width:100%;height: 18px; ">

        					<div style="width: 50%; display: inline-block"> Karigar &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; : &nbsp; </div>

        					<div style="width: 100%; display: inline-block; margin-top: -2px"> <?php echo $karigar['karigar_name']; ?></div>

        				  </div>

        				  <div style="text-align: left; width:100%;height: 18px; ">

        					<div style="width: 50%; display: inline-block"> Due date &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; : &nbsp; </div>

        					<div style="width: 50%; display: inline-block; margin-top: -2px"> <?php echo $order['smith_due_date']; ?></div>

        				  </div>

    				</div>

    				<div style="display: inline-block; width: 18%;font-size: 12px;margin-top:-6%;margin-left:10%;">

    				    <label><b>COMMON PO</b></label><br>

    				    <label style="font-size:10px;"><?php echo (isset($RATE_TYPE[$order['rate_type_fix']]) ? $RATE_TYPE[$order['rate_type_fix']] : ''); ?></label>

    				</div>

    				<div style="display: inline-block; width: 50%;margin-left:18%;">

                          <?php /* isset() guards throughout: get_company() does not return an
                                   address3 column, and an undefined-index notice would be
                                   rendered into the PDF body by DOMPDF. */ ?>
                          <label><?php echo (isset($comp_details['company_name']) ? $comp_details['company_name'] : '');?></label>

                          <label><?php echo (!empty($comp_details['address1']) ? '<br>'.$comp_details['address1'] :'')?></label>

                          <label><?php echo (!empty($comp_details['address2']) ? '<br>'.$comp_details['address2'] :'')?></label>

                          <label><?php echo (!empty($comp_details['address3']) ? '<br>'.$comp_details['address3'] :'')?></label>

                          <label><?php echo (!empty($comp_details['city']) ? '<br>'.$comp_details['city'].(!empty($comp_details['pincode']) ? ' - '.$comp_details['pincode'] :'') :'')?></label>

                          <label><?php echo (!empty($comp_details['state']) ? '<br>'.$comp_details['state'] :'')?></label>

                          <label><?php echo (!empty($comp_details['country']) ? '<br>'.$comp_details['country'] :'')?></label>

    				</div>

			</div>

<div  class="content-wrapper">
 <div class="box">
  <div class="box-body">
 			<div  class="container-fluid">
				<div id="printable">

				    <br>

				    <div class="table-responsive">

				        <table id="order_details" class="table text-center order_details" style="width:100%;font-size:10px;">

				            <tr>
				                <td class="order_items" style="text-align:center;font-weight:bold;">#</td>
				                <td class="order_items" style="text-align:center;font-weight:bold;">Category</td>
				                <td class="order_items" style="text-align:center;font-weight:bold;">Purity</td>
				                <td class="order_items" style="text-align:center;font-weight:bold;">Product</td>
				                <td class="order_items" style="text-align:center;font-weight:bold;">Design</td>
				                <td class="order_items" style="text-align:center;font-weight:bold;">Size</td>
				                <td class="order_items" style="text-align:center;font-weight:bold;">Pcs</td>
				                <td class="order_items" style="text-align:center;font-weight:bold;">Gross Wt</td>
				                <td class="order_items" style="text-align:center;font-weight:bold;">Stone Wt</td>
				                <td class="order_items" style="text-align:center;font-weight:bold;">Net Wt</td>
				                <td class="order_items" style="text-align:center;font-weight:bold;">Pure Wt</td>
				                <td class="order_items" style="text-align:center;font-weight:bold;">Rate/Gm</td>
				                <td class="order_items" style="text-align:center;font-weight:bold;">Taxable</td>
				                <td class="order_items" style="text-align:center;font-weight:bold;">Tax</td>
				                <td class="order_items" style="text-align:center;font-weight:bold;">Total</td>
				            </tr>

				            <?php
				            $i = 1;
				            foreach ($items as $r) {

				                $is_unfixed = ((int)$r['line_rate_type'] === 2);
				                if ($is_unfixed) { $has_unfixed = TRUE; }

				                $grand_pcs     += (int)$r['no_of_pcs'];
				                $grand_gross   += (float)$r['gross_wt'];
				                $grand_stone   += (float)$r['stone_wt'];
				                $grand_net     += (float)$r['net_wt'];
				                $grand_pure    += (float)$r['pure_wt'];
				                $grand_taxable += (float)$r['taxable_amount'];
				                $grand_tax     += (float)$r['tax_amount'];
				                $grand_total   += (float)$r['total_amount'];
				            ?>
				            <tr>
				                <td class="order_items" style="text-align:center;"><?php echo $i++; ?></td>
				                <td class="order_items" style="text-align:center;"><?php echo $r['category_name']; ?></td>
				                <td class="order_items" style="text-align:center;"><?php echo $r['purity_name']; ?></td>
				                <td class="order_items" style="text-align:center;"><?php echo $r['product_name']; ?></td>
				                <td class="order_items" style="text-align:center;"><?php echo (trim($r['design_name'].' '.$r['sub_design_name']) != '' ? trim($r['design_name'].' '.$r['sub_design_name']) : '-'); ?></td>
				                <td class="order_items" style="text-align:center;"><?php echo ($r['size'] != '' ? $r['size'] : '-'); ?></td>
				                <td class="order_items" style="text-align:center;"><?php echo (int)$r['no_of_pcs']; ?></td>
				                <td class="order_items" style="text-align:right;"><?php echo number_format($r['gross_wt'], 3, '.', ''); ?></td>
				                <td class="order_items" style="text-align:right;"><?php echo number_format($r['stone_wt'], 3, '.', ''); ?></td>
				                <td class="order_items" style="text-align:right;"><?php echo number_format($r['net_wt'], 3, '.', ''); ?></td>
				                <td class="order_items" style="text-align:right;"><?php echo number_format($r['pure_wt'], 3, '.', ''); ?><?php echo ((float)$r['purity_value'] > 0 ? '<br><small>@'.number_format($r['purity_value'], 2, '.', '').'</small>' : ''); ?></td>

				                <?php if ($is_unfixed) { ?>
				                    <td class="order_items" colspan="4" style="text-align:center;">RATE TO BE FIXED AT SUPPLIER BILL ENTRY</td>
				                <?php } else { ?>
				                    <td class="order_items" style="text-align:right;"><?php echo money_format_india($r['rate_per_gram'], 2); ?></td>
				                    <td class="order_items" style="text-align:right;"><?php echo money_format_india($r['taxable_amount'], 2); ?></td>
				                    <td class="order_items" style="text-align:right;"><?php echo money_format_india($r['tax_amount'], 2); ?><?php echo ((float)$r['tax_percent'] > 0 ? ' ('.number_format($r['tax_percent'], 2, '.', '').'%)' : ''); ?></td>
				                    <td class="order_items" style="text-align:right;"><?php echo money_format_india($r['total_amount'], 2); ?></td>
				                <?php } ?>
				            </tr>
				            <?php } ?>

				            <tr>
				                <td class="order_items" colspan="6" style="text-align:center;font-weight:bold;">Total</td>
				                <td class="order_items" style="text-align:center;font-weight:bold;"><?php echo $grand_pcs; ?></td>
				                <td class="order_items" style="text-align:right;font-weight:bold;"><?php echo number_format($grand_gross, 3, '.', ''); ?></td>
				                <td class="order_items" style="text-align:right;font-weight:bold;"><?php echo number_format($grand_stone, 3, '.', ''); ?></td>
				                <td class="order_items" style="text-align:right;font-weight:bold;"><?php echo number_format($grand_net, 3, '.', ''); ?></td>
				                <td class="order_items" style="text-align:right;font-weight:bold;"><?php echo number_format($grand_pure, 3, '.', ''); ?></td>
				                <td class="order_items" style="text-align:center;"></td>
				                <td class="order_items" style="text-align:right;font-weight:bold;"><?php echo money_format_india($grand_taxable, 2); ?></td>
				                <td class="order_items" style="text-align:right;font-weight:bold;"><?php echo money_format_india($grand_tax, 2); ?></td>
				                <td class="order_items" style="text-align:right;font-weight:bold;"><?php echo money_format_india($grand_total, 2); ?></td>
				            </tr>

				        </table>

				    </div>

				    <br>

				    <div style="width:100%;font-size:11px;">
				        <?php if ($has_unfixed) { ?>
				            <b>Note :</b> This Purchase Order contains Unfixed rate lines. Those lines record a quantity
				            commitment only; rate and value are agreed at Supplier Bill Entry. The total above covers the
				            Fixed rate lines only.
				        <?php } else { ?>
				            <b>Note :</b> Rate fixed in advance. Supplier is committed to deliver
				            <?php echo number_format($grand_net, 3, '.', ''); ?> g against this Purchase Order at the rates shown.
				        <?php } ?>
				    </div>

				    <br><br>

				    <div style="width:100%;font-size:11px;">
				        <div style="width:49%; display:inline-block;text-align:left;">Supplier Acknowledgement</div>
				        <div style="width:49%; display:inline-block;text-align:right;">For <?php echo (isset($comp_details['company_name']) ? $comp_details['company_name'] : '');?></div>
				    </div>

				</div>
			</div>
  </div>
 </div>
</div>

</span>

</body></html>
