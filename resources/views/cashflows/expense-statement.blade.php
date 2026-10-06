<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expense statement — {{ $head }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/document-print.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/cashflow-voucher.css') }}">
</head>
<body class="pdf-preview">
    <div class="pdf-toolbar pdf-toolbar--landscape no-print">
        <a class="pdf-action" href="{{ route('cashflows.show', $entry) }}">Back to entry</a>
        <div class="pdf-toolbar-group">
            <button type="button" class="pdf-action pdf-action--primary" onclick="window.print()">Print</button>
        </div>
    </div>

    <main class="pdf-sheet pdf-sheet--landscape voucher-sheet">
        <header class="voucher-head">
            <div>
                <p class="voucher-brand">MissPack India Pvt Ltd</p>
                <p class="voucher-sub">Expense statement by type</p>
            </div>
            <div class="voucher-kind">
                <strong>{{ $head }}</strong>
                <span>{{ $from->format('d M Y') }} – {{ $to->format('d M Y') }}</span>
            </div>
        </header>

        <div class="voucher-summary">
            <div><span>Entries</span><strong>{{ $rows->count() }}</strong></div>
            <div><span>Total out</span><strong>{{ \App\Helpers\CommonHelper::indianCurrency($total) }}</strong></div>
        </div>

        <table class="voucher-lines">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Voucher</th>
                    <th>Particular</th>
                    <th>Account</th>
                    <th>Party</th>
                    <th class="num">Amount</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td>{{ $row->entry_date?->format('d M Y') }}</td>
                        <td>{{ $row->voucherNumber() }}</td>
                        <td>{{ $row->particular }}</td>
                        <td>{{ $row->account?->account_name }}</td>
                        <td>{{ $row->partyLabel() ?: '—' }}</td>
                        <td class="num">{{ \App\Helpers\CommonHelper::amount($row->amountMoved(), $row->currency ?: 'INR') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">No expenses of this type in the financial year.</td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="5">Total</td>
                    <td class="num">{{ \App\Helpers\CommonHelper::indianCurrency($total) }}</td>
                </tr>
            </tfoot>
        </table>

        <p class="voucher-words"><span>Amount in words</span>{{ \App\Helpers\CommonHelper::inWords($total) }}</p>
    </main>
</body>
</html>
