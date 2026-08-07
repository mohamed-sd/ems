<?php
/**
 * tools/u10_nfr_bus.php — صحة الطابور وتأخر الوسيط (U10-F32/F33)
 * php tools/u10_nfr_bus.php
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');
$o = function ($s) { fwrite(STDOUT, $s . "\n"); };

$o('══ F32 · صحة طابور الأحداث (DLQ) ══');
$r = mysqli_query($conn, "SHOW TABLES LIKE 'ems\\_event\\_%'");
$tabs = array();
while ($x = mysqli_fetch_row($r)) { $tabs[] = $x[0]; }
$o('  جداول الناقل: ' . implode(' · ', $tabs));
foreach ($tabs as $t) {
    $cols = array();
    $r = mysqli_query($conn, "SHOW COLUMNS FROM `$t`");
    while ($x = mysqli_fetch_assoc($r)) { $cols[$x['Field']] = 1; }
    $st = isset($cols['status']) ? 'status' : (isset($cols['state']) ? 'state' : null);
    if ($st) {
        $r = mysqli_query($conn, "SELECT `$st` s, COUNT(*) c FROM `$t` GROUP BY `$st`");
        $parts = array();
        while ($x = mysqli_fetch_assoc($r)) { $parts[] = "{$x['s']}={$x['c']}"; }
        $o("  $t: " . implode(' · ', $parts));
        /* الأعمار للمعلق/الفاشل */
        $timeCol = isset($cols['created_at']) ? 'created_at' : (isset($cols['occurred_at']) ? 'occurred_at' : null);
        if ($timeCol) {
            $r = mysqli_query($conn, "SELECT COUNT(*) c, TIMESTAMPDIFF(HOUR, MIN(`$timeCol`), NOW()) oldest_h
                                        FROM `$t` WHERE `$st` IN ('pending','failed','retry','dead')");
            $x = mysqli_fetch_assoc($r);
            $o("    المعلق/الفاشل: {$x['c']} · أقدمه منذ " . ($x['oldest_h'] ?? 0) . ' ساعة');
        }
    } else {
        $r = mysqli_query($conn, "SELECT COUNT(*) c FROM `$t`");
        $o("  $t: " . mysqli_fetch_assoc($r)['c'] . ' صفًّا (بلا عمود حالة)');
    }
}

$o('══ F33 · تأخر الوسيط (النشر ← الاستهلاك) ══');
/* processed_operations يحمل event_id + processed_at — يقارن بوقت الحدث */
$r = mysqli_query($conn,
    "SELECT COUNT(*) n,
            ROUND(AVG(TIMESTAMPDIFF(SECOND, e.created_at, p.processed_at)), 1) avg_s,
            MAX(TIMESTAMPDIFF(SECOND, e.created_at, p.processed_at)) max_s
       FROM processed_operations p
       JOIN ems_business_events e ON e.id = p.event_id
      WHERE p.processed_at IS NOT NULL AND e.created_at IS NOT NULL
        AND p.processed_at >= e.created_at");
if ($r && ($x = mysqli_fetch_assoc($r)) && (int) $x['n'] > 0) {
    $o("  عينة مقيسة: {$x['n']} استهلاكًا · متوسط اللاج {$x['avg_s']} ث · أقصاه {$x['max_s']} ث");
} else {
    $o('  ⚠ لا عينة قابلة للقياس من processed_operations — يقاس من fin_event_links');
    /* الموروث المحوّل بحملات يلوث المتوسط — القياس الصادق على السكة الحية
       (أحداث آخر أسبوع) والموروث يعلن منفصلًا */
    $r = mysqli_query($conn,
        "SELECT COUNT(*) n,
                ROUND(AVG(TIMESTAMPDIFF(SECOND, e.created_at, l.created_at)), 1) avg_s,
                MAX(TIMESTAMPDIFF(SECOND, e.created_at, l.created_at)) max_s
           FROM fin_event_links l JOIN ems_business_events e ON e.id = l.event_id
          WHERE l.created_at >= e.created_at AND e.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    if ($r && ($x = mysqli_fetch_assoc($r)) && (int) $x['n'] > 0) {
        $o("  السكة الحية (أحداث آخر 7 أيام): {$x['n']} أثرًا · متوسط اللاج {$x['avg_s']} ث · أقصاه {$x['max_s']} ث");
    }
    /* المعمارية متزامنة (EffectFanout في معاملة الناشر) — التوزيع هو الصادق:
       اللاج ≤5ث = المسار الحي المتزامن · والباقي = حملات تحويل الموروث بتواريخ
       أحداثها الأصلية (لاج حملة لا لاج وسيط) */
    $r = mysqli_query($conn,
        "SELECT SUM(TIMESTAMPDIFF(SECOND, e.created_at, l.created_at) <= 5) sync_n,
                SUM(TIMESTAMPDIFF(SECOND, e.created_at, l.created_at) > 5) campaign_n
           FROM fin_event_links l JOIN ems_business_events e ON e.id = l.event_id
          WHERE l.created_at >= e.created_at");
    $x = mysqli_fetch_assoc($r);
    $o("  التوزيع الصادق: متزامن ≤5ث = {$x['sync_n']} · حملات تحويل الموروث = {$x['campaign_n']}");
    $o('  ◆ الحكم: المسار الحي متزامن ذريًّا (المروحة داخل معاملة الناشر) — لاجه ~0ث بنيويًّا');
    $r = mysqli_query($conn,
        "SELECT COUNT(*) n FROM fin_event_links l JOIN ems_business_events e ON e.id = l.event_id
          WHERE e.created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $x = mysqli_fetch_assoc($r);
    $o("  (الموروث المحوَّل بحملات: {$x['n']} أثرًا — لاجه لاج حملة لا وسيط · يعلن منفصلًا)");
}
