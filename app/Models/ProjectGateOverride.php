<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectGateOverride extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_gate_evaluation_id', 'reason', 'expected_resolution_date', 'risk_snapshot',
        'approved_by', 'approved_at', 'revoked_at', 'revoked_by', 'revocation_reason',
    ];

    protected $casts = [
        'expected_resolution_date' => 'date', 'risk_snapshot' => 'array',
        'approved_at' => 'datetime', 'revoked_at' => 'datetime',
    ];

    public function evaluation() { return $this->belongsTo(ProjectGateEvaluation::class, 'project_gate_evaluation_id'); }
    public function approver() { return $this->belongsTo(User::class, 'approved_by'); }
    public function revoker() { return $this->belongsTo(User::class, 'revoked_by'); }
}
