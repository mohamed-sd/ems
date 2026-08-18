<?php
/**
 * 2027_06_28_bus_actions_handler_fix.php
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ عيبٌ **سابقٌ للحملةِ** رصدته المراجعةُ العكسية (2026-08-18): الفحصُ الحاكمُ
 *   ② «أفعالٌ بلا معالج» راسبٌ بفعلَين من تسجيلِ ENG-01 (هجرة 2027_05_26):
 *
 *   ① `bus.event.publish` مسجَّلٌ بمعالجٍ **لا وجودَ له**:
 *      `App\Services\Bus\EventOutboxPublisher` — والصنفُ الحيُّ في الكودِ
 *      اسمُه `EventOutboxFanout` وليس فيه `publish`؛ والناشرُ الحقيقيُّ
 *      المقيسُ هو `App\Core\EventPublisher::publish` (السطر 243).
 *      فالسجلُّ يشير إلى اسمٍ لم يُبنَ قطُّ — والتصحيحُ يوجّهه إلى ما يعمل.
 *
 *   ② `bus.board.view` بلا معالجٍ إطلاقًا — وهو **فعلُ قراءةٍ** (is_write=0)
 *      لشاشةِ لوحةِ الناقل، ولا يليق به معالجُ كتابة. فمعالجُه شاشتُه نفسُها
 *      عبرَ `handler_path` — وهو ما يقبله الفحصُ للأفعالِ القارئة.
 *
 * ◆ ولا يُمسُّ منطقُ نشرٍ ولا مستهلك: تصحيحُ **تسميةٍ في السجلِّ** ليطابق
 *   الكودَ القائمَ — «المخطّطُ أصدق» ولا يُخترع صنفٌ ليُرضي سجلًّا.
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
if ($conn->connect_errno) { exit("تعذّر: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');
$one = function ($s) use ($conn) { $r = $conn->query($s); return $r ? ($r->fetch_row()[0] ?? null) : null; };

echo "\n▐ الحالُ قبل\n";
$r = $conn->query("SELECT action_code, handler_class, handler_method, handler_path, is_write
                     FROM actions WHERE action_code IN ('bus.event.publish','bus.board.view')");
while ($x = $r->fetch_assoc()) {
    printf("   · %-20s class=%-45s method=%-10s path=%s\n", $x['action_code'],
        $x['handler_class'] ?: '—', $x['handler_method'] ?: '—', $x['handler_path'] ?: '—');
}

/* ① الناشرُ الحقيقيُّ المقيسُ من الكود
   ◆ فخٌّ مقيس: الشرطةُ المائلةُ تُبتلع مرتين (سلسلةُ PHP ثم SQL) فتُخزَّن
     «AppCoreEventPublisher» بلا فواصلَ ويرسب الفحصُ نفسُه — فالربطُ المحضَّر. */
$cls = 'App' . chr(92) . 'Core' . chr(92) . 'EventPublisher';
$stp = $conn->prepare("UPDATE actions SET handler_class = ?, handler_method = 'publish'
                        WHERE action_code = 'bus.event.publish'");
$stp->bind_param('s', $cls);
$stp->execute();
printf("\n   ✔ bus.event.publish ⇐ %s::publish (%d)\n", $cls, $stp->affected_rows);
$stp->close();

/* ② فعلُ القراءةِ معالجُه شاشتُه */
$conn->query("UPDATE actions
                 SET handler_path = 'Governance/bus_board.php'
               WHERE action_code = 'bus.board.view' AND (handler_path IS NULL OR handler_path = '')");
printf("   ✔ bus.board.view ⇐ handler_path = Governance/bus_board.php (%d)\n", $conn->affected_rows);

echo "\n▐ التحقق\n";
printf("   · ملفُّ الناشر موجود : %s\n", is_file($ROOT . '/app/Core/EventPublisher.php') ? 'نعم' : 'لا');
printf("   · وفيه publish      : %s\n",
    strpos((string) @file_get_contents($ROOT . '/app/Core/EventPublisher.php'), 'function publish') !== false ? 'نعم' : 'لا');
printf("   · شاشةُ اللوحةِ موجودة: %s\n", is_file($ROOT . '/Governance/bus_board.php') ? 'نعم' : 'لا');
echo "\n◆ الشاهدُ: tools/act_checks.php — الفحصُ ② يجب أن يعود صفرًا\n";
