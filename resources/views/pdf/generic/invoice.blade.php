<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 15mm 12mm 20mm 12mm; }
        body { font-family: sans-serif; font-size: 10pt; color: #222; }
        .header { border-bottom: 2px solid #1a3a5c; padding-bottom: 8px; margin-bottom: 12px; }
        .header h1 { font-size: 14pt; margin: 0; color: #1a3a5c; }
        .header .company-details { font-size: 9pt; color: #555; }
        .meta { display: flex; justify-content: space-between; margin-bottom: 12px; }
        .meta .left, .meta .right { width: 48%; }
        .meta label { font-weight: bold; font-size: 8pt; color: #888; text-transform: uppercase; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        table.items th { background: #1a3a5c; color: white; padding: 6px 4px; font-size: 9pt; text-align: left; }
        table.items td { padding: 5px 4px; border-bottom: 1px solid #e0e0e0; font-size: 9pt; }
        table.items .text-right { text-align: right; }
        .totals { margin-left: auto; width: 40%; }
        .totals td { padding: 4px; font-size: 10pt; }
        .totals .grand { font-weight: bold; font-size: 12pt; border-top: 2px solid #1a3a5c; }
        .amount-words { font-size: 8pt; font-weight: bold; margin: 8px 0; padding: 6px; background: #f5f5f5; border: 1px solid #ddd; }
        .footer { margin-top: 20px; padding-top: 10px; border-top: 1px solid #ddd; font-size: 8pt; }
        .page-number { text-align: center; font-size: 8pt; color: #888; margin-top: 5px; }
        .page-break { page-break-before: always; }
    </style>
</head>
<body>
    @foreach($itemPages as $pageIndex => $pageItems)
        @if($pageIndex > 0)
            <div class="page-break"></div>
        @endif

        {{-- Header on every page --}}
        <div class="header">
            <h1>{{ $company->name }}</h1>
            <div class="company-details">
                {{ $company->address }} |
                @if($company->phone) Tel: {{ $company->phone }} | @endif
                @if($company->registration_number) Reg: {{ $company->registration_number }} @endif
            </div>
        </div>

        {{-- Meta only on first page --}}
        @if($pageIndex === 0)
        <div class="meta">
            <div class="left">
                <label>Bill To</label>
                <div>{{ $customer->name ?? 'Walk-in Customer' }}</div>
                @if($customer?->address)<div>{{ $customer->address }}</div>@endif
                @if($customer?->phone)<div>Tel: {{ $customer->phone }}</div>@endif
            </div>
            <div class="right">
                <table>
                    <tr><td><label>Document</label></td><td>{{ strtoupper($document->document_type) }}</td></tr>
                    <tr><td><label>Number</label></td><td>{{ $document->official_number ?? 'DRAFT' }}</td></tr>
                    <tr><td><label>Date</label></td><td>{{ optional($document->document_date)->format('d/m/Y') }}</td></tr>
                    @if($document->due_date)<tr><td><label>Due</label></td><td>{{ $document->due_date->format('d/m/Y') }}</td></tr>@endif
                </table>
            </div>
        </div>
        @endif

        {{-- Items table header (repeated on every page) --}}
        <table class="items">
            <thead>
                <tr>
                    <th style="width:6%">#</th>
                    <th style="width:42%">Description</th>
                    <th style="width:10%" class="text-right">Qty</th>
                    <th style="width:14%" class="text-right">Unit Price</th>
                    <th style="width:14%" class="text-right">Discount</th>
                    <th style="width:14%" class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($pageItems as $item)
                <tr>
                    <td>{{ $loop->parent->index * 15 + $loop->index + 1 }}</td>
                    <td>{{ $item->description }}</td>
                    <td class="text-right">{{ rtrim(rtrim(number_format($item->quantity, 2), '0'), '.') }}</td>
                    <td class="text-right">{{ number_format($item->unit_price, 2) }}</td>
                    <td class="text-right">{{ $item->discount > 0 ? number_format($item->discount, 2) : '-' }}</td>
                    <td class="text-right">{{ number_format($item->line_total, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        {{-- Totals only on last page --}}
        @if($pageIndex === count($itemPages) - 1)
        <table class="totals">
            <tr><td>Subtotal</td><td class="text-right">{{ number_format($document->subtotal, 2) }}</td></tr>
            @if($document->discount_total > 0)
            <tr><td>Discount</td><td class="text-right">({{ number_format($document->discount_total, 2) }})</td></tr>
            @endif
            @if($document->tax_total > 0)
            <tr><td>Tax</td><td class="text-right">{{ number_format($document->tax_total, 2) }}</td></tr>
            @endif
            <tr class="grand"><td>Grand Total ({{ $document->currency }})</td><td class="text-right">{{ number_format($document->grand_total, 2) }}</td></tr>
        </table>

        {{-- Amount in Words --}}
        @if($amountWords)
        <div class="amount-words">
            {{ $amountWords }}
        </div>
        @endif

        {{-- Notes and Terms --}}
        @if($document->notes)
        <div style="margin-top:10px; font-size:9pt;"><strong>Notes:</strong> {!! nl2br(e($document->notes)) !!}</div>
        @endif
        @if($document->terms)
        <div style="font-size:8pt; color:#666;"><strong>Terms:</strong> {!! nl2br(e($document->terms)) !!}</div>
        @endif
        @endif

        {{-- Page number --}}
        <div class="page-number">Page {{ $pageIndex + 1 }} of {{ count($itemPages) }}</div>
    @endforeach

    {{-- Footer only after all main pages --}}
    <div class="footer">
        This is a computer-generated document. | Generated: {{ now()->setTimezone('Asia/Kuala_Lumpur')->format('d/m/Y h:i A') }} MYT
    </div>
</body>
</html>
