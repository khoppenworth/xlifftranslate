<?php
// Copy this file to config.php and fill in your Google Cloud Translation API key.
// Get a key from: https://console.cloud.google.com/apis/credentials
// Enable: "Cloud Translation API"
return [
    'google_api_key' => '', // e.g. 'AIzaSy...'
    // Hard cap to avoid accidental large bills. Change as needed.
    'max_chars_per_request' => 25000,
];
