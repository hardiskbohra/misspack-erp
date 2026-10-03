<?php

namespace App\Http\Controllers;

use App\Helpers\DateRanges;
use App\Models\PartyStatementShare;
use App\Models\SalesInvoice;
use App\Models\VendorPaymentEntry;
use App\Services\PartyStatement;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Statements of account, and the links they travel on.
 *
 * The surface answers the month-end question "who do I need to send a statement
 * to, and what does it say". Each line is one party **in one currency**, because
 * that is what a statement is: a vendor billed in RMB and paid in rupees has two
 * balances, and a line that added them would be wrong by the exchange rate.
 *
 * A link is the only way a statement leaves this building: PDF for the printer,
 * but for WhatsApp and email a URL that expires. Nothing about the party's
 * ledger is public: the token is the whole of the authentication, so it is long,
 * it can be revoked in one click, every open is counted, and the log — who was
 * sent what, when, and whether they looked — is the record that answers "I sent
 * that on the 5th" without anyone having to remember.
 */
class PartyStatementController extends Controller
{
    public function __construct(private PartyStatement $statements)
    {
    }

    /** The run: every party with a balance or movement in the period. */
    public function index(Request $request): View
    {
        $filters = $this->filters($request);

        /* "All" is two lists, not a third one: clients and vendors are read
           differently (receivable vs payable), so they are built separately and
           merged — never averaged into one number that means nothing. */
        $types = $filters['partyType'] === 'all' ? ['client', 'vendor'] : [$filters['partyType']];
        $rows = [];
        $totals = ['debit' => 0.0, 'credit' => 0.0, 'closing' => 0.0, 'parties' => 0, 'balanced' => 0];

        foreach ($types as $type) {
            $activity = $this->statements->activity(
                $type,
                $filters['dateFrom'],
                $filters['dateTo'],
                $filters['q'],
                $filters['currency']
            );

            foreach ($activity['rows'] as $row) {
                $row['party_type'] = $type;
                $row['party_type_label'] = PartyStatement::partyTypes()[$type];
                $rows[] = $row;
            }

            foreach (['debit', 'credit', 'closing', 'parties', 'balanced'] as $key) {
                $totals[$key] += $activity['totals'][$key] ?? 0;
            }
        }

        usort($rows, fn ($a, $b) => [$b['last_date'] ?? '', $a['name']] <=> [$a['last_date'] ?? '', $b['name']]);
        $totals = array_map(fn ($value) => is_float($value) ? round($value, 2) : $value, $totals);

        $shares = PartyStatementShare::query()
            ->when($filters['q'] !== null && $filters['q'] !== '', fn ($q) => $q->where('party_name', 'like', '%'.$filters['q'].'%'))
            ->when($filters['partyType'] !== 'all', fn ($q) => $q->where('party_type', $filters['partyType']))
            ->latest('id')
            ->limit(30)
            ->get();

        return view('cashflows.statements', [
            'rows' => $rows,
            'totals' => $totals,
            'shares' => $shares,
            'currencyOptions' => $this->currencyChoices(),
            'liveCount' => PartyStatementShare::live()->count(),
            'sentThisMonth' => PartyStatementShare::where('created_at', '>=', DateRanges::presets()['this_month']['from'])->count(),
            ...$filters,
        ]);
    }

    /** One statement, on screen, with a printer and a Share button beside it. */
    public function show(Request $request, string $partyType, int $party): View
    {
        $filters = $this->filters($request);
        $statement = $this->statements->build(
            $partyType,
            $party,
            $filters['dateFrom'],
            $filters['dateTo'],
            ['currency' => $filters['currency'], 'ageing' => $filters['ageing']]
        );

        abort_if($statement === null, 404);

        $readyShare = null;
        if ($token = $request->query('share')) {
            $readyShare = PartyStatementShare::forParty($partyType, $party)->where('token', $token)->first();
        }

        return view('cashflows.statement-show', [
            'statement' => $statement,
            'currencyOptions' => $this->statements->currencies($partyType, $party),
            'shares' => PartyStatementShare::forParty($partyType, $party)->latest('id')->limit(20)->get(),
            'readyShare' => $readyShare,
            'expiryChoices' => PartyStatementShare::EXPIRY_CHOICES,
            'channelChoices' => PartyStatementShare::CHANNELS,
            ...$filters,
        ]);
    }

    /**
     * The PDF, written the way the report PDF is written: dompdf when it is
     * installed, and a print-ready page when it is not. A missing package must
     * not be a dead button in front of a customer.
     */
    public function pdf(Request $request, string $partyType, int $party)
    {
        $filters = $this->filters($request);
        $statement = $this->statements->build(
            $partyType,
            $party,
            $filters['dateFrom'],
            $filters['dateTo'],
            ['currency' => $filters['currency'], 'ageing' => $filters['ageing']]
        );

        abort_if($statement === null, 404);

        $fileName = 'statement-'
            .Str::slug($statement['party']['name'] ?: 'party')
            .'-'.Str::slug($statement['period']['label'])
            .'.pdf';

        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            return \Barryvdh\DomPDF\Facade\Pdf::loadView('cashflows.statement-pdf', ['statement' => $statement])
                ->setPaper('a4')
                ->download($fileName);
        }

        return response()->view('cashflows.statement-pdf', [
            'statement' => $statement,
            'pdfFallbackMessage' => 'Install barryvdh/laravel-dompdf for a direct PDF download. This page prints — use Print > Save as PDF.',
        ]);
    }

    /**
     * Mint the link. How it will be sent is chosen here, at the moment somebody
     * knows, so the log reads "WhatsApp, 5 Oct" and not "a link was created".
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'party_type' => ['required', 'in:client,vendor'],
            'party_id' => ['required', 'integer'],
            'period' => ['nullable', 'string', 'max:20'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'currency' => ['nullable', 'string', 'max:10'],
            'label' => ['nullable', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:300'],
            'expires_in' => ['required', 'integer', 'in:'.implode(',', array_keys(PartyStatementShare::EXPIRY_CHOICES))],
            'channel' => ['required', 'in:link,whatsapp,email'],
            'ageing' => ['nullable', 'boolean'],
        ]);

        $party = $this->statements->findParty($data['party_type'], (int) $data['party_id']);
        abort_if($party === null, 404);

        $currency = strtoupper((string) ($data['currency'] ?? '')) ?: $this->statements->defaultCurrency($data['party_type'], (int) $data['party_id']);

        $share = PartyStatementShare::issue([
            'party_type' => $data['party_type'],
            'party_id' => (int) $data['party_id'],
            'party_name' => $this->statements->partyName($data['party_type'], $party),
            'party_currency' => $currency,
            'date_from' => $data['date_from'] ?? null,
            'date_to' => $data['date_to'] ?? null,
            'label' => $data['label'] ?? null,
            'note' => $data['note'] ?? null,
            'shared_via' => $data['channel'],
            'expires_in_days' => (int) $data['expires_in'],
            'options' => ['ageing' => (bool) ($data['ageing'] ?? true), 'currency' => $currency],
        ]);

        return redirect()
            ->route('cashflows.statements.show', array_filter([
                'partyType' => $data['party_type'],
                'party' => (int) $data['party_id'],
                /* The period travels as the preset it is: an "All time"
                   statement has no dates to carry, and landing back on this
                   month after issuing a link would show a different statement
                   from the one that was just sent. */
                'period' => $data['period'] ?? null,
                'date_from' => $data['date_from'] ?? null,
                'date_to' => $data['date_to'] ?? null,
                'currency' => $currency,
                'share' => $share->token,
            ], fn ($value) => $value !== null && $value !== ''))
            ->with('success', 'Statement link ready for '.$share->party_name.' — it expires '.strtolower($share->expiresLabel()).'.');
    }

    /** Close the door without losing the record of having opened it once. */
    public function revoke(PartyStatementShare $share): RedirectResponse
    {
        $share->update(['revoked_at' => now()]);

        return back()->with('success', 'Link revoked. The statement is no longer readable at that address.');
    }

    public function destroy(PartyStatementShare $share): RedirectResponse
    {
        $share->delete();

        return back()->with('success', 'Link removed from the log.');
    }

    /**
     * The statement itself, for whoever holds the link — no login, because the
     * accountant is not a user of this ERP and never will be.
     */
    public function publicShow(Request $request, string $token): Response
    {
        $share = PartyStatementShare::where('token', $token)->first();

        if (! $share) {
            return response()->view('statements.expired', ['reason' => 'link'], 404);
        }

        if (! $share->isLive()) {
            return response()->view('statements.expired', ['share' => $share, 'reason' => $share->state()], 410);
        }

        $statement = $this->statements->build(
            $share->party_type,
            $share->party_id,
            $share->date_from?->toDateString(),
            $share->date_to?->toDateString(),
            ['currency' => $share->party_currency, 'ageing' => (bool) $share->option('ageing', true)]
        );

        if ($statement === null) {
            return response()->view('statements.expired', ['share' => $share, 'reason' => 'party'], 410);
        }

        $statement['note'] = $share->note;

        /* A view is the party reading it. Somebody signed in here opening the
           link to check it is not the party reading it, and counting that would
           make "last viewed" a lie the first time anybody tested the link. */
        if (! Auth::check()) {
            $share->views = $share->views + 1;
            $share->first_viewed_at ??= now();
            $share->last_viewed_at = now();
            $share->last_viewed_ip = $request->ip();
            $share->save();
        }

        return response()->view('statements.public', [
            'statement' => $statement,
            'share' => $share,
        ]);
    }

    /* -------------------------------------------------------------- shared */

    /**
     * One filter shape for the surface and the statement, so the period on the
     * screen is the period in the link and the currency in the columns.
     */
    private function filters(Request $request): array
    {
        $partyType = $request->query('party_type', 'all');
        if (! in_array($partyType, ['all', 'client', 'vendor'], true)) {
            $partyType = 'all';
        }

        $presets = DateRanges::presets();
        $period = $request->query('period');
        /* A range can arrive as a name ("all", "custom") rather than a date;
           DateRanges reads it, so nothing below has to guess. */
        $dateFrom = DateRanges::normalise($request->query('date_from'));
        $dateTo = DateRanges::normalise($request->query('date_to'));

        if ($period === 'all') {
            $dateFrom = $dateTo = null;
        } elseif ($period && isset($presets[$period])) {
            $dateFrom = $presets[$period]['from'];
            $dateTo = $presets[$period]['to'];
        } elseif (! $dateFrom && ! $dateTo) {
            /* Opening the screen in the middle of a month is opening it to close
               that month, so the default is the month the office is in. */
            $dateFrom = $presets['this_month']['from'];
            $dateTo = $presets['this_month']['to'];
        }

        $activeRange = ($dateFrom || $dateTo) ? DateRanges::keyOf($dateFrom, $dateTo) : 'all';

        /* What the period control shows: the preset that was asked for, the
           preset those dates happen to be, or "custom" when they are neither. */
        $periodKey = in_array($period, array_merge(array_keys($presets), ['all', 'custom']), true)
            ? $period
            : ($activeRange ?: 'custom');

        return [
            'partyType' => $partyType,
            'currency' => strtoupper((string) $request->query('currency', 'INR')) ?: 'INR',
            'q' => trim((string) $request->query('q')) ?: null,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'ageing' => $request->query('ageing', '1') !== '0',
            'activeRange' => $activeRange,
            'periodKey' => $periodKey,
            'dateRanges' => $presets,
            'dateRangeLabels' => DateRanges::LABELS,
        ];
    }

    /** Every currency a statement can be issued in, for the picker. */
    private function currencyChoices(): array
    {
        $fromLedger = [];

        if (Schema::hasTable('vendor_payment_entries')) {
            $fromLedger = VendorPaymentEntry::query()
                ->select('foreign_currency')->distinct()->pluck('foreign_currency')->all();
        }

        if (Schema::hasTable('sales_invoices')) {
            $fromLedger = array_merge($fromLedger, SalesInvoice::query()
                ->select('currency')->distinct()->pluck('currency')->all());
        }

        $codes = array_values(array_unique(array_filter(array_map(
            fn ($code) => strtoupper((string) $code),
            $fromLedger
        ))));

        sort($codes);

        /* INR is always offered: it is the currency the books are kept in even
           in a month with no rupee invoice at all. */
        return array_values(array_unique(array_merge(['INR'], $codes)));
    }
}
