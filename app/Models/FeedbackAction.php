<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * What we are doing about an answer.
 *
 * A detractor answer raises one of these by itself, with an owner and a due
 * date, because the difference between a feedback module and a feedback form is
 * whether anyone is accountable afterwards. A person may also raise one by hand
 * for a complaint worth chasing, a compliment worth repeating, or a client worth
 * winning back.
 *
 * `resolved_at` and `client_notified_at` are separate columns because they are
 * separate facts: work finished inside the building is not the same as the
 * client having been told it was, and only the second one closes the loop.
 */
class FeedbackAction extends Model
{
    use HasFactory;

    public const STATUS_OPEN = 'open';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_DISMISSED = 'dismissed';

    public const TYPE_FIX = 'fix';
    public const TYPE_ACKNOWLEDGE = 'acknowledge';
    public const TYPE_REFERRAL = 'referral';
    public const TYPE_WIN_BACK = 'win_back';

    public const SEVERITY_LOW = 'low';
    public const SEVERITY_NORMAL = 'normal';
    public const SEVERITY_HIGH = 'high';
    public const SEVERITY_CRITICAL = 'critical';

    /** How long the office has to have *started* doing something about it. */
    public const DETRACTOR_DUE_HOURS = 48;

    protected $fillable = [
        'feedback_response_id', 'type', 'severity', 'owner_id', 'due_on', 'status',
        'task_id', 'resolution_note', 'resolved_at', 'client_notified_at', 'created_by',
    ];

    protected $casts = [
        'due_on' => 'date',
        'resolved_at' => 'datetime',
        'client_notified_at' => 'datetime',
    ];

    public function response()
    {
        return $this->belongsTo(FeedbackResponse::class, 'feedback_response_id');
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isOpen(): bool
    {
        return ! in_array($this->status, [self::STATUS_RESOLVED, self::STATUS_DISMISSED], true);
    }

    public function isOverdue(): bool
    {
        return $this->isOpen() && $this->due_on !== null && $this->due_on->isPast();
    }

    public function typeLabel(): string
    {
        return self::typeOptions()[$this->type] ?? Str::headline((string) $this->type);
    }

    public function severityLabel(): string
    {
        return self::severityOptions()[$this->severity] ?? Str::headline((string) $this->severity);
    }

    public function statusLabel(): string
    {
        return self::statusOptions()[$this->status] ?? Str::headline((string) $this->status);
    }

    public function severityTone(): string
    {
        return [
            self::SEVERITY_LOW => 'blue',
            self::SEVERITY_NORMAL => 'purple',
            self::SEVERITY_HIGH => 'orange',
            self::SEVERITY_CRITICAL => 'red',
        ][$this->severity] ?? 'blue';
    }

    public function statusTone(): string
    {
        return [
            self::STATUS_OPEN => 'orange',
            self::STATUS_IN_PROGRESS => 'blue',
            self::STATUS_RESOLVED => 'green',
            self::STATUS_DISMISSED => 'grey',
        ][$this->status] ?? 'grey';
    }

    /** "by Friday" / "10 days overdue" — the phrase the queue reads first. */
    public function dueLabel(): string
    {
        if ($this->due_on === null) {
            return 'No date set';
        }

        if (! $this->isOpen()) {
            return 'Due '.$this->due_on->format('d M Y');
        }

        return $this->due_on->isPast()
            ? $this->due_on->diffInDays(now()).' days overdue'
            : 'Due '.$this->due_on->format('d M Y');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_OPEN, self::STATUS_IN_PROGRESS]);
    }

    public static function typeOptions(): array
    {
        return [
            self::TYPE_FIX => 'Fix the cause',
            self::TYPE_ACKNOWLEDGE => 'Acknowledge and explain',
            self::TYPE_REFERRAL => 'Ask for a referral',
            self::TYPE_WIN_BACK => 'Win the client back',
        ];
    }

    public static function severityOptions(): array
    {
        return [
            self::SEVERITY_LOW => 'Low',
            self::SEVERITY_NORMAL => 'Normal',
            self::SEVERITY_HIGH => 'High',
            self::SEVERITY_CRITICAL => 'Critical',
        ];
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_OPEN => 'Open',
            self::STATUS_IN_PROGRESS => 'In progress',
            self::STATUS_RESOLVED => 'Resolved',
            self::STATUS_DISMISSED => 'Dismissed',
        ];
    }
}
