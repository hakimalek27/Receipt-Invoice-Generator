@php
    $terms = $terms ?? [];
@endphp
@if(! empty($terms))
    <div class="pgg-terms">
        <div class="pgg-terms-head">Terms &amp; Conditions :</div>
        <ol>
            @foreach($terms as $term)
                <li>{!! $term !!}</li>
            @endforeach
        </ol>
    </div>
@endif

@if(! empty($document->terms))
    <div class="pgg-terms-free"><strong>Additional Terms:</strong> {!! nl2br(e($document->terms)) !!}</div>
@endif
