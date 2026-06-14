# Quotation Management System (QMS)

An enterprise-grade Quotation Management System built in **core PHP 8.2** (no framework)
with an MVC-inspired architecture, MySQL/MariaDB, Bootstrap 5, and TCPDF — featuring
role-based access control, professional PDF quotations with QR verification, and reporting.

## Features

- **Roles & RBAC** — Admin, Manager, Executive with data scoping
  - *Admin*: full access — manage users, plans, settings, all quotations, all reports, audit logs
  - *Manager*: manage own executives, generate quotations, view team quotations & reports
  - *Executive*: generate and manage own quotations
- **Authentication** — bcrypt hashing, hardened sessions, login activity tracking, CSRF protection
- **Customers** — CRUD with per-customer quotation history (name, address, telephone, NIC, email)
- **Plans** — CRUD with active/inactive status
- **Quotations** — auto-numbered (`QTN-YYYYMM-NNNN`), multi line-item builder with live totals,
  discount/tax, notes, terms, expiry, status workflow
- **PDF** — professional A4 quotation with company logo, breakdown, terms, signature block and a
  **native QR code** linking to a public verification page
- **Public verification** — `/verify/{token}` confirms a quotation's authenticity (QR target)
- **Reports** — daily, monthly and employee-performance reports with charts + PDF export
- **Dashboard** — role-aware stat cards, trend & status charts, recent quotations
- **Settings** — admin-editable company info, logo upload, default terms, tax rate, number prefix
- **Audit** — activity log + login activity viewers
- **UI** — responsive Bootstrap 5.3 admin layout with **dark/light mode**

## Tech stack

| Layer    | Choice                                            |
|----------|---------------------------------------------------|
| Language | PHP 8.2 (no framework)                             |
| DB       | MySQL 8 / MariaDB 10.4+ (PDO, prepared statements) |
| UI       | Bootstrap 5.3, Bootstrap Icons, Chart.js          |
| PDF      | TCPDF (vendored, no Composer) + native QR         |

## Project structure

```
public/        Document root — front controller, .htaccess, assets
app/Core/      Framework: App, Router, Controller, Model, Auth, Session, Csrf, Validator, View ...
app/Controllers/  Auth, Dashboard, User, Customer, Plan, Quotation, Report, Setting, ActivityLog, Verify
app/Models/    User, Role, Customer, Plan, Quotation, QuotationItem, Setting, ActivityLog, LoginActivity
app/Services/  PdfService, QuotationNumberService, ReportService, UploadService
app/Middleware/  AuthMiddleware, RoleMiddleware
app/Views/     Bootstrap views + layouts/partials
config/        config.php, database.php (env-overridable)
routes/web.php Route table
libs/tcpdf/    Vendored TCPDF (installed by scripts/install_tcpdf.ps1)
database/      database.sql (schema + seed)
scripts/       install_tcpdf.ps1
```

## Quick start

See **[INSTALL.md](INSTALL.md)** for local XAMPP setup and **[DEPLOY.md](DEPLOY.md)** for HestiaCP.

```bash
# 1. Vendor TCPDF (no Composer needed)
powershell -ExecutionPolicy Bypass -File scripts/install_tcpdf.ps1

# 2. Create the database + seed
mysql -u root < database/database.sql

# 3. Serve
php -S localhost:8000 -t public
# open http://localhost:8000
```

## Default logins

> **Change these immediately in production.**

| Role      | Email                 | Password       |
|-----------|-----------------------|----------------|
| Admin     | admin@qms.local       | `Admin@123`    |
| Manager   | manager@qms.local     | `Manager@123`  |
| Executive | executive@qms.local   | `Executive@123`|

## Security highlights

- PDO prepared statements everywhere (base `Model` enforces it)
- CSRF synchroniser tokens on every state-changing form
- Output escaping via `e()` (XSS prevention)
- Server-side validation (`Validator`) including server-recomputed money totals
- Hardened sessions (HttpOnly, SameSite, Secure over HTTPS, id regeneration, idle/absolute timeout)
- Secure uploads (real MIME + extension + size checks, randomized names, scripts denied in uploads/)
- Security headers (CSP, X-Frame-Options, X-Content-Type-Options, Referrer-Policy)
