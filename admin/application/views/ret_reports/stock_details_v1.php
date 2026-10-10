 <style>
	#stock_list td { text-align: right; }
	#stock_list td:first-child { text-align: left; }
	#stock_list th { text-align: right; }
	#stock_list th:first-child { text-align: left; }
 </style>
 <!-- Content Wrapper. Contains page content -->



 <div class="content-wrapper">

 	<!-- Content Header (Page header) -->

 	<section class="content-header">

 		<h1>

 			Stock Report

 		</h1>

 		<ol class="breadcrumb">

 			<li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>

 			<li><a href="#">Reports</a></li>

 			<li class="active">Stock report</li>

 		</ol>

 	</section>



 	<!-- Main content -->

 	<section class="content">

 		<div class="row">

 			<div class="col-xs-12">



 				<div class="box box-primary">

 					<div class="box-body">

 						<div class="box box-info stock_details">

 							<div class="box-header with-border">

 								<div class="row">

 									<div class="col-md-12">

 										<?php if ($this->session->userdata('branch_settings') == 1 && $this->session->userdata('id_branch') == 0) { ?>

 											<div class="col-md-2">

 												<div class="form-group tagged">

 													<label>Select Branch</label>

 													<select id="branch_select" class="form-control branch_filter" ></select>

 												</div>

 											</div>

 										<?php } else { ?>

 											<input type="hidden" id="branch_filter" value="<?php echo $this->session->userdata('id_branch') ?>">

 											<input type="hidden" id="branch_name" value="<?php echo $this->session->userdata('branch_name') ?>">

 										<?php } ?>



 										<div class="col-md-2">

 											<div class="form-group">

 												<label>Date</label>

 												<?php

													$fromdt = date("d/m/Y");

													$todt = date("d/m/Y");

													?>

 												<input type="text" class="form-control pull-right dateRangePicker" id="dt_range" placeholder="From Date -  To Date" value="<?php echo $fromdt . ' - ' . $todt ?>" readonly="">

 											</div>

 										</div>



 										<div class="col-md-2">

 											<label>Select Metal</label>

 											<select id="metal" class="form-control" style="width:100%;" multiple></select>

 										</div>

										<div class="col-md-2"> 
											
											<label>Select Purity</label>
											
											<select id="select_purity" class="form-control" style="width:100%;"></select>

										</div>

 										<div class="col-md-2">

 											<label>Select Category</label>

 											<select id="category" class="form-control" style="width:100%;"></select>

 										</div>

 										<div class="col-md-2">

 											<label>Select Grouping</label>

 											<select id="prod_group_id" class="form-control" style="width:100%;"></select>

 										</div>

 									</div>
 								</div>


 								<div class="row">

									<div class="col-md-2">

										<label>Select Product</label>

										<select id="prod_select" class="form-control" style="width:100%;"></select>

									</div>

									<div class="col-md-2">

										<label>Select Design</label>

										<select id="des_select" class="form-control" style="width:100%;"></select>

									</div>

 										<div class="col-md-2">

 											<label>Select Sub Design</label>

 											<select id="sub_des_select" class="form-control" style="width:100%;"></select>

 										</div>

 										<div class="col-md-2">

 											<label>List By</label>

 											<select id="list_by" class="form-control" style="width:100%;">

 												<option value="0">ALL</option>

 												<option value="1">Tag</option>

 												<option value="2">Non tag</option>

 											</select>

 										</div>

 										<div class="col-md-2">

 											<label>Group By</label>

 											<select id="select_group_by" class="form-control" style="width:100%;">

 												<option value="1">Product</option>

 												<option value="2">Category</option>

 												<option value="3">Section and WeightRange</option>

 												<option value="4">Product,Design and Sub Design</option>

 											</select>

 										</div>

 										<div class="col-md-2" style="min-width: 195px;">

 											<label>&nbsp;</label>

 											<div class="form-group" style="display: flex; align-items: center; gap: 4px; white-space: nowrap;">

 												<button type="button" id="stock_detail_search_v1" class="btn btn-info" style="padding: 6px 12px; font-size: 12px; height: 34px; font-weight: bold;">Search</button>
 												<button type="button" id="stock_detail_thermal_print" class="btn btn-warning" style="padding: 6px 10px; font-size: 12px; height: 34px; font-weight: bold;"><i class="fa fa-print"></i> Thermal Print</button>

 											</div>

 										</div>

 									</div>
 								</div>



 							</div>



 							<div class="box box-info stock_details">

 								<div class="box-header with-border">

 									<h3 class="box-title">Stock Summary</h3>

 									<div class="box-tools pull-right">

 										<button class="btn btn-box-tool" data-widget="collapse" data-toggle="tooltip" title="Collapse"><i class="fa fa-plus"></i></button>

 									</div>

 								</div>

 								<div class="box-body collapse">

 									<div class="row">

 										<div class="box-body col-md-offset-2 col-md-8">



 											<table id="stock_list_summary" class="table table-bordered table-striped text-center">

 												<thead>

 													<tr>

 														<th>STOCK</th>

 														<th>Pcs</th>

 														<th>Weight</th>

 													</tr>



 												</thead>

 												<tbody>

 													<tr>

 														<td>Opening Stock</td>

 														<td class="opqty"></td>

 														<td class="opwt"></td>

 													</tr>

 													<tr>

 														<td>Inward Stock</td>

 														<td class="inqty"></td>

 														<td class="inwt"></td>

 													</tr>

 													<tr>

 														<td>Lot Inward Stock</td>

 														<td class="lotinqty"></td>

 														<td class="lotinwt"></td>

 													</tr>

 													<tr>

 														<td> Branch Transfer Inward Stock</td>

 														<td class="brinqty"></td>

 														<td class="brinwt"></td>

 													</tr>

 													<tr>

 														<td>Outward Stock</td>

 														<td class="outqty"></td>

 														<td class="outwt"></td>

 													</tr>

 													<tr>

 														<td>Issued Stock</td>

 														<td class="issqty"></td>

 														<td class="isswt"></td>

 													</tr>

 													<tr>

 														<td> Showroom Sale</td>

 														<td class="soldqty"></td>

 														<td class="soldwt"></td>

 													</tr>

 													<tr>

 														<td> Other O/W</td>

 														<td class="otherqty"></td>

 														<td class="otherwt"></td>

 													</tr>



 													<tr>

 														<td>Closing Stock </td>

 														<td class="clsqty"></td>

 														<td class="clswt"></td>

 													</tr>



 												</tbody>





 											</table>



 										</div>

 									</div>

 								</div>

 							</div>



 							<div class="box-body">

 								<div class="row">

 									<div class="box-body">

 										<div id="stock_list_btn_bar" style="display:none; margin-bottom:8px;">
											<button type="button" id="btn_stock_print" class="btn btn-default btn-sm">Print</button>
											<button type="button" id="btn_stock_colvis" class="btn btn-default btn-sm">Column visibility</button>
											<button type="button" id="btn_stock_excel" class="btn btn-default btn-sm">Excel</button>
										</div>
										<div class="table-responsive">

 											<table id="stock_list" class="table table-bordered table-striped text-center" style="width:100%">

 												<thead>

 													<!--<tr>

							  	<th colspan="1"></th>

							  	<th colspan="3">Opening</th>

								<th colspan="3">Inward</th>

								<th colspan="3">Outward</th>

								<th colspan="3">Closing</th>

		                      </tr>-->

 													<tr>

 														<th>Category/Product</th>

 														<th>Op Stock</th>

 														<th>Op Gross Wt</th>

 														<th>Op Net Wt</th>

 														<th>Op dia Wt</th>

 														<th>I/W Pcs</th>

 														<th>I/W Gross Wt</th>

 														<th>I/W Net Wt</th>

 														<th>I/W Dia Wt</th>

 														<th>O/W (Sale/Other) Pcs</th>
 														<th>O/W (Sale/Other) GW</th>
 														<th>O/W (Sale/Other) NW</th>
 														<th>O/W (Sale/Other) DW</th>

 														<th>Closing Stock</th>

 														<th>Closing Gross Wt</th>

 														<th>Closing Net Wt</th>

 														<th>Closing Dia Wt</th>

														<th>Closing Pur Wt</th>

 														<th>Intransit</th>

 														<th>Closing Stock</th>



 													</tr>

 												</thead>

 												<tbody></tbody>

 											</table>

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
// NOTE: jQuery and `base_url` are both loaded by layout/footer.php, which renders
// AFTER this view. Binding on window 'load' (not $(document).ready) is therefore
// mandatory — at view-parse time `$` does not exist yet and this whole block would
// die with "$ is not defined", leaving the button inert.
window.addEventListener('load', function() {
    $(document).on('click', '#stock_detail_thermal_print', function(e) {
        e.preventDefault();
        var branch_name = ($('#branch_name').val() != '' && $('#branch_name').val() != undefined ? $('#branch_name').val() : $("#branch_select option:selected").text());

        var postData = {
            'dt_range': $("#dt_range").val(),
            'id_branch': ($('#branch_filter').val() != '' && $('#branch_filter').val() != undefined ? $('#branch_filter').val() : $("#branch_select").val()) || 0,
            'id_product': $('#prod_select').val(),
            'id_metal': $('#metal').val(),
            'id_category': $('#category').val(),
            'group_by': $('#select_group_by').val(),
            'id_design': $('#des_select').val(),
            'id_sub_design': $('#sub_des_select').val(),
            'list_by': $('#list_by').val(),
            'prod_group_id': $('#prod_group_id').val(),
            // v1 only — v2 has no purity filter. Matches the 'ajax' payload at
            // ret_reports.js:11670 so the slip honours the same filters as the grid.
            'purity': ($('#select_purity').val() != '' && $('#select_purity').val() != undefined && $('#select_purity').val() != null) ? $('#select_purity').val() : 0,
            'branch_name': branch_name
        };

        var printUrl = base_url + "index.php/admin_ret_reports/stock_details_v1/thermal_print";

        var form = $('<form>', {
            'action': printUrl,
            'method': 'POST',
            'target': '_blank'
        });

        $.each(postData, function(key, value) {
            if ($.isArray(value)) {
                $.each(value, function(i, val) {
                    form.append($('<input>', {
                        'type': 'hidden',
                        'name': key + '[]',
                        'value': val
                    }));
                });
            } else {
                form.append($('<input>', {
                    'type': 'hidden',
                    'name': key,
                    'value': value
                }));
            }
        });

        $('body').append(form);
        form.submit();
        form.remove();
    });
});
</script>
