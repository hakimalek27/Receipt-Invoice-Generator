@php
    $label = $label ?? 'Bill To:';
    $customerLines = [];
    if ($customer) {
        $rawAddress = (string) ($customer->address ?? '');
        if ($rawAddress !== '') {
            $customerLines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $rawAddress))));
        }
    }
    $attn = $customer->attention_to ?? null;
    $tel = $customer->phone ?? null;
    $email = $customer->email ?? null;
    $fax = $customer->fax ?? null;
@endphp
<div class="ws-billto-box">
    <div class="ws-billto-label">{{ $label }}</div>
    @if($customer)
        <div class="ws-billto-name">{{ $customer->name }}</div>
        @foreach($customerLines as $line)
            <div class="ws-billto-line">{{ $line }}</div>
        @endforeach
        <table class="ws-billto-table">
            <tr>
                <td class="ws-billto-key">Attn:</td>
                <td class="ws-billto-val" colspan="3">{{ $attn ?: '' }}</td>
            </tr>
            <tr>
                <td class="ws-billto-key">Tel:</td>
                <td class="ws-billto-val">{{ $tel ?: '' }}</td>
                <td class="ws-billto-key ws-billto-key-fax">Fax:</td>
                <td class="ws-billto-val">{{ $fax ?: '' }}</td>
            </tr>
            <tr>
                <td class="ws-billto-key">Email:</td>
                <td class="ws-billto-val" colspan="3">{{ $email ?: '' }}</td>
            </tr>
        </table>
    @else
        <div class="ws-billto-name">Walk-in Customer</div>
    @endif
</div>
