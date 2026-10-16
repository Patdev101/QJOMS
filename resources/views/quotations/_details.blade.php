{{-- customer info, lines and totals of a quotation; shared by the quotation and job order pages --}}
@php $margin = $quotation->margin(); @endphp

<section class="card">
    <div class="card-head"><h2><x-icon name="user" /> Customer &amp; project</h2></div>
    <div class="info-grid">
        @foreach ([
            ['users', 'Customer', $quotation->customer->name],
            ['user', 'Contact person', $quotation->contact_person],
            ['phone', 'Contact no.', $quotation->contact_no],
            ['map-pin', 'Address', $quotation->address],
            ['folder', 'Project', $quotation->project],
            ['calendar', 'Quotation date', $quotation->date->format('F j, Y')],
        ] as [$icon, $label, $value])
            <div class="info-item">
                <span class="info-ic"><x-icon :name="$icon" size="17" /></span>
                <div>
                    <div class="info-label">{{ $label }}</div>
                    <div @class(['info-value', 'muted' => ! $value])>{{ $value ?: '—' }}</div>
                </div>
            </div>
        @endforeach
    </div>
</section>

<section class="card">
    <div class="card-head">
        <h2><x-icon name="file-text" /> Services &amp; items</h2>
        <span class="muted">{{ $quotation->lines->count() }} {{ Str::plural('line', $quotation->lines->count()) }}</span>
    </div>
    <div class="table-scroll">
        <table class="table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Type</th>
                    <th>Code</th>
                    <th>Description</th>
                    <th>UOM</th>
                    <th class="num">Qty</th>
                    <th class="num">Unit price</th>
                    <th class="num">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($quotation->lines as $line)
                    <tr>
                        <td class="muted">{{ $loop->iteration }}</td>
                        <td><span class="tag {{ $line->type }}">{{ App\Models\Tariff::TYPES[$line->type] }}</span></td>
                        <td><span class="code">{{ $line->code }}</span></td>
                        <td class="cell-main">{{ $line->description }}</td>
                        <td>{{ $line->uom }}</td>
                        <td class="num">{{ (float) $line->qty }}</td>
                        <td class="num">{{ number_format($line->unit_price, 2) }}</td>
                        <td class="num cell-main">{{ number_format($line->total, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="lines-foot">
        <div class="staff-box">
            <div class="staff-box-title"><x-icon name="lock" size="14" /> Staff only · not printed</div>
            <div class="staff-grid">
                <div><span>Total cost</span><b>₱{{ number_format($quotation->total_cost, 2) }}</b></div>
                <div><span>Profit</span><b @class(['neg' => $quotation->profit() < 0])>₱{{ number_format($quotation->profit(), 2) }}</b></div>
                <div><span>Margin</span><b @class(['neg' => $quotation->profit() < 0])>{{ $margin === null ? '—' : number_format($margin, 1).'%' }}</b></div>
            </div>
        </div>
        <div class="totals">
            <div class="totals-row"><span>Sub total</span><b>₱{{ number_format($quotation->subtotal, 2) }}</b></div>
            <div class="totals-row"><span>Discount ({{ (float) $quotation->discount_percent }}%)</span><b>− ₱{{ number_format($quotation->discount_amount, 2) }}</b></div>
            <div class="totals-grand"><span>Grand total</span><span class="amount">₱{{ number_format($quotation->grand_total, 2) }}</span></div>
        </div>
    </div>
</section>
