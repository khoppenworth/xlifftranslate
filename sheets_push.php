<?php
session_start();
require_once __DIR__ . '/auth.php'; require_login();
require_once __DIR__ . '/google.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error'=>'POST only']); exit; }
if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) { http_response_code(400); echo json_encode(['error'=>'Bad CSRF']); exit; }

$ids = $_POST['ids'] ?? [];
$sources = $_POST['sources'] ?? [];
$targets = $_POST['targets'] ?? [];
$values = [];
for ($i=0; $i<count($ids); $i++) {
  $values[] = [$ids[$i] ?? '', $sources[$i] ?? '', $targets[$i] ?? ''];
}
$c = cfg(); $range = (string)($c['google_sheets_range'] ?? 'Sheet1!A:C');
$ok = sheets_values_update($range, $values);
echo json_encode(['ok'=>$ok]);
