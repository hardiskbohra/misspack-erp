<?php

namespace App\Http\Controllers;

use App\Models\SalesInvoice;
use App\Services\EInvoice\EInvoicePayload;
use App\Services\EInvoice\EInvoiceRegistration;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The three e-invoice verbs on a sales invoice: generate the payload (and
 * register it when the configured gateway can), record the portal's
 * acknowledgement on the manual path, cancel within the portal's window.
 *
 * Every one of them answers the same show page — the e-invoice card there is
 * where the payload is copied from and where the acknowledgement is pasted in,
 * so a redirect `back` always lands the user looking at the thing they changed.
 */
class SalesInvoiceEInvoiceController extends Controller
{
    public function __construct(private readonly EInvoiceRegistration $registrar) {}

    public function generate(SalesInvoice $salesInvoice): RedirectResponse
    {
        /* Surface the fixable reasons *before* any attempt is persisted, so a
           "cannot register" flash names the remedy, not an HTTP status. */
        $issues = (new EInvoicePayload($salesInvoice))->issues();
        if ($issues !== []) {
            return back()->with('error', 'E-invoice cannot be generated yet: ' . implode(' ', $issues));
        }

        try {
            $record = $this->registrar->generate($salesInvoice, request()->user());
        } catch (DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('status', $record->isRegistered()
            ? 'E-invoice registered — IRN ' . $record->irn . '.'
            : 'E-invoice payload generated. Register it on the e-invoice portal (or your GSP), then record the IRN and acknowledgement below.');
    }

    public function record(Request $request, SalesInvoice $salesInvoice): RedirectResponse
    {
        $record = $salesInvoice->eInvoice;
        if (! $record) {
            return back()->with('error', 'Generate the e-invoice payload first — the acknowledgement needs something to attach to.');
        }

        $data = $request->validate([
            'irn' => ['required', 'string', 'size:64'],
            'ack_no' => ['nullable', 'string', 'max:30'],
            'ack_date' => ['nullable', 'date'],
            'signed_invoice' => ['nullable', 'string', 'max:20000'],
            'signed_qr_code' => ['nullable', 'string', 'max:20000'],
        ]);

        try {
            $this->registrar->recordPortalAcknowledgement($record, $data, $request->user());
        } catch (DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('status', 'IRN recorded. The invoice print now carries the e-invoice QR code.');
    }

    public function cancel(Request $request, SalesInvoice $salesInvoice): RedirectResponse
    {
        $record = $salesInvoice->eInvoice;
        if (! $record) {
            return back()->with('error', 'This invoice has no e-invoice registration to cancel.');
        }

        $data = $request->validate([
            'cancel_reason' => ['required', 'in:1,2,3,4'],
            'cancel_remark' => ['nullable', 'string', 'max:100'],
        ]);

        try {
            $this->registrar->cancel($record, $data['cancel_reason'], $data['cancel_remark'] ?? null, $request->user());
        } catch (DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('status', 'E-invoice registration cancelled. Past the cancellation window, corrections move through a credit note.');
    }
}
