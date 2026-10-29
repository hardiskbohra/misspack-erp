<?php

namespace App\Casts;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Keep existing two-place strings stable, but expose a meaningful thousandth.
 * Decimal arithmetic avoids losing large values while formatting a DB decimal.
 */
final class DecimalUpToThree implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) return null;

        $three = (string) BigDecimal::of((string) $value)->toScale(3, RoundingMode::HalfUp);
        return str_ends_with($three, '0') ? substr($three, 0, -1) : $three;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        return $value;
    }
}
