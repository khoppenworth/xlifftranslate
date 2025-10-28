<?php
require_once __DIR__ . '/auth.php'; require_login('editor');
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD']!=='POST'){ http_response_code(405); echo json_encode(['error'=>'POST only']); exit; }
if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) { http_response_code(400); echo json_encode(['error'=>'Bad CSRF']); exit; }
if (!isset($_FILES['csv']) || $_FILES['csv']['error']!==UPLOAD_ERR_OK){ http_response_code(400); echo json_encode(['error'=>'Upload a CSV']); exit; }
$raw=file_get_contents($_FILES['csv']['tmp_name']); if($raw===false){ http_response_code(400); echo json_encode(['error'=>'Cannot read upload']); exit; }
$raw=preg_replace('/^\xEF\xBB\xBF/','',$raw); $raw=str_replace(["\r\n","\r"],"\n",$raw);
$tmp=tempnam(sys_get_temp_dir(),'csv'); file_put_contents($tmp,$raw);
$fh=fopen($tmp,'r'); if(!$fh){ http_response_code(400); echo json_encode(['error'=>'Cannot open CSV']); exit; }
$header=fgetcsv($fh); if(!$header){ fclose($fh); unlink($tmp); http_response_code(400); echo json_encode(['error'=>'Empty CSV']); exit; }
$cols=array_map('strtolower',array_map('trim',$header)); $idxId=array_search('id',$cols); $idxTarget=array_search('target',$cols);
if($idxId===false||$idxTarget===false){ fclose($fh); unlink($tmp); http_response_code(400); echo json_encode(['error'=>'Header must contain id and target']); exit; }
$parsed=$_SESSION['parsed'] ?? null; if(!$parsed){ fclose($fh); unlink($tmp); http_response_code(400); echo json_encode(['error'=>'No XLIFF loaded']); exit; }
$valid=[]; foreach(($parsed['units']??[]) as $u) $valid[$u['id']]=true;
$targets=$_SESSION['targets'] ?? []; $updated=0; $skipped=0; $limit=30000;
while(($row=fgetcsv($fh))!==false && $limit-- > 0){ $id=(string)($row[$idxId] ?? ''); $tgt=(string)($row[$idxTarget] ?? '');
  if($id===''||!isset($valid[$id])){ $skipped++; continue; } $targets[$id]=$tgt; $updated++; }
fclose($fh); unlink($tmp); $_SESSION['targets']=$targets; echo json_encode(['ok'=>true,'updated'=>$updated,'skipped'=>$skipped]);
