@php
    $variant = $variant ?? 'full';
    $documentTitle = $documentTitle ?? strtoupper(str_replace('_', ' ', $document->document_type));
    $regionLine = trim(implode(' ', array_filter([
        $company->postcode ?? null,
        $company->city ?? null,
        $company->state ?? null,
        $company->country ?? null,
    ])));
@endphp

@if($variant === 'compact')
    <div class="wehdah-header wehdah-header-compact">
        <div class="wehdah-header-compact-grid">
            <div class="wehdah-header-compact-name">{{ $company->name ?? '' }}</div>
            <div class="wehdah-header-compact-center">{{ strtoupper($documentTitle) }} &ndash; CONTINUED</div>
            <div class="wehdah-header-compact-right">
                No: {{ $document->official_number ?? 'DRAFT' }}
                @if(!empty($customer?->name)) &middot; {{ \Illuminate\Support\Str::limit($customer->name, 28) }} @endif
            </div>
        </div>
    </div>
@else
    <div class="wehdah-header">
        <div class="wehdah-header-grid">
            <div class="wehdah-header-logo">
                @if(!empty($company->logo_url))
                    <img src="{{ $company->logo_url }}" alt="logo">
                @else
                    <div class="wehdah-header-logo-fallback">{{ strtoupper(substr($company->name ?? 'W', 0, 2)) }}</div>
                @endif
            </div>
            <div class="wehdah-header-info">
                <div class="wehdah-header-company">
                    <span class="wehdah-header-name">{{ strtoupper($company->name ?? '') }}</span>
                    @if(!empty($company->registration_number))
                        <span class="wehdah-header-reg">({{ $company->registration_number }})</span>
                    @endif
                </div>
                @if(!empty($company->address))
                    <div class="wehdah-header-line">{{ $company->address }}</div>
                @endif
                @if(!empty($company->address_line_2))
                    <div class="wehdah-header-line">{{ $company->address_line_2 }}</div>
                @endif
                @if($regionLine !== '')
                    <div class="wehdah-header-line">{{ $regionLine }}</div>
                @endif
                <div class="wehdah-header-contact">
                    @if(!empty($company->phone))Tel: {{ $company->phone }}@endif
                    @if(!empty($company->phone) && !empty($company->email)) &nbsp;|&nbsp; @endif
                    @if(!empty($company->email))Email: {{ $company->email }}@endif
                </div>
            </div>
        </div>
    </div>
@endif
