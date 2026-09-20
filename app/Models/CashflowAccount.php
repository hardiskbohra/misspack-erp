<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CashflowAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_name', 'account_type', 'bank_name', 'account_number', 'ifsc_code', 'branch',
        'currency', 'opening_balance', 'current_balance', 'is_active', 'notes',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'current_balance' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function entries()
    {
        return $this->hasMany(CashflowEntry::class, 'account_id');
    }

    public function typeLabel(): string
    {
        return self::typeOptions()[$this->account_type] ?? Str::headline($this->account_type);
    }

    public static function typeOptions(): array
    {
        return [
            'current' => 'Current Account',
            'saving' => 'Saving Account',
            'cash' => 'Cash Account',
            'credit_card' => 'Credit Card',
            'loan' => 'Loan Account',
        ];
    }
}
