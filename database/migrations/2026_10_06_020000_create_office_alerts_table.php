<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('office_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('event_key', 80);
            $table->string('fingerprint', 120)->unique();
            $table->string('title');
            $table->text('body');
            $table->string('severity', 20); // info | attention | critical
            $table->boolean('requires_ack')->default(false);
            $table->string('team', 40)->nullable();
            $table->string('action_url')->nullable();
            $table->string('action_label', 80)->nullable();
            $table->string('subject_type', 80)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['severity', 'created_at']);
            $table->index(['event_key', 'created_at']);
        });

        Schema::create('office_alert_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('office_alert_id')->constrained('office_alerts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('seen_at')->nullable();
            $table->timestamp('acked_at')->nullable();
            $table->timestamps();

            $table->unique(['office_alert_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('office_alert_states');
        Schema::dropIfExists('office_alerts');
    }
};
