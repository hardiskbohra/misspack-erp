<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->text('comment');
            $table->string('comment_type', 40)->default('internal'); // internal, customer_update, follow_up, purchase_note
            $table->timestamp('next_follow_up_at')->nullable();
            $table->boolean('is_pinned')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['lead_id', 'created_at']);
            $table->index(['comment_type', 'next_follow_up_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_comments');
    }
};
