<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $document->official_number ?? 'OFFICIAL RECEIPT' }}</title>
    @include('pdf.partials.wehdah-styles')
    <style>
        .pgg-payment-meta { margin: 8px 0; padding: 6px 10px;
                            background: {{ $brand['secondary'] ?? '#efeaf7' }};
                            border: 1px solid {{ $brand['accent'] ?? '#3f2872' }};
                            font-size: 8.5pt; }
        .pgg-payment-meta strong { color: {{ $brand['primary'] ?? '#5d3a9b' }}; }
        .pgg-payment-meta-row { display: table; width: 100%; }
        .pgg-payment-meta-cell { display: table-cell; padding-right: 18px; }
    </style>
</head>
<body>
@foreach($itemPages as $pageIndex => $pageItems)
    @if($pageIndex > 0)<div class="page-break"></div>@endif

    @if($pageIndex === 0)
        @include('pdf.partials.pgg-gradient-header', ['documentTitle' => 'OFFICIAL RECEIPT'])
        @include('pdf.partials.arabic-salutation', ['document' => $document])
        <div class="wehdah-meta-row">
            <div class="wehdah-meta-col wehdah-meta-col-left">
                @include('pdf.partials.wehdah-customer', ['label' => 'Received From:'])
            </div>
            <div class="wehdah-meta-col wehdah-meta-col-right">
                @include('pdf.partials.wehdah-meta', ['documentTitleEn' => 'OFFICIAL RECEIPT', 'showValidity' => false])
            </div>
        </div>
        <p class="wehdah-intro">Received with thanks the sum of:</p>
    @else
        @include('pdf.partials.wehdah-header', ['variant' => 'compact', 'documentTitle' => 'OFFICIAL RECEIPT'])
    @endif

    <table class="wehdah-items">
        <thead>
            <tr>
                <th class="wehdah-items-num">No</th>
                <th class="wehdah-items-desc">Description</th>
                <th class="r wehdah-items-amount">Amount ({{ $document->currency }})</th>
            </tr>
        </thead>
        <tbody>
            @foreach($pageItems as $item)
                @include('pdf.partials.section-header-row', ['item' => $item, 'columnCount' => 3])
                @include('pdf.partials.wehdah-item-row', [
                    'item' => $item,
                    'index' => ($pageIndex * $itemsPerPage) + $loop->iteration,
                    'columns' => 'receipt',
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
            <tr>
                <td class="wehdah-grand-label">Total Received ({{ $document->currency }})</td>
                <td class="wehdah-grand-val">{{ number_format((float) $document->grand_total, 2) }}</td>
            </tr>
        </table>

        @if(!empty($payment))
            <div class="pgg-payment-meta">
                <div class="pgg-payment-meta-row">
                    @if(!empty($payment['method']))
                        <div class="pgg-payment-meta-cell"><strong>Method:</strong> {{ str_replace('_', ' ', ucfirst($payment['method'])) }}</div>
                    @endif
                    @if(!empty($payment['reference_number']))
                        <div class="pgg-payment-meta-cell"><strong>Reference:</strong> {{ $payment['reference_number'] }}</div>
                    @endif
                    @if(!empty($payment['payment_date']))
                        <div class="pgg-payment-meta-cell"><strong>Payment Date:</strong> {{ $payment['payment_date'] }}</div>
                    @endif
                </div>
            </div>
        @endif

        @if(!empty($payment['allocations']))
            <div class="wehdah-terms">
                <strong>Applied to:</strong>
                @foreach($payment['allocations'] as $allocation)
                    {{ $allocation['document_number'] ?? '-' }} ({{ number_format($allocation['amount'], 2) }}){{ !$loop->last ? ', ' : '' }}
                @endforeach
            </div>
        @endif

        @include('pdf.partials.wehdah-bank')

        <div class="wehdah-terms">
            Tax-exemption receipt for charitable contributions available upon request.
            Goods sold are not returnable and payment made is not refundable.
        </div>

        @if($document->notes)
            <div class="wehdah-terms"><strong>Notes:</strong> {!! nl2br(e($document->notes)) !!}</div>
        @endif

        @include('pdf.partials.wehdah-signature', [
            'singleColumn' => true,
            'rightIntro' => 'For ' . ($company->name ?? ''),
            'rightLabel' => 'Authorised Signature',
        ])
    @endif

    <div class="wehdah-page-number">Page {{ $pageIndex + 1 }} of {{ count($itemPages) }}</div>
@endforeach

<div class="wehdah-footer-doc">
    Computer-generated document &middot; {{ now()->setTimezone('Asia/Kuala_Lumpur')->format('d/m/Y h:i A') }} MYT
</div>
</body>
</html>
