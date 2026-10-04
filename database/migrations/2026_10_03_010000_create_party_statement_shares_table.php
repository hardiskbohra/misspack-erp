<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A statement of account, sent as a link.
 *
 * The statement itself is computed from the ledger every time it is opened —
 * never stored — because a stored copy is a copy that goes stale the moment a
 * bill is booked late. What is stored is the *right to look*: which party,
 * which period, which currency, for how long, and what happened to it.
 *
 * The party name is denormalised on purpose. The log answers "what did I send
 * Shree Traders on the 5th" six months from now, and it has to keep answering
 * it after the party is renamed or deleted — the one place in this schema where
 * a copied string is the more truthful record.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('party_statement_shares', function (Blueprint $table) {
            $table->id();

            /* The link's secret: 48 random characters, the whole of the
               authentication a statement link has. */
            $table->string('token', 80)->unique();

            $table->string('party_type', 20);            // client | vendor
            $table->unsignedBigInteger('party_id');
            $table->string('party_name');                // as it read when the link was made
            $table->string('party_currency', 10)->default('INR');
            $table->date('date_from')->nullable();
            $table->date('date_to')->nullable();
            $table->string('label')->nullable();         // "October 2026" — what the log reads
            $table->text('note')->nullable();            // the line printed on the statement
            $table->json('options')->nullable();         // ageing on/off, currency mode
            $table->string('shared_via', 20)->nullable(); // link | whatsapp | email | portal

            $table->dateTime('expires_at')->nullable();  // null = never expires
            $table->dateTime('revoked_at')->nullable();

            $table->unsignedInteger('views')->default(0);
            $table->dateTime('first_viewed_at')->nullable();
            $table->dateTime('last_viewed_at')->nullable();
            $table->string('last_viewed_ip', 45)->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            /* The two questions the log is asked: "this party's links" and
               "what expires next". */
            $table->index(['party_type', 'party_id']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('party_statement_shares');
    }
};
