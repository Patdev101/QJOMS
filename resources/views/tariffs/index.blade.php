@extends('layout')
@section('title', App\Models\Tariff::TYPES[$type].' tariff')
@section('nav', 'tariff-'.$type)

@php
    $label = App\Models\Tariff::TYPES[$type];
    $plural = $type === 'service' ? 'services' : 'items';
@endphp

@section('content')
<div class="page-head">
    <div>
        <h1>{{ $label }} tariff</h1>
        <p class="sub">Standard {{ $plural }} with cost and selling price. The price fills in automatically on quotations.</p>
    </div>
    <a href="{{ route('tariffs.create', ['type' => $type]) }}" class="btn btn-primary"><x-icon name="plus" /> Add {{ strtolower($label) }}</a>
</div>

<div class="card">
    <form class="toolbar" method="GET">
        <div class="segmented">
            <a href="{{ route('tariffs.index', ['type' => 'service']) }}" @class(['active' => $type === 'service'])>
                <x-icon name="wrench" size="16" /> Services <span class="n">{{ $counts['service'] ?? 0 }}</span>
            </a>
            <a href="{{ route('tariffs.index', ['type' => 'item']) }}" @class(['active' => $type === 'item'])>
                <x-icon name="package" size="16" /> Items <span class="n">{{ $counts['item'] ?? 0 }}</span>
            </a>
        </div>
        <input type="hidden" name="type" value="{{ $type }}">
        <label class="search">
            <x-icon name="search" size="17" />
            <span class="sr-only">Search</span>
            <input type="search" name="q" value="{{ request('q') }}" placeholder="Search code or description">
        </label>
    </form>

    @if ($tariffs->isEmpty())
        <div class="empty">
            <div class="empty-icon"><x-icon :name="$type === 'service' ? 'wrench' : 'package'" size="26" /></div>
            @if (request('q'))
                <h3>No {{ $plural }} found</h3>
                <p>Nothing matches "{{ request('q') }}".</p>
                <a href="{{ route('tariffs.index', ['type' => $type]) }}" class="btn">Clear search</a>
            @else
                <h3>No {{ $plural }} yet</h3>
                <p>Add your standard {{ $plural }} so they can be picked on quotations.</p>
                <a href="{{ route('tariffs.create', ['type' => $type]) }}" class="btn btn-primary"><x-icon name="plus" /> Add {{ strtolower($label) }}</a>
            @endif
        </div>
    @else
        <div class="table-scroll">
            <table class="table">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Description</th>
                        <th>UOM</th>
                        <th class="num">Cost</th>
                        <th class="num">Price</th>
                        <th class="num">Margin</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($tariffs as $t)
                        @php $margin = $t->margin(); @endphp
                        <tr data-href="{{ route('tariffs.edit', $t) }}" @class(['is-inactive' => ! $t->active])>
                            <td><span class="code">{{ $t->code }}</span></td>
                            <td>
                                <div class="cell-main">{{ $t->description }}</div>
                                @if (! $t->active)
                                    <span class="pill inactive">Inactive</span>
                                @elseif ($t->unit)
                                    <div class="cell-sub">per {{ strtolower($t->unit) }}</div>
                                @endif
                            </td>
                            <td>{{ $t->uom }}</td>
                            <td class="num muted">{{ number_format($t->cost, 2) }}</td>
                            <td class="num cell-main">₱{{ number_format($t->price, 2) }}</td>
                            <td class="num">
                                @if ($margin === null)
                                    <span class="muted">—</span>
                                @else
                                    <span class="{{ $margin >= 30 ? 'margin-good' : ($margin >= 10 ? 'margin-ok' : 'margin-low') }}">{{ number_format($margin, 1) }}%</span>
                                @endif
                            </td>
                            <td>
                                <div class="row-actions">
                                    <a href="{{ route('tariffs.edit', $t) }}" class="btn btn-ghost btn-icon" title="Edit" aria-label="Edit {{ $t->code }}"><x-icon name="pencil" size="16" /></a>
                                    <form method="POST" action="{{ route('tariffs.destroy', $t) }}"
                                          data-confirm="{{ $t->code }} — {{ $t->description }} will be deleted. Quotations that already use it keep their copy." data-confirm-title="Delete {{ strtolower($label) }}?"
                                          data-confirm-ok="Delete" data-confirm-tone="danger">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-ghost btn-icon btn-danger" title="Delete" aria-label="Delete {{ $t->code }}"><x-icon name="trash" size="16" /></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @include('partials.pager', ['paginator' => $tariffs])
    @endif
</div>
@endsection
