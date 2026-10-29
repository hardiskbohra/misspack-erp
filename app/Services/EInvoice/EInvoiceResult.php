<?php

namespace App\Services\EInvoice;

/**
 * What a gateway came back with after register/cancel.
 *
 * `registered` carries the portal's own four artefacts (IRN, ack number and
 * date, signed QR) — everything else about the record (status transitions,
 * the stored request JSON, who clicked) is the registrar's job, not the
 * gateway's, so provider swaps cannot change bookkeeping.
 */
final class EInvoiceResult
{
    private function __construct(
        public readonly bool $ok,
        public readonly bool $needsManualFinish = false,
        public readonly ?string $irn = null,
        public readonly ?string $ackNo = null,
        public readonly ?\DateTimeInterface $ackDate = null,
        public readonly ?string $signedInvoice = null,
        public readonly ?string $signedQrCode = null,
        public readonly ?array $rawResponse = null,
        public readonly ?string $error = null,
    ) {
    }

    /** The provider completed registration; the four artefacts are inside. */
    public static function registered(string $irn, ?string $ackNo, ?\DateTimeInterface $ackDate, ?string $signedInvoice, ?string $signedQr, ?array $raw = null): self
    {
        return new self(true, false, $irn, $ackNo, $ackDate, $signedInvoice, $signedQr, $raw);
    }

    /** The provider validated nothing and sent nothing: record what the portal said. */
    public static function awaitingPortalAcknowledgement(): self
    {
        return new self(true, true);
    }

    /** An action (cancel) completed; no registration artefacts involved. */
    public static function done(?array $raw = null): self
    {
        return new self(true, false, null, null, null, null, null, $raw);
    }

    public static function failed(string $error, ?array $raw = null): self
    {
        return new self(false, false, null, null, null, null, null, $raw, $error);
    }
}
