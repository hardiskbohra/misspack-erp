<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Task extends Model
{
    use HasFactory;

    public const STATUS_BACKLOG = 'backlog';
    public const STATUS_NEW_REQUEST = 'new_request';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';

    // Backward compatibility for older code that used STATUS_PENDING.
    public const STATUS_PENDING = self::STATUS_NEW_REQUEST;

    public const PRIORITY_LOW = 'low';
    public const PRIORITY_MEDIUM = 'medium';
    public const PRIORITY_HIGH = 'high';
    public const PRIORITY_URGENT = 'urgent';

    protected $fillable = [
        'title',
        'description',
        'due_date',
        'priority',
        'status',
        'category',
        'image_url',
        'sort_order',
        'assignee_id',
        'created_by',
        'completed_at',
    ];

    protected $casts = [
        'due_date' => 'date',
        'completed_at' => 'datetime',
        'sort_order' => 'integer',
    ];

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeIncomplete(Builder $query): Builder
    {
        return $query->where('status', '!=', self::STATUS_COMPLETED);
    }

    public function scopeAssignedTo(Builder $query, ?int $userId): Builder
    {
        return $query->when($userId, function (Builder $q) use ($userId) {
            $q->where('assignee_id', $userId);
        });
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        return $query->when($search, function (Builder $q) use ($search) {
            $q->where(function (Builder $nested) use ($search) {
                $nested->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%");
            });
        });
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function statusLabel(): string
    {
        return self::statusOptions()[$this->status] ?? Str::headline((string) $this->status);
    }

    public function priorityLabel(): string
    {
        return self::priorityOptions()[$this->priority] ?? Str::headline((string) $this->priority);
    }

    public function statusColorClass(): string
    {
        return str_replace('_', '-', (string) $this->status);
    }

    public function priorityColorClass(): string
    {
        return str_replace('_', '-', (string) $this->priority);
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_BACKLOG => 'Backlog',
            self::STATUS_NEW_REQUEST => 'New Request',
            self::STATUS_IN_PROGRESS => 'In Progress',
            self::STATUS_COMPLETED => 'Complete',
        ];
    }

    public static function priorityOptions(): array
    {
        return [
            self::PRIORITY_LOW => 'Low',
            self::PRIORITY_MEDIUM => 'Medium',
            self::PRIORITY_HIGH => 'High',
            self::PRIORITY_URGENT => 'Urgent',
        ];
    }

    public static function categoryOptions(): array
    {
        return [
            'Sampling' => 'Sampling',
            'ERP' => 'ERP',
            'Sales' => 'Sales',
            'Purchase' => 'Purchase',
            'Shipment' => 'Shipment',
            'Finance' => 'Finance',
            'Internal' => 'Internal',
            'General' => 'General',
        ];
    }
}
