<?php
require_once __DIR__ . '/auth.php';
function rl_file($bucket,$ip){ $d=rtrim(cfg()['rate_limit']['storage_dir'] ?? sys_get_temp_dir(),'/'); return $d."/xliff_v6_".preg_replace('/[^a-z0-9_\-]/i','_',$bucket)."_".preg_replace('/[^a-z0-9_\-:\.]/i','_',$ip).".json"; }
function rate_limit_allow($bucket,$capacity,$refillPerMin){
  $ip=$_SERVER['REMOTE_ADDR'] ?? 'unknown'; $f=rl_file($bucket,$ip); $now=microtime(true);
  $st=['tokens'=>$capacity,'updated'=>$now]; if (is_file($f)){ $j=@file_get_contents($f); if($j) $st=json_decode($j,true) ?: $st;
    $elapsed=max(0.0, $now-($st['updated']??$now)); $st['tokens']=min($capacity, ($st['tokens']??$capacity)+$elapsed*($refillPerMin/60.0)); $st['updated']=$now;}
  if ($st['tokens']<1.0){ @file_put_contents($f,json_encode($st)); return false; } $st['tokens']-=1.0; @file_put_contents($f,json_encode($st)); return true;
}
