<?php if (!defined('BASEPATH')) exit('No direct script access allowed'); ?>
<!-- Digi Silver - Branch Wise Closing report (renderer is shared from the Digi Gold tab) -->
<div class="row">
    <div class="col-md-12">
        <div class="box box-solid dg-box">
            <div class="box-header with-border">
                <h3 class="box-title" style="font-weight:600;"><i class="fa fa-money"></i> Digi Silver &mdash; Branch Wise Closing</h3>
                <div class="box-tools pull-right" style="margin-top:-4px;">
                    <label style="font-weight:500;margin-right:6px;">Date</label>
                    <input type="date" id="digi_silver_date" class="form-control input-sm digi-closing-date"
                        data-metal="silver" data-target="digi_silver_report" value="<?php echo date('Y-m-d'); ?>"
                        style="display:inline-block;width:170px;" />
                </div>
            </div><!-- /.box-header -->
            <div class="box-body table-responsive" id="digi_silver_report">
                <p class="text-muted">Loading&hellip;</p>
            </div><!-- /.box-body -->
        </div><!-- /.box -->
    </div><!-- /.col -->
</div><!-- /.row -->

<!-- Branch Wise: Paid vs Benefit (Amount) chart -->
<div class="row">
    <div class="col-md-12">
        <div class="box box-solid dg-box">
            <div class="box-header with-border">
                <h3 class="box-title" style="font-weight:600;"><i class="fa fa-bar-chart"></i> Digi Silver &mdash; Branch Wise: Paid vs Benefit (Amount)</h3>
            </div><!-- /.box-header -->
            <div class="box-body">
                <div class="dg-chart-wrap" style="position:relative;height:380px;">
                    <canvas id="digi_silver_chart"></canvas>
                </div>
            </div><!-- /.box-body -->
        </div><!-- /.box -->
    </div><!-- /.col -->
</div><!-- /.row -->

<!-- Paid vs Benefit share (Amount / Weight) pie charts -->
<div class="row">
    <div class="col-md-6">
        <div class="box box-solid dg-box">
            <div class="box-body">
                <div class="dg-chart-wrap" style="position:relative;height:340px;">
                    <canvas id="digi_silver_pie_amt"></canvas>
                </div>
            </div><!-- /.box-body -->
        </div><!-- /.box -->
    </div><!-- /.col -->
    <div class="col-md-6">
        <div class="box box-solid dg-box">
            <div class="box-body">
                <div class="dg-chart-wrap" style="position:relative;height:340px;">
                    <canvas id="digi_silver_pie_wt"></canvas>
                </div>
            </div><!-- /.box-body -->
        </div><!-- /.box -->
    </div><!-- /.col -->
</div><!-- /.row -->

<script type="text/javascript">
(function () {
    // window.renderDigiClosing is defined by the Digi Gold tab (dashboard/digi_gold.php),
    // which waits for jQuery too. Poll for it (vanilla setTimeout, no jQuery dependency).
    var tries = 0;
    (function init() {
        if (typeof window.renderDigiClosing === 'function') {
            window.renderDigiClosing('silver', 'digi_silver_report');
        } else if (tries++ < 100) {
            setTimeout(init, 100);
        } else if (window.jQuery) {
            window.jQuery('#digi_silver_report').html('<div class="alert alert-danger" style="margin:0;">Report renderer failed to load.</div>');
        }
    })();
})();
</script>
