<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cashflow_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cashflow_entry_id')->constrained('cashflow_entries')->cascadeOnDelete();
            $table->string('document_type', 30)->default('bill'); // bill, bank_slip, receipt, gst, other
            $table->string('title')->nullable();
            $table->string('file_path');
            $table->string('original_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('extension', 20)->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            /* The archive lists a month at a time and filters by type, and the
               ledger asks "which entries have none" on every page load. */
            $table->index(['cashflow_entry_id', 'document_type']);
            $table->index('document_type');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cashflow_attachments');
    }
};
