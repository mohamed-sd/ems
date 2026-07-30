<?php
/**
 * database/seeds/m25_meter_backfill.php — التقاطُ قراءات العدّاد الموروثة (M-25)
 * ═══════════════════════════════════════════════════════════════════════════
 * `timesheet.start_hours/end_hours` (+دقائقُ وثوانٍ) عدّادُ ساعاتٍ **فعليٌّ**
 * كان يُكتب في الميدان ولا يُقرأ. هذا الباذر يحوّل كلَّ صفٍّ يحمل قراءةً إلى
 * صفٍّ في `meter_readings` **بتاريخه ومرجعه** — عبر الخدمة نفسِها فحراسُها
 * تسري (الرتابةُ والعطالة)، بترتيب التاريخ كي تُبنى السلسلةُ صاعدةً.
 *
 * ── ما لا يفعله هذا الباذر ─────────────────────────────────────────────────
 * · **لا يحوّل `equipments.operating_hours`** إلى قراءات: قيمةٌ **بلا تاريخ**
 *   ليست واقعةً، واختراعُ تاريخٍ لها تلفيقٌ (عقيدة ⑦). تبقى حيث هي وتُعلَن.
 * · لا يمسّ صفَّ دوامٍ واحدًا · ولا يحذف شيئًا · وعاطلٌ بمفتاح القراءة.
 *
 * التشغيل:  php database/seeds/m25_meter_backfill.php [--dry-run]
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/app/Core/TenantRegistry.php';
require_once dirname(__DIR__, 2) . '/app/Core/TenantContext.php';
require_once dirname(__DIR__, 2) . '/app/Core/TenantGateException.php';
require_once dirname(__DIR__, 2) . '/app/Core/TenantDb.php';
require_once dirname(__DIR__, 2) . '/app/Services/Fleet/MeterReadingService.php';

use App\Core\TenantDb;
use App\Core\TenantContext;
use App\Services\Fleet\MeterReadingService as MRS;

while (ob_get_level() > 0) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');

$dryRun = in_array('--dry-run', isset($argv) ? $argv : array(), true);
function say($m) { fwrite(STDOUT, $m . "\n"); }

// المرشَّحون: كلُّ صفِّ دوامٍ يحمل عدّادَ نهايةٍ ومعدةً — بترتيب التاريخ
$sql = "SELECT t.id, t.company_id, t.`date`, o.equipment AS eq_id,
               t.end_hours, t.end_minutes, t.end_seconds
          FROM timesheet t
          JOIN operations o ON o.id = t.operator
         WHERE o.equipment IS NOT NULL
           AND (COALESCE(t.end_hours,0) > 0 OR COALESCE(t.end_minutes,0) > 0 OR COALESCE(t.end_seconds,0) > 0)
         ORDER BY o.equipment, t.`date`, t.id";
$res = $conn->query($sql);
$rows = array();
while ($x = $res->fetch_assoc()) { $rows[] = $x; }

say('المرشَّحون في سجل الدوام: ' . count($rows) . ' صفًّا يحمل قراءةَ عدّاد.');

if ($dryRun) {
    foreach ($rows as $r) {
        $v = round((float) $r['end_hours'] + ((float) $r['end_minutes'] / 60) + ((float) $r['end_seconds'] / 3600), 2);
        say("  [dry-run] المعدة {$r['eq_id']} · {$r['date']} · {$v} ساعة · TS-{$r['id']}");
    }
    say('لم يُكتب شيء.');
    exit(0);
}

$made = 0; $idle = 0; $declared = array();
foreach ($rows as $r) {
    $co = (int) $r['company_id'];
    $gate = new TenantDb($conn, TenantContext::forSystem($co, 0, '', true));
    $out = MRS::captureFromTimesheet($conn, $gate, $co, array(
        'id' => (int) $r['id'], 'eq_id' => (int) $r['eq_id'], 'date' => (string) $r['date'],
        'end_hours' => $r['end_hours'], 'end_minutes' => $r['end_minutes'], 'end_seconds' => $r['end_seconds'],
    ), 0);
    if (!empty($out['ok'])) {
        $made++;
        say("  ✔ المعدة {$r['eq_id']} · {$r['date']} — قراءةٌ #{$out['reading_id']}");
    } elseif (strpos((string) $out['skipped'], '409') === 0) {
        $idle++;
    } else {
        // تعذّرٌ **يُعلَن** ولا يُصحَّح تخمينًا
        $declared[] = "المعدة {$r['eq_id']} · {$r['date']} · TS-{$r['id']} — {$out['skipped']}";
    }
}

say("\n── الحصيلة ──");
say("  قراءاتٌ التُقطت: {$made} · عاطلةٌ (قائمةٌ سلفًا): {$idle} · متعذّرةٌ معلَنة: " . count($declared));
foreach ($declared as $d) { say("    ⚠ {$d}"); }

// ما لا يُرحَّل — يُعلَن بعدده
$flat = $conn->query("SELECT COUNT(*) n FROM equipments
                       WHERE operating_hours IS NOT NULL AND operating_hours > 0")->fetch_assoc()['n'];
say("\n  ⓘ {$flat} معدةً تحمل `operating_hours` **بلا تاريخ** — لم تُحوَّل قراءاتٍ عمدًا:");
say('    قيمةٌ بلا تاريخٍ ليست واقعةً، واختراعُ تاريخٍ لها تلفيق. تبقى حيث هي');
say('    وتصير السلسلةُ مصدرَ الحقيقة فورَ تسجيل أول قراءةٍ لكل معدة.');
exit(0);
