<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * ENG-01 · البند ① من TS-01 — استعلامُ المطابقةِ قبلَ الإنشاء
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tools/eng01_schema_match.php
 *
 * ينفّذ نمطَ TSP-0003-ج على كلِّ جدولٍ مقترَحٍ في المحرّكاتِ الثلاثة:
 *   SELECT TABLE_NAME FROM information_schema.TABLES
 *   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME LIKE '%<مفهوم>%'
 *
 * فإن وُجد نظيرٌ قائمٌ وُسِّع ولم يُنشأ جدولٌ ثالث.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }

/** @var mysqli $conn */
$db = $conn;

// الجداولُ المقترَحةُ الستةُ ومفاهيمُها — كلُّ مفهومٍ بأنماطِ بحثٍ متعددةٍ لأن الاسمَ قد يختلف
$PROPOSED = [
    'ems_event_outbox'        => ['outbox', 'event', 'publish', 'bus'],
    'ems_event_subscriptions' => ['subscri', 'consumer', 'listener', 'handler'],
    'ems_event_deliveries'    => ['deliver', 'dispatch', 'fanout', 'effect'],
    'ems_job_queue'           => ['job', 'queue', 'task', 'worker'],
    'ems_job_schedule'        => ['schedule', 'cron', 'recurr'],
    'fa_asset_hours'          => ['asset', 'depreciat', 'depr', 'hours'],
    'dr_drills'               => ['drill', 'restore', 'backup', 'recovery', 'dr_'],
];

echo "══════════════════════════════════════════════════════════════════\n";
echo " ENG-01 · استعلامُ المطابقةِ على المخططِ الحيّ (TSP-0003-ج)\n";
echo " القاعدة: " . $db->query("SELECT DATABASE()")->fetch_row()[0] . "\n";
$tot = $db->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()")->fetch_row()[0];
echo " إجماليُّ الجداول: $tot\n";
echo "══════════════════════════════════════════════════════════════════\n\n";

foreach ($PROPOSED as $proposed => $patterns) {
    echo "▐ المقترَح: $proposed\n";

    // هل الاسمُ المقترَحُ نفسُه موجود؟
    $st = $db->prepare("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?");
    $st->bind_param('s', $proposed);
    $st->execute();
    $exact = $st->get_result()->fetch_row();
    $st->close();
    echo "  · الاسمُ نفسُه: " . ($exact ? "موجود ✔" : "غير موجود") . "\n";

    $hits = [];
    foreach ($patterns as $p) {
        $like = '%' . $p . '%';
        $st = $db->prepare(
            "SELECT TABLE_NAME, TABLE_ROWS, ENGINE FROM information_schema.TABLES
             WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME LIKE ? ORDER BY TABLE_NAME"
        );
        $st->bind_param('s', $like);
        $st->execute();
        $r = $st->get_result();
        while ($row = $r->fetch_assoc()) { $hits[$row['TABLE_NAME']] = $row; }
        $st->close();
    }
    ksort($hits);
    echo "  · نظائرُ محتملة (" . count($hits) . "): ";
    if (!$hits) { echo "لا شيء\n"; }
    else {
        echo "\n";
        foreach ($hits as $n => $row) {
            printf("      %-44s rows=%-8s %s\n", $n, (string)($row['TABLE_ROWS'] ?? '?'), $row['ENGINE']);
        }
    }
    echo "\n";
}

// ───────── القاموسُ والتنقّلُ والصلاحياتُ — الجداولُ الحاكمة ─────────
echo "══════════════════════════════════════════════════════════════════\n";
echo " جداولُ الحوكمةِ التي سيُسجَّل فيها (الأفعالُ · الوحداتُ · التنقّل)\n";
echo "══════════════════════════════════════════════════════════════════\n";
foreach (['gov_actions','gov_action_dictionary','permission_modules','modules','nav09_import','nav_items','gov_screens','gov_pages'] as $t) {
    $st = $db->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?");
    $st->bind_param('s', $t); $st->execute();
    $ex = (int)$st->get_result()->fetch_row()[0]; $st->close();
    if ($ex) {
        $c = $db->query("SELECT COUNT(*) FROM `$t`")->fetch_row()[0];
        printf("  ✔ %-28s صفوف=%s\n", $t, $c);
    } else {
        printf("  ✗ %-28s غير موجود\n", $t);
    }
}
echo "\n";
