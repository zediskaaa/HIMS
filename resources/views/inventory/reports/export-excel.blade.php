<!DOCTYPE html>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    @php
        echo '<!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>' . e(substr($report['meta']['report_title'], 0, 31)) . '</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->';
    @endphp
    <style>
        body { font-family: Calibri, Arial, sans-serif; font-size: 11pt; color: #000000; }
        .header-title { font-size: 14pt; font-weight: bold; color: #0f172a; }
        .header-sub { font-size: 10pt; color: #475569; }
        .meta-label { font-weight: bold; color: #334155; }
        table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }
        th { background-color: #0284c7; color: #ffffff; font-weight: bold; border: 1px solid #0369a1; padding: 6px 10px; text-align: left; }
        td { border: 1px solid #cbd5e1; padding: 5px 8px; vertical-align: middle; }
        .even { background-color: #f8fafc; }
        .totals-row td { background-color: #e2e8f0; font-weight: bold; border-top: 2px solid #0f172a; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .section-title { font-size: 12pt; font-weight: bold; color: #0369a1; padding-top: 15px; }
    </style>
</head>
<body>
    <table>
        <tr>
            <td colspan="4" class="header-title">{{ $report['meta']['hospital_name'] }}</td>
        </tr>
        <tr>
            <td colspan="4" class="header-sub">{{ $report['meta']['sub_title'] }}</td>
        </tr>
        <tr>
            <td colspan="4" style="font-size: 13pt; font-weight: bold; color: #0284c7;">{{ $report['meta']['report_title'] }}</td>
        </tr>
        <tr>
            <td class="meta-label">Generated:</td>
            <td>{{ $report['meta']['generated_at']->format('Y-m-d H:i:s') }}</td>
            <td class="meta-label">By:</td>
            <td>{{ $report['meta']['generated_by'] }}</td>
        </tr>
        <tr>
            <td class="meta-label">Period:</td>
            <td colspan="3">{{ $report['meta']['period']['description'] }}</td>
        </tr>
        @foreach ($report['meta']['filter_labels'] as $label => $val)
            <tr>
                <td class="meta-label">Filter: {{ $label }}</td>
                <td colspan="3">{{ $val }}</td>
            </tr>
        @endforeach
        <tr><td colspan="4" style="border:none;">&nbsp;</td></tr>
    </table>

    {{-- Summary Box --}}
    @if (!empty($report['summary']))
        <table>
            <tr>
                <th colspan="2" style="background-color: #334155;">Summary Overview</th>
            </tr>
            @foreach ($report['summary'] as $label => $val)
                <tr>
                    <td class="meta-label" style="width: 250px;">{{ $label }}</td>
                    <td>{{ is_numeric($val) ? number_format($val) : $val }}</td>
                </tr>
            @endforeach
            <tr><td colspan="2" style="border:none;">&nbsp;</td></tr>
        </table>
    @endif

    {{-- Multi-Section or Single Section Table --}}
    @if (($report['meta']['report_type'] ?? '') === 'all')
        @foreach ($report['sections'] as $section)
            <table>
                <tr>
                    <td colspan="{{ count($section['columns']) }}" class="section-title">
                        === {{ strtoupper($section['title']) }} ===
                    </td>
                </tr>
                @if (empty($section['rows']))
                    <tr>
                        <td colspan="{{ count($section['columns']) }}" style="font-style: italic; color: #64748b;">
                            No records found for this section under the selected filter criteria.
                        </td>
                    </tr>
                @else
                    <tr>
                        @foreach ($section['columns'] as $colKey => $colName)
                            <th class="{{ in_array($colKey, ['quantity_on_hand', 'units', 'quantity', 'reorder_level', 'items', 'orders', 'received_orders', 'movements', 'capacity'], true) ? 'text-center' : (in_array($colKey, ['unit_cost', 'total_value', 'value', 'risk_value', 'total_amount'], true) ? 'text-right' : '') }}">
                                {{ $colName }}
                            </th>
                        @endforeach
                    </tr>
                    @foreach ($section['rows'] as $idx => $row)
                        <tr class="{{ $idx % 2 === 1 ? 'even' : '' }}">
                            @foreach ($section['columns'] as $colKey => $colName)
                                @php
                                    $val = $row[$colKey] ?? '-';
                                    $isNumeric = is_numeric($val);
                                    $isCurrency = in_array($colKey, ['unit_cost', 'total_value', 'value', 'risk_value', 'total_amount'], true);
                                @endphp
                                <td class="{{ in_array($colKey, ['quantity_on_hand', 'units', 'quantity', 'reorder_level', 'items', 'orders', 'received_orders', 'movements', 'capacity'], true) ? 'text-center' : ($isCurrency ? 'text-right' : '') }}">
                                    @if ($isCurrency && $isNumeric)
                                        ₱{{ number_format((float) $val, 2) }}
                                    @else
                                        {{ $val }}
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                    @if (!empty($section['totals']))
                        <tr class="totals-row">
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
                    @endif
                @endif
                <tr><td colspan="{{ count($section['columns']) }}" style="border:none;">&nbsp;</td></tr>
            </table>
        @endforeach
    @else
        <table>
            @if (empty($report['data']))
                <tr>
                    <td colspan="{{ count($report['columns']) }}" style="font-style: italic; color: #64748b; padding: 15px; text-align: center;">
                        {{ $report['empty_message'] ?? 'No records found matching the applied filter criteria.' }}
                    </td>
                </tr>
            @else
                <tr>
                    @foreach ($report['columns'] as $colKey => $colName)
                        <th class="{{ in_array($colKey, ['quantity_on_hand', 'units', 'quantity', 'reorder_level', 'items', 'orders', 'received_orders', 'movements', 'capacity'], true) ? 'text-center' : (in_array($colKey, ['unit_cost', 'total_value', 'value', 'risk_value', 'total_amount'], true) ? 'text-right' : '') }}">
                            {{ $colName }}
                        </th>
                    @endforeach
                </tr>
                @foreach ($report['data'] as $idx => $row)
                    <tr class="{{ $idx % 2 === 1 ? 'even' : '' }}">
                        @foreach ($report['columns'] as $colKey => $colName)
                            @php
                                $val = $row[$colKey] ?? '-';
                                $isNumeric = is_numeric($val);
                                $isCurrency = in_array($colKey, ['unit_cost', 'total_value', 'value', 'risk_value', 'total_amount'], true);
                            @endphp
                            <td class="{{ in_array($colKey, ['quantity_on_hand', 'units', 'quantity', 'reorder_level', 'items', 'orders', 'received_orders', 'movements', 'capacity'], true) ? 'text-center' : ($isCurrency ? 'text-right' : '') }}">
                                @if ($isCurrency && $isNumeric)
                                    ₱{{ number_format((float) $val, 2) }}
                                @else
                                    {{ $val }}
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
                @if (!empty($report['totals']))
                    <tr class="totals-row">
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
                @endif
            @endif
        </table>
    @endif
</body>
</html>
