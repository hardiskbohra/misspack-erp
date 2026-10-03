<?php

namespace App\Http\Controllers;

use App\Helpers\DateRanges;
use App\Services\PartyStatement;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The client's own statement, in the portal, without a link.
 *
 * A client that has a login should never have to keep a link to read their own
 * account: this is the same statement the Share button produces, built from the
 * same service, rendered by the same partial — only the period and the currency
 * belong to whoever is looking at it, and it is always their own account.
 *
 * Nothing here is internal: the row statuses are the office's vocabulary and
 * stay on the office side (context 'portal' prints the statement without them).
 */
class ClientPortalStatementController extends ClientPortalBaseController
{
    public function index(Request $request, PartyStatement $statements): View
    {
        $client = $this->client($request);

        $presets = DateRanges::presets();
        $period = $request->query('period');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');

        if ($period === 'all') {
            $dateFrom = $dateTo = null;
        } elseif ($period && isset($presets[$period])) {
            $dateFrom = $presets[$period]['from'];
            $dateTo = $presets[$period]['to'];
        } elseif (! $dateFrom && ! $dateTo) {
            $dateFrom = $presets['this_month']['from'];
            $dateTo = $presets['this_month']['to'];
        }

        $currencies = $statements->currencies('client', $client->id);
        $currency = strtoupper((string) $request->query('currency', ''));
        if ($currency === '' || ! in_array($currency, $currencies, true)) {
            $currency = $currencies[0] ?? 'INR';
        }

        $statement = $statements->build('client', $client->id, $dateFrom, $dateTo, [
            'currency' => $currency,
            'ageing' => true,
        ]);

        abort_if($statement === null, 404);

        $activeRange = ($dateFrom || $dateTo) ? DateRanges::keyOf($dateFrom, $dateTo) : 'all';

        return view('client_portal.statements.index', [
            'statement' => $statement,
            'currencyOptions' => $currencies,
            'dateFrom' => $dateFrom ? Carbon::parse($dateFrom)->toDateString() : null,
            'dateTo' => $dateTo ? Carbon::parse($dateTo)->toDateString() : null,
            'dateRangeLabels' => DateRanges::LABELS,
            'activeRange' => $activeRange,
            'periodKey' => in_array($period, array_merge(array_keys($presets), ['all', 'custom']), true)
                ? $period
                : ($activeRange ?: 'custom'),
        ]);
    }
}
