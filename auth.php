<?php
session_start();
function cfg(){ static $c=null; if($c===null){ $c = require __DIR__ . '/config.php'; } return $c; }
function ensure_csrf(){ if(empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16)); return $_SESSION['csrf']; }
function current_user(){ return $_SESSION['user'] ?? null; }
function has_role($need){
  $u = current_user(); if(!$u) return false;
  if ($need==='viewer') return true;
  $rank = ['viewer'=>1,'editor'=>2,'admin'=>3];
  $have = $rank[$u['role'] ?? 'viewer'] ?? 1;
  $req  = $rank[$need] ?? 1;
  return $have >= $req;
}
function require_login($role='viewer'){
  ensure_csrf();
  if (!current_user()){
    header('Location: login.php?redirect='.rawurlencode($_SERVER['REQUEST_URI'] ?? 'index.php'));
    exit;
  }
  if (!has_role($role)){
    http_response_code(403); echo "Forbidden: role '{$role}' required."; exit;
  }
  return true;
}