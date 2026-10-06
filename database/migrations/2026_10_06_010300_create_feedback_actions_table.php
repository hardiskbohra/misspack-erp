<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What we did about it — the table that makes the module worth building.
 *
 * A detractor answer raises one of these by itself, with an owner and a clock,
 * so the module can answer the only question that matters about a bad score:
 * *who is doing something about it, and by when*. An action can also be opened
 * by hand for a complaint, a compliment worth repeating, or a win-back.
 *
 * `task_id` links to the office's own task list when the work is ordinary and
 * belongs there. The action is still the record of *why* the work exists — the
 * task will be closed and forgotten; the action is what the figures and the
 * client history are read from.
 *
 * `client_notified_at` records the moment a resolved action was shown back to
 * the client. Closing the loop is the part that turns a complaint into a
 * relationship, and it is the part everybody skips, so it is a column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedback_actions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('feedback_response_id')->constrained('feedback_responses')->cascadeOnDelete();

            $table->string('type', 20)->default('fix');         // fix | acknowledge | referral | win_back
            $table->string('severity', 20)->default('normal');  // low | normal | high | critical
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();

            $table->date('due_on')->nullable();
            $table->string('status', 20)->default('open');      // open | in_progress | resolved | dismissed

            $table->foreignId('task_id')->nullable()->constrained('tasks')->nullOnDelete();
            $table->text('resolution_note')->nullable();
            $table->dateTime('resolved_at')->nullable();
            $table->dateTime('client_notified_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            /* "What is still open, oldest first" — the needs-attention queue. */
            $table->index(['status', 'due_on']);
            $table->index(['owner_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback_actions');
    }
};
