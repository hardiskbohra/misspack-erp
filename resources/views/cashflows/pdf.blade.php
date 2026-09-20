<!doctype html>
<html>

<head>
    <meta charset="utf-8">
    <title>Cashflow Report</title>
    <style>
        * {
            box-sizing: border-box
        }

        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            color: #17233b;
            font-size: 12px;
            margin: 0;
            padding: 24px
        }

        .header {
            display: flex;
            justify-content: space-between;
            border-bottom: 2px solid #dfe7f3;
            padding-bottom: 14px;
            margin-bottom: 18px
        }

        .brand {
            font-size: 22px;
            font-weight: 900
        }

        .subtitle {
            color: #687386;
            font-weight: 500;
            margin-top: 4px;
            margin-bottom: 10px
        }

        .badge {
            display: inline-block;
            background: #edf5ff;
            color: #4f83f1;
            padding: 7px 10px;
            border-radius: 999px;
            font-weight: 900
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-bottom: 18px
        }

        .stat {
            border: 1px solid #dfe7f3;
            border-radius: 12px;
            padding: 12px;
            background: #fbfdff
        }

        .stat span {
            display: block;
            color: #687386;
            font-size: 10px;
            font-weight: 900;
            text-transform: uppercase
        }

        .stat strong {
            display: block;
            font-size: 18px;
            margin-top: 5px
        }

        .section {
            margin-top: 18px
        }

        .section h3 {
            font-size: 15px;
            margin: 0 0 10px
        }

        table {
            width: 100%;
            border-collapse: collapse
        }

        th,
        td {
            border-bottom: 1px solid #dfe7f3;
            padding: 8px;
            text-align: left;
            vertical-align: top
        }

        th {
            font-size: 10px;
            color: #7d8aa0;
            text-transform: uppercase;
            letter-spacing: .05em;
            background: #f8fafc
        }

        .credit {
            color: #059669;
            font-weight: 900
        }

        .debit {
            color: #e11d48;
            font-weight: 900
        }

        .small {
            color: #687386;
            font-size: 10px
        }

        .notice {
            padding: 10px;
            margin-bottom: 14px;
            background: #fff4e5;
            color: #92400e;
            border: 1px solid #fed7aa;
            border-radius: 10px;
            font-weight: 700
        }

        @media print {
            body {
                padding: 0
            }

            .no-print {
                display: none
            }
        }
    </style>
</head>

<body @if(!empty($pdfFallbackMessage)) onload="setTimeout(function(){ window.print(); }, 500)" @endif>
    @if(!empty($pdfFallbackMessage))
    <div class="notice no-print">{{ $pdfFallbackMessage }}</div>@endif
    <div class="header">
        <div>
            <div class="brand">MissPack Statement Report</div>
            <div class="subtitle">{{ $dateFrom->format('d M Y') }} to {{ $dateTo->format('d M Y') }} ·
                {{ ucfirst(str_replace('_', ' ', $reportType)) }} report</div>
        </div>
        <div><span class="badge">{{ strtoupper($period) }}</span></div>
    </div>
    {{-- <div class="grid">
        <div class="stat"><span>Total Credit</span><strong>{{ number_format($summary['credit'], 2) }}</strong></div>
        <div class="stat"><span>Total Debit</span><strong>{{ number_format($summary['debit'], 2) }}</strong></div>
        <div class="stat"><span>Net Cashflow</span><strong>{{ number_format($summary['net'], 2) }}</strong></div>
        <div class="stat"><span>Entries</span><strong>{{ $summary['count'] }}</strong></div>
    </div> --}}
    <div class="section">
        <h3>Account Summary</h3>
        <table>
            <thead>
                <tr>
                    <th>Account</th>
                    <th>Type</th>
                    <th>Credit</th>
                    <th>Debit</th>
                    <th>Net</th>
                </tr>
            </thead>
            <tbody>@forelse($accountSummary as $row)<tr>
                <td>{{ $row['name'] }}</td>
                <td>{{ $row['type'] }}</td>
                <td class="credit">{{ number_format($row['credit'], 2) }}</td>
                <td class="debit">{{ number_format($row['debit'], 2) }}</td>
                <td>{{ number_format($row['credit'] - $row['debit'], 2) }}</td>
            </tr>@empty<tr>
                    <td colspan="5">No data.</td>
                </tr>@endforelse</tbody>
        </table>
    </div>
    <div class="section">
        <h3>Statement Entries</h3>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Particular</th>
                    <th>Invoice/Bill</th>
                    <th>Bank Ref</th>
                    <th>Account</th>
                    <th>Category</th>
                    <th>Credit</th>
                    <th>Debit</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>@forelse($entries as $entry)<tr>
                <td>{{ $entry->entry_date?->format('d M Y') }}</td>
                <td>{{ $entry->particular }}
                    <div class="small">{{ $entry->related_party_name ?: $entry->expense_head }}</div>
                </td>
                <td>{{ $entry->invoice_bill_number ?: '-' }}</td>
                <td>{{ $entry->bank_reference_number ?: '-' }}</td>
                <td>{{ $entry->account?->account_name }}</td>
                <td>{{ $entry->category?->name ?: '-' }}</td>
                <td class="credit">
                    {{ $entry->credit_amount > 0 ? number_format((float) $entry->credit_amount, 2) : '-' }}</td>
                <td class="debit">
                    {{ $entry->debit_amount > 0 ? number_format((float) $entry->debit_amount, 2) : '-' }}</td>
                <td>{{ $entry->statusLabel() }}</td>
            </tr>@empty<tr>
                    <td colspan="10">No entries found.</td>
                </tr>@endforelse</tbody>
        </table>
    </div>
</body>

</html>