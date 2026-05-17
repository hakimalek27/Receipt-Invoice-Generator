<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 12mm 10mm 15mm 10mm; }
        body { font-family: 'Lao UI', 'Rockwell', serif; font-size: 10pt; color: #1a1a1a; }
        .header { background: #1a3a5c; color: white; padding: 12px 15px; margin-bottom: 10px; }
        .header h1 { font-size: 16pt; margin: 0; }
        .header .subtitle { font-size: 8pt; opacity: 0.8; }
        .meta { display: table; width: 100%; margin-bottom: 8px; }
        .meta .col { display: table-cell; width: 50%; vertical-align: top; padding: 6px; }
        .meta label { font-weight: bold; font-size: 7pt; color: #1a3a5c; }
        table.items { width: 100%; border-collapse: collapse; margin: 8px 0; }
        table.items th { background: #1a3a5c; color: white; padding: 6px 5px; font-size: 8pt; text-align: left; }
        table.items td { padding: 5px; border-bottom: 1px solid #e0e0e0; font-size: 9pt; }
        table.items .r { text-align: right; }
        .totals { margin-left: auto; width: 42%; }
        .totals td { padding: 3px 6px; font-size: 10pt; }
        .totals .grand { font-weight: bold; font-size: 12pt; border-top: 2px solid #1a3a5c; border-bottom: 2px solid #1a3a5c; }
        .amount-words { font-weight: bold; font-size: 7.5pt; margin: 6px 0; padding: 4px 8px; background: #f0f4f8; border: 1px solid #c0d0e0; }
        .footer { margin-top: 10px; padding-top: 6px; border-top: 1px solid #ddd; font-size: 7pt; text-align: center; }
        .page-number { text-align: right; font-size: 7pt; color: #888; }
        .page-break { page-break-before: always; }
    </style>
</head>
<body>
    @foreach($itemPages as $pageIndex => $pageItems)
        @if($pageIndex > 0)<div class="page-break"></div>@endif
        <div class="header">
            <h1>{{ $company->name }}</h1>
            <div class="subtitle">{{ $company->registration_number }} | {{ $company->address }}</div>
        </div>
        @if($pageIndex === 0)
        <div class="meta">
            <div class="col">
                <label>Kepada</label>
                <div><strong>{{ $customer->name ?? 'Walk-in' }}</strong></div>
                @if($customer?->address)<div>{{ $customer->address }}</div>@endif
            </div>
            <div class="col" style="text-align:right;">
                <table style="width:100%; font-size:9pt;">
                    <tr><td><label>Jenis</label></td><td><strong>SEBUT HARGA</strong></td></tr>
                    <tr><td><label>No</label></td><td>{{ $document->official_number ?? 'DRAFT' }}</td></tr>
                    <tr><td><label>Tarikh</label></td><td>{{ optional($document->document_date)->format('d/m/Y') }}</td></tr>
                </table>
            </div>
        </div>
        @endif
        <table class="items">
            <thead>
                <tr><th>#</th><th>Item / Keterangan</th><th class="r">Qty</th><th class="r">Harga Unit</th><th class="r">Diskaun</th><th class="r">Jumlah</th></tr>
            </thead>
            <tbody>
                @foreach($pageItems as $item)
                <tr>
                    <td>{{ $loop->parent->index * 15 + $loop->index + 1 }}</td>
                    <td>{{ $item->description }}</td>
                    <td class="r">{{ rtrim(rtrim(number_format($item->quantity, 2), '0'), '.') }}</td>
                    <td class="r">{{ number_format($item->unit_price, 2) }}</td>
                    <td class="r">{{ $item->discount > 0 ? number_format($item->discount, 2) : '-' }}</td>
                    <td class="r">{{ number_format($item->line_total, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @if($pageIndex === count($itemPages) - 1)
        <table class="totals">
            <tr><td>Jumlah</td><td class="r">{{ number_format($document->subtotal, 2) }}</td></tr>
            <tr class="grand"><td><strong>JUMLAH SEBUT HARGA</strong></td><td class="r"><strong>{{ number_format($document->grand_total, 2) }}</strong></td></tr>
        </table>
        @if($amountWords)<div class="amount-words">{{ $amountWords }}</div>@endif
        @endif
        <div class="page-number">Muka {{ $pageIndex + 1 }} / {{ count($itemPages) }}</div>
    @endforeach
    <div class="footer">Dokumen janaan komputer</div>
</body>
</html>
