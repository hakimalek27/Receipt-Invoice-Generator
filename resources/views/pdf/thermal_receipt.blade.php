<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $document->official_number ?? strtoupper(str_replace('_', ' ', $document->document_type)) }}</title>
    <style>
        @page { size: 60mm auto; margin: 3mm 2mm; }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 8.5pt;
            color: #000;
            line-height: 1.35;
            margin: 0;
        }
        .c { text-align: center; }
        .r { text-align: right; }
        .b { font-weight: bold; }
        /* Right-aligned cells get a tiny left padding so the numbers
           never butt up against the previous column on narrow 60mm. */
        .cell.r { padding-left: 4px; }
        .hr {
            border-top: 1px dashed #000;
            margin: 5px 0;
            height: 0;
        }
        .co-name {
            font-size: 11pt;
            font-weight: bold;
        }
        .doc-title {
            font-size: 11pt;
            font-weight: bold;
            margin: 8px 0 6px;
        }
        .row {
            display: table;
            width: 100%;
        }
        .cell {
            display: table-cell;
            vertical-align: top;
        }
        .meta-row {
            margin-top: 1px;
        }
        .meta-cell {
            display: table-cell;
            width: 50%;
        }
        .item-row {
            margin: 3px 0 4px;
        }
        .totals .row {
            margin: 1px 0;
        }
        .footer {
            text-align: center;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    {{-- Company header (centered) --}}
    @php
        $addr1 = trim((string) ($company->address ?? ''));
        $addr2 = trim((string) ($company->address_line_2 ?? ''));
        if ($addr2 !== '' && stripos($addr1, $addr2) !== false) {
            $addr2 = '';
        }
        $postcodeCity = trim(implode(', ', array_filter([
            trim((string) ($company->postcode ?? '')),
            trim((string) ($company->city ?? '')),
        ])));
        $stateLine = trim((string) ($company->state ?? ''));
        $countryName = match (strtoupper((string) ($company->country ?? ''))) {
            'MY' => 'Malaysia', 'SG' => 'Singapore', default => $company->country ?? null,
        };
        // Skip stateLine if the post-code line already contains it.
        $regionLine = trim(implode(', ', array_filter([
            $stateLine !== '' ? $stateLine : null,
            $countryName,
        ])));
    @endphp
    <div class="c">
        <div class="co-name">{{ strtoupper($company->name ?? '') }}</div>
        @if(!empty($company->registration_number))
            <div>({{ $company->registration_number }})</div>
        @endif
        @if($addr1 !== '')<div>{{ $addr1 }}</div>@endif
        @if($addr2 !== '')<div>{{ $addr2 }}</div>@endif
        @if($postcodeCity !== '')<div>{{ $postcodeCity }}</div>@endif
        @if($regionLine !== '')<div>{{ $regionLine }}</div>@endif
    </div>

    <div class="c doc-title">{{ strtoupper(str_replace('_', ' ', $document->document_type)) }}</div>

    {{-- Meta block — stack vertically so long values (number, customer
         name) get the full 56mm content width instead of fighting for
         half of a two-column row. --}}
    @php
        $docNoLabels = [
            'cash_bill' => 'Bill No:',
            'official_receipt' => 'Receipt No:',
            'payment_voucher' => 'Voucher No:',
        ];
        $docNoLabel = $docNoLabels[$document->document_type] ?? 'Doc No:';
    @endphp
    <div><span class="b">{{ $docNoLabel }}</span> {{ $document->official_number ?? '-' }}</div>
    <div><span class="b">Date:</span> {{ optional($document->document_date)->format('d/m/Y') }}</div>
    <div><span class="b">Cust:</span> {{ $customer?->name ?? 'Cash Sales' }}</div>
    @if(!empty($payment['method']))
        <div><span class="b">Pay By:</span> {{ ucfirst(str_replace('_', ' ', $payment['method'])) }}</div>
    @endif

    <div class="hr"></div>

    {{-- Items header --}}
    <div class="row b">
        <div class="cell" style="width: 30%;">Item</div>
        <div class="cell r" style="width: 15%;">Qty</div>
        <div class="cell r" style="width: 25%;">Price</div>
        <div class="cell r" style="width: 30%;">Total</div>
    </div>

    <div class="hr"></div>

    {{-- Items --}}
    @foreach($items as $item)
        @php
            $lineTotal = (float) ($item->line_total ?? (($item->quantity ?? 0) * ($item->unit_price ?? 0) - ($item->discount ?? 0)));
            $code = $item->product?->sku ?? '';
            $qtyDisplay = rtrim(rtrim(number_format((float) ($item->quantity ?? 0), 2), '0'), '.');
        @endphp
        <div class="item-row">
            <div>{{ $item->description }}</div>
            <div class="row">
                <div class="cell" style="width: 30%;">{{ $code }}</div>
                <div class="cell r" style="width: 15%;">{{ $qtyDisplay }}</div>
                <div class="cell r" style="width: 25%;">{{ number_format((float) ($item->unit_price ?? 0), 2) }}</div>
                <div class="cell r" style="width: 30%;">{{ number_format($lineTotal, 2) }}</div>
            </div>
        </div>
    @endforeach

    <div class="hr"></div>

    {{-- Totals --}}
    @php
        $totalQty = (float) $items->sum('quantity');
        $totalQtyDisplay = rtrim(rtrim(number_format($totalQty, 2), '0'), '.');
        $grandTotal = (float) $document->grand_total;
        $paymentAmount = isset($payment['amount']) ? (float) $payment['amount'] : null;
        $changeDue = $paymentAmount !== null ? max(0, $paymentAmount - $grandTotal) : null;
        $currency = $document->currency ?? 'MYR';
    @endphp
    <div class="totals">
        <div class="row">
            <div class="cell" style="width: 60%;">Total Item Qty</div>
            <div class="cell r">{{ $totalQtyDisplay }}</div>
        </div>
        <div class="row">
            <div class="cell" style="width: 60%;">Total ({{ $currency }})</div>
            <div class="cell r">{{ number_format($grandTotal, 2) }}</div>
        </div>
        <div class="row">
            <div class="cell" style="width: 60%;">Rounding ({{ $currency }})</div>
            <div class="cell r">0.00</div>
        </div>
        <div class="row b">
            <div class="cell" style="width: 60%;">Amount Due ({{ $currency }})</div>
            <div class="cell r">{{ number_format($grandTotal, 2) }}</div>
        </div>
        @if($paymentAmount !== null)
            <div class="row">
                <div class="cell" style="width: 60%;">Payment ({{ $currency }})</div>
                <div class="cell r">{{ number_format($paymentAmount, 2) }}</div>
            </div>
            <div class="row">
                <div class="cell" style="width: 60%;">Change Due ({{ $currency }})</div>
                <div class="cell r">{{ number_format($changeDue, 2) }}</div>
            </div>
        @endif
    </div>

    {{-- Footer --}}
    <div class="footer">
        <div>Thank You</div>
        <div>{{ now()->setTimezone('Asia/Kuala_Lumpur')->format('d/m/Y h:i:s A') }}</div>
    </div>
</body>
</html>
