<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $entry->voucherNumber() }} — {{ $entry->voucherTitle() }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/document-print.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/cashflow-voucher.css') }}">
</head>
@php
    $kind = $entry->voucherKind();
    $money = \App\Helpers\CommonHelper::amount($entry->amountMoved(), $entry->currency ?: 'INR');
    $words = \App\Helpers\CommonHelper::inWords($entry->amountMoved(), $entry->currency ?: 'INR');
@endphp
<body class="pdf-preview" onload="setTimeout(function () { window.print(); }, 400)">
    <div class="pdf-toolbar no-print">
        <a class="pdf-action" href="{{ route('cashflows.show', $entry) }}">Back to entry</a>
        <div class="pdf-toolbar-group">
            <button type="button" class="pdf-action pdf-action--primary" onclick="window.print()">Print</button>
        </div>
    </div>

    <main class="pdf-sheet voucher-sheet voucher-sheet--{{ $kind }}">
        <header class="voucher-head">
            @include('cashflows.partials.voucher-letterhead')
            <div class="voucher-kind">
                <strong>{{ $entry->voucherTitle() }}</strong>
                <span>{{ $entry->voucherNumber() }}</span>
            </div>
        </header>

        <dl class="voucher-meta">
            <div><dt>Date</dt><dd>{{ $entry->entry_date?->format('d M Y') }}</dd></div>
            <div><dt>Account</dt><dd>{{ $entry->account?->account_name ?: '—' }} ({{ $entry->account?->typeLabel() }})</dd></div>
            <div><dt>Mode</dt><dd>{{ $paymentModeOptions[$entry->payment_mode] ?? '—' }}</dd></div>
            <div><dt>Reference</dt><dd>{{ $entry->bank_reference_number ?: $entry->invoice_bill_number ?: '—' }}</dd></div>
        </dl>

        <p class="voucher-party">
            <span>{{ $entry->isMoneyOut() ? 'Paid to' : 'Received from' }}</span>
            <strong>{{ $entry->partyLabel() ?: $entry->particular }}</strong>
        </p>

        <table class="voucher-lines">
            <thead>
                <tr>
                    <th>Particulars</th>
                    <th>Head</th>
                    <th class="num">Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>{{ $entry->particular }}</td>
                    <td>{{ $entry->category?->name ?: ($entry->expense_head ?: '—') }}</td>
                    <td class="num">{{ $money }}</td>
                </tr>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="2">Total</td>
                    <td class="num">{{ $money }}</td>
                </tr>
            </tfoot>
        </table>

        <p class="voucher-words"><span>Amount in words</span>{{ $words }}</p>

        @if ($entry->notes)
            <p class="voucher-notes">{{ $entry->notes }}</p>
        @endif

        <footer class="voucher-signs">
            <div>
                <span>Prepared by</span>
                <strong>{{ $entry->creator?->name ?: '—' }}</strong>
            </div>
            <div>
                <span>Checked by</span>
                <strong>&nbsp;</strong>
            </div>
            <div>
                <span>{{ $kind === 'receipt' ? 'Received' : 'Authorised' }}</span>
                <strong>&nbsp;</strong>
            </div>
        </footer>
    </main>
</body>
</html>
