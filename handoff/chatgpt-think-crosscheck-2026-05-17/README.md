# ChatGPT Think Mode Crosscheck

Read `receipt-invoice-generator-crosscheck-plan.json` first. It is self-contained and combines the original audit pack, the proposed Claude audit-output plan, and Codex's corrections.

The review goal is to confirm whether the next audit-output pass stays aligned with the user's workflow:

- Historical Excel files are references only, not production imports.
- Document design remains `match + kemas`.
- Every company keeps separate profile, template, records, numbering, and sequence counters.
- Official numbers are allocated only when a document is issued.
- e-Invoice v1 is metadata-ready only.
- Wehdah, Persada, Virtue Damsel, and Nas Ceria numbering must never merge.
- The project should be scaffolded as a new system only after audit output and missing-info gates are settled.

Do not treat this folder as application code. It is a planning and crosscheck artifact for audit review.
