<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DashboardPeriod
{
    public function fromRequest(Request $request): array
    {
        $type = in_array($request->query('period_type'), ['month', 'quarter', 'half', 'year', 'custom'], true)
            ? (string) $request->query('period_type') : 'month';
        $year = max(2000, min((int) $request->query('year', now()->year), now()->year + 2));
        $month = max(1, min((int) $request->query('month', now()->month), 12));
        $quarter = max(1, min((int) $request->query('quarter', (int) ceil(now()->month / 3)), 4));
        $half = max(1, min((int) $request->query('half', now()->month <= 6 ? 1 : 2), 2));

        [$start, $end, $label] = match ($type) {
            'custom' => $this->customRange($request),
            'quarter' => [Carbon::create($year, (($quarter - 1) * 3) + 1, 1)->startOfDay(), Carbon::create($year, $quarter * 3, 1)->endOfMonth()->endOfDay(), 'Q'.$quarter.' '.$year],
            'half' => [Carbon::create($year, $half === 1 ? 1 : 7, 1)->startOfDay(), Carbon::create($year, $half === 1 ? 6 : 12, 1)->endOfMonth()->endOfDay(), 'H'.$half.' '.$year],
            'year' => [Carbon::create($year, 1, 1)->startOfDay(), Carbon::create($year, 12, 31)->endOfDay(), (string) $year],
            default => [Carbon::create($year, $month, 1)->startOfDay(), Carbon::create($year, $month, 1)->endOfMonth()->endOfDay(), Carbon::create($year, $month, 1)->format('F Y')],
        };
        $days = max(1, (int) $start->diffInDays($end) + 1);
        $previousEnd = $start->copy()->subSecond();
        $previousStart = $previousEnd->copy()->subDays($days - 1)->startOfDay();
        $previousLabel = match ($type) {
            'month' => $previousStart->format('F Y'),
            'quarter' => 'prior quarter',
            'half' => 'prior half year',
            'custom' => $previousStart->format('d M Y').' – '.$previousEnd->format('d M Y'),
            default => (string) ($year - 1),
        };

        return compact('type', 'year', 'month', 'quarter', 'half', 'start', 'end', 'label', 'previousStart', 'previousEnd', 'previousLabel');
    }

    /** Inclusive calendar-date bounds shared by the overview and module dashboards. */
    public function customRange(Request $request): array
    {
        $dates = Validator::make($request->query(), [
            'start_date' => ['bail', 'required', 'date_format:Y-m-d'],
            'end_date' => ['bail', 'required', 'date_format:Y-m-d'],
        ], [], ['start_date' => 'from date', 'end_date' => 'to date'])->validate();

        // Compare only after both values are valid dates (including non-scalar input).
        Validator::make($dates, ['end_date' => ['after_or_equal:start_date']], [], [
            'start_date' => 'from date', 'end_date' => 'to date',
        ])->validate();

        $start = Carbon::createFromFormat('!Y-m-d', $dates['start_date'])->startOfDay();
        $end = Carbon::createFromFormat('!Y-m-d', $dates['end_date'])->endOfDay();

        return [$start, $end, $start->format('d M Y').' – '.$end->format('d M Y')];
    }

    public function availableYears(): array
    {
        return range(now()->year + 1, max(2024, now()->year - 5));
    }
}
