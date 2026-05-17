# Remediation Audit 2026-05-18

This folder is the R0 evidence freeze for repairing the existing
`phase-1-scaffold` implementation without restarting from `main`.

Read order:

1. `current-state.json` - branch, commit, route, PDF view, test, and build baseline.
2. `canonical-source-registry.json` - allowed reference sources per company/template.
3. `scope-freeze.json` - locked decisions and Phase 0.5 implementation defaults.
4. `gate-r0-report.md` - R0 gate result and next phase readiness.

R0 intentionally does not change application behavior. It only records the
current state and freezes the decisions that later remediation phases must obey.
