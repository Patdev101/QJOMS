@extends('layout')
@section('title', 'Customer profiles')
@section('nav', 'customers')

@section('content')
<div class="page-head">
    <div>
        <h1>Customer profiles</h1>
        <p class="sub">A customer's discount is filled in automatically on their quotations.</p>
    </div>
    <a href="{{ route('customers.create') }}" class="btn btn-primary"><x-icon name="plus" /> Add customer</a>
</div>

<div class="card">
    <form class="toolbar" method="GET">
        <label class="search">
            <x-icon name="search" size="17" />
            <span class="sr-only">Search</span>
            <input type="search" name="q" value="{{ request('q') }}" placeholder="Search name or contact person">
        </label>
        <span class="muted">{{ $customers->total() }} {{ Str::plural('customer', $customers->total()) }}</span>
    </form>

    @if ($customers->isEmpty())
        <div class="empty">
            <div class="empty-icon"><x-icon name="users" size="26" /></div>
            @if (request('q'))
                <h3>No customers found</h3>
                <p>Nobody matches "{{ request('q') }}".</p>
                <a href="{{ route('customers.index') }}" class="btn">Clear search</a>
            @else
                <h3>No customers yet</h3>
                <p>Add your customers once, then just pick them on every quotation.</p>
                <a href="{{ route('customers.create') }}" class="btn btn-primary"><x-icon name="plus" /> Add customer</a>
            @endif
        </div>
    @else
        <div class="table-scroll">
            <table class="table">
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Contact</th>
                        <th>Discount</th>
                        <th class="num">Quotations</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($customers as $c)
                        <tr data-href="{{ route('customers.edit', $c) }}">
                            <td>
                                <div class="who">
                                    <span class="avatar" style="background: {{ $c->avatarColor() }}">{{ $c->initials() }}</span>
                                    <div>
                                        <div class="cell-main">{{ $c->name }}</div>
                                        <div class="cell-sub">{{ $c->address ?: 'No address' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                {{ $c->contact_person ?: '—' }}
                                <div class="cell-sub">{{ $c->contact_no }}</div>
                            </td>
                            <td>
                                @if ($c->discount_percent > 0)
                                    <span class="tag discount"><x-icon name="tag" size="13" /> {{ (float) $c->discount_percent }}% off</span>
                                @else
                                    <span class="tag none">No discount</span>
                                @endif
                            </td>
                            <td class="num">{{ $c->quotations_count }}</td>
                            <td>
                                <div class="row-actions">
                                    <a href="{{ route('customers.edit', $c) }}" class="btn btn-ghost btn-icon" title="Edit" aria-label="Edit {{ $c->name }}"><x-icon name="pencil" size="16" /></a>
                                    @if ($c->quotations_count)
                                        <button type="button" class="btn btn-ghost btn-icon" disabled title="Has quotations, so it can't be deleted"><x-icon name="trash" size="16" /></button>
                                    @else
                                        <form method="POST" action="{{ route('customers.destroy', $c) }}"
                                              data-confirm="{{ $c->name }} will be removed from your customer list." data-confirm-title="Delete customer?"
                                              data-confirm-ok="Delete" data-confirm-tone="danger">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-ghost btn-icon btn-danger" title="Delete" aria-label="Delete {{ $c->name }}"><x-icon name="trash" size="16" /></button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @include('partials.pager', ['paginator' => $customers])
    @endif
</div>
@endsection
