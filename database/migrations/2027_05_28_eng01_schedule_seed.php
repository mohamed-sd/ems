<?php
/**
 * 2027_05_28_eng01_schedule_seed.php
 * ═══════════════════════════════════════════════════════════════════════════
 * ENG-01 ⑥ · جدولةُ الأنواعِ الثمانيةِ وإلغاءُ الأوامرِ اليدوية
 * ───────────────────────────────────────────────────────────────────────────
 * كلُّ صفٍّ هنا يحمل replaces_manual: الأمرَ الذي كان موظفٌ يكتبه بيدِه.
 * والأمرُ نفسُه لم يُحذف — بل صار يرفض التشغيلَ اليدويَّ ويحيل إلى الطابور
 * (includes/manual_run_guard.php)، فمن ناداه قرأ أين صارت مهمتُه.
 *
 * ومهلةُ إنذارِ التوقفِ لكلٍّ من دوريتِه: ما يعمل كلَّ خمسِ دقائقَ يُنذَر بعد
 * ساعة، وما يعمل شهريًّا يُنذَر بعد يومين — «فتوقفُ العاملِ صامتًا أخطرُ من
 * فشلِ مهمة».
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

// الأدوار: 19 مدير المالية · 15 إدارة الصلاحيات (الحوكمة) · 1 إدارة التشغيل
$SCHED = array(
    //  job_type            cron            max_rt  alert   owner  الأمرُ اليدويُّ الملغى
    array('fin_posting',       '*/10 * * * *',   600,   3600, 19, 'php Operations/cron_fin_posting.php --company=N --limit=1000'),
    array('capacity_rollup',   '*/15 * * * *',   600,   7200,  1, 'php Operations/cron_capacity_rollup.php --company=N'),
    array('event_retry',       '*/5 * * * *',    300,   3600, 15, 'php cron_events.php'),
    array('statement_build',   '0 1 * * *',      900,  90000, 19, 'php Finance/cron_finance_fin.php'),
    array('alert_dispatch',    '*/5 * * * *',    300,   3600, 15, '—  (لم يكن له أمرٌ — الإنذارُ كان لا يُرفع أصلًا)'),
    array('depreciation_run',  '0 2 1 * *',      900, 180000, 19, 'زرٌّ في شاشةِ الأصولِ يضغطه محاسب'),
    array('settlement_recalc', '0 3 * * *',      600,  90000, 19, 'إعادةُ احتسابٍ يدويةٌ عند الطلب'),
    array('pilot_monitor',     '*/30 * * * *',   300,   7200, 15, 'مراجعةٌ بصريةٌ للوحاتِ التجربة'),
);

echo "\n▐ جدولةُ الأنواعِ الثمانيةِ — وكلُّ صفٍّ يُلغي أمرًا يدويًّا\n\n";

$st = $conn->prepare(
    "INSERT INTO `ems_job_schedule`
        (`company_id`,`job_type`,`cron_expr`,`max_runtime_seconds`,`alert_after_seconds`,
         `owner_role_id`,`is_active`,`replaces_manual`,`created_by`)
     VALUES (1,?,?,?,?,?,1,?,0)
     ON DUPLICATE KEY UPDATE
        `cron_expr`=VALUES(`cron_expr`), `max_runtime_seconds`=VALUES(`max_runtime_seconds`),
        `alert_after_seconds`=VALUES(`alert_after_seconds`), `owner_role_id`=VALUES(`owner_role_id`),
        `replaces_manual`=VALUES(`replaces_manual`), `is_active`=1"
);
$n = 0;
foreach ($SCHED as $s) {
    list($type, $cron, $rt, $al, $own, $repl) = $s;
    $st->bind_param('ssiiis', $type, $cron, $rt, $al, $own, $repl);
    if ($st->execute()) {
        $n++;
        printf("   ✔ %-18s %-14s إنذار=%-7s دور=%-3s ⇐ %s\n", $type, $cron, $al . 'ث', $own, mb_substr($repl, 0, 48));
    } else {
        echo "   ✗ $type — " . $st->error . "\n";
    }
}
$st->close();

echo "\n   المجموع: $n جدولةً\n";
$tot = $conn->query("SELECT COUNT(*) FROM `ems_job_schedule` WHERE `is_active`=1")->fetch_row()[0];
$repl = $conn->query("SELECT COUNT(*) FROM `ems_job_schedule` WHERE `replaces_manual` IS NOT NULL AND `replaces_manual` NOT LIKE '—%'")->fetch_row()[0];
echo "   جدولاتٌ نشطة: $tot · منها ألغت أمرًا يدويًّا: $repl\n\n";
