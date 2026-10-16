@extends('layout')
@section('title', 'Dashboard')
@section('nav', 'dashboard')

@section('content')
<div class="page-head">
    <div>
        <div class="eyebrow">{{ now()->format('l, F j, Y') }}</div>
        <h1>{{ $greeting }}!</h1>
        <p class="sub">Here's what needs your attention today.</p>
    </div>
</div>

<div class="stats">
    <div class="card stat">
        <span class="stat-icon icon-amber"><x-icon name="clock" size="20" /></span>
        <div class="stat-label">Waiting for approval</div>
        <div class="stat-value">{{ $stats['pending_count'] }}</div>
        <div class="stat-foot">₱{{ number_format($stats['pending_value'], 2) }} quoted</div>
    </div>
    <div class="card stat">
        <span class="stat-icon icon-pine"><x-icon name="clipboard" size="20" /></span>
        <div class="stat-label">Job orders in progress</div>
        <div class="stat-value">{{ $stats['active_jobs'] }}</div>
        <div class="stat-foot">Open and ongoing work</div>
    </div>
    <div class="card stat">
        <span class="stat-icon icon-clay"><x-icon name="receipt" size="20" /></span>
        <div class="stat-label">Ready for billing</div>
        <div class="stat-value">{{ $stats['to_bill_count'] }}</div>
        <div class="stat-foot">₱{{ number_format($stats['to_bill_value'], 2) }} to bill</div>
    </div>
    <div class="card stat">
        <span class="stat-icon icon-ink"><x-icon name="trending" size="20" /></span>
        <div class="stat-label">Approved in {{ now()->format('F') }}</div>
        <div class="stat-value">₱{{ number_format($stats['month_value']) }}</div>
        <div class="stat-foot">{{ $stats['month_count'] }} {{ Str::plural('quotation', $stats['month_count']) }} approved</div>
    </div>
</div>

<div class="dash-grid">
    <section class="card">
        <div class="card-head">
            <h2><x-icon name="clock" /> Waiting for customer approval</h2>
        </div>
        @forelse ($waiting as $q)
            <div class="list-row">
                <div>
                    <div class="cell-main">{{ $q->customer->name }}</div>
                    <div class="cell-sub"><span class="code">{{ $q->quotation_no }}</span> {{ $q->project }}</div>
                </div>
                <div class="text-right">
                    <div class="cell-main num">₱{{ number_format($q->grand_total, 2) }}</div>
                    <div class="cell-sub">sent {{ $q->created_at->diffForHumans() }}</div>
                </div>
            </div>
        @empty
            <div class="empty compact">
                <div class="empty-icon"><x-icon name="check-circle" size="24" /></div>
                <h3>All caught up</h3>
                <p>No quotations are waiting for the customer.</p>
            </div>
        @endforelse
    </section>

    <section class="card">
        <div class="card-head">
            <h2><x-icon name="clipboard" /> Job orders to work on</h2>
        </div>
        @forelse ($jobs as $jo)
            <div class="list-row">
                <div>
                    <div class="cell-main">{{ $jo->quotation->customer->name }}</div>
                    <div class="cell-sub"><span class="code">{{ $jo->jo_no }}</span> {{ $jo->quotation->project }}</div>
                </div>
                <span class="pill {{ $jo->status }}">{{ App\Models\JobOrder::STATUSES[$jo->status] }}</span>
            </div>
        @empty
            <div class="empty compact">
                <div class="empty-icon"><x-icon name="clipboard" size="24" /></div>
                <h3>No active job orders</h3>
                <p>Approved quotations show up here as job orders.</p>
            </div>
        @endforelse
    </section>
</div>
@endsection
