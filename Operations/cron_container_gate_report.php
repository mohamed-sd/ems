<?php
/**
 * Operations/cron_container_gate_report.php — تقريرُ مطابقة بوابة الحصص الأسبوعي
 * (H-01-③ · شرطُ PLAN-01 §13-① الثاني: «تقريرُ مطابقةٍ أسبوعيٌّ يقارن ما رُفض
 * بما كان يمرّ» — والتعميمُ بعد أسبوعٍ نظيفٍ لا قبله)
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php Operations/cron_container_gate_report.php   (يدويًّا أو مجدولًا أسبوعيًّا)
 * يقرأ: أحداثَ would_block المهيكلة (activity_logs — وضعُ الرصد) + وحداتِ
 * المشاريع المفعَّلة للأسبوع الأخير، ويكتب docs/reports/CONTAINER_GATE_WEEK_<تاريخ>.md
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
require_once __DIR__ . '/../includes/cron_guard.php';
ems_cron_guard('cron_container_gate_report.php'); // INJ-0025: لا تُشغَّل من المتصفّح
require_once dirname(__DIR__) . '/app/Services/Operations/ContainerGate.php';

use App\Services\Operations\ContainerGate;

while (ob_get_level() > 0) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');

$sites = ContainerGate::enabledSites();
$mode  = ContainerGate::mode();
$since = date('Y-m-d', strtotime('-7 days'));
$today = date('Y-m-d');

$lines = array();
$lines[] = '# تقريرُ مطابقة بوابة الحصص — أسبوع ' . $since . ' → ' . $today;
$lines[] = '';
$lines[] = '> H-01-③ · شرطُ PLAN-01 §13-①: يقارن ما رُفض (أو ما كان سيُرفض تحت الرصد) بما مرّ — والتعميمُ/قلبُ enforce بعد أسبوعٍ نظيف.';
$lines[] = '';
$lines[] = '- **العلَم:** `EMS_CONTAINER_GATE=' . ($sites ? implode(',', $sites) : '(فارغ)') . '` · **الوضع:** ' . $mode;
$lines[] = '';

if (!$sites) {
    $lines[] = '**البوابةُ مطفأة** — لا مشاريعَ مفعَّلة، ولا شيءَ يُطابَق.';
} else {
    foreach ($sites as $pid) {
        $pid = (int) $pid;
        // ما سُجّل فعلًا في الأسبوع (الواقعُ الذي مرّ)
        $entries = 0;
        if ($st = $conn->prepare("SELECT COUNT(*) c FROM unit_entries WHERE project_id = ? AND created_at >= ?")) {
            $st->bind_param('is', $pid, $since);
            $st->execute();
            $entries = (int) $st->get_result()->fetch_assoc()['c'];
            $st->close();
        }
        // ما كان سيُحجب (رصدُ monitor) أو حُجب — من السجل المهيكل
        $blocks = array(); $blockTotal = 0;
        if ($st = $conn->prepare(
            "SELECT new_value FROM activity_logs
              WHERE screen_name = 'container_gate' AND action_type = 'would_block'
                AND created_at >= ? AND new_value LIKE ?")) {
            $needle = '%\"project_id\":' . $pid . '%';
            $st->bind_param('ss', $since, $needle);
            $st->execute();
            $res = $st->get_result();
            while ($row = $res->fetch_assoc()) {
                $blockTotal++;
                $j = json_decode((string) $row['new_value'], true);
                $kinds = isset($j['kinds']) ? (string) $j['kinds'] : 'unknown';
                foreach (explode(',', $kinds) as $k) {
                    $k = trim($k);
                    if ($k !== '') { $blocks[$k] = ($blocks[$k] ?? 0) + 1; }
                }
            }
            $st->close();
        }
        $lines[] = '## المشروع ' . $pid;
        $lines[] = '';
        $lines[] = '| القياس | العدد |';
        $lines[] = '|---|---|';
        $lines[] = '| وحداتٌ سُجّلت في الأسبوع (مرّت) | ' . $entries . ' |';
        $lines[] = '| محاولاتٌ ' . ($mode === 'monitor' ? 'كانت ستُحجب (رصد)' : 'حُجبت') . ' | ' . $blockTotal . ' |';
        foreach ($blocks as $kind => $n) {
            $label = array('no_operator' => 'معدةٌ بلا مشغّل', 'no_container' => 'حاويةٌ ناقصة',
                           'no_rotation' => 'مشغّلٌ بلا دورة تناوب')[$kind] ?? $kind;
            $lines[] = '| — منها: ' . $label . ' | ' . $n . ' |';
        }
        $lines[] = '';
        $lines[] = $blockTotal === 0
            ? '**أسبوعٌ نظيف** — صفرُ ما كان سيُحجب' . ($mode === 'monitor' ? ' ⇒ شرطُ قلب `enforce` مستوفًى (N-04 §2-④)' : '') . '.'
            : '**ليس نظيفًا بعد** — عالج الأسبابَ أعلاه (الدوراتُ من شاشة الحاويات · التوزيعُ منها) قبل قلب `enforce`.';
        $lines[] = '';
    }
}

$dir = dirname(__DIR__) . '/docs/reports';
if (!is_dir($dir)) { mkdir($dir, 0755, true); }
$path = $dir . '/CONTAINER_GATE_WEEK_' . str_replace('-', '', $today) . '.md';
file_put_contents($path, implode("\n", $lines) . "\n");
fwrite(STDOUT, "كُتب التقرير: {$path}\n");
exit(0);
