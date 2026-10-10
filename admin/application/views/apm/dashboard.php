<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>APM Dashboard | LOGIMAX</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root {
    --bg: #0f1117; --surface: #1a1d27; --surface2: #232733;
    --border: #2a2e3a; --text: #e4e6eb; --text2: #8b8fa3;
    --accent: #6c5ce7; --green: #00b894; --red: #e74c3c;
    --orange: #f39c12; --blue: #0984e3; --cyan: #00cec9;
    --radius: 12px;
}
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family:'Inter',sans-serif; background:var(--bg); color:var(--text); min-height:100vh; }

/* Header */
.apm-header { padding:20px 32px; border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between; background:var(--surface); }
.apm-header h1 { font-size:20px; font-weight:700; }
.apm-header h1 span { color:var(--accent); }
.apm-header .controls { display:flex; gap:8px; align-items:center; }
.apm-header .controls select, .apm-header .controls button {
    background:var(--surface2); border:1px solid var(--border); color:var(--text);
    padding:8px 14px; border-radius:8px; font-size:13px; cursor:pointer;
}
.apm-header .controls button { background:var(--accent); border-color:var(--accent); font-weight:600; }
.apm-header .controls button:hover { opacity:.85; }
.live-dot { width:8px; height:8px; background:var(--green); border-radius:50%; display:inline-block; margin-right:6px; animation:pulse 2s infinite; }
@keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.3} }

/* Layout */
.apm-body { padding:24px 32px; max-width:1600px; margin:0 auto; }

/* Stats Row */
.stats-row { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:16px; margin-bottom:24px; }
.stat-card { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); padding:18px; }
.stat-card .label { font-size:11px; text-transform:uppercase; letter-spacing:.8px; color:var(--text2); margin-bottom:6px; }
.stat-card .value { font-size:28px; font-weight:700; }
.stat-card .sub { font-size:12px; color:var(--text2); margin-top:4px; }
.stat-card.green .value { color:var(--green); }
.stat-card.red .value { color:var(--red); }
.stat-card.orange .value { color:var(--orange); }
.stat-card.blue .value { color:var(--blue); }
.stat-card.cyan .value { color:var(--cyan); }
.stat-card.accent .value { color:var(--accent); }

/* Panels */
.panel-grid { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:20px; }
.panel-grid.full { grid-template-columns:1fr; }
.panel { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); overflow:hidden; }
.panel-head { padding:14px 18px; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center; }
.panel-head h3 { font-size:14px; font-weight:600; }
.panel-head .badge { font-size:11px; padding:3px 10px; border-radius:20px; font-weight:600; }
.badge-red { background:rgba(231,76,60,.15); color:var(--red); }
.badge-green { background:rgba(0,184,148,.15); color:var(--green); }
.badge-orange { background:rgba(243,156,18,.15); color:var(--orange); }
.badge-blue { background:rgba(9,132,227,.15); color:var(--blue); }
.panel-body { padding:16px 18px; }

/* Tables */
.apm-table { width:100%; border-collapse:collapse; font-size:13px; }
.apm-table th { text-align:left; padding:8px 10px; color:var(--text2); font-weight:500; font-size:11px; text-transform:uppercase; letter-spacing:.5px; border-bottom:1px solid var(--border); }
.apm-table td { padding:8px 10px; border-bottom:1px solid rgba(42,46,58,.5); }
.apm-table tr:last-child td { border-bottom:none; }
.apm-table tr:hover td { background:rgba(108,92,231,.05); }
.mono { font-family:'Courier New',monospace; font-size:12px; }
.text-red { color:var(--red); }
.text-green { color:var(--green); }
.text-orange { color:var(--orange); }
.text-muted { color:var(--text2); }

/* Bar chart */
.bar-row { display:flex; align-items:center; gap:10px; margin-bottom:8px; }
.bar-label { width:80px; font-size:12px; color:var(--text2); text-align:right; flex-shrink:0; }
.bar-track { flex:1; height:24px; background:var(--surface2); border-radius:6px; overflow:hidden; position:relative; }
.bar-fill { height:100%; border-radius:6px; transition:width .6s ease; display:flex; align-items:center; padding-left:8px; font-size:11px; font-weight:600; min-width:fit-content; }
.bar-fill.fast { background:linear-gradient(90deg,#00b894,#00cec9); }
.bar-fill.medium { background:linear-gradient(90deg,#f39c12,#fdcb6e); }
.bar-fill.slow { background:linear-gradient(90deg,#e74c3c,#fd79a8); }

/* Throughput chart (CSS-only) */
.chart-container { height:180px; display:flex; align-items:flex-end; gap:3px; padding-top:10px; }
.chart-bar { flex:1; border-radius:4px 4px 0 0; min-width:8px; position:relative; cursor:pointer; transition:opacity .2s; }
.chart-bar:hover { opacity:.8; }
.chart-bar .tooltip { display:none; position:absolute; bottom:100%; left:50%; transform:translateX(-50%); background:#000; color:#fff; padding:6px 10px; border-radius:6px; font-size:11px; white-space:nowrap; z-index:10; }
.chart-bar:hover .tooltip { display:block; }

/* SQL preview */
.sql-preview { max-width:400px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-size:11px; color:var(--text2); cursor:pointer; }
.sql-preview:hover { white-space:normal; word-break:break-all; color:var(--text); }

/* Loader */
.loader { text-align:center; padding:60px; }
.loader .spinner { width:40px; height:40px; border:3px solid var(--border); border-top-color:var(--accent); border-radius:50%; animation:spin 1s linear infinite; margin:0 auto 16px; }
@keyframes spin { to{transform:rotate(360deg)} }

/* Responsive */
@media(max-width:1024px) {
    .panel-grid { grid-template-columns:1fr; }
    .stats-row { grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); }
    .apm-body { padding:16px; }
}
</style>
</head>
<body>

<div class="apm-header">
    <h1><span class="live-dot"></span> <span>APM</span> Dashboard</h1>
    <div class="controls">
        <select id="rangeSelect">
            <option value="today" selected>Today</option>
            <option value="7d">Last 7 Days</option>
            <option value="30d">Last 30 Days</option>
        </select>
        <select id="moduleFilter"><option value="">All Modules</option></select>
        <button onclick="loadData()">↻ Refresh</button>
        <button onclick="exportCSV()" style="background:var(--surface2);border-color:var(--border)">⬇ CSV</button>
    </div>
</div>

<div class="apm-body">
    <div id="content">
        <div class="loader"><div class="spinner"></div><div style="color:var(--text2)">Loading APM data...</div></div>
    </div>
</div>

<script>
const API_URL = '<?= site_url("admin_apm/api") ?>';
let apmData = null;

async function loadData() {
    const range = document.getElementById('rangeSelect').value;
    const module = document.getElementById('moduleFilter').value;
    let url = `${API_URL}?range=${range}`;
    if (module) url += `&module=${encodeURIComponent(module)}`;
    
    document.getElementById('content').innerHTML = '<div class="loader"><div class="spinner"></div><div style="color:var(--text2)">Loading...</div></div>';
    
    try {
        const res = await fetch(url);
        apmData = await res.json();
        if (apmData.status === 'error') throw new Error(apmData.message);
        render(apmData);
    } catch(e) {
        document.getElementById('content').innerHTML = `<div style="color:var(--red);padding:40px;text-align:center">Error: ${e.message}</div>`;
    }
}

function fmt(n) { return n === null || n === undefined ? '0' : Number(n).toLocaleString(); }
function fmtMs(ms) {
    ms = Number(ms) || 0;
    if (ms < 1000) return ms + 'ms';
    return (ms/1000).toFixed(1) + 's';
}
function fmtKb(kb) {
    kb = Number(kb) || 0;
    if (kb < 1024) return kb + ' KB';
    return (kb/1024).toFixed(1) + ' MB';
}
function msColor(ms) {
    if (ms < 300) return 'text-green';
    if (ms < 1000) return 'text-orange';
    return 'text-red';
}

function render(d) {
    const o = d.overview;
    const total = Number(o.total_requests) || 0;
    const t = o.trend || {};
    
    // Trend badge helper: positive pct = up arrow, negative = down
    // invertColor: true means "up is bad" (response time, errors), false means "up is good" (requests)
    function trendBadge(pct, invertColor) {
        if (pct === null || pct === undefined) return '';
        const arrow = pct > 0 ? '↑' : pct < 0 ? '↓' : '→';
        const absPct = Math.abs(pct);
        let color;
        if (pct === 0) color = 'var(--text2)';
        else if (invertColor) color = pct > 0 ? 'var(--red)' : 'var(--green)';
        else color = pct > 0 ? 'var(--green)' : 'var(--red)';
        return `<span style="color:${color};font-size:11px;font-weight:600;margin-left:4px" title="vs previous period">${arrow}${absPct}%</span>`;
    }

    let html = '';
    
    // ── Stats Row ──
    html += `<div class="stats-row">
        <div class="stat-card blue"><div class="label">Total Requests</div><div class="value">${fmt(o.total_requests)}${trendBadge(t.request_change_pct, false)}</div><div class="sub">${fmt(o.unique_controllers)} controllers</div></div>
        <div class="stat-card ${Number(o.avg_response_ms) < 500 ? 'green' : 'orange'}"><div class="label">Avg Response</div><div class="value">${fmtMs(o.avg_response_ms)}${trendBadge(t.response_change_pct, true)}</div><div class="sub">P95: ${fmtMs(o.p95_response_ms)}</div></div>
        <div class="stat-card green"><div class="label">Uptime</div><div class="value">${o.uptime_pct}%</div><div class="sub">${fmt(o.server_error_count)} 5xx errors</div></div>
        <div class="stat-card ${Number(o.error_rate_pct) > 5 ? 'red' : 'green'}"><div class="label">Error Rate</div><div class="value">${o.error_rate_pct}%${trendBadge(t.error_change_pct, true)}</div><div class="sub">${fmt(o.error_count)} total errors</div></div>
        <div class="stat-card cyan"><div class="label">Avg Queries/Req</div><div class="value">${o.avg_queries}${trendBadge(t.query_change_pct, true)}</div><div class="sub">Avg DB time: ${fmtMs(o.avg_db_time_ms)}</div></div>
        <div class="stat-card accent"><div class="label">Avg Memory</div><div class="value">${fmtKb(o.avg_memory_kb)}</div><div class="sub">Peak: ${fmtKb(o.max_memory_kb)}</div></div>
        <div class="stat-card orange"><div class="label">Slow Requests</div><div class="value">${fmt(o.slow_requests)}</div><div class="sub">&gt;3s response time</div></div>
        <div class="stat-card blue"><div class="label">Active Users</div><div class="value">${fmt(o.unique_users)}</div><div class="sub">${fmt(o.ajax_requests)} AJAX calls</div></div>
    </div>`;
    
    // ── Trend Summary (if data exists) ──
    if (t.prev_requests > 0) {
        html += `<div style="font-size:12px;color:var(--text2);margin-bottom:16px;padding:0 4px">
            📊 Compared to previous period (${t.prev_from} → ${t.prev_to}) · ${fmt(t.prev_requests)} requests
        </div>`;
    }
    
    // ── Throughput Chart ──
    html += renderThroughput(d.throughput);
    
    // ── Response Distribution + Slow Endpoints ──
    html += '<div class="panel-grid">';
    html += renderDistribution(d.response_distribution);
    html += renderSlowEndpoints(d.slow_endpoints);
    html += '</div>';
    
    // ── Slow Queries + N+1 Detection ──
    html += '<div class="panel-grid">';
    html += renderSlowQueries(d.slow_queries);
    html += renderNPlusOne(d.n_plus_one);
    html += '</div>';

    // ── Errors ──
    html += '<div class="panel-grid">';
    html += renderErrors(d.errors);
    html += '</div>';
    
    // ── Memory Hogs + Top Users ──
    html += '<div class="panel-grid">';
    html += renderMemoryHogs(d.memory_hogs);
    html += renderTopUsers(d.top_users);
    html += '</div>';

    // ── Percentiles + Bloated Responses ──
    html += '<div class="panel-grid">';
    html += renderPercentiles(d.percentiles);
    html += renderBloatedResponses(d.bloated_responses);
    html += '</div>';
    
    document.getElementById('content').innerHTML = html;
    populateModuleFilter(d.slow_endpoints);
}

function renderThroughput(data) {
    if (!data || !data.length) return '';
    const maxReq = Math.max(...data.map(d => Number(d.requests)));
    let bars = '';
    data.forEach(d => {
        const h = maxReq > 0 ? Math.max(4, (d.requests / maxReq) * 160) : 4;
        const avgMs = Number(d.avg_ms) || 0;
        const color = avgMs < 300 ? '#00b894' : avgMs < 1000 ? '#f39c12' : '#e74c3c';
        bars += `<div class="chart-bar" style="height:${h}px;background:${color}">
            <div class="tooltip">${d.period}<br>${d.requests} req · ${fmtMs(d.avg_ms)} avg${Number(d.errors) > 0 ? ' · '+d.errors+' err' : ''}</div>
        </div>`;
    });
    return `<div class="panel" style="margin-bottom:20px">
        <div class="panel-head"><h3>📈 Request Throughput</h3><span class="badge badge-blue">${data.length} periods</span></div>
        <div class="panel-body"><div class="chart-container">${bars}</div>
        <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--text2);margin-top:6px"><span>${data[0]?.period}</span><span>${data[data.length-1]?.period}</span></div></div>
    </div>`;
}

function renderDistribution(dist) {
    if (!dist) return '<div class="panel"><div class="panel-body text-muted">No data</div></div>';
    const buckets = [
        { label:'<100ms', val:Number(dist.under_100ms)||0, cls:'fast' },
        { label:'100-300ms', val:Number(dist['100_300ms'])||0, cls:'fast' },
        { label:'300-500ms', val:Number(dist['300_500ms'])||0, cls:'medium' },
        { label:'0.5-1s', val:Number(dist['500_1000ms'])||0, cls:'medium' },
        { label:'1-3s', val:Number(dist['1_3s'])||0, cls:'slow' },
        { label:'>3s', val:Number(dist.over_3s)||0, cls:'slow' },
    ];
    const maxVal = Math.max(...buckets.map(b=>b.val), 1);
    let bars = '';
    buckets.forEach(b => {
        const pct = (b.val / maxVal * 100).toFixed(0);
        bars += `<div class="bar-row"><div class="bar-label">${b.label}</div>
            <div class="bar-track"><div class="bar-fill ${b.cls}" style="width:${Math.max(pct,2)}%">${b.val}</div></div></div>`;
    });
    return `<div class="panel"><div class="panel-head"><h3>⏱ Response Distribution</h3></div><div class="panel-body">${bars}</div></div>`;
}

function renderSlowEndpoints(data) {
    if (!data || !data.length) return '<div class="panel"><div class="panel-head"><h3>🐌 Slow Endpoints</h3></div><div class="panel-body text-muted">No data yet</div></div>';
    let rows = '';
    data.slice(0,12).forEach(d => {
        rows += `<tr onclick="drillDown('${d.controller}','${d.method}')" style="cursor:pointer" title="Click for drill-down">
            <td class="mono">${d.controller}</td><td class="mono">${d.method}</td>
            <td class="${msColor(d.avg_ms)}" style="font-weight:600">${fmtMs(d.avg_ms)}</td>
            <td class="text-muted">${fmtMs(d.max_ms)}</td>
            <td>${d.hits}</td><td>${d.avg_queries}</td>
        </tr>`;
    });
    return `<div class="panel"><div class="panel-head"><h3>🐌 Slow Endpoints</h3><span class="badge badge-orange">Top ${Math.min(data.length,12)}</span></div>
        <div class="panel-body" style="overflow-x:auto"><table class="apm-table"><thead><tr><th>Controller</th><th>Method</th><th>Avg</th><th>Max</th><th>Hits</th><th>Queries</th></tr></thead><tbody>${rows}</tbody></table></div></div>`;
}

function renderSlowQueries(data) {
    if (!data || !data.length) return '<div class="panel"><div class="panel-head"><h3>🔍 Slow Queries</h3></div><div class="panel-body text-muted">No queries &gt;100ms captured</div></div>';
    let rows = '';
    data.slice(0,10).forEach(d => {
        const sql = (d.slowest_query_sql||'').substring(0,200);
        rows += `<tr>
            <td class="text-red" style="font-weight:600">${fmtMs(d.slowest_query_ms)}</td>
            <td class="mono">${d.controller}/${d.method}</td>
            <td><div class="sql-preview" title="${(d.slowest_query_sql||'').replace(/"/g,'&quot;')}">${sql}</div></td>
        </tr>`;
    });
    return `<div class="panel"><div class="panel-head"><h3>🔍 Slow Queries</h3><span class="badge badge-red">${data.length} captured</span></div>
        <div class="panel-body" style="overflow-x:auto"><table class="apm-table"><thead><tr><th>Time</th><th>Endpoint</th><th>Query</th></tr></thead><tbody>${rows}</tbody></table></div></div>`;
}

function renderErrors(data) {
    if (!data) return '';
    const recent = data.recent || [];
    const byStatus = data.by_status || [];
    if (!recent.length && !byStatus.length) return '<div class="panel"><div class="panel-head"><h3>❌ Errors</h3></div><div class="panel-body text-green">✓ No errors in this period</div></div>';
    
    let statusBadges = byStatus.map(s => `<span class="badge ${Number(s.http_status)>=500?'badge-red':'badge-orange'}" style="margin-right:6px">${s.http_status}: ${s.count}</span>`).join('');
    let rows = '';
    recent.slice(0,8).forEach(d => {
        rows += `<tr>
            <td><span class="badge ${Number(d.http_status)>=500?'badge-red':'badge-orange'}">${d.http_status}</span></td>
            <td class="mono">${d.controller}/${d.method}</td>
            <td class="text-muted" style="font-size:11px">${d.request_time}</td>
        </tr>`;
    });
    return `<div class="panel"><div class="panel-head"><h3>❌ Errors</h3><div>${statusBadges}</div></div>
        <div class="panel-body"><table class="apm-table"><thead><tr><th>Status</th><th>Endpoint</th><th>Time</th></tr></thead><tbody>${rows}</tbody></table></div></div>`;
}

function renderMemoryHogs(data) {
    if (!data || !data.length) return '<div class="panel"><div class="panel-head"><h3>🧠 Memory Usage</h3></div><div class="panel-body text-muted">No data</div></div>';
    let rows = '';
    data.slice(0,10).forEach(d => {
        rows += `<tr><td class="mono">${d.controller}/${d.method}</td><td style="font-weight:600">${fmtKb(d.avg_kb)}</td><td class="text-orange">${fmtKb(d.max_kb)}</td><td>${d.hits}</td></tr>`;
    });
    return `<div class="panel"><div class="panel-head"><h3>🧠 Memory Usage</h3><span class="badge badge-blue">By endpoint</span></div>
        <div class="panel-body"><table class="apm-table"><thead><tr><th>Endpoint</th><th>Avg</th><th>Peak</th><th>Hits</th></tr></thead><tbody>${rows}</tbody></table></div></div>`;
}

function renderTopUsers(data) {
    if (!data || !data.length) return '<div class="panel"><div class="panel-head"><h3>👥 Top Users</h3></div><div class="panel-body text-muted">No data</div></div>';
    let rows = '';
    data.forEach(d => {
        const name = d.user_name || ('User #' + d.user_id);
        const code = d.emp_code && d.emp_code !== '-' ? `<span class="text-muted" style="font-size:11px"> (${d.emp_code})</span>` : '';
        rows += `<tr><td>${name}${code}</td><td style="font-weight:600">${fmt(d.requests)}</td><td>${fmtMs(d.avg_ms)}</td><td class="${msColor(d.max_ms)}">${fmtMs(d.max_ms)}</td></tr>`;
    });
    return `<div class="panel"><div class="panel-head"><h3>👥 Top Users</h3></div>
        <div class="panel-body"><table class="apm-table"><thead><tr><th>User</th><th>Requests</th><th>Avg</th><th>Slowest</th></tr></thead><tbody>${rows}</tbody></table></div></div>`;
}

function renderNPlusOne(data) {
    if (!data || !data.summary || Number(data.summary.total_n1_requests) === 0) {
        return `<div class="panel"><div class="panel-head"><h3>🔄 N+1 Query Detection</h3></div>
            <div class="panel-body text-green">✓ No N+1 patterns detected</div></div>`;
    }
    const s = data.summary;
    const offenders = data.offenders || [];
    const samples = data.samples || [];

    // Header stats
    let headerBadges = `<span class="badge badge-red">${fmt(s.total_n1_requests)} requests affected</span>`;

    // Offenders table
    let offRows = '';
    offenders.forEach(d => {
        offRows += `<tr>
            <td class="mono">${d.controller}</td><td class="mono">${d.method}</td>
            <td class="text-red" style="font-weight:600">${d.n1_occurrences}×</td>
            <td>${d.avg_repeats} avg</td>
            <td class="text-orange">${fmtMs(d.avg_wasted_ms)}</td>
            <td>${d.avg_total_queries} queries</td>
        </tr>`;
    });

    // Sample SQL
    let sampleRows = '';
    samples.slice(0, 5).forEach(d => {
        const sql = (d.n1_pattern_sql || '').substring(0, 200);
        sampleRows += `<tr>
            <td class="mono">${d.controller}/${d.method}</td>
            <td class="text-red" style="font-weight:600">${d.n1_query_count}×</td>
            <td class="text-orange">${fmtMs(d.n1_total_ms)}</td>
            <td><div class="sql-preview" title="${(d.n1_pattern_sql||'').replace(/"/g,'&quot;')}">${sql}</div></td>
        </tr>`;
    });

    let html = `<div class="panel"><div class="panel-head"><h3>🔄 N+1 Query Detection</h3>${headerBadges}</div>
        <div class="panel-body">
            <div style="display:flex;gap:20px;margin-bottom:14px;font-size:13px">
                <div><span class="text-muted">Avg repeats:</span> <strong>${s.avg_repeat_count}</strong></div>
                <div><span class="text-muted">Max repeats:</span> <strong class="text-red">${s.max_repeat_count}</strong></div>
                <div><span class="text-muted">Total wasted:</span> <strong class="text-orange">${fmtMs(s.total_wasted_ms)}</strong></div>
            </div>`;

    if (offRows) {
        html += `<div style="margin-bottom:14px"><div style="font-size:12px;font-weight:600;color:var(--text2);margin-bottom:6px">WORST OFFENDERS</div>
            <table class="apm-table"><thead><tr><th>Controller</th><th>Method</th><th>Occurrences</th><th>Repeats</th><th>Wasted</th><th>Total Queries</th></tr></thead>
            <tbody>${offRows}</tbody></table></div>`;
    }
    if (sampleRows) {
        html += `<div><div style="font-size:12px;font-weight:600;color:var(--text2);margin-bottom:6px">SAMPLE QUERIES</div>
            <table class="apm-table"><thead><tr><th>Endpoint</th><th>Repeats</th><th>Time</th><th>SQL Pattern</th></tr></thead>
            <tbody>${sampleRows}</tbody></table></div>`;
    }
    html += `</div></div>`;
    return html;
}

function populateModuleFilter(endpoints) {
    const sel = document.getElementById('moduleFilter');
    if (!endpoints || sel.options.length > 1) return;
    const controllers = [...new Set(endpoints.map(e=>e.controller))].sort();
    controllers.forEach(c => { const o = document.createElement('option'); o.value=c; o.textContent=c; sel.appendChild(o); });
}

// A8: Percentile breakdown
function renderPercentiles(data) {
    if (!data || data.total < 10) return '<div class="panel"><div class="panel-body text-muted">Not enough data for percentiles</div></div>';
    
    const max = Math.max(data.p99, 1);
    const pcts = [
        { label: 'P50', val: data.p50, desc: 'Median' },
        { label: 'P75', val: data.p75, desc: '75th percentile' },
        { label: 'P90', val: data.p90, desc: '90th percentile' },
        { label: 'P95', val: data.p95, desc: '95th percentile' },
        { label: 'P99', val: data.p99, desc: '99th percentile' },
    ];

    let bars = '';
    pcts.forEach(p => {
        const pct = Math.max(2, (p.val / max) * 100);
        const cls = p.val < 500 ? 'fast' : p.val < 2000 ? 'medium' : 'slow';
        bars += `<div class="bar-row">
            <div class="bar-label">${p.label}</div>
            <div class="bar-track"><div class="bar-fill ${cls}" style="width:${pct}%">${fmtMs(p.val)}</div></div>
        </div>`;
    });

    return `<div class="panel">
        <div class="panel-head"><h3>\u{1F4CA} Response Percentiles</h3><span class="badge badge-blue">${fmt(data.total)} requests</span></div>
        <div class="panel-body">${bars}</div>
    </div>`;
}

// A6: Bloated responses
function renderBloatedResponses(data) {
    if (!data || !data.length) return '<div class="panel"><div class="panel-body text-muted">No bloated responses (>50KB avg)</div></div>';
    
    let rows = '';
    data.forEach(r => {
        const avgKb = Number(r.avg_kb) || 0;
        const maxKb = Number(r.max_kb) || 0;
        const cls = avgKb > 500 ? 'text-red' : avgKb > 200 ? 'text-orange' : '';
        rows += `<tr>
            <td class="mono">${r.controller}/${r.method}</td>
            <td>${fmt(r.requests)}</td>
            <td class="${cls}">${avgKb} KB</td>
            <td class="${cls}">${maxKb} KB</td>
        </tr>`;
    });

    return `<div class="panel">
        <div class="panel-head"><h3>\u{1F4E6} Bloated Responses</h3><span class="badge badge-orange">${data.length} endpoints</span></div>
        <div class="panel-body"><table class="apm-table">
            <thead><tr><th>Endpoint</th><th>Requests</th><th>Avg Size</th><th>Max Size</th></tr></thead>
            <tbody>${rows}</tbody>
        </table></div>
    </div>`;
}

// A7: CSV export
function exportCSV() {
    const range = document.getElementById('rangeSelect').value;
    const module = document.getElementById('moduleFilter').value;
    let url = '<?= site_url("admin_apm/export_csv") ?>?range=' + range;
    if (module) url += '&module=' + encodeURIComponent(module);
    window.open(url, '_blank');
}

// A9: Endpoint drill-down (open in new tab as JSON for now)
function drillDown(controller, method) {
    const range = document.getElementById('rangeSelect').value;
    let url = '<?= site_url("admin_apm/endpoint_detail") ?>?controller=' + encodeURIComponent(controller);
    if (method) url += '&method=' + encodeURIComponent(method);
    url += '&range=' + range;
    window.open(url, '_blank');
}

document.getElementById('rangeSelect').addEventListener('change', loadData);
document.getElementById('moduleFilter').addEventListener('change', loadData);

// Auto-refresh every 60s
setInterval(loadData, 60000);

// Initial load
loadData();
</script>
</body>
</html>
