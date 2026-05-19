@php $columnCount = $columnCount ?? 7; @endphp
@if(!empty($item->section_header))
<tr class="ws-section-row">
    <td colspan="{{ $columnCount }}">{{ $item->section_header }}</td>
</tr>
@endif
