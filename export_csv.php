<?php
require_once __DIR__ . '/auth.php'; require_login('viewer');
if ($_SERVER['REQUEST_METHOD']!=='POST'){ http_response_code(405); echo "POST only"; exit; }
if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) { http_response_code(400); echo "Bad CSRF"; exit; }
$parsed=$_SESSION['parsed'] ?? null; if(!$parsed){ http_response_code(400); echo "No XLIFF in session."; exit; }
$units=$parsed['units'] ?? [];
$ids = isset($_POST['ids']) ? (array)$_POST['ids'] : [];
$liveTargets = $_POST['tgt'] ?? null;
$rows=[];
if ($ids) {
  $map=[]; foreach ($units as $u) $map[$u['id']]=$u;
  foreach ($ids as $id) if (isset($map[$id])) {
    $u = $map[$id];
    $target = $liveTargets[$id] ?? ($_SESSION['targets'][$id] ?? ($u['target'] ?? ''));
    $rows[] = [$id, strip_tags($u['source']), (string)$target];
  }
} else {
  foreach ($units as $u) {
    $id = $u['id'];
    $target = $liveTargets[$id] ?? ($_SESSION['targets'][$id] ?? ($u['target'] ?? ''));
    $rows[] = [$id, strip_tags($u['source']), (string)$target];
  }
}
$filename='xliff_rows_'.date('Ymd_His').'.csv';
header('Content-Type: text/csv; charset=UTF-8'); header('Content-Disposition: attachment; filename="'.$filename.'"');
$out=fopen('php://output','w'); fwrite($out, chr(0xEF).chr(0xBB).chr(0xBF)); fputcsv($out, ['id','source','target']);
foreach ($rows as $r) fputcsv($out, [$r[0]??'',$r[1]??'',$r[2]??'']); fclose($out); exit;