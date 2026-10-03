@extends('layouts.app')

@section('title', $user->name)
@section('page-title', $user->name)

@section('page-actions')
    <a class="master-btn master-btn-ghost" href="{{ route('users.index') }}">All users</a>
    {{-- No link into the workspace: `/my` is *the signed-in person's* own
         record, so the office following that link would see the office. The
         record below is the same data, in the office's own words. --}}
    @if ($user->isEmployee())
        <span class="emp-pill is-off">Signs in as an employee — sees only their own record</span>
    @else
        <span class="emp-pill is-off">Office account — full access</span>
    @endif
@endsection

@section('content')
@push('styles')
    <link rel="stylesheet" href="{{ $assetVer('assets/css/employees.css') }}">
    <link rel="stylesheet" href="{{ $assetVer('assets/css/master-list.css') }}">
@endpush

<div class="emp employee-record master-list">
    <div class="master-card master-header emp-record-head">
        <div class="emp-record-who">
            <span class="emp-avatar" aria-hidden="true">{{ $user->initials ?: strtoupper(substr($user->name, 0, 2)) }}</span>
            <div>
                <h1>{{ $user->name }}</h1>
                <p class="master-sub" style="margin:2px 0 0;">
                    {{ $user->designation ?: 'No designation' }}
                    @if ($user->department) · {{ $user->department }}@endif
                    @if ($user->employee_code) · {{ $user->employee_code }}@endif
                </p>
                <p class="emp-record-tags">
                    <span class="emp-pill {{ $user->isAdmin() ? 'is-info' : 'is-ok' }}">{{ $user->roleLabel() }}</span>
                    <span class="emp-pill is-{{ $user->employmentStatusTone() === 'ok' ? 'ok' : ($user->employmentStatusTone() === 'warn' ? 'warn' : 'off') }}">
                        {{ $user->employmentStatusLabel() }}
                    </span>
                    <span class="emp-pill is-off">{{ $user->email }}</span>
                    @if ($user->mobile)<span class="emp-pill is-off">{{ $user->mobile }}</span>@endif
                </p>
            </div>
        </div>
    </div>

    <div class="master-stats desktop-only">
        <div class="master-stat master-stat--flat green">
            <span class="icon">₹</span>
            <div>
                <p class="master-stat-title">Paid in {{ $year }}</p>
                <p class="master-stat-value">{{ \App\Helpers\CommonHelper::indianCurrency($total['total']) }}</p>
                <p class="master-sub">{{ $total['entries'] }} entries · {{ $total['months_paid'] }} months</p>
            </div>
        </div>
        <div class="master-stat master-stat--flat blue">
            <span class="icon">🧾</span>
            <div>
                <p class="master-stat-title">Payslips</p>
                <p class="master-stat-value">{{ $payslips->count() }}</p>
                <p class="master-sub">{{ $payslips->where('status', 'draft')->count() }} still draft</p>
            </div>
        </div>
        <div class="master-stat master-stat--flat {{ $checklist['complete'] ? 'teal' : 'orange' }}">
            <span class="icon">📄</span>
            <div>
                <p class="master-stat-title">Documents</p>
                <p class="master-stat-value">{{ $checklist['present'] }}/{{ $checklist['required'] }}</p>
                <p class="master-sub">{{ $checklist['verified'] }} checked by the office</p>
            </div>
        </div>
        <div class="master-stat master-stat--flat {{ $record['complete'] ? 'purple' : 'red' }}">
            <span class="icon">✓</span>
            <div>
                <p class="master-stat-title">Profile</p>
                <p class="master-stat-value">{{ $record['complete'] ? 'Complete' : count($record['missing']).' to add' }}</p>
                <p class="master-sub">{{ $record['complete'] ? 'nothing outstanding' : implode(', ', array_slice($record['missing'], 0, 2)) }}</p>
            </div>
        </div>
    </div>

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
                    <select class="master-select" id="recordRole" name="role" required @disabled($isSelf)>
                        @foreach ($roles as $key => $label)
                            <option value="{{ $key }}" @selected(old('role', $user->role ?: 'admin') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @if ($isSelf)
                        <input type="hidden" name="role" value="{{ $user->role ?: 'admin' }}">
                        <p class="master-sub" style="margin:6px 0 0;">You cannot change your own role.</p>
                    @endif
                    @error('role')<p class="master-error">{{ $message }}</p>@enderror
                </div>
                <div class="master-field">
                    <label class="master-label" for="recordCode">Employee code</label>
                    <input class="master-input" id="recordCode" name="employee_code" value="{{ old('employee_code', $user->employee_code) }}">
                    @error('employee_code')<p class="master-error">{{ $message }}</p>@enderror
                </div>
                <div class="master-field">
                    <label class="master-label" for="recordDesignation">Designation</label>
                    <input class="master-input" id="recordDesignation" name="designation" value="{{ old('designation', $user->designation) }}">
                </div>
                <div class="master-field">
                    <label class="master-label" for="recordDepartment">Department</label>
                    <input class="master-input" id="recordDepartment" name="department" value="{{ old('department', $user->department) }}">
                </div>
                <div class="master-field">
                    <label class="master-label" for="recordJoining">Date of joining</label>
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
                    <label class="master-label" for="recordMobile">Mobile</label>
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

    @if ($user->isEmployee())
        <div class="master-grid">
            <div class="master-card master-card--flat master-section">
                <h3 class="master-section-title">Payslips</h3>
                <p class="master-sub" style="margin:0 0 14px;">
                    One slip per month. A draft is the office's working copy — the employee cannot see it until it is issued.
                </p>

                <form method="POST" action="{{ route('users.payslips.store', $user) }}" enctype="multipart/form-data">
                    @csrf
                    <div class="master-form-grid">
                        <div class="master-field">
                            <label class="master-label" for="payslipPeriod">Month</label>
                            <input class="master-input" id="payslipPeriod" type="month" name="period"
                                value="{{ old('period', date('Y-m')) }}" required>
                            @error('period')<p class="master-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="payslipGross">Gross</label>
                            <input class="master-input" id="payslipGross" name="gross_amount" value="{{ old('gross_amount') }}" placeholder="0">
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="payslipDeductions">Deductions</label>
                            <input class="master-input" id="payslipDeductions" name="deductions" value="{{ old('deductions') }}" placeholder="0">
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="payslipNet">Net paid</label>
                            <input class="master-input" id="payslipNet" name="net_amount" value="{{ old('net_amount') }}"
                                placeholder="Left blank: gross − deductions">
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

                @if ($payslips->isNotEmpty())
                    <div class="master-table-wrap" style="margin-top:18px;">
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
                                @foreach ($payslips as $payslip)
                                    <tr>
                                        <td data-label="Month"><strong>{{ $payslip->periodLabel() }}</strong>
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
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <div class="master-card master-card--flat master-section">
                <h3 class="master-section-title">Add a document for them</h3>
                <p class="master-sub" style="margin:0 0 14px;">
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
                        </div>
                    </div>
                    <div class="master-actions">
                        <button class="master-btn master-btn-primary" type="submit">File it</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="master-card master-card--flat master-section">
            <h3 class="master-section-title">Pay, this year</h3>
            @include('employees.partials.pay-months', ['months' => $months, 'currency' => 'INR'])
            <p class="master-sub" style="margin:12px 0 0;">
                {{ $salaryEntries->count() }} salary {{ \Illuminate\Support\Str::plural('credit', $salaryEntries->count()) }}
                in {{ $year }}, filed against this person in the cashflow ledger.
            </p>
        </div>
    @endif

    <div class="master-card master-card--flat master-section">
        @include('employees.partials.document-checklist', ['checklist' => $checklist, 'mode' => 'office', 'user' => $user])
    </div>
</div>
@endsection
