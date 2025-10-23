# XLIFF Table Translate (LAMP)

A tiny PHP app to:
1) Upload an XLIFF file
2) Show its `trans-unit` entries in a table
3) Auto‑translate targets with **Google Cloud Translation API**
4) Edit targets inline
5) Export a new XLIFF with your translations

## Requirements
- PHP 8.0+ with DOM extension
- Apache/Nginx (standard LAMP)
- Internet access if using Google Translate
- A Google Cloud Translation API key (optional for mock mode)

## Setup
1. Copy files to your server, e.g. `/var/www/html/xliff/`
2. Duplicate `config.sample.php` to `config.php` and paste your API key.
3. Visit `http://your-server/xliff/index.php`

If you **don’t** set an API key, the app still works with a MOCK translator (it prefixes text with `[MOCK-fr] ...` etc.)

## Notes
- Supports XLIFF 1.2 fully for common cases, and basic 2.0 (unit/segment).
- Source inline XML tags are preserved. Targets accept text or inline XML.
- Export creates a fresh XLIFF and sets `target-language` if you filled a target language.

## Security tips
- Restrict upload size in PHP (`upload_max_filesize`, `post_max_size`).
- Consider adding auth if you deploy this publicly.
