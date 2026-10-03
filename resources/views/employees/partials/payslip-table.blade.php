@php
    /* The one payslip list, rendered for both readers: the office's record page
       and the employee's own salary page. $context decides the two columns that
       are staff work rather than the employee's business — the issue state and
       the corrections — and nothing else.

       The figures, the month names and the slip numbers are identical on both
       sides: a list that read differently depending on who opened it is how two
       people end up disagreeing about one month. */
    $ctx = $context ?? 'app';
    $money = fn ($value, $currency) => \App\Helpers\CommonHelper::amount((float) $value, $currency);
@endphp

<div class="master-table-wrap">
    <table class="master-table">
        <thead>
            <tr>
                <th scope="col">Month</th>
                <th scope="col" class="is-num">Earned</th>
                <th scope="col" class="is-num">Deducted</th>
                <th scope="col" class="is-num">Net</th>
                <th scope="col">Paid on</th>
                <th scope="col">Slip</th>
                @if ($ctx === 'office')
                    <th scope="col">Status</th>
                    <th scope="col">Action</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @forelse ($payslips as $payslip)
                <tr>
                    <td data-label="Month">
                        <strong>{{ $payslip->periodLabel() }}</strong>
                        <span class="master-sub">{{ $payslip->slipNumber() }}</span>
                        @if ($ctx === 'office' && $payslip->entry)
                            <span class="master-sub">matched to the ledger entry of {{ $payslip->entry->entry_date?->format('d M Y') }}</span>
                        @endif
                    </td>
                    <td class="is-num" data-label="Earned">{{ $money($payslip->gross_amount, $payslip->currency) }}</td>
                    <td class="is-num" data-label="Deducted">{{ $money($payslip->deductions, $payslip->currency) }}</td>
                    <td class="is-num" data-label="Net"><strong>{{ $money($payslip->net_amount, $payslip->currency) }}</strong></td>
                    <td data-label="Paid on">{{ $payslip->paidOnLabel() }}</td>
                    <td data-label="Slip">
                        <div class="emp-row-actions">
                            <a class="master-btn master-btn-soft master-btn-sm"
                                href="{{ $ctx === 'office'
                                    ? route('users.payslips.pdf', ['user' => $user, 'payslip' => $payslip])
                                    : route('my.payslips.pdf', $payslip) }}"
                                target="_blank" rel="noopener">
                                <i class="fas fa-file-invoice-dollar" aria-hidden="true"></i> Payslip
                            </a>
                            <a class="master-btn master-btn-ghost master-btn-sm"
                                href="{{ $ctx === 'office'
                                    ? route('users.show', ['user' => $user, 'tab' => 'salary', 'year' => $year ?? date('Y'), 'payslip' => $payslip->id]).'#payslip-sheet'
                                    : route('my.salary', ['preview' => $payslip->id]).'#payslip-sheet' }}">Preview</a>
                            @if ($payslip->hasFile())
                                <a class="master-btn master-btn-ghost master-btn-sm"
                                    href="{{ $ctx === 'office'
                                        ? route('users.payslips.file', ['user' => $user, 'payslip' => $payslip])
                                        : route('my.payslips.file', $payslip) }}">Signed copy</a>
                            @endif

                            {{-- Handing the slip over is the office's job, and the
                                 message carries the month and where to look — never
                                 the figures, which stay behind the login. --}}
                            @if ($ctx === 'office' && $payslip->isIssued())
                                @php($wa = $payslip->whatsappUrl($user->mobile))
                                @php($mail = $payslip->mailUrl($user->email))
                                @if ($wa || $mail)
                                    <span class="user-share">
                                        @if ($wa)
                                            <a class="master-btn master-btn-ghost master-btn-sm" href="{{ $wa }}" target="_blank"
                                                rel="noopener" title="Send on WhatsApp">WhatsApp</a>
                                        @endif
                                        @if ($mail)
                                            <a class="master-btn master-btn-ghost master-btn-sm" href="{{ $mail }}"
                                                title="Send by email">Email</a>
                                        @endif
                                    </span>
                                @endif
                            @endif
                        </div>
                    </td>
                    @if ($ctx === 'office')
                        <td data-label="Status">
                            <span class="emp-pill {{ $payslip->isIssued() ? 'is-ok' : 'is-warn' }}">{{ $payslip->statusLabel() }}</span>
                            @if (! $payslip->hasBreakdown())
                                <span class="master-sub">totals only</span>
                            @endif
                        </td>
                        <td data-label="Action">
                            <div class="emp-row-actions">
                                <a class="master-btn master-btn-ghost master-btn-sm"
                                    href="{{ route('users.show', ['user' => $user, 'tab' => 'salary', 'payslip' => $payslip->id]) }}#payslip-form">Correct</a>
                                <form method="POST" action="{{ route('users.payslips.update', ['user' => $user, 'payslip' => $payslip]) }}">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="period" value="{{ $payslip->period }}">
                                    <input type="hidden" name="gross_amount" value="{{ $payslip->gross_amount }}">
                                    <input type="hidden" name="deductions" value="{{ $payslip->deductions }}">
                                    <input type="hidden" name="net_amount" value="{{ $payslip->net_amount }}">
                                    <input type="hidden" name="paid_on" value="{{ $payslip->paid_on?->format('Y-m-d') }}">
                                    <input type="hidden" name="status" value="{{ $payslip->isIssued() ? 'draft' : 'issued' }}">
                                    <button class="master-btn master-btn-ghost master-btn-sm" type="submit">
                                        {{ $payslip->isIssued() ? 'Back to draft' : 'Issue' }}
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('users.payslips.destroy', ['user' => $user, 'payslip' => $payslip]) }}"
                                    onsubmit="return confirm('Remove the payslip for {{ $payslip->periodLabel() }}?');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="master-btn master-btn-ghost master-btn-sm" type="submit">Remove</button>
                                </form>
                            </div>
                        </td>
                    @endif
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $ctx === 'office' ? 8 : 6 }}">
                        <div class="master-list-empty">
                            <span class="master-list-empty-icon" aria-hidden="true">🧾</span>
                            <p class="master-list-empty-title">{{ $ctx === 'office' ? 'No payslip yet' : 'No payslip has been issued yet' }}</p>
                            <p class="master-list-empty-text">
                                @if ($ctx === 'office')
                                    Record a month above. The slip itself is printed from these figures, and the
                                    employee sees it in their own workspace the moment it is issued.
                                @else
                                    A payslip appears here as the office issues it — and it is kept, so you can
                                    always come back for an older month.
                                @endif
                            </p>
                        </div>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
