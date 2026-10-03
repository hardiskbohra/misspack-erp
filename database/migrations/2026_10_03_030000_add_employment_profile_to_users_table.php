<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every user of this ERP is either the office or an employee of it, and the
 * difference is a permission boundary — an employee must not read the ledger,
 * the clients or the payroll of the person next to them.
 *
 * The separation therefore starts here, with one column that says which side of
 * that line a user is on. It is *not* a second table: the employee's tasks, the
 * shipments they created, the cashflow rows filed against them and their own
 * login all hang off this same row already, and a parallel `employees` table
 * would be a second answer to "who is this person".
 *
 * Existing users become administrators. That is the only safe direction for a
 * migration: the accounts that exist today have been using the whole ERP for
 * months, and a default that hid the ledger from them would look like an outage.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        $existing = Schema::getColumnListing('users');
        $has = fn (string $column) => in_array($column, $existing, true);

        Schema::table('users', function (Blueprint $table) use ($has) {
            /* Office or employee. Indexed because every list of people and every
               permission check asks which it is. */
            if (! $has('role')) {
                $table->string('role', 20)->default('admin')->after('email')->index();
            }

            /* The employment record an office keeps about somebody, in the one
               place their name already lives. */
            if (! $has('employee_code')) {
                $table->string('employee_code', 40)->nullable()->after('designation');
            }
            if (! $has('date_of_joining')) {
                $table->date('date_of_joining')->nullable()->after('employee_code');
            }
            if (! $has('date_of_birth')) {
                $table->date('date_of_birth')->nullable()->after('date_of_joining');
            }
            if (! $has('employment_type')) {
                $table->string('employment_type', 20)->nullable()->after('date_of_birth');
            }
            if (! $has('employment_status')) {
                $table->string('employment_status', 20)->default('active')->after('employment_type');
            }
            if (! $has('address')) {
                $table->text('address')->nullable()->after('employment_status');
            }
            if (! $has('emergency_contact_name')) {
                $table->string('emergency_contact_name')->nullable()->after('address');
            }
            if (! $has('emergency_contact_mobile')) {
                $table->string('emergency_contact_mobile', 20)->nullable()->after('emergency_contact_name');
            }
            if (! $has('pan_number')) {
                $table->string('pan_number', 20)->nullable()->after('emergency_contact_mobile');
            }
            if (! $has('bank_name')) {
                $table->string('bank_name')->nullable()->after('pan_number');
            }
            if (! $has('bank_account_name')) {
                $table->string('bank_account_name')->nullable()->after('bank_name');
            }
            if (! $has('bank_account_number')) {
                $table->string('bank_account_number', 40)->nullable()->after('bank_account_name');
            }
            if (! $has('bank_ifsc')) {
                $table->string('bank_ifsc', 20)->nullable()->after('bank_account_number');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        $existing = Schema::getColumnListing('users');

        Schema::table('users', function (Blueprint $table) use ($existing) {
            foreach ([
                'role', 'employee_code', 'date_of_joining', 'date_of_birth', 'employment_type',
                'employment_status', 'address', 'emergency_contact_name', 'emergency_contact_mobile',
                'pan_number', 'bank_name', 'bank_account_name', 'bank_account_number', 'bank_ifsc',
            ] as $column) {
                if (in_array($column, $existing, true)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
