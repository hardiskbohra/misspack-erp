<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public $timestamps = true;

    protected $fillable = [
        'event','outcome','actor_type','actor_id','actor_name','actor_identifier','auditable_type','auditable_id',
        'route_name','method','url','response_status','ip_address','user_agent','changed_fields','context','occurred_at',
    ];

    protected $casts = ['changed_fields' => 'array', 'context' => 'array', 'occurred_at' => 'datetime'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Audit records are immutable.'));
        static::deleting(fn () => throw new \LogicException('Audit records cannot be deleted.'));
    }

    public function outcomeLabel(): string { return ucfirst($this->outcome); }
}
