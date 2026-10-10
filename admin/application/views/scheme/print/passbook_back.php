<html><head>
	<meta charset="utf-8"> 
	<title>Billing Receipt</title>
	<link rel="stylesheet" href="<?php echo base_url();?>assets/css/passbook.css">
	<style type="text/css">
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
											<td class="table_heading" style="width: 5%; white-space: nowrap;">INS</td>
											<td class="table_heading" style="width: 11%; white-space: nowrap;">DATE</td>
											<td class="table_heading" style="width: 16%; white-space: nowrap;">RECPTNO</td>
											<td class="table_heading" style="width: 12%; white-space: nowrap;">AMOUNT</td>
											<?php if($customer['is_weight_scheme'] == 1)
											{ ?>
											<td class="table_heading" style="width: 10%; white-space: nowrap;">RATE</td>
											<td class="table_heading" style="width: 10%; white-space: nowrap;">WEIGHT</td>
											<td class="table_heading" style="width: 11%; white-space: nowrap;">ACC WGT</td>
											<?php } ?>
											
											<td class="table_heading" style="width: 10%; white-space: nowrap;">MODE</td>
										<!--	<td class="table_heading " style="width: 15%">REMARKS</td> -->
											<td class="table_heading" style="width: 15%; white-space: nowrap;">EMP CODE</td><!--alignRight-->
										</tr> 
                						<?php
            						    }else{ ?>
            						        <tr>
												<td style="width: 5%; white-space: nowrap;">&nbsp;</td>
												<td style="width: 11%; white-space: nowrap;">&nbsp;</td>
												<td style="width: 16%; white-space: nowrap;">&nbsp;</td>
												<td style="width: 12%; white-space: nowrap;">&nbsp;</td>
												<?php if($customer['is_weight_scheme'] == 1)
												{ ?>
												<td style="width: 10%; white-space: nowrap;">&nbsp;</td>
												<td style="width: 10%; white-space: nowrap;">&nbsp;</td>
												<td style="width: 11%; white-space: nowrap;">&nbsp;</td>
												<?php } ?>
												<td style="width: 10%; white-space: nowrap;">&nbsp;</td>
												<td style="width: 15%; white-space: nowrap;">&nbsp;</td>
											</tr>
            						    <?php } 
                						$tot_wgt = 0;
                						foreach($payment as $pay){
                						    $tot_wgt = $tot_wgt + $pay['metal_weight'];
                						    if($pay['is_print_taken'] == 1){ ?>
                						        <tr>
                								    <td style="width: 5%; white-space: nowrap;">&nbsp;</td>
                								    <td style="width: 11%; white-space: nowrap;">&nbsp;</td>
                								    <td style="width: 16%; white-space: nowrap;">&nbsp;</td>
                								    <td style="width: 12%; white-space: nowrap;">&nbsp;</td>
                								    <?php if($customer['is_weight_scheme'] == 1)
                								    { ?>
                								    <td style="width: 10%; white-space: nowrap;">&nbsp;</td>
                								    <td style="width: 10%; white-space: nowrap;">&nbsp;</td>
                								    <td style="width: 11%; white-space: nowrap;">&nbsp;</td>
                								    <?php } ?>
                								    <td style="width: 10%; white-space: nowrap;">&nbsp;</td>
                								    <td style="width: 15%; white-space: nowrap;">&nbsp;</td>
            									</tr>
                						   <?php }else{ ?>
                						       <tr>
                									<td style="width: 5%; white-space: nowrap;"><?php echo $pay['ins'];?></td> 
                									<td style="width: 11%; white-space: nowrap;"><?php echo $pay['date_payment'];?></td> <!--class="alignRight"  class='textOverflowHidden'-->
                									<td style="width: 16%; white-space: nowrap;"><?php echo $pay['receipt_no'];?></td>
                									<td style="width: 12%; white-space: nowrap;"><?php echo (number_format((float)($pay['payment_amount']),0,'.',''));?></td>
                									<?php if($customer['is_weight_scheme'] == 1)
											        { ?>
                									<td style="width: 10%; white-space: nowrap;"><?php echo round($pay['metal_rate']);?></td>
                									<td style="width: 10%; white-space: nowrap;"><?php echo $pay['metal_weight'] > 0 ? formatMetalWeight($pay['metal_weight']) : '';?></td>
                									<td style="width: 11%; white-space: nowrap;"><?php echo $tot_wgt > 0 ? formatMetalWeight($tot_wgt) : '';?></td>
                									<?php } ?>
                									<td style="width: 10%; white-space: nowrap;"><?php echo $pay['payment_mode'];?></td>
                								<!--	<td><?php echo $pay['remark'];?></td> -->
                									<td style="width: 15%; white-space: nowrap;"></td>
                								
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