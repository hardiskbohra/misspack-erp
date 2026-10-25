<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectExceptionEvent extends Model
{
    protected $fillable = [
        'project_exception_id', 'event_type', 'from_status', 'to_status', 'severity',
        'escalation_level', 'note', 'snapshot', 'actor_id', 'occurred_at',
    ];

    protected $casts = ['snapshot' => 'array', 'escalation_level' => 'integer', 'occurred_at' => 'datetime'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Project exception events are immutable.'));
        static::deleting(fn () => throw new \LogicException('Project exception events are immutable.'));
    }

    public function exception() { return $this->belongsTo(ProjectException::class, 'project_exception_id'); }
    public function actor() { return $this->belongsTo(User::class, 'actor_id'); }
}
