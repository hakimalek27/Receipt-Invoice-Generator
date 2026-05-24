@php
    $variant = $variant ?? 'full';
    $documentTitle = $documentTitle ?? strtoupper(str_replace('_', ' ', $document->document_type));
    $regionLine = trim(implode(' ', array_filter([
        $company->postcode ?? null,
        $company->city ?? null,
        $company->state ?? null,
        $company->country ?? null,
    ])));
    $countryName = match (strtoupper((string) ($company->country ?? ''))) {
        'MY' => 'Malaysia',
        'SG' => 'Singapore',
        default => $company->country ?? null,
    };
    $regionLineExpanded = trim(implode(' ', array_filter([
        $company->postcode ?? null,
        $company->city ?? null,
        $company->state ?? null,
        $countryName,
    ]))).'.';
@endphp

@if($variant === 'compact')
    <div class="ws-header-compact">
        <div class="ws-header-compact-name">{{ $company->name ?? '' }}</div>
        <div class="ws-header-compact-center">{{ strtoupper($documentTitle) }} &ndash; CONTINUED</div>
        <div class="ws-header-compact-right">
            No: {{ $document->official_number ?? 'DRAFT' }}
            @if(!empty($customer?->name)) &middot; {{ \Illuminate\Support\Str::limit($customer->name, 28) }} @endif
        </div>
    </div>
@else
    <div class="ws-title-strip">
        <span class="ws-title-strip-text">{{ strtoupper($documentTitle) }}</span>
    </div>
    @php
        $addr1 = trim((string) ($company->address ?? ''));
        $addr2 = trim((string) ($company->address_line_2 ?? ''));
        // Skip line 2 if it is a substring of line 1 (case-insensitive).
        if ($addr2 !== '' && stripos($addr1, $addr2) !== false) {
            $addr2 = '';
        }
        $postcode = trim((string) ($company->postcode ?? ''));
        $showRegion = $regionLineExpanded !== '.' && $regionLineExpanded !== '';
        // Skip region if postcode already appears in the free-form address lines.
        if ($showRegion && $postcode !== ''
            && (stripos($addr1, $postcode) !== false || stripos($addr2, $postcode) !== false)) {
            $showRegion = false;
        }
        $logoSrc = $logoDataUri ?? null;
    @endphp
    <div class="ws-header-center">
        @if($logoSrc)
            <img src="{{ $logoSrc }}" alt="logo" class="ws-header-logo-img">
        @endif
        <div class="ws-header-info">
            <div class="ws-company-name">
                {{ strtoupper($company->name ?? '') }}
                @if(!empty($company->registration_number))
                    <span class="ws-company-reg">{{ $company->registration_number }}</span>
                @endif
            </div>
            @if($addr1 !== '')
                <div class="ws-company-address">{{ $addr1 }}</div>
            @endif
            @if($addr2 !== '')
                <div class="ws-company-address">{{ $addr2 }}</div>
            @endif
            @if($showRegion)
                <div class="ws-company-address">{{ $regionLineExpanded }}</div>
            @endif
            <div class="ws-company-contact">
                @if(!empty($company->phone))Phone: {{ $company->phone }}@endif
                @if(!empty($company->phone) && !empty($company->email)) &nbsp; @endif
                @if(!empty($company->email))Email: {{ $company->email }}@endif
            </div>
        </div>
    </div>
@endif
