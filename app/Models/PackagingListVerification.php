<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PackagingListVerification extends Model
{
    use HasFactory;

    public const STATUS_PASSED = 'passed';
    public const STATUS_WARNING = 'passed_with_warnings';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'packaging_list_id', 'status', 'content_hash', 'automated_checks', 'manual_checks',
        'notes', 'verified_by', 'verified_at',
    ];

    protected $casts = [
        'automated_checks' => 'array',
        'manual_checks' => 'array',
        'verified_at' => 'datetime',
    ];

    public function packagingList()
    {
        return $this->belongsTo(PackagingList::class);
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function passed(): bool
    {
        return in_array($this->status, [self::STATUS_PASSED, self::STATUS_WARNING], true);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PASSED => 'Passed',
            self::STATUS_WARNING => 'Passed with warnings',
            default => 'Failed',
        };
    }
}
