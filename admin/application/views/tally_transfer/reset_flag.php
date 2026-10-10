<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
	<!-- Content Header (Page header) -->
	<section class="content-header">
		<h1>
			Tally Transfer
			<small>Reset Flag</small>
		</h1>
		<ol class="breadcrumb">
			<li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
			<li><a href="#">Tally Transfer</a></li>
			<li class="active">Reset Flag</li>
		</ol>
	</section>

	<!-- Main content -->
	<section class="content">
		<div class="row">
			<div class="col-xs-12">

				<div class="box box-primary">
					<div class="box-header with-border">
						<h3 class="box-title">Reset Tally Transfer Flag</h3>
					</div>
					<div class="box-body">

						<div class="alert alert-info">
							This tool only resets the <b>"transferred to Tally"</b> flag so the selected records are
							picked up again on the next Tally import. It does not modify any other bill data.
						</div>

						<div id="tt_result_alert"></div>

						<div class="row">
							<div class="col-md-3">
								<div class="form-group">
									<label>Select API</label>
									<select id="api_select" class="form-control">
										<option value="">-- Select API --</option>
										<?php foreach ($api_list as $api) { ?>
											<option value="<?php echo $api['key']; ?>"><?php echo $api['label']; ?></option>
										<?php } ?>
									</select>
								</div>
							</div>

							<div class="col-md-3">
								<div class="form-group">
									<label>Filter By</label>
									<select id="filter_mode" class="form-control">
										<option value="daterange">Date Range</option>
										<option value="billids">Bill ID / Reference No</option>
									</select>
								</div>
							</div>

							<?php if ($this->session->userdata('branch_settings') == 1 && $this->session->userdata('id_branch') == 0) { ?>
								<div class="col-md-3" id="tt_branch_wrap">
									<div class="form-group tagged">
										<label>Select Branch</label>
										<select id="branch_select" class="form-control branch_filter"></select>
									</div>
								</div>
							<?php } else { ?>
								<div class="col-md-3" id="tt_branch_wrap">
									<div class="form-group tagged">
										<label>Select Branch</label>
										<select class="form-control" disabled>
											<option selected><?php echo $this->session->userdata('branch_name'); ?></option>
										</select>
									</div>
								</div>
								<input type="hidden" id="branch_filter" value="<?php echo $this->session->userdata('id_branch') ?>">
								<input type="hidden" id="branch_name" value="<?php echo $this->session->userdata('branch_name') ?>">
							<?php } ?>

							<div class="col-md-3" id="tt_daterange_wrap">
								<div class="form-group">
									<label>&nbsp;</label>
									<div class="input-group">
										<button class="btn btn-default btn-block" id="rpt_date_picker" type="button">
											<i class="fa fa-calendar"></i> Date range picker
											<i class="fa fa-caret-down"></i>
										</button>
										<span style="display:none;" id="rpt_from_date"></span>
										<span style="display:none;" id="rpt_to_date"></span>
									</div>
								</div>
							</div>

							<div class="col-md-3">
								<div class="form-group">
									<label>Status</label>
									<select id="tt_status_filter" class="form-control">
										<option value="">All (Pending + Transferred)</option>
										<option value="pending">Pending only (not yet transferred)</option>
										<option value="transferred">Transferred only (already sent)</option>
									</select>
								</div>
							</div>
						</div>

						<div class="row" id="tt_billids_wrap" style="display:none;">
							<div class="col-md-9">
								<div class="form-group">
									<label>Bill ID(s) / Reference No(s)</label>
									<textarea id="bill_ids" class="form-control" rows="3"
										placeholder="Enter one or more bill IDs / reference numbers, separated by commas or new lines"></textarea>
								</div>
							</div>
						</div>

						<div class="row">
							<div class="col-md-12">
								<button type="button" id="tt_preview_btn" class="btn btn-info">
									<i class="fa fa-search"></i> Preview
								</button>
							</div>
						</div>

						<hr>

						<div class="row">
							<div class="col-md-12">
								<span id="tt_total_count" class="badge bg-green"></span>
								<span id="tt_pending_count" class="badge" style="background-color:#f39c12;"></span>
								<span id="tt_transferred_count" class="badge bg-green" style="background-color:#00a65a;"></span>
								<span id="tt_selected_count" class="badge bg-blue"></span>

								<button type="button" id="tt_update_btn" class="btn btn-danger pull-right" disabled>
									<i class="fa fa-refresh"></i> Update Flag
								</button>

								<div id="tt_table_wrap" style="margin-top:10px; clear:both;">
									<div class="table-responsive">
										<table id="tally_reset_list" class="table table-bordered table-striped text-center">
											<thead>
												<tr>
													<th><input type="checkbox" id="tt_select_all"></th>
													<th>Source</th>
													<th>Ref No</th>
													<th>Date</th>
													<th>Party</th>
													<th>Amount</th>
													<th>Current Status</th>
													<th>Remark</th>
												</tr>
											</thead>
											<tbody></tbody>
										</table>
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

<!-- Confirm Modal -->
<div class="modal fade" id="tt_confirm_modal" tabindex="-1" role="dialog">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal">&times;</button>
				<h4 class="modal-title">Confirm Flag Reset</h4>
			</div>
			<div class="modal-body">
				<p>This will mark <b><span id="tt_modal_count"></span></b> record(s) to be re-transferred to Tally on
					the next import run. Continue?</p>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
				<button type="button" id="tt_confirm_update_btn" class="btn btn-danger">Yes, Update</button>
			</div>
		</div>
	</div>
</div>
