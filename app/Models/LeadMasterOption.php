<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class LeadMasterOption extends Model
{
    use HasFactory;

    protected $fillable = [
        'group', 'key', 'label', 'color', 'sort_order', 'is_active', 'meta',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'meta' => 'array',
    ];

    public function groupLabel(): string
    {
        return self::groupOptions()[$this->group] ?? Str::headline($this->group);
    }

    public static function groupOptions(): array
    {
        return [
            'lead_status' => 'Lead Status',
            'lead_source' => 'Lead Source',
            'lead_priority' => 'Lead Priority',
            'finish' => 'Finish',
            'printing' => 'Printing',
            'currency' => 'Currency',
            'quote_status' => 'Vendor Quote Status',
            'incoterm' => 'Incoterm',
            'capacity_unit' => 'Capacity Unit',
        ];
    }
}
