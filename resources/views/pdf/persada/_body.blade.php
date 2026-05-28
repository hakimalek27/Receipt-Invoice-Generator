@php
    $pgg = $pgg ?? [];
    $label = $pgg['label'] ?? strtoupper(str_replace('_', ' ', $document->document_type));
    $style = $pgg['style'] ?? 'recipient';
    $customerLabel = $pgg['customerLabel'] ?? 'To:';
    $intro = $pgg['intro'] ?? null;
    $metaRows = $pgg['meta'] ?? [];
    $showPrices = $pgg['showPrices'] ?? true;
    $amountReceived = $pgg['amountReceived'] ?? false;
    $terms = $pgg['terms'] ?? [];
    $extraNote = $pgg['extraNote'] ?? null;
    $signVariant = $pgg['signVariant'] ?? 'authorised';
    $signatoryTitle = $pgg['signatoryTitle'] ?? 'Executive Director';
    $signatoryCompany = $pgg['signatoryCompany'] ?? ($company->name ?? 'Persada Gemilang Global');
    $footerNote = $pgg['footerNote'] ?? null;

    $subject = trim((string) ($document->subject ?? ''));
    $title = (! empty($pgg['titleWithSubject']) && $subject !== '')
        ? $label.' FOR '.strtoupper($subject)
        : $label;

    $cur = (empty($document->currency) || $document->currency === 'MYR') ? 'RM' : $document->currency;

    $regionLine = $customer ? trim(implode(' ', array_filter([
        $customer->postcode ?? null, $customer->city ?? null,
        $customer->state ?? null, $customer->country ?? null,
    ]))) : '';
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $document->official_number ?? $label }}</title>
    @include('pdf.persada._styles')
</head>
<body>
@include('pdf.persada._letterhead')

{{-- DomPDF ignores @page margins (paper set via setPaper) and a repeating
     <thead> spacer triggers a blank leading page, so a plain spacer div pushes
     page-1 content below the baked letterhead header band. --}}
<div class="pgg-top-spacer"></div>

    @if(! empty($document->include_arabic_salutation) && ! empty($brand['salam_data_uri']))
        <div class="pgg-salam"><img src="{{ $brand['salam_data_uri'] }}" alt=""></div>
    @endif

    @if($style === 'letter')
        <div class="pgg-cols">
            <div class="pgg-col-left">
                <div class="pgg-recipient-label">{{ $customerLabel }}</div>
                <div class="pgg-recipient-name">{{ $customer->name ?? 'Walk-in Customer' }}</div>
                @if(! empty($customer?->attention_to))<div>Attn: {{ $customer->attention_to }}</div>@endif
                @if(! empty($customer?->address))<div>{{ $customer->address }}</div>@endif
                @if($regionLine !== '')<div>{{ $regionLine }}</div>@endif
            </div>
            <div class="pgg-col-right">
                <table class="pgg-meta">
                    @foreach($metaRows as $row)
                        <tr><td class="k">{{ $row[0] }}</td><td class="v">{{ $row[1] }}</td></tr>
                    @endforeach
                </table>
            </div>
        </div>
        <div class="pgg-salutation">Dear Tan Sri/ Datuk Seri/ Dato’, Tuan, Puan,</div>
        <div class="pgg-title">{{ $title }}</div>
        @if($extraNote)<div class="pgg-extra-note">{{ $extraNote }}</div>@endif
        @if($intro)<div class="pgg-intro">{{ $intro }}</div>@endif
    @else
        <div class="pgg-title-center">{{ $title }}</div>
        <div class="pgg-cols">
            <div class="pgg-col-left">
                <div class="pgg-recipient-label">{{ $customerLabel }}</div>
                <div class="pgg-recipient-name">{{ $customer->name ?? 'Walk-in Customer' }}</div>
                @if(! empty($customer?->attention_to))<div>Attn: {{ $customer->attention_to }}</div>@endif
                @if(! empty($customer?->address))<div>{{ $customer->address }}</div>@endif
                @if($regionLine !== '')<div>{{ $regionLine }}</div>@endif
            </div>
            <div class="pgg-col-right">
                <table class="pgg-meta">
                    @foreach($metaRows as $row)
                        <tr><td class="k">{{ $row[0] }}</td><td class="v">{{ $row[1] }}</td></tr>
                    @endforeach
                </table>
            </div>
        </div>
        @if($extraNote)<div class="pgg-extra-note">{{ $extraNote }}</div>@endif
        @if($intro)<div class="pgg-intro">{{ $intro }}</div>@endif
    @endif

    @include('pdf.persada._items_table', [
        'pageItems' => $items,
        'startIndex' => 0,
        'showPrices' => $showPrices,
        'currencyLabel' => $cur,
        'isLastPage' => true,
        'amountReceived' => $amountReceived,
    ])

    @if($amountWords)
        <div class="pgg-amount-words">{{ $amountWords }}</div>
    @endif

    @include('pdf.persada._terms', ['terms' => $terms])

    @include('pdf.persada._signature', [
        'signVariant' => $signVariant,
        'signatoryTitle' => $signatoryTitle,
        'signatoryCompany' => $signatoryCompany,
    ])

    @if($showComputerGenFooter ?? true)
        <div class="pgg-footer">
            {{ $footerNote ?? 'Computer-generated document' }} &middot;
            {{ now()->setTimezone('Asia/Kuala_Lumpur')->format('d/m/Y h:i A') }} MYT
        </div>
    @endif

@include('pdf.partials.artwork-pages', ['documentTitleEn' => $label, 'showConfirmation' => false])
</body>
</html>
