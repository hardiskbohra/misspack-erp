<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfficeAlertState extends Model
{
    protected $fillable = [
        'office_alert_id', 'user_id', 'seen_at', 'acked_at', 'snoozed_until', 'popup_at',
    ];

    protected $casts = [
        'seen_at' => 'datetime',
        'acked_at' => 'datetime',
        'snoozed_until' => 'datetime',
        'popup_at' => 'datetime',
    ];

    public function alert(): BelongsTo
    {
        return $this->belongsTo(OfficeAlert::class, 'office_alert_id');
    }
}
