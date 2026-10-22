<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_approval_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_workflow_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_product_id')->constrained()->cascadeOnDelete();
            $table->string('approval_type', 30);
            $table->string('title');
            $table->text('instructions')->nullable();
            $table->string('status', 30)->default('open');
            $table->unsignedSmallInteger('current_version_number')->default(1);
            $table->timestamp('response_due_at')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('requested_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['project_id', 'status', 'response_due_at'], 'project_approvals_project_status_idx');
            $table->index(['project_product_id', 'approval_type'], 'project_approvals_product_type_idx');
        });

        Schema::create('project_approval_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_approval_request_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('version_number');
            $table->foreignId('project_attachment_id')->constrained()->restrictOnDelete();
            $table->string('content_hash', 64);
            $table->text('submission_notes')->nullable();
            $table->string('status', 30)->default('pending');
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at');
            $table->foreignId('decided_by_portal_user_id')->nullable()->constrained('client_portal_users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('client_comment')->nullable();
            $table->timestamps();
            $table->unique(['project_approval_request_id', 'version_number'], 'project_approval_versions_number_uq');
        });

        Schema::create('project_approval_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_approval_request_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('version_number');
            $table->text('reason');
            $table->foreignId('approved_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at');
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('revocation_reason')->nullable();
            $table->timestamps();
            $table->index(['project_approval_request_id', 'version_number'], 'project_approval_overrides_request_version_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_approval_overrides');
        Schema::dropIfExists('project_approval_versions');
        Schema::dropIfExists('project_approval_requests');
    }
};
