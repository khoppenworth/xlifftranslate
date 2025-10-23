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
if (!$texts || !$target) { http_response_code(400); echo json_encode(['error'=>'Missing texts/target']); exit; }

$cfgFile = __DIR__ . '/config.php';
if (!file_exists($cfgFile)) { http_response_code(500); echo json_encode(['error'=>'Missing config.php']); exit; }
$cfg = require $cfgFile;
$apiKey = trim((string)($cfg['google_api_key'] ?? ''));
$maxChars = (int)($cfg['max_chars_per_request'] ?? 25000);

if ($apiKey === '') {
    // Fallback mock (useful for local demo without key)
    $out = [];
    foreach ($texts as $t) { $out[] = "[MOCK-" . ($target ?: 'xx') . "] " . $t; }
    echo json_encode(['translations' => $out, 'mock' => true]);
    exit;
}

// Enforce char limit
$total = 0;
foreach ($texts as $t) { $total += mb_strlen($t, 'UTF-8'); }
if ($total > $maxChars) { http_response_code(400); echo json_encode(['error'=>"Request too large ($total chars). Try smaller batches."]); exit; }

// Google Translate API v2 REST
// POST https://translation.googleapis.com/language/translate/v2?key=API_KEY
// body: q (multi), source (optional), target (required), format=html
$url = "https://translation.googleapis.com/language/translate/v2?key=" . urlencode($apiKey);
$post = [
    'target' => $target,
    'format' => 'html',
];
if ($source) $post['source'] = $source;
foreach ($texts as $t) { $post['q'][] = $t; }

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode($post),
    CURLOPT_TIMEOUT => 30,
]);
$resp = curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err  = curl_error($ch);
curl_close($ch);

if ($resp === false || $http >= 400) {
    http_response_code(500);
    echo json_encode(['error'=>'Translate API error', 'detail'=>$err ?: $resp]);
    exit;
}

$data = json_decode($resp, true);
$translations = [];
foreach (($data['data']['translations'] ?? []) as $row) {
    $translations[] = $row['translatedText'] ?? '';
}

echo json_encode(['translations'=>$translations, 'mock'=>false]);
