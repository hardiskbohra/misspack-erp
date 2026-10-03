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
        'components', 'working_days', 'paid_days',
        'paid_on', 'cashflow_entry_id', 'status',
        'file_path', 'original_name', 'mime_type', 'file_size', 'extension',
        'notes', 'created_by',
    ];

    protected $casts = [
        'components' => 'array',
        'working_days' => 'integer',
        'paid_days' => 'integer',
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

    /**
     * The lines a payslip is normally made of, offered in the form and used to
     * seed a breakdown. They are suggestions, not a closed list: a slip stores
     * the labels it was given, so an office with a different structure types
     * its own and nothing here needs changing.
     */
    public const EARNING_LINES = [
        'Basic', 'House rent allowance', 'Conveyance', 'Special allowance', 'Overtime', 'Bonus',
    ];

    public const DEDUCTION_LINES = [
        'Provident fund', 'ESI', 'Professional tax', 'TDS', 'Advance / loan', 'Other deduction',
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

    /**
     * What the gross was made of — the stored breakdown when there is one, and
     * otherwise a single line saying gross, because a payslip whose lines do not
     * add up to its own total is worse than a short payslip.
     *
     * @return array<int, array{label: string, amount: float}>
     */
    public function earnings(): array
    {
        $lines = $this->lines('earnings');

        if ($lines !== []) {
            return $lines;
        }

        return (float) $this->gross_amount === 0.0
            ? []
            : [['label' => 'Gross salary', 'amount' => (float) $this->gross_amount]];
    }

    /** @return array<int, array{label: string, amount: float}> */
    public function deductionLines(): array
    {
        $lines = $this->lines('deductions');

        if ($lines !== []) {
            return $lines;
        }

        return (float) $this->deductions === 0.0
            ? []
            : [['label' => 'Deductions', 'amount' => (float) $this->deductions]];
    }

    /** Does this slip carry a breakdown, or only its totals? */
    public function hasBreakdown(): bool
    {
        return $this->lines('earnings') !== [] || $this->lines('deductions') !== [];
    }

    /**
     * The lines of one side of the slip, read once and cleaned once: a line with
     * no label or no money is not a line, and a label with money in it is.
     *
     * @return array<int, array{label: string, amount: float}>
     */
    private function lines(string $side): array
    {
        $lines = (array) (($this->components ?? [])[$side] ?? []);
        $clean = [];

        foreach ($lines as $line) {
            $label = trim((string) ($line['label'] ?? ''));
            $amount = round((float) ($line['amount'] ?? 0), 2);

            if ($label === '' || $amount == 0.0) {
                continue;
            }

            $clean[] = ['label' => $label, 'amount' => $amount];
        }

        return $clean;
    }

    /** The loss of pay the two day counts describe, if the slip records them. */
    public function lopDays(): ?int
    {
        if ($this->working_days === null || $this->paid_days === null) {
            return null;
        }

        return max(0, (int) $this->working_days - (int) $this->paid_days);
    }

    public function paidDaysLabel(): string
    {
        return $this->paid_days === null ? '—' : (string) $this->paid_days;
    }

    public function workingDaysLabel(): string
    {
        return $this->working_days === null ? '—' : (string) $this->working_days;
    }

    /**
     * The slip's own number: the person's code and the month it pays for, so
     * two slips can be told apart in an email thread and on paper.
     *
     * It is derived rather than stored on purpose — a stored number is one more
     * thing a correction can leave wrong, and this one cannot drift.
     */
    public function slipNumber(): string
    {
        $code = (string) ($this->user?->employee_code ?: 'EMP-'.str_pad((string) $this->user_id, 4, '0', STR_PAD_LEFT));

        return $code.'/'.$this->period;
    }

    /** The days on which the money for this month was credited, if any. */
    public function paidOnLabel(): string
    {
        return $this->paid_on ? $this->paid_on->format('d M Y') : '—';
    }

    /**
     * What to say when the slip is sent to the person it belongs to.
     *
     * It carries the two facts the person wants (which month, what landed) and
     * where to look — never the figures of the slip itself in a message that
     * travels over WhatsApp. The document stays behind a login; the sentence
     * says it is there.
     */
    public function shareMessage(): string
    {
        return 'Your payslip for '.$this->periodLabel().' is ready — net '
            .\App\Helpers\CommonHelper::amount((float) $this->net_amount, $this->currency)
            .'. Sign in to view, print or download it: '.route('my.salary');
    }

    /** A WhatsApp draft, addressed to this person's mobile. */
    public function whatsappUrl(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?: '';

        if ($digits === '') {
            return null;
        }

        if (strlen($digits) === 10) {
            $digits = '91'.$digits;
        }

        return 'https://wa.me/'.$digits.'?text='.rawurlencode($this->shareMessage());
    }

    /** The same sentence, as an email draft the office can edit before sending. */
    public function mailUrl(?string $email): ?string
    {
        $email = trim((string) $email);

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return 'mailto:'.$email
            .'?subject='.rawurlencode('Payslip — '.$this->periodLabel())
            .'&body='.rawurlencode($this->shareMessage());
    }
}
