<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ProjectApprovalRequest extends Model
{
    use HasFactory;

    public const TYPE_ARTWORK = 'artwork';
    public const TYPE_PPS = 'pps';

    protected $fillable = [
        'project_workflow_id', 'project_id', 'project_product_id', 'approval_type', 'title',
        'instructions', 'status', 'current_version_number', 'response_due_at', 'requested_by',
        'requested_at', 'resolved_at', 
    ];

    protected $casts = [
        'current_version_number' => 'integer', 'response_due_at' => 'datetime',
        'requested_at' => 'datetime', 'resolved_at' => 'datetime',
    ];

    public function workflow() { return $this->belongsTo(ProjectWorkflow::class, 'project_workflow_id'); }
    public function project() { return $this->belongsTo(Project::class); }
    public function product() { return $this->belongsTo(ProjectProduct::class, 'project_product_id'); }
    public function versions() { return $this->hasMany(ProjectApprovalVersion::class)->orderByDesc('version_number'); }
    public function currentVersion() { return $this->hasOne(ProjectApprovalVersion::class)->latestOfMany('version_number'); }
    public function requester() { return $this->belongsTo(User::class, 'requested_by'); }
    public function overrides() { return $this->hasMany(ProjectApprovalOverride::class)->latest('approved_at')->latest('id'); }

    public function activeOverrideRecord(): ?ProjectApprovalOverride
    {
        return $this->relationLoaded('overrides')
            ? $this->overrides->first(fn ($override) => $override->version_number === $this->current_version_number && ! $override->revoked_at)
            : $this->overrides()->where('version_number', $this->current_version_number)->whereNull('revoked_at')->first();
    }

    public function isOverdue(): bool
    {
        return $this->status === 'open' && $this->response_due_at && $this->response_due_at->isPast();
    }

    public function hasActiveOverride(): bool
    {
        return $this->activeOverrideRecord() !== null;
    }

    public function effectiveStatus(): string
    {
        return $this->hasActiveOverride() ? 'overridden' : $this->status;
    }

    public function statusLabel(): string
    {
        return ['open'=>'Awaiting client','approved'=>'Approved','rejected'=>'Rejected','changes_requested'=>'Changes requested','revoked'=>'Revoked','overridden'=>'Manager override'][$this->effectiveStatus()] ?? Str::headline($this->effectiveStatus());
    }

    public function typeLabel(): string
    {
        return self::typeOptions()[$this->approval_type] ?? Str::headline($this->approval_type);
    }

    public static function typeOptions(): array
    {
        return [self::TYPE_ARTWORK => 'Artwork approval', self::TYPE_PPS => 'PPS approval'];
    }
}
