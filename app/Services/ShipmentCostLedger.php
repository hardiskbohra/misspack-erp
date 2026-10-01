<?php

namespace App\Services;

use App\Models\Shipment;
use App\Models\ShipmentCost;
use Illuminate\Support\Facades\Schema;

/**
 * INR snapshot + money roll-ups for shipment cost heads.
 *
 * Every head is entered in the currency it is actually billed in (a freight
 * invoice may arrive in USD while the CHA bills in INR), so each row keeps an
 * INR value at the rate used on the day. That single number is what the
 * cashflow mirror, the paid-so-far figure and the landed-cost view all use —
 * there is no second conversion anywhere.
 */
class ShipmentCostLedger
{
    public static function available(): bool
    {
        return class_exists(ShipmentCost::class) && Schema::hasTable('shipment_costs');
    }

    /**
     * Fill the INR value of a head from its own amount + rate.
     * INR rows keep their amount; a foreign row without a rate is left at 0
     * and the form asks for the rate instead of inventing one.
     */
    public function recalculateInr(ShipmentCost $cost): void
    {
        $currency = strtoupper((string) ($cost->currency ?: 'INR'));
        $amount = (float) $cost->amount;

        if ($currency === 'INR') {
            $cost->amount_in_inr = round($amount, 2);

            return;
        }

        $rate = (float) $cost->exchange_rate;

        $cost->amount_in_inr = $rate > 0 ? round($amount * $rate, 2) : 0;
    }

    /**
     * Totals for one shipment:
     *   by_currency — sums in the currency each head was billed in
     *   inr         — everything converted at the recorded rates
     *   paid_inr    — only the heads that already left an account
     *   rows        — how many heads were captured
     *
     * @return array{by_currency: array<string, float>, inr: float, paid_inr: float, rows: int}
     */
    public function totals(Shipment $shipment): array
    {
        $costs = $shipment->relationLoaded('costs') ? $shipment->costs : $shipment->costs()->get();

        $byCurrency = [];
        $inr = 0.0;
        $paidInr = 0.0;

        foreach ($costs as $cost) {
            $currency = strtoupper((string) ($cost->currency ?: 'INR'));
            $byCurrency[$currency] = round(($byCurrency[$currency] ?? 0) + (float) $cost->amount, 2);

            $inr += (float) $cost->amount_in_inr;

            if ($cost->isPaid()) {
                $paidInr += (float) $cost->amount_in_inr;
            }
        }

        return [
            'by_currency' => $byCurrency,
            'inr' => round($inr, 2),
            'paid_inr' => round($paidInr, 2),
            'rows' => $costs->count(),
        ];
    }
}
