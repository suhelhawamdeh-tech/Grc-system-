# Changelog

## 1.1.0 (development release, critical backlog items)
- Usability: in-app help centre (role quick-starts, per-page slide-over guide, glossary, AR/EN), private notes and reminders, "My tasks" page with badge, icon navigation and refreshed styling (GRC-006 and usability requirement).
- Control library with risk links and independent control testing: independence, sample/exception and evidence rules, automatic remediation actions (GRC-001, GRC-002).
- Sharia compliance: rulings register, reviews, non-compliance events with escalation, decision, purification gate, annual report (GRC-020).
- Business impact analysis with recovery-gap detection and a generic maker-checker approval log (GRC-015; GRC-004 for BIA only).
- E-mail digests and escalation through SMTP and a cron script `bin/notify.php` (GRC-005).
- Native Excel (.xlsx) export for all CSV exports and a printable board risk pack (GRC-007 partial: no server-side PDF engine).
- Migrations 004 to 008. 925 automated checks.

## 1.0.0
- Phase 1 core: authentication, RBAC with department/branch scope, risk register, 5×5 assessment, incidents/losses, dashboard, CSV/print reports, setup wizard, migrations, tests, Bluehost documentation.
- Phase 2 modules: KRIs, risk appetite and breaches, compliance obligations, committee decisions, policies with versions, RCSA.
