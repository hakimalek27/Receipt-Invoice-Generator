@php
    $documentTitleEn = $documentTitleEn ?? strtoupper(str_replace('_', ' ', $document->document_type));
    $showValidity = $showValidity ?? false;
    $paymentTerm = null;
    if (! empty($document->document_date) && ! empty($document->due_date)) {
        $date = \Carbon\Carbon::parse($document->document_date);
        $due = \Carbon\Carbon::parse($document->due_date);
        $diff = $date->diffInDays($due, false);
        if ($diff === 0) {
            $paymentTerm = 'C.O.D.';
        } elseif ($diff > 0) {
            $paymentTerm = 'Net '.$diff.' days';
        }
    }
    $numberLabel = match($document->document_type) {
        'quotation' => 'Quote No.',
        'delivery_order' => 'D/O No.',
        'official_receipt' => 'Receipt No.',
        default => 'Invoice No.',
    };
@endphp
<table class="ws-meta-table">
    <tr>
        <td class="ws-meta-key">{{ $numberLabel }}</td>
        <td class="ws-meta-val">{{ $document->official_number ?? 'DRAFT' }}</td>
    </tr>
    <tr>
        <td class="ws-meta-key">Date</td>
        <td class="ws-meta-val">
            {{ $document->document_date ? \Carbon\Carbon::parse($document->document_date)->format('d/m/Y') : '-' }}
        </td>
    </tr>
    @if($showValidity && !empty($document->due_date))
        <tr>
            <td class="ws-meta-key">Validity</td>
            <td class="ws-meta-val">{{ \Carbon\Carbon::parse($document->due_date)->format('d/m/Y') }}</td>
        </tr>
    @endif
    @if($paymentTerm && !$showValidity)
        <tr>
            <td class="ws-meta-key">Payment Term</td>
            <td class="ws-meta-val">{{ $paymentTerm }}</td>
        </tr>
    @endif
    <tr>
        <td class="ws-meta-key">Currency</td>
        <td class="ws-meta-val">{{ $document->currency ?? 'MYR' }}</td>
    </tr>
</table>
