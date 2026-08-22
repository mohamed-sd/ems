<?php
/* حزامٌ سلبيّ: أيرسُب المقياسُ المُصلَحُ إن نُزع الامتصاصُ فعلًا؟
   ◆ وأولُ محاولةٍ للحزامِ كانت هي العمياء: استبدلتُ الرمزَ بـ«الرمز_REMOVED»
     فبقيت السلسلةُ الأصليةُ جزءًا من الجديدة، فمرَّ المعطوبُ. **الحزامُ نفسُه
     يحتاج حزامًا** — والنزعُ يكون بمحوٍ لا بإلحاق. */
$src = (string) file_get_contents('C:/wamp64/www/ems/inheader.php');
$chk = function ($s) {
    return (strpos($s,'ems_flash_gov') !== false
            && (strpos($s,'EmsAlert') !== false || strpos($s,'emsGovFlash') !== false));
};
$live   = $chk($src);
$noSess = $chk(str_replace('ems_flash_gov','zz_purged_key',$src));       /* نُزع الامتصاص */
$noView = $chk(str_replace(array('EmsAlert','emsGovFlash'),'zz_purged_view',$src)); /* نُزع العرض */
printf("  %s الحيُّ يمرّ\n", $live ? '✔':'✘');
printf("  %s ونزعُ الامتصاصِ يُمسَك\n", !$noSess ? '✔':'✘');
printf("  %s ونزعُ العارضِ يُمسَك\n", !$noView ? '✔':'✘');
exit(($live && !$noSess && !$noView) ? 0 : 1);
