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

@page { size: A4 portrait; margin: 8mm 9mm 8mm 9mm; }

body {
    font-family: 'DejaVu Sans', sans-serif;
    font-size: 9.5pt;
    color: {{ $textPrimary }};
    margin: 0;
    line-height: 1.32;
}

/* ============ Header strip (dark blue title bar) ============ */
.ws-title-strip {
    background: {{ $accentDark }};
    color: #ffffff;
    text-align: center;
    padding: 4px 0 4px;
    margin: -2px -2px 0 -2px;
    border-bottom: 3px solid #ffffff;
}
.ws-title-strip-text {
    font-family: 'RobotoSlab', 'Times New Roman', serif;
    font-size: 17pt;
    font-weight: 700;
    letter-spacing: 3px;
    text-decoration: underline;
    text-underline-offset: 3px;
}

/* ============ Header row: logo cell + company block cell ============ */
.ws-header-table {
    width: 100%;
    border-collapse: collapse;
    margin: 4px 0 4px;
}
.ws-header-logo-cell {
    width: 210px;
    vertical-align: middle;
    padding: 6px 1cm 6px 4px;
    text-align: right;
    border-bottom: 1px solid #cccccc;
}
.ws-header-logo-img {
    max-width: 160px;
    max-height: 152px;
    display: inline-block;
}
.ws-header-text-cell {
    vertical-align: middle;
    padding: 4px 0 3px;
    border-bottom: 1px solid #cccccc;
}
/* Suppress the inner block's own bottom border so the table cells'
   shared bottom border is the only visual divider. */
.ws-header-text-cell .ws-company-block {
    border-bottom: none;
    margin-bottom: 0;
    padding-bottom: 0;
}

/* ============ Company identity block (centered) ============ */
.ws-company-block {
    text-align: center;
    padding: 4px 0 3px;
    border-bottom: 1px solid #cccccc;
    margin-bottom: 4px;
    line-height: 1.25;
}
.ws-company-name {
    font-family: 'DejaVu Sans', sans-serif;
    font-size: 12pt;
    font-weight: bold;
    color: {{ $accentDark }};
    letter-spacing: 0.3px;
}
.ws-company-reg {
    font-size: 8.5pt;
    font-weight: normal;
    color: {{ $textMuted }};
    margin-left: 3px;
}
.ws-company-address {
    font-family: 'DejaVu Sans', sans-serif;
    font-size: 8.5pt;
    color: {{ $textPrimary }};
    line-height: 1.3;
}
.ws-company-contact {
    font-family: 'DejaVu Sans Condensed', 'DejaVu Sans', sans-serif;
    font-size: 8.5pt;
    color: {{ $textPrimary }};
    margin-top: 1px;
    letter-spacing: 0.15px;
}

/* ============ Meta + Bill To row (two columns) ============ */
.ws-meta-row { display: table; width: 100%; margin: 3px 0 6px; }
.ws-meta-col { display: table-cell; vertical-align: top; }
.ws-meta-col-left { width: 58%; padding-right: 8px; }
.ws-meta-col-right { width: 42%; }

.ws-billto-box {
    border: 1px solid #333333;
    padding: 5px 8px 6px;
    margin-bottom: 0;
}
.ws-billto-label {
    font-size: 9pt; font-weight: bold; color: {{ $accentDark }};
    border-bottom: 1px solid {{ $accentDark }};
    padding-bottom: 1px; margin-bottom: 3px;
}
.ws-billto-name { font-size: 10pt; font-weight: bold; color: {{ $textPrimary }}; }
.ws-billto-line { font-size: 8.5pt; color: {{ $textPrimary }}; line-height: 1.4; }
.ws-billto-table { width: 100%; margin-top: 4px; font-size: 8.5pt; }
.ws-billto-table td { padding: 0 4px 0 0; vertical-align: top; line-height: 1.5; }
.ws-billto-key { font-weight: bold; width: 42px; color: {{ $textMuted }}; }
.ws-billto-key-fax { width: 38px; padding-left: 6px; }
.ws-billto-val { color: {{ $textPrimary }}; }

.ws-meta-table { width: 100%; border-collapse: collapse; font-size: 9pt; }
.ws-meta-table td {
    padding: 3px 7px;
    border: 1px solid {{ $accentDark }};
    line-height: 1.25;
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
.ws-intro { font-size: 9pt; margin: 3px 0 4px; font-style: italic; color: {{ $textPrimary }}; }

/* ============ Items table ============ */
table.ws-items {
    width: 100%; border-collapse: collapse; margin-top: 3px;
}
table.ws-items thead th {
    background: {{ $accentDark }};
    color: #ffffff;
    font-family: 'DejaVu Sans', sans-serif;
    font-size: 9pt;
    font-weight: bold;
    padding: 4px 5px;
    border: 1px solid {{ $accentDark }};
    letter-spacing: 0.3px;
}
table.ws-items tbody td {
    padding: 3px 5px;
    border-left: 1px solid #cfd7dc;
    border-right: 1px solid #cfd7dc;
    border-bottom: 1px solid #e6ebef;
    font-size: 9pt;
    vertical-align: top;
    line-height: 1.3;
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
    font-size: 9pt;
    padding: 3px 8px !important;
    border-top: 2px solid {{ $accentDark }} !important;
}

.ws-items-bullets { margin: 2px 0 0 14px; padding: 0; }
.ws-items-bullets li { font-size: 8.5pt; line-height: 1.3; }
.ws-items-img { float: left; max-width: 50px; max-height: 50px; margin-right: 6px; border: 1px solid #ddd; }

/* ============ Continued / page footer ============ */
.ws-continued { text-align: right; font-size: 8pt; font-style: italic; color: #888; margin: 3px 0 1px; }

/* ============ Totals block ============ */
.ws-totals-row { display: table; width: 100%; margin-top: 3px; }
.ws-totals-words-cell, .ws-totals-grand-cell { display: table-cell; vertical-align: middle; }
.ws-totals-words-cell { width: 60%; padding-right: 10px; }
.ws-totals-grand-cell { width: 40%; }

.ws-words-label { font-size: 8.5pt; font-weight: bold; color: {{ $accentDark }}; margin-bottom: 1px; }
.ws-words-text {
    font-family: 'DejaVu Sans', Arial, sans-serif;
    font-size: 9.5pt;
    font-weight: bold;
    text-transform: uppercase;
    color: {{ $textPrimary }};
    letter-spacing: 0.25px;
    line-height: 1.3;
    padding: 3px 6px;
    background: #f4f7fa;
    border-left: 3px solid {{ $accentDark }};
}

.ws-grand-table { width: 100%; border-collapse: collapse; }
.ws-grand-table td { padding: 4px 8px; }
.ws-grand-label {
    background: {{ $accentDark }};
    color: #ffffff;
    font-weight: bold;
    font-size: 9.5pt;
    text-align: right;
    border: 2px solid {{ $accentDark }};
    font-family: 'DejaVu Sans', sans-serif;
}
.ws-grand-val {
    font-size: 12pt;
    font-weight: bold;
    text-align: right;
    border: 2px solid {{ $accentDark }};
    background: #ffffff;
    font-family: 'DejaVu Sans', sans-serif;
}

.ws-sub-totals { width: 50%; margin-left: auto; border-collapse: collapse; margin-bottom: 2px; }
.ws-sub-totals td { padding: 1px 8px; font-size: 9pt; }
.ws-sub-totals td.r { text-align: right; }

/* ============ Bank details strip ============ */
.ws-bank {
    text-align: center;
    font-size: 8.5pt;
    margin: 6px 0 3px;
    padding: 4px 8px;
    background: #f4f7fa;
    border-top: 1px solid {{ $accentLine }};
    border-bottom: 1px solid {{ $accentLine }};
    line-height: 1.35;
}
.ws-bank-label { font-weight: bold; color: {{ $accentDark }}; }
.ws-bank-sep { color: #888; margin: 0 6px; }

/* ============ Terms ============ */
.ws-terms {
    font-size: 8pt;
    color: {{ $textPrimary }};
    margin: 3px 0 2px;
    line-height: 1.35;
}
.ws-terms strong { color: {{ $accentDark }}; }

/* ============ Signature block ============ */
table.ws-signature { width: 100%; margin-top: 10px; border-collapse: collapse; }
.ws-signature-cell {
    width: 50%; vertical-align: bottom; padding: 2px;
    font-family: 'DejaVu Sans', sans-serif;
}
.ws-signature-cell-left { padding-right: 14px; }
.ws-signature-cell-right { padding-left: 14px; text-align: right; }
.ws-signature-intro { font-size: 8.5pt; margin-bottom: 18px; }
.ws-signature-line { border-top: 1px solid {{ $textPrimary }}; margin-bottom: 2px; }
.ws-signature-label { font-size: 8.5pt; font-weight: bold; color: {{ $textPrimary }}; }
.ws-signature-images {
    position: relative;
    height: 132px;
    margin-bottom: 2px;
    text-align: center;
}
.ws-signature-images img.ws-sig-img {
    max-height: 108px;
    max-width: 420px;
    vertical-align: bottom;
}
.ws-signature-images img.ws-stamp-img {
    max-height: 132px;
    max-width: 210px;
    vertical-align: bottom;
    opacity: 0.9;
}

/* ============ Payment meta box (OR only) ============ */
.ws-payment-meta {
    margin: 4px 0;
    padding: 3px 8px;
    background: #f4f7fa;
    border: 1px solid {{ $accentDark }};
    font-size: 8.5pt;
}
.ws-payment-meta strong { color: {{ $accentDark }}; }
.ws-payment-meta-row { display: table; width: 100%; }
.ws-payment-meta-cell { display: table-cell; padding-right: 14px; line-height: 1.35; }

/* ============ Footer doc note ============ */
.ws-footer-doc {
    margin-top: 4px;
    padding-top: 2px;
    border-top: 1px solid #e0e0e0;
    font-size: 7pt;
    text-align: center;
    color: #888;
}
.ws-page-number { text-align: right; font-size: 7pt; color: #888; margin-top: 2px; }
.page-break { page-break-before: always; }

/* ============ Compact header for continuation pages ============ */
.ws-header-compact {
    background: {{ $accentDark }};
    color: #ffffff;
    padding: 4px 12px;
    margin: -2px -2px 4px -2px;
    display: table;
    width: 100%;
    font-family: 'DejaVu Sans', sans-serif;
    font-size: 8.5pt;
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
