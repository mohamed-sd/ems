<?php
/**
 * tools/u13_wiring_probe.php — إثباتُ الوصلِ حيًّا: من نشرِ الحدثِ إلى مهمةِ المحاسب
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ لا يكفي أن توجد الخدمةُ وتُثبَت وحدَها. السؤالُ: **أتعمل في دورةِ العملِ
 *   الحقيقية؟** فهذا الفاحصُ ينشر واقعةً ماليةً بالناقلِ نفسِه الذي تنشر به
 *   الإداراتُ، ثم يُشغّل موزِّعَ الأحداثِ كما يُشغِّله الكرون، ثم يتحقق أن:
 *     ① صفًّا وُلد في `fin_routing_log` بمسارِه وتخصصِه
 *     ② مهمةً ظهرت في مساحةِ عملِ محاسبِ التخصص (OBL-0003)
 *     ③ الحدثَ مربوطٌ بالتوجيهِ من الطرفين (`financial_event_id`)
 *     ④ إعادةَ التشغيلِ لا تُكرِّر (العطالة)
 *
 * وينظّف أثرَه كاملًا.
 *
 * التشغيل: php tools/u13_wiring_probe.php
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);
ini_set('display_errors', '1');
mysqli_report(MYSQLI_REPORT_OFF);
mb_internal_encoding('UTF-8');

$ROOT = dirname(__DIR__);
require_once $ROOT . '/config.php';
require_once $ROOT . '/app/Core/EventPublisher.php';
require_once $ROOT . '/app/Core/EventDispatcher.php';
require_once $ROOT . '/app/Services/Finance/RoutingConsumer.php';

if (!isset($conn) || !($conn instanceof mysqli)) { exit("لا اتصال\n"); }
$conn->set_charset('utf8mb4');

$CHECKS = array();
function chk($id, $t, $ok, $d = '') { global $CHECKS; $CHECKS[] = array($id, $t, (bool) $ok, (string) $d); }
function one($db, $sql) { $r = $db->query($sql); if (!$r) { echo "  ✗ SQL: " . $db->error . "\n"; return null; } $x = $r->fetch_row(); return $x ? $x[0] : null; }

$co = (int) one($conn, "SELECT company_id FROM fin_accountants
                         WHERE (is_deleted IS NULL OR is_deleted=0)
                         GROUP BY company_id ORDER BY COUNT(*) DESC LIMIT 1");
$stamp = 'WIRE-' . substr(sha1((string) getmypid() . microtime(true)), 0, 10);
echo "الكيان: $co · البصمة: $stamp\n\n";

/* ── ① النشر: كما تنشر إدارةُ المشترياتِ واقعتَها ─────────────────────── */
$pub = null;
try {
    $pub = \App\Core\EventPublisher::publish($conn, array(
        'event_key'      => 'expense.purchase.recorded',
        'category'       => 'financial',
        'source_module'  => 'procurement',
        'company_id'     => $co,
        'entity_type'    => 'wiring_probe',
        'entity_id'      => 1,
        'occurred_at'    => gmdate('Y-m-d H:i:s'),
        'created_by'     => 1,
        'amount'         => 1250.00,
        'currency'       => 'USD',
        'source_ref'     => $stamp,
        'idempotency_key' => 'u13probe:' . $stamp,
        'notes'          => 'فحصُ وصلٍ — واقعةُ شراءٍ تشغيلي',
        /* عقدُ §٩ يوجب حمولةً صريحةً — ولا حدثَ بلا بيانِ ما وقع. */
        'payload'        => array('probe' => 'u13_wiring', 'stamp' => $stamp,
                                  'purpose' => 'إثباتُ وصلِ التوجيهِ بالناقل'),
    ));
} catch (\Throwable $e) {
    echo "✗ تعذّر النشر: " . get_class($e) . ': ' . $e->getMessage() . "\n";
}
chk('W-01', 'الإدارةُ تنشر واقعتَها في الناقل', !empty($pub['id']),
    $pub ? ('حدث #' . $pub['id'] . ' · ' . $pub['event_no']) : 'لم يُنشر');
if (empty($pub['id'])) { goto report; }

$evId = intval($pub['id']);
$evNo = (string) $pub['event_no'];

/* ── ② التوزيع: كما يُشغِّله cron_events ─────────────────────────────── */
$dispatcher = new \App\Core\EventDispatcher($conn);
$dispatcher->register(
    \App\Services\Finance\RoutingConsumer::NAME,
    \App\Services\Finance\RoutingConsumer::handler(),
    $evId - 1                       // يبدأ قبلَ حدثِنا مباشرةً
);
$stats = $dispatcher->runOnce();
$s = isset($stats[\App\Services\Finance\RoutingConsumer::NAME])
   ? $stats[\App\Services\Finance\RoutingConsumer::NAME] : array('processed' => 0, 'failed' => 0);
chk('W-02', 'موزِّعُ الأحداثِ يستهلكها بمستهلكِ التوجيه',
    (int) $s['processed'] >= 1 && (int) $s['failed'] === 0,
    'عولج ' . $s['processed'] . ' · أخفق ' . $s['failed']);

/* ── ③ الشاهد: صفٌّ في سجلِّ التوجيهِ بمسارِه وتخصصِه ─────────────────── */
$log = null;
$r = $conn->query("SELECT * FROM fin_routing_log
                    WHERE company_id = {$co} AND source_kind = 'financial_event'
                      AND source_ref = '" . $conn->real_escape_string($evNo) . "' LIMIT 1");
if ($r) { $log = $r->fetch_assoc(); }
chk('W-03', 'التوجيهُ يكتب شاهدَه بمسارِه وتخصصِه',
    $log && $log['route_code'] === 'RT-01' && $log['target_spec'] === 'ACC-01',
    $log ? ('مسار ' . $log['route_code'] . ' · تخصص ' . $log['target_spec']
          . ' · بـ' . $log['resolved_by']) : 'لا شاهد');

/* ── ④ المهمة: تظهر في مساحةِ عملِ محاسبِ التخصص (OBL-0003) ──────────── */
$wi = $log && $log['work_item_id'] ? intval($log['work_item_id']) : 0;
$wiRow = null;
if ($wi > 0) {
    $r = $conn->query("SELECT id, title, assigned_user_id, due_at, status FROM work_items WHERE id = {$wi}");
    if ($r) { $wiRow = $r->fetch_assoc(); }
}
chk('W-04', 'الطلبُ يظهر مهمةً في مساحةِ محاسبِ تخصصِه (OBL-0003)',
    $wiRow && intval($wiRow['assigned_user_id']) > 0,
    $wiRow ? ('مهمة #' . $wiRow['id'] . ' → مستخدم ' . $wiRow['assigned_user_id']
            . ' · مهلة ' . $wiRow['due_at']) : 'لا مهمة');

/* ── ⑤ التتبعُ من الطرفين ────────────────────────────────────────────── */
chk('W-05', 'الشاهدُ مربوطٌ بالحدثِ الذي ولّده',
    $log && intval($log['financial_event_id']) === $evId,
    $log ? ('حدث ' . var_export($log['financial_event_id'], true) . ' = ' . $evId) : '—');

/* ── ⑥ العطالة: إعادةُ التشغيلِ لا تُكرِّر ───────────────────────────── */
$before = (int) one($conn, "SELECT COUNT(*) FROM fin_routing_log
                             WHERE source_ref = '" . $conn->real_escape_string($evNo) . "'");
$wiBefore = (int) one($conn, "SELECT COUNT(*) FROM work_items
                               WHERE source_ref = 'financial_event:" . $conn->real_escape_string($evNo) . "'");
$d2 = new \App\Core\EventDispatcher($conn);
$d2->register(\App\Services\Finance\RoutingConsumer::NAME . '_replay',
    \App\Services\Finance\RoutingConsumer::handler(), $evId - 1);
$d2->runOnce();
$after = (int) one($conn, "SELECT COUNT(*) FROM fin_routing_log
                            WHERE source_ref = '" . $conn->real_escape_string($evNo) . "'");
$wiAfter = (int) one($conn, "SELECT COUNT(*) FROM work_items
                              WHERE source_ref = 'financial_event:" . $conn->real_escape_string($evNo) . "'");
chk('W-06', 'إعادةُ الاستهلاكِ لا تُكرِّر توجيهًا ولا مهمة (عطالة)',
    $before === $after && $wiBefore === $wiAfter && $before > 0,
    "توجيه $before→$after · مهام $wiBefore→$wiAfter");

/* ── ⑦ الحارس: الواقعةُ صارت مؤهَّلةً للخزينةِ بعد التوجيه ───────────── */
require_once $ROOT . '/app/Services/Finance/RoutingEngine.php';
$g = \App\Services\Finance\RoutingEngine::assertRouted($conn, $co, 'financial_event', $evNo);
chk('W-07', 'وبعد التوجيهِ تجتاز الواقعةُ حارسَ الخزينة (OBL-0001)',
    !empty($g['ok']), (string) ($g['reason'] ?? ''));

/* ── التنظيف ──────────────────────────────────────────────────────────── */
report:
$esc = isset($evNo) ? $conn->real_escape_string($evNo) : $stamp;
$conn->query("DELETE FROM work_items WHERE source_ref = 'financial_event:{$esc}'");
$conn->query("DELETE FROM fin_routing_log WHERE source_ref = '{$esc}'");
$conn->query("DELETE FROM fin_event_effects WHERE event_id = " . (isset($evId) ? (int) $evId : 0));
$conn->query("DELETE FROM fin_financial_events WHERE source_ref = '" . $conn->real_escape_string($stamp) . "'");
$conn->query("DELETE FROM ems_business_events WHERE idempotency_key = 'u13probe:" . $conn->real_escape_string($stamp) . "'");
$conn->query("DELETE FROM ems_event_consumers WHERE consumer_name LIKE '%_replay'");

$pass = 0;
echo "\n" . str_repeat('═', 78) . "\n  إثباتُ الوصل — من نشرِ الحدثِ إلى مهمةِ المحاسب\n" . str_repeat('═', 78) . "\n\n";
foreach ($CHECKS as $c) {
    if ($c[2]) { $pass++; }
    printf("   %s %-6s %-52s\n", $c[2] ? '✔' : '✗', $c[0], $c[1]);
    if ($c[3] !== '') { printf("        %s\n", $c[3]); }
}
printf("\n%s\n  %d/%d %s\n%s\n", str_repeat('═', 78), $pass, count($CHECKS),
    $pass === count($CHECKS) ? '— الوصلُ يعمل' : '— الوصلُ ناقص', str_repeat('═', 78));
exit($pass === count($CHECKS) ? 0 : 1);
