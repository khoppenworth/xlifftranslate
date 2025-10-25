Here’s a complete **README.md** you can drop into the project root.

---

# XLIFF Table Translate (LAMP)

A lightweight web app to load **XLIFF 1.2 / 2.0**, view as a table, bulk edit, translate via multiple providers, and export a new XLIFF. Built for standard **LAMP** (Linux + Apache/Nginx + MySQL* + PHP; *no DB required*).

## Features

* Upload XLIFF → table view with **ID / Source / Target**
* **Bulk select** (shift-click, drag-to-select), **copy/paste**, CSV import/export
* **Auto-translate** (LibreTranslate, DeepL, Azure, Google, MyMemory, mock)
* LibreTranslate **chunking + retry/backoff** to avoid limits
* **Auto-detect source language** on upload (Libre `/detect`)
* **Per-request provider switcher**
* **Google Sheets sync** (push/pull selected or all rows) using service account JWT (no Composer/SDK)
* **Autosave**, **Undo last bulk paste**, **filter/search**, **pagination**
* **Dark mode**, **drag-to-resize columns**
* Hotkeys: `Ctrl/Cmd+A`, `Ctrl/Cmd+C`, `Ctrl/Cmd+V`, `Alt+T`, `Alt+Enter`

---

## 1) Requirements

* **PHP 8.0+** with extensions:

  * `xml` (DOMDocument), `curl`, `mbstring`, `openssl`, `json`
* **Web server**: Apache 2.4+ or Nginx + PHP-FPM
* (Optional) **Docker** for LibreTranslate
* Outbound HTTPS allowed (if using external APIs or Google Sheets)

### Install PHP extensions (Ubuntu/Debian)

```bash
sudo apt update
sudo apt install -y apache2 libapache2-mod-php \
  php php-xml php-curl php-mbstring php-openssl php-cli
sudo systemctl restart apache2
```

### Install PHP extensions (RHEL/CentOS/Alma/Rocky)

```bash
sudo dnf install -y httpd php php-xml php-curl php-mbstring php-openssl
sudo systemctl enable --now httpd php-fpm
```

> If you see `Parse error: Class "DOMDocument" not found`, install **php-xml** and restart the web server.

---

## 2) Deploy the App

1. Copy files to your web root, e.g. `/var/www/xliff`:

   ```bash
   sudo mkdir -p /var/www/xliff
   sudo rsync -av ./ /var/www/xliff/
   sudo chown -R www-data:www-data /var/www/xliff   # use 'apache' on RHEL-based
   ```
2. Verify permissions for uploads (no persistent storage required beyond session).
3. Ensure `.htaccess` is honored (Apache):

   ```bash
   sudo a2enmod rewrite
   # In your VirtualHost, set: AllowOverride All
   sudo systemctl restart apache2
   ```

### Apache VirtualHost example

```apache
<VirtualHost *:80>
  ServerName xliff.local
  DocumentRoot /var/www/xliff

  <Directory /var/www/xliff>
    AllowOverride All
    Require all granted
  </Directory>

  ErrorLog ${APACHE_LOG_DIR}/xliff_error.log
  CustomLog ${APACHE_LOG_DIR}/xliff_access.log combined
</VirtualHost>
```

### Nginx + PHP-FPM example

```nginx
server {
  listen 80;
  server_name xliff.local;
  root /var/www/xliff;
  index index.php index.html;

  location / {
    try_files $uri $uri/ /index.php?$args;
  }

  location ~ \.php$ {
    include fastcgi_params;
    fastcgi_pass unix:/run/php/php-fpm.sock;  # adjust for your distro
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
  }
}
```

> **SELinux** (RHEL-based): if using Docker/Sheets/HTTP calls, allow outbound:
>
> ```bash
> sudo setsebool -P httpd_can_network_connect 1
> ```

---

## 3) Configure `config.php`

Open `config.php` and review:

```php
return [
  'translator' => 'libre',        // default provider

  // LibreTranslate
  'libre_endpoint' => 'http://localhost:5000',
  'libre_api_key' => '',
  'libre_max_chars_per_call' => 8000,
  'libre_delay_ms' => 120,

  // Other providers (optional)
  'google_api_key' => '',
  'deepl_api_key'  => '',
  'deepl_endpoint' => 'https://api-free.deepl.com/v2/translate',
  'azure_key'      => '',
  'azure_region'   => '',
  'azure_endpoint' => 'https://api.cognitive.microsofttranslator.com/translate?api-version=3.0',

  // General
  'max_chars_per_request' => 25000,
  'login_userpass' => 'admin:admin123',
  'max_upload_mb'  => 10,

  // Google Sheets (service account)
  'google_sheets_id'      => '',
  'google_sheets_range'   => 'Sheet1!A:C',   // id, source, target
  'google_sa_email'       => '',
  'google_sa_private_key' => '',             // PEM block
];
```

* **Chunking & throttling** (Libre limits): tune

  * `libre_max_chars_per_call` (per-segment split, soft at sentence ends)
  * `libre_delay_ms` (pacing)
  * `max_chars_per_request` (server-side batch split & merge)
* **Auth**: change `login_userpass` immediately for public deployments.
* **Upload size**: adjust PHP if needed (see troubleshooting).

---

## 4) Install LibreTranslate via Docker

You can run LibreTranslate locally to avoid external rate limits.

### Quick start (included `docker-compose.yml`)

From the project directory:

```bash
docker compose up -d
```

This starts LibreTranslate on **[http://localhost:5000](http://localhost:5000)** with a common set of languages preloaded.

### Customize languages & performance

In `docker-compose.yml`:

```yaml
services:
  libretranslate:
    image: libretranslate/libretranslate:latest
    ports: ["5000:5000"]
    environment:
      - LT_LOAD_ONLY=en,fr,es,de,pt,it,ar,hi,sw,am,zh  # pick what you need
      - LT_DISABLE_FILES_UPLOADS=true
    restart: unless-stopped
```

Other useful envs (see LibreTranslate docs):

* `LT_CACHE_DIR=/data/cache`
* `LT_THREADS=4`
* `LT_CHAR_LIMIT=10000` (per request)
* `LT_REQ_LIMIT=...` (rate limiting)

Then set in `config.php`:

```php
'translator' => 'libre',
'libre_endpoint' => 'http://localhost:5000',
```

---

## 5) Google Sheets Integration (Service Account, no Composer)

1. In **Google Cloud Console**:

   * Enable **Google Sheets API**
   * Create a **Service Account**; generate a **JSON key**
2. Open your Google Sheet → **Share** with the service account email (Editor).
3. In `config.php`:

   * `google_sheets_id` → the spreadsheet ID from its URL
   * `google_sheets_range` → e.g. `Sheet1!A:C` (ID, Source, Target)
   * `google_sa_email` → service account email
   * `google_sa_private_key` → paste the **PEM** block:

     ```php
     'google_sa_private_key' => "-----BEGIN PRIVATE KEY-----\n...lines...\n-----END PRIVATE KEY-----",
     ```

     Make sure the newline characters are preserved (either as real newlines in the PHP string, or `\n` escaped correctly).

### Using Sheets in the UI

* **Push → Google Sheet**: sends selected rows (or all if none selected) as `id,source,target` to the configured range (overwrites that range).
* **Pull from Google Sheet**: reads the same range and updates **target** by matching on **ID**. Unknown IDs are ignored.

---

## 6) Usage

1. Visit the app and log in (default `admin:admin123`, change in `config.php`).
2. **Upload** `.xlf/.xliff/.xml` — supports 1.2 and 2.0.
3. Review/edit in the table:

   * **Select** rows: click, **shift-click** range, or **drag** across checkboxes
   * **Copy**/**Paste**: toolbar buttons or `Ctrl/Cmd+C` & `Ctrl/Cmd+V`
   * **Paste → target**: multi-line paste maps line-by-line; single-line fills all
   * **Undo last bulk paste**
   * Filter/search, pagination, dark mode
   * **Resize** columns by dragging header edges
4. **Translate**

   * Set **Target** language (e.g., `fr` or `fr-FR`)
   * Pick **Provider** (or leave default)
   * Click **Translate ALL** or use **row translate**:

     * **Alt+T** → translate current row
     * **Alt+Enter** → translate current row and jump to next
5. **Save** (autosave runs after edits; the Save button forces it)
6. **Export** → Download XLIFF with your new target entries.

---

## 7) Health Check

Open `/health.php` to verify PHP environment:

```
PHP 8.2.x
DOM extension: YES
SimpleXML extension: YES
cURL extension: YES
mbstring extension: YES
```

---

## 8) Security & Hardening

* Change `login_userpass` to a strong password.
* Serve over **HTTPS** (Let’s Encrypt, reverse proxy).
* If public, consider IP allowlists / basic auth at web server layer.
* Keep service account key **outside** the web root or inject via env + include it securely.
* Apache: disable directory listing (`Options -Indexes`, included).
* Nginx/Apache: set reasonable body limits (see Troubleshooting).

---

## 9) Troubleshooting

### “Class `DOMDocument` not found”

Install `php-xml` and restart the web server.

### “cURL not found” / API calls fail

Install `php-curl`. Ensure outbound HTTPS allowed (firewall/proxy).

### “413 Payload Too Large” on upload

Increase limits:

**PHP** (`/etc/php/*/apache2/php.ini` or FPM):

```ini
upload_max_filesize = 20M
post_max_size = 20M
```

**Apache**:

```apache
LimitRequestBody 0
```

**Nginx**:

```nginx
client_max_body_size 20m;
```

Restart services.

### LibreTranslate rate or size errors

* Increase `libre_delay_ms`, lower `libre_max_chars_per_call`
* Reduce `max_chars_per_request`
* Ensure LibreTranslate `LT_CHAR_LIMIT` and server resources are adequate

### Google Sheets push/pull fails

* Confirm the spreadsheet is shared with the **service account email** (Editor)
* Verify `google_sheets_id` and `google_sheets_range`
* Ensure `google_sa_private_key` is properly formatted (PEM with newlines)

### SELinux blocked network calls (RHEL-based)

```
sudo setsebool -P httpd_can_network_connect 1
```

---

## 10) Upgrading

1. Back up your current folder.
2. Replace files with the new release.
3. Keep your existing `config.php` (or merge changes).
4. Clear browser cache if assets changed.

---

## 11) Keyboard Shortcuts (Quick Reference)

* **Select all rows**: `Ctrl/Cmd + A` (when table focused)
* **Copy targets**: `Ctrl/Cmd + C`
* **Paste into targets**: `Ctrl/Cmd + V`
* **Row translate**: `Alt + T`
* **Row translate + next**: `Alt + Enter`

---

## 12) Notes on Providers

* **LibreTranslate**: self-host for privacy and cost control. The app splits long texts, retries on 429/5xx, and paces calls.
* **DeepL/Azure/Google**: requires valid API keys; if keys are missing, the app falls back gracefully.
* **MyMemory**: free tier, best-effort; subject to rate/quality variability.
* **Mock**: local testing (prefixes text with `[MOCK-<lang>]`).

---

## 13) License / Credits

* XLIFF parsing via PHP DOM (no external libs).
* LibreTranslate is open source (see its license).
* This app is MIT-licensed unless you specify otherwise.

---

Happy translating! If you want, I can also include a **systemd unit** for LibreTranslate, production **Nginx HTTPS** sample, or a script to **rotate the Sheets key** safely.
