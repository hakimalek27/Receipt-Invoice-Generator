@php
    $leftIntro = $leftIntro ?? 'Yours faithfully,';
    $leftLabel = $leftLabel ?? 'Authorised Signature';
    $rightIntro = $rightIntro ?? 'Goods received in right and good condition';
    $rightLabel = $rightLabel ?? 'Company Sign & Chop';
    $singleColumn = $singleColumn ?? false;
    $signatureUrl = $document->isIssued()
        ? data_get($document->issuer_snapshot_json ?? [], 'signature_image_url')
        : ($company->signature_image_url ?? null);
    $stampUrl = $document->isIssued()
        ? data_get($document->issuer_snapshot_json ?? [], 'stamp_image_url')
        : ($company->stamp_image_url ?? null);
@endphp

@if($singleColumn)
    <table class="ws-signature" style="width: 60%; margin-left: auto;">
        <tr>
            <td class="ws-signature-cell ws-signature-cell-right">
                <div class="ws-signature-intro">{{ $rightIntro }}</div>
                @if($signatureUrl || $stampUrl)
                    <div class="ws-signature-images">
                        @if($signatureUrl)<img src="{{ $signatureUrl }}" alt="signature" class="ws-sig-img">@endif
                        @if($stampUrl)<img src="{{ $stampUrl }}" alt="stamp" class="ws-stamp-img">@endif
                    </div>
                @endif
                <div class="ws-signature-line"></div>
                <div class="ws-signature-label">{{ $rightLabel }}</div>
            </td>
        </tr>
    </table>
@else
    <table class="ws-signature">
        <tr>
            <td class="ws-signature-cell ws-signature-cell-left">
                <div class="ws-signature-intro">{{ $leftIntro }}</div>
                @if($signatureUrl || $stampUrl)
                    <div class="ws-signature-images">
                        @if($signatureUrl)<img src="{{ $signatureUrl }}" alt="signature" class="ws-sig-img">@endif
                        @if($stampUrl)<img src="{{ $stampUrl }}" alt="stamp" class="ws-stamp-img">@endif
                    </div>
                @endif
                <div class="ws-signature-line"></div>
                <div class="ws-signature-label">{{ $leftLabel }}</div>
            </td>
            <td class="ws-signature-cell ws-signature-cell-right">
                <div class="ws-signature-intro">{{ $rightIntro }}</div>
                <div style="height: 60px;"></div>
                <div class="ws-signature-line"></div>
                <div class="ws-signature-label">{{ $rightLabel }}</div>
            </td>
        </tr>
    </table>
@endif
