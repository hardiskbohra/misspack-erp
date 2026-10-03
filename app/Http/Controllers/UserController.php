<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\EmployeeProfile;
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
    /** List all users with optional search */
    public function index(Request $request)
    {
        $search  = $request->get('search', '');
        $perPage = $request->get('per_page', 10);
        $role    = $request->get('role', 'all');

        $query = User::query()->withCount(['payslips', 'employeeDocuments']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name',        'like', "%{$search}%")
                  ->orWhere('email',       'like', "%{$search}%")
                  ->orWhere('mobile',      'like', "%{$search}%")
                  ->orWhere('department',  'like', "%{$search}%")
                  ->orWhere('designation', 'like', "%{$search}%")
                  ->orWhere('employee_code', 'like', "%{$search}%");
            });
        }

        if ($role !== 'all' && array_key_exists($role, User::ROLES)) {
            $query->where('role', $role);
        }

        $users = $query->orderBy('id')->paginate($perPage)->withQueryString();

        return view('users.index', [
            'users' => $users,
            'search' => $search,
            'perPage' => $perPage,
            'role' => $role,
            'roles' => User::ROLES,
            'roleCounts' => $this->roleCounts(),
            'employmentTypes' => User::EMPLOYMENT_TYPES,
            'employmentStatuses' => User::EMPLOYMENT_STATUSES,
        ]);
    }

    /**
     * One person's record: who they are, what they were paid, their payslips and
     * their papers — everything the office holds about them on one page, which
     * is what "manage the profile" means in practice.
     */
    public function show(User $user, EmployeeProfile $profile): View
    {
        $record = $profile->profile($user);
        $year = (int) request()->query('year', date('Y'));

        return view('users.show', [
            'user' => $user,
            'record' => $record,
            'checklist' => $profile->documentChecklist($user),
            'payslips' => $profile->payslips($user),
            'total' => $profile->yearTotal($user, $year),
            'months' => $profile->monthlyPay($user, $year),
            'salaryEntries' => $profile->salaryEntries($user, $year.'-01-01', $year.'-12-31'),
            'year' => $year,
            'documentTypes' => \App\Models\EmployeeDocument::documentTypeOptions(),
            'payslipStatuses' => \App\Models\EmployeePayslip::STATUSES,
            'employmentTypes' => User::EMPLOYMENT_TYPES,
            'employmentStatuses' => User::EMPLOYMENT_STATUSES,
            'roles' => User::ROLES,
            'isSelf' => (int) auth()->id() === (int) $user->id,
        ]);
    }

    /** How many of each role — the counts the role chips carry. */
    private function roleCounts(): array
    {
        $counts = ['all' => User::query()->count()];

        if (Schema::hasColumn('users', 'role')) {
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
