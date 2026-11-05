<?php

namespace Tests\Unit;

use App\Models\PurchaseInvoice;
use PHPUnit\Framework\TestCase;

class PurchaseConvertedStateTest extends TestCase
{
    public function test_only_orders_with_a_legacy_link_or_exhausted_converted_amount_are_fully_converted(): void
    {
        foreach ([
            // type, legacy bill id, converted so far, remaining PO amount, expected
            ['order', null, null, null, false],
            ['order', null, '0', '0', false],
            ['order', 0, '0', '10000', false],
            ['order', null, '2500', '7500', false],
            ['order', null, '9999.999', '0.001', false],
            ['order', null, '0.001', '0', true],
            ['order', null, '10000', '0', true],
            ['order', 22, '0', '10000', true],
            ['bill', 22, '10000', '0', false],
            ['bill', null, '10000', '0', false],
        ] as [$type, $link, $converted, $remaining, $expected]) {
            $doc = new PurchaseInvoice(['invoice_type' => $type, 'converted_invoice_id' => $link,
                'converted_amount' => $converted, 'total_amount' => $remaining]);
            $this->assertSame($expected, $doc->isFullyConverted(), json_encode([$type, $link, $converted, $remaining]));
        }
    }
}
