<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_portal_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('client_portal_user_id')->nullable()->constrained('client_portal_users')->nullOnDelete();
            $table->string('related_type', 80)->nullable(); // project, shipment, quote, invoice, kyc, general
            $table->unsignedBigInteger('related_id')->nullable();
            $table->string('category', 60)->default('general');
            $table->string('title')->nullable();
            $table->string('file_path');
            $table->string('original_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('extension', 20)->nullable();
            $table->boolean('is_public_to_client')->default(true);
            $table->boolean('is_reviewed')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'related_type', 'related_id']);
            $table->index(['client_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_portal_documents');
    }
};
