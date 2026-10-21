<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Quotation;
use App\Models\Tariff;
use Database\Seeders\MssaiTariffSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShipyardTariffTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(\App\Models\User::factory()->create());
        $this->customer = Customer::create(['name' => 'Shogun Ships', 'discount_percent' => 0]);
    }

    /** "Pax / Day": 12 pax × 30 days × ₱70 */
    public function test_two_part_unit_multiplies_both_quantities(): void
    {
        $water = Tariff::create(['type' => 'service', 'code' => 'B-019', 'description' => 'Water consumption of crew per head / per day',
            'uom' => 'Pax', 'uom2' => 'Day', 'cost' => 20, 'price' => 70]);
        $layDays = Tariff::create(['type' => 'service', 'code' => 'A-003', 'description' => 'First ten days in dry dock per day',
            'uom' => 'day', 'cost' => 0, 'price' => 149.20]);

        $this->post('/quotations', [
            'date' => '2026-10-21', 'customer_id' => $this->customer->id, 'discount_percent' => 10,
            'vessel_name' => 'M/Tkr. Leila Micaela', 'loa' => 66.16, 'date_drydocked' => '2026-07-02',
            'lines' => [
                ['type' => 'service', 'tariff_id' => $water->id, 'qty' => 12, 'qty2' => 30, 'unit_price' => 70, 'note' => 'Crew of 12 for 30 days'],
                // a one-part unit ignores a second quantity: lay days are days × price only
                ['type' => 'service', 'tariff_id' => $layDays->id, 'qty' => 10, 'qty2' => 99, 'unit_price' => 149.20],
            ],
        ])->assertSessionHasNoErrors();

        $quotation = Quotation::with('lines')->firstOrFail();
        [$waterLine, $layLine] = [$quotation->lines[0], $quotation->lines[1]];

        $this->assertEquals(25200, $waterLine->total);
        $this->assertSame('Pax / Day', $waterLine->unitLabel());
        $this->assertSame('12 × 30', $waterLine->qtyLabel());
        $this->assertSame('Crew of 12 for 30 days', $waterLine->note); // optional note, printed under the line
        $this->assertNull($layLine->note);
        $this->assertEquals(1492, $layLine->total);
        $this->assertNull($layLine->qty2);
        $this->assertSame('10', $layLine->qtyLabel());

        $this->assertEquals(26692, $quotation->subtotal);
        $this->assertEquals(24022.80, $quotation->grand_total);
        $this->assertEquals(7200, $quotation->total_cost); // 12 × 30 × ₱20

        // the vessel is shown and printed, with only the particulars that were filled in
        $this->assertSame(['L.O.A.' => '66.16 m', 'Date drydocked' => 'July 2, 2026'], $quotation->vesselDetails());
        $this->get("/quotations/{$quotation->id}/print")->assertOk()
            ->assertSee('M/Tkr. Leila Micaela')->assertSee('66.16 m')->assertSee('Pax / Day')->assertSee('12 Pax')->assertSee('× 30 Day')
            ->assertSee('Crew of 12 for 30 days')->assertDontSee('L.B.P.');
        $this->get("/quotations/{$quotation->id}")->assertOk();
        $this->get("/quotations/{$quotation->id}/edit")->assertOk();
    }

    public function test_an_empty_second_quantity_counts_as_one(): void
    {
        $storage = Tariff::create(['type' => 'service', 'code' => 'B-028', 'description' => 'Storage Fee', 'uom' => 'Sqm', 'uom2' => 'Day', 'price' => 400]);

        $this->post('/quotations', [
            'date' => '2026-10-21', 'customer_id' => $this->customer->id, 'discount_percent' => 0,
            'lines' => [['type' => 'service', 'tariff_id' => $storage->id, 'qty' => 3, 'unit_price' => 400]],
        ])->assertSessionHasNoErrors();

        $line = Quotation::firstOrFail()->lines()->firstOrFail();
        $this->assertEquals(1200, $line->total);
        $this->assertEquals(1, $line->qty2);
    }

    /** units that are blocks of hours ("8 Hrs", "24 Hrs", "8Hrs") are typed in hours; a plain "Hr" is hours already */
    public function test_hour_blocks_are_typed_in_hours(): void
    {
        $light = Tariff::create(['type' => 'service', 'code' => 'B-014', 'description' => 'up to 100 watts', 'uom' => '8 Hrs', 'uom2' => 'Day', 'cost' => 10, 'price' => 50]);
        $berth = Tariff::create(['type' => 'service', 'code' => 'B-004', 'description' => 'Passing Vessel', 'uom' => '24 Hrs', 'uom2' => 'Day', 'price' => 5000]);
        $grinder = Tariff::create(['type' => 'service', 'code' => 'B-036', 'description' => 'Grinder', 'uom' => 'Unit', 'uom2' => '8Hrs', 'price' => 500]);
        $tug = Tariff::create(['type' => 'service', 'code' => 'B-003', 'description' => 'Twin screw tugboat', 'uom' => 'Hr', 'price' => 7500]);

        $this->post('/quotations', [
            'date' => '2026-10-22', 'customer_id' => $this->customer->id, 'discount_percent' => 0,
            'lines' => [
                ['type' => 'service', 'tariff_id' => $light->id, 'qty' => 16, 'qty2' => 10, 'unit_price' => 50],   // 2 blocks × 10 days
                ['type' => 'service', 'tariff_id' => $light->id, 'qty' => 12, 'unit_price' => 50],                // 1.5 blocks × 1 day
                ['type' => 'service', 'tariff_id' => $berth->id, 'qty' => 24, 'qty2' => 3, 'unit_price' => 5000],  // 1 × 3 days
                ['type' => 'service', 'tariff_id' => $grinder->id, 'qty' => 2, 'qty2' => 24, 'unit_price' => 500], // 2 units × 3 shifts
                ['type' => 'service', 'tariff_id' => $grinder->id, 'qty' => 1, 'unit_price' => 500],               // empty = one 8-hr shift
                ['type' => 'service', 'tariff_id' => $tug->id, 'qty' => 4, 'unit_price' => 7500],                  // 4 hours
            ],
        ])->assertSessionHasNoErrors();

        $q = Quotation::with('lines')->firstOrFail();
        $this->assertSame([1000.0, 75.0, 15000.0, 3000.0, 500.0, 30000.0], $q->lines->map(fn ($l) => (float) $l->total)->all());
        $this->assertEquals(8, $q->lines[4]->qty2);
        $this->assertSame('16 hrs × 10', $q->lines[0]->qtyLabel());
        $this->assertSame('2 × 24 hrs', $q->lines[3]->qtyLabel());
        $this->assertEquals(215, $q->total_cost); // light: 2 blocks × 10 days × ₱10, plus 1.5 blocks × ₱10
        $this->get("/quotations/{$q->id}/print")->assertOk()->assertSee('16 hrs')->assertSee('× 10 Day')->assertSee('× 24 hrs');
    }

    /** a manual line can have a unit in two parts too: a 2nd unit box, or "Pax / Day" typed in the UOM box */
    public function test_manual_line_with_two_units(): void
    {
        $base = ['date' => '2026-10-22', 'customer_id' => $this->customer->id, 'discount_percent' => 0];

        // a 2nd quantity without the name of its unit is refused
        $this->post('/quotations', $base + ['lines' => [['type' => 'service', 'tariff_id' => '', 'description' => 'Crew meals', 'uom' => 'Pax', 'qty' => 12, 'qty2' => 30, 'unit_price' => 70]]])
            ->assertSessionHasErrors('lines.0.uom2');

        $this->post('/quotations', $base + ['lines' => [
            ['type' => 'service', 'tariff_id' => '', 'description' => 'Crew meals', 'uom' => 'Pax', 'uom2' => 'Day', 'qty' => 12, 'qty2' => 30, 'unit_price' => 70],
            ['type' => 'service', 'tariff_id' => '', 'description' => 'Crew water', 'uom' => 'Pax / Day', 'qty' => 12, 'qty2' => 30, 'unit_price' => 10],
            ['type' => 'service', 'tariff_id' => '', 'description' => 'Extra lights', 'uom' => '8 Hrs', 'uom2' => 'Day', 'qty' => 24, 'qty2' => 2, 'unit_price' => 50],
        ]])->assertSessionHasNoErrors();

        $q = Quotation::with('lines')->firstOrFail();
        $this->assertSame([25200.0, 3600.0, 300.0], $q->lines->map(fn ($l) => (float) $l->total)->all()); // 3 blocks × 2 days × ₱50
        $this->assertSame(['Pax', 'Day'], [$q->lines[1]->uom, $q->lines[1]->uom2]);
        $this->get("/quotations/{$q->id}/edit")->assertOk()->assertInertia(fn ($page) => $page->where('quotation.lines.0.uom2', 'Day'));
    }

    public function test_tariff_can_be_saved_with_a_second_unit(): void
    {
        $this->post('/tariffs', ['type' => 'service', 'code' => 'X-1', 'description' => 'Storage fee', 'uom' => 'Sqm', 'uom2' => 'Day',
            'cost' => 0, 'price' => 400, 'active' => true])->assertRedirect('/tariffs?type=service');

        $tariff = Tariff::where('code', 'X-1')->firstOrFail();
        $this->assertSame('Day', $tariff->uom2);

        // a unit can be left blank, as some lines are in the sheet
        $this->post('/tariffs', ['type' => 'service', 'code' => 'X-2', 'description' => 'Environmental fee', 'cost' => 0, 'price' => 0, 'active' => true])
            ->assertSessionHasNoErrors();
        $this->assertSame('', Tariff::where('code', 'X-2')->value('uom'));
        $this->get("/tariffs/{$tariff->id}/edit")->assertOk();
        $this->get('/tariffs?type=service')->assertOk()
            ->assertInertia(fn ($page) => $page->where('tariffs.data.0.uom', 'Sqm / Day'));
    }

    public function test_shipyard_tariff_seeder_can_run_twice_and_keeps_edited_prices(): void
    {
        $this->seed(MssaiTariffSeeder::class);
        $count = Tariff::count();
        $this->assertGreaterThan(300, $count);

        $layDays = Tariff::where('description', 'First ten days in dry dock per day')->orderBy('code')->firstOrFail();
        // units exactly as written in Tariff 2.0
        $this->assertSame('LM', $layDays->uom);
        $this->assertTrue(Tariff::where('uom', 'N/A')->exists());
        $this->assertEquals(149.20, $layDays->price);
        $this->assertSame('Day', Tariff::where('uom', 'Pax')->whereNotNull('uom2')->firstOrFail()->uom2);

        // a price changed by staff stays after seeding again, and nothing is added twice
        $layDays->update(['price' => 165]);
        $this->seed(MssaiTariffSeeder::class);
        $this->assertSame($count, Tariff::count());
        $this->assertEquals(165, $layDays->fresh()->price);
    }
}
