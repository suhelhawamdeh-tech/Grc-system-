# Changelog

## 1.4.0
- **Internal audit** (`/ia`): audit universe scored on six risk factors (department residual risk, materiality, regulatory exposure, complexity, time since last audit, last rating) with audit cycles by priority; annual plan built from the ranking, approval by the audit committee recorded and the plan locked; engagements planned → fieldwork → reporting → issued; workpapers with sample, exceptions, conclusion and evidence, reviewed by a second auditor (maker-checker); findings written as condition / criteria / cause / effect / recommendation, kept as drafts until the report is issued, then followed with management (response within 14 days, agreed action and date, implementation, auditor verification or return, counted extensions).
- **Anti-money laundering** (`/aml`): risk factors in four dimensions (customers, products and services, delivery channels, geographic areas) with illustrative levels to be reviewed by the bank; customer rating from weighted dimensions plus hard rules (PEP or sanctions match = high; high-risk type or country never below medium), due-diligence level and review cycle; manual override with reason; enterprise-wide view with inherent risk, control effectiveness from linked controls and residual risk; AML reports (STR confidential to the AML team, CTR, periodic, EWRA) with due dates and submission records; CSV export.
- **T24 integration** (`/admin/integrations`): customers and loss-ledger entries loaded from a T24 export file (CSV, pipe, semicolon, tab or JSON) or from the T24 REST API (IRIS-style paging, basic or bearer authentication, encrypted secret), with configurable field mapping, value mapping through the AML factors, all-or-nothing validation, run log and a cron job `bin/t24-sync.php`.
- My tasks, e-mail digests and the dashboard include the new items; help topics and role quick-starts updated.
- Migrations 020 to 022.

## 1.3.0
- Risk acceptance authority (two levels, second needs the administrator) and approval-gated closure of large incidents (GRC-011, GRC-004).
- Two-step verification (TOTP, recovery codes, optional enforcement) and password reset by e-mail (GRC-013).
- Recurring RCSA schedules launched by the daily job, plus event-triggered reassessments (GRC-012).
- Bulk CSV import with all-or-nothing preview for risks, KRI readings, obligations and ledger entries (GRC-014).
- Composite KRIs, loss reconciliation with the general ledger, strategic objectives scorecard.
- Migrations 014 to 019. 1234 automated checks.

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
