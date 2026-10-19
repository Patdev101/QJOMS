<?php

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Seeder;

/**
 * The real customer profile(s) to start the live system with.
 * Run: php artisan db:seed --class=NsrmsCustomerSeeder   (safe to run again: matches by name, never duplicates)
 */
class NsrmsCustomerSeeder extends Seeder
{
    public function run(): void
    {
        Customer::firstOrCreate(['name' => 'Norman Ship Repair & Machine Shop'], [
            'address' => 'Bawal Street Barangay Townsite, Mariveles, Bataan Philippines',
            'contact_person' => 'Norman Ship Repair & Machine Shop',
            'contact_no' => '0476332615, 09452439589, 09289498877',
            'email' => 'normanshiprepair30@gmail.com',
            'discount_percent' => 0,
        ]);
    }
}
