<?php
require_once __DIR__ . '/auth.php'; require_login('editor');
require_once __DIR__ . '/rate_limit.php';
header('Content-Type: application/json');

$cfg = cfg();
$rl = $cfg['rate_limit'] ?? ['translate_per_minute'=>60, 'burst'=>30];
$cap = max(1, ((int)($rl['translate_per_minute'] ?? 60)) + (int)($rl['burst'] ?? 0));
$ppm = max(1, (int)($rl['translate_per_minute'] ?? 60));
if (!rate_limit_allow('translate', $cap, $ppm)) { http_response_code(429); echo json_encode(['error'=>'Rate limit exceeded']); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error'=>'POST only']); exit; }

$payload = json_decode(file_get_contents('php://input'), true);
$texts = $payload['texts'] ?? [];
$source = trim((string)($payload['source'] ?? ''));
$target = trim((string)($payload['target'] ?? ''));
$csrf   = $payload['csrf'] ?? '';
$overrideProvider = strtolower(trim((string)($payload['provider'] ?? '')));
if (!hash_equals($_SESSION['csrf'] ?? '', $csrf)) { http_response_code(400); echo json_encode(['error'=>'Bad CSRF']); exit; }
if ($target==='') { http_response_code(400); echo json_encode(['error'=>'Missing target language']); exit; }
if (!is_array($texts)) { http_response_code(400); echo json_encode(['error'=>'Bad payload']); exit; }

$norm=[]; foreach($texts as $t){ $norm[]=(string)$t; }
$translator = $overrideProvider ?: strtolower(trim((string)($cfg['translator'] ?? 'libre')));
$maxCharsReq = (int)($cfg['max_chars_per_request'] ?? 25000);
$total=0; foreach($norm as $t) $total += mb_strlen($t,'UTF-8');

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
    case 'mock': default: return array_map(function($t) use($target){ return trim($t)===''?'':("[MOCK-$target] ".$t); }, $texts);
  }
}
if ($total > $maxCharsReq){
  $out=[]; $chunk=[]; $sum=0; $limit=max(1,(int)($maxCharsReq*0.9));
  foreach($norm as $t){ $len=mb_strlen($t,'UTF-8'); if($sum+$len>$limit && $chunk){ $out=array_merge($out,do_translate($translator,$chunk,$source,$target,$cfg)); $chunk=[]; $sum=0; } $chunk[]=$t; $sum+=$len; }
  if($chunk) $out=array_merge($out,do_translate($translator,$chunk,$source,$target,$cfg));
} else { $out=do_translate($translator,$norm,$source,$target,$cfg); }
$out=array_values($out);
if (count($out)!==count($norm)){ $out = (count($out)<count($norm)) ? array_pad($out, count($norm), '') : array_slice($out,0,count($norm)); }
echo json_encode(['translations'=>$out]);

# --- Providers ---
function translate_libre(array $texts,string $source,string $target,array $cfg): array {
  $endpoint=rtrim((string)($cfg['libre_endpoint'] ?? 'http://localhost:5000'),'/');
  $apiKey=trim((string)($cfg['libre_api_key'] ?? ''));
  $maxChars=max(1000,(int)($cfg['libre_max_chars_per_call'] ?? 8000));
  $delayMs=max(0,(int)($cfg['libre_delay_ms'] ?? 0));
  $out=[]; foreach($texts as $t){
    $s=(string)$t; if (trim(strip_tags($s))===''){ $out[]=''; continue; }
    $parts=split_text_for_limit($s,$maxChars); $pieces=[];
    foreach ($parts as $part){ $pieces[] = libre_call_with_retry($endpoint,$apiKey,$part,$source,$target,$delayMs); }
    $out[]=implode('',$pieces);
  } return $out;
}
function libre_call_with_retry($endpoint,$apiKey,$q,$source,$target,$delayMs){
  $payload=['q'=>$q,'source'=>$source?:'auto','target'=>$target,'format'=>'html']; if($apiKey!=='') $payload['api_key']=$apiKey;
  $attempts=0; $max=4; $sleep=$delayMs;
  while(true){
    [$http,$resp,$err]=http_json("$endpoint/translate",'POST',['Content-Type: application/json'], json_encode($payload, JSON_UNESCAPED_UNICODE));
    if ($resp!==false && $http>=200 && $http<300){ $j=json_decode($resp,true); if($delayMs>0) usleep($delayMs*1000); return (string)($j['translatedText'] ?? ''); }
    $attempts++; if($attempts>=$max){ return ''; }
    usleep(($sleep + 400*$attempts)*1000);
  }
}
function split_text_for_limit(string $text,int $maxChars): array {
  if (mb_strlen($text,'UTF-8') <= $maxChars) return [$text];
  $chunks=[]; $cur=''; $len=mb_strlen($text,'UTF-8');
  for($i=0;$i<$len;$i++){ $ch=mb_substr($text,$i,1,'UTF-8'); if(mb_strlen($cur,'UTF-8')+1 > $maxChars){ $chunks[]=$cur; $cur=''; } $cur.=$ch;
    if(($ch==='.'||$ch==='!'||$ch==='?') && mb_strlen($cur,'UTF-8')>=($maxChars*0.6)){ $chunks[]=$cur; $cur=''; } }
  if($cur!=='') $chunks[]=$cur; return $chunks;
}
function translate_deepl(array $texts,string $source,string $target,array $cfg): array {
  $endpoint=(string)($cfg['deepl_endpoint'] ?? 'https://api-free.deepl.com/v2/translate');
  $key=trim((string)($cfg['deepl_api_key'] ?? '')); if($key==='') return array_map(fn($t)=>$t,$texts);
  $post=['target_lang'=>strtoupper($target)]; if($source) $post['source_lang']=strtoupper($source);
  foreach($texts as $t) $post['text'][]=$t;
  [$http,$resp,$err]=http_json($endpoint,'POST',['Authorization: DeepL-Auth-Key '.$key,'Content-Type: application/x-www-form-urlencoded'], http_build_query($post));
  if($resp===false || $http>=400) return array_map(fn($t)=>$t,$texts);
  $data=json_decode($resp,true); $out=[]; foreach(($data['translations']??[]) as $row) $out[]=(string)($row['text']??''); return $out;
}
function translate_azure(array $texts,string $source,string $target,array $cfg): array {
  $endpoint=(string)($cfg['azure_endpoint'] ?? 'https://api.cognitive.microsofttranslator.com/translate?api-version=3.0');
  $key=trim((string)($cfg['azure_key'] ?? '')); $region=trim((string)($cfg['azure_region'] ?? ''));
  if($key===''||$region==='') return array_map(fn($t)=>$t,$texts);
  $url=$endpoint.'&to='.urlencode($target).($source?'&from='.urlencode($source):'');
  $body=[]; foreach($texts as $t) $body[]=['Text'=>$t];
  [$http,$resp,$err]=http_json($url,'POST',['Ocp-Apim-Subscription-Key: '.$key,'Ocp-Apim-Subscription-Region: '.$region,'Content-Type: application/json'], json_encode($body, JSON_UNESCAPED_UNICODE));
  if($resp===false || $http>=400) return array_map(fn($t)=>$t,$texts);
  $data=json_decode($resp,true); $out=[]; foreach($data as $chunk) $out[]=(string)($chunk['translations'][0]['text']??''); return $out;
}
function translate_google(array $texts,string $source,string $target,array $cfg): array {
  $apiKey=trim((string)($cfg['google_api_key'] ?? '')); if($apiKey==='') return array_map(fn($t)=>$t,$texts);
  $url="https://translation.googleapis.com/language/translate/v2?key=".urlencode($apiKey);
  $post=['target'=>$target,'format'=>'html']; if($source) $post['source']=$source; foreach($texts as $t) $post['q'][]=$t;
  [$http,$resp,$err]=http_json($url,'POST',['Content-Type: application/json'], json_encode($post, JSON_UNESCAPED_UNICODE));
  if($resp===false || $http>=400) return array_map(fn($t)=>$t,$texts);
  $data=json_decode($resp,true); $out=[]; foreach(($data['data']['translations']??[]) as $row) $out[]=(string)($row['translatedText']??''); return $out;
}
function translate_mymemory(array $texts,string $source,string $target,array $cfg): array {
  $out=[]; foreach($texts as $q){ $url='https://api.mymemory.translated.net/get?q='.rawurlencode($q).'&langpair='.rawurlencode(($source?:'auto').'|'.$target);
    [$http,$resp,$err]=http_json($url,'GET',[]); if($resp===false || $http>=400){ $out[]=$q; continue; } $data=json_decode($resp,true); $out[]=(string)($data['responseData']['translatedText'] ?? $q); }
  return $out;
}
