# Changelog

## 1.2.0
- Incidents: Basel event type and business line, recovery source, non-financial impact, closure gate, escalation threshold (setting), recurrence detection with automatic risk reassessment trigger, patterns page (GRC-009, GRC-010 partial).
- Advanced KRIs: critical level, breach register with justification/action plan, data-quality gate on outlier readings (GRC-008 partial).
- Regulatory circulars: register, distribution to departments, acknowledgement with action taken (GRC-018).
- Boards and committees: members, meetings, attendance, quorum, minutes and compliance report (GRC-022).
- Policy version approval (maker-checker) and flag for risks relying on unproven controls (GRC-004 extension, GRC-003).
- Migrations 009 to 013. 1061 automated checks.

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
