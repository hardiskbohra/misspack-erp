<?php

namespace App\Services\EInvoice;

use App\Models\SalesInvoiceEInvoice;

/**
 * The default provider: the office registers the invoice on the e-invoice
 * portal (or through its GSP) itself and records the acknowledgement here.
 *
 * This is not a placeholder — it is how a large share of Indian SMEs actually
 * comply today, and it turns the ERP into the system of record *for* the
 * portal without pretending to be the portal: the generated payload is copied
 * out, the returned IRN/Ack/QR is pasted back in, and from then on the print
 * and every audit answer come from one row in this database.
 */
final class ManualGateway implements EInvoiceGateway
{
    public function name(): string
    {
        return 'manual';
    }

    public function register(array $payload): EInvoiceResult
    {
        return EInvoiceResult::awaitingPortalAcknowledgement();
    }

    public function cancel(SalesInvoiceEInvoice $record, string $reason, ?string $remark): EInvoiceResult
    {
        // The cancellation happened on the portal; we just accept the record.
        return EInvoiceResult::done(['via' => 'portal']);
    }
}
