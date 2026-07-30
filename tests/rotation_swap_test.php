<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * H-04 — اختبار قبول: الاستبدالُ الذري وجدولُ المناوبة (OPM-01 §5.1/§5.2)
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/rotation_swap_test.php
 *
 * ما يُثبته:
 *   ① الهجرة: swaps يحمل الحركةَ (moved_qty · to_container_id بFK).
 *   ② الاستبدالُ الذري: الخارجةُ تُجمَّد **عند رصيدها** والبديلةُ تُفتح
 *      **بالمتبقي** والسجلُّ بحركته — **وΣ أبناءِ الأب الحيُّ ثابتٌ قبل=بعد**
 *      («حصةُ المورد لا تتغير» جبرًا) — والاحتياطيةُ القائمةُ **تُفعَّل** لا تُستنسخ.
 *   ③ الحراس: رصيدٌ صفري 422 · رئيسية 422 · بلا سببٍ 422 · الداخلُ=الخارج 422 ·
 *      غيرُ النشطة 423.
 *   ④ جدولُ المناوبة onDuty: نمطُ 60/30 بتواريخَ محسوبةٍ يدويًّا · بلا دورةٍ =
 *      مناوبٌ دائمًا · الترتيبُ بالدور (أساسيٌّ في راحته ⇒ بديلُه الأول).
 *   ⑤ الوصل: فعلُ «بدّل» صار ذريًّا واقتراحُ المناوب في خطة الغد (فحصُ مصدر).
 *
 * البذرُ معزول: عقدٌ صوري 2040 بشجرته تحت الوسم H04T يُكنس كاملةً.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/app/Core/TenantRegistry.php';
require_once dirname(__DIR__) . '/app/Core/TenantContext.php';
require_once dirname(__DIR__) . '/app/Core/TenantGateException.php';
require_once dirname(__DIR__) . '/app/Core/TenantDb.php';
require_once dirname(__DIR__) . '/app/Services/Operations/RotationSwapService.php';

use App\Core\TenantDb;
use App\Core\TenantContext;
use App\Services\Operations\RotationSwapService as RSS;

while (ob_get_level() > 0) { ob_end_clean(); }

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$CO = 4; $ACTOR = 999901;
$gate = new TenantDb($conn, TenantContext::forSystem($CO, $ACTOR, '', true));

$teardown = function () use ($conn) {
    $ids = array();
    $r = $conn->query("SELECT id FROM contracts WHERE first_party LIKE 'H04T%'");
    if ($r) { while ($row = $r->fetch_assoc()) { $ids[] = intval($row['id']); } }
    foreach ($ids as $cid) {
        $conn->query("DELETE sw FROM container_swaps sw JOIN op_containers c ON c.id = sw.container_id
                       WHERE c.contract_id = {$cid}");
        $conn->query("DELETE rr FROM operator_rotations rr JOIN op_containers c ON c.id = rr.container_id
                       WHERE c.contract_id = {$cid}");
        // كنسٌ من الورقة صعودًا (FK الأب)
        foreach (array('مشغّل', 'معدة', 'مورد', 'رئيسية') as $lvl) {
            $conn->query("DELETE FROM op_containers WHERE contract_id = {$cid} AND level = '{$lvl}'");
        }
        $conn->query("DELETE FROM contracts WHERE id = {$cid}");
    }
};
register_shutdown_function($teardown);
$teardown();

fwrite(STDOUT, "\n══ H-04 — الاستبدالُ الذري وجدولُ المناوبة ══\n");

// ═══ ① الهجرة ═══
head('① الهجرة — السجلُّ يحمل حركتَه');
$r = $conn->query("SELECT COUNT(*) c FROM information_schema.COLUMNS
                   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='container_swaps'
                     AND COLUMN_NAME IN ('moved_qty','to_container_id')");
check($r && intval($r->fetch_assoc()['c']) === 2, 'moved_qty + to_container_id قائمان');
$r = $conn->query("SELECT COUNT(*) c FROM information_schema.KEY_COLUMN_USAGE
                   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='container_swaps'
                     AND COLUMN_NAME='to_container_id' AND REFERENCED_TABLE_NAME='op_containers'");
check($r && intval($r->fetch_assoc()['c']) === 1, 'FK البديلة → op_containers');

// ═══ بذرُ شجرةٍ صورية ═══
head('البذر — شجرةٌ صورية: رئيسية 1000 ← مورد 300 ← معدة 300 ← مشغّل 300 (مستهلكٌ 120)');
$emps = array();
$er = $conn->query("SELECT id FROM employees WHERE company_id = {$CO} ORDER BY id LIMIT 3");
while ($row = $er->fetch_assoc()) { $emps[] = intval($row['id']); }
list($OP_A, $OP_B, $OP_C) = $emps;
$EQ = intval($conn->query("SELECT id FROM equipments WHERE company_id = {$CO} LIMIT 1")->fetch_assoc()['id']);
$SUP = intval($conn->query("SELECT id FROM suppliers WHERE company_id = {$CO} LIMIT 1")->fetch_assoc()['id'] ?? 0);
$conn->query("INSERT INTO contracts (company_id, contract_signing_date, first_party) VALUES ({$CO}, '2026-01-01', 'H04T')");
$CID = intval($conn->insert_id);
// إدراجٌ صريح (الأعمدةُ الحاملة تختلف بالمستوى)
$conn->query("INSERT INTO op_containers (company_id, container_no, level, parent_id, contract_id, unit_type,
    cap_qty, allocated_qty, consumed_qty, valid_from, state, origin, created_by)
    VALUES ({$CO}, 'H04T-ROOT', 'رئيسية', NULL, {$CID}, 'hour', 1000, 300, 0, '2040-01-01', 'نشطة', 'عقد', {$ACTOR})");
$ROOT = intval($conn->insert_id);
$conn->query("INSERT INTO op_containers (company_id, container_no, level, parent_id, contract_id, unit_type,
    cap_qty, allocated_qty, consumed_qty, supplier_id, valid_from, state, origin, created_by)
    VALUES ({$CO}, 'H04T-SUP', 'مورد', {$ROOT}, {$CID}, 'hour', 300, 300, 0, " . ($SUP ?: 'NULL') . ", '2040-01-01', 'نشطة', 'عقد', {$ACTOR})");
$SUPC = intval($conn->insert_id);
$conn->query("INSERT INTO op_containers (company_id, container_no, level, parent_id, contract_id, unit_type,
    cap_qty, allocated_qty, consumed_qty, equipment_id, role_kind, valid_from, state, origin, created_by)
    VALUES ({$CO}, 'H04T-EQ', 'معدة', {$SUPC}, {$CID}, 'hour', 300, 300, 0, {$EQ}, 'أساسية', '2040-01-01', 'نشطة', 'عقد', {$ACTOR})");
$EQC = intval($conn->insert_id);
$conn->query("INSERT INTO op_containers (company_id, container_no, level, parent_id, contract_id, unit_type,
    cap_qty, allocated_qty, consumed_qty, operator_employee_id, role_kind, shift_no, valid_from, state, origin, created_by)
    VALUES ({$CO}, 'H04T-OPA', 'مشغّل', {$EQC}, {$CID}, 'hour', 300, 0, 120, {$OP_A}, 'أساسي', 1, '2040-01-01', 'نشطة', 'عقد', {$ACTOR})");
$OPCA = intval($conn->insert_id);
// احتياطيةٌ قائمةٌ للبديل B «بحصةٍ صفريةٍ حتى تُفعَّل» — معلَّقةٌ بانتظار التفعيل
$conn->query("INSERT INTO op_containers (company_id, container_no, level, parent_id, contract_id, unit_type,
    cap_qty, allocated_qty, consumed_qty, operator_employee_id, role_kind, shift_no, valid_from, state, origin, created_by)
    VALUES ({$CO}, 'H04T-OPB', 'مشغّل', {$EQC}, {$CID}, 'hour', 0, 0, 0, {$OP_B}, 'بديل أول', 1, '2040-01-01', 'معلَّقة', 'عقد', {$ACTOR})");
$OPCB = intval($conn->insert_id);
check($ROOT && $SUPC && $EQC && $OPCA && $OPCB, "الشجرةُ قائمة (root {$ROOT} ← sup {$SUPC} ← eq {$EQC} ← A {$OPCA} · B صفرية {$OPCB})");

// Σ الحيُّ للأب قبل الاستبدال (المعلَّقةُ تحفظ consumed والنشطةُ متبقيها)
$sumBefore = 300.0; // cap A كاملة

// ═══ ② الاستبدالُ الذري ═══
head('② الاستبدالُ الذري — تجميدٌ عند الرصيد وفتحٌ بالمتبقي وΣ ثابت');
$r = RSS::swap($conn, $gate, $CO, $OPCA, $OP_B, 'مرضُ الأساسي — إحلالُ بديله الأول', 'DOC-H04T-1', $ACTOR, '2040-02-01');
check($r['ok'] && intval($r['to_container_id']) === $OPCB && floatval($r['moved_qty']) === 180.0,
      'النقلُ الذري: المتبقي 180 (300−120) إلى الاحتياطية القائمة — **فُعّلت لا استُنسخت**');
$a = $conn->query("SELECT state, cap_qty, consumed_qty, valid_to FROM op_containers WHERE id = {$OPCA}")->fetch_assoc();
check($a['state'] === 'معلَّقة' && floatval($a['cap_qty']) === 300.0 && floatval($a['consumed_qty']) === 120.0
      && $a['valid_to'] === '2040-02-01',
      'الخارجةُ مجمَّدةٌ **عند رصيدها** — تاريخُها محفوظٌ (120 مستهلكةً باقية)');
$b = $conn->query("SELECT state, cap_qty, consumed_qty FROM op_containers WHERE id = {$OPCB}")->fetch_assoc();
check($b['state'] === 'نشطة' && floatval($b['cap_qty']) === 180.0,
      'البديلةُ نشطةٌ **بالمتبقي** (0 + 180)');
// Σ الحيُّ للأب: consumed الخارجة + cap البديلة = 120 + 180 = 300 — والأبُ لم يُمسّ
$parent = $conn->query("SELECT cap_qty, allocated_qty FROM op_containers WHERE id = {$EQC}")->fetch_assoc();
$sumAfter = 120.0 + 180.0;
check($sumAfter === $sumBefore && floatval($parent['allocated_qty']) === 300.0,
      '«حصةُ المورد لا تتغير» جبرًا: Σ الحيُّ 300 قبل=بعد والأبُ لم يُمسّ');
$sw = $conn->query("SELECT swap_kind, out_ref, in_ref, moved_qty, to_container_id, doc_ref
                    FROM container_swaps WHERE container_id = {$OPCA} ORDER BY id DESC LIMIT 1")->fetch_assoc();
check($sw && $sw['swap_kind'] === 'مشغّل' && intval($sw['out_ref']) === $OP_A && intval($sw['in_ref']) === $OP_B
      && floatval($sw['moved_qty']) === 180.0 && intval($sw['to_container_id']) === $OPCB
      && $sw['doc_ref'] === 'DOC-H04T-1',
      'سجلُّ الاستبدال بحركته كاملةً (من · إلى · الكمية · البديلة · المستند)');

// استبدالٌ ثانٍ من البديلة إلى C (لا أختَ قائمةً → تُنشأ)
$r = RSS::swap($conn, $gate, $CO, $OPCB, $OP_C, 'غيابٌ طارئ — بديلٌ ثانٍ', '', $ACTOR, '2040-03-01');
check($r['ok'] && floatval($r['moved_qty']) === 180.0 && intval($r['to_container_id']) > 0
      && intval($r['to_container_id']) !== $OPCB,
      'بلا أختٍ قائمة → البديلةُ **تُنشأ** بالمتبقي (180)');
$OPCC = intval($r['to_container_id']);

// ═══ ③ الحراس ═══
head('③ الحراس');
$r = RSS::swap($conn, $gate, $CO, $OPCA, $OP_C, 'محاولة على مجمَّدة', '', $ACTOR);
check(!$r['ok'] && $r['code'] === 423, 'غيرُ النشطة (المجمَّدة) → 423');
$conn->query("UPDATE op_containers SET consumed_qty = 180 WHERE id = {$OPCC}");
$r = RSS::swap($conn, $gate, $CO, $OPCC, $OP_A, 'رصيدٌ صفري', '', $ACTOR);
check(!$r['ok'] && $r['code'] === 422 && strpos($r['reason'], 'صفر') !== false, 'رصيدٌ صفري → 422 «لا شيءَ يُنقل»');
$conn->query("UPDATE op_containers SET consumed_qty = 0 WHERE id = {$OPCC}");
$r = RSS::swap($conn, $gate, $CO, $ROOT, $OP_A, 'رئيسية', '', $ACTOR);
check(!$r['ok'] && $r['code'] === 422, 'رئيسيةٌ → 422 (الاستبدالُ لمعدة/مشغّل)');
$r = RSS::swap($conn, $gate, $CO, $OPCC, $OP_C, 'نفسه', '', $ACTOR);
check(!$r['ok'] && $r['code'] === 422, 'الداخلُ = الخارج → 422');
$r = RSS::swap($conn, $gate, $CO, $OPCC, $OP_A, '', '', $ACTOR);
check(!$r['ok'] && $r['code'] === 422, 'بلا سببٍ → 422 «بقرارٍ موثَّق»');

// ═══ ④ جدولُ المناوبة ═══
head('④ onDuty — «من يعمل في أي تاريخ» بحسابٍ يدوي');
// C (نشطة · بديل ثانٍ بالوراثة؟ role ورث «بديل أول») — نعيد الأدوار صراحةً للوضوح
$conn->query("UPDATE op_containers SET role_kind='أساسي', state='نشطة' WHERE id = {$OPCC}");
// گوتشا CHECK الصامتة: cap دون consumed (120) يُفشل التحديثَ بلا استثناء — فيبقى ≥ 300
$conn->query("UPDATE op_containers SET role_kind='بديل أول', state='نشطة', cap_qty=300 WHERE id = {$OPCA}");
// دورةُ C: 60 عمل / 30 راحة من 2040-01-01 — يدويًّا: 2040-02-15 اليوم 46 (عمل) · 2040-03-05 اليوم 65 (راحة)
$gate->insert('operator_rotations', array(
    'container_id' => $OPCC, 'operator_employee_id' => $OP_C,
    'cycle_on_days' => 60, 'cycle_off_days' => 30, 'cycle_start' => '2040-01-01',
    'note' => 'H04T', 'created_by' => $ACTOR,
));
$d = RSS::onDuty($gate, $EQC, '2040-02-15');
check($d['on_duty'] === $OP_C, '2040-02-15 (اليوم 46 من 60 عمل): الأساسيُّ C مناوب');
$d = RSS::onDuty($gate, $EQC, '2040-03-05');
check($d['on_duty'] === $OP_A && $d['on_duty'] !== $OP_C,
      '2040-03-05 (اليوم 65 — راحةُ C): بديلُه الأول A (بلا دورةٍ = مناوبٌ دائمًا) — الترتيبُ بالدور');
$chainC = null;
foreach ($d['chain'] as $lnk) { if ($lnk['operator_employee_id'] === $OP_C) { $chainC = $lnk; } }
check($chainC && $chainC['on_duty'] === false && strpos($chainC['why'], 'راحته') !== false,
      'القائمةُ تشرح: C في راحته بيومها المحسوب');
$d = RSS::onDuty($gate, $EQC, '2039-12-15');
check($d['on_duty'] === $OP_A, 'قبل بدء دورة C: لا يناوب (دورتُه لم تبدأ) — A يغطي');

// ═══ ⑤ الوصل ═══
head('⑤ الوصل — «بدّل» ذريٌّ واقتراحُ المناوب');
$scr = file_get_contents(dirname(__DIR__) . '/Operations/containers.php');
check(strpos($scr, 'RotationSwapService::swap') !== false && strpos($scr, "insert('container_swaps'") === false,
      'فعلُ «بدّل» صار نقلًا ذريًّا عبر الخدمة (لا إدراجَ خامًا)');
$dp = file_get_contents(dirname(__DIR__) . '/Operations/daily_plan.php');
check(strpos($dp, 'onDuty') !== false && strpos($dp, 'مناوبُ اليوم') !== false,
      'خطةُ الغد تقترح المناوبَ (اقتراحٌ لا فرض)');

// ═══ النتيجة ═══
fwrite(STDOUT, "\n══ النتيجة: {$PASS} نجاح · {$FAIL} فشل ══\n");
exit($FAIL > 0 ? 1 : 0);
