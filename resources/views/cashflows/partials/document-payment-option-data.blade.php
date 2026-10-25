@php
    // Use the same document-currency figures as the invoice and vendor ledgers.
    // CashflowPickers eager-loads these sums once, not once per option.
    $paymentIsSales = $invoice instanceof \App\Models\SalesInvoice;
    $paymentIsOrder = ! $paymentIsSales && $invoice->isOrder();
    $paymentTotal = $paymentIsSales ? $invoice->receivableTotal() : (float) $invoice->total_amount;
    $paymentPaid = $paymentIsSales ? $invoice->receivedAmount() : $invoice->paidAmount();
    $paymentBalance = $paymentIsOrder ? $invoice->openAmount() : $invoice->balanceDue();
    $paymentTotalLabel = $paymentIsSales && (float) $invoice->cash_converted_total_amount > 0 ? 'Current receivable' : 'Document total';
    $paymentNote = '';
    if ($paymentIsSales && $invoice->isSuperseded()) {
        $paymentNote = 'This proforma has been converted. Record allocations against its invoice.';
    } elseif ($paymentIsSales && (float) $invoice->cash_converted_total_amount > 0) {
        $paymentNote = 'Current receivable excludes the value already converted to cash invoices.';
    } elseif ($paymentIsOrder) {
        $paymentNote = $invoice->moneyGateMessage() ?: 'Purchase order: available for advance is the order value less recorded payments; no bill is due yet.';
    }
@endphp
data-payment-reference="{{ $invoice->invoice_number }}"
data-payment-total="{{ $paymentTotal }}"
data-payment-paid="{{ $paymentPaid }}"
data-payment-balance="{{ $paymentBalance }}"
data-payment-total-label="{{ $paymentTotalLabel }}"
data-payment-balance-label="{{ $paymentIsOrder ? 'Available for advance' : 'Balance due' }}"
data-payment-note="{{ $paymentNote }}"
