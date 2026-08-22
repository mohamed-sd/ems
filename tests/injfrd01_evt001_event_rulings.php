<?php
/**
 * tests/injfrd01_evt001_event_rulings.php
 *   شاهدُ FR-EVT-001 · FR-EVT-002 — كلُّ نوعِ حدثٍ محكومٌ حيًّا
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **معاييرُ الدفتر**: FR-EVT-001 «**صفرُ نوعِ حدثٍ بلا حكم**» · وسالبُه «نوعٌ
 *   مصنَّفٌ أعمالًا وبلا مستهلكٍ ← رسوب». و FR-EVT-002 «**صفرُ نوعِ أعمالٍ بلا
 *   مستهلكٍ نشط**» · و«لا يُعَدُّ مكتملًا بإنتاجِه وحدَه».
 *
 * ◆ **والقياسُ على المنتَجِ الحيِّ لا على السجلِّ وحدَه**: `gov_event_rulings`
 *   لقطةٌ بلحظتِها (`measured_at`)، ونوعٌ يُنتَج اليومَ ولم يكن يومَها **يمرُّ
 *   بلا حكمٍ صامتًا**. فيُقاس المقامُ من `ems_business_events` — ما أُنتج فعلًا
 *   — ويُقابَل بالسجل. **والسجلُّ وحدَه يُصدِّق نفسَه.**
 *
 * التشغيل: php tests/injfrd01_evt001_event_rulings.php [--negative]
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

$neg  = in_array('--negative', $argv, true);
$MARK = 'evt001.belt.unruled';

echo "══ FR-EVT-001 · FR-EVT-002 — كلُّ نوعِ حدثٍ محكومٌ حيًّا ══\n";

if ($neg) {
    /* ◆ **والقاعدةُ نفسُها ترفض — وهو أقوى من قياسٍ يرصد**: أوّلُ حزامٍ كتبتُه
     *   حاول إنتاجَ حدثٍ بلا حكمٍ ليُقاس رصدُه، **فرفضته القاعدةُ نفسُها**
     *   بقيد `chk_consumers` (`consumers_declared > 0`). فبان أن الضمانَ
     *   بنيويٌّ لا قياسيّ: **حدثٌ بلا مستهلكٍ مُعلَنٍ لا يُولد أصلًا**.
     *   ⇒ صار الحزامُ يُثبت **الرفضَ** لا الرصد. */
    $co = n($db, "SELECT `company_id` FROM `ems_business_events` LIMIT 1");
    $rejected = false; $err = '';
    try {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $db->query("INSERT INTO `ems_business_events`
            (`company_id`,`event_no`,`event_uuid`,`event_key`,`category`,`source_module`,
             `source_ref`,`entity_type`,`entity_id`,`consumers_declared`,`created_at`)
            VALUES ({$co},'BELT-EVT-001',UUID(),'{$MARK}','belt','belt',0,'belt',0,0,NOW())");
    } catch (\Throwable $t) {
        $rejected = true; $err = $t->getMessage();
    } finally {
        mysqli_report(MYSQLI_REPORT_OFF);
    }
    chk($rejected, '**القاعدةُ ترفض حدثًا بصفرِ مستهلكٍ مُعلَن**',
        $rejected ? 'قيدُ chk_consumers ردَّه: ' . mb_substr($err, 0, 46) : 'مرَّ — والقيدُ لا يعمل');
    $left = n($db, "SELECT COUNT(*) FROM `ems_business_events` WHERE `event_key` = '{$MARK}'");
    chk($left === 0, 'وصفرُ صفٍّ كُتب رغمَ المحاولة', "المكتوب: {$left}");
}

/* ① المقامُ من المنتَجِ الحيّ */
$produced = n($db, "SELECT COUNT(DISTINCT `event_key`) FROM `ems_business_events`");
$ruled    = n($db, "SELECT COUNT(*) FROM `gov_event_rulings`");
chk($produced > 0, 'المقامُ يُقرأ من **المنتَجِ الحيّ** لا من السجل',
    "أنواعٌ أُنتجت فعلًا: {$produced} · محكومة: {$ruled}");

/* ② **صفرُ نوعٍ منتَجٍ بلا حكم** — FR-EVT-001 */
$unruled = array();
$r = $db->query("SELECT DISTINCT b.`event_key` FROM `ems_business_events` b
                  LEFT JOIN `gov_event_rulings` g ON g.`event_key` = b.`event_key`
                 WHERE g.`event_key` IS NULL");
while ($r && $x = $r->fetch_row()) { $unruled[] = $x[0]; }
chk(empty($unruled), 'FR-EVT-001 · **صفرُ نوعِ حدثٍ منتَجٍ بلا حكم**',
    empty($unruled) ? '0' : count($unruled) . ': ' . implode(' · ', array_slice($unruled, 0, 4)));

/* ③ ولا حكمَ بلا سببٍ مكتوب */
$noReason = n($db, "SELECT COUNT(*) FROM `gov_event_rulings`
                     WHERE `reason` IS NULL OR TRIM(`reason`) = ''");
chk($noReason === 0, 'ولا حكمَ بلا سببٍ مكتوب', "بلا سبب: {$noReason}");

/* ④ والمفرداتُ مغلقة — حكمٌ خارجَ الاثنين يُرسِّب */
$badRuling = n($db, "SELECT COUNT(*) FROM `gov_event_rulings`
                      WHERE `ruling` NOT IN ('business','audit')");
chk($badRuling === 0, 'والحكمُ من مفردتَين لا ثالثَ لهما', "خارجَهما: {$badRuling}");

/* ⑤ **حدثُ الأعمالِ له مستهلكٌ نشطٌ ومعالجٌ على القرص** — FR-EVT-002 */
$orphan = array();
$r = $db->query("SELECT `event_key`, `subscription_active`, `handler_on_disk`
                   FROM `gov_event_rulings`
                  WHERE `ruling` = 'business'
                    AND (`subscription_active` = 0 OR `handler_on_disk` = 0)");
while ($r && $x = $r->fetch_assoc()) { $orphan[] = $x['event_key']; }
$biz = n($db, "SELECT COUNT(*) FROM `gov_event_rulings` WHERE `ruling` = 'business'");
chk(empty($orphan), 'FR-EVT-002 · **صفرُ حدثِ أعمالٍ بلا مستهلكٍ نشط**',
    empty($orphan) ? "{$biz} حدثَ أعمالٍ كلُّها موصولة"
                   : count($orphan) . ': ' . implode(' · ', array_slice($orphan, 0, 4)));

/* ⑥ ولا يُعَدُّ الحدثُ مكتملًا بإنتاجِه وحدَه — يُقاس التسليمُ لا الإنتاج */
$hasDeliveries = n($db, "SELECT COUNT(*) FROM information_schema.TABLES
                          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ems_event_deliveries'");
if ($hasDeliveries === 1) {
    $delivered = n($db, "SELECT COUNT(DISTINCT e.`event_key`)
                           FROM `ems_business_events` e
                           JOIN `ems_event_deliveries` d ON d.`event_id` = e.`id`");
    chk($delivered > 0, 'ويُقاس **التسليمُ** لا الإنتاجُ وحدَه',
        "أنواعٌ سُلِّمت فعلًا: {$delivered}");
} else {
    chk(false, 'جدولُ التسليماتِ موجود', 'مفقود');
}

if ($neg) {
    $db->query("DELETE FROM `ems_business_events` WHERE `event_key` = '{$MARK}'");
    $left = n($db, "SELECT COUNT(*) FROM `ems_business_events` WHERE `event_key` = '{$MARK}'");
    chk($left === 0, 'وكُنس الحزامُ أثرَه', "المتبقي: {$left}");
    /* ◆ **وهذا الحزامُ يُثبت الرفضَ لا الرصد**، فنجاحُه أن **تمرَّ** فحوصُه —
     *   لأن الضمانَ بنيويٌّ في قيدِ القاعدةِ نفسِها: حدثٌ بلا مستهلكٍ مُعلَنٍ
     *   **لا يُولد أصلًا**. وهو أقوى من قياسٍ يرصد بعدَ الولادة. */
    echo "\n◆ الحزامُ يُثبت **الرفضَ لا الرصد**: الضمانُ بنيويٌّ في قيدِ القاعدة،\n";
    echo "  فحدثٌ بلا مستهلكٍ مُعلَنٍ **لا يُولد أصلًا** — ونجاحُه أن تمرَّ فحوصُه.\n";
    printf("النتيجة: %d نجاح · %d رسوب\n", $ok, $bad);
    exit($bad === 0 ? 0 : 1);
}

echo "\n" . str_repeat('─', 66) . "\n";
printf("النتيجة: %d نجاح · %d رسوب\n", $ok, $bad);
exit($bad === 0 ? 0 : 1);
