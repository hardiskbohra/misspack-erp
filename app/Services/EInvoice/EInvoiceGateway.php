<?php

namespace App\Services\EInvoice;

use App\Models\SalesInvoiceEInvoice;

/**
 * The contract every e-invoice path honours, manual and HTTP alike.
 *
 * Two providers ship: `ManualGateway` for offices that register on the GST
 * portal (or a GSP) by hand and paste the acknowledgement back, and
 * `HttpGateway` for a configured JSON endpoint. They differ only in how the
 * acknowledgement arrives — the record, its statuses and the UI are provider-
 * agnostic, so switching `services.einvoice.provider` is a config change,
 * not a code change.
 */
interface EInvoiceGateway
{
    /** Short provider name stored on the record (`manual`, `http`). */
    public function name(): string;

    /**
     * Submit a payload and return a result. `registering: false` means the
     * provider cannot complete registration by itself and the office finishes
     * it by recording the portal's answer (the manual flow).
     */
    public function register(array $payload): EInvoiceResult;

    /** Ask the provider to cancel; the office checks the 24-hour window first. */
    public function cancel(SalesInvoiceEInvoice $record, string $reason, ?string $remark): EInvoiceResult;
}
