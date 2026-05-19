<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $document->official_number ?? 'INVOICE' }}</title>
    @include('pdf.partials.wehdah-styles')
</head>
<body>
@foreach($itemPages as $pageIndex => $pageItems)
    @if($pageIndex > 0)<div class="page-break"></div>@endif

    @if($pageIndex === 0)
        @include('pdf.partials.wehdah-header', [
            'variant' => 'full',
            'documentTitle' => 'INVOICE',
            'tagline' => 'Masjid & Community Services',
        ])
        <div class="wehdah-meta-row">
            <div class="wehdah-meta-col wehdah-meta-col-left">
                @include('pdf.partials.wehdah-customer', ['label' => 'Bill To:'])
            </div>
            <div class="wehdah-meta-col wehdah-meta-col-right">
                @include('pdf.partials.wehdah-meta', ['documentTitleEn' => 'INVOICE', 'showValidity' => false])
            </div>
        </div>
    @else
        @include('pdf.partials.wehdah-header', ['variant' => 'compact', 'documentTitle' => 'INVOICE'])
    @endif

    <table class="wehdah-items">
        <thead>
            <tr>
                <th class="wehdah-items-num">Item</th>
                <th class="wehdah-items-desc">Description</th>
                <th class="r wehdah-items-qty">Qty</th>
                <th class="wehdah-items-unit">Unit</th>
                <th class="r wehdah-items-price">Unit Price</th>
                <th class="r wehdah-items-discount">Discount</th>
                <th class="r wehdah-items-total">Total Price</th>
            </tr>
        </thead>
        <tbody>
            @foreach($pageItems as $item)
                @include('pdf.partials.section-header-row', ['item' => $item, 'columnCount' => 7])
                @include('pdf.partials.wehdah-item-row', [
                    'item' => $item,
                    'index' => ($pageIndex * $itemsPerPage) + $loop->iteration,
                    'columns' => 'full',
                ])
            @endforeach
        </tbody>
    </table>

    @if($pageIndex !== count($itemPages) - 1)
        <div class="wehdah-continued">Continued on next page &rarr;</div>
    @else
        @if($amountWords)
            <div class="wehdah-amount-words">{{ $amountWords }}</div>
        @endif
        <table class="wehdah-totals">
            @if((float) $document->subtotal !== (float) $document->grand_total)
                <tr><td>Subtotal</td><td class="r">{{ number_format((float) $document->subtotal, 2) }}</td></tr>
                @if((float) $document->discount_total > 0)
                    <tr><td>Discount</td><td class="r">({{ number_format((float) $document->discount_total, 2) }})</td></tr>
                @endif
                @if((float) $document->tax_total > 0)
                    <tr><td>Tax</td><td class="r">{{ number_format((float) $document->tax_total, 2) }}</td></tr>
                @endif
            @endif
            <tr>
                <td class="wehdah-grand-label">Grand Total ({{ $document->currency }})</td>
                <td class="wehdah-grand-val">{{ number_format((float) $document->grand_total, 2) }}</td>
            </tr>
        </table>

        @include('pdf.partials.wehdah-bank')

        <div class="wehdah-terms">
            Bagi pihak <strong>{{ strtoupper($company->name ?? '') }}</strong>, kami ucapkan jutaan terima kasih atas sokongan tuan/puan.<br>
            Goods sold are not returnable and payment made is not refundable.
        </div>

        @if($document->terms)
            <div class="wehdah-terms"><strong>Terms:</strong> {!! nl2br(e($document->terms)) !!}</div>
        @endif

        @include('pdf.partials.wehdah-signature', [
            'leftIntro' => 'Yours faithfully,',
            'leftLabel' => 'Authorised Signature',
            'rightIntro' => 'Goods received in right and good condition',
            'rightLabel' => 'Company Sign & Chop',
        ])
    @endif

    <div class="wehdah-page-number">Page {{ $pageIndex + 1 }} of {{ count($itemPages) }}</div>
@endforeach

<div class="wehdah-footer-doc">
    Computer-generated document &middot; {{ now()->setTimezone('Asia/Kuala_Lumpur')->format('d/m/Y h:i A') }} MYT
</div>

@include('pdf.partials.artwork-pages', ['documentTitleEn' => 'INVOICE', 'showConfirmation' => true])
</body>
</html>
