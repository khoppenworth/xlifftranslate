<?php
session_start();
function cfg(){ static $c=null; if($c===null){ $c = require __DIR__ . '/config.php'; } return $c; }
function ensure_csrf(){ if(empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16)); return $_SESSION['csrf']; }
function require_login($role='viewer'){ /* Hook for real auth; for now everyone is allowed */ ensure_csrf(); return true; }