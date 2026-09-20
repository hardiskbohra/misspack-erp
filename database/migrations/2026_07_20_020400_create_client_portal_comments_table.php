<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_portal_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('client_portal_user_id')->nullable()->constrained('client_portal_users')->nullOnDelete();
            $table->string('related_type', 80)->default('general');
            $table->unsignedBigInteger('related_id')->nullable();
            $table->string('author_type', 30)->default('client'); // client, internal, system
            $table->foreignId('internal_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->boolean('is_public_to_client')->default(true);
            $table->boolean('is_read_by_internal')->default(false);
            $table->timestamps();

            $table->index(['client_id', 'related_type', 'related_id']);
            $table->index(['author_type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_portal_comments');
    }
};
