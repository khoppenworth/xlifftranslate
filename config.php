<?php
return [
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

  // --- Auth & Roles ---
  'login_userpass' => '',
  'users' => [
    'admin' => ['pass' => 'admin123', 'role' => 'admin'],
    'editor' => ['pass' => 'editor123', 'role' => 'editor'],
    'viewer' => ['pass' => 'viewer123', 'role' => 'viewer'],
  ],
  'passwords_are_bcrypt' => false,
  'enable_oidc' => false,
  'oidc_username_header' => 'HTTP_X_REMOTE_USER',
  'oidc_role_map' => [],

  // --- Rate limiting (per IP) ---
  'rate_limit' => [
    'translate_per_minute' => 60,
    'burst' => 30,
    'storage_dir' => sys_get_temp_dir(),
  ],

  // Google Sheets (placeholders; optional wiring in app.js)
  'google_sheets_id' => '',
  'google_sheets_range' => 'Sheet1!A:C',
  'google_sa_email' => '',
  'google_sa_private_key' => '',
];
