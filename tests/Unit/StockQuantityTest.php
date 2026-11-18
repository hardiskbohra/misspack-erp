<?php

namespace Tests\Unit;

use App\Services\StockQuantity;
use PHPUnit\Framework\TestCase;

class StockQuantityTest extends TestCase
{
    public function test_fixed_point_quantities_do_not_use_binary_floats(): void
    {
        $this->assertSame(100, StockQuantity::fromInput('0.1'));
        $this->assertSame(1001, StockQuantity::fromInput('1.001'));
        $this->assertSame(999999999999999, StockQuantity::fromInput('999999999999.999'));
        $this->assertSame('1.001', StockQuantity::format(1001));
        $this->assertSame('-0.001', StockQuantity::format(-1));
        $this->assertSame('0.000', StockQuantity::format(0));
    }

    public function test_malformed_exponent_negative_and_over_precision_inputs_are_not_rounded(): void
    {
        foreach (['', '-1', '1e3', '1.0001', '1000000000000', 'NaN', '1,000'] as $value) {
            try {
                StockQuantity::fromInput($value);
                $this->fail("Unexpectedly accepted {$value}");
            } catch (\InvalidArgumentException $expected) {
                $this->assertNotSame('', $expected->getMessage());
            }
        }
    }
}
