@extends('layouts.app')

@section('title', $user->name)
@section('page-title', $user->name)

@section('page-actions')
    <a class="master-btn master-btn-ghost" href="{{ route('users.index') }}">All users</a>
    @if (! $user->isEmployee())
        <span class="emp-pill is-off">Office account — full access</span>
    @else
        <span class="emp-pill is-off">Signs in as an employee — sees only their own record</span>
    @endif
@endsection

@section('content')
@push('styles')
    <link rel="stylesheet" href="{{ $assetVer('assets/css/employees.css') }}">
@endpush

@php
    /* The tab strip is drawn from the controller's own list, and the panel
       names have to match it — a tab that opens nothing is worse than no tab. */
    $tabUrl = fn (string $key) => route('users.show', ['user' => $user, 'tab' => $key, 'year' => $year]);
@endphp

<div class="emp employee-record master-list"
    data-user-id="{{ $user->id }}"
    data-user-tab="{{ $tab }}">

    {{-- Identity, then the five figures. Both are read at a glance and both are
         the same on every tab, so they sit above the strip rather than inside
         it. --}}
    <div class="master-card master-card--flat emp-record-head">
        <div class="emp-record-who">
            <span class="emp-avatar" aria-hidden="true">{{ $user->initials ?: strtoupper(substr($user->name, 0, 2)) }}</span>
            <div>
                <h1>{{ $user->name }}</h1>
                <p class="master-sub emp-record-line">
                    {{ $user->designation ?: 'No designation' }}
                    @if ($user->department) · {{ $user->department }}@endif
                    @if ($user->employee_code) · {{ $user->employee_code }}@endif
                    @if ($user->date_of_joining) · joined {{ $user->date_of_joining->format('d M Y') }}@endif
                </p>
                <p class="emp-record-tags">
                    <span class="emp-pill {{ $user->isAdmin() ? 'is-info' : 'is-ok' }}">{{ $user->roleLabel() }}</span>
                    <span class="emp-pill is-{{ $user->employmentStatusTone() === 'ok' ? 'ok' : ($user->employmentStatusTone() === 'warn' ? 'warn' : 'off') }}">
                        {{ $user->employmentStatusLabel() }}
                    </span>
                    <span class="emp-pill is-off">{{ $user->email }}</span>
                    @if ($user->mobile)<span class="emp-pill is-off">{{ $user->mobile }}</span>@endif
                    @if ($user->employment_type)<span class="emp-pill is-off">{{ $user->employmentTypeLabel() }}</span>@endif
                </p>
            </div>
        </div>
    </div>

    <div class="master-stats desktop-only">
        <div class="master-stat master-stat--flat green">
            <span class="icon" aria-hidden="true">₹</span>
            <div>
                <p class="master-stat-title">Paid in {{ $year }}</p>
                <p class="master-stat-value">{{ \App\Helpers\CommonHelper::indianCurrency($total['total']) }}</p>
                <p class="master-sub">{{ $total['entries'] }} {{ \Illuminate\Support\Str::plural('entry', $total['entries']) }} · {{ $total['months_paid'] }} {{ \Illuminate\Support\Str::plural('month', $total['months_paid']) }}</p>
            </div>
        </div>
        <div class="master-stat master-stat--flat blue">
            <span class="icon" aria-hidden="true">🧾</span>
            <div>
                <p class="master-stat-title">Payslips</p>
                <p class="master-stat-value">{{ $payslips->count() }}</p>
                <p class="master-sub">{{ $payslips->where('status', 'draft')->count() }} still draft</p>
            </div>
        </div>
        <div class="master-stat master-stat--flat {{ $checklist['complete'] ? 'teal' : 'orange' }}">
            <span class="icon" aria-hidden="true">📄</span>
            <div>
                <p class="master-stat-title">Documents</p>
                <p class="master-stat-value">{{ $checklist['present'] }}/{{ $checklist['required'] }}</p>
                <p class="master-sub">{{ $checklist['verified'] }} checked by the office</p>
            </div>
        </div>
        <div class="master-stat master-stat--flat purple">
            <span class="icon" aria-hidden="true">🖇</span>
            <div>
                <p class="master-stat-title">Files on pay</p>
                <p class="master-stat-value">{{ $attachments->count() }}</p>
                <p class="master-sub">attached to their salary entries</p>
            </div>
        </div>
        <div class="master-stat master-stat--flat {{ $record['complete'] ? 'green' : 'red' }}">
            <span class="icon" aria-hidden="true">{{ $record['complete'] ? '✓' : '!' }}</span>
            <div>
                <p class="master-stat-title">Profile</p>
                <p class="master-stat-value">{{ $record['complete'] ? 'Complete' : count($record['missing']) . ' to add' }}</p>
                <p class="master-sub">{{ $record['complete'] ? 'nothing outstanding' : implode(', ', array_slice($record['missing'], 0, 2)) }}</p>
            </div>
        </div>
    </div>

    {{-- The tabs themselves. Links, not buttons: every panel has its own URL, so
         a tab can be shared, bookmarked, opened in a new tab, and the back
         button works. The script only moves the active class. --}}
    <div class="master-tabs-card">
        <div class="master-tabs" role="tablist" aria-label="Record sections">
            @foreach ($tabs as $key => $label)
                <a class="master-tab {{ $tab === $key ? 'is-active' : '' }}"
                    role="tab"
                    aria-selected="{{ $tab === $key ? 'true' : 'false' }}"
                    href="{{ $tabUrl($key) }}"
                    data-user-tab-link="{{ $key }}">
                    {{ $label }}
                    @if (($tabCounts[$key] ?? 0) > 0)
                        <span class="master-tab-count">{{ $tabCounts[$key] }}</span>
                    @endif
                </a>
            @endforeach
        </div>

        {{-- One panel is rendered at a time: the tabs are a reading order, and a
             page that rendered all six would carry every query at once. --}}
        <div class="master-tabs-panels">
            @if ($tab === 'overview')
                <section class="master-tab-panel" aria-label="Overview">
                    <div class="master-grid">
                        <div class="master-card master-card--flat master-section">
                            <h3 class="master-section-title">This year, month by month</h3>
                            @if ($employee)
                                @include('employees.partials.pay-months', ['months' => $months, 'currency' => 'INR'])
                                <div class="emp-card-foot">
                                    <a class="master-btn master-btn-soft master-btn-sm" href="{{ $tabUrl('salary') }}">All salary entries</a>
                                </div>
                            @else
                                <p class="master-sub">
                                    This is an office account: it can open every screen in the ERP, and it is
                                    not on the payroll. Switch the role to <strong>Employee</strong> to give it
                                    a workspace of its own.
                                </p>
                            @endif
                        </div>

                        <div class="master-card master-card--flat master-section">
                            <h3 class="master-section-title">What is missing</h3>
                            <ul class="emp-todo">
                                @forelse ($record['missing'] as $missing)
                                    <li>
                                        <span class="emp-todo-dot" aria-hidden="true"></span>
                                        <span><strong>{{ $missing }}</strong> is missing from the record.</span>
                                        <a class="master-btn master-btn-ghost master-btn-sm" href="{{ $tabUrl('details') }}">Open details</a>
                                    </li>
                                @empty
                                    <li><span class="emp-todo-dot is-ok" aria-hidden="true"></span><span>The profile is complete.</span></li>
                                @endforelse

                                @foreach ($checklist['rows'] as $row)
                                    @if ($row['required'] && ! $row['present'])
                                        <li>
                                            <span class="emp-todo-dot" aria-hidden="true"></span>
                                            <span><strong>{{ $row['label'] }}</strong> is not on file.</span>
                                            <a class="master-btn master-btn-ghost master-btn-sm" href="{{ $tabUrl('documents') }}">File it</a>
                                        </li>
                                    @endif
                                @endforeach

                                @if ($employee && $payslips->isEmpty())
                                    <li>
                                        <span class="emp-todo-dot is-info" aria-hidden="true"></span>
                                        <span>No payslip has been issued yet.</span>
                                        <a class="master-btn master-btn-ghost master-btn-sm" href="{{ $tabUrl('payslips') }}">Record one</a>
                                    </li>
                                @endif
                            </ul>
                        </div>
                    </div>

                    <div class="master-grid">
                        <div class="master-card master-card--flat master-section">
                            <h3 class="master-section-title">The record, in short</h3>
                            <div class="master-detail-list">
                                @foreach ($record['groups'] as $group)
                                    @foreach ($group['fields'] as $field)
                                        <div class="master-info">
                                            <span>{{ $field['label'] }}</span>
                                            <strong class="{{ blank($field['value']) ? 'is-blank' : '' }}">
                                                {{ blank($field['value']) ? 'Not on file' : $field['value'] }}
                                            </strong>
                                        </div>
                                    @endforeach
                                @endforeach
                            </div>
                        </div>

                        <div class="master-card master-card--flat master-section">
                            <h3 class="master-section-title">Work assigned</h3>
                            @if ($tasks->isEmpty())
                                <p class="master-sub">Nothing assigned to this person right now.</p>
                            @else
                                <ul class="user-task-list">
                                    @foreach ($tasks as $task)
                                        <li class="user-task {{ $task->status === 'completed' ? 'is-done' : '' }}">
                                            <span class="user-task-dot" aria-hidden="true"></span>
                                            <span class="user-task-title">{{ $task->title }}</span>
                                            <span class="master-sub">
                                                {{ $task->due_date ? $task->due_date->format('d M Y') : 'no due date' }}
                                                · {{ ucfirst(str_replace('_', ' ', (string) $task->status)) }}
                                            </span>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    </div>
                </section>
            @endif

            @if ($tab === 'details')
                <section class="master-tab-panel" aria-label="Details">
                    <div class="master-grid">
                        @foreach ($record['groups'] as $group)
                            <div class="master-card master-card--flat master-section">
                                <h3 class="master-section-title">{{ $group['title'] }}</h3>
                                <div class="master-detail-list">
                                    @foreach ($group['fields'] as $field)
                                        <div class="master-info">
                                            <span>{{ $field['label'] }}</span>
                                            <strong class="{{ blank($field['value']) ? 'is-blank' : '' }}">
                                                {{ blank($field['value']) ? 'Not on file' : $field['value'] }}
                                            </strong>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="master-card master-card--flat master-section">
                        <h3 class="master-section-title">Edit the record</h3>
                        <form method="POST" action="{{ route('users.update', $user) }}">
                            @csrf
                            @method('PUT')
                            <div class="master-form-grid">
                                <div class="master-field">
                                    <label class="master-label" for="recordName">Name</label>
                                    <input class="master-input" id="recordName" name="name" value="{{ old('name', $user->name) }}" required>
                                    @error('name')<p class="master-error">{{ $message }}</p>@enderror
                                </div>
                                <div class="master-field">
                                    <label class="master-label" for="recordEmail">Email</label>
                                    <input class="master-input" id="recordEmail" type="email" name="email" value="{{ old('email', $user->email) }}" required>
                                    @error('email')<p class="master-error">{{ $message }}</p>@enderror
                                </div>
                                <div class="master-field">
                                    <label class="master-label" for="recordRole">Role</label>
                                    <select class="master-select" id="recordRole" name="role" required data-role-select @disabled($isSelf)>
                                        @foreach ($roles as $key => $label)
                                            <option value="{{ $key }}" @selected(old('role', $user->role ?: 'admin') === $key)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    @if ($isSelf)
                                        <input type="hidden" name="role" value="{{ $user->role ?: 'admin' }}">
                                        <p class="master-sub user-modal-foot">You cannot change your own role.</p>
                                    @elseif ($isLastAdmin)
                                        <input type="hidden" name="role" value="{{ $user->role ?: 'admin' }}">
                                        <p class="master-sub user-modal-foot">This is the last administrator, so the role cannot be changed.</p>
                                    @endif
                                    @error('role')<p class="master-error">{{ $message }}</p>@enderror
                                </div>
                                <div class="master-field">
                                    <label class="master-label" for="recordCode">Employee code</label>
                                    <input class="master-input" id="recordCode" name="employee_code" value="{{ old('employee_code', $user->employee_code) }}">
                                    @error('employee_code')<p class="master-error">{{ $message }}</p>@enderror
                                </div>
                                <div class="master-field">
                                    <label class="master-label" for="recordDesignation">Designation <span class="master-required" data-role-required>*</span></label>
                                    <input class="master-input" id="recordDesignation" name="designation" value="{{ old('designation', $user->designation) }}">
                                </div>
                                <div class="master-field">
                                    <label class="master-label" for="recordDepartment">Department</label>
                                    <input class="master-input" id="recordDepartment" name="department" value="{{ old('department', $user->department) }}">
                                </div>
                                <div class="master-field">
                                    <label class="master-label" for="recordJoining">Date of joining <span class="master-required" data-role-required>*</span></label>
                                    <input class="master-input" id="recordJoining" type="date" name="date_of_joining"
                                        value="{{ old('date_of_joining', $user->date_of_joining?->format('Y-m-d')) }}">
                                </div>
                                <div class="master-field">
                                    <label class="master-label" for="recordDob">Date of birth</label>
                                    <input class="master-input" id="recordDob" type="date" name="date_of_birth"
                                        value="{{ old('date_of_birth', $user->date_of_birth?->format('Y-m-d')) }}">
                                </div>
                                <div class="master-field">
                                    <label class="master-label" for="recordType">Employment type</label>
                                    <select class="master-select" id="recordType" name="employment_type">
                                        <option value="">—</option>
                                        @foreach ($employmentTypes as $key => $label)
                                            <option value="{{ $key }}" @selected(old('employment_type', $user->employment_type) === $key)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="master-field">
                                    <label class="master-label" for="recordStatus">Employment status</label>
                                    <select class="master-select" id="recordStatus" name="employment_status">
                                        @foreach ($employmentStatuses as $key => $label)
                                            <option value="{{ $key }}" @selected(old('employment_status', $user->employment_status ?: 'active') === $key)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="master-field">
                                    <label class="master-label" for="recordPan">PAN</label>
                                    <input class="master-input" id="recordPan" name="pan_number" value="{{ old('pan_number', $user->pan_number) }}">
                                </div>
                                <div class="master-field">
                                    <label class="master-label" for="recordMobile">Mobile <span class="master-required" data-role-required>*</span></label>
                                    <input class="master-input" id="recordMobile" name="mobile" value="{{ old('mobile', $user->mobile) }}">
                                </div>
                                <div class="master-field">
                                    <label class="master-label" for="recordBank">Bank</label>
                                    <input class="master-input" id="recordBank" name="bank_name" value="{{ old('bank_name', $user->bank_name) }}">
                                </div>
                                <div class="master-field">
                                    <label class="master-label" for="recordAccountName">Account name</label>
                                    <input class="master-input" id="recordAccountName" name="bank_account_name" value="{{ old('bank_account_name', $user->bank_account_name) }}">
                                </div>
                                <div class="master-field">
                                    <label class="master-label" for="recordAccount">Account number</label>
                                    <input class="master-input" id="recordAccount" name="bank_account_number" value="{{ old('bank_account_number', $user->bank_account_number) }}">
                                </div>
                                <div class="master-field">
                                    <label class="master-label" for="recordIfsc">IFSC</label>
                                    <input class="master-input" id="recordIfsc" name="bank_ifsc" value="{{ old('bank_ifsc', $user->bank_ifsc) }}">
                                </div>
                                <div class="master-field full">
                                    <label class="master-label" for="recordAddress">Address</label>
                                    <textarea class="master-input" id="recordAddress" name="address" rows="2">{{ old('address', $user->address) }}</textarea>
                                </div>
                                <div class="master-field">
                                    <label class="master-label" for="recordEmergency">Emergency contact</label>
                                    <input class="master-input" id="recordEmergency" name="emergency_contact_name" value="{{ old('emergency_contact_name', $user->emergency_contact_name) }}">
                                </div>
                                <div class="master-field">
                                    <label class="master-label" for="recordEmergencyMobile">Emergency mobile</label>
                                    <input class="master-input" id="recordEmergencyMobile" name="emergency_contact_mobile" value="{{ old('emergency_contact_mobile', $user->emergency_contact_mobile) }}">
                                </div>
                                <div class="master-field">
                                    <label class="master-label" for="recordPassword">New password (leave blank to keep it)</label>
                                    <input class="master-input" id="recordPassword" type="password" name="password" autocomplete="new-password">
                                    @error('password')<p class="master-error">{{ $message }}</p>@enderror
                                </div>
                                <div class="master-field">
                                    <label class="master-label" for="recordPasswordConfirm">Repeat password</label>
                                    <input class="master-input" id="recordPasswordConfirm" type="password" name="password_confirmation" autocomplete="new-password">
                                </div>
                            </div>
                            <div class="master-actions">
                                <button class="master-btn master-btn-primary" type="submit">Save the record</button>
                            </div>
                        </form>
                    </div>
                </section>
            @endif

            @if ($tab === 'salary')
                <section class="master-tab-panel" aria-label="Salary">
                    @if (! $employee)
                        <div class="master-card master-card--flat master-section">
                            <p class="master-sub">An office account is not paid through this module, so it has no salary entries.</p>
                        </div>
                    @else
                        <div class="master-card master-card--flat">
                            <div class="master-list-bar">
                                <div class="master-list-chips">
                                    @foreach ($years as $option)
                                        <a class="master-list-chip {{ (int) $option === (int) $year ? 'is-active' : '' }}"
                                            href="{{ route('users.show', ['user' => $user, 'tab' => 'salary', 'year' => $option]) }}">{{ $option }}</a>
                                    @endforeach
                                </div>
                                <p class="master-sub">
                                    Every credit the ledger filed against this person, month by month.
                                </p>
                            </div>

                            <div class="emp-months-wrap">
                                @include('employees.partials.pay-months', ['months' => $months, 'currency' => 'INR'])
                            </div>
                        </div>

                        <div class="master-card master-table-card master-card--flat">
                            <div class="master-list-toolbar">
                                <p class="master-list-hint">
                                    {{ $total['entries'] }} {{ \Illuminate\Support\Str::plural('entry', $total['entries']) }} in {{ $year }} · total
                                    <strong>{{ \App\Helpers\CommonHelper::indianCurrency($total['total']) }}</strong>
                                </p>
                                <div class="master-list-toolbar-actions">
                                    <a class="master-btn master-btn-soft master-btn-sm"
                                        href="{{ route('cashflows.index', ['employee_id' => $user->id, 'transaction_type' => 'credit']) }}">
                                        <i class="fas fa-arrow-up-right-from-square" aria-hidden="true"></i> Open in the ledger
                                    </a>
                                </div>
                            </div>

                            <div class="master-table-wrap">
                                <table class="master-table">
                                    <thead>
                                        <tr>
                                            <th scope="col">Date</th>
                                            <th scope="col">Particular</th>
                                            <th scope="col">Reference</th>
                                            <th scope="col">Mode</th>
                                            <th scope="col" class="is-num">Credited</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($salaryEntries as $entry)
                                            <tr class="is-clickable" data-href="{{ route('cashflows.show', $entry) }}">
                                                <td data-label="Date">{{ $entry->entry_date?->format('d M Y') }}</td>
                                                <td data-label="Particular">
                                                    <strong>{{ $entry->particular ?: 'Salary' }}</strong>
                                                    @if ($entry->notes)<span class="master-sub">{{ $entry->notes }}</span>@endif
                                                </td>
                                                <td data-label="Reference">{{ $entry->bank_reference_number ?: '—' }}</td>
                                                <td data-label="Mode">{{ \App\Models\CashflowEntry::paymentModeOptions()[$entry->payment_mode] ?? '—' }}</td>
                                                <td class="is-num" data-label="Credited">
                                                    <strong>{{ \App\Helpers\CommonHelper::amount($entry->credit_amount, $entry->currency ?: 'INR') }}</strong>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5">
                                                    <div class="master-list-empty">
                                                        <span class="master-list-empty-icon" aria-hidden="true">₹</span>
                                                        <p class="master-list-empty-title">No salary credited in {{ $year }}</p>
                                                        <p class="master-list-empty-text">
                                                            Pay is a cashflow entry filed against this person — record it in the ledger
                                                            with the Employee set to their name and it appears here.
                                                        </p>
                                                        <div class="master-list-empty-actions">
                                                            <a class="master-btn master-btn-primary" href="{{ route('cashflows.create', ['employee_id' => $user->id]) }}">Record a payment</a>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                    @if ($salaryEntries->isNotEmpty())
                                        <tfoot>
                                            <tr class="master-list-total">
                                                <td colspan="4"><strong>Total credited in {{ $year }}</strong></td>
                                                <td class="is-num"><strong>{{ \App\Helpers\CommonHelper::indianCurrency($total['total']) }}</strong></td>
                                            </tr>
                                        </tfoot>
                                    @endif
                                </table>
                            </div>
                        </div>
                    @endif
                </section>
            @endif

            @if ($tab === 'payslips')
                <section class="master-tab-panel" aria-label="Payslips">
                    @if (! $employee)
                        <div class="master-card master-card--flat master-section">
                            <p class="master-sub">Payslips belong to an employee account. Switch the role to Employee first.</p>
                        </div>
                    @else
                        <div class="master-card master-card--flat master-section">
                            <h3 class="master-section-title">Record a month</h3>
                            <form method="POST" action="{{ route('users.payslips.store', $user) }}" enctype="multipart/form-data">
                                @csrf
                                <div class="master-form-grid">
                                    <div class="master-field">
                                        <label class="master-label" for="payslipPeriod">Month</label>
                                        <input class="master-input" id="payslipPeriod" type="month" name="period" value="{{ old('period', date('Y-m')) }}" required>
                                        @error('period')<p class="master-error">{{ $message }}</p>@enderror
                                    </div>
                                    <div class="master-field">
                                        <label class="master-label" for="payslipGross">Gross</label>
                                        <input class="master-input" id="payslipGross" name="gross_amount" value="{{ old('gross_amount') }}" placeholder="0">
                                        @error('gross_amount')<p class="master-error">{{ $message }}</p>@enderror
                                    </div>
                                    <div class="master-field">
                                        <label class="master-label" for="payslipDeductions">Deductions</label>
                                        <input class="master-input" id="payslipDeductions" name="deductions" value="{{ old('deductions') }}" placeholder="0">
                                        @error('deductions')<p class="master-error">{{ $message }}</p>@enderror
                                    </div>
                                    <div class="master-field">
                                        <label class="master-label" for="payslipNet">Net paid</label>
                                        <input class="master-input" id="payslipNet" name="net_amount" value="{{ old('net_amount') }}"
                                            placeholder="Left blank: gross − deductions">
                                        @error('net_amount')<p class="master-error">{{ $message }}</p>@enderror
                                    </div>
                                    <div class="master-field">
                                        <label class="master-label" for="payslipPaidOn">Paid on</label>
                                        <input class="master-input" id="payslipPaidOn" type="date" name="paid_on" value="{{ old('paid_on') }}">
                                    </div>
                                    <div class="master-field">
                                        <label class="master-label" for="payslipStatus">Status</label>
                                        <select class="master-select" id="payslipStatus" name="status">
                                            @foreach ($payslipStatuses as $key => $label)
                                                <option value="{{ $key }}" @selected(old('status', 'issued') === $key)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="master-field full">
                                        <label class="master-label" for="payslipFile">Slip file (optional)</label>
                                        <input class="master-input emp-file-input" id="payslipFile" type="file" name="attachment">
                                    </div>
                                    <div class="master-field full">
                                        <label class="master-label" for="payslipNotes">Notes</label>
                                        <input class="master-input" id="payslipNotes" name="notes" value="{{ old('notes') }}">
                                    </div>
                                </div>
                                <div class="master-actions">
                                    <button class="master-btn master-btn-primary" type="submit">Record the payslip</button>
                                </div>
                            </form>
                        </div>

                        <div class="master-card master-table-card master-card--flat">
                            <div class="master-list-toolbar">
                                <p class="master-list-hint" title="A draft is the office's working copy — the employee cannot see it until it is issued.">
                                    {{ $payslips->count() }} {{ \Illuminate\Support\Str::plural('payslip', $payslips->count()) }} on file · drafts stay private
                                </p>
                            </div>

                            <div class="master-table-wrap">
                                <table class="master-table">
                                    <thead>
                                        <tr>
                                            <th scope="col">Month</th>
                                            <th scope="col" class="is-num">Net</th>
                                            <th scope="col">Paid on</th>
                                            <th scope="col">Status</th>
                                            <th scope="col">File</th>
                                            <th scope="col">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($payslips as $payslip)
                                            <tr>
                                                <td data-label="Month">
                                                    <strong>{{ $payslip->periodLabel() }}</strong>
                                                    @if ($payslip->entry)
                                                        <span class="master-sub">matched to the ledger entry of {{ $payslip->entry->entry_date?->format('d M Y') }}</span>
                                                    @endif
                                                </td>
                                                <td class="is-num" data-label="Net">{{ \App\Helpers\CommonHelper::amount($payslip->net_amount, $payslip->currency) }}</td>
                                                <td data-label="Paid on">{{ $payslip->paidOnLabel() }}</td>
                                                <td data-label="Status">
                                                    <span class="emp-pill {{ $payslip->isIssued() ? 'is-ok' : 'is-warn' }}">{{ $payslip->statusLabel() }}</span>
                                                </td>
                                                <td data-label="File">
                                                    @if ($payslip->hasFile())
                                                        <a href="{{ route('users.payslips.file', ['user' => $user, 'payslip' => $payslip]) }}">Download</a>
                                                    @else
                                                        <span class="master-sub">—</span>
                                                    @endif
                                                </td>
                                                <td data-label="Action">
                                                    <div class="emp-row-actions">
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
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6">
                                                    <div class="master-list-empty">
                                                        <span class="master-list-empty-icon" aria-hidden="true">🧾</span>
                                                        <p class="master-list-empty-title">No payslip yet</p>
                                                        <p class="master-list-empty-text">
                                                            Record a month above. The figures are the record; attaching the signed
                                                            slip is optional, and the employee sees it the moment it is issued.
                                                        </p>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                </section>
            @endif

            @if ($tab === 'documents')
                <section class="master-tab-panel" aria-label="Documents">
                    @if ($employee)
                        <div class="master-card master-card--flat master-section">
                            <h3 class="master-section-title">Add a document for them</h3>
                            <p class="master-sub user-modal-foot">
                                Papers the office collects itself — an offer letter, a signed form. They appear in the
                                employee's own workspace marked as filed by the office.
                            </p>
                            <form method="POST" action="{{ route('users.documents.store', $user) }}" enctype="multipart/form-data">
                                @csrf
                                <div class="master-form-grid">
                                    <div class="master-field">
                                        <label class="master-label" for="officeDocType">Type</label>
                                        <select class="master-select" id="officeDocType" name="document_type" required>
                                            @foreach ($documentTypes as $key => $label)
                                                <option value="{{ $key }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="master-field">
                                        <label class="master-label" for="officeDocTitle">Title</label>
                                        <input class="master-input" id="officeDocTitle" name="title" placeholder="Optional">
                                    </div>
                                    <div class="master-field">
                                        <label class="master-label" for="officeDocNumber">Number</label>
                                        <input class="master-input" id="officeDocNumber" name="document_number" placeholder="Optional">
                                    </div>
                                    <div class="master-field">
                                        <label class="master-label" for="officeDocExpiry">Expires</label>
                                        <input class="master-input" id="officeDocExpiry" type="date" name="expires_on">
                                    </div>
                                    <div class="master-field full">
                                        <label class="master-label" for="officeDocFiles">Files</label>
                                        <input class="master-input emp-file-input" id="officeDocFiles" type="file" name="documents[]" multiple required>
                                        @error('documents')<p class="master-error">{{ $message }}</p>@enderror
                                        @error('documents.*')<p class="master-error">{{ $message }}</p>@enderror
                                    </div>
                                </div>
                                <div class="master-actions">
                                    <button class="master-btn master-btn-primary" type="submit">File it</button>
                                </div>
                            </form>
                        </div>
                    @endif

                    <div class="master-card master-card--flat master-section">
                        @include('employees.partials.document-checklist', [
                            'checklist' => $checklist,
                            'mode' => 'office',
                            'user' => $user,
                        ])
                    </div>

                    <div class="master-card master-table-card master-card--flat">
                        <div class="master-list-toolbar">
                            <p class="master-list-hint" title="Bills the office attached to the entries that paid this person — a bank slip, a signed voucher.">
                                Files attached to their pay
                            </p>
                        </div>

                        <div class="master-table-wrap">
                            <table class="master-table">
                                <thead>
                                    <tr>
                                        <th scope="col">Document</th>
                                        <th scope="col">Filed on</th>
                                        <th scope="col">Size</th>
                                        <th scope="col">File</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($attachments as $attachment)
                                        <tr>
                                            <td data-label="Document">
                                                <strong>{{ $attachment->title ?: $attachment->documentTypeLabel() }}</strong>
                                                @if ($attachment->cashflowEntry)
                                                    <span class="master-sub">
                                                        {{ $attachment->cashflowEntry->entry_date?->format('d M Y') }} ·
                                                        {{ $attachment->cashflowEntry->particular ?: 'Salary' }}
                                                    </span>
                                                @endif
                                            </td>
                                            <td data-label="Filed on">{{ $attachment->created_at?->format('d M Y') ?: '—' }}</td>
                                            <td data-label="Size">{{ $attachment->sizeLabel() }}</td>
                                            <td data-label="File">
                                                <a href="{{ $attachment->url() }}" target="_blank" rel="noopener">Open</a>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4">
                                                <div class="master-list-empty">
                                                    <span class="master-list-empty-icon" aria-hidden="true">🖇</span>
                                                    <p class="master-list-empty-title">No files on their pay yet</p>
                                                    <p class="master-list-empty-text">
                                                        Attach a bill or a bank slip to a salary entry in the ledger and it
                                                        appears here — and in the employee's own workspace.
                                                    </p>
                                                    <div class="master-list-empty-actions">
                                                        <a class="master-btn master-btn-soft"
                                                            href="{{ route('cashflows.documents', ['related_party_type' => 'employee', 'employee_id' => $user->id]) }}">
                                                            Open the documents archive
                                                        </a>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
            @endif

            @if ($tab === 'work')
                <section class="master-tab-panel" aria-label="Work">
                    <div class="master-card master-table-card master-card--flat">
                        <div class="master-list-toolbar">
                            <p class="master-list-hint">
                                {{ $tasks->count() }} {{ \Illuminate\Support\Str::plural('task', $tasks->count()) }} assigned
                                · {{ $openTasks }} still open
                            </p>
                            <div class="master-list-toolbar-actions">
                                <a class="master-btn master-btn-soft master-btn-sm" href="{{ route('tasks.index') }}">
                                    <i class="fas fa-list-check" aria-hidden="true"></i> All tasks
                                </a>
                            </div>
                        </div>

                        <div class="master-table-wrap">
                            <table class="master-table">
                                <thead>
                                    <tr>
                                        <th scope="col">Task</th>
                                        <th scope="col">Priority</th>
                                        <th scope="col">Due</th>
                                        <th scope="col">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($tasks as $task)
                                        <tr class="is-clickable" data-href="{{ route('tasks.edit', $task) }}">
                                            <td data-label="Task">
                                                <strong>{{ $task->title }}</strong>
                                                @if ($task->description)<span class="master-sub">{{ \Illuminate\Support\Str::limit($task->description, 90) }}</span>@endif
                                            </td>
                                            <td data-label="Priority">{{ ucfirst((string) ($task->priority ?: '—')) }}</td>
                                            <td data-label="Due">{{ $task->due_date?->format('d M Y') ?: '—' }}</td>
                                            <td data-label="Status">
                                                <span class="emp-pill {{ $task->status === 'completed' ? 'is-ok' : 'is-info' }}">
                                                    {{ ucfirst(str_replace('_', ' ', (string) $task->status)) }}
                                                </span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4">
                                                <div class="master-list-empty">
                                                    <span class="master-list-empty-icon" aria-hidden="true">✓</span>
                                                    <p class="master-list-empty-title">Nothing assigned</p>
                                                    <p class="master-list-empty-text">
                                                        Work assigned to this person in the Tasks module shows up here.
                                                    </p>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
    @if ($employee)
        <script src="{{ $assetVer('assets/js/employees.js') }}"></script>
    @endif
    <script src="{{ $assetVer('assets/js/users.js') }}"></script>
@endpush
