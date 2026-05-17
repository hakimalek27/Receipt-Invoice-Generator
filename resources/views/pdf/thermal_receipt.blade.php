<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { size: 60mm auto; margin: 2mm; }
        body { font-family: monospace; font-size: 7pt; width: 56mm; }
        .center { text-align: center; }
        .line { border-top: 1px dashed #000; margin: 4px 0; }
        table { width: 100%; font-size: 7pt; }
        table.items td { padding: 2px 1px; }
        .right { text-align: right; }
        .total { font-weight: bold; font-size: 9pt; }
    </style>
</head>
<body>
    <div class="center">
        <strong>{{ $company->name }}</strong><br>
        @if($company->registration_number){{ $company->registration_number }}<br>@endif
        {{ $company->phone }}<br>
        <div class="line"></div>
        <strong>{{ strtoupper($document->document_type) }}</strong><br>
        {{ $document->official_number ?? 'DRAFT' }}<br>
        {{ optional($document->document_date)->format('d/m/Y h:i A') }}<br>
        <div class="line"></div>
    </div>

    <table class="items">
        @foreach($items as $item)
        <tr>
            <td colspan="2">{{ $item->description }}</td>
        </tr>
        <tr>
            <td>{{ rtrim(rtrim(number_format($item->quantity, 2), '0'), '.') }} x {{ number_format($item->unit_price, 2) }}</td>
            <td class="right">{{ number_format($item->line_total, 2) }}</td>
        </tr>
        @endforeach
    </table>

    <div class="line"></div>
    <div class="center">
        <div class="total">TOTAL: {{ $document->currency }} {{ number_format($document->grand_total, 2) }}</div>
        @if($amountWords)
        <div style="font-size:6pt; margin-top:4px;">{{ $amountWords }}</div>
        @endif
        <div class="line"></div>
        <small>Thank you<br>{{ now()->setTimezone('Asia/Kuala_Lumpur')->format('d/m/Y h:i A') }}</small>
    </div>
</body>
</html>
