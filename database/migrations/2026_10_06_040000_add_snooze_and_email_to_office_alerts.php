<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('office_alerts', function (Blueprint $table) {
            $table->timestamp('emailed_at')->nullable()->after('acked_by');
        });

        Schema::table('office_alert_states', function (Blueprint $table) {
            $table->timestamp('snoozed_until')->nullable()->after('acked_at');
            $table->timestamp('popup_at')->nullable()->after('snoozed_until');
        });
    }

    public function down(): void
    {
        Schema::table('office_alerts', function (Blueprint $table) {
            $table->dropColumn('emailed_at');
        });

        Schema::table('office_alert_states', function (Blueprint $table) {
            $table->dropColumn(['snoozed_until', 'popup_at']);
        });
    }
};
