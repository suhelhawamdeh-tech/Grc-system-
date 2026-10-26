# Run the platform on a Windows laptop (evaluation only)

This runs the **same PHP/MySQL application** locally with demo data. It is not a production setup (PHP's built-in server, no HTTPS). The steps have not been executed on Windows by the author; the same commands were run on Linux.

## Option A — one script (recommended)
1. Install **XAMPP** (PHP 8.2 + MariaDB): https://www.apachefriends.org. Open the XAMPP Control Panel and **Start MySQL** (Apache is not needed).
2. Download/unzip `grc-platform-1.0.0.zip` (or clone the repo) into e.g. `C:\grc`.
3. Open PowerShell in that folder and run:
   ```powershell
   powershell -ExecutionPolicy Bypass -File bin\windows-quickstart.ps1
   ```
   It checks PHP, creates the database, installs the schema plus **synthetic** data, and starts the server.
4. Browse to **http://localhost:8080** and sign in as `admin` with the password you chose. Use the language switch (العربية / English).

## Option B — manual
```powershell
$env:PATH += ';C:\xampp\php'
# create an empty database "grc_demo" (utf8mb4) in phpMyAdmin: http://localhost/phpmyadmin
$env:GRC_DB_HOST='127.0.0.1'; $env:GRC_DB_NAME='grc_demo'; $env:GRC_DB_USER='root'; $env:GRC_DB_PASS=''
$env:GRC_ADMIN_USER='admin'; $env:GRC_ADMIN_PASS='ChooseAStrongPass1'; $env:GRC_ADMIN_EMAIL='admin@example.invalid'
$env:GRC_DEMO='1'; $env:GRC_DEMO_PASS='ChooseADemoPass1'
$env:GRC_CONFIG_FILE="$PWD\grc-config-local\config.php"
php bin\install.php
php -S localhost:8080 bin\dev-router.php      # keep the same PowerShell window (env var GRC_CONFIG_FILE must be set)
```
If PHP complains about missing extensions, edit `C:\xampp\php\php.ini` and enable `extension=pdo_mysql`, `extension=mbstring`, `extension=fileinfo`, `extension=zip`, then restart the command.

## Option C — Docker
Not supported by this project's deployment target and not provided.

## What to try
Log in → dashboard (heat maps, tiles) → Risk register (filter, search Arabic) → new risk (scores update from the matrix) → add treatment action → upload a PDF → Incidents (net loss) → Reports (CSV opens in Excel; use *CSV (;)* if columns don't split) → Administration → Risk assessment scales → log in as `demo.dept.retail` to see department-restricted views.

Reset: drop the database, delete `storage\installed.lock` and `grc-config-local`, run the script again.
