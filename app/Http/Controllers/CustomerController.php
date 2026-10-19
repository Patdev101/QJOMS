<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $customers = Customer::withCount('quotations')
            ->when($request->q, fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhere('contact_person', 'like', "%{$s}%")))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'initials' => $c->initials(),
                'color' => $c->avatarColor(),
                'address' => $c->address,
                'contact_person' => $c->contact_person,
                'contact_no' => $c->contact_no,
                'discount_percent' => (float) $c->discount_percent,
                'quotations_count' => $c->quotations_count,
            ]);

        return Inertia::render('Customers/Index', [
            'customers' => $customers,
            'filters' => ['q' => $request->q ?? ''],
        ]);
    }

    public function create()
    {
        return Inertia::render('Customers/Form', ['customer' => null]);
    }

    public function store(Request $request)
    {
        Customer::create($this->validated($request));

        return redirect()->route('customers.index')->with('success', 'Customer added.');
    }

    public function edit(Customer $customer)
    {
        return Inertia::render('Customers/Form', [
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'address' => (string) $customer->address,
                'contact_person' => (string) $customer->contact_person,
                'contact_no' => (string) $customer->contact_no,
                'discount_percent' => (string) (float) $customer->discount_percent,
            ],
        ]);
    }

    public function update(Request $request, Customer $customer)
    {
        $customer->update($this->validated($request));

        return redirect()->route('customers.index')->with('success', 'Customer updated.');
    }

    public function destroy(Customer $customer)
    {
        if ($customer->quotations()->exists()) {
            return back()->with('error', 'This customer has quotations and cannot be deleted.');
        }

        $customer->delete();

        return back()->with('success', 'Customer deleted.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'contact_no' => 'nullable|string|max:50',
            'discount_percent' => 'required|numeric|min:0|max:100',
        ]);
    }
}
