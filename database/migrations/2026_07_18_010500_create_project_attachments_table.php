<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('project_product_id')->nullable()->constrained('project_products')->nullOnDelete();
            $table->unsignedBigInteger('project_comment_id')->nullable();
            $table->unsignedBigInteger('project_tracking_update_id')->nullable();
            $table->unsignedBigInteger('project_payment_id')->nullable();
            $table->string('category', 60)->default('other');
            $table->string('title')->nullable();
            $table->string('file_path');
            $table->string('original_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('extension', 20)->nullable();
            $table->boolean('is_photo')->default(false);
            $table->boolean('is_public')->default(false);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('uploaded_by_type', 30)->default('internal');
            $table->string('client_name')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'category']);
            $table->index(['project_id', 'is_public']);
            $table->index(['project_product_id', 'category']);
            $table->index('project_comment_id');
            $table->index('project_tracking_update_id');
            $table->index('project_payment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_attachments');
    }
};
