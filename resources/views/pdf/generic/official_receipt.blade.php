<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
        .header { border-bottom: 2px solid #111; padding-bottom: 12px; margin-bottom: 18px; }
        .title { font-size: 24px; font-weight: bold; text-align: right; }
        .meta, .items, .totals { width: 100%; border-collapse: collapse; }
        .meta td { vertical-align: top; padding: 3px 0; }
        .items th, .items td { border: 1px solid #333; padding: 7px; }
        .items th { background: #f2f2f2; }
        .right { text-align: right; }
        .totals td { padding: 5px; }
        .signature { margin-top: 60px; width: 45%; border-top: 1px solid #111; text-align: center; padding-top: 8px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">OFFICIAL RECEIPT</div>
        <strong>{{ $company->name ?? 'Company' }}</strong><br>
        {{ $company->address ?? '' }}<br>
        {{ $company->phone ?? '' }} {{ $company->email ?? '' }}
    </div>

    <table class="meta">
        <tr>
            <td>
                <strong>Received From</strong><br>
                {{ $customer->name ?? 'Walk-in Customer' }}<br>
                {{ $customer->address ?? '' }}
            </td>
            <td class="right">
                <strong>No:</strong> {{ $document->official_number }}<br>
                <strong>Date:</strong> {{ optional($document->document_date)->format('d/m/Y') }}<br>
                <strong>Currency:</strong> {{ $document->currency }}
            </td>
        </tr>
    </table>

    <br>
    <table class="items">
        <thead>
            <tr>
                <th style="width: 8%">No</th>
                <th>Description</th>
                <th style="width: 18%" class="right">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($items as $item)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $item->description }}</td>
                    <td class="right">{{ number_format((float) $item->line_total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td class="right"><strong>Total Received: {{ $document->currency }} {{ number_format((float) $document->grand_total, 2) }}</strong></td>
        </tr>
    </table>

    @if ($amountWords)
        <p><strong>Amount in words:</strong> {{ $amountWords }}</p>
    @endif

    <div class="signature">Authorised Signature</div>
</body>
</html>
