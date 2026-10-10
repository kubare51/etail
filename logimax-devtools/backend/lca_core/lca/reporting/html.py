"""HTML Report Generator -- self-contained dark-theme health reports.

Generates a single HTML file with embedded CSS and Chart.js that shows:
  - Overall health score (0-100, A-F grade)
  - Sub-score breakdown (radar chart)
  - Findings by category (bar chart)
  - Top issues table
  - Trend chart (if history available)

No external dependencies -- Chart.js loaded from CDN, all CSS inline.

Ported from standalone lca_report.py into LCA v2 package.
"""

from __future__ import annotations

from pathlib import Path
from datetime import datetime

from lca.analysis.health import HealthReport


def generate_html_report(
    report: HealthReport,
    trend_data: list[dict] | None = None,
    output_path: Path | None = None,
) -> str:
    """Generate a self-contained HTML health report.

    Args:
        report: HealthReport from compute_health()
        trend_data: Optional list of historical report dicts
        output_path: If provided, write HTML to this file

    Returns:
        HTML string
    """
    # Prepare sub-score data for charts
    labels = [s.name for s in report.sub_scores]
    scores = [round(s.score, 1) for s in report.sub_scores]
    weighted = [round(s.score * s.weight, 1) for s in report.sub_scores]
    findings = [s.findings_count for s in report.sub_scores]

    # Grade color
    grade_colors = {
        "A": "#10b981", "B": "#22d3ee", "C": "#eab308",
        "D": "#f97316", "F": "#ef4444", "?": "#6b7280",
    }
    grade_color = grade_colors.get(report.grade, "#6b7280")

    # Trend data
    trend_labels = "[]"
    trend_scores = "[]"
    if trend_data and len(trend_data) > 1:
        trend_labels = str([d.get("timestamp", "")[:10] for d in trend_data[-20:]])
        trend_scores = str([d.get("score", 0) for d in trend_data[-20:]])

    html = f"""<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LCA Health Report - {report.target}</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
    <style>
        * {{ margin: 0; padding: 0; box-sizing: border-box; }}
        body {{
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: #0f172a;
            color: #e2e8f0;
            line-height: 1.6;
            padding: 2rem;
        }}
        .container {{ max-width: 1200px; margin: 0 auto; }}

        /* Header */
        .header {{
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #1e293b;
        }}
        .header h1 {{ font-size: 1.5rem; color: #f1f5f9; }}
        .header .meta {{ color: #64748b; font-size: 0.85rem; }}

        /* Score Hero */
        .score-hero {{
            display: flex;
            align-items: center;
            gap: 2rem;
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            border: 1px solid #334155;
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
        }}
        .score-circle {{
            width: 140px;
            height: 140px;
            border-radius: 50%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            border: 4px solid {grade_color};
            box-shadow: 0 0 30px {grade_color}33;
            flex-shrink: 0;
        }}
        .score-circle .score {{ font-size: 2.5rem; font-weight: 800; color: {grade_color}; }}
        .score-circle .grade {{ font-size: 1rem; color: #94a3b8; }}
        .score-details {{ flex: 1; }}
        .score-details h2 {{ font-size: 1.3rem; margin-bottom: 0.5rem; }}

        /* Cards */
        .grid {{ display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }}
        .card {{
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 12px;
            padding: 1.5rem;
        }}
        .card h3 {{
            font-size: 1rem;
            color: #94a3b8;
            margin-bottom: 1rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }}

        /* Sub-score bars */
        .sub-score {{
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 0.75rem;
        }}
        .sub-score .label {{
            width: 120px;
            font-size: 0.85rem;
            color: #cbd5e1;
            flex-shrink: 0;
        }}
        .sub-score .bar-bg {{
            flex: 1;
            height: 8px;
            background: #334155;
            border-radius: 4px;
            overflow: hidden;
        }}
        .sub-score .bar-fill {{
            height: 100%;
            border-radius: 4px;
            transition: width 0.5s ease;
        }}
        .sub-score .value {{
            width: 40px;
            text-align: right;
            font-size: 0.85rem;
            font-weight: 600;
        }}

        /* Table */
        table {{
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }}
        th {{
            text-align: left;
            padding: 0.6rem;
            border-bottom: 1px solid #334155;
            color: #94a3b8;
            font-weight: 500;
        }}
        td {{
            padding: 0.6rem;
            border-bottom: 1px solid #1e293b;
        }}
        tr:hover td {{ background: #1e293b; }}

        /* Chart container */
        .chart-container {{
            position: relative;
            height: 280px;
        }}

        /* Footer */
        .footer {{
            text-align: center;
            color: #475569;
            font-size: 0.75rem;
            padding-top: 2rem;
            border-top: 1px solid #1e293b;
            margin-top: 2rem;
        }}

        /* Color utility */
        .c-green {{ color: #10b981; }}
        .c-yellow {{ color: #eab308; }}
        .c-orange {{ color: #f97316; }}
        .c-red {{ color: #ef4444; }}
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>LCA Health Report</h1>
            <div class="meta">
                <div>Target: <strong>{report.target}</strong></div>
                <div>{report.timestamp[:19]} | {report.files_analyzed} files</div>
            </div>
        </div>

        <!-- Score Hero -->
        <div class="score-hero">
            <div class="score-circle">
                <span class="score">{report.score:.0f}</span>
                <span class="grade">Grade {report.grade}</span>
            </div>
            <div class="score-details">
                <h2>Overall Health Score</h2>
                <p style="color:#94a3b8;">
                    Weighted composite of {len(report.sub_scores)} analysis dimensions.
                    {'Score improved!' if trend_data and len(trend_data) > 1 and trend_data[-1].get('score', 0) > trend_data[-2].get('score', 0) else ''}
                </p>
            </div>
        </div>

        <!-- Sub-scores -->
        <div class="grid">
            <div class="card">
                <h3>Score Breakdown</h3>
                {"".join(_render_sub_score_bar(s) for s in report.sub_scores)}
            </div>
            <div class="card">
                <h3>Radar View</h3>
                <div class="chart-container">
                    <canvas id="radarChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Findings Table -->
        <div class="card" style="margin-bottom:2rem;">
            <h3>Findings Summary</h3>
            <table>
                <thead>
                    <tr>
                        <th>Category</th>
                        <th>Score</th>
                        <th>Weight</th>
                        <th>Weighted</th>
                        <th>Findings</th>
                        <th>Detail</th>
                    </tr>
                </thead>
                <tbody>
                    {"".join(_render_table_row(s) for s in report.sub_scores)}
                </tbody>
            </table>
        </div>

        <!-- Trend Chart -->
        {'<div class="card"><h3>Score Trend</h3><div class="chart-container"><canvas id="trendChart"></canvas></div></div>' if trend_data and len(trend_data) > 1 else ''}

        <div class="footer">
            Generated by Logimax Code Analyzer (LCA) v2 | {datetime.now().strftime('%Y-%m-%d %H:%M')}
        </div>
    </div>

    <script>
        // Radar Chart
        new Chart(document.getElementById('radarChart'), {{
            type: 'radar',
            data: {{
                labels: {labels},
                datasets: [{{
                    label: 'Score',
                    data: {scores},
                    borderColor: '#6366f1',
                    backgroundColor: 'rgba(99, 102, 241, 0.15)',
                    borderWidth: 2,
                    pointBackgroundColor: '#6366f1',
                }}],
            }},
            options: {{
                responsive: true,
                maintainAspectRatio: false,
                plugins: {{ legend: {{ display: false }} }},
                scales: {{
                    r: {{
                        beginAtZero: true,
                        max: 100,
                        ticks: {{ color: '#64748b', backdropColor: 'transparent' }},
                        grid: {{ color: '#334155' }},
                        pointLabels: {{ color: '#94a3b8', font: {{ size: 11 }} }},
                    }},
                }},
            }},
        }});

        // Trend Chart
        var trendCanvas = document.getElementById('trendChart');
        if (trendCanvas) {{
            new Chart(trendCanvas, {{
                type: 'line',
                data: {{
                    labels: {trend_labels},
                    datasets: [{{
                        label: 'Health Score',
                        data: {trend_scores},
                        borderColor: '#6366f1',
                        backgroundColor: 'rgba(99, 102, 241, 0.1)',
                        fill: true,
                        tension: 0.3,
                    }}],
                }},
                options: {{
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {{ legend: {{ display: false }} }},
                    scales: {{
                        y: {{ beginAtZero: true, max: 100, ticks: {{ color: '#64748b' }}, grid: {{ color: '#1e293b' }} }},
                        x: {{ ticks: {{ color: '#64748b' }}, grid: {{ display: false }} }},
                    }},
                }},
            }});
        }}
    </script>
</body>
</html>"""

    if output_path:
        output_path.parent.mkdir(parents=True, exist_ok=True)
        output_path.write_text(html, encoding='utf-8')

    return html


def _score_color(score: float) -> str:
    """Get color for a score value."""
    if score >= 80:
        return "#10b981"
    elif score >= 60:
        return "#eab308"
    elif score >= 40:
        return "#f97316"
    return "#ef4444"


def _render_sub_score_bar(s) -> str:
    """Render a single sub-score bar."""
    color = _score_color(s.score)
    return f"""
    <div class="sub-score">
        <span class="label">{s.name}</span>
        <div class="bar-bg">
            <div class="bar-fill" style="width:{s.score}%;background:{color};"></div>
        </div>
        <span class="value" style="color:{color};">{s.score:.0f}</span>
    </div>"""


def _render_table_row(s) -> str:
    """Render a findings table row."""
    color = _score_color(s.score)
    return f"""
    <tr>
        <td>{s.name}</td>
        <td style="color:{color};font-weight:600;">{s.score:.0f}</td>
        <td>{s.weight:.0%}</td>
        <td>{s.score * s.weight:.1f}</td>
        <td>{s.findings_count}</td>
        <td style="color:#94a3b8;font-size:0.8rem;">{s.detail}</td>
    </tr>"""
