<?php

namespace App\Models;

use App\Services\RecurrenceVocabulary;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One date on a rule's plan, and the office's decision about it.
 *
 * The row is the **ask**: "on 5 November this rule wants to pay ₹40,000 to
 * Shivalik Complex — yes or no?". It is written when the rule is approved (and
 * topped up as the plan is worked through), it is decided **on or after** its
 * effective date, and answering yes posts the ledger entry and keeps the entry's
 * id here. Read the migration for the four statuses and the reason the row
 * carries no amount of its own.
 *
 * "Due", "overdue" and "days late" are **read from today's date**, never stored:
 * a row that says `overdue` is wrong by tomorrow morning, and the office would
 * be reading yesterday's alarm. This is the same rule the feedback module
 * applies to a score's band.
 */
class CashflowRecurrenceOccurrence extends Model
{
    protected $table = 'cashflow_recurrence_occurrences';

    protected $fillable = [
        'cashflow_recurrence_rule_id', 'sequence', 'effective_date', 'status',
        'cashflow_entry_id',
    ];

    protected $casts = [
        'sequence' => 'integer',
        'effective_date' => 'date',
        'decided_at' => 'datetime',
        'notified_at' => 'datetime',
    ];

    /* --------------------------------------------------------------- relations */

    public function rule(): BelongsTo
    {
        return $this->belongsTo(CashflowRecurrenceRule::class, 'cashflow_recurrence_rule_id');
    }

    /**
     * The ledger entry this occurrence posted. The one-way mirror: an approval
     * writes it, nothing edits it from here, and an occurrence that was never
     * approved has none.
     */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(CashflowEntry::class, 'cashflow_entry_id');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /* ----------------------------------------------------------------- states */

    public function isPending(): bool
    {
        return $this->status === RecurrenceVocabulary::OCCURRENCE_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === RecurrenceVocabulary::OCCURRENCE_APPROVED;
    }

    public function isSkipped(): bool
    {
        return $this->status === RecurrenceVocabulary::OCCURRENCE_SKIPPED;
    }

    public function isCancelled(): bool
    {
        return $this->status === RecurrenceVocabulary::OCCURRENCE_CANCELLED;
    }

    /** Still on the table — the plan holds it and nobody has decided. */
    public function isOpen(): bool
    {
        return $this->isPending();
    }

    /**
     * The day has come: this one may be decided now.
     *
     * The comparison is inclusive on purpose. On the effective date the office
     * is asked; the day *before* is not a quieter time to answer, because the
     * approval is the whole point of that date — the notification goes out when
     * the money is due, and the answer belongs to the same moment.
     */
    public function isDue(?\Carbon\CarbonInterface $today = null): bool
    {
        $today = $today ?: now();

        return $this->isPending()
            && $this->effective_date !== null
            && $this->effective_date->toDateString() <= $today->toDateString();
    }

    public function isOverdue(?\Carbon\CarbonInterface $today = null): bool
    {
        $today = $today ?: now();

        return $this->isPending()
            && $this->effective_date !== null
            && $this->effective_date->toDateString() < $today->toDateString();
    }

    /** "3 days late" — a reading of two dates, not a stored word. */
    public function latenessLabel(?\Carbon\CarbonInterface $today = null): ?string
    {
        if (! $this->isOverdue($today)) {
            return null;
        }

        $days = $this->effective_date->diffInDays($today ?: now());

        return $days === 1 ? '1 day late' : $days.' days late';
    }

    public function statusLabel(): string
    {
        return RecurrenceVocabulary::OCCURRENCE_LABELS[$this->status] ?? 'Planned';
    }

    public function statusTone(): string
    {
        return RecurrenceVocabulary::OCCURRENCE_TONES[$this->status] ?? 'neutral';
    }

    /**
     * The cell's own reading of a date: a pending date is not a warning until
     * it is due, and not overdue-coloured until it is late. The two decided
     * states keep the vocabulary's own label and tone, so the one place that
     * knows what a state is called stays the one place that names it.
     */
    public function cellLabel(?\Carbon\CarbonInterface $today = null): string
    {
        if (! $this->isPending()) {
            return $this->statusLabel();
        }

        if ($this->isOverdue($today)) {
            return 'Overdue';
        }

        return $this->isDue($today) ? 'Due today' : 'Waiting for its date';
    }

    public function cellTone(?\Carbon\CarbonInterface $today = null): string
    {
        if (! $this->isPending()) {
            return $this->statusTone();
        }

        if ($this->isOverdue($today)) {
            return 'danger';
        }

        return $this->isDue($today) ? 'warning' : 'neutral';
    }

    /** What this date is worth: the posted entry's amount, else the rule's. */
    public function moneyAmount(): float
    {
        if ($this->cashflow_entry_id && $this->relationLoaded('entry') && $this->entry) {
            return $this->entry->amount();
        }

        return (float) ($this->relationLoaded('rule') && $this->rule ? $this->rule->amount : 0);
    }

    public function currency(): string
    {
        return (string) ($this->relationLoaded('rule') && $this->rule ? $this->rule->currency : 'INR');
    }

    /* ----------------------------------------------------------------- scopes */

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', RecurrenceVocabulary::OCCURRENCE_PENDING);
    }

    /** Everything the plan still holds, oldest first — the order it is worked in. */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', RecurrenceVocabulary::OCCURRENCE_OPEN);
    }

    /** The asks that are allowed to be answered now: due today or already late. */
    public function scopeDueBy(Builder $query, \Carbon\CarbonInterface $date): Builder
    {
        return $query->pending()->whereDate('effective_date', '<=', $date->toDateString());
    }

    /** The ones nobody has been told about yet. Notified is a fact, not a count. */
    public function scopeUnnotified(Builder $query): Builder
    {
        return $query->pending()->whereNull('notified_at');
    }

    public function scopePlanOrder(Builder $query): Builder
    {
        return $query->orderBy('effective_date')->orderBy('sequence');
    }
}
