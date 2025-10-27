<?php
require_once __DIR__ . '/auth.php'; require_login('viewer');
require_once __DIR__ . '/xliff_lib.php';
$cfg = cfg(); $csrf = ensure_csrf(); $u = current_user();

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
</head>
<body>
<header class="appbar">
  <div class="title"><?=htmlspecialchars($cfg['app_name'])?></div>
  <nav class="nav">
    <span class="muted">Signed in as <strong><?=htmlspecialchars($u['username']??'')?></strong> (<?=htmlspecialchars($u['role']??'viewer')?>)</span>
    <a class="chip" href="health.php">health</a>
    <a class="chip" href="logout.php">logout</a>
  </nav>
</header>

<main class="container grid">
  <section class="card">
    <h3>Upload XLIFF</h3>
    <?php if($err): ?><div class="card warn"><?=$err?></div><?php endif; ?>
    <?php if($ok): ?><div class="card ok"><?=$ok?></div><?php endif; ?>
    <form method="post" enctype="multipart/form-data" class="grid2">
      <input type="hidden" name="csrf" value="<?=$csrf?>">
      <input type="hidden" name="action" value="upload">
      <label class="field"><span>File</span>
        <input type="file" name="xlf" accept=".xlf,.xliff,application/xml" required>
      </label>
      <div class="row">
        <button class="btn primary" type="submit">Load</button>
      </div>
      <p class="muted">Requires PHP DOM extension (php-xml).</p>
    </form>
  </section>

  <section class="card">
    <h3>Translate</h3>
    <div class="toolbar">
      <div class="left">
        <label class="field"><span>Provider</span>
          <select id="provider">
            <option value="libre">LibreTranslate</option>
            <option value="deepl">DeepL</option>
            <option value="azure">Azure</option>
            <option value="google">Google</option>
            <option value="mymemory">MyMemory</option>
            <option value="mock">Mock</option>
          </select>
        </label>
        <label class="field"><span>Source lang</span>
          <input id="sourceLang" placeholder="auto or en" value="auto">
        </label>
        <label class="field"><span>Target lang</span>
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
          <?php if(has_role('editor')): ?>
          <button id="btnSave" class="btn">Save</button>
          <?php endif; ?>
          <button id="btnExportCSV" class="btn">Export CSV</button>
          <a id="btnExportXLF" class="btn" href="export.php?csrf=<?=$csrf?>">Download XLIFF</a>
        </div>
      </div>
    </div>
  </section>

  <section class="card col-span-2">
    <h3>Segments</h3>
    <div class="scroll">
      <table class="table">
        <thead>
          <tr>
            <th><input type="checkbox" id="selAll"></th>
            <th style="width:220px">ID</th>
            <th>Source</th>
            <th>Target</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($units as $u):
            $id = htmlspecialchars($u['id']);
            $src = $u['source'];
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
    </div>
  </section>

  <section class="card">
    <h3>Import CSV targets</h3>
    <form action="import_csv.php" method="post" enctype="multipart/form-data" class="grid2">
      <input type="hidden" name="csrf" value="<?=$csrf?>">
      <label class="field"><span>CSV file (id,source,target)</span>
        <input type="file" name="csv" accept=".csv" required>
      </label>
      <div class="row"><button class="btn" type="submit">Import</button></div>
    </form>
  </section>
</main>

<div id="snackbar"></div>
<div id="overlay"><div class="card">Working…</div></div>
<script>window.__CSRF__="<?= $csrf ?>";</script>
<script src="assets/js/app.js"></script>
</body></html>
