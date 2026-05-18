@php $columnCount = $columnCount ?? 7; @endphp
@if(!empty($item->section_header))
<tr class="section-header-row">
    <td colspan="{{ $columnCount }}"
        style="background: {{ $brand['secondary'] ?? '#eef' }};
               color: {{ $brand['primary'] ?? '#1a3a5c' }};
               font-weight: bold; padding: 8px 10px; font-size: 10pt;
               border-top: 2px solid {{ $brand['primary'] ?? '#1a3a5c' }};
               border-bottom: 1px solid {{ $brand['primary'] ?? '#1a3a5c' }};">
        {{ $item->section_header }}
    </td>
</tr>
@endif
