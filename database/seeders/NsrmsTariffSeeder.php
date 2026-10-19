<?php

namespace Database\Seeders;

use App\Models\Tariff;
use Illuminate\Database\Seeder;

/**
 * Norman Ship Repair & Machine Shop (NSRMS) tariff, Rev. 2 (07/18/25):
 * II. Hull, Hold & Tank Preservation › A. Preservation Works.
 *   category     = the numbered heading   (1. Cleaning / Surface Preparation works)
 *   sub-category = each lettered work item (a. High pressure washing ...)
 *   tariffs      = its two prices: Hull area (-H) and Hold/Tank area (-T)
 * Costs are not known yet (0.00).
 *
 * Run: php artisan db:seed --class=NsrmsTariffSeeder   (safe to run again: it updates by code)
 */
class NsrmsTariffSeeder extends Seeder
{
    public function run(): void
    {
        $cleaning = 'Cleaning / Surface Preparation works';
        $blasting = 'Gritblasting (incl. grit supply, recovery, drying, cleaning and hopper loading)';
        $painting = "Painting (paint material Owner's supply)";

        // [code, category, sub-category = work item, uom, unit name, hull price, hold/tank price]
        $lines = [
            ['CLN-01', $cleaning, 'High pressure washing (3000 psi minimum)', 'sqm', 'Square meter', 18.00, 25.00],
            ['CLN-02', $cleaning, 'Hose / wash down', 'sqm', 'Square meter', 10.00, 15.00],
            ['CLN-03', $cleaning, 'Scraping, incl. recovery of barnacles, rust, etc.', 'sqm', 'Square meter', 20.00, 30.00],
            ['CLN-04', $cleaning, 'Surface preparation using power tools (cup brush, flap disc, grinding disc, etc.)', 'sqm', 'Square meter', 95.00, 110.00],
            ['CLN-05', $cleaning, 'Surface preparation using manual tools (chipping, sanding, etc.)', 'sqm', 'Square meter', 78.00, 95.00],
            ['GBL-01', $blasting, 'Gritsweeping SA 1.0', 'sqm', 'Square meter', 350.00, 475.00],
            ['GBL-02', $blasting, 'Gritblasting SA 2.0', 'sqm', 'Square meter', 425.00, 600.00],
            ['GBL-03', $blasting, 'Gritblasting SA 2.5', 'sqm', 'Square meter', 550.00, 750.00],
            ['PNT-01', $painting, 'Using airless spray', 'sqm/coat', 'Square meter per coat', 16.00, 20.00],
            ['PNT-02', $painting, 'Using roller / hand brush', 'sqm/coat', 'Square meter per coat', 18.00, 22.50],
        ];

        foreach ($lines as [$code, $category, $subcategory, $uom, $unit, $hull, $tank]) {
            // painting lines read better on a quotation with the word "Painting" in front
            $description = $category === $painting ? 'Painting '.lcfirst($subcategory) : $subcategory;

            // the two services of each work item
            foreach ([['H', 'Hull Area', $hull], ['T', 'Hold/Tank Area', $tank]] as [$suffix, $area, $price]) {
                $tariff = Tariff::firstOrNew(['code' => "{$code}-{$suffix}"]);
                $tariff->fill([
                    'type' => 'service',
                    'category' => $category,
                    'subcategory' => $subcategory,
                    'description' => "{$description} — {$area}",
                    'uom' => $uom,
                    'unit' => $unit,
                    'price' => $price,
                    'active' => true,
                ]);
                // a cost already typed in the system is kept; new ones start at 0 until known
                if (! $tariff->exists) {
                    $tariff->cost = 0;
                }
                $tariff->save();
            }
        }
    }
}
