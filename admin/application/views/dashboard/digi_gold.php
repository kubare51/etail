<?php if (!defined('BASEPATH')) exit('No direct script access allowed'); ?>
<!-- Digi Gold - Branch Wise Closing report (table first, charts below) -->
<div class="row">
    <div class="col-md-12">
        <div class="box box-solid dg-box">
            <div class="box-header with-border">
                <h3 class="box-title" style="font-weight:600;"><i class="fa fa-money"></i> Digi Gold &mdash; Branch Wise Closing</h3>
                <div class="box-tools pull-right" style="margin-top:-4px;">
                    <label style="font-weight:500;margin-right:6px;">Date</label>
                    <input type="date" id="digi_gold_date" class="form-control input-sm digi-closing-date"
                        data-metal="gold" data-target="digi_gold_report" value="<?php echo date('Y-m-d'); ?>"
                        style="display:inline-block;width:170px;" />
                </div>
            </div><!-- /.box-header -->
            <div class="box-body table-responsive" id="digi_gold_report">
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
                <h3 class="box-title" style="font-weight:600;"><i class="fa fa-bar-chart"></i> Digi Gold &mdash; Branch Wise: Paid vs Benefit (Amount)</h3>
            </div><!-- /.box-header -->
            <div class="box-body">
                <div class="dg-chart-wrap" style="position:relative;height:380px;">
                    <canvas id="digi_gold_chart"></canvas>
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
                    <canvas id="digi_gold_pie_amt"></canvas>
                </div>
            </div><!-- /.box-body -->
        </div><!-- /.box -->
    </div><!-- /.col -->
    <div class="col-md-6">
        <div class="box box-solid dg-box">
            <div class="box-body">
                <div class="dg-chart-wrap" style="position:relative;height:340px;">
                    <canvas id="digi_gold_pie_wt"></canvas>
                </div>
            </div><!-- /.box-body -->
        </div><!-- /.box -->
    </div><!-- /.col -->
</div><!-- /.row -->

<script type="text/javascript">
(function () {
    var BASE_URL = "<?php echo base_url(); ?>";

    // This template loads jQuery near the end of the page, so wait for it
    // (vanilla setTimeout is always available) before wiring anything up.
    function digiBoot() {
        if (!window.jQuery) { setTimeout(digiBoot, 100); return; }
        var jQuery = window.jQuery;

    // Shared renderer - defined once, reused by both Digi Gold and Digi Silver tabs
    if (typeof window.renderDigiClosing !== 'function') {

        window.digiFmtWt = function (v) {
            v = Number(v) || 0;
            if (Math.abs(v) < 0.0005) return '-';
            return v.toLocaleString('en-IN', { minimumFractionDigits: 3, maximumFractionDigits: 3 }) + ' g';
        };
        window.digiFmtAmt = function (v) {
            v = Number(v) || 0;
            return '(Rs. ' + v.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ')';
        };

        // Build one PAID/BENEFIT section (5 cells: Opening, Today, Inward, Outward, Closing)
        window.digiSectionCells = function (sec, isTotal) {
            var keys = [['o_wt', 'o_amt'], ['t_wt', 't_amt'], ['in_wt', 'in_amt'], ['out_wt', 'out_amt'], ['c_wt', 'c_amt']];
            var html = '';
            for (var i = 0; i < keys.length; i++) {
                var wt = sec[keys[i][0]], amt = sec[keys[i][1]];
                var cls = 'dg-num' + (i === 4 ? ' dg-close' : '') + (isTotal ? ' dg-total-num' : '');
                html += '<td class="' + cls + '">'
                    + '<div class="dg-wt">' + window.digiFmtWt(wt) + '</div>'
                    + '<div class="dg-amt">' + window.digiFmtAmt(amt) + '</div>'
                    + '</td>';
            }
            return html;
        };

        // Grouped bar chart: per-branch PAID vs BENEFIT closing amount.
        // Canvas id is derived from the table target: <target>_report -> <metal>_chart.
        window.digiRenderChart = function (metal, targetId, data) {
            var chartId = (metal === 'silver') ? 'digi_silver_chart' : 'digi_gold_chart';
            var canvas = document.getElementById(chartId);
            if (!canvas || typeof window.Chart !== 'function') { return; }

            var labels = [], paid = [], ben = [];
            var branches = (data && data.branches) ? data.branches : [];
            for (var i = 0; i < branches.length; i++) {
                labels.push(branches[i].name);
                paid.push(Number(branches[i].paid.c_amt) || 0);
                ben.push(Number(branches[i].ben.c_amt) || 0);
            }

            window.digiCharts = window.digiCharts || {};
            if (window.digiCharts[chartId]) { window.digiCharts[chartId].destroy(); }

            var metalLabel = (metal === 'silver') ? 'Silver' : 'Gold';
            window.digiCharts[chartId] = new window.Chart(canvas.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        { label: 'Paid (Customer Actual Payment)', backgroundColor: '#d9b13b', hoverBackgroundColor: '#c99f2c', data: paid, maxBarThickness: 46 },
                        { label: 'Benefit (Slab Bonus)', backgroundColor: '#6e3b16', hoverBackgroundColor: '#5a2f10', data: ben, maxBarThickness: 46 }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    title: { display: true, text: 'Digi ' + metalLabel + ' — Branch Wise: Paid vs Benefit (Amount)', fontSize: 15, fontColor: '#2d3748' },
                    legend: { position: 'bottom' },
                    tooltips: {
                        callbacks: {
                            label: function (ti, d) {
                                var v = d.datasets[ti.datasetIndex].data[ti.index];
                                return d.datasets[ti.datasetIndex].label + ': ' + window.digiFmtAmt(v);
                            }
                        }
                    },
                    scales: {
                        xAxes: [{ scaleLabel: { display: true, labelString: 'Branch' }, gridLines: { display: false } }],
                        yAxes: [{
                            scaleLabel: { display: true, labelString: 'Amount (₹)' },
                            ticks: { beginAtZero: true, callback: function (v) { return window.digiFmtAmt(v); } }
                        }]
                    }
                }
            });
        };

        // Draws the percentage on each pie slice (Chart.js 2.8.0 has no built-in
        // datalabels). Dark text on the gold slice, white on the brown slice.
        window.digiPiePctPlugin = {
            afterDatasetsDraw: function (chart) {
                var ds = chart.data.datasets[0];
                var total = ds.data.reduce(function (a, b) { return a + (Number(b) || 0); }, 0);
                if (total <= 0) { return; }
                var ctx = chart.ctx;
                ctx.save();
                ctx.font = 'bold 12px Inter, -apple-system, "Segoe UI", sans-serif';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                chart.getDatasetMeta(0).data.forEach(function (arc, i) {
                    var val = Number(ds.data[i]) || 0;
                    if (val <= 0) { return; }
                    var pct = val / total * 100;
                    var txt = (Math.abs(pct - Math.round(pct)) < 0.05 ? pct.toFixed(0) : pct.toFixed(1)) + '%';
                    var pos = arc.tooltipPosition();
                    ctx.fillStyle = (i === 0) ? '#3d2b00' : '#ffffff';
                    ctx.fillText(txt, pos.x, pos.y);
                });
                ctx.restore();
            }
        };

        // Two pies from the report totals: Paid vs Benefit share, by Amount and by Weight.
        window.digiRenderPie = function (metal, data) {
            var totals = (data && data.totals) ? data.totals : null;
            var metalLabel = (metal === 'silver') ? 'Silver' : 'Gold';
            var pre = (metal === 'silver') ? 'digi_silver_pie_' : 'digi_gold_pie_';
            var specs = [
                { id: pre + 'amt', title: 'Digi ' + metalLabel + ' — Paid vs Benefit (Amount)',
                  paid: totals ? Number(totals.paid.c_amt) || 0 : 0, ben: totals ? Number(totals.ben.c_amt) || 0 : 0, fmt: window.digiFmtAmt },
                { id: pre + 'wt', title: 'Digi ' + metalLabel + ' — Paid vs Benefit (Weight)',
                  paid: totals ? Number(totals.paid.c_wt) || 0 : 0, ben: totals ? Number(totals.ben.c_wt) || 0 : 0, fmt: window.digiFmtWt }
            ];

            window.digiCharts = window.digiCharts || {};
            for (var s = 0; s < specs.length; s++) {
                (function (spec) {
                    var canvas = document.getElementById(spec.id);
                    if (!canvas || typeof window.Chart !== 'function') { return; }
                    if (window.digiCharts[spec.id]) { window.digiCharts[spec.id].destroy(); }
                    window.digiCharts[spec.id] = new window.Chart(canvas.getContext('2d'), {
                        type: 'pie',
                        data: {
                            labels: ['Paid', 'Benefit'],
                            datasets: [{
                                data: [spec.paid, spec.ben],
                                backgroundColor: ['#d9b13b', '#6e3b16'],
                                hoverBackgroundColor: ['#c99f2c', '#5a2f10'],
                                borderColor: '#ffffff', borderWidth: 1
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            title: { display: true, text: spec.title, fontSize: 15, fontColor: '#2d3748' },
                            legend: { position: 'bottom' },
                            tooltips: {
                                callbacks: {
                                    label: function (ti, d) {
                                        var val = Number(d.datasets[0].data[ti.index]) || 0;
                                        var tot = d.datasets[0].data.reduce(function (a, b) { return a + (Number(b) || 0); }, 0);
                                        var pct = tot > 0 ? (val / tot * 100).toFixed(1) + '%' : '0%';
                                        return d.labels[ti.index] + ': ' + spec.fmt(val) + ' (' + pct + ')';
                                    }
                                }
                            }
                        },
                        plugins: [window.digiPiePctPlugin]
                    });
                })(specs[s]);
            }
        };

        window.renderDigiClosing = function (metal, targetId) {
            var $box = jQuery('#' + targetId);
            var dateVal = jQuery('.digi-closing-date[data-target="' + targetId + '"]').val() || '';
            $box.html('<p class="text-muted">Loading&hellip;</p>');

            jQuery.ajax({
                url: BASE_URL + 'index.php/dashboard/digi_branch_closing/' + metal + '?date=' + encodeURIComponent(dateVal) + '&_=' + new Date().getTime(),
                type: 'GET',
                dataType: 'json',
                success: function (data) {
                    if (!data || data.has_scheme === false) {
                        $box.html('<div class="alert alert-warning" style="margin:0;">No active Digi '
                            + (metal === 'silver' ? 'Silver' : 'Gold') + ' scheme found.</div>');
                        window.digiRenderChart(metal, targetId, { branches: [] });
                        window.digiRenderPie(metal, { totals: null });
                        return;
                    }

                    var t = '<table class="table dg-table">';
                    t += '<thead>';
                    // Group header row: BRANCH | PAID | BENEFIT
                    t += '<tr>'
                        + '<th rowspan="2" class="dg-branch-h">BRANCH</th>'
                        + '<th colspan="5" class="dg-group-h">PAID (Customer Actual Payment)</th>'
                        + '<th colspan="5" class="dg-group-h">BENEFIT (Slab Bonus, Weight-Converted)</th>'
                        + '</tr>';
                    // Coloured sub-header row (5 columns, repeated for PAID and BENEFIT)
                    var subCols = [['Opening', 'bg-purple'], ['Today', 'bg-green'], ['Inward', 'bg-aqua'], ['Outward', 'bg-orange'], ['Closing', 'bg-blue']];
                    var sub = '';
                    for (var s = 0; s < subCols.length; s++) {
                        sub += '<th class="dg-sub-h ' + subCols[s][1] + '">' + subCols[s][0] + '</th>';
                    }
                    t += '<tr>' + sub + sub + '</tr>';
                    t += '</thead><tbody>';

                    if (!data.branches || data.branches.length === 0) {
                        t += '<tr><td colspan="11" class="text-center text-muted">No data for this date.</td></tr>';
                    } else {
                        for (var b = 0; b < data.branches.length; b++) {
                            var row = data.branches[b];
                            t += '<tr><td class="dg-branch">' + row.name + '</td>'
                                + window.digiSectionCells(row.paid, false)
                                + window.digiSectionCells(row.ben, false)
                                + '</tr>';
                        }
                    }

                    // Total row
                    t += '<tr class="dg-total"><td class="dg-branch">Total</td>'
                        + window.digiSectionCells(data.totals.paid, true)
                        + window.digiSectionCells(data.totals.ben, true)
                        + '</tr>';
                    t += '</tbody></table>';

                    $box.html(t);
                    window.digiRenderChart(metal, targetId, data);
                    window.digiRenderPie(metal, data);
                },
                error: function () {
                    $box.html('<div class="alert alert-danger" style="margin:0;">Unable to load report.</div>');
                }
            });
        };

        // Reload when a date picker changes (bound once, delegated)
        jQuery(document).on('change', '.digi-closing-date', function () {
            var metal = jQuery(this).data('metal');
            var target = jQuery(this).data('target');
            window.renderDigiClosing(metal, target);
        });

        // Charts drawn inside an inactive Bootstrap tab-pane size to 0 width;
        // resize them once their tab becomes visible.
        jQuery(document).on('shown.bs.tab', 'a[data-toggle="tab"]', function () {
            if (!window.digiCharts) { return; }
            for (var k in window.digiCharts) {
                if (window.digiCharts[k]) { window.digiCharts[k].resize(); }
            }
        });
    }

        // Initialise this (Digi Gold) tab
        window.renderDigiClosing('gold', 'digi_gold_report');
    }
    digiBoot();
})();
</script>

<style type="text/css">
/* Digi Gold / Silver - Branch Wise Closing (styled to match the CRM Collection table) */
.dg-box { border-radius: 8px; box-shadow: 0 1px 4px rgba(0,0,0,.08); }
.dg-box > .box-header .box-title { font-weight: 600; }

/* Match the CRM Collection table typography (Inter, imported by crm_dashboard.css) */
.dg-table { width: 100%; margin-bottom: 8px; border-collapse: separate; border-spacing: 0;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
.dg-table thead th {
    color: #fff; text-align: center; vertical-align: middle;
    font-weight: 600; font-size: 12px; letter-spacing: .4px; text-transform: uppercase;
    padding: 9px 10px; border: 1px solid rgba(255,255,255,.30) !important;
}
/* BRANCH + PAID/BENEFIT group headers: plain white, black text, gray border */
.dg-table thead .dg-branch-h { background: #fff !important; color: #000 !important; text-align: left; border: 1px solid #f4f4f4 !important; }
.dg-table thead .dg-group-h  { background: #fff !important; color: #000 !important; border: 1px solid #f4f4f4 !important; }
/* sub-header colours come from AdminLTE: bg-purple, bg-green, bg-aqua, bg-orange, bg-blue */
.dg-table thead .dg-sub-h { font-size: 12px; }

.dg-table tbody td {
    padding: 8px 10px; border: 1px solid #eef0f3; font-size: 13px; font-weight: 500;
    color: #4a5568; text-align: right; white-space: nowrap;
}
.dg-table tbody td.dg-branch { text-align: left; font-weight: 600; color: #2d3748; }
.dg-table tbody td.dg-close { background: #f2f6fd; }
.dg-wt  { font-weight: 600; }
.dg-amt { color: #8a8f98; font-size: 11px; font-style: italic; }

.dg-table tbody tr.dg-total td { background: #f7fafc !important; font-weight: 700; font-size: 13px; color: #2d3748; border-color: #ffffff !important; border-top: 2px solid #ffffff; }
.dg-table tbody tr.dg-total td.dg-branch { color: #2d3748; }
.dg-total-num .dg-wt  { color: #2d3748; font-weight: 700; }
.dg-total-num .dg-amt { color: #2d3748; font-style: normal; }
</style>
