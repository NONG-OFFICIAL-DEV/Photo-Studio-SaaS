<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        /*
         * The default fonts (and dompdf's DejaVu Sans, back when this was
         * a dompdf view) lack Khmer glyphs — customer/tenant names in
         * Khmer rendered as "???????". Noto Sans Khmer covers both Khmer
         * script and Latin/digits/punctuation, so one font handles the
         * whole invoice without per-run font-switching. Embedded as a
         * base64 data URI rather than a filesystem path — Browsershot
         * renders this view as a bare HTML string with no base URL, so a
         * local file:// path has nothing to resolve against.
         */
        @font-face {
            font-family: 'Noto Sans Khmer';
            font-style: normal;
            font-weight: normal;
            src: url('{{ $khmerFontRegularDataUri }}') format('truetype');
        }
        @font-face {
            font-family: 'Noto Sans Khmer';
            font-style: normal;
            font-weight: bold;
            src: url('{{ $khmerFontBoldDataUri }}') format('truetype');
        }
        body { font-family: 'Noto Sans Khmer', 'DejaVu Sans', sans-serif; font-size: 12px; color: #1f2937; }
        .header { display: table; width: 100%; margin-bottom: 24px; }
        .header .studio { display: table-cell; vertical-align: top; }
        .header .invoice-meta { display: table-cell; vertical-align: top; text-align: right; }
        .studio-logo { max-height: 56px; max-width: 220px; margin-bottom: 8px; }
        .studio-name { font-size: 18px; font-weight: bold; margin-bottom: 4px; }
        .muted { color: #6b7280; }
        .invoice-title { font-size: 22px; font-weight: bold; letter-spacing: 1px; }
        .status { display: inline-block; margin-top: 4px; padding: 2px 10px; border-radius: 10px; font-size: 11px; text-transform: uppercase; background: #e5e7eb; }
        .bill-to { margin-bottom: 20px; }
        .bill-to .label { text-transform: uppercase; font-size: 10px; color: #6b7280; margin-bottom: 4px; }
        table.items { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        table.items th { text-align: left; border-bottom: 2px solid #1f2937; padding: 6px 4px; font-size: 11px; text-transform: uppercase; }
        table.items td { padding: 6px 4px; border-bottom: 1px solid #e5e7eb; }
        table.items th.num, table.items td.num { text-align: right; }
        .totals { width: 260px; margin-left: auto; }
        .totals .row { display: table; width: 100%; }
        .totals .row .label, .totals .row .value { display: table-cell; padding: 3px 0; }
        .totals .row .value { text-align: right; }
        .totals .grand { font-size: 15px; font-weight: bold; border-top: 2px solid #1f2937; padding-top: 6px; }
        .totals .balance { font-weight: bold; color: #b91c1c; }
        .notes { margin-top: 24px; }
        .footer { margin-top: 32px; padding-top: 12px; border-top: 1px solid #e5e7eb; font-size: 10px; color: #6b7280; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <div class="studio">
            @if($logoDataUri)
                <img class="studio-logo" src="{{ $logoDataUri }}" alt="{{ $invoice->tenant->name }}">
            @endif
            <div class="studio-name">{{ $invoice->tenant->name }}</div>
            @if($invoice->tenant->address)<div class="muted">{{ $invoice->tenant->address }}</div>@endif
            @if($invoice->tenant->phone)<div class="muted">{{ $invoice->tenant->phone }}</div>@endif
            @if($invoice->tenant->email)<div class="muted">{{ $invoice->tenant->email }}</div>@endif
        </div>
        <div class="invoice-meta">
            <div class="invoice-title">INVOICE</div>
            <div class="muted">{{ $invoice->invoice_number }}</div>
            <div class="status">{{ $invoice->status->label() }}</div>
        </div>
    </div>

    <div class="header">
        <div class="studio bill-to">
            <div class="label">Bill To</div>
            <div>{{ $invoice->customer->name }}</div>
            @if($invoice->customer->phone)<div class="muted">{{ $invoice->customer->phone }}</div>@endif
        </div>
        <div class="invoice-meta">
            <div><span class="muted">Issue Date:</span> {{ optional($invoice->issue_date)->format('M j, Y') }}</div>
            <div><span class="muted">Due Date:</span> {{ optional($invoice->due_date)->format('M j, Y') }}</div>
        </div>
    </div>

    <table class="items">
        <thead>
            <tr>
                <th>Item</th>
                <th class="num">Unit Price</th>
                <th class="num">Qty</th>
                <th class="num">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->items as $item)
                <tr>
                    <td>{{ $item->name }}</td>
                    <td class="num">${{ number_format($item->unit_price, 2) }}</td>
                    <td class="num">{{ $item->quantity }}</td>
                    <td class="num">${{ number_format($item->line_total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals">
        <div class="row"><div class="label muted">Subtotal</div><div class="value">${{ number_format($invoice->subtotal, 2) }}</div></div>
        <div class="row"><div class="label muted">Discount</div><div class="value">-${{ number_format($invoice->discount_amount, 2) }}</div></div>
        <div class="row"><div class="label muted">Tax ({{ number_format($invoice->tax_rate, 2) }}%)</div><div class="value">${{ number_format($invoice->tax_amount, 2) }}</div></div>
        <div class="row grand"><div class="label">Total</div><div class="value">${{ number_format($invoice->total, 2) }}</div></div>
        <div class="row"><div class="label muted">Amount Paid</div><div class="value">${{ number_format($invoice->amount_paid, 2) }}</div></div>
        <div class="row balance"><div class="label">Balance Due</div><div class="value">${{ number_format($invoice->balance_due, 2) }}</div></div>
    </div>

    @if($invoice->notes)
        <div class="notes">
            <div class="label muted" style="text-transform: uppercase; font-size: 10px;">Notes</div>
            <div>{{ $invoice->notes }}</div>
        </div>
    @endif

    @if($footer = $invoice->tenant->setting('invoice_footer'))
        <div class="footer">{{ $footer }}</div>
    @endif
</body>
</html>
