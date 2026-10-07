<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The public project portal is gone. The client used to follow a project through
 * `/project-portal/{token}`, a read-and-reply page whose only authentication was
 * the token; the client now signs in to the login portal, and that is where a
 * project is shown. The login portal decides what a client sees with
 * `show_client_portal`, which it read — and still reads — on this table, so that
 * column stays. `public_token` existed to mint the token link and nothing else
 * resolved it, so it goes with the page.
 *
 * Guarded like every destructive step here: an install that never had the column
 * (or has already dropped it) must not fail the migrate run.
 *
 * down() is deliberately empty: the route that read this token no longer exists,
 * and minting a 48-character key per project for a page nothing renders would
 * only be debt with a migration attached.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('projects') || ! Schema::hasColumn('projects', 'public_token')) {
            return;
        }

        Schema::table('projects', function (Blueprint $table): void {
            $table->dropColumn('public_token');
        });
    }

    public function down(): void
    {
        // Nothing to restore: no screen reads a project token any more.
    }
};
