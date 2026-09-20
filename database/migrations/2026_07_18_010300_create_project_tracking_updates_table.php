<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_tracking_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('project_product_id')->nullable()->constrained('project_products')->nullOnDelete();
            $table->string('title');
            $table->string('status', 40)->default('info');
            $table->unsignedTinyInteger('progress_percent')->nullable();
            $table->string('location')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_public')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['project_id', 'is_public']);
            $table->index(['project_product_id', 'status']);
            $table->index('occurred_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_tracking_updates');
    }
};
