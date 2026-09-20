<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CashflowCategory extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'type', 'color', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function entries()
    {
        return $this->hasMany(CashflowEntry::class, 'category_id');
    }

    public function typeLabel(): string
    {
        return self::typeOptions()[$this->type] ?? Str::headline($this->type);
    }

    public static function typeOptions(): array
    {
        return [
            'income' => 'Income',
            'expense' => 'Expense',
            'transfer' => 'Transfer',
            'tax' => 'Tax',
            'bank_charge' => 'Bank Charge',
            'other' => 'Other',
        ];
    }
}
