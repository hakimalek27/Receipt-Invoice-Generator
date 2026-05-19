@php
    $accentDark = '#002060';
    $accentLine = '#1F3A5F';
    $textPrimary = '#1a1a1a';
    $textMuted = '#555';
@endphp
<style>
@font-face {
    font-family: 'RobotoSlab';
    src: url('{{ storage_path('fonts/RobotoSlab-Variable.ttf') }}') format('truetype');
    font-weight: 100 900;
    font-style: normal;
}

@page { size: A4 portrait; margin: 12mm 11mm 14mm 11mm; }

body {
    font-family: 'DejaVu Sans', sans-serif;
    font-size: 10pt;
    color: {{ $textPrimary }};
    margin: 0;
    line-height: 1.4;
}

/* ============ Header strip (dark blue title bar) ============ */
.ws-title-strip {
    background: {{ $accentDark }};
    color: #ffffff;
    text-align: center;
    padding: 9px 0 8px;
    margin: -2px -2px 0 -2px;
    border-bottom: 4px solid #ffffff;
}
.ws-title-strip-text {
    font-family: 'RobotoSlab', 'Times New Roman', serif;
    font-size: 22pt;
    font-weight: 700;
    letter-spacing: 4px;
    text-decoration: underline;
    text-underline-offset: 4px;
}

/* ============ Company identity block (centered) ============ */
.ws-company-block {
    text-align: center;
    padding: 10px 0 6px;
    border-bottom: 1px solid #cccccc;
    margin-bottom: 10px;
}
.ws-company-name {
    font-family: 'DejaVu Sans', sans-serif;
    font-size: 13.5pt;
    font-weight: bold;
    color: {{ $accentDark }};
    letter-spacing: 0.4px;
}
.ws-company-reg {
    font-size: 9pt;
    font-weight: normal;
    color: {{ $textMuted }};
    margin-left: 4px;
}
.ws-company-address {
    font-family: 'DejaVu Sans', sans-serif;
    font-size: 9.5pt;
    color: {{ $textPrimary }};
    line-height: 1.45;
    margin-top: 3px;
}
.ws-company-contact {
    font-family: 'DejaVu Sans Condensed', 'DejaVu Sans', sans-serif;
    font-size: 9pt;
    color: {{ $textPrimary }};
    margin-top: 3px;
    letter-spacing: 0.2px;
}

/* ============ Meta + Bill To row (two columns) ============ */
.ws-meta-row { display: table; width: 100%; margin: 6px 0 10px; }
.ws-meta-col { display: table-cell; vertical-align: top; }
.ws-meta-col-left { width: 58%; padding-right: 10px; }
.ws-meta-col-right { width: 42%; }

.ws-billto-label {
    font-size: 10pt; font-weight: bold; color: {{ $accentDark }};
    border-bottom: 1px solid {{ $accentDark }};
    padding-bottom: 2px; margin-bottom: 4px;
}
.ws-billto-name { font-size: 11pt; font-weight: bold; color: {{ $textPrimary }}; }
.ws-billto-line { font-size: 9pt; color: {{ $textPrimary }}; line-height: 1.5; }
.ws-billto-table { width: 100%; margin-top: 4px; font-size: 9pt; }
.ws-billto-table td { padding: 1px 4px 1px 0; vertical-align: top; }
.ws-billto-key { font-weight: bold; width: 50px; color: {{ $textMuted }}; }
.ws-billto-val { color: {{ $textPrimary }}; }

.ws-meta-table { width: 100%; border-collapse: collapse; font-size: 10pt; }
.ws-meta-table td {
    padding: 4px 8px;
    border: 1px solid {{ $accentDark }};
}
.ws-meta-key {
    background: {{ $accentDark }};
    color: #ffffff;
    font-weight: bold;
    text-align: right;
    width: 42%;
    font-family: 'DejaVu Sans', sans-serif;
}
.ws-meta-val {
    text-align: left;
    background: #ffffff;
    font-weight: bold;
    font-family: 'DejaVu Sans', sans-serif;
}

/* ============ Intro text (quote/OR only) ============ */
.ws-intro { font-size: 10pt; margin: 6px 0 8px; font-style: italic; color: {{ $textPrimary }}; }

/* ============ Items table ============ */
table.ws-items {
    width: 100%; border-collapse: collapse; margin-top: 6px;
}
table.ws-items thead th {
    background: {{ $accentDark }};
    color: #ffffff;
    font-family: 'DejaVu Sans', sans-serif;
    font-size: 9.5pt;
    font-weight: bold;
    padding: 7px 5px;
    border: 1px solid {{ $accentDark }};
    letter-spacing: 0.3px;
}
table.ws-items tbody td {
    padding: 6px 5px;
    border-left: 1px solid #cfd7dc;
    border-right: 1px solid #cfd7dc;
    border-bottom: 1px solid #e6ebef;
    font-size: 9.5pt;
    vertical-align: top;
}
table.ws-items tbody tr:last-child td { border-bottom: 1px solid {{ $accentDark }}; }
table.ws-items .c { text-align: center; }
table.ws-items .r { text-align: right; }
table.ws-items .l { text-align: left; }

.ws-col-item   { width: 6%;  text-align: center; }
.ws-col-desc   { width: 42%; text-align: left;   }
.ws-col-qty    { width: 7%;  text-align: center; }
.ws-col-unit   { width: 7%;  text-align: left;   }
.ws-col-price  { width: 12%; text-align: right;  }
.ws-col-disc   { width: 9%;  text-align: right;  }
.ws-col-total  { width: 17%; text-align: right;  font-weight: 600; }
.ws-col-amount { width: 18%; text-align: right;  font-weight: 600; }

.ws-section-row td {
    background: #eef2f7 !important;
    color: {{ $accentDark }};
    font-weight: bold;
    font-size: 10pt;
    padding: 6px 8px !important;
    border-top: 2px solid {{ $accentDark }} !important;
}

.ws-items-bullets { margin: 4px 0 0 14px; padding: 0; }
.ws-items-bullets li { font-size: 9pt; line-height: 1.4; }
.ws-items-img { float: left; max-width: 56px; max-height: 56px; margin-right: 8px; border: 1px solid #ddd; }

/* ============ Continued / page footer ============ */
.ws-continued { text-align: right; font-size: 8.5pt; font-style: italic; color: #888; margin: 6px 0 2px; }

/* ============ Totals block ============ */
.ws-totals-row { display: table; width: 100%; margin-top: 4px; }
.ws-totals-words-cell, .ws-totals-grand-cell { display: table-cell; vertical-align: middle; }
.ws-totals-words-cell { width: 60%; padding-right: 12px; }
.ws-totals-grand-cell { width: 40%; }

.ws-words-label { font-size: 9pt; font-weight: bold; color: {{ $accentDark }}; }
.ws-words-text {
    font-family: 'DejaVu Sans', Arial, sans-serif;
    font-size: 10pt;
    font-weight: bold;
    text-transform: uppercase;
    color: {{ $textPrimary }};
    letter-spacing: 0.3px;
    line-height: 1.4;
    padding: 4px 6px;
    background: #f4f7fa;
    border-left: 3px solid {{ $accentDark }};
}

.ws-grand-table { width: 100%; border-collapse: collapse; }
.ws-grand-table td { padding: 6px 9px; }
.ws-grand-label {
    background: {{ $accentDark }};
    color: #ffffff;
    font-weight: bold;
    font-size: 10pt;
    text-align: right;
    border: 2px solid {{ $accentDark }};
    font-family: 'DejaVu Sans', sans-serif;
}
.ws-grand-val {
    font-size: 13pt;
    font-weight: bold;
    text-align: right;
    border: 2px solid {{ $accentDark }};
    background: #ffffff;
    font-family: 'DejaVu Sans', sans-serif;
}

.ws-sub-totals { width: 50%; margin-left: auto; border-collapse: collapse; margin-bottom: 4px; }
.ws-sub-totals td { padding: 3px 9px; font-size: 9.5pt; }
.ws-sub-totals td.r { text-align: right; }

/* ============ Bank details strip ============ */
.ws-bank {
    text-align: center;
    font-size: 9pt;
    margin: 14px 0 6px;
    padding: 6px 8px;
    background: #f4f7fa;
    border-top: 1px solid {{ $accentLine }};
    border-bottom: 1px solid {{ $accentLine }};
    line-height: 1.5;
}
.ws-bank-label { font-weight: bold; color: {{ $accentDark }}; }
.ws-bank-sep { color: #888; margin: 0 6px; }

/* ============ Terms ============ */
.ws-terms {
    font-size: 8.5pt;
    color: {{ $textPrimary }};
    margin: 6px 0 4px;
    line-height: 1.45;
}
.ws-terms strong { color: {{ $accentDark }}; }

/* ============ Signature block ============ */
table.ws-signature { width: 100%; margin-top: 26px; border-collapse: collapse; }
.ws-signature-cell {
    width: 50%; vertical-align: bottom; padding: 4px;
    font-family: 'DejaVu Sans', sans-serif;
}
.ws-signature-cell-left { padding-right: 18px; }
.ws-signature-cell-right { padding-left: 18px; text-align: right; }
.ws-signature-intro { font-size: 9pt; margin-bottom: 28px; }
.ws-signature-line { border-top: 1px solid {{ $textPrimary }}; margin-bottom: 4px; }
.ws-signature-label { font-size: 9pt; font-weight: bold; color: {{ $textPrimary }}; }
.ws-signature-images { position: relative; height: 60px; margin-bottom: 4px; }
.ws-signature-images img.ws-sig-img { max-height: 50px; max-width: 160px; vertical-align: bottom; }
.ws-signature-images img.ws-stamp-img { max-height: 60px; max-width: 80px; vertical-align: bottom; margin-left: 8px; opacity: 0.9; }

/* ============ Payment meta box (OR only) ============ */
.ws-payment-meta {
    margin: 8px 0;
    padding: 6px 10px;
    background: #f4f7fa;
    border: 1px solid {{ $accentDark }};
    font-size: 9pt;
}
.ws-payment-meta strong { color: {{ $accentDark }}; }
.ws-payment-meta-row { display: table; width: 100%; }
.ws-payment-meta-cell { display: table-cell; padding-right: 18px; }

/* ============ Footer doc note ============ */
.ws-footer-doc {
    margin-top: 10px;
    padding-top: 4px;
    border-top: 1px solid #e0e0e0;
    font-size: 7.5pt;
    text-align: center;
    color: #888;
}
.ws-page-number { text-align: right; font-size: 7.5pt; color: #888; margin-top: 4px; }
.page-break { page-break-before: always; }

/* ============ Compact header for continuation pages ============ */
.ws-header-compact {
    background: {{ $accentDark }};
    color: #ffffff;
    padding: 5px 12px;
    margin: -2px -2px 8px -2px;
    display: table;
    width: 100%;
    font-family: 'DejaVu Sans', sans-serif;
    font-size: 9pt;
}
.ws-header-compact-name { display: table-cell; width: 32%; font-weight: bold; }
.ws-header-compact-center { display: table-cell; width: 36%; text-align: center; font-weight: bold; letter-spacing: 0.6px; }
.ws-header-compact-right { display: table-cell; width: 32%; text-align: right; }

/* ============ Artwork pages (reused) ============ */
.ws-artwork-bar {
    background: #eef2f7;
    color: {{ $accentDark }};
    padding: 8px 12px;
    margin-bottom: 12px;
    display: table;
    width: 100%;
}
.ws-artwork-title { display: table-cell; font-size: 14pt; font-weight: bold; }
.ws-artwork-doc { display: table-cell; text-align: right; font-size: 9pt; vertical-align: middle; }
.ws-artwork-image { border: 1px solid #d0d7de; padding: 8px; text-align: center; vertical-align: middle; }
.ws-artwork-image img { max-width: 100%; max-height: 880px; }
.ws-artwork-caption { font-size: 9pt; color: #555; margin-top: 6px; text-align: center; }
.ws-artwork-confirm { margin-top: 24px; font-size: 10pt; text-align: center; font-weight: 600; }
.ws-artwork-confirm-line { margin: 30px auto 6px; width: 60%; border-top: 1px solid #1a1a1a; }
</style>
