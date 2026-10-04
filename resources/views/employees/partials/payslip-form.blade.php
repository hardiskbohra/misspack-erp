@php
    /* The payslip form, written once and used twice: recording a month, and
       correcting one. Both are dialogs — the salary tab carries a table, not a
       fourteen-field column, and a form that is only open when somebody is
       filling it in is a form nobody scrolls past.

       $mode 'create' | 'edit', $slip the row being corrected (null on create),
       $prefill the month and amount a row asked for, and $dialog the modal id
       the close button and the footer hang on. The fields keep their own key
       names: the lines arrive as earning_lines / deduction_lines, the totals as
       gross_amount / deductions / net_amount. */
    $edit = $mode === 'edit';
    $value = fn (string $key, $fallback = null) => old($key, $fallback);
    /* four rows a side, seeded with the office's own vocabulary, and filled —
       row by row — from the slip being corrected */
    $lineRows = function (string $field, string $side) use ($slip, $lines, $edit, $prefill) {
        $stored = $edit
            ? ($side === 'earnings' ? $slip->earnings() : $slip->deductionLines())
            : [];
        $defaults = array_slice($lines[$side], 0, 4);
        /* arrived from a ledger row? then the money it paid is the first
           earning, and the net follows from it */
        $asked = ! $edit && $side === 'earnings' && filled($prefill['earning_amount'] ?? null)
            ? (float) $prefill['earning_amount']
            : null;
        $rows = [];

        for ($i = 0; $i < 4; $i++) {
            $seeded = (string) (isset($stored[$i]) ? rtrim(rtrim(number_format((float) $stored[$i]['amount'], 2, '.', ''), '0'), '.') : '');

            if ($i === 0 && $asked !== null) {
                $seeded = rtrim(rtrim(number_format($asked, 2, '.', ''), '0'), '.');
            }

            $rows[] = [
                'label' => old($field.'.'.$i.'.label', $stored[$i]['label'] ?? ($defaults[$i] ?? '')),
                'amount' => old($field.'.'.$i.'.amount', $seeded),
            ];
        }

        return $rows;
    };
@endphp

<div class="master-modal-header">
    <div class="master-modal-heading">
        <span class="master-modal-icon" aria-hidden="true">₹</span>
        <div>
            <h3 class="master-modal-title" id="{{ $dialog }}Title">
                {{ $edit ? 'Correct the payslip for '.$slip->periodLabel() : 'Record a month' }}
            </h3>
            <p class="master-modal-subtitle">
                {{ $user->name }}
                @if ($edit)
                    · {{ $slip->slipNumber() }} · {{ $slip->statusLabel() }}
                @else
                    · {{ $user->employee_code ?: 'no employee code' }}
                @endif
            </p>
        </div>
    </div>
    <button type="button" class="master-modal-close" data-close-modal="{{ $dialog }}" aria-label="Close">&times;</button>
</div>

<div class="master-modal-body" data-payslip-form>
    @if ($errors->any())
        <div class="master-info-box is-danger">
            <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
            {{ $errors->first() }}
        </div>
    @endif

    <div class="master-section-label">The month</div>
    <div class="master-form-grid">
        <div class="master-field">
            <label class="master-label" for="{{ $dialog }}Period">Month <span class="master-required">*</span></label>
            <input class="master-input" id="{{ $dialog }}Period" type="month" name="period"
                value="{{ $value('period', $edit ? $slip->period : ($prefill['period'] ?? date('Y-m'))) }}" required>
            @error('period')<span class="master-error">{{ $message }}</span>@enderror
        </div>
        <div class="master-field">
            <label class="master-label" for="{{ $dialog }}PaidOn">Paid on</label>
            <input class="master-input" id="{{ $dialog }}PaidOn" type="date" name="paid_on"
                value="{{ $value('paid_on', $edit ? $slip->paid_on?->format('Y-m-d') : ($prefill['paid_on'] ?? '')) }}">
            @error('paid_on')<span class="master-error">{{ $message }}</span>@enderror
        </div>
        <div class="master-field">
            <label class="master-label" for="{{ $dialog }}Working">Working days</label>
            <input class="master-input" id="{{ $dialog }}Working" name="working_days" inputmode="numeric"
                placeholder="26" value="{{ $value('working_days', $edit ? $slip->working_days : '') }}">
            @error('working_days')<span class="master-error">{{ $message }}</span>@enderror
        </div>
        <div class="master-field">
            <label class="master-label" for="{{ $dialog }}PaidDays">Paid days</label>
            <input class="master-input" id="{{ $dialog }}PaidDays" name="paid_days" inputmode="numeric"
                placeholder="25" value="{{ $value('paid_days', $edit ? $slip->paid_days : '') }}">
            <span class="master-sub">What is left between the two prints as LOP.</span>
            @error('paid_days')<span class="master-error">{{ $message }}</span>@enderror
        </div>
    </div>

    <div class="master-section-label">What the slip says</div>
    <div class="master-form-grid user-payslip-lines">
        @foreach (['earnings' => ['field' => 'earning_lines', 'title' => 'Earnings'], 'deductions' => ['field' => 'deduction_lines', 'title' => 'Deductions']] as $side => $meta)
            <div class="master-field">
                <span class="master-label">{{ $meta['title'] }}</span>
                @foreach ($lineRows($meta['field'], $side) as $i => $row)
                    <div class="user-line-row">
                        <input class="master-input" name="{{ $meta['field'] }}[{{ $i }}][label]" value="{{ $row['label'] }}"
                            placeholder="Line {{ $i + 1 }}" aria-label="{{ $meta['title'] }} line {{ $i + 1 }}">
                        <input class="master-input is-amount" name="{{ $meta['field'] }}[{{ $i }}][amount]" value="{{ $row['amount'] }}"
                            inputmode="decimal" placeholder="0" data-payslip-amount
                            aria-label="{{ $meta['title'] }} line {{ $i + 1 }} amount">
                    </div>
                @endforeach
                @error($meta['field'].'.*.amount')<span class="master-error">{{ $message }}</span>@enderror
            </div>
        @endforeach
    </div>

    {{-- The totals the slip will print, as they are typed: the same arithmetic
         the server does when it saves, so the office never has to guess. --}}
    <div class="pay-totals" data-payslip-totals>
        <span>Earned <strong data-payslip-total="earned">—</strong></span>
        <span>Deducted <strong data-payslip-total="deducted">—</strong></span>
        <span>Net <strong data-payslip-total="net">—</strong></span>
        <em>the slip prints the net in words as well</em>
    </div>

    <div class="master-form-grid">
        <div class="master-field">
            <label class="master-label" for="{{ $dialog }}Net">Net paid</label>
            <input class="master-input" id="{{ $dialog }}Net" name="net_amount"
                value="{{ $value('net_amount', $edit ? $slip->net_amount : '') }}"
                inputmode="decimal" placeholder="Left blank: earned − deductions" data-payslip-net>
            @error('net_amount')<span class="master-error">{{ $message }}</span>@enderror
        </div>
        <div class="master-field">
            <label class="master-label" for="{{ $dialog }}Status">Status</label>
            <select class="master-select" id="{{ $dialog }}Status" name="status">
                @foreach ($statuses as $key => $label)
                    <option value="{{ $key }}" @selected(old('status', $edit ? $slip->status : 'issued') === $key)>{{ $label }}</option>
                @endforeach
            </select>
            <span class="master-sub">A draft is the office's working copy — the employee cannot see it.</span>
        </div>
        <div class="master-field">
            <label class="master-label" for="{{ $dialog }}File">Signed copy (optional)</label>
            <input class="master-input emp-file-input" id="{{ $dialog }}File" type="file" name="attachment">
            <span class="master-sub">
                The slip itself is printed from these figures — attach a scan only if you have one.
            </span>
            @if ($edit && $slip->hasFile())
                <label class="master-check">
                    <input type="checkbox" name="remove_attachment" value="1" @checked(old('remove_attachment'))>
                    <span>Remove the attached copy</span>
                </label>
            @endif
        </div>
        <div class="master-field">
            <label class="master-label" for="{{ $dialog }}Notes">Note on the slip</label>
            <input class="master-input" id="{{ $dialog }}Notes" name="notes" value="{{ $value('notes', $edit ? $slip->notes : '') }}"
                placeholder="Printed on the payslip">
            @error('notes')<span class="master-error">{{ $message }}</span>@enderror
        </div>
    </div>
</div>

<div class="master-modal-footer">
    @if ($edit)
        {{-- A dialog cannot hold a second form: the remove button submits the
             one that waits outside the card, and asks first. --}}
        <button type="submit" form="{{ $dialog }}Delete" class="master-btn master-btn-danger"
            onclick="return confirm('Remove the payslip for {{ $slip->periodLabel() }}? The ledger entry is not touched.');">
            <i class="fas fa-trash-can" aria-hidden="true"></i> Remove
        </button>
    @endif
    <button type="button" class="master-btn master-btn-light" data-close-modal="{{ $dialog }}">Cancel</button>
    <button type="submit" class="master-btn master-btn-primary">
        <i class="fas fa-save" aria-hidden="true"></i>
        {{ $edit ? 'Save the correction' : 'Record the payslip' }}
    </button>
</div>
