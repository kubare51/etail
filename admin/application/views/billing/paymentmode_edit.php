      <!-- Content Wrapper. Contains page content -->
      <style>
      	.remove-btn {
      		margin-top: -168px;
      		margin-left: -38px;
      		background-color: #e51712 !important;
      		border: none;
      		color: white !important;
      	}

      	.sm {
      		font-weight: normal;
      	}
		.container {
			left: 0;
			right: 0;
			margin: auto;
			margin-top: 1px;
			border: 1px solid rgba(180, 180, 180, .5);
			border-radius: 25px;
		}
		fieldset 
		{
			border: 1px solid #ddd !important;
			margin: 0;
			padding: 10px;       
			position: relative;
			border-radius:4px;
			padding-left:10px!important;
		}	
	
		legend
		{
			font-size:14px;
			font-weight:bold;
			margin-bottom: 0px; 
			width: 11%;
			border-bottom: none;
			/* border: 1px solid #ddd; */
			/* border-radius: 4px;  */
			padding: 5px 5px 5px 5px; 
		}
		.legend2 {
			width: 9%;
		}
		.modal_btns {
			display: flex;
			justify-content: space-between;
		}
		/* ===== Incentive Employee Modal ===== */
		#item-emp-modal .modal-header {
			background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
			color: #fff;
			border-radius: 0;
			padding: 12px 15px;
		}
		#item-emp-modal .modal-header .modal-title {
			font-weight: 600;
			font-size: 15px;
		}
		#item-emp-modal .modal-header .close {
			color: #fff;
			opacity: 0.8;
			text-shadow: none;
		}
		#item-emp-modal .modal-header .close:hover {
			opacity: 1;
		}
		#item-emp-modal .item-count-badge {
			background: rgba(255,255,255,0.2);
			color: #fff;
			font-size: 11px;
			font-weight: 600;
			padding: 3px 10px;
			border-radius: 12px;
			margin-left: 10px;
		}
		#item_emp_table tbody td {
			vertical-align: middle;
		}
		#item_emp_table tbody tr.row-updated {
			background: #eafaf1 !important;
		}
      </style>
      <div class="content-wrapper">
      	<!-- Content Header (Page header) -->
      	<section class="content-header">
      		<h1>
      			Bill Edit
      		</h1>
      		<ol class="breadcrumb">
      			<li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
      			<li><a href="#">Billing</a></li>
      			<li class="active">Bill Edit</li>
      		</ol>
      	</section>

      	<!-- Main content -->
      	<section class="content order">

      		<!-- Default box -->
      		<div class="box box-primary">
				<div class="container">
					<div class="box-body">
					  <fieldset id="cusDet">
					  <legend>Customer Details</legend>
      				<?php
						if ($this->session->flashdata('chit_alert')) {
							$message = $this->session->flashdata('chit_alert');
						?>
      					<div class="alert alert-<?php echo $message['class']; ?> alert-dismissable">
      						<button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
      						<h4><i class="icon fa fa-check"></i> <?php echo $message['title']; ?>!</h4>
      						<?php echo $message['message']; ?>
      					</div>

      				<?php } ?>
      				<!-- form container -->
      				<!-- form -->

      				<!-- <div class="row">

                               <div class="form-group">
                        <label for="" class="col-md-2 col-md-offset-3">Branch<span class="error">*</span></label>
                            <div class="col-md-3">
                                <?php if ($this->session->userdata('id_branch') == '') {  ?>
                                     <select id="branch_select" style="width:64%;" required tabindex="1"></select>
                                <?php } else { ?>
                                    <select id="branch_select" style="width:50%;" required disabled></select>
                                    <?php  } ?>
                            </div>
                               </div>

							   </div> -->
      				<br>
      				<div class="row">
      					<div class="col-md-2"></div>
      					<label for="tire_minimum_required" class="col-md-2">Bill No<span class="error">*</span> </label>

      					<div class="col-md-3">
      						<div class="input-group">
      							<div class="input-group">
      								<span class="input-group-btn">
      									<select class="form-control" id="fin_year_select" style="width:100px;">
      										<?php
												foreach ($billing['financial_year'] as $fin_year) { ?>
      											<option value=<?php echo $fin_year['fin_year_code']; ?> <?php echo ($fin_year['fin_status'] == 1 ? 'selected' : '')  ?>><?php echo $fin_year['fin_year_name']; ?></option>
      										<?php }
												?>
      									</select>
      								</span>
      								<input type="text" class="form-control text" id="paymentmode_ed" placeholder="Enter Bill No">
      								<span class="input-group-btn">
      									<button type="button" id="billed_search" class="btn btn-default btn-flat"><i class="fa fa-search"></i></button>
      								</span>
      							</div>
      						</div>
      					</div>
      				</div>
      				<br>

      				<div class="row">
      					<div class="col-md-2"></div>
      					<label for="" class="col-md-2">Customer Name<span class="error">*</span></label>
      					<div class="col-md-3">
      						<div class="input-group col-md-12">
      							<input type="text" class="form-control text" id="c_name" style="text-transform:uppercase;">
								<input type="hidden" class="cus_id" id="bill_cus_id">
      						</div>
      					</div>
						<div class="col-md-2">
							<button class="btn btn-primary pull-right" id="updBilledName" onclick="updateBillCusDetails({'customer_name' : document.getElementById('c_name').value})">Update</button>
						</div>
      				</div>

      				<br>
      				<div class="row">
      					<div class="col-md-2"></div>
      					<label for="" class="col-md-2">PAN Number<span class="error">*</span></label>
      					<div class="col-md-3">
      						<div class="input-group col-md-12">
      							<input type="text" class="form-control text" id="pan_no" style="text-transform:uppercase;">
      						</div>
      					</div>
						<div class="col-md-2">
							<button class="btn btn-primary pull-right" id="updPanNo" onclick="updateBillCusDetails({'pan_no' : document.getElementById('pan_no').value})">Update</button>
						</div>
      				</div>

      				<br>
      				<div class="row">
      					<div class="col-md-2"></div>
      					<label for="" class="col-md-2">GST Number<span class="error">*</span></label>
      					<div class="col-md-3">
      						<div class="input-group col-md-12">
      							<input type="text" class="form-control text" id="gst_num" style="text-transform:uppercase;">
      						</div>
      					</div>
						<div class="col-md-2">
							<button class="btn btn-primary pull-right" id="updGstNo" onclick="updateBillCusDetails({'gst_no' : document.getElementById('gst_num').value, 'billing_for': document.querySelector('input[name=\'billing_for\']:checked') ? document.querySelector('input[name=\'billing_for\']:checked').value : null})">Update</button>
						</div>
      				</div>

      				<br>
      				<div class="row">
      					<div class="col-md-2"></div>
      					<label for="" class="col-md-2">Aadhaar Number<span class="error">*</span></label>
      					<div class="col-md-3">
      						<div class="input-group col-md-12">
      							<input type="text" class="form-control text" id="aadhaar_no" style="text-transform:uppercase;">
      						</div>
      					</div>
						<div class="col-md-2">
							<button class="btn btn-primary pull-right" id="updAadharNo" onclick="updateBillCusDetails({'aadhaar_no' : document.getElementById('aadhaar_no').value})">Update</button>
						</div>
      				</div>

      				<br>
      				<div class="row">
      					<div class="col-md-2"></div>
      					<label for="" class="col-md-2">Customer Type<span class="error">*</span></label>
      					<div class="col-md-3">
						  	<div class="form-check">
							  	<label class="radio-inline"><input type="radio" name="billing_for" class="form-check-input text" id="billing_for1" style="text-transform:uppercase;" value=1 />INDIVIDUAL</label>
      							<label class="radio-inline"><input type="radio" name="billing_for" class="form-check-input text" id="billing_for2" style="text-transform:uppercase;" value=2 />COMPANY</label>
      						</div>
      					</div>
						  <div class="col-md-2">
							<button class="btn btn-primary pull-right" id="updCusType"
								onclick="updateBillCusDetails({'billing_for': document.querySelector('input[name=\'billing_for\']:checked').value})">
								Update
							</button>
						</div>
      				</div>

      				<br>
      				<div class="row">
      					<div class="col-md-2"></div>
      					<label for="" class="col-md-2">Employee Name<span class="error">*</span></label>
      					<div class="col-md-3">
      						<div class="input-group col-md-12">
      							<select class="form-control" id="emp_select" style="text-transform:uppercase;">
								</select>
								<input type="hidden" id="id_employee">
							</div>
						</div>
						<div class="col-md-2">
							<button class="btn btn-primary pull-right" id="updEmpName" onclick="updateBillCusDetails({'id_employee' : document.getElementById('id_employee').value})">Update</button>
						</div>
						<div class="col-md-2">
							<button type="button" class="btn btn-info" id="btnEditItemEmp" style="display:none;" title="Incentive Employee"><i class="fa fa-users"></i> Incentive</button>
						</div>
      				</div>

					  <br>
					  <div class="row">
					  	<div class="col-md-2"></div>
					  	<label for="" class="col-md-2"></label>
					  	<div class="col-md-3"></div>
					  	<div class="col-md-2">
					  		<button type="button" class="btn btn-warning pull-right" id="btnChangeCustomer" style="display:none;" title="Change Bill Customer">
					  			<i class="fa fa-exchange"></i> Change Customer
					  		</button>
					  	</div>
					  </div>

					  <br>

      				<!-- <div class="row">
      					<div class="col-md-2"></div>
      					<label for="" class="col-md-2">Customer Name<span class="error">*</span></label>
      					<div class="col-md-3">
      						<div class="input-group col-md-12">
      							<input type="text" class="form-control text" id="bill_cus_name">
								  <input type="hidden" id="bill_cus_id">
      						</div>
      					</div>
						<div class="col-md-2">
							<button class="btn btn-primary pull-right" id="updCusName" onclick="updateBillCusDetails({'bill_cus_id' : document.getElementById('bill_cus_id').value})">Update</button>
						</div>
      				</div> -->
      				<br>
					</fieldset>
					<fieldset  id="cusPayDet" class="fs2">
				  		<legend class="legend2">Billing Details</legend>
      				<div class="row">
      					<div class="col-md-2"></div>
      					<label for="" id="rcvd_amt_lbl" class="col-md-2">Received Amount</label>
      					<div class="col-md-3">
      						<div class="input-group col-md-12">
      							<input class="form-control" id="billed_cash" name="billing[billed_cash]" type="text" disabled value="" />
      						</div>
      					</div>
      				</div>
      				
      				<br>
      				<div class="row">
      					<div class="col-md-2"></div>
      					<label for="" class="col-md-2">Cash</label>
      					<div class="col-md-3">
							  <div class="input-group col-md-12">
								  <input class="form-control" id="make_pay_cash" autocomplete="off" name="billing[cash_payment]" type="number" placeholder="Enter Amount" value="" />
								</div>
      					</div>
      				</div>
      				<br>
      				<div class="row">
						  <div class="col-md-2"></div>
      					  <label for="" class="col-md-2">Card Payment</label>
						  <span class="input-group col-md-3">
							<div class="col-md-12">
								<div class="input-group">
									<input class="form-control" id="cc_billed_cash" name="billing[cc_billed_cash]" type="text" readonly value="" />
									<a class="input-group-addon btn btn-default" id="card_detail_modal" href="#"><b>+</b></a>
								</div>
							</div>
						  </span>
      				</div>
      				<!--  <br>
					  <div class="row">
                          <label for="" class="col-md-2">Debit Card</label>
						  <div class="col-md-2">
                         <div class="input-group col-md-12">
						 <input class="form-control" id="dc_billed_cash" name="billing[dc_billed_cash]" style="margin-right:-2px" type="text" readonly value=""/>
						 <br>
                                 <a class="btn bg-olive btn-xs pull-right" id="card_detail_modal" href="#" data-toggle="modal" data-target="#card-detail-modal" style="margin-right:-50px;margin-top:-13px;" ><b>+</b></a>
                              </div>
							  </div>
                      </div>-->
      				<br>
      				<div class="row">
      					<div class="col-md-2"></div>
      					<label for="" class="col-md-2">Cheque</label>
      					<span class="input-group col-md-3">
      						<div class="col-md-12">
								<div class="input-group">
									<input class="form-control" id="chq_billed_cash" name="billing[chq_billed_cash]" type="text" readonly value="" />
									<a class="input-group-addon btn btn-default" id="cheque_modal" href="#" ><b>+</b></a>
								</div>
      						</div>
						</span>
      				</div>
      				<br>
      				<div class="row">
						<div class="col-md-2"></div>
						<label for="" class="col-md-2">Net Banking</label>
						<div class="col-md-3">
							<div class="input-group">
								<input class="form-control" id="nb_billed_cash" name="billing[nb_billed_cash]" type="text" readonly />
								<a class="input-group-addon btn btn-default" id="net_bank_modal" href="#" ><b>+</b></a>
							</div>
						</div>
						<div class="col-md-2">
							<button class="btn btn-primary pull-right" id="save_bill_edit">Update</button>
						</div>
					</div>
      			</div>

      			<p class="hepl-block"></p>
      			<div class="row">
						<div class="col-md-2 col-md-offset-5">
						<input type="hidden" id="card_payment" name="billing[card_pay]" value="" />
							<input type="hidden" id="chq_payment" name="billing[chq_pay]" value="" />
							<input type="hidden" id="nb_payment" name="billing[net_bank_pay]" value="" />
							<input type="hidden" id="hidden_bill_id" value="" />
							<input type="hidden" id="max_return_amt" value="<?php echo isset($billing['max_return_amt']) ? $billing['max_return_amt'] : 0; ?>" />
							<input type="hidden" id="validate_max_cash" value="<?php echo isset($billing['validate_cash_amt']) ? $billing['validate_cash_amt'] : 0; ?>" />
							<input type="hidden" id="max_cash_amt" value="<?php echo isset($billing['max_cash_amt']) ? $billing['max_cash_amt'] : 0; ?>" />
							<input type="hidden" id="is_eda" value="1" />
							<button type="button" class="btn btn-default btn-cancel" id="cancel_bill_edit">Back</button>

						</div> <br><br>
      			</div>
				  <div class="overlay" style="display:none">
      			<i class="fa fa-refresh fa-spin"></i>
			</div>
		</fieldset>
		</div> <!-- box-body-->
      </div> <!-- Default box-->
	  </div>
      <?php echo form_close(); ?>

      <!-- /form -->
      </section>
      </div>
      </div>

      <!-- Incentive Employee Modal -->
      <div class="modal fade" id="item-emp-modal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true" data-backdrop="static" data-keyboard="false">
      	<div class="modal-dialog" style="width:70%;">
      		<div class="modal-content">
      			<div class="modal-header">
      				<button type="button" class="close inc_emp_close_btn" data-dismiss="modal"><span aria-hidden="true">&times;</span></button>
      				<h4 class="modal-title"><i class="fa fa-users"></i> Incentive Employee <span class="item-count-badge" id="itemCountBadge">0 items</span></h4>
      			</div>
      			<div class="modal-body">
      				<div class="box-body">
      					<div class="table-responsive">
      						<table id="item_emp_table" class="table table-bordered">
      							<thead>
      								<tr>
      									<th width="5%">#</th>
      									<th width="25%">Product Name</th>
      									<th width="12%">Gross Wt</th>
      									<th width="15%">Current Employee</th>
      									<th width="33%">Change Employee</th>
      									<th width="10%">Action</th>
      								</tr>
      							</thead>
      							<tbody id="itemEmpTableBody">
      								<tr><td colspan="6" class="text-center text-muted">Loading items...</td></tr>
      							</tbody>
      						</table>
      					</div>
      				</div>
      			</div>
      			<div class="modal-footer">
      				<button type="button" class="btn btn-close btn-warning inc_emp_close_btn" data-dismiss="modal">Close</button>
      			</div>
      		</div>
      	</div>
      </div>
      <!-- /Incentive Employee Modal -->

      <!-- Customer Change Modal -->
      <div class="modal fade" id="customer-change-modal" role="dialog" aria-labelledby="cusChangeLabel" aria-hidden="true" data-backdrop="static" data-keyboard="false">
      	<div class="modal-dialog" style="width:70%;">
      		<div class="modal-content">
      			<div class="modal-header" style="background:#f7f7f7;border-bottom:2px solid #3c8dbc;">
      				<button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
      				<h4 class="modal-title" id="cusChangeLabel"><i class="fa fa-exchange" style="color:#3c8dbc;"></i> Change Customer</h4>
      			</div>
      			<div class="modal-body" style="padding:15px 20px;">
      				<!-- TOP ROW: Search+Reason (left) | Impact Records (right) -->
      				<div class="row">
      					<div class="col-md-7">
      						<div class="form-group" style="margin-bottom:10px;">
      							<label style="font-weight:600;">Search New Customer</label>
      							<select class="form-control" id="cus_change_search" style="width:100%;"></select>
      						</div>
      						<div class="form-group" style="margin-bottom:0;">
      							<label style="font-weight:600;">Reason for Change <span class="text-red">*</span></label>
      							<textarea class="form-control" id="cus_change_reason" rows="3" placeholder="Enter reason for customer change (required)..." maxlength="500" style="resize:vertical;"></textarea>
      						</div>
      					</div>
      					<div class="col-md-5">
      						<div id="cusChangeImpact" style="display:none;">
      							<div style="border-left:4px solid #f39c12;background:#fcf8e3;padding:10px 14px;border-radius:2px;height:100%;">
      								<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
      									<strong style="color:#8a6d3b;"><i class="fa fa-exclamation-triangle"></i> Impacted Records</strong>
      									<small class="text-danger"><i class="fa fa-refresh"></i> Will be updated</small>
      								</div>
      								<table class="table table-condensed" style="margin-bottom:0;font-size:12px;background:transparent;">
      									<thead><tr style="border-bottom:1px solid #e8d8b0;"><th>Record</th><th>Details</th><th style="width:45px;text-align:center;">Count</th></tr></thead>
      									<tbody id="ccImpactBody"></tbody>
      								</table>
      							</div>
      						</div>
      						<div id="cusChangeImpactPlaceholder" style="border:1px dashed #d2d6de;border-radius:3px;padding:30px 15px;text-align:center;color:#aaa;height:100%;">
      							<i class="fa fa-list-alt" style="font-size:24px;"></i><br>
      							<small>Impact preview will appear here<br>after selecting a customer</small>
      						</div>
      					</div>
      				</div>
      				<!-- BOTTOM ROW: 3-Column Comparison (shown after customer selected) -->
      				<div class="row" id="cusChangeDetailsRow" style="display:none; margin-top:12px;">
      					<!-- Col 1: Current Customer -->
      					<div class="col-md-4">
      						<div class="panel" style="margin-bottom:0;border-color:#d2d6de;">
      							<div class="panel-heading" style="background:#f4f4f4;color:#444;border-color:#d2d6de;padding:8px 12px;">
      								<strong><i class="fa fa-user-circle-o"></i> Current Customer</strong>
      								<span class="pull-right label" id="ccCurrentType" style="font-size:10px;">B2C</span>
      							</div>
      							<div class="panel-body" style="padding:0;">
      								<table class="table table-condensed" style="margin-bottom:0;font-size:12px;">
      									<tr><td style="border-top:none;width:70px;color:#888;"><i class="fa fa-user"></i> Name</td><td style="border-top:none;font-weight:600;" id="ccCurrentName">-</td></tr>
      									<tr><td style="color:#888;"><i class="fa fa-phone"></i> Mobile</td><td id="ccCurrentMobile">-</td></tr>
      									<tr><td style="color:#888;"><i class="fa fa-building"></i> GST</td><td id="ccCurrentGST" style="font-size:11px;">-</td></tr>
      									<tr><td style="color:#888;"><i class="fa fa-id-card"></i> PAN</td><td id="ccCurrentPAN">-</td></tr>
      									<tr><td style="color:#888;"><i class="fa fa-address-card"></i> Aadhaar</td><td id="ccCurrentAadhaar">-</td></tr>
      								</table>
      							</div>
      						</div>
      					</div>
      					<!-- Col 2: New Customer -->
      					<div class="col-md-4">
      						<div class="panel" style="margin-bottom:0;border-color:#00a65a;">
      							<div class="panel-heading" style="background:#00a65a;color:#fff;border-color:#00a65a;padding:8px 12px;">
      								<strong><i class="fa fa-user-plus"></i> New Customer</strong>
      								<span class="pull-right label label-default" id="ccNewType" style="font-size:10px;">-</span>
      							</div>
      							<div class="panel-body" style="padding:0;">
      								<table class="table table-condensed" style="margin-bottom:0;font-size:12px;">
      									<tr><td style="border-top:none;width:70px;color:#888;"><i class="fa fa-user"></i> Name</td><td style="border-top:none;font-weight:600;" id="ccPreviewName">-</td></tr>
      									<tr><td style="color:#888;"><i class="fa fa-phone"></i> Mobile</td><td id="ccPreviewMobile">-</td></tr>
      									<tr><td style="color:#888;"><i class="fa fa-building"></i> GST</td><td id="ccPreviewGST" style="font-size:11px;">-</td></tr>
      									<tr><td style="color:#888;"><i class="fa fa-id-card"></i> PAN</td><td id="ccPreviewPAN">-</td></tr>
      									<tr><td style="color:#888;"><i class="fa fa-address-card"></i> Aadhaar</td><td id="ccPreviewAadhaar">-</td></tr>
      								</table>
      							</div>
      						</div>
      					</div>
      					<!-- Col 3: Validation Status -->
      					<div class="col-md-4">
      						<div class="panel" id="ccValidationPanel" style="margin-bottom:0;border-color:#e08e0b;">
      							<div class="panel-heading" style="background:#e08e0b;color:#fff;border-color:#e08e0b;padding:8px 12px;">
      								<strong><i class="fa fa-check-square-o"></i> Validation Status</strong>
      							</div>
      							<div class="panel-body" style="padding:0;">
      								<table class="table table-condensed" style="margin-bottom:0;font-size:12px;">
      									<tr id="val_same_cus_item"><td style="border-top:none;width:75px;color:#888;white-space:nowrap;"><i class="fa fa-exchange"></i> Customer</td><td style="border-top:none;" id="val_same_cus_status"><span class="text-muted"><i class="fa fa-circle-o"></i> -</span></td></tr>
      									<tr id="val_gst_item"><td style="color:#888;white-space:nowrap;"><i class="fa fa-building"></i> GST</td><td id="val_gst_status"><span class="text-muted"><i class="fa fa-circle-o"></i> -</span></td></tr>
      									<tr id="val_pan_item"><td style="color:#888;white-space:nowrap;"><i class="fa fa-id-card"></i> PAN</td><td id="val_pan_status"><span class="text-muted"><i class="fa fa-circle-o"></i> -</span></td></tr>
      									<tr id="val_reason_item"><td style="color:#888;white-space:nowrap;"><i class="fa fa-pencil"></i> Reason</td><td id="val_reason_status"><span class="text-muted"><i class="fa fa-circle-o"></i> -</span></td></tr>
      									<tr id="val_cash_item"><td style="color:#888;white-space:nowrap;"><i class="fa fa-money"></i> Cash</td><td id="val_cash_status"><span class="text-muted"><i class="fa fa-circle-o"></i> -</span></td></tr>
      								</table>
      								<span id="max_cash_text" style="display:none;">₹2L</span>
      							</div>
      						</div>
      					</div>
      				</div>
      			</div>
      			<div class="modal-footer" style="background:#f7f7f7;">
      				<button type="button" class="btn btn-default" data-dismiss="modal"><i class="fa fa-times"></i> Cancel</button>
      				<button type="button" class="btn btn-warning" id="btnConfirmCusChange" disabled><i class="fa fa-check"></i> Confirm Change</button>
      			</div>
      		</div>
      	</div>
      </div>

      <!-- / Customer Change Modal -->


      <div class="modal fade" id="card-detail-modal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true" data-backdrop="static" data-keyboard="false">
      	<div class="modal-dialog" style="width:80%;">
      		<div class="modal-content">
      			<div class="modal-header">
      				<button type="button" class="close card_close_btn" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
      				<h4 class="modal-title" id="myModalLabel">Card Details</h4>
      			</div>
      			<div class="modal-body">
      				<div class="box-body">
      					<div class="row">
      						<div class="col-sm-12 pull-right">
								<span class="h_amt" style="color:red;">Balance Amount:</span>
								<span class="balance_amount f_amt" style="color:red;">0</span>
      							<button type="button" class="btn bg-olive btn-sm pull-right" id="new_card"><i class="fa fa-user-plus"></i>ADD</button>
      							<p class="error "><span id="cardPayAlert"></span></p>
      						</div>
      					</div>
      					<p></p>
      					<div class="table-responsive">
      						<table id="card_details" class="table table-bordered">
      							<thead>
      								<tr>
      									<!-- <th>Card Name</th>
      									<th>Type</th>
      									<th>Device<span class="error">*</span></th>
      									<th>Card No</th>
      									<th>Amount</th>
      									<th>Approval No</th>
      									<th>Action</th> -->
										<th width="17%">Card Name</th>
										<th width="15%">Type</th> 
										<th width="10%">Device<span class="error">*</span></th> 
										<th width="13%">Date</th>
										<th width="13%">Card No</th> 
										<th width="14%">Amount</th> 
										<th width="13%">Approval No</th> 
										<th width="5%">Action</th>
      								</tr>
      							</thead>
      							<tbody>

      							</tbody>
      							<tfoot>
      								<tr>
      									<th colspan=4>Total</th>
      									<th colspan=3>
      										<span class="cc_total_amount"></span>
      										<span class="cc_total_amt" style="display: none;"></span>
      										<span class="dc_total_amt" style="display: none;"></span>
      									</th>
      								</tr>
      							</tfoot>
      						</table>
      					</div>
      				</div>
      			</div>
      			<div class="modal-footer">
      				<a href="#" id="add_newcc" class="btn btn-success">Save</a>
      				<button type="button" class="btn btn-close btn-warning card_close_btn" data-dismiss="modal">Close</button>
      			</div>
      		</div>
      	</div>
      </div>
      <!-- / Advance Adj -->
      <!-- cheque-->
      <!-- Card Details -->
      <div class="modal fade" id="cheque-detail-modal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true" data-backdrop="static" data-keyboard="false">
      	<div class="modal-dialog" style="width:70%;">
      		<div class="modal-content">
      			<div class="modal-header">
      				<button type="button" class="close chq_close_btn" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
      				<h4 class="modal-title" id="myModalLabel">Cheque Details</h4>
      			</div>
      			<div class="modal-body">
      				<div class="box-body">
      					<div class="row">
      						<div class="col-sm-12 pull-right">
								<span class="h_amt" style="color:red;">Balance Amount:</span>
								<span class="balance_amount f_amt" style="color:red;">0</span>
      							<button type="button" class="btn bg-olive btn-sm pull-right" id="new_chq"><i class="fa fa-user-plus"></i>ADD</button>
      							<p class="error "><span id="chqPayAlert"></span></p>
      						</div>
      					</div>
      					<p></p>
      					<div class="table-responsive">
      						<table id="chq_details" class="table table-bordered">
      							<thead>
      								<tr>
      									<!-- <th>Cheque Date</th>
      									<th>Bank</th>
      									<th>Cheque No</th>
      									<th>Amount</th>
      									<th>Action</th> -->

										<th width="20%">Cheque Date</th>
										<th width="20%">Bank</th>
										<th width="25%">Cheque No</th>
										<th width="30%">Amount</th>
										<th width="5%">Action</th>
      								</tr>
      							</thead>
      							<tbody>

      							</tbody>
      							<tfoot>
      								<tr>
      									<!-- <td>Total</td>
      									<td></td>
      									<td></td>
      									<td><span class="chq_total_amount"></span></td>
      									<td></td> -->
										<td colspan="3">Total</td>
										<td colspan="3"><span class="chq_total_amount"></span></td>
      								</tr>
      							</tfoot>
      						</table>
      					</div>
      				</div>
      			</div>
      			<div class="modal-footer">
      				<a href="#" id="add_newchq" class="btn btn-success">Save</a>
      				<button type="button" class="btn btn-close btn-warning chq_close_btn" data-dismiss="modal">Close</button>
      			</div>
      		</div>
      	</div>
      </div>
      <!-- cheque-->
      <!-- Net Banking-->
      <div class="modal fade" id="net_banking_modal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true" data-backdrop="static" data-keyboard="false">
      	<div class="modal-dialog" style="width:60%;">
      		<div class="modal-content">
      			<div class="modal-header">
      				<button type="button" class="close NB_close_btn" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
      				<h4 class="modal-title" id="myModalLabel">Bank Details</h4>
      			</div>
      			<div class="modal-body">
      				<div class="box-body">
      					<div class="row">
      						<div class="col-sm-12 pull-right">
								<span class="h_amt" style="color:red;">Balance Amount:</span>
								<span class="balance_amount f_amt" style="color:red;">0</span>
      							<button type="button" class="btn bg-olive btn-sm pull-right" id="new_net_bank"><i class="fa fa-user-plus"></i>ADD</button>
      							<p class="error "><span id="NetBankAlert"></span></p>
      						</div>
      					</div>
      					<p></p>
      					<div class="table-responsive">
      						<table id="net_bank_details" class="table table-bordered">
      							<thead>
      								<tr>
      									<!-- <th>Type</th>
      									<th class="upi_type">Bank</th>
      									<th>Payment Date</th>
      									<th class="device">Device</th>
      									<th>Ref No</th>
      									<th>Amount</th>
      									<th>Action</th> -->

										<th width="15%">Type</th>
										<th width="20%">Bank/Device</th> 
										<th width="20%">Net Banking Date</th> 
										<th width="20%">Ref No</th>  
										<th width="20%">Amount</th>  
										<th width="5%">Action</th>
      								</tr>
      							</thead>
      							<tbody>
      								<!--	<tr>
								<td><select name="nb_details[nb_type][]" class="nb_type"><option value=1>RTGS</option><option value=2>IMPS</option></select></td>
								<td><select class="form-control id_bank" name="nb_details[id_bank][]" ></select></td>
								<td><input type="number" step="any" class="ref_no" name="nb_details[ref_no][]"/></td>
								<td><input type="number" step="any" class="amount" name="nb_details[amount][]"/></td>
								<td><a href="#" oonclick="removeNb_row($(this).closest('tr'))" class="btn btn-danger btn-del"><i class="fa fa-trash"></i></a></td>
							</tr> -->
      							</tbody>
      							<tfoot>
      								<tr>
      									<th colspan=4>Total</th>
      									<th colspan=3>
      										<span class="nb_total_amount"></span>
      									</th>
      								</tr>
      							</tfoot>
      						</table>
      					</div>
      				</div>
      			</div>
      			<div class="modal-footer">
      				<a href="#" id="add_newnb" class="btn btn-success">Save</a>
      				<button type="button" class="btn btn-close btn-warning NB_close_btn" data-dismiss="modal">Close</button>
      			</div>
      		</div>
      	</div>
      </div>
      <!-- Net Banking-->
