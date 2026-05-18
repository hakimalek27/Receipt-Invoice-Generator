# Production-Ready Verification Summary

Date: 2026-05-18
Branch: `remediation/deepseek-audit-fix`

## Completed Locally

- Inertia UI now covers dashboard, document list/filter, document editor, issue modal, PDF preview/download, artwork upload, payments/official receipt, and master data for company/customer/product/template/numbering.
- Same-origin Inertia API calls are session-authenticated through Sanctum stateful API and CSRF metadata.
- Telegram flow now records inbound/outbound messages and sends draft summary, confirmation instruction, and issued-number summary when `TELEGRAM_BOT_TOKEN` is configured.
- PDF renderer now supports `PDF_RENDERER=playwright` through `scripts/render-pdf.mjs`; DomPDF remains a controlled legacy fallback.
- Deployment runbook now includes Playwright install, Sanctum domain, Telegram bot token, DeepSeek smoke, PDF smoke, queue, backup, restore, and SSL gates.

## Verification Run

- `php artisan test`: passed, 124 tests / 426 assertions.
- `npm run build`: passed.
- `php artisan route:list --except-vendor`: passed and shows API/UI route surface.
- `php artisan rig:pdf-smoke --paper=A4`: passed with DomPDF default.
- `PDF_RENDERER=playwright php artisan rig:pdf-smoke --paper=A4`: passed.
- `php artisan rig:deepseek-smoke`: passed through fallback parser because no live DeepSeek key is configured locally.
- Browser smoke: login -> dashboard -> create invoice draft -> amount-in-words toggle -> save draft -> preview PDF -> issue: passed.
- Browser smoke: payments -> allocate issued receivable -> generate official receipt: passed.
- Browser smoke: master data -> create customer -> create product: passed.

## Production Sign-Off Still Requires Live Inputs

- Tencent Ubuntu SSH/domain credentials and final production domain.
- Real `TELEGRAM_BOT_TOKEN`, webhook secret, allowed chat IDs, and chat-user mapping.
- Real `DEEPSEEK_API_KEY`; run `php artisan rig:deepseek-smoke --require-live` after configuring it.
- Production backup destination and restore drill evidence.
- Live SSL, queue worker, scheduler, log, and PDF download smoke on Tencent.
