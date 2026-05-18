<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 13mm 12mm 18mm 12mm; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10pt; color: #111; }
        .top-band { height: 14px; background: #16427a; border-radius: 0 10px 10px 0; margin: -5mm 0 12px -12mm; }
        .header { display: table; width: 100%; border-bottom: 1.5px solid #18345d; padding-bottom: 10px; margin-bottom: 12px; }
        .logo-mark { display: table-cell; width: 70px; vertical-align: middle; }
        .logo-box { width: 54px; height: 54px; border: 7px solid #16427a; color: #149653; font-size: 20pt; font-weight: bold; text-align: center; line-height: 42px; }
        .company { display: table-cell; vertical-align: middle; }
        .company h1 { margin: 0; font-size: 15pt; letter-spacing: 0; }
        .company .details { font-size: 8.5pt; line-height: 1.35; }
        .contact { display: table-cell; width: 34%; vertical-align: middle; font-size: 8.5pt; line-height: 1.5; }
        .doc-title { text-align: right; color: #16427a; font-size: 19pt; font-weight: bold; margin: 8px 0; }
        .meta { display: table; width: 100%; margin: 10px 0; }
        .meta .cell { display: table-cell; width: 50%; vertical-align: top; }
        .right { text-align: right; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 8px; }
        .items th { background: #16427a; color: white; padding: 6px 5px; font-size: 8pt; text-align: left; }
        .items td { border-bottom: 1px solid #d8dee8; padding: 5px; font-size: 8.5pt; vertical-align: top; page-break-inside: avoid; }
        .totals { width: 42%; margin-left: auto; border-collapse: collapse; margin-top: 10px; }
        .totals td { padding: 4px 5px; }
        .grand { color: #16427a; font-weight: bold; border-top: 2px solid #16427a; }
        .amount-words { margin-top: 8px; border: 1px solid #16427a; padding: 6px; font-size: 8pt; font-weight: bold; }
        .signature { margin-top: 36px; display: table; width: 100%; }
        .signature .cell { display: table-cell; width: 50%; font-size: 8pt; }
        .stamp { color: #16427a; border: 2px solid #16427a; border-radius: 50%; width: 80px; height: 80px; text-align: center; line-height: 1.1; font-size: 8pt; padding-top: 18px; margin-left: auto; }
        .page-number { text-align: right; font-size: 7.5pt; color: #666; margin-top: 6px; }
        .page-break { page-break-before: always; }
    </style>
</head>
<body>
    @foreach($itemPages as $pageIndex => $pageItems)
        @if($pageIndex > 0)<div class="page-break"></div>@endif
        <div class="top-band"></div>
        <div class="header">
            <div class="logo-mark"><div class="logo-box">PG</div></div>
            <div class="company">
                <h1>{{ $company->name }}</h1>
                <div class="details">
                    {{ $company->registration_number }}<br>
                    {{ $company->address }}
                </div>
            </div>
            <div class="contact">
                @if($company->phone) Tel: {{ $company->phone }}<br>@endif
                @if($company->email) {{ $company->email }}<br>@endif
            </div>
        </div>

        <div class="doc-title">INVOICE</div>

        @if($pageIndex === 0)
            <div class="meta">
                <div class="cell">
                    <strong>Bill To</strong><br>
                    {{ $customer->name ?? 'Walk-in Customer' }}<br>
                    {{ $customer->address ?? '' }}
                </div>
                <div class="cell right">
                    <strong>No:</strong> {{ $document->official_number ?? 'DRAFT' }}<br>
                    <strong>Date:</strong> {{ optional($document->document_date)->format('d/m/Y') }}<br>
                    <strong>Currency:</strong> {{ $document->currency }}
                </div>
            </div>
        @endif

        <table class="items">
            <thead>
                <tr>
                    <th style="width: 7%">No</th>
                    <th>Description</th>
                    <th style="width: 10%" class="right">Qty</th>
                    <th style="width: 14%" class="right">Rate</th>
                    <th style="width: 15%" class="right">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach($pageItems as $item)
                    <tr>
                        <td>{{ ($pageIndex * $itemsPerPage) + $loop->index + 1 }}</td>
                        <td>{{ $item->description }}</td>
                        <td class="right">{{ rtrim(rtrim(number_format((float) $item->quantity, 2), '0'), '.') }}</td>
                        <td class="right">{{ number_format((float) $item->unit_price, 2) }}</td>
                        <td class="right">{{ number_format((float) $item->line_total, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        @if($pageIndex === count($itemPages) - 1)
            <table class="totals">
                <tr><td>Subtotal</td><td class="right">{{ number_format((float) $document->subtotal, 2) }}</td></tr>
                @if((float) $document->discount_total > 0)<tr><td>Discount</td><td class="right">({{ number_format((float) $document->discount_total, 2) }})</td></tr>@endif
                @if((float) $document->tax_total > 0)<tr><td>Tax</td><td class="right">{{ number_format((float) $document->tax_total, 2) }}</td></tr>@endif
                <tr class="grand"><td>Grand Total ({{ $document->currency }})</td><td class="right">{{ number_format((float) $document->grand_total, 2) }}</td></tr>
            </table>
            @if($amountWords)<div class="amount-words">{{ $amountWords }}</div>@endif
            <div class="signature">
                <div class="cell">________________________<br>Customer</div>
                <div class="cell right"><div class="stamp">{{ $company->name }}<br>CHOP</div></div>
            </div>
        @endif

        <div class="page-number">Page {{ $pageIndex + 1 }} of {{ count($itemPages) }}</div>
    @endforeach
    @include('pdf.partials.artwork-pages')
</body>
</html>
