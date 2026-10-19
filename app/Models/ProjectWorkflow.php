<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\ProjectFinancialClosure;

class ProjectWorkflow extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id', 'template_key', 'template_version', 'status', 'calculated_stage',
        'calculated_progress', 'reported_stage', 'reported_progress', 'reported_reason',
        'reported_by', 'reported_at', 'commercial_sales_invoice_id',
        'advance_percentage_override', 'started_by', 'started_at',
    ];

    protected $casts = [
        'calculated_progress' => 'integer',
        'reported_progress' => 'integer',
        'advance_percentage_override' => 'decimal:2',
        'reported_at' => 'datetime',
        'started_at' => 'datetime',
    ];

    public function project() { return $this->belongsTo(Project::class); }
    public function steps() { return $this->hasMany(ProjectWorkflowStep::class)->orderBy('sort_order')->orderBy('id'); }
    public function evaluations() { return $this->hasMany(ProjectGateEvaluation::class)->latest('evaluated_at')->latest('id'); }
    public function approvals() { return $this->hasMany(ProjectApprovalRequest::class)->latest('requested_at')->latest('id'); }
    public function financialClosures() { return $this->hasMany(ProjectFinancialClosure::class)->latest('closed_at')->latest('id'); }
    public function reporter() { return $this->belongsTo(User::class, 'reported_by'); }
    public function starter() { return $this->belongsTo(User::class, 'started_by'); }
    public function commercialInvoice() { return $this->belongsTo(SalesInvoice::class, 'commercial_sales_invoice_id'); }

    public function stageLabel(): string
    {
        return \App\Services\ProjectWorkflowExecution::stageOptions()[$this->calculated_stage] ?? str($this->calculated_stage)->headline()->toString();
    }
}
