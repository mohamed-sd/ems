<?php
/**
 * tools/u13_wiring_probe2.php — إثباتُ وصلِ محرّكِ الالتزاماتِ بنفاذِ العقد
 * ═══════════════════════════════════════════════════════════════════════════
 * OR-01/OBL-0021: «◆ الالتزامُ يُنشأ عند **اعتمادِ العقدِ** لا عند أولِ دفعة —
 *   فالعقدُ النافذُ يولّد جدولَ استحقاقٍ لكلِّ مدتِه فورًا.»
 *
 * يُنشئ عقدًا حقيقيًّا في `contracts` باثني عشرَ شهرًا يبدأ **يومَ عشرين**، ثم
 * يستدعي أثرَ التوقيعِ نفسَه الذي تستدعيه آلةُ الحالة، ثم يتحقق أن:
 *   ① اختبارَ التجنبِ سُجِّل بنتيجتِه (OBL-0200)
 *   ② الجدولَ وُلد **دفعةً واحدة** لكلِّ المدة (SY-06)
 *   ③ ثلاثةَ عشرَ استحقاقًا محاسبيًّا واثني عشرَ إقفالًا تعاقديًّا (SY-02/SY-03)
 *   ④ الكسرَ الأولَ والأخيرَ موسومان ومحسوبان بالتناسبِ اليومي (SY-04/SY-05)
 *   ⑤ الطبقاتِ الثلاثَ أعمدةً مستقلة (OBL-0137)
 *   ⑥ **صفرَ قيدٍ** مصدرُه المحرّك (OR-10)
 *   ⑦ العطالةَ: توقيعٌ ثانٍ لا يُكرِّر الجدول
 *
 * وينظّف أثرَه كاملًا.
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_OFF);
mb_internal_encoding('UTF-8');

$ROOT = dirname(__DIR__);
require_once $ROOT . '/config.php';
require_once $ROOT . '/app/Services/Contract/ContractSignedEffects.php';
require_once $ROOT . '/app/Services/Finance/ObligationEngine.php';
if (!isset($conn) || !($conn instanceof mysqli)) { exit("لا اتصال\n"); }
$conn->set_charset('utf8mb4');

$CHECKS = array();
function chk($id, $t, $ok, $d = '') { global $CHECKS; $CHECKS[] = array($id, $t, (bool) $ok, (string) $d); }
function one($db, $sql) { $r = $db->query($sql); if (!$r) { echo "  ✗ SQL: " . $db->error . "\n"; return null; } $x = $r->fetch_row(); return $x ? $x[0] : null; }

$co = (int) one($conn, "SELECT company_id FROM fin_accountants
                         WHERE (is_deleted IS NULL OR is_deleted=0)
                         GROUP BY company_id ORDER BY COUNT(*) DESC LIMIT 1");
$stamp = 'OBLW-' . substr(sha1((string) getmypid() . microtime(true)), 0, 8);
/* اثنا عشرَ شهرًا يبدأ يومَ عشرين — المثالُ الذي تسميه الوثيقةُ نصًّا. */
$start = '2026-01-20';
echo "الكيان: $co · البصمة: $stamp · البدء: $start\n\n";

/* ── عقدٌ حقيقيٌّ في الجدولِ الحيّ ─────────────────────────────────────── */
/* ◆ `contracts.project_id` عليه مفتاحٌ أجنبيٌّ على `project` — فالافتراضيُّ 0
     ينفجر. يُؤخذ مشروعٌ قائمٌ من الكيانِ نفسِه. */
$proj = (int) one($conn, "SELECT id FROM project WHERE company_id={$co} ORDER BY id LIMIT 1");
if ($proj <= 0) { $proj = (int) one($conn, "SELECT id FROM project ORDER BY id LIMIT 1"); }
$st = $conn->prepare("INSERT INTO contracts
    (company_id, project_id, contract_signing_date, contract_duration_months, actual_start,
     total_contract_permonth, price_currency_contract, first_party, second_party,
     retention_pct, guarantees, contract_status, status)
    VALUES (?,?,?,12,?,10000.00,'USD','إكوبيشن',?,10.00,'ضمانُ حسنِ تنفيذ','نافذ','1')");
$second = 'عميلُ فحصِ الوصل ' . $stamp;
$st->bind_param('iisss', $co, $proj, $start, $start, $second);
$ok = $st->execute();
$cid = $ok ? $st->insert_id : 0;
$st->close();
chk('O-01', 'عقدٌ نافذٌ في الجدولِ الحيّ', $cid > 0, $cid ? ('عقد #' . $cid) : $conn->error);
if (!$cid) { goto report; }

/* ── أثرُ التوقيعِ — الدالةُ نفسُها التي تستدعيها آلةُ الحالة ──────────── */
$gate = new class($conn, $cid) {
    private $c; private $id;
    public function __construct($c, $id) { $this->c = $c; $this->id = $id; }
    public function selectOne($t, $o) {
        $r = $this->c->query("SELECT * FROM `$t` WHERE id = " . (int) $this->id . " LIMIT 1");
        return $r ? $r->fetch_assoc() : null;
    }
};
$res = \App\Services\Contract\ContractSignedEffects::apply($conn, $gate, $co, $cid, array('actor' => 1));
$ref = 'contract:' . $cid;

/* ── ① اختبارُ التجنب ─────────────────────────────────────────────────── */
$av = null;
$r = $conn->query("SELECT * FROM fin_obl_avoidance WHERE company_id={$co} AND contract_ref='{$ref}'");
if ($r) { $av = $r->fetch_assoc(); }
chk('O-02', 'اختبارُ التجنبِ سُجِّل بنتيجتِه قبلَ الجدول (OBL-0200)',
    $av && $av['verdict'] !== '',
    $av ? ('نتيجة ' . $av['verdict'] . ' · غيرُ قابلٍ للتجنب ' . $av['unavoidable']
         . ' · نسبتُه ' . $av['unavoidable_pct'] . '٪') : 'لا نتيجة');

/* ── ② الالتزامُ وجدولُه ──────────────────────────────────────────────── */
$ob = null;
$r = $conn->query("SELECT * FROM fin_obl_register WHERE company_id={$co} AND contract_ref='{$ref}' AND state='active'");
if ($r) { $ob = $r->fetch_assoc(); }
chk('O-03', 'العقدُ النافذُ ولّد التزامَه فورًا (OR-01)',
    $ob !== null, $ob ? ('التزام ' . $ob['obligation_no'] . ' · نوع ' . $ob['ob_type']
                       . ' · جانب ' . $ob['side']) : 'لا التزام');
if (!$ob) { goto report; }
$obId = intval($ob['id']);

$n = (int) one($conn, "SELECT COUNT(*) FROM fin_obl_schedule WHERE obligation_id={$obId}");
chk('O-04', 'ثلاثةَ عشرَ استحقاقًا محاسبيًّا لعقدٍ يبدأ يومَ عشرين (SY-02 · AR-01)',
    $n === 13 && (int) $ob['accounting_periods'] === 13, "الفترات: $n · المسجَّل: " . $ob['accounting_periods']);
chk('O-05', 'واثنا عشرَ إقفالًا تعاقديًّا — ولا يُخلطان (SY-03 · AR-02)',
    (int) $ob['contract_periods'] === 12, 'التعاقدية: ' . $ob['contract_periods']);

/* ── ③ الكسرُ موسومٌ ومحسوبٌ بالتناسبِ اليومي ─────────────────────────── */
$parts = array();
$r = $conn->query("SELECT period_no, period_start, period_end, is_partial, partial_days, month_days,
                          l1_commitment, term_class
                     FROM fin_obl_schedule WHERE obligation_id={$obId} ORDER BY period_no");
while ($r && $x = $r->fetch_assoc()) { $parts[] = $x; }
$firstPartial = $parts && (int) $parts[0]['is_partial'] === 1;
$lastPartial  = $parts && (int) $parts[count($parts) - 1]['is_partial'] === 1;
$midFull = true;
for ($i = 1; $i < count($parts) - 1; $i++) { if ((int) $parts[$i]['is_partial'] === 1) { $midFull = false; } }
chk('O-06', 'كسرٌ أولٌ وأحدَ عشرَ كاملًا وكسرٌ أخيرٌ — موسومةً صريحًا (SY-05)',
    $firstPartial && $lastPartial && $midFull,
    $parts ? ('الأول ' . $parts[0]['period_start'] . '→' . $parts[0]['period_end']
            . ' (' . $parts[0]['partial_days'] . '/' . $parts[0]['month_days'] . ')'
            . ' · الأخير ' . $parts[count($parts) - 1]['period_start'] . '→' . $parts[count($parts) - 1]['period_end']) : '—');

/* التناسبُ اليومي: 12 يومًا من 31 × الحصةِ الشهرية 10,000 = 3,870.97 */
$expect = round(10000 * ((int) $parts[0]['partial_days'] / (int) $parts[0]['month_days']), 2);
chk('O-07', 'الكسرُ محسوبٌ بالتناسبِ اليوميِّ من الحصةِ الشهرية (SY-04)',
    abs((float) $parts[0]['l1_commitment'] - $expect) < 0.02,
    'المحسوب ' . $parts[0]['l1_commitment'] . ' · المتوقَّع ' . $expect);

$sum = (float) one($conn, "SELECT ROUND(SUM(l1_commitment),2) FROM fin_obl_schedule WHERE obligation_id={$obId}");
chk('O-08', 'ومجموعُ الجدولِ يطابق قيمةَ العقدِ بلا تسرُّب',
    abs($sum - (float) $ob['total_value']) < 1.00, "المجموع $sum · قيمةُ العقد " . $ob['total_value']);

/* ── ④ الطبقاتُ الثلاثُ مستقلة ────────────────────────────────────────── */
$cols = (int) one($conn, "SELECT COUNT(*) FROM information_schema.COLUMNS
                           WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fin_obl_schedule'
                             AND COLUMN_NAME IN ('l1_commitment','l2_recognized','l3_open')");
$l2 = (float) one($conn, "SELECT COALESCE(SUM(l2_recognized),0) FROM fin_obl_schedule WHERE obligation_id={$obId}");
chk('O-09', 'الطبقاتُ الثلاثُ أعمدةٌ مستقلةٌ — والمعترَفُ به صفرٌ قبلَ الأداء (OBL-0137)',
    $cols === 3 && abs($l2) < 0.01, "أعمدةُ الطبقات $cols · المعترَفُ به $l2");

/* ── ⑤ التصنيفُ قصيرٌ وطويلٌ آليًّا ───────────────────────────────────── */
$short = (int) one($conn, "SELECT COUNT(*) FROM fin_obl_schedule WHERE obligation_id={$obId} AND term_class='short'");
$long  = (int) one($conn, "SELECT COUNT(*) FROM fin_obl_schedule WHERE obligation_id={$obId} AND term_class='long'");
chk('O-10', 'التصنيفُ قصيرًا أو طويلًا محسوبٌ بتاريخِ الاستحقاق (OR-03)',
    ($short + $long) === $n && $short > 0, "قصير $short · طويل $long");

/* ── ⑥ صفرُ قيدٍ مصدرُه المحرّك ──────────────────────────────────────── */
$je = (int) one($conn, "SELECT COUNT(*) FROM fin_journal_entries j JOIN fin_financial_events e ON e.id=j.event_id
                         WHERE e.source_ref='{$ref}' AND e.event_key LIKE '%obligation%'");
chk('O-11', 'المحرّكُ لا يُنشئ قيدًا — بل جدولًا معلَنًا (OR-10 · OBL-0051)',
    $je === 0, "قيودٌ من المحرّك: $je");

/* ── ⑦ العطالة: توقيعٌ ثانٍ لا يُكرِّر ───────────────────────────────── */
\App\Services\Contract\ContractSignedEffects::apply($conn, $gate, $co, $cid, array('actor' => 1));
$obs = (int) one($conn, "SELECT COUNT(*) FROM fin_obl_register WHERE company_id={$co} AND contract_ref='{$ref}'");
$sch = (int) one($conn, "SELECT COUNT(*) FROM fin_obl_schedule WHERE obligation_id={$obId}");
chk('O-12', 'إعادةُ تطبيقِ أثرِ التوقيعِ لا تُكرِّر التزامًا ولا جدولًا (عطالة)',
    $obs === 1 && $sch === $n, "التزامات $obs · فترات $sch");

/* ── التنظيف ──────────────────────────────────────────────────────────── */
report:
if (!empty($obId)) { $conn->query("DELETE FROM fin_obl_schedule WHERE obligation_id={$obId}"); }
if (!empty($ref)) {
    $conn->query("DELETE FROM fin_obl_register WHERE contract_ref='{$ref}'");
    $conn->query("DELETE FROM fin_obl_avoidance WHERE contract_ref='{$ref}'");
    $conn->query("DELETE FROM fin_financial_events WHERE source_ref='{$ref}'");
    $conn->query("DELETE FROM op_containers WHERE contract_id=" . (int) ($cid ?? 0));
}
if (!empty($cid)) { $conn->query("DELETE FROM contracts WHERE id={$cid}"); }

$pass = 0;
echo "\n" . str_repeat('═', 78) . "\n  إثباتُ الوصل — من نفاذِ العقدِ إلى جدولِ استحقاقِه\n" . str_repeat('═', 78) . "\n\n";
foreach ($CHECKS as $c) {
    if ($c[2]) { $pass++; }
    printf("   %s %-6s %-56s\n", $c[2] ? '✔' : '✗', $c[0], $c[1]);
    if ($c[3] !== '') { printf("        %s\n", $c[3]); }
}
printf("\n%s\n  %d/%d %s\n%s\n", str_repeat('═', 78), $pass, count($CHECKS),
    $pass === count($CHECKS) ? '— الوصلُ يعمل' : '— الوصلُ ناقص', str_repeat('═', 78));
exit($pass === count($CHECKS) ? 0 : 1);
