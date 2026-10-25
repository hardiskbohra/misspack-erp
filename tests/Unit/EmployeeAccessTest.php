<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\EmployeeAccess;
use PHPUnit\Framework\TestCase;

class EmployeeAccessTest extends TestCase
{
    private function person(int $id, string $role): User
    {
        $user = new User(['role' => $role]);
        $user->id = $id;
        return $user;
    }

    public function test_employee_can_read_self_but_not_another_employee_or_office_record(): void
    {
        $access = new EmployeeAccess();
        $actor = $this->person(1, 'employee');
        $this->assertTrue($access->canView($actor, $actor));
        $this->assertFalse($access->canView($actor, $this->person(2, 'employee')));
        $this->assertFalse($access->canView($actor, $this->person(3, 'admin')));
        $this->assertFalse($access->canManage($actor, $actor));
    }

    public function test_missing_actor_or_owner_cannot_read_a_record(): void
    {
        $access = new EmployeeAccess();
        $owner = $this->person(1, 'employee');
        $this->assertFalse($access->canView(null, $owner));
        $this->assertFalse($access->canView($owner, null));
        $this->assertFalse($access->canManage(null, $owner));
    }

    public function test_office_can_view_and_manage_employee_records(): void
    {
        $access = new EmployeeAccess();
        $admin = $this->person(1, 'admin');
        $owner = $this->person(2, 'employee');
        $this->assertTrue($access->canView($admin, $owner));
        $this->assertTrue($access->canManage($admin, $owner));
    }

    public function test_self_service_allowlist_never_contains_salary_bank_or_role_fields(): void
    {
        $access = new EmployeeAccess();
        $this->assertSame([], array_values(array_intersect($access->ownEditableFields(), $access->officeOnlyFields())));
        foreach (['role', 'access_profile', 'permissions', 'bank_account_number', 'designation', 'salary', 'employment_status'] as $field) {
            $this->assertNotContains($field, $access->ownEditableFields());
        }
        $this->assertEqualsCanonicalizing($access->ownEditableFields(), array_keys($access->ownFieldRules()));
    }
}
