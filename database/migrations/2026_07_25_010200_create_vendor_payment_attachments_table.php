<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_payment_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_payment_entry_id')->constrained('vendor_payment_entries')->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->string('file_path');
            $table->string('original_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('extension', 20)->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('vendor_payment_entry_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_payment_attachments');
    }
};
