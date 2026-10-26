<?php

namespace App\Mail;

use App\Models\SalesInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The invoice, leaving the building. The office writes the subject and the
 * message in the compose dialog; the mailable wraps them with the invoice's
 * own numbers and — with `$linkAllowed` — the public copy link. Nothing here
 * invents content: what the send log stores is what the client received.
 */
class SalesInvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public SalesInvoice $invoice,
        public string $subjectLine,
        public string $messageText,
        public bool $linkAllowed = false,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.sales-invoice',
            with: ['publicLink' => $this->publicUrl()],
        );
    }

    /** The public copy link is offered only when the caller promises it will
        open — the invoice is already portal-visible, or this send flips it. */
    public function publicUrl(): ?string
    {
        return $this->linkAllowed && $this->invoice->public_token
            ? route('sales-invoices.public', $this->invoice->public_token)
            : null;
    }
}
