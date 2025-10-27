<?php
require_once __DIR__ . '/auth.php'; require_login('viewer');
require_once __DIR__ . '/xliff_lib.php';
$cfg = cfg(); $csrf = ensure_csrf();

$err = null; $ok = null;

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='upload'){
  if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) { $err='Bad CSRF'; }
  else if (!isset($_FILES['xlf']) || $_FILES['xlf']['error']!==UPLOAD_ERR_OK){ $err='Upload failed'; }
  else {
    $xml = file_get_contents($_FILES['xlf']['tmp_name']);
    try{
      $parsed = parse_xliff($xml);
      $_SESSION['parsed'] = ['units'=>array_map(function($u){ return ['id'=>$u['id'], 'source'=>$u['source'], 'target'=>$u['target']]; }, $parsed['units']), 'xml'=>$xml];
      $_SESSION['targets'] = [];
      $ok = 'XLIFF loaded: '.count($parsed['units']).' units';
    } catch(Exception $e){ $err = 'Parse error: '.$e->getMessage(); }
  }
}

$parsed = $_SESSION['parsed'] ?? null;
$units = $parsed['units'] ?? [];

?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?=htmlspecialchars($cfg['app_name'])?></title>
<link rel="stylesheet" href="assets/css/material.css">
<style>
  .table{width:100%; border-collapse:collapse}
  .table th,.table td{border-bottom:1px solid #eee; padding:8px; vertical-align:top}
  .idcell{font-family:ui-monospace,Consolas,monospace; font-size:12px; color:#555}
  textarea.tgt{width:100%; min-height:70px; resize:vertical}
  .src{max-width:520px; white-space:pre-wrap}
  .kbar{position:sticky; top:0; background:#fff; padding:8px; border-bottom:1px solid #eee; z-index:5; display:flex; gap:8px; align-items:center; justify-content:space-between}
  .stack{display:flex; flex-direction:column; gap:6px; min-width:200px}
  .btn{padding:8px 12px; border-radius:10px; border:1px solid #ddd; background:#fafafa; cursor:pointer}
  .btn.primary{background:#3b82f6; color:#fff; border-color:#3b82f6}
  .toolbar .left,.toolbar .right{display:flex; gap:8px; align-items:center}
  .chip{display:inline-block; padding:3px 8px; background:#eef; color:#334; border-radius:999px; font-size:12px}
  #snackbar{position:fixed; left:50%; transform:translateX(-50%); bottom:22px; background:#222; color:#fff; padding:10px 14px; border-radius:10px; opacity:0; transition:.25s; z-index:50}
  #snackbar.show{opacity:1}
  #overlay{position:fixed; inset:0; background:rgba(255,255,255,.6); display:none; align-items:center; justify-content:center; z-index:40}
  #overlay.show{display:flex}
</style>
</head>
<body>
<header class="appbar">
  <div class="title"><?=htmlspecialchars($cfg['app_name'])?></div>
  <div class="actions"><a class="chip" href="health.php">health</a></div>
</header>

<main class="container">
  <?php if($err): ?><div class="card warn"><?=$err?></div><?php endif; ?>
  <?php if($ok): ?><div class="card ok"><?=$ok?></div><?php endif; ?>

  <form class="card" method="post" enctype="multipart/form-data">
    <h3>Upload XLIFF</h3>
    <input type="hidden" name="csrf" value="<?=$csrf?>">
    <input type="hidden" name="action" value="upload">
    <input type="file" name="xlf" accept=".xlf,.xliff,application/xml" required>
    <div><button class="btn primary" type="submit">Load</button></div>
    <p class="muted">Requires PHP DOM extension (php-xml).</p>
  </form>

  <section class="card">
    <h3>Translate</h3>
    <div class="toolbar">
      <div class="left">
        <label class="stack"><span>Provider</span>
          <select id="provider">
            <option value="libre">LibreTranslate</option>
            <option value="deepl">DeepL</option>
            <option value="azure">Azure</option>
            <option value="google">Google</option>
            <option value="mymemory">MyMemory</option>
            <option value="mock">Mock</option>
          </select>
        </label>
        <label class="stack"><span>Source lang</span>
          <input id="sourceLang" placeholder="auto or en" value="auto">
        </label>
        <label class="stack"><span>Target lang</span>
          <input id="targetLang" placeholder="fr / fr-FR / EN-GB">
        </label>
      </div>
      <div class="right kbar">
        <div class="left">
          <button id="btnSelectAll" class="btn">Select all</button>
          <button id="btnClearSel" class="btn">Clear</button>
        </div>
        <div class="right">
          <button id="btnTranslateSel" class="btn">Translate Selected</button>
          <button id="btnTranslateAll" class="btn primary">Translate ALL</button>
          <button id="btnSave" class="btn">Save</button>
          <button id="btnExportCSV" class="btn">Export CSV</button>
          <a id="btnExportXLF" class="btn" href="export.php?csrf=<?=$csrf?>">Download XLIFF</a>
        </div>
      </div>
    </div>
  </section>

  <input type="hidden" name="csrf" value="<?=$csrf?>">

  <section class="card">
    <h3>Segments</h3>
    <table class="table">
      <thead>
        <tr>
          <th><input type="checkbox" id="selAll"></th>
          <th style="width:200px">ID</th>
          <th>Source</th>
          <th>Target</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($units as $u):
          $id = htmlspecialchars($u['id']);
          $src = $u['source']; // HTML safe: it's inner XML
          $tgt = htmlspecialchars($_SESSION['targets'][$u['id']] ?? $u['target'] ?? '');
        ?>
        <tr>
          <td><input class="rowSel" type="checkbox"></td>
          <td class="idcell"><?=$id?></td>
          <td class="src"><?=$src?></td>
          <td><textarea class="tgt" data-id="<?=$id?>"><?=$tgt?></textarea></td>
          <td><button class="btn btn-row" type="button">Translate</button></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </section>
</main>

<div id="snackbar"></div>
<div id="overlay"><div class="card">Working…</div></div>
<script>window.__CSRF__="<?= $csrf ?>";</script>
<script src="assets/js/app.js"></script>
</body></html>
