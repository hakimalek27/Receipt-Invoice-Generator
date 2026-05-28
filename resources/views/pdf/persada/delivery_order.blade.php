@php
    $num = $document->official_number ?? 'DRAFT';
    $pgg = [
        'label' => 'DELIVERY ORDER',
        'titleWithSubject' => true,
        'style' => 'recipient',
        'customerLabel' => 'Deliver To:',
        'showPrices' => false,
        'intro' => 'The following goods have been delivered in good order and condition:',
        'meta' => [
            ['DO No.', $num],
            ['Date', optional($document->document_date)->format('d/m/Y')],
        ],
        'terms' => [
            'Goods received in good order and condition unless stated otherwise.',
            'Please verify quantities upon receipt; claims will not be entertained thereafter.',
        ],
        'signVariant' => 'delivery',
    ];
@endphp
@include('pdf.persada._body')
