<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class OfficeService extends Model
{
    /**
     * Facility retainers for the office — maid, water, flowers, security —
     * not employees (no login, no payroll) and not purchase vendors.
     */
    public const CLASS_HOUSEKEEPING = 'housekeeping';
    public const CLASS_WATER = 'water';
    public const CLASS_FLOWERS = 'flowers';
    public const CLASS_SECURITY = 'security';
    public const CLASS_PANTRY = 'pantry';
    public const CLASS_WASTE = 'waste';
    public const CLASS_PEST = 'pest_control';
    public const CLASS_OTHER = 'other';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'service_number', 'name', 'service_class', 'contact_name', 'phone', 'email',
        'status', 'retainer_amount', 'retainer_cycle', 'currency',
        'bank_name', 'bank_account_name', 'bank_account_number', 'bank_ifsc', 'upi_id',
        'address', 'notes',
    ];

    protected $casts = [
        'retainer_amount' => 'decimal:2',
    ];

    public function cashflowEntries(): HasMany
    {
        return $this->hasMany(CashflowEntry::class, 'office_service_id');
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim((string) $this->name)) ?: [];
        $letters = '';
        foreach (array_slice($parts, 0, 2) as $part) {
            $letters .= mb_strtoupper(mb_substr($part, 0, 1));
        }

        return $letters !== '' ? $letters : 'OS';
    }

    public function classLabel(): string
    {
        return self::classOptions()[$this->service_class] ?? Str::headline($this->service_class);
    }

    public function statusLabel(): string
    {
        return self::statusOptions()[$this->status] ?? Str::headline($this->status);
    }

    public function cycleLabel(): string
    {
        return self::cycleOptions()[$this->retainer_cycle] ?? Str::headline((string) $this->retainer_cycle);
    }

    public static function classOptions(): array
    {
        return [
            self::CLASS_HOUSEKEEPING => 'Housekeeping',
            self::CLASS_WATER => 'Water',
            self::CLASS_FLOWERS => 'Flowers',
            self::CLASS_SECURITY => 'Security',
            self::CLASS_PANTRY => 'Pantry',
            self::CLASS_WASTE => 'Waste',
            self::CLASS_PEST => 'Pest control',
            self::CLASS_OTHER => 'Other',
        ];
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_INACTIVE => 'Inactive',
        ];
    }

    public static function cycleOptions(): array
    {
        return [
            'monthly' => 'Monthly',
            'weekly' => 'Weekly',
            'on_call' => 'On call',
        ];
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        return $query->when($search, function (Builder $q) use ($search) {
            $q->where(function (Builder $nested) use ($search) {
                $nested->where('name', 'like', "%{$search}%")
                    ->orWhere('service_number', 'like', "%{$search}%")
                    ->orWhere('contact_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        });
    }
}
