# Local evaluation on Windows (PowerShell). Runs THIS application (PHP + MySQL/MariaDB) on http://localhost:8080
# Prerequisites: XAMPP (or any PHP 8.1+ with pdo_mysql, mbstring, fileinfo enabled) and a running MySQL/MariaDB.
# Usage (from the project folder):   powershell -ExecutionPolicy Bypass -File bin\windows-quickstart.ps1
$ErrorActionPreference = 'Stop'
$php = (Get-Command php -ErrorAction SilentlyContinue).Source
if (-not $php -and (Test-Path 'C:\xampp\php\php.exe')) { $php = 'C:\xampp\php\php.exe' }
if (-not $php) { throw 'php.exe not found. Install XAMPP (https://www.apachefriends.org) or add PHP to PATH.' }
$mysql = 'C:\xampp\mysql\bin\mysql.exe'
Write-Host "Using PHP: $php"; & $php -v | Select-Object -First 1
& $php public\check.php
$dbName = Read-Host 'Database name to create (will hold demo data only)  [grc_demo]'; if (-not $dbName) { $dbName = 'grc_demo' }
$dbUser = Read-Host 'MySQL user  [root]'; if (-not $dbUser) { $dbUser = 'root' }
$dbPass = Read-Host 'MySQL password (XAMPP default is empty)' -AsSecureString
$plainDb = [Runtime.InteropServices.Marshal]::PtrToStringAuto([Runtime.InteropServices.Marshal]::SecureStringToBSTR($dbPass))
if (Test-Path $mysql) { $pw = if ($plainDb) { "-p$plainDb" } else { '' }; & $mysql -u $dbUser $pw -e "CREATE DATABASE IF NOT EXISTS ``$dbName`` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" }
else { Write-Host "mysql.exe not found at $mysql - create the database '$dbName' in phpMyAdmin (utf8mb4_unicode_ci) and press Enter."; Read-Host | Out-Null }
$adminPw = Read-Host 'Choose an administrator password for this local test (12+ chars, letters + digit)'
$demoPw  = Read-Host 'Choose one password for all synthetic demo users (12+ chars, letters + digit)'
$env:GRC_DB_HOST = '127.0.0.1'; $env:GRC_DB_NAME = $dbName; $env:GRC_DB_USER = $dbUser; $env:GRC_DB_PASS = $plainDb
$env:GRC_ADMIN_USER = 'admin'; $env:GRC_ADMIN_PASS = $adminPw; $env:GRC_ADMIN_EMAIL = 'admin@example.invalid'
$env:GRC_DEMO = '1'; $env:GRC_DEMO_PASS = $demoPw
$env:GRC_CONFIG_FILE = (Join-Path $PWD 'grc-config-local\config.php')
& $php bin\install.php
if ($LASTEXITCODE -ne 0) { throw 'Install failed (see message above). If it says "Already installed", drop the database and delete storage\installed.lock and grc-config-local.' }
Write-Host "`nOpen http://localhost:8080  - login: admin / (the password you chose).  Demo users: demo.risk, demo.compliance, demo.dept.retail, demo.dept.it, demo.branch.tripoli, demo.exec, demo.auditor (password = demo password; they must change it at first login)."
Write-Host 'Press Ctrl+C to stop the server.'
& $php -S localhost:8080 bin\dev-router.php
