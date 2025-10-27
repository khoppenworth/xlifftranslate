<?php
require_once __DIR__ . '/auth.php'; require_login('viewer');
require_once __DIR__ . '/xliff_lib.php';
if (!hash_equals($_SESSION['csrf'] ?? '', $_GET['csrf'] ?? '')) { http_response_code(400); echo "Bad CSRF"; exit; }
$parsed = $_SESSION['parsed'] ?? null; if(!$parsed){ http_response_code(400); echo "No XLIFF in session"; exit; }
$targets = $_SESSION['targets'] ?? [];
try{
  $merged = replace_targets(parse_xliff($parsed['xml']), $targets);
} catch(Exception $e){ http_response_code(500); echo "Build error: ".$e->getMessage(); exit; }
$fn = 'translated_'.date('Ymd_His').'.xlf';
header('Content-Type: application/xml'); header('Content-Disposition: attachment; filename="'.$fn.'"');
echo $merged;