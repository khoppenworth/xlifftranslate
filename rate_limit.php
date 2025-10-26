<?php
require_once __DIR__ . '/auth.php';
function rl_getfile($bucket, $ip){
  $cfg = cfg();
  $dir = rtrim($cfg['rate_limit']['storage_dir'] ?? sys_get_temp_dir(), '/');
  $safeBucket = preg_replace('/[^a-z0-9_\-]/i','_', $bucket);
  $safeIp = preg_replace('/[^a-z0-9_\-:\.]/i','_', $ip);
  return $dir . "/xliff_rl_" . $safeBucket . "_" . $safeIp . ".json";
}
function rate_limit_allow($bucket, $capacity, $refillPerMinute){
  $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
  $file = rl_getfile($bucket, $ip);
  $now = microtime(true);
  $state = ['tokens'=>$capacity, 'updated'=>$now];
  if (is_file($file)) {
    $json = @file_get_contents($file);
    if ($json) { $state = json_decode($json, true) ?: $state; }
    $elapsed = max(0.0, $now - ($state['updated'] ?? $now));
    // refill tokens
    $refillPerSec = $refillPerMinute / 60.0;
    $state['tokens'] = min($capacity, ($state['tokens'] ?? $capacity) + $elapsed * $refillPerSec);
    $state['updated'] = $now;
  }
  if ($state['tokens'] < 1.0) {
    // Save and deny
    @file_put_contents($file, json_encode($state));
    return false;
  }
  $state['tokens'] -= 1.0;
  @file_put_contents($file, json_encode($state));
  return true;
}
