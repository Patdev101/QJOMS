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
        $type = $this->typeFrom($request);

        // grouped like the supplier's sheet: category › sub-category › code
        $tariffs = Tariff::where('type', $type)
            ->when($request->section, fn ($q, $s) => $s === '__none__' ? $q->whereNull('section') : $q->where('section', $s))
            ->when($request->category, fn ($q, $c) => $q->where('category', $c))
            ->when($request->q, fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('code', 'like', "%{$s}%")
                ->orWhere('description', 'like', "%{$s}%")
                ->orWhere('category', 'like', "%{$s}%")
                ->orWhere('subcategory', 'like', "%{$s}%")))
            // main categories (sheet sections) first, in the sheet's order; tariffs without one last
            ->orderByRaw('case when section is null then 1 else 0 end')
            ->orderBy('section') // "A. …" to "G. …", then "II. Hull, Hold & Tank Preservation"
            ->orderByRaw('case when category is null then 1 else 0 end')
            // categories in the supplier's sheet order (by their first code), not A–Z
            ->orderByRaw('(select min(t2.code) from tariffs t2 where t2.type = tariffs.type and t2.category = tariffs.category)')
            ->orderBy('code') // codes follow the supplier's sheet, so items keep the sheet's order
            ->paginate(500) // shown as a folder tree, so a whole category stays on one page
            ->withQueryString()
            ->through(fn ($t) => [
                'id' => $t->id,
                'section' => $t->section,
                'category' => $t->category,
                'subcategory' => $t->subcategory,
                'code' => $t->code,
                'description' => $t->description,
                'uom' => Tariff::unitLabel($t->uom, $t->uom2),
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
            // the filter lists main categories (A. DRY DOCKING AND UNDOCKING …) in the sheet's order
            'sections' => Tariff::where('type', $type)->whereNotNull('section')->distinct()->orderBy('section')->pluck('section'),
            'filters' => ['q' => $request->q ?? '', 'section' => $request->section ?? '', 'category' => $request->category ?? ''],
        ]);
    }

    public function create(Request $request)
    {
        return Inertia::render('Tariffs/Form', [
            'tariff' => null,
            'type' => $this->typeFrom($request),
            // from the "＋" on a category / sub-category row
            'preset' => ['category' => (string) $request->category, 'subcategory' => (string) $request->subcategory],
            'groups' => $this->groups(),
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
                'category' => (string) $tariff->category,
                'subcategory' => (string) $tariff->subcategory,
                'code' => $tariff->code,
                'description' => $tariff->description,
                'uom' => $tariff->uom,
                'uom2' => (string) $tariff->uom2,
                'unit' => (string) $tariff->unit,
                'cost' => number_format($tariff->cost, 2, '.', ''),
                'price' => number_format($tariff->price, 2, '.', ''),
                'active' => $tariff->active,
            ],
            'type' => $tariff->type,
            'groups' => $this->groups(),
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

    /** ?type=service or ?type=item; anything else (missing, misspelled, or a list) means service */
    private function typeFrom(Request $request): string
    {
        $type = $request->query('type');

        return is_string($type) && array_key_exists($type, Tariff::TYPES) ? $type : 'service';
    }

    /** categories already in use, each with its sub-categories — suggestions on the form */
    private function groups(): array
    {
        return Tariff::whereNotNull('category')->get(['category', 'subcategory'])
            ->groupBy('category')
            ->map(fn ($rows) => $rows->pluck('subcategory')->filter()->unique()->sort()->values())
            ->sortKeys()
            ->all();
    }

    private function validated(Request $request, ?Tariff $tariff = null): array
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(Tariff::TYPES))],
            'category' => 'nullable|string|max:255',
            'subcategory' => 'nullable|string|max:500',
            'code' => ['required', 'string', 'max:50', Rule::unique('tariffs')->ignore($tariff)],
            'description' => 'required|string|max:500',
            'uom' => 'nullable|string|max:20', // some shipyard tariff lines have no unit in the sheet
            'uom2' => 'nullable|string|max:20',
            'unit' => 'nullable|string|max:50',
            // the largest amount the database column holds (12 digits, 2 after the point)
            'cost' => 'required|numeric|min:0|max:9999999999.99',
            'price' => 'required|numeric|min:0|max:9999999999.99',
        ]);

        $data['uom'] ??= '';
        $data['active'] = $request->boolean('active');

        return $data;
    }
}
