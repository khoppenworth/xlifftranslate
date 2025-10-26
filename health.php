<?php
require_once __DIR__ . '/auth.php';
header('Content-Type: text/plain; charset=utf-8');
printf("App: %s\n", cfg()['app_name'] ?? 'XLIFF Studio');
printf("PHP %s\n", PHP_VERSION);
printf("DOM extension: %s\n", class_exists('DOMDocument') ? 'YES' : 'NO');
printf("cURL extension: %s\n", function_exists('curl_init') ? 'YES' : 'NO');
printf("mbstring extension: %s\n", function_exists('mb_strlen') ? 'YES' : 'NO');
printf("User: %s (%s)\n", $_SESSION['user'] ?? '-', $_SESSION['role'] ?? '-');
