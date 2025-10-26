<?php
require_once __DIR__ . '/auth.php'; require_login('editor');
@ini_set('display_errors','0'); error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');
ob_start();
function json_fail($code, $msg, $extra = []){
  http_response_code($code);
  $out = array_merge(['error'=>$msg], $extra);
  $json = json_encode($out, JSON_UNESCAPED_UNICODE);
  if ($json === false) { $json = '{"error":"JSON encode failed"}'; }
  while (ob_get_level()) { ob_end_clean(); }
  echo $json; exit;
}
set_exception_handler(function($e){ json_fail(500, 'Exception: '.$e->getMessage()); });
set_error_handler(function($severity,$message,$file,$line){ json_fail(500, 'PHP error: '.$message, ['where'=>basename($file).':'.$line]); });

if ($_SERVER['REQUEST_METHOD']!=='POST') json_fail(405,'POST only');
$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
if (!is_array($payload)) json_fail(400, 'Bad JSON body');
if (!hash_equals($_SESSION['csrf'] ?? '', $payload['csrf'] ?? '')) json_fail(400,'Bad CSRF');
$changes = $payload['targets'] ?? null;
if (!is_array($changes)) json_fail(400,'Bad payload');

$parsed = $_SESSION['parsed'] ?? null; if(!$parsed) json_fail(400,'No XLIFF loaded');
$units = $parsed['units'] ?? []; $valid=[]; foreach($units as $u) $valid[$u['id']] = true;
$targets = $_SESSION['targets'] ?? []; $updated=0;
foreach ($changes as $id=>$text){ if(isset($valid[$id])){ $targets[$id] = (string)$text; $updated++; } }
$_SESSION['targets'] = $targets;

while (ob_get_level()) { ob_end_clean(); }
echo json_encode(['ok'=>true,'updated'=>$updated], JSON_UNESCAPED_UNICODE);
exit;
