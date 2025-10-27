<?php
require_once __DIR__ . '/auth.php';
$cfg = cfg(); $csrf = ensure_csrf(); $err=null;
if ($_SERVER['REQUEST_METHOD']==='POST'){
  if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')){ $err='Bad CSRF'; }
  else {
    $u = trim($_POST['username'] ?? ''); $p = trim($_POST['password'] ?? '');
    $found=null; foreach(($cfg['users'] ?? []) as $usr){ if(($usr['username'] ?? '')===$u){ $found=$usr; break; } }
    if(!$found){ $err='Invalid credentials'; }
    else {
      if(($found['password'] ?? '')===$p){
        session_regenerate_id(true);
        $_SESSION['user'] = ['username'=>$u, 'role'=>$found['role'] ?? 'viewer'];
        header('Location: '.($_GET['redirect'] ?? 'index.php')); exit;
      } else { $err='Invalid credentials'; }
    }
  }
}
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login · <?=$cfg['app_name']?></title>
<link rel="stylesheet" href="assets/css/material.css">
</head>
<body class="auth">
  <div class="login-card card">
    <h2>Sign in</h2>
    <?php if($err): ?><div class="card warn"><?=$err?></div><?php endif; ?>
    <form method="post">
      <input type="hidden" name="csrf" value="<?=$csrf?>">
      <label class="field"><span>Username</span>
        <input name="username" autocomplete="username" required>
      </label>
      <label class="field"><span>Password</span>
        <input type="password" name="password" autocomplete="current-password" required>
      </label>
      <button class="btn primary" type="submit">Sign in</button>
    </form>
    <p class="muted">Default: <code>admin</code> / <code>admin123</code></p>
  </div>
</body>
</html>