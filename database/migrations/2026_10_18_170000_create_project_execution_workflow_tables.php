<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_workflows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('template_key', 80)->default('standard_execution');
            $table->unsignedSmallInteger('template_version')->default(1);
            $table->string('status', 30)->default('active');
            $table->string('calculated_stage', 60)->default('setup');
            $table->unsignedTinyInteger('calculated_progress')->default(0);
            $table->string('reported_stage', 60)->nullable();
            $table->text('reported_reason')->nullable();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reported_at')->nullable();
            $table->foreignId('commercial_sales_invoice_id')->nullable()->constrained('sales_invoices')->nullOnDelete();
            $table->decimal('advance_percentage_override', 5, 2)->nullable();
            $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'calculated_stage'], 'project_workflows_status_stage_idx');
        });

        Schema::create('project_workflow_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_workflow_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_product_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('step_key', 80);
            $table->string('stage_key', 60);
            $table->string('title');
            $table->string('scope', 20)->default('project');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->decimal('weight', 7, 3)->default(1);
            $table->string('status', 30)->default('not_started');
            $table->boolean('is_required')->default(true);
            $table->boolean('is_client_visible')->default(false);
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('planned_start_date')->nullable();
            $table->date('planned_end_date')->nullable();
            $table->timestamp('actual_started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('blocked_reason')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['project_workflow_id', 'stage_key', 'sort_order'], 'project_steps_workflow_stage_idx');
            $table->index(['project_product_id', 'step_key'], 'project_steps_product_key_idx');
            $table->index(['owner_id', 'status', 'planned_end_date'], 'project_steps_owner_status_idx');
        });

        Schema::create('project_gate_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_workflow_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_product_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('gate_key', 80);
            $table->string('result', 30);
            $table->string('content_hash', 64);
            $table->string('summary');
            $table->json('details')->nullable();
            $table->foreignId('evaluated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('evaluated_at');
            $table->timestamps();
            $table->index(['project_workflow_id', 'gate_key', 'evaluated_at'], 'project_gates_workflow_key_idx');
        });

        Schema::create('project_gate_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_gate_evaluation_id')->constrained()->cascadeOnDelete();
            $table->text('reason');
            $table->date('expected_resolution_date')->nullable();
            $table->json('risk_snapshot')->nullable();
            $table->foreignId('approved_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at');
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('revocation_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_gate_overrides');
        Schema::dropIfExists('project_gate_evaluations');
        Schema::dropIfExists('project_workflow_steps');
        Schema::dropIfExists('project_workflows');
    }
};
