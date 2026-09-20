<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('project_product_id')->nullable()->constrained('project_products')->nullOnDelete();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('author_type', 30)->default('internal'); // internal, client, system
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('client_name')->nullable();
            $table->string('client_email')->nullable();
            $table->text('body');
            $table->boolean('is_public')->default(false);
            $table->boolean('is_pinned')->default(false);
            $table->timestamps();

            $table->index(['project_id', 'is_public']);
            $table->index(['project_product_id', 'is_public']);
            $table->index('parent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_comments');
    }
};
