<?php

namespace App\Http\Controllers;

use App\Models\Tariff;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class TariffController extends Controller
{
    public function index(Request $request)
    {
        $type = array_key_exists($request->type, Tariff::TYPES) ? $request->type : 'service';

        $tariffs = Tariff::where('type', $type)
            ->when($request->q, fn ($q, $s) => $q->where(fn ($w) => $w->where('code', 'like', "%{$s}%")->orWhere('description', 'like', "%{$s}%")))
            ->orderBy('code')
            ->paginate(20)
            ->withQueryString()
            ->through(fn ($t) => [
                'id' => $t->id,
                'code' => $t->code,
                'description' => $t->description,
                'uom' => $t->uom,
                'unit' => $t->unit,
                'cost' => (float) $t->cost,
                'price' => (float) $t->price,
                'margin' => $t->margin(),
                'active' => $t->active,
            ]);

        $counts = Tariff::selectRaw('type, count(*) as n')->groupBy('type')->pluck('n', 'type');

        return Inertia::render('Tariffs/Index', [
            'tariffs' => $tariffs,
            'type' => $type,
            'types' => Tariff::TYPES,
            'counts' => ['service' => $counts['service'] ?? 0, 'item' => $counts['item'] ?? 0],
            'filters' => ['q' => $request->q ?? ''],
        ]);
    }

    public function create(Request $request)
    {
        return Inertia::render('Tariffs/Form', [
            'tariff' => null,
            'type' => array_key_exists($request->type, Tariff::TYPES) ? $request->type : 'service',
        ]);
    }

    public function store(Request $request)
    {
        $tariff = Tariff::create($this->validated($request));

        return redirect()->route('tariffs.index', ['type' => $tariff->type])->with('success', 'Tariff added.');
    }

    public function edit(Tariff $tariff)
    {
        return Inertia::render('Tariffs/Form', [
            'tariff' => [
                'id' => $tariff->id,
                'type' => $tariff->type,
                'code' => $tariff->code,
                'description' => $tariff->description,
                'uom' => $tariff->uom,
                'unit' => (string) $tariff->unit,
                'cost' => number_format($tariff->cost, 2, '.', ''),
                'price' => number_format($tariff->price, 2, '.', ''),
                'active' => $tariff->active,
            ],
            'type' => $tariff->type,
        ]);
    }

    public function update(Request $request, Tariff $tariff)
    {
        $tariff->update($this->validated($request, $tariff));

        return redirect()->route('tariffs.index', ['type' => $tariff->type])->with('success', 'Tariff updated.');
    }

    public function destroy(Tariff $tariff)
    {
        // quotation lines keep their own copy of code/description/price, so deleting is safe
        $tariff->delete();

        return back()->with('success', 'Tariff deleted.');
    }

    private function validated(Request $request, ?Tariff $tariff = null): array
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(Tariff::TYPES))],
            'code' => ['required', 'string', 'max:50', Rule::unique('tariffs')->ignore($tariff)],
            'description' => 'required|string|max:255',
            'uom' => 'required|string|max:20',
            'unit' => 'nullable|string|max:50',
            'cost' => 'required|numeric|min:0',
            'price' => 'required|numeric|min:0',
        ]);

        $data['active'] = $request->boolean('active');

        return $data;
    }
}
