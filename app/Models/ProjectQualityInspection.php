<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectQualityInspection extends Model
{
    use HasFactory;

    public const CHECKS = [
        'quantity' => 'Quantity reconciled', 'dimensions' => 'Dimensions and specification checked',
        'material' => 'Material and construction checked', 'artwork' => 'Artwork and colour checked',
        'workmanship' => 'Workmanship checked', 'function' => 'Functional checks completed',
        'packaging' => 'Packaging standard checked', 'evidence' => 'Inspection evidence attached or referenced',
    ];

    protected $fillable = [
        'project_id','project_product_id','batch_reference','inspected_quantity','passed_quantity',
        'rejected_quantity','rework_quantity','result','checklist','notes','evidence_path','evidence_original_name',
        'evidence_mime','evidence_size','evidence_hash','content_hash','inspected_by','inspected_at',
    ];
    protected $casts = [
        'inspected_quantity'=>'decimal:3','passed_quantity'=>'decimal:3','rejected_quantity'=>'decimal:3',
        'rework_quantity'=>'decimal:3','checklist'=>'array','inspected_at'=>'datetime',
    ];

    public function project() { return $this->belongsTo(Project::class); }
    public function product() { return $this->belongsTo(ProjectProduct::class, 'project_product_id'); }
    public function inspector() { return $this->belongsTo(User::class, 'inspected_by'); }

    public function passed(): bool { return in_array($this->result, ['passed','passed_with_observations'], true); }
    public function evidenceUrl(): string { return \Illuminate\Support\Facades\Storage::disk('public')->url($this->evidence_path); }
    public function resultLabel(): string { return ['passed'=>'Passed','passed_with_observations'=>'Passed with observations','failed'=>'Failed','rework_required'=>'Rework required'][$this->result] ?? str($this->result)->headline()->toString(); }
}
