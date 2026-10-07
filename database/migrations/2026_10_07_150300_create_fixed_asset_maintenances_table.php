<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Maintenance and repairs — what was done to the asset, by whom, for how much.
 *
 * The register's other history, and the one that costs money after the purchase:
 * a printer's annual contract, a machine's breakdown, a laptop's screen. Each row
 * is one event with the vendor, the invoice and the amount, which is what makes
 * "what has this asset cost us since we bought it" and "what did we spend on
 * repairs this year" answerable rather than remembered.
 *
 * Three fields earn their place beyond the obvious:
 *
 *   - **`next_due_on`** — preventive service is scheduled in advance, and the
 *     register should say what is coming rather than waiting for a breakdown.
 *     The dashboard figure and the register's attention stat read it;
 *   - **downtime_from` / `downtime_to`** — a machine being out of service is the
 *     reason a repair matters to the business, and the days lost are a reading
 *     (`days_down`) rather than a number somebody types;
 *   - **`vendor_id` beside `vendor_name`** — the linked vendor is how the spend
 *     reaches their record; the typed name is how the office enters the man who
 *     fixed it at the desk without inventing a vendor master row for him. Same
 *     shape the cashflow module uses for a party.
 *
 * Nothing is stored about the asset's *state* here. Whether an asset is under
 * repair is the asset's `status`, and a maintenance row does not set it — the
 * office does, in one click, because a repair that has been logged is not
 * automatically a machine that is still down.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixed_asset_maintenances', function (Blueprint $table) {
            $table->id();

            $table->foreignId('fixed_asset_id')->constrained('fixed_assets')->cascadeOnDelete();

            /* service | repair | amc | calibration | upgrade — AssetVocabulary. */
            $table->string('kind', 20)->default('service');
            $table->date('performed_on');

            $table->foreignId('vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
            $table->string('vendor_name', 160)->nullable();
            $table->string('invoice_no', 80)->nullable();
            $table->decimal('cost', 15, 2)->default(0);

            $table->date('downtime_from')->nullable();
            $table->date('downtime_to')->nullable();

            $table->date('next_due_on')->nullable();
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            /* "This asset's history", "what is due", and the year's spend. */
            $table->index(['fixed_asset_id', 'performed_on']);
            $table->index('next_due_on');
            $table->index('performed_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_asset_maintenances');
    }
};
