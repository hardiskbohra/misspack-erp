<!doctype html>
<html>

<head>
    <meta charset="utf-8">
    <title>Cashflow Report</title>
    <link rel="stylesheet" href="{{ asset('assets/css/cashflows-pdf.css') }}">
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