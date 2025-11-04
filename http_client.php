<?php
function http_json(string $url, string $method = 'POST', array $headers = [], $body = null, int $timeout = 60): array {
  $method = strtoupper($method ?: 'GET');
  $timeout = max(1, $timeout);
  if (function_exists('curl_init')) {
    $ch = curl_init($url);
    $opts = [
      CURLOPT_CUSTOMREQUEST => $method,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_HTTPHEADER => $headers,
      CURLOPT_TIMEOUT => $timeout,
      CURLOPT_FOLLOWLOCATION => false,
    ];
    if ($body !== null) {
      $opts[CURLOPT_POSTFIELDS] = $body;
    }
    curl_setopt_array($ch, $opts);
    $resp = curl_exec($ch);
    $http = (int) (curl_getinfo($ch, CURLINFO_HTTP_CODE) ?: 0);
    $err = curl_error($ch) ?: null;
    curl_close($ch);
    return [$http, $resp, $err];
  }

  $context = [
    'http' => [
      'method' => $method,
      'timeout' => $timeout,
      'ignore_errors' => true,
    ],
  ];
  if (!empty($headers)) {
    $context['http']['header'] = implode("\r\n", $headers);
  }
  if ($body !== null) {
    $context['http']['content'] = is_string($body) ? $body : (string) $body;
  }
  $contextResource = stream_context_create($context);
  $resp = @file_get_contents($url, false, $contextResource);
  $err = null;
  if ($resp === false) {
    $last = error_get_last();
    $err = $last['message'] ?? 'stream error';
  }
  $http = 0;
  global $http_response_header;
  $httpHeader = $http_response_header ?? [];
  if (!empty($httpHeader[0]) && preg_match('~^HTTP/\d+(?:\.\d+)?\s+(\d+)~', $httpHeader[0], $m)) {
    $http = (int) $m[1];
  }
  return [$http, $resp, $err];
}

