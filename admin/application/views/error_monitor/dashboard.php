<style>
    .error-stats { display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap; }
    .stat-card { background: #fff; border-radius: 8px; padding: 15px 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); min-width: 150px; text-align: center; border-top: 3px solid #ddd; }
    .stat-card.critical { border-top-color: #e74c3c; }
    .stat-card.high { border-top-color: #e67e22; }
    .stat-card.medium { border-top-color: #f1c40f; }
    .stat-card.low { border-top-color: #27ae60; }
    .stat-card .stat-value { font-size: 28px; font-weight: 700; color: #333; }
    .stat-card .stat-label { font-size: 12px; color: #888; text-transform: uppercase; margin-top: 4px; }
    .severity-critical { color: #e74c3c; font-weight: 700; }
    .severity-high { color: #e67e22; font-weight: 600; }
    .severity-medium { color: #f1c40f; font-weight: 500; }
    .severity-low { color: #27ae60; }
    .error-type-badge { padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; color: #fff; }
    .badge-php_fatal { background: #e74c3c; }
    .badge-php_warning { background: #e67e22; }
    .badge-php_exception { background: #9b59b6; }
    .badge-js_error { background: #3498db; }
    .badge-js_ajax { background: #1abc9c; }
    .badge-db_error { background: #c0392b; }
    .error-message { max-width: 400px; word-break: break-word; font-family: monospace; font-size: 12px; }
    .error-file { color: #666; font-family: monospace; font-size: 12px; }
    .occ-count { font-weight: 700; color: #e74c3c; }
    .filter-bar { display: flex; gap: 10px; margin-bottom: 15px; align-items: center; flex-wrap: wrap; }
    .filter-bar select, .filter-bar input { padding: 6px 10px; border: 1px solid #ddd; border-radius: 4px; }
    .btn-resolve { background: #27ae60; color: #fff; border: none; padding: 4px 10px; border-radius: 4px; cursor: pointer; font-size: 11px; }
    .btn-ack { background: #f39c12; color: #fff; border: none; padding: 4px 10px; border-radius: 4px; cursor: pointer; font-size: 11px; }
    .btn-resolve:hover { background: #219a52; }
    .btn-ack:hover { background: #d68910; }
</style>

<div class="content-wrapper">
    <section class="content-header">
        <h1>🔴 Error Monitor <small>Live error tracking</small></h1>
    </section>

    <section class="content">
        <!-- Stats Row -->
        <div class="error-stats" id="statsRow">
            <div class="stat-card critical">
                <div class="stat-value" id="statCritical">-</div>
                <div class="stat-label">Critical</div>
            </div>
            <div class="stat-card high">
                <div class="stat-value" id="statHigh">-</div>
                <div class="stat-label">High</div>
            </div>
            <div class="stat-card medium">
                <div class="stat-value" id="statMedium">-</div>
                <div class="stat-label">Medium</div>
            </div>
            <div class="stat-card low">
                <div class="stat-value" id="statLow">-</div>
                <div class="stat-label">Low</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="statTotal">-</div>
                <div class="stat-label">Total Unresolved</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="statOccurrences">-</div>
                <div class="stat-label">Occurrences (24h)</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Unresolved Errors</h3>
                <div class="box-tools pull-right">
                    <button class="btn btn-sm btn-warning" onclick="autoResolve()">🗑️ Auto-resolve 30d+</button>
                    <button class="btn btn-sm btn-default" onclick="refreshAll()">🔄 Refresh</button>
                </div>
            </div>
            <div class="box-body">
                <div class="filter-bar">
                    <select id="filterType">
                        <option value="">All Types</option>
                        <option value="php_fatal">PHP Fatal</option>
                        <option value="php_warning">PHP Warning</option>
                        <option value="php_exception">PHP Exception</option>
                        <option value="js_error">JS Error</option>
                        <option value="js_ajax">AJAX Failure</option>
                        <option value="db_error">DB Error</option>
                    </select>
                    <select id="filterSeverity">
                        <option value="">All Severities</option>
                        <option value="critical">Critical</option>
                        <option value="high">High</option>
                        <option value="medium">Medium</option>
                        <option value="low">Low</option>
                    </select>
                    <select id="filterHours">
                        <option value="1">Last 1 hour</option>
                        <option value="6">Last 6 hours</option>
                        <option value="24" selected>Last 24 hours</option>
                        <option value="72">Last 3 days</option>
                        <option value="168">Last 7 days</option>
                        <option value="720">Last 30 days</option>
                    </select>
                    <input type="text" id="filterSearch" placeholder="Search errors..." style="width: 200px;">
                </div>

                <table id="errorTable" class="table table-striped table-bordered table-condensed" width="100%">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Severity</th>
                            <th>Message</th>
                            <th>File</th>
                            <th>Controller</th>
                            <th>Count</th>
                            <th>Last Seen</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>

        <!-- Top Offenders -->
        <div class="box box-danger">
            <div class="box-header with-border">
                <h3 class="box-title">🔥 Top Offenders (most occurrences)</h3>
            </div>
            <div class="box-body">
                <table class="table table-striped table-condensed" id="topErrorsTable">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Severity</th>
                            <th>Message</th>
                            <th>File:Line</th>
                            <th>Controller</th>
                            <th>Occurrences</th>
                        </tr>
                    </thead>
                    <tbody id="topErrorsBody"></tbody>
                </table>
            </div>
        </div>
    </section>
</div>

<!-- Error Detail Modal -->
<div class="modal fade" id="errorDetailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Error Details</h4>
            </div>
            <div class="modal-body" id="errorDetailBody" style="font-family: monospace; font-size: 12px; white-space: pre-wrap;"></div>
            <div class="modal-footer">
                <textarea id="resolveNotes" class="form-control" placeholder="Resolution notes (optional)" rows="2" style="margin-bottom:10px;"></textarea>
                <button type="button" class="btn btn-success" id="btnResolveModal">✅ Resolve</button>
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize DataTable
    var dt = $("#errorTable").DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: base_url + "error_monitor/ajax_errors",
            data: function(d) {
                d.type = $("#filterType").val();
                d.severity = $("#filterSeverity").val();
                d.hours = $("#filterHours").val();
                d.search = $("#filterSearch").val();
                d.resolved = 0;
            },
            error: function(xhr) {
                console.error("Error loading error data:", xhr.responseText);
            }
        },
        columns: [
            { data: "error_type", render: function(d) { return '<span class="error-type-badge badge-' + d + '">' + d.replace("_", " ") + '</span>'; }},
            { data: "severity", render: function(d) { return '<span class="severity-' + d + '">' + d + '</span>'; }},
            { data: "message", render: function(d) { return '<div class="error-message" title="' + $("<div>").text(d).html() + '">' + $("<div>").text(d || "").html().substring(0, 120) + '</div>'; }},
            { data: "file", render: function(d, t, r) { return '<span class="error-file">' + (d ? d.split("/").pop() : "") + (r.line ? ":" + r.line : "") + '</span>'; }},
            { data: "controller", render: function(d, t, r) { return (d || "") + (r.method ? "/" + r.method : ""); }},
            { data: "occurrence_count", render: function(d) { return '<span class="occ-count">' + d + 'x</span>'; }},
            { data: "last_seen" },
            { data: "id", orderable: false, render: function(d) {
                return '<button class="btn-resolve" onclick="resolveError(' + d + ')">✅</button> '
                     + '<button class="btn-ack" onclick="viewError(' + d + ')">👁️</button>';
            }}
        ],
        order: [[6, "desc"]],
        pageLength: 25,
        language: { emptyTable: "No unresolved errors — system is clean! 🎉" }
    });

    // Filter changes trigger reload
    $("#filterType, #filterSeverity, #filterHours").on("change", function() { dt.ajax.reload(); });
    
    var searchTimer;
    $("#filterSearch").on("keyup", function() {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function() { dt.ajax.reload(); }, 400);
    });

    // Load stats
    loadStats();
});

function loadStats() {
    $.get(base_url + "error_monitor/ajax_stats", { hours: 24 }, function(res) {
        if (res.status && res.data) {
            var d = res.data;
            $("#statTotal").text(d.total_unresolved || 0);
            $("#statOccurrences").text(d.total_occurrences || 0);
            $("#statCritical").text((d.by_severity && d.by_severity.critical) || 0);
            $("#statHigh").text((d.by_severity && d.by_severity.high) || 0);
            $("#statMedium").text((d.by_severity && d.by_severity.medium) || 0);
            $("#statLow").text((d.by_severity && d.by_severity.low) || 0);

            // Top offenders
            var tbody = "";
            if (d.top_errors) {
                $.each(d.top_errors, function(i, e) {
                    tbody += '<tr>'
                        + '<td><span class="error-type-badge badge-' + e.error_type + '">' + e.error_type.replace("_", " ") + '</span></td>'
                        + '<td><span class="severity-' + e.severity + '">' + e.severity + '</span></td>'
                        + '<td class="error-message">' + $("<div>").text(e.message || "").html().substring(0, 100) + '</td>'
                        + '<td class="error-file">' + (e.file ? e.file.split("/").pop() : "") + ':' + (e.line || "") + '</td>'
                        + '<td>' + (e.controller || "") + '</td>'
                        + '<td><span class="occ-count">' + e.occurrence_count + 'x</span></td>'
                        + '</tr>';
                });
            }
            $("#topErrorsBody").html(tbody || '<tr><td colspan="6" class="text-center">No errors 🎉</td></tr>');
        }
    });
}

function resolveError(id) {
    if (!confirm("Mark this error as resolved?")) return;
    $.post(base_url + "error_monitor/resolve", { id: id }, function(res) {
        if (res.status) {
            $("#errorTable").DataTable().ajax.reload();
            loadStats();
        }
        alert(res.msg);
    }, "json");
}

function viewError(id) {
    // Load error detail and show modal
    $.get(base_url + "error_monitor/ajax_errors", { start: 0, length: 1, search: id }, function(res) {
        if (res.data && res.data.length > 0) {
            var e = res.data[0];
            var html = "Type: " + e.error_type + "\nSeverity: " + e.severity
                + "\n\nMessage:\n" + e.message
                + "\n\nFile: " + (e.file || "N/A") + ":" + (e.line || "")
                + "\nController: " + (e.controller || "N/A") + "/" + (e.method || "")
                + "\nURL: " + (e.url || "N/A")
                + "\n\nStack Trace:\n" + (e.stack_trace || "N/A")
                + "\n\nUser ID: " + (e.user_id || "N/A")
                + "\nIP: " + (e.ip_address || "N/A")
                + "\nOccurrences: " + e.occurrence_count
                + "\nFirst seen: " + e.first_seen
                + "\nLast seen: " + e.last_seen;
            
            if (e.request_data) {
                html += "\n\nRequest Data:\n" + e.request_data;
            }
            
            $("#errorDetailBody").text(html);
            $("#btnResolveModal").off("click").on("click", function() {
                $.post(base_url + "error_monitor/resolve", { id: e.id, notes: $("#resolveNotes").val() }, function(res) {
                    if (res.status) {
                        $("#errorDetailModal").modal("hide");
                        $("#errorTable").DataTable().ajax.reload();
                        loadStats();
                    }
                    alert(res.msg);
                }, "json");
            });
            
            $("#errorDetailModal").modal("show");
        }
    }, "json");
}

function autoResolve() {
    if (!confirm("Auto-resolve all errors with no occurrence in the last 30 days?")) return;
    $.post(base_url + "error_monitor/auto_resolve", { days: 30 }, function(res) {
        alert(res.msg);
        if (res.status) {
            $("#errorTable").DataTable().ajax.reload();
            loadStats();
        }
    }, "json");
}

function refreshAll() {
    $("#errorTable").DataTable().ajax.reload();
    loadStats();
}
</script>
