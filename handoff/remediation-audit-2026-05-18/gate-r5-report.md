# Gate R5 Report - Telegram and DeepSeek

## Scope Completed

- Replaced the Telegram webhook stub with a guarded draft workflow.
- Telegram webhook now requires a configured secret and validates `X-Telegram-Bot-Api-Secret-Token`.
- Telegram chat authorization is based on stable chat IDs from `TELEGRAM_ALLOWED_CHAT_IDS`.
- Telegram chat-to-user binding is explicit through `TELEGRAM_CHAT_USER_MAP`; username-based authorization is not used.
- Telegram messages create draft documents only and return a confirmation token; no official number is allocated during draft creation.
- Confirmation tokens are persisted hashed in `telegram_confirmation_tokens`, bound to company, user, document, chat ID, optional Telegram user ID, `draft_hash`, idempotency key, and expiry.
- Confirmation tokens are one-time use; replay, expiry, wrong chat, and changed draft hash are rejected.
- DeepSeek parser now has a real HTTP client path with API key, base URL, model, timeout, retries, JSON response mode, strict schema validation, and regex fallback.
- DeepSeek output is rejected if it tries to set company, final number, official number, or issue status.
- DeepSeek prompt/output text is sanitized and logs only redacted metadata such as status, document type, and item count.

## Guardrails Preserved

- AI never selects company; server context sets `company_id`.
- AI never allocates official number and never issues a document.
- Telegram cannot issue a document without a valid one-time confirmation token.
- Historical Excel/PDF/image references remain reference only.
- Company scoping still comes from the mapped application user and company.
- e-Invoice remains metadata-ready only; no MyInvois submission integration was added.

## Verified Commands

- `php artisan test tests\\Feature\\ApiSecurityTest.php tests\\Feature\\ApiSurfaceTest.php`
  - Passed: 28 tests / 102 assertions.
- `php artisan test`
  - Passed: 119 tests / 392 assertions.
- `npm run build`
  - Passed.

## Remaining Notes

- Actual outbound Telegram message sending is still outside this gate; the webhook returns the token in the API response for current verification.
- DeepSeek model name and public API compatibility should be rechecked against current DeepSeek documentation before production API enablement.
