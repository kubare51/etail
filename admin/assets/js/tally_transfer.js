var tt_preview_data = [];
var tt_selected_ids = {}; // "source|row_id" -> true, persists selection across DataTables pages

// A single api can pull from several tables (e.g. Receipt = bill payment + gift voucher + issue
// receipt + chit utilization + chit payment), so row_id alone is not unique -- it must be paired
// with the source key to know which table/flag a selection actually targets.
function tt_row_key(row) {
	return row.source + '|' + row.row_id;
}

$(document).ready(function () {

	if ($('#api_select').length == 0) {
		return;
	}

	$('#rpt_date_picker').daterangepicker(
		{
			ranges: {
				'Today': [moment(), moment()],
				'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
				'Last 7 Days': [moment().subtract(6, 'days'), moment()],
				'Last 30 Days': [moment().subtract(29, 'days'), moment()],
				'This Month': [moment().startOf('month'), moment().endOf('month')],
				'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
			},
			startDate: moment(),
			endDate: moment()
		},
		function (start, end) {
			$('#rpt_date_picker').html('<i class="fa fa-calendar"></i> ' + start.format('D/M/YYYY') + ' - ' + end.format('D/M/YYYY') + ' <i class="fa fa-caret-down"></i>');
			$('#rpt_from_date').text(start.format('YYYY-MM-DD'));
			$('#rpt_to_date').text(end.format('YYYY-MM-DD'));
		}
	);
	$('#rpt_date_picker').html('<i class="fa fa-calendar"></i> ' + moment().format('D/M/YYYY') + ' - ' + moment().format('D/M/YYYY') + ' <i class="fa fa-caret-down"></i>');
	$('#rpt_from_date').text(moment().format('YYYY-MM-DD'));
	$('#rpt_to_date').text(moment().format('YYYY-MM-DD'));

	$('#filter_mode').on('change', function () {
		tt_toggle_filter_mode();
	});
	tt_toggle_filter_mode();

	$('#tt_preview_btn').on('click', function () {
		tt_preview();
	});

	// "Select all" applies to every loaded row (all pages), not just the current page.
	$('#tt_select_all').on('change', function () {
		var checked = $(this).is(':checked');
		$.each(tt_preview_data, function (i, row) {
			if (row.status != 'not_found') {
				if (checked) {
					tt_selected_ids[tt_row_key(row)] = true;
				} else {
					delete tt_selected_ids[tt_row_key(row)];
				}
			}
		});
		tt_redraw_selection();
	});

	// Delegated from a stable wrapper (not the table itself, which DataTables recreates on every reinit).
	$('#tt_table_wrap').on('change', '.tt_row_chk', function () {
		var key = $(this).data('key');
		if ($(this).is(':checked')) {
			tt_selected_ids[key] = true;
		} else {
			delete tt_selected_ids[key];
		}
		tt_update_selected_count();
	});

	$('#tt_update_btn').on('click', function () {
		var count = Object.keys(tt_selected_ids).length;
		if (count == 0) {
			return;
		}
		$('#tt_modal_count').text(count);
		$('#tt_confirm_modal').modal('show');
	});

	$('#tt_confirm_update_btn').on('click', function () {
		$('#tt_confirm_modal').modal('hide');
		tt_update_flag();
	});
});

function tt_toggle_filter_mode() {
	if ($('#filter_mode').val() == 'billids') {
		$('#tt_daterange_wrap').hide();
		$('#tt_billids_wrap').show();
	} else {
		$('#tt_daterange_wrap').show();
		$('#tt_billids_wrap').hide();
	}
}

function tt_get_branch_id() {
	// Branch-locked login: value comes from the hidden field next to the disabled select.
	if ($('#branch_filter').length && $('#branch_filter').val() != '') {
		return $('#branch_filter').val();
	}
	// All-branch login: value comes from the shared branch_select populated by general.js
	// (get_branchname()), same as every other report page -- blank/"All" means no filter.
	return $('#branch_select').val();
}

function tt_show_alert(type, message) {
	$('#tt_result_alert').html(
		'<div class="alert alert-' + type + ' alert-dismissable">' +
		'<button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>' +
		message +
		'</div>'
	);
}

function tt_preview() {
	var api = $('#api_select').val();
	if (!api) {
		tt_show_alert('warning', 'Please select an API first.');
		return;
	}

	var mode = $('#filter_mode').val();
	var data = { 'api': api, 'mode': mode, 'status': $('#tt_status_filter').val() };

	if (mode == 'billids') {
		var billIds = $('#bill_ids').val();
		if (!billIds || !billIds.trim()) {
			tt_show_alert('warning', 'Please enter at least one bill ID / reference number.');
			return;
		}
		data.bill_ids = billIds;
	} else {
		var fromDate = $('#rpt_from_date').text();
		var toDate = $('#rpt_to_date').text();
		if (!fromDate || !toDate) {
			tt_show_alert('warning', 'Please select a date range.');
			return;
		}
		data.from_date = fromDate;
		data.to_date = toDate;
		data.id_branch = tt_get_branch_id();
	}

	$('#tt_result_alert').html('');
	$('.overlay').css('display', 'block');

	$.ajax({
		url: base_url + 'index.php/admin_tally_transfer/reset_flag/preview',
		data: data,
		dataType: 'JSON',
		type: 'POST',
		success: function (resp) {
			$('.overlay').css('display', 'none');

			if (resp.error) {
				tt_show_alert('danger', resp.error);
				tt_render_rows([]);
				return;
			}

			tt_render_rows(resp.rows || [], resp.counts);

			if (resp.truncated) {
				tt_show_alert('warning', 'Showing the first ' + resp.rows.length + ' matching records only. Narrow the date range to make sure every record is covered.');
			}
		},
		error: function () {
			$('.overlay').css('display', 'none');
			tt_show_alert('danger', 'Something went wrong while fetching records. Please try again.');
		}
	});
}

function tt_row_checkbox_html(row) {
	if (row.status == 'not_found') {
		return '<input type="checkbox" class="tt_row_chk" disabled>';
	}
	var key = tt_row_key(row);
	var checked = tt_selected_ids[key] ? 'checked' : '';
	return '<input type="checkbox" class="tt_row_chk" data-key="' + key + '" ' + checked + '>';
}

function tt_row_status_html(row) {
	if (row.status == 'not_found') {
		return '<span class="label label-default">Not Found</span>';
	}
	if (row.pending == 1) {
		return '<span class="label label-warning">Pending</span>';
	}
	return '<span class="label label-success">Transferred</span>';
}

function tt_render_rows(rows, counts) {
	tt_preview_data = rows || [];
	tt_selected_ids = {};
	$('#tt_select_all').prop('checked', false);

	counts = counts || { total: 0, pending: 0, transferred: 0 };
	$('#tt_total_count').text(counts.total + ' total');
	$('#tt_pending_count').text(counts.pending + ' pending');
	$('#tt_transferred_count').text(counts.transferred + ' transferred');

	$('#tally_reset_list').dataTable({
		"bDestroy": true,
		"bInfo": true,
		"bFilter": true,
		"bSort": true,
		"bPaginate": true,
		"iDisplayLength": 25,
		"aLengthMenu": [[10, 25, 50, 100], [10, 25, 50, 100]],
		"dom": 'lfrtip',
		"aaData": tt_preview_data,
		"aoColumns": [
			{ "mDataProp": function (row) { return tt_row_checkbox_html(row); } },
			{ "mDataProp": function (row) { return row.status == 'not_found' ? '-' : row.source_label; } },
			{ "mDataProp": function (row) { return row.status == 'not_found' ? row.input_id : (row.ref_no || row.row_id); } },
			{ "mDataProp": function (row) { return row.status == 'not_found' ? '-' : (row.display_date || '-'); } },
			{ "mDataProp": function (row) { return row.status == 'not_found' ? '-' : (row.party || '-'); } },
			{
				"mDataProp": function (row) {
					if (row.status == 'not_found') { return '-'; }
					return row.tot_amount != null ? money_format_india(parseFloat(row.tot_amount).toFixed(2)) : '-';
				}
			},
			{ "mDataProp": function (row) { return tt_row_status_html(row); } },
			{ "mDataProp": function (row) { return row.status == 'not_found' ? row.message : 'Ready to reset'; } }
		],
		"language": {
			"emptyTable": "No records found"
		}
	});

	tt_update_selected_count();
}

// Forces a redraw so every visible checkbox reflects tt_selected_ids (used after "select all").
function tt_redraw_selection() {
	var oTable = $('#tally_reset_list').DataTable();
	oTable.draw(false);
	tt_update_selected_count();
}

function tt_update_selected_count() {
	var count = Object.keys(tt_selected_ids).length;
	$('#tt_selected_count').text(count + ' selected');
	$('#tt_update_btn').prop('disabled', count == 0);
}

function tt_update_flag() {
	var api = $('#api_select').val();
	var ids = Object.keys(tt_selected_ids);

	if (!ids.length) {
		return;
	}

	var mode = $('#filter_mode').val();
	var filterSummary = '';
	if (mode == 'billids') {
		filterSummary = 'Bill IDs: ' + $('#bill_ids').val();
	} else {
		filterSummary = 'Date Range: ' + $('#rpt_from_date').text() + ' to ' + $('#rpt_to_date').text();
	}

	$('.overlay').css('display', 'block');

	$.ajax({
		url: base_url + 'index.php/admin_tally_transfer/reset_flag/reset',
		data: { 'api': api, 'ids': ids, 'filter_summary': filterSummary },
		dataType: 'JSON',
		type: 'POST',
		success: function (resp) {
			$('.overlay').css('display', 'none');

			if (resp.error) {
				tt_show_alert('danger', resp.error);
				return;
			}

			var msg = resp.updated.length + ' record(s) updated successfully.';
			if (resp.not_found.length) {
				msg += ' ' + resp.not_found.length + ' record(s) not found: ' + resp.not_found.join(', ');
			}
			tt_show_alert(resp.not_found.length ? 'warning' : 'success', msg);

			tt_preview();
		},
		error: function () {
			$('.overlay').css('display', 'none');
			tt_show_alert('danger', 'Something went wrong while updating. Please try again.');
		}
	});
}
