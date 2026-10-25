<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectApprovalOverride extends Model
{
    use HasFactory;

    protected $fillable = ['project_approval_request_id','version_number','reason','approved_by','approved_at','revoked_at','revoked_by','revocation_reason'];
    protected $casts = ['version_number'=>'integer','approved_at'=>'datetime','revoked_at'=>'datetime'];

    public function request() { return $this->belongsTo(ProjectApprovalRequest::class, 'project_approval_request_id'); }
    public function approver() { return $this->belongsTo(User::class, 'approved_by'); }
    public function revoker() { return $this->belongsTo(User::class, 'revoked_by'); }
}
