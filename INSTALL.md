# Installation Guide (Local — XAMPP / Windows)

This guide sets up QMS locally on XAMPP (Apache + MariaDB) or with PHP's built-in server.

## Requirements

- PHP **8.2+** with extensions: `pdo_mysql`, `mbstring`, `openssl`, `curl`, `fileinfo`
  (all enabled by default in recent XAMPP). `gd` is **optional** — QR codes render without it.
- MySQL 8 **or** MariaDB 10.4+ (XAMPP ships MariaDB).
- The project located at `C:\xampp\htdocs\quotation` (this folder).

## Step 1 — Vendor TCPDF (no Composer required)

TCPDF is downloaded directly into `libs/tcpdf/`:

```powershell
powershell -ExecutionPolicy Bypass -File scripts\install_tcpdf.ps1
```

Verify `libs\tcpdf\tcpdf.php` now exists.

## Step 2 — Create the database

Using the MariaDB client bundled with XAMPP:

```powershell
& "C:\xampp\mysql\bin\mysql.exe" -u root < database\database.sql
```

Or via **phpMyAdmin** (http://localhost/phpmyadmin → *Import* → choose `database/database.sql`).

This creates the `qms` database with all tables, indexes, foreign keys and seed data
(default admin/manager/executive accounts, demo plans, customers and quotations).

## Step 3 — Configure database credentials (if needed)

Defaults target XAMPP (`127.0.0.1`, user `root`, empty password, database `qms`) and require
no changes. To override without editing tracked files, create `config/database.local.php`:

```php
<?php
return [
    'host'     => '127.0.0.1',
    'database' => 'qms',
    'username' => 'root',
    'password' => '',
];
```

Environment variables (`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, `DB_PORT`) also take effect.

## Step 4 — Serve the app

### Option A — PHP built-in server (simplest)

```powershell
& "C:\xampp\php\php.exe" -S localhost:8000 -t public
```

Open **http://localhost:8000**.

### Option B — XAMPP Apache

1. Start **Apache** and **MySQL** in the XAMPP Control Panel.
2. Open **http://localhost/quotation/public/**

   The included `public/.htaccess` handles routing. (The root `.htaccess` also forwards
   `http://localhost/quotation/` into `public/`.)

> Tip: For a clean root URL locally, point an Apache VirtualHost `DocumentRoot` at
> `C:\xampp\htdocs\quotation\public`.

## Step 5 — Sign in

| Role      | Email                 | Password       |
|-----------|-----------------------|----------------|
| Admin     | admin@qms.local       | `Admin@123`    |
| Manager   | manager@qms.local     | `Manager@123`  |
| Executive | executive@qms.local   | `Executive@123`|

Then visit **Settings** (as admin) to set your company name, logo and default terms.

## Troubleshooting

- **Blank page / 500** — set `APP_DEBUG=true` (or leave the default local config) to see errors;
  check `php` error log. Ensure Step 1 (TCPDF) completed.
- **"Database connection failed"** — confirm MySQL/MariaDB is running and credentials match.
- **PDF fails** — confirm `libs/tcpdf/tcpdf.php` exists (re-run Step 1).
- **Logo won't embed with transparency** — optionally enable `extension=gd` in `php.ini`
  and restart PHP/Apache (not required for QR codes).
- **Assets/CSS missing** — when using Apache from a subfolder, access the app via
  `/quotation/public/` so base-URL auto-detection resolves correctly.
