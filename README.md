# GRC Platform — Governance, Risk and Compliance for banks (Arabic / English)

A PHP + MySQL application built for **Bluehost shared hosting (cPanel)**. Bilingual (Arabic RTL / English LTR), navy-and-gold banking design, LYD as the default currency.

> **Status:** Phase 1 (core) and Phase 2 (KRIs, risk appetite, compliance obligations, committee decisions, policies, RCSA) are implemented and covered by an automated suite (609 checks, see [`docs/TEST_REPORT.md`](docs/TEST_REPORT.md)). Remaining gaps are listed in [`docs/PLAN.md`](docs/PLAN.md).
> The software has **not** been deployed to Bluehost or independently security-reviewed by the author; see [Limitations](#limitations-and-what-is-not-verified). Software alone does not establish regulatory or Sharia compliance.

## Quick start

| Goal | Where |
|---|---|
| Deploy on Bluehost/cPanel | [`docs/DEPLOYMENT_BLUEHOST.md`](docs/DEPLOYMENT_BLUEHOST.md) |
| Step-by-step tick list | [`docs/INSTALL_CHECKLIST.md`](docs/INSTALL_CHECKLIST.md) |
| Something broke | [`docs/TROUBLESHOOTING.md`](docs/TROUBLESHOOTING.md) |
| What was tested, exactly | [`docs/TEST_REPORT.md`](docs/TEST_REPORT.md) |
| Plan / remaining work | [`docs/PLAN.md`](docs/PLAN.md) |

Local development: `mysql` database + `php -S`:
```bash
export GRC_DB_NAME=grc_dev GRC_DB_USER=grc GRC_DB_PASS=... GRC_ADMIN_USER=admin GRC_ADMIN_PASS='...' \
       GRC_DEMO=1 GRC_DEMO_PASS='...' GRC_CONFIG_FILE=$HOME/grc-config/config.php
php bin/install.php                                  # schema + reference data + admin (+ synthetic demo data)
GRC_CONFIG_FILE=$HOME/grc-config/config.php php -S 127.0.0.1:8080 tests/router.php
php tests/run.php                                    # full automated suite (wipes its two test databases!)
php bin/package.php                                  # dist/grc-platform-<version>.zip
```

## Requirements
* **PHP 8.1 – 8.4** (developed on 8.4; select 8.1+ in cPanel *MultiPHP Manager*)
* Extensions: `pdo_mysql`, `mbstring`, `json`, `fileinfo`, `ctype`, `session` (required); `zip`, `openssl`, `intl` (optional)
* **MySQL 5.7+ / MariaDB 10.3+**, `utf8mb4`
* Apache with `mod_rewrite` (default on Bluehost)
* No Node.js, Composer, Docker, Redis, WebSockets, queue workers or cron jobs are needed at runtime. There are **no third-party runtime dependencies**, so nothing has to be vendored; no frontend build step exists (plain CSS, ~1 KB JS, server-rendered charts).
* Run the pre-install check: `https://<your-domain>/check.php` or `php public/check.php`.

## Architecture
```
public/            web root: index.php (front controller), .htaccess, assets/, check.php
app/               PHP code (private)  — src/Core (framework), src/Controllers, src/Support, views/, lang/ (ar, en), routes.php
database/          migrations/*.sql (versioned) and install.sql (generated single file for phpMyAdmin)
storage/           private runtime files: uploads/, sessions/, logs/  (deny-all .htaccess)
../grc-config/     config.php with DB credentials — created by the setup wizard OUTSIDE the app folder when possible
bin/               CLI: install.php, migrate.php, build-sql.php, package.php
tests/             dependency-free test runner (not shipped in the ZIP)
```
* Small in-house MVC: `Router` (permission per route) → `Controller` → `View`. Prepared statements everywhere (`PDO`, native prepares).
* Base path is auto-detected, so it runs at a domain root, on a subdomain, or in a sub-directory (`/grc/`).
* Amounts are handled as exact integers (thousandths) — no floating-point rounding; DB type `DECIMAL(18,3)` (LYD has 3 decimals).
* Charts are HTML/CSS (bars, columns) and a table heat map, all rendered on the server; each element links to the filtered records.

### Roles and access (enforced on the server)
| Role | Scope | Can |
|---|---|---|
| System administrator | all | everything: users, org, scales, settings, audit |
| Risk manager | all | risks, incidents, exports, dashboard, **scales**, ownership transfer |
| Compliance officer | all | risks, incidents, exports, dashboard |
| Department manager | **own department** | create/edit/archive risks and incidents in the department |
| Branch liaison officer | **own branch** | create/edit risks and incidents of the branch (no archive) |
| Executive viewer | all | read-only dashboard, lists, exports |
| Auditor | all | read-only + audit log |

Out-of-scope records return **404** (not 403) so existence is not revealed; lists, dashboard totals, CSV, print and attachment downloads all use the same scope predicate. Restricted users cannot create or move records outside their scope.

### Risk assessment model
* 5×5 matrix; **score = likelihood × impact** for inherent and residual **separately**. Residual is entered explicitly; control effectiveness is recorded but never subtracted automatically. A rationale is mandatory whenever residual ≠ inherent.
* Labels (ar/en), qualitative descriptions, financial thresholds (LYD), score bands (ranges, colours, "counts as high") are all configurable in *Administration → Risk assessment scales*; bands must tile 1–25 exactly.
* **The shipped thresholds, bands and criteria are DEMONSTRATION values.** A banner is shown everywhere until an authorised user records institutional approval; any later change resets the approval.
* Financial thresholds are advisory: the risk page warns when an estimated loss implies a different impact level than the one chosen.

### Phase 2 modules
| Module | Who | What it does |
|---|---|---|
| **KRIs** (`/kris`) | view: admin, risk, compliance, dept manager (own dept), exec, auditor · report: risk, compliance, dept manager · define: admin, risk | Configurable amber/red thresholds (higher- or lower-is-worse), monthly/quarterly cycle, readings linked to reporting periods, automatic green/amber/red, "reading due" flag, alerts on list and dashboard; changing thresholds re-evaluates history |
| **Risk appetite** (`/appetite`) | view: admin, risk, compliance, exec, auditor · manage: admin, risk | Appetite statements with approval metadata; live measurement (max residual score / max net loss for the current period) from stored data; *Evaluate now* opens breach records (no duplicates); breach workflow open → escalated → accepted/resolved with mandatory note |
| **Compliance obligations** (`/compliance`) | view: admin, risk, compliance, dept manager (own dept), exec, auditor · define: admin, compliance · update status/evidence: dept manager (own dept) | Source reference, owner, frequency, due date, status with evidence attachments, "compliant" needs evidence or a note, roll-forward for recurring items, scoped CSV |
| **Committee decisions** (`/committees`) | view: admin, risk, compliance, exec, auditor · manage: admin, risk, compliance | Decisions per committee (Board risk/audit, executive, ALCO, Sharia board, other) with follow-up actions, owners, deadlines and overdue tracking |
| **Policies** (`/policies`) | read: everyone · manage: admin, risk, compliance | Policy/procedure/standard/charter/Sharia-resolution register; **immutable version history** with approval details and optional document; review dates; only one current version |
| **RCSA** (`/rcsa`) | respond: dept managers/branch liaison (own scope) · manage/review: admin, risk | Bilingual questionnaires → launch per department/branch → assess → submit → reviewer approves or returns; the assessor cannot review their own assessment |

Dashboard *Governance and compliance* tiles (KRIs red/amber, open breaches, overdue obligations, overdue committee actions, RCSA to review/overdue, policies past review) are computed from the tables and shown only to users with the matching permission (and scope).

### Security controls implemented
Password hashing (`password_hash`, bcrypt/argon default, rehash on login, 12+ chars with letter+digit) · prepared statements · CSRF token on every POST · output escaping · session cookie `HttpOnly`/`SameSite=Lax`/`Secure` on HTTPS, `use_strict_mode`, ID regenerated on login, 30-min idle / 8-h absolute timeout · DB-backed login throttling (5 failures per user / 25 per IP per 15 min; no cron needed) · uniform login errors and constant-time-ish verification · server-side validation and authorisation on every route (route permission + object scope) · upload allow-list (pdf, png, jpg, txt, docx, xlsx), 5 MB, extension **and** content sniffing, random names, stored in non-public `storage/uploads`, authorised download as `attachment` with `nosniff` · CSV formula-injection neutralisation · CSP without inline scripts, `X-Frame-Options: DENY` · production errors are generic with a log reference · audit log that redacts secrets · first-run setup protected by a file-based token and permanently closed after install (also closed whenever any user exists) · no hard-coded credentials · no secrets in the repo.

### Islamic banking
Categories for Sharia non-compliance risk, displaced commercial risk, equity investment risk, rate-of-return risk, fiduciary risk and asset/commodity price risk are seeded (`kind = islamic`) and fully editable. Recording them supports risk management only; **Sharia compliance is determined by the Sharia supervisory board, not by this software.**

## Assumptions
1. One department per user and one branch per user for restricted roles; a risk belongs to exactly one department and optionally one branch (no branch = department/head-office level).
2. "Active risk" = `status = active` and not archived. "Overdue action" = action not completed/cancelled with a deadline before today (action on archived risks excluded).
3. "High residual" = residual score in a band flagged *counts as high* (demo: High and Critical).
4. Gregorian dates, Latin digits (input also accepts Arabic-Indic digits for amounts).
5. Default language Arabic (configurable). Arabic font stack uses fonts already present on user devices (Segoe UI/Tahoma/Noto); no web fonts are loaded.
6. Records are never hard-deleted: archive/restore, deactivate users/lookups.
7. Near misses carry zero loss; closing an incident requires a root cause.

## Limitations and what is not verified
See [`docs/TEST_REPORT.md`](docs/TEST_REPORT.md). In short: automated tests ran on PHP 8.4 + MariaDB 10.11 with PHP's built-in server. **Not verified:** real Apache/`.htaccess` behaviour, Bluehost/cPanel, PHP 8.1–8.3, MySQL 5.7/8, phpMyAdmin import UI, real HTTPS, email (none is sent), screen-reader use, formal WCAG audit, load/performance, penetration test. Not implemented: password-reset by email (admin resets passwords), MFA/SSO, email notifications, Excel (.xlsx) export (CSV only), PDF generation (browser "Print → Save as PDF"), multi-department users, per-field permissions.
