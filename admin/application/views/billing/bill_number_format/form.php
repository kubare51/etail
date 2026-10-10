<script type="text/javascript">
	function insertValueQuery(bill_type) {

		if (bill_type == 1) { //Sales

			var content_field = document.getElementById("sales_content");

			var selected_field = document.getElementById("sales_field_list").value;

			console.log(selected_field);

		} else if (bill_type == 2) { //Sales & Purchase

			var content_field = document.getElementById("sales_and_purchase_content");

			var selected_field = document.getElementById("sales_and_purchase_field_list").value;

		} else if (bill_type == 3) { //Sales & Return

			var content_field = document.getElementById("sales_and_return_content");

			var selected_field = document.getElementById("sales_and_return_field_list").value;

		} else if (bill_type == 4) { //Purchase

			var content_field = document.getElementById("purchase_content");

			var selected_field = document.getElementById("purchase_field_list").value;

		} else if (bill_type == 5) { //Order Advance

			var content_field = document.getElementById("order_advance_content");

			var selected_field = document.getElementById("order_advance_field_list").value;

		} else if (bill_type == 7) { //Sales return

			var content_field = document.getElementById("sales_return_content");

			var selected_field = document.getElementById("sales_return_field_list").value;

		} else if (bill_type == 8) { //Credit Collection

			var content_field = document.getElementById("credit_collection_content");

			var selected_field = document.getElementById("credit_collection_field_list").value;

		} else if (bill_type == 9) { //Order Delivery

			var content_field = document.getElementById("order_delivery_content");

			var selected_field = document.getElementById("order_delivery_field_list").value;

		} else if (bill_type == 10) { //Chit Pre close

			var content_field = document.getElementById("chit_pre_close_content");

			var selected_field = document.getElementById("chit_pre_close_field_list").value;

		} else if (bill_type == 11) { //Repair Order Delivery

			var content_field = document.getElementById("repair_order_delivery_content");

			var selected_field = document.getElementById("repair_order_delivery_field_list").value;

		}

		//IE support

		// if (document.selection) {

		// 	content_field.focus();

		// 	sel = document.selection.createRange();

		// 	sel.text = selected_field;

		// 	document.sqlform.insert.focus();

		// }

		// //MOZILLA/NETSCAPE support
		// else if (content_field.selectionStart || content_field.selectionStart == "0") {

		// 	//alert(content_field.selectionStart);

		// 	var startPos = content_field.selectionStart;

		// 	var endPos = content_field.selectionEnd;

		// 	var chaineSql = content_field.value;

		// 	content_field.value = chaineSql.substring(0, startPos) + selected_field + chaineSql.substring(endPos, chaineSql.length);

		// } else {

		content_field.value += selected_field;

		// }

		// $('#field_list').val('');

	}

	// ---------------------------------------------------------------
	// Issue / Receipt Voucher tabs -- dual-list transfer.
	//
	// Same behaviour as insertReceiptFormat()/revertReceiptFormat() on
	// settings/general/edit, but keyed by row so all 9 voucher types share
	// one implementation, and the preview is driven by each option's
	// data-sample attribute rather than by matching its label text.
	// ---------------------------------------------------------------

	// Separators are never consumed -- a format needs several of them.
	// Must stay in step with admin_ret_billing::ir_reusable_tokens().
	var IR_REUSABLE_TOKENS = ['@@hyphen@@', '@@slash@@'];

	function irIsReusable(value) {
		return IR_REUSABLE_TOKENS.indexOf(value) !== -1;
	}

	// Available -> end of Chosen
	function irInsertToken(key) {

		var $available = $('#' + key + '_available');
		var $option = $available.find('option:selected');

		if (!$option.length) {
			alert('Select a value');
			return;
		}

		$('#' + key + '_selected').append(
			$('<option></option>')
				.val($option.val())
				.attr('data-sample', $option.attr('data-sample'))
				.text($option.text())
		);

		if (!irIsReusable($option.val())) {
			$option.remove();
		}

		irSyncFormat(key);
	}

	// Chosen -> Available
	function irRevertToken(key) {

		var $selected = $('#' + key + '_selected');
		var $option = $selected.find('option:selected');

		if (!$option.length) {
			alert('Select a value');
			return;
		}

		// Reusable tokens are still in the Available list, so putting them
		// back would create a duplicate entry.
		if (!irIsReusable($option.val())) {
			$('#' + key + '_available').append(
				$('<option></option>')
					.val($option.val())
					.attr('data-sample', $option.attr('data-sample'))
					.text($option.text())
			);
		}

		$option.remove();

		irSyncFormat(key);
	}

	// Serialise the Chosen list into the hidden input and rebuild the preview.
	function irSyncFormat(key) {

		var format = '';
		var sample = '';

		$('#' + key + '_selected option').each(function () {
			format += $(this).val();
			sample += $(this).attr('data-sample');
		});

		$('#' + key + '_format').val(format);
		$('#' + key + '_preview').text(sample);
	}

	$(function () {

		// Populate every hidden input and preview from what the server
		// rendered, so a tab saved without any edit posts its current format
		// rather than an empty string.
		$('#tab_receipt, #tab_issue').find('select[id$="_selected"]').each(function () {
			irSyncFormat(this.id.replace(/_selected$/, ''));
		});

		// voucher_save redirects back with #tab_receipt / #tab_issue so the
		// user lands on the tab they were editing.
		var hash = window.location.hash;

		if (hash && $('#bill_no_format_tabs a[href="' + hash + '"]').length) {
			$('#bill_no_format_tabs a[href="' + hash + '"]').tab('show');
		}

		$('#bill_no_format_tabs a').on('shown.bs.tab', function (e) {
			window.location.hash = $(e.target).attr('href');
		});
	});
</script>

<!-- Content Wrapper. Contains page content -->

<div class="content-wrapper">

	<!-- Content Header (Page header) -->

	<section class="content-header">

		<h1>

			Bill Number Format

			<small>Bill Number Format Settings</small>

		</h1>

		<ol class="breadcrumb">

			<li><a href="#"><i class="fa fa-dashboard"></i> Settings</a></li>

			<li>

				<a href="#"> Bill Number Format</a>

			</li>

			<li class="active"> Bill Number Format Settings</li>

		</ol>

	</section>

	<!-- Main content -->

	<section class="content">

		<!-- Default box -->

		<div class="box">

			<div class="box-header with-border">

				<h3 class="box-title">Bill Number format</h3>

			</div>

			<div class="box-body">

				<!-- put your content here -->

				<div class="col-md-12">

					<?php
					if ($this->session->flashdata('chit_alert')) {
						$ir_message = $this->session->flashdata('chit_alert');
					?>
						<div class="alert alert-<?php echo $ir_message['class']; ?> alert-dismissable">
							<button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
							<h4><i class="icon fa fa-check"></i> <?php echo $ir_message['title']; ?>!</h4>
							<?php echo $ir_message['message']; ?>
						</div>
					<?php } ?>

					<ul class="nav nav-tabs" id="bill_no_format_tabs">
						<li class="active"><a href="#tab_bill" data-toggle="tab">Bill No</a></li>
						<li><a href="#tab_receipt" data-toggle="tab">Receipt Voucher</a></li>
						<li><a href="#tab_issue" data-toggle="tab">Issue Voucher</a></li>
					</ul>

					<div class="tab-content" style="padding-top:20px;">

					<div role="tabpanel" class="tab-pane active" id="tab_bill">


					<?php

					$attributes 		=	array('role' => 'form');

					echo form_open_multipart((sizeof($exists) > 0  ? 'admin_ret_billing/bill_number_format/update' : 'admin_ret_billing/bill_number_format/save')); ?>

					<div class="row">

						<?php

						if (isset($db_error_msg) && $db_error_msg != '') {

							echo '<div class="alert alert-danger alert-dismissable">

														<button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>

														<h4><i class="icon fa fa-warning"></i> Warning!</h4>    <strong>' . $db_error_msg . '</strong>

												</div>';
						}

						?>

					</div>

					<div class="row">

						<div class="col-sm-10 col-sm-offset-1">

							<div id="error-msg"></div>

						</div>

					</div>

					<form id="format">

						<!-- Sales -->

						<div class="row">

							<div class="form-group">

								<label class="control-label col-sm-2 ">Sales Bill </label>

								<div class="col-sm-4 ">

									<textarea class="form-control" aria-describedby="basic-addon1" name="content[sales][text]" id="sales_content" cols="35" rows="5" tabindex="4" required="required"><?php echo set_value('content[sales][text]', $exists[0]['bill_no_format']); ?></textarea>

									<input type="hidden" value="1" name=content[sales][bill_type]>

									<span class="help-block">Enter or choose the Sales Bill content.</span>

								</div>

								<div class="col-sm-1">

									<div class="btn-group" data-toggle="buttons">

										<label onclick="insertValueQuery(1)" style="margin-top: 85px;" class="btn btn-primary">

											<a style="color:#FFFFFF; text-decoration:none" href="" id="move_button" name="move_button" title="click">

												<< </a>

										</label>

									</div>

								</div>

								<div class="col-sm-3">

									<select style="height: 150px;" class="form-control" size="11" id="sales_field_list" name="field_list">

										<?php echo $sale_format; ?>

										<?php foreach ($sale_format as $bill_no) { ?>

											<option value="<?php echo $bill_no['value'] ?>"><?php echo $bill_no['text'] ?></option>

										<?php } ?>

									</select>

									<span class="help-block">Select the value.</span>

								</div>

							</div>

						</div>

						<!--Sales &  Purchase -->

						<div class="row">

							<div class="form-group">

								<label class="control-label col-sm-2 ">Sales & Purchase Bill </label>

								<div class="col-sm-4 ">

									<textarea class="form-control" name="content[sales_and_purchase][text]" id="sales_and_purchase_content" cols="35" rows="5" tabindex="4" required="required"><?php echo set_value('content[sales_and_purchase][text]', $exists[1]['bill_no_format']); ?></textarea>

									<input type="hidden" value="2" name=content[sales_and_purchase][bill_type]>

									<span class="help-block">Enter or choose the Sales & Purchase Bill content.</span>

								</div>

								<div class="col-sm-1">

									<div class="btn-group" data-toggle="buttons">

										<label onclick="insertValueQuery(2)" style="margin-top: 85px;" class="btn btn-primary">

											<a style="color:#FFFFFF; text-decoration:none" href="" id="move_button" name="move_button" title="click">

												<< </a>

										</label>

									</div>

								</div>

								<div class="col-sm-3">

									<select style="height: 150px;" class="form-control" size="11" id="sales_and_purchase_field_list" name="field_list">

										<?php foreach ($sale_and_purchase_format as $bill_no) { ?>

											<option value="<?php echo $bill_no['value'] ?>"><?php echo $bill_no['text'] ?></option>

										<?php } ?>

									</select>

									<span class="help-block">Select the value.</span>

								</div>

							</div>

						</div>

						<!-- Sales  & Return -->

						<div class="row">

							<div class="form-group">

								<label class="control-label col-sm-2 ">Sales & Return Bill </label>

								<div class="col-sm-4 ">

									<textarea class="form-control" name="content[sales_and_return][text]" id="sales_and_return_content" cols="35" rows="5" tabindex="4" required="required"><?php echo set_value('content[sales_and_return][text]', $exists[2]['bill_no_format']); ?></textarea>

									<input type="hidden" value="3" name=content[sales_and_return][bill_type]>

									<span class="help-block">Enter or choose the Sales & Return Bill content.</span>

								</div>

								<div class="col-sm-1">

									<div class="btn-group" data-toggle="buttons">

										<label onclick="insertValueQuery(3)" style="margin-top: 85px;" class="btn btn-primary">

											<a style="color:#FFFFFF; text-decoration:none" href="" id="move_button" name="move_button" title="click">

												<< </a>

										</label>

									</div>

								</div>

								<div class="col-sm-3">

									<select style="height: 150px;" class="form-control" size="11" id="sales_and_return_field_list" name="field_list">

										<?php foreach ($sale_and_return_format as $bill_no) { ?>

											<option value="<?php echo $bill_no['value'] ?>"><?php echo $bill_no['text'] ?></option>

										<?php } ?>

									</select>

									<span class="help-block">Select the value.</span>

								</div>

							</div>

						</div>

						<!-- Purchase Bill -->

						<div class="row">

							<div class="form-group">

								<label class="control-label col-sm-2 ">Purchase Bill </label>

								<div class="col-sm-4 ">

									<textarea class="form-control" name="content[purchase][text]" id="purchase_content" cols="35" rows="5" tabindex="4" required="required"><?php echo set_value('content[purchase][text]', $exists[3]['bill_no_format']); ?></textarea>

									<input type="hidden" value="4" name=content[purchase][bill_type]>

									<span class="help-block">Enter or choose the Purchase Bill content.</span>

								</div>

								<div class="col-sm-1">

									<div class="btn-group" data-toggle="buttons">

										<label onclick="insertValueQuery(4)" style="margin-top: 85px;" class="btn btn-primary">

											<a style="color:#FFFFFF; text-decoration:none" href="" id="move_button" name="move_button" title="click">

												<< </a>

										</label>

									</div>

								</div>

								<div class="col-sm-3">

									<select style="height: 150px;" class="form-control" size="11" id="purchase_field_list" name="field_list">

										<?php foreach ($purchase_format as $bill_no) { ?>

											<option value="<?php echo $bill_no['value'] ?>"><?php echo $bill_no['text'] ?></option>

										<?php } ?>

									</select>

									<span class="help-block">Select the value.</span>

								</div>

							</div>

						</div>

						<!-- Order Advance -->

						<div class="row">

							<div class="form-group">

								<label class="control-label col-sm-2 ">Order Advance Bill </label>

								<div class="col-sm-4 ">

									<textarea class="form-control" name="content[order_advance][text]" id="order_advance_content" cols="35" rows="5" tabindex="4" required="required"><?php echo set_value('content[order_advance][text]', $exists[4]['bill_no_format']); ?></textarea>

									<input type="hidden" value="5" name=content[order_advance][bill_type]>

									<span class="help-block">Enter or choose the Order Advance Bill content.</span>

								</div>

								<div class="col-sm-1">

									<div class="btn-group" data-toggle="buttons">

										<label onclick="insertValueQuery(5)" style="margin-top: 85px;" class="btn btn-primary">

											<a style="color:#FFFFFF; text-decoration:none" href="" id="move_button" name="move_button" title="click">

												<< </a>

										</label>

									</div>

								</div>

								<div class="col-sm-3">

									<select style="height: 150px;" class="form-control" size="11" id="order_advance_field_list" name="field_list">

										<?php foreach ($ord_adv_format as $bill_no) { ?>

											<option value="<?php echo $bill_no['value'] ?>"><?php echo $bill_no['text'] ?></option>

										<?php } ?>

									</select>

									<span class="help-block">Select the value.</span>

								</div>

							</div>

						</div>

						<!-- Sales Return -->

						<div class="row">

							<div class="form-group">

								<label class="control-label col-sm-2 ">Sales Return Bill </label>

								<div class="col-sm-4 ">

									<textarea class="form-control" name="content[sales_return_content][text]" id="sales_return_content" cols="35" rows="5" tabindex="4" required="required"><?php echo set_value('content[sales_return_content][text]', $exists[5]['bill_no_format']); ?></textarea>

									<input type="hidden" value="7" name=content[sales_return_content][bill_type]>

									<span class="help-block">Enter or choose the Sales Return Bill content.</span>

								</div>

								<div class="col-sm-1">

									<div class="btn-group" data-toggle="buttons">

										<label onclick="insertValueQuery(7)" style="margin-top: 85px;" class="btn btn-primary">

											<a style="color:#FFFFFF; text-decoration:none" href="" id="move_button" name="move_button" title="click">

												<< </a>

										</label>

									</div>

								</div>

								<div class="col-sm-3">

									<select style="height: 150px;" class="form-control" size="11" id="sales_return_field_list" name="field_list">

										<?php foreach ($sale_return_format as $bill_no) { ?>

											<option value="<?php echo $bill_no['value'] ?>"><?php echo $bill_no['text'] ?></option>

										<?php } ?>

									</select>

									<span class="help-block">Select the value.</span>

								</div>

							</div>

						</div>

						<!--credit collection-->

						<div class="row">

							<div class="form-group">

								<label class="control-label col-sm-2 ">Credit Collection </label>

								<div class="col-sm-4 ">

									<textarea class="form-control" name="content[credit_collection_content][text]" id="credit_collection_content" cols="35" rows="5" tabindex="4" required="required"><?php echo set_value('content[credit_collection_content][text]', $exists[6]['bill_no_format']); ?></textarea>

									<input type="hidden" value="8" name=content[credit_collection_content][bill_type]>

									<span class="help-block">Enter or choose the Sales Return Bill content.</span>

								</div>

								<div class="col-sm-1">

									<div class="btn-group" data-toggle="buttons">

										<label onclick="insertValueQuery(8)" style="margin-top: 85px;" class="btn btn-primary">

											<a style="color:#FFFFFF; text-decoration:none" href="" id="move_button" name="move_button" title="click">

												<< </a>

										</label>

									</div>

								</div>

								<div class="col-sm-3">

									<select style="height: 150px;" class="form-control" size="11" id="credit_collection_field_list" name="field_list">

										<?php foreach ($credit_collection_format as $bill_no) { ?>

											<option value="<?php echo $bill_no['value'] ?>"><?php echo $bill_no['text'] ?></option>

										<?php } ?>

									</select>

									<span class="help-block">Select the value.</span>

								</div>

							</div>

						</div>
						<!-- Order Delivery -->
						<div class="row">

							<div class="form-group">

								<label class="control-label col-sm-2 ">Order Delivery </label>

								<div class="col-sm-4 ">

									<textarea class="form-control" name="content[order_delivery_content][text]" id="order_delivery_content" cols="35" rows="5" tabindex="4" required="required"><?php echo $exists[7]['bill_type'] == 9 ? set_value('content[order_delivery_content][text]', $exists[7]['bill_no_format']) : ''; ?></textarea>

									<input type="hidden" value="9" name=content[order_delivery_content][bill_type]>

									<span class="help-block">Enter or choose the Order Delivery content.</span>

								</div>

								<div class="col-sm-1">

									<div class="btn-group" data-toggle="buttons">


										<label onclick="insertValueQuery(9)" style="margin-top: 85px;" class="btn btn-primary">

											<a style="color:#FFFFFF; text-decoration:none" href="" id="move_button" name="move_button" title="click">
												<< </a>

										</label>

									</div>

								</div>

								<div class="col-sm-3">


									<select style="height: 150px;" class="form-control" size="11" id="order_delivery_field_list" name="field_list">

										<?php foreach ($order_delivery as $bill_no) { ?>

											<option value="<?php echo $bill_no['value'] ?>"><?php echo $bill_no['text'] ?></option>

										<?php } ?>

									</select>

									<span class="help-block">Select the value.</span>

								</div>

							</div>

						</div>

						<!-- Chit Pre Close -->

						<div class="row">

							<div class="form-group">

								<label class="control-label col-sm-2 ">Chit Pre Close Bill </label>

								<div class="col-sm-4 ">

									<textarea class="form-control" name="content[chit_pre_close_content][text]" id="chit_pre_close_content" cols="35" rows="5" tabindex="4" required="required"><?php echo set_value('content[chit_pre_close_content][text]', $exists[8]['bill_no_format']); ?></textarea>

									<input type="hidden" value="10" name=content[chit_pre_close_content][bill_type]>

									<span class="help-block">Enter or choose the Chit Pre Close Bill content.</span>

								</div>

								<div class="col-sm-1">

									<div class="btn-group" data-toggle="buttons">

										<label onclick="insertValueQuery(10)" style="margin-top: 85px;" class="btn btn-primary">

											<a style="color:#FFFFFF; text-decoration:none" href="" id="move_button" name="move_button" title="click">

												<< </a>

										</label>

									</div>

								</div>

								<div class="col-sm-3">

									<select style="height: 150px;" class="form-control" size="11" id="chit_pre_close_field_list" name="field_list">

										<?php foreach ($chit_format as $bill_no) { ?>

											<option value="<?php echo $bill_no['value'] ?>"><?php echo $bill_no['text'] ?></option>

										<?php } ?>

									</select>

									<span class="help-block">Select the value.</span>

								</div>

							</div>

						</div>

						<!-- Repait Order Delivery -->

						<div class="row">

							<div class="form-group">

								<label class="control-label col-sm-2 ">Repair Order Delivery </label>

								<div class="col-sm-4 ">


									<textarea class="form-control" name="content[repair_order_delivery_content][text]" id="repair_order_delivery_content" cols="35" rows="5" tabindex="4" required="required"><?php echo $exists[9]['bill_type'] == 11 ? set_value('content[repair_order_delivery_content][text]', $exists[9]['bill_no_format']) : ''; ?></textarea>

									<input type="hidden" value="11" name=content[repair_order_delivery_content][bill_type]>

									<span class="help-block">Enter or choose the Repair Order Delivery Bill content.</span>

								</div>

								<div class="col-sm-1">

									<div class="btn-group" data-toggle="buttons">

										<label onclick="insertValueQuery(11)" style="margin-top: 85px;" class="btn btn-primary">

											<a style="color:#FFFFFF; text-decoration:none" href="" id="move_button" name="move_button" title="click">

												<< </a>

										</label>

									</div>

								</div>

								<div class="col-sm-3">

									<select style="height: 150px;" class="form-control" size="11" id="repair_order_delivery_field_list" name="field_list">

										<?php foreach ($repair_order_delivery as $bill_no) { ?>

											<option value="<?php echo $bill_no['value'] ?>"><?php echo $bill_no['text'] ?></option>

										<?php } ?>

									</select>

									<span class="help-block">Select the value.</span>

								</div>

							</div>

						</div>

						<div class="row">

							<div class="box box-default"><br />

								<div class="col-xs-offset-5">
									
                                     <?php if($access['add']==1){?>
									<button type="submit" class="btn btn-primary">Save</button>
										<?php }?>
									<button type="button" class="btn btn-default btn-cancel">Cancel</button>

								</div> <br />

							</div>

						</div>

						<?php echo form_close(); ?>

					</form>

					<!-- /form -->

					</div><!-- #tab_bill End -->

					<?php
					/**
					 * Issue / Receipt Voucher panes.
					 *
					 * Both share one markup loop -- the only difference is the
					 * row config the controller passes in. Values bind by
					 * (voucher_mode, voucher_type), never by array position.
					 */
					$ir_panes = array(
						'tab_receipt' => array('title' => 'Receipt Voucher Number Format', 'rows' => $receipt_rows),
						'tab_issue'   => array('title' => 'Issue Voucher Number Format',   'rows' => $issue_rows),
					);

					foreach ($ir_panes as $ir_pane_id => $ir_pane) { ?>

					<div role="tabpanel" class="tab-pane" id="<?php echo $ir_pane_id; ?>">

						<?php echo form_open('admin_ret_billing/bill_number_format/voucher_save'); ?>

						<input type="hidden" name="active_tab" value="<?php echo $ir_pane_id; ?>">

						<div class="row">
							<div class="col-sm-12">
								<p class="help-block">
									<?php echo $ir_pane['title']; ?> &mdash; the voucher number stored in the database is unchanged;
									this only controls how it is displayed on lists, prints and reports.
								</p>
							</div>
						</div>

						<?php
						/**
						 * One dual-list transfer per voucher type, matching
						 * "Receipt Number Format Customization" on
						 * settings/general/edit: chosen fields on the LEFT in
						 * order, available values on the RIGHT, >> to remove and
						 * << to add. Hyphen(-) is never consumed from the
						 * Available list, so it can be used as a separator as
						 * many times as the format needs.
						 *
						 * The controller has already split each row's saved
						 * format into 'chosen' and 'available'.
						 */
						foreach ($ir_pane['rows'] as $ir_row) {

							$ir_key   = $ir_row['key'];
							$ir_mode  = $ir_row['voucher_mode'];
							$ir_type  = $ir_row['voucher_type'];
						?>

						<div class="row">

							<div class="form-group">

								<label class="control-label col-sm-2 "><?php echo $ir_row['label']; ?></label>

								<div class="col-sm-4 ">

									<select style="height: 150px;" class="form-control" size="11" id="<?php echo $ir_key; ?>_selected">

										<?php foreach ($ir_row['chosen'] as $ir_token) { ?>

											<option value="<?php echo $ir_token['value']; ?>" data-sample="<?php echo htmlspecialchars($ir_token['sample'], ENT_QUOTES); ?>"><?php echo $ir_token['text']; ?></option>

										<?php } ?>

									</select>

									<input type="hidden" name="ir_content[<?php echo $ir_key; ?>][text]" id="<?php echo $ir_key; ?>_format">

									<input type="hidden" value="<?php echo $ir_mode; ?>" name="ir_content[<?php echo $ir_key; ?>][voucher_mode]">

									<input type="hidden" value="<?php echo $ir_type; ?>" name="ir_content[<?php echo $ir_key; ?>][voucher_type]">

									<span class="help-block">choose/Remove the <?php echo $ir_row['label']; ?> Fields.</span>

									<span style="font-weight:bold;">Format </span><span id="<?php echo $ir_key; ?>_preview" style="font-weight:bold;color:red;"></span>

								</div>

								<div class="col-sm-1">

									<div class="btn-group">

										<button type="button" onclick="irRevertToken('<?php echo $ir_key; ?>')" style="margin-top: 30px;" class="btn btn-primary" title="Remove the selected field">
											&gt;&gt;
										</button>

										<button type="button" onclick="irInsertToken('<?php echo $ir_key; ?>')" style="margin-top: 30px;" class="btn btn-primary" title="Add the selected value">
											&lt;&lt;
										</button>

									</div>

								</div>

								<div class="col-sm-3">

									<select style="height: 150px;" class="form-control" size="11" id="<?php echo $ir_key; ?>_available">

										<?php foreach ($ir_row['available'] as $ir_token) { ?>

											<option value="<?php echo $ir_token['value']; ?>" data-sample="<?php echo htmlspecialchars($ir_token['sample'], ENT_QUOTES); ?>"><?php echo $ir_token['text']; ?></option>

										<?php } ?>

									</select>

									<span class="help-block">Select the value.</span>

								</div>

							</div>

						</div>

						<?php } // each row ?>

						<div class="row">

							<div class="box box-default"><br />

								<div class="col-xs-offset-5">

									<?php if ($access['add'] == 1) { ?>
										<button type="submit" class="btn btn-primary">Save</button>
									<?php } ?>

									<button type="button" class="btn btn-default btn-cancel">Cancel</button>

								</div> <br />

							</div>

						</div>

						<?php echo form_close(); ?>

					</div><!-- <?php echo $ir_pane_id; ?> End -->

					<?php } // each pane ?>

					</div><!-- .tab-content End -->

				</div>

			</div><!-- .box-body End -->

		</div>

	</section><!-- /.content -->

</div><!-- /.content-wrapper -->