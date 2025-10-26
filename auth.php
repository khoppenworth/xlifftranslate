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
function cfg() {
    static $cfg;
    if (!$cfg) {
        $file = __DIR__ . '/config.php';
        $cfg = require $file;
    }
    return $cfg;
}
function set_user($username, $role='editor'){
    $_SESSION['user'] = $username;
    $_SESSION['role'] = $role;
}
function current_user(){ return $_SESSION['user'] ?? null; }
function current_role(){ return $_SESSION['role'] ?? 'viewer'; }
function require_login($minRole = 'viewer') {
    $cfg = cfg();

    // OIDC/SSO path
    if (!empty($cfg['enable_oidc'])) {
        $hdr = strtoupper(trim($cfg['oidc_username_header'] ?? 'HTTP_X_REMOTE_USER'));
        if (!empty($_SERVER[$hdr])) {
            $user = $_SERVER[$hdr];
            $roleMap = $cfg['oidc_role_map'] ?? [];
            $role = $roleMap[$user] ?? 'editor';
            set_user($user, $role);
        } else {
            header('HTTP/1.1 401 Unauthorized'); echo "SSO required"; exit;
        }
    }

    // Form login path
    if (!isset($_SESSION['user'])) {
        header('Location: login.php'); exit;
    }

    // Role check
    $order = ['viewer'=>1, 'editor'=>2, 'admin'=>3];
    $have = $order[current_role()] ?? 0;
    $need = $order[$minRole] ?? 0;
    if ($have < $need) {
        header('HTTP/1.1 403 Forbidden'); echo "Insufficient role"; exit;
    }
}
