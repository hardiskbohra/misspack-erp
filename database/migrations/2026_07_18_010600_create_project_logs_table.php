<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('project_product_id')->nullable()->constrained('project_products')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_type', 30)->default('internal');
            $table->string('actor_name')->nullable();
            $table->string('event_type', 60);
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->boolean('is_public')->default(false);
            $table->timestamps();

            $table->index(['project_id', 'event_type']);
            $table->index(['project_id', 'is_public']);
            $table->index('project_product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_logs');
    }
};
