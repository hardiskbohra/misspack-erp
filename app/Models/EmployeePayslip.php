<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * One month of somebody's pay.
 *
 * The figures are the record; the file is the paper. Both matter and neither is
 * required: an office that pays by bank transfer may never produce a PDF, and a
 * slip with no figures but a scan is still the slip the employee needs. So a
 * payslip is drawn from what is there and the page says which parts exist.
 */
class EmployeePayslip extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_ISSUED = 'issued';

    protected $fillable = [
        'user_id', 'period', 'gross_amount', 'deductions', 'net_amount', 'currency',
        'paid_on', 'cashflow_entry_id', 'status',
        'file_path', 'original_name', 'mime_type', 'file_size', 'extension',
        'notes', 'created_by',
    ];

    protected $casts = [
        'paid_on' => 'date',
        'gross_amount' => 'decimal:2',
        'deductions' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'file_size' => 'integer',
    ];

    public const STATUSES = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_ISSUED => 'Issued',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function entry()
    {
        return $this->belongsTo(CashflowEntry::class, 'cashflow_entry_id');
    }

    /** The month as a period, not as a day: "Mar 2026". */
    public function periodLabel(): string
    {
        $date = $this->periodDate();

        return $date ? $date->format('M Y') : (string) $this->period;
    }

    public function periodDate(): ?Carbon
    {
        if (! $this->period || ! preg_match('/^\d{4}-\d{2}$/', (string) $this->period)) {
            return null;
        }

        return Carbon::createFromFormat('Y-m', (string) $this->period)->startOfMonth();
    }

    public function periodFrom(): string
    {
        return $this->period.'-01';
    }

    public function periodTo(): string
    {
        return $this->periodDate()?->copy()->endOfMonth()->toDateString() ?? $this->period.'-28';
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    public function isIssued(): bool
    {
        return $this->status === self::STATUS_ISSUED;
    }

    public function hasFile(): bool
    {
        return filled($this->file_path);
    }

    public function fileName(): string
    {
        return (string) ($this->original_name ?: 'Payslip '.$this->periodLabel());
    }

    public function fileUrl(): ?string
    {
        return $this->file_path ? Storage::disk('public')->url($this->file_path) : null;
    }

    /** The days on which the money for this month was credited, if any. */
    public function paidOnLabel(): string
    {
        return $this->paid_on ? $this->paid_on->format('d M Y') : '—';
    }
}
