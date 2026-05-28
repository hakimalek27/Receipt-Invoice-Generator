@php
    $navy = '#1F3864';
    $scentury = ($document->product_line ?? null) === 'scentury';
    $headerFill = $scentury ? '#B8860B' : $navy;
@endphp
<style>
    /* DomPDF ignores @page margins when paper is set via setPaper(), so content
       clearance for the baked letterhead chrome is handled by the .pgg-frame
       table's thead/tfoot spacers (they repeat on every page). The default
       DomPDF margin (~12.5mm) provides the side gutters. */
    @page { margin: 12mm; }
    body { font-family: 'Helvetica', 'DejaVu Sans', sans-serif; font-size: 11pt; color: #000; margin: 0; }

    /* Pushes page-1 content below the baked letterhead header band (~53mm). */
    .pgg-top-spacer { height: 55mm; }

    /* Full-page letterhead, repeated behind content on every page. */
    .pgg-letterhead-bg { position: fixed; top: 0; left: 0; width: 210mm; height: 297mm; z-index: -1; }
    .pgg-letterhead-bg img { width: 210mm; height: 297mm; display: block; }

    /* Salam sits in the body flow, centered, between the recipient/ref block
       and the salutation (matches the reference layout). */
    .pgg-salam { text-align: center; margin: 3pt 0 5pt; }
    .pgg-salam img { max-height: 12mm; width: auto; }

    .pgg-salutation { font-size: 11pt; margin: 0 0 5pt; }
    .pgg-title { font-size: 11pt; font-weight: bold; text-decoration: underline; margin: 0 0 7pt; }
    .pgg-title-center { text-align: center; font-size: 15pt; font-weight: bold;
                        letter-spacing: 1pt; color: {{ $navy }}; margin: 0 0 10pt; }
    .pgg-intro { font-size: 10pt; margin: 0 0 8pt; }
    .pgg-extra-note { font-size: 9pt; font-style: italic; color: #444; margin: 0 0 8pt; }

    .pgg-cols { display: table; width: 100%; margin-bottom: 10pt; }
    .pgg-col-left { display: table-cell; width: 58%; vertical-align: top; padding-right: 14px; font-size: 10pt; line-height: 1.45; }
    .pgg-col-right { display: table-cell; width: 42%; vertical-align: top; }
    .pgg-recipient-label { font-weight: bold; color: {{ $navy }}; margin-bottom: 3pt; }
    .pgg-recipient-attn { font-weight: bold; }
    .pgg-recipient-name { font-weight: bold; }

    table.pgg-meta { width: 100%; font-size: 9.5pt; border-collapse: collapse; }
    table.pgg-meta td { padding: 1pt 0; vertical-align: top; }
    table.pgg-meta .k { width: 38%; white-space: nowrap; }
    table.pgg-meta .colon { width: 5%; }
    table.pgg-meta .v { text-align: left; }

    /* ---- 5-column items table (NO / DESCRIPTION / QTY / PRICE / TOTAL) ---- */
    table.pgg-items { width: 100%; border-collapse: collapse; margin-bottom: 6pt; }
    table.pgg-items th { background: {{ $headerFill }}; color: #fff; font-size: 10pt; font-weight: bold;
                         padding: 5pt 4pt; border: 0.75pt solid {{ $headerFill }}; text-align: center; }
    table.pgg-items td { border: 0.75pt solid #000; padding: 5pt 5pt; font-size: 10pt;
                         vertical-align: top; page-break-inside: avoid; }
    .pgg-c-no { width: 6%; text-align: center; }
    .pgg-c-desc { width: 45.5%; }
    .pgg-c-qty { width: 7.5%; text-align: center; }
    .pgg-c-price { width: 20%; text-align: left; }
    .pgg-c-total { width: 21%; text-align: left; }

    .pgg-prod-title { font-weight: bold; text-align: center; margin: 2pt 0; }
    .pgg-prod-img { text-align: center; margin: 3pt 0; }
    .pgg-prod-img img { max-width: 78pt; max-height: 78pt; }
    .pgg-bullets { margin: 4pt 0 2pt 0; padding: 0; list-style: none; }
    .pgg-bullets li { font-size: 9pt; line-height: 1.4; padding-left: 10pt; text-indent: -10pt; }
    .pgg-svc-title { font-weight: bold; text-align: center; }
    .pgg-price-note { display: block; font-size: 8pt; color: #333; }

    tr.pgg-section td { background: #eef1f6; font-weight: bold; font-size: 9.5pt; text-align: left; }
    tr.pgg-grand td { background: #F2F2F2; font-weight: bold; }
    tr.pgg-summary td { font-size: 9.5pt; }
    .pgg-sum-label { text-align: right; }
    .pgg-sum-val { text-align: left; }

    .pgg-amount-words { font-size: 9pt; font-style: italic; margin: 6pt 0; }

    /* ---- Terms ---- */
    .pgg-terms { font-size: 8pt; margin: 10pt 0 4pt; line-height: 1.45; }
    .pgg-terms-head { font-weight: bold; margin-bottom: 3pt; }
    .pgg-terms ol { margin: 0; padding-left: 16pt; }
    .pgg-terms li { margin-bottom: 1pt; }
    .pgg-terms-free { font-size: 8pt; margin: 6pt 0; line-height: 1.45; }
    .pgg-terms-free strong { color: {{ $navy }}; }

    /* ---- Signature ---- */
    .pgg-sign { margin-top: 10pt; position: relative; }
    .pgg-sign-thanks { font-weight: bold; margin-bottom: 1pt; }
    .pgg-sign-yours { font-weight: bold; margin-bottom: 0; }
    .pgg-sign-dots { margin-top: 16pt; font-size: 13pt; letter-spacing: 1pt; }
    .pgg-sign-title { font-weight: bold; }
    .pgg-stamp { position: absolute; left: 4pt; top: 30pt; }
    .pgg-stamp img { width: 78pt; height: 78pt; opacity: 0.92; }

    .pgg-sign-cols { display: table; width: 100%; margin-top: 18pt; page-break-inside: avoid; }
    .pgg-sign-col { display: table-cell; width: 33%; text-align: center; vertical-align: bottom;
                    font-size: 9pt; padding: 0 8pt; }
    .pgg-sign-col .ln { border-top: 1px solid #000; margin: 34pt 4pt 4pt; }
    .pgg-sign-col .lbl { font-weight: bold; }

    .pgg-continued { text-align: right; font-size: 8pt; font-style: italic; color: #777; margin: 4pt 0; }
    .pgg-cont-title { font-size: 9pt; font-weight: bold; color: {{ $navy }}; margin-bottom: 4pt; }
    .pgg-pageno { text-align: right; font-size: 7.5pt; color: #888; margin-top: 4pt; }
    .pgg-footer { margin-top: 3pt; font-size: 7pt; text-align: center; color: #888; }
    .page-break { page-break-before: always; }
</style>
