# XLIFF Studio v7.1

A LAMP app to upload, view, translate, and export XLIFF (1.2 and 2.0).
This build **restores login** and a **richer Material-style UI** while keeping all functional hardening from v7.0.

## Features
- Upload & parse XLIFF (1.2 / 2.0), grid view
- **Login + logout** (configurable users)
- Provider-aware translation (Libre/DeepL/Azure/Google/MyMemory) with **source-only** enforcement
- Strips inline XLIFF shells (`<g>…</g>`, `<x/>`) from UI display to avoid leaking IDs
- **Quick target dropdown**, **Select all / Clear** selections
- **Translate row / selected / ALL**
- **Auto-save** targets to session after translation and **before exports**
- **Export CSV** and **Download XLIFF** (reflect on-screen edits)
- Robust JSON APIs with error buffering (no more “bad JSON from server”)
- Simple session **rate limiting**
- **Import CSV** back into session targets

## Requirements
- PHP 8+ with extensions: `php-xml`, `php-curl`, `php-mbstring`, `php-json`, `php-session`
- Apache or Nginx with PHP-FPM
- Optional: LibreTranslate (local) or other provider keys (DeepL/Azure/Google)

### Ubuntu quick install
```bash
sudo apt-get update
sudo apt-get install -y apache2 libapache2-mod-php php php-xml php-curl php-mbstring php-json
sudo a2enmod headers rewrite
sudo systemctl restart apache2
```

## Setup
1. Copy the folder to your web root, e.g. `/var/www/html/xliff-studio`.
2. Ensure the app can write sessions (default PHP session dir is fine).
3. Open `config.php` and set:
   - Default provider (`translator`)
   - Provider endpoints and keys
   - **Users** (username/password/role)
4. Visit `/xliff-studio/login.php`, sign in (default: `admin` / `admin123`).

## LibreTranslate (Docker) — local free alternative
```bash
docker run -d --restart=always -p 5000:5000 --name libretranslate libretranslate/libretranslate
```
Set `libre_endpoint` in `config.php` (default is `http://localhost:5000`).

## Roles
- `viewer`: can view and export
- `editor`: can translate and save

## Notes
- **Source-only translation** means the server always uses the uploaded XLIFF’s `<source>` content for each row (by id), ignoring any injected text.
- **Exports** reflect current textarea values; the app auto-saves before export to ensure fidelity.

## Troubleshooting
- **DOMDocument not found** → install `php-xml`.
- **Bad JSON** → with this build you’ll get clean JSON errors; check browser snackbar and PHP error log.
- **cURL missing** → install `php-curl`.
- **CSRF** errors → reload the page (new token), ensure cookies are enabled.
