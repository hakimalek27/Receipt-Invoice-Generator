@php
    $documentTitleEn = $documentTitleEn ?? null;
    $showConfirmation = $showConfirmation ?? false;
@endphp
@if(!empty($attachments))
    @foreach($attachments as $attachmentIndex => $attachment)
        <div class="page-break"></div>
        <div style="font-family: 'Lao UI', 'Rockwell', 'DejaVu Sans', sans-serif; color: #1a1a1a;">
            <div style="background: #e8edf3; color: #1a3a5c; padding: 8px 12px; margin-bottom: 12px; display: table; width: 100%;">
                <div style="display: table-cell; font-size: 14pt; font-weight: bold;">Artwork {{ $attachmentIndex + 1 }}</div>
                @if($documentTitleEn || !empty($document->official_number))
                    <div style="display: table-cell; text-align: right; font-size: 9pt; vertical-align: middle;">
                        @if($documentTitleEn){{ $documentTitleEn }}@endif
                        @if(!empty($document->official_number)) &middot; {{ $document->official_number }}@endif
                    </div>
                @endif
            </div>

            @if($attachment['is_image'] && $attachment['data_uri'])
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="border: 1px solid #d0d7de; padding: 6px; text-align: center; vertical-align: middle;">
                            <img src="{{ $attachment['data_uri'] }}" alt="Artwork {{ $attachmentIndex + 1 }}" style="max-width: 100%; max-height: 720px;">
                        </td>
                    </tr>
                </table>
                @if(!empty($attachment['caption']) || !empty($attachment['original_name']))
                    <div style="font-size: 9pt; color: #555; margin-top: 4px; text-align: center;">{{ $attachment['caption'] ?: $attachment['original_name'] }}</div>
                @endif
            @else
                <table style="width: 100%; border-collapse: collapse; font-size: 10pt;">
                    <tr>
                        <td style="border: 1px solid #d0d7de; padding: 10px; background: #f6f8fa;">
                            <strong>Attachment file:</strong> {{ $attachment['original_name'] }}<br>
                            <strong>MIME:</strong> {{ $attachment['mime_type'] }}<br>
                            <strong>Size:</strong> {{ number_format((int) $attachment['size_bytes']) }} bytes
                        </td>
                    </tr>
                </table>
            @endif

            @if($showConfirmation && $loop->last)
                <div style="margin-top: 16px; font-size: 10pt; text-align: center; font-weight: 600;">All artwork has been confirmed</div>
                <div style="margin: 18px auto 4px; width: 60%; border-top: 1px solid #1a1a1a;"></div>
                <div style="text-align: center; font-size: 9pt; font-weight: bold;">Company Sign &amp; Chop</div>
            @endif
        </div>
    @endforeach
@endif
