<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $document->official_number ?? 'QUOTATION' }}</title>
    @include('pdf.wehdah._styles')
</head>
<body>
@foreach($itemPages as $pageIndex => $pageItems)
    @if($pageIndex > 0)<div class="page-break"></div>@endif

    @if($pageIndex === 0)
        @include('pdf.wehdah._header', ['variant' => 'full', 'documentTitle' => 'QUOTATION'])
        <div class="ws-meta-row">
            <div class="ws-meta-col ws-meta-col-left">
                @include('pdf.wehdah._bill-to', ['label' => 'To:'])
            </div>
            <div class="ws-meta-col ws-meta-col-right">
                @include('pdf.wehdah._meta-block', ['documentTitleEn' => 'QUOTATION', 'showValidity' => true])
            </div>
        </div>
        @if(!empty($boilerplate['intro']))
            <p class="ws-intro">{{ $boilerplate['intro'] }}</p>
        @endif
    @else
        @include('pdf.wehdah._header', ['variant' => 'compact', 'documentTitle' => 'QUOTATION'])
    @endif

    <table class="ws-items">
        <thead>
            <tr>
                <th class="ws-col-item">Item</th>
                <th class="ws-col-desc">Description</th>
                <th class="ws-col-qty">Qty</th>
                <th class="ws-col-unit">Unit</th>
                <th class="ws-col-price">Unit Price</th>
                <th class="ws-col-disc">Discount</th>
                <th class="ws-col-total">Total Price</th>
            </tr>
        </thead>
        <tbody>
            @foreach($pageItems as $item)
                @include('pdf.wehdah._section-header', ['item' => $item, 'columnCount' => 7])
                @include('pdf.wehdah._item-row', [
                    'item' => $item,
                    'index' => ($pageIndex * $itemsPerPage) + $loop->iteration,
                    'columns' => 'full',
                ])
            @endforeach
        </tbody>
    </table>

    @if($pageIndex !== count($itemPages) - 1)
        <div class="ws-continued">Continued on next page &rarr;</div>
    @else
        <div class="ws-totals-row">
            <div class="ws-totals-words-cell">
                @if($amountWords)
                    <div class="ws-words-label">Amount in words:</div>
                    <div class="ws-words-text">{{ $amountWords }}</div>
                @endif
            </div>
            <div class="ws-totals-grand-cell">
                @if((float) $document->subtotal !== (float) $document->grand_total)
                    <table class="ws-sub-totals">
                        <tr><td>Subtotal</td><td class="r">{{ number_format((float) $document->subtotal, 2) }}</td></tr>
                        @if((float) $document->discount_total > 0)
                            <tr><td>Discount</td><td class="r">({{ number_format((float) $document->discount_total, 2) }})</td></tr>
                        @endif
                    </table>
                @endif
                <table class="ws-grand-table">
                    <tr>
                        <td class="ws-grand-label">Grand Total ({{ $document->currency }})</td>
                        <td class="ws-grand-val">{{ number_format((float) $document->grand_total, 2) }}</td>
                    </tr>
                </table>
            </div>
        </div>

        @if(!empty($boilerplate['footer_terms']))
            <div class="ws-terms">{!! nl2br(e($boilerplate['footer_terms'])) !!}</div>
        @endif

        @if($document->terms)
            <div class="ws-terms"><strong>Terms:</strong> {!! nl2br(e($document->terms)) !!}</div>
        @endif

        @include('pdf.wehdah._signature', [
            'leftIntro' => $boilerplate['signature_left_intro'] ?? 'Yours faithfully,',
            'leftLabel' => $boilerplate['signature_left_label'] ?? 'Authorised Signature',
            'rightIntro' => $boilerplate['signature_right_intro'] ?? 'We confirm the order by accepting the terms',
            'rightLabel' => $boilerplate['signature_right_label'] ?? 'Signature & Company Stamp',
        ])
    @endif

    <div class="ws-page-number">Page {{ $pageIndex + 1 }} of {{ count($itemPages) }}</div>
@endforeach

<div class="ws-footer-doc">
    Computer-generated document &middot; {{ now()->setTimezone('Asia/Kuala_Lumpur')->format('d/m/Y h:i A') }} MYT
</div>

@include('pdf.partials.artwork-pages', ['documentTitleEn' => 'QUOTATION', 'showConfirmation' => true])
</body>
</html>
