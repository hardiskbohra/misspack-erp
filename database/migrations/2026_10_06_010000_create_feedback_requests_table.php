<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One ask: a project, a client, and the link that carries the question.
 *
 * The row is the *right to answer*, exactly as `party_statement_shares` is the
 * right to look: the token is the whole of the authentication, the expiry and
 * the revocation are the door closing, and the view counters are how the office
 * knows the difference between "they didn't answer" and "they never opened it".
 *
 * Nothing about the project is copied onto this row. Unlike a statement — whose
 * party name must survive a rename because the log is read six months later —
 * a feedback ask is always read beside the project it belongs to, and a copied
 * project name here would be a second name to keep true.
 *
 * `kind` is on the row rather than in a second table because the close-out ask
 * and the mid-project pulse are the same question asked at two moments: same
 * form, same scoring, same loop, different timing and different wording.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedback_requests', function (Blueprint $table) {
            $table->id();

            /* The link's secret: 48 random characters, the whole of the
               authentication a feedback link has. */
            $table->string('token', 80)->unique();

            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();

            $table->string('kind', 20)->default('close_out');   // close_out | pulse
            $table->string('note')->nullable();                 // the line printed above the form

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('shared_via', 20)->nullable();       // link | whatsapp | email | portal

            $table->dateTime('expires_at')->nullable();          // null = never expires
            $table->dateTime('revoked_at')->nullable();

            $table->unsignedInteger('views')->default(0);
            $table->dateTime('first_viewed_at')->nullable();
            $table->dateTime('last_viewed_at')->nullable();
            $table->string('last_viewed_ip', 45)->nullable();

            /* Two reminders is the whole ladder. A third is pestering, and a
               client who has not answered by then has answered. */
            $table->unsignedInteger('reminder_count')->default(0);
            $table->dateTime('last_reminded_at')->nullable();

            $table->timestamps();

            /* The questions the list is asked: "this project's asks", "this
               client's asks", "what is still open", "what expires next". */
            $table->index(['project_id', 'kind']);
            $table->index(['client_id', 'created_at']);
            $table->index('expires_at');
            $table->index(['reminder_count', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback_requests');
    }
};
