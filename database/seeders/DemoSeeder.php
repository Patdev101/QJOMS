<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\JobOrder;
use App\Models\Quotation;
use App\Models\Tariff;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Sample quotations and job orders for trying out the screens.
 * Run on purpose only: php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $abc = Customer::where('name', 'ABC Construction')->firstOrFail();
        $bayside = Customer::where('name', 'Bayside Shipping')->firstOrFail();
        $walkIn = Customer::where('name', 'Walk-in Customer')->firstOrFail();

        $this->quote($abc, 'Barge hull repair', ['SVC-001' => 16, 'SVC-002' => 40, 'ITM-001' => 6, 'ITM-003' => 25], 9, 'billed');
        $this->quote($bayside, 'MV Bayside engine overhaul', ['SVC-003' => 1, 'SVC-001' => 8], 6, 'ongoing');
        $this->quote($abc, 'Pier railing fabrication', ['ITM-001' => 4, 'ITM-003' => 18, 'SVC-001' => 12], 4, 'completed');
        $this->quote($bayside, 'Repainting of tugboat', ['SVC-002' => 85, 'ITM-002' => 12], 2);
        $this->quote($walkIn, 'Small boat welding', ['SVC-001' => 3, 'ITM-003' => 2], 1);
        $this->quote($abc, 'Warehouse door repair', ['SVC-001' => 6, 'ITM-001' => 1], 0);
    }

    /** builds a quotation the same way the screen does; with $joStatus it is approved into a job order */
    private function quote(Customer $customer, string $project, array $lines, int $daysAgo, ?string $joStatus = null): void
    {
        $date = Carbon::now()->subDays($daysAgo)->setTime(9 + $daysAgo % 6, 15);
        $tariffs = Tariff::whereIn('code', array_keys($lines))->get()->keyBy('code');

        $rows = collect($lines)->map(fn ($qty, $code) => [
            'tariff_id' => $tariffs[$code]->id,
            'type' => $tariffs[$code]->type,
            'code' => $code,
            'description' => $tariffs[$code]->description,
            'uom' => $tariffs[$code]->uom,
            'qty' => $qty,
            'unit_cost' => $tariffs[$code]->cost,
            'unit_price' => $tariffs[$code]->price,
            'total' => $qty * $tariffs[$code]->price,
        ])->values();

        $subtotal = $rows->sum('total');
        $discount = round($subtotal * $customer->discount_percent / 100, 2);

        $quotation = new Quotation([
            'quotation_no' => Quotation::nextNumber(),
            'date' => $date,
            'customer_id' => $customer->id,
            'address' => $customer->address,
            'contact_person' => $customer->contact_person,
            'contact_no' => $customer->contact_no,
            'project' => $project,
            'subtotal' => $subtotal,
            'discount_percent' => $customer->discount_percent,
            'discount_amount' => $discount,
            'grand_total' => $subtotal - $discount,
            'total_cost' => $rows->sum(fn ($r) => $r['qty'] * $r['unit_cost']),
            'status' => $joStatus ? 'approved' : 'pending',
            'approved_at' => $joStatus ? $date->copy()->addDay() : null,
        ]);
        $quotation->created_at = $date;
        $quotation->save();
        $quotation->lines()->createMany($rows->all());

        if ($joStatus) {
            JobOrder::create([
                'jo_no' => JobOrder::nextNumber(),
                'date' => $date->copy()->addDay(),
                'quotation_id' => $quotation->id,
                'status' => $joStatus,
            ]);
        }
    }
}
