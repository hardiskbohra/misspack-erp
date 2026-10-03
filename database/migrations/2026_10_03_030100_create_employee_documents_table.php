<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The papers an employee hands over: identity, address, experience, resume.
 *
 * Kept apart from `cashflow_attachments` on purpose. Those are the bills behind
 * the ledger — the accountant's file. These are a person's own documents, and
 * the whole point of them is that the employee can see and upload their own
 * while nobody else's is visible to them: a `user_id` that the *reader* is
 * checked against, which a shared attachments table could not express without
 * every other reader suddenly having to know about it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('employee_documents')) {
            return;
        }

        Schema::create('employee_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            /* id_proof · address_proof · resume · experience_certificate ·
               education · pan_card · bank_proof · offer_letter · other */
            $table->string('document_type', 40);
            $table->string('title')->nullable();
            $table->string('document_number')->nullable();

            $table->string('file_path');
            $table->string('original_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('extension', 20)->nullable();

            $table->date('expires_on')->nullable();

            /* Verification is the office's mark, and it is why an uploaded
               document is worth anything: "we have seen this one". */
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->string('remarks')->nullable();

            /* Who put it there — the employee themselves or somebody in the
               office on their behalf. */
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'document_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_documents');
    }
};
