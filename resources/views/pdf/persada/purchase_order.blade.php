@php
    $num = $document->official_number ?? 'DRAFT';
    $pgg = [
        'label' => 'PURCHASE ORDER',
        'titleWithSubject' => true,
        'style' => 'letter',
        'customerLabel' => 'Supplier:',
        'intro' => 'We are pleased to place the following purchase order. Kindly acknowledge receipt and confirm delivery schedule:',
        'meta' => [
            ['PO No.', $num],
            ['Date', optional($document->document_date)->format('d/m/Y')],
            ['Required By', optional($document->due_date)->format('d/m/Y') ?: '-'],
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
