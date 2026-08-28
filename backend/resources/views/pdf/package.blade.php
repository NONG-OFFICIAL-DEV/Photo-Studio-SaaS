<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        {{-- Same Noto Sans Khmer embedding as pdf/invoice.blade.php — see
             that file's comment for why this has to be a base64 data URI
             rather than a filesystem path. --}}
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
        .header .quote-meta { display: table-cell; vertical-align: top; text-align: right; }
        .studio-logo { max-height: 56px; max-width: 220px; margin-bottom: 8px; }
        .studio-name { font-size: 18px; font-weight: bold; margin-bottom: 4px; }
        .muted { color: #6b7280; }
        .quote-title { font-size: 22px; font-weight: bold; letter-spacing: 1px; }
        .package-name { font-size: 16px; font-weight: bold; margin: 20px 0 4px; }
        .package-description { margin-bottom: 20px; }
        table.items { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        table.items th { text-align: left; border-bottom: 2px solid #1f2937; padding: 6px 4px; font-size: 11px; text-transform: uppercase; }
        table.items td { padding: 6px 4px; border-bottom: 1px solid #e5e7eb; }
        table.items th.num, table.items td.num { text-align: right; }
        .optional-tag { font-size: 10px; color: #6b7280; }
        .totals { width: 260px; margin-left: auto; }
        .totals .row { display: table; width: 100%; }
        .totals .row .label, .totals .row .value { display: table-cell; padding: 3px 0; }
        .totals .row .value { text-align: right; }
        .totals .grand { font-size: 15px; font-weight: bold; border-top: 2px solid #1f2937; padding-top: 6px; }
        .footer { margin-top: 32px; padding-top: 12px; border-top: 1px solid #e5e7eb; font-size: 10px; color: #6b7280; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <div class="studio">
            @if($logoDataUri)
                <img class="studio-logo" src="{{ $logoDataUri }}" alt="{{ $package->tenant->name }}">
            @endif
            <div class="studio-name">{{ $package->tenant->name }}</div>
            @if($package->tenant->address)<div class="muted">{{ $package->tenant->address }}</div>@endif
            @if($package->tenant->phone)<div class="muted">{{ $package->tenant->phone }}</div>@endif
            @if($package->tenant->email)<div class="muted">{{ $package->tenant->email }}</div>@endif
        </div>
        <div class="quote-meta">
            <div class="quote-title">PACKAGE QUOTE</div>
        </div>
    </div>

    <div class="package-name">{{ $package->name }}</div>
    @if($package->description)
        <div class="package-description">{{ $package->description }}</div>
    @endif

    @if($package->components->isNotEmpty())
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
                @foreach($package->components as $component)
                    <tr>
                        <td>
                            {{ $component->name }}
                            @if($component->is_optional)<span class="optional-tag"> (optional)</span>@endif
                        </td>
                        <td class="num">${{ number_format($component->unit_price, 2) }}</td>
                        <td class="num">{{ $component->quantity }}</td>
                        <td class="num">${{ number_format($component->line_total, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="totals">
        @if($package->override_price === null)
            <div class="row"><div class="label muted">Components Total</div><div class="value">${{ number_format($package->component_total, 2) }}</div></div>
            @if($package->discount_value)
                <div class="row">
                    <div class="label muted">Discount</div>
                    <div class="value">
                        -{{ $package->discount_type?->value === 'percent' ? number_format($package->discount_value, 2).'%' : '$'.number_format($package->discount_value, 2) }}
                    </div>
                </div>
            @endif
        @endif
        <div class="row grand"><div class="label">Price</div><div class="value">${{ number_format($package->final_price, 2) }}</div></div>
    </div>

    <div class="footer">
        Interested? Contact us to book!
        @if($footer = $package->tenant->setting('invoice_footer'))
            <br>{{ $footer }}
        @endif
    </div>
</body>
</html>
