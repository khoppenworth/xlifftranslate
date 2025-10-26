<?php
require_once __DIR__ . '/auth.php';
$cfg = cfg();
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
if (!empty($cfg['enable_oidc'])) { header('Location: index.php'); exit; }
$err='';
if ($_SERVER['REQUEST_METHOD']==='POST' && hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
  $u = $_POST['username'] ?? ''; $p = $_POST['password'] ?? '';
  $single = trim((string)($cfg['login_userpass'] ?? '')); $bcrypt = !empty($cfg['passwords_are_bcrypt']);
  if ($single!=='') {
    [$eu,$ep] = array_pad(explode(':',$single,2),2,'');
    $ok = $bcrypt ? (password_verify($p,$ep) && $u===$eu) : ($u===$eu && $p===$ep);
    if ($ok) { set_user($u,'admin'); header('Location: index.php'); exit; } else $err='Invalid credentials';
  } else {
    $users = $cfg['users'] ?? [];
    if (isset($users[$u])) {
      $stored = $users[$u]['pass'] ?? ''; $role = $users[$u]['role'] ?? 'editor';
      $ok = $bcrypt ? password_verify($p,$stored) : ($p===$stored);
      if ($ok) { set_user($u,$role); header('Location: index.php'); exit; }
    }
    $err='Invalid credentials';
  }
}
?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Sign in · XLIFF</title><link rel="stylesheet" href="assets/css/style.css"></head>
<body class="theme-auto"><div class="wrap narrow">
<div class="card center">
  <h1 class="brand-lg">XLIFF Table Translate</h1>
  <?php if ($err): ?><div class="alert alert-error"><?=htmlspecialchars($err,ENT_QUOTES)?></div><?php endif; ?>
  <form method="post" class="stack"><input type="hidden" name="csrf" value="<?=htmlspecialchars($_SESSION['csrf'],ENT_QUOTES)?>">
    <label>Username<input type="text" name="username" required></label>
    <label>Password<input type="password" name="password" required></label>
    <button class="primary w100">Sign in</button>
  </form>
</div></div></body></html>
