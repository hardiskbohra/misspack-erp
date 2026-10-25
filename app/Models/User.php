<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * Who a user is, in this ERP's own terms.
     *
     * An **administrator** is the office: everything, including everybody's
     * payroll. An **employee** is a person on the payroll: their own profile,
     * their own salary rows, their own payslips and their own papers — and
     * nothing else in the application.
     *
     * Two roles, not five, because those are the two the business has and every
     * extra role would need every screen to be able to say what it means. The
     * map is the extension point: a third role is one line here plus its rule in
     * `App\Services\EmployeeAccess`.
     */
    public const ROLE_ADMIN = 'admin';
    public const ROLE_EMPLOYEE = 'employee';

    public const ROLES = [
        self::ROLE_ADMIN => 'Administrator (office)',
        self::ROLE_EMPLOYEE => 'Employee',
    ];

    /**
     * Existing users are administrators: see the migration that added the
     * column. A row saved before roles existed must keep working, so the
     * fallback here is the office as well.
     */
    public const DEFAULT_ROLE = self::ROLE_ADMIN;

    public const EMPLOYMENT_TYPES = [
        'full_time' => 'Full time',
        'part_time' => 'Part time',
        'contract' => 'Contract',
        'intern' => 'Intern',
        'consultant' => 'Consultant',
    ];

    public const EMPLOYMENT_STATUSES = [
        'active' => 'Active',
        'probation' => 'On probation',
        'notice' => 'Notice period',
        'on_leave' => 'On leave',
        'exited' => 'Exited',
    ];

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'access_profile',
        'permissions',
        'permissions_updated_at',
        'permissions_updated_by',
        'mobile',
        'department',
        'designation',
        'avatar',
        'employee_code',
        'date_of_joining',
        'date_of_birth',
        'employment_type',
        'employment_status',
        'address',
        'emergency_contact_name',
        'emergency_contact_mobile',
        'pan_number',
        'bank_name',
        'bank_account_name',
        'bank_account_number',
        'bank_ifsc',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'date_of_joining'   => 'date',
            'date_of_birth'     => 'date',
            'password'          => 'hashed',
            'permissions' => 'array',
            'permissions_updated_at' => 'datetime',
            /* The second factor is a secret even from a database dump: the key
               and the recovery-code hashes are encrypted on the way in and
               decrypted only when the challenge needs them. */
            'two_factor_secret' => 'encrypted',
            'two_factor_confirmed_at' => 'datetime',
            'two_factor_recovery_codes' => 'encrypted:array',
        ];
    }

    /* ---------------------------------------------------------------- roles */

    /** The office: every screen, and everybody's record. */
    public function isAdmin(): bool
    {
        return $this->role !== self::ROLE_EMPLOYEE;
    }

    /**
     * Whether the password must be followed by a code at the door.
     *
     * True only once enrolment is *confirmed* — a pending setup key never
     * locks anybody out. The fuller vocabulary (pending, verify, recovery
     * codes) lives in `App\Services\TwoFactorAuthentication`.
     */
    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_confirmed_at !== null
            && ! empty($this->two_factor_secret);
    }

    /**
     * Whether this office account sits on a briefing desk.
     *
     * An empty department is a director: they see every desk. A named
     * department only sees that desk plus unscoped office briefings.
     */
    public function watchesTeam(?string $team): bool
    {
        if (! OfficeSetting::isWatcher($this)) {
            return false;
        }

        $team = strtolower(trim((string) $team));
        if ($team === '' || $team === 'office') {
            return true;
        }

        $desk = OfficeSetting::deskFor($this);
        if ($desk === 'office') {
            return true;
        }

        return $desk === $team;
    }

    /** On the payroll: their own workspace, and nothing else. */
    public function isEmployee(): bool
    {
        return $this->role === self::ROLE_EMPLOYEE;
    }

    public function roleLabel(): string
    {
        return self::ROLES[$this->role] ?? self::ROLES[self::DEFAULT_ROLE];
    }

    public function hasPermission(string $permission): bool
    {
        return app(\App\Services\PermissionMatrix::class)->allows($this, $permission);
    }

    public function accessProfileLabel(): string
    {
        if ($this->isEmployee()) return 'Employee self-service';
        return ($this->access_profile ?: 'full') === 'full' ? 'Full office access' : 'Custom office access';
    }

    public function permissionsUpdater()
    {
        return $this->belongsTo(self::class, 'permissions_updated_by');
    }

    public function employmentTypeLabel(): ?string
    {
        return $this->employment_type ? (self::EMPLOYMENT_TYPES[$this->employment_type] ?? null) : null;
    }

    public function employmentStatusLabel(): string
    {
        return self::EMPLOYMENT_STATUSES[$this->employment_status] ?? 'Active';
    }

    /** A green/amber/red tone for the status pill the list and the profile wear. */
    public function employmentStatusTone(): string
    {
        return match ($this->employment_status) {
            'active' => 'ok',
            'probation', 'notice', 'on_leave' => 'warn',
            'exited' => 'off',
            default => 'ok',
        };
    }

    /**
     * The people on the payroll, in name order: an employee's own kind.
     */
    public static function payableEmployees()
    {
        return static::query()
            ->when(Schema::hasColumn('users', 'role'), fn ($query) => $query->where('role', self::ROLE_EMPLOYEE))
            ->orderBy('name');
    }

    /**
     * The list an Employee field offers — the ledger's, for instance.
     *
     * Employees come first, because that field is about somebody being paid, and
     * the office account is very rarely the answer. It stays in the list
     * underneath, and deliberately: entries filed before roles existed point at
     * those accounts, and a picker that no longer contains the person a row
     * refers to turns an old row into a mystery.
     *
     * One definition, on the model, so every payroll picker agrees.
     */
    public static function employeePicker()
    {
        return static::query()
            ->orderBy('name')
            ->get()
            ->when(Schema::hasColumn('users', 'role'),
                fn ($users) => $users->sortBy(fn (self $user) => $user->isEmployee() ? 0 : 1)->values());
    }

    /* --------------------------------------------------------- the record */

    public function payslips()
    {
        return $this->hasMany(\App\Models\EmployeePayslip::class, 'user_id')->orderByDesc('period');
    }

    public function employeeDocuments()
    {
        return $this->hasMany(\App\Models\EmployeeDocument::class, 'user_id');
    }

    /** The cashflow rows that paid this person. */
    public function salaryEntries()
    {
        return $this->hasMany(\App\Models\CashflowEntry::class, 'employee_id');
    }

    /** First two initials from name */
    public function getInitialsAttribute(): string
    {
        $words = preg_split('/\s+/u', trim((string) $this->name), -1, PREG_SPLIT_NO_EMPTY);

        return mb_strtoupper(implode('', array_map(
            fn ($word) => mb_substr($word, 0, 1), array_slice($words ?: ['User'], 0, 2)
        )));
    }

    /** Public-disk URL to avatar or null; no dependency on the configured app host. */
    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatar ? Storage::disk('public')->url($this->avatar) : null;
    }

    public function assignedTasks()
    {
        return $this->hasMany(\App\Models\Task::class, 'assignee_id');
    }

    public function createdTasks()
    {
        return $this->hasMany(\App\Models\Task::class, 'created_by');
    }

    public function createdShipments()
    {
        return $this->hasMany(\App\Models\Shipment::class, 'created_by');
    }

    public function shipmentTrackingUpdates()
    {
        return $this->hasMany(\App\Models\ShipmentTrackingHistory::class, 'created_by');
    }

    public function createdClients()
    {
        return $this->hasMany(\App\Models\Client::class, 'created_by');
    }

    public function reviewedClientKycs()
    {
        return $this->hasMany(\App\Models\Client::class, 'kyc_reviewed_by');
    }

    public function createdVendors()
    {
        return $this->hasMany(\App\Models\Vendor::class, 'created_by');
    }

    public function cashflowEntries()
    {
        return $this->hasMany(\App\Models\CashflowEntry::class, 'created_by');
    }

    public function assignedLeads()
    {
        return $this->hasMany(\App\Models\Lead::class, 'assigned_to');
    }

    public function createdLeads()
    {
        return $this->hasMany(\App\Models\Lead::class, 'created_by');
    }

}
