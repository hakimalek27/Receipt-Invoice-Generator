@php
    $documentTitle = $documentTitle ?? strtoupper(str_replace('_', ' ', $document->document_type));
    $regionLine = trim(implode(' ', array_filter([
        $company->postcode ?? null,
        $company->city ?? null,
        $company->state ?? null,
        $company->country ?? null,
    ])));
    $productLine = $document->product_line ?? null;
@endphp

<div class="pgg-gradient-band" style="margin: -2px -2px 8px -2px;">
    @if(!empty($brand['header_image_data_uri']))
        <img src="{{ $brand['header_image_data_uri'] }}"
             style="width: 100%; height: 18mm; display: block; object-fit: cover;" alt="">
    @else
        <div style="width: 100%; height: 12mm;
                    background: {{ $brand['primary'] ?? '#5d3a9b' }};"></div>
    @endif
</div>

<div class="wehdah-header" style="border-bottom: 1.5pt solid {{ $brand['accent'] ?? '#3f2872' }};
                                  padding-bottom: 6pt; margin-bottom: 10pt;">
    <div class="wehdah-header-grid">
        <div class="wehdah-header-logo">
            @if(!empty($company->logo_url))
                <img src="{{ $company->logo_url }}" alt="logo">
            @else
                <div class="wehdah-header-logo-fallback"
                     style="background: {{ $brand['primary'] ?? '#5d3a9b' }}; color: #fff;">
                    {{ strtoupper(substr($company->name ?? 'P', 0, 2)) }}
                </div>
            @endif
        </div>
        <div class="wehdah-header-info">
            <div class="wehdah-header-company">
                <span class="wehdah-header-name"
                      style="color: {{ $brand['primary'] ?? '#5d3a9b' }};">
                    {{ strtoupper($company->name ?? '') }}
                </span>
                @if(!empty($company->registration_number))
                    <span class="wehdah-header-reg">({{ $company->registration_number }})</span>
                @endif
            </div>
            @if($productLine === 'scentury')
                <div style="font-family: 'Times New Roman', serif; font-size: 11pt;
                            color: {{ $brand['accent'] ?? '#3f2872' }};
                            font-style: italic; letter-spacing: 1.2px;
                            margin-top: 2pt;">
                    &mdash; SCENTURY by {{ $company->name ?? '' }} &mdash;
                </div>
            @endif
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
