<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ProjectApprovalVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_approval_request_id', 'version_number', 'project_attachment_id', 'content_hash',
        'submission_notes', 'status', 'submitted_by', 'submitted_at',
        'decided_by_portal_user_id', 'decided_at', 'client_comment',
    ];

    protected $casts = ['version_number'=>'integer','submitted_at'=>'datetime','decided_at'=>'datetime'];

    public function request() { return $this->belongsTo(ProjectApprovalRequest::class, 'project_approval_request_id'); }
    public function attachment() { return $this->belongsTo(ProjectAttachment::class, 'project_attachment_id'); }
    public function submitter() { return $this->belongsTo(User::class, 'submitted_by'); }
    public function clientUser() { return $this->belongsTo(ClientPortalUser::class, 'decided_by_portal_user_id'); }

    public function statusLabel(): string
    {
        return ['pending'=>'Awaiting client','approved'=>'Approved','rejected'=>'Rejected','changes_requested'=>'Changes requested','superseded'=>'Superseded'][$this->status] ?? Str::headline($this->status);
    }
}
