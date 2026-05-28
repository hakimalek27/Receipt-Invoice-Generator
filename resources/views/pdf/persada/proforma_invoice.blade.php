@php
    $num = $document->official_number ?? 'DRAFT';
    $pgg = [
        'label' => 'PROFORMA INVOICE',
        'titleWithSubject' => true,
        'style' => 'letter',
        'customerLabel' => 'Bill To:',
        'extraNote' => 'This is a proforma invoice for your reference and is not a valid tax invoice.',
        'intro' => 'Please find below our proforma invoice for your kind attention:',
        'meta' => [
            ['Proforma No.', $num],
            ['Date', optional($document->document_date)->format('d/m/Y')],
            ['Valid Until', optional($document->due_date)->format('d/m/Y') ?: '30 days'],
        ],
        'terms' => [
            'All payment transfer or cheques should be made payable and crossed to PERSADA GEMILANG GLOBAL.',
            'Please bank in to Bank Kerjasama Rakyat – 110 258 1847',
            'A valid tax invoice will be issued upon receipt of payment.',
            'Goods sold are not returnable and payment made is not refundable.',
        ],
        'signVariant' => 'letter',
    ];
@endphp
@include('pdf.persada._body')
