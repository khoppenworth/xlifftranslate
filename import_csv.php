<?php
require_once __DIR__ . '/auth.php'; require_login('editor');
if ($_SERVER['REQUEST_METHOD']!=='POST'){ http_response_code(405); echo "POST only"; exit; }
if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) { http_response_code(400); echo "Bad CSRF"; exit; }
if (!isset($_FILES['csv']) || $_FILES['csv']['error']!==UPLOAD_ERR_OK){ http_response_code(400); echo "Upload failed"; exit; }
$fh = fopen($_FILES['csv']['tmp_name'], 'r');
$headers = fgetcsv($fh); // id,source,target
$targets = $_SESSION['targets'] ?? [];
while(($row=fgetcsv($fh))!==false){
  $id = $row[0] ?? ''; $tgt = $row[2] ?? '';
  if($id!==''){ $targets[$id] = $tgt; }
}
fclose($fh);
$_SESSION['targets'] = $targets;
header('Location: index.php');