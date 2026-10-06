<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The vocabulary the form is built from.
 *
 * The dimensions are editable in the app because the questions a packaging
 * client cares about are the business's to change, not a developer's: add
 * "support after delivery" the week a client asks for it, retire one nobody
 * scores, reorder them so the form leads with what matters this year. The rows
 * seeded by the migration beside this one are the fallback, and an empty table
 * must still render a working form — see `FeedbackVocabulary`.
 *
 * `meta` carries the per-dimension things a column would be the wrong shape
 * for: the hint under the label, whether the line is shown on the public form,
 * the order of a star band. Keeping it here is what lets the settings screen
 * grow a field without a migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedback_master_options', function (Blueprint $table) {
            $table->id();

            $table->string('group', 40);      // dimension | action_type | severity
            $table->string('key', 60);
            $table->string('label');
            $table->string('color', 20)->nullable();
            $table->text('hint')->nullable(); // the sentence under the question
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('meta')->nullable();

            $table->timestamps();

            /* One key per group, and the index the vocabulary service reads on
               every form render. */
            $table->unique(['group', 'key']);
            $table->index(['group', 'is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback_master_options');
    }
};
