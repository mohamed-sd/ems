<?php
/**
 * tools/injint01/skew_impact.php — تدقيقُ أثرِ نافذةِ انحرافِ الساعة (EXE-01 §5)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **الأمرُ يطلب حصرَ النافذةِ بحدَّيها ولا يُثبِّت رقمًا مسبقًا.** ولا مصدرَ
 *   للنافذةِ في القاعدة — **فتُستخرَج من تواريخِ الالتزام** (‏GAP-71 المسجَّلة:
 *   «تواريخُ غيرُ رتيبة … أيُّ ترتيبٍ بساعةِ الحائطِ يكذب»). فالترتيبُ
 *   الطوبولوجيُّ يكشف كلَّ ختمٍ ارتفع عن ابنِه — وحدّا النافذةِ منها.
 *
 * ⛔ **والأساسُ الزمنيُّ يُسوَّى قبل أيِّ مقارنة**: التطبيقُ يكتب بـ`NOW()`
 *   المحلّيّةِ (+180د هنا) وحدّا النافذةِ بـUTC. فمن قارنهما خامَّين أزاح
 *   النافذةَ ثلاثَ ساعاتٍ فأدخل وأخرج صفوفًا بلا سبب.
 *
 * ⛔ **ولا يُصحَّح طابعٌ تاريخيٌّ تلقائيًّا** (‏§5·2 و§9): هذه الأداةُ **تستخرج
 *   وتُصنِّف فقط**. والقيدُ الماليُّ يُعرَض منفصلًا ولا يُمَسُّ إلا بقرارٍ
 *   محاسبيٍّ موثَّق.
 *
 * التشغيل: php tools/injint01/skew_impact.php --from='YYYY-MM-DD HH:MM:SS' --to='...'
 *          (الحدّانِ بـUTC — وتُسوَّى داخلًا)
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8'); mysqli_report(MYSQLI_REPORT_OFF);
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';

$FROM = ''; $TO = '';
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--from=(.+)$/', $a, $m)) { $FROM = trim($m[1], "'\""); }
    if (preg_match('/^--to=(.+)$/', $a, $m))   { $TO   = trim($m[1], "'\""); }
}
if ($FROM === '' || $TO === '') { exit("⛔ الحدّانِ إلزاميّان — ولا نافذةَ مفترَضة.\n   --from='UTC' --to='UTC'\n"); }

$h = ems_env('DB_HOST'); $p = 3306;
if (strpos($h, ':') !== false) { list($h, $p) = explode(':', $h); $p = (int) $p; }
$c = new mysqli($h, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $p);
if ($c->connect_errno) { exit('تعذّر الاتصال: ' . $c->connect_error . "\n"); }
$c->set_charset('utf8mb4');
$one = function ($q) use ($c) { $r = $c->query($q); if (!$r) { return null; } $x = $r->fetch_row(); return $x ? $x[0] : null; };
$rows = function ($q) use ($c) { $r = $c->query($q); $o = array(); if (!$r) { return $o; } while ($x = $r->fetch_assoc()) { $o[] = $x; } return $o; };
$tex = function ($t) use ($c) { $r = $c->query("SHOW TABLES LIKE '" . $c->real_escape_string($t) . "'"); return $r && $r->num_rows > 0; };

/* ═══ ⓪ تسويةُ الأساسِ الزمنيّ — الحدّانِ UTC والأعمدةُ محلّيّة ═══════════ */
$off = (int) round((strtotime($one('SELECT NOW()')) - strtotime($one('SELECT UTC_TIMESTAMP()'))) / 60);
$fromL = gmdate('Y-m-d H:i:s', strtotime($FROM) + $off * 60);
$toL   = gmdate('Y-m-d H:i:s', strtotime($TO)   + $off * 60);
printf("◆ النافذة UTC   : %s .. %s\n", $FROM, $TO);
printf("◆ إزاحةُ التطبيق : %+d دقيقة\n", $off);
printf("◆ النافذة محلّيًّا: %s .. %s\n\n", $fromL, $toL);

/* ═══ ① الفئاتُ الستُّ كما نصَّ §5·2 — ولكلٍّ حكمُها ═══════════════════ */
$CAT = array(
 array('cat'=>'قيدٌ ماليّ أو تاريخُ ترحيل','tbl'=>'fin_journal_entries','col'=>'created_at',
       'rule'=>'أخطرُها — لا يُمَسُّ إلا بقرارٍ محاسبيٍّ موثَّق · ويُعرَض منفصلًا'),
 array('cat'=>'قيدٌ ماليّ — الأسطر','tbl'=>'fin_journal_lines','col'=>'created_at',
       'rule'=>'تابعٌ لقيدِه — لا حكمَ مستقلّ'),
 array('cat'=>'واقعةٌ ماليّة','tbl'=>'fin_financial_events','col'=>'created_at',
       'rule'=>'يُوسَم ويُستبعَد من أيِّ إعادةٍ حتى يُحكَم فيه'),
 array('cat'=>'حدثُ أعمال','tbl'=>'ems_business_events','col'=>'created_at',
       'rule'=>'يُوسَم ويُستبعَد من أيِّ إعادةٍ حتى يُحكَم فيه'),
 array('cat'=>'أثرُ تدقيق','tbl'=>'permission_audit_events','col'=>'at',
       'rule'=>'لا يُصحَّح — يُوسَم بأنَّ طابعَه داخلَ نافذةِ انحراف'),
 array('cat'=>'تسليمُ حدث','tbl'=>'ems_event_deliveries','col'=>'updated_at',
       'rule'=>'يُراجَع أثرُه على المهلِ وإعادةِ المحاولة'),
 array('cat'=>'مهمّةٌ مجدولة','tbl'=>'ems_job_schedule','col'=>'updated_at',
       'rule'=>'تُعاد جدولتُها إن لزم — بلا مساسٍ بتاريخِها التاريخيّ'),
);

$total = 0; $out = array();
printf("%-30s %-26s %10s  %s\n", 'الفئة', 'الجدول', 'صفوف', 'الحكم');
echo str_repeat('-', 108) . "\n";
foreach ($CAT as $x) {
    if (!$tex($x['tbl'])) { printf("%-30s %-26s %10s  ⛔ لا جدول\n", $x['cat'], $x['tbl'], '—'); continue; }
    $has = $one("SELECT COUNT(*) FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$x['tbl']}' AND COLUMN_NAME='{$x['col']}'");
    if (!$has) { printf("%-30s %-26s %10s  ⛔ لا عمودَ %s\n", $x['cat'], $x['tbl'], '—', $x['col']); continue; }
    $n = (int) $one("SELECT COUNT(*) FROM `{$x['tbl']}` WHERE `{$x['col']}` BETWEEN '$fromL' AND '$toL'");
    $total += $n;
    printf("%-30s %-26s %10d  %s\n", $x['cat'], $x['tbl'], $n, mb_substr($x['rule'], 0, 44));
    $out[] = array_merge($x, array('rows' => $n));
}

printf("\n◆ إجماليُّ الصفوفِ في النافذة: %d\n", $total);

/* ═══ ② القيدُ الماليُّ منفصلًا — كما يفرض §5·2 ═══════════════════════ */
echo "\n══ القيدُ الماليُّ داخلَ النافذة — عرضٌ منفصل ══\n";
$je = $rows("SELECT COUNT(*) n, MIN(created_at) f, MAX(created_at) l,
                    ROUND(SUM((SELECT COALESCE(SUM(debit),0) FROM fin_journal_lines l WHERE l.entry_id=e.id)),2) d
               FROM fin_journal_entries e WHERE e.created_at BETWEEN '$fromL' AND '$toL'");
if ($je && (int) $je[0]['n'] > 0) {
    printf("  قيود=%s · من %s إلى %s · مدين=%s\n", $je[0]['n'], $je[0]['f'], $je[0]['l'], $je[0]['d']);
    echo "  ⛔ **لا يُمَسُّ طابعُها تلقائيًّا** — قرارٌ محاسبيٌّ موثَّقٌ أو لا شيء.\n";
} else {
    echo "  ✔ لا قيدَ ماليًّا كُتب داخلَ النافذة — فأخطرُ الفئاتِ خاليةٌ بالقياس.\n";
}

/* ═══ ③ الميزانُ داخلَ النافذةِ وخارجَها — أَتأثّر؟ ═══════════════════ */
echo "\n══ سلامةُ الميزان ══\n";
printf("  فرقُ المدينِ والدائنِ كلّيًّا : %s\n", $one('SELECT ROUND(SUM(debit)-SUM(credit),2) FROM fin_journal_lines'));
printf("  فرقُه للقيودِ داخلَ النافذة  : %s\n",
    $one("SELECT ROUND(COALESCE(SUM(l.debit),0)-COALESCE(SUM(l.credit),0),2)
            FROM fin_journal_lines l JOIN fin_journal_entries e ON e.id=l.entry_id
           WHERE e.created_at BETWEEN '$fromL' AND '$toL'"));

file_put_contents($ROOT . '/docs/injint01/skew_impact.json', json_encode(
    array('window_utc' => array($FROM, $TO), 'window_local' => array($fromL, $toL),
          'offset_min' => $off, 'total_rows' => $total, 'categories' => $out),
    JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
echo "\n  التفصيل: docs/injint01/skew_impact.json\n";
echo "⛔ ولم يُصحَّح طابعٌ واحد — استخراجٌ وتصنيفٌ فقط.\n";
