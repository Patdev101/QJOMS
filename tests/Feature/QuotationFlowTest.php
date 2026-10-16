<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\JobOrder;
use App\Models\Quotation;
use App\Models\Tariff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuotationFlowTest extends TestCase
{
    use RefreshDatabase;

    /** for now only the dashboard and "new quotation" are open */
    public function test_new_quotation_is_computed_and_saved_back_to_the_dashboard(): void
    {
        $customer = Customer::create(['name' => 'ABC', 'discount_percent' => 10]);
        $service = Tariff::create(['type' => 'service', 'code' => 'S1', 'description' => 'Welding', 'uom' => 'hr', 'cost' => 300, 'price' => 500]);
        $item = Tariff::create(['type' => 'item', 'code' => 'I1', 'description' => 'Plate', 'uom' => 'pc', 'cost' => 1000, 'price' => 1500]);

        $this->get('/')->assertOk();
        $this->get('/quotations/create')->assertOk();

        $this->post('/quotations', [
            'date' => '2026-10-14',
            'customer_id' => $customer->id,
            'project' => 'Barge repair',
            'discount_percent' => 10,
            'lines' => [
                ['type' => 'service', 'tariff_id' => $service->id, 'qty' => 4, 'unit_price' => 500],
                ['type' => 'item', 'tariff_id' => $item->id, 'qty' => 2, 'unit_price' => 1500],
            ],
        ])->assertRedirect('/');

        $quotation = Quotation::first();
        $this->assertSame('QT-'.now()->year.'-0001', $quotation->quotation_no);
        $this->assertEquals(5000, $quotation->subtotal);   // 2000 + 3000
        $this->assertEquals(500, $quotation->discount_amount);
        $this->assertEquals(4500, $quotation->grand_total);
        $this->assertEquals(3200, $quotation->total_cost); // 1200 + 2000

        // the dashboard lists it, but not as a link
        $this->get('/')->assertSee($quotation->quotation_no)->assertDontSee("/quotations/{$quotation->id}");
    }

    public function test_switched_off_pages_answer_not_found(): void
    {
        $customer = Customer::create(['name' => 'ABC', 'discount_percent' => 0]);
        $quotation = Quotation::create([
            'quotation_no' => 'QT-2026-0001', 'date' => '2026-10-14', 'customer_id' => $customer->id, 'status' => 'pending',
        ]);

        foreach (['/quotations', "/quotations/{$quotation->id}", "/quotations/{$quotation->id}/edit", "/quotations/{$quotation->id}/print",
            '/job-orders', '/customers', '/customers/create', '/tariffs', '/tariffs/create'] as $page) {
            $this->get($page)->assertNotFound();
        }

        // approving does nothing
        $this->post("/quotations/{$quotation->id}/approve")->assertNotFound();
        $this->assertSame('pending', $quotation->fresh()->status);
        $this->assertSame(0, JobOrder::count());
    }
}
