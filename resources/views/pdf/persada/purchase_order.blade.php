@php
    $num = $document->official_number ?? 'DRAFT';
    $tarikh = $document->document_date
        ? \Illuminate\Support\Carbon::parse($document->document_date)->locale('ms')->translatedFormat('d F Y')
        : '';
    $pgg = [
        'label' => 'PURCHASE ORDER',
        'titleWithSubject' => true,
        'style' => 'letter',
        'customerLabel' => 'Supplier:',
        'meta' => [
            ['Ruj. Tuan', ''],
            ['No. PO', $num],
            ['Tarikh', $tarikh],
        ],
        'terms' => [
            'Please quote our purchase order number on all delivery orders and invoices.',
            'Goods must be delivered to the address stated above unless otherwise agreed.',
            'Payment will be made within 30 days from receipt of a valid tax invoice.',
        ],
        'signVariant' => 'authorised',
    ];
@endphp
@include('pdf.persada._body')
