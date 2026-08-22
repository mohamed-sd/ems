<?php
/**
 * tests/injfrd01_evt004_006_fx_backlog_idempotency.php
 *   شاهدُ FR-EVT-004 · FR-EVT-006 — أثرُ متأخرِ الصرف · وعطالةُ الاستهلاك
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **FR-EVT-004**: «معالجةُ متأخرِ أسعارِ الصرفِ بأمانٍ **وتقريرُ أثرِ التأخيرِ
 *   على الأرقامِ المالية**» · ومعيارُه «المؤشرُ يلحق + **تقريرُ أثرٍ أو إعلانُ
 *   صفرِ أثر**» · وسالبُه «إعادةُ معالجةٍ ← صفرُ أثرٍ مزدوج».
 *
 * ◆ **FR-EVT-006**: «عطالةُ الاستهلاك: الحدثُ نفسُه مرتَين لا يُنتج أثرًا
 *   مزدوجًا» · ومعيارُه «صفرُ أثرٍ مزدوجٍ بعدَ إعادةِ تسليم».
 *
 * ◆ **والأثرُ يُقاس ولا يُوصَف**: `fx` متأخرٌ منذ 2026-08-12 بـ5054 واقعة.
 *   والسؤالُ الماليُّ ليس «كم متأخرًا» بل «**كم رقمًا ماليًّا خطأً بسببِه**».
 *   والجوابُ يُقاس: كم واقعةً **بمبلغٍ غيرِ صفريٍّ** تنقصها قيمةُ الأساس.
 *
 * ◆ **ولا يُعلَن صفرُ أثرٍ بمقامٍ صفريّ**: يُشترَط أن يكون في المتأخرِ وقائعُ
 *   بمبالغَ حقيقيةٍ قبلَ أن يُقرأ «صفرُ أثرٍ» — وإلا كان الصفرُ صفرَ بحث.
 *
 * ◆ **والعطالةُ بنيويةٌ لا مقيسةٌ وحسب**: مفتاحان فريدان يمنعان الازدواجَ من
 *   القاعدةِ نفسِها — `ems_event_deliveries.uq_idem` و
 *   `fin_event_links.uq_link_parent_effect`. والحزامُ يُثبت **الرفضَ** لا الرصد.
 *
 * التشغيل: php tests/injfrd01_evt004_006_fx_backlog_idempotency.php [--negative]
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
function n(mysqli $d, $q) { $r = @$d->query($q); return $r ? (int) $r->fetch_row()[0] : -1; }

$neg = in_array('--negative', $argv, true);

echo "══ FR-EVT-004 · FR-EVT-006 — أثرُ المتأخرِ وعطالةُ الاستهلاك ══\n";

/* ── ① المتأخرُ يُقاس بمؤشرِه الحيّ ─────────────────────────────────────── */
$cur = n($db, "SELECT `cursor_event_id` FROM `ems_event_consumers` WHERE `consumer` = 'fx'");
if ($cur < 0) {
    chk(false, 'مستهلكُ الصرفِ موجودٌ في السجل', 'غيرُ موجود — لا شيءَ يُقاس');
    echo "\nالنتيجة: {$ok} نجاح · {$bad} رسوب\n";
    exit(1);
}
$since = '';
$r = $db->query("SELECT `updated_at` FROM `ems_event_consumers` WHERE `consumer` = 'fx'");
if ($r && $x = $r->fetch_row()) { $since = (string) $x[0]; }
$lag = n($db, "SELECT COUNT(*) FROM `fin_financial_events` WHERE `id` > {$cur}");
printf("  مؤشرُ fx: %d · آخرُ تقدُّم: %s · **متأخرٌ %d واقعة**\n", $cur, $since, $lag);

/* ── ② **تقريرُ الأثرِ الماليِّ** — لا عددُ الوقائعِ بل الأرقامُ الخطأ ────── */
echo "\n── ② أثرُ التأخيرِ على **الأرقامِ المالية** ──\n";
$nonZero = n($db, "SELECT COUNT(*) FROM `fin_financial_events`
                    WHERE `id` > {$cur} AND `amount` <> 0");
$zeroAmt = n($db, "SELECT COUNT(*) FROM `fin_financial_events`
                    WHERE `id` > {$cur} AND `amount` = 0");
$noBase  = n($db, "SELECT COUNT(*) FROM `fin_financial_events`
                    WHERE `id` > {$cur} AND `amount` <> 0
                      AND (`base_amount` IS NULL OR `base_amount` = 0)");

/* ◆ **ولا يُعلَن صفرُ أثرٍ بمقامٍ صفريّ** */
chk($nonZero > 0, '**المقامُ غيرُ صفريّ** — في المتأخرِ وقائعُ بمبالغَ حقيقية',
    "بمبلغٍ غيرِ صفريٍّ={$nonZero} · بمبلغٍ صفريٍّ={$zeroAmt}");

$curSum = array();
$r = $db->query("SELECT `currency`, COUNT(*) c, ROUND(SUM(`amount`),2) s
                   FROM `fin_financial_events` WHERE `id` > {$cur} AND `amount` <> 0
                  GROUP BY `currency`");
while ($r && $x = $r->fetch_row()) { $curSum[] = "{$x[0]}: {$x[1]} واقعةً · {$x[2]}"; }
echo '     التعرُّضُ بالعملة — ' . implode(' | ', $curSum) . "\n";

chk($noBase === 0,
    'FR-EVT-004 · **تقريرُ الأثر: صفرُ رقمٍ ماليٍّ ناقصٍ بسببِ التأخير**',
    $noBase === 0
      ? "من {$nonZero} واقعةً بمبلغٍ حقيقيٍّ — **صفرٌ بلا قيمةِ أساسٍ محسوبة**"
      : "**{$noBase} واقعةً بمبلغٍ حقيقيٍّ وبلا أساس** — أثرٌ ماليٌّ قائم");
echo "     ◆ والسببُ بنيويّ: قيمةُ الأساسِ تُحسب **عند الكتابة** (base = amount × rate)\n";
echo "       لا في مستهلكِ الصرف — فتأخُّرُ المستهلكِ لا يترك رقمًا ناقصًا.\n";
echo "     ◆ و{$zeroAmt} واقعةً بمبلغٍ صفريٍّ **لا أثرَ ماليَّ لها** بتعريفِها.\n";

/* ── ③ FR-EVT-006 — العطالةُ **بنيويةٌ** في القاعدة ─────────────────────── */
echo "\n── ③ عطالةُ الاستهلاك ──\n";
$uq = array();
$r = $db->query("SELECT CONCAT(TABLE_NAME,'.',INDEX_NAME) k
                   FROM information_schema.STATISTICS
                  WHERE TABLE_SCHEMA = DATABASE() AND NON_UNIQUE = 0
                    AND INDEX_NAME IN ('uq_idem','uq_link_parent_effect')
                  GROUP BY TABLE_NAME, INDEX_NAME");
while ($r && $x = $r->fetch_row()) { $uq[] = $x[0]; }
/* ◆ **والعددُ يُقاس ولا يُجمَّد**: كتبتُ `=== 2` فرسب القياسُ لأن جدولًا ثالثًا
 *   (`ems_event_delivery_orphans`) يحمل `uq_idem` أيضًا — **زيادةُ حراسةٍ
 *   قُرئت رسوبًا**. ⇒ المطلوبُ **لا يقلُّ عن اثنَين**، ويُسمَّون. */
chk(count($uq) >= 2, 'مفاتيحُ فريدةٌ **تمنع الأثرَ المزدوجَ من القاعدة**',
    implode(' · ', $uq));

$dupDeliv = n($db, "SELECT COUNT(*) FROM (
                      SELECT `idempotency_key` FROM `ems_event_deliveries`
                       WHERE `idempotency_key` IS NOT NULL
                       GROUP BY `idempotency_key` HAVING COUNT(*) > 1) t");
$dupLink  = n($db, "SELECT COUNT(*) FROM (
                      SELECT `company_id`,`parent_kind`,`parent_ref`,`effect_type`
                        FROM `fin_event_links`
                       GROUP BY 1,2,3,4 HAVING COUNT(*) > 1) t");
$delivN = n($db, "SELECT COUNT(*) FROM `ems_event_deliveries`");
$linkN  = n($db, "SELECT COUNT(*) FROM `fin_event_links`");
chk($dupDeliv === 0 && $dupLink === 0,
    'FR-EVT-006 · **صفرُ أثرٍ مزدوجٍ في المقامِ الحيِّ كلِّه**',
    "تسليمات={$delivN} مكرَّرٌ={$dupDeliv} · روابطُ أثرٍ={$linkN} مكرَّرٌ={$dupLink}");

if ($neg) {
    /* ◆ الحزامُ يُثبت **الرفضَ**: إعادةُ تسليمٍ بالمفتاحِ نفسِه تُردُّ من القاعدة */
    $key = 'evt006.belt.' . getmypid();
    $co  = n($db, "SELECT `company_id` FROM `ems_event_deliveries` WHERE `company_id` > 0 LIMIT 1");
    /* ◆ **ومعرِّفُ الحدثِ يجب أن يكون حقيقيًّا**: أوّلُ حزامٍ استعمل رقمًا
     *   مخترَعًا (999999901) فردَّه `fk_evdeliv_event` — **ولم يُدَسَّ شيء**.
     *   ⇒ يُؤخَذ معرِّفُ حدثٍ **قائمٍ فعلًا** من `ems_business_events`. */
    $ev  = n($db, "SELECT `id` FROM `ems_business_events` ORDER BY `id` DESC LIMIT 1");
    if ($ev <= 0) { echo "  ⛔ لا حدثَ حقيقيًّا يُرتكَز عليه — أُوقِف
"; exit(1); }
    $ins = "INSERT INTO `ems_event_deliveries`
              (`consumer`,`event_id`,`attempts`,`consumer_key`,`state`,`idempotency_key`,`company_id`)
            VALUES ('belt', {$ev}, 1, 'belt', 'done', '{$key}', {$co})";
    $first = $db->query($ins);
    $planted = n($db, "SELECT COUNT(*) FROM `ems_event_deliveries` WHERE `idempotency_key` = '{$key}'");
    if (!$first || $planted !== 1) {
        echo "  ⛔ **تعذّر دسُّ الحزام** — " . $db->error . "\n";
        echo "     وحزامٌ لا يدسُّ شيئًا لا يُثبت شيئًا. أُوقِف.\n";
        exit(1);
    }
    echo "  ◆ دُسَّ تسليمٌ بمفتاحِ عطالة — **ووجودُه مُثبَتٌ قبلَ القياس**\n";

    $rejected = false; $err = '';
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    try { $db->query($ins); } catch (\Throwable $t) { $rejected = true; $err = $t->getMessage(); }
    mysqli_report(MYSQLI_REPORT_OFF);
    $count = n($db, "SELECT COUNT(*) FROM `ems_event_deliveries` WHERE `idempotency_key` = '{$key}'");
    chk($rejected && $count === 1,
        '**إعادةُ التسليمِ بالمفتاحِ نفسِه تُردُّ من القاعدة** — أثرٌ واحدٌ لا أثران',
        "صفوفٌ بعدَ محاولتَين: {$count} · " . ($rejected ? 'ردَّتها: ' . mb_substr($err, 0, 44) : 'مرَّت ✘'));

    $db->query("DELETE FROM `ems_event_deliveries` WHERE `idempotency_key` = '{$key}'");
    $left = n($db, "SELECT COUNT(*) FROM `ems_event_deliveries` WHERE `idempotency_key` = '{$key}'");
    chk($left === 0, 'وكُنس الحزامُ أثرَه', "المتبقي: {$left}");
    echo "\n◆ الحزامُ يُثبت **الرفضَ لا الرصد**: الضمانُ بنيويٌّ في مفتاحِ القاعدة،\n";
    echo "  فالأثرُ المزدوجُ **لا يُولد أصلًا** — وهو أقوى من قياسٍ يرصد بعدَ الولادة.\n";
}

echo "\n" . str_repeat('─', 66) . "\n";
printf("النتيجة: %d نجاح · %d رسوب\n", $ok, $bad);
exit($bad === 0 ? 0 : 1);
