<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('project_number')->unique();
            $table->string('public_token', 80)->unique();
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('customer_quote_id')->nullable();
            $table->string('name');
            $table->string('status', 40)->default('planned');
            $table->string('stage', 60)->default('quote_finalised');
            $table->string('priority', 30)->default('normal');
            $table->string('health', 20)->default('green');
            $table->date('start_date')->nullable();
            $table->date('target_date')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('currency', 10)->default('INR');
            $table->decimal('estimated_value', 16, 2)->default(0);
            $table->decimal('budget_amount', 16, 2)->default(0);
            $table->unsignedTinyInteger('progress_percent')->default(0);
            $table->text('scope_summary')->nullable();
            $table->text('deliverables')->nullable();
            $table->text('client_notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->boolean('show_client_portal')->default(true);
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['client_id', 'status']);
            $table->index(['status', 'stage']);
            $table->index(['priority', 'health']);
            $table->index('customer_quote_id');
            $table->index('target_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
