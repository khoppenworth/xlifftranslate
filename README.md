# XLIFF Studio v6.0 (LAMP, Material UI)

A self-contained XLIFF editor/translator for LAMP with a Material-inspired UI.

## Highlights
- XLIFF **1.2 & 2.0** parsing/building (DOM; requires `php-xml`).
- Translate **only source column**; strict **1:1 mapping** (chunking-safe).
- Providers: LibreTranslate (default), DeepL, Azure, Google, MyMemory, Mock.
- CSV import/export; XLIFF export; session-based storage (no DB).
- Auth: roles (`viewer`, `editor`, `admin`) or single credential.
- Security: CSRF, rate limiting, hardened cookies, CSP headers.
- UI: Top app bar, side nav, cards, responsive table, snackbars, overlay progress.
- Shortcuts: `Alt+T` (row), `Alt+Enter` (row and next).

## Requirements
- Apache + PHP 8.x with `php-xml`, `php-curl`, `php-mbstring`.
- LibreTranslate running locally or accessible via network (default `http://localhost:5000`).

## Setup
1. Copy files into your Apache docroot (or a subfolder).
2. Ensure modules: `sudo apt-get install php-xml php-curl php-mbstring` and restart Apache.
3. Edit `config.php` (users, endpoints). Optional single login: `login_userpass="admin:secret"`.
4. Start LibreTranslate: `docker run -d -p 5000:5000 libretranslate/libretranslate:latest`
5. Visit `/login.php`, sign in, upload an XLIFF, translate rows/all, export.

## Notes
- All state is in PHP session; no database required.
- If you front with SSO, set `enable_oidc=true` and map roles via `oidc_role_map`.
- If you need more Material pizazz, add icons/assets locally and reference from `assets/`.
