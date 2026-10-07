<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A class of asset: what the office groups them by, and the depreciation
 * defaults every asset in it inherits.
 *
 * The row is deliberately more than a name. A private limited company
 * depreciates by **class** — furniture 10 years straight line, computers 3
 * years written down, plant 15 — and the useful life, the method and the
 * residual value are three facts about the class before they are ever facts
 * about one chair. So they live here, and the asset form pre-fills from them:
 * choosing "Laptop" writes 3 / WDV / 5% into the three fields, and the office
 * changes them only when the specific asset is genuinely different.
 *
 * That also makes this table a **setting** rather than a record, and it lives
 * where settings live (`/settings/assets`), under the boundary rule in
 * `docs/settings-module.md`: it changes how the assets module behaves for
 * everybody, and a category is not something the company owns. The chairs are.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixed_asset_categories', function (Blueprint $table) {
            $table->id();

            /* The office's own short code — FA-FUR, FA-COMPUTER — because that
               is what the register and the auditor's schedules use. */
            $table->string('code', 40)->unique();
            $table->string('name', 120);

            /* The depreciation defaults. `useful_life_years` is the Schedule II
               style life in whole years; `residual_percent` is the value the
               asset is expected to be worth at the end (5% is the common
               working figure); the method is one of `AssetVocabulary`'s. */
            $table->unsignedSmallInteger('useful_life_years');
            $table->string('depreciation_method', 20)->default('straight_line');
            $table->decimal('residual_percent', 5, 2)->default(5);

            $table->unsignedInteger('sort_order')->default(10);
            $table->string('notes', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_asset_categories');
    }
};
