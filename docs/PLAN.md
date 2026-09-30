# Implementation plan and progress

Legend: **[x] done and covered by automated tests**, [~] partial, [ ] not done. Last updated at the end of session 1.

## Architecture decisions (Bluehost shared hosting constraints)
- Plain PHP 8.1+ (no framework, **no Composer/Node runtime dependencies**, nothing to vendor). Front controller + small in-house MVC.
- MySQL/MariaDB via PDO with prepared statements; `utf8mb4_unicode_ci`.
- Server-rendered pages; charts are HTML/CSS/table (no JS chart library, no build step).
- File sessions in `storage/sessions`; DB-backed throttling and audit; no cron, queue, Redis or websockets.
- Config outside the app folder when possible (`../grc-config/config.php`), fallback `config/` protected by `.htaccess`, manual-file fallback in the wizard.
- Works at a subdomain root or in a sub-directory (base path auto-detected).
- Versioned SQL migrations + generated `database/install.sql` for phpMyAdmin.

## Phase 1 — core (complete)
- [x] Skeleton, config, router, sessions, CSRF, i18n (ar/en, RTL/LTR)
- [x] Schema + reference data (categories incl. Islamic, 5×5 scales, bands) migrations
- [x] First-run setup wizard (token-protected, locks itself), CLI installer
- [x] Auth: login/logout/password change/forced change, throttling, 7 roles, department/branch scope, audit log
- [x] Departments, branches, users, risk categories, reporting periods, ownership transfer
- [x] Risk register: create/view/edit/filter/search/archive, attachments, field-level history, treatment actions
- [x] Risk assessment: configurable 5×5, separate inherent/residual scoring, explicit residual, financial thresholds, demo-threshold approval flag
- [x] Incidents and losses: exact net-loss arithmetic, validation, risk links, attachments
- [x] Dashboard: every metric from DB, heat maps, drill-down links, scope aware
- [x] CSV (BOM, `;` variant, formula-injection safe) + printable RTL reports, same authorisation
- [x] Compatibility check (`check.php`), packaging script, docs (README, Bluehost guide, checklist, troubleshooting, test report)

## Phase 2 — extension modules (complete in this session)
- [x] KRIs: thresholds (both directions), monthly/quarterly cycle, reporting periods, alerts (list/filter/dashboard), threshold edits re-evaluate history
- [x] Risk appetite: statements (max residual score / max net loss), live measurement, auto + manual breach tracking with status workflow
- [x] Compliance obligations: owners, deadlines, statuses, evidence attachments, recurring roll-forward, scoped CSV
- [x] Committee decisions with follow-up actions (owner/deadline/status, overdue tracking)
- [x] Policies with immutable version history, approval metadata, review dates, document upload
- [x] RCSA: bilingual questionnaires, launch per department/branch, respond → submit → review (approve/return), segregation of duties
- [x] Governance strip on the dashboard (all figures from DB, permission- and scope-aware)

## Known gaps / next steps (not implemented)
- [ ] E-mail notifications and scheduled reminders (needs cron/SMTP; cPanel cron could call a CLI script)
- [ ] Password reset by e-mail, MFA/SSO
- [ ] XLSX/PDF generation (CSV + browser print only)
- [ ] KRI/appetite/RCSA CSV exports and printable reports (only obligations have CSV)
- [ ] Risk-from-RCSA "create risk from finding" shortcut; RCSA aggregation across departments
- [ ] Users with multiple departments/branches
- [ ] Web fonts bundled for Arabic (currently relies on system fonts)
- [ ] Verification on real Bluehost, Apache, PHP 8.1–8.3, MySQL 5.7/8, screen readers, formal WCAG audit, penetration test
