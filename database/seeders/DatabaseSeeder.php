<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Tariff;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /** sample customers and tariffs so the screens are not empty on first run */
    public function run(): void
    {
        Customer::create(['name' => 'ABC Construction', 'address' => 'Calapan City, Oriental Mindoro', 'contact_person' => 'Juan Dela Cruz', 'contact_no' => '0917 123 4567', 'discount_percent' => 10]);
        Customer::create(['name' => 'Bayside Shipping', 'address' => 'Batangas Port', 'contact_person' => 'Maria Santos', 'contact_no' => '0918 765 4321', 'discount_percent' => 5]);
        Customer::create(['name' => 'Walk-in Customer', 'discount_percent' => 0]);

        foreach ([
            ['service', 'SVC-001', 'Welding works', 'hr', 'Hour', 350, 600],
            ['service', 'SVC-002', 'Hull painting', 'sqm', 'Square meter', 180, 320],
            ['service', 'SVC-003', 'Engine inspection', 'lot', 'Lot', 2500, 4500],
            ['item', 'ITM-001', 'Steel plate 4mm', 'pc', 'Piece', 3200, 4100],
            ['item', 'ITM-002', 'Marine paint (4L)', 'gal', 'Gallon', 1450, 1900],
            ['item', 'ITM-003', 'Welding rod 3.2mm', 'kg', 'Kilogram', 140, 210],
        ] as [$type, $code, $desc, $uom, $unit, $cost, $price]) {
            Tariff::create(compact('type', 'code', 'uom', 'unit', 'cost', 'price') + ['description' => $desc]);
        }
    }
}
