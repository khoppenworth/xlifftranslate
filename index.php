<?php
session_start();
require_once __DIR__ . '/xliff_lib.php';

// Basic CSRF
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
                $info = 'File loaded. Found ' . count($parsed['units']) . ' units.';
            } catch (Throwable $e) {
                $err = 'Parse error: ' . htmlspecialchars($e->getMessage());
            }
        }
    } elseif ($_POST['action'] === 'save_edits' && $parsed) {
        $targets = $_POST['target'] ?? [];
        $_SESSION['targets'] = $targets; // keep temp
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
  <h1>XLIFF Table Translate</h1>

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
      <button id="autoTranslate">Auto-translate all (Google)</button>
      <small class="muted">Requires Google Translation API key in <code>config.php</code></small>
    </div>

    <form id="editForm" method="post">
      <input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>">
      <input type="hidden" name="action" value="save_edits">
      <div class="table-wrap">
        <table class="grid">
          <thead>
            <tr><th style="width:18%">ID</th><th>Source</th><th>Target (editable)</th></tr>
          </thead>
          <tbody>
            <?php 
              $targetsSaved = $_SESSION['targets'] ?? [];
              foreach ($parsed['units'] as $u): 
                $id = $u['id'];
                $src = $u['source'];
                $tgt = $targetsSaved[$id] ?? ($u['target'] ?? '');
            ?>
              <tr>
                <td><?=h($id)?></td>
                <td class="mono"><?= $src ?></td>
                <td>
                  <textarea name="target[<?=h($id)?>]" rows="2"><?= $tgt ?></textarea>
                </td>
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
    <p>Tip: Inline tags inside source (e.g., <code>&lt;g&gt;</code>, <code>&lt;x&gt;</code>) are preserved. Targets accept plain text or inline XML.</p>
  </footer>
</div>
<script src="assets/js/app.js"></script>
</body>
</html>
