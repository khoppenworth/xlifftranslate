<?php
require_once __DIR__ . '/auth.php'; require_login('viewer');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo "POST only"; exit; }
if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) { http_response_code(400); echo "Bad CSRF"; exit; }

$parsed = $_SESSION['parsed'] ?? null;
$targets = $_SESSION['targets'] ?? [];
if (!$parsed) { http_response_code(400); echo "No XLIFF in session."; exit; }

$ids = isset($_POST['ids']) ? (array)$_POST['ids'] : [];
$units = $parsed['units'] ?? [];
$rows = [];
if ($ids) {
  $map = [];
  foreach ($units as $u) { $map[$u['id']] = $u; }
  foreach ($ids as $id) {
    if (!isset($map[$id])) continue;
    $u = $map[$id];
    $rows[] = [$id, strip_tags($u['source']), (string)($targets[$id] ?? $u['target'] ?? '')];
  }
} else {
  foreach ($units as $u) {
    $id = $u['id'];
    $rows[] = [$id, strip_tags($u['source']), (string)($targets[$id] ?? $u['target'] ?? '')];
  }
}

$filename = 'xliff_rows_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$out = fopen('php://output', 'w');
fwrite($out, chr(0xEF).chr(0xBB).chr(0xBF));
fputcsv($out, ['id', 'source', 'target']);
foreach ($rows as $r) fputcsv($out, [$r[0] ?? '', $r[1] ?? '', $r[2] ?? '']);
fclose($out);
exit;
