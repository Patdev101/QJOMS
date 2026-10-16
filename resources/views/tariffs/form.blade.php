@extends('layout')
@section('title', $tariff->exists ? 'Edit '.$tariff->code : 'Add tariff')
@section('nav', 'tariff-'.old('type', $tariff->type))

@section('content')
<div class="page-head">
    <div>
        <div class="eyebrow">
            <a href="{{ route('tariffs.index', ['type' => $tariff->type]) }}"><x-icon name="chevron-left" size="14" /> {{ App\Models\Tariff::TYPES[$tariff->type] }} tariff</a>
        </div>
        <h1>{{ $tariff->exists ? 'Edit '.$tariff->code : 'Add tariff' }}</h1>
    </div>
</div>

<form method="POST" action="{{ $tariff->exists ? route('tariffs.update', $tariff) : route('tariffs.store') }}" class="card form-page">
    @csrf
    @if ($tariff->exists) @method('PUT') @endif

    <div class="form-section">
        <div>
            <h3>What is it?</h3>
            <p>Services are work you do. Items are materials or parts you supply.</p>
        </div>
        <div>
            <span class="label">Type</span>
            <div class="segmented">
                <label><input type="radio" name="type" value="service" @checked(old('type', $tariff->type) === 'service')><x-icon name="wrench" size="16" /> Service</label>
                <label><input type="radio" name="type" value="item" @checked(old('type', $tariff->type) === 'item')><x-icon name="package" size="16" /> Item</label>
            </div>

            <div class="grid-2 field-gap">
                <div class="field">
                    <label for="code">Code</label>
                    <input id="code" name="code" value="{{ old('code', $tariff->code) }}" placeholder="e.g. SVC-004" required autofocus @class(['mono', 'is-invalid' => $errors->has('code')])>
                </div>
                <div class="field">
                    <label for="uom">UOM</label>
                    <input id="uom" name="uom" value="{{ old('uom', $tariff->uom) }}" placeholder="hr, pc, sqm, lot" required @class(['is-invalid' => $errors->has('uom')])>
                </div>
                <div class="field span-2">
                    <label for="description">Description</label>
                    <input id="description" name="description" value="{{ old('description', $tariff->description) }}" required @class(['is-invalid' => $errors->has('description')])>
                </div>
                <div class="field span-2">
                    <label for="unit">Unit name</label>
                    <input id="unit" name="unit" value="{{ old('unit', $tariff->unit) }}" placeholder="e.g. Hour, Piece, Square meter" @class(['is-invalid' => $errors->has('unit')])>
                    <span class="hint">The full name of the unit of measure.</span>
                </div>
            </div>
        </div>
    </div>

    <div class="form-section">
        <div>
            <h3>Pricing</h3>
            <p>Cost is for staff only. It never appears on a printed quotation.</p>
        </div>
        <div>
            <div class="grid-2">
                <div class="field">
                    <label for="cost">Cost</label>
                    <span class="affix has-pre">
                        <span class="pre">₱</span>
                        <input id="cost" type="number" name="cost" min="0" step="0.01" value="{{ old('cost', $tariff->cost ?? 0) }}" required @class(['is-invalid' => $errors->has('cost')])>
                    </span>
                </div>
                <div class="field">
                    <label for="price">Selling price</label>
                    <span class="affix has-pre">
                        <span class="pre">₱</span>
                        <input id="price" type="number" name="price" min="0" step="0.01" value="{{ old('price', $tariff->price ?? 0) }}" required @class(['is-invalid' => $errors->has('price')])>
                    </span>
                </div>
            </div>
            <div class="margin-preview">
                <span>Profit per unit <b id="mpProfit">₱0.00</b></span>
                <span>Margin <b id="mpMargin">—</b></span>
            </div>
        </div>
    </div>

    <div class="form-section">
        <div>
            <h3>Availability</h3>
            <p>Inactive ones stay on old quotations but can't be picked on new ones.</p>
        </div>
        <div>
            <label class="switch">
                <input type="checkbox" name="active" value="1" @checked($errors->any() ? old('active') : $tariff->active)>
                Active: can be used on new quotations
            </label>
        </div>
    </div>

    <div class="form-foot">
        <a href="{{ route('tariffs.index', ['type' => $tariff->type]) }}" class="btn btn-ghost">Cancel</a>
        <button type="submit" class="btn btn-primary"><x-icon name="check" /> {{ $tariff->exists ? 'Save changes' : 'Add to tariff' }}</button>
    </div>
</form>
@endsection

@push('scripts')
<script>
    // live profit and margin while typing the cost and price
    (function () {
        const cost = document.getElementById('cost');
        const price = document.getElementById('price');
        const money = n => '₱' + n.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

        function update() {
            const c = Number(cost.value) || 0;
            const p = Number(price.value) || 0;
            const profit = document.getElementById('mpProfit');
            const margin = document.getElementById('mpMargin');

            profit.textContent = money(p - c);
            profit.classList.toggle('neg', p - c < 0);
            margin.textContent = p > 0 ? ((p - c) / p * 100).toFixed(1) + '%' : '—';
            margin.className = p <= 0 ? '' : ((p - c) / p >= .3 ? 'margin-good' : ((p - c) / p >= .1 ? 'margin-ok' : 'margin-low'));
        }

        cost.addEventListener('input', update);
        price.addEventListener('input', update);
        update();
    })();
</script>
@endpush
