<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $document->official_number ?? 'QUOTATION' }}</title>
    @include('pdf.partials.wehdah-styles')
</head>
<body>
@foreach($itemPages as $pageIndex => $pageItems)
    @if($pageIndex > 0)<div class="page-break"></div>@endif

    @if($pageIndex === 0)
        @include('pdf.partials.wehdah-header', [
            'variant' => 'full',
            'documentTitle' => 'QUOTATION',
            'tagline' => 'Masjid & Community Services',
        ])
        <div class="wehdah-meta-row">
            <div class="wehdah-meta-col wehdah-meta-col-left">
                @include('pdf.partials.wehdah-customer', ['label' => 'To:'])
            </div>
            <div class="wehdah-meta-col wehdah-meta-col-right">
                @include('pdf.partials.wehdah-meta', ['documentTitleEn' => 'QUOTATION', 'showValidity' => true])
            </div>
        </div>
        <p class="wehdah-intro">Terima kasih atas pertanyaan tuan/puan. Berikut adalah sebut harga kami untuk pertimbangan:</p>
    @else
        @include('pdf.partials.wehdah-header', ['variant' => 'compact', 'documentTitle' => 'QUOTATION'])
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
            @endif
            <tr>
                <td class="wehdah-grand-label">Grand Total ({{ $document->currency }})</td>
                <td class="wehdah-grand-val">{{ number_format((float) $document->grand_total, 2) }}</td>
            </tr>
        </table>

        <div class="wehdah-terms">
            Kami berharap sebut harga ini memenuhi keperluan tuan/puan. Sebarang pertanyaan lanjut, sila hubungi kami.
        </div>

        @if($document->terms)
            <div class="wehdah-terms"><strong>Terms:</strong> {!! nl2br(e($document->terms)) !!}</div>
        @endif

        @include('pdf.partials.wehdah-signature', [
            'leftIntro' => 'Yang menjalankan tugas,',
            'leftLabel' => 'Authorised Signature',
            'rightIntro' => 'Disahkan dan diterima oleh,',
            'rightLabel' => 'Wakil Pelanggan',
        ])
    @endif

    <div class="wehdah-page-number">Page {{ $pageIndex + 1 }} of {{ count($itemPages) }}</div>
@endforeach

<div class="wehdah-footer-doc">
    Computer-generated document &middot; {{ now()->setTimezone('Asia/Kuala_Lumpur')->format('d/m/Y h:i A') }} MYT
</div>

@include('pdf.partials.artwork-pages', ['documentTitleEn' => 'QUOTATION', 'showConfirmation' => true])
</body>
</html>
