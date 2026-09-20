<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CashflowMasterOption extends Model
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
            'currency' => 'Currency',
            'accounting_status' => 'Accounting Status',
            'payment_mode' => 'Payment Mode',
            'related_party_type' => 'Related Party Type',
            'account_type' => 'Account Type',
            'category_type' => 'Category Type',
        ];
    }
}
