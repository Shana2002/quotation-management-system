# Deployment Guide — HestiaCP

This guide deploys QMS to a HestiaCP-managed server (Apache, or Nginx-proxy-Apache, with PHP 8.2).

## 1. Create the web domain

1. HestiaCP → **WEB** → **Add Web Domain** → enter your domain (e.g. `qms.example.com`).
2. After creation, open the domain's **Edit** page and enable:
   - **SSL Support** + **Let's Encrypt** (issue a free certificate).
   - **PHP version 8.2** (Backend Template / FastCGI), if version selection is available.

## 2. Point the document root at `public/`

QMS keeps application code outside the web root. The domain's docroot must be the `public/` folder.

**Option A — Web Template (recommended):** Edit the web domain → **Proxy/Web Template** and choose a
template whose document root is `public_html/public`, **or** create a custom template. Then upload
the project so the structure is:

```
~/web/qms.example.com/public_html/         <- project root (app/, config/, libs/ ...)
~/web/qms.example.com/public_html/public/  <- the actual document root
```

**Option B — symlink:** Upload the project to `~/web/<domain>/qms/` and symlink the docroot:

```bash
ln -s ~/web/<domain>/qms/public ~/web/<domain>/public_html
```

**Option C — fallback (no docroot change):** Upload the whole project into `public_html/`. The
included root `.htaccess` forwards requests into `public/` and blocks direct access to
`app/`, `config/`, `database/`, `libs/`, `routes/`, `storage/`, `scripts/`. This works but Option A/B
is cleaner and safer.

## 3. Upload the files

Use SFTP/SCP or HestiaCP File Manager. Include everything **except** local-only items.

Then vendor TCPDF on the server. If you have SSH:

```bash
cd ~/web/<domain>/public_html        # project root
# If PowerShell isn't available on the host, fetch TCPDF with curl instead:
curl -L -o /tmp/tcpdf.zip https://github.com/tecnickcom/TCPDF/archive/refs/tags/6.7.5.zip
unzip -q /tmp/tcpdf.zip -d /tmp/tcpdf && mv /tmp/tcpdf/TCPDF-6.7.5 libs/tcpdf
test -f libs/tcpdf/tcpdf.php && echo "TCPDF OK"
```

> No SSH? Run `scripts\install_tcpdf.ps1` locally first, then upload the resulting
> `libs/tcpdf/` directory along with the project.

## 4. Create the database

1. HestiaCP → **DB** → **Add Database**. Note the generated DB name, user and password.
2. Import the schema via **phpMyAdmin** (linked from HestiaCP) → *Import* → `database/database.sql`.
   - The file begins with `CREATE DATABASE IF NOT EXISTS qms` and `USE qms`. If your HestiaCP
     database has a different name, either create a `qms` database too, or remove the
     `CREATE DATABASE`/`USE` lines and import into the HestiaCP-provided database.

## 5. Configure the app

Set credentials via environment variables (preferred) or `config/database.local.php`.

For Apache + FastCGI you can set env vars in the domain's PHP settings, or create
`config/database.local.php` (git-ignored):

```php
<?php
return [
    'host'     => 'localhost',
    'database' => 'admin_qms',      // your HestiaCP DB name
    'username' => 'admin_qmsuser',  // your HestiaCP DB user
    'password' => 'SECRET',
];
```

Also set the public URL and production mode (via env or a small edit to `config/config.php`):

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://qms.example.com
APP_TIMEZONE=Asia/Colombo
```

In production, `APP_DEBUG=false` hides stack traces and logs errors instead.

## 6. File permissions

The web user must be able to write generated files and uploads:

```bash
cd ~/web/<domain>/public_html
chmod -R 775 storage public/assets/uploads
# Ensure ownership matches the domain user (HestiaCP usually handles this):
chown -R <domain-user>:<domain-user> .
```

Confirm `public/assets/uploads/.htaccess` is present (it denies script execution in uploads).

## 7. Web-server specifics

### Apache
The shipped `public/.htaccess` provides the front-controller rewrite and security headers —
ensure `mod_rewrite` and `mod_headers` are enabled (default on HestiaCP Apache).

### Nginx (or Nginx → PHP-FPM)
If the domain is served by Nginx directly, add a custom location so all requests hit the
front controller. In HestiaCP, add an Nginx custom template / `nginx.ssl.conf_*` snippet:

```nginx
location / {
    try_files $uri $uri/ /index.php?url=$uri&$args;
}

# Block direct access to application internals
location ~* ^/(app|config|database|libs|routes|storage|scripts)/ {
    deny all;
}
```

(Document root for this server block should be the `public/` directory.)

## 8. Harden & verify

1. **Change the seeded passwords** — sign in as admin and update all demo accounts (or delete them).
2. Visit **Settings** → set company name, address, contacts, upload logo, default terms, tax rate.
3. Create a customer → a plan → a quotation → **Download PDF** and **scan the QR** — it should open
   `https://qms.example.com/verify/{token}` and confirm the quotation.
4. Confirm HTTPS is enforced (Let's Encrypt) so secure session cookies are used.

## Optional — enable GD

QR codes do **not** require GD. Enable it only for the most robust PNG-alpha logo embedding:
HestiaCP → server packages / `php.ini` → ensure `extension=gd` is enabled, then restart PHP-FPM/Apache.

## Upgrades

- Keep `config/database.local.php`, `storage/`, and `public/assets/uploads/` when redeploying code.
- Re-run the TCPDF vendoring step only if `libs/tcpdf/` is not carried over.
