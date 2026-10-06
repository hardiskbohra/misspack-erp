<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('office_alerts', function (Blueprint $table) {
            $table->timestamp('acked_at')->nullable()->after('meta');
            $table->foreignId('acked_by')->nullable()->after('acked_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('office_alerts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('acked_by');
            $table->dropColumn('acked_at');
        });
    }
};
