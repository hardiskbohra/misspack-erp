<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectGateEvaluation extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_workflow_id', 'project_product_id', 'gate_key', 'result', 'content_hash',
        'summary', 'details', 'evaluated_by', 'evaluated_at',
    ];

    protected $casts = ['details' => 'array', 'evaluated_at' => 'datetime'];

    public function workflow() { return $this->belongsTo(ProjectWorkflow::class, 'project_workflow_id'); }
    public function product() { return $this->belongsTo(ProjectProduct::class, 'project_product_id'); }
    public function overrides() { return $this->hasMany(ProjectGateOverride::class); }
    public function activeOverride() { return $this->hasOne(ProjectGateOverride::class)->whereNull('revoked_at')->latestOfMany(); }
}
