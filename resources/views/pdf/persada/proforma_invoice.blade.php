@php
    $num = $document->official_number ?? 'DRAFT';
    $tarikh = $document->document_date
        ? \Illuminate\Support\Carbon::parse($document->document_date)->locale('ms')->translatedFormat('d F Y')
        : '';
    $pgg = [
        'label' => 'PROFORMA INVOICE',
        'titleWithSubject' => true,
        'style' => 'letter',
        'extraNote' => 'This is a proforma invoice for your reference and is not a valid tax invoice.',
        'meta' => [
            ['Ruj. Tuan', ''],
            ['No. Proforma', $num],
            ['Tarikh', $tarikh],
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
