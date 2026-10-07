<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The six dimensions the office already believes decide a project, seeded once.
 *
 * They are seeded rather than hard-coded so that the first thing the settings
 * screen shows is a working form, and so the office can change the list without
 * a deploy. `FeedbackVocabulary` carries the same six as its fallback, which is
 * what keeps a fresh install with an unseeded table from rendering an empty
 * scorecard.
 *
 * Delivery is deliberately first among the equals: in this trade a late
 * shipment is what a client complains about first and what they remember
 * longest.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('feedback_master_options')) {
            return;
        }

        $now = now();
        $order = 0;

        $dimensions = [
            ['delivery', 'On-time delivery', 'Did the goods arrive when we said they would?', 'teal'],
            ['product_quality', 'Product quality', 'Was the packaging itself right — material, strength, finish?', 'green'],
            ['print_artwork', 'Print / artwork accuracy', 'Did the printed artwork match the approved PPS?', 'blue'],
            ['communication', 'Communication & responsiveness', 'Did you hear back quickly, and clearly?', 'purple'],
            ['packaging_dispatch', 'Packaging & dispatch', 'Was the consignment packed, labelled and documented properly?', 'orange'],
            ['value', 'Value for money', 'Was the price fair for what you received?', 'red'],
        ];

        foreach ($dimensions as [$key, $label, $hint, $color]) {
            $exists = DB::table('feedback_master_options')
                ->where('group', 'dimension')
                ->where('key', $key)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('feedback_master_options')->insert([
                'group' => 'dimension',
                'key' => $key,
                'label' => $label,
                'hint' => $hint,
                'color' => $color,
                'sort_order' => $order++,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        /* The rows are the office's vocabulary by the time this runs backwards:
           a dimension renamed or retired here must not come back on a rollback,
           and the answers that named it stay. */
    }
};
