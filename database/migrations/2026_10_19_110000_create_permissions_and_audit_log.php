<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('access_profile', 30)->default('full')->after('role');
            $table->json('permissions')->nullable()->after('access_profile');
            $table->timestamp('permissions_updated_at')->nullable()->after('permissions');
            $table->foreignId('permissions_updated_by')->nullable()->after('permissions_updated_at')->constrained('users')->nullOnDelete();
            $table->index(['role', 'access_profile'], 'users_role_access_profile_idx');
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('event', 80);
            $table->string('outcome', 20)->default('success');
            $table->string('actor_type', 40)->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_name')->nullable();
            $table->string('actor_identifier')->nullable();
            $table->string('auditable_type', 120)->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->string('route_name')->nullable();
            $table->string('method', 10);
            $table->text('url')->nullable();
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('changed_fields')->nullable();
            $table->json('context')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->index(['actor_type', 'actor_id', 'occurred_at'], 'audit_actor_occurred_idx');
            $table->index(['auditable_type', 'auditable_id', 'occurred_at'], 'audit_subject_occurred_idx');
            $table->index(['event', 'outcome', 'occurred_at'], 'audit_event_outcome_occurred_idx');
            $table->index('route_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['permissions_updated_by']);
            $table->dropIndex('users_role_access_profile_idx');
            $table->dropColumn(['access_profile', 'permissions', 'permissions_updated_at', 'permissions_updated_by']);
        });
    }
};
