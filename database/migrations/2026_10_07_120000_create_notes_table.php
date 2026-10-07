<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A note is a private thing: it belongs to one login and to nobody else.
 *
 * That is the whole of the schema decision. `user_id` is not "who wrote it" —
 * it is **who may read it**, which is why it is non-null, indexed first, and
 * cascades when the person's account goes: a note with no owner would be a note
 * with nobody to be private to.
 *
 * `colour`, `is_pinned` and `archived_at` are the three things a sticky note
 * does on a desk — it is a colour, it is stuck where you can see it, and in the
 * end it is filed away rather than thrown away. There is no status column and
 * no state machine: a note is on the desk (`archived_at` null) or it is not.
 *
 * The colour column carries no default on purpose. The vocabulary lives in
 * `App\Services\NoteVocabulary`, and the one writer fills a colour the app
 * knows — a database default would be a second, silent answer to "what colour
 * is a note that was never asked".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('title', 160);
            $table->text('body')->nullable();
            $table->string('colour', 20)->nullable();
            $table->boolean('is_pinned')->default(false);
            $table->timestamp('archived_at')->nullable();

            $table->timestamps();

            /* The board: one owner's notes, pinned first, newest edit first. */
            $table->index(['user_id', 'is_pinned', 'updated_at']);
            /* The two questions the chips ask: what is on the desk, what is filed. */
            $table->index(['user_id', 'archived_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notes');
    }
};
