<?php

namespace App\Services\EInvoice;

use App\Models\SalesInvoice;
use App\Models\SalesInvoiceEInvoice;
use App\Models\User;
use App\Services\AuditTrail;
use DomainException;

/**
 * The one writer of `sales_invoice_e_invoices`.
 *
 * The moving parts meet here and only here: the payload builder (what we ask),
 * the configured gateway (who completes it), the audit trail (who did it) and
 * the record itself (what the portal said). Controllers call these three
 * verbs — generate, record, cancel — and the UI never touches the gateway or
 * the schema directly, so the day the provider changes from `manual` to
 * `http`, only the gateway line under `gateway()` knows.
 */
final class EInvoiceRegistration
{
    public function __construct(private readonly AuditTrail $audit) {}

    /** The configured provider for this environment. */
    public function gateway(): EInvoiceGateway
    {
        $provider = (string) config('services.einvoice.provider', 'manual');

        return match ($provider) {
            'http' => new HttpGateway(
                (string) config('services.einvoice.endpoint'),
                config('services.einvoice.token'),
                (int) config('services.einvoice.timeout', 30),
            ),
            default => new ManualGateway(),
        };
    }

    /**
     * Build the payload and either register it (http) or park it pending the
     * portal's acknowledgement (manual). Returns the record; refuses with a
     * DomainException whose message lists every fixable reason when the
     * invoice is not e-registerable.
     */
    public function generate(SalesInvoice $invoice, User $actor): SalesInvoiceEInvoice
    {
        $payload = (new EInvoicePayload($invoice))->build();
        $gateway = $this->gateway();

        $record = SalesInvoiceEInvoice::query()->firstOrNew(['sales_invoice_id' => $invoice->id]);
        if ($record->exists && $record->isRegistered()) {
            throw new DomainException('This invoice already has an IRN (' . $record->irn . '). Cancel it before registering again.');
        }

        $record->fill([
            'status' => SalesInvoiceEInvoice::STATUS_PENDING,
            'provider' => $gateway->name(),
            'request_payload' => $payload,
            'response_payload' => null,
            'error' => null,
            'irn' => null, 'ack_no' => null, 'ack_date' => null,
            'signed_invoice' => null, 'signed_qr_code' => null,
            'cancelled_at' => null, 'cancel_reason' => null, 'cancel_remark' => null,
        ]);

        $result = $gateway->register($payload);
        $this->applyRegistration($record, $result, $actor->id);
        $record->save();

        $this->audit->event('sales_invoice.einvoice.generated', [
            'auditable_type' => SalesInvoice::class,
            'auditable_id' => $invoice->id,
            'actor_type' => 'office_user', 'actor_id' => $actor->id,
            'actor_name' => $actor->name, 'actor_identifier' => $actor->email,
            'context' => ['provider' => $gateway->name(), 'status' => $record->status],
        ]);

        return $record;
    }

    /**
     * Record the acknowledgement the portal handed back on the manual path
     * (IRN is the 64-character SHA-256 the portal prints; anything shorter is
     * not an IRN). Also used as the admin override when a gateway result was
     * captured off-band.
     */
    public function recordPortalAcknowledgement(SalesInvoiceEInvoice $record, array $data, User $actor): SalesInvoiceEInvoice
    {
        if ($record->status !== SalesInvoiceEInvoice::STATUS_PENDING && ! $record->isRegistered()) {
            throw new DomainException('Only a pending registration can accept a portal acknowledgement.');
        }

        $irn = strtoupper(trim($data['irn'] ?? ''));
        if (! preg_match('/^[0-9A-F]{64}$/', $irn)) {
            throw new DomainException('An IRN is exactly 64 hexadecimal characters — that value is not an IRN.');
        }

        $clashes = SalesInvoiceEInvoice::query()
            ->where('irn', $irn)->where('id', '!=', $record->id)->exists();
        if ($clashes) {
            throw new DomainException('That IRN is already recorded against another invoice.');
        }

        $ackDate = null;
        if (! empty($data['ack_date'])) {
            $ackDate = strtotime((string) $data['ack_date']);
            if ($ackDate === false) {
                throw new DomainException('The acknowledgement date could not be read.');
            }
        }

        $record->fill([
            'status' => SalesInvoiceEInvoice::STATUS_REGISTERED,
            'irn' => $irn,
            'ack_no' => $data['ack_no'] ?? null,
            'ack_date' => $ackDate ? now()->setTimestamp($ackDate) : now(),
            'signed_invoice' => $data['signed_invoice'] ?? null,
            'signed_qr_code' => $data['signed_qr_code'] ?? null,
            'registered_by' => $actor->id,
            'error' => null,
        ])->save();

        $this->audit->event('sales_invoice.einvoice.registered', [
            'auditable_type' => SalesInvoice::class,
            'auditable_id' => $record->sales_invoice_id,
            'actor_type' => 'office_user', 'actor_id' => $actor->id,
            'actor_name' => $actor->name, 'actor_identifier' => $actor->email,
            'context' => ['irn_tail' => substr($irn, -8), 'provider' => $record->provider],
        ]);

        return $record;
    }

    /**
     * Cancel a registration. The portal's own rule — within twenty-four hours
     * of the acknowledgement — is enforced on every path, manual included,
     * because a cancelled IRN and an ERP that still shows it is a reconciliation
     * that never closes. Past the window the correct instrument is a credit note.
     */
    public function cancel(SalesInvoiceEInvoice $record, string $reason, ?string $remark, User $actor): SalesInvoiceEInvoice
    {
        if (! $record->isRegistered() || $record->cancelled_at !== null) {
            throw new DomainException('Only a registered e-invoice can be cancelled.');
        }
        if (! array_key_exists($reason, SalesInvoiceEInvoice::CANCEL_REASONS)) {
            throw new DomainException('The cancel reason must be one of the portal codes 1–4.');
        }
        if (! $record->isCancellable()) {
            throw new DomainException('The portal accepts cancellations only within '
                . SalesInvoiceEInvoice::CANCEL_WINDOW_HOURS . ' hours of the acknowledgement — issue a credit note instead.');
        }

        $result = $this->gateway()->cancel($record, $reason, $remark);
        if (! $result->ok) {
            throw new DomainException($result->error ?? 'The provider refused the cancellation.');
        }

        $record->fill([
            'status' => SalesInvoiceEInvoice::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancel_reason' => $reason,
            'cancel_remark' => $remark,
            'response_payload' => $result->rawResponse ?? $record->response_payload,
        ])->save();

        $this->audit->event('sales_invoice.einvoice.cancelled', [
            'auditable_type' => SalesInvoice::class,
            'auditable_id' => $record->sales_invoice_id,
            'actor_type' => 'office_user', 'actor_id' => $actor->id,
            'actor_name' => $actor->name, 'actor_identifier' => $actor->email,
            'context' => ['cancel_reason' => $reason],
        ]);

        return $record;
    }

    private function applyRegistration(SalesInvoiceEInvoice $record, EInvoiceResult $result, int $actorId): void
    {
        if (! $result->ok) {
            $record->fill([
                'status' => SalesInvoiceEInvoice::STATUS_FAILED,
                'error' => $result->error ?? 'Registration failed.',
                'response_payload' => $result->rawResponse,
            ]);

            return;
        }

        if ($result->registered && $result->irn) {
            $record->fill([
                'status' => SalesInvoiceEInvoice::STATUS_REGISTERED,
                'irn' => $result->irn,
                'ack_no' => $result->ackNo,
                'ack_date' => $result->ackDate ?? now(),
                'signed_invoice' => $result->signedInvoice,
                'signed_qr_code' => $result->signedQrCode,
                'registered_by' => $actorId,
                'response_payload' => $result->rawResponse,
            ]);

            return;
        }

        // needsManualFinish: payload parked, awaiting portal acknowledgement.
    }
}
