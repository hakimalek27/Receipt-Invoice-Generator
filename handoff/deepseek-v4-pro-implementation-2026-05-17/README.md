# DeepSeek v4 Pro Implementation Handoff

Read `deepseek-v4-pro-phase-implementation-plan.json` first. It is a phase-by-phase implementation contract, not application code.

Core rules:

- Implement one phase at a time.
- Run the required tests and smoke tests for the current phase before moving on.
- Stop on any locked-decision conflict, company/template/numbering leak, failed gate, PDF mismatch, amount-in-words mismatch, or AI/Telegram confirmation bypass.
- Historical Excel files are references only, never production imports.
- Keep document design `match + kemas` and preserve Wehdah, NAS Ceria, and Persada identity from the allowed canonical sources.
- Use `official_receipt` internally; 60mm thermal output is a print layout/artifact.
- e-Invoice v1 is metadata-ready only.

The JSON includes the amount-in-words feature and multi-page PDF pagination contract requested by the user.
