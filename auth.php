<?php
if (session_status() === PHP_SESSION_NONE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? 80) == 443);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}
function cfg() { static $cfg; if (!$cfg) $cfg = require __DIR__ . '/config.php'; return $cfg; }
function set_user($u,$role){ $_SESSION['user']=$u; $_SESSION['role']=$role; }
function current_user(){ return $_SESSION['user'] ?? null; }
function current_role(){ return $_SESSION['role'] ?? 'viewer'; }
function require_login($minRole='viewer'){
  $cfg = cfg();
  if (!empty($cfg['enable_oidc'])) {
    $hdr = strtoupper(trim($cfg['oidc_username_header'] ?? 'HTTP_X_REMOTE_USER'));
    if (!empty($_SERVER[$hdr])) {
      $user = $_SERVER[$hdr];
      $role = ($cfg['oidc_role_map'][$user] ?? 'editor');
      set_user($user, $role);
    } else { header('HTTP/1.1 401 Unauthorized'); echo "SSO required"; exit; }
  }
  if (!isset($_SESSION['user'])) { header('Location: login.php'); exit; }
  $order = ['viewer'=>1,'editor'=>2,'admin'=>3];
  if (($order[current_role()] ?? 0) < ($order[$minRole] ?? 0)) { header('HTTP/1.1 403 Forbidden'); echo "Insufficient role"; exit; }
}
