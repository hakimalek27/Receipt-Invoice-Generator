@php
    $num = $document->official_number ?? 'DRAFT';
    $pgg = [
        'label' => 'CREDIT NOTE',
        'titleWithSubject' => false,
        'style' => 'recipient',
        'customerLabel' => 'To:',
        'extraNote' => 'This credit note adjusts the amount owed on the referenced invoice.',
        'intro' => 'A credit has been issued to your account for the following:',
        'meta' => [
            ['Credit Note No.', $num],
            ['Date', optional($document->document_date)->format('d/m/Y')],
        ],
        'terms' => [
            'This credit note may be applied against outstanding or future invoices.',
        ],
        'signVariant' => 'authorised',
        'footerNote' => 'This is a computer-generated credit note.',
    ];
@endphp
@include('pdf.persada._body')
