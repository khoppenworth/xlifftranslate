# XLIFF Table Translate — v5.3

This build performs a **full logic sweep** and hardens functionality:
- ✅ Only **source cells** are ever sent to the translator (client-side).
- ✅ **1:1 mapping** guaranteed for LibreTranslate (server-side) even with chunking.
- ✅ Row-count checks: client verifies response length before writing back.
- ✅ Skip empty rows gracefully and preserve alignment.
- ✅ Reverted language pickers to **free-text inputs** (`sourceLang`, `targetLang`).
- ✅ CSV import/export + XLIFF export; roles; CSRF; rate limiting; CSP; health check.

## Quick start
1. Put files in your Apache/PHP (LAMP) docroot.
2. Ensure PHP extensions: `php-xml`, `php-curl`, `php-mbstring`.
3. Set users in `config.php` (viewer/editor/admin) or a single `login_userpass`.
4. Start LibreTranslate (Docker or systemd) and set `libre_endpoint` if needed.
5. Visit `/login.php`, sign in, upload an XLIFF, and translate.

## LibreTranslate via Docker
```bash
docker run -d --name libretranslate -p 5000:5000 libretranslate/libretranslate:latest
# optional languages env: -e LT_LOAD_ONLY=en,fr,es,de
```

## Security reminders
- This app uses CSRF tokens and sets secure cookies.
- `.htaccess` applies restrictive security headers including CSP.
- Rate limit for `translate.php` is configurable in `config.php`.

