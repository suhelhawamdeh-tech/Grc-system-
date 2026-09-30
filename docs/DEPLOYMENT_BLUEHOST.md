# Deploying on Bluehost shared hosting (cPanel)

> Written from cPanel/Bluehost documentation knowledge; **it has not been executed on a live Bluehost account.** Menu names can differ slightly by plan/theme. Keep a backup before each step that changes data.

You need: the deployment ZIP (`grc-platform-<version>.zip`, built with `php bin/package.php`), cPanel access, and a domain you control.

## 0. Choose a layout
| Layout | Use when |
|---|---|
| **A (recommended)** subdomain whose *document root* is the `public/` folder; private code lives above it | you can set the document root when creating the subdomain (Bluehost allows this) |
| **B** everything inside the (sub)domain folder, protected by the bundled root `.htaccess` | you must reuse an existing docroot, or install into a sub-directory (e.g. `example.com/grc/`) |

## 1. Create the subdomain and document root (layout A)
1. cPanel → **Domains** (or *Subdomains*) → *Create A New Domain* → `grc.example.com`.
2. Untick "share document root"; set the document root to `/home/CPUSER/grc-app/public` (the folder will be created by the upload in step 3; create it now via File Manager if cPanel demands it exists).
3. Wait for DNS (usually minutes for a subdomain on Bluehost DNS).

## 2. PHP version and extensions
1. cPanel → **MultiPHP Manager** → select `grc.example.com` → PHP **8.1, 8.2 or 8.3** (8.4 if offered).
2. cPanel → **Select PHP Version → Extensions** (or MultiPHP INI Editor): ensure `pdo_mysql`, `mbstring`, `fileinfo`, `json`, `ctype`; optional `zip`, `intl`. In *MultiPHP INI Editor* set `upload_max_filesize = 6M` and `post_max_size = 8M`.

## 3. Upload the files
1. File Manager → `/home/CPUSER/` → **Upload** the ZIP → **Extract**. You get `grc-platform/`; rename it to `grc-app` (so `public/` is `/home/CPUSER/grc-app/public`, matching step 1).
2. Result (layout A):
```
/home/CPUSER/grc-app/app  database  storage  bin  docs  public   <- public = document root of the subdomain
/home/CPUSER/grc-config/                                          <- created by the setup wizard (outside the web root)
```
   *If you cannot use `public/` as the document root but the app folder is elsewhere*: copy `public/app-path.php.example` to `public/app-path.php` and put the absolute app path in it.
   *Layout B*: extract into the docroot itself (or into a `grc/` sub-folder of it). The root `.htaccess` forwards requests to `public/` and blocks `app/`, `database/`, `storage/`, `bin/`, `docs/`.
3. Permissions (File Manager → right-click → *Change Permissions*): folders `755`, files `644`; `storage/` and its sub-folders `750` (or `755` if the web user cannot write). Never `777`.

## 4. Pre-installation check
Open `https://grc.example.com/check.php`. Fix every **FAIL** (PHP version, extensions, writable `storage/*`). **WARN** on HTTPS is fine until step 7. The page disables itself after installation.

## 5. Create the database and user
1. cPanel → **MySQL® Databases** → *Create New Database* `grc` → full name becomes `CPUSER_grc`.
2. *MySQL Users → Add New User* `grcapp` with a **strong generated password** (store it in a password manager — not in the repo/chat) → `CPUSER_grcapp`.
3. *Add User To Database* → `CPUSER_grcapp` → `CPUSER_grc` → tick **ALL PRIVILEGES**.
4. Host is `localhost`, port `3306`.

## 6. Import the schema (two ways — choose one)
* **Recommended: let the web setup do it.** Skip to step 8; the wizard applies `database/migrations/*.sql` itself.
* **phpMyAdmin:** cPanel → phpMyAdmin → select `CPUSER_grc` → *Import* → choose `database/install.sql` from the ZIP → *Go*. (Empty database only.) The wizard then detects the existing schema and only asks for the administrator.
* Large host limits are not an issue: `install.sql` is < 100 KB.

## 7. HTTPS
cPanel → **SSL/TLS Status** → run **AutoSSL** for `grc.example.com` (Bluehost includes Let's Encrypt/Sectigo AutoSSL). When the padlock works, either enable *Force HTTPS Redirect* in cPanel (Domains) or tick "Redirect all HTTP requests to HTTPS" in the setup wizard / set `'force_https' => true` in `config.php`. Optionally add `'hsts' => true` under `app` once HTTPS is stable (HSTS is sticky).

## 8. First-run setup (creates the first administrator)
1. Visit `https://grc.example.com/setup`.
2. **Token**: open `/home/CPUSER/grc-app/storage/setup-token.txt` in File Manager (it is created on your first visit) and paste it. This proves control of the server; nobody else can run setup.
3. **Database**: enter host `localhost`, DB name `CPUSER_grc`, user `CPUSER_grcapp`, password. The wizard tests the connection, then writes `config.php` to `/home/CPUSER/grc-config/` (mode 600, with a deny-all `.htaccess`). If the folder is not writable it shows the file content so you can create it manually at one of the listed paths.
4. **Administrator**: institution names, your name, username, e-mail, strong password (12+ chars, letters + a digit).
5. Leave *"synthetic demonstration data"* **unticked for production**. Tick it only on an evaluation database.
6. After **Install**, `/setup` returns 404 permanently (lock file + "any user exists" guard) and the token file is deleted. Sign in, then:
   * *Administration → Departments / Branches / Risk categories / Reporting periods*
   * *Users* → create staff accounts (initial password forces change at first login)
   * *Risk assessment scales* → review the **demonstration** criteria, adjust to your board-approved methodology, then click *Mark as approved by the institution*.

## 9. Backups
* **Database:** cPanel → *Backup* (partial backup → download MySQL database `CPUSER_grc`) or phpMyAdmin → Export (*Custom*, all tables, "Add DROP TABLE"). Schedule a monthly download at minimum; cPanel/Bluehost account backups are not a substitute.
* **Files:** download `grc-app/storage/uploads/` (attachments) and `/home/CPUSER/grc-config/config.php` (keep encrypted/offline: it holds the DB password).
* Test a restore at least once.

## 10. Restore
1. New empty database + user (step 5). 2. phpMyAdmin → Import your `.sql` backup. 3. Upload the app (same or newer version), place `config.php` in `grc-config/` with the new DB details (or run `/setup`'s DB step only if no users exist). 4. Copy `storage/uploads/` back. 5. Sign in and spot-check risks, attachments and the audit log.

## 11. Upgrade
1. Announce a maintenance window; **back up DB + uploads + config** (step 9).
2. Upload the new ZIP to a temporary folder, extract, and copy over `app/`, `public/`, `database/`, `bin/`, `docs/` (do **not** overwrite `storage/` or `grc-config/`). Keep your `public/app-path.php` if you use one.
3. Sign in as administrator → *Administration → System status* → **Apply pending migrations** (or run `php bin/migrate.php` over SSH if available). Migrations are additive and recorded in `schema_migrations`.
4. Verify *System status* shows "up to date", then smoke-test login, a risk, an export.
5. Rollback = restore the previous files and the pre-upgrade DB backup.

## 12. Security hardening after go-live
* Delete `public/check.php` (optional; it self-disables).
* Confirm `https://grc.example.com/storage/…`, `/app/…`, `/database/install.sql` return 403/404.
* Confirm *System status → Config file outside web root* is OK.
* Use unique passwords, deactivate leavers, review the audit log.
* Keep PHP updated via MultiPHP Manager.
