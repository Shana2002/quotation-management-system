# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

Quotation Management System (QMS): an enterprise app in **core PHP 8.2, no framework, no Composer**.
MVC-inspired, PDO/MySQL, Bootstrap 5, vendored TCPDF for PDFs with native QR codes. See `README.md`,
`INSTALL.md`, `DEPLOY.md` for setup/deploy details.

## Environment reality (Windows / XAMPP)

- DB is **MariaDB 10.4** locally (XAMPP), but the schema targets **both MySQL 8 and MariaDB** — keep
  SQL within the portable subset (InnoDB, utf8mb4, no MySQL-8-only or MariaDB-only syntax).
- **GD is off.** Do not rely on it. QR codes use TCPDF's native `write2DBarcode` (no GD). Logo PNG
  embedding works without GD; alpha transparency is the only thing GD would improve.
- **Composer is not installed and must not be required.** TCPDF is vendored under `libs/tcpdf/`.

## Commonly used commands

```bash
# Vendor TCPDF (one-time; downloads into libs/tcpdf/, no Composer)
powershell -ExecutionPolicy Bypass -File scripts/install_tcpdf.ps1

# Start MariaDB if it isn't running (XAMPP). Use run_in_background — it exits otherwise.
"C:/xampp/mysql/bin/mysqld.exe" --defaults-file="C:/xampp/mysql/bin/my.ini" --console

# Create / reset the database to the clean seed (DROPs and recreates all tables)
"C:/xampp/mysql/bin/mysql.exe" -u root -h 127.0.0.1 < database/database.sql

# Serve
"C:/xampp/php/php.exe" -S 127.0.0.1:8000 -t public      # then http://127.0.0.1:8000

# Lint every PHP file (there is no unit-test suite; lint + the curl smoke tests below are the gate)
for f in $(find app config routes public -name '*.php'); do "C:/xampp/php/php.exe" -l "$f"; done
```

There is **no test framework**. Verification is done with throwaway PHP/cURL scripts driving the
running server through a cookie jar (login → CSRF token from the form → exercise routes, assert on
HTTP status + body substrings). Write these to a temp dir, not into the repo.

> Windows `curl` can't read MinGW `/tmp/...` paths for `-F file=@...` uploads — convert with
> `cygpath -w` first.

## Architecture (the parts that span files)

**Request flow:** `public/index.php` → `app/Core/App.php` (PSR-4-ish autoloader for `App\`, loads
config + helpers, starts session, sends security headers) → `Router` matches `routes/web.php` →
runs middleware tokens → controller action → `View::render`.

**Routing + middleware** (`routes/web.php`): routes are `[Controller::class, 'method']` plus a
middleware-token array. Tokens are strings resolved in `Router::runMiddleware`:
- `'auth'` → `AuthMiddleware`
- `'role:admin,manager'` → `RoleMiddleware` (comma-separated allowed roles)

Path params (`{id}`, `{token}`) become ordered controller method args.

**Data access** (`app/Core/Model.php`): every model extends this base, which provides
`find/findBy/all/where/count/create/update/delete` built **only** on PDO prepared statements.
`$fillable` gates mass assignment; any column name interpolated into SQL goes through `guardColumn`.
Models add bespoke JOIN queries as needed (e.g. `User::findByEmail` joins the role name).

**Roles & data scoping:** roles are `admin | manager | executive`. The single source of truth for
quotation visibility is `Quotation::scopeForUser($user)`, which returns a `[whereFragment, params]`
pair reused by every list/stat/report query. Manager scope = self + executives whose `manager_id`
is the manager. `Quotation::userCanAccess()` guards single-record access. Reports
(`ReportService`) and dashboards reuse the same scope — change scoping in one place.

**Auth** (`app/Core/Auth.php`): session stores the user row (minus hash) under `auth_user`,
including the joined `role_name`. `Auth::hasRole()`/`can()` drive both middleware and view-level
gating. Login regenerates the session id and records every attempt in `login_activity`.

**Views** (`app/Core/View.php`): plain-PHP templates in `app/Views`. `render($view, $data, $layout)`
renders the child into `$content`, then renders the layout. Layouts: `layouts/app` (authenticated
shell) and `layouts/auth` (login + public verify). Always escape output with `e()`.

**Settings** are a key/value table (`settings`), read via `Setting::allAsMap()` (request-cached) and
written via `put`/`putMany` (upsert). Company branding, default terms, tax rate and quotation prefix
live here and feed the PDF and the quotation builder.

**Quotation creation** (`QuotationController::store`): line items arrive as `items[i][...]`; the
controller **recomputes all money server-side** (never trusts posted totals), wraps the quotation +
items insert in a transaction, and generates the number via `QuotationNumberService`
(`{PREFIX}-{YYYYMM}-{NNNN}`, sequence derived from the max existing number that month) and a random
`verification_token`.

**PDF + verification** (`PdfService`): `require_once libs/tcpdf/tcpdf.php` in the constructor, builds
the document with `writeHTML` + a native QR via `write2DBarcode`. The QR encodes `/verify/{token}`,
a public (no-auth) page served by `VerifyController` that confirms authenticity.

## Cross-cutting conventions & gotchas

- **`Response::html($html, $status)` sets the HTTP status.** When rendering an error page, pass the
  status explicitly (`Response::html(View::render('errors/403', …), 403)`) — a bare call defaults to
  200 and silently downgrades 403/404/419/500. Same pattern in `App`, `Controller`, `RoleMiddleware`.
- **`config/config.php` is loaded twice** (App bootstrap + the `config()` helper). Any function it
  declares (e.g. `env()`) must be wrapped in `if (!function_exists(...))` or it fatals on redeclare.
- **CSRF:** every state-changing form must include `<?= csrf_field() ?>`, and the controller action
  must call `$this->verifyCsrf()` first (renders 419 on failure).
- **Validation:** server-side via `Core/Validator` with pipe rules (`required|email|unique:table,col[,ignoreId]`);
  on failure use `$this->back($path, $errors, $old)` which flashes errors + old input.
- **Helpers** (`app/Helpers/functions.php`): `e`, `url`, `asset`, `money`, `old`, `csrf_field`,
  `can`, `status_badge`, `format_date`, `config`. `base_url()` auto-detects from the request unless
  `APP_URL` is set — keep links going through `url()`/`asset()` so subfolder hosting works.
- **DB credentials / config** are env-overridable (`DB_*`, `APP_*`) with a git-ignored
  `config/database.local.php` fallback. Don't hardcode.
- **Front-end** is CDN-loaded (Bootstrap, Chart.js, qrcodejs); the CSP header in `App` whitelists
  jsdelivr/cdnjs, so new external script/style origins must be added there too. Dark/light theme is
  Bootstrap `data-bs-theme` toggled in `public/assets/js/app.js` and persisted in localStorage.
```
