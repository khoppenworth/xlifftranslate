<?php
session_start();
function cfg() {
    static $cfg;
    if (!$cfg) {
        $file = __DIR__ . '/config.php';
        $cfg = require $file;
    }
    return $cfg;
}
function require_login() {
    $cfg = cfg();
    $need = trim((string)($cfg['login_userpass'] ?? ''));
    if ($need === '') return;
    if (!isset($_SESSION['user'])) {
        header('Location: login.php');
        exit;
    }
}
