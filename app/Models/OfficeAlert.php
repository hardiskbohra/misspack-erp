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
    ];

    protected $casts = [
        'requires_ack' => 'boolean',
        'meta' => 'array',
    ];

    public function states(): HasMany
    {
        return $this->hasMany(OfficeAlertState::class);
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
