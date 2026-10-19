@php
    $jo = $quotation->jobOrder;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $jo ? $jo->jo_no : $quotation->quotation_no }}</title>
    <style>
        * { box-sizing: border-box; }
        body { max-width: 820px; margin: 28px auto; padding: 0 20px; color: #1f1c18; font: 13px/1.5 "Segoe UI", Arial, sans-serif; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .toolbar { display: flex; justify-content: flex-end; gap: 8px; margin-bottom: 20px; }
        .toolbar button { height: 38px; padding: 0 18px; border: 0; border-radius: 9px; background: #1a3029; color: #fff; font: inherit; font-weight: 600; cursor: pointer; }
        .doc-head { display: flex; justify-content: space-between; align-items: flex-end; gap: 20px; padding-bottom: 14px; border-bottom: 3px solid #1a3029; }
        .doc-title { margin: 0; font-size: 28px; font-weight: 800; letter-spacing: .12em; color: #1a3029; }
        .doc-no { text-align: right; }
        .doc-no b { display: block; font-size: 16px; }
        .doc-no span { color: #6f665a; }
        .info { display: grid; grid-template-columns: 1fr 1fr; gap: 6px 32px; margin: 18px 0 22px; }
        .info div { display: flex; gap: 10px; }
        .info dt { min-width: 110px; color: #6f665a; }
        .info dd { margin: 0; font-weight: 600; }
        table { width: 100%; border-collapse: collapse; }
        th { padding: 8px 10px; background: #f1ece2; border-bottom: 2px solid #1a3029; text-align: left; font-size: 11px; letter-spacing: .06em; text-transform: uppercase; }
        td { padding: 8px 10px; border-bottom: 1px solid #e3dccf; }
        .num { text-align: right; white-space: nowrap; }
        .totals { width: 300px; margin: 14px 0 0 auto; }
        .totals div { display: flex; justify-content: space-between; padding: 4px 10px; }
        .totals .grand { margin-top: 6px; padding: 10px; border-radius: 6px; background: #1a3029; color: #fff; font-size: 16px; font-weight: 800; }
        .sign { display: grid; grid-template-columns: 1fr 1fr; gap: 70px; margin-top: 70px; text-align: center; color: #6f665a; }
        .sign div { padding-top: 6px; border-top: 1px solid #1f1c18; }
        /* Any paper size or orientation (picked in the print dialog). Margin 0 stops the browser adding its own
           date, title, web address and page number; the body padding gives the real margins instead. */
        @page { margin: 0; }
        .sign, .totals { break-inside: avoid; }
        @media print {
            .toolbar { display: none; }
            body { max-width: none; margin: 0; padding: 14mm 16mm; }
            .sign { margin-top: 48px; }
        }
        /* landscape pages are short: tighter spacing so it still fits on one page */
        @media print and (orientation: landscape) {
            body { padding: 9mm 14mm; }
            .info { margin: 12px 0 14px; }
            td, th { padding: 5px 10px; }
            .sign { margin-top: 30px; }
        }
    </style>
</head>
<body onload="window.print()">
    <div class="toolbar"><button type="button" onclick="window.print()">Print</button></div>

    <div class="doc-head">
        <h1 class="doc-title">{{ $jo ? 'JOB ORDER' : 'QUOTATION' }}</h1>
        <div class="doc-no">
            <b>{{ $jo ? $jo->jo_no : $quotation->quotation_no }}</b>
            <span>{{ ($jo ? $jo->date : $quotation->date)->format('F j, Y') }}</span>
            @if ($jo)
                <br><span>Quotation {{ $quotation->quotation_no }}</span>
            @endif
        </div>
    </div>

    <dl class="info">
        <div><dt>Customer</dt><dd>{{ $quotation->customer->name }}</dd></div>
        <div><dt>Contact person</dt><dd>{{ $quotation->contact_person }}</dd></div>
        <div><dt>Address</dt><dd>{{ $quotation->address }}</dd></div>
        <div><dt>Contact no.</dt><dd>{{ $quotation->contact_no }}</dd></div>
        <div><dt>Project</dt><dd>{{ $quotation->project }}</dd></div>
    </dl>

    <table>
        <thead>
            <tr><th>#</th><th>Type</th><th>Code</th><th>Description</th><th>UOM</th><th class="num">Qty</th><th class="num">Unit price</th><th class="num">Total</th></tr>
        </thead>
        <tbody>
            @foreach ($quotation->lines as $line)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ App\Models\Tariff::TYPES[$line->type] }}</td>
                    <td>{{ $line->code }}</td>
                    <td>{{ $line->description }}</td>
                    <td>{{ $line->uom }}</td>
                    <td class="num">{{ (float) $line->qty }}</td>
                    <td class="num">{{ number_format($line->unit_price, 2) }}</td>
                    <td class="num">{{ number_format($line->total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals">
        <div><span>Sub total</span><span>₱{{ number_format($quotation->subtotal, 2) }}</span></div>
        <div><span>Discount ({{ (float) $quotation->discount_percent }}%)</span><span>− ₱{{ number_format($quotation->discount_amount, 2) }}</span></div>
        <div class="grand"><span>Grand total</span><span>₱{{ number_format($quotation->grand_total, 2) }}</span></div>
    </div>

    <div class="sign">
        <div>Prepared by</div>
        <div>Customer approval</div>
    </div>
</body>
</html>
