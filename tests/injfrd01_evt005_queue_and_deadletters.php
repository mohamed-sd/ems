<?php
/**
 * tests/injfrd01_evt005_queue_and_deadletters.php
 *   شاهدُ FR-EVT-005 · FR-EVT-007 — النتيجةُ عبرَ الطابور · ولا رسالةَ بلا قرار
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **معياران**: FR-EVT-005 «فحصُ المجدولِ يُخرج **صفرَ مهمةٍ ترجع خارجَ
 *   الطابور**» · و FR-EVT-007 «**صفرُ رسالةٍ ميتةٍ بلا قرارٍ** بعدَ المهلة».
 *
 * ◆ **والمقامُ من المجدولِ الحيّ**: كلُّ مهمةٍ مُعلَنةٍ في `ems_job_schedule` يجب
 *   أن يكون لها أثرٌ في `ems_job_queue` — فمهمةٌ تعمل ولا تمرُّ بالطابور تعود
 *   نتيجتُها خارجَه ولا يراها أحد.
 *
 * ◆ **والمهلةُ عتبةٌ بلا مصدرٍ نافذ**: الدفترُ لا ينصُّ على مهلةِ حسمِ الرسالةِ
 *   الميتة (`Threshold_Source = NEEDS_GOVERNING_SOURCE`). فلا تُخترع مهلةٌ
 *   هنا — **يُقاس صفرُ رسالةٍ بلا حكمٍ مطلقًا**، وهو أشدُّ من أيِّ مهلة.
 *
 * التشغيل: php tests/injfrd01_evt005_queue_and_deadletters.php [--negative]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$db = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($db->connect_errno) { exit("تعذّر الاتصال: {$db->connect_error}\n"); }
$db->set_charset('utf8mb4');

$ok = 0; $bad = 0;
function chk($c, $l, $d = '') {
    global $ok, $bad;
    if ($c) { $ok++; echo "  ✔ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
    else    { $bad++; echo "  ✘ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
}
function n(mysqli $d, $q) { $r = @$d->query($q); return $r ? (int) $r->fetch_row()[0] : -1; }

$neg = in_array('--negative', $argv, true);
$MARK = 999901;

echo "══ FR-EVT-005 · FR-EVT-007 — الطابورُ والرسائلُ الميتة ══\n";

if ($neg) {
    /* ◆ الحزامُ يدسُّ رسالةً ميتةً بلا حكمٍ ثم يكنسها — فيُقاس أن الفحصَ يمسك */
    /* ◆ **الحزامُ يُثبت دسَّه قبلَ أن يقيس** — وإلا صار لا-فعلٍ يُقرأ نجاحًا.
     *   وقد وقع ذلك مرتَين في هذه الجولة: `job_type` محكومٌ بقيدٍ مغلقٍ
     *   (`chk_job_type` بثمانيةِ أنواعٍ لا تاسعَ لها) فرُدَّ `belt_probe`
     *   صامتًا ومرَّ الحزامُ كاذبًا. ⇒ يُستعمل نوعٌ مشروع، **ويُتحقَّق من
     *   وجودِ الصفِّ بعدَ الدسِّ وإلا أُوقِف الحزامُ بصوتٍ عالٍ**. */
    $co = n($db, "SELECT `company_id` FROM `ems_job_queue` LIMIT 1");
    $db->query("INSERT INTO `ems_job_queue`
        (`job_id`,`company_id`,`job_type`,`payload_json`,`state`,`attempts`,`max_attempts`,`created_at`)
        VALUES ({$MARK},{$co},'pilot_monitor','{\"belt\":1}','dead',1,1,NOW())");
    $planted = n($db, "SELECT COUNT(*) FROM `ems_job_queue` WHERE `job_id` = {$MARK}");
    if ($planted !== 1) {
        echo "  ⛔ **تعذّر دسُّ الحزام** — " . $db->error . "\n";
        echo "     وحزامٌ لا يدسُّ شيئًا لا يُثبت شيئًا. أُوقِف.\n";
        exit(1);
    }
    echo "  ◆ دُسَّت رسالةٌ ميتةٌ بلا حكمٍ — **ووجودُها مُثبَتٌ قبلَ القياس**\n";
}

/* ① FR-EVT-005 — كلُّ مهمةٍ مُعلَنةٍ لها أثرٌ في الطابور */
$jobs = n($db, "SELECT COUNT(*) FROM `ems_job_schedule` WHERE `is_active` = 1");
$outside = array();
$r = $db->query("SELECT s.`job_type` FROM `ems_job_schedule` s
                  WHERE s.`is_active` = 1
                    AND NOT EXISTS (SELECT 1 FROM `ems_job_queue` q
                                     WHERE q.`job_type` = s.`job_type`)");
while ($r && $x = $r->fetch_row()) { $outside[] = $x[0]; }
chk(empty($outside), 'FR-EVT-005 · **صفرُ مهمةٍ ترجع خارجَ الطابور**',
    empty($outside) ? "{$jobs} مهمةً نشطةً كلُّها تمرُّ بالطابور"
                    : implode(' · ', $outside));

/* ② ولا مهمةَ نشطةٍ بلا نجاحٍ مسجَّل — الأثرُ يُقرأ لا يُفترَض */
$noRun = n($db, "SELECT COUNT(*) FROM `ems_job_schedule`
                  WHERE `is_active` = 1 AND `last_success_at` IS NULL");
chk($noRun === 0, 'ولكلِّ مهمةٍ نشطةٍ نجاحٌ مسجَّلٌ بزمنِه', "بلا نجاح: {$noRun}");

/* ③ FR-EVT-007 — **صفرُ رسالةٍ ميتةٍ بلا حكم** */
$dead = n($db, "SELECT COUNT(*) FROM `ems_job_queue` WHERE `state` = 'dead'");
$noRule = array();
$r = $db->query("SELECT q.`job_id` FROM `ems_job_queue` q
                  WHERE q.`state` = 'dead'
                    AND NOT EXISTS (SELECT 1 FROM `gov_dead_letter_rulings` g
                                     WHERE g.`job_id` = q.`job_id`)");
while ($r && $x = $r->fetch_row()) { $noRule[] = $x[0]; }
chk(empty($noRule), 'FR-EVT-007 · **صفرُ رسالةٍ ميتةٍ بلا حكم**',
    empty($noRule) ? "{$dead} ميتةً كلُّها محكومة"
                   : count($noRule) . ' بلا حكم: #' . implode(' #', array_slice($noRule, 0, 5)));

/* ④ ولكلِّ حكمٍ مالكٌ وسببٌ ودليل */
$thin = n($db, "SELECT COUNT(*) FROM `gov_dead_letter_rulings`
                 WHERE TRIM(`owner_role`) = '' OR TRIM(`reason`) = '' OR TRIM(`evidence`) = ''");
chk($thin === 0, 'ولكلِّ حكمٍ **مالكٌ وسببٌ ودليل**', "ناقصٌ: {$thin}");

/* ⑤ والمفرداتُ مغلقة */
$badRule = n($db, "SELECT COUNT(*) FROM `gov_dead_letter_rulings`
                    WHERE `ruling` NOT IN ('TEST_POLLUTION','RETRY','DROP','NEEDS_GOVERNING_SOURCE')");
chk($badRule === 0, 'والحكمُ من مفرداتٍ مغلقة', "خارجَها: {$badRule}");

/* ⑥ ولا حذفَ — الصفُّ باقٍ بحالتِه */
$pol = n($db, "SELECT COUNT(*) FROM `gov_dead_letter_rulings` WHERE `ruling` = 'TEST_POLLUTION'");
chk($dead >= $pol, 'والصفُّ المحجورُ **باقٍ لم يُحذف**',
    "ميتةٌ في الطابور: {$dead} · محجورةٌ بحكم: {$pol}");

if ($neg) {
    $db->query("DELETE FROM `ems_job_queue` WHERE `job_id` = {$MARK}");
    $left = n($db, "SELECT COUNT(*) FROM `ems_job_queue` WHERE `job_id` = {$MARK}");
    chk($left === 0, 'وكُنس الحزامُ أثرَه', "المتبقي: {$left}");
    echo "\n◆ الحزامُ السلبيّ: **يُتوقَّع رسوبٌ في ③** — فإن مرَّ فالفحصُ لا يقرأ\n";
    echo "  الطابورَ الحيَّ بل سجلَّ الأحكامِ وحدَه.\n";
    printf("النتيجة: %d نجاح · %d رسوب\n", $ok, $bad);
    exit($bad > 0 ? 0 : 1);
}

echo "\n" . str_repeat('─', 66) . "\n";
printf("النتيجة: %d نجاح · %d رسوب\n", $ok, $bad);
exit($bad === 0 ? 0 : 1);
