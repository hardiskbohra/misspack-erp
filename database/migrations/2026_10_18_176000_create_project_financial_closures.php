<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_financial_closures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_workflow_id')->constrained()->restrictOnDelete();
            $table->string('closure_type', 50);
            $table->string('content_hash', 64);
            $table->json('financial_snapshot');
            $table->text('reason')->nullable();
            $table->date('expected_resolution_date')->nullable();
            $table->foreignId('approved_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('closed_at');
            $table->timestamps();
            $table->index(['project_id', 'closed_at'], 'project_financial_closures_project_date_idx');
            $table->index(['project_workflow_id', 'closure_type'], 'project_financial_closures_workflow_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_financial_closures');
    }
};
