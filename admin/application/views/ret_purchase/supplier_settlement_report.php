<?php
/**
 * Supplier Settlement Report (SPS-15)
 *
 * One row per purchase bill: value, what has been settled, what is left, how many
 * payments it took and where it stands. Clicking a row opens every payment ever
 * allocated to that bill - cancelled ones included - with a running balance.
 *
 * Acceptance criteria covered:
 *   #2 outstanding balance visible after each payment
 *   #6 reports reflect payment allocations and outstanding balances
 *   #7 payment history and settlement status per supplier and purchase transaction
 *   #8 settlement details available for audit and reconciliation
 */
?>
<style>
    .sps-report .filters .form-group { margin-bottom: 0; }
    .sps-report table                { font-size: 12px; }
    .sps-report td.num,
    .sps-report th.num               { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
    .sps-report tfoot td             { font-weight: 700; background: #f7f7f5; border-top: 2px solid #d2d6de; }
    .sps-report tbody tr.drill       { cursor: pointer; }
    .sps-report tbody tr.drill:hover { background: #f0f7ff; }
    .sps-report .muted               { color: #999; }
    .sps-report .st-Settled          { color: #00733e; font-weight: 600; }
    .sps-report .st-Partial          { color: #8a6d3b; font-weight: 600; }
    .sps-report .st-Unpaid           { color: #a94442; font-weight: 600; }
    .sps-report .cancelled           { color: #a94442; text-decoration: line-through; }
    .sps-report .hist-note           { font-size: 11px; color: #a94442; }
</style>

<div class="content-wrapper">
  <section class="content-header">
    <h1>Purchase <small>Supplier Settlement Report</small></h1>
    <ol class="breadcrumb">
      <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
      <li><a href="#">Purchase</a></li>
      <li class="active">Supplier Settlement</li>
    </ol>
  </section>

  <section class="content sps-report">
    <div class="box box-primary">

      <div class="box-header with-border">
        <h3 class="box-title">Bill-wise Settlement Position</h3>
        <span id="sps_rep_count" class="badge bg-green"></span>
      </div>

      <div class="box-body">

        <!-- ===== filters ===== -->
        <div class="row filters" style="margin-bottom:14px;">
          <div class="col-md-3">
            <div class="form-group">
              <label>Supplier</label>
              <select id="sps_rep_karigar" class="form-control" style="width:100%;">
                <option value="0">All suppliers</option>
                <?php foreach ((isset($suppliers) ? $suppliers : array()) as $s) { ?>
                  <option value="<?php echo (int)$s['id_karigar']; ?>"><?php echo html_escape($s['firstname']); ?></option>
                <?php } ?>
              </select>
            </div>
          </div>

          <div class="col-md-2">
            <div class="form-group">
              <label>Settlement Status</label>
              <select id="sps_rep_status" class="form-control" style="width:100%;">
                <option value="all">All</option>
                <option value="Unpaid">Unpaid</option>
                <option value="Partial">Partially Settled</option>
                <option value="Settled">Settled</option>
              </select>
            </div>
          </div>

          <div class="col-md-2">
            <div class="form-group">
              <label>Bill From</label>
              <input type="text" id="sps_rep_from" class="form-control datepicker" autocomplete="off">
            </div>
          </div>

          <div class="col-md-2">
            <div class="form-group">
              <label>Bill To</label>
              <input type="text" id="sps_rep_to" class="form-control datepicker" autocomplete="off">
            </div>
          </div>

          <div class="col-md-3">
            <div class="form-group">
              <label>&nbsp;</label><br>
              <button type="button" id="sps_rep_go" class="btn btn-primary"><i class="fa fa-search"></i> Show</button>
              <button type="button" id="sps_rep_clear" class="btn btn-default">Clear</button>
            </div>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-bordered table-striped" id="sps_rep_table">
            <thead>
              <tr>
                <th>Supplier</th>
                <th>Bill No</th>
                <th>Bill Date</th>
                <th class="num">Bill Amount</th>
                <th class="num">Dr Note</th>
                <th class="num">Return</th>
                <th class="num">Settled</th>
                <th class="num">Balance</th>
                <th class="num">Payments</th>
                <th>Last Payment</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody id="sps_rep_body">
              <tr><td colspan="11" class="muted">Choose your filters and press Show.</td></tr>
            </tbody>
            <tfoot id="sps_rep_foot"></tfoot>
          </table>
        </div>

        <p class="muted" style="margin-top:6px;font-size:11px;">
          Click any row to see every payment allocated to that bill.
        </p>

      </div>
    </div>
  </section>
</div>

<!-- ===== drill-down: payment history for one bill ===== -->
<div class="modal fade" id="sps_hist_modal" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="btn btn-box-tool pull-right" data-dismiss="modal"><i class="fa fa-times"></i></button>
        <h4 class="modal-title">Settlement History <small id="sps_hist_ref"></small></h4>
      </div>
      <div class="modal-body sps-report">
        <div id="sps_hist_summary" style="margin-bottom:10px;"></div>
        <div class="table-responsive">
          <table class="table table-bordered table-striped">
            <thead>
              <tr>
                <th>#</th>
                <th>Date</th>
                <th>Payment No</th>
                <th>Mode</th>
                <th class="num">Allocated</th>
                <th class="num">Balance After</th>
                <th>Entered By</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody id="sps_hist_body"></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
(function () {

    /* Payment-mode codes from ret_po_payment_detail.type, shown so a settlement line can
       be traced back to how the money actually moved (AC #8). */
    var SPS_MODE = { '1': 'Cash', '2': 'Net Banking', '3': 'Advance Adj', '5': 'Cheque' };

    function num(v) {
        var n = parseFloat(v) || 0;
        return n.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function esc(s) {
        return $('<div>').text(s === null || s === undefined ? '' : s).html();
    }

    function modeLabel(csv) {
        if (!csv) { return '<span class="muted">-</span>'; }
        return String(csv).split(',').map(function (t) {
            return SPS_MODE[$.trim(t)] || ('Type ' + $.trim(t));
        }).join(', ');
    }

    function loadReport() {
        $.ajax({
            type: 'POST',
            url: base_url + 'index.php/admin_ret_purchase/supplier_settlement_report/ajax',
            dataType: 'json',
            data: {
                id_karigar:    $('#sps_rep_karigar').val(),
                settle_status: $('#sps_rep_status').val(),
                from_date:     $('#sps_rep_from').val(),
                to_date:       $('#sps_rep_to').val()
            },
            beforeSend: function () { $('div.overlay').css('display', 'block'); },
            success: function (res) {
                $('div.overlay').css('display', 'none');

                var rows = (res && res.list) ? res.list : [];
                $('#sps_rep_count').text(rows.length + ' bill(s)');

                if (!rows.length) {
                    $('#sps_rep_body').html('<tr><td colspan="11" class="muted">No bills match these filters.</td></tr>');
                    $('#sps_rep_foot').html('');
                    return;
                }

                var html = '';
                $.each(rows, function (i, r) {
                    html += '<tr class="drill" data-po="' + r.po_id + '">' +
                        '<td>' + esc(r.supplier_name) + '</td>' +
                        '<td>' + esc(r.po_ref_no) + '</td>' +
                        '<td>' + esc(r.po_date) + '</td>' +
                        '<td class="num">' + num(r.bill_amount) + '</td>' +
                        '<td class="num">' + num(r.debit_note_amt) + '</td>' +
                        '<td class="num">' + num(r.return_amt) + '</td>' +
                        '<td class="num">' + num(r.settled_amt) + '</td>' +
                        '<td class="num">' + num(r.balance_amt) + '</td>' +
                        '<td class="num">' + (parseInt(r.payment_count, 10) || 0) + '</td>' +
                        '<td>' + (r.last_payment_date ? esc(r.last_payment_date) : '<span class="muted">-</span>') + '</td>' +
                        '<td class="st-' + esc(r.settle_status) + '">' + esc(r.settle_status) + '</td>' +
                        '</tr>';
                });
                $('#sps_rep_body').html(html);

                var t = res.totals || {};
                $('#sps_rep_foot').html(
                    '<tr><td colspan="3">Total</td>' +
                    '<td class="num">' + num(t.bill_amount) + '</td>' +
                    '<td class="num">' + num(t.debit_note_amt) + '</td>' +
                    '<td class="num">' + num(t.return_amt) + '</td>' +
                    '<td class="num">' + num(t.settled_amt) + '</td>' +
                    '<td class="num">' + num(t.balance_amt) + '</td>' +
                    '<td colspan="3"></td></tr>'
                );
            },
            error: function () {
                $('div.overlay').css('display', 'none');
                $.toaster({ priority: 'danger', title: 'Error', message: '</br>Unable to load the settlement report' });
            }
        });
    }

    function loadHistory(po_id) {
        $.ajax({
            type: 'POST',
            url: base_url + 'index.php/admin_ret_purchase/supplier_settlement_report/history',
            dataType: 'json',
            data: { po_id: po_id },
            beforeSend: function () { $('div.overlay').css('display', 'block'); },
            success: function (res) {
                $('div.overlay').css('display', 'none');

                var s = res.status || {};
                var h = res.history || [];

                $('#sps_hist_ref').text('- ' + (s.po_ref_no || '') + ' / ' + (s.supplier_name || ''));

                $('#sps_hist_summary').html(
                    '<div class="row">' +
                    '<div class="col-md-3"><b>Bill Amount</b><br>' + num(s.bill_amount) + '</div>' +
                    '<div class="col-md-3"><b>Settled</b><br>' + num(s.settled_amt) + '</div>' +
                    '<div class="col-md-3"><b>Balance</b><br>' + num(s.balance_amt) + '</div>' +
                    '<div class="col-md-3"><b>Status</b><br><span class="st-' + esc(s.settle_status) + '">' +
                        esc(s.settle_status) + '</span></div>' +
                    '</div>'
                );

                if (!h.length) {
                    $('#sps_hist_body').html('<tr><td colspan="8" class="muted">No payment has been allocated to this bill yet.</td></tr>');
                    $('#sps_hist_modal').modal('show');
                    return;
                }

                /* The running balance starts at the bill's NET value - what the bill was
                   worth after Dr/Cr notes and returns - so the final figure lands on the
                   same balance the report row shows. */
                var running = (parseFloat(s.bill_amount) || 0)
                            - (parseFloat(s.debit_note_amt) || 0)
                            + (parseFloat(s.credit_note_amt) || 0)
                            - (parseFloat(s.return_amt) || 0)
                            - (parseFloat(s.crdr_adj_amt) || 0)
                            - (parseFloat(s.ret_adj_amt) || 0);

                var html = '';
                $.each(h, function (i, p) {
                    var cancelled = (parseInt(p.pay_status, 10) === 2);

                    /* A cancelled payment is SHOWN but does not move the running balance -
                       omitting it would hide a reversal from the audit trail, and counting
                       it would contradict the outstanding figure. */
                    if (!cancelled) {
                        running = Math.round((running - (parseFloat(p.bill_amount) || 0)) * 100) / 100;
                    }

                    html += '<tr' + (cancelled ? ' class="cancelled"' : '') + '>' +
                        '<td>' + (i + 1) + '</td>' +
                        '<td>' + esc(p.pay_date) + '</td>' +
                        '<td>' + esc(p.pay_refno) + '</td>' +
                        '<td>' + modeLabel(p.mode_types) + '</td>' +
                        '<td class="num">' + num(p.bill_amount) + '</td>' +
                        '<td class="num">' + (cancelled ? '<span class="muted">-</span>' : num(running)) + '</td>' +
                        '<td>' + esc(p.created_by_name) + '</td>' +
                        '<td>' + (cancelled
                            ? 'Cancelled<br><span class="hist-note">' + esc(p.cancel_reason || '') + '</span>'
                            : 'Live') + '</td>' +
                        '</tr>';
                });

                $('#sps_hist_body').html(html);
                $('#sps_hist_modal').modal('show');
            },
            error: function () {
                $('div.overlay').css('display', 'none');
                $.toaster({ priority: 'danger', title: 'Error', message: '</br>Unable to load the payment history' });
            }
        });
    }

    $(document).on('click', '#sps_rep_go', loadReport);

    $(document).on('click', '#sps_rep_clear', function () {
        $('#sps_rep_karigar').val('0');
        $('#sps_rep_status').val('all');
        $('#sps_rep_from').val('');
        $('#sps_rep_to').val('');
        loadReport();
    });

    $(document).on('click', '#sps_rep_table tbody tr.drill', function () {
        loadHistory($(this).data('po'));
    });

    $(function () {
        if ($.fn.datepicker) {
            $('.sps-report .datepicker').datepicker({ format: 'dd-mm-yyyy', autoclose: true, todayHighlight: true });
        }
        loadReport();
    });

})();
</script>
