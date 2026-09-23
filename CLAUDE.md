# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

Quotation Management System (QMS) for **OXIAURA Plantation (PVT) LTD**, an Agarwood-investment
company: an enterprise app in **core PHP 8.2, no framework, no Composer**. MVC-inspired, PDO/MySQL,
Bootstrap 5, vendored TCPDF for letter-style PDFs. See `README.md`, `INSTALL.md`, `DEPLOY.md` for
setup/deploy details.

Quotations are **plan-type investment proposals**, not generic line-item invoices. Each of the six
products (Royal Plus, Guaranteed Plus, Monthly Wealth, Supreme Plus, Golden Crop, Plant Selling) has
its own inputs, calculation, and projection table, rendered as a personal letter PDF.

## Environment reality (Windows / XAMPP)

- DB is **MariaDB 10.4** locally (XAMPP), but the schema targets **both MySQL 8 and MariaDB** — keep
  SQL within the portable subset (InnoDB, utf8mb4, no MySQL-8-only or MariaDB-only syntax).
- **GD is off.** Do not rely on it. Logo and letterhead images embed fine without GD; alpha
  transparency is the only thing GD would improve.
- **Composer is not installed and must not be required.** TCPDF is vendored under `libs/tcpdf/`.

## Commonly used commands

```bash
# Vendor TCPDF (one-time; downloads into libs/tcpdf/, no Composer)
powershell -ExecutionPolicy Bypass -File scripts/install_tcpdf.ps1

# Vendor the Carlito letter font (one-time; downloads into libs/tcpdf/fonts/).
# Without it PdfService falls back to helvetica, which is not what the letter is set in.
powershell -ExecutionPolicy Bypass -File scripts/install_fonts.ps1

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
written via `put`/`putMany` (upsert). Company branding, default terms, tax rate, quotation prefix and
the `letterhead_image` upload live here and feed the PDF and the quotation builder.

**Plan types** (`app/PlanTypes/`): the heart of the domain. Each product implements
`PlanTypeInterface` (most extend `AbstractPlanType`; Royal/Guaranteed extend `InterestPlanType`) and
is registered in `PlanTypeRegistry`. A type declares `inputFields($params)` (drives the dynamic
builder form), `defaultParameters()` + `defaultBenefits()` + `defaultSummary()` + `defaultTerms()`
(seed/admin-editable), `validate()`, and `compute($inputs, $params)`. `compute()` returns a
**render-agnostic projection** so the show view and the PDF render any plan type without knowing its
specifics:

- `intro` — the letter's opening paragraph.
- `details` — the letter's flat "Investment Plan Details" table:
  `{title, headers[], rows[]}` where each row is `{label, value}`. Built via
  `AbstractPlanType::details()`, which **drops any row whose value is `''`** — so a type lists every
  row it might emit and lets the data decide which appear (e.g. monthly-only rows on an annual plan).
  Quotations issued before this was flattened carry `sections[]` instead; `Quotation::detailRows()`
  reads both shapes and is what the show view and `PdfService` both call — never read
  `details['rows']` directly, or an older quotation renders an empty table.
- `tokens` — a flat map of pre-formatted strings (`amount`, `monthly_return`, `term_label`, …) used to
  fill `${token}` placeholders in the plan's letter templates.
- `headers[]` / `rows[][]` — the older flat table, retained as the projection's on-screen fallback.
- `summary{}`, `headline_amount`.

`availableTokens($params)` (implemented once in `AbstractPlanType`) derives the token list by running
a representative `compute()`, so the plan-edit reference panel can never drift from what `compute()`
actually emits. To add a product: add a class + register it. Rates/prices live in the plan's editable
`parameters` JSON, **not** in code. A rate that is a percentage is stored **per year** — the interest
plans' `years.<n>.annual_rate` — and the monthly figure the letter quotes is derived as annual ÷ 12 at
compute time, so a plan cannot hold a monthly rate that contradicts its annual one (a legacy
`monthly_rate` is only honoured, ×12, when `annual_rate` is absent). Because that division rarely lands
on a round number, the monthly payout is quoted in whole rupees via `AbstractPlanType::fmtWhole()`; the
annual, term-total and maturity figures keep full precision and are computed from the annual rate, not
from the rounded monthly.

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

**PDF + verification** (`PdfService` + `LetterPdf`): `require_once libs/tcpdf/tcpdf.php` in the
constructor. `generateQuotation()` renders an OXIAURA **letter** — the issued document's own anatomy:
a full-page letterhead, date, addressee, "Dear Sir/ Madam", the intro paragraph, the flat "Investment
Plan Details" table, **Investment Summary**, **Terms & Conditions**, "Thank you," and the signatory.
The letter is set to the reference document's measured geometry (margins, gaps, table rules and
leading are named constants at the top of the class) and is expected to be **visually
indistinguishable** from `Royal Plus Quotation New II.pdf`; anything changed there should be checked
against that file rather than eyeballed.

There is **no QR code, no verify/expiry/contact block and no Benefits & Conditions** on the letter.
`verification_token` and the public `/verify/{token}` page (`VerifyController`) still work from a
shared link, and `plans.benefits` and the snapshot's `benefits` key are still stored — they are
simply not printed, and the plan form no longer offers them, so a future restoration has the data.

`LetterPdf extends TCPDF` for two reasons, both load-bearing:
- It paints `settings.letterhead_image` (falling back to the shipped
  `public/assets/img/letterhead-default.jpg`, an extracted ~137 dpi asset — upload the print-resolution
  artwork in Settings) as a **full-bleed page background from `Header()`**, so it also covers the pages
  TCPDF adds on an automatic break. `Header()` must disable and restore AutoPageBreak around the
  `Image()` call, and must restore the **break margin with it** — `setAutoPageBreak()` writes both and
  its `$margin` defaults to `0`, so restoring only the flag would silently drop the bottom margin.
- It sets `tcpdflink = false`, because TCPDF otherwise stamps a 1 pt "Powered by TCPDF" hyperlink on
  every document it closes and offers no public setter.

Branding/signatory come from `settings` (company_*, signatory_name/title). `generateReport()` keeps
the older coded letterhead and a plain `TCPDF` — reports are internal, not letters, and still carry
the TCPDF credit.

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
- **TCPDF internals the letter depends on.** Each of these cost real debugging time; the comments at
  `PdfService`/`LetterPdf` say where each one bites.
  - `Image()` runs its box through `fitBlock()`, which **shrinks it to the page's writable height
    whenever `AutoPageBreak` is on — even with `$fitonpage = false`.** A full-bleed background must be
    drawn with AutoPageBreak off for the duration of the call.
  - `setAutoPageBreak($auto, $margin = 0)` writes the **break margin** as well as the flag, and its
    `$margin` defaults to `0`. Always restore it with the margin it was set with.
  - `MultiCell()`'s line height is `cell_height_ratio × fontSize`; its `$h` argument is *not* the line
    height. `writeHTML` maps a unitless CSS `line-height` onto that same ratio, which is how the letter
    sets its leading.
  - `getStringHeight($w, $txt)` measures a wrapped block **without drawing it** (it saves and restores
    `cell_padding` and `lasth`), so it is safe to call to decide whether a block will fit.
  - `PageBreakTrigger` is protected with no getter — `LetterPdf::pageBreakTrigger()` exposes it, which
    is how `PdfService` decides for itself when to break.
  - `setCellPaddings()` insets every cell by **1 mm on each side** by default; the letter zeroes it and
    re-applies its own asymmetric table insets.
  - `Rect($x, $y, $w, $h)` defaults to style `'S'` — stroke only, no fill, which is why the table's
    cells let the watermark show through. TCPDF's default line width (0.2 mm) is the letter's hairline.
  - TCPDF stams `Close()` with a 1 pt "Powered by TCPDF" hyperlink (`$tcpdflink`, hex-escaped in the
    source so a plain grep for the text won't find it) and offers no public setter — `LetterPdf`'s
    constructor turns it off.
- **The letter's font is Carlito** (a metric-compatible Calibri clone), vendored by
  `scripts/install_fonts.ps1` into the git-ignored `libs/tcpdf/fonts/`, so **every checkout must run
  that script**. `PdfService::font()` falls back to `helvetica` when it is missing — a checkout that
  skipped the step still produces a PDF, just not the intended typeface.
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
