<?php
/**
 * tools/uxui_pollution_classify.php — التصنيفُ الثلاثيُّ للتلوث · **بلا كتابةِ بيانات**
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ قرارُ المالك (2026-08-19 · أولًا ③ + سادسًا):
 *   · «**حقولُ الملاحظاتِ لا تُمس** — نصُّها مشروعٌ ولو ذكر اختبارًا».
 *   · «وحدها الـ111 موضعًا في **حقولِ الرمزِ والتعداد** تُعالَج».
 *   · «‏0/560 مصنفةً لا تبقى إلى آخرِ المرحلة — أكملِ التصنيفَ الثلاثيَّ
 *     بمقامٍ لكلِّ نوع، ثم عالجِ المؤكدَ وحدَه».
 *
 * ◆ والتصنيفُ **بقاعدةٍ تُفحص لا بحدس**:
 *   `LEGIT_PRODUCTION` — حقلُ ملاحظاتٍ/وصفٍ مشروع (بقرارِ المالكِ صراحةً) ·
 *                        أو جدولُ حجرٍ/تدقيقٍ يحفظ النصَّ عمدًا.
 *   `TEST_POLLUTION`   — **خانةُ رمزٍ أو تعدادٍ** تحمل جملةً بشريةً (فيها فراغٌ
 *                        وحروفٌ عربيةٌ) — ولا تحتملها الخانةُ بتعريفِها.
 *   `SUSPECT`          — اسمٌ ظاهرٌ أو نصٌّ عامٌّ فيه العلامة: يحتاج نظرًا
 *                        بشريًّا ولا يُعالَج آليًّا.
 *
 * ◆ **وصفرُ كتابةٍ في بياناتِ العمل** — الكتابةُ في سجلِّ الجردِ وحدَه.
 *
 * التشغيل:
 *   php tools/uxui_pollution_classify.php            جردٌ بلا كتابة
 *   php tools/uxui_pollution_classify.php --apply    يكتب الحكمَ في سجلِّ الجرد
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mysqli_report(MYSQLI_REPORT_OFF);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$conn = new mysqli($host, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال\n"); }
$conn->set_charset('utf8mb4');
$APPLY = in_array('--apply', $argv, true);

$rows = array();
$r = $conn->query("SELECT id, table_name, column_name, marker, hits, sample_value, column_role
                     FROM gov_pollution_findings ORDER BY id");
while ($r && ($x = $r->fetch_assoc())) { $rows[] = $x; }
if (!$rows) { exit("سجلُّ الجردِ فارغ — شغِّلْ uxui_pollution_scan.php --save\n"); }

/* جداولٌ وظيفتُها حفظُ النصِّ الملوَّثِ عمدًا — لا تُحسب تلوثًا */
$KEEPERS = array('uat_field_quarantine', 'approval_rules_quarantine', 'gov_pollution_findings',
                 'gov_policy_changes', 'gov_permission_corrections');

$verdicts = array('LEGIT_PRODUCTION' => 0, 'TEST_POLLUTION' => 0, 'SUSPECT' => 0);
$plan = array();
foreach ($rows as $x) {
    $role = (string) $x['column_role'];
    $val  = (string) $x['sample_value'];
    /* جملةٌ بشرية: فراغٌ + حروفٌ عربية */
    $isSentence = (strpos($val, ' ') !== false) && preg_match('~[\x{0600}-\x{06FF}]~u', $val);

    if (in_array($x['table_name'], $KEEPERS, true)) {
        $v = 'LEGIT_PRODUCTION';
        $why = 'جدولُ حجرٍ/سجلٍّ يحفظ النصَّ الأصليَّ عمدًا — وجودُه فيه هو وظيفتُه';
    } elseif ($role === 'حقلٌ نصيٌّ مشروع') {
        $v = 'LEGIT_PRODUCTION';
        $why = 'حقلُ ملاحظاتٍ/وصفٍ — **لا يُمسُّ بقرارِ المالكِ صراحةً**: نصُّه مشروعٌ ولو ذكر اختبارًا';
    } elseif (($role === 'خانةُ رمز' || $role === 'تعداد') && $isSentence) {
        $v = 'TEST_POLLUTION';
        $why = 'جملةٌ بشريةٌ في خانةِ رمزٍ/تعدادٍ — لا تحتملها الخانةُ بتعريفِها';
    } elseif ($role === 'خانةُ رمز' || $role === 'تعداد') {
        $v = 'SUSPECT';
        $why = 'خانةُ رمزٍ فيها العلامةُ بلا جملةٍ بشرية — قد تكون رمزًا مشروعًا يحوي الحروف';
    } else {
        $v = 'SUSPECT';
        $why = 'اسمٌ ظاهرٌ أو نصٌّ عامّ — يحتاج نظرًا بشريًّا ولا يُعالَج آليًّا';
    }
    $verdicts[$v]++;
    $plan[] = array('id' => (int) $x['id'], 'v' => $v, 'why' => $why, 'row' => $x);
}

echo "════ التصنيفُ الثلاثيُّ للتلوث — صفرُ كتابةٍ في بياناتِ العمل ════\n";
echo "  مواضعُ الجرد: " . count($rows) . "\n\n";
$tot = count($rows);
foreach ($verdicts as $k => $n) {
    printf("  %-18s %-4d = %s٪ من %d\n", $k, $n, ($tot ? round($n * 100 / $tot, 1) : 0), $tot);
}

/* المؤكَّدُ وحدَه — بمقامِه */
$confirmed = array();
foreach ($plan as $p) { if ($p['v'] === 'TEST_POLLUTION') { $confirmed[] = $p; } }
echo "\n▐ المؤكَّدُ (TEST_POLLUTION) — وهو وحدَه ما يُعالَج\n";
$byTable = array();
foreach ($confirmed as $p) { $byTable[$p['row']['table_name']][] = $p['row']['column_name']; }
echo "  مواضع: " . count($confirmed) . " · في " . count($byTable) . " جدولًا · إصابات: "
   . array_sum(array_map(function ($p) { return (int) $p['row']['hits']; }, $confirmed)) . "\n";
$i = 0;
foreach ($byTable as $t => $cols) {
    echo "    · {$t} → " . implode(' · ', array_unique($cols)) . "\n";
    if (++$i >= 8) { echo "    … و" . (count($byTable) - 8) . " جدولًا آخر\n"; break; }
}

echo "\n▐ ما لا يُمسُّ بقرارِ المالك\n";
$legit = 0; $legitHits = 0;
foreach ($plan as $p) { if ($p['v'] === 'LEGIT_PRODUCTION') { $legit++; $legitHits += (int) $p['row']['hits']; } }
echo "  {$legit} موضعًا · {$legitHits} إصابةً — حقولُ ملاحظاتٍ وسجلاتُ حجر\n";

if (!$APPLY) { echo "\n  ▸ جردٌ بلا كتابة — أضِفْ --apply لكتابةِ الحكمِ في سجلِّ الجرد\n"; exit(0); }

$u = $conn->prepare("UPDATE gov_pollution_findings SET verdict = ? WHERE id = ?");
$n = 0;
foreach ($plan as $p) { $u->bind_param('si', $p['v'], $p['id']); if ($u->execute()) { $n++; } }
echo "\n  ▸ كُتب الحكمُ لـ{$n} موضعًا في **سجلِّ الجرد** — وصفرُ كتابةٍ في بياناتِ العمل\n";
$left = (int) $conn->query("SELECT COUNT(*) c FROM gov_pollution_findings WHERE verdict='UNCLASSIFIED'")->fetch_assoc()['c'];
echo "  ما زال UNCLASSIFIED: {$left}\n";
