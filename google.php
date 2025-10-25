<?php
require_once __DIR__ . '/auth.php';

function gcfg(){ static $c; if(!$c){ $c = cfg(); } return $c; }

function google_jwt_access_token(): ?string {
  $c = gcfg();
  $sa_email = trim((string)($c['google_sa_email'] ?? ''));
  $sa_key = (string)($c['google_sa_private_key'] ?? '');
  if ($sa_email === '' || $sa_key === '') return null;
  $header = ['alg'=>'RS256','typ'=>'JWT'];
  $now = time();
  $claim = [
    'iss' => $sa_email,
    'scope' => 'https://www.googleapis.com/auth/spreadsheets',
    'aud' => 'https://oauth2.googleapis.com/token',
    'exp' => $now + 3600,
    'iat' => $now
  ];
  $enc = function($x){ return rtrim(strtr(base64_encode(json_encode($x)), '+/', '-_'), '='); };
  $seg1 = $enc($header);
  $seg2 = $enc($claim);
  $signing_input = $seg1.'.'.$seg2;
  $key = openssl_pkey_get_private($sa_key);
  if (!$key) return null;
  $sig = ''; openssl_sign($signing_input, $sig, $key, 'sha256WithRSAEncryption');
  $seg3 = rtrim(strtr(base64_encode($sig), '+/', '-_'), '=');
  $jwt = $seg1.'.'.$seg2.'.'.$seg3;

  // exchange for access token
  $post = http_build_query(['grant_type'=>'urn:ietf:params:oauth:grant-type:jwt-bearer','assertion'=>$jwt]);
  [$http,$resp,$err] = http_json('https://oauth2.googleapis.com/token','POST',['Content-Type: application/x-www-form-urlencoded'],$post);
  if ($resp === false || $http >= 400) return null;
  $data = json_decode($resp, true);
  return $data['access_token'] ?? null;
}

function http_json($url, $method='GET', $headers=[], $body=null) {
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_CUSTOMREQUEST => $method,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_POSTFIELDS => $body,
    CURLOPT_TIMEOUT => 60,
  ]);
  $resp = curl_exec($ch);
  $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $err  = curl_error($ch);
  curl_close($ch);
  return [$http, $resp, $err];
}

function sheets_values_get(string $range): ?array {
  $c = gcfg(); $sid = trim((string)($c['google_sheets_id'] ?? ''));
  if ($sid === '') return null;
  $token = google_jwt_access_token(); if (!$token) return null;
  $url = "https://sheets.googleapis.com/v4/spreadsheets/".rawurlencode($sid)."/values/".rawurlencode($range);
  [$http,$resp,$err] = http_json($url,'GET',['Authorization: Bearer '.$token]);
  if ($resp === false || $http >= 400) return null;
  $data = json_decode($resp,true);
  return $data['values'] ?? [];
}

function sheets_values_update(string $range, array $values): bool {
  $c = gcfg(); $sid = trim((string)($c['google_sheets_id'] ?? ''));
  if ($sid === '') return false;
  $token = google_jwt_access_token(); if (!$token) return false;
  $url = "https://sheets.googleapis.com/v4/spreadsheets/".rawurlencode($sid)."/values/".rawurlencode($range)."?" . http_build_query([
    'valueInputOption' => 'RAW'
  ]);
  $payload = json_encode(['range'=>$range,'majorDimension'=>'ROWS','values'=>$values], JSON_UNESCAPED_UNICODE);
  [$http,$resp,$err] = http_json($url,'PUT',['Authorization: Bearer '.$token,'Content-Type: application/json'], $payload);
  return $resp !== false && $http < 400;
}
