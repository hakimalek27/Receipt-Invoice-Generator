@php
    $num = $document->official_number ?? 'DRAFT';
    $pgg = [
        'label' => 'CASH BILL',
        'titleWithSubject' => false,
        'style' => 'recipient',
        'customerLabel' => 'Bill To:',
        'amountReceived' => true,
        'meta' => [
            ['Bill No.', $num],
            ['Date', optional($document->document_date)->format('d/m/Y')],
        ],
        'terms' => [
            'Goods sold are not returnable and payment made is not refundable.',
        ],
        'signVariant' => 'authorised',
        'footerNote' => 'This is a computer-generated cash bill.',
    ];
@endphp
@include('pdf.persada._body')
