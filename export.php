<?php
require_once __DIR__ . '/auth.php'; require_login('viewer');
require_once __DIR__ . '/xliff_lib.php';
if (!hash_equals($_SESSION['csrf'] ?? '', $_GET['csrf'] ?? '')) { http_response_code(400); echo "Bad CSRF"; exit; }
$parsed = $_SESSION['parsed'] ?? null; $targets = $_SESSION['targets'] ?? [];
if (!$parsed) { http_response_code(400); echo "No XLIFF in session."; exit; }
$map = []; foreach ($parsed['units'] as $u) { $id=$u['id']; $map[$id]=$targets[$id] ?? $u['target'] ?? ''; }
$targetLang = $_GET['lang'] ?? ($_POST['lang'] ?? ''); if (!$targetLang) $targetLang = $parsed['targetLang'] ?? '';
try { $out = build_xliff($parsed['xml'], $map, $targetLang ?: null); }
catch (Throwable $e) { http_response_code(500); echo "Build error: ".htmlspecialchars($e->getMessage()); exit; }
header('Content-Type: application/xml; charset=UTF-8');
header('Content-Disposition: attachment; filename="translated_'.date('Ymd_His').'.xlf"');
echo $out;
