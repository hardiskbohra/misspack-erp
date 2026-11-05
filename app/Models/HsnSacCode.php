<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One HSN (goods) or SAC (services) code with the GST rate the business
 * currently charges for it.
 *
 * Scope is deliberately single-rate-current: invoices copy the rate onto the
 * line at save time, so a changed table never re-prices history, and a rate
 * revision is a Settings edit, not a data migration. When a rate notification
 * changes, edit the row — the form stops offering the old rate from that save
 * onward, which is exactly the behaviour a rate master is for.
 */
class HsnSacCode extends Model
{
    protected $fillable = [
        'code', 'kind', 'description', 'gst_rate', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'gst_rate' => 'float',
        'is_active' => 'bool',
        'sort_order' => 'int',
    ];

    /** The codes the invoice forms may offer, in pick order. */
    public static function optionsForForms(): array
    {
        return static::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get(['code', 'kind', 'description', 'gst_rate'])
            ->map(fn (self $row) => [
                'code' => $row->code,
                'kind' => $row->kind,
                'description' => $row->description,
                'gst_rate' => $row->gst_rate,
            ])
            ->all();
    }

    /** Look up the rate for a code, or null when the code is not mastered. */
    public static function rateFor(?string $code): ?float
    {
        if (! $code) {
            return null;
        }

        $row = static::query()->where('code', $code)->where('is_active', true)->first();

        return $row ? $row->gst_rate : null;
    }

    /** Codes are digits, 4 to 8 long — upper layers normalise before save. */
    public static function normaliseCode(?string $code): ?string
    {
        $cleaned = preg_replace('/\D/', '', (string) $code);

        return $cleaned === '' ? null : substr($cleaned, 0, 8);
    }
}
