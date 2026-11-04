<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_items', function (Blueprint $table) {
            $table->unsignedBigInteger('record_revision')->default(0);
            $table->unsignedBigInteger('identity_revision')->default(0);
            $table->timestamp('archived_at')->nullable()->index();
            $table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();
        });
        Schema::table('stock_movements', function (Blueprint $table) {
            // Existing movements are lazily snapshotted before the first identity edit.
            $table->json('item_snapshot')->nullable();
        });
        // Preserve product references even if an item's product is corrected later.
        // Before this migration item identities were immutable, so the current
        // product is the only historical product reference for existing items.
        Schema::create('stock_item_product_history', function (Blueprint $table) {
            $table->foreignId('stock_item_id')->constrained('stock_items')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->primary(['stock_item_id', 'product_id']);
            $table->index('product_id');
        });
        DB::table('stock_item_product_history')->insertUsing(['stock_item_id', 'product_id'],
            DB::table('stock_items')->select('id', 'product_id'));
        Schema::create('stock_item_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_item_id')->constrained('stock_items')->restrictOnDelete();
            $table->string('action', 30);
            $table->unsignedBigInteger('record_revision');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name');
            $table->text('reason');
            $table->json('before_snapshot');
            $table->json('after_snapshot');
            $table->foreignId('stock_count_id')->nullable()->constrained('stock_counts')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['stock_item_id', 'record_revision']);
        });
    }

    public function down(): void
    {
        // Never silently discard identity history, archive state, or movement snapshots.
        // MySQL DDL commits independently: stop writers during this preflight/drop.
        if ((Schema::hasTable('stock_item_events') && DB::table('stock_item_events')->exists())
            || (Schema::hasColumn('stock_items', 'archived_at') && DB::table('stock_items')->whereNotNull('archived_at')->exists())
            || (Schema::hasColumn('stock_items', 'record_revision') && DB::table('stock_items')->where('record_revision', '>', 0)->exists())
            || (Schema::hasColumn('stock_movements', 'item_snapshot') && DB::table('stock_movements')->whereNotNull('item_snapshot')->exists())) {
            throw new RuntimeException('Cannot roll back stock register controls while identity, archive or movement snapshot history exists.');
        }
        Schema::dropIfExists('stock_item_events');
        Schema::dropIfExists('stock_item_product_history');
        Schema::table('stock_movements', fn (Blueprint $table) => $table->dropColumn('item_snapshot'));
        Schema::table('stock_items', function (Blueprint $table) {
            $table->dropIndex(['archived_at']);
            $table->dropConstrainedForeignId('archived_by');
            $table->dropColumn(['archived_at', 'record_revision', 'identity_revision']);
        });
    }
};
