<?php

namespace App\Models;

use App\Services\RecurrenceVocabulary;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A recurring rule — a standing instruction to pay (or receive) the same money
 * again, and the office's argument about it before it starts.
 *
 * Read `database/migrations/2026_10_07_130000_create_cashflow_recurrence_rules_table.php`
 * first: the columns are explained there, and this class deliberately adds no
 * fact of its own. Everything below is either a **relation**, a **scope** (the
 * queries the screens ask) or a **derived reading** — the count of what has been
 * released, the next date, the progress through the window. None of those are
 * columns, because a stored count beside a log is a second answer that drifts,
 * and this module has exactly one log: the occurrences.
 *
 * The state machine is four words, and it is enforced by `RecurrenceIntake`:
 *
 *     draft ──ask──▶ draft (sent) ──approve──▶ active ⇄ paused
 *       ▲                 │                       │
 *       └──── send back ───┘                       └──▶ ended
 *
 * `draft` is where a rule is born and where it stays until the office approves
 * it; a draft posts nothing, plans nothing and notifies nobody. `active` means
 * the plan is real and every date on it will ask for approval on the day.
 * `paused` is "stop for now" — the plan's undecided tail is withdrawn and comes
 * back on resume. `ended` is final, whether by hand or by running out of dates.
 */
class CashflowRecurrenceRule extends Model
{
    protected $table = 'cashflow_recurrence_rules';

    protected $fillable = [
        'title', 'particular', 'transaction_type', 'amount', 'currency',
        'account_id', 'category_id', 'payment_mode', 'expense_head',
        'related_party_type', 'related_party_name',
        'client_id', 'vendor_id', 'employee_id', 'office_service_id',
        'notes', 'frequency', 'starts_on', 'ends_on', 'occurrence_limit',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'starts_on' => 'date',
        'ends_on' => 'date',
        'occurrence_limit' => 'integer',
        'requested_at' => 'datetime',
        'decided_at' => 'datetime',
        'paused_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    /* --------------------------------------------------------------- relations */

    /** The plan: one row per date this rule has asked about, decisions included. */
    public function occurrences(): HasMany
    {
        return $this->hasMany(CashflowRecurrenceOccurrence::class, 'cashflow_recurrence_rule_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(CashflowAccount::class, 'account_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(CashflowCategory::class, 'category_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    /**
     * The person a salary rule pays. A real link, like the ledger's own
     * `employee_id`, so the employee's page can find their recurring salary.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function officeService(): BelongsTo
    {
        return $this->belongsTo(OfficeService::class, 'office_service_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /* ----------------------------------------------------------------- states */

    public function isDraft(): bool
    {
        return $this->status === RecurrenceVocabulary::STATUS_DRAFT;
    }

    public function isActive(): bool
    {
        return $this->status === RecurrenceVocabulary::STATUS_ACTIVE;
    }

    public function isPaused(): bool
    {
        return $this->status === RecurrenceVocabulary::STATUS_PAUSED;
    }

    public function isEnded(): bool
    {
        return $this->status === RecurrenceVocabulary::STATUS_ENDED;
    }

    /** A draft that has been sent and is waiting on somebody's yes or no. */
    public function isWaitingApproval(): bool
    {
        return $this->isDraft() && $this->requested_at !== null;
    }

    /** A draft the office sent back — it has a decision and is editable again. */
    public function wasSentBack(): bool
    {
        return $this->isDraft() && $this->requested_at === null && $this->decided_at !== null;
    }

    /**
     * The state a reader sees, which is the status column plus the ask: a draft
     * nobody has picked up reads differently from one waiting on an answer.
     */
    public function stateKey(): string
    {
        if ($this->isWaitingApproval()) {
            return 'approval';
        }

        return $this->status;
    }

    public function stateLabel(): string
    {
        $extra = $this->wasSentBack() ? 'Sent back' : null;

        return $extra ?? (RecurrenceVocabulary::STATE_LABELS[$this->stateKey()] ?? 'Draft');
    }

    /** A tone for the badge: ok / warn / info / off, resolved by the sheet. */
    public function stateTone(): string
    {
        return RecurrenceVocabulary::STATE_TONES[$this->stateKey()] ?? 'off';
    }

    /* ------------------------------------------------------------- the reading */

    public function frequencyLabel(): string
    {
        return RecurrenceVocabulary::FREQUENCIES[$this->frequency] ?? $this->frequency;
    }

    /**
     * The cadence as a sentence, anchored where the schedule is anchored: the
     * next date is derived from `starts_on`'s day, so the label says the day.
     */
    public function cadenceLabel(): string
    {
        if (! $this->starts_on) {
            return $this->frequencyLabel();
        }

        return RecurrenceVocabulary::cadence($this->frequency, $this->starts_on);
    }

    /** "12 occurrences" / "until 31 Dec 2026" / both — or "no end date". */
    public function windowLabel(): string
    {
        $parts = [];

        if ($this->occurrence_limit) {
            $parts[] = $this->occurrence_limit.' occurrence'.($this->occurrence_limit === 1 ? '' : 's');
        }

        if ($this->ends_on) {
            $parts[] = 'until '.$this->ends_on->format('d M Y');
        }

        return $parts === [] ? 'No end date' : implode(' · ', $parts);
    }

    /** Whose money this is, resolved the way the ledger resolves an entry. */
    public function partyLabel(): string
    {
        return (string) ($this->client?->company_name
            ?? $this->vendor?->vendor_name
            ?? $this->employee?->name
            ?? $this->officeService?->name
            ?? $this->related_party_name
            ?? $this->expense_head
            ?? '');
    }

    /**
     * How far the plan has got: how many occurrences have been decided out of
     * how many the plan currently holds. Both are counts of the log — the rule
     * stores neither.
     */
    public function releasedCount(): int
    {
        return (int) ($this->relationLoaded('occurrences')
            ? $this->occurrences->where('status', RecurrenceVocabulary::OCCURRENCE_APPROVED)->count()
            : $this->occurrences()->where('status', RecurrenceVocabulary::OCCURRENCE_APPROVED)->count());
    }

    public function plannedCount(): int
    {
        return (int) ($this->relationLoaded('occurrences')
            ? $this->occurrences->whereIn('status', RecurrenceVocabulary::OCCURRENCE_OPEN)->count()
            : $this->occurrences()->whereIn('status', RecurrenceVocabulary::OCCURRENCE_OPEN)->count());
    }

    /** The next date the office will be asked about, if the plan holds one. */
    public function nextDate(): ?\Carbon\CarbonInterface
    {
        $occurrences = $this->relationLoaded('occurrences')
            ? $this->occurrences
            : $this->occurrences()->get();

        return $occurrences
            ->whereIn('status', RecurrenceVocabulary::OCCURRENCE_OPEN)
            ->sortBy('effective_date')
            ->first()
            ?->effective_date;
    }

    /* ----------------------------------------------------------------- scopes */

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        return $query->when($search, function (Builder $q) use ($search) {
            $q->where(function (Builder $nested) use ($search) {
                $nested->where('title', 'like', "%{$search}%")
                    ->orWhere('particular', 'like', "%{$search}%")
                    ->orWhere('related_party_name', 'like', "%{$search}%")
                    ->orWhere('expense_head', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas('client', fn (Builder $client) => $client->where('company_name', 'like', "%{$search}%"))
                    ->orWhereHas('vendor', fn (Builder $vendor) => $vendor->where('vendor_name', 'like', "%{$search}%"))
                    ->orWhereHas('employee', fn (Builder $employee) => $employee->where('name', 'like', "%{$search}%"));
            });
        });
    }

    /** The chips. `approval` is a state of the ask, not of the column. */
    public function scopeInState(Builder $query, string $state): Builder
    {
        return match ($state) {
            'approval' => $query->where('status', RecurrenceVocabulary::STATUS_DRAFT)->whereNotNull('requested_at'),
            'running' => $query->where('status', RecurrenceVocabulary::STATUS_ACTIVE),
            'paused' => $query->where('status', RecurrenceVocabulary::STATUS_PAUSED),
            'drafts' => $query->where('status', RecurrenceVocabulary::STATUS_DRAFT)->whereNull('requested_at'),
            'ended' => $query->where('status', RecurrenceVocabulary::STATUS_ENDED),
            default => $query,
        };
    }

    public function scopeInFrequency(Builder $query, ?string $frequency): Builder
    {
        return $query->when($frequency, fn (Builder $q) => $q->where('frequency', $frequency));
    }

    /* The four orders the list can be read in — each one a scope, because the
       select's keys and these names are the same contract (see
       `RecurrenceFilters::SORT_SCOPES`). */

    public function scopeRecentFirst(Builder $query): Builder
    {
        return $query->orderByDesc('created_at')->orderByDesc('id');
    }

    public function scopeOldestFirst(Builder $query): Builder
    {
        return $query->orderBy('created_at')->orderBy('id');
    }

    public function scopeTitleOrder(Builder $query): Builder
    {
        return $query->orderBy('title');
    }

    public function scopeAmountOrder(Builder $query): Builder
    {
        return $query->orderByDesc('amount');
    }

    /**
     * What will ask for approval over a window — the plan, not the rule: an
     * active rule with nothing left in the window does not appear.
     */
    public function scopeAskingBetween(Builder $query, \Carbon\CarbonInterface $from, \Carbon\CarbonInterface $to): Builder
    {
        return $query->where('status', RecurrenceVocabulary::STATUS_ACTIVE)
            ->whereHas('occurrences', fn (Builder $occurrence) => $occurrence
                ->whereIn('status', RecurrenceVocabulary::OCCURRENCE_OPEN)
                ->whereBetween('effective_date', [$from->toDateString(), $to->toDateString()]));
    }
}
