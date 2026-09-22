# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

Quotation Management System (QMS) for **OXIAURA Plantation (PVT) LTD**, an Agarwood-investment
company: an enterprise app in **core PHP 8.2, no framework, no Composer**. MVC-inspired, PDO/MySQL,
Bootstrap 5, vendored TCPDF for letter-style PDFs with native QR codes. See `README.md`,
`INSTALL.md`, `DEPLOY.md` for setup/deploy details.

Quotations are **plan-type investment proposals**, not generic line-item invoices. Each of the six
products (Royal Plus, Guaranteed Plus, Monthly Wealth, Supreme Plus, Golden Crop, Plant Selling) has
its own inputs, calculation, and projection table, rendered as a personal letter PDF.

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

**Plan types** (`app/PlanTypes/`): the heart of the domain. Each product implements
`PlanTypeInterface` (most extend `AbstractPlanType`; Royal/Guaranteed extend `InterestPlanType`) and
is registered in `PlanTypeRegistry`. A type declares `inputFields($params)` (drives the dynamic
builder form), `defaultParameters()` + `defaultBenefits()` + `defaultSummary()` + `defaultTerms()`
(seed/admin-editable), `validate()`, and `compute($inputs, $params)`. `compute()` returns a
**render-agnostic projection** so the show view and the PDF render any plan type without knowing its
specifics:

- `intro` — the letter's opening paragraph.
- `details` — the sectioned "Investment Plan Details" table: `{title, headers[], sections[]}` where
  each section is `{title, rows[]}` and each row is `{label, value}`. Built via
  `AbstractPlanType::details()`, which **drops any row whose value is `''`** — so a type lists every
  row it might emit and lets the data decide which appear (e.g. monthly-only rows on an annual plan).
- `tokens` — a flat map of pre-formatted strings (`amount`, `monthly_return`, `term_label`, …) used to
  fill `${token}` placeholders in the plan's letter templates.
- `headers[]` / `rows[][]` — the older flat table, retained as the projection's on-screen fallback.
- `summary{}`, `headline_amount`.

`availableTokens($params)` (implemented once in `AbstractPlanType`) derives the token list by running
a representative `compute()`, so the plan-edit reference panel can never drift from what `compute()`
actually emits. To add a product: add a class + register it. Rates/prices live in the plan's editable
`parameters` JSON, **not** in code.

**Letter templates** (`plans.summary_template` / `plans.terms_template`): the "Investment Summary" and
"Terms & Conditions" prose, authored per plan in plan edit mode as `${token}` templates. The plan form
renders a click-to-insert chip for every token the selected type can fill. `Core/Placeholder` resolves
them — `resolve()` (unknown tokens left visible), `lines()` (resolves and strips leading
bullets/numbering so the renderer re-adds its own), `referenced()` / `unknown()` (used by
`PlanController::validatePlan` to **reject** a save whose template references a token the type cannot
supply, rather than printing `${amunt}` on a customer's letter).

**Quotation creation** (`QuotationController::store`): captures only the inputs the chosen plan
type declares, runs `$type->validate()`, then `$type->compute()` with the plan's `parameters`. The
projection is **enriched** with `plan_label`/`letter_title`/`benefits` **and the resolved
`summary_lines[]` / `terms_lines[]`** (a snapshot, so editing a plan later never changes historical
quotes — the *resolved text* is stored, not the template) and stored as JSON on
`quotations.projection`; `quotations.inputs` keeps the raw inputs. `total`/`subtotal` hold the
projection's `headline_amount` (capital) for dashboards/reports; discount/tax are unused (0). Number
via `QuotationNumberService` (`{PREFIX}-{YYYYMM}-{NNNN}`) + a random `verification_token`. `plans`
carry `plan_type` + JSON `parameters` + `benefits` + the two templates; decode with
`Plan::parameters()` / `Quotation::projection()`.

**PDF + verification** (`PdfService`): `require_once libs/tcpdf/tcpdf.php` in the constructor.
`generateQuotation()` renders an OXIAURA **letter** (branded letterhead, date, addressee, "Dear Sir/
Madam", the intro paragraph, the sectioned details table, **Investment Summary**, **Terms &
Conditions**, the optional Benefits block, signatory) + a native QR via `write2DBarcode` (no GD),
placed just after the body rather than at a fixed `y` so a long letter can't have it land on the text.
The QR encodes `/verify/{token}`, a public (no-auth) page served by `VerifyController`.
Branding/signatory come from `settings` (company_*, signatory_name/title). Quotations issued before
the sectioned layout still render — `letterBody()` falls back to the flat `headers`/`rows` table when
`projection.details` is absent.

## Cross-cutting conventions & gotchas

- **`Response::html($html, $status)` sets the HTTP status.** When rendering an error page, pass the
  status explicitly (`Response::html(View::render('errors/403', …), 403)`) — a bare call defaults to
  200 and silently downgrades 403/404/419/500. Same pattern in `App`, `Controller`, `RoleMiddleware`.
- **`config/config.php` is loaded twice** (App bootstrap + the `config()` helper). Any function it
  declares (e.g. `env()`) must be wrapped in `if (!function_exists(...))` or it fatals on redeclare.
- **TCPDF emits PHP warnings on PHP 8** (e.g. undefined `startcolumn`/`startx` in table-border code)
  that corrupt the binary stream under `display_errors`. `PdfService::render()` wraps every build in
  an output buffer + reduced `error_reporting` so notices can't contaminate the returned PDF. Also
  **avoid `<ul>/<li>`** in `writeHTML` (broken on PHP 8) — render bullets as `<br/>`-joined lines,
  and emit `•` as the entity `&#8226;` (TCPDF's core font renders a literal `•` as mojibake).
- **Seeding JSON columns in `database.sql`:** MySQL interprets backslash escapes in string literals,
  so a `\n` inside JSON becomes a real newline → invalid JSON. Write `\\n` in the SQL, and keep the
  `SET NAMES utf8mb4;` at the top of the file so multibyte chars (the `•` in benefits) import intact.
  The app stores runtime JSON via PDO prepared statements, which has neither problem. The letter
  templates are **TEXT, not JSON** — there a `\n` is the correct spelling for a line break.
- **Schema changes ship as migrations in `database/migrations/`**, run once per database by hand
  (`mysql … < file.sql`); `database/database.sql` is the from-scratch seed and must carry the same
  change. Split a change into a `_schema.sql` (the `ALTER`) and a `_backfill.sql` (data) because
  MySQL 8 has no `ADD COLUMN IF NOT EXISTS` and stops at the first error — a combined file would skip
  its own backfill on a re-run. Write backfills guarded (`WHERE col IS NULL OR col = ''`) so they are
  safe to re-run, and keep `SET NAMES utf8mb4;` at the top.
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

## graphify

This project has a knowledge graph at graphify-out/ with god nodes, community structure, and cross-file relationships.

Rules:
- For codebase questions, first run `graphify query "<question>"` when graphify-out/graph.json exists. Use `graphify path "<A>" "<B>"` for relationships and `graphify explain "<concept>"` for focused concepts. These return a scoped subgraph, usually much smaller than GRAPH_REPORT.md or raw grep output.
- If graphify-out/wiki/index.md exists, use it for broad navigation instead of raw source browsing.
- Read graphify-out/GRAPH_REPORT.md only for broad architecture review or when query/path/explain do not surface enough context.
- After modifying code, run `graphify update .` to keep the graph current (AST-only, no API cost).
