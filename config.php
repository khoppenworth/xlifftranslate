<?php
return [
  'app_name' => 'XLIFF Studio',
  'translator' => 'libre', // libre | deepl | azure | google | mymemory | mock
  'libre_endpoint' => 'http://localhost:5000',
  'libre_api_key' => '',
  'libre_max_chars_per_call' => 8000,
  'libre_delay_ms' => 0,
  'deepl_endpoint' => 'https://api-free.deepl.com/v2/translate',
  'deepl_api_key' => '',
  'azure_endpoint' => 'https://api.cognitive.microsofttranslator.com/translate?api-version=3.0',
  'azure_key' => '',
  'azure_region' => '',
  'google_api_key' => '',
  'rate_limit' => ['translate_per_minute'=>60, 'burst'=>30],
  'max_chars_per_request' => 25000,
];