@extends('layout')
@section('title', $quotation->exists ? 'Revise '.$quotation->quotation_no : 'New quotation')
@section('nav', 'quotations')

@php
    $oldLines = old('lines', $quotation->lines->map(fn ($l) => [
        'type' => $l->type, 'tariff_id' => $l->tariff_id, 'qty' => (float) $l->qty, 'unit_price' => (float) $l->unit_price,
    ])->all());

    // "lines.2.qty" style keys, so the rows with a problem can be marked red
    $lineErrors = collect($errors->keys())->filter(fn ($key) => str_starts_with($key, 'lines.'))->values();
@endphp

@section('content')
<div class="page-head">
    <div>
        <div class="eyebrow"><a href="{{ $quotation->exists ? route('quotations.show', $quotation) : route('dashboard') }}"><x-icon name="chevron-left" size="14" /> {{ $quotation->exists ? $quotation->quotation_no : 'Dashboard' }}</a></div>
        <h1>
            {{ $quotation->exists ? 'Revise quotation' : 'New quotation' }}
            @unless ($quotation->exists)
                <span class="code code-lg" title="The number is given when you save">{{ $quotation->quotation_no }}</span>
            @endunless
        </h1>
        <p class="sub">
            @if ($quotation->exists)
                Changes are saved on this same quotation. It stays <b>for approval</b> until the customer agrees.
            @else
                Choose the customer, then add the services and items they asked for.
            @endif
        </p>
    </div>
</div>

{{-- novalidate: the script below checks the lines first, then lets the browser check the other fields --}}
<form method="POST" action="{{ $quotation->exists ? route('quotations.update', $quotation) : route('quotations.store') }}" id="quotationForm" novalidate>
    @csrf
    @if ($quotation->exists) @method('PUT') @endif

    <div class="stack">
        <section class="card">
            <div class="card-head"><h2><x-icon name="user" /> Customer &amp; project</h2></div>
            <div class="card-body grid-2">
                <div class="field">
                    <label for="customer_id">Customer</label>
                    <select id="customer_id" name="customer_id" required @if (! $quotation->exists) autofocus @endif @class(['is-invalid' => $errors->has('customer_id')])>
                        <option value="">Choose a customer…</option>
                        @foreach ($customers as $c)
                            <option value="{{ $c->id }}" @selected(old('customer_id', $quotation->customer_id) == $c->id)
                                data-address="{{ $c->address }}" data-person="{{ $c->contact_person }}"
                                data-phone="{{ $c->contact_no }}" data-discount="{{ (float) $c->discount_percent }}">{{ $c->name }}</option>
                        @endforeach
                    </select>
                    <span class="customer-badge" id="customerBadge" hidden><x-icon name="tag" size="14" /> <span></span></span>
                    @if ($customers->isEmpty())
                        <span class="hint">No customers yet.</span>
                    @endif
                </div>
                <div class="field">
                    <label for="date">Date</label>
                    <input id="date" type="date" name="date" value="{{ old('date', $quotation->date?->format('Y-m-d')) }}" required @class(['is-invalid' => $errors->has('date')])>
                </div>
                <div class="field">
                    <label for="address">Address</label>
                    <input id="address" name="address" value="{{ old('address', $quotation->address) }}">
                </div>
                <div class="field">
                    <label for="contact_person">Contact person</label>
                    <input id="contact_person" name="contact_person" value="{{ old('contact_person', $quotation->contact_person) }}">
                </div>
                <div class="field">
                    <label for="project">Project</label>
                    <input id="project" name="project" value="{{ old('project', $quotation->project) }}" placeholder="e.g. Barge hull repair">
                </div>
                <div class="field">
                    <label for="contact_no">Contact no.</label>
                    <input id="contact_no" name="contact_no" value="{{ old('contact_no', $quotation->contact_no) }}">
                </div>
            </div>
        </section>

        <section class="card">
            <div class="card-head">
                <h2><x-icon name="file-text" /> Services &amp; items</h2>
                <span class="muted" style="font-size: 13px">Type a code or description to search · Enter jumps to the next field</span>
            </div>
            <div class="table-scroll">
                <table class="table lines">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th class="col-type">Type</th>
                            <th class="col-code">Code</th>
                            <th>Description</th>
                            <th class="col-uom">UOM</th>
                            <th class="col-qty num">Qty</th>
                            <th class="col-price num">Unit price</th>
                            <th class="col-total num">Total</th>
                            <th class="col-x"><span class="sr-only">Remove</span></th>
                        </tr>
                    </thead>
                    <tbody id="lineBody"></tbody>
                    <tbody class="empty-lines" id="emptyLines">
                        <tr><td colspan="9">No lines yet. Add the services and items the customer asked for.</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="add-row">
                <button type="button" class="btn btn-sm" data-add="service"><x-icon name="wrench" size="16" /> Add service</button>
                <button type="button" class="btn btn-sm" data-add="item"><x-icon name="package" size="16" /> Add item</button>
            </div>

            <div class="lines-foot">
                <div class="staff-box">
                    <div class="staff-box-title"><x-icon name="lock" size="14" /> Staff only · not printed</div>
                    <div class="staff-grid">
                        <div><span>Total cost</span><b id="sumCost">₱0.00</b></div>
                        <div><span>Profit</span><b id="sumProfit">₱0.00</b></div>
                        <div><span>Margin</span><b id="sumMargin">—</b></div>
                    </div>
                </div>
                <div class="totals">
                    <div class="totals-row"><span>Sub total</span><b id="sumSubtotal">₱0.00</b></div>
                    <div class="totals-row">
                        <span class="disc-label">
                            Discount
                            <span class="affix has-suf disc-input">
                                <input type="number" name="discount_percent" id="discount_percent" min="0" max="100" step="0.01" required aria-label="Discount percent"
                                       value="{{ (float) old('discount_percent', $quotation->discount_percent ?? 0) }}" @class(['is-invalid' => $errors->has('discount_percent')])>
                                <span class="suf">%</span>
                            </span>
                        </span>
                        <b id="sumDiscount">− ₱0.00</b>
                    </div>
                    <div class="discount-hint" id="discountHint"></div>
                    <div class="totals-grand"><span>Grand total</span><span class="amount" id="sumGrand">₱0.00</span></div>
                </div>
            </div>
        </section>
    </div>

    <div class="action-bar">
        <div>
            <div class="bar-label">Grand total</div>
            <div class="bar-total" id="barGrand">₱0.00</div>
        </div>
        <div class="bar-meta" id="barMeta"></div>
        <div class="actions">
            <a href="{{ $quotation->exists ? route('quotations.show', $quotation) : route('dashboard') }}" class="btn btn-ghost">Cancel</a>
            <button type="submit" class="btn btn-amber"><x-icon name="check" /> {{ $quotation->exists ? 'Save changes' : 'Save quotation' }}</button>
        </div>
    </div>
</form>

<template id="lineTemplate">
    <tr class="line is-empty">
        <td class="line-no">1</td>
        <td>
            <select class="type" aria-label="Type">
                @foreach (App\Models\Tariff::TYPES as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
        </td>
        <td>
            <input type="text" class="picker-input" placeholder="Search…" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="pickerList" aria-label="Code">
            <input type="hidden" class="tariff-id">
        </td>
        <td class="desc-cell"><div class="desc"></div><div class="unit"></div></td>
        <td class="uom">—</td>
        <td><input type="number" class="qty" min="0.01" step="0.01" value="1" required aria-label="Quantity"></td>
        <td>
            <input type="number" class="price" min="0" step="0.01" required aria-label="Unit price">
            <button type="button" class="price-hint" title="Use the list price" hidden></button>
        </td>
        <td class="line-total num">0.00</td>
        <td><button type="button" class="btn btn-ghost btn-icon btn-danger remove" title="Remove line" aria-label="Remove line"><x-icon name="trash" size="16" /></button></td>
    </tr>
</template>

<div class="picker-list" id="pickerList" role="listbox" hidden></div>
@endsection

@push('scripts')
<script>
(function () {
    const tariffs = @json($tariffs);
    const startLines = @json(array_values($oldLines));
    const lineErrors = @json($lineErrors);

    const form = document.getElementById('quotationForm');
    const body = document.getElementById('lineBody');
    const emptyLines = document.getElementById('emptyLines');
    const template = document.getElementById('lineTemplate');
    const list = document.getElementById('pickerList');
    const customerSelect = document.getElementById('customer_id');
    const discountInput = document.getElementById('discount_percent');

    const plain = n => Number(n).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const money = n => (n < 0 ? '−₱' : '₱') + plain(Math.abs(n));
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const tariffOf = row => tariffs.find(t => String(t.id) === row.querySelector('.tariff-id').value);
    const rows = () => [...body.querySelectorAll('tr.line')];
    let dirty = false;

    /* ---------- code picker: type a code or description, pick with the mouse or the arrow keys ---------- */
    let open = null;     // the row whose list is showing
    let options = [];
    let highlighted = 0;

    function openPicker(row) {
        const input = row.querySelector('.picker-input');
        const type = row.querySelector('.type').value;
        const current = tariffOf(row);
        // while the box still shows the chosen code, list everything of that type
        const query = current && input.value === current.code ? '' : input.value.trim().toLowerCase();

        options = tariffs.filter(t => t.type === type && (! query
            || t.code.toLowerCase().includes(query)
            || t.description.toLowerCase().includes(query))).slice(0, 80);
        highlighted = Math.max(0, options.findIndex(t => current && t.id === current.id));
        open = row;

        renderList(type);
        placeList();
        list.hidden = false;
        input.setAttribute('aria-expanded', 'true');
    }

    function renderList(type) {
        if (! options.length) {
            list.innerHTML = `<div class="picker-empty">Nothing found. New ${type === 'item' ? 'items' : 'services'} are added under ${type === 'item' ? 'Item' : 'Service'} tariff.</div>`;
            return;
        }

        list.innerHTML = options.map((t, i) => `
            <div class="picker-opt${i === highlighted ? ' is-active' : ''}" data-i="${i}" role="option" aria-selected="${i === highlighted}">
                <span class="code">${esc(t.code)}</span>
                <span class="picker-desc">${esc(t.description)}</span>
                <span class="picker-price">₱${plain(t.price)} / ${esc(t.uom)}</span>
            </div>`).join('');
    }

    function moveHighlight(step) {
        highlighted = Math.min(Math.max(highlighted + step, 0), options.length - 1);
        list.querySelectorAll('.picker-opt').forEach((el, i) => {
            el.classList.toggle('is-active', i === highlighted);
            el.setAttribute('aria-selected', i === highlighted);
        });
        list.querySelector('.is-active')?.scrollIntoView({ block: 'nearest' });
    }

    // the list floats above the page, so the table's scroll area never cuts it off
    function placeList() {
        if (! open) return;

        const box = open.querySelector('.picker-input').getBoundingClientRect();
        const width = Math.max(box.width, 380);
        const roomBelow = window.innerHeight - box.bottom;

        list.style.width = width + 'px';
        list.style.left = Math.max(8, Math.min(box.left, window.innerWidth - width - 8)) + 'px';

        if (roomBelow < 240 && box.top > roomBelow) {
            list.style.top = '';
            list.style.bottom = (window.innerHeight - box.top + 4) + 'px';
        } else {
            list.style.bottom = '';
            list.style.top = (box.bottom + 4) + 'px';
        }
    }

    function closePicker() {
        if (! open) return;

        // the box always shows the chosen code, or stays empty
        const current = tariffOf(open);
        const input = open.querySelector('.picker-input');

        input.value = current ? current.code : '';
        input.setAttribute('aria-expanded', 'false');
        list.hidden = true;
        open = null;
    }

    function choose(i, moveFocus) {
        const t = options[i];
        const row = open;

        if (! t || ! row) return;

        row.querySelector('.tariff-id').value = t.id;
        row.querySelector('.picker-input').classList.remove('is-invalid');
        closePicker();
        showTariff(row, true);
        dirty = true;
        recalc();

        if (moveFocus) {
            row.querySelector('.qty').focus();
            row.querySelector('.qty').select();
        }
    }

    list.addEventListener('mousedown', function (event) {
        event.preventDefault(); // keep the focus in the search box
        const option = event.target.closest('.picker-opt');

        if (option) {
            choose(Number(option.dataset.i), true);
        }
    });

    window.addEventListener('scroll', placeList, true);
    window.addEventListener('resize', placeList);

    /* ---------- lines ---------- */
    function showTariff(row, usePrice) {
        const t = tariffOf(row);

        row.classList.toggle('is-empty', ! t);
        row.querySelector('.picker-input').value = t ? t.code : '';
        row.querySelector('.desc').textContent = t ? t.description : '';
        row.querySelector('.unit').textContent = t && t.unit ? 'per ' + t.unit.toLowerCase() : '';
        row.querySelector('.uom').textContent = t ? t.uom : '—';

        if (usePrice) {
            row.querySelector('.price').value = t ? Number(t.price).toFixed(2) : '';
        }
    }

    function renumber() {
        rows().forEach(function (row, i) {
            row.querySelector('.line-no').textContent = i + 1;
            row.querySelector('.type').name = `lines[${i}][type]`;
            row.querySelector('.tariff-id').name = `lines[${i}][tariff_id]`;
            row.querySelector('.qty').name = `lines[${i}][qty]`;
            row.querySelector('.price').name = `lines[${i}][unit_price]`;
        });
    }

    function addLine(data, focus) {
        data = data || {};
        const row = template.content.firstElementChild.cloneNode(true);
        const picker = row.querySelector('.picker-input');
        const hasPrice = data.unit_price !== undefined && data.unit_price !== null && data.unit_price !== '';

        body.appendChild(row);
        row.querySelector('.type').value = data.type || 'service';
        row.querySelector('.tariff-id').value = data.tariff_id || '';
        showTariff(row, ! hasPrice);

        if (data.qty !== undefined && data.qty !== '') row.querySelector('.qty').value = Number(data.qty);
        if (hasPrice) row.querySelector('.price').value = Number(data.unit_price).toFixed(2);

        row.querySelector('.type').addEventListener('change', function () {
            row.querySelector('.tariff-id').value = '';
            showTariff(row, true);
            recalc();
            picker.focus();
        });

        picker.addEventListener('focus', () => openPicker(row));
        picker.addEventListener('click', () => open !== row && openPicker(row));
        picker.addEventListener('input', () => openPicker(row));
        picker.addEventListener('blur', closePicker);
        picker.addEventListener('keydown', function (event) {
            if (open !== row) {
                if (event.key === 'ArrowDown' || event.key === 'Enter') {
                    event.preventDefault();
                    openPicker(row);
                }
                return;
            }

            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                moveHighlight(event.key === 'ArrowDown' ? 1 : -1);
            } else if (event.key === 'Enter') {
                event.preventDefault();
                choose(highlighted, true);
            } else if (event.key === 'Escape') {
                event.preventDefault();
                event.stopPropagation();
                closePicker();
            } else if (event.key === 'Tab' && picker.value.trim() && picker.value !== (tariffOf(row) || {}).code) {
                choose(highlighted, false); // typed something: Tab takes the highlighted match
            }
        });

        row.querySelector('.price-hint').addEventListener('click', function () {
            const t = tariffOf(row);
            row.querySelector('.price').value = Number(t.price).toFixed(2);
            dirty = true;
            recalc();
        });

        row.querySelector('.remove').addEventListener('click', function () {
            const next = row.nextElementSibling || row.previousElementSibling;
            row.remove();
            renumber();
            recalc();
            dirty = true;
            (next ? next.querySelector('.picker-input') : document.querySelector('[data-add="service"]')).focus();
        });

        // Enter never sends the form from a line: it moves on to the next field or a new line
        row.addEventListener('keydown', function (event) {
            if (event.key !== 'Enter' || ! event.target.matches('.qty, .price, .type')) return;

            event.preventDefault();

            if (event.target.matches('.type')) {
                picker.focus();
            } else if (event.target.matches('.qty')) {
                row.querySelector('.price').focus();
                row.querySelector('.price').select();
            } else if (row.nextElementSibling) {
                row.nextElementSibling.querySelector('.picker-input').focus();
            } else {
                addLine({ type: row.querySelector('.type').value }, true);
            }
        });

        renumber();
        recalc();

        if (focus) picker.focus();

        return row;
    }

    /* ---------- totals ---------- */
    function recalc() {
        let subtotal = 0;
        let cost = 0;
        let filled = 0;

        rows().forEach(function (row) {
            const t = tariffOf(row);
            const qty = Number(row.querySelector('.qty').value) || 0;
            const price = Number(row.querySelector('.price').value) || 0;
            const total = Math.round(qty * price * 100) / 100;
            const hint = row.querySelector('.price-hint');
            const changed = t && row.querySelector('.price').value !== '' && price !== Number(t.price);

            row.querySelector('.line-total').textContent = plain(total);
            hint.hidden = ! changed;
            if (changed) hint.textContent = 'List price ₱' + plain(t.price) + ' · reset';

            subtotal += total;
            if (t) {
                cost += qty * Number(t.cost);
                filled++;
            }
        });

        const percent = Math.min(Math.max(Number(discountInput.value) || 0, 0), 100);
        const discount = Math.round(subtotal * percent) / 100;
        const grand = subtotal - discount;
        const profit = grand - cost;

        document.getElementById('sumSubtotal').textContent = money(subtotal);
        document.getElementById('sumDiscount').textContent = '− ' + money(discount);
        document.getElementById('sumGrand').textContent = money(grand);
        document.getElementById('barGrand').textContent = money(grand);
        document.getElementById('sumCost').textContent = money(cost);
        document.getElementById('sumProfit').textContent = money(profit);
        document.getElementById('sumProfit').classList.toggle('neg', profit < 0);
        document.getElementById('sumMargin').textContent = grand > 0 ? (profit / grand * 100).toFixed(1) + '%' : '—';
        document.getElementById('sumMargin').classList.toggle('neg', profit < 0);
        document.getElementById('barMeta').textContent = filled + (filled === 1 ? ' line' : ' lines') + (percent ? ` · ${percent}% discount` : '');

        emptyLines.hidden = rows().length > 0;
        discountHint();
    }

    /* ---------- customer: fills in the contact details and the customer's discount ---------- */
    function customerDiscount() {
        const option = customerSelect.selectedOptions[0];
        return option && option.value ? Number(option.dataset.discount || 0) : null;
    }

    function showCustomerBadge() {
        const badge = document.getElementById('customerBadge');
        const percent = customerDiscount();

        badge.hidden = percent === null;
        badge.classList.toggle('none', ! percent);
        badge.querySelector('span').textContent = percent ? `${percent}% customer discount` : 'No customer discount';
    }

    function discountHint() {
        const hint = document.getElementById('discountHint');
        const percent = customerDiscount();

        if (percent === null) {
            hint.innerHTML = '';
        } else if (Number(discountInput.value) !== percent) {
            hint.innerHTML = `Changed from the customer's ${percent}% · <button type="button" class="reset-link" id="resetDiscount">Reset</button>`;
            document.getElementById('resetDiscount').addEventListener('click', function () {
                discountInput.value = percent;
                dirty = true;
                recalc();
            });
        } else {
            hint.textContent = percent ? 'From the customer profile' : '';
        }
    }

    customerSelect.addEventListener('change', function () {
        const option = customerSelect.selectedOptions[0];

        document.getElementById('address').value = option.dataset.address || '';
        document.getElementById('contact_person').value = option.dataset.person || '';
        document.getElementById('contact_no').value = option.dataset.phone || '';
        discountInput.value = option.value ? Number(option.dataset.discount || 0) : 0;

        showCustomerBadge();
        recalc();
    });

    /* ---------- form ---------- */
    body.addEventListener('input', recalc);
    discountInput.addEventListener('input', recalc);
    form.addEventListener('input', () => dirty = true);

    document.querySelectorAll('[data-add]').forEach(button => button.addEventListener('click', function () {
        addLine({ type: button.dataset.add }, true);
        dirty = true;
    }));

    // Enter in a header field does not send the form by accident
    form.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' && event.target.matches('input:not([type=submit])')) {
            event.preventDefault();
        }
    });

    form.addEventListener('submit', function (event) {
        const missing = rows().find(row => ! row.querySelector('.tariff-id').value);

        if (! rows().length) {
            event.preventDefault();
            emptyLines.classList.add('attention');
            emptyLines.querySelector('td').textContent = 'Add at least one service or item before saving.';
            document.querySelector('[data-add="service"]').focus();
            return;
        }

        if (missing) {
            event.preventDefault();
            missing.querySelector('.picker-input').classList.add('is-invalid');
            missing.querySelector('.picker-input').focus();
            return;
        }

        if (! form.checkValidity()) {
            event.preventDefault();
            form.reportValidity();
            return;
        }

        dirty = false;
    });

    // leaving the page with unsaved changes asks first
    window.addEventListener('beforeunload', function (event) {
        if (dirty) {
            event.preventDefault();
            event.returnValue = '';
        }
    });

    (startLines.length ? startLines : [{ type: 'service' }]).forEach(line => addLine(line));

    // after a failed save, mark the fields that need fixing
    const fieldClass = { type: '.type', tariff_id: '.picker-input', qty: '.qty', unit_price: '.price' };
    lineErrors.forEach(function (key) {
        const [, index, field] = key.split('.');
        const row = rows()[Number(index)];

        if (row && fieldClass[field]) {
            row.querySelector(fieldClass[field]).classList.add('is-invalid');
        }
    });

    showCustomerBadge();
    recalc();
    dirty = false;
})();
</script>
@endpush
