# Test report (session 1)

**Command:** `php tests/run.php` — dependency-free runner; copies the app to a temp dir, wipes two scratch databases, installs on a clean DB, starts PHP's built-in server and drives it over HTTP with a cookie-aware client.
**Result: 925 checks passed, 0 failed** (609 in the first release plus 316 for release 1.1: usability, controls, Sharia, BIA/approvals, e-mail with a fake SMTP sink, Excel export and board pack). Not verified here: Bluehost, Apache, PHP 8.1–8.3, real SMTP providers, Microsoft Excel opening of the generated .xlsx (structure and XML validity are tested, but the file was not opened in Excel), screen readers.

**Environment actually used:** Linux container, PHP 8.4.19 (CLI, built-in server), MariaDB 10.11.14, curl. Chromium (Playwright) was used only to take screenshots of Arabic/English/mobile pages for a visual check.

| Area | Passed | Failed |
|---|---|---|
| Unit: money, scoring, csv, permissions, i18n | 37 | 0 |
| Clean install via CLI on an empty database, then start server | 19 | 0 |
| Authentication | 34 | 0 |
| Authorization: roles, direct URLs, scope | 96 | 0 |
| Risk workflow: create / validate / score / edit / filter / archive | 48 | 0 |
| Incidents: loss calculation and validation | 22 | 0 |
| CSV export safety and print reports | 15 | 0 |
| Uploads | 23 | 0 |
| Admin: users, org structure, scales, settings, audit | 37 | 0 |
| Phase 2 unit: KRI status logic | 4 | 0 |
| Phase 2 authorization matrix | 73 | 0 |
| Phase 2: KRIs | 19 | 0 |
| Phase 2: risk appetite and breaches | 13 | 0 |
| Phase 2: compliance obligations | 20 | 0 |
| Phase 2: committee decisions | 7 | 0 |
| Phase 2: policies and versions | 13 | 0 |
| Phase 2: RCSA workflow | 24 | 0 |
| Phase 2: governance dashboard tiles = database | 7 | 0 |
| Language switch, RTL / LTR rendering | 63 | 0 |
| Web setup wizard on a clean database + subdirectory deployment | 28 | 0 |
| Packaging | 7 | 0 |

## What the tests demonstrate
* **Authentication:** CSRF rejection, uniform login errors, session ID regeneration, throttling after 5 failures (even with the right password), forced password change, logout via POST only, deactivated users blocked.
* **Authorization:** role × URL matrix (Phase 1 and 2), unauthorised direct URLs, out-of-scope records return 404 (view, edit, POST edit, archive, print, attachments, CSV, dashboard), scope enforced on create/move, restricted users cannot widen CSV via query parameters.
* **Risk assessment:** score = L×I computed server-side (forged score fields ignored), residual recorded explicitly, rationale rule, field-level history, filters/search (Arabic, LIKE-wildcards, SQL-injection strings, junk parameters), archive/restore.
* **Losses:** exact decimal arithmetic (0.3−0.1=0.200), validation rules, net = gross − recoveries, totals equal `SUM()` in the database.
* **Dashboard:** KPI values, heat-map cell sums, chart drill-down counts and Phase 2 tiles are compared with independent SQL for admin, department-scoped, branch-scoped and executive users.
* **Exports:** BOM, column consistency, formula-injection neutralisation (`= + - @ TAB`), `;` variant, audit of exports, print pages RTL/LTR.
* **Uploads:** allow-list + content sniffing (PHP disguised as pdf/png, fake docx, exe, html, double extension, empty, 6 MB), random non-public storage, authorised `attachment` download with `nosniff`, soft delete.
* **i18n:** identical ar/en key sets (base + module files), no untranslated keys rendered on ~30 pages in both languages, `dir`/`lang` attributes, language switch, open-redirect protection, stylesheet uses logical properties only, no inline scripts.
* **Installation:** CLI install on an empty DB, second install refused, web wizard (token, DB test, config written with 600 perms + deny file, admin creation, lock, 404 afterwards, closed even if the lock file is deleted), sub-directory deployment under `/grc/` (assets, links, cookie path, deep-link `next`).
* **Packaging:** ZIP contents (no tests/secrets/runtime data), `install.sql` imports into an empty DB and equals the migrated schema.

## NOT verified (cannot be claimed)
* Real **Bluehost / cPanel** behaviour; real **Apache** and the shipped `.htaccess` files (tests use PHP's built-in server with an equivalent router); AutoSSL/HTTPS and the `Secure`/HSTS paths.
* **PHP 8.1–8.3** (only 8.4 exercised; code avoids 8.4-only features but this is untested) and **MySQL 5.7/8.x** (only MariaDB 10.11).
* phpMyAdmin's import UI (the SQL was executed through PDO with emulated prepares).
* Excel opening the CSV (structure, BOM and escaping verified programmatically, not in Excel), print-to-PDF output quality, screen readers, keyboard-only walkthrough, formal WCAG contrast/accessibility audit (band colours use a luminance-based text colour; not measured).
* Performance/load, concurrency, large datasets; session handling under host-specific `session.save_path`/GC settings.
* Independent penetration test or security certification — none has been performed; the controls listed in the README are implemented and unit/integration tested only as described above.
* Arabic wording was written by the author of this software (an AI) and needs review by a native-speaking banking/compliance professional before production use.
