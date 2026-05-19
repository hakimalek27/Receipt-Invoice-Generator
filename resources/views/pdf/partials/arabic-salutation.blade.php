@if(!empty($document->include_arabic_salutation))
<div style="text-align: center; font-family: 'DejaVu Sans', sans-serif;
            font-size: 14pt; margin: 12px 0 6px;
            color: {{ $brand['primary'] ?? '#1a3a5c' }}; direction: rtl;">
    بِسْمِ ٱللَّٰهِ ٱلرَّحْمَٰنِ ٱلرَّحِيمِ
</div>
<div style="text-align: center; font-size: 9pt; color: #555; margin-bottom: 10px;">
    Dengan nama Allah Yang Maha Pemurah lagi Maha Penyayang
</div>
<div style="text-align: center; font-size: 9pt; color: #555; margin-bottom: 10px;
            font-weight: bold; letter-spacing: 0.4px;">
    السَّلامُ عَلَيْكُمْ وَرَحْمَةُ اللهِ وَبَرَكَاتُهُ
</div>
@endif
