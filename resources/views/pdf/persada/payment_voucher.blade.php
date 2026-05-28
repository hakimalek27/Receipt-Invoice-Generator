@php
    $num = $document->official_number ?? 'DRAFT';
    $pgg = [
        'label' => 'PAYMENT VOUCHER',
        'titleWithSubject' => false,
        'style' => 'recipient',
        'customerLabel' => 'Paid To:',
        'intro' => 'Being payment for the following:',
        'meta' => [
            ['Voucher No.', $num],
            ['Date', optional($document->document_date)->format('d/m/Y')],
        ],
        'terms' => [],
        'signVariant' => 'voucher',
        'footerNote' => 'This is a computer-generated payment voucher.',
    ];
@endphp
@include('pdf.persada._body')
