<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\JobOrder;
use App\Models\Quotation;
use App\Models\Tariff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

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
            ->withQueryString();

        $counts = Quotation::selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

        return view('quotations.index', compact('quotations', 'counts'));
    }

    public function create()
    {
        return $this->form(new Quotation(['date' => now(), 'quotation_no' => Quotation::nextNumber()]));
    }

    public function store(Request $request)
    {
        $quotation = DB::transaction(function () use ($request) {
            $quotation = new Quotation(['quotation_no' => Quotation::nextNumber(), 'status' => 'pending']);

            return $this->save($request, $quotation);
        });

        return redirect()->route('dashboard')->with('success', "Quotation {$quotation->quotation_no} saved.");
    }

    public function show(Quotation $quotation)
    {
        $quotation->load('customer', 'lines', 'jobOrder');

        return view('quotations.show', compact('quotation'));
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

    private function form(Quotation $quotation)
    {
        return view('quotations.form', [
            'quotation' => $quotation,
            'customers' => Customer::orderBy('name')->get(),
            'tariffs' => Tariff::where('active', true)->orWhereIn('id', $quotation->lines->pluck('tariff_id')->filter())
                ->orderBy('code')->get(['id', 'type', 'code', 'description', 'uom', 'unit', 'cost', 'price']),
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
