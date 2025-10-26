<?php
return [
  // Default translator
  'translator' => 'libre',

  // LibreTranslate
  'libre_endpoint' => 'http://localhost:5000',
  'libre_api_key' => '',
  'libre_max_chars_per_call' => 8000,
  'libre_delay_ms' => 120,

  // Other providers (optional)
  'google_api_key' => '',
  'deepl_api_key' => '',
  'deepl_endpoint' => 'https://api-free.deepl.com/v2/translate',
  'azure_key' => '',
  'azure_region' => '',
  'azure_endpoint' => 'https://api.cognitive.microsofttranslator.com/translate?api-version=3.0',

  // General
  'max_chars_per_request' => 25000,
  'max_upload_mb' => 10,

  // --- Auth ---
  // Use either a single userpass OR the users array below
  'login_userpass' => '', // e.g. 'admin:admin123' (leave empty to use 'users')
  // Role-based users: ['username' => ['pass'=>'plaintext-or-hash', 'role'=>'admin|editor|viewer']]
  'users' => [
    'admin' => ['pass' => 'admin123', 'role' => 'admin'],
    'editor' => ['pass' => 'editor123', 'role' => 'editor'],
  ],
  // If you want to use password hashes, set 'passwords_are_bcrypt' => true and provide password hashes (password_hash).
  'passwords_are_bcrypt' => false,

  // Optional OIDC/SSO (fronted by reverse proxy). If enabled, form login is bypassed.
  // Reverse proxy must set a header with the username (e.g., X-Remote-User).
  'enable_oidc' => false,
  'oidc_username_header' => 'HTTP_X_REMOTE_USER',
  // Map SSO usernames to roles (default role = 'editor' if not mapped).
  'oidc_role_map' => [
    // 'alice@company.com' => 'admin',
  ],

  // --- Rate limiting (per IP) ---
  'rate_limit' => [
    'translate_per_minute' => 60, // allow 60 translate requests per minute per IP
    'burst' => 30,                // extra initial burst
    'storage_dir' => sys_get_temp_dir(), // where to store counters
  ],

  // Google Sheets (service account)
  'google_sheets_id' => '',
  'google_sheets_range' => 'Sheet1!A:C',    // 3 columns: id, source, target
  'google_sa_email' => '',
  'google_sa_private_key' => '',
];
