<?php

namespace Database\Seeders;

use App\Models\Tariff;
use Illuminate\Database\Seeder;

/**
 * Shipyard service tariff, "Tariff-Table 2.0" (Sheet2) exactly as written, one line per sheet row (`row` in the file),
 * from database/data/mssai-tariff.json:
 *   category / sub-category / description / UOM / price = the sheet's Category, Sub Cat, Desc, UOM and Price columns
 *   (a row with no Desc uses its Sub Cat text, since every line needs a description)
 *   uom + uom2 = a unit with "/" ("Pax/ Day"): on a quotation qty × qty2 × price
 *   unit       = the sheet's text when the price is not a number ("Actual cost") — the price is then 0.00
 * Prices are taken as they are; staff correct them on the tariff page. Costs are not known yet (0.00).
 *
 * Run: php artisan db:seed --class=MssaiTariffSeeder   (safe to run again: it updates by code)
 */
class MssaiTariffSeeder extends Seeder
{
    public function run(): void
    {
        $lines = json_decode(file_get_contents(database_path('data/mssai-tariff.json')), true, flags: JSON_THROW_ON_ERROR);

        foreach ($lines as $line) {
            $tariff = Tariff::firstOrNew(['code' => $line['code']]);
            $tariff->fill([
                'type' => 'service',
                'section' => $line['section'] ?? null,
                'category' => $line['category'],
                'subcategory' => $line['subcategory'],
                'description' => $line['description'],
                'uom' => $line['uom'],
                'uom2' => $line['uom2'],
                'unit' => $line['unit'],
                'active' => true,
            ]);
            // prices and costs already changed in the system are kept; new ones come from the sheet
            if (! $tariff->exists) {
                $tariff->price = $line['price'];
                $tariff->cost = 0;
            }
            $tariff->save();
        }
    }
}
