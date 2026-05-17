# Gate R4 Report - PDF Renderer, Templates, Artwork

## Scope Completed

- Added missing PDF view surface for NAS Ceria invoice/quotation, Persada invoice, and generic quotation, delivery order, cash bill, credit note, debit note, purchase order, payment voucher, and proforma invoice.
- Kept Wehdah branded invoice/quotation as the preferred template for `WS` invoice and quotation.
- Added artwork attachment payload support for PDF rendering with private-storage path normalization and image data URI embedding only for allowed local attachment paths.
- Added artwork pages after the main document pages for Wehdah and generic/branded fallback templates.
- Fixed thermal 60mm rendering to use a dynamic non-zero paper height instead of a zero-height paper box.
- Hardened document attachment upload to allow only JPG, JPEG, PNG, WEBP, and PDF, reject SVG, reject path-segment filenames, and continue storing with UUID names in private local storage.
- Added PDF template tests for view existence, company/document render matrix, multi-page item pagination, amount-in-words final-page placement, artwork ordering, thermal height, SVG rejection, and path traversal filename rejection.

## Renderer Decision

The original remediation target remains Playwright/Browsershot/Chromium for high-fidelity Excel-style PDF rendering. This R4 repair keeps the existing DomPDF implementation as the working legacy adapter so every supported v1 document type can render and be tested now. Chromium migration remains a renderer adapter improvement, not a license to ship missing or crashing document templates.

## Verified Commands

- `php artisan test tests\\Feature\\PdfTemplateTest.php`
  - Passed: 6 tests / 69 assertions.
- `php artisan test tests\\Feature\\ApiSurfaceTest.php tests\\Feature\\DocumentWorkflowTest.php tests\\Feature\\PdfTemplateTest.php`
  - Passed: 36 tests / 179 assertions.
- `php artisan test`
  - Passed: 113 tests / 362 assertions.
- `npm run build`
  - Passed.
- `git diff --check`
  - Passed.
- Placeholder marker scan across app, resources, tests, and remediation evidence
  - No matches.

## Gate Notes

- Historical Excel/PDF/image files remain reference material only; no legacy rows were imported into production tables.
- Company template routing remains isolated by company code: Wehdah `WS`, NAS Ceria `NCS`, Persada `PGG`, otherwise generic fallback.
- Official numbers are still issued only through the workflow issue path; PDF rendering draft previews continue to show `DRAFT`.
- e-Invoice remains metadata-ready only; no MyInvois submission endpoint or job was added in this phase.
- Artwork files are appended after all main pages and are not fetched from arbitrary remote URLs or local paths.
- The app root now redirects guests to login; legacy boot tests were aligned to that authenticated-app behavior so full suite reflects the current UI surface.
