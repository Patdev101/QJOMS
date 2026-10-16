@extends('layout')
@section('title', $jobOrder->jo_no)
@section('nav', 'job-orders')

@php
    $quotation = $jobOrder->quotation;
    $statusKeys = array_keys(App\Models\JobOrder::STATUSES);
    $currentIndex = array_search($jobOrder->status, $statusKeys);
    $explain = [
        'open' => 'Not started yet',
        'ongoing' => 'Work in progress',
        'completed' => 'Done, ready for billing',
        'billed' => 'Billed to the customer',
    ];
@endphp

@section('content')
<div class="page-head">
    <div>
        <div class="eyebrow"><a href="{{ route('job-orders.index') }}"><x-icon name="chevron-left" size="14" /> Job orders</a></div>
        <h1>
            {{ $jobOrder->jo_no }}
            <span class="pill {{ $jobOrder->status }}">{{ App\Models\JobOrder::STATUSES[$jobOrder->status] }}</span>
        </h1>
        <p class="sub">
            {{ $quotation->customer->name }}@if ($quotation->project) · {{ $quotation->project }}@endif
            · from <a href="{{ route('quotations.show', $quotation) }}" class="link">{{ $quotation->quotation_no }}</a>,
            approved {{ $quotation->approved_at?->format('M j, Y') }}
        </p>
    </div>
    <div class="actions">
        <a href="{{ route('quotations.print', $quotation) }}" target="_blank" class="btn"><x-icon name="printer" /> Print job order</a>
    </div>
</div>

<div class="stack">
    <section class="card">
        <div class="card-head">
            <h2><x-icon name="clipboard" /> Job status</h2>
            <span class="muted" style="font-size: 13px">Click a step to update it</span>
        </div>
        <div class="track">
            @foreach (App\Models\JobOrder::STATUSES as $key => $label)
                @php $state = $loop->index < $currentIndex ? 'done' : ($key === $jobOrder->status ? 'current' : ''); @endphp
                <form method="POST" action="{{ route('job-orders.status', $jobOrder) }}"
                      @if ($key === 'billed')
                          data-confirm-title="Mark {{ $jobOrder->jo_no }} as billed?"
                          data-confirm="Do this once the customer has been billed for ₱{{ number_format($quotation->grand_total, 2) }}."
                          data-confirm-ok="Mark as billed"
                      @endif>
                    @csrf @method('PATCH')
                    <input type="hidden" name="status" value="{{ $key }}">
                    <button type="submit" class="track-step {{ $state }}" @disabled($key === $jobOrder->status) @if ($state === 'current') aria-current="step" @endif>
                        <span class="step-dot">
                            @if ($state === 'done')
                                <x-icon name="check" size="15" />
                            @else
                                {{ $loop->iteration }}
                            @endif
                        </span>
                        <b>{{ $label }}</b>
                        <small>{{ $explain[$key] }}</small>
                    </button>
                </form>
            @endforeach
        </div>
    </section>

    @include('quotations._details')
</div>
@endsection
