<?php

namespace App\Mail;

use App\Models\OfficeAlert;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OfficeBriefingMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public OfficeAlert $alert)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[MissPack] '.$this->alert->title,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.office-briefing',
        );
    }
}
