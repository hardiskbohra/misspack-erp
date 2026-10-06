<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('office_services', function (Blueprint $table) {
            $table->id();
            $table->string('service_number', 32)->unique();
            $table->string('name');
            $table->string('service_class', 40);
            $table->string('contact_name')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('email')->nullable();
            $table->string('status', 20)->default('active');
            $table->decimal('retainer_amount', 12, 2)->nullable();
            $table->string('retainer_cycle', 20)->default('monthly');
            $table->string('currency', 8)->default('INR');
            $table->string('bank_name')->nullable();
            $table->string('bank_account_name')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->string('bank_ifsc', 20)->nullable();
            $table->string('upi_id')->nullable();
            $table->text('address')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['service_class', 'status']);
        });

        if (Schema::hasTable('cashflow_entries') && ! Schema::hasColumn('cashflow_entries', 'office_service_id')) {
            Schema::table('cashflow_entries', function (Blueprint $table) {
                $table->foreignId('office_service_id')->nullable()->after('employee_id')
                    ->constrained('office_services')->nullOnDelete();
            });
        }

        if (Schema::hasTable('cashflow_master_options')) {
            $exists = DB::table('cashflow_master_options')
                ->where('group', 'related_party_type')
                ->where('key', 'office_service')
                ->exists();

            if (! $exists) {
                $max = (int) DB::table('cashflow_master_options')
                    ->where('group', 'related_party_type')
                    ->max('sort_order');

                DB::table('cashflow_master_options')->insert([
                    'group' => 'related_party_type',
                    'key' => 'office_service',
                    'label' => 'Office service',
                    'color' => '#0ea5e9',
                    'sort_order' => $max + 1,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('cashflow_entries') && Schema::hasColumn('cashflow_entries', 'office_service_id')) {
            Schema::table('cashflow_entries', function (Blueprint $table) {
                $table->dropConstrainedForeignId('office_service_id');
            });
        }

        if (Schema::hasTable('cashflow_master_options')) {
            DB::table('cashflow_master_options')
                ->where('group', 'related_party_type')
                ->where('key', 'office_service')
                ->delete();
        }

        Schema::dropIfExists('office_services');
    }
};
