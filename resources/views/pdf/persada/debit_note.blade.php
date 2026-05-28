@php
    $num = $document->official_number ?? 'DRAFT';
    $pgg = [
        'label' => 'DEBIT NOTE',
        'titleWithSubject' => false,
        'style' => 'recipient',
        'customerLabel' => 'To:',
        'extraNote' => 'This debit note increases the amount owed on the referenced invoice.',
        'intro' => 'The following additional charges have been debited to your account:',
        'meta' => [
            ['Debit Note No.', $num],
            ['Date', optional($document->document_date)->format('d/m/Y')],
        ],
        'terms' => [
            'Please bank in to Bank Kerjasama Rakyat – 110 258 1847',
            'Payment for the debited amount is due within 30 days.',
        ],
        'signVariant' => 'authorised',
        'footerNote' => 'This is a computer-generated debit note.',
    ];
@endphp
@include('pdf.persada._body')
