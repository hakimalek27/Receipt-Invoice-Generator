@php
    $num = $document->official_number ?? 'DRAFT';
    $tarikh = $document->document_date
        ? \Illuminate\Support\Carbon::parse($document->document_date)->locale('ms')->translatedFormat('d F Y')
        : '';
    $tarikhAkhir = $document->due_date
        ? \Illuminate\Support\Carbon::parse($document->due_date)->locale('ms')->translatedFormat('d F Y')
        : '';
    $meta = [
        ['Ruj. Tuan', ''],
        ['No. Invois', $num],
        ['Tarikh', $tarikh],
    ];
    if ($tarikhAkhir !== '') {
        $meta[] = ['Tarikh Akhir Bayaran', $tarikhAkhir];
    }
    $pgg = [
        'label' => 'INVOICE',
        'titleWithSubject' => true,
        'style' => 'letter',
        'meta' => $meta,
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
