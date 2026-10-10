<html><head>
	<meta charset="utf-8"> 
	<title>Billing Receipt</title>
	<link rel="stylesheet" href="<?php echo base_url();?>assets/css/passbook.css">
	<style type="text/css">
	@page { size: 400pt 690pt; margin: 0; }
	body { margin: 0 !important; padding: 0 !important; }
	.PDFReceipt { margin: 0 !important; padding: 0 !important; }
	.PDFReceipt * { font-size: 7pt !important; }
	.PDFReceipt table { table-layout: fixed !important; width: 380pt !important; margin: 0 !important; padding: 0 !important; border-collapse: collapse; border-spacing: 0; }
	.PDFReceipt th, .PDFReceipt td { white-space: nowrap; padding: 2px 1px !important; text-align: center; margin: 0; }
	.content-wrapper { padding-top: 10px !important; padding-left: 30px !important; margin: 0 !important; }
	.box, .box-body, .container-fluid, .col-xs-12, .table-responsive, #printable { padding: 0 !important; margin: 0 !important; }
	.hare_krishna { margin: 0 !important; padding: 0 !important; height: 0 !important; }
	@font-face {
        font-family: 'Bamini Tamil';
        src: url('https://retail.logimaxindia.com/etail_v1/admin/custom_fonts/BaaminiPlain.ttf');
    }
    
	#terms{
	    font-family: 'Bamini Tamil';
	}
	 .head
	 {
		 color: black;
		 font-size: 30px;
	 }
	 .alignCenter {
		 text-align: center;
	 }
	 .alignRight {
		 text-align: right;
	 }
	 .table_heading {
		 font-weight: bold;
	 }
	 .textOverflowHidden {
		white-space: nowrap; 
		overflow: hidden;
		text-overflow: ellipsis;
	 }
	 .border{
	     border-bottom : 2px solid #000 !important;
	 }
    </style>
</head>
<body>
    <?php
        function moneyFormatIndia($num) {
            return preg_replace("/(\d+?)(?=(\d\d)+(\d)(?!\d))(\.\d+)?/i", "$1,", $num);
        }
    ?>
    <span class="PDFReceipt"> 
        <div class="hare_krishna"></div>
        <!--<div class="header_top"></div>-->
		<?php if($customer['one_time_premium'] == 0){ ?>
		<div  class="content-wrapper">
        	<div class="box">
        		<div class="box-body">
        			<div  class="container-fluid">
        				<div id="printable"> 
        					<!--<hr class="header_dashed"> -->
        					<div class="col-xs-12">
        						<div class="table-responsive">
        						  
            						<table id="" class="table text-center" >
            						    <?php 
            						    if($payment[0]['is_print_taken'] == 0){ ?>
            						    <tr>
											<td class="table_heading" style="width: 6%">INS</td>
											<td class="table_heading" style="width: 12%">DATE</td>
											<td class="table_heading" style="width: 14%">RECPTNO</td>
											<td class="table_heading" style="width: 12%">AMOUNT</td>
											<?php if($customer['is_weight_scheme'] == 1)
											{ ?>
											<td class="table_heading" style="width: 10%">RATE</td>
											<td class="table_heading" style="width: 10%">WEIGHT</td>
											<td class="table_heading" style="width: 12%">ACC WGT</td>
											<?php } ?>
											
											<td class="table_heading" style="width: 10%">MODE</td>
										<!--	<td class="table_heading " style="width: 15%">REMARKS</td> -->
											<td class="table_heading" style="width: 14%">EMP SIGN</td><!--alignRight-->
										</tr> 
                						<?php
            						    }else{ ?>
            						        <tr><td> </td><td> </td><td> </td><td> </td><td> </td><td> </td><td> </td><td> </td></tr>
            						    <?php } 
                						$tot_wgt = 0;
                						foreach($payment as $pay){
                						    $tot_wgt = $tot_wgt + $pay['metal_weight'];
                						    if($pay['is_print_taken'] == 1){ ?>
                						        <tr>
                								    <td> </td><td> </td><td> </td><td> </td><td> </td><td> </td><td> </td><td> </td><td> </td><td> </td>
            									</tr>
                						   <?php }else{ ?>
                						       <tr>
                									<td><?php echo $pay['ins'];?></td> 
                									<td><?php echo $pay['date_payment'];?></td> <!--class="alignRight"  class='textOverflowHidden'-->
                									<td>
                									    <?php echo $pay['receipt_no'];?>
                									    
                									 </td>
                									<td><?php echo (number_format((float)($pay['payment_amount']),0,'.',''));?></td>
                									<?php if($customer['is_weight_scheme'] == 1)
											        { ?>
                									<td><?php echo round($pay['metal_rate']);?></td>
                									<td><?php echo $pay['metal_weight'] > 0 ? formatMetalWeight($pay['metal_weight']) : '';?></td>
                									<td><?php echo $tot_wgt > 0 ? formatMetalWeight($tot_wgt) : '';?></td>
                									<?php } ?>
                									<td><?php echo $pay['payment_mode'];?></td>
                								<!--	<td><?php echo $pay['remark'];?></td> -->
                									<td></td>
                								
            								  </tr>
            						    <?php } } ?>
            						</table>
            					</div>
        					</div>
        				</div>
        			</div>
        		</div>
        	</div>
        </div>
		<?php } ?>
		
		<br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br>
        <!--<div  class="content-wrapper">
                	<div class="box">
                		<div class="box-body">
                		    <div style="width: 14%; text-align:center;">Terms and Conditions</div>
                		    <p id="terms"><?php echo $customer['description'] ?></p>
                		    </div>
                		    </div>
                		    </div>-->
    </span>    
    
    
    
</body></html>


 <?php
    // function trim_decimal($value, $precision)
    // {
    //     $precision = (int)$precision;
    //     // Convert scientific notation to decimal string safely
    //     if (stripos((string)$value, 'e') !== false) {
    //         $value = sprintf('%.20f', $value); // expand E notation
    //         $value = rtrim($value, '0');
    //         $value = rtrim($value, '.');
    //     }
    //     $value = (string)$value;
    //     // Precision = 0 → integer only
    //     if ($precision === 0) {
    //         return explode('.', $value)[0];
    //     }
    //     // No decimal point → pad zeros
    //     if (strpos($value, '.') === false) {
    //         return $value . '.' . str_repeat('0', $precision);
    //     }
    //     list($int, $dec) = explode('.', $value, 2);
    //     // Truncate + pad
    //     $dec = substr($dec, 0, $precision);
    //     $dec = str_pad($dec, $precision, '0');
    //     return $int . '.' . $dec;
    // }