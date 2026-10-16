@extends('layout')
@section('title', 'Job orders')
@section('nav', 'job-orders')

@php
    $chips = ['' => ['All', $total], 'active' => ['In progress', $counts['active']], 'completed' => ['Ready for billing', $counts['completed']], 'billed' => ['Billed', $counts['billed']]];
@endphp

@section('content')
<div class="page-head">
    <div>
        <h1>Job orders</h1>
        <p class="sub">Every approved quotation becomes a job order. Track the work here until it's billed.</p>
    </div>
</div>

<div class="card">
    <form class="toolbar" method="GET">
        <div class="chips">
            @foreach ($chips as $key => [$label, $n])
                <a href="{{ route('job-orders.index', array_filter(['status' => $key, 'q' => request('q')])) }}"
                   @class(['chip', 'active' => request('status', '') === $key])>{{ $label }} <span class="n">{{ $n }}</span></a>
            @endforeach
        </div>
        @if (request('status'))
            <input type="hidden" name="status" value="{{ request('status') }}">
        @endif
        <label class="search">
            <x-icon name="search" size="17" />
            <span class="sr-only">Search</span>
            <input type="search" name="q" value="{{ request('q') }}" placeholder="Search JO no., customer, project">
        </label>
    </form>

    @if ($jobOrders->isEmpty())
        <div class="empty">
            <div class="empty-icon"><x-icon name="clipboard" size="26" /></div>
            @if (request('q') || request('status'))
                <h3>No job orders found</h3>
                <p>Try another search or filter.</p>
                <a href="{{ route('job-orders.index') }}" class="btn">Clear filters</a>
            @else
                <h3>No job orders yet</h3>
                <p>Approve a quotation once the customer agrees, and it will appear here.</p>
                <a href="{{ route('quotations.index', ['status' => 'pending']) }}" class="btn">See quotations for approval</a>
            @endif
        </div>
    @else
        <div class="table-scroll">
            <table class="table">
                <thead>
                    <tr>
                        <th>Job order</th>
                        <th>Customer &amp; project</th>
                        <th>Quotation</th>
                        <th>Date</th>
                        <th class="num">Amount</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($jobOrders as $jo)
                        <tr data-href="{{ route('job-orders.show', $jo) }}">
                            <td><span class="code">{{ $jo->jo_no }}</span></td>
                            <td>
                                <div class="cell-main">{{ $jo->quotation->customer->name }}</div>
                                <div class="cell-sub">{{ $jo->quotation->project ?: 'No project name' }}</div>
                            </td>
                            <td><a href="{{ route('quotations.show', $jo->quotation) }}" class="link">{{ $jo->quotation->quotation_no }}</a></td>
                            <td>{{ $jo->date->format('M j, Y') }}</td>
                            <td class="num cell-main">₱{{ number_format($jo->quotation->grand_total, 2) }}</td>
                            <td><span class="pill {{ $jo->status }}">{{ App\Models\JobOrder::STATUSES[$jo->status] }}</span></td>
                            <td class="row-go"><x-icon name="chevron-right" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @include('partials.pager', ['paginator' => $jobOrders])
    @endif
</div>
@endsection
