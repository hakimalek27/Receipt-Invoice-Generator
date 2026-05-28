@php
    $num = $document->official_number ?? 'DRAFT';
    $paidBy = ! empty($payment['method']) ? ucfirst(str_replace('_', ' ', $payment['method'])) : null;
    $meta = [
        ['Receipt No.', $num],
        ['Date', optional($document->document_date)->format('d/m/Y')],
    ];
    if ($paidBy) {
        $meta[] = ['Paid By', $paidBy];
    }
    if (! empty($payment['reference_number'])) {
        $meta[] = ['Reference', $payment['reference_number']];
    }
    $pgg = [
        'label' => 'OFFICIAL RECEIPT',
        'titleWithSubject' => false,
        'style' => 'recipient',
        'customerLabel' => 'Received From:',
        'intro' => 'Received with thanks the sum of:',
        'amountReceived' => true,
        'meta' => $meta,
        'terms' => [],
        'signVariant' => 'authorised',
        'footerNote' => 'This is a computer-generated receipt.',
    ];
@endphp
@include('pdf.persada._body')
