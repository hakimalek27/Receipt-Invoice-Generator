@php
    $documentTitleEn = $documentTitleEn ?? strtoupper(str_replace('_', ' ', $document->document_type));
    $showValidity = $showValidity ?? false;
    $validityText = $validityText ?? '14 Days';
    $paymentTerm = $document->terms_snapshot_json['payment_term']
        ?? ($document->metadata['payment_term'] ?? null);
@endphp

<div class="wehdah-meta">
    <div class="wehdah-meta-title">{{ $documentTitleEn }}</div>
    <table class="wehdah-meta-table">
        <tr><td class="wehdah-meta-key">No.:</td><td class="wehdah-meta-val">{{ $document->official_number ?? 'DRAFT' }}</td></tr>
        <tr><td class="wehdah-meta-key">Date:</td><td class="wehdah-meta-val">{{ optional($document->document_date)->format('d/m/Y') }}</td></tr>
        @if($paymentTerm)
            <tr><td class="wehdah-meta-key">Payment Term:</td><td class="wehdah-meta-val">{{ $paymentTerm }}</td></tr>
        @endif
        @if($showValidity)
            <tr><td class="wehdah-meta-key">Validity:</td><td class="wehdah-meta-val">{{ $validityText }}</td></tr>
        @endif
        <tr><td class="wehdah-meta-key">Currency:</td><td class="wehdah-meta-val">{{ $document->currency }}</td></tr>
    </table>
</div>
