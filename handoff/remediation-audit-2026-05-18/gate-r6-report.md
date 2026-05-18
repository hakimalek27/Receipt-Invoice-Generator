# Gate R6 Report - e-Invoice Metadata Readiness

## Scope Completed

- Kept v1 as metadata-ready only; no MyInvois submission route, controller, job, or outbound API flow was added.
- Added supplier metadata fields on companies: TIN, SST registration number, MSIC code, business activity description, and structured address fields.
- Added buyer metadata fields on customers: BRN registration number, SST registration number, and MSIC code, alongside the existing tax identifier and address components.
- Updated master data API validation so company and customer e-Invoice metadata can be edited.
- Enforced non-MYR document creation to include an FX rate snapshot.
- Preserved line-level e-Invoice metadata on document items: tax type/code, tax rate, tax amount, classification code, and tax exemption reason.
- Expanded issue-time supplier and buyer snapshots to include original address parts plus a canonical address string, TIN/BRN/SST/MSIC metadata, and supplier business activity.
- Ensured conversion keeps source currency and FX rate.

## Guardrails Preserved

- MyInvois submission remains out of v1 scope.
- Existing invoice issue flow still allocates official numbers only at issue time.
- Company records, document records, and e-Invoice metadata remain company-scoped.
- Historical Excel files remain references only and are not imported as production records.

## Verified Commands

- `php artisan test tests\\Feature\\EinvoiceMetadataTest.php`
  - Passed: 11 tests / 45 assertions.
- `php artisan test tests\\Feature\\DocumentWorkflowTest.php tests\\Feature\\ApiSurfaceTest.php tests\\Feature\\EinvoiceMetadataTest.php`
  - Passed: 41 tests / 156 assertions.
- `php artisan test`
  - Passed: 123 tests / 414 assertions.
- `npm run build`
  - Passed.

## Remaining Notes

- Current code-list validation remains editable placeholder validation. The actual LHDN MyInvois classification and tax code lists must be rechecked against current official docs before a later submission phase.
