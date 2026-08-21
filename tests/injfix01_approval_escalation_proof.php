<?php
/**
 * tests/injfix01_approval_escalation_proof.php — INJ-FIX-01 · GAP-04
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المعيار**: «**صفرُ خطوةٍ أقدمَ من مهلتِها بلا تصعيد**».
 *
 * ◆ **والمهلةُ تُقرأ من `gov_ladders` لا من ثابتٍ في الفاحص** — فمهلةٌ منسوخةٌ
 *   في فاحصٍ تتفرَّق عن مهلةِ النظامِ بلا إنذار.
 *
 * ◆ **والحزامُ السلبيُّ يُجرَّب**: يُدرَج تصعيدٌ وهميٌّ لخطوةٍ لا وجودَ لها ثم
 *   يُتحقَّق أنه لا يُحسَب لخطوةٍ حقيقية — فبوابةٌ تقبل أيَّ تصعيدٍ لأيِّ خطوةٍ
 *   تُغلق بالعدِّ لا بالربط.
 *
 * التشغيل: php tests/injfix01_approval_escalation_proof.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/env.php';
$h = ems_env('DB_HOST'); $prt = 3306;
if (strpos($h, ':') !== false) { list($h, $prt) = explode(':', $h); $prt = (int) $prt; }
$conn = new mysqli($h, ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER'),
    ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS'),
    ems_env('DB_NAME'), $prt);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

$ok = 0; $bad = 0;
function chk($cond, $msg)
{
    global $ok, $bad;
    if ($cond) { $ok++; echo "  ✔ {$msg}\n"; } else { $bad++; echo "  ✘ {$msg}\n"; }
}

/* ══ ① المهلةُ مُعلَنةٌ في النظامِ لا في الفاحص ═══════════════════════════ */
echo "══ ① المهلُ تُقرأ من `gov_ladders` ══\n";
$sla = array(); $DEFAULT = 48;
$q = $conn->query("SELECT `entity_type`, MIN(`escalate_after_hours`) hrs
                     FROM `gov_ladders` WHERE COALESCE(`entity_type`,'') <> ''
                    GROUP BY `entity_type`");
while ($q && $x = $q->fetch_assoc()) { $sla[$x['entity_type']] = (int) $x['hrs']; }
chk(count($sla) > 0, 'المهلُ مُعلَنةٌ لأنواعِ الكيانات — ' . count($sla) . ' نوعًا');
$ps = array();
foreach ($sla as $k => $v) { $ps[] = "{$k}={$v}س"; }
echo '     ' . implode(' · ', $ps) . " · الافتراضيُّ المُعلَن {$DEFAULT}س\n";

/* ══ ② الحكم — صفرُ متجاوزٍ بلا تصعيد ═════════════════════════════════════ */
echo "\n══ ② صفرُ خطوةٍ أقدمَ من مهلتِها بلا تصعيد ══\n";
$pend = 0; $over = 0; $naked = array(); $seedSkipped = 0;
$q = $conn->query("SELECT s.`id`, r.`entity_type`, r.`entity_id`,
                          TIMESTAMPDIFF(HOUR, s.`created_at`, NOW()) age
                     FROM `approval_steps` s JOIN `approval_requests` r ON r.`id` = s.`request_id`
                    WHERE s.`status` = 'pending' AND r.`status` = 'pending'");
$rows = array();
while ($q && $x = $q->fetch_assoc()) { $rows[] = $x; }
foreach ($rows as $x) {
    $pend++;
    $et = (string) $x['entity_type'];
    if (preg_match('/^legacy_uat_/', $et)) { $seedSkipped++; continue; }
    $lim = isset($sla[$et]) ? $sla[$et] : $DEFAULT;
    if ((int) $x['age'] <= $lim) { continue; }
    $over++;
    $r2 = $conn->query("SELECT COUNT(*) FROM `work_escalations`
                         WHERE `item_kind` = 'approval_step' AND `item_ref` = " . (int) $x['id']);
    if ($r2 && (int) $r2->fetch_row()[0] === 0) {
        $naked[] = "#{$x['id']} {$et}#{$x['entity_id']} ({$x['age']}س)";
    }
}
printf("     معلَّق=%d · متجاوزُ المهلة=%d · بذرٌ مستثنًى=%d\n", $pend, $over, $seedSkipped);
chk(count($naked) === 0, '**صفرُ متجاوزٍ بلا تصعيد** — ' . count($naked)
    . (count($naked) ? ' — ' . implode(' · ', array_slice($naked, 0, 4)) : ''));

/* ══ ③ التصعيدُ مربوطٌ بخطوةٍ حقيقيةٍ لا معدودٌ فحسب ═════════════════════ */
echo "\n══ ③ التصعيدُ مربوطٌ لا معدود ══\n";
$q = $conn->query("SELECT COUNT(*) FROM `work_escalations` e
                    WHERE e.`item_kind`='approval_step'
                      AND NOT EXISTS (SELECT 1 FROM `approval_steps` s WHERE s.`id` = e.`item_ref`)");
$dangling = $q ? (int) $q->fetch_row()[0] : -1;
chk($dangling === 0, "صفرُ تصعيدٍ يشير إلى خطوةٍ لا وجودَ لها — {$dangling}");
$q = $conn->query("SELECT COUNT(*) FROM `work_escalations`
                    WHERE `item_kind`='approval_step' AND COALESCE(`note`,'') = ''");
$noNote = $q ? (int) $q->fetch_row()[0] : -1;
chk($noNote === 0, "لكلِّ تصعيدٍ سببٌ مكتوبٌ يسمّي الخطوةَ وعمرَها والمهلة — بلا={$noNote}");

/* ── الحزامُ السلبيّ: تصعيدٌ لخطوةٍ وهميةٍ لا يُغني عن الحقيقية ── */
$GHOST = 999999998;
$conn->query("DELETE FROM `work_escalations` WHERE `item_kind`='approval_step' AND `item_ref`={$GHOST}");
$conn->query("INSERT INTO `work_escalations`
    (`company_id`,`item_kind`,`item_ref`,`to_user_id`,`level`,`reason`,`note`,`created_by`)
    VALUES (4,'approval_step',{$GHOST},1,1,'sla_response','__probe__',0)");
$q = $conn->query("SELECT COUNT(*) FROM `work_escalations` e
                    WHERE e.`item_kind`='approval_step'
                      AND NOT EXISTS (SELECT 1 FROM `approval_steps` s WHERE s.`id` = e.`item_ref`)");
$after = $q ? (int) $q->fetch_row()[0] : -1;
$conn->query("DELETE FROM `work_escalations` WHERE `item_kind`='approval_step' AND `item_ref`={$GHOST}");
chk($after === 1, "الحزامُ السلبيُّ: تصعيدٌ لخطوةٍ وهميةٍ **يُرصَد** ({$after}) — فالربطُ يُفحَص لا يُفترَض");
$q = $conn->query("SELECT COUNT(*) FROM `work_escalations` WHERE `note`='__probe__'");
chk($q && (int) $q->fetch_row()[0] === 0, 'أثرُ الفحصِ مكنوس');

echo "\n" . str_repeat('─', 66) . "\n";
printf("النتيجة: %d نجاح · %d رسوب\n", $ok, $bad);
exit($bad === 0 ? 0 : 1);
