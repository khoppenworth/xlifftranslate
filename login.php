<?php
require_once __DIR__ . '/auth.php';
$cfg = cfg();
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
if (!empty($cfg['enable_oidc'])) { header('Location: index.php'); exit; }
$err='';
if ($_SERVER['REQUEST_METHOD']==='POST' && hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')){
  $u=$_POST['username']??''; $p=$_POST['password']??'';
  $single=trim((string)($cfg['login_userpass'] ?? '')); $bcrypt=!empty($cfg['passwords_are_bcrypt']);
  if ($single!==''){ [$eu,$ep]=array_pad(explode(':',$single,2),2,''); $ok=$bcrypt?(password_verify($p,$ep)&&$u===$eu):($u===$eu && $p===$ep);
    if ($ok){ set_user($u,'admin'); header('Location: index.php'); exit; } else $err='Invalid credentials';
  } else {
    $users=$cfg['users']??[]; if (isset($users[$u])){ $stored=$users[$u]['pass']??''; $role=$users[$u]['role']??'editor';
      $ok=$bcrypt?password_verify($p,$stored):($p===$stored); if ($ok){ set_user($u,$role); header('Location: index.php'); exit; } }
    $err='Invalid credentials';
  }
}
?><!doctype html><html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign in · <?=htmlspecialchars($cfg['app_name'])?></title>
<link rel="stylesheet" href="assets/css/material.css">
</head><body class="mdui">
<header class="appbar center"><div class="appbar-title"><?=htmlspecialchars($cfg['app_name'])?></div></header>
<main class="container narrow">
  <section class="card">
    <h1 class="h5">Welcome</h1>
    <?php if ($err): ?><div class="chip error"><?=htmlspecialchars($err,ENT_QUOTES)?></div><?php endif; ?>
    <form method="post" class="stack">
      <input type="hidden" name="csrf" value="<?=htmlspecialchars($_SESSION['csrf'],ENT_QUOTES)?>">
      <label class="field"><span>Username</span><input name="username" required></label>
      <label class="field"><span>Password</span><input type="password" name="password" required></label>
      <button class="btn primary w100">Sign in</button>
    </form>
  </section>
</main>
</body></html>
