<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShipmentCost extends Model
{
    use HasFactory;

    public const HEAD_FREIGHT = 'freight';
    public const HEAD_INSURANCE = 'insurance';
    public const HEAD_CUSTOMS_DUTY = 'customs_duty';
    public const HEAD_CHA = 'cha';
    public const HEAD_LAST_MILE = 'last_mile';
    public const HEAD_DEMURRAGE = 'demurrage';
    public const HEAD_OTHER = 'other';

    protected $fillable = [
        'shipment_id', 'cost_head', 'label', 'amount', 'currency', 'exchange_rate', 'amount_in_inr',
        'vendor_id', 'incurred_on', 'document_number', 'paid_account_id', 'payment_mode', 'paid_on',
        'cashflow_entry_id', 'notes', 'sort_order', 'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'exchange_rate' => 'decimal:6',
        'amount_in_inr' => 'decimal:2',
        'incurred_on' => 'date',
        'paid_on' => 'date',
    ];

    public static function headOptions(): array
    {
        return [
            self::HEAD_FREIGHT => 'Freight',
            self::HEAD_INSURANCE => 'Insurance',
            self::HEAD_CUSTOMS_DUTY => 'Customs duty',
            self::HEAD_CHA => 'CHA / Clearing',
            self::HEAD_LAST_MILE => 'Last mile',
            self::HEAD_DEMURRAGE => 'Demurrage / Detention',
            self::HEAD_OTHER => 'Other',
        ];
    }

    public function shipment()
    {
        return $this->belongsTo(Shipment::class);
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function paidAccount()
    {
        return $this->belongsTo(CashflowAccount::class, 'paid_account_id');
    }

    public function headLabel(): string
    {
        if ($this->cost_head === self::HEAD_OTHER && trim((string) $this->label) !== '') {
            return trim((string) $this->label);
        }

        return self::headOptions()[$this->cost_head] ?? ucfirst((string) $this->cost_head);
    }

    /**
     * A head is "paid" once it points at the account the money left from —
     * that is also the trigger for the INR cashflow mirror.
     */
    public function isPaid(): bool
    {
        return (int) $this->paid_account_id > 0;
    }

    public function amountLabel(): string
    {
        return Shipment::formatAmount($this->currency, $this->amount);
    }
}
