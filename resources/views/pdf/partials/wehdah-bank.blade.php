@php
    $banks = [];
    if (!empty($document->bank_snapshot_json) && is_array($document->bank_snapshot_json)) {
        $snapshot = $document->bank_snapshot_json;
        if (isset($snapshot[0]) && is_array($snapshot[0])) {
            $banks = $snapshot;
        }
    }
    if (empty($banks) && isset($company) && method_exists($company, 'bankAccounts')) {
        $banks = $company->bankAccounts()->where('is_active', true)->orderBy('sort_order')->get()
            ->map(fn ($b) => [
                'bank_name' => $b->bank_name,
                'account_number' => $b->account_number,
                'account_holder' => $b->account_holder,
            ])->all();
    }
@endphp

@if(!empty($banks))
    <div class="wehdah-bank">
        <span class="wehdah-bank-label">Bank Details:</span>
        @foreach($banks as $bank)
            <span class="wehdah-bank-line">{{ $bank['bank_name'] ?? '' }} {{ $bank['account_number'] ?? '' }}</span>@if(!$loop->last) <span class="wehdah-bank-sep">&nbsp;-&nbsp;</span> @endif
        @endforeach
    </div>
@endif
