<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $document->official_number ?? 'OFFICIAL RECEIPT' }}</title>
    @include('pdf.wehdah._styles')
</head>
<body>
@foreach($itemPages as $pageIndex => $pageItems)
    @if($pageIndex > 0)<div class="page-break"></div>@endif

    @if($pageIndex === 0)
        @include('pdf.wehdah._header', ['variant' => 'full', 'documentTitle' => 'OFFICIAL RECEIPT'])
        <div class="ws-meta-row">
            <div class="ws-meta-col ws-meta-col-left">
                @include('pdf.wehdah._bill-to', ['label' => 'Received From:'])
            </div>
            <div class="ws-meta-col ws-meta-col-right">
                @include('pdf.wehdah._meta-block', ['documentTitleEn' => 'OFFICIAL RECEIPT', 'showValidity' => false])
            </div>
        </div>
        @if(!empty($boilerplate['intro']))
            <p class="ws-intro">{{ $boilerplate['intro'] }}</p>
        @endif
    @else
        @include('pdf.wehdah._header', ['variant' => 'compact', 'documentTitle' => 'OFFICIAL RECEIPT'])
    @endif

    <table class="ws-items">
        <thead>
            <tr>
                <th class="ws-col-item">No</th>
                <th class="ws-col-desc">Description</th>
                <th class="ws-col-amount">Amount ({{ $document->currency }})</th>
            </tr>
        </thead>
        <tbody>
            @foreach($pageItems as $item)
                @include('pdf.wehdah._section-header', ['item' => $item, 'columnCount' => 3])
                @include('pdf.wehdah._item-row', [
                    'item' => $item,
                    'index' => ($pageIndex * $itemsPerPage) + $loop->iteration,
                    'columns' => 'receipt',
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
                <table class="ws-grand-table">
                    <tr>
                        <td class="ws-grand-label">Total Received ({{ $document->currency }})</td>
                        <td class="ws-grand-val">{{ number_format((float) $document->grand_total, 2) }}</td>
                    </tr>
                </table>
            </div>
        </div>

        @if(!empty($payment))
            <div class="ws-payment-meta">
                <div class="ws-payment-meta-row">
                    @if(!empty($payment['method']))
                        <div class="ws-payment-meta-cell"><strong>Method:</strong> {{ str_replace('_', ' ', ucfirst($payment['method'])) }}</div>
                    @endif
                    @if(!empty($payment['reference_number']))
                        <div class="ws-payment-meta-cell"><strong>Reference:</strong> {{ $payment['reference_number'] }}</div>
                    @endif
                    @if(!empty($payment['payment_date']))
                        <div class="ws-payment-meta-cell"><strong>Payment Date:</strong> {{ $payment['payment_date'] }}</div>
                    @endif
                </div>
            </div>
        @endif

        @if(!empty($payment['allocations']))
            <div class="ws-terms">
                <strong>Applied to:</strong>
                @foreach($payment['allocations'] as $allocation)
                    {{ $allocation['document_number'] ?? '-' }} ({{ number_format($allocation['amount'], 2) }}){{ !$loop->last ? ', ' : '' }}
                @endforeach
            </div>
        @endif

        @include('pdf.wehdah._bank')

        @if(!empty($boilerplate['footer_terms']))
            <div class="ws-terms">{!! nl2br(e($boilerplate['footer_terms'])) !!}</div>
        @endif

        @if($document->notes)
            <div class="ws-terms"><strong>Notes:</strong> {!! nl2br(e($document->notes)) !!}</div>
        @endif

        @include('pdf.wehdah._signature', [
            'singleColumn' => true,
            'rightIntro' => $boilerplate['signature_right_intro'] ?? ('For ' . ($company->name ?? '')),
            'rightLabel' => $boilerplate['signature_right_label'] ?? 'Authorised Signature',
        ])
    @endif

    <div class="ws-page-number">Page {{ $pageIndex + 1 }} of {{ count($itemPages) }}</div>
@endforeach

@if($showComputerGenFooter ?? true)
<div class="ws-footer-doc">
    Computer-generated document &middot; {{ now()->setTimezone('Asia/Kuala_Lumpur')->format('d/m/Y h:i A') }} MYT
</div>
@endif
</body>
</html>
