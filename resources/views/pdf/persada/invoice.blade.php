@php
    $num = $document->official_number ?? 'DRAFT';
    $pgg = [
        'label' => 'INVOICE',
        'titleWithSubject' => true,
        'style' => 'letter',
        'customerLabel' => 'Bill To:',
        'intro' => 'Please find below our invoice for your kind attention:',
        'meta' => [
            ['Invoice No.', $num],
            ['Date', optional($document->document_date)->format('d/m/Y')],
            ['Due Date', optional($document->due_date)->format('d/m/Y') ?: '30 days'],
        ],
        'terms' => [
            'All payment transfer or cheques should be made payable and crossed to PERSADA GEMILANG GLOBAL.',
            'Please bank in to Bank Kerjasama Rakyat – 110 258 1847',
            'Payment due within 30 days of invoice date.',
            'Goods sold are not returnable and payment made is not refundable.',
        ],
        'signVariant' => 'letter',
    ];
@endphp
@include('pdf.persada._body')
