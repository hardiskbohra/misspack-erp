@php
    /* The document. It is written once and rendered in four places — the app's
       preview, the printer/PDF, the public link and the client portal — so a
       figure cannot look different depending on who is reading it.

       $context decides the one thing that is not the same for everybody: the
       internal status of a row ("pending", "booked") is staff vocabulary, not
       the party's, so it is printed only inside the app. */
    $ctx = $context ?? 'app';
    $currency = $statement['currency'];
    $isInr = $currency === 'INR';
    /* The rupee sign, or the vendor's own code — never the letters INR. */
    $currencyLabel = \App\Helpers\CommonHelper::currencyLabel($currency);
    $money = fn ($value) => \App\Helpers\CommonHelper::amount((float) $value, $currency);
    $party = $statement['party'];
    $totals = $statement['totals'];
    $direction = $statement['direction'] === 'payable' ? 'Payable' : 'Receivable';
    $debitWord = $statement['party_type'] === 'vendor' ? 'paid' : 'invoice';
    $creditWord = $statement['party_type'] === 'vendor' ? 'billed' : 'received';
    $other = $statement['other_currencies'] ?? [];

    /* The foot's line about other currencies: written the way the amount is
       written everywhere else, so a foreign figure cannot arrive as a raw
       float. */
    $otherText = collect($other)->map(function ($line) {
        $parts = [];
        if ($line['debit'] > 0) {
            $parts[] = \App\Helpers\CommonHelper::amount($line['debit'], $line['currency']).' debit';
        }
        if ($line['credit'] > 0) {
            $parts[] = \App\Helpers\CommonHelper::amount($line['credit'], $line['currency']).' credit';
        }

        return $line['currency'].' — '.number_format($line['rows']).' '
            .\Illuminate\Support\Str::plural('row', $line['rows'])
            .($parts ? ' ('.implode(', ', $parts).')' : '');
    })->implode(' · ');
@endphp

<article class="stmt {{ in_array($ctx, ['pdf', 'public'], true) ? 'pdf-sheet' : ($ctx === 'portal' ? 'pdf-print-sheet' : '') }}" aria-label="Statement of account for {{ $party['name'] }}">
    <header class="stmt-head">
        <div class="stmt-brand">
            <p class="stmt-brand-name">{{ $statement['issuer']['seller_company_name'] }}</p>
            <p class="stmt-brand-line">{{ $statement['issuer']['seller_address'] }}</p>
            <p class="stmt-brand-line">{{ $statement['issuer']['seller_city'] }},
                {{ $statement['issuer']['seller_state'] }} {{ $statement['issuer']['seller_pincode'] }},
                {{ $statement['issuer']['seller_country'] }}</p>
            <p class="stmt-brand-line">GSTIN {{ $statement['issuer']['seller_gstin'] }} · PAN
                {{ $statement['issuer']['seller_pan'] }}</p>
            <p class="stmt-brand-line">{{ $statement['issuer']['seller_email'] }} ·
                {{ $statement['issuer']['seller_mobile'] }} · {{ $statement['issuer']['seller_website'] }}</p>
        </div>
        <div class="stmt-heading">
            <p class="stmt-eyebrow">Statement of account</p>
            <h1 class="stmt-party-name">{{ $party['name'] }}</h1>
            <p class="stmt-period">{{ $statement['period']['label'] }}</p>
            <p class="stmt-stamp">
                <span class="stmt-chip">{{ $currencyLabel }}</span>
                <span class="stmt-chip">{{ $direction }}</span>
                <span class="stmt-stamp-time">Generated
                    {{ $statement['generated_at']->format('d M Y, h:i A') }}</span>
            </p>
        </div>
    </header>

    <section class="stmt-meta">
        <div class="stmt-block">
            <p class="stmt-block-title">{{ $party['kind'] }}</p>
            <p class="stmt-block-strong">{{ $party['name'] }}</p>
            @if ($party['code'])
                <p class="stmt-block-line">{{ $party['code'] }}</p>
            @endif
            @foreach ($party['address_lines'] as $line)
                <p class="stmt-block-line">{{ $line }}</p>
            @endforeach
            @if ($party['gstin'])
                <p class="stmt-block-line">GSTIN {{ $party['gstin'] }}</p>
            @endif
            @if ($party['contact_person'] || $party['email'] || $party['phone'])
                <p class="stmt-block-line stmt-block-contact">
                    {{ collect([$party['contact_person'], $party['email'], $party['phone']])->filter()->implode(' · ') }}
                </p>
            @endif
        </div>

        <div class="stmt-block stmt-block--summary">
            <p class="stmt-block-title">Account summary</p>
            <div class="stmt-figures">
                <div class="stmt-figure">
                    <span>Opening balance</span>
                    <strong>{{ $money($statement['opening']) }}</strong>
                </div>
                <div class="stmt-figure">
                    <span>Debit <em>({{ $debitWord }})</em></span>
                    <strong>{{ $money($totals['debit']) }}</strong>
                </div>
                <div class="stmt-figure">
                    <span>Credit <em>({{ $creditWord }})</em></span>
                    <strong>{{ $money($totals['credit']) }}</strong>
                </div>
                <div class="stmt-figure stmt-figure--closing">
                    <span>{{ $direction }}</span>
                    <strong>{{ $money($totals['closing']) }}</strong>
                </div>
            </div>
            <p class="stmt-block-line">{{ number_format($totals['count']) }}
                {{ \Illuminate\Support\Str::plural('row', $totals['count']) }} in this period · balance as on
                {{ ($statement['period']['to'] ?? $statement['generated_at'])->format('d M Y') }}</p>
        </div>
    </section>

    <table class="stmt-table">
        <thead>
            <tr>
                <th scope="col">Date</th>
                <th scope="col">Particulars</th>
                <th scope="col">Reference</th>
                @if ($ctx === 'app')
                    <th scope="col">Status</th>
                @endif
                <th scope="col" class="stmt-num">Debit <em>({{ $debitWord }})</em></th>
                <th scope="col" class="stmt-num">Credit <em>({{ $creditWord }})</em></th>
                <th scope="col" class="stmt-num">Balance</th>
            </tr>
        </thead>
        <tbody>
            <tr class="stmt-row-opening">
                <td>{{ $statement['period']['from'] ? $statement['period']['from']->format('d M Y') : '—' }}</td>
                <td colspan="{{ $ctx === 'app' ? 3 : 2 }}"><span class="stmt-opening-label">Opening balance</span></td>
                <td class="stmt-num">—</td>
                <td class="stmt-num">—</td>
                <td class="stmt-num stmt-num-strong">{{ $money($statement['opening']) }}</td>
            </tr>

            @forelse ($statement['rows'] as $row)
                @php
                    /* Cashflow is rupees. A vendor statement is the vendor's
                       currency, so the rupee equivalent stays off the page. */
                    $inrNote = ($statement['party_type'] !== 'vendor' && ! $isInr && ! empty($row['inr']))
                        ? \App\Helpers\CommonHelper::indianCurrency($row['inr'])
                        : null;
                @endphp
                <tr>
                    <td>{{ $row['date'] ? $row['date']->format('d M Y') : '—' }}</td>
                    <td>
                        {{ $row['particular'] }}
                        @if ($statement['party_type'] !== 'vendor' && ! empty($row['rate']))
                            <span class="stmt-cell-note">@ {{ $row['rate'] }}</span>
                        @endif
                    </td>
                    <td>{{ $row['reference'] ?: '—' }}</td>
                    @if ($ctx === 'app')
                        <td>@if ($row['status'])<span class="stmt-badge">{{ $row['status'] }}</span>@endif</td>
                    @endif
                    <td class="stmt-num">{{ $row['debit'] > 0 ? $money($row['debit']) : '—' }}
                        @if ($row['debit'] > 0 && $inrNote)<span class="stmt-cell-note">{{ $inrNote }}</span>@endif
                    </td>
                    <td class="stmt-num">{{ $row['credit'] > 0 ? $money($row['credit']) : '—' }}
                        @if ($row['credit'] > 0 && $inrNote)<span class="stmt-cell-note">{{ $inrNote }}</span>@endif
                    </td>
                    <td class="stmt-num stmt-num-strong">{{ $money($row['balance']) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $ctx === 'app' ? 7 : 6 }}">
                        <div class="stmt-empty">No transactions in this period — the balance above is what carried in.</div>
                    </td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="{{ $ctx === 'app' ? 4 : 3 }}"><span class="stmt-total-label">Closing
                        {{ $direction }}</span></td>
                <td class="stmt-num">{{ $money($totals['debit']) }}</td>
                <td class="stmt-num">{{ $money($totals['credit']) }}</td>
                <td class="stmt-num stmt-num-strong">{{ $money($totals['closing']) }}</td>
            </tr>
        </tfoot>
    </table>

    @if ($statement['ageing'])
        <section class="stmt-ageing">
            <h2 class="stmt-section-title">{{ $statement['ageing']['title'] }}</h2>

            <div class="stmt-buckets">
                @foreach ($statement['ageing']['buckets'] as $key => $bucket)
                    <div class="stmt-bucket {{ $bucket['count'] > 0 ? 'is-live' : '' }}">
                        <span class="stmt-bucket-label">{{ $bucket['label'] }}</span>
                        <strong class="stmt-bucket-value">{{ $money($bucket['amount']) }}</strong>
                        <span class="stmt-bucket-count">{{ $bucket['count'] }}
                            {{ \Illuminate\Support\Str::plural('document', $bucket['count']) }}</span>
                    </div>
                @endforeach
            </div>

            <table class="stmt-table stmt-table--compact">
                <thead>
                    <tr>
                        <th scope="col">Date</th>
                        <th scope="col">Reference</th>
                        @if ($statement['party_type'] === 'client')
                            <th scope="col">Due</th>
                        @endif
                        <th scope="col">Age</th>
                        <th scope="col" class="stmt-num">Outstanding</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($statement['ageing']['rows'] as $item)
                        <tr>
                            <td>{{ $item['date'] ? $item['date']->format('d M Y') : '—' }}</td>
                            <td>{{ $item['reference'] ?: $item['particular'] }}</td>
                            @if ($statement['party_type'] === 'client')
                                <td>{{ $item['due'] ? $item['due']->format('d M Y') : '—' }}</td>
                            @endif
                            <td>{{ $item['days'] <= 0 ? $item['bucket_label'] : $item['days'].' days' }}</td>
                            <td class="stmt-num">{{ $money($item['amount']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="{{ $statement['party_type'] === 'client' ? 3 : 2 }}"><span
                                class="stmt-total-label">Total outstanding</span></td>
                        <td colspan="2" class="stmt-num stmt-num-strong">{{ $money($statement['ageing']['total']) }}</td>
                    </tr>
                </tfoot>
            </table>

            <p class="stmt-note">{{ $statement['ageing']['note'] }}</p>
        </section>
    @endif

    @if (! empty($statement['note']))
        <section class="stmt-message">
            <p class="stmt-block-title">Note</p>
            <p>{{ $statement['note'] }}</p>
        </section>
    @endif

    <footer class="stmt-foot">
        <p class="stmt-note">{{ $statement['source_note'] }}</p>

        @if ($other !== [])
            <p class="stmt-note stmt-note--wide">
                Also on this account, outside this statement: {{ $otherText }} — each currency is its own statement.
            </p>
        @endif

        <p class="stmt-note stmt-note--wide">
            Bank: {{ $statement['issuer']['seller_bank_name'] }} ·
            A/C {{ $statement['issuer']['seller_account_number'] }} ·
            IFSC {{ $statement['issuer']['seller_ifsc'] }} ·
            SWIFT {{ $statement['issuer']['seller_swift'] }}
        </p>

        <p class="stmt-note">A computer-generated statement. Kindly confirm the balance against your books and tell us
            within seven days of the statement date if anything does not match.</p>
    </footer>
</article>
