<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packaging_list_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('packaging_list_id')->constrained()->cascadeOnDelete();
            $table->string('status', 30)->index();
            $table->string('content_hash', 64);
            $table->json('automated_checks');
            $table->json('manual_checks');
            $table->text('notes')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at');
            $table->timestamps();

            $table->index(['packaging_list_id', 'verified_at'], 'pl_verifications_list_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('packaging_list_verifications');
    }
};
