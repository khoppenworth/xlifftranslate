<?php
session_start();
require_once __DIR__ . '/auth.php'; require_login();
require_once __DIR__ . '/google.php';
header('Content-Type: application/json');
if (!hash_equals($_SESSION['csrf'] ?? '', $_GET['csrf'] ?? '')) { http_response_code(400); echo json_encode(['error'=>'Bad CSRF']); exit; }
$c = cfg(); $range = (string)($c['google_sheets_range'] ?? 'Sheet1!A:C');
$values = sheets_values_get($range) ?? [];
$out = [];
foreach ($values as $row) {
  $id = $row[0] ?? ''; $src = $row[1] ?? ''; $tgt = $row[2] ?? '';
  if ($id !== '') $out[$id] = ['source'=>$src,'target'=>$tgt];
}
echo json_encode(['rows'=>$out]);
