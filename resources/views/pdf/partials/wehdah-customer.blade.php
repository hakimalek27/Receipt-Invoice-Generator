@php
    $label = $label ?? 'Bill To:';
    $customer = $customer ?? null;
    $regionLine = $customer ? trim(implode(' ', array_filter([
        $customer->postcode ?? null,
        $customer->city ?? null,
        $customer->state ?? null,
        $customer->country ?? null,
    ]))) : '';
@endphp

<div class="wehdah-customer">
    <div class="wehdah-customer-label">{{ $label }}</div>
    <div class="wehdah-customer-box">
        <span class="wehdah-corner wehdah-corner-tl"></span>
        <span class="wehdah-corner wehdah-corner-tr"></span>
        <span class="wehdah-corner wehdah-corner-bl"></span>
        <span class="wehdah-corner wehdah-corner-br"></span>
        <div class="wehdah-customer-name">{{ $customer->name ?? 'Walk-in Customer' }}</div>
        @if(!empty($customer?->address))
            <div class="wehdah-customer-line">{{ $customer->address }}</div>
        @endif
        @if(!empty($customer?->address_line_2))
            <div class="wehdah-customer-line">{{ $customer->address_line_2 }}</div>
        @endif
        @if($regionLine !== '')
            <div class="wehdah-customer-line">{{ $regionLine }}</div>
        @endif
        @if(!empty($customer?->attention_to))
            <div class="wehdah-customer-line">Attn: {{ $customer->attention_to }}</div>
        @endif
        @if(!empty($customer?->phone))
            <div class="wehdah-customer-line">Tel: {{ $customer->phone }}</div>
        @endif
        @if(!empty($customer?->email))
            <div class="wehdah-customer-line">Email: {{ $customer->email }}</div>
        @endif
    </div>
</div>
