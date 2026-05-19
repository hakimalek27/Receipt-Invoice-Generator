@php
    $bankAccounts = [];

    if ($document->isIssued() && !empty($document->bank_snapshot_json)) {
        foreach ((array) $document->bank_snapshot_json as $entry) {
            $bankAccounts[] = (object) $entry;
        }
    } else {
        $accounts = \App\Models\CompanyBankAccount::query()
            ->where('company_id', $company?->id ?? $document->company_id)
            ->orderBy('sort_order')
            ->get();

        foreach ($accounts as $account) {
            $bankAccounts[] = (object) [
                'bank_name' => $account->bank_name,
                'account_number' => $account->account_number,
            ];
        }
    }
@endphp

@if(!empty($bankAccounts))
    <div class="ws-bank">
        <span class="ws-bank-label">Bank Details:</span>
        @foreach($bankAccounts as $i => $account)
            <span class="ws-bank-line">{{ $account->bank_name }} {{ $account->account_number }}</span>
            @if(!$loop->last)<span class="ws-bank-sep">|</span>@endif
        @endforeach
    </div>
@endif
