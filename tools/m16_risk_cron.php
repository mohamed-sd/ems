<?php
/**
 * tools/m16_risk_cron.php — نبضةُ إدارةِ المخاطرِ (M-16 المرحلتان ٩ و١٢ و١٣)
 * ───────────────────────────────────────────────────────────────────────────
 * تشغّل ما تفرضه الوثيقةُ آليًّا ولا يُدخَل بيدٍ:
 *   ① قواعدُ الإشاراتِ الثلاثَ عشرةَ الدورية (§13-5) — SG-10 وSG-14 وSG-15
 *      لحظيةٌ تُطلق من مسارِ فعلِها لا من هنا.
 *   ② قراءةُ المؤشراتِ الآلية (المرحلة ١٢: «تُقرأ من النظامِ آليًّا») وتصعيدُ الحرج.
 *   ③ مسحُ المراجعاتِ المستحقةِ (المرحلة ١٣) وتصعيدُ المتأخرِ لمديرِ المخاطر.
 *   ④ إعادةُ حكمِ الشهيةِ (المرحلة ٩) على ما لم يُحكم عليه بعد.
 *
 * التشغيل:  php tools/m16_risk_cron.php [--dry] [--company=4]
 * الخروج 0 دائمًا ما لم يفشل الاتصال — النبضةُ لا تُسقط جدولَ المهامِّ بفشلِ قاعدة.
 *
 * لماذا mysqli_report(MYSQLI_REPORT_OFF): طبقةُ الناشرِ تعتمد على أن يعود
 * execute() بـfalse عند 1062 لتردَّ مرجعَ الحدثِ الأولِ (عطالة). ومع الافتراضِ
 * الرامي في PHP 8 يصير التكرارُ استثناءً فتُفقد العطالة — وconfig.php يضبطها
 * إطفاءً في مسارِ الويب، وهذا مسارُ CLI فيضبطها لنفسِه.
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/env.php';
require_once $ROOT . '/app/Services/Risk/RiskSignalEngine.php';

use App\Services\Risk\RiskSignalEngine;
use App\Services\Risk\RiskService;

$dry = in_array('--dry', $argv, true);
$onlyCompany = 0;
foreach ($argv as $a) {
    if (strpos($a, '--company=') === 0) { $onlyCompany = (int) substr($a, 10); }
}

$host = (string) ems_env('DB_HOST', 'localhost:3307');
$port = 3306;
if (strpos($host, ':') !== false) { list($host, $p) = explode(':', $host, 2); $port = (int) $p; }
$db = new mysqli($host, (string) ems_env('DB_USER'), (string) ems_env('DB_PASS'), (string) ems_env('DB_NAME'), $port);
if ($db->connect_error) { fwrite(STDERR, 'اتصال: ' . $db->connect_error . "\n"); exit(1); }
$db->set_charset('utf8mb4');

/* الشركاتُ التي لإدارةِ المخاطرِ فيها بذرةٌ مرجعية — لا نبضةَ على شركةٍ بلا وحدات */
$companies = array();
$q = $onlyCompany > 0
    ? "SELECT DISTINCT company_id FROM risk_units WHERE active = 1 AND company_id = " . $onlyCompany
    : "SELECT DISTINCT company_id FROM risk_units WHERE active = 1";
$r = $db->query($q);
while ($x = $r->fetch_row()) { $companies[] = (int) $x[0]; }

echo "نبضة M-16" . ($dry ? ' [جفافًا — لا كتابة]' : '') . " · شركات: " . implode(', ', $companies) . "\n";
echo str_repeat('─', 74), "\n";

$grand = array('raised' => 0, 'kri_read' => 0, 'kri_breached' => 0, 'esc' => 0, 'appetite' => 0);

foreach ($companies as $co) {
    echo "شركة $co\n";

    /* ① قواعدُ الإشاراتِ الدورية */
    $rep = RiskSignalEngine::runAll($db, $co, 0, $dry);
    $sumRaised = 0;
    foreach ($rep as $code => $res) {
        $sumRaised += (int) ($res['raised'] ?? 0);
        $mark = empty($res['ok']) ? '✘' : (($res['raised'] ?? 0) > 0 ? '●' : '·');
        printf("  %s %-6s رُصد %-4s أُطلق %-4s %s\n", $mark, $code,
            (string) ($res['matched'] ?? 0), (string) ($res['raised'] ?? 0),
            (string) ($res['note'] ?? $res['reason'] ?? ''));
    }
    $grand['raised'] += $sumRaised;

    /* ② قراءةُ المؤشراتِ الآلية */
    $k = RiskSignalEngine::readKris($db, $co, 0, $dry);
    printf("  ◆ المؤشرات: قُرئت %d · تجاوزت الحرج %d · بلا قارئ %d\n",
        $k['read'], $k['breached'], $k['skipped']);
    $grand['kri_read'] += $k['read'];
    $grand['kri_breached'] += $k['breached'];

    /* ③ المراجعاتُ المستحقة */
    $s = RiskSignalEngine::sweepOverdueReviews($db, $co, 0, $dry);
    printf("  ◆ مراجعات متأخرة: %d · صُعِّد %d\n", $s['overdue'], $s['escalated']);
    $grand['esc'] += $s['escalated'];

    /* ④ حكمُ الشهيةِ لما لم يُحكم عليه (المرحلة ٩) */
    $n = 0;
    if (!$dry) {
        $st = $db->prepare("SELECT id FROM risk_register
                             WHERE company_id = ? AND state <> 'closed' AND merged_into_id IS NULL
                               AND current_level IS NOT NULL AND current_level <> ''
                               AND appetite_verdict IS NULL");
        $st->bind_param('i', $co);
        $st->execute();
        $ids = array();
        $res = $st->get_result();
        while ($x = $res->fetch_assoc()) { $ids[] = (int) $x['id']; }
        $st->close();
        foreach ($ids as $rid) { RiskService::compareAppetite($db, $co, $rid, 0); $n++; }
    }
    printf("  ◆ حكم الشهية: %d خطرًا\n", $n);
    $grand['appetite'] += $n;
    echo "\n";
}

echo str_repeat('─', 74), "\n";
printf("الحصيلة: إشارات %d · مؤشرات %d (حرج %d) · تصعيدات %d · شهية %d\n",
    $grand['raised'], $grand['kri_read'], $grand['kri_breached'], $grand['esc'], $grand['appetite']);
exit(0);
