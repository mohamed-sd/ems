<?php
/**
 * tools/rpr03_consumer_emitters.php — تفكيكُ الاشتراكاتِ الميتةِ المفردة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ مقياسُ `RPR-03` #٣ كان يعدُّ الاشتراكاتِ المعلَّقةَ على مفردةٍ لم تُنطق
 *   عدًّا واحدًا — **وجنساها مختلفان علاجًا** (قِيس ٢٠٢٦-٠٨-٣١):
 *   ① `EMITTER_EXISTS` — ناطقُ المفردةِ **موجودٌ في الشيفرةِ** (خدمةُ
 *      دورتِه تنشرها) والدورةُ لم تجرِ بعدُ ⇒ **فجوةُ استعمالٍ** كالمحطّاتِ
 *      الصامتةِ — بابُها تشغيلُ الدورةِ (UAT/أعمال) لا سلكٌ مقطوع.
 *   ② `NO_EMITTER` — لا ناطقَ في الشيفرةِ كلِّها ⇒ اشتراكٌ على واقعةٍ
 *      مصمَّمةٍ **لم يُسلَّك نشرُها** — وسلكُ ناطقٍ ماليٍّ جديدٍ فعلُ
 *      أعمالٍ يُؤلَّف لا يُرتجل.
 * ◆ القياسُ من الشيفرةِ الحيّةِ (مسحُ النصِّ عن المفردةِ خارجَ tools/) —
 *   وكلُّ حكمٍ يسمّي ناطقَه أو غيابَه.
 *
 * التشغيل: php tools/rpr03_consumer_emitters.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn']; $conn->set_charset('utf8mb4');

$toks = array();
$r = $conn->query("SELECT DISTINCT event_name FROM event_consumers e
     WHERE e.active = 1 AND e.produces = 'write' AND e.consumer_class NOT LIKE '%GovernanceWatch%'
       AND NOT EXISTS(SELECT 1 FROM ems_business_events b WHERE b.event_key = e.event_name)");
while ($x = $r->fetch_row()) { $toks[] = (string) $x[0]; }

$dirs = array('app', 'includes', 'Finance', 'Financing', 'Suppliers', 'Procurement', 'Portal',
              'Operations', 'Timesheet', 'FinRequests', 'admin', 'Contracts', 'Clients',
              'Maintenance', 'Transport', 'Workforce', 'Employees', 'Tickets', 'Risk', 'Governance');
$speak = array_fill_keys($toks, array());
foreach ($dirs as $d) {
    if (!is_dir($ROOT . '/' . $d)) { continue; }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT . '/' . $d,
            FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        $f = (string) $f;
        if (substr($f, -4) !== '.php') { continue; }
        $src = (string) @file_get_contents($f);
        foreach ($toks as $t) {
            if ($speak[$t] !== array() && count($speak[$t]) > 2) { continue; }
            if (strpos($src, $t) !== false) {
                $rel = substr(str_replace(chr(92), '/', $f), strlen($ROOT) + 1);
                $speak[$t][] = $rel;
            }
        }
    }
}
$withEmitter = array(); $noEmitter = array();
foreach ($toks as $t) {
    if ($speak[$t]) { $withEmitter[$t] = $speak[$t][0]; } else { $noEmitter[] = $t; }
}
printf("═══ الاشتراكاتُ الميتةُ المفردةِ مفكَّكةً من الشيفرة ═══\n");
printf("ميتة المفردة كلها = %d\n", count($toks));
printf("EMITTER_EXISTS = %d\n", count($withEmitter));
printf("NO_EMITTER = %d\n", count($noEmitter));
foreach ($withEmitter as $t => $f) { printf("  ✔ %-34s ناطقُه %s\n", $t, $f); }
foreach ($noEmitter as $t) { printf("  ⛔ %-34s بلا ناطقٍ في الشيفرةِ كلِّها\n", $t); }
