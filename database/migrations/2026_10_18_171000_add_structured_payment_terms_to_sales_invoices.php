<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_invoices', function (Blueprint $table) {
            $table->boolean('advance_required')->default(false)->after('payment_terms');
            $table->decimal('advance_percentage', 5, 2)->nullable()->after('advance_required');
            $table->boolean('balance_before_dispatch')->default(false)->after('advance_percentage');
            $table->decimal('payment_tolerance', 12, 2)->default(0)->after('balance_before_dispatch');
        });
    }

    public function down(): void
    {
        Schema::table('sales_invoices', function (Blueprint $table) {
            $table->dropColumn(['advance_required', 'advance_percentage', 'balance_before_dispatch', 'payment_tolerance']);
        });
    }
};
