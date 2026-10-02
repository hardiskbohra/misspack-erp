<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CashflowAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'cashflow_entry_id', 'document_type', 'title', 'file_path',
        'original_name', 'mime_type', 'file_size', 'extension', 'uploaded_by',
        'party_name', 'document_date', 'amount', 'currency',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'document_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function cashflowEntry()
    {
        return $this->belongsTo(CashflowEntry::class, 'cashflow_entry_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * A bill does not always have a bank line behind it: a purchase bill booked
     * to a project, a document that arrives before the payment, an invoice
     * settled outside this ledger. Those documents stand on their own until an
     * entry is matched to them — and if none ever is, that is a valid answer.
     */
    public function isLinked(): bool
    {
        return $this->cashflow_entry_id !== null;
    }

    public function scopeLinked($query)
    {
        return $query->whereNotNull('cashflow_entry_id');
    }

    public function scopeUnlinked($query)
    {
        return $query->whereNull('cashflow_entry_id');
    }

    public function stateLabel(): string
    {
        return $this->isLinked() ? 'Matched to an entry' : 'Not matched to an entry';
    }

    /**
     * Whose bill this is. A stand-alone document carries its own party name;
     * a matched one takes the counterparty of the entry it is matched to.
     */
    public function partyLabel(): string
    {
        if (filled($this->party_name)) {
            return (string) $this->party_name;
        }

        $entry = $this->cashflowEntry;

        return $entry?->client?->company_name
            ?? $entry?->vendor?->vendor_name
            ?? $entry?->related_party_name
            ?? '—';
    }

    /**
     * The date that matters for a month's paperwork: the document's own date
     * when it has one, otherwise the entry's, otherwise the day it was filed.
     */
    public function displayDate(): ?\Carbon\CarbonInterface
    {
        return $this->document_date ?? $this->cashflowEntry?->entry_date ?? $this->created_at;
    }

    public function amountLabel(): ?string
    {
        return $this->amount === null
            ? null
            : \App\Helpers\CommonHelper::amount($this->amount, $this->currency);
    }

    /** One line in a picker: what it is, whose it is, when and how much. */
    public function optionLabel(): string
    {
        return implode(' · ', array_filter([
            $this->name(),
            $this->party_name,
            $this->displayDate()?->format('d M Y'),
            $this->amountLabel(),
        ]));
    }

    /**
     * What the bill is, in the accountant's terms rather than the file's.
     * The type is what the month-close pack sorts by, so the list is closed:
     * a value outside it would land in "Other" and silently fall out of the
     * purchase register.
     */
    public static function documentTypeOptions(): array
    {
        return [
            'bill' => 'Bill / Invoice',
            'bank_slip' => 'Bank Slip / Advice',
            'receipt' => 'Receipt',
            'gst' => 'GST Document',
            'other' => 'Other',
        ];
    }

    public function documentTypeLabel(): string
    {
        return self::documentTypeOptions()[$this->document_type]
            ?? Str::headline((string) $this->document_type);
    }

    public function name(): string
    {
        return $this->title ?: ($this->original_name ?: 'Document');
    }

    public function url(): string
    {
        return asset('storage/'.$this->file_path);
    }

    public function isImage(): bool
    {
        return in_array(strtolower((string) $this->extension), ['jpg', 'jpeg', 'png', 'webp', 'gif', 'heic', 'heif'], true);
    }

    public function icon(): string
    {
        if ($this->isImage()) {
            return 'fa-file-image';
        }

        return match (strtolower((string) $this->extension)) {
            'pdf' => 'fa-file-pdf',
            'doc', 'docx' => 'fa-file-word',
            'xls', 'xlsx', 'csv' => 'fa-file-excel',
            'ppt', 'pptx' => 'fa-file-powerpoint',
            'zip' => 'fa-file-zipper',
            'txt' => 'fa-file-lines',
            default => 'fa-file',
        };
    }

    /**
     * Bytes as somebody would say them out loud. Kept here rather than in a
     * helper because it is only ever the size of a file on this model.
     */
    public function sizeLabel(): string
    {
        $bytes = (int) $this->file_size;

        if ($bytes <= 0) {
            return '—';
        }

        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1024 * 1024) {
            return rtrim(rtrim(number_format($bytes / 1024, 1), '0'), '.').' KB';
        }

        return rtrim(rtrim(number_format($bytes / (1024 * 1024), 1), '0'), '.').' MB';
    }
}
