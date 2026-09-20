<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            if (! Schema::hasColumn('leads', 'public_token')) {
                $table->string('public_token', 80)->nullable()->unique()->after('lead_number');
            }
        });

        if (Schema::hasColumn('leads', 'public_token')) {
            DB::table('leads')
                ->whereNull('public_token')
                ->orderBy('id')
                ->get(['id'])
                ->each(function ($lead) {
                    do {
                        $token = Str::random(48);
                    } while (DB::table('leads')->where('public_token', $token)->exists());

                    DB::table('leads')
                        ->where('id', $lead->id)
                        ->update([
                            'public_token' => $token,
                            'updated_at' => now(),
                        ]);
                });
        }
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            if (Schema::hasColumn('leads', 'public_token')) {
                $table->dropUnique(['public_token']);
                $table->dropColumn('public_token');
            }
        });
    }
};
