<?php
require_once __DIR__ . '/auth.php'; require_login('editor');
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD']!=='POST'){ http_response_code(405); echo json_encode(['error'=>'POST only']); exit; }
$payload = json_decode(file_get_contents('php://input'), true);
if (!hash_equals($_SESSION['csrf'] ?? '', $payload['csrf'] ?? '')) { http_response_code(400); echo json_encode(['error'=>'Bad CSRF']); exit; }
$changes = $payload['targets'] ?? null;
if (!is_array($changes)) { http_response_code(400); echo json_encode(['error'=>'Bad payload']); exit; }
$parsed = $_SESSION['parsed'] ?? null; if(!$parsed){ http_response_code(400); echo json_encode(['error'=>'No XLIFF loaded']); exit; }
$units = $parsed['units'] ?? []; $valid=[]; foreach($units as $u) $valid[$u['id']] = true;
$targets = $_SESSION['targets'] ?? []; $updated=0;
foreach ($changes as $id=>$text){ if(isset($valid[$id])){ $targets[$id] = (string)$text; $updated++; } }
$_SESSION['targets'] = $targets;
echo json_encode(['ok'=>true,'updated'=>$updated]);
