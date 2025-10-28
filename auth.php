<?php
if (session_status() === PHP_SESSION_NONE) {
  $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? 80) == 443);
  session_set_cookie_params([ 'lifetime'=>0,'path'=>'/','domain'=>'','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax' ]);
  session_start();
}
function cfg(){ static $c; if (!$c) $c=require __DIR__.'/config.php'; return $c; }
function set_user($u,$r){ $_SESSION['user']=$u; $_SESSION['role']=$r; }
function current_user(){ return $_SESSION['user'] ?? null; }
function current_role(){ return $_SESSION['role'] ?? 'viewer'; }
function require_login($min='viewer'){
  $cfg = cfg();
  if (!empty($cfg['enable_oidc'])) {
    $hdr=strtoupper(trim($cfg['oidc_username_header'] ?? 'HTTP_X_REMOTE_USER'));
    if (!empty($_SERVER[$hdr])) { $u=$_SERVER[$hdr]; $r=$cfg['oidc_role_map'][$u] ?? 'editor'; set_user($u,$r); }
    else { header('HTTP/1.1 401 Unauthorized'); echo "SSO required"; exit; }
  }
  if (!isset($_SESSION['user'])) { header('Location: login.php'); exit; }
  $ord=['viewer'=>1,'editor'=>2,'admin'=>3];
  if (($ord[current_role()] ?? 0) < ($ord[$min] ?? 0)) { header('HTTP/1.1 403 Forbidden'); echo "Insufficient role"; exit; }
}
