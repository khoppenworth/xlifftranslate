<?php
require_once __DIR__ . '/auth.php';
$cfg = cfg();
session_start();
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
    $need = trim((string)($cfg['login_userpass'] ?? ''));
    if ($need !== '') {
        [$u,$p] = array_pad(explode(':', $need, 2), 2, '');
        if ($_POST['username'] === $u && $_POST['password'] === $p) {
            $_SESSION['user'] = $u;
            header('Location: index.php'); exit;
        } else { $error = 'Invalid credentials.'; }
    } else { $_SESSION['user'] = 'guest'; header('Location: index.php'); exit; }
}
?><!doctype html><html><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign in · XLIFF</title><link rel="stylesheet" href="assets/css/style.css"></head>
<body class="theme-auto"><div class="wrap narrow">
  <div class="card center">
    <h1 class="brand-lg">XLIFF Table Translate</h1>
    <?php if ($error): ?><div class="alert alert-error"><?=htmlspecialchars($error, ENT_QUOTES)?></div><?php endif; ?>
    <form method="post" class="stack">
      <input type="hidden" name="csrf" value="<?=htmlspecialchars($_SESSION['csrf'], ENT_QUOTES)?>">
      <label>Username<input type="text" name="username" required></label>
      <label>Password<input type="password" name="password" required></label>
      <button type="submit" class="primary w100">Sign in</button>
    </form>
  </div>
</div></body></html>
