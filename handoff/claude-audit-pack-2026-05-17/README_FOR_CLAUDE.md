# Claude Audit Pack: Receipt Invoice Generator

This folder is a handoff pack for Claude or another audit agent to review and improve the planned multi-company receipt, invoice, quotation, delivery order, and related document generator.

## Read Order

1. `receipt-invoice-generator-audit-plan.json`
2. `analysis/workbook-audit-summary.json`
3. `reference-manifest.json`
4. `analysis/template-design-notes.md`
5. `analysis/open-questions.md`

## Locked Decisions

- Historical Excel files are template references only, not production imports.
- Document design target is `match + kemas`: preserve the original identity but polish layout and consistency.
- Every company must have separate profile, records, templates, numbering, and sequence counters.
- Official document numbers are allocated only when issuing, never at draft creation.
- e-Invoice v1 is metadata-ready only. Full MyInvois submission is a later phase.
- Do not merge Wehdah, Persada, Virtue Damsel, and Nas Ceria numbering.
- The workspace was empty before this handoff, so implementation should scaffold a new app.

## Audit Request

Please audit for correctness, gaps, security risks, workflow mistakes, Malaysian SME/global document needs, PDF/template feasibility, API safety, Telegram/DeepSeek safety, and e-Invoice future readiness. Suggest improvements, but do not change locked decisions without clear justification.

## Important Caution

The copied Excel files contain mixed old records, hidden sheets, template examples, and sample company data. Treat them as visual and structural references only.
