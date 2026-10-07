<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A fixed asset — the company's own register, as a table.
 *
 * The office keeps this in a spreadsheet today, and the columns below are that
 * spreadsheet read back: asset ID, category, name, description, make, model,
 * serial / IMEI, purchase date, supplier, invoice number and date, cost, GST,
 * location, department, custodian, status, warranty end, useful life,
 * depreciation method, accumulated depreciation, net book value, insurance,
 * last physical verification, condition, disposal date and value, remarks.
 *
 * Two of those columns are **not here**, and that is the design:
 *
 *   - **accumulated depreciation** and **net book value** are readings, not
 *     facts. They are what the asset's cost, life, method, purchase date and
 *     disposal date *mean* on a given day, computed by `AssetDepreciation` from
 *     the columns that are here. A stored copy beside them is a second answer
 *     that drifts — the spreadsheet's whole problem is that its NBV column is
 *     right on the day it is typed and wrong every day after — and a check
 *     (`assets-check`) refuses the columns so nobody adds them back;
 *   - **total cost** is likewise `cost + gst_amount`, derived. The register
 *     sorts by it with an order-by expression.
 *
 * What *is* stored about depreciation is the **recipe**, and nothing else:
 * useful life, method and residual percent, each inherited from the category
 * unless this asset overrides it.
 *
 * Three more facts the Excel keeps in a cell and the register keeps properly:
 *
 *   - `depreciate_on_total` — whether the GST is capitalised or claimed as
 *     input credit. A company that takes the credit depreciates the ex-GST
 *     value; one that cannot (a car, a blocked credit) capitalises the lot, and
 *     the difference is money over the life of the asset. False by default,
 *     because taking the credit is the normal case;
 *   - `last_verified_on` / `last_verified_by` / `condition` — the physical
 *     verification, which is what the auditor's CARO 2020 clause 3(i) asks
 *     about: proper records of the PPE *including its situation*, and a
 *     verification at reasonable intervals. Recording *who* looked and *what
 *     they found* is the difference between a date and evidence;
 *   - `status` walks in_use → spare → maintenance → disposed. A **disposed**
 *     asset leaves the books at its disposal date: the schedule stops that day
 *     and its NBV reads zero afterwards, because the balance sheet no longer
 *     holds it. The money it fetched and the profit or loss against its book
 *     value are both derived at read time.
 *
 * The register is per *asset*, not per quantity: a company that bought 20
 * chairs with one invoice enters them as one row and says so in the description
 * or the serial column ("20 chairs, lot"), exactly as the spreadsheet does. A
 * quantity column would need a second table to be honest about which chair was
 * verified, and pretending otherwise is worse than the shortcut.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixed_assets', function (Blueprint $table) {
            $table->id();

            /* The office's own numbering — the ID the register is filed under. */
            $table->string('asset_code', 40)->unique();

            /* What it is. */
            $table->string('name', 160);
            $table->foreignId('category_id')->nullable()->constrained('fixed_asset_categories')->nullOnDelete();
            $table->text('description')->nullable();
            $table->string('make', 120)->nullable();
            $table->string('model', 120)->nullable();
            $table->string('serial_no', 120)->nullable();

            /* Where it came from. */
            $table->date('purchase_date');
            $table->foreignId('vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
            $table->string('supplier_name', 160)->nullable();
            $table->string('invoice_no', 80)->nullable();
            $table->date('invoice_date')->nullable();

            /* What it cost. `cost` is the invoice value net of GST and is what
               depreciation runs on unless the credit was not taken. */
            $table->decimal('cost', 15, 2);
            $table->decimal('gst_amount', 15, 2)->default(0);
            $table->boolean('depreciate_on_total')->default(false);

            /* Where it is and who answers for it. The allocation history is how
               these got to their current values; this is the current answer the
               register reads and filters on, and one writer keeps them in step
               (`AssetIntake::allocate()`). */
            $table->string('location', 120)->nullable();
            $table->string('department', 80)->nullable();
            $table->foreignId('custodian_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('status', 20)->default('in_use');
            $table->date('warranty_end_date')->nullable();

            /* The depreciation recipe: the category's, unless overridden. */
            $table->unsignedSmallInteger('useful_life_years')->nullable();
            $table->string('depreciation_method', 20)->nullable();
            $table->decimal('residual_percent', 5, 2)->nullable();

            /* Insurance and verification. */
            $table->text('insurance_details')->nullable();
            $table->date('insurance_expiry')->nullable();
            $table->date('last_verified_on')->nullable();
            $table->foreignId('last_verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('condition', 20)->nullable();

            /* Off the books. The schedule stops at `disposal_date`. */
            $table->date('disposal_date')->nullable();
            $table->decimal('disposal_value', 15, 2)->nullable();

            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            /* The questions the register asks: what is in use, what this
               category is worth, what this person holds, what was bought in a
               year, what has been disposed. */
            $table->index(['status', 'purchase_date']);
            $table->index('category_id');
            $table->index('custodian_id');
            $table->index('purchase_date');
            $table->index('disposal_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_assets');
    }
};
