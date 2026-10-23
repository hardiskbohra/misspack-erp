<section class="core-card client-account-summary" aria-label="Client account summary" data-client-account-summary>
    <header class="client-account-head">
        <div><h2>Payment summary</h2><p>{{ $client->company_name }} · All-time saved totals</p></div>
        <dl class="client-account-counts">
            <div><dt>Projects</dt><dd>{{ number_format($accountSummary['project_count']) }}</dd></div>
            <div><dt>Current invoices / PIs</dt><dd>{{ number_format($accountSummary['invoice_count']) }}</dd></div>
        </dl>
    </header>
    @foreach($accountSummary['currencies'] as $position)
        @php($code = $position['currency'])
        <div class="client-account-currency" data-account-currency="{{ $code }}">
            <h3>{{ $code }} account</h3>
            <dl class="client-account-metrics">
                <div><dt>Invoice / PI value</dt><dd data-account-invoiced>{{ \App\Helpers\CommonHelper::amount($position['invoice_total'], $code) }}</dd></div>
                <div><dt>Net payments</dt><dd data-account-payments>{{ \App\Helpers\CommonHelper::amount($position['payments'], $code) }}</dd><small>Receipts less refunds</small></div>
                <div><dt>{{ $position['net_credit'] > 0 ? 'Net credit balance' : 'Net balance due' }}</dt><dd data-account-balance>{{ \App\Helpers\CommonHelper::amount($position['net_credit'] > 0 ? $position['net_credit'] : $position['balance_due'], $code) }}</dd><small>Invoice balances: {{ \App\Helpers\CommonHelper::amount($position['invoice_balance'], $code) }}</small></div>
                <div><dt>Unapplied advance / credit</dt><dd data-account-advance>{{ \App\Helpers\CommonHelper::amount($position['unapplied_credit'], $code) }}</dd><small>Already included in net balance</small></div>
            </dl>
        </div>
    @endforeach
    <p class="client-account-note">Includes drafts and opening payments; excludes cancelled/replaced invoice values. Credits include project advances and are not auto-allocated. Currencies stay separate.</p>
</section>
