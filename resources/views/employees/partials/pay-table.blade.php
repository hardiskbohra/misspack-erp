@php
    /* One table, one row per salary entry, with the payslip of that month on
       the same row — the office's record page and the employee's own page both
       draw from here. $context decides only what is office work: generating a
       slip, correcting one, and the draft state. The months, the figures, the
       slip numbers and the print links are identical on both sides, because a
       list that reads differently depending on who opened it is how two people
       end up disagreeing about one month. */
    $ctx = $context ?? 'office';
    $office = $ctx === 'office';
    $money = fn ($value, $currency) => \App\Helpers\CommonHelper::amount((float) $value, $currency);
    $page = fn (array $query) => route('users.show', array_merge(['user' => $user, 'tab' => 'salary', 'year' => $year], $query));
@endphp

<div class="master-table-wrap">
    <table class="master-table">
        <thead>
            <tr>
                <th scope="col">Month</th>
                <th scope="col">Particular</th>
                <th scope="col">Reference</th>
                <th scope="col">How</th>
                <th scope="col" class="is-num">Amount</th>
                <th scope="col">Payslip</th>
                <th scope="col">Action</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                @php($entry = $row['entry'])
                @php($slip = $row['slip'])
                <tr @if ($row['owns_slip']) id="pay-{{ $row['period'] }}" @endif
                    @if ($entry) class="is-clickable" data-href="{{ route('cashflows.show', $entry) }}" @endif>
                    <td data-label="Month">
                        <strong>{{ $row['label'] }}</strong>
                        @if ($entry)
                            <span class="master-sub">{{ $entry->entry_date?->format('d M Y') }}</span>
                        @else
                            <span class="master-sub">No ledger entry</span>
                        @endif
                    </td>
                    <td data-label="Particular">
                        @if ($entry)
                            <strong>{{ $entry->particular ?: 'Salary' }}</strong>
                            @if ($entry->notes)<span class="master-sub">{{ $entry->notes }}</span>@endif
                        @else
                            <span class="master-sub">The slip stands on its own — no payment is filed against this month.</span>
                        @endif
                    </td>
                    <td data-label="Reference">{{ $entry?->bank_reference_number ?: '—' }}</td>
                    <td data-label="How">
                        @if ($entry)
                            <span class="emp-pill {{ $entry->isMoneyOut() ? 'is-ok' : 'is-off' }}">
                                {{ $office
                                    ? ($entry->isMoneyOut() ? 'Paid out' : 'Back')
                                    : ($entry->isMoneyOut() ? 'Paid to you' : 'Back to the office') }}
                            </span>
                            <span class="master-sub">
                                {{ \App\Models\CashflowEntry::paymentModeOptions()[$entry->payment_mode] ?? 'Mode not set' }}
                                @if ($office && in_array($entry->accounting_status, ['pending', 'disputed'], true))
                                    · {{ $entry->statusLabel() }}
                                @endif
                            </span>
                        @else
                            —
                        @endif
                    </td>
                    <td class="is-num" data-label="Amount">
                        @if ($entry)
                            <strong>{{ $entry->signedAmountLabel() }}</strong>
                        @endif
                    </td>
                    <td data-label="Payslip">
                        @if ($slip)
                            <strong>{{ $slip->slipNumber() }}</strong>
                            <span class="master-sub">
                                {{ $slip->hasBreakdown() ? 'With breakdown' : 'Totals only' }}
                                @if ($office) · {{ $slip->statusLabel() }} @endif
                            </span>
                        @elseif (! $row['owns_slip'])
                            <a class="master-sub" href="#pay-{{ $row['period'] }}">Same month as the row above</a>
                        @elseif ($office)
                            <span class="master-sub">Not generated</span>
                        @else
                            <span class="master-sub">Being prepared</span>
                        @endif
                    </td>
                    <td data-label="Action">
                        <div class="emp-row-actions">
                            @if ($slip)
                                <a class="master-btn master-btn-soft master-btn-sm"
                                    href="{{ $office
                                        ? route('users.payslips.pdf', ['user' => $user, 'payslip' => $slip])
                                        : route('my.payslips.pdf', $slip) }}"
                                    target="_blank" rel="noopener" title="Open the payslip — print it or save it as a PDF">
                                    <i class="fas fa-file-invoice-dollar" aria-hidden="true"></i> Payslip
                                </a>
                                @if ($office)
                                    <a class="master-btn master-btn-ghost master-btn-sm"
                                        href="{{ $page(['payslip' => $slip->id]).'#payslip-edit' }}"
                                        title="Change the month's figures or its state">
                                        <i class="fas fa-pen" aria-hidden="true"></i> Modify
                                    </a>
                                @endif
                                {{-- The paper the office signed, when there is one: the
                                     slip above is printed from the record, this is the scan. --}}
                                @if ($slip->hasFile())
                                    <a class="master-btn master-btn-ghost master-btn-sm"
                                        href="{{ $office
                                            ? route('users.payslips.file', ['user' => $user, 'payslip' => $slip])
                                            : route('my.payslips.file', $slip) }}"
                                        title="The signed copy attached to this month">
                                        <i class="fas fa-paperclip" aria-hidden="true"></i> Attachment
                                    </a>
                                @endif
                                @if ($office && $slip->isIssued())
                                    @php($wa = $slip->whatsappUrl($user->mobile))
                                    @php($mail = $slip->mailUrl($user->email))
                                    @if ($wa)
                                        <a class="master-btn master-btn-ghost master-btn-sm" href="{{ $wa }}" target="_blank"
                                            rel="noopener" title="Send the month on WhatsApp" aria-label="Send this payslip on WhatsApp">
                                            <i class="fab fa-whatsapp" aria-hidden="true"></i>
                                        </a>
                                    @endif
                                    @if ($mail)
                                        <a class="master-btn master-btn-ghost master-btn-sm" href="{{ $mail }}"
                                            title="Send the month by email" aria-label="Send this payslip by email">
                                            <i class="fas fa-envelope" aria-hidden="true"></i>
                                        </a>
                                    @endif
                                @endif
                            @elseif ($office && $row['generatable'])
                                <a class="master-btn master-btn-primary master-btn-sm"
                                    href="{{ $page(['month' => $row['period']]).'#payslip-form' }}"
                                    title="Print the payslip for this month from its figures">
                                    <i class="fas fa-file-circle-plus" aria-hidden="true"></i> Generate payslip
                                </a>
                            @else
                                <span class="master-sub">—</span>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">
                        <div class="master-list-empty">
                            <span class="master-list-empty-icon" aria-hidden="true">₹</span>
                            <p class="master-list-empty-title">
                                {{ $office ? 'No salary entry in '.$year : 'No salary entry for you in '.$year }}
                            </p>
                            <p class="master-list-empty-text">
                                @if ($office)
                                    Pay is a cashflow entry filed against this person — record it in the ledger
                                    with the Employee set to their name and the month appears here, ready for
                                    its payslip. A salary is paid out, so it is a <strong>debit</strong> on the
                                    company's book.
                                @else
                                    A month appears here as the office pays it, with the payslip it issues
                                    beside it.
                                @endif
                            </p>
                            @if ($office)
                                <div class="master-list-empty-actions">
                                    <a class="master-btn master-btn-primary" href="{{ route('cashflows.create', ['employee_id' => $user->id]) }}">Record a payment</a>
                                </div>
                            @endif
                        </div>
                    </td>
                </tr>
            @endforelse
        </tbody>
        @if (! empty($total) && ($total['entries'] ?? 0) > 0)
            <tfoot>
                <tr class="master-list-total">
                    <td colspan="4"><strong>Net paid in {{ $year }}</strong></td>
                    <td class="is-num"><strong>{{ \App\Helpers\CommonHelper::indianCurrency($total['total']) }}</strong></td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
        @endif
    </table>
</div>
