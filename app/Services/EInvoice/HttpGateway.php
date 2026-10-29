<?php

namespace App\Services\EInvoice;

use App\Models\SalesInvoiceEInvoice;
use Illuminate\Support\Facades\Http;

/**
 * A JSON e-invoice endpoint (a GSP or an in-house bridge): POST the NIC-schema
 * payload, read the acknowledgement back.
 *
 * Configuration (see config/services.php `einvoice`):
 *   endpoint  — POST target accepting the payload and answering the four
 *               artefacts ({irn, ack_no, ack_date, signed_invoice, signed_qr});
 *               the same endpoint + "/cancel" receives {irn, cancel_rsn, cancel_rmrk}.
 *   token     — bearer token, if the endpoint wants one.
 *   timeout   — seconds; registration is not retried automatically here
 *               because a duplicated submit can generate a duplicate IRN on
 *               some bridges — a timed-out attempt stays `failed` and is
 *               re-registered deliberately by a human.
 */
final class HttpGateway implements EInvoiceGateway
{
    public function __construct(
        private readonly string $endpoint,
        private readonly ?string $token = null,
        private readonly int $timeout = 30,
    ) {
    }

    public function name(): string
    {
        return 'http';
    }

    public function register(array $payload): EInvoiceResult
    {
        $response = $this->request()->post($this->endpoint, $payload);

        if ($response->failed()) {
            return EInvoiceResult::failed(
                'E-invoice endpoint answered HTTP ' . $response->status(),
                $response->json() ?? ['body' => $response->body()],
            );
        }

        $json = (array) $response->json();
        $irn = $json['irn'] ?? $json['Irn'] ?? null;

        if (! is_string($irn) || $irn === '') {
            return EInvoiceResult::failed('The endpoint answered 2xx but returned no IRN.', $json);
        }

        return EInvoiceResult::registered(
            $irn,
            $json['ack_no'] ?? $json['AckNo'] ?? null,
            $this->parseDate($json['ack_date'] ?? $json['AckDt'] ?? null),
            $json['signed_invoice'] ?? $json['SignedInvoice'] ?? null,
            $json['signed_qr'] ?? $json['SignedQRCode'] ?? null,
            $json,
        );
    }

    public function cancel(SalesInvoiceEInvoice $record, string $reason, ?string $remark): EInvoiceResult
    {
        $response = $this->request()->post(rtrim($this->endpoint, '/') . '/cancel', [
            'irn' => $record->irn,
            'cancel_rsn' => $reason,
            'cancel_rmrk' => $remark,
        ]);

        if ($response->failed()) {
            return EInvoiceResult::failed('Cancel answered HTTP ' . $response->status(), $response->json());
        }

        return EInvoiceResult::done($response->json());
    }

    private function request(): \Illuminate\Http\Client\PendingRequest
    {
        $request = Http::acceptJson()->timeout($this->timeout);

        return $this->token ? $request->withToken($this->token) : $request;
    }

    private function parseDate(mixed $value): ?\DateTimeInterface
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        foreach (['d/m/Y H:i:s', 'd/m/Y', \DateTimeInterface::ATOM] as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, trim($value));
            if ($date !== false) {
                return $date;
            }
        }

        return null;
    }
}
