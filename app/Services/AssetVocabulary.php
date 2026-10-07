<?php

namespace App\Services;

/**
 * The words the assets module is allowed to say.
 *
 * Every status, condition, method and maintenance kind in this module is one of
 * these — a select offers them, a badge tones them, a filter understands them,
 * and the check reads the same lists. A string typed anywhere else is a state
 * the rest of the module does not know about, which is how a register ends up
 * half in "In use" and half in "in-use".
 *
 * The **tones** are the shared `core-badge` tones that exist in `core.css`
 * (`success`, `warning`, `danger`, `info`, `neutral`): a module may choose which
 * of them a state wears, and may not invent a sixth.
 */
final class AssetVocabulary
{
    /* ------------------------------------------------------------------ status */

    public const STATUS_IN_USE = 'in_use';
    public const STATUS_SPARE = 'spare';
    public const STATUS_MAINTENANCE = 'maintenance';
    public const STATUS_DISPOSED = 'disposed';

    public const STATUSES = [
        self::STATUS_IN_USE => 'In use',
        self::STATUS_SPARE => 'In store / spare',
        self::STATUS_MAINTENANCE => 'Under repair',
        self::STATUS_DISPOSED => 'Disposed',
    ];

    public const STATUS_TONES = [
        self::STATUS_IN_USE => 'success',
        self::STATUS_SPARE => 'info',
        self::STATUS_MAINTENANCE => 'warning',
        self::STATUS_DISPOSED => 'neutral',
    ];

    /** The chips, in the order the register reads them: everything, then the states. */
    public const STATE_LABELS = [
        'everything' => 'Everything',
        self::STATUS_IN_USE => 'In use',
        self::STATUS_SPARE => 'In store',
        self::STATUS_MAINTENANCE => 'Under repair',
        self::STATUS_DISPOSED => 'Disposed',
    ];

    /* --------------------------------------------------------------- condition */

    public const CONDITION_NEW = 'new';
    public const CONDITION_GOOD = 'good';
    public const CONDITION_FAIR = 'fair';
    public const CONDITION_POOR = 'poor';
    public const CONDITION_DAMAGED = 'damaged';

    public const CONDITIONS = [
        self::CONDITION_NEW => 'New',
        self::CONDITION_GOOD => 'Good',
        self::CONDITION_FAIR => 'Fair',
        self::CONDITION_POOR => 'Poor',
        self::CONDITION_DAMAGED => 'Damaged',
    ];

    public const CONDITION_TONES = [
        self::CONDITION_NEW => 'success',
        self::CONDITION_GOOD => 'success',
        self::CONDITION_FAIR => 'warning',
        self::CONDITION_POOR => 'warning',
        self::CONDITION_DAMAGED => 'danger',
    ];

    /* -------------------------------------------------------------- depreciation */

    public const METHOD_STRAIGHT_LINE = 'straight_line';
    public const METHOD_WDV = 'wdv';
    public const METHOD_NONE = 'none';

    public const METHODS = [
        self::METHOD_STRAIGHT_LINE => 'Straight line (SLM)',
        self::METHOD_WDV => 'Written down value (WDV)',
        self::METHOD_NONE => 'Not depreciated',
    ];

    /** What the class defaults to when a category says nothing (5% is the norm). */
    public const DEFAULT_RESIDUAL_PERCENT = 5.0;

    /** The Indian financial year the schedules are cut into: 1 April – 31 March. */
    public const FINANCIAL_YEAR_START_MONTH = 4;

    /* ---------------------------------------------------------------- maintenance */

    public const KIND_SERVICE = 'service';
    public const KIND_REPAIR = 'repair';
    public const KIND_AMC = 'amc';
    public const KIND_CALIBRATION = 'calibration';
    public const KIND_UPGRADE = 'upgrade';

    public const KINDS = [
        self::KIND_SERVICE => 'Preventive service',
        self::KIND_REPAIR => 'Repair',
        self::KIND_AMC => 'AMC / contract',
        self::KIND_CALIBRATION => 'Calibration',
        self::KIND_UPGRADE => 'Upgrade',
    ];

    public const KIND_TONES = [
        self::KIND_SERVICE => 'info',
        self::KIND_REPAIR => 'warning',
        self::KIND_AMC => 'neutral',
        self::KIND_CALIBRATION => 'info',
        self::KIND_UPGRADE => 'success',
    ];

    /**
     * A percentage, said the way the register says it — two decimals, no symbol.
     *
     * It lives here for the same reason `CommonHelper::amount()` holds the money
     * format: a view that runs `number_format()` itself is a second definition of
     * what a percentage looks like, and the second one is the one that drifts.
     * (`tools/checks/cost-check.cjs` enforces the mirror rule for money.)
     */
    public static function percentLabel(float $percent, int $decimals = 2): string
    {
        return number_format($percent, $decimals);
    }

    /**
     * The same percentage in prose — trailing zeros dropped, because "5% left" is
     * how a sentence says it and "5.00%" is how a column says it.
     *
     * It trims the column form rather than calling `number_format()` again: two
     * calls would be two definitions, and this one would be the one that drifts.
     * (`rtrim` stops at the decimal point, so the integer's own zeros survive.)
     */
    public static function percentLabelTrimmed(float $percent): string
    {
        return rtrim(rtrim(self::percentLabel($percent), '0'), '.');
    }

    /* --------------------------------------------------------------- the readers */

    public static function statusLabel(string $status): string
    {
        return self::STATUSES[$status] ?? 'In use';
    }

    public static function statusTone(string $status): string
    {
        return self::STATUS_TONES[$status] ?? 'neutral';
    }

    public static function conditionLabel(string $condition): string
    {
        return self::CONDITIONS[$condition] ?? $condition;
    }

    public static function conditionTone(string $condition): string
    {
        return self::CONDITION_TONES[$condition] ?? 'neutral';
    }

    public static function methodLabel(string $method): string
    {
        return self::METHODS[$method] ?? self::METHODS[self::METHOD_STRAIGHT_LINE];
    }

    public static function kindLabel(string $kind): string
    {
        return self::KINDS[$kind] ?? 'Service';
    }

    public static function kindTone(string $kind): string
    {
        return self::KIND_TONES[$kind] ?? 'neutral';
    }

    public static function hasStatus(?string $status): bool
    {
        return $status !== null && array_key_exists($status, self::STATUSES);
    }

    public static function hasMethod(?string $method): bool
    {
        return $method !== null && array_key_exists($method, self::METHODS);
    }

    public static function hasCondition(?string $condition): bool
    {
        return $condition !== null && array_key_exists($condition, self::CONDITIONS);
    }

    public static function hasKind(?string $kind): bool
    {
        return $kind !== null && array_key_exists($kind, self::KINDS);
    }

    /** Whether a status means the asset is still on the company's books. */
    public static function onBooks(?string $status): bool
    {
        return $status !== self::STATUS_DISPOSED;
    }
}
