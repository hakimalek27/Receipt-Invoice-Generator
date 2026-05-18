<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 14mm 12mm 18mm 12mm; }
        body { font-family: Arial, DejaVu Sans, sans-serif; font-size: 10pt; color: #111; }
        .header { border-bottom: 2px solid #222; padding-bottom: 8px; margin-bottom: 14px; }
        .company { font-size: 16pt; font-weight: bold; }
        .details { font-size: 8.5pt; color: #444; }
        .title { text-align: right; font-size: 20pt; font-weight: bold; margin-top: -20px; }
        .meta { display: table; width: 100%; margin: 12px 0; }
        .meta .cell { display: table-cell; width: 50%; vertical-align: top; }
        .right { text-align: right; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 8px; }
        .items th { background: #d9e2f3; border: 1px solid #777; padding: 6px 4px; font-size: 8.5pt; }
        .items td { border: 1px solid #999; padding: 5px 4px; font-size: 8.5pt; vertical-align: top; page-break-inside: avoid; }
        .totals { width: 42%; margin-left: auto; border-collapse: collapse; margin-top: 10px; }
        .totals td { border: 1px solid #999; padding: 5px; }
        .grand { background: #d9e2f3; font-weight: bold; }
        .amount-words { margin-top: 8px; border: 1px solid #999; padding: 6px; font-size: 8pt; font-weight: bold; }
        .signature { margin-top: 36px; width: 42%; border-top: 1px solid #222; text-align: center; padding-top: 5px; font-size: 8pt; }
        .page-number { text-align: right; font-size: 7.5pt; color: #666; margin-top: 6px; }
        .page-break { page-break-before: always; }
    </style>
</head>
<body>
    @foreach($itemPages as $pageIndex => $pageItems)
        @if($pageIndex > 0)<div class="page-break"></div>@endif
        <div class="header">
            <div class="company">{{ $company->name }}</div>
            <div class="details">
                {{ $company->address }}
                @if($company->phone) | Tel: {{ $company->phone }} @endif
                @if($company->registration_number) | {{ $company->registration_number }} @endif
            </div>
            <div class="title">{{ $documentTitle ?? 'INVOICE' }}</div>
        </div>

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
                    <th>Item / Description</th>
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
                <tr class="grand"><td>Total ({{ $document->currency }})</td><td class="right">{{ number_format((float) $document->grand_total, 2) }}</td></tr>
            </table>
            @if($amountWords)<div class="amount-words">{{ $amountWords }}</div>@endif
            @if($document->terms)<p><strong>Terms:</strong> {!! nl2br(e($document->terms)) !!}</p>@endif
            <div class="signature">Authorised Signature</div>
        @endif

        <div class="page-number">Page {{ $pageIndex + 1 }} of {{ count($itemPages) }}</div>
    @endforeach
    @include('pdf.partials.artwork-pages')
</body>
</html>
