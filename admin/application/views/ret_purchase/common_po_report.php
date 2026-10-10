<?php
/**
 * Common PO Status Report (CPO-18)
 *
 * One row per Common PO: what was committed, what arrived, what was advanced,
 * and where the supplier account stands. Answers the two questions the ledger
 * column can only answer one PO at a time — how much advance is sitting
 * unadjusted, and which POs still owe the supplier money.
 */
?>
<style>
    .cpo-report .filters .form-group { margin-bottom: 0; }
    .cpo-report table                { font-size: 12px; }
    .cpo-report td.num,
    .cpo-report th.num               { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
    .cpo-report tfoot td             { font-weight: 700; background: #f7f7f5; border-top: 2px solid #d2d6de; }
    .cpo-report .short               { color: #a94442; }
    .cpo-report .excess              { color: #8a6d3b; }
    .cpo-report .muted               { color: #999; }
</style>

<div class="content-wrapper">
  <section class="content-header">
    <h1>Purchase <small>Common PO Status Report</small></h1>
    <ol class="breadcrumb">
      <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
      <li><a href="#">Purchase</a></li>
      <li class="active">Common PO Report</li>
    </ol>
  </section>

  <section class="content cpo-report">
    <div class="box box-primary">

      <div class="box-header with-border">
        <h3 class="box-title">Common PO Status</h3>
        <span id="cpo_rep_count" class="badge bg-green"></span>
      </div>

      <div class="box-body">

        <!-- ===== filters ===== -->
        <div class="row filters" style="margin-bottom:14px;">
          <div class="col-md-3">
            <div class="form-group">
              <label>Supplier</label>
              <select id="cpo_rep_karigar" class="form-control" style="width:100%;">
                <option value="0">All suppliers</option>
                <?php foreach ((isset($suppliers) ? $suppliers : array()) as $s) { ?>
                  <option value="<?php echo (int)$s['id_karigar']; ?>"><?php echo html_escape($s['firstname']); ?></option>
                <?php } ?>
              </select>
            </div>
          </div>

          <div class="col-md-2">
            <div class="form-group">
              <label>PO Status</label>
              <select id="cpo_rep_status" class="form-control" style="width:100%;">
                <option value="all">All</option>
                <option value="0">Open</option>
                <option value="1">Advance Paid</option>
                <option value="2">Received</option>
                <option value="3">Billed</option>
                <option value="4">Closed</option>
                <option value="5">Cancelled</option>
              </select>
            </div>
          </div>

          <div class="col-md-2">
            <div class="form-group">
              <label>Ledger Position</label>
              <select id="cpo_rep_position" class="form-control" style="width:100%;">
                <option value="all">All</option>
                <option value="Nil">Nil</option>
                <option value="Payable">Payable</option>
                <option value="Receivable">Receivable</option>
                <option value="Open">Open</option>
              </select>
            </div>
          </div>

          <div class="col-md-3">
            <div class="form-group">
              <label>PO Date</label>
              <div class="input-group">
                <input type="text" id="cpo_rep_from" class="form-control datemask date" placeholder="From" data-date-format="dd-mm-yyyy">
                <span class="input-group-addon">to</span>
                <input type="text" id="cpo_rep_to" class="form-control datemask date" placeholder="To" data-date-format="dd-mm-yyyy">
              </div>
            </div>
          </div>

          <div class="col-md-2">
            <div class="form-group">
              <label>&nbsp;</label><br>
              <button type="button" id="cpo_rep_search" class="btn btn-info">Search</button>
              <button type="button" id="cpo_rep_reset" class="btn btn-default">Reset</button>
            </div>
          </div>
        </div>

        <!-- ===== table ===== -->
        <div class="table-responsive">
          <table id="cpo_report_table" class="table table-bordered table-striped">
            <thead>
              <tr>
                <th>PO No</th>
                <th>Date</th>
                <th>Supplier</th>
                <th>Rate Type</th>
                <th>Status</th>
                <th class="num">Committed g</th>
                <th class="num">Received g</th>
                <th class="num">Variance g</th>
                <th class="num">PO Value</th>
                <th class="num">Billed</th>
                <th class="num">Advance</th>
                <th class="num">Adjusted</th>
                <th class="num">Payable</th>
                <th class="num">Receivable</th>
                <th>Position</th>
                <th>Trace</th>
              </tr>
            </thead>
            <tbody id="cpo_report_body">
              <tr><td colspan="16" class="text-center muted">Choose your filters and click Search.</td></tr>
            </tbody>
            <tfoot id="cpo_report_foot"></tfoot>
          </table>
        </div>

        <p class="help-block" style="margin-top:10px;">
          <b>Payable</b> = Billed &minus; Adjusted &nbsp;&middot;&nbsp;
          <b>Receivable</b> = Advance &minus; Adjusted.
          Figures come from live documents only &mdash; a cancelled bill, a cancelled
          payment or a reversed adjustment drops out immediately.
          <b>Variance</b> is received weight less committed weight: negative is short delivery.
        </p>

      </div>

      <div class="overlay" style="display:none">
        <i class="fa fa-refresh fa-spin"></i>
      </div>

    </div>
  </section>
</div>
