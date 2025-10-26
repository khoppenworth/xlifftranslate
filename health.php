<?php
header('Content-Type: text/plain; charset=utf-8');
printf("PHP %s\n", PHP_VERSION);
printf("DOM extension: %s\n", class_exists('DOMDocument') ? 'YES' : 'NO');
printf("SimpleXML extension: %s\n", class_exists('SimpleXMLElement') ? 'YES' : 'NO');
printf("cURL extension: %s\n", function_exists('curl_init') ? 'YES' : 'NO');
printf("mbstring extension: %s\n", function_exists('mb_strlen') ? 'YES' : 'NO');
printf("User: %s (%s)\n", $_SESSION['user'] ?? '-', $_SESSION['role'] ?? '-');
