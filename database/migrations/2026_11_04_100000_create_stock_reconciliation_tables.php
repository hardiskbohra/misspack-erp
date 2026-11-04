<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->string('variant', 200)->nullable();
            $table->string('batch_code', 120)->nullable();
            $table->date('expires_on')->nullable();
            $table->string('unit', 30);
            $table->char('identity_hash', 64)->unique();
            // Integer thousandths are exact on MySQL and SQLite. Cache only;
            // stock_movements is the append-only source of the balance.
            $table->unsignedBigInteger('quantity_milli')->default(0);
            $table->unsignedBigInteger('ledger_version')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('creator_name', 255);
            $table->timestamps();
            $table->index(['product_id', 'expires_on']);
        });

        Schema::create('stock_counts', function (Blueprint $table) {
            $table->id();
            $table->string('title', 160);
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('draft');
            $table->unsignedBigInteger('revision')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('counter_name', 255);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('approver_name', 255)->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        Schema::create('stock_count_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_count_id')->constrained('stock_counts')->restrictOnDelete();
            $table->foreignId('stock_item_id')->constrained('stock_items')->restrictOnDelete();
            $table->json('item_snapshot');
            $table->unsignedBigInteger('book_milli');
            $table->unsignedBigInteger('ledger_version');
            $table->unsignedBigInteger('counted_milli')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();
            $table->unique(['stock_count_id', 'stock_item_id']);
        });

        Schema::create('stock_count_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_count_id')->constrained('stock_counts')->restrictOnDelete();
            $table->string('action', 30);
            $table->unsignedBigInteger('count_revision');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name', 255);
            $table->text('reason')->nullable();
            $table->json('snapshot')->nullable();
            $table->timestamps();
            $table->index(['stock_count_id', 'id']);
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_item_id')->constrained('stock_items')->restrictOnDelete();
            $table->string('type', 30); // opening, receipt, issue, count_adjustment
            $table->bigInteger('delta_milli');
            $table->unsignedBigInteger('balance_after_milli');
            $table->unsignedBigInteger('ledger_version');
            $table->text('reason');
            $table->string('reference', 255)->nullable();
            $table->string('request_key', 80)->unique();
            $table->foreignId('stock_count_line_id')->nullable()->constrained('stock_count_lines')->restrictOnDelete();
            $table->unique('stock_count_line_id');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name', 255);
            $table->timestamps();
            $table->unique(['stock_item_id', 'ledger_version']);
            $table->index(['stock_item_id', 'id']);
        });
    }

    public function down(): void
    {
        foreach (['stock_movements', 'stock_count_events', 'stock_count_lines', 'stock_counts', 'stock_items'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new RuntimeException('Cannot roll back stock: stock history exists. Back it up and remove or migrate it deliberately first.');
            }
        }
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_count_events');
        Schema::dropIfExists('stock_count_lines');
        Schema::dropIfExists('stock_counts');
        Schema::dropIfExists('stock_items');
    }
};
