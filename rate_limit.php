<?php
if(!function_exists('rate_limit_allow')){
  function rate_limit_allow($bucket, $cap=60, $per_minute=60){
    $now = time();
    $key = 'rl_'.$bucket;
    $state = $_SESSION[$key] ?? ['tokens'=>$cap, 'ts'=>$now];
    $elapsed = max(0, $now - $state['ts']);
    $state['tokens'] = min($cap, $state['tokens'] + ($elapsed * $per_minute)/60.0);
    $state['ts'] = $now;
    if ($state['tokens'] >= 1){
      $state['tokens'] -= 1;
      $_SESSION[$key] = $state;
      return true;
    }
    $_SESSION[$key] = $state;
    return false;
  }
}