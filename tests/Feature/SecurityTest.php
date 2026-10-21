<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Quotation;
use App\Models\Tariff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    /** every page except sign-in needs a signed-in user */
    public function test_pages_need_sign_in(): void
    {
        foreach (['/', '/quotations', '/quotations/create', '/job-orders', '/invoices', '/customers', '/tariffs', '/tariffs/create', '/account/password'] as $page) {
            $this->get($page)->assertRedirect('/login');
        }
        $this->post('/quotations', [])->assertRedirect('/login');
        $this->post('/tariffs', [])->assertRedirect('/login');
    }

    public function test_pages_send_browser_protection_headers(): void
    {
        $this->get('/login')->assertOk()
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_password_can_be_changed_with_the_current_one(): void
    {
        $user = User::factory()->create(['password' => 'password']);
        $this->actingAs($user);

        $this->get('/account/password')->assertOk();

        // wrong current password, too short, not repeated the same, same as before
        $this->put('/account/password', ['current_password' => 'nope', 'password' => 'NewPass123', 'password_confirmation' => 'NewPass123'])
            ->assertSessionHasErrors('current_password');
        $this->put('/account/password', ['current_password' => 'password', 'password' => 'short', 'password_confirmation' => 'short'])
            ->assertSessionHasErrors('password');
        $this->put('/account/password', ['current_password' => 'password', 'password' => 'NewPass123', 'password_confirmation' => 'Other123'])
            ->assertSessionHasErrors('password');
        $this->put('/account/password', ['current_password' => 'password', 'password' => 'password', 'password_confirmation' => 'password'])
            ->assertSessionHasErrors('password');
        $this->assertTrue(Hash::check('password', $user->fresh()->password));

        $this->put('/account/password', ['current_password' => 'password', 'password' => 'NewPass123', 'password_confirmation' => 'NewPass123'])
            ->assertRedirect('/')->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('NewPass123', $user->fresh()->password));
    }

    /** numbers too big for the database give a message on the form, not a server error */
    public function test_too_large_amounts_are_refused_politely(): void
    {
        $this->actingAs(User::factory()->create());
        $customer = Customer::create(['name' => 'ABC']);
        $tariff = Tariff::create(['type' => 'service', 'code' => 'S1', 'description' => 'Welding', 'uom' => 'Pax', 'uom2' => 'Day', 'price' => 100]);

        $this->post('/tariffs', ['type' => 'service', 'code' => 'S9', 'description' => 'Big', 'uom' => 'lot', 'cost' => 0, 'price' => 1e13])
            ->assertSessionHasErrors('price');

        $this->post('/quotations', [
            'date' => '2026-10-21', 'customer_id' => $customer->id, 'discount_percent' => 0,
            'lines' => [['type' => 'service', 'tariff_id' => $tariff->id, 'qty' => 9999999, 'qty2' => 9999999, 'unit_price' => 9999999]],
        ])->assertSessionHasErrors('lines.0.qty');
        $this->assertSame(0, Quotation::count());
    }
}
