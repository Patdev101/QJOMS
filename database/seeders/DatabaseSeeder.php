<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Tariff;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * The admin account, plus sample customers and tariffs so the screens are not empty on a developer's computer.
     * Safe to run more than once: nothing is added twice.
     * On a live server (APP_ENV=production) only the admin account is created — no sample data.
     */
    public function run(): void
    {
        $this->call(AdminUserSeeder::class);

        if (app()->environment('production')) {
            return;
        }

        foreach ([
            ['ABC Construction', 'Calapan City, Oriental Mindoro', 'Juan Dela Cruz', '0917 123 4567', 10],
            ['Bayside Shipping', 'Batangas Port', 'Maria Santos', '0918 765 4321', 5],
            ['Walk-in Customer', null, null, null, 0],
        ] as [$name, $address, $person, $phone, $discount]) {
            Customer::firstOrCreate(['name' => $name], [
                'address' => $address, 'contact_person' => $person, 'contact_no' => $phone, 'discount_percent' => $discount,
            ]);
        }

        foreach ([
            ['service', 'SVC-001', 'Welding works', 'hr', 'Hour', 350, 600],
            ['service', 'SVC-002', 'Hull painting', 'sqm', 'Square meter', 180, 320],
            ['service', 'SVC-003', 'Engine inspection', 'lot', 'Lot', 2500, 4500],
            ['item', 'ITM-001', 'Steel plate 4mm', 'pc', 'Piece', 3200, 4100],
            ['item', 'ITM-002', 'Marine paint (4L)', 'gal', 'Gallon', 1450, 1900],
            ['item', 'ITM-003', 'Welding rod 3.2mm', 'kg', 'Kilogram', 140, 210],
        ] as [$type, $code, $desc, $uom, $unit, $cost, $price]) {
            Tariff::firstOrCreate(['code' => $code], compact('type', 'uom', 'unit', 'cost', 'price') + ['description' => $desc]);
        }
    }
}
