@php
    /* The payslip itself, written once and rendered on three surfaces: the
       office's record page, the employee's own workspace, and paper (printed, or
       rendered to PDF when dompdf is installed).

       $context decides the one thing that is not the same for everybody: the
       draft/issued state and the ledger link behind the slip are staff
       vocabulary, so they are printed only inside the app. The figures are
       identical everywhere — that is the point of building them in one service
       (App\Services\PayslipDocument) and reading them here. */
    $ctx = $context ?? 'app';
    $currency = $doc['totals']['currency'];
    $money = fn ($value) => \App\Helpers\CommonHelper::amount((float) $value, $currency);
    $issuer = $doc['issuer'];
    $employee = $doc['employee'];
    $attendance = $doc['attendance'];
    $payment = $doc['payment'];
@endphp

@push('styles')
    <link rel="stylesheet" href="{{ $assetVer('assets/css/payslip.css') }}">
@endpush

<article class="payslip" aria-label="Payslip for {{ $employee['name'] }} — {{ $doc['slip']['period'] }}">
    <header class="ps-head">
        <div class="ps-brand">
            <p class="ps-brand-name">{{ $issuer['name'] }}</p>
            @if ($issuer['address'])
                <p class="ps-brand-line">{{ $issuer['address'] }}</p>
            @endif
            <p class="ps-brand-line">
                {{ collect([$issuer['city'], $issuer['state'], $issuer['pincode']])->filter()->implode(', ') }}
                @if ($issuer['country']) · {{ $issuer['country'] }} @endif
            </p>
            @if ($issuer['gstin'] || $issuer['pan'])
                <p class="ps-brand-line">
                    @if ($issuer['gstin']) GSTIN {{ $issuer['gstin'] }} @endif
                    @if ($issuer['pan']) · PAN {{ $issuer['pan'] }} @endif
                </p>
            @endif
            <p class="ps-brand-line">{{ $issuer['email'] }} · {{ $issuer['mobile'] }}</p>
        </div>

        <div class="ps-heading">
            <p class="ps-eyebrow">Salary slip</p>
            <h1 class="ps-period">{{ $doc['slip']['period'] }}</h1>
            <p class="ps-slip-no">Slip {{ $doc['slip']['number'] }}</p>
            <p class="ps-stamp">
                <span class="ps-chip {{ $doc['slip']['issued'] ? 'is-ok' : 'is-warn' }}">{{ $doc['slip']['status'] }}</span>
                @if ($doc['slip']['has_breakdown'])
                    <span class="ps-chip">With breakdown</span>
                @else
                    <span class="ps-chip">Totals only</span>
                @endif
                @if ($ctx === 'app' && $doc['slip']['matches_ledger'])
                    <span class="ps-chip">Matched to the ledger</span>
                @endif
            </p>
        </div>
    </header>

    <section class="ps-facts" aria-label="Employee">
        <div class="ps-fact">
            <span class="ps-fact-label">Employee</span>
            <span class="ps-fact-value">{{ $employee['name'] }}</span>
        </div>
        <div class="ps-fact">
            <span class="ps-fact-label">Employee code</span>
            <span class="ps-fact-value">{{ $employee['code'] }}</span>
        </div>
        <div class="ps-fact">
            <span class="ps-fact-label">Designation</span>
            <span class="ps-fact-value">{{ $employee['designation'] }}</span>
        </div>
        <div class="ps-fact">
            <span class="ps-fact-label">Department</span>
            <span class="ps-fact-value">{{ $employee['department'] }}</span>
        </div>
        <div class="ps-fact">
            <span class="ps-fact-label">Employment</span>
            <span class="ps-fact-value">{{ $employee['type'] }} · {{ $employee['status'] }}</span>
        </div>
        <div class="ps-fact">
            <span class="ps-fact-label">Date of joining</span>
            <span class="ps-fact-value">{{ $employee['joined'] }}</span>
        </div>
        <div class="ps-fact">
            <span class="ps-fact-label">PAN</span>
            <span class="ps-fact-value">{{ $employee['pan'] }}</span>
        </div>
        <div class="ps-fact">
            <span class="ps-fact-label">Bank</span>
            <span class="ps-fact-value">{{ $employee['bank'] }}</span>
        </div>
        <div class="ps-fact">
            <span class="ps-fact-label">Pay period</span>
            <span class="ps-fact-value">{{ $doc['slip']['from'] }} — {{ $doc['slip']['to'] }}</span>
        </div>
        @if ($attendance['working_days'] !== null || $attendance['paid_days'] !== null)
            <div class="ps-fact">
                <span class="ps-fact-label">Days</span>
                <span class="ps-fact-value">
                    {{ $attendance['paid_days'] ?? '—' }} paid
                    @if ($attendance['working_days'] !== null) of {{ $attendance['working_days'] }} @endif
                    @if (($attendance['lop'] ?? 0) > 0) · {{ $attendance['lop'] }} LOP @endif
                </span>
            </div>
        @endif
    </section>

    <section class="ps-lines" aria-label="Earnings and deductions">
        <table class="ps-table">
            <thead>
                <tr>
                    <th scope="col">Earnings</th>
                    <th scope="col" class="ps-num">Amount</th>
                    <th scope="col">Deductions</th>
                    <th scope="col" class="ps-num">Amount</th>
                </tr>
            </thead>
            <tbody>
                @php($rows = max(count($doc['earnings']), count($doc['deductions']), 1))
                @for ($i = 0; $i < $rows; $i++)
                    <tr>
                        <td>{{ $doc['earnings'][$i]['label'] ?? '' }}</td>
                        <td class="ps-num">
                            {{ isset($doc['earnings'][$i]) ? $money($doc['earnings'][$i]['amount']) : '' }}
                        </td>
                        <td>{{ $doc['deductions'][$i]['label'] ?? '' }}</td>
                        <td class="ps-num">
                            {{ isset($doc['deductions'][$i]) ? $money($doc['deductions'][$i]['amount']) : '' }}
                        </td>
                    </tr>
                @endfor
            </tbody>
            <tfoot>
                <tr>
                    <th scope="row">Total earned</th>
                    <td class="ps-num">{{ $money($doc['totals']['earned']) }}</td>
                    <th scope="row">Total deducted</th>
                    <td class="ps-num">{{ $money($doc['totals']['deducted']) }}</td>
                </tr>
            </tfoot>
        </table>
    </section>

    <section class="ps-net" aria-label="Net pay">
        <div class="ps-net-figure">
            <span class="ps-net-label">Net pay</span>
            <strong class="ps-net-value">{{ $money($doc['totals']['net']) }}</strong>
        </div>
        <p class="ps-net-words">{{ $doc['totals']['net_in_words'] }}</p>
    </section>

    <section class="ps-payment" aria-label="Payment">
        <div class="ps-fact">
            <span class="ps-fact-label">Paid on</span>
            <span class="ps-fact-value">{{ $payment['paid_on_label'] }}</span>
        </div>
        @if ($payment['mode'])
            <div class="ps-fact">
                <span class="ps-fact-label">Mode</span>
                <span class="ps-fact-value">{{ $payment['mode'] }}</span>
            </div>
        @endif
        @if ($payment['reference'])
            <div class="ps-fact">
                <span class="ps-fact-label">Reference</span>
                <span class="ps-fact-value">{{ $payment['reference'] }}</span>
            </div>
        @endif
        @if ($ctx === 'app' && $payment['ledger_amount'] !== null)
            <div class="ps-fact">
                <span class="ps-fact-label">Ledger</span>
                <span class="ps-fact-value">
                    {{ $payment['ledger_date']?->format('d M Y') }} ·
                    {{ $money($payment['ledger_amount']) }}
                    @if ($payment['ledger_particular']) · {{ $payment['ledger_particular'] }} @endif
                </span>
            </div>
        @endif
    </section>

    @if ($doc['slip']['notes'])
        <p class="ps-note"><strong>Note</strong> {{ $doc['slip']['notes'] }}</p>
    @endif

    <footer class="ps-foot">
        <p class="ps-foot-line">
            A computer-generated payslip — no signature is required.
            @if ($ctx === 'pdf')
                Printed {{ $doc['slip']['generated_at']->format('d M Y, h:i A') }}.
            @endif
        </p>
        @if ($ctx !== 'pdf')
            <p class="ps-foot-line">
                Generated {{ $doc['slip']['generated_at']->format('d M Y, h:i A') }}
                @if (! $doc['slip']['issued'] && $ctx === 'app')
                    · this slip is still a <strong>draft</strong>: the employee cannot see it until it is issued
                @endif
            </p>
        @endif
    </footer>
</article>
