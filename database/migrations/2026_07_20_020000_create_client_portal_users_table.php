<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_portal_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->string('username')->unique();
            $table->string('email')->nullable()->index();
            $table->string('mobile', 40)->nullable();
            $table->string('password');
            $table->boolean('portal_enabled')->default(true);
            $table->boolean('is_active')->default(true);
            $table->boolean('must_change_password')->default(true);
            $table->timestamp('password_changed_at')->nullable();
            $table->timestamp('invitation_sent_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->rememberToken();
            $table->timestamps();

            $table->index(['client_id', 'is_active']);
            $table->index(['portal_enabled', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_portal_users');
    }
};
