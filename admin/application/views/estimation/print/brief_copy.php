<html><head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
		<title>Estimation</title>
		<link rel="stylesheet" href="<?php echo base_url();?>assets/css/estimation.css">
	<style type="text/css">
		body, html {
		margin-bottom:0
		}
		 @page { 
		 	size: 78mm;
		 	margin-bottom:40px !important;
		 } 
		 
		 /* output size */
		 span { display: inline-block; }
	</style>
</head>
<body class="plugin">
	<span class="PDFReceipt">
		<div class="printable">
			<div class="item_details">
    			<div class="breif_copy">
    				<label><b>BRIEF COPY</b></label>
    			</div>
    			<div style="margin-top:4px;font-size:10px;width:100%;">
    				<label><?php echo $estimation['created_time'].' , Estimation - '. $estimation['esti_no'];?></label>
    				<br><label><?php echo $estimation['customer_name'].($estimation['village_name']!='' ? ' / '.$estimation['village_name']:'').' / '.$estimation['mobile']; ?></label>
    				<?php 
    				if(sizeof($est_other_item['item_details'])>0)
    				{
    
    					foreach($est_other_item['item_details'] as $items){
    						// Stone Weight Markup: chargeable net (record-driven; delta=0 when no chargeable stored)
    						$bc_delta = 0;
    						if (!empty($items['stone_details'])) {
    							foreach ($items['stone_details'] as $bs) {
    								if (($bs['is_apply_in_lwt'] ?? 0) == 1 && isset($bs['chargeable_wt']) && $bs['chargeable_wt'] !== null && $bs['chargeable_wt'] !== '') {
    									$bdiv = (isset($bs['divided_by_value']) && $bs['divided_by_value'] > 0) ? $bs['divided_by_value'] : 1;
    									$bc_delta += (($bs['chargeable_wt'] - $bs['wt']) / $bdiv);
    								}
    							}
    						}
    						$bc_net = number_format($items['net_wt'] - $bc_delta, 3, '.', '');
    					?>
    					<br><span style="font-size:10px;"><?php echo $items['product_name'].' / '.($items['design_name']!='' ? $items['design_name'].' / ' : '').$bc_net.' / '.($items['size']!='' ? $items['size'].' - ':'').($items['tag_id']!='' ? ' / '.$items['tag_code'] :'');?></span>
    				<?php }?>
    				<p></p>
    				<?php }?>
    					<?php foreach($est_other_item['old_matel_details'] as $items){?>
    					<br><label><?php echo $items['metal'].' / '.$items['gross_wt'].' / '.$items['amount'];?></label>
    				<?php }?>
    				<br><label>EMP-ID : <?php echo $this->session->userdata('uid').' / '.date('h:i A', strtotime(date('d-m-Y H:i:s')));?></label>
    			</div><p></p>
			</div>
		</div>
	 </span>          
</body></html>