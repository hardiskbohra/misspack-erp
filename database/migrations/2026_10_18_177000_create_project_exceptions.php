<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_workflow_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('project_workflow_step_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source_type', 50);
            $table->string('source_key', 120)->unique();
            $table->string('source_fingerprint', 64);
            $table->string('category', 50);
            $table->string('severity', 20)->default('warning');
            $table->string('status', 30)->default('open');
            $table->string('title');
            $table->text('summary');
            $table->json('source_snapshot');
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('next_review_at')->nullable();
            $table->unsignedTinyInteger('escalation_level')->default(0);
            $table->timestamp('detected_at');
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('response_plan')->nullable();
            $table->timestamp('escalated_at')->nullable();
            $table->foreignId('escalated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution_note')->nullable();
            $table->timestamps();
            $table->index(['status', 'severity', 'due_at'], 'project_exceptions_status_severity_due_idx');
            $table->index(['project_id', 'status'], 'project_exceptions_project_status_idx');
            $table->index(['owner_id', 'status', 'next_review_at'], 'project_exceptions_owner_status_review_idx');
        });

        Schema::create('project_exception_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_exception_id')->constrained()->restrictOnDelete();
            $table->string('event_type', 40);
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30)->nullable();
            $table->string('severity', 20);
            $table->unsignedTinyInteger('escalation_level')->default(0);
            $table->text('note')->nullable();
            $table->json('snapshot')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->index(['project_exception_id', 'occurred_at'], 'project_exception_events_exception_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_exception_events');
        Schema::dropIfExists('project_exceptions');
    }
};
