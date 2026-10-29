<?php

namespace App\Http\Controllers;

use App\Models\SalesInvoice;
use App\Models\SalesInvoiceNote;
use App\Services\SalesInvoiceAdjustment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Credit notes, debit notes, and returns against tax invoices — corrections
 * that are documents, not edits: numbered, reasoned, and afterwards immutable.
 */
class SalesInvoiceNoteController extends Controller
{
    public function store(Request $request, SalesInvoice $salesInvoice, SalesInvoiceAdjustment $adjustments): RedirectResponse
    {
        $kind = $request->input('kind') === SalesInvoiceNote::KIND_DEBIT
            ? SalesInvoiceNote::KIND_DEBIT
            : SalesInvoiceNote::KIND_CREDIT;

        $note = $adjustments->issue($salesInvoice, $kind, $request->all());

        $label = $kind === SalesInvoiceNote::KIND_CREDIT ? 'Credit note' : 'Debit note';

        return redirect()->route('sales-invoices.show', $salesInvoice)
            ->with('success', "{$label} {$note->number} issued for "
                .\App\Helpers\CommonHelper::amount($note->total, $salesInvoice->currency).'.');
    }

    public function storeReturn(Request $request, SalesInvoice $salesInvoice, SalesInvoiceAdjustment $adjustments): RedirectResponse
    {
        $note = $adjustments->recordReturn($salesInvoice, $request->all());

        return redirect()->route('sales-invoices.show', $salesInvoice)
            ->with('success', 'Return '.$note->number.' recorded — the goods are'
                .($note->restock ? ' restocked and' : '').' the credit is on the invoice.');
    }

    public function cancel(Request $request, SalesInvoiceNote $note, SalesInvoiceAdjustment $adjustments): RedirectResponse
    {
        $invoice = $note->invoice;
        $adjustments->cancel($note, $request->input('cancel_reason'));

        return redirect()->route('sales-invoices.show', $invoice)
            ->with('success', $note->titleLabel().' was cancelled. Its money no longer applies to the invoice.');
    }

    /** A note is a document too: it prints on the same letterhead as its invoice. */
    public function print(SalesInvoiceNote $note): View
    {
        $note->load(['invoice.items', 'creator']);

        return view('sales_invoices.notes.print', [
            'note' => $note,
            'invoice' => $note->invoice,
        ]);
    }
}
