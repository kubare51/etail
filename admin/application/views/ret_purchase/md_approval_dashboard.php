<style>
    /* CPO-06c — bullion detail and PO value on the approval row */
    #md_approval_table td.md-num,
    #md_approval_table th.md-num   { text-align: right; white-space: nowrap;
                                     font-variant-numeric: tabular-nums; }
    #md_approval_table .md-sub     { color: #777; font-size: 11px; white-space: nowrap; }
    #md_approval_table .md-muted   { color: #aaa; }
    #md_approval_table .md-cpo-tag { font-weight: 600; letter-spacing: .3px; }
</style>

  <!-- Content Wrapper. Contains page content -->
      <div class="content-wrapper">
        <!-- Content Header (Page header) -->
        <section class="content-header">
          <h1>
           Purchase
            <small>MD Approval Dashboard</small>
          </h1>
          <ol class="breadcrumb">
            <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
            <li><a href="#">Purchase</a></li>
            <li class="active">MD Approval Dashboard</li>
          </ol>
        </section>

        <!-- Main content -->
        <section class="content">
          <div class="row">
            <div class="col-xs-12">
               <div class="box box-primary">
			    <div class="box-header with-border">
                  <h3 class="box-title">Purchase Orders - Pending MD Approval</h3>
                  <span id="pending_count" class="badge bg-orange"></span>

                  <!-- Bulk Action Bar (hidden by default) -->
                  <div class="pull-right" id="bulk_action_bar" style="display:none;">
                    <button type="button" id="btn_bulk_approve" class="btn btn-success btn-sm">
                      <i class="fa fa-check-circle"></i> Approve Selected
                    </button>
                  </div>
                </div>

                 <div class="box-body">

                  <div class="table-responsive">
	                 <table id="md_approval_table" class="table table-bordered table-striped text-center">
	                    <thead>
	                      <tr>
	                        <th width="3%;"><input type="checkbox" id="select_all_chk"></th>
	                        <th width="9%;">PO No</th>
	                        <th width="7%;">Order Date</th>
	                        <th width="10%;">Karigar</th>
	                        <th width="12%;">Product</th>
	                        <th width="5%;">Pcs</th>
	                        <th width="8%;">Approx Wt</th>
	                        <th width="10%;">Weight Range</th>
	                        <th width="11%;" class="md-num">PO Value</th>
	                        <th width="7%;">Type</th>
	                        <th width="8%;">Created By</th>
	                        <th width="10%;">Action</th>
	                      </tr>
	                    </thead>
	                 </table>
                  </div>

                </div><!-- /.box-body -->

                <div class="overlay" id="loading_overlay" style="display:none">
				  <i class="fa fa-refresh fa-spin"></i>
				</div>

            </div><!-- /.col -->

          </div><!-- /.row -->

        </section><!-- /.content -->

      </div><!-- /.content-wrapper -->


<!-- Reject Reason Modal -->
<div class="modal fade" id="reject_modal" tabindex="-1" role="dialog" aria-labelledby="rejectModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header" style="background-color:#dd4b39; color:#fff;">
        <button type="button" class="close" data-dismiss="modal" style="color:#fff;"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title" id="rejectModalLabel"><i class="fa fa-times-circle"></i> Reject Purchase Order</h4>
      </div>
      <div class="modal-body">
        <input type="hidden" id="reject_order_id" value="">
        <div class="form-group">
          <label for="reject_reason">Reason for Rejection <span class="text-red">*</span></label>
          <textarea class="form-control" id="reject_reason" rows="4" placeholder="Enter reason for rejecting this PO..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-danger" id="btn_confirm_reject"><i class="fa fa-times"></i> Reject</button>
        <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
      </div>
    </div>
  </div>
</div>
<!-- / Reject Reason Modal -->



<script>
$(document).ready(function() {

    var base_url = '<?php echo base_url(); ?>';

    /* CPO-06c: these cells are built as HTML, so anything coming from a master —
       product name, karigar name — is escaped on the way in. */
    function mdEsc(s) {
        if (s === null || s === undefined) { return ''; }
        return $('<div>').text(s).html();
    }

    /* Indian lakh/crore grouping, via the shared money_format_india() in
       layout/footer.php. money_format_india does not round, so the decimals are
       fixed first — the pairing used everywhere else in the application. */
    function mdMoney(v, dp) {
        var n = parseFloat(v);
        var s = (isNaN(n) ? 0 : n).toFixed(dp === undefined ? 2 : dp);
        return (typeof money_format_india === 'function') ? money_format_india(s) : s;
    }

    // Initialize DataTable
    var mdTable = $('#md_approval_table').DataTable({
        "ajax": {
            "url": base_url + "index.php/Admin_ret_purchase_approval/md_dashboard/ajax",
            "type": "GET",
            "dataSrc": "data"
        },
        "columns": [
            {
                "data": "id_customerorder",
                "render": function(data) {
                    return '<input type="checkbox" class="row_chk" value="' + data + '">';
                },
                "orderable": false,
                "searchable": false
            },
            {
                // CPO-06c: mark a Common PO on sight. It is approved against an agreed
                // rate, so it is a different kind of decision from an ornament order.
                "data": "pur_no",
                "render": function(data, type, row) {
                    if (type !== 'display') { return data; }
                    var v = mdEsc(data);
                    if (Number(row.is_common_po) === 1) {
                        v += '<br><span class="label label-primary md-cpo-tag">Common PO</span>';
                    }
                    return v;
                }
            },
            { "data": "order_date" },
            { "data": "karigar_name" },
            {
                // Bullion lines carry a purity, not a design.
                "data": "products",
                "render": function(data, type, row) {
                    if (type !== 'display') { return data; }
                    var v = mdEsc(data);
                    if (Number(row.is_common_po) === 1 && row.purity_summary) {
                        v += '<br><small class="md-sub">Purity ' + mdEsc(row.purity_summary) + '</small>';
                    }
                    return v;
                }
            },
            { "data": "total_pcs" },
            {
                // Bullion is bought on pure weight — that is what the value is struck on,
                // so it is shown next to the gross rather than left to be inferred.
                "data": "order_approx_wt",
                "render": function(data, type, row) {
                    if (type !== 'display') { return data; }
                    var v = mdEsc(data);
                    if (Number(row.is_common_po) === 1 && Number(row.pure_wt) > 0) {
                        v += '<br><small class="md-sub">Pure ' + mdMoney(row.pure_wt, 3) + ' g</small>';
                    }
                    return v;
                }
            },
            {
                // Empty for a Common PO — bullion has no weight range. Show the agreed
                // rate there instead of a blank cell.
                "data": "weight_range",
                "render": function(data, type, row) {
                    if (type !== 'display') { return data; }
                    if (Number(row.is_common_po) !== 1) { return mdEsc(data); }
                    return row.rate_summary
                        ? '<small class="md-sub">' + mdEsc(row.rate_summary) + ' / g</small>'
                        : '<small class="md-sub md-muted">rate not fixed</small>';
                }
            },
            {
                // The number MD is actually approving. It was on no screen before.
                "data": "po_value_label",
                "className": "md-num",
                "render": function(data, type, row) {
                    if (type !== 'display') { return Number(row.po_value) || 0; }
                    if (Number(row.po_value) <= 0 && Number(row.is_common_po) !== 1) { return '<span class="md-muted">&mdash;</span>'; }
                    var v = '<b>' + mdEsc(data) + '</b>';
                    if (row.rate_type_label) {
                        v += '<br><small class="md-sub">' + mdEsc(row.rate_type_label) + '</small>';
                    }
                    return v;
                }
            },
            { "data": "order_type_label" },
            { "data": "created_by_name" },
            {
                "data": "id_customerorder",
                "render": function(data) {
                    return '<button class="btn btn-success btn-xs btn_approve" data-id="' + data + '" title="Approve">' +
                           '<i class="fa fa-check"></i></button> ' +
                           '<button class="btn btn-danger btn-xs btn_reject" data-id="' + data + '" title="Reject">' +
                           '<i class="fa fa-times"></i></button>';
                },
                "orderable": false,
                "searchable": false
            }
        ],
        "order": [[2, "desc"]],
        "pageLength": 25,
        "drawCallback": function(settings) {
            var count = this.api().data().length;
            $('#pending_count').text(count + ' Pending');
        }
    });

    // ===== Select All Checkbox =====
    $('#select_all_chk').on('change', function() {
        var checked = this.checked;
        $('.row_chk').prop('checked', checked);
        toggleBulkBar();
    });

    // Individual checkbox change
    $(document).on('change', '.row_chk', function() {
        var allChecked = $('.row_chk').length === $('.row_chk:checked').length;
        $('#select_all_chk').prop('checked', allChecked);
        toggleBulkBar();
    });

    function toggleBulkBar() {
        var checkedCount = $('.row_chk:checked').length;
        if(checkedCount > 0) {
            $('#bulk_action_bar').show();
        } else {
            $('#bulk_action_bar').hide();
        }
    }

    // ===== Single Approve =====
    $(document).on('click', '.btn_approve', function() {
        var id = $(this).data('id');
        if(!confirm('Are you sure you want to approve this Purchase Order?')) return;

        $('#loading_overlay').show();
        $.ajax({
            url: base_url + "index.php/Admin_ret_purchase_approval/md_dashboard/approve",
            type: "POST",
            data: { id_customerorder: id },
            dataType: "json",
            success: function(res) {
                $('#loading_overlay').hide();
                if(res.status) {
                    toastr.success(res.msg);
                    mdTable.ajax.reload(null, false);
                } else {
                    toastr.error(res.msg);
                }
            },
            error: function() {
                $('#loading_overlay').hide();
                toastr.error('Server error. Please try again.');
            }
        });
    });

    // ===== Single Reject - Open Modal =====
    $(document).on('click', '.btn_reject', function() {
        var id = $(this).data('id');
        $('#reject_order_id').val(id);
        $('#reject_reason').val('');
        $('#reject_modal').modal('show');
    });

    // Confirm Reject
    $('#btn_confirm_reject').on('click', function() {
        var id = $('#reject_order_id').val();
        var reason = $.trim($('#reject_reason').val());
        if(reason === '') {
            toastr.warning('Please enter a reason for rejection.');
            return;
        }

        $('#reject_modal').modal('hide');
        $('#loading_overlay').show();
        $.ajax({
            url: base_url + "index.php/Admin_ret_purchase_approval/md_dashboard/reject",
            type: "POST",
            data: { id_customerorder: id, reason: reason },
            dataType: "json",
            success: function(res) {
                $('#loading_overlay').hide();
                if(res.status) {
                    toastr.success(res.msg);
                    mdTable.ajax.reload(null, false);
                } else {
                    toastr.error(res.msg);
                }
            },
            error: function() {
                $('#loading_overlay').hide();
                toastr.error('Server error. Please try again.');
            }
        });
    });

    // ===== Bulk Approve =====
    $('#btn_bulk_approve').on('click', function() {
        var selected = [];
        $('.row_chk:checked').each(function() {
            selected.push($(this).val());
        });
        if(selected.length === 0) {
            toastr.warning('Please select at least one order.');
            return;
        }
        if(!confirm('Are you sure you want to approve ' + selected.length + ' Purchase Order(s)?')) return;

        $('#loading_overlay').show();
        $.ajax({
            url: base_url + "index.php/Admin_ret_purchase_approval/md_dashboard/bulk_approve",
            type: "POST",
            data: { order_ids: selected },
            dataType: "json",
            success: function(res) {
                $('#loading_overlay').hide();
                if(res.status) {
                    toastr.success(res.msg);
                    mdTable.ajax.reload(null, false);
                    $('#select_all_chk').prop('checked', false);
                    toggleBulkBar();
                } else {
                    toastr.error(res.msg);
                }
            },
            error: function() {
                $('#loading_overlay').hide();
                toastr.error('Server error. Please try again.');
            }
        });
    });

});
</script>
