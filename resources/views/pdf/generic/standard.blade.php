@php
    $title = $documentTitle ?? strtoupper(str_replace('_', ' ', $document->document_type));
    $showPrices = $showPrices ?? true;
    $showTotals = $showTotals ?? $showPrices;
    $totalLabel = $totalLabel ?? 'Grand Total';
    $noteLabel = $noteLabel ?? 'Notes';
    $termsLabel = $termsLabel ?? 'Terms';
    $brand = $brand ?? [];
    $brandPrimary = $brand['primary'] ?? '#1a3a5c';
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 15mm 12mm 18mm 12mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; color: #222; }
        .header { border-bottom: 2px solid {{ $brandPrimary }}; padding-bottom: 8px; margin-bottom: 12px; }
        .header h1 { font-size: 14pt; margin: 0; color: {{ $brandPrimary }}; }
        .header .company-details { font-size: 8.5pt; color: #555; }
        .doc-title { text-align: right; font-size: 18pt; font-weight: bold; color: {{ $brandPrimary }}; margin-top: -18px; }
        .meta { display: table; width: 100%; margin-bottom: 12px; }
        .meta .left, .meta .right { display: table-cell; width: 50%; vertical-align: top; }
        .meta .right { text-align: right; }
        .meta label { font-weight: bold; font-size: 8pt; color: #666; text-transform: uppercase; }
        table { width: 100%; border-collapse: collapse; }
        table.items { margin-bottom: 10px; }
        table.items th { background: {{ $brandPrimary }}; color: white; padding: 6px 4px; font-size: 8pt; text-align: left; }
        table.items td { padding: 5px 4px; border-bottom: 1px solid #e0e0e0; font-size: 8.5pt; vertical-align: top; page-break-inside: avoid; }
        .text-right { text-align: right; }
        .totals { margin-left: auto; width: 42%; }
        .totals td { padding: 4px; font-size: 9.5pt; }
        .totals .grand { font-weight: bold; font-size: 11pt; border-top: 2px solid {{ $brandPrimary }}; }
        .amount-words { font-size: 8pt; font-weight: bold; margin: 8px 0; padding: 6px; background: #f5f5f5; border: 1px solid #ddd; }
        .footer-block { margin-top: 12px; font-size: 8.5pt; }
        .signature { margin-top: 34px; width: 45%; border-top: 1px solid #333; text-align: center; padding-top: 6px; font-size: 8pt; }
        .page-number { text-align: right; font-size: 7.5pt; color: #888; margin-top: 5px; }
        .page-break { page-break-before: always; }
    </style>
</head>
<body>
    @foreach($itemPages as $pageIndex => $pageItems)
        @if($pageIndex > 0)
            <div class="page-break"></div>
        @endif

        <div class="header">
            <h1>{{ $company->name }}</h1>
            <div class="company-details">
                {{ $company->address }}
                @if($company->phone) | Tel: {{ $company->phone }} @endif
                @if($company->email) | {{ $company->email }} @endif
                @if($company->registration_number) | Reg: {{ $company->registration_number }} @endif
            </div>
            <div class="doc-title">{{ $title }}</div>
        </div>

        @if($pageIndex === 0)
            <div class="meta">
                <div class="left">
                    <label>To</label>
                    <div><strong>{{ $customer->name ?? 'Walk-in Customer' }}</strong></div>
                    @if($customer?->address)<div>{{ $customer->address }}</div>@endif
                    @if($customer?->phone)<div>Tel: {{ $customer->phone }}</div>@endif
                </div>
                <div class="right">
                    <label>No</label> {{ $document->official_number ?? 'DRAFT' }}<br>
                    <label>Date</label> {{ optional($document->document_date)->format('d/m/Y') }}<br>
                    @if($document->due_date)<label>Due</label> {{ $document->due_date->format('d/m/Y') }}<br>@endif
                    <label>Currency</label> {{ $document->currency }}
                </div>
            </div>
        @endif

        <table class="items">
            <thead>
                <tr>
                    <th style="width: 7%">No</th>
                    <th>{{ $showPrices ? 'Item / Description' : 'Description' }}</th>
                    <th style="width: 11%" class="text-right">Qty</th>
                    @if($showPrices)
                        <th style="width: 14%" class="text-right">Rate</th>
                        <th style="width: 13%" class="text-right">Discount</th>
                        <th style="width: 15%" class="text-right">Amount</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @foreach($pageItems as $item)
                    <tr>
                        <td>{{ ($pageIndex * $itemsPerPage) + $loop->index + 1 }}</td>
                        <td>{{ $item->description }}</td>
                        <td class="text-right">{{ rtrim(rtrim(number_format((float) $item->quantity, 2), '0'), '.') }} {{ $item->uom }}</td>
                        @if($showPrices)
                            <td class="text-right">{{ number_format((float) $item->unit_price, 2) }}</td>
                            <td class="text-right">{{ (float) $item->discount > 0 ? number_format((float) $item->discount, 2) : '-' }}</td>
                            <td class="text-right">{{ number_format((float) $item->line_total, 2) }}</td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>

        @if($pageIndex === count($itemPages) - 1)
            @if($showTotals)
                <table class="totals">
                    <tr><td>Subtotal</td><td class="text-right">{{ number_format((float) $document->subtotal, 2) }}</td></tr>
                    @if((float) $document->discount_total > 0)
                        <tr><td>Discount</td><td class="text-right">({{ number_format((float) $document->discount_total, 2) }})</td></tr>
                    @endif
                    @if((float) $document->tax_total > 0)
                        <tr><td>Tax</td><td class="text-right">{{ number_format((float) $document->tax_total, 2) }}</td></tr>
                    @endif
                    <tr class="grand"><td>{{ $totalLabel }} ({{ $document->currency }})</td><td class="text-right">{{ number_format((float) $document->grand_total, 2) }}</td></tr>
                </table>

                @if($amountWords)
                    <div class="amount-words">{{ $amountWords }}</div>
                @endif
            @endif

            @if($document->notes)
                <div class="footer-block"><strong>{{ $noteLabel }}:</strong> {!! nl2br(e($document->notes)) !!}</div>
            @endif
            @if($document->terms)
                <div class="footer-block"><strong>{{ $termsLabel }}:</strong> {!! nl2br(e($document->terms)) !!}</div>
            @endif
            <div class="signature">Authorised Signature</div>
        @endif

        <div class="page-number">Page {{ $pageIndex + 1 }} of {{ count($itemPages) }}</div>
    @endforeach

    @include('pdf.partials.artwork-pages')
</body>
</html>
