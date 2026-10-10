// Settings > Auto Day Close - list screen + Run Log screen.
// Client-side checks here are a convenience layer only; every rule is re-checked
// server-side in Admin_ret_dayclose (see the validation matrix in the task plan).

var dc_page = (window.location.pathname + window.location.search); // not used for logic, kept for debugging

function dc_alert(data) {
	var msg = '<div class="alert alert-' + data.class + ' alert-dismissable"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>' +
		'<h4><i class="icon fa fa-' + (data.class == 'success' ? 'check' : 'ban') + '"></i> ' + (data.title || 'Auto Day Close') + '</h4>' + data.message + '</div>';
	$('#chit_alert').html(msg);
	$.toaster({ priority: (data.class == 'success' ? 'success' : 'danger'), title: (data.title || 'Auto Day Close'), message: data.message });
}

// Whether a "HH:MM" slot string falls before noon.
function dc_is_am(hm) {
	if (!hm) { return false; }
	var hour = parseInt(hm.split(':')[0], 10);
	return hour < 12;
}

// Confirmation before ANY close-time save (default, branch default, today-only, today-only-
// all) if the chosen time is AM - auto day close is always meant to run in the evening, so
// an AM time is very likely a wrong selection (e.g. picking 07:00 AM instead of 07:00 PM)
// that would close a branch's business day within minutes of it opening.
//
// Asynchronous (shows a Bootstrap modal, not a blocking window.confirm): if hm isn't AM,
// onConfirmed() runs immediately; otherwise it only runs if the user explicitly confirms in
// the modal. onCancelled() (optional) runs if they dismiss it instead - callers that already
// applied a value to a control (e.g. a <select> whose change already fired) use this to
// revert the visible selection, since nothing was actually saved.
function dc_confirm_am(hm, onConfirmed, onCancelled) {
	if (!dc_is_am(hm)) { onConfirmed(); return; }

	var label = moment(hm, 'HH:mm').format('hh:mm A');
	$('#am_warning_time').text(label);

	$('#am_warning_confirm').off('click').on('click', function () {
		$('#confirm-am-warning').data('dc-confirmed', true);
		$('#confirm-am-warning').modal('hide');
		onConfirmed();
	});

	$('#confirm-am-warning').data('dc-confirmed', false);
	$('#confirm-am-warning').off('hidden.bs.modal').on('hidden.bs.modal', function () {
		if (!$(this).data('dc-confirmed') && onCancelled) { onCancelled(); }
	});

	$('#confirm-am-warning').modal('show');
}

// This modal can open on top of #confirm-override / #confirm-override-all, which are
// already showing when their own Save button triggers the AM check. Bootstrap 3 doesn't
// stack modals by default (the second backdrop lands behind the first), so bump both the
// modal and its backdrop above whatever is already open.
$(document).on('show.bs.modal', '#confirm-am-warning', function () {
	var zIndex = 1040 + (10 * $('.modal.in').length);
	$(this).css('z-index', zIndex + 10);
	setTimeout(function () {
		$('.modal-backdrop').not('.dc-stacked').css('z-index', zIndex).addClass('dc-stacked');
	}, 0);
});

// Builds <option> tags for every 30-min slot 00:00 - 23:30, selecting `selected_hm` if given.
function dc_build_slot_options($select, selected_hm) {
	$select.empty();
	for (var h = 0; h <= 23; h++) {
		for (var m = 0; m < 2; m++) {
			var hh = (h < 10 ? '0' + h : '' + h);
			var mm = (m == 0 ? '00' : '30');
			var hm = hh + ':' + mm;
			var label = moment(hm, 'HH:mm').format('hh:mm A');
			var opt = $('<option></option>').attr('value', hm).text(label);
			if (hm == selected_hm) { opt.attr('selected', 'selected'); }
			$select.append(opt);
		}
	}
}

$(document).ready(function () {

	if ($('#dayclose_list').length) { dc_init_list(); }
	if ($('#dayclose_log_list').length) { dc_init_log(); }

});

// =====================================================================
// List screen
// =====================================================================

function dc_init_list() {

	load_schedule();

	$('#branch_select').on('change', function () { load_schedule(); });
	$('#refresh_schedule').on('click', function () { load_schedule(); });

	$('#save_default_time').on('click', function () {
		var close_time = $('#dc_default_time').val();
		dc_confirm_am(close_time, function () {
			$.ajax({
				url: base_url + 'index.php/admin_ret_dayclose/schedule_post/save_global?nocache=' + new Date().getTime(),
				type: 'POST', dataType: 'JSON', cache: false,
				data: { default_time: close_time },
				success: function (data) { dc_alert(data); load_schedule(); }
			});
		});
	});

	$('#save_unlock_fallback').on('click', function () {
		$.ajax({
			url: base_url + 'index.php/admin_ret_dayclose/schedule_post/save_global?nocache=' + new Date().getTime(),
			type: 'POST', dataType: 'JSON', cache: false,
			data: { unlock_fallback: $('#dc_unlock_fallback').val() },
			success: function (data) { dc_alert(data); load_schedule(); }
		});
	});

	// Extend / Change today's close time
	$(document).on('click', '.dc-extend-btn', function () {
		var id_branch = $(this).data('id_branch');
		var branch_name = $(this).data('branch_name');
		var entry_date = $(this).data('entry_date');
		var current_effective = $(this).data('effective');

		$('#ov_id_branch').val(id_branch);
		$('#ov_branch_name').text(branch_name);
		$('#ov_entry_date').text(entry_date);
		$('#ov_entry_date2').text(entry_date);
		$('#ov_current_effective').text(current_effective);
		$('#ov_remarks').val('');
		$('#error-msg-ov').html('');
		$('#ov_remove').toggle($(this).data('has_override') == 1);

		dc_load_valid_slots($('#ov_close_time'));

		$('#confirm-override').modal('show');
	});

	// Fetches slots later than "now" (server-side, Asia/Kolkata) and fills the given <select>.
	// Shared by the per-branch and the "extend all branches" modals.
	function dc_load_valid_slots($select) {
		$.ajax({
			url: base_url + 'index.php/admin_ret_dayclose/schedule/valid_slots?nocache=' + new Date().getTime(),
			type: 'GET', dataType: 'JSON', cache: false,
			success: function (slots) {
				$select.empty();
				if (!slots.length) {
					$select.append($('<option></option>').attr('value', '').text('No time left today'));
				}
				$.each(slots, function (i, s) {
					$select.append($('<option></option>').attr('value', s.value).text(s.label));
				});
			}
		});
	}

	$('#save_override').on('click', function (e) {
		e.preventDefault();
		var remarks = $.trim($('#ov_remarks').val());
		var close_time = $('#ov_close_time').val();

		if (!close_time) {
			$('#error-msg-ov').html('<div class="alert alert-danger">Please select a close time.</div>');
			return;
		}
		if (remarks.length < 3) {
			$('#error-msg-ov').html('<div class="alert alert-danger">Please enter a reason for extending today\'s day close.</div>');
			return;
		}

		dc_confirm_am(close_time, function () {
			$.ajax({
				url: base_url + 'index.php/admin_ret_dayclose/schedule_post/save_override?nocache=' + new Date().getTime(),
				type: 'POST', dataType: 'JSON', cache: false,
				data: { id_branch: $('#ov_id_branch').val(), close_time: close_time, remarks: remarks },
				success: function (data) {
					if (data.class == 'success') {
						$('#confirm-override').modal('hide');
						dc_alert(data);
						load_schedule();
					} else {
						$('#error-msg-ov').html('<div class="alert alert-danger">' + data.message + '</div>');
					}
				}
			});
		});
	});

	$('#ov_remove').on('click', function () {
		$.ajax({
			url: base_url + 'index.php/admin_ret_dayclose/schedule_post/delete_override?nocache=' + new Date().getTime(),
			type: 'POST', dataType: 'JSON', cache: false,
			data: { id_branch: $('#ov_id_branch').val() },
			success: function (data) {
				$('#confirm-override').modal('hide');
				dc_alert(data);
				load_schedule();
			}
		});
	});

	// Extend all branches today
	$('#extend_all_branches').on('click', function () {
		$('#ova_remarks').val('');
		$('#error-msg-ova').html('');
		dc_load_valid_slots($('#ova_close_time'));
		$('#confirm-override-all').modal('show');
	});

	$('#save_override_all').on('click', function (e) {
		e.preventDefault();
		var remarks = $.trim($('#ova_remarks').val());
		var close_time = $('#ova_close_time').val();

		if (!close_time) {
			$('#error-msg-ova').html('<div class="alert alert-danger">Please select a close time.</div>');
			return;
		}
		if (remarks.length < 3) {
			$('#error-msg-ova').html('<div class="alert alert-danger">Please enter a reason for extending today\'s day close.</div>');
			return;
		}

		dc_confirm_am(close_time, function () {
			$.ajax({
				url: base_url + 'index.php/admin_ret_dayclose/schedule_post/save_override_all?nocache=' + new Date().getTime(),
				type: 'POST', dataType: 'JSON', cache: false,
				data: { close_time: close_time, remarks: remarks },
				success: function (data) {
					if (data.class == 'success') {
						$('#confirm-override-all').modal('hide');
						dc_alert(data);
						load_schedule();
					} else {
						$('#error-msg-ova').html('<div class="alert alert-danger">' + data.message + '</div>');
					}
				}
			});
		});
	});

	$(document).on('change', '.dc-branch-default', function () {
		var $this = $(this);
		var id_branch = $this.data('id_branch');
		var close_time = $this.val();

		dc_confirm_am(close_time, function () {
			$.ajax({
				url: base_url + 'index.php/admin_ret_dayclose/schedule_post/save_branch_default?nocache=' + new Date().getTime(),
				type: 'POST', dataType: 'JSON', cache: false,
				data: { id_branch: id_branch, close_time: close_time },
				success: function (data) { dc_alert(data); load_schedule(); }
			});
		}, function () {
			$this.val($this.data('prev_value')); // revert the visible selection - nothing was saved
		});
	});
}

function load_schedule() {
	var id_branch = $('#branch_select').length ? $('#branch_select').val() : '';
	$('.overlay').css('display', 'block');
	$.ajax({
		url: base_url + 'index.php/admin_ret_dayclose/schedule?nocache=' + new Date().getTime(),
		type: 'GET', dataType: 'JSON', cache: false,
		data: { id_branch: (id_branch ? id_branch : '') },
		success: function (data) {
			$('.overlay').css('display', 'none');
			render_global(data.global);
			render_branches(data.branches);
		},
		error: function () { $('.overlay').css('display', 'none'); }
	});
}

function render_global(global) {
	dc_build_slot_options($('#dc_default_time'), global.default_time_raw);
	dc_build_slot_options($('#dc_unlock_fallback'), global.unlock_fallback_raw);
}

function dc_source_badge(source) {
	if (source == 'override') { return '<span class="label label-info">Today only</span>'; }
	if (source == 'branch') { return '<span class="label label-default">Branch</span>'; }
	return '<span class="label label-default">Global</span>';
}

function render_branches(branches) {
	var $tbody = $('#dayclose_list tbody');
	$tbody.empty();
	$('#total_branches').text(branches.length);

	if (!branches.length) {
		$tbody.append('<tr><td colspan="6">No branches found.</td></tr>');
		return;
	}

	$.each(branches, function (i, b) {
		var status_html = (b.is_day_closed == 1)
			? '<span class="label label-default"><i class="fa fa-check"></i> CLOSED</span><br><small>closed ' + (b.closed_at || '') + '</small>'
			: '<span class="label label-success">&#9679; OPEN</span>';

		var effective_html = '<strong>' + b.effective_time + '</strong><br>' + dc_source_badge(b.effective_source) +
			(b.effective_source == 'override' && b.override_remarks ? '<br><small title="' + b.override_remarks + '"><i class="fa fa-info-circle"></i> ' + b.override_remarks + '</small>' : '');

		var default_select_id = 'dc_bd_' + b.id_branch;
		var default_html = '<select id="' + default_select_id + '" class="form-control dc-branch-default" data-id_branch="' + b.id_branch + '" style="width:110px;display:inline-block;"></select>';

		var action_html;
		if (b.is_day_closed == 1) {
			action_html = '<button type="button" class="btn btn-default btn-xs" disabled title="Day close for ' + b.entry_date + ' completed at ' + b.closed_at + '. Today\'s time can no longer be changed."><i class="fa fa-lock"></i> Locked</button>';
		} else {
			var has_override = (b.effective_source == 'override') ? 1 : 0;
			action_html = '<button type="button" class="btn btn-' + (has_override ? 'warning' : 'success') + ' btn-xs dc-extend-btn" ' +
				'data-id_branch="' + b.id_branch + '" data-branch_name="' + b.branch_name + '" data-entry_date="' + b.entry_date + '" ' +
				'data-effective="' + b.effective_time + '" data-has_override="' + has_override + '">' +
				(has_override ? 'Change' : 'Extend') + '</button>';
		}

		var row = '<tr>' +
			'<td>' + b.branch_name + '</td>' +
			'<td>' + b.entry_date + '</td>' +
			'<td>' + status_html + '</td>' +
			'<td>' + effective_html + '</td>' +
			'<td>' + default_html + '</td>' +
			'<td>' + action_html + '</td>' +
			'</tr>';
		$tbody.append(row);

		var $ds = $('#' + default_select_id);
		$ds.append($('<option></option>').attr('value', '').text('Use global'));
		for (var h = 0; h <= 23; h++) {
			for (var m = 0; m < 2; m++) {
				var hh = (h < 10 ? '0' + h : '' + h);
				var mm = (m == 0 ? '00' : '30');
				var hm = hh + ':' + mm;
				var opt = $('<option></option>').attr('value', hm).text(moment(hm, 'HH:mm').format('hh:mm A'));
				if (hm == b.branch_default_raw) { opt.attr('selected', 'selected'); }
				$ds.append(opt);
			}
		}
		if (b.is_day_closed == 1) { $ds.attr('disabled', 'disabled'); }
		$ds.data('prev_value', b.branch_default_raw); // so a cancelled AM confirmation can revert the visible selection
	});
}

// =====================================================================
// Run Log screen
// =====================================================================

function dc_init_log() {

	if ($('#log_from_date').length && $.fn.datepicker) {
		$('#log_from_date,#log_to_date').datepicker({ format: 'yyyy-mm-dd', autoclose: true });
	}

	load_log();

	$('#log_filter').on('click', function () { load_log(); });
}

function load_log() {
	var params = {
		id_branch: $('#log_branch_select').length ? $('#log_branch_select').val() : '',
		from_date: $('#log_from_date').val(),
		to_date: $('#log_to_date').val()
	};

	$.ajax({
		url: base_url + 'index.php/admin_ret_dayclose/schedule/log_data?nocache=' + new Date().getTime(),
		type: 'GET', dataType: 'JSON', cache: false,
		data: params,
		success: function (data) {
			render_log_summary(data.summary);
			render_log_rows(data.logs);
		}
	});
}

function render_log_summary(s) {
	$('#dc_last_run').text(s.last_run_at ? (s.last_run_at + ' (' + s.last_run_minutes_ago + ' min ago)') : 'No runs recorded yet');
	$('#dc_closed_today').text(s.closed_today);
	$('#dc_fallback_30d').text(s.fallback_30d);
	$('#dc_failed_30d').text(s.failed_30d);

	if (s.is_stale) {
		$('#dc_stale_minutes').text(s.last_run_minutes_ago ? (s.last_run_minutes_ago + ' minutes') : 'an unknown time');
		$('#dc_stale_warning').show();
	} else {
		$('#dc_stale_warning').hide();
	}
}

function dc_action_class(action) {
	if (action == 'closed') { return 'success'; }
	if (action == 'reopened_fallback') { return 'warning'; }
	if (action == 'failed') { return 'danger'; }
	return 'default';
}

function render_log_rows(logs) {
	var $tbody = $('#dayclose_log_list tbody');
	$tbody.empty();

	if (!logs.length) {
		$tbody.append('<tr><td colspan="7">No log entries found for this filter.</td></tr>');
		return;
	}

	$.each(logs, function (i, l) {
		var cls = dc_action_class(l.action);
		var row = '<tr class="' + (cls != 'default' ? 'bg-' + (cls == 'danger' ? 'red' : (cls == 'success' ? 'green' : 'yellow')) + '-light' : '') + '">' +
			'<td>' + l.run_at + '</td>' +
			'<td>' + l.branch_name + '</td>' +
			'<td>' + l.entry_date + '</td>' +
			'<td>' + l.effective_close_time + '</td>' +
			'<td>' + l.time_source + '</td>' +
			'<td><span class="label label-' + cls + '">' + l.action + '</span></td>' +
			'<td style="text-align:left;">' + (l.message || '') + '</td>' +
			'</tr>';
		$tbody.append(row);
	});
}
