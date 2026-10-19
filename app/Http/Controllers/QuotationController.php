<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\JobOrder;
use App\Models\Quotation;
use App\Models\Tariff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class QuotationController extends Controller
{
    public function index(Request $request)
    {
        $quotations = Quotation::with('customer', 'jobOrder')
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->q, fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('quotation_no', 'like', "%{$s}%")
                ->orWhere('project', 'like', "%{$s}%")
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$s}%"))))
            ->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn ($q) => [
                'id' => $q->id,
                'quotation_no' => $q->quotation_no,
                'customer' => $q->customer->name,
                'project' => $q->project,
                'date' => $q->date->format('M j, Y'),
                'age' => $q->created_at->diffForHumans(),
                'grand_total' => (float) $q->grand_total,
                'status' => $q->status,
                'jo_no' => $q->jobOrder?->jo_no,
            ]);

        $counts = Quotation::selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

        return Inertia::render('Quotations/Index', [
            'quotations' => $quotations,
            'counts' => ['all' => $counts->sum(), 'pending' => $counts['pending'] ?? 0, 'approved' => $counts['approved'] ?? 0],
            'filters' => ['status' => $request->status ?? '', 'q' => $request->q ?? ''],
        ]);
    }

    public function create()
    {
        return $this->form(new Quotation(['quotation_no' => Quotation::nextNumber(), 'date' => now()]));
    }

    public function store(Request $request)
    {
        $quotation = DB::transaction(function () use ($request) {
            $quotation = new Quotation(['quotation_no' => Quotation::nextNumber(), 'status' => 'pending']);

            return $this->save($request, $quotation);
        });

        return redirect()->route('quotations.show', $quotation)->with('success', "Quotation {$quotation->quotation_no} saved.");
    }

    public function show(Quotation $quotation)
    {
        return Inertia::render('Quotations/Show', ['quotation' => $quotation->details()]);
    }

    public function print(Quotation $quotation)
    {
        $quotation->load('customer', 'lines', 'jobOrder');

        return view('quotations.print', compact('quotation'));
    }

    public function edit(Quotation $quotation)
    {
        if (! $quotation->isEditable()) {
            return redirect()->route('quotations.show', $quotation)->with('error', 'An approved quotation can no longer be edited.');
        }

        return $this->form($quotation->load('lines'));
    }

    public function update(Request $request, Quotation $quotation)
    {
        if (! $quotation->isEditable()) {
            return redirect()->route('quotations.show', $quotation)->with('error', 'An approved quotation can no longer be edited.');
        }

        DB::transaction(fn () => $this->save($request, $quotation));

        return redirect()->route('quotations.show', $quotation)->with('success', 'Quotation revised.');
    }

    /** customer agreed to the computation: the quotation becomes a job order */
    public function approve(Quotation $quotation)
    {
        if (! $quotation->isEditable()) {
            return back()->with('error', 'This quotation is already approved.');
        }

        $jobOrder = DB::transaction(function () use ($quotation) {
            $quotation->update(['status' => 'approved', 'approved_at' => now()]);

            return JobOrder::create([
                'jo_no' => JobOrder::nextNumber(),
                'date' => now(),
                'quotation_id' => $quotation->id,
                'status' => 'open',
            ]);
        });

        return redirect()->route('job-orders.show', $jobOrder)->with('success', "Quotation approved. Job Order {$jobOrder->jo_no} created.");
    }

    public function destroy(Quotation $quotation)
    {
        if (! $quotation->isEditable()) {
            return back()->with('error', 'An approved quotation cannot be deleted.');
        }

        $quotation->delete();

        return redirect()->route('quotations.index')->with('success', 'Quotation deleted.');
    }

    /** the React form, empty for a new quotation or filled in for a revision */
    private function form(Quotation $quotation)
    {
        return Inertia::render('Quotations/Create', [
            'number' => $quotation->quotation_no,
            'today' => now()->format('Y-m-d'),
            'types' => Tariff::TYPES,
            'quotation' => $quotation->exists ? [
                'id' => $quotation->id,
                'customer_id' => (string) $quotation->customer_id,
                'date' => $quotation->date->format('Y-m-d'),
                'address' => (string) $quotation->address,
                'contact_person' => (string) $quotation->contact_person,
                'contact_no' => (string) $quotation->contact_no,
                'project' => (string) $quotation->project,
                'discount_percent' => (string) (float) $quotation->discount_percent,
                'lines' => $quotation->lines->map(fn ($l) => [
                    'type' => $l->type,
                    'tariff_id' => $l->tariff_id,
                    'qty' => (string) (float) $l->qty,
                    'unit_price' => number_format($l->unit_price, 2, '.', ''),
                ]),
            ] : null,
            'customers' => Customer::orderBy('name')->get(['id', 'name', 'address', 'contact_person', 'contact_no', 'discount_percent'])
                ->map(fn ($c) => [...$c->toArray(), 'discount_percent' => (float) $c->discount_percent]),
            // inactive tariffs still count when an older quotation already uses them
            'tariffs' => Tariff::where('active', true)->orWhereIn('id', $quotation->lines->pluck('tariff_id')->filter())
                ->orderBy('code')->get(['id', 'type', 'code', 'description', 'uom', 'unit', 'cost', 'price'])
                ->map(fn ($t) => [...$t->toArray(), 'cost' => (float) $t->cost, 'price' => (float) $t->price]),
        ]);
    }

    private function save(Request $request, Quotation $quotation): Quotation
    {
        $data = $request->validate([
            'date' => 'required|date',
            'customer_id' => 'required|exists:customers,id',
            'address' => 'nullable|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'contact_no' => 'nullable|string|max:50',
            'project' => 'nullable|string|max:255',
            'discount_percent' => 'required|numeric|min:0|max:100',
            'lines' => 'required|array|min:1',
            'lines.*.type' => ['required', Rule::in(array_keys(Tariff::TYPES))],
            'lines.*.tariff_id' => 'required|exists:tariffs,id',
            'lines.*.qty' => 'required|numeric|gt:0',
            'lines.*.unit_price' => 'required|numeric|min:0',
        ], [
            'lines.required' => 'Add at least one item or service.',
            'lines.*.tariff_id.required' => 'Choose a code on every line.',
            'lines.*.qty.gt' => 'Quantity must be more than zero.',
        ]);

        $tariffs = Tariff::whereIn('id', collect($data['lines'])->pluck('tariff_id'))->get()->keyBy('id');

        // the server does the math; the numbers on screen are only a preview
        $lines = collect($data['lines'])->map(function ($line) use ($tariffs) {
            $tariff = $tariffs[$line['tariff_id']];

            return [
                'tariff_id' => $tariff->id,
                'type' => $tariff->type,
                'code' => $tariff->code,
                'description' => $tariff->description,
                'uom' => $tariff->uom,
                'qty' => $line['qty'],
                'unit_cost' => $tariff->cost,
                'unit_price' => $line['unit_price'],
                'total' => round($line['qty'] * $line['unit_price'], 2),
            ];
        });

        $subtotal = $lines->sum('total');
        $discount = round($subtotal * $data['discount_percent'] / 100, 2);

        $quotation->fill(collect($data)->except('lines')->all() + [
            'subtotal' => $subtotal,
            'discount_amount' => $discount,
            'grand_total' => $subtotal - $discount,
            'total_cost' => $lines->sum(fn ($l) => $l['qty'] * $l['unit_cost']),
        ])->save();

        $quotation->lines()->delete();
        $quotation->lines()->createMany($lines->all());

        return $quotation;
    }
}
