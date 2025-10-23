<?php
session_start();
require_once __DIR__ . '/auth.php';
require_login();
require_once __DIR__ . '/xliff_lib.php';

if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));

$err = '';
$info = '';
$parsed = $_SESSION['parsed'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
    if ($_POST['action'] === 'upload') {
        if (!isset($_FILES['xliff']) || $_FILES['xliff']['error'] !== UPLOAD_ERR_OK) {
            $err = 'Please upload a valid XLIFF file.';
        } else {
            $data = file_get_contents($_FILES['xliff']['tmp_name']);
            try {
                $parsed = parse_xliff($data);
                $_SESSION['parsed'] = $parsed;
                $_SESSION['targets'] = [];
                $info = 'File loaded. Found ' . count($parsed['units']) . ' units.';
            } catch (Throwable $e) {
                $err = 'Parse error: ' . htmlspecialchars($e->getMessage());
            }
        }
    } elseif ($_POST['action'] === 'save_edits' && $parsed) {
        $targets = $_POST['target'] ?? [];
        $_SESSION['targets'] = $targets;
        $info = 'Edits saved locally (not exported yet).';
    }
}

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>XLIFF Table Translate</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="wrap">
  <header class="topbar">
    <div class="brand">XLIFF Table Translate</div>
    <nav class="nav">
      <a href="index.php">Home</a>
      <a href="health.php" target="_blank">Server check</a>
      <a href="logout.php">Logout</a>
    </nav>
  </header>

  <?php if ($err): ?><div class="alert alert-error"><?=h($err)?></div><?php endif; ?>
  <?php if ($info): ?><div class="alert alert-info"><?=h($info)?></div><?php endif; ?>

  <section class="card">
    <h2>1) Upload XLIFF</h2>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>">
      <input type="hidden" name="action" value="upload">
      <input type="file" name="xliff" accept=".xlf,.xliff,.xml" required>
      <button type="submit">Load</button>
    </form>
  </section>

  <?php if ($parsed): 
        $srcLang = $parsed['sourceLang'] ?: '';
        $trgLang = $parsed['targetLang'] ?: '';
  ?>
  <section class="card">
    <h2>2) Review & Translate</h2>
    <div class="controls">
      <label>Source language:
        <input type="text" id="sourceLang" value="<?=h($srcLang)?>" placeholder="en-US">
      </label>
      <label>Target language:
        <input type="text" id="targetLang" value="<?=h($trgLang)?>" placeholder="fr-FR">
      </label>
      <button id="autoTranslate">Auto-translate ALL</button>
      <small class="muted">Bulk copy/paste supported via the toolbar below.</small>
    </div>

    <div class="toolbar">
      <button type="button" id="selectAll">Select all rows</button>
      <button type="button" id="clearSel">Clear selection</button>
      <span class="sep"></span>
      <button type="button" id="copySrc">Copy source (selected)</button>
      <button type="button" id="copyTgt">Copy target (selected)</button>
      <button type="button" id="pasteTgt">Paste → target (fill down)</button>
    </div>

    <form id="editForm" method="post">
      <input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>">
      <input type="hidden" name="action" value="save_edits">
      <div class="table-wrap">
        <table class="grid" id="xliffTable">
          <thead>
            <tr>
              <th style="width:36px"><input type="checkbox" id="selAll"></th>
              <th style="width:14%">ID</th>
              <th>Source</th>
              <th style="width:40%">Target (editable)</th>
              <th style="width:10%">Translate</th>
            </tr>
          </thead>
          <tbody>
            <?php 
              $targetsSaved = $_SESSION['targets'] ?? [];
              foreach ($parsed['units'] as $idx => $u): 
                $id = $u['id'];
                $src = $u['source'];
                $tgt = $targetsSaved[$id] ?? ($u['target'] ?? '');
            ?>
              <tr data-row="<?= $idx ?>">
                <td><input type="checkbox" class="rowSel"></td>
                <td class="idcell"><?=h($id)?></td>
                <td class="mono src"><?= $src ?></td>
                <td><textarea name="target[<?=h($id)?>]" rows="2" class="tgt"><?= $tgt ?></textarea></td>
                <td><button type="button" class="btn-row" data-row="<?= $idx ?>">↻</button></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="actions">
        <button type="submit">Save edits</button>
        <a href="export.php?csrf=<?=h($_SESSION['csrf'])?>" class="btn">3) Download translated XLIFF</a>
      </div>
    </form>
  </section>
  <?php endif; ?>

  <footer>
    <p>Tips: Use the checkboxes to select rows. <em>Copy</em> gathers values into lines. <em>Paste → target</em> splits clipboard by line and fills down.</p>
  </footer>
</div>
<script src="assets/js/app.js"></script>
</body>
</html>
