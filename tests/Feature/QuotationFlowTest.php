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

    private Customer $customer;

    private Tariff $service;

    private Tariff $item;

    protected function setUp(): void
    {
        parent::setUp();

        // every page needs a signed-in user
        $this->actingAs(\App\Models\User::factory()->create(['name' => 'Test Staff']));

        $this->customer = Customer::create(['name' => 'ABC', 'discount_percent' => 10]);
        $this->service = Tariff::create(['type' => 'service', 'code' => 'S1', 'description' => 'Welding', 'uom' => 'hr', 'cost' => 300, 'price' => 500]);
        $this->item = Tariff::create(['type' => 'item', 'code' => 'I1', 'description' => 'Plate', 'uom' => 'pc', 'cost' => 1000, 'price' => 1500]);
    }

    /** the whole demo flow: make → revise → approve → job order → billed */
    public function test_quotation_goes_from_new_to_billed_job_order(): void
    {
        $this->get('/')->assertOk();
        $this->get('/quotations/create')->assertOk();

        $this->post('/quotations', [
            'date' => '2026-10-16',
            'customer_id' => $this->customer->id,
            'project' => 'Barge repair',
            'discount_percent' => 10,
            'lines' => [
                ['type' => 'service', 'tariff_id' => $this->service->id, 'qty' => 4, 'unit_price' => 500],
                ['type' => 'item', 'tariff_id' => $this->item->id, 'qty' => 2, 'unit_price' => 1500],
            ],
        ]);

        $quotation = Quotation::firstOrFail();
        $this->assertSame('QT-'.now()->year.'-0001', $quotation->quotation_no);
        $this->assertEquals(5000, $quotation->subtotal);   // 2000 + 3000
        $this->assertEquals(500, $quotation->discount_amount);
        $this->assertEquals(4500, $quotation->grand_total);
        $this->assertEquals(3200, $quotation->total_cost); // 1200 + 2000

        $this->get('/quotations?status=pending&q=Barge')->assertOk()->assertSee($quotation->quotation_no);
        $this->get("/quotations/{$quotation->id}")->assertOk()->assertSee('Barge repair');
        $this->get("/quotations/{$quotation->id}/print")->assertOk()->assertSee('Customer approval')->assertDontSee('Profit');

        // revise the same quotation: one line, discount changed to 5%
        $this->get("/quotations/{$quotation->id}/edit")->assertOk();
        $this->put("/quotations/{$quotation->id}", [
            'date' => '2026-10-16',
            'customer_id' => $this->customer->id,
            'discount_percent' => 5,
            'lines' => [['type' => 'service', 'tariff_id' => $this->service->id, 'qty' => 2, 'unit_price' => 550]],
        ])->assertRedirect("/quotations/{$quotation->id}");

        $quotation->refresh();
        $this->assertSame(1, Quotation::count());
        $this->assertCount(1, $quotation->lines);
        $this->assertEquals(1045, $quotation->grand_total); // 1100 - 55

        // customer agrees: approve → job order, and the quotation is locked
        $this->post("/quotations/{$quotation->id}/approve");
        $jobOrder = JobOrder::firstOrFail();
        $this->assertSame('approved', $quotation->fresh()->status);
        $this->assertSame('JO-'.now()->year.'-0001', $jobOrder->jo_no);
        $this->get("/quotations/{$quotation->id}/edit")->assertRedirect("/quotations/{$quotation->id}");
        $this->post("/quotations/{$quotation->id}/approve");
        $this->assertSame(1, JobOrder::count());

        $this->get('/job-orders?status=active')->assertOk()->assertSee($jobOrder->jo_no);
        $this->get("/job-orders/{$jobOrder->id}")->assertOk()->assertSee($jobOrder->jo_no);

        $this->patch("/job-orders/{$jobOrder->id}/status", ['status' => 'completed'])->assertRedirect();
        $this->post("/job-orders/{$jobOrder->id}/invoice", ['date' => '2026-10-16', 'due_date' => '2026-11-15'])->assertRedirect();
        $this->assertSame('billed', $jobOrder->fresh()->status);
        $this->get('/job-orders?status=billed')->assertOk()->assertSee($jobOrder->jo_no);
    }

    public function test_completed_job_order_is_invoiced_and_paid_with_history(): void
    {
        $this->post('/quotations', [
            'date' => '2026-10-19', 'customer_id' => $this->customer->id, 'discount_percent' => 0,
            'lines' => [['type' => 'service', 'tariff_id' => $this->service->id, 'qty' => 2, 'unit_price' => 500]], // ₱1,000
        ]);
        $quotation = Quotation::firstOrFail();
        $this->post("/quotations/{$quotation->id}/approve");
        $jobOrder = JobOrder::firstOrFail();

        // can't invoice before the work is completed, and "billed" can't be set by hand
        $this->post("/job-orders/{$jobOrder->id}/invoice", ['date' => '2026-10-19', 'due_date' => '2026-11-18']);
        $this->assertDatabaseCount('invoices', 0);
        $this->patch("/job-orders/{$jobOrder->id}/status", ['status' => 'billed'])->assertSessionHasErrors('status');

        $this->patch("/job-orders/{$jobOrder->id}/status", ['status' => 'completed']);
        $this->post("/job-orders/{$jobOrder->id}/invoice", ['date' => '2026-10-19', 'due_date' => '2026-10-01'])->assertSessionHasErrors('due_date');
        $this->post("/job-orders/{$jobOrder->id}/invoice", ['date' => '2026-10-19', 'due_date' => '2026-11-18', 'notes' => '30 days']);

        $invoice = \App\Models\Invoice::firstOrFail();
        $this->assertSame('INV-'.now()->year.'-0001', $invoice->invoice_no);
        $this->assertEquals(1000, $invoice->amount);
        $this->assertSame('billed', $jobOrder->fresh()->status);
        $this->assertSame('unpaid', $invoice->status());

        // an invoiced job order can't move back
        $this->patch("/job-orders/{$jobOrder->id}/status", ['status' => 'completed'])->assertSessionHas('error');
        $this->assertSame('billed', $jobOrder->fresh()->status);

        foreach (['/invoices', '/invoices?status=unpaid', "/invoices/{$invoice->id}", "/invoices/{$invoice->id}/print", "/job-orders/{$jobOrder->id}"] as $page) {
            $this->get($page)->assertOk();
        }

        // payments: more than the balance is refused; partial, then full
        $this->post("/invoices/{$invoice->id}/payments", ['date' => '2026-10-20', 'amount' => 1500, 'method' => 'cash'])->assertSessionHasErrors('amount');
        $this->post("/invoices/{$invoice->id}/payments", ['date' => '2026-10-20', 'amount' => 400, 'method' => 'cash']);
        $this->assertSame('partial', $invoice->fresh()->status());
        $this->assertEquals(600, $invoice->fresh()->balance());
        $this->post("/invoices/{$invoice->id}/payments", ['date' => '2026-10-21', 'amount' => 600, 'method' => 'gcash', 'reference' => 'GC-1']);
        $this->assertSame('paid', $invoice->fresh()->status());
        $this->get("/invoices/{$invoice->id}/print")->assertSee('PAID');

        // removing a payment puts the money back on the balance
        $this->delete('/payments/'.$invoice->payments()->latest('id')->first()->id);
        $this->assertEquals(600, $invoice->fresh()->balance());

        // the whole story is in the history
        $actions = \App\Models\Activity::where('quotation_id', $quotation->id)->orderBy('id')->pluck('action')->all();
        $this->assertSame(['created', 'approved', 'status', 'invoiced', 'payment', 'payment', 'payment_removed'], $actions);
        $this->get("/quotations/{$quotation->id}")->assertSee('payment_removed')->assertSee('INV-'.now()->year.'-0001');
    }

    /** a line typed by hand instead of picked from the tariff */
    public function test_manual_line_is_saved_revised_and_printed(): void
    {
        $base = ['date' => '2026-10-22', 'customer_id' => $this->customer->id, 'discount_percent' => 0];

        // no tariff and no description: refused with a clear message
        $this->post('/quotations', $base + ['lines' => [['type' => 'service', 'tariff_id' => '', 'description' => '  ', 'qty' => 1, 'unit_price' => 100]]])
            ->assertSessionHasErrors('lines.0.description');
        $this->assertSame(0, Quotation::count());

        $this->post('/quotations', $base + ['lines' => [
            ['type' => 'service', 'tariff_id' => $this->service->id, 'qty' => 2, 'unit_price' => 500],
            ['type' => 'item', 'tariff_id' => '', 'description' => 'Coast Guard security assistance', 'uom' => 'time', 'qty' => 4, 'unit_price' => 2000, 'note' => 'Towing in and out'],
        ]])->assertSessionHasNoErrors();

        $quotation = Quotation::with('lines')->firstOrFail();
        $manual = $quotation->lines[1];
        $this->assertNull($manual->tariff_id);
        $this->assertSame('item', $manual->type);
        $this->assertSame('Coast Guard security assistance', $manual->description);
        $this->assertSame('time', $manual->uom);
        $this->assertSame('', $manual->code);
        $this->assertEquals(8000, $manual->total);
        $this->assertEquals(9000, $quotation->subtotal);     // 1,000 + 8,000
        $this->assertEquals(600, $quotation->total_cost);    // only the tariff line has a cost (2 × 300)

        // the revise form gets the typed text back, and saving keeps it
        $this->get("/quotations/{$quotation->id}/edit")->assertOk()
            ->assertInertia(fn ($page) => $page->where('quotation.lines.1.description', 'Coast Guard security assistance')->where('quotation.lines.1.uom', 'time'));
        $this->put("/quotations/{$quotation->id}", $base + ['lines' => [
            ['type' => 'item', 'tariff_id' => '', 'description' => 'Coast Guard security assistance (2 guards)', 'uom' => 'time', 'qty' => 4, 'unit_price' => 2500],
        ]])->assertSessionHasNoErrors();
        $this->assertEquals(10000, $quotation->fresh()->grand_total);

        $this->get("/quotations/{$quotation->id}")->assertOk();
        $this->get("/quotations/{$quotation->id}/print")->assertOk()->assertSee('Coast Guard security assistance (2 guards)');
    }

    public function test_saving_opens_the_new_quotation(): void
    {
        $this->post('/quotations', [
            'date' => '2026-10-16', 'customer_id' => $this->customer->id, 'discount_percent' => 0,
            'lines' => [['type' => 'service', 'tariff_id' => $this->service->id, 'qty' => 1, 'unit_price' => 500]],
        ])->assertRedirect('/quotations/'.Quotation::firstOrFail()->id);
    }

    public function test_customers_and_tariffs_can_be_managed(): void
    {
        foreach (['/customers', '/customers/create', "/customers/{$this->customer->id}/edit",
            '/tariffs?type=item', '/tariffs/create', "/tariffs/{$this->item->id}/edit"] as $page) {
            $this->get($page)->assertOk();
        }

        $this->post('/customers', ['name' => 'New Co', 'discount_percent' => 7.5])->assertRedirect('/customers');
        $newCo = Customer::where('name', 'New Co')->firstOrFail();
        $this->assertEquals(7.5, $newCo->discount_percent);
        $this->put("/customers/{$newCo->id}", ['name' => 'New Co Inc', 'discount_percent' => 0])->assertRedirect('/customers');
        $this->assertSame('New Co Inc', $newCo->fresh()->name);
        $this->delete("/customers/{$newCo->id}")->assertRedirect();
        $this->assertNull($newCo->fresh());

        $this->post('/tariffs', ['type' => 'service', 'code' => 'S2', 'description' => 'Painting', 'uom' => 'sqm', 'cost' => 100, 'price' => 180, 'active' => true])
            ->assertRedirect('/tariffs?type=service');
        $this->post('/tariffs', ['type' => 'service', 'code' => 'S2', 'description' => 'Again', 'uom' => 'sqm', 'cost' => 1, 'price' => 2])
            ->assertSessionHasErrors('code'); // codes are unique
        $painting = Tariff::where('code', 'S2')->firstOrFail();
        $this->put("/tariffs/{$painting->id}", ['type' => 'service', 'code' => 'S2', 'description' => 'Painting', 'uom' => 'sqm', 'cost' => 100, 'price' => 200])
            ->assertRedirect();
        $this->assertFalse($painting->fresh()->active); // unchecked box = inactive
        $this->delete("/tariffs/{$painting->id}")->assertRedirect();
        $this->assertNull($painting->fresh());
    }

    public function test_quotation_delete_rules(): void
    {
        $pending = Quotation::create(['quotation_no' => 'QT-2026-0001', 'date' => '2026-10-19', 'customer_id' => $this->customer->id, 'status' => 'pending']);

        // a customer with quotations can't be deleted
        $this->delete("/customers/{$this->customer->id}")->assertRedirect();
        $this->assertNotNull($this->customer->fresh());

        $this->delete("/quotations/{$pending->id}")->assertRedirect('/quotations');
        $this->assertNull($pending->fresh());

        // an approved quotation can't be deleted
        $approved = Quotation::create(['quotation_no' => 'QT-2026-0002', 'date' => '2026-10-19', 'customer_id' => $this->customer->id, 'status' => 'approved']);
        $this->delete("/quotations/{$approved->id}")->assertRedirect();
        $this->assertNotNull($approved->fresh());
    }
}
