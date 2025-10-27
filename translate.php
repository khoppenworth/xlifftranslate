<?php
require_once __DIR__ . '/auth.php'; require_login('editor');
require_once __DIR__ . '/rate_limit.php';
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

$cfg = cfg();
$rl = $cfg['rate_limit'] ?? ['translate_per_minute'=>60, 'burst'=>30];
$cap = max(1, ((int)($rl['translate_per_minute'] ?? 60)) + (int)($rl['burst'] ?? 0));
$ppm = max(1, (int)($rl['translate_per_minute'] ?? 60));
if (!rate_limit_allow('translate', $cap, $ppm)) json_fail(429, 'Rate limit exceeded');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_fail(405, 'POST only');

$raw = file_get_contents('php://input'); $payload = json_decode($raw, true);
if (!is_array($payload)) json_fail(400, 'Bad JSON body');
$clientIds   = $payload['ids'] ?? [];
$source = trim((string)($payload['source'] ?? ''));
$target = trim((string)($payload['target'] ?? ''));
$csrf   = $payload['csrf'] ?? '';
$overrideProvider = strtolower(trim((string)($payload['provider'] ?? '')));
if (!hash_equals($_SESSION['csrf'] ?? '', $csrf)) json_fail(400, 'Bad CSRF');
if ($target==='') json_fail(400, 'Missing target language');
if (!is_array($clientIds) || empty($clientIds)) json_fail(400, 'No ids provided');

$translator = $overrideProvider ?: strtolower(trim((string)($cfg['translator'] ?? 'libre')));

function norm2($code){ $code=str_replace('_','-',trim($code)); if($code==='') return ''; $parts=explode('-',$code,2); return strtolower($parts[0]); }
function normalize_lang($provider,$code,$isTarget=true){
  $code = str_replace('_','-',trim($code));
  if ($provider==='libre' || $provider==='google' || $provider==='mymemory') return strtolower(explode('-',$code,2)[0]);
  if ($provider==='deepl') {
    $up = strtoupper($code);
    $whitelist = ['EN','EN-GB','EN-US','PT','PT-BR','ES','FR','DE','IT','NL','PL','RU','JA','ZH'];
    if (in_array($up,$whitelist,true)) return $up;
    if ($up==='EN-UK') return 'EN-GB';
    if (preg_match('/^([A-Z]{2})-([A-Z]{2})$/',$up,$m)) return in_array($up,$whitelist,true) ? $up : $m[1];
    return substr($up,0,2);
  }
  if ($provider==='azure') { $parts=explode('-',$code,2); return strtolower($parts[0]).(isset($parts[1])?('-'.strtoupper($parts[1])):''); }
  return norm2($code);
}
$sourceN = $source ? normalize_lang($translator,$source,false) : '';
$targetN = normalize_lang($translator,$target,true);

function strip_xliff_inline($html){
  $html = preg_replace('~</?g\b[^>]*>~i', '', (string)$html);
  $html = preg_replace('~<x\b[^>]*/>~i', '', $html);
  return $html;
}
if (!function_exists('curl_init')) json_fail(500, 'cURL extension not available');

$parsed = $_SESSION['parsed'] ?? null;
if (!$parsed) json_fail(400, 'No XLIFF loaded');

$mapSrc = []; foreach (($parsed['units'] ?? []) as $u) { $mapSrc[$u['id']] = (string)($u['source'] ?? ''); }
$texts = []; foreach ($clientIds as $id) { $src = $mapSrc[$id] ?? ''; $texts[] = strip_xliff_inline($src); }
if (!count($texts)) json_fail(400, 'No matching ids in current XLIFF');

$maxCharsReq = (int)($cfg['max_chars_per_request'] ?? 25000);
$total=0; foreach($texts as $t) $total += mb_strlen($t,'UTF-8');

function http_json($url,$method='POST',$headers=[],$body=null){
  $ch=curl_init($url); curl_setopt_array($ch,[CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>$headers,CURLOPT_POSTFIELDS=>$body,CURLOPT_TIMEOUT=>60]);
  $resp=curl_exec($ch); $http=curl_getinfo($ch,CURLINFO_HTTP_CODE); $err=curl_error($ch); curl_close($ch); return [$http,$resp,$err];
}
function do_translate($translator,$texts,$source,$target,$cfg){
  switch($translator){
    case 'libre': return translate_libre($texts,$source,$target,$cfg);
    case 'deepl': return translate_deepl($texts,$source,$target,$cfg);
    case 'azure': return translate_azure($texts,$source,$target,$cfg);
    case 'google': return translate_google($texts,$source,$target,$cfg);
    case 'mymemory': return translate_mymemory($texts,$source,$target,$cfg);
    case 'mock': default: return array_map(function($t) use($target){ return trim($t)===''?'':("[MOCK-$target] "+$t); }, $texts);
  }
}

if ($total > $maxCharsReq){
  $out=[]; $chunk=[]; $sum=0; $limit=max(1,(int)($maxCharsReq*0.9));
  foreach($texts as $t){ $len=mb_strlen($t,'UTF-8'); if($sum+$len>$limit && $chunk){ $out=array_merge($out,do_translate($translator,$chunk,$sourceN,$targetN,$cfg)); $chunk=[]; $sum=0; } $chunk[]=$t; $sum+=$len; }
  if($chunk) $out=array_merge($out,do_translate($translator,$chunk,$sourceN,$targetN,$cfg));
} else { $out=do_translate($translator,$texts,$sourceN,$targetN,$cfg); }
$out=array_values($out);
if (count($out)!==count($texts)){ $out = (count($out)<count($texts)) ? array_pad($out, count($texts), '') : array_slice($out,0,count($texts)); }

while (ob_get_level()) { ob_end_clean(); }
echo json_encode(['translations'=>$out], JSON_UNESCAPED_UNICODE);
exit;
