<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Customer;
use App\Models\Tariff;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_signed_out_visitors_are_sent_to_the_sign_in_page(): void
    {
        foreach (['/', '/quotations', '/quotations/create', '/job-orders', '/invoices', '/customers', '/tariffs'] as $page) {
            $this->get($page)->assertRedirect('/login');
        }

        $this->post('/customers', ['name' => 'Sneaky', 'discount_percent' => 0])->assertRedirect('/login');
        $this->assertDatabaseCount('customers', 0);
        $this->get('/login')->assertOk();
    }

    public function test_admin_can_sign_in_and_out(): void
    {
        $this->seed(AdminUserSeeder::class);

        $this->post('/login', ['email' => 'admin@example.com', 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post('/login', ['email' => 'admin@example.com', 'password' => 'password'])->assertRedirect('/');
        $this->assertAuthenticated();
        $this->get('/login')->assertRedirect('/'); // already signed in

        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_too_many_wrong_passwords_are_blocked_for_a_while(): void
    {
        $this->seed(AdminUserSeeder::class);

        foreach (range(1, 5) as $i) {
            $this->post('/login', ['email' => 'admin@example.com', 'password' => 'wrong']);
        }

        $this->post('/login', ['email' => 'admin@example.com', 'password' => 'password'])->assertStatus(429);
        $this->assertGuest();
    }

    public function test_history_records_who_did_it(): void
    {
        $this->actingAs(User::factory()->create(['name' => 'Maria']));
        $customer = Customer::create(['name' => 'ABC', 'discount_percent' => 0]);
        $tariff = Tariff::create(['type' => 'service', 'code' => 'S1', 'description' => 'Welding', 'uom' => 'hr', 'cost' => 0, 'price' => 100]);

        $this->post('/quotations', [
            'date' => '2026-10-19', 'customer_id' => $customer->id, 'discount_percent' => 0,
            'lines' => [['type' => 'service', 'tariff_id' => $tariff->id, 'qty' => 1, 'unit_price' => 100]],
        ]);

        $this->assertSame('Maria', Activity::firstOrFail()->user_name);
    }

    public function test_a_malformed_tariff_filter_is_ignored(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/tariffs?type[]=x')->assertOk();
    }
}
