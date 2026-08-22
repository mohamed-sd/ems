<?php
/* حزامٌ سلبيّ للمقياسِ المُتبِعِ للقشرة: أيرسُب إن نزعت القشرةُ الرأسَ فعلًا؟ */
$shell = (string) file_get_contents('C:/wamp64/www/ems/includes/fin_analysis_shell.php');
$src   = (string) file_get_contents('C:/wamp64/www/ems/Finance/fin_equity_stmt.php');
$chk = function ($src,$shell) {
    $via = strpos($src,'fin_analysis_shell.php')!==false && strpos($shell,'page_header.php')!==false;
    return !(strpos($src,'page_header.php')===false && !$via);   /* true = يمرّ */
};
printf("  %s الحيُّ يمرّ\n", $chk($src,$shell)?'✔':'✘');
printf("  %s وقشرةٌ بلا رأسٍ تُمسَك\n", !$chk($src,str_replace('page_header.php','zz_none',$shell))?'✔':'✘');
printf("  %s وشاشةٌ لا قشرةَ لها ولا رأسَ تُمسَك\n", !$chk('<?php include "insidebar.php";',$shell)?'✔':'✘');
$ok = $chk($src,$shell) && !$chk($src,str_replace('page_header.php','zz_none',$shell)) && !$chk('<?php include "insidebar.php";',$shell);
exit($ok?0:1);
