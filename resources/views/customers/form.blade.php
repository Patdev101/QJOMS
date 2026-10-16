@extends('layout')
@section('title', $customer->exists ? 'Edit customer' : 'Add customer')
@section('nav', 'customers')

@section('content')
<div class="page-head">
    <div>
        <div class="eyebrow"><a href="{{ route('customers.index') }}"><x-icon name="chevron-left" size="14" /> Customer profiles</a></div>
        <h1>{{ $customer->exists ? $customer->name : 'Add customer' }}</h1>
    </div>
</div>

<form method="POST" action="{{ $customer->exists ? route('customers.update', $customer) : route('customers.store') }}" class="card form-page">
    @csrf
    @if ($customer->exists) @method('PUT') @endif

    <div class="form-section">
        <div>
            <h3>Customer</h3>
            <p>The company or person you send quotations to.</p>
        </div>
        <div>
            <div class="field">
                <label for="name">Customer name</label>
                <input id="name" name="name" value="{{ old('name', $customer->name) }}" required autofocus @class(['is-invalid' => $errors->has('name')])>
            </div>
            <div class="field field-gap">
                <label for="address">Address</label>
                <input id="address" name="address" value="{{ old('address', $customer->address) }}" @class(['is-invalid' => $errors->has('address')])>
            </div>
        </div>
    </div>

    <div class="form-section">
        <div>
            <h3>Contact</h3>
            <p>Filled in on new quotations. You can still change it per quotation.</p>
        </div>
        <div class="grid-2">
            <div class="field">
                <label for="contact_person">Contact person</label>
                <input id="contact_person" name="contact_person" value="{{ old('contact_person', $customer->contact_person) }}" @class(['is-invalid' => $errors->has('contact_person')])>
            </div>
            <div class="field">
                <label for="contact_no">Contact no.</label>
                <input id="contact_no" name="contact_no" value="{{ old('contact_no', $customer->contact_no) }}" placeholder="0917 123 4567" @class(['is-invalid' => $errors->has('contact_no')])>
            </div>
        </div>
    </div>

    <div class="form-section">
        <div>
            <h3>Discount</h3>
            <p>Applied automatically to this customer's quotations.</p>
        </div>
        <div>
            <div class="field" style="max-width: 200px">
                <label for="discount_percent">Discount</label>
                <span class="affix has-suf">
                    <input id="discount_percent" type="number" name="discount_percent" min="0" max="100" step="0.01"
                           value="{{ (float) old('discount_percent', $customer->discount_percent ?? 0) }}" required @class(['is-invalid' => $errors->has('discount_percent')])>
                    <span class="suf">%</span>
                </span>
            </div>
            <span class="hint">Use 0 for no discount. Staff can still change it on a quotation before it's approved.</span>
        </div>
    </div>

    <div class="form-foot">
        <a href="{{ route('customers.index') }}" class="btn btn-ghost">Cancel</a>
        <button type="submit" class="btn btn-primary"><x-icon name="check" /> {{ $customer->exists ? 'Save changes' : 'Add customer' }}</button>
    </div>
</form>
@endsection
