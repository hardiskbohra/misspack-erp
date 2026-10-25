<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->text('bank_address')->nullable();
            $table->text('beneficiary_address')->nullable();
        });
        Schema::table('vendor_kyc_documents', function (Blueprint $table) {
            $table->string('category', 40)->default('supporting');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_kyc_documents', function (Blueprint $table) {
            $table->dropColumn('category');
        });
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn(['bank_address', 'beneficiary_address']);
        });
    }
};
