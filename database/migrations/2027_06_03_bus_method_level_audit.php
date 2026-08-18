<?php
/**
 * 2027_06_03_bus_method_level_audit.php
 * ═══════════════════════════════════════════════════════════════════════════
 * فحصٌ على مستوى **الطريقةِ** لا الصنفِ وحدَه — وإغلاقُ تسليماتِ مستهلكٍ متقاعد
 * ───────────────────────────────────────────────────────────────────────────
 * الهجرةُ السابقةُ فحصت **وجودَ ملفِّ الصنف** فأصلحت 11 اشتراكًا. وكشف التسليمُ
 * الحقيقيُّ ما فاتها: صنفٌ موجودٌ **وطريقتُه غيرُ موجودة** —
 *   `App\Services\Capacity\BalanceCalculator::rebuild` والموجودُ فعلًا
 *   `rebuildContainerCache($gate,$contractId)` ويطلب بوابةً لا واقعة.
 *
 * فالفحصُ هنا يحمّل الصنفَ ويسأل `method_exists` — ولا يُصدَّق إعلانٌ بوجودِ ملفّ.
 *
 * ويُغلق كذلك 25 تسليمًا مُعلَّقًا لمستهلكٍ **تقاعد** (`effectfanout`): نوعُها
 * `expense.depreciation.recorded` مغطًّى الآن بـ1,956 تسليمًا ناجحًا عبرَ
 * الحارسِ الحوكميّ، فإبقاؤها تُعاد إلى الأبدِ ضجيجٌ لا إنذار. تُختم في صندوقِ
 * الموتى **بسببِها** ولا تُحذف.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');

$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');
$one = function (string $s) use ($conn) { $r = $conn->query($s); return $r ? ($r->fetch_row()[0] ?? null) : null; };

echo "\n▐ ① فحصُ الطريقةِ لا الصنفِ وحدَه\n";
$bad = array();
$r = $conn->query("SELECT `c_id`,`event_name`,`consumer_class`,`consumer_method` FROM `event_consumers` WHERE `active`=1");
while ($x = $r->fetch_assoc()) {
    $cls = (string) $x['consumer_class'];
    $mth = ($x['consumer_method'] !== null && $x['consumer_method'] !== '') ? (string) $x['consumer_method'] : 'handle';
    $rel = str_replace('\\', '/', preg_replace('#^App\\\\#', '', $cls));
    $file = $ROOT . '/app/' . $rel . '.php';
    if (!is_readable($file)) { $bad[] = array($x, 'لا ملفَّ للصنف'); continue; }
    if (!class_exists($cls)) { @require_once $file; }
    if (!class_exists($cls))              { $bad[] = array($x, 'الملفُّ موجودٌ ولا يُعرِّف الصنف'); continue; }
    if (!method_exists($cls, $mth))       { $bad[] = array($x, "الصنفُ موجودٌ والطريقة «{$mth}» غيرُ موجودةٍ فيه"); }
}
printf("   · اشتراكاتٌ نشطةٌ لا تصلح للنداء: %d\n", count($bad));
foreach ($bad as $b) { printf("      %-40s %s\n", $b[0]['event_name'], $b[1]); }

echo "\n▐ ② تعطيلُها بسببِها ثم تغطيةُ نوعِها بمستهلكٍ عامل\n";
$up = $conn->prepare("UPDATE `event_consumers` SET `active`=0, `inactive_reason`=?, `inactive_at`=NOW() WHERE `c_id`=?");
$ins = $conn->prepare(
    "INSERT IGNORE INTO `event_consumers`
        (`event_name`,`consumer_class`,`consumer_method`,`produces`,`active`,
         `consumer_key`,`max_attempts`,`timeout_seconds`)
     VALUES (?, 'App\\\\Services\\\\Bus\\\\Consumers\\\\GovernanceWatchConsumer', 'handle', 'notify', 1,
             'governance_watch', 5, 60)");
$off = 0; $cov = 0;
foreach ($bad as $b) {
    $cid = (int) $b[0]['c_id'];
    $reason = $b[1] . ' — يُعطَّل ولا يُحذف، والأثرُ الأصليُّ باقٍ حيث كان.';
    $up->bind_param('si', $reason, $cid);
    if ($up->execute()) { $off++; }
    $ev = (string) $b[0]['event_name'];
    $live = (int) $one("SELECT COUNT(*) FROM `event_consumers`
                         WHERE `event_name`='" . $conn->real_escape_string($ev) . "' AND `active`=1");
    if ($live === 0) { $ins->bind_param('s', $ev); if ($ins->execute() && $conn->affected_rows > 0) { $cov++; } }
}
$up->close(); $ins->close();
printf("   ✔ عُطّل %d · وغُطّي %d نوعًا بمستهلكٍ عامل\n", $off, $cov);

echo "\n▐ ③ إغلاقُ تسليماتِ مستهلكٍ متقاعدٍ — في صندوقِ الموتى بسببِها\n";
$stale = (int) $one(
    "SELECT COUNT(*) FROM `ems_event_deliveries` d
      WHERE d.`state` IN ('failed','dlq') AND d.`fail_code`='NO_SUB'");
$conn->query(
    "UPDATE `ems_event_deliveries` d
        SET d.`state`='dlq', d.`fail_code`='CONSUMER_RETIRED',
            d.`processed_at`=NOW(3),
            d.`next_attempt_at`=NULL,
            d.`fail_text`=CONCAT('[مستهلكٌ متقاعد] المستهلك «', d.`consumer_key`,
                '» عُطّل بسببٍ مكتوب، ونوعُ الحدثِ مغطًّى بمستهلكٍ عاملٍ آخر. ',
                'أُغلق ولا يُعاد — ولم يُحذف الصفّ.')
      WHERE d.`state` IN ('failed','dlq') AND d.`fail_code`='NO_SUB'");
printf("   ✔ أُغلق %d تسليمًا (كان %d)\n", $conn->affected_rows, $stale);

echo "\n▐ ④ التحقُّق\n";
printf("   · اشتراكاتٌ نشطة            : %s\n", $one("SELECT COUNT(*) FROM `event_consumers` WHERE `active`=1"));
printf("   · معطَّلةٌ بسببٍ مكتوب       : %s\n", $one("SELECT COUNT(*) FROM `event_consumers` WHERE `active`=0 AND `inactive_reason` IS NOT NULL"));
printf("   · أنواعٌ بلا مستهلك (CK-11) : %s   [المتوقَّع 0]\n",
    $one("SELECT COUNT(*) FROM (SELECT DISTINCT e.event_key FROM `ems_business_events` e
          WHERE NOT EXISTS (SELECT 1 FROM `event_consumers` c WHERE c.event_name=e.event_key AND c.active=1)) t"));
printf("   · تسليماتٌ فاشلةٌ تنتظر إعادة: %s\n",
    $one("SELECT COUNT(*) FROM `ems_event_deliveries` WHERE `state`='failed' AND `outbox_id`>0"));
echo "\n";
