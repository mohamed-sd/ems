<?php
require __DIR__.'/../config.php';
require __DIR__.'/../includes/permissions_helper.php';
$r = $conn->query("SHOW COLUMNS FROM gov_role_profiles"); $c=array(); while($x=$r->fetch_assoc()){$c[]=$x['Field'];} echo "gov_role_profiles: ".implode(', ',$c)."\n\n";
// كل مستخدم فعّال: احسب can_view على 114 و115 بهويةٍ حقيقية
$us = $conn->query("SELECT id,username,role,status FROM users WHERE status='active' ORDER BY role+0, id");
$agg = array();
while ($u = $us->fetch_assoc()) {
  $_SESSION['user'] = array('id'=>intval($u['id']), 'role'=>$u['role']);
  $p114 = get_module_permissions($conn, 114);
  $p115 = get_module_permissions($conn, 115);
  $k = $u['role'];
  if (!isset($agg[$k])) $agg[$k] = array('n'=>0,'v114'=>0,'a114'=>0,'v115'=>0,'a115'=>0,'sample'=>$u['username']);
  $agg[$k]['n']++;
  $agg[$k]['v114'] += $p114['can_view']?1:0;
  $agg[$k]['a114'] += $p114['can_add']?1:0;
  $agg[$k]['v115'] += $p115['can_view']?1:0;
  $agg[$k]['a115'] += $p115['can_add']?1:0;
}
echo sprintf("%-5s %-4s %-9s %-9s %-9s %-9s\n",'role','n','view114','add114','view115','add115');
foreach ($agg as $role=>$a) {
  echo sprintf("%-5s %-4d %-9s %-9s %-9s %-9s\n", $role, $a['n'], $a['v114'].'/'.$a['n'], $a['a114'].'/'.$a['n'], $a['v115'].'/'.$a['n'], $a['a115'].'/'.$a['n']);
}
