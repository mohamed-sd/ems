<?php
/**
 * tests/nav_home_http_proof.php
 * برهانُ HTTP لباب «① الرئيسية» (الدستور §6 · UX-01 §4) — 2026-07-27:
 * بعد نقل الرابط من ثابتٍ في insidebar.php إلى صفٍّ في nav_items لكل دور،
 * يثبت لكل دورٍ مختبَر: «الرئيسية» تظهر **مرةً واحدة** (لا ازدواجَ مصدر)،
 * و**أولَ** عنصرٍ قبل المراسلات (لا تهبط تحتها)، وتشير إلى **لوحته هو**
 * (عامةً كانت أو مخصصة) بلا تحويلٍ وسيط — فيتلوّن رابطُها النشط.
 * التشغيل: php tests/nav_home_http_proof.php   (يتطلب Apache حيًّا)
 */
$BASE='http://localhost/ems'; $TMP=sys_get_temp_dir();
function req($url,$jar,$post=null){$ch=curl_init($url);curl_setopt_array($ch,array(
 CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_COOKIEJAR=>$jar,
 CURLOPT_COOKIEFILE=>$jar,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_TIMEOUT=>40));
 if($post!==null){curl_setopt($ch,CURLOPT_POST,true);curl_setopt($ch,CURLOPT_POSTFIELDS,http_build_query($post));}
 $raw=curl_exec($ch);$hs=curl_getinfo($ch,CURLINFO_HEADER_SIZE);$code=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
 return array($code,substr($raw,0,$hs),substr($raw,$hs));}
$cases=array(
 array('u'=>'مديرمالي','board'=>'Finance/cfo_daily_board_fin.php'),
 array('u'=>'صيانة',   'board'=>'Maintenance/dashboard_mnt.php'),
 array('u'=>'بلاغات',  'board'=>'main/role_board.php'),
);
$pass=0;$fail=0;
foreach($cases as $c){
  $jar=$TMP.'/hp_'.md5($c['u']).'.txt'; @unlink($jar);
  list($x,$h,$b)=req($BASE.'/login.php',$jar);
  preg_match('~name="csrf_token"\s+value="([^"]+)"~',$b,$m);
  req($BASE.'/login.php',$jar,array('username'=>$c['u'],'password'=>'12345678','csrf_token'=>isset($m[1])?$m[1]:''));
  list($code,$h,$b)=req($BASE.'/'.$c['board'],$jar);
  if($code!==200){echo "  [FAIL] {$c['u']}: HTTP $code\n";$fail++;continue;}
  $nHome = preg_match_all('~<a[^>]*href="([^"]*)"[^>]*>(?:(?!</a>).)*?الرئيسية~su',$b,$am);
  $pHome = strpos($b,'>الرئيسية<'); if($pHome===false) $pHome = strpos($b,'الرئيسية');
  $pChat = strpos($b,'sidebarChatLink');
  $okOne   = ($nHome === 1);
  $okHref  = $okOne && strpos($am[1][0], basename($c['board'])) !== false;
  $okOrder = ($pHome !== false && $pChat !== false && $pHome < $pChat);
  if($okOne && $okHref && $okOrder){
    echo "  [PASS] {$c['u']}: «الرئيسية» أولًا → {$am[1][0]} · ثم المراسلات\n"; $pass++;
  } else {
    echo "  [FAIL] {$c['u']}: عدد=$nHome · href=".($okOne?$am[1][0]:'-')." · الترتيب=".($okOrder?'سليم':'مقلوب')."\n"; $fail++;
  }
}
echo "\nالنتيجة: $pass ناجح · $fail فاشل\n";
