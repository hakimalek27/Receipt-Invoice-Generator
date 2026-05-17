# R1 Gate Report

## Result

R1 backend safety passed. This phase fixed money math, canonical draft
fingerprints, scoped idempotency, issue confirmation requirements, and first-row
numbering allocation safety.

## Changes Completed

- Added `DocumentFingerprintService` with SHA-256 canonical payload hashing.
- Replaced item-array `md5` draft hashes with canonical document fingerprints.
- Recomputed and compared draft hash inside the locked issue transaction.
- Made `confirmed_total` required for API issue requests.
- Scoped idempotency by company, user, document, action, and key.
- Stored idempotency `request_hash`, `draft_hash`, and status.
- Rejected reused idempotency keys with different request bodies.
- Removed global official-number uniqueness and replaced it with company/type scoped uniqueness.
- Reworked sequence allocation to upsert the counter row before locking/incrementing it.
- Fixed document totals so subtotal is gross, discount is separate, line total is net, and grand total is not discounted twice.
- Preserved line totals and e-Invoice line metadata during conversion.
- Avoided Windows test failures by hashing generated PDF bytes instead of reopening the stored file.

## Verification

| Command | Result |
| --- | --- |
| `php artisan test tests/Feature/DocumentWorkflowTest.php` | PASS, 17 tests |
| `php artisan test tests/Feature/ApiSecurityTest.php` | PASS, 16 tests |
| `php artisan test tests/Feature/NumberingTest.php` | PASS, 9 tests |
| `php artisan test` | PASS, 94 tests / 237 assertions |
| `npm run build` | PASS |
| `git diff --check` | PASS |

## Gate Status

| Check | Status |
| --- | --- |
| Discounted totals are not double-subtracted | PASS |
| Conversion preserves totals and line metadata | PASS |
| Draft hash changes when canonical payload changes | PASS |
| Issue rejects stale draft hash inside transaction | PASS |
| Issue API requires `confirmed_total` | PASS |
| Idempotency replay returns same response | PASS |
| Same key with different request is rejected | PASS |
| Same idempotency key can be reused safely by different company/user scopes | PASS |
| Numbering remains per-company/per-type/per-year | PASS |

## Deferred To Later Phases

- R2: payment allocation balance/status rules and official receipt linkage.
- R2/R4: issued PDF rendering from snapshot DTOs instead of live company/customer models.
- R3: full document route surface and UI.
- R4: Chromium renderer and missing PDF templates.
- R5: persisted Telegram confirmation tokens and real DeepSeek API.
