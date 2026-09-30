# Implementation Plan & Progress

Status legend: [x] done, [~] partial, [ ] not started. Updated at the end of every work session.

## Architecture decisions (Bluehost shared hosting constraints)
- Plain PHP 8.1+ (no framework, **no Composer runtime dependencies**, so nothing to vendor). Front controller + small in-house MVC.
- MySQL/MariaDB via PDO (prepared statements, native prepares). utf8mb4 / utf8mb4_unicode_ci.
- Server-rendered pages. Charts are HTML/CSS/SVG rendered on the server (no JS charting library, no build step, no Node).
- Sessions: PHP file sessions in `storage/sessions`. Login throttling and audit log in MySQL. No queues/cron required.
- Config lives outside the web root when possible (`../grc-config/config.php`), fallback `config/config.php` protected by .htaccess.
- Works in a subdomain document root or a subdirectory; base path auto-detected.
- Migrations: `database/migrations/*.sql` (+ generated single-file `database/install.sql` for phpMyAdmin import).

## Phase 1 (core)
- [ ] Skeleton, config, bootstrap, router, session, CSRF, i18n (ar/en, RTL/LTR)
- [ ] Schema + reference data migrations
- [ ] First-run setup (token protected, locks itself)
- [ ] Auth: login/logout/password change, throttling, roles, server-side scope enforcement, audit log
- [ ] Org structure: departments, branches, users, categories, reporting periods, risk ownership transfer
- [ ] Risk register (CRUD, filters, search, archive, attachments, history, treatment actions)
- [ ] Risk assessment: 5x5 configurable matrix, bands, financial thresholds, explicit residual
- [ ] Incidents & losses (net loss, validation, risk links, attachments)
- [ ] Dashboard (all metrics derived from DB, drill-down links, scope aware)
- [ ] Reports: CSV (BOM, formula-injection safe), printable RTL reports, same authz
- [ ] Tests, compat check, packaging script, docs, deployment guide

## Phase 2 (after Phase 1 verified)
- [ ] KRIs, [ ] Risk appetite & breaches, [ ] Compliance obligations, [ ] Committee decisions, [ ] Policies, [ ] RCSA
