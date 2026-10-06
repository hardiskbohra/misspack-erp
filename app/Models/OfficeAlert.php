<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OfficeAlert extends Model
{
    public const SEVERITY_INFO = 'info';
    public const SEVERITY_ATTENTION = 'attention';
    public const SEVERITY_CRITICAL = 'critical';

    protected $fillable = [
        'event_key', 'fingerprint', 'title', 'body', 'severity', 'requires_ack',
        'team', 'action_url', 'action_label', 'subject_type', 'subject_id', 'meta',
        'acked_at', 'acked_by', 'emailed_at',
    ];

    protected $casts = [
        'requires_ack' => 'boolean',
        'meta' => 'array',
        'acked_at' => 'datetime',
        'emailed_at' => 'datetime',
    ];

    public function states(): HasMany
    {
        return $this->hasMany(OfficeAlertState::class);
    }

    public function ackedBy()
    {
        return $this->belongsTo(User::class, 'acked_by');
    }

    /**
     * Which desks this briefing is for. Null / office = the whole office.
     */
    public static function teamAliases(): array
    {
        return [
            'sales' => ['sales', 'marketing', 'business', 'crm'],
            'operations' => ['operations', 'ops', 'logistics', 'shipping', 'warehouse'],
            'accounts' => ['accounts', 'account', 'finance', 'accounting'],
        ];
    }

    public static function teamLabel(?string $team): string
    {
        return match ($team) {
            'sales' => 'Sales',
            'operations' => 'Operations',
            'accounts' => 'Accounts',
            default => 'Office',
        };
    }

    public function severityLabel(): string
    {
        return match ($this->severity) {
            self::SEVERITY_CRITICAL => 'Needs action',
            self::SEVERITY_ATTENTION => 'Follow up',
            default => 'FYI',
        };
    }
}
