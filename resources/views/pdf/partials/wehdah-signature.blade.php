@php
    $leftIntro = $leftIntro ?? 'Yours faithfully,';
    $leftLabel = $leftLabel ?? 'Authorised Signature';
    $rightIntro = $rightIntro ?? 'Goods received in right and good condition';
    $rightLabel = $rightLabel ?? 'Company Sign & Chop';
    $singleColumn = $singleColumn ?? false;
    $showCompanyAssets = $showCompanyAssets ?? true;
    $signatureSrc = ($showCompanyAssets && isset($company)) ? ($company->signature_url ?? null) : null;
    $stampSrc = ($showCompanyAssets && isset($company)) ? ($company->stamp_url ?? null) : null;
    $hasCompanyAssets = $signatureSrc || $stampSrc;
@endphp

@if($singleColumn)
    <div class="wehdah-signature wehdah-signature-single">
        <div class="wehdah-signature-right">
            <div class="wehdah-signature-intro">{{ $rightIntro }}</div>
            <div class="wehdah-signature-line"></div>
            <div class="wehdah-signature-label">{{ $rightLabel }}</div>
        </div>
    </div>
@else
    <table class="wehdah-signature">
        <tr>
            <td class="wehdah-signature-cell wehdah-signature-cell-left">
                @if($hasCompanyAssets)
                    <div class="wehdah-signature-intro-tight">{{ $leftIntro }}</div>
                    <div class="wehdah-signature-images">
                        @if($signatureSrc)
                            <img src="{{ $signatureSrc }}" class="wehdah-sig-img" alt="signature">
                        @endif
                        @if($stampSrc)
                            <img src="{{ $stampSrc }}" class="wehdah-stamp-img" alt="company stamp">
                        @endif
                    </div>
                    <div class="wehdah-signature-line"></div>
                    <div class="wehdah-signature-label">{{ $leftLabel }}</div>
                @else
                    <div class="wehdah-signature-intro">{{ $leftIntro }}</div>
                    <div class="wehdah-signature-line"></div>
                    <div class="wehdah-signature-label">{{ $leftLabel }}</div>
                @endif
            </td>
            <td class="wehdah-signature-cell wehdah-signature-cell-right">
                <div class="wehdah-signature-intro">{{ $rightIntro }}</div>
                <div class="wehdah-signature-line"></div>
                <div class="wehdah-signature-label">{{ $rightLabel }}</div>
            </td>
        </tr>
    </table>
@endif
