@extends('layout')
@section('title', 'Quotations')
@section('nav', 'quotations')

@php
    $chips = ['' => ['All', $counts->sum()], 'pending' => ['For approval', $counts['pending'] ?? 0], 'approved' => ['Approved', $counts['approved'] ?? 0]];
@endphp

@section('content')
<div class="page-head">
    <div>
        <h1>Quotations</h1>
        <p class="sub">Revise a quotation until the customer agrees, then approve it to create a job order.</p>
    </div>
    <a href="{{ route('quotations.create') }}" class="btn btn-primary"><x-icon name="plus" /> New quotation</a>
</div>

<div class="card">
    <form class="toolbar" method="GET">
        <div class="chips">
            @foreach ($chips as $key => [$label, $n])
                <a href="{{ route('quotations.index', array_filter(['status' => $key, 'q' => request('q')])) }}"
                   @class(['chip', 'active' => request('status', '') === $key])>{{ $label }} <span class="n">{{ $n }}</span></a>
            @endforeach
        </div>
        @if (request('status'))
            <input type="hidden" name="status" value="{{ request('status') }}">
        @endif
        <label class="search">
            <x-icon name="search" size="17" />
            <span class="sr-only">Search</span>
            <input type="search" name="q" value="{{ request('q') }}" placeholder="Search no., customer or project">
        </label>
    </form>

    @if ($quotations->isEmpty())
        <div class="empty">
            <div class="empty-icon"><x-icon name="file-text" size="26" /></div>
            @if (request('q') || request('status'))
                <h3>No quotations found</h3>
                <p>Try another search or filter.</p>
                <a href="{{ route('quotations.index') }}" class="btn">Clear filters</a>
            @else
                <h3>No quotations yet</h3>
                <p>Create your first quotation from the customer's list of services and items.</p>
                <a href="{{ route('quotations.create') }}" class="btn btn-primary"><x-icon name="plus" /> New quotation</a>
            @endif
        </div>
    @else
        <div class="table-scroll">
            <table class="table">
                <thead>
                    <tr>
                        <th>Quotation</th>
                        <th>Customer &amp; project</th>
                        <th>Date</th>
                        <th class="num">Grand total</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($quotations as $q)
                        <tr data-href="{{ route('quotations.show', $q) }}">
                            <td><span class="code">{{ $q->quotation_no }}</span></td>
                            <td>
                                <div class="cell-main">{{ $q->customer->name }}</div>
                                <div class="cell-sub">{{ $q->project ?: 'No project name' }}</div>
                            </td>
                            <td>
                                {{ $q->date->format('M j, Y') }}
                                <div class="cell-sub">{{ $q->created_at->diffForHumans() }}</div>
                            </td>
                            <td class="num cell-main">₱{{ number_format($q->grand_total, 2) }}</td>
                            <td>
                                @if ($q->jobOrder)
                                    <span class="pill approved">Approved</span>
                                    <div class="cell-sub">→ <a href="{{ route('job-orders.show', $q->jobOrder) }}" class="link">{{ $q->jobOrder->jo_no }}</a></div>
                                @else
                                    <span class="pill pending">For approval</span>
                                @endif
                            </td>
                            <td class="row-go"><x-icon name="chevron-right" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @include('partials.pager', ['paginator' => $quotations])
    @endif
</div>
@endsection
