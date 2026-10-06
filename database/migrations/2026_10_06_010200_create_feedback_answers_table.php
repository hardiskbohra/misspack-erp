<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The scorecard, as rows.
 *
 * One row per dimension rather than six columns on the response. Two reasons,
 * and both are the reason this module is data and not a form:
 *
 *   - a dimension is added, renamed or retired in the vocabulary table without
 *     a migration, which is what "editable in-app" has to mean if it is to mean
 *     anything;
 *   - "we lost this quarter on delivery but won on print" is a `GROUP BY`,
 *     not a report somebody builds by hand from six columns.
 *
 * The dimension key is written as it read at the time. A dimension retired
 * later still has its history; it simply stops being asked.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedback_answers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('feedback_response_id')->constrained('feedback_responses')->cascadeOnDelete();

            $table->string('dimension', 60);   // the vocabulary key, e.g. product_quality
            $table->unsignedTinyInteger('score'); // 1–5
            $table->text('comment')->nullable();

            $table->timestamps();

            /* One line per dimension per answer, and the index the office report
               groups on. */
            $table->unique(['feedback_response_id', 'dimension']);
            $table->index('dimension');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback_answers');
    }
};
