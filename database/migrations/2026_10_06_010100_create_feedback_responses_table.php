<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One answer, one row — and after that, a record.
 *
 * The scores are written once. The office may add an internal note and may
 * change a consent flag when the client asks, but the numbers never move: a
 * figure that can be quietly edited after the fact is not evidence of anything,
 * and the whole point of asking is to have something we cannot talk our way out
 * of. A client who wants to revise an answer gets the ask revoked and reissued;
 * the old row stays beside the new one.
 *
 * The band (promoter / passive / detractor) is deliberately **not** stored. It
 * is computed from `overall_rating`, `nps_score` and the dimension scores by
 * `FeedbackVocabulary`, in one place, so improving the rule improves every
 * existing answer instead of leaving a column of old verdicts behind.
 *
 * `feedback_request_id` is unique: one ask, one answer. That is the index that
 * makes a forwarded link harmless — the second submission is refused by the
 * database, not by a polite message.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedback_responses', function (Blueprint $table) {
            $table->id();

            $table->foreignId('feedback_request_id')->unique()->constrained('feedback_requests')->cascadeOnDelete();

            /* Nullable on purpose: deleting a project must not delete the
               client's words about us. The answer outlives the job it was
               about, and the figures still count it. */
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();

            $table->string('respondent_name');
            $table->string('respondent_email')->nullable();
            $table->string('respondent_designation')->nullable();

            $table->unsignedTinyInteger('overall_rating');            // 1–5
            $table->unsignedTinyInteger('nps_score');                 // 0–10
            $table->string('would_order_again', 10)->nullable();      // yes | maybe | no

            $table->text('went_well')->nullable();
            $table->text('could_improve')->nullable();
            $table->text('testimonial')->nullable();

            /* Named, always: the follow-up needs someone to ring. The consent
               flags are about *publishing*, which is a different question from
               attribution. */
            $table->boolean('attribution_consent')->default(true);
            $table->boolean('publish_website')->default(false);
            $table->boolean('publish_social')->default(false);
            $table->boolean('publish_sales')->default(false);
            $table->boolean('publish_case_study')->default(false);
            $table->boolean('is_anonymous')->default(false);

            $table->string('source', 20)->default('link');             // link | portal
            $table->dateTime('submitted_at');
            $table->string('ip', 45)->nullable();
            $table->string('user_agent')->nullable();

            $table->timestamps();

            $table->index(['project_id', 'submitted_at']);
            $table->index(['client_id', 'submitted_at']);
            $table->index(['overall_rating', 'nps_score']);
            $table->index('submitted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback_responses');
    }
};
