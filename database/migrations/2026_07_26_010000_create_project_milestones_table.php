<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('project_product_id')->nullable()->constrained('project_products')->nullOnDelete();
            $table->string('milestone_key', 80)->default('custom');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status', 40)->default('not_started');
            $table->unsignedTinyInteger('progress_percent')->default(0);
            $table->date('planned_start_date')->nullable();
            $table->date('planned_end_date')->nullable();
            $table->date('actual_start_date')->nullable();
            $table->date('actual_end_date')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_public')->default(false);
            $table->boolean('is_required')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->text('client_note')->nullable();
            $table->text('blocked_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['project_id', 'project_product_id']);
            $table->index(['project_id', 'is_public']);
            $table->index(['project_id', 'status']);
            $table->index(['project_product_id', 'milestone_key']);
            $table->index('planned_end_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_milestones');
    }
};
