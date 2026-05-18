# R2 Gate Report

## Result

R2 workflow completeness passed. This phase tightened conversion, voiding,
payment allocation, official receipt creation, and issued render data
immutability.

## Changes Completed

- Added an explicit conversion matrix for supported conversions.
- Rejected unsupported conversions such as invoice back to quotation.
- Required a non-empty void reason.
- Added payment currency and receipt document linkage.
- Restricted payment allocation to issued receivable documents.
- Rejected payment allocation for draft documents.
- Rejected payment allocation over the document outstanding balance.
- Rejected payment allocation when payment/document currencies differ.
- Added official receipt creation/linking from payment records.
- Added a minimal generic official receipt PDF view so receipt issuing does not crash before R4 template polish.
- Changed PDF render data for issued documents to use issuer/buyer snapshots instead of live company/customer profile data.

## Verification

| Command | Result |
| --- | --- |
| `php artisan test tests/Feature/DocumentWorkflowTest.php` | PASS, 24 tests |
| `php artisan test tests/Feature/ApiSecurityTest.php` | PASS, 16 tests |
| `php artisan test tests/Feature/EinvoiceMetadataTest.php` | PASS, 7 tests |
| `php artisan test` | PASS, 101 tests / 254 assertions |
| `npm run build` | PASS |
| `git diff --check` | PASS |

## Gate Status

| Check | Status |
| --- | --- |
| Payment allocation only to issued receivable docs | PASS |
| Over-allocation rejected | PASS |
| Currency mismatch rejected | PASS |
| Official receipt can be issued and linked to payment | PASS |
| Void reason required | PASS |
| Unsupported conversion rejected | PASS |
| Issued render data uses snapshots | PASS |

## Deferred To Later Phases

- R3: expose workflow through API/UI routes.
- R4: replace minimal official receipt PDF with reference-aligned template family.
- R4: move PDF engine to Chromium and add visual regression.
- R5: Telegram confirmation may create drafts only after secure token persistence is implemented.
