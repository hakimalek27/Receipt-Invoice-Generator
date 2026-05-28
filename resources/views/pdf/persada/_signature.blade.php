@php
    $variant = $signVariant ?? 'authorised';
    $signTitle = $signatoryTitle ?? 'Executive Director';
    $signCompany = $signatoryCompany ?? 'Persada Gemilang Global';
    // Drafts go out unstamped (for client review); issued documents are stamped.
    $showStamp = ! empty($brand['stamp_data_uri'])
        && method_exists($document, 'isIssued') && $document->isIssued();
    $dots = str_repeat('.', 31);
@endphp

@if($variant === 'voucher')
    <div class="pgg-sign-cols">
        <div class="pgg-sign-col"><div class="ln"></div><div class="lbl">Prepared by</div></div>
        <div class="pgg-sign-col"><div class="ln"></div><div class="lbl">Approved by</div></div>
        <div class="pgg-sign-col"><div class="ln"></div><div class="lbl">Received by</div></div>
    </div>
@elseif($variant === 'delivery')
    <div class="pgg-sign-cols">
        <div class="pgg-sign-col"><div class="ln"></div><div class="lbl">Delivered by</div><div>{{ $signCompany }}</div></div>
        <div class="pgg-sign-col">&nbsp;</div>
        <div class="pgg-sign-col"><div class="ln"></div><div class="lbl">Received by</div><div>Name &amp; Date</div></div>
    </div>
@elseif($variant === 'letter')
    <div class="pgg-sign">
        @if($showStamp)<div class="pgg-stamp"><img src="{{ $brand['stamp_data_uri'] }}" alt=""></div>@endif
        <div class="pgg-sign-thanks">Thank you.</div>
        <div class="pgg-sign-yours">Yours sincerely,</div>
        <div class="pgg-sign-dots">{{ $dots }}</div>
        <div class="pgg-sign-title">{{ $signTitle }}</div>
        <div>{{ $signCompany }}</div>
    </div>
@else
    <div class="pgg-sign">
        @if($showStamp)<div class="pgg-stamp"><img src="{{ $brand['stamp_data_uri'] }}" alt=""></div>@endif
        <div class="pgg-sign-dots">{{ $dots }}</div>
        <div class="pgg-sign-title">{{ $signTitle }}</div>
        <div>{{ $signCompany }}</div>
        <div style="font-size: 8pt; color: #555;">Authorised Signature</div>
    </div>
@endif
