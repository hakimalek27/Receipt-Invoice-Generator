<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $document->official_number ?? 'DELIVERY ORDER' }}</title>
    @include('pdf.partials.wehdah-styles')
</head>
<body>
@foreach($itemPages as $pageIndex => $pageItems)
    @if($pageIndex > 0)<div class="page-break"></div>@endif

    @if($pageIndex === 0)
        @include('pdf.partials.wehdah-header', ['variant' => 'full', 'documentTitle' => 'DELIVERY ORDER'])
        <div class="wehdah-meta-row">
            <div class="wehdah-meta-col wehdah-meta-col-left">
                @include('pdf.partials.wehdah-customer', ['label' => 'Deliver To:'])
            </div>
            <div class="wehdah-meta-col wehdah-meta-col-right">
                @include('pdf.partials.wehdah-meta', ['documentTitleEn' => 'DELIVERY ORDER', 'showValidity' => false])
            </div>
        </div>
    @else
        @include('pdf.partials.wehdah-header', ['variant' => 'compact', 'documentTitle' => 'DELIVERY ORDER'])
    @endif

    <table class="wehdah-items">
        <thead>
            <tr>
                <th class="wehdah-items-num">No</th>
                <th class="wehdah-items-desc">Description</th>
                <th class="r wehdah-items-qty">Qty</th>
                <th class="wehdah-items-unit">Unit</th>
            </tr>
        </thead>
        <tbody>
            @foreach($pageItems as $item)
                @include('pdf.partials.wehdah-item-row', [
                    'item' => $item,
                    'index' => ($pageIndex * $itemsPerPage) + $loop->iteration,
                    'columns' => 'do',
                ])
            @endforeach
        </tbody>
    </table>

    @if($pageIndex !== count($itemPages) - 1)
        <div class="wehdah-continued">Continued on next page &rarr;</div>
    @else
        @if($document->notes)
            <div class="wehdah-terms"><strong>Notes:</strong> {!! nl2br(e($document->notes)) !!}</div>
        @endif
        @if($document->terms)
            <div class="wehdah-terms"><strong>Terms:</strong> {!! nl2br(e($document->terms)) !!}</div>
        @endif

        @include('pdf.partials.wehdah-signature', [
            'leftIntro' => 'Delivered by,',
            'leftLabel' => 'Authorised Signature',
            'rightIntro' => 'Goods received in good condition',
            'rightLabel' => 'Customer Sign & Chop',
        ])
    @endif

    <div class="wehdah-page-number">Page {{ $pageIndex + 1 }} of {{ count($itemPages) }}</div>
@endforeach

<div class="wehdah-footer-doc">
    Computer-generated document &middot; {{ now()->setTimezone('Asia/Kuala_Lumpur')->format('d/m/Y h:i A') }} MYT
</div>

@include('pdf.partials.artwork-pages', ['documentTitleEn' => 'DELIVERY ORDER', 'showConfirmation' => true])
</body>
</html>
