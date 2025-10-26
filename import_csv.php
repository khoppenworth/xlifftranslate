<?php
require_once __DIR__ . '/auth.php'; require_login('editor');
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error'=>'POST only']); exit; }
if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) { http_response_code(400); echo json_encode(['error'=>'Bad CSRF']); exit; }
if (!isset($_FILES['csv']) || $_FILES['csv']['error'] !== UPLOAD_ERR_OK) { http_response_code(400); echo json_encode(['error'=>'Upload a CSV']); exit; }
$fh = fopen($_FILES['csv']['tmp_name'], 'r'); if (!$fh) { http_response_code(400); echo json_encode(['error'=>'Cannot open CSV']); exit; }
$header = fgetcsv($fh); if (!$header) { http_response_code(400); echo json_encode(['error'=>'Empty CSV']); exit; }
$cols = array_map('strtolower', array_map('trim', $header)); $idxId=array_search('id',$cols); $idxTarget=array_search('target',$cols);
if ($idxId===false || $idxTarget===false) { http_response_code(400); echo json_encode(['error'=>'Header must contain id and target']); exit; }
$parsed = $_SESSION['parsed'] ?? null; if (!$parsed) { http_response_code(400); echo json_encode(['error'=>'No XLIFF loaded']); exit; }
$validIds=[]; foreach (($parsed['units'] ?? []) as $u) $validIds[$u['id']] = true;
$targets = $_SESSION['targets'] ?? []; $updated=0; $skipped=0; $maxRows=20000;
while (($row = fgetcsv($fh)) !== false) { if (--$maxRows < 0) break; $id=(string)($row[$idxId] ?? ''); $tgt=(string)($row[$idxTarget] ?? '');
  if ($id==='' || !isset($validIds[$id])) { $skipped++; continue; } $targets[$id]=$tgt; $updated++; }
fclose($fh); $_SESSION['targets']=$targets; echo json_encode(['ok'=>true,'updated'=>$updated,'skipped'=>$skipped]);
