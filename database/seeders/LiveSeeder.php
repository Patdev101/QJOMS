<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Everything the live server starts with — no sample data:
 * admin account, the NSRMS customer, the NSRMS tariff and the shipyard (MSSAI) service tariff.
 * Run: php artisan db:seed --class=LiveSeeder --force   (safe to run again, nothing is duplicated)
 */
class LiveSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AdminUserSeeder::class,
            NsrmsCustomerSeeder::class,
            NsrmsTariffSeeder::class,
            MssaiTariffSeeder::class,
        ]);
    }
}
