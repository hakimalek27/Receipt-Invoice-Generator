@php
    $cur = $currencyLabel ?? 'RM';
    $showPrices = $showPrices ?? true;
    $startIndex = $startIndex ?? 0;
    $isLastPage = $isLastPage ?? false;
    $amountReceived = $amountReceived ?? false;
    $colCount = $showPrices ? 5 : 3;
@endphp
<table class="pgg-items">
    <thead>
        <tr>
            <th class="pgg-c-no">NO</th>
            <th class="pgg-c-desc">DESCRIPTION</th>
            <th class="pgg-c-qty">QTY</th>
            @if($showPrices)
                <th class="pgg-c-price">PRICE ({{ $cur }})</th>
                <th class="pgg-c-total">TOTAL ({{ $cur }})</th>
            @endif
        </tr>
    </thead>
    <tbody>
    @foreach($pageItems as $item)
        @php
            $lines = preg_split('/\r\n|\r|\n/', (string) ($item->description ?? ''));
            $headerLines = [];
            $bulletLines = [];
            $bulletStarted = false;
            foreach ($lines as $line) {
                $trimmed = ltrim($line);
                if (preg_match('/^[-*\x{2022}]\s+/u', $trimmed)) {
                    $bulletStarted = true;
                    $bulletLines[] = preg_replace('/^[-*\x{2022}]\s+/u', '', $trimmed);
                } elseif ($bulletStarted && trim($line) === '') {
                    continue;
                } elseif ($bulletStarted) {
                    $bulletLines[] = $trimmed;
                } elseif (trim($line) !== '') {
                    $headerLines[] = $line;
                }
            }
            $hasImage = ! empty($item->image_url) && str_starts_with((string) $item->image_url, 'data:image/');
            $isProduct = $hasImage || ! empty($bulletLines);
            $uom = trim((string) ($item->uom ?? ''));
            $uomNote = ($uom !== '' && ! in_array(strtolower($uom), ['unit', 'units', 'pcs', 'pc', 'nos', 'no', 'item', 'items'], true)) ? $uom : null;
            $qtyDisplay = rtrim(rtrim(number_format((float) $item->quantity, 2), '0'), '.');
        @endphp
        @if(! empty($item->section_header))
            <tr class="pgg-section"><td colspan="{{ $colCount }}">{{ $item->section_header }}</td></tr>
        @endif
        <tr>
            <td class="pgg-c-no">{{ $startIndex + $loop->iteration }}</td>
            <td class="pgg-c-desc">
                @if($isProduct)
                    @foreach($headerLines as $hl)
                        <div class="pgg-prod-title">{{ $hl }}</div>
                    @endforeach
                    @if($hasImage)
                        <div class="pgg-prod-img"><img src="{{ $item->image_url }}" alt=""></div>
                    @endif
                    @if(! empty($bulletLines))
                        <ul class="pgg-bullets">
                            @foreach($bulletLines as $b)<li>- {{ $b }}</li>@endforeach
                        </ul>
                    @endif
                @else
                    @foreach($headerLines as $hl)
                        <div class="pgg-svc-title">{{ $hl }}</div>
                    @endforeach
                @endif
            </td>
            <td class="pgg-c-qty">{{ $qtyDisplay }}</td>
            @if($showPrices)
                <td class="pgg-c-price">
                    {{ $cur }} {{ number_format((float) $item->unit_price, 2) }}
                    @if($uomNote)<span class="pgg-price-note">({{ $uomNote }})</span>@endif
                </td>
                <td class="pgg-c-total">
                    {{ $cur }} {{ number_format((float) $item->line_total, 2) }}
                    @if($uomNote)<span class="pgg-price-note">({{ $uomNote }})</span>@endif
                </td>
            @endif
        </tr>
    @endforeach

    @if($isLastPage && $showPrices)
        @if((float) $document->subtotal !== (float) $document->grand_total)
            <tr class="pgg-summary">
                <td class="pgg-sum-label" colspan="4">Subtotal</td>
                <td class="pgg-sum-val">{{ $cur }} {{ number_format((float) $document->subtotal, 2) }}</td>
            </tr>
            @if((float) $document->discount_total > 0)
                <tr class="pgg-summary">
                    <td class="pgg-sum-label" colspan="4">Discount</td>
                    <td class="pgg-sum-val">({{ $cur }} {{ number_format((float) $document->discount_total, 2) }})</td>
                </tr>
            @endif
            @if((float) $document->tax_total > 0)
                <tr class="pgg-summary">
                    <td class="pgg-sum-label" colspan="4">SST / Tax</td>
                    <td class="pgg-sum-val">{{ $cur }} {{ number_format((float) $document->tax_total, 2) }}</td>
                </tr>
            @endif
        @endif
        <tr class="pgg-grand">
            <td class="pgg-sum-label" colspan="4">GRAND TOTAL</td>
            <td class="pgg-sum-val">{{ $cur }} {{ number_format((float) $document->grand_total, 2) }}</td>
        </tr>
        @if($amountReceived)
            <tr class="pgg-grand">
                <td class="pgg-sum-label" colspan="4">AMOUNT RECEIVED</td>
                <td class="pgg-sum-val">{{ $cur }} {{ number_format((float) ($payment['amount'] ?? $document->grand_total), 2) }}</td>
            </tr>
        @endif
    @endif
    </tbody>
</table>
