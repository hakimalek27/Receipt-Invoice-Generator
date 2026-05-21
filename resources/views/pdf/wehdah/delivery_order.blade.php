<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $document->official_number ?? 'DELIVERY ORDER' }}</title>
    @include('pdf.wehdah._styles')
</head>
<body>
@foreach($itemPages as $pageIndex => $pageItems)
    @if($pageIndex > 0)<div class="page-break"></div>@endif

    @if($pageIndex === 0)
        @include('pdf.wehdah._header', ['variant' => 'full', 'documentTitle' => 'DELIVERY ORDER'])
        <div class="ws-meta-row">
            <div class="ws-meta-col ws-meta-col-left">
                @include('pdf.wehdah._bill-to', ['label' => 'Deliver To:'])
            </div>
            <div class="ws-meta-col ws-meta-col-right">
                @include('pdf.wehdah._meta-block', ['documentTitleEn' => 'DELIVERY ORDER', 'showValidity' => false])
            </div>
        </div>
    @else
        @include('pdf.wehdah._header', ['variant' => 'compact', 'documentTitle' => 'DELIVERY ORDER'])
    @endif

    <table class="ws-items">
        <thead>
            <tr>
                <th class="ws-col-item">No</th>
                <th class="ws-col-desc">Description</th>
                <th class="ws-col-qty">Qty</th>
                <th class="ws-col-unit">Unit</th>
            </tr>
        </thead>
        <tbody>
            @foreach($pageItems as $item)
                @include('pdf.wehdah._section-header', ['item' => $item, 'columnCount' => 4])
                @include('pdf.wehdah._item-row', [
                    'item' => $item,
                    'index' => ($pageIndex * $itemsPerPage) + $loop->iteration,
                    'columns' => 'do',
                ])
            @endforeach
        </tbody>
    </table>

    @if($pageIndex !== count($itemPages) - 1)
        <div class="ws-continued">Continued on next page &rarr;</div>
    @else
        @if($document->notes)
            <div class="ws-terms"><strong>Notes:</strong> {!! nl2br(e($document->notes)) !!}</div>
        @endif
        @if(!empty($boilerplate['footer_terms']))
            <div class="ws-terms">{!! nl2br(e($boilerplate['footer_terms'])) !!}</div>
        @endif
        @if($document->terms)
            <div class="ws-terms"><strong>Terms:</strong> {!! nl2br(e($document->terms)) !!}</div>
        @endif

        @include('pdf.wehdah._signature', [
            'leftIntro' => $boilerplate['signature_left_intro'] ?? 'Delivered by,',
            'leftLabel' => $boilerplate['signature_left_label'] ?? 'Authorised Signature',
            'rightIntro' => $boilerplate['signature_right_intro'] ?? 'Goods received in right and good condition',
            'rightLabel' => $boilerplate['signature_right_label'] ?? 'Customer Sign & Chop',
        ])
    @endif

    <div class="ws-page-number">Page {{ $pageIndex + 1 }} of {{ count($itemPages) }}</div>
@endforeach

@if($showComputerGenFooter ?? true)
<div class="ws-footer-doc">
    Computer-generated document &middot; {{ now()->setTimezone('Asia/Kuala_Lumpur')->format('d/m/Y h:i A') }} MYT
</div>
@endif

@include('pdf.partials.artwork-pages', ['documentTitleEn' => 'DELIVERY ORDER', 'showConfirmation' => true])
</body>
</html>
