<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        /* One administrator and a payroll of employees, so a fresh install has
           both sides of the line to look at. Everything except Jonathan is an
           employee account: it can sign in, and it lands on its own workspace
           instead of the office's screens. */
        $users = [
            [
                'name'        => 'Jonathan Admin',
                'email'       => 'admin@misspack.com',
                'password'    => Hash::make('admin123'),
                'mobile'      => '+91 9786838000',
                'department'  => 'Management',
                'designation' => 'Administrator',
                'role'        => User::ROLE_ADMIN,
            ],
            [
                'name'        => 'Mark Zukerburg',
                'email'       => 'mark@misspack.com',
                'role'        => User::ROLE_EMPLOYEE,
                'employee_code' => 'EMP-0001',
                'date_of_joining' => '2023-04-03',
                'employment_type' => 'full_time',
                'employment_status' => 'active',
                'password'    => Hash::make('password'),
                'mobile'      => '+91 8786838000',
                'department'  => 'Technology',
                'designation' => 'Web Developer',
            ],
            [
                'name'        => 'Sam Smith',
                'email'       => 'sam@misspack.com',
                'role'        => User::ROLE_EMPLOYEE,
                'employee_code' => 'EMP-0002',
                'date_of_joining' => '2023-04-03',
                'employment_type' => 'full_time',
                'employment_status' => 'active',
                'password'    => Hash::make('password'),
                'mobile'      => '+91 7788838000',
                'department'  => 'Design',
                'designation' => 'Web Designer',
            ],
            [
                'name'        => 'John Deo',
                'email'       => 'john@misspack.com',
                'role'        => User::ROLE_EMPLOYEE,
                'employee_code' => 'EMP-0003',
                'date_of_joining' => '2023-04-03',
                'employment_type' => 'full_time',
                'employment_status' => 'active',
                'password'    => Hash::make('password'),
                'mobile'      => '+91 8786838001',
                'department'  => 'QA',
                'designation' => 'Tester',
            ],
            [
                'name'        => 'Sarah Connor',
                'email'       => 'sarah@misspack.com',
                'role'        => User::ROLE_EMPLOYEE,
                'employee_code' => 'EMP-0004',
                'date_of_joining' => '2023-04-03',
                'employment_type' => 'full_time',
                'employment_status' => 'active',
                'password'    => Hash::make('password'),
                'mobile'      => '+91 9876543210',
                'department'  => 'Human Resources',
                'designation' => 'HR Manager',
            ],
        ];

        foreach ($users as $data) {
            $user = User::firstOrCreate(['email' => $data['email']], $data);

            /* An account that already existed before this version has no role
               yet: give it the role the seed says it should have, but never
               overwrite an employment record somebody has since edited. */
            if ($user->wasRecentlyCreated === false && blank($user->role)) {
                $user->forceFill([
                    'role' => $data['role'],
                    'employee_code' => $user->employee_code ?: ($data['employee_code'] ?? null),
                    'date_of_joining' => $user->date_of_joining ?: ($data['date_of_joining'] ?? null),
                    'employment_type' => $user->employment_type ?: ($data['employment_type'] ?? null),
                    'employment_status' => $user->employment_status ?: ($data['employment_status'] ?? null),
                ])->save();
            }
        }
    }
}
