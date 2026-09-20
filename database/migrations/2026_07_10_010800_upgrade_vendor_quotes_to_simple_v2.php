<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('vendor_quotes')) {
            Schema::table('vendor_quotes', function (Blueprint $table) {
                $this->addColumnIfMissing($table, 'product_name', 'string', ['nullable' => true]);
                $this->addColumnIfMissing($table, 'product_image_path', 'string', ['nullable' => true]);
                $this->addColumnIfMissing($table, 'quantity', 'unsignedInteger', ['nullable' => true]);
                $this->addColumnIfMissing($table, 'unit', 'string', ['default' => 'pcs', 'length' => 30]);
                $this->addColumnIfMissing($table, 'vendor_unit_price', 'decimal', ['nullable' => true, 'total' => 14, 'places' => 4]);
                $this->addColumnIfMissing($table, 'landing_cost_inr', 'decimal', ['nullable' => true, 'total' => 14, 'places' => 2]);
                $this->addColumnIfMissing($table, 'selling_price_inr', 'decimal', ['nullable' => true, 'total' => 14, 'places' => 2]);
                $this->addColumnIfMissing($table, 'printing_options', 'text', ['nullable' => true]);
                $this->addColumnIfMissing($table, 'size_details', 'text', ['nullable' => true]);
                $this->addColumnIfMissing($table, 'weight_details', 'text', ['nullable' => true]);
                $this->addColumnIfMissing($table, 'notes', 'text', ['nullable' => true]);
            });

            if (Schema::hasColumn('vendor_quotes', 'final_landing_cost_inr')) {
                DB::table('vendor_quotes')->whereNull('landing_cost_inr')->update([
                    'landing_cost_inr' => DB::raw('final_landing_cost_inr'),
                ]);
            }

            if (Schema::hasColumn('vendor_quotes', 'final_suggested_price_inr')) {
                DB::table('vendor_quotes')->whereNull('selling_price_inr')->update([
                    'selling_price_inr' => DB::raw('final_suggested_price_inr'),
                ]);
            }

            if (Schema::hasColumn('vendor_quotes', 'size_measurements')) {
                DB::table('vendor_quotes')->whereNull('size_details')->update([
                    'size_details' => DB::raw('size_measurements'),
                ]);
            }

            if (Schema::hasColumn('vendor_quotes', 'weight_measurements')) {
                DB::table('vendor_quotes')->whereNull('weight_details')->update([
                    'weight_details' => DB::raw('weight_measurements'),
                ]);
            }

            if (Schema::hasColumn('vendor_quotes', 'vendor_notes')) {
                DB::table('vendor_quotes')->whereNull('notes')->update([
                    'notes' => DB::raw('vendor_notes'),
                ]);
            }
        }

        if (! Schema::hasTable('vendor_quote_prices')) {
            Schema::create('vendor_quote_prices', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_quote_id')->constrained('vendor_quotes')->cascadeOnDelete();
                $table->unsignedInteger('quantity')->nullable();
                $table->string('unit', 30)->default('pcs');
                $table->string('finish_type')->nullable();
                $table->string('printing_type')->nullable();
                $table->decimal('vendor_unit_price', 14, 4)->nullable();
                $table->decimal('landing_cost_inr', 14, 2)->nullable();
                $table->decimal('selling_price_inr', 14, 2)->nullable();
                $table->unsignedInteger('moq')->nullable();
                $table->text('remarks')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('vendor_quote_items') && Schema::hasTable('vendor_quote_prices')) {
            $alreadyMigrated = DB::table('vendor_quote_prices')->exists();

            if (! $alreadyMigrated) {
                $items = DB::table('vendor_quote_items')->get();

                foreach ($items as $item) {
                    DB::table('vendor_quote_prices')->insert([
                        'vendor_quote_id' => $item->vendor_quote_id,
                        'quantity' => $item->quantity ?? null,
                        'unit' => $item->unit ?? 'pcs',
                        'finish_type' => $item->finish_type ?? null,
                        'printing_type' => $item->printing_type ?? null,
                        'vendor_unit_price' => $item->vendor_unit_price ?? null,
                        'landing_cost_inr' => property_exists($item, 'landing_cost_inr') ? $item->landing_cost_inr : null,
                        'selling_price_inr' => property_exists($item, 'calculated_unit_price_inr') ? $item->calculated_unit_price_inr : null,
                        'moq' => $item->moq ?? null,
                        'remarks' => $item->remarks ?? null,
                        'created_at' => $item->created_at ?? now(),
                        'updated_at' => $item->updated_at ?? now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        // No-op. This migration upgrades older vendor quote data to the simplified V2 structure.
    }

    private function addColumnIfMissing(Blueprint $table, string $column, string $type, array $options = []): void
    {
        if (Schema::hasColumn('vendor_quotes', $column)) {
            return;
        }

        if ($type === 'string') {
            $definition = $table->string($column, $options['length'] ?? 255);
        } elseif ($type === 'text') {
            $definition = $table->text($column);
        } elseif ($type === 'unsignedInteger') {
            $definition = $table->unsignedInteger($column);
        } elseif ($type === 'decimal') {
            $definition = $table->decimal($column, $options['total'] ?? 14, $options['places'] ?? 2);
        } else {
            return;
        }

        if (($options['nullable'] ?? false) === true) {
            $definition->nullable();
        }

        if (array_key_exists('default', $options)) {
            $definition->default($options['default']);
        }
    }
};
