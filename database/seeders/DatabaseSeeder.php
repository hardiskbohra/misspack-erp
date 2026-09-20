<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'name'        => 'Jonathan Admin',
                'email'       => 'admin@misspack.com',
                'password'    => Hash::make('admin123'),
                'mobile'      => '+91 9786838000',
                'department'  => 'Management',
                'designation' => 'Administrator',
            ],
            [
                'name'        => 'Mark Zukerburg',
                'email'       => 'mark@misspack.com',
                'password'    => Hash::make('password'),
                'mobile'      => '+91 8786838000',
                'department'  => 'Technology',
                'designation' => 'Web Developer',
            ],
            [
                'name'        => 'Sam Smith',
                'email'       => 'sam@misspack.com',
                'password'    => Hash::make('password'),
                'mobile'      => '+91 7788838000',
                'department'  => 'Design',
                'designation' => 'Web Designer',
            ],
            [
                'name'        => 'John Deo',
                'email'       => 'john@misspack.com',
                'password'    => Hash::make('password'),
                'mobile'      => '+91 8786838001',
                'department'  => 'QA',
                'designation' => 'Tester',
            ],
            [
                'name'        => 'Sarah Connor',
                'email'       => 'sarah@misspack.com',
                'password'    => Hash::make('password'),
                'mobile'      => '+91 9876543210',
                'department'  => 'Human Resources',
                'designation' => 'HR Manager',
            ],
        ];

        foreach ($users as $data) {
            User::firstOrCreate(['email' => $data['email']], $data);
        }
    }
}
