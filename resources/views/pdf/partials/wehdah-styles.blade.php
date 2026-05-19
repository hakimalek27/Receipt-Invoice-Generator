@php
    $brand = $brand ?? [];
    $brandPrimary = $brand['primary'] ?? '#1a3a5c';
    $brandSecondary = $brand['secondary'] ?? '#f0f4f8';
    $brandAccent = $brand['accent'] ?? '#16427a';
@endphp
<style>
    @page { size: A4 portrait; margin: 12mm 10mm 15mm 10mm; }
    body { font-family: 'Lao UI', 'Rockwell', 'DejaVu Sans', serif; font-size: 10pt; color: #1a1a1a; margin: 0; }

    /* ------- Header (full + compact) ------- */
    .wehdah-header { background: {{ $brandPrimary }}; color: #ffffff; padding: 10px 14px; margin-bottom: 10px; }
    .wehdah-header-grid { display: table; width: 100%; }
    .wehdah-header-logo { display: table-cell; width: 60px; vertical-align: middle; padding-right: 12px; }
    .wehdah-header-logo img { max-width: 60px; max-height: 60px; }
    .wehdah-header-logo-fallback { width: 50px; height: 50px; background: #ffffff; color: {{ $brandPrimary }}; font-weight: bold; font-size: 18pt; text-align: center; line-height: 50px; border-radius: 4px; }
    .wehdah-header-info { display: table-cell; vertical-align: middle; }
    .wehdah-header-company { font-size: 13pt; font-weight: bold; letter-spacing: 0.5px; }
    .wehdah-header-reg { font-size: 8pt; font-weight: normal; margin-left: 6px; opacity: 0.85; }
    .wehdah-header-line { font-size: 8.5pt; opacity: 0.95; line-height: 1.35; }
    .wehdah-header-contact { font-size: 8pt; margin-top: 3px; opacity: 0.9; }
    .wehdah-header-compact { padding: 6px 12px; margin-bottom: 8px; }
    .wehdah-header-compact-grid { display: table; width: 100%; font-size: 8pt; }
    .wehdah-header-compact-name { display: table-cell; width: 32%; font-weight: bold; }
    .wehdah-header-compact-center { display: table-cell; width: 36%; text-align: center; font-weight: bold; letter-spacing: 0.6px; }
    .wehdah-header-compact-right { display: table-cell; width: 32%; text-align: right; }

    /* ------- Customer block ------- */
    .wehdah-meta-row { display: table; width: 100%; margin-bottom: 10px; }
    .wehdah-meta-col { display: table-cell; vertical-align: top; }
    .wehdah-meta-col-left { width: 58%; padding-right: 12px; }
    .wehdah-meta-col-right { width: 42%; }
    .wehdah-customer-label { font-size: 9pt; font-weight: bold; color: {{ $brandPrimary }}; margin-bottom: 4px; }
    .wehdah-customer-box { position: relative; border: 1px solid #c0c8d0; padding: 8px 10px; font-size: 9pt; line-height: 1.45; min-height: 90px; }
    .wehdah-corner { position: absolute; width: 8px; height: 8px; border-color: {{ $brandPrimary }}; }
    .wehdah-corner-tl { top: -1px; left: -1px; border-top: 2px solid; border-left: 2px solid; }
    .wehdah-corner-tr { top: -1px; right: -1px; border-top: 2px solid; border-right: 2px solid; }
    .wehdah-corner-bl { bottom: -1px; left: -1px; border-bottom: 2px solid; border-left: 2px solid; }
    .wehdah-corner-br { bottom: -1px; right: -1px; border-bottom: 2px solid; border-right: 2px solid; }
    .wehdah-customer-name { font-weight: bold; }
    .wehdah-customer-line { font-size: 9pt; }

    /* ------- Meta block ------- */
    .wehdah-meta-title { font-size: 18pt; font-weight: bold; color: {{ $brandPrimary }}; text-align: right; margin-bottom: 6px; letter-spacing: 1px; }
    .wehdah-meta-table { width: 100%; font-size: 9pt; }
    .wehdah-meta-key { text-align: right; padding: 2px 6px 2px 0; color: #666; width: 38%; }
    .wehdah-meta-val { text-align: right; font-weight: bold; }

    /* ------- Intro (quotation) ------- */
    .wehdah-intro { font-size: 9.5pt; margin: 6px 0 8px; }

    /* ------- Items table ------- */
    table.wehdah-items { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
    table.wehdah-items th { background: {{ $brandPrimary }}; color: #ffffff; padding: 6px 5px; font-size: 8.5pt; text-align: left; }
    table.wehdah-items td { padding: 5px; border-bottom: 1px solid #e0e0e0; font-size: 9pt; vertical-align: top; page-break-inside: avoid; }
    table.wehdah-items th.r, table.wehdah-items td.r { text-align: right; }
    .wehdah-items-num { width: 6%; }
    .wehdah-items-qty { width: 8%; text-align: right; }
    .wehdah-items-unit { width: 8%; }
    .wehdah-items-price { width: 13%; text-align: right; }
    .wehdah-items-discount { width: 10%; text-align: right; }
    .wehdah-items-total { width: 14%; text-align: right; font-weight: 600; }
    .wehdah-items-amount { width: 18%; text-align: right; font-weight: 600; }
    .wehdah-items-bullets { margin: 4px 0 0 14px; padding: 0; }
    .wehdah-items-bullets li { font-size: 8.5pt; line-height: 1.4; }

    /* ------- Continued footer ------- */
    .wehdah-continued { text-align: right; font-size: 8pt; font-style: italic; color: #888; margin: 6px 0 2px; }

    /* ------- Totals ------- */
    .wehdah-amount-words { font-weight: bold; font-size: 8.5pt; margin: 8px 0; padding: 6px 10px; background: {{ $brandSecondary }}; border: 1px solid #c0d0e0; }
    .wehdah-totals { margin-left: auto; width: 50%; border-collapse: collapse; }
    .wehdah-totals td { padding: 4px 8px; font-size: 9.5pt; }
    .wehdah-totals .wehdah-grand-label { font-weight: bold; font-size: 10pt; background: {{ $brandPrimary }}; color: #ffffff; }
    .wehdah-totals .wehdah-grand-val { font-weight: bold; font-size: 12pt; border: 2px solid {{ $brandPrimary }}; text-align: right; }

    /* ------- Bank ------- */
    .wehdah-bank { font-size: 8.5pt; margin: 8px 0; padding: 6px 10px; border: 1px solid #c0d0e0; background: #fafcff; }
    .wehdah-bank-label { font-weight: bold; color: {{ $brandPrimary }}; margin-right: 8px; }
    .wehdah-bank-line { font-weight: 600; }
    .wehdah-bank-sep { color: #888; }

    /* ------- Terms / footer text ------- */
    .wehdah-terms { font-size: 8pt; margin: 8px 0 4px; line-height: 1.4; }
    .wehdah-terms strong { color: {{ $brandPrimary }}; }

    /* ------- Signature ------- */
    table.wehdah-signature { width: 100%; margin-top: 28px; border-collapse: collapse; }
    .wehdah-signature-single { margin-top: 28px; }
    .wehdah-signature-cell { width: 50%; vertical-align: bottom; padding: 4px; font-size: 8pt; }
    .wehdah-signature-cell-left { padding-right: 16px; }
    .wehdah-signature-cell-right { padding-left: 16px; text-align: right; }
    .wehdah-signature-intro { font-size: 8pt; margin-bottom: 28px; }
    .wehdah-signature-intro-tight { font-size: 8pt; margin-bottom: 6px; }
    .wehdah-signature-line { border-top: 1px solid #1a1a1a; margin-bottom: 4px; }
    .wehdah-signature-label { font-size: 8pt; font-weight: bold; }
    .wehdah-signature-single .wehdah-signature-right { width: 60%; margin-left: auto; }
    .wehdah-signature-images { position: relative; height: 60px; margin-bottom: 4px; }
    .wehdah-signature-images img.wehdah-sig-img { max-height: 50px; max-width: 160px; vertical-align: bottom; }
    .wehdah-signature-images img.wehdah-stamp-img { max-height: 60px; max-width: 80px; vertical-align: bottom; margin-left: 8px; opacity: 0.92; }

    /* ------- Footer / page numbers ------- */
    .wehdah-page-number { text-align: right; font-size: 7.5pt; color: #888; margin-top: 6px; }
    .wehdah-footer-doc { margin-top: 8px; padding-top: 4px; border-top: 1px solid #e0e0e0; font-size: 7pt; text-align: center; color: #888; }
    .page-break { page-break-before: always; }

    /* ------- Artwork ------- */
    .wehdah-artwork-bar { background: {{ $brandSecondary }}; color: {{ $brandPrimary }}; padding: 8px 12px; margin-bottom: 12px; display: table; width: 100%; }
    .wehdah-artwork-title { display: table-cell; font-size: 14pt; font-weight: bold; }
    .wehdah-artwork-doc { display: table-cell; text-align: right; font-size: 9pt; vertical-align: middle; }
    .wehdah-artwork-image { border: 1px solid #d0d7de; padding: 8px; text-align: center; vertical-align: middle; }
    .wehdah-artwork-image img { max-width: 100%; max-height: 880px; }
    .wehdah-artwork-caption { font-size: 9pt; color: #555; margin-top: 6px; text-align: center; }
    .wehdah-artwork-confirm { margin-top: 24px; font-size: 10pt; text-align: center; font-weight: 600; }
    .wehdah-artwork-confirm-line { margin: 30px auto 6px; width: 60%; border-top: 1px solid #1a1a1a; }
</style>
