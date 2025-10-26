# XLIFF Table Translate — v5.2 (hardened)

What’s added in v5.2:
- Content-Security-Policy (CSP) in `.htaccess`
- Per‑IP **rate limiting** for `translate.php` (token bucket)
- **Server‑side CSV import** (`import_csv.php`): update targets by ID safely
- **Role‑based auth** (viewer/editor/admin) + optional **OIDC/SSO** (header-based)
- **systemd** unit for LibreTranslate and a **TLS Nginx** reverse-proxy sample

## Configure roles and SSO
In `config.php`:
```php
'login_userpass' => '',  // use users[] instead
'users' => [
  'admin'  => ['pass' => 'admin123',  'role' => 'admin'],
  'editor' => ['pass' => 'editor123', 'role' => 'editor'],
  'viewer' => ['pass' => 'viewer123', 'role' => 'viewer'],
],
'passwords_are_bcrypt' => false,   // set true if you paste password_hash() outputs
'enable_oidc' => false,            // set true if a reverse proxy sets X-Remote-User
'oidc_username_header' => 'HTTP_X_REMOTE_USER',
'oidc_role_map' => ['alice@corp.com' => 'admin'],
```

- `viewer`: read & export only
- `editor`: everything except user/role management (there is no UI for roles; edit config)
- `admin`: same as editor (reserved for future admin features)

## Rate limiting
```php
'rate_limit' => [
  'translate_per_minute' => 60,
  'burst' => 30,
  'storage_dir' => sys_get_temp_dir(),
],
```
The translate endpoint will respond **429** when the bucket is empty.

## Server‑side CSV import
Endpoint: `import_csv.php` (POST, CSRF protected). Upload a CSV with header:
```
id,source,target
```
Only **id** and **target** are required; unknown IDs are skipped. Limit: 20,000 rows per import.

## CSP
The default CSP restricts assets to self and allows inline styles (for simplicity). If you self‑host fonts/scripts elsewhere, add them to `style-src` / `script-src`. Example:
```
Header set Content-Security-Policy "default-src 'self'; img-src 'self' data:; style-src 'self' https://fonts.googleapis.com 'unsafe-inline'; font-src 'self' https://fonts.gstatic.com; script-src 'self'; connect-src 'self'; frame-ancestors 'self'; base-uri 'self'; form-action 'self'"
```

## LibreTranslate as a systemd service
See `deploy/libretranslate.service` and run:
```bash
sudo useradd --system --no-create-home libretranslate || true
sudo cp deploy/libretranslate.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now libretranslate
```

## TLS reverse proxy (Nginx)
Use `deploy/nginx.tls.conf` as a starting point (with Let’s Encrypt paths). Point `proxy_pass` at your backend (Apache vhost or php-fpm upstream).

## Notes
- CSV **export**: `export_csv.php` (selected IDs or all when none selected) — outputs UTF‑8 BOM for Excel.
- CSV **import** complements the UI import button. Wire it by posting `formData` to `import_csv.php` if you want it from the UI.
- All state‑changing endpoints use CSRF and require at least `editor` role.
