<?php
require_once __DIR__ . '/auth.php'; require_login('viewer');
require_once __DIR__ . '/xliff_lib.php';
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));

$err=''; $info=''; $parsed = $_SESSION['parsed'] ?? null;
$cfg = cfg();

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function http_json($url, $method='POST', $headers=[], $body=null) {
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_CUSTOMREQUEST => $method,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_POSTFIELDS => $body,
    CURLOPT_TIMEOUT => 30,
  ]);
  $resp = curl_exec($ch);
  $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $err  = curl_error($ch);
  curl_close($ch);
  return [$http, $resp, $err];
}
// Basic sanitize for display
function sanitize_fragment($html){
  $html = preg_replace('~<\s*(script|style)[^>]*>.*?<\s*/\s*\1\s*>~is', '', $html ?? '');
  $html = preg_replace('/\son[a-z]+\s*=\s*(\"[^\"]*\"|\'[^\']*\'|[^\s>]+)/i', '', $html);
  $html = preg_replace('/(href|src)\s*=\s*([\'\"])javascript:[^\\2]*\\2/i', '', $html);
  return $html;
}
// Upload/edit allowed for editor+
$canEdit = (current_role() !== 'viewer');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
  if (!$canEdit) { $err = 'Read-only role.'; }
  else if ($_POST['action'] === 'upload') {
    if (!isset($_FILES['xliff']) || $_FILES['xliff']['error'] !== UPLOAD_ERR_OK) { $err = 'Please upload a valid XLIFF file.'; }
    else {
      $maxMb = (int)($cfg['max_upload_mb'] ?? 10);
      if ($_FILES['xliff']['size'] > $maxMb * 1024 * 1024) {
        $err = 'File too large. Limit is '.$maxMb.' MB.';
      } else {
        $name = strtolower($_FILES['xliff']['name'] ?? '');
        if (!preg_match('/\.(xlf|xliff|xml)$/i', $name)) { $err = 'Invalid file type. Upload .xlf/.xliff/.xml'; }
        else {
          $data = file_get_contents($_FILES['xliff']['tmp_name']);
          try {
            $parsed = parse_xliff($data);
            $endpoint = rtrim((string)($cfg['libre_endpoint'] ?? 'http://localhost:5000'), '/');
            $sample = '';
            foreach ($parsed['units'] as $i=>$u){ if ($i>=20) break; $sample .= strip_tags($u['source'])." "; }
            if (!$parsed['sourceLang'] && trim($sample) !== '') {
              [$http,$resp,$e] = http_json($endpoint.'/detect','POST',['Content-Type: application/json'], json_encode(['q'=>$sample], JSON_UNESCAPED_UNICODE));
              $j = json_decode($resp, true);
              if (is_array($j) && isset($j[0]['language'])) $parsed['sourceLang'] = $j[0]['language'];
            }
            foreach ($parsed['units'] as &$u) { $u['source'] = sanitize_fragment($u['source']); $u['target'] = sanitize_fragment($u['target']); }
            $_SESSION['parsed']=$parsed; $_SESSION['targets']=[];
            $info = 'File loaded: '.count($parsed['units']).' units.' . ($parsed['sourceLang'] ? ' Source auto-detect: '.$parsed['sourceLang'] : '');
          } catch (Throwable $e) { $err = 'Parse error: '.h($e->getMessage()); }
        }
      }
    }
  } elseif ($_POST['action'] === 'save_edits' && $parsed) {
    if ($canEdit) { $_SESSION['targets'] = $_POST['target'] ?? []; $info = 'Edits saved.'; } else { $err='Read-only role.'; }
  }
}
?><!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>XLIFF Table Translate</title>
<link rel="stylesheet" href="assets/css/style.css">
</head><body class="theme-auto">
<header class="site-header">
  <div class="container">
    <div class="brand">XLIFF Table Translate</div>
    <nav>
      <span class="muted">User: <?=h(current_user() ?? 'unknown')?> (<?=h(current_role())?>)</span>
      <button id="themeToggle" class="button" title="Toggle theme">🌓</button>
      <a href="health.php" target="_blank">Server check</a>
      <a href="logout.php">Logout</a>
    </nav>
  </div>
</header>

<main class="container">
  <?php if (!empty($err)): ?><div class="alert alert-error"><?=h($err)?></div><?php endif; ?>
  <?php if (!empty($info)): ?><div class="alert alert-info"><?=h($info)?></div><?php endif; ?>

  <section class="card">
    <h2>Upload XLIFF</h2>
    <form method="post" enctype="multipart/form-data" class="stack">
      <input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>">
      <input type="hidden" name="action" value="upload">
      <input type="file" name="xliff" accept=".xlf,.xliff,.xml" <?= $canEdit ? '' : 'disabled' ?> required>
      <button type="submit" class="primary" <?= $canEdit ? '' : 'disabled' ?>>Load file</button>
      <p class="muted">If DOM is missing, install php-xml and restart your web server.</p>
    </form>
  </section>

  <?php $parsed = $_SESSION['parsed'] ?? null; if ($parsed): $srcLang = $parsed['sourceLang'] ?: ''; $trgLang = $parsed['targetLang'] ?: ''; ?>
  <section class="card">
    <h2>Review & Translate</h2>
    <div class="controls">
      <label>Source <input type="text" id="sourceLang" value="<?=h($srcLang)?>" placeholder="en-US" <?= $canEdit ? '' : 'disabled' ?>></label>
      <label>Target <input type="text" id="targetLang" value="<?=h($trgLang)?>" placeholder="fr-FR" <?= $canEdit ? '' : 'disabled' ?>></label>
      <label>Provider
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
      <button id="autoTranslate" class="primary" <?= $canEdit ? '' : 'disabled' ?>>Translate ALL</button>
      <span class="muted">Endpoint: <code><?=h($cfg['libre_endpoint'])?></code></span>
    </div>

    <div class="controls">
      <label>Filter <input type="search" id="filterBox" placeholder="Search source or target..."></label>
      <label>Rows/page
        <select id="pageSize">
          <option>25</option><option>50</option><option selected>100</option><option>250</option><option>500</option>
        </select>
      </label>
      <div class="pager">
        <button id="prevPage">◀</button>
        <span id="pageInfo" class="muted"></span>
        <button id="nextPage">▶</button>
      </div>
      <div class="right">
        <button id="exportCSV">Export CSV (selected)</button>
        <label class="btn-file">Import CSV<input type="file" id="importCSV" accept=".csv,text/csv"></label>
        <button id="pushSheet" <?= $canEdit ? '' : 'disabled' ?>>Push → Google Sheet</button>
        <button id="pullSheet" class="secondary" <?= $canEdit ? '' : 'disabled' ?>>Pull from Google Sheet</button>
      </div>
    </div>

    <div class="toolbar">
      <div class="left">
        <button type="button" id="selectAll">Select all</button>
        <button type="button" id="clearSel">Clear</button>
        <button type="button" id="undoBulk" disabled>Undo last bulk paste</button>
      </div>
      <div class="right">
        <button type="button" id="copySrc">Copy source</button>
        <button type="button" id="copyTgt">Copy target</button>
        <button type="button" id="pasteTgt" class="secondary" <?= $canEdit ? '' : 'disabled' ?>>Paste → target</button>
        <button type="button" id="saveEdits" <?= $canEdit ? '' : 'disabled' ?>>Save</button>
        <a href="export.php?csrf=<?=h($_SESSION['csrf'])?>" class="button">Download XLIFF</a>
      </div>
    </div>

    <form id="editForm" method="post">
      <input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>">
      <input type="hidden" name="action" value="save_edits">
      <div class="table-wrap">
        <table class="grid" id="xliffTable">
          <thead><tr>
            <th data-resizable style="width:42px"><input type="checkbox" id="selAll"></th>
            <th data-resizable style="width:18%">ID</th>
            <th data-resizable>Source</th>
            <th data-resizable style="width:40%">Target</th>
            <th data-resizable style="width:64px">↻</th>
          </tr></thead>
          <tbody>
            <?php $targetsSaved = $_SESSION['targets'] ?? [];
              foreach ($parsed['units'] as $idx => $u):
                $id=$u['id']; $src=$u['source']; $tgt=$targetsSaved[$id] ?? ($u['target'] ?? '');
            ?>
            <tr data-row="<?= $idx ?>">
              <td><input type="checkbox" class="rowSel"></td>
              <td class="idcell"><?=h($id)?></td>
              <td class="mono src"><?= $src ?></td>
              <td><textarea name="target[<?=h($id)?>]" rows="2" class="tgt" data-id="<?=h($id)?>" <?= $canEdit ? '' : 'readonly' ?>><?= $tgt ?></textarea></td>
              <td><button type="button" class="btn-row" data-row="<?= $idx ?>" <?= $canEdit ? '' : 'disabled' ?>>↻</button></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <p class="muted">Hotkeys: <code>Alt+T</code> translate current row, <code>Alt+Enter</code> translate & focus next.</p>
    </form>
  </section>
  <?php endif; ?>
</main>

<!-- Clipboard modal -->
<div id="clipModal" class="modal" hidden>
  <div class="modal-content">
    <h3 id="clipTitle">Clipboard</h3>
    <textarea id="clipArea" rows="14" class="w100"></textarea>
    <div class="modal-actions">
      <button id="clipClose">Close</button>
      <button id="clipCopy" class="primary">Copy</button>
      <button id="clipPaste" class="primary" hidden>Use as paste</button>
    </div>
  </div>
</div>

<script src="assets/js/app.js"></script>
</body></html>
