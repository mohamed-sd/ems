<?php
$base='http://localhost/ems/';
$jar=sys_get_temp_dir().'/ems_t2_'.getmypid().'.cookie';
function req($u,$p=null,$j=null,$f=true){$ch=curl_init($u);
 curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>1,CURLOPT_HEADER=>1,CURLOPT_COOKIEJAR=>$j,
  CURLOPT_COOKIEFILE=>$j,CURLOPT_FOLLOWLOCATION=>$f,CURLOPT_TIMEOUT=>25]);
 if($p!==null){curl_setopt($ch,CURLOPT_POST,1);curl_setopt($ch,CURLOPT_POSTFIELDS,$p);}
 $r=curl_exec($ch);$hs=curl_getinfo($ch,CURLINFO_HEADER_SIZE);$c=curl_getinfo($ch,CURLINFO_HTTP_CODE);
 curl_close($ch);return ['code'=>$c,'head'=>substr($r,0,$hs),'body'=>substr($r,$hs)];}
$a=req($base.'login.php',null,$jar);
preg_match('~name="csrf_token" value="([^"]+)"~',$a['body'],$m);
$b=req($base.'login.php',http_build_query(['username'=>'مراجعة الشاشات','password'=>'12345678','csrf_token'=>$m[1]??'']),$jar);
$ok=(strpos($b['body'],'تسجيل الدخول')===false);
echo "① الدخول HTTP={$b['code']} ⇒ ".($ok?'✔ نجح':'✘ رُدَّ')."\n";
if(!$ok && preg_match('~(المحاولات[^<]{0,70}|غير صحيح[^<]{0,40}|غير مرتبط[^<]{0,50})~u',$b['body'],$e))
  echo "   السبب: ".trim(strip_tags($e[1]))."\n";
if($ok){
  preg_match('~<title>([^<]*)</title>~',$b['body'],$t); echo "   الصفحة: ".($t[1]??'?')."\n";
  /* عدُّ روابطِ السايدبار */
  preg_match_all('~href="\.\./([^"]+\.php)~',$b['body'],$L);
  echo "② روابطُ السايدبارِ في الصفحة = ".count(array_unique($L[1]))."\n";
}
file_put_contents(__DIR__.'/.jar',$jar);
