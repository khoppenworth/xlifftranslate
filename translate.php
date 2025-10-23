<?php
session_start();
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error'=>'POST only']); exit; }

$payload = json_decode(file_get_contents('php://input'), true);
$texts = $payload['texts'] ?? [];
$source = trim((string)($payload['source'] ?? ''));
$target = trim((string)($payload['target'] ?? ''));
$csrf   = $payload['csrf'] ?? '';
if (!hash_equals($_SESSION['csrf'] ?? '', $csrf)) { http_response_code(400); echo json_encode(['error'=>'Bad CSRF']); exit; }
if (!$target) { http_response_code(400); echo json_encode(['error'=>'Missing target language']); exit; }
if (!$texts)  { echo json_encode(['translations'=>[]]); exit; }

$cfg = require __DIR__ . '/config.php';
$translator = strtolower(trim((string)($cfg['translator'] ?? 'libre')));
$maxChars   = (int)($cfg['max_chars_per_request'] ?? 25000);

$total = 0; foreach ($texts as $t) { $total += mb_strlen($t, 'UTF-8'); }
if ($total > $maxChars) { http_response_code(400); echo json_encode(['error'=>"Request too large ($total chars). Try smaller batches."]); exit; }

function http_json($url, $method='POST', $headers=[], $body=null) {
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_CUSTOMREQUEST => $method,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_POSTFIELDS => $body,
    CURLOPT_TIMEOUT => 45,
  ]);
  $resp = curl_exec($ch);
  $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $err  = curl_error($ch);
  curl_close($ch);
  return [$http, $resp, $err];
}

$translations = [];

switch ($translator) {
  case 'libre': {
    $endpoint = rtrim((string)($cfg['libre_endpoint'] ?? 'http://localhost:5000'), '/');
    $apiKey   = trim((string)($cfg['libre_api_key'] ?? ''));
    foreach ($texts as $q) {
      $payload = [
        'q' => $q,
        'source' => $source ?: 'auto',
        'target' => $target,
        'format' => 'html',
      ];
      if ($apiKey !== '') $payload['api_key'] = $apiKey;
      [$http, $resp, $err] = http_json("$endpoint/translate", 'POST',
        ['Content-Type: application/json'],
        json_encode($payload, JSON_UNESCAPED_UNICODE)
      );
      if ($resp === false || $http >= 400) { http_response_code(500); echo json_encode(['error'=>'LibreTranslate error','detail'=>$err ?: $resp]); exit; }
      $data = json_decode($resp, true);
      $translations[] = (string)($data['translatedText'] ?? '');
    }
    break;
  }
  default: {
    http_response_code(400); echo json_encode(['error'=>'Unknown translator']);
    exit;
  }
}

echo json_encode(['translations'=>$translations,'mock'=>false]);
