  <!-- Content Wrapper. Contains page content -->

  <div class="content-wrapper">

<!-- Content Header (Page header) -->



<!-- Main content -->

<section class="content">

  <div class="row">

	<div class="col-xs-12">

	   

	   <div class="box box-primary">

		 <div class="box-body">  

				   <div class="box box-info stock_details">

				<div class="box-header with-border">

				 <div class="col-md-2">  <h3 class="box-title">Supplier Ledger</h3> </div>

					
				 <div class="col-md-2"> 
					 <input type="checkbox" id="metal_wise_required" class="metal_wise_required" name="metal_wise_required" value="1"> 
					 <label for="metal_wise_required"> Metal column required</label>
				 </div>

					<div class="col-md-2"> 

						<select id="metal" class="form-control" style="width:100%;" multiple></select>

					</div>

					

					<div class="col-md-2"> 

						<select id="karigar" class="form-control" style="width:100%;"></select>

					</div>

					<div class="col-md-2"> 

						<div class="form-group">    

	

							<?php   

								$fromdt = date("d/m/Y");

								$todt = date("d/m/Y");

							?>

							   <input type="text" class="form-control pull-right dateRangePicker" id="dt_range" placeholder="From Date -  To Date" value="<?php echo $fromdt.' - '.$todt?>" readonly="">  

						</div> 

					</div>

					 <div class="col-md-2"> 

						 <div class="form-group">

							 <button type="button" id="supplier_ledger_search" class="btn btn-info">Search</button>   

						 </div>

					 </div>

				  <div class="box-tools pull-right">

					<button class="btn btn-box-tool" data-widget="collapse" data-toggle="tooltip" title="Collapse"><i class="fa fa-minus"></i></button>

				  </div>

				</div>

				<div class="box-body">

					<div class="row">

						<div class="box-body">

						   <div class="table-responsive">
							  
							  <?php
								  $hide_pcs = get_flag('hide_supplier_ledger_pcs', false);
								  $hide_gwt = get_flag('hide_supplier_ledger_gwt', false);
								  $hide_nwt = get_flag('hide_supplier_ledger_nwt', false);
								  $issue_colspan   = 1 + (!$hide_pcs ? 1 : 0) + (!$hide_gwt ? 1 : 0) + (!$hide_nwt ? 1 : 0);
								  $receipt_colspan = $issue_colspan;
								  $balance_colspan = 2 + (!$hide_pcs ? 1 : 0) + (!$hide_gwt ? 1 : 0) + (!$hide_nwt ? 1 : 0);
							  ?>
							  <div id="smith_ledgere_list_sip">
								<table id="smith_ledgere_list" class="table table-bordered table-striped text-center">

								<thead>

									<tr class="tablerow">

										<th colspan="3"></th>

										<th colspan="<?php echo $issue_colspan; ?>">Issue</th>

										<th colspan="<?php echo $receipt_colspan; ?>">Receipt</th>

										<th colspan="2">Amount</th>

										<th colspan="<?php echo $balance_colspan; ?>">Balance</th>

										<th colspan="3"></th>

									</tr>

									<tr>

										<th>Particulars</th>

										<th>Tran No</th>

										<th>Tran Date</th>



										<?php if (!$hide_pcs): ?>
										<th>Issue Pcs</th>
										<?php endif; ?>
										<?php if (!$hide_gwt): ?>
										<th>IssueGross Wt</th>
										<?php endif; ?>
										<?php if (!$hide_nwt): ?>
										<th>IssueNet Wt</th>
										<?php endif; ?>
										<th>Issue Pure Wt</th>



										<?php if (!$hide_pcs): ?>
										<th>Receipt Pcs</th>
										<?php endif; ?>
										<?php if (!$hide_gwt): ?>
										<th>Receipt Gross Wt</th>
										<?php endif; ?>
										<?php if (!$hide_nwt): ?>
										<th>Receipt Net Wt</th>
										<?php endif; ?>
										<th>Receipt Pure Wt</th>



										<th>Amount Debit</th>

										<th>Amount Credit</th>



										<?php if (!$hide_pcs): ?>
										<th>Blc Pcs</th>
										<?php endif; ?>
										<?php if (!$hide_gwt): ?>
										<th>Blc Gross Wt</th>
										<?php endif; ?>
										<?php if (!$hide_nwt): ?>
										<th>Blc Net Wt</th>
										<?php endif; ?>
										<th>Blc Pure Wt</th>

										<th>Blc Amt</th>





										<th>Touch</th>

										<th>Rate</th>

										<th>Narration</th>







										

									</tr>

								</thead> 

								<tbody></tbody>

								<tfoot></tfoot>

								</table>

							</div>
							<div id="smith_ledgere_metal_list_sip" style="display:none;">
							 <table id="smith_ledgere_metal_list" class="table table-bordered table-striped text-center">

								<thead>

								  <tr>

									<th>Supplier</th>

									<th>Gold Pure Wt</th>

									<th>Silver Pure Wt</th>

									<th>Platinum Pure Wt</th>

									<th>Amount</th>

								  </tr>

									</thead> 

								 <tbody></tbody>

								 <tfoot></tfoot>

							 </table>
							</div>
						  </div>

						</div> 

					</div> 

				</div>

			</div>

		</div><!-- /.box-body -->

		<div class="overlay" style="display:none">

		  <i class="fa fa-refresh fa-spin"></i>

		</div>

	  </div>

	</div><!-- /.col -->

  </div><!-- /.row -->

</section><!-- /.content -->

</div><!-- /.content-wrapper -->

<script type="text/javascript">
var SAL_HIDE_PCS = <?php echo $hide_pcs ? 'true' : 'false'; ?>;
var SAL_HIDE_GWT = <?php echo $hide_gwt ? 'true' : 'false'; ?>;
var SAL_HIDE_NWT = <?php echo $hide_nwt ? 'true' : 'false'; ?>;
</script>



