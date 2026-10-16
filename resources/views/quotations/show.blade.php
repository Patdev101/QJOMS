@extends('layout')
@section('title', $quotation->quotation_no)
@section('nav', 'quotations')

@php
    $jo = $quotation->jobOrder;
    $steps = [
        ['label' => 'Quotation made', 'sub' => $quotation->created_at->format('M j, Y'), 'state' => 'done'],
        ['label' => 'Customer approval', 'sub' => $jo ? 'Approved '.$quotation->approved_at?->format('M j') : 'Waiting', 'state' => $jo ? 'done' : 'current'],
        ['label' => 'Job order', 'sub' => $jo ? $jo->jo_no.' · '.App\Models\JobOrder::STATUSES[$jo->status] : 'After approval', 'state' => $jo ? ($jo->status === 'billed' ? 'done' : 'current') : ''],
        ['label' => 'Billing', 'sub' => $jo?->status === 'billed' ? 'Billed' : ($jo?->status === 'completed' ? 'Ready to bill' : ''), 'state' => $jo?->status === 'billed' ? 'done' : ''],
    ];
@endphp

@section('content')
<div class="page-head">
    <div>
        <div class="eyebrow"><a href="{{ route('quotations.index') }}"><x-icon name="chevron-left" size="14" /> Quotations</a></div>
        <h1>
            {{ $quotation->quotation_no }}
            @if ($jo)
                <span class="pill approved">Approved</span>
            @else
                <span class="pill pending">For approval</span>
            @endif
        </h1>
        <p class="sub">{{ $quotation->customer->name }}@if ($quotation->project) · {{ $quotation->project }}@endif · ₱{{ number_format($quotation->grand_total, 2) }}</p>
    </div>

    <div class="actions">
        <a href="{{ route('quotations.print', $quotation) }}" target="_blank" class="btn"><x-icon name="printer" /> Print</a>

        @if ($quotation->isEditable())
            <a href="{{ route('quotations.edit', $quotation) }}" class="btn"><x-icon name="pencil" /> Revise</a>
            <form method="POST" action="{{ route('quotations.approve', $quotation) }}"
                  data-confirm-title="Customer approved {{ $quotation->quotation_no }}?"
                  data-confirm="It becomes a job order and can no longer be revised."
                  data-confirm-ok="Approve & create job order" data-confirm-tone="success">
                @csrf
                <button type="submit" class="btn btn-primary"><x-icon name="check" /> Approve → Job order</button>
            </form>
        @else
            <a href="{{ route('job-orders.show', $jo) }}" class="btn btn-primary">Open {{ $jo->jo_no }} <x-icon name="arrow-right" /></a>
        @endif
    </div>
</div>

<div class="stack">
    @include('partials.steps', ['steps' => $steps])

    @if ($quotation->isEditable())
        <div class="callout">
            <x-icon name="clock" size="20" />
            <div>
                <b>Waiting for the customer's go signal.</b>
                Print or send this quotation. If they want changes, click <b>Revise</b>.
                Once they agree to the computation, click <b>Approve</b> to turn it into a job order.
            </div>
        </div>
    @endif

    @include('quotations._details')

    @if ($quotation->isEditable())
        <div class="danger-zone">
            <form method="POST" action="{{ route('quotations.destroy', $quotation) }}"
                  data-confirm-title="Delete {{ $quotation->quotation_no }}?"
                  data-confirm="This quotation and its lines will be removed for good."
                  data-confirm-ok="Delete quotation" data-confirm-tone="danger">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-ghost btn-danger"><x-icon name="trash" size="16" /> Delete quotation</button>
            </form>
        </div>
    @endif
</div>
@endsection
