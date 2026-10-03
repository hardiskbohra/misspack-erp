<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A payslip is read line by line — that is what a payslip *is*.
 *
 * The first version of this table stored three totals: gross, deductions, net.
 * The PDF that comes out of three totals is a receipt, not a payslip: the
 * employee cannot see what the gross was made of, and the office cannot explain
 * a month without opening its own spreadsheet.
 *
 * So the breakdown is stored: an earnings list and a deductions list of
 * `{label, amount}` pairs, plus the two day counts a payslip normally carries
 * (working days and paid days, whose difference is the loss of pay). The totals
 * stay — they are what the list sorts and totals by — and when the lines are
 * present the totals are *derived from them*, so a slip can never show a gross
 * its own lines do not add up to.
 *
 * Nothing is dropped and both new shapes are nullable: a slip recorded from
 * three totals alone still reads exactly as it did.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('employee_payslips')) {
            return;
        }

        Schema::table('employee_payslips', function (Blueprint $table) {
            if (! Schema::hasColumn('employee_payslips', 'components')) {
                /* { "earnings": [{label, amount}], "deductions": [{label, amount}] } */
                $table->json('components')->nullable()->after('net_amount');
            }

            if (! Schema::hasColumn('employee_payslips', 'working_days')) {
                $table->unsignedSmallInteger('working_days')->nullable()->after('currency');
            }

            if (! Schema::hasColumn('employee_payslips', 'paid_days')) {
                $table->unsignedSmallInteger('paid_days')->nullable()->after('working_days');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('employee_payslips')) {
            return;
        }

        Schema::table('employee_payslips', function (Blueprint $table) {
            foreach (['working_days', 'paid_days', 'components'] as $column) {
                if (Schema::hasColumn('employee_payslips', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
