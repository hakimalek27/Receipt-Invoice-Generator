@php
    $leftIntro = $leftIntro ?? 'Yours faithfully,';
    $leftLabel = $leftLabel ?? 'Authorised Signature';
    $rightIntro = $rightIntro ?? 'Goods received in right and good condition';
    $rightLabel = $rightLabel ?? 'Company Sign & Chop';
    $singleColumn = $singleColumn ?? false;
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
                <div class="wehdah-signature-intro">{{ $leftIntro }}</div>
                <div class="wehdah-signature-line"></div>
                <div class="wehdah-signature-label">{{ $leftLabel }}</div>
            </td>
            <td class="wehdah-signature-cell wehdah-signature-cell-right">
                <div class="wehdah-signature-intro">{{ $rightIntro }}</div>
                <div class="wehdah-signature-line"></div>
                <div class="wehdah-signature-label">{{ $rightLabel }}</div>
            </td>
        </tr>
    </table>
@endif
