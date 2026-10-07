<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who has the asset, and where — every hand-over, kept.
 *
 * The asset carries the *current* location, department and custodian; this table
 * is how they got there and who had it before. Both are needed and neither is
 * enough: the register must be able to answer "what is at the Ahmedabad office"
 * in one query without walking history, and an auditor asking "who had this
 * laptop in 2024" must get an answer that is not a guess.
 *
 * **One open row per asset** (`returned_on` null) is the invariant, and it is
 * the writer's job, not the database's: `AssetIntake::allocate()` closes the open
 * row and opens the next in the same transaction, so the asset's current
 * columns and its history cannot disagree — and a partial unique index is not
 * portable across the MySQL the office runs and the SQLite the tests run.
 *
 * `allocated_to` is nullable and `holder_name` exists beside it because not
 * everything is issued to a person: a printer stands in the front office and a
 * server rack belongs to a room. The history should be able to say "Front
 * office" without inventing a user.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixed_asset_allocations', function (Blueprint $table) {
            $table->id();

            /* Cascade: a hand-over is part of the asset's own story. Deleting the
               asset deletes its story, deliberately — see
               `AssetIntake::delete()` on what that means for the ledger. */
            $table->foreignId('fixed_asset_id')->constrained('fixed_assets')->cascadeOnDelete();

            $table->foreignId('allocated_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('holder_name', 160)->nullable();

            $table->string('location', 120)->nullable();
            $table->string('department', 80)->nullable();

            $table->date('allocated_on');
            $table->date('returned_on')->nullable();
            $table->string('condition_on_return', 20)->nullable();
            $table->text('note')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            /* "This asset's history" and "what does this person hold". */
            $table->index(['fixed_asset_id', 'allocated_on']);
            $table->index(['allocated_to', 'returned_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_asset_allocations');
    }
};
