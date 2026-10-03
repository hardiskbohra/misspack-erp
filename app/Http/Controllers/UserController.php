<?php

namespace App\Http\Controllers;

use App\Helpers\DateRanges;
use App\Models\EmployeeDocument;
use App\Models\EmployeePayslip;
use App\Models\User;
use App\Services\DocumentUpload;
use App\Services\EmployeeProfile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The user module — which is also the employee module, because an employee *is*
 * a user of this application with a smaller set of doors.
 *
 * Creating somebody therefore starts with the one question that decides
 * everything after it: are they the office or are they on the payroll? An
 * administrator gets the whole ERP; an employee gets their own record. The
 * fields each one needs are different, the checks are different, and both are
 * decided here rather than being spread across screens.
 *
 * Two rules exist purely to stop an office locking itself out: the last
 * administrator cannot be demoted, and nobody can delete themselves.
 */
class UserController extends Controller
{
    /** The filters this list understands, as they read on screen. */
    private const FILTER_KEYS = ['search', 'role', 'department', 'designation', 'status', 'code', 'joined'];

    /**
     * The record's tabs: the questions an office asks about a person, in the
     * order it asks them. Declared here so the strip, the panel and the
     * `?tab=` guard can never disagree about what exists.
     */
    private const TABS = [
        'overview' => 'Overview',
        'details' => 'Details',
        'salary' => 'Salary',
        'payslips' => 'Payslips',
        'documents' => 'Documents',
        'work' => 'Work',
    ];

    /**
     * The columns the search box reads. The list, the query and the chips all
     * read this one list, so a column can never be searched but not filtered,
     * or filtered but not searched.
     */
    private const SEARCH_COLUMNS = ['name', 'email', 'mobile', 'department', 'designation', 'employee_code'];

    /** The column behind each filter, by the name the query string uses. */
    private const FILTER_COLUMNS = [
        'role' => 'role',
        'department' => 'department',
        'designation' => 'designation',
        'status' => 'employment_status',
        'code' => 'employee_code',
        'joined' => 'date_of_joining',
    ];

    /** @var array<string, bool> */
    private static array $columns = [];

    /** The employee side of the module — one service, one definition of pay. */
    private function profile(): EmployeeProfile
    {
        return new EmployeeProfile();
    }

    /**
     * Does the table have this column *yet*?
     *
     * Half of this module's columns arrive with its own migration, and an
     * application deployed by hand is read on databases that have not caught
     * up. A page that offers fewer filters is a better answer than a 500 in
     * front of somebody's payroll — but the rule that matters more is the
     * second half: a filter is only *offered* when it can actually be applied.
     * A control that silently does nothing is worse than no control.
     */
    private function hasColumn(string $column): bool
    {
        return self::$columns[$column] ??= Schema::hasColumn('users', $column);
    }

    /**
     * Which filters this database can apply right now.
     *
     * @return array<string, bool>
     */
    private function availableFilters(): array
    {
        $available = ['search' => true];

        foreach (self::FILTER_COLUMNS as $key => $column) {
            $available[$key] = $this->hasColumn($column);
        }

        return $available;
    }

    /**
     * The payslip and document counts the list prints — asked for only when
     * their tables exist, so an unmigrated database still lists its people.
     */
    private function counting(Builder $query): Builder
    {
        $relations = [];

        foreach (['payslips' => 'employee_payslips', 'employeeDocuments' => 'employee_documents'] as $relation => $table) {
            if (Schema::hasTable($table)) {
                $relations[] = $relation;
            }
        }

        return $relations === [] ? $query : $query->withCount($relations);
    }

    /** List all users — the same list screen as shipments and the ledger. */
    public function index(Request $request)
    {
        /* Read by presence, never with `??`: an unset filter is empty, and the
           word "all" is a value the office might genuinely type into a box. */
        $filters = [];
        foreach (self::FILTER_KEYS as $key) {
            $value = $request->query($key);
            $filters[$key] = is_string($value) ? trim($value) : null;
        }

        $perPage = (int) $request->get('per_page', 10);
        $available = $this->availableFilters();
        $chips = $this->filterChips($filters, $available);

        $query = $this->counting($this->filtered($filters, $available));

        $users = $query->orderBy('id')->paginate($perPage ?: 10)->withQueryString();

        return view('users.index', array_merge($filters, [
            'users' => $users,
            'filters' => $filters,
            'filtersActive' => $chips !== [],
            'availableFilters' => $available,
            'filterChips' => $chips,
            'perPage' => $perPage,
            'roles' => User::ROLES,
            'roleCounts' => $this->roleCounts(),
            'stats' => $this->stats(),
            'departments' => $this->departments(),
            'employmentTypes' => User::EMPLOYMENT_TYPES,
            'employmentStatuses' => User::EMPLOYMENT_STATUSES,
        ]));
    }

    /**
     * One person's record, in tabs — the same shape as a vendor's page.
     *
     * Tabs, not one long page, because the questions are asked one at a time:
     * who is this, what are they paid, where are their papers. And the same
     * panels serve an administrator's record, which is why `employee` is a
     * boolean the view reads rather than four separate pages.
     */
    public function show(User $user, EmployeeProfile $profile): View
    {
        $year = (int) request()->query('year', date('Y'));
        $tab = (string) request()->query('tab', 'overview');
        $tab = array_key_exists($tab, self::TABS) ? $tab : 'overview';

        $record = $profile->profile($user);
        $checklist = $profile->documentChecklist($user);
        $payslips = $profile->payslips($user);
        $tasks = $profile->tasks($user);

        return view('users.show', [
            'user' => $user,
            'employee' => $user->isEmployee(),
            'tab' => $tab,
            'tabs' => self::TABS,
            'tabCounts' => [
                'salary' => $profile->salaryEntries($user)->count(),
                'payslips' => $payslips->count(),
                'documents' => Schema::hasTable('employee_documents') ? $user->employeeDocuments()->count() : 0,
                'notes' => 0,
            ],
            'record' => $record,
            'checklist' => $checklist,
            'payslips' => $payslips,
            'total' => $profile->yearTotal($user, $year),
            'months' => $profile->monthlyPay($user, $year),
            'years' => $profile->salaryYears($user),
            'salaryEntries' => $profile->salaryEntries($user, $year.'-01-01', $year.'-12-31'),
            'attachments' => $profile->attachments($user),
            'tasks' => $tasks,
            'openTasks' => $tasks->whereNotIn('status', ['completed'])->count(),
            'year' => $year,
            'documentTypes' => EmployeeDocument::documentTypeOptions(),
            'payslipStatuses' => EmployeePayslip::STATUSES,
            'employmentTypes' => User::EMPLOYMENT_TYPES,
            'employmentStatuses' => User::EMPLOYMENT_STATUSES,
            'roles' => User::ROLES,
            'isSelf' => (int) auth()->id() === (int) $user->id,
            'isLastAdmin' => $this->isLastAdmin($user),
        ]);
    }

    /**
     * The list's filter vocabulary, in one place.
     *
     * Written by presence, not with `??`: an unset filter is empty and the word
     * "all" is a value somebody might genuinely type into the search box — the
     * bug the ledger shipped twice, and this list is not going to repeat it.
     */
    private function filtered(array $filters, array $available)
    {
        $query = User::query();
        $search = $filters['search'] ?? null;

        if (filled($search)) {
            $columns = array_values(array_filter(self::SEARCH_COLUMNS, fn (string $column) => $this->hasColumn($column)));

            if ($columns !== []) {
                $query->where(function ($q) use ($columns, $search) {
                    foreach ($columns as $column) {
                        $q->orWhere($column, 'like', "%{$search}%");
                    }
                });
            }
        }

        foreach (['role', 'department', 'designation'] as $column) {
            $value = $filters[$column] ?? null;

            if (($available[$column] ?? false) && filled($value) && $value !== 'all') {
                $query->where($column, $value);
            }
        }

        $code = $filters['code'] ?? null;

        if (($available['code'] ?? false) && filled($code) && $code !== 'all') {
            /* with / without, not free text: an office asking "who has no code
               yet" is asking a complete question and typing nothing to ask it. */
            $code === 'missing'
                ? $query->where(fn ($q) => $q->whereNull('employee_code')->orWhere('employee_code', ''))
                : $query->whereNotNull('employee_code')->where('employee_code', '!=', '');
        }

        $status = $filters['status'] ?? null;

        if (($available['status'] ?? false) && filled($status) && $status !== 'all') {
            $status === 'exited'
                ? $query->where('employment_status', 'exited')
                : $query->where(fn ($q) => $q->where('employment_status', $status)->orWhereNull('employment_status'));
        }

        $joined = $filters['joined'] ?? null;

        if (($available['joined'] ?? false) && filled($joined) && $joined !== 'all') {
            $preset = DateRanges::presets()[$joined] ?? null;

            if ($preset) {
                $query->whereDate('date_of_joining', '>=', $preset['from'])
                      ->whereDate('date_of_joining', '<=', $preset['to']);
            }
        }

        return $query;
    }

    /** Is anything actually filtering? */
    /**
     * One removable chip per active filter — the applied strip every list wears.
     *
     * Built here rather than in the blade so the chip, its label and the value
     * it shows are one definition: a filter that can be applied and not removed
     * is a filter nobody dares use.
     *
     * @return array<int, array{key: string, label: string, value: string}>
     */
    private function filterChips(array $filters, array $available): array
    {
        $labels = [
            'search' => 'Search', 'role' => 'Role', 'department' => 'Department',
            'designation' => 'Designation', 'status' => 'Status', 'code' => 'Employee code',
            'joined' => 'Joined',
        ];
        $chips = [];

        foreach ($labels as $key => $label) {
            $value = $filters[$key] ?? null;

            /* A filter this database cannot apply is not a filter that is on:
               the chip would be a claim the query never made. */
            if (! ($available[$key] ?? false) || ! filled($value) || $value === 'all') {
                continue;
            }

            $shown = match (true) {
                $key === 'role' => User::ROLES[$value] ?? $value,
                $key === 'status' => User::EMPLOYMENT_STATUSES[$value] ?? $value,
                $key === 'code' => $value === 'missing' ? 'Not set yet' : 'On file',
                $key === 'joined' => DateRanges::LABELS[$value] ?? $value,
                default => $value,
            };

            $chips[] = ['key' => $key, 'label' => $label, 'value' => $shown];
        }

        return $chips;
    }

    /**
     * The five figures the list opens with — the same tile strip as shipments
     * and the ledger. They count the whole module, not the current page, and
     * they are the questions an office actually has about its people.
     *
     * @return array<string, int|float>
     */
    private function stats(): array
    {
        $counts = $this->roleCounts();
        $hasRole = $this->hasColumn('role');

        /* What left the company for the team this month, read through the same
           query the person's own record uses — a tile and a page cannot mean two
           different things. */
        $paidToTeam = $this->profile()->paidToTeam(
            now()->startOfMonth()->toDateString(),
            now()->endOfMonth()->toDateString()
        );

        /* Who is on the payroll and has a code to be paid against — the same
           predicate as the `code=present` filter, so the tile and the filter
           can never mean two different things. */
        $withCode = $hasRole && $this->hasColumn('employee_code')
            ? User::query()->where('role', User::ROLE_EMPLOYEE)
                ->whereNotNull('employee_code')->where('employee_code', '!=', '')->count()
            : 0;

        $documents = Schema::hasTable('employee_documents') ? EmployeeDocument::query()->count() : 0;

        $missingFiles = 0;

        if ($hasRole && Schema::hasTable('employee_documents')) {
            /* Who is on the payroll with no identity paper at all — the one gap
               an audit asks about first. */
            $missingFiles = User::query()->where('role', User::ROLE_EMPLOYEE)
                ->whereDoesntHave('employeeDocuments', fn ($q) => $q->where('document_type', EmployeeDocument::TYPE_ID_PROOF))
                ->count();
        }

        return [
            'total' => $counts['all'] ?? 0,
            'employees' => $counts[User::ROLE_EMPLOYEE] ?? 0,
            'admins' => $counts[User::ROLE_ADMIN] ?? 0,
            'with_code' => $withCode,
            'paid_this_month' => $paidToTeam,
            'documents' => $documents,
            'missing_id_proof' => $missingFiles,
            'month_label' => now()->format('M Y'),
        ];
    }

    /** The departments actually in use, for the filter — never a stale list. */
    private function departments(): array
    {
        if (! $this->hasColumn('department')) {
            return [];
        }

        return User::query()
            ->whereNotNull('department')
            ->where('department', '!=', '')
            ->orderBy('department')
            ->distinct()
            ->pluck('department')
            ->all();
    }

    /**
     * The list as a spreadsheet — the same rows, the same filters, one click.
     *
     * CSV rather than a package: it opens in Excel, Google Sheets and Tally's
     * import, which is what an office actually does with a list of its people.
     */
    public function export(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $filters = [];
        foreach (self::FILTER_KEYS as $key) {
            $value = $request->query($key);
            $filters[$key] = is_string($value) ? trim($value) : null;
        }

        $rows = $this->counting($this->filtered($filters, $this->availableFilters()))
            ->orderBy('id')
            ->get();

        $handle = fopen('php://temp', 'r+');

        fputcsv($handle, [
            'Employee code', 'Name', 'Role', 'Email', 'Mobile', 'Department', 'Designation',
            'Employment type', 'Status', 'Date of joining', 'PAN', 'Bank', 'Account number',
            'IFSC', 'Payslips', 'Documents',
        ]);

        foreach ($rows as $person) {
            fputcsv($handle, [
                $person->employee_code,
                $person->name,
                $person->roleLabel(),
                $person->email,
                $person->mobile,
                $person->department,
                $person->designation,
                $person->employmentTypeLabel(),
                $person->employmentStatusLabel(),
                $person->date_of_joining?->format('Y-m-d'),
                /* the masked number, not the real one: an export is a file on
                   somebody's desktop, and a spreadsheet of account numbers is
                   the worst place for them. */
                $person->pan_number,
                $person->bank_name,
                (new EmployeeProfile())->mask($person->bank_account_number),
                $person->bank_ifsc,
                (int) $person->payslips_count,
                (int) $person->employee_documents_count,
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return DocumentUpload::temporaryDownload(
            $csv,
            'employees-'.date('Y-m-d').'.csv'
        );
    }

    /** How many of each role — the counts the role chips carry. */
    private function roleCounts(): array
    {
        $counts = ['all' => User::query()->count()];

        if ($this->hasColumn('role')) {
            foreach (array_keys(User::ROLES) as $role) {
                $counts[$role] = User::query()->where('role', $role)->count();
            }
        }

        return $counts;
    }

    /** Return user data as JSON for the edit modal */
    public function getData(User $user)
    {
        return response()->json([
            'id'          => $user->id,
            'name'        => $user->name,
            'email'       => $user->email,
            'mobile'      => $user->mobile,
            'department'  => $user->department,
            'designation' => $user->designation,
            'avatar'      => $user->avatar ? asset('storage/' . $user->avatar) : null,
            'verified'    => !is_null($user->email_verified_at),
            'role'        => $user->role ?: User::DEFAULT_ROLE,
            'employee_code' => $user->employee_code,
            'date_of_joining' => $user->date_of_joining?->format('Y-m-d'),
            'date_of_birth' => $user->date_of_birth?->format('Y-m-d'),
            'employment_type' => $user->employment_type,
            'employment_status' => $user->employment_status ?: 'active',
            'address' => $user->address,
            'emergency_contact_name' => $user->emergency_contact_name,
            'emergency_contact_mobile' => $user->emergency_contact_mobile,
            'pan_number' => $user->pan_number,
            'bank_name' => $user->bank_name,
            'bank_account_name' => $user->bank_account_name,
            'bank_account_number' => $user->bank_account_number,
            'bank_ifsc' => $user->bank_ifsc,
            'is_self' => (int) auth()->id() === (int) $user->id,
            'is_last_admin' => $this->isLastAdmin($user),
        ]);
    }

    /** Store new user */
    public function store(Request $request)
    {
        $data = $this->validated($request);

        $data['password'] = Hash::make($request->password);

        /* A new employee gets the next code in the series unless the office has
           its own numbering; leaving the field blank is the normal case. */
        if ($data['role'] === User::ROLE_EMPLOYEE && blank($data['employee_code'] ?? null)) {
            $data['employee_code'] = app(EmployeeProfile::class)->nextEmployeeCode();
        }

        if ($request->hasFile('avatar')) {
            $data['avatar'] = $request->file('avatar')->store('avatars', 'public');
        }

        $user = User::create($data);

        return back()->with('success', $data['role'] === User::ROLE_EMPLOYEE
            ? 'Employee added. Their workspace is ready at /my — they log in with this email and password.'
            : 'User added successfully!');
    }

    /** Update existing user */
    public function update(Request $request, User $user)
    {
        $data = $this->validated($request, $user);

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        if ($request->hasFile('avatar')) {
            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
            }
            $data['avatar'] = $request->file('avatar')->store('avatars', 'public');
        }

        if ($request->boolean('remove_avatar') && $user->avatar) {
            Storage::disk('public')->delete($user->avatar);
            $data['avatar'] = null;
        }

        $user->update($data);

        return back()->with('success', 'User updated successfully!');
    }

    /** Delete user */
    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        /* The office must always keep an administrator: deleting the last one
           locks everybody out of the ledger, the clients and this page. */
        if ($this->isLastAdmin($user)) {
            return back()->with('error', 'This is the last administrator. Give somebody else that role first.');
        }

        /* A person's file goes with them: payslips, documents and their uploaded
           papers, because a deleted row that still owns files is a leak. */
        foreach ($user->employeeDocuments as $document) {
            if ($document->file_path) {
                Storage::disk('public')->delete($document->file_path);
            }
        }

        foreach ($user->payslips as $payslip) {
            if ($payslip->file_path) {
                Storage::disk('public')->delete($payslip->file_path);
            }
        }

        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
        }

        $user->delete();

        return back()->with('success', 'User deleted successfully!');
    }

    /* ------------------------------------------------------------------ */

    /**
     * What a user form has to fill in, decided by the role it is creating.
     *
     * An **employee** is a person on a payroll: they need a designation, a
     * joining date and a mobile number, because those are what every later
     * screen assumes exists — the ledger's employee picker, their own profile,
     * the salary page. An **administrator** needs only a name, an email and a
     * password: they are not paid by this module.
     */
    private function validated(Request $request, ?User $user = null): array
    {
        $role = (string) $request->input('role', $user?->role ?: User::DEFAULT_ROLE);

        if (! array_key_exists($role, User::ROLES)) {
            $role = User::DEFAULT_ROLE;
        }

        /* The last administrator keeps the role: demoting them would leave an
           office with nobody who can open this page. */
        if ($user && $role !== User::ROLE_ADMIN && $this->isLastAdmin($user)) {
            throw ValidationException::withMessages([
                'role' => 'This is the last administrator, so the role cannot be changed. Make somebody else an administrator first.',
            ]);
        }

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users')->ignore($user?->id)],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:6', 'confirmed'],
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
            'mobile' => ['nullable', 'string', 'max:20'],
            'department' => ['nullable', 'string', 'max:255'],
            'designation' => ['nullable', 'string', 'max:255'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:2048'],
            'employee_code' => ['nullable', 'string', 'max:40'],
            'date_of_joining' => ['nullable', 'date'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'employment_type' => ['nullable', Rule::in(array_keys(User::EMPLOYMENT_TYPES))],
            'employment_status' => ['nullable', Rule::in(array_keys(User::EMPLOYMENT_STATUSES))],
            'address' => ['nullable', 'string', 'max:1000'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_mobile' => ['nullable', 'string', 'max:20'],
            'pan_number' => ['nullable', 'string', 'max:20'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_account_name' => ['nullable', 'string', 'max:255'],
            'bank_account_number' => ['nullable', 'string', 'max:40'],
            'bank_ifsc' => ['nullable', 'string', 'max:20'],
        ];

        if ($role === User::ROLE_EMPLOYEE) {
            /* The three the rest of the application takes for granted. */
            $rules['designation'] = ['required', 'string', 'max:255'];
            $rules['date_of_joining'] = ['required', 'date'];
            $rules['mobile'] = ['required', 'string', 'max:20'];
        }

        $data = $request->validate($rules);

        unset($data['password'], $data['avatar']);

        /* An employee code, when given, is theirs alone. */
        if (filled($data['employee_code'] ?? null)) {
            $clash = User::query()
                ->where('employee_code', $data['employee_code'])
                ->when($user, fn ($q) => $q->whereKeyNot($user->id))
                ->exists();

            if ($clash) {
                throw ValidationException::withMessages([
                    'employee_code' => 'That employee code belongs to somebody else.',
                ]);
            }
        }

        $data['role'] = $role;
        $data['employment_status'] = $data['employment_status'] ?? 'active';

        return $data;
    }

    /** The only administrator left — the one account that must keep its power. */
    private function isLastAdmin(User $user): bool
    {
        if (! $user->isAdmin()) {
            return false;
        }

        return User::query()->where('role', '!=', User::ROLE_EMPLOYEE)->count() <= 1;
    }
}
