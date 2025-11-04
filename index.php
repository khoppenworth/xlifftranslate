<?php
require_once __DIR__ . '/auth.php'; require_login('viewer');
require_once __DIR__ . '/xliff_lib.php';
require_once __DIR__ . '/http_client.php';
$cfg=cfg(); if (empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(16));
$err=''; $info=''; $canEdit = (current_role() !== 'viewer');
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function sanitize_fragment($html){ $html=preg_replace('~<\s*(script|style)[^>]*>.*?<\s*/\s*\1\s*>~is','',$html??''); $html=preg_replace('/\son[a-z]+\s*=\s*(\"[^\"]*\"|\'[^\']*\'|[^\s>]+)/i','',$html); $html=preg_replace('/(href|src)\s*=\s*([\'\"])javascript:[^\\2]*\\2/i','',$html); return $html; }

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')){
  if (!$canEdit){ $err='Read-only role.'; }
  elseif ($_POST['action']==='upload'){
    if (!isset($_FILES['xliff']) || $_FILES['xliff']['error']!==UPLOAD_ERR_OK){ $err='Please upload a valid XLIFF file.'; }
    else {
      $maxMb=(int)($cfg['max_upload_mb'] ?? 20);
      if ($_FILES['xliff']['size'] > $maxMb*1024*1024) $err='File too large. Limit is '.$maxMb.' MB.';
      else {
        $name=strtolower($_FILES['xliff']['name'] ?? '');
        if (!preg_match('/\.(xlf|xliff|xml)$/i',$name)) $err='Invalid file type. Upload .xlf/.xliff/.xml';
        else {
          $data=file_get_contents($_FILES['xliff']['tmp_name']);
          try{
            $parsed=parse_xliff($data);
            $endpoint=rtrim((string)($cfg['libre_endpoint'] ?? 'http://localhost:5000'),'/');
            $sample=''; foreach ($parsed['units'] as $i=>$u){ if ($i>=20) break; $sample.=strip_tags($u['source'])." "; }
            if (!$parsed['sourceLang'] && trim($sample)!==''){ [$http,$resp,$e] = http_json($endpoint.'/detect','POST',['Content-Type: application/json'], json_encode(['q'=>$sample], JSON_UNESCAPED_UNICODE), 30); $j=json_decode($resp,true); if(is_array($j) && isset($j[0]['language'])) $parsed['sourceLang']=$j[0]['language']; }
            foreach ($parsed['units'] as &$u){ $u['source']=sanitize_fragment($u['source']); $u['target']=sanitize_fragment($u['target']); }
            unset($u);
            $_SESSION['parsed']=$parsed; $_SESSION['targets']=[]; $info='Loaded '.count($parsed['units']).' units.' . ($parsed['sourceLang']?' Source: '.$parsed['sourceLang']:'');
          } catch (Throwable $e){ $err='Parse error: '.h($e->getMessage()); }
        }
      }
    }
  } elseif ($_POST['action']==='save' && isset($_SESSION['parsed'])){
    $_SESSION['targets'] = $_POST['target'] ?? []; $info='Edits saved.';
  }
}

$parsed = $_SESSION['parsed'] ?? null;
?><!doctype html><html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?=h($cfg['app_name'])?></title>
<link rel="stylesheet" href="assets/css/material.css">
</head><body class="mdui">

<header class="appbar">
  <button id="navToggle" class="btn icon" title="Menu">☰</button>
  <div class="appbar-title"><?=h($cfg['app_name'])?></div>
  <div class="actions">
    <a class="btn tonal" href="health.php" target="_blank">Health</a>
    <a class="btn" href="logout.php">Logout</a>
  </div>
</header>

<main class="container">
  <div class="row">
    <aside class="nav" id="sidebar">
      <div class="section">
        <h3>Account</h3>
        <div class="badge">User: <?=h(current_user() ?? 'unknown')?> · Role: <?=h(current_role())?></div>
      </div>
      <div class="section">
        <h3>Workflow</h3>
        <form method="post" enctype="multipart/form-data" class="stack">
          <input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>">
          <input type="hidden" name="action" value="upload">
          <label class="field"><span>Upload XLIFF</span><input type="file" name="xliff" accept=".xlf,.xliff,.xml" <?= $canEdit ? '' : 'disabled' ?> required></label>
          <button class="btn primary" <?= $canEdit ? '' : 'disabled' ?>>Load file</button>
          <div class="muted code">Need DOM? Install <strong>php-xml</strong>.</div>
        </form>
      </div>
      <?php if ($parsed): ?>
      <div class="section">
        <h3>Languages</h3>
        <div class="stack">
          <label class="field"><span>Source</span><input id="sourceLang" value="<?=h($parsed['sourceLang'] ?? '')?>" <?= $canEdit ? '' : 'disabled' ?> placeholder="en"></label>
          <label class="field"><span>Target</span><input id="targetLang" value="<?=h($parsed['targetLang'] ?? '')?>" <?= $canEdit ? '' : 'disabled' ?> placeholder="fr"></label>
          <label class="field"><span>Provider</span>
            <select id="provider" <?= $canEdit ? '' : 'disabled' ?>>
              <option value="">(default: <?=h($cfg['translator'])?>)</option>
              <option value="libre">LibreTranslate</option>
              <option value="deepl">DeepL</option>
              <option value="azure">Azure</option>
              <option value="google">Google</option>
              <option value="mymemory">MyMemory</option>
              <option value="mock">Mock</option>
            </select>
          </label>
        </div>
      </div>
      <?php endif; ?>
      <div class="section">
        <h3>About</h3>
        <div class="muted">XLIFF Studio v6.0 · Material UI · No DB · LAMP.</div>
      </div>
    </aside>

    <section>
      <?php if (!empty($err)): ?><div class="card"><div class="chip error"><?=h($err)?></div></div><?php endif; ?>
      <?php if (!empty($info)): ?><div class="card"><div class="chip"><?=h($info)?></div></div><?php endif; ?>

      <?php if (!$parsed): ?>
        <section class="card">
          <h2>Get started</h2>
          <p>Upload an XLIFF file to begin. Supports XLIFF 1.2 and 2.0. Your data stays in session (no database needed).</p>
        </section>
      <?php else: ?>
        <section class="kbar">
          <div class="left">
            <button id="btnTranslateAll" class="btn primary" <?= $canEdit ? '' : 'disabled' ?>>Translate ALL</button>
            <button id="btnTranslateSel" class="btn tonal" <?= $canEdit ? '' : 'disabled' ?>>Translate Selected</button>
            <button id="btnSave" class="btn" <?= $canEdit ? '' : 'disabled' ?>>Save</button>
          </div>
          <div class="right">
            <button id="btnExportCSV" class="btn">Export CSV</button>
            <label class="btn">
              Import CSV
              <input id="importCSV" type="file" accept=".csv,text/csv" style="display:none">
            </label>
            <a class="btn" href="export.php?csrf=<?=h($_SESSION['csrf'])?>">Download XLIFF</a>
          </div>
        </section>

        <section class="card">
          <div class="table-wrap">
            <table class="table" id="xliffTable">
              <thead><tr>
                <th><input type="checkbox" id="selAll"></th>
                <th style="width:18%">ID</th>
                <th>Source</th>
                <th style="width:40%">Target</th>
                <th style="width:56px">↻</th>
              </tr></thead>
              <tbody>
                <?php $targetsSaved=$_SESSION['targets'] ?? [];
                foreach (($parsed['units'] ?? []) as $i=>$u):
                  $id=$u['id']; $src=$u['source']; $tgt=$targetsSaved[$id] ?? ($u['target'] ?? '');
                ?>
                <tr data-row="<?= $i ?>">
                  <td><input type="checkbox" class="rowSel"></td>
                  <td class="mono idcell"><?=h($id)?></td>
                  <td class="mono src"><?= $src ?></td>
                  <td><textarea name="target[<?=h($id)?>]" class="tgt" rows="2" data-id="<?=h($id)?>" <?= $canEdit ? '' : 'readonly' ?>><?= $tgt ?></textarea></td>
                  <td><button type="button" class="btn icon btn-row" data-row="<?= $i ?>" <?= $canEdit ? '' : 'disabled' ?>>↻</button></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <div class="muted">Shortcuts: <span class="code">Alt+T</span> translate row · <span class="code">Alt+Enter</span> translate & focus next.</div>
        </section>
      <?php endif; ?>
    </section>
  </div>
  <div class="footer">© <?=date('Y')?> XLIFF Studio</div>
</main>

<div id="snackbar" class="snackbar"></div>
<div id="overlay" class="overlay"><div class="progress">Working…</div></div>

<script src="assets/js/app.js"></script>
</body></html>
