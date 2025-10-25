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

  // Limits / auth
  'max_chars_per_request' => 25000,
  'login_userpass' => 'admin:admin123',
  'max_upload_mb' => 10,

  // Google Sheets (service account, no Composer)
  // Create a service account in Google Cloud, enable Sheets API, share the sheet with the service account email.
  // Paste the PEM private key for the service account into 'google_sa_private_key' (BEGIN/END PRIVATE KEY)
  'google_sheets_id' => '',                 // The spreadsheet ID (from URL)
  'google_sheets_range' => 'Sheet1!A:C',    // 3 columns: id, source, target
  'google_sa_email' => '',                  // service-account@project.iam.gserviceaccount.com
  'google_sa_private_key' => '',            // -----BEGIN PRIVATE KEY-----\n...\n-----END PRIVATE KEY-----
];
