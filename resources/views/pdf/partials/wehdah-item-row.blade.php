@php
    $rawDescription = (string) ($item->description ?? '');
    $lines = preg_split('/\r\n|\r|\n/', $rawDescription);
    $headerLines = [];
    $bulletLines = [];
    $bulletStarted = false;
    foreach ($lines as $line) {
        $trimmed = ltrim($line);
        if (preg_match('/^[-*\x{2022}]\s+/u', $trimmed)) {
            $bulletStarted = true;
            $bulletLines[] = preg_replace('/^[-*\x{2022}]\s+/u', '', $trimmed);
        } elseif ($bulletStarted && trim($line) === '') {
            continue;
        } elseif ($bulletStarted) {
            $bulletLines[] = $trimmed;
        } else {
            $headerLines[] = $line;
        }
    }
    $columns = $columns ?? 'full';
@endphp

<tr>
    <td class="wehdah-items-num">{{ $index }}</td>
    <td class="wehdah-items-desc">
        @foreach($headerLines as $line)
            {{ $line }}@if(!$loop->last)<br>@endif
        @endforeach
        @if(!empty($bulletLines))
            <ul class="wehdah-items-bullets">
                @foreach($bulletLines as $bullet)
                    <li>{{ $bullet }}</li>
                @endforeach
            </ul>
        @endif
    </td>
    @if($columns === 'do')
        <td class="wehdah-items-qty">{{ rtrim(rtrim(number_format((float) $item->quantity, 2), '0'), '.') }}</td>
        <td class="wehdah-items-unit">{{ $item->uom }}</td>
    @elseif($columns === 'receipt')
        <td class="wehdah-items-amount">{{ number_format((float) $item->line_total, 2) }}</td>
    @else
        <td class="wehdah-items-qty">{{ rtrim(rtrim(number_format((float) $item->quantity, 2), '0'), '.') }}</td>
        <td class="wehdah-items-unit">{{ $item->uom }}</td>
        <td class="wehdah-items-price">{{ number_format((float) $item->unit_price, 2) }}</td>
        <td class="wehdah-items-discount">{{ (float) $item->discount > 0 ? number_format((float) $item->discount, 2) : '-' }}</td>
        <td class="wehdah-items-total">{{ number_format((float) $item->line_total, 2) }}</td>
    @endif
</tr>
