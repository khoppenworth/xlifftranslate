<?php
require_once __DIR__ . '/auth.php';
function rl_file($bucket,$ip){ $d=rtrim(cfg()['rate_limit']['storage_dir'] ?? sys_get_temp_dir(),'/'); return $d."/xliff_rl_".preg_replace('/[^a-z0-9_\-]/i','_',$bucket)."_".preg_replace('/[^a-z0-9_\-:\.]/i','_',$ip).".json"; }
function rate_limit_allow($bucket,$capacity,$refillPerMin){
  $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown'; $f = rl_file($bucket,$ip);
  $now = microtime(true);
  $state = ['tokens'=>$capacity,'updated'=>$now];
  if (is_file($f)) { $j=@file_get_contents($f); if ($j) $state = json_decode($j,true) ?: $state;
    $elapsed = max(0.0, $now - ($state['updated'] ?? $now));
    $state['tokens'] = min($capacity, ($state['tokens'] ?? $capacity) + $elapsed * ($refillPerMin/60.0));
    $state['updated'] = $now;
  }
  if ($state['tokens'] < 1.0) { @file_put_contents($f,json_encode($state)); return false; }
  $state['tokens'] -= 1.0; @file_put_contents($f,json_encode($state)); return true;
}
