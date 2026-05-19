@php
    $documentTitleEn = $documentTitleEn ?? null;
    $showConfirmation = $showConfirmation ?? false;

    /**
     * Layout rules (per user spec):
     * - 1 artwork on a page  → single full
     * - 2 artworks on a page → 2-up vertical stack
     * - 3 or 4 artworks      → 2×2 grid (3rd-case leaves one cell blank)
     * - more than 4          → chunk into pages of max 4, last page uses
     *                          single/pair/grid based on remainder.
     */
    $chunks = ! empty($attachments) ? array_chunk($attachments, 4) : [];
@endphp

@if(! empty($chunks))
    @foreach($chunks as $chunkIndex => $chunk)
        @php
            $count = count($chunk);
            $startIndex = $chunkIndex * 4;
            $isLastChunk = $loop->last;
        @endphp

        <div class="page-break"></div>

        <div style="font-family: 'Lao UI', 'Rockwell', 'DejaVu Sans', sans-serif; color: #1a1a1a;">
            <div style="background: #e8edf3; color: #1a3a5c; padding: 6px 12px; margin-bottom: 8px; display: table; width: 100%;">
                <div style="display: table-cell; font-size: 13pt; font-weight: bold;">
                    @if($count === 1)
                        Artwork {{ $startIndex + 1 }}
                    @elseif($count === 2)
                        Artworks {{ $startIndex + 1 }} &ndash; {{ $startIndex + 2 }}
                    @else
                        Artworks {{ $startIndex + 1 }} &ndash; {{ $startIndex + $count }}
                    @endif
                </div>
                @if($documentTitleEn || !empty($document->official_number))
                    <div style="display: table-cell; text-align: right; font-size: 9pt; vertical-align: middle;">
                        @if($documentTitleEn){{ $documentTitleEn }}@endif
                        @if(!empty($document->official_number)) &middot; {{ $document->official_number }}@endif
                    </div>
                @endif
            </div>

            @if($count === 1)
                {{-- ============ Single full-page layout ============ --}}
                @php $attachment = $chunk[0]; $artworkNum = $startIndex + 1; @endphp
                @if($attachment['is_image'] && $attachment['data_uri'])
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr>
                            <td style="border: 1px solid #d0d7de; padding: 4px; text-align: center; vertical-align: middle;">
                                <img src="{{ $attachment['data_uri'] }}"
                                     alt="Artwork {{ $artworkNum }}"
                                     style="max-width: 100%; max-height: 210mm;">
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
                @if(!empty($attachment['caption']) || !empty($attachment['original_name']))
                    <div style="font-size: 9pt; color: #555; margin-top: 3px; text-align: center;">
                        {{ $attachment['caption'] ?: $attachment['original_name'] }}
                    </div>
                @endif

            @elseif($count === 2)
                {{-- ============ 2-up vertical stack ============ --}}
                <table style="width: 100%; border-collapse: separate; border-spacing: 0 4px;">
                    @foreach($chunk as $i => $attachment)
                        @php $artworkNum = $startIndex + $i + 1; @endphp
                        <tr>
                            <td style="vertical-align: top;">
                                <div style="font-size: 9pt; font-weight: bold; color: #1a3a5c; margin-bottom: 1px;">
                                    Artwork {{ $artworkNum }}{{ !empty($attachment['caption']) ? ' — '.$attachment['caption'] : '' }}
                                </div>
                                @if($attachment['is_image'] && $attachment['data_uri'])
                                    <table style="width: 100%; border-collapse: collapse;">
                                        <tr>
                                            <td style="border: 1px solid #d0d7de; padding: 3px; text-align: center; vertical-align: middle;">
                                                <img src="{{ $attachment['data_uri'] }}"
                                                     alt="Artwork {{ $artworkNum }}"
                                                     style="max-width: 100%; max-height: 100mm;">
                                            </td>
                                        </tr>
                                    </table>
                                @else
                                    <table style="width: 100%; border-collapse: collapse; font-size: 10pt;">
                                        <tr>
                                            <td style="border: 1px solid #d0d7de; padding: 10px; background: #f6f8fa;">
                                                <strong>{{ $attachment['original_name'] }}</strong> &middot;
                                                {{ $attachment['mime_type'] }} &middot;
                                                {{ number_format((int) $attachment['size_bytes']) }} bytes
                                            </td>
                                        </tr>
                                    </table>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </table>

            @else
                {{-- ============ 2x2 grid (3 or 4 artworks) ============ --}}
                @php
                    // Pad chunk to 4 cells so the table always shows a 2×2 grid; trailing cells render blank.
                    $padded = array_pad($chunk, 4, null);
                @endphp
                <table style="width: 100%; border-collapse: separate; border-spacing: 5px 5px;">
                    @foreach([[0, 1], [2, 3]] as $row)
                        <tr>
                            @foreach($row as $cellIndex)
                                @php $attachment = $padded[$cellIndex] ?? null; @endphp
                                <td style="width: 50%; vertical-align: top;">
                                    @if($attachment !== null)
                                        @php $artworkNum = $startIndex + $cellIndex + 1; @endphp
                                        <div style="font-size: 8.5pt; font-weight: bold; color: #1a3a5c; margin-bottom: 1px;">
                                            Artwork {{ $artworkNum }}{{ !empty($attachment['caption']) ? ' — '.$attachment['caption'] : '' }}
                                        </div>
                                        @if($attachment['is_image'] && $attachment['data_uri'])
                                            <table style="width: 100%; border-collapse: collapse;">
                                                <tr>
                                                    <td style="border: 1px solid #d0d7de; padding: 3px; text-align: center; vertical-align: middle;">
                                                        <img src="{{ $attachment['data_uri'] }}"
                                                             alt="Artwork {{ $artworkNum }}"
                                                             style="max-width: 100%; max-height: 105mm;">
                                                    </td>
                                                </tr>
                                            </table>
                                        @else
                                            <table style="width: 100%; border-collapse: collapse; font-size: 9pt;">
                                                <tr>
                                                    <td style="border: 1px solid #d0d7de; padding: 6px; background: #f6f8fa;">
                                                        <strong>{{ $attachment['original_name'] }}</strong><br>
                                                        {{ $attachment['mime_type'] }}<br>
                                                        {{ number_format((int) $attachment['size_bytes']) }} bytes
                                                    </td>
                                                </tr>
                                            </table>
                                        @endif
                                    @else
                                        <div style="height: 1mm;">&nbsp;</div>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </table>
            @endif

            @if($showConfirmation && $isLastChunk)
                <div style="margin-top: 8px; font-size: 9.5pt; text-align: center; font-weight: 600;">All artwork has been confirmed</div>
                <div style="margin: 10px auto 3px; width: 60%; border-top: 1px solid #1a1a1a;"></div>
                <div style="text-align: center; font-size: 9pt; font-weight: bold;">Company Sign &amp; Chop</div>
            @endif
        </div>
    @endforeach
@endif
