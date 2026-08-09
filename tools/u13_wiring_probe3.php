<?php
/**
 * tools/u13_wiring_probe3.php — إثباتُ كرونِ التنبيهاتِ حيًّا
 * ═══════════════════════════════════════════════════════════════════════════
 * OBL-0125: «◆ التنبيهُ المُهمَلُ بعد مهلتِه ينشر إشارةَ خطر — فالتنبيهُ الذي لا
 *   يُصعَّد لا يُنذر.» وسجلُّ التنبيهاتِ كان قائمًا بلا مُطلِق.
 *
 * يُنشئ التزامًا بفتراتٍ **متأخرةٍ وقادمة**، يُشغّل الكرونَ كما يُشغِّله النظام،
 * ثم يتحقق أن:
 *   ① تنبيهَ الاستحقاقِ القادمِ أُطلق (AL-01) وصار مهمةً
 *   ② تنبيهَ المتأخرِ أُطلق (AL-03) والمتأخرَ رُحِّل إلى الذممِ الدائنة (OR-05)
 *   ③ التصنيفَ أُعيد آليًّا (OR-03)
 *   ④ إعادةَ التشغيلِ لا تُكرِّر تنبيهًا (عطالة)
 *   ⑤ التنبيهَ المُهمَلَ بعد مهلتِه يُصعَّد (OBL-0125)
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_OFF);
mb_internal_encoding('UTF-8');

$ROOT = dirname(__DIR__);
require_once $ROOT . '/config.php';
require_once $ROOT . '/app/Services/Finance/ObligationEngine.php';
if (!isset($conn) || !($conn instanceof mysqli)) { exit("لا اتصال\n"); }
$conn->set_charset('utf8mb4');
$OE = 'App\Services\Finance\ObligationEngine';

$CHECKS = array();
function chk($id, $t, $ok, $d = '') { global $CHECKS; $CHECKS[] = array($id, $t, (bool) $ok, (string) $d); }
function one($db, $sql) { $r = $db->query($sql); if (!$r) { echo "  ✗ SQL: " . $db->error . "\n"; return null; } $x = $r->fetch_row(); return $x ? $x[0] : null; }

$co = (int) one($conn, "SELECT company_id FROM fin_accountants
                         WHERE (is_deleted IS NULL OR is_deleted=0)
                         GROUP BY company_id ORDER BY COUNT(*) DESC LIMIT 1");
$stamp = 'ALRT-' . substr(sha1((string) getmypid() . microtime(true)), 0, 8);
$ref   = 'probe:' . $stamp;
/* يبدأ قبلَ ستةِ أشهرٍ وينتهي بعدَ ستة — ففيه متأخرٌ وقادمٌ معًا. */
$start = date('Y-m-01', strtotime('-6 months'));
$end   = date('Y-m-t', strtotime('+6 months'));
echo "الكيان: $co · البصمة: $stamp · المدة: $start → $end\n\n";

$OE::avoidanceTest($conn, array('company_id' => $co, 'contract_kind' => 'supplier',
    'contract_ref' => $ref, 'contract_value' => 130000, 'cancellable' => 0,
    'cancel_cost' => 5000, 'decided_by' => 1));
$gen = $OE::generateSchedule($conn, array(
    'company_id' => $co, 'ob_type' => 'OB-01', 'side' => 'payable',
    'contract_kind' => 'supplier', 'contract_ref' => $ref, 'counterparty' => 'موردُ فحصِ التنبيهات',
    'total_value' => 130000, 'start_date' => $start, 'end_date' => $end, 'generated_by' => 1));
$obl = (int) ($gen['obligation_id'] ?? 0);
chk('A-01', 'التزامٌ بفتراتٍ متأخرةٍ وقادمةٍ معًا', $obl > 0,
    $obl ? ('التزام #' . $obl . ' · ' . ($gen['accounting_periods'] ?? '?') . ' فترة') : (string) ($gen['reason'] ?? ''));
if (!$obl) { goto report; }

$overdueBefore = (int) one($conn, "SELECT COUNT(*) FROM fin_obl_schedule
                                    WHERE obligation_id={$obl} AND due_date < CURDATE() AND state='scheduled'");
$soonBefore = (int) one($conn, "SELECT COUNT(*) FROM fin_obl_schedule
                                 WHERE obligation_id={$obl} AND state='scheduled'
                                   AND due_date >= CURDATE() AND due_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)");
echo "  متأخرةٌ قبلَ الكرون: $overdueBefore · قادمةٌ خلالَ أسبوع: $soonBefore\n\n";

/* ── تشغيلُ الكرونِ كما يُشغِّله النظام ─────────────────────────────────── */
$out = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($ROOT . '/Finance/cron_obligation_alerts.php') . ' 2>&1');
echo '  ' . trim((string) $out) . "\n";

$fired = (int) one($conn, "SELECT COUNT(*) FROM fin_obl_alert_log WHERE obligation_id={$obl}");
chk('A-02', 'الكرونُ أطلق تنبيهاتِ هذا الالتزام', $fired > 0, "تنبيهات: $fired");

$al03 = (int) one($conn, "SELECT COUNT(*) FROM fin_obl_alert_log WHERE obligation_id={$obl} AND alert_code='AL-05'");
chk('A-03', 'تنبيهُ الالتزامِ المتأخرِ أُطلق (AL-05)', $al03 > 0 || $overdueBefore === 0,
    "متأخرٌ منبَّهٌ عليه: $al03 · متأخراتٌ قائمة: $overdueBefore");

$withTask = (int) one($conn, "SELECT COUNT(*) FROM fin_obl_alert_log
                               WHERE obligation_id={$obl} AND work_item_id IS NOT NULL");
chk('A-04', 'والتنبيهُ صار مهمةً بمهلةٍ ومسؤول (BR-02)', $withTask > 0, "تنبيهاتٌ بمهام: $withTask");

$movedN = (int) one($conn, "SELECT COUNT(*) FROM fin_obl_schedule
                             WHERE obligation_id={$obl} AND state='moved_to_payables'");
chk('A-05', 'والمستحقُّ المتأخرُ رُحِّل إلى الذممِ الدائنة (OR-05)',
    $movedN === $overdueBefore, "رُحِّل $movedN من $overdueBefore");

$long = (int) one($conn, "SELECT COUNT(*) FROM fin_obl_schedule WHERE obligation_id={$obl} AND term_class='long'");
chk('A-06', 'والتصنيفُ أُعيد آليًّا — ولا فترةَ خارجَ السنةِ في هذا العقد (OR-03)',
    $long === 0, "طويل: $long");

/* ── العطالة ────────────────────────────────────────────────────────────── */
$b = (int) one($conn, "SELECT COUNT(*) FROM fin_obl_alert_log WHERE obligation_id={$obl}");
$bw = (int) one($conn, "SELECT COUNT(*) FROM work_items WHERE source_ref LIKE 'obl_alert:%'");
shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($ROOT . '/Finance/cron_obligation_alerts.php') . ' 2>&1');
$a = (int) one($conn, "SELECT COUNT(*) FROM fin_obl_alert_log WHERE obligation_id={$obl}");
$aw = (int) one($conn, "SELECT COUNT(*) FROM work_items WHERE source_ref LIKE 'obl_alert:%'");
chk('A-07', 'إعادةُ تشغيلِ الكرونِ لا تُكرِّر تنبيهًا ولا مهمة (عطالة)',
    $b === $a && $bw === $aw, "تنبيهات $b→$a · مهام $bw→$aw");

/* ── التصعيد: تنبيهٌ مضت مهلتُه ───────────────────────────────────────── */
$conn->query("UPDATE fin_obl_alert_log SET due_at = DATE_SUB(NOW(), INTERVAL 2 DAY), state='open'
               WHERE obligation_id={$obl} LIMIT 1");
shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($ROOT . '/Finance/cron_obligation_alerts.php') . ' 2>&1');
$esc = (int) one($conn, "SELECT COUNT(*) FROM fin_obl_alert_log
                          WHERE obligation_id={$obl} AND state='escalated' AND escalated_at IS NOT NULL");
chk('A-08', 'والتنبيهُ المُهمَلُ بعد مهلتِه يُصعَّد إشارةَ خطر (OBL-0125)',
    $esc > 0, "مُصعَّد: $esc");

/* ── التنظيف ──────────────────────────────────────────────────────────── */
report:
if (!empty($obl)) {
    $conn->query("DELETE FROM work_items WHERE id IN
                   (SELECT work_item_id FROM fin_obl_alert_log WHERE obligation_id={$obl} AND work_item_id IS NOT NULL)");
    $conn->query("DELETE FROM fin_obl_alert_log WHERE obligation_id={$obl}");
    $conn->query("DELETE FROM fin_obl_schedule WHERE obligation_id={$obl}");
}
$conn->query("DELETE FROM fin_obl_register WHERE contract_ref='" . $conn->real_escape_string($ref) . "'");
$conn->query("DELETE FROM fin_obl_avoidance WHERE contract_ref='" . $conn->real_escape_string($ref) . "'");

$pass = 0;
echo "\n" . str_repeat('═', 78) . "\n  إثباتُ الوصل — كرونُ تنبيهاتِ الالتزامات\n" . str_repeat('═', 78) . "\n\n";
foreach ($CHECKS as $c) {
    if ($c[2]) { $pass++; }
    printf("   %s %-6s %-54s\n", $c[2] ? '✔' : '✗', $c[0], $c[1]);
    if ($c[3] !== '') { printf("        %s\n", $c[3]); }
}
printf("\n%s\n  %d/%d %s\n%s\n", str_repeat('═', 78), $pass, count($CHECKS),
    $pass === count($CHECKS) ? '— الكرونُ يعمل' : '— ناقص', str_repeat('═', 78));
exit($pass === count($CHECKS) ? 0 : 1);
