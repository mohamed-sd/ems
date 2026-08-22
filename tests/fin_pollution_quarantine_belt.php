<?php
/* حزامٌ سلبيّ: أيمسك المقياسُ الجديدُ تلوّثًا يصل **نشطًا**؟
   ◆ القلقُ المشروع: عدُّ النشطِ قد يبدو تخفيفًا. فيُجرَّب حيًّا — يُدسُّ صفٌّ
     نشطٌ ثم يُقاس ثم يُكنس، ولا يُترك أثرٌ. */
error_reporting(E_ALL & ~E_DEPRECATED); mb_internal_encoding('UTF-8');
require_once 'C:/wamp64/www/ems/includes/env.php';
$h=ems_env('DB_HOST');$p=3306; if(strpos($h,':')!==false){list($h,$p)=explode(':',$h);$p=(int)$p;}
$db=new mysqli($h, ems_env('DB_MIGRATOR_USER'), ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'), $p);
$db->set_charset('utf8mb4');
$n = function() use ($db){ return (int)$db->query("SELECT COUNT(*) FROM fin_signal_rules WHERE active=1")->fetch_row()[0]; };
$before = $n();
printf("  %s الأساسُ النظيفُ = 16 (%d)\n", $before===16?'✔':'✘', $before);
$db->query("INSERT INTO fin_signal_rules (company_id,signal_code,name_ar,rule_expr,ratio_code,operator,threshold,streak_periods,severity,destination_ar,cadence,active,created_at)
            VALUES (4,'NEGBELT-01','دخيلٌ نشطٌ للحزام','x','x','lte',NULL,1,'حرج','x','x',1,NOW())");
$after = $n();
printf("  %s ودخيلٌ نشطٌ يُمسَك — صار %d ≠ 16\n", $after===17?'✔':'✘', $after);
$db->query("DELETE FROM fin_signal_rules WHERE signal_code='NEGBELT-01'");
$end = $n();
printf("  %s وكُنس الحزامُ أثرَه — عاد %d\n", $end===16?'✔':'✘', $end);
exit(($before===16 && $after===17 && $end===16) ? 0 : 1);
