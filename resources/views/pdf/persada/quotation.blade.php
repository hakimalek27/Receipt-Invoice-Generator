@php
    $num = $document->official_number ?? 'DRAFT';
    $tarikh = $document->document_date
        ? \Illuminate\Support\Carbon::parse($document->document_date)->locale('ms')->translatedFormat('d F Y')
        : '';
    $pgg = [
        'label' => 'QUOTATION',
        'titleWithSubject' => true,
        'style' => 'letter',
        'meta' => [
            ['Ruj. Tuan', ''],
            ['Ruj. Kami', $num],
            ['Tarikh', $tarikh],
        ],
        'terms' => [
            'All payment transfer or cheques should be made payable and crossed to PERSADA GEMILANG GLOBAL.',
            'Please bank in to Bank Kerjasama Rakyat – 110 258 1847',
            'Goods sold are not returnable and not refundable.',
            'Validity of this quotation will end if the price changed.',
            'We trust that you will find our quote satisfactory and look forward for your response. Please contact us if have any question.',
        ],
        'signVariant' => 'letter',
    ];
@endphp
@include('pdf.persada._body')
