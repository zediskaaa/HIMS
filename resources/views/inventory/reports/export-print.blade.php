<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $report['meta']['report_title'] }} — {{ config('app.name', 'HIMS') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />
    <style>
        *, ::before, ::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #f8fafc;
            color: #0f172a;
            line-height: 1.4;
            padding: 24px;
            font-size: 12px;
        }

        .paper-container {
            max-width: 1080px;
            margin: 0 auto;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            padding: 36px 40px;
        }

        /* Floating Toolbar */
        .no-print-bar {
            position: sticky;
            top: 12px;
            z-index: 999;
            max-width: 1080px;
            margin: 0 auto 16px auto;
            background: #0f172a;
            color: #ffffff;
            border-radius: 8px;
            padding: 10px 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 4px 14px rgba(0,0,0,0.15);
        }
        .no-print-bar h1 {
            font-size: 13px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .btn-group { display: flex; gap: 8px; }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            font-size: 12px;
            font-weight: 500;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            border: none;
            transition: all 0.15s ease-in-out;
        }
        .btn-primary {
            background-color: #0284c7;
            color: #ffffff;
        }
        .btn-primary:hover { background-color: #0369a1; }
        .btn-secondary {
            background-color: #334155;
            color: #f1f5f9;
        }
        .btn-secondary:hover { background-color: #475569; }

        /* Institutional Header */
        .hospital-header {
            text-align: center;
            border-bottom: 2px solid #0f172a;
            padding-bottom: 14px;
            margin-bottom: 20px;
        }
        .hospital-header .republic {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #475569;
        }
        .hospital-header .agency {
            font-size: 11px;
            font-weight: 600;
            color: #334155;
            text-transform: uppercase;
        }
        .hospital-header .facility-name {
            font-size: 16px;
            font-weight: 700;
            color: #0f172a;
            margin: 2px 0;
            letter-spacing: -0.01em;
        }
        .hospital-header .facility-address {
            font-size: 10px;
            color: #64748b;
        }
        .hospital-header .system-name {
            margin-top: 6px;
            font-size: 11px;
            font-weight: 600;
            color: #0284c7;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        /* Report Metadata Title Block */
        .report-title-block {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 14px 18px;
            margin-bottom: 20px;
        }
        .report-title-block h2 {
            font-size: 15px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 4px;
        }
        .report-meta-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 6px 20px;
            font-size: 11px;
        }
        .meta-item { display: flex; gap: 6px; }
        .meta-label { font-weight: 600; color: #475569; }
        .meta-value { color: #0f172a; }

        /* Filter Badges */
        .filters-summary {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px dashed #cbd5e1;
        }
        .filter-pill {
            background: #e2e8f0;
            color: #334155;
            font-size: 10px;
            font-weight: 500;
            padding: 2px 8px;
            border-radius: 9999px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .filter-pill strong { color: #0f172a; font-weight: 600; }

        /* Summary Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 10px;
            margin-bottom: 22px;
        }
        .stat-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 10px 12px;
        }
        .stat-card .stat-label {
            font-size: 10px;
            color: #64748b;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .stat-card .stat-val {
            font-size: 15px;
            font-weight: 700;
            color: #0f172a;
            margin-top: 2px;
        }

        /* Table Styling */
        .section-header {
            margin: 24px 0 10px 0;
            padding-bottom: 6px;
            border-bottom: 1.5px solid #0284c7;
            display: flex;
            align-items: baseline;
            justify-content: space-between;
        }
        .section-header h3 {
            font-size: 13px;
            font-weight: 700;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        .section-header .section-count {
            font-size: 11px;
            color: #64748b;
        }

        table.report-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10.5px;
            margin-bottom: 18px;
        }
        table.report-table th,
        table.report-table td {
            padding: 5px 7px;
            border: 1px solid #cbd5e1;
            text-align: left;
            vertical-align: middle;
        }
        table.report-table th {
            background-color: #f1f5f9;
            color: #1e293b;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 9.5px;
            letter-spacing: 0.03em;
        }
        table.report-table tbody tr:nth-child(even) {
            background-color: #f8fafc;
        }
        table.report-table tfoot td {
            background-color: #f1f5f9;
            font-weight: 700;
            color: #0f172a;
            border-top: 2px solid #0f172a;
        }
        .text-right { text-align: right !important; }
        .text-center { text-align: center !important; }
        .nowrap { white-space: nowrap; }

        /* Badges inside table */
        .badge {
            display: inline-block;
            font-size: 9px;
            font-weight: 600;
            padding: 1px 6px;
            border-radius: 4px;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        .badge-success { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
        .badge-warning { background: #fef3c7; color: #b45309; border: 1px solid #fde68a; }
        .badge-danger { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }
        .badge-neutral { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }

        /* Empty State */
        .empty-state {
            padding: 24px;
            text-align: center;
            background: #f8fafc;
            border: 1px dashed #cbd5e1;
            border-radius: 6px;
            color: #64748b;
            font-style: italic;
            margin-bottom: 20px;
        }

        /* Sign-Off Block */
        .signoff-section {
            margin-top: 36px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
            page-break-inside: avoid;
        }
        .signoff-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 24px;
            margin-top: 28px;
        }
        .signoff-box {
            text-align: center;
        }
        .signoff-line {
            border-bottom: 1px solid #0f172a;
            margin-bottom: 6px;
            height: 36px;
        }
        .signoff-name {
            font-weight: 700;
            color: #0f172a;
            font-size: 11px;
            text-transform: uppercase;
        }
        .signoff-title {
            color: #475569;
            font-size: 10px;
        }

        /* Footer */
        .report-footer {
            margin-top: 28px;
            padding-top: 12px;
            border-top: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 9px;
            color: #94a3b8;
        }

        /* Print Media Styles */
        @media print {
            body {
                background: #ffffff !important;
                padding: 0 !important;
                color: #000000 !important;
            }
            .paper-container {
                max-width: 100% !important;
                border: none !important;
                box-shadow: none !important;
                padding: 0 !important;
                border-radius: 0 !important;
            }
            .no-print-bar { display: none !important; }
            table.report-table { font-size: 9.5px !important; }
            table.report-table th, table.report-table td { padding: 4px 6px !important; }
            .section-header { page-break-after: avoid; }
            tr { page-break-inside: avoid; }
            @page {
                size: letter portrait;
                margin: 12mm 10mm 12mm 10mm;
            }
        }
    </style>
</head>
<body>

    {{-- Floating Print Actions Bar (Hidden on Print) --}}
    <div class="no-print-bar">
        <h1>
            <span>📋</span>
            <span>HIMS Institutional Report Preview & Export</span>
        </h1>
        <div class="btn-group">
            <button type="button" class="btn btn-primary" onclick="window.print()">
                🖨️ Print / Save as PDF
            </button>
            <button type="button" class="btn btn-secondary" onclick="window.close()">
                ✕ Close
            </button>
        </div>
    </div>

    {{-- Document Paper Container --}}
    <div class="paper-container">

        {{-- Official Hospital Letterhead --}}
        <header class="hospital-header">
            <div class="republic">Republic of the Philippines</div>
            <div class="agency">Department of Health</div>
            <div class="facility-name">DR. JOSE N. RODRIGUEZ MEMORIAL HOSPITAL AND SANITARIUM</div>
            <div class="facility-address">Tala, Caloocan City, Metro Manila • www.djnrmhs.doh.gov.ph</div>
            <div class="system-name">Hospital Inventory Management System (HIMS) — Official Report</div>
        </header>

        {{-- Report Identification & Metadata Block --}}
        <div class="report-title-block">
            <div>
                <h2>{{ $report['meta']['report_title'] }}</h2>
                <div class="meta-item">
                    <span class="meta-label">Reporting Period:</span>
                    <span class="meta-value">{{ $report['meta']['period']['description'] }}</span>
                </div>
            </div>
            <div class="report-meta-grid">
                <div class="meta-item">
                    <span class="meta-label">Date Generated:</span>
                    <span class="meta-value">{{ $report['meta']['generated_at']->format('M d, Y H:i:s') }}</span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">Generated By:</span>
                    <span class="meta-value">{{ $report['meta']['generated_by'] }}</span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">Report Module:</span>
                    <span class="meta-value">{{ ucwords(str_replace('_', ' ', $report['meta']['report_type'])) }}</span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">Security Classification:</span>
                    <span class="meta-value">{{ $report['meta']['is_financial_permitted'] ? 'Internal / Commercial Sensitive' : 'Operational Restrictive' }}</span>
                </div>
            </div>
        </div>

        {{-- Active Filters Pill Summary --}}
        @if (!empty($report['meta']['filter_labels']))
            <div class="filters-summary">
                <span style="font-size: 10px; color: #64748b; font-weight: 600; padding: 2px 0;">APPLIED FILTERS:</span>
                @foreach ($report['meta']['filter_labels'] as $label => $value)
                    <span class="filter-pill">
                        <span>{{ $label }}:</span>
                        <strong>{{ $value }}</strong>
                    </span>
                @endforeach
            </div>
        @endif

        {{-- Top Level KPI Summary Cards --}}
        @if (!empty($report['summary']))
            <div class="stats-grid" style="margin-top: 16px;">
                @foreach ($report['summary'] as $label => $val)
                    <div class="stat-card">
                        <div class="stat-label">{{ $label }}</div>
                        <div class="stat-val">{{ is_numeric($val) ? number_format($val) : $val }}</div>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Report Content: ALL REPORTS (Multi-Section Compilation) --}}
        @if (($report['meta']['report_type'] ?? '') === 'all')
            @foreach ($report['sections'] as $secKey => $section)
                <div class="section-header">
                    <h3>{{ $section['title'] }}</h3>
                    <span class="section-count">{{ count($section['rows']) }} record{{ count($section['rows']) === 1 ? '' : 's' }}</span>
                </div>

                @if (empty($section['rows']))
                    <div class="empty-state">No records found for this section under current filter criteria.</div>
                @else
                    <table class="report-table">
                        <thead>
                            <tr>
                                @foreach ($section['columns'] as $colKey => $colName)
                                    <th class="{{ in_array($colKey, ['quantity_on_hand', 'units', 'quantity', 'reorder_level', 'items', 'orders', 'received_orders', 'movements', 'capacity'], true) ? 'text-center' : (in_array($colKey, ['unit_cost', 'total_value', 'value', 'risk_value', 'total_amount'], true) ? 'text-right' : '') }}">
                                        {{ $colName }}
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($section['rows'] as $row)
                                <tr>
                                    @foreach ($section['columns'] as $colKey => $colName)
                                        @php
                                            $val = $row[$colKey] ?? '-';
                                            $isNumeric = is_numeric($val);
                                            $isCurrency = in_array($colKey, ['unit_cost', 'total_value', 'value', 'risk_value', 'total_amount'], true);
                                            $isStatus = in_array($colKey, ['status', 'status_key'], true);
                                        @endphp
                                        <td class="{{ in_array($colKey, ['quantity_on_hand', 'units', 'quantity', 'reorder_level', 'items', 'orders', 'received_orders', 'movements', 'capacity'], true) ? 'text-center' : ($isCurrency ? 'text-right nowrap' : '') }}">
                                            @if ($isCurrency && $isNumeric)
                                                ₱{{ number_format((float) $val, 2) }}
                                            @elseif ($isStatus)
                                                @php
                                                    $badgeClass = match (strtolower((string) $val)) {
                                                        'in stock', 'received', 'active' => 'badge-success',
                                                        'low stock', 'expiring soon', 'pending' => 'badge-warning',
                                                        'out of stock', 'expired', 'cancelled' => 'badge-danger',
                                                        default => 'badge-neutral',
                                                    };
                                                @endphp
                                                <span class="badge {{ $badgeClass }}">{{ $val }}</span>
                                            @else
                                                {{ $val }}
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                        @if (!empty($section['totals']))
                            <tfoot>
                                <tr>
                                    @foreach ($section['columns'] as $colKey => $colName)
                                        @php
                                            $totVal = $section['totals'][$colKey] ?? '-';
                                            $isCurrency = in_array($colKey, ['unit_cost', 'total_value', 'value', 'risk_value', 'total_amount'], true);
                                        @endphp
                                        <td class="{{ in_array($colKey, ['quantity_on_hand', 'units', 'quantity', 'reorder_level', 'items', 'orders', 'received_orders', 'movements'], true) ? 'text-center' : ($isCurrency ? 'text-right' : '') }}">
                                            {{ is_numeric($totVal) ? number_format($totVal) : $totVal }}
                                        </td>
                                    @endforeach
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                @endif
            @endforeach

        {{-- Report Content: SINGLE REPORT --}}
        @else
            <div class="section-header">
                <h3>Detailed Ledger / Results</h3>
                <span class="section-count">{{ count($report['data']) }} record{{ count($report['data']) === 1 ? '' : 's' }}</span>
            </div>

            @if (empty($report['data']))
                <div class="empty-state">
                    <strong>Notice:</strong> {{ $report['empty_message'] ?? 'No records found matching the applied filter criteria.' }}
                </div>
            @else
                <table class="report-table">
                    <thead>
                        <tr>
                            @foreach ($report['columns'] as $colKey => $colName)
                                <th class="{{ in_array($colKey, ['quantity_on_hand', 'units', 'quantity', 'reorder_level', 'items', 'orders', 'received_orders', 'movements', 'capacity'], true) ? 'text-center' : (in_array($colKey, ['unit_cost', 'total_value', 'value', 'risk_value', 'total_amount'], true) ? 'text-right' : '') }}">
                                    {{ $colName }}
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($report['data'] as $row)
                            <tr>
                                @foreach ($report['columns'] as $colKey => $colName)
                                    @php
                                        $val = $row[$colKey] ?? '-';
                                        $isNumeric = is_numeric($val);
                                        $isCurrency = in_array($colKey, ['unit_cost', 'total_value', 'value', 'risk_value', 'total_amount'], true);
                                        $isStatus = in_array($colKey, ['status', 'status_key'], true);
                                    @endphp
                                    <td class="{{ in_array($colKey, ['quantity_on_hand', 'units', 'quantity', 'reorder_level', 'items', 'orders', 'received_orders', 'movements', 'capacity'], true) ? 'text-center' : ($isCurrency ? 'text-right nowrap' : '') }}">
                                        @if ($isCurrency && $isNumeric)
                                            ₱{{ number_format((float) $val, 2) }}
                                        @elseif ($isStatus)
                                            @php
                                                $badgeClass = match (strtolower((string) $val)) {
                                                    'in stock', 'received', 'active' => 'badge-success',
                                                    'low stock', 'expiring soon', 'pending' => 'badge-warning',
                                                    'out of stock', 'expired', 'cancelled' => 'badge-danger',
                                                    default => 'badge-neutral',
                                                };
                                            @endphp
                                            <span class="badge {{ $badgeClass }}">{{ $val }}</span>
                                        @else
                                            {{ $val }}
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                    @if (!empty($report['totals']))
                        <tfoot>
                            <tr>
                                @foreach ($report['columns'] as $colKey => $colName)
                                    @php
                                        $totVal = $report['totals'][$colKey] ?? '-';
                                        $isCurrency = in_array($colKey, ['unit_cost', 'total_value', 'value', 'risk_value', 'total_amount'], true);
                                    @endphp
                                    <td class="{{ in_array($colKey, ['quantity_on_hand', 'units', 'quantity', 'reorder_level', 'items', 'orders', 'received_orders', 'movements'], true) ? 'text-center' : ($isCurrency ? 'text-right' : '') }}">
                                        {{ is_numeric($totVal) ? number_format($totVal) : $totVal }}
                                    </td>
                                @endforeach
                            </tr>
                        </tfoot>
                    @endif
                </table>
            @endif
        @endif

        {{-- Official Sign-Off and Certification Block --}}
        <section class="signoff-section">
            <div style="font-size: 11px; font-weight: 600; color: #334155; text-transform: uppercase;">
                Verification & Institutional Sign-Off
            </div>
            <div class="signoff-grid">
                <div class="signoff-box">
                    <div class="signoff-line"></div>
                    <div class="signoff-name">{{ $report['meta']['generated_by'] }}</div>
                    <div class="signoff-title">Prepared by / Reporting Officer</div>
                </div>
                <div class="signoff-box">
                    <div class="signoff-line"></div>
                    <div class="signoff-name">Materials Management Division</div>
                    <div class="signoff-title">Reviewed & Verified by</div>
                </div>
                <div class="signoff-box">
                    <div class="signoff-line"></div>
                    <div class="signoff-name">Medical Center Chief II</div>
                    <div class="signoff-title">Approved by / Hospital Authority</div>
                </div>
            </div>
        </section>

        {{-- Document Footnote --}}
        <footer class="report-footer">
            <div>CONFIDENTIAL & PROPRIETARY — Hospital Inventory Management System (HIMS)</div>
            <div>Generated: {{ $report['meta']['generated_at']->format('Y-m-d H:i:s') }} • Tala Hospital</div>
        </footer>

    </div>

    @if (!empty($autoPrint))
        <script>
            window.addEventListener('load', function() {
                setTimeout(function() {
                    window.print();
                }, 350);
            });
        </script>
    @endif

</body>
</html>
