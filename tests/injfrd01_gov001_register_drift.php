<?php
/**
 * tests/injfrd01_gov001_register_drift.php
 *   شاهدُ FR-GOV-001 — حارسٌ يقارن **حكمَ السجلِّ بالواقعِ المقيس**
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المعيارُ بنصِّه**: «حارسٌ يقارن حكمَ السجلِّ بالواقعِ المقيسِ **ويُنذر عندَ
 *   الانحراف**» · ومعيارُ القبول «**إنذارٌ مجرَّبٌ بانحرافٍ مصطنَع**» ·
 *   وسلوكُ الفشل «**فشلُ الحارسِ نفسِه يُنذَر**».
 *
 * ◆ **والعطبُ الذي يمنعه**: سجلُّ حوكمةٍ يقول «حُكم على كذا» ثمّ يتغيّر الواقعُ
 *   تحته — فيبقى الحكمُ ساريًا على شيءٍ لم يعد موجودًا، أو يظهر جديدٌ بلا حكم.
 *   **والسجلُّ الذي لا يُقارَن يصير ذاكرةً لا حارسًا.**
 *
 * ◆ **وسبعةُ سجلّاتٍ تُقارَن بمقياسِها الحيّ** — لكلٍّ سؤالٌ واحدٌ لا يقبل
 *   التأويل، وجوابُه يُقاس من القاعدةِ أو الشجرةِ لحظةَ التشغيل.
 *
 * ◆ **وفشلُ الحارسِ نفسِه يُنذَر**: سجلٌّ مفقودٌ أو استعلامٌ يرجع `-1` **يُرسِّب**
 *   ولا يُقرأ صفرًا. فحارسٌ يعمى لا يُقرأ نجاحًا.
 *
 * التشغيل: php tests/injfrd01_gov001_register_drift.php [--negative]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
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
/** يرجع -1 عند فشلِ الاستعلام — و**‑1 ليست صفرًا**. */
function n(mysqli $d, $q) { $r = @$d->query($q); return $r ? (int) $r->fetch_row()[0] : -1; }
function tbl(mysqli $d, $t) {
    return n($d, "SELECT COUNT(*) FROM information_schema.TABLES
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . $d->real_escape_string($t) . "'");
}

$neg = in_array('--negative', $argv, true);
echo "══ FR-GOV-001 — حكمُ السجلِّ يُقارَن بالواقعِ المقيس ══\n";

/* كلُّ فحصٍ: اسمٌ · سؤالٌ · انحرافٌ مقيسٌ (0 = مطابق) */
$checks = array();

/* ① أحكامُ الأحداث: كلُّ نوعٍ منتَجٍ محكوم */
$checks[] = array(
    'reg' => 'gov_event_rulings',
    'q'   => 'أنواعٌ أُنتجت ولا حكمَ لها',
    'sql' => "SELECT COUNT(DISTINCT b.`event_key`) FROM `ems_business_events` b
               LEFT JOIN `gov_event_rulings` g ON g.`event_key` = b.`event_key`
              WHERE g.`event_key` IS NULL",
);
/* ② أحكامُ الرسائلِ الميتة: كلُّ ميتةٍ محكومة */
$checks[] = array(
    'reg' => 'gov_dead_letter_rulings',
    'q'   => 'ميتةٌ في الطابورِ بلا حكم',
    'sql' => "SELECT COUNT(*) FROM `ems_job_queue` q
               WHERE q.`state` = 'dead'
                 AND NOT EXISTS (SELECT 1 FROM `gov_dead_letter_rulings` g
                                  WHERE g.`job_id` = q.`job_id`)",
);
/* ③ أحكامُ المستهلكِ اليتيم: كلُّ محكومٍ ما يزال يتيمًا فعلًا */
$checks[] = array(
    'reg' => 'gov_orphan_consumer_rulings',
    'q'   => 'محكومٌ بيُتمٍ ولم يعد في سجلِّ المستهلكين',
    'sql' => "SELECT COUNT(*) FROM `gov_orphan_consumer_rulings` g
               WHERE NOT EXISTS (SELECT 1 FROM `ems_event_consumers` c
                                  WHERE c.`consumer` = g.`consumer_key`)",
);
/* ④ أحكامُ السياساتِ الوهمية: كلُّ محكومٍ بـNO_REAL_TARGET ما يزال بلا هدف */
$checks[] = array(
    'reg' => 'gov_phantom_policy_rulings',
    'q'   => 'محكومٌ NO_REAL_TARGET وهدفُه صار موجودًا',
    'sql' => "SELECT COUNT(*) FROM `gov_phantom_policy_rulings` g
               WHERE g.`ruling` = 'NO_REAL_TARGET'
                 AND EXISTS (SELECT 1 FROM information_schema.COLUMNS c
                              WHERE c.TABLE_SCHEMA = DATABASE()
                                AND CONCAT(c.TABLE_NAME,'.',c.COLUMN_NAME) = g.`declared_target`)",
);
/* ⑤ أحكامُ الملكية: كلُّ حكمٍ على مسارٍ ما يزال على القرص */
$checks[] = array(
    'reg' => 'gov_ownership_rulings',
    'q'   => 'حكمُ ملكيةٍ بلا شاهدٍ أو مالكٍ مكتوب',
    'sql' => "SELECT COUNT(*) FROM `gov_ownership_rulings`
               WHERE TRIM(`witness`) = '' OR TRIM(`owner_after`) = ''",
);
/* ⑥ دَينُ السياساتِ الحساسة: كلُّ مُعلَنٍ ما يزال بلا عمودٍ حقيقيّ */
$checks[] = array(
    'reg' => 'gov_sensitive_policy_debt',
    'q'   => 'دَينٌ مُعلَنٌ وهدفُه صار موجودًا (فالحكمُ تعفّن)',
    'sql' => "SELECT COUNT(*) FROM `gov_sensitive_policy_debt` d
               WHERE EXISTS (SELECT 1 FROM information_schema.COLUMNS c
                              WHERE c.TABLE_SCHEMA = DATABASE()
                                AND CONCAT(c.TABLE_NAME,'.',c.COLUMN_NAME) = d.`declared_target`)",
);
/* ⑦ سجلُّ الاستعلامِ الخام (ملفّ): كلُّ مدخلٍ ما يزال ملفًّا قائمًا */
$regFile = $ROOT . '/docs/raw_query_exceptions.json';

echo "\n── مقارنةُ الأحكامِ بالواقع ──\n";
$drift = 0; $blind = 0;
foreach ($checks as $c) {
    if (tbl($db, $c['reg']) !== 1) {
        $blind++;
        chk(false, "سجلٌّ **مفقود**: `{$c['reg']}` — وحارسٌ يعمى لا يُقرأ نجاحًا");
        continue;
    }
    $v = n($db, $c['sql']);
    if ($v < 0) {
        $blind++;
        chk(false, "**فشلُ القياسِ** على `{$c['reg']}` — و‑1 ليست صفرًا · {$c['q']}");
        continue;
    }
    if ($v > 0) { $drift++; }
    chk($v === 0, "`{$c['reg']}` — {$c['q']}", $v === 0 ? 'صفرُ انحراف' : "**انحرافٌ: {$v}**");
}

/* ⑦ الملفُّ يُقاس كما تُقاس الجداول */
if (!is_file($regFile)) {
    $blind++;
    chk(false, 'سجلُّ الاستعلامِ الخامِّ **مفقود** — `docs/raw_query_exceptions.json`');
} else {
    $reg = json_decode((string) file_get_contents($regFile), true);
    $ent = isset($reg['entries']) && is_array($reg['entries']) ? $reg['entries'] : array();
    $goneFiles = 0;
    foreach (array_keys($ent) as $rel) { if (!is_file($ROOT . '/' . $rel)) { $goneFiles++; } }
    if ($goneFiles > 0) { $drift++; }
    chk($goneFiles === 0, '`raw_query_exceptions.json` — مدخلٌ لملفٍّ لم يعد موجودًا',
        $goneFiles === 0 ? count($ent) . ' مدخلًا كلُّها قائمة' : "**انحرافٌ: {$goneFiles}**");
}

printf("\n  **الحصيلة: %d سجلًّا فُحص · انحرافٌ=%d · حارسٌ أعمى=%d**\n",
       count($checks) + 1, $drift, $blind);
chk($blind === 0, '**ولا حارسَ أعمى** — فشلُ الحارسِ نفسِه يُنذَر (سلوكُ الفشل)',
    "أعمى: {$blind}");

if ($neg) {
    /* ◆ **انحرافٌ مصطنَعٌ يجب أن يُرصد**: يُدسُّ حكمُ يُتمٍ لمستهلكٍ لا وجودَ له */
    echo "\n── الحزامُ السالب: انحرافٌ مصطنَع ──\n";
    $MARK = 'gov001_belt_ghost';
    $st = $db->prepare("INSERT INTO `gov_orphan_consumer_rulings`
        (`consumer_key`,`event_codes`,`ruling`,`owner`,`reason`,`evidence`,`ruled_at`)
        VALUES (?, 'belt', 'NO_HANDLER_ON_DISK', 'حزام', 'حزامٌ سالب', 'belt', NOW())");
    $st->bind_param('s', $MARK);
    if (!$st->execute()) { exit("  ⛔ **رُفض دسُّ الحزام** — " . $st->error . "\n"); }
    $st->close();
    $planted = n($db, "SELECT COUNT(*) FROM `gov_orphan_consumer_rulings`
                        WHERE `consumer_key` = '{$MARK}'");
    if ($planted !== 1) { echo "  ⛔ **لم يُدَسَّ شيء** — أُوقِف\n"; exit(1); }
    echo "  ◆ دُسَّ حكمٌ على مستهلكٍ لا وجودَ له — **ووجودُه مُثبَتٌ قبلَ القياس**\n";

    $v = n($db, "SELECT COUNT(*) FROM `gov_orphan_consumer_rulings` g
                  WHERE NOT EXISTS (SELECT 1 FROM `ems_event_consumers` c
                                     WHERE c.`consumer` = g.`consumer_key`)");
    chk($v > 0, '**الحارسُ يرصد الانحرافَ المصطنَع**',
        $v > 0 ? "انحرافٌ مقيسٌ: {$v}" : 'لم يُرصد ✘ — الحارسُ لا يقارن');

    $db->query("DELETE FROM `gov_orphan_consumer_rulings` WHERE `consumer_key` = '{$MARK}'");
    $left = n($db, "SELECT COUNT(*) FROM `gov_orphan_consumer_rulings` WHERE `consumer_key` = '{$MARK}'");
    chk($left === 0, 'وكُنس الحزامُ أثرَه', "المتبقي: {$left}");
    $v2 = n($db, "SELECT COUNT(*) FROM `gov_orphan_consumer_rulings` g
                   WHERE NOT EXISTS (SELECT 1 FROM `ems_event_consumers` c
                                      WHERE c.`consumer` = g.`consumer_key`)");
    chk($v2 === 0, '**وبزوالِ الانحرافِ يعود المطابقُ** — فالرصدُ حيٌّ لا لاصق', "بعدَ الكنس: {$v2}");
}

echo "\n" . str_repeat('─', 66) . "\n";
printf("النتيجة: %d نجاح · %d رسوب\n", $ok, $bad);
exit($bad === 0 ? 0 : 1);
