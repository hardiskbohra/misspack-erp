<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class OfficeSetting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    protected $casts = [
        'value' => 'array',
    ];

    public const BRIEFINGS = 'briefings';

    public static function briefingDefaults(): array
    {
        return [
            'enabled' => true,
            'popups' => true,
            'emails' => true,
            'chase_emails' => true,
            'chase_hours' => 4,
            'stale_days' => 2,
            'extra_emails' => '',
            'sources' => [
                'kyc' => true,
                'shipment_exception' => true,
                'shipment_stale' => true,
                'shipment_overdue' => true,
                'digest_in_transit' => true,
                'digest_hold' => true,
                'digest_cashflow' => true,
            ],
        ];
    }

    public static function sourceLabels(): array
    {
        return [
            'kyc' => ['KYC submitted', 'When a client submits KYC — Sales.'],
            'shipment_exception' => ['Hold / delay', 'When a shipment moves to custom hold or delayed — Operations.'],
            'shipment_stale' => ['Silent file', 'Open shipment with no tracking note for the stale window — Operations.'],
            'shipment_overdue' => ['Missed ETA', 'Open shipment past its ETA — Operations.'],
            'digest_in_transit' => ['In-transit digest', 'Daily follow-up list (attention, not critical).'],
            'digest_hold' => ['Hold digest', 'Daily count of files on hold or delayed (attention).'],
            'digest_cashflow' => ['Pending cashflow', 'Daily count of pending ledger rows — Accounts.'],
        ];
    }

    public static function briefings(): array
    {
        $defaults = self::briefingDefaults();

        if (! Schema::hasTable('office_settings')) {
            return $defaults;
        }

        $row = static::query()->find(self::BRIEFINGS);
        $value = is_array($row?->value) ? $row->value : [];
        $sources = array_merge($defaults['sources'], $value['sources'] ?? []);

        return array_merge($defaults, $value, ['sources' => $sources]);
    }

    public static function putBriefings(array $value): void
    {
        static::query()->updateOrCreate(
            ['key' => self::BRIEFINGS],
            ['value' => $value]
        );
    }
}
