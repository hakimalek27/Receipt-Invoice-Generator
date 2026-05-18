# Sample Document Smoke - 2026-05-18

## Generated Samples

Generated against the local SQLite development database after applying all migrations.

| Sample | Document ID | Number | Template | Paper | Pages | PDF Path |
| --- | ---: | --- | --- | --- | ---: | --- |
| Wehdah invoice with artwork | 7 | WS-INV-2026-00003 | `pdf.wehdah.invoice` | A4 | 3 | `storage/app/private/documents/1/7_v1.pdf` |
| Wehdah quotation | 8 | WS-Q-2026-00002 | `pdf.wehdah.quotation` | A4 | 1 | `storage/app/private/documents/1/8_v1.pdf` |
| Wehdah delivery order | 9 | WS-DO-2026-00002 | `pdf.generic.delivery_order` | A4 | 1 | `storage/app/private/documents/1/9_v1.pdf` |
| NAS Ceria invoice | 10 | NCS-INV-2026-00002 | `pdf.nasceria.invoice` | A4 | 1 | `storage/app/private/documents/2/10_v1.pdf` |
| Persada invoice | 11 | PGG-INV-2026-00002 | `pdf.persada.invoice` | A4 | 1 | `storage/app/private/documents/3/11_v1.pdf` |
| Wehdah official receipt | 13 | WS-REC-2026-00001 | `pdf.generic.official_receipt` | A4 | 1 | `storage/app/private/documents/1/13_v1.pdf` |
| Wehdah thermal receipt | 13 | WS-REC-2026-00001 | `pdf.thermal_receipt` | 60mm | 1 | `storage/app/private/documents/1/13_v2.pdf` |

## Verified Commands

- `php artisan migrate --force`
  - Applied pending local migrations and the payment workflow backfill migration.
- `php artisan test`
  - Passed: 123 tests / 414 assertions.
- `npm run build`
  - Passed.
- Local server smoke on port 8088:
  - `/login` returned 200.
  - `/` returned 302 to `/login`.

## Important Findings

- Backend/API/PDF generation is functional for the smoke samples above.
- DeepSeek integration has a real HTTP client path, strict JSON validation, and fallback parser, but no live DeepSeek API key was configured locally, so live DeepSeek API execution was not verified.
- Telegram webhook validation, allowlist, draft creation, and confirmation token workflow are covered by tests, but outbound Telegram bot replies are not implemented yet; current webhook response returns the confirmation token to the API caller.
- UI is not feature-complete. Current Inertia screens are foundation screens with document list/dashboard/master-data entry points and a placeholder document editor. Full premium editor controls, polished buttons, artwork upload UI, preview UI, and issue confirmation UX still need a dedicated UI completion phase.
- PDF templates are `match + kemas` working approximations. They are not verified as pixel-perfect against the Excel/JPG/PDF references because the current renderer remains DomPDF legacy fallback, not Playwright/Chromium visual regression.
