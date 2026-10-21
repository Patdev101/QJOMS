<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Customer;
use App\Models\JobOrder;
use App\Models\Quotation;
use App\Models\Tariff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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

        Activity::log($quotation, 'created', "Quotation {$quotation->quotation_no} created for ₱".number_format($quotation->grand_total, 2));

        return redirect()->route('quotations.show', $quotation)->with('success', "Quotation {$quotation->quotation_no} saved.");
    }

    public function show(Quotation $quotation)
    {
        return Inertia::render('Quotations/Show', [
            'quotation' => $quotation->details(),
            'history' => Activity::timeline($quotation->id),
        ]);
    }

    public function print(Quotation $quotation)
    {
        $quotation->load('customer', 'lines.tariff', 'jobOrder');

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

        $before = (float) $quotation->grand_total;
        DB::transaction(fn () => $this->save($request, $quotation));
        Activity::log($quotation, 'revised', 'Quotation revised'.($before != $quotation->grand_total
            ? ': total ₱'.number_format($before, 2).' → ₱'.number_format($quotation->grand_total, 2)
            : ''));

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

        Activity::log($quotation, 'approved', "Approved by the customer; job order {$jobOrder->jo_no} created");

        return redirect()->route('job-orders.show', $jobOrder)->with('success', "Quotation approved. Job Order {$jobOrder->jo_no} created.");
    }

    public function destroy(Quotation $quotation)
    {
        if (! $quotation->isEditable()) {
            return back()->with('error', 'An approved quotation cannot be deleted.');
        }

        Activity::log($quotation, 'deleted', "Quotation {$quotation->quotation_no} deleted");
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
                'vessel_name' => (string) $quotation->vessel_name,
                ...collect(['loa', 'lbp', 'breadth', 'depth'])->mapWithKeys(fn ($f) => [$f => $quotation->{$f} === null ? '' : (string) (float) $quotation->{$f}])->all(),
                ...collect(['date_arrival', 'date_drydocked', 'date_undocked', 'date_sail_out'])->mapWithKeys(fn ($f) => [$f => $quotation->{$f}?->format('Y-m-d') ?? ''])->all(),
                'discount_percent' => (string) (float) $quotation->discount_percent,
                'lines' => $quotation->lines->map(fn ($l) => [
                    'type' => $l->type,
                    'tariff_id' => $l->tariff_id,
                    'qty' => (string) (float) $l->qty,
                    'qty2' => $l->qty2 === null ? '' : (string) (float) $l->qty2,
                    'note' => (string) $l->note,
                    // a manual line (no tariff) keeps what was typed
                    'description' => $l->tariff_id ? '' : $l->description,
                    'uom' => $l->tariff_id ? '' : $l->uom,
                    'uom2' => $l->tariff_id ? '' : (string) $l->uom2,
                    'unit_price' => number_format($l->unit_price, 2, '.', ''),
                ]),
            ] : null,
            'customers' => Customer::orderBy('name')->get(['id', 'name', 'address', 'contact_person', 'contact_no', 'discount_percent'])
                ->map(fn ($c) => [...$c->toArray(), 'discount_percent' => (float) $c->discount_percent]),
            // inactive tariffs still count when an older quotation already uses them
            'tariffs' => Tariff::where('active', true)->orWhereIn('id', $quotation->lines->pluck('tariff_id')->filter())
                ->orderBy('code')->get(['id', 'type', 'category', 'subcategory', 'code', 'description', 'uom', 'uom2', 'unit', 'cost', 'price'])
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
            'vessel_name' => 'nullable|string|max:255',
            'loa' => 'nullable|numeric|min:0|max:999999',
            'lbp' => 'nullable|numeric|min:0|max:999999',
            'breadth' => 'nullable|numeric|min:0|max:999999',
            'depth' => 'nullable|numeric|min:0|max:999999',
            'date_arrival' => 'nullable|date',
            'date_drydocked' => 'nullable|date',
            'date_undocked' => 'nullable|date',
            'date_sail_out' => 'nullable|date',
            'discount_percent' => 'required|numeric|min:0|max:100',
            'lines' => 'required|array|min:1',
            'lines.*.type' => ['required', Rule::in(array_keys(Tariff::TYPES))],
            // a line is picked from the tariff, or typed by hand (no tariff: description, UOM and price as typed)
            'lines.*.tariff_id' => 'nullable|exists:tariffs,id',
            'lines.*.description' => 'nullable|string|max:500',
            'lines.*.uom' => 'nullable|string|max:20',
            'lines.*.uom2' => 'nullable|string|max:20',
            'lines.*.qty' => 'required|numeric|gt:0|max:9999999999.99',
            'lines.*.qty2' => 'nullable|numeric|gt:0|max:9999999999.99',
            'lines.*.unit_price' => 'required|numeric|min:0|max:9999999999.99',
            'lines.*.note' => 'nullable|string|max:500',
        ], [
            'lines.required' => 'Add at least one item or service.',
            'lines.*.unit_price.required' => 'Type a unit price on every line.',
            'lines.*.qty.gt' => 'Quantity must be more than zero.',
            'lines.*.qty2.gt' => 'The second quantity must be more than zero.',
        ]);

        $tariffs = Tariff::whereIn('id', collect($data['lines'])->pluck('tariff_id')->filter())->get()->keyBy('id');

        // a manual line (no tariff) needs its own description
        $unnamed = collect($data['lines'])->search(fn ($l) => empty($l['tariff_id']) && trim((string) ($l['description'] ?? '')) === '');
        if ($unnamed !== false) {
            throw ValidationException::withMessages(["lines.{$unnamed}.description" => 'Choose a service on every line, or type a description for a manual line.']);
        }
        // …and a 2nd quantity on a manual line needs the name of that unit ("Day")
        $noUnit = collect($data['lines'])->search(fn ($l) => empty($l['tariff_id']) && ($l['qty2'] ?? null) !== null
            && trim((string) ($l['uom2'] ?? '')) === '' && ! str_contains((string) ($l['uom'] ?? ''), '/'));
        if ($noUnit !== false) {
            throw ValidationException::withMessages(["lines.{$noUnit}.uom2" => 'Type the second unit (for example Day) for the second quantity.']);
        }

        // the server does the math; the numbers on screen are only a preview
        $lines = collect($data['lines'])->map(function ($line) use ($tariffs) {
            if (empty($line['tariff_id'])) {
                // typed by hand: description, unit(s), qty and price as entered; no cost is known.
                // "Pax / Day" typed in the UOM box is a unit in two parts, the same as a second unit
                $uom = trim((string) ($line['uom'] ?? ''));
                $uom2 = trim((string) ($line['uom2'] ?? ''));
                if ($uom2 === '' && str_contains($uom, '/') && strtoupper($uom) !== 'N/A') {
                    [$uom, $uom2] = array_map('trim', explode('/', $uom, 2));
                }
                $uom2 = $uom2 === '' ? null : $uom2;
                $qty2 = $uom2 ? (float) (($line['qty2'] ?? null) ?: (Tariff::hoursPerUnit($uom2) ?? 1)) : null;
                $units = Tariff::units($uom, (float) $line['qty']) * ($qty2 === null ? 1 : Tariff::units($uom2, $qty2));

                return [
                    'tariff_id' => null,
                    'type' => $line['type'],
                    'section' => null, // summarized as "Other works"
                    'code' => '',
                    'description' => trim((string) $line['description']),
                    'note' => $line['note'] ?? null,
                    'uom' => $uom,
                    'uom2' => $uom2,
                    'qty' => $line['qty'],
                    'qty2' => $qty2,
                    'unit_cost' => 0,
                    'unit_price' => $line['unit_price'],
                    'total' => round($units * $line['unit_price'], 2),
                    'line_cost' => 0,
                ];
            }

            $tariff = $tariffs[$line['tariff_id']];
            // a two-part unit ("Pax / Day") multiplies both quantities; an empty second quantity is one unit
            // (one block of hours, e.g. 8, for "Unit / 8Hrs")
            $qty2 = $tariff->uom2 ? (float) (($line['qty2'] ?? null) ?: (Tariff::hoursPerUnit($tariff->uom2) ?? 1)) : null;
            // hour blocks ("8 Hrs") are typed in hours: 16 hours = 2 blocks of the tariff price
            $units = Tariff::units($tariff->uom, (float) $line['qty']) * ($qty2 === null ? 1 : Tariff::units($tariff->uom2, $qty2));

            return [
                'tariff_id' => $tariff->id,
                'type' => $tariff->type,
                'section' => $tariff->section ?? $tariff->category, // NSRMS tariffs have no sheet section: their category
                'code' => $tariff->code,
                'description' => $tariff->description,
                'note' => $line['note'] ?? null,
                'uom' => $tariff->uom,
                'uom2' => $tariff->uom2,
                'qty' => $line['qty'],
                'qty2' => $qty2,
                'unit_cost' => $tariff->cost,
                'unit_price' => $line['unit_price'],
                'total' => round($units * $line['unit_price'], 2),
                'line_cost' => $units * (float) $tariff->cost, // staff-only total cost; not a column
            ];
        });

        // a total the database can't hold would fail with a server error: say so on the form instead
        $tooBig = $lines->search(fn ($l) => $l['total'] > 999999999999.99);
        if ($tooBig !== false || $lines->sum('total') > 999999999999.99) {
            throw ValidationException::withMessages(['lines.'.($tooBig === false ? 0 : $tooBig).'.qty' => 'This quotation total is too large. Check the quantities and prices.']);
        }

        $subtotal = $lines->sum('total');
        $discount = round($subtotal * $data['discount_percent'] / 100, 2);

        $quotation->fill(collect($data)->except('lines')->all() + [
            'subtotal' => $subtotal,
            'discount_amount' => $discount,
            'grand_total' => $subtotal - $discount,
            'total_cost' => round($lines->sum('line_cost'), 2),
        ])->save();

        $quotation->lines()->delete();
        $quotation->lines()->createMany($lines->map(fn ($l) => collect($l)->except('line_cost')->all())->all());

        return $quotation;
    }
}
