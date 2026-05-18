@if(!empty($attachments))
    @foreach($attachments as $attachmentIndex => $attachment)
        <div class="page-break"></div>
        <div style="font-family: sans-serif; color: #1a1a1a;">
            <div style="border-bottom: 2px solid #1a3a5c; padding-bottom: 8px; margin-bottom: 12px;">
                <strong style="font-size: 16pt;">Artwork {{ $attachmentIndex + 1 }}</strong>
                <div style="font-size: 8pt; color: #666;">
                    {{ $attachment['caption'] ?: $attachment['original_name'] }}
                </div>
            </div>

            @if($attachment['is_image'] && $attachment['data_uri'])
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="border: 1px solid #d0d7de; padding: 8px; text-align: center; vertical-align: middle;">
                            <img src="{{ $attachment['data_uri'] }}" style="max-width: 100%; max-height: 930px;">
                        </td>
                    </tr>
                </table>
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
        </div>
    @endforeach
@endif
