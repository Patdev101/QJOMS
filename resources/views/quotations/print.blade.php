@php
    $jo = $quotation->jobOrder;
    $approved = $quotation->status === 'approved';
    $docDate = $jo ? $jo->date : $quotation->date;
    $vessel = $quotation->vesselDetails();
    $particulars = array_filter($vessel, fn ($label) => ! str_starts_with($label, 'Date'), ARRAY_FILTER_USE_KEY);
    $schedule = array_filter($vessel, fn ($label) => str_starts_with($label, 'Date'), ARRAY_FILTER_USE_KEY);
    $hasVessel = $quotation->vessel_name || $particulars;
    // work grouped by the tariff's category, in the order the lines were entered
    $groups = $quotation->lines->groupBy(fn ($l) => $l->tariff?->category ?: ($l->type === 'item' ? 'Items' : 'Services'));
    $largest = max($groups->map(fn ($g) => (float) $g->sum('total'))->max() ?: 0, 1);
    $appName = config('app.name', 'QJOMS');
    $initials = collect(preg_split('/\s+/', $appName))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->join('');
    $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $jo ? $jo->jo_no : $quotation->quotation_no }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">
    <style>
        * { box-sizing: border-box; }
        :root { --pine: #2b6450; --pine-dark: #1a3029; --amber: #d98f1f; --ink: #1f1c18; --muted: #8a8072; --line: #e3dccf; --sand: #f6f1e7; }
        body { max-width: 860px; margin: 24px auto; padding: 0 20px; color: var(--ink); font: 11.5px/1.45 "Plus Jakarta Sans", "Segoe UI", Arial, sans-serif; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .toolbar { display: flex; justify-content: flex-end; gap: 8px; margin-bottom: 18px; }
        .toolbar button { height: 38px; padding: 0 18px; border: 0; border-radius: 9px; background: var(--pine-dark); color: #fff; font: inherit; font-size: 13px; font-weight: 600; cursor: pointer; }

        .top { display: flex; justify-content: space-between; gap: 20px; align-items: flex-start; }
        .brand { display: flex; gap: 12px; align-items: center; }
        .mono { width: 46px; height: 46px; border-radius: 12px; background: var(--pine); color: #fff; display: grid; place-items: center; font-weight: 800; font-size: 17px; flex: none; }
        .brand b { display: block; font-size: 16px; line-height: 1.2; }
        .brand span { color: #6b6357; font-size: 10px; }
        .title { text-align: right; }
        .title h1 { margin: 0; font-size: 24px; letter-spacing: .26em; font-weight: 800; color: var(--pine); }
        .meta { display: grid; grid-template-columns: auto auto; gap: 1px 10px; justify-content: end; margin: 4px 0 0; font-size: 10.5px; }
        .meta dt { color: var(--muted); }
        .meta dd { margin: 0; font-weight: 700; text-align: right; }
        .stamp { display: inline-block; margin-top: 6px; padding: 2px 10px; border: 2px solid var(--amber); border-radius: 6px; color: #9a5f08; font-weight: 800; letter-spacing: .12em; font-size: 9.5px; }
        .stamp.final { background: var(--pine); border-color: var(--pine); color: #fff; }
        .rule { height: 4px; margin: 12px 0 14px; border-radius: 4px; background: linear-gradient(90deg, var(--pine) 0 72%, var(--amber) 72%); }

        .boxes { display: grid; grid-template-columns: 1.2fr 1fr 1fr; gap: 10px; }
        .boxes.two { grid-template-columns: 1.4fr 1fr; }
        .boxes.one { grid-template-columns: 1fr; }
        .box { padding: 9px 11px; border: 1px solid var(--line); border-radius: 10px; background: #fcfaf6; }
        .box h5 { margin: 0 0 5px; font-size: 8.5px; letter-spacing: .14em; text-transform: uppercase; color: var(--pine); }
        .box .big { font-size: 12.5px; font-weight: 800; }
        .box .sm { color: #6b6357; font-size: 10px; }
        .kv { display: grid; grid-template-columns: auto 1fr; gap: 1px 8px; margin-top: 5px; font-size: 10.5px; }
        .kv dt { color: var(--muted); }
        .kv dd { margin: 0; font-weight: 600; }
        .mini { display: grid; grid-template-columns: 1fr 1fr; gap: 4px; margin-top: 5px; }
        .mini div { padding: 2px 6px; border: 1px solid #ece5d8; border-radius: 6px; background: #fff; font-weight: 700; }
        .mini small { display: block; color: var(--muted); font-size: 8px; font-weight: 500; }
        .tl { margin: 0; padding: 0; list-style: none; }
        .tl li { position: relative; padding: 0 0 4px 14px; font-weight: 700; }
        .tl li::before { content: ""; position: absolute; left: 1px; top: 4px; width: 7px; height: 7px; border-radius: 50%; background: var(--amber); }
        .tl li:not(:last-child)::after { content: ""; position: absolute; left: 4px; top: 12px; bottom: 0; width: 1px; background: var(--line); }
        .tl small { display: block; color: var(--muted); font-size: 8px; font-weight: 500; line-height: 1.1; }

        .scope { margin: 14px 0 4px; font-size: 12px; }
        .scope b { color: var(--pine); }
        .sec { margin-top: 10px; }
        .sec-h { display: flex; align-items: center; gap: 8px; padding: 5px 0; border-bottom: 1.5px solid var(--pine); break-after: avoid; }
        .sec-h i { width: 20px; height: 20px; border-radius: 50%; background: var(--pine); color: #fff; display: grid; place-items: center; font-style: normal; font-weight: 800; font-size: 10px; flex: none; }
        .sec-h b { flex: 1; font-size: 11.5px; }
        .sec-h span { color: var(--muted); font-size: 9.5px; }
        table { width: 100%; border-collapse: collapse; }
        th { padding: 4px 6px; border-bottom: 1px solid var(--line); color: var(--muted); font-size: 8px; letter-spacing: .08em; text-align: left; text-transform: uppercase; }
        td { padding: 5px 6px; border-bottom: 1px solid #f0ebe2; vertical-align: top; font-size: 10.5px; }
        tr { break-inside: avoid; }
        .num { text-align: right; white-space: nowrap; }
        .code { font-family: Consolas, monospace; font-size: 8.5px; color: var(--muted); }
        .per { display: block; font-family: Consolas, monospace; font-size: 8.5px; color: var(--muted); }
        .note { display: block; margin-top: 2px; color: #6b6357; font-size: 9.5px; font-style: italic; }
        .subt td { border-bottom: 0; background: var(--sand); font-weight: 800; }

        .summary { margin-top: 18px; }
        /* the same column widths in every group, so Quantity / Rate / Amount line up down all pages */
        .sec table { table-layout: fixed; }
        .sec th:nth-child(1) { width: 26px; }
        .sec th:nth-last-child(3) { width: 110px; }
        .sec th:nth-last-child(2) { width: 110px; }
        .sec th:nth-last-child(1) { width: 110px !important; }
        .sec td { overflow-wrap: anywhere; }
        .page-wrap { width: 100%; border-collapse: collapse; }
        .page-wrap > thead > tr > td, .page-wrap > tfoot > tr > td, .page-wrap > tbody > tr > td { padding: 0; border: 0; }
        .page-space { height: 0; }
        .closing { display: grid; grid-template-columns: 1fr 280px; gap: 10px 30px; margin-top: 16px; break-inside: avoid; page-break-inside: avoid; }
        .closing .tcard { grid-column: 2; align-self: start; }
        .where h5 { margin: 0 0 8px; font-size: 8.5px; letter-spacing: .14em; text-transform: uppercase; color: var(--pine); }
        .bar-row { display: grid; grid-template-columns: 1fr auto; gap: 3px 10px; margin-bottom: 7px; font-size: 10px; }
        .bar { grid-column: 1 / -1; height: 5px; border-radius: 4px; background: #efe8db; }
        .bar i { display: block; height: 100%; border-radius: 4px; background: var(--pine); }
        .closing .sign { grid-column: 1 / -1; }
        .tcard { overflow: hidden; border: 1px solid var(--line); border-radius: 10px; }
        .tcard div { display: flex; justify-content: space-between; padding: 5px 11px; font-size: 10.5px; }
        .tcard .grand { padding: 9px 11px; background: var(--pine); color: #fff; font-size: 13.5px; font-weight: 800; }

        .sign { display: grid; grid-template-columns: 1fr 1fr; gap: 60px; margin-top: 46px; break-inside: avoid; }
        .sign small { display: block; margin-bottom: 30px; color: var(--muted); font-size: 8px; letter-spacing: .1em; text-transform: uppercase; }
        .sign .ln { padding-top: 4px; border-top: 1px solid var(--ink); color: #6b6357; font-size: 9.5px; }

        /* any paper size or orientation (picked in the print dialog); margin 0 keeps the browser's own
           date, title, address and page number off the page — the body padding gives the real margins */
        @page { margin: 0; }
        @media print {
            .toolbar { display: none; }
            body { max-width: none; margin: 0; padding: 0 14mm; }
            .page-space { height: 12mm; }
        }
        @media print and (orientation: landscape) {
            .page-space { height: 8mm; }
            .sign { margin-top: 28px; }
        }
        @media (max-width: 640px) { .boxes, .boxes.two, .closing, .sign { grid-template-columns: 1fr; } .top { flex-direction: column; } .title { text-align: left; } }
    </style>
</head>
<body onload="window.print()">
    <div class="toolbar"><button type="button" onclick="window.print()">Print</button></div>
    {{-- the empty header/footer rows repeat on every printed page, so every page gets a top and bottom margin --}}
    <table class="page-wrap">
    <thead><tr><td><div class="page-space"></div></td></tr></thead>
    <tbody><tr><td>

    <div class="top">
        <div class="brand">
            <div class="mono">{{ $initials ?: 'Q' }}</div>
            <div><b>{{ $appName }}</b><span>Quotation &amp; Job Order</span></div>
        </div>
        <div class="title">
            <h1>{{ $jo ? 'JOB ORDER' : ($approved ? 'FINAL QUOTATION' : 'QUOTATION') }}</h1>
            <dl class="meta">
                <dt>No.</dt><dd>{{ $jo ? $jo->jo_no : $quotation->quotation_no }}</dd>
                @if ($jo)<dt>Quotation</dt><dd>{{ $quotation->quotation_no }}</dd>@endif
                <dt>Date</dt><dd>{{ $docDate->format('F j, Y') }}</dd>
                @unless ($jo)<dt>Valid until</dt><dd>{{ $quotation->date->copy()->addDays(30)->format('F j, Y') }}</dd>@endunless
            </dl>
            <span class="stamp {{ $approved ? 'final' : '' }}">{{ $approved ? 'APPROVED' : 'FOR APPROVAL' }}</span>
        </div>
    </div>
    <div class="rule"></div>

    <div class="boxes {{ $hasVessel && $schedule ? '' : ($hasVessel || $schedule ? 'two' : 'one') }}">
        <div class="box">
            <h5>Prepared for</h5>
            <div class="big">{{ $quotation->customer->name }}</div>
            @if ($quotation->address)<div class="sm">{{ $quotation->address }}</div>@endif
            <dl class="kv">
                @if ($quotation->contact_person)<dt>Contact</dt><dd>{{ $quotation->contact_person }}</dd>@endif
                @if ($quotation->contact_no)<dt>Phone</dt><dd>{{ $quotation->contact_no }}</dd>@endif
            </dl>
        </div>
        @if ($hasVessel)
            <div class="box">
                <h5>Vessel</h5>
                <div class="big">{{ $quotation->vessel_name ?: '—' }}</div>
                @if ($particulars)
                    <div class="mini">
                        @foreach ($particulars as $label => $value)
                            <div><small>{{ $label }}</small>{{ $value }}</div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif
        @if ($schedule)
            <div class="box">
                <h5>Schedule</h5>
                <ul class="tl">
                    @foreach ($schedule as $label => $value)
                        <li><small>{{ \Illuminate\Support\Str::after($label, 'Date ') }}</small>{{ $value }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>

    <p class="scope">
        @if ($quotation->project)
            <b>{{ $quotation->project }}</b>{{ $quotation->vessel_name ? ' — '.$quotation->vessel_name : '' }}
        @elseif ($quotation->vessel_name)
            Works for <b>{{ $quotation->vessel_name }}</b>
        @else
            Services and items as listed below.
        @endif
    </p>

    @foreach ($groups as $category => $lines)
        <section class="sec">
            <div class="sec-h">
                <i>{{ $loop->iteration }}</i>
                <b>{{ $category }}</b>
                <span>{{ $lines->count() }} {{ $lines->count() === 1 ? 'item' : 'items' }}</span>
            </div>
            <table>
                <thead>
                    <tr><th style="width:22px">#</th><th>Work</th><th class="num">Quantity</th><th class="num">Rate</th><th class="num" style="width:96px">Amount</th></tr>
                </thead>
                <tbody>
                    @foreach ($lines as $line)
                        <tr>
                            <td style="color:var(--muted)">{{ $loop->iteration }}</td>
                            <td><b>{{ $line->description }}</b> <span class="code">{{ $line->code }}</span>@if ($line->note)<span class="note">{{ $line->note }}</span>@endif</td>
                            <td class="num">
                                {{ App\Models\QuotationLine::quantityWithUnit($line->qty, $line->uom) }}
                                @if ($line->uom2 && $line->qty2 !== null)<br>× {{ App\Models\QuotationLine::quantityWithUnit($line->qty2, $line->uom2) }}@endif
                            </td>
                            <td class="num">₱{{ number_format($line->unit_price, 2) }}@if ($line->unitLabel())<span class="per">/ {{ $line->unitLabel() }}</span>@endif</td>
                            <td class="num"><b>₱{{ number_format($line->total, 2) }}</b></td>
                        </tr>
                    @endforeach
                    @if ($groups->count() > 1)
                        <tr class="subt"><td></td><td colspan="3">Subtotal — {{ $category }}</td><td class="num">₱{{ number_format($lines->sum('total'), 2) }}</td></tr>
                    @endif
                </tbody>
            </table>
        </section>
    @endforeach

    {{-- summary by main section of the tariff (A. DRY DOCKING AND UNDOCKING, B. GENERAL RELATED SERVICES …), beside the totals;
         the summary, totals and signatures stay together --}}
    @php
        $sections = $quotation->lines->groupBy(fn ($l) => $l->section ?: 'OTHER WORKS (manual lines)')
            ->map(fn ($g) => (float) $g->sum('total'))
            ->sortBy(fn ($v, $name) => (str_starts_with($name, 'OTHER WORKS') ? '1' : '0').$name);
        $topSection = max($sections->max() ?: 0, 1);
    @endphp
    <div class="closing">
        <div class="where">
            <h5>Summary — where the cost goes</h5>
            @foreach ($sections as $name => $part)
                <div class="bar-row">
                    <span>{{ $name }}</span>
                    <b>{{ $quotation->subtotal > 0 ? number_format($part / $quotation->subtotal * 100, 1) : '0.0' }}% · ₱{{ number_format($part, 2) }}</b>
                    <div class="bar"><i style="width: {{ $part / $topSection * 100 }}%"></i></div>
                </div>
            @endforeach
        </div>
        <div class="tcard">
            <div><span>Sub total</span><b>₱{{ number_format($quotation->subtotal, 2) }}</b></div>
            @if ((float) $quotation->discount_percent > 0)
                <div><span>Discount ({{ (float) $quotation->discount_percent }}%)</span><b>− ₱{{ number_format($quotation->discount_amount, 2) }}</b></div>
            @endif
            <div class="grand"><span>Grand total</span><span>₱{{ number_format($quotation->grand_total, 2) }}</span></div>
        </div>
        <div class="sign">
            <div><small>Prepared by</small><div class="ln">Signature over printed name</div></div>
            <div><small>Customer approval</small><div class="ln">Signature over printed name · Date</div></div>
        </div>
    </div>
    </td></tr></tbody>
    <tfoot><tr><td><div class="page-space"></div></td></tr></tfoot>
    </table>
</body>
</html>
