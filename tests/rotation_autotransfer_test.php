<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * N-05 — اختبار قبول: انتقالُ حصة المشغّل لبديله آليًّا في التناوب (OPM-01 §5.2-③)
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/rotation_autotransfer_test.php
 *
 * ما يُثبته:
 *   ① العلمُ قائمةُ نطاقٍ (فارغٌ = مطفأ · مشروعُ الاختبار وحدَه مفعَّل).
 *   ② يومُ راحة الأساسي: الحصةُ تنتقل آليًّا لبديله (swap ذريٌّ بسجل قرارٍ آلي).
 *   ③ العطالة: التشغيلُ الثاني في اليوم نفسِه صفرُ نقل.
 *   ④ **دورةُ العودة — لا تضاعف**: عودةُ الأساسي تعيد تفعيلَ حاويته المجمَّدة
 *      **بالعائد وحدَه** (إحكامُ H-04: التجميدُ cap=consumed).
 *   ⑤ الشراكةُ (نشطتان) تُتخطى معلَنةً · خارجُ العلم صفرُ أثر.
 *   ⑥ الكرونُ اليومي قائمٌ بنمطه (فحصُ مصدر).
 *
 * البذرُ معزول: مشروعٌ افتراضيٌّ 990041 وعقدٌ صوري H05T بشجرته — يُكنس كاملةً.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once __DIR__ . '/_guard_env.php';
ems_test_env_override(array('EMS_ROTATION_AUTOTRANSFER' => '990041'));

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
$CO = 4; $ACTOR = 999901; $PRJ = 990041;
$gate = new TenantDb($conn, TenantContext::forSystem($CO, $ACTOR, '', true));

$teardown = function () use ($conn, $PRJ) {
    $ids = array();
    $r = $conn->query("SELECT id FROM contracts WHERE first_party LIKE 'H05T%'");
    if ($r) { while ($row = $r->fetch_assoc()) { $ids[] = intval($row['id']); } }
    foreach ($ids as $cid) {
        $conn->query("DELETE sw FROM container_swaps sw JOIN op_containers c ON c.id = sw.container_id
                       WHERE c.contract_id = {$cid}");
        $conn->query("DELETE rr FROM operator_rotations rr JOIN op_containers c ON c.id = rr.container_id
                       WHERE c.contract_id = {$cid}");
        foreach (array('مشغّل', 'معدة', 'مورد', 'رئيسية') as $lvl) {
            $conn->query("DELETE FROM op_containers WHERE contract_id = {$cid} AND level = '{$lvl}'");
        }
        $conn->query("DELETE FROM contracts WHERE id = {$cid}");
    }
};
register_shutdown_function($teardown);
$teardown();

fwrite(STDOUT, "\n══ N-05 — انتقالُ الحصة آليًّا في مواعيد التناوب ══\n");

// ═══ بذر ═══
head('البذر — شجرةٌ على المشروع الافتراضي 990041: C أساسيٌّ بدورة 60/30 · A بديلٌ أول صفريةً');
$emps = array();
$er = $conn->query("SELECT id FROM employees WHERE company_id = {$CO} ORDER BY id LIMIT 2");
while ($row = $er->fetch_assoc()) { $emps[] = intval($row['id']); }
list($OP_A, $OP_C) = $emps;
$EQ = intval($conn->query("SELECT id FROM equipments WHERE company_id = {$CO} LIMIT 1")->fetch_assoc()['id']);
$conn->query("INSERT INTO contracts (company_id, contract_signing_date, first_party) VALUES ({$CO}, '2026-01-01', 'H05T')");
$CID = intval($conn->insert_id);
$conn->query("INSERT INTO op_containers (company_id, container_no, level, parent_id, contract_id, unit_type,
    cap_qty, allocated_qty, consumed_qty, project_id, valid_from, state, origin, created_by)
    VALUES ({$CO}, 'H05T-ROOT', 'رئيسية', NULL, {$CID}, 'hour', 1000, 300, 0, {$PRJ}, '2041-01-01', 'نشطة', 'عقد', {$ACTOR})");
$ROOT = intval($conn->insert_id);
$conn->query("INSERT INTO op_containers (company_id, container_no, level, parent_id, contract_id, unit_type,
    cap_qty, allocated_qty, consumed_qty, project_id, valid_from, state, origin, created_by)
    VALUES ({$CO}, 'H05T-SUP', 'مورد', {$ROOT}, {$CID}, 'hour', 300, 300, 0, {$PRJ}, '2041-01-01', 'نشطة', 'عقد', {$ACTOR})");
$SUPC = intval($conn->insert_id);
$conn->query("INSERT INTO op_containers (company_id, container_no, level, parent_id, contract_id, unit_type,
    cap_qty, allocated_qty, consumed_qty, equipment_id, role_kind, project_id, valid_from, state, origin, created_by)
    VALUES ({$CO}, 'H05T-EQ', 'معدة', {$SUPC}, {$CID}, 'hour', 300, 300, 0, {$EQ}, 'أساسية', {$PRJ}, '2041-01-01', 'نشطة', 'عقد', {$ACTOR})");
$EQC = intval($conn->insert_id);
$conn->query("INSERT INTO op_containers (company_id, container_no, level, parent_id, contract_id, unit_type,
    cap_qty, allocated_qty, consumed_qty, operator_employee_id, role_kind, shift_no, project_id, valid_from, state, origin, created_by)
    VALUES ({$CO}, 'H05T-OPC', 'مشغّل', {$EQC}, {$CID}, 'hour', 300, 0, 120, {$OP_C}, 'أساسي', 1, {$PRJ}, '2041-01-01', 'نشطة', 'عقد', {$ACTOR})");
$OPCC = intval($conn->insert_id);
$conn->query("INSERT INTO op_containers (company_id, container_no, level, parent_id, contract_id, unit_type,
    cap_qty, allocated_qty, consumed_qty, operator_employee_id, role_kind, shift_no, project_id, valid_from, state, origin, created_by)
    VALUES ({$CO}, 'H05T-OPA', 'مشغّل', {$EQC}, {$CID}, 'hour', 0, 0, 0, {$OP_A}, 'بديل أول', 1, {$PRJ}, '2041-01-01', 'معلَّقة', 'عقد', {$ACTOR})");
$OPCA = intval($conn->insert_id);
$gate->insert('operator_rotations', array(
    'container_id' => $OPCC, 'operator_employee_id' => $OP_C,
    'cycle_on_days' => 60, 'cycle_off_days' => 30, 'cycle_start' => '2041-01-01',
    'note' => 'H05T', 'created_by' => $ACTOR,
));
check($ROOT && $EQC && $OPCC && $OPCA, "الشجرةُ قائمة (eq {$EQC} · C نشطة 300/120 · A صفريةٌ معلَّقة)");

// ═══ ① العلم ═══
head('① العلمُ قائمةُ نطاق');
check(RSS::autoTransferEnabledFor($PRJ) === true && RSS::autoTransferEnabledFor(10) === false,
      'مشروعُ الاختبار وحدَه مفعَّل — قائمةٌ لا ثنائي');
$r = RSS::autoTransferForDate($conn, $gate, $CO, 10, '2041-03-05', $ACTOR);
check($r['transferred'] === 0 && !empty($r['skipped']), 'خارجُ العلم → صفرُ أثرٍ معلَن');

// ═══ ② يومُ الراحة — النقلُ الآلي ═══
head('② يومُ راحة الأساسي (2041-03-05 · اليوم 64) — الحصةُ تنتقل لبديله');
$r = RSS::autoTransferForDate($conn, $gate, $CO, $PRJ, '2041-03-05', $ACTOR);
check($r['transferred'] === 1, 'نُقلت حصةٌ واحدةٌ آليًّا');
$c = $conn->query("SELECT state, cap_qty, consumed_qty FROM op_containers WHERE id = {$OPCC}")->fetch_assoc();
check($c['state'] === 'معلَّقة' && floatval($c['cap_qty']) === 120.0,
      'حاويةُ C جُمّدت وقُفلت على مستهلَكها (cap=consumed=120)');
$a = $conn->query("SELECT state, cap_qty, consumed_qty FROM op_containers WHERE id = {$OPCA}")->fetch_assoc();
check($a['state'] === 'نشطة' && floatval($a['cap_qty']) === 180.0,
      'حاويةُ A فُعّلت بالمتبقي (180)');
$sw = $conn->query("SELECT reason, moved_qty FROM container_swaps WHERE container_id = {$OPCC} ORDER BY id DESC LIMIT 1")->fetch_assoc();
check($sw && strpos($sw['reason'], 'تناوبٌ مجدول') !== false && floatval($sw['moved_qty']) === 180.0,
      'سجلُّ القرار الآلي: «تناوبٌ مجدول» بكميته');

// ═══ ③ العطالة ═══
head('③ العطالة');
$r = RSS::autoTransferForDate($conn, $gate, $CO, $PRJ, '2041-03-05', $ACTOR);
check($r['transferred'] === 0, 'التشغيلُ الثاني في اليوم نفسِه: الحائزُ هو المناوبُ — صفرُ نقل');

// ═══ ④ دورةُ العودة — لا تضاعف ═══
head('④ عودةُ الأساسي (2041-04-01 · اليوم 91 = عملُ دورته الثانية) — العائدُ وحدَه');
$conn->query("UPDATE op_containers SET consumed_qty = 50 WHERE id = {$OPCA}");   // A استهلك 50 من 180
$r = RSS::autoTransferForDate($conn, $gate, $CO, $PRJ, '2041-04-01', $ACTOR);
check($r['transferred'] === 1, 'الحصةُ عادت للأساسي آليًّا');
$a = $conn->query("SELECT state, cap_qty, consumed_qty FROM op_containers WHERE id = {$OPCA}")->fetch_assoc();
check($a['state'] === 'معلَّقة' && floatval($a['cap_qty']) === 50.0,
      'حاويةُ A جُمّدت على مستهلَكها (50)');
$c = $conn->query("SELECT state, cap_qty, consumed_qty FROM op_containers WHERE id = {$OPCC}")->fetch_assoc();
check($c['state'] === 'نشطة' && floatval($c['cap_qty']) === 250.0 && floatval($c['consumed_qty']) === 120.0,
      '**لا تضاعف**: C أُعيد تفعيلُها بالعائد وحدَه (120 + 130 = 250 · متبقيها 130 = متبقي A)');
// Σ الحيُّ للأب ثابتٌ عبر الدورتين: 120 (مستهلك A المجمدة... ) → مجموعُ caps الحية والمجمدة = 50 + 250 = 300
$sum = $conn->query("SELECT SUM(cap_qty) s FROM op_containers WHERE parent_id = {$EQC} AND is_deleted = 0")->fetch_assoc();
check(floatval($sum['s']) === 300.0, 'Σ caps السلسلة كلِّها = 300 عبر الدورتين — لا فقدَ ولا تضاعف');

// ═══ ⑤ الشراكة ═══
head('⑤ الشراكةُ ليست تناوبًا');
$conn->query("UPDATE op_containers SET state = 'نشطة' WHERE id = {$OPCA}");   // نشطتان معًا
$r = RSS::autoTransferForDate($conn, $gate, $CO, $PRJ, '2041-04-02', $ACTOR);
$hasPartnerSkip = false;
foreach ($r['skipped'] as $s) { if (strpos($s, 'شراكة') !== false) { $hasPartnerSkip = true; } }
check($r['transferred'] === 0 && $hasPartnerSkip, 'نشطتان → تُتخطى **معلَنةً** (تُدار يدويًّا)');

// ═══ ⑥ الكرون ═══
head('⑥ الكرونُ اليومي');
$cron = file_get_contents(dirname(__DIR__) . '/Operations/cron_rotation_transfer.php');
check(strpos($cron, 'autoTransferForDate') !== false && strpos($cron, 'EMS_ROTATION_AUTOTRANSFER') !== false
      && strpos($cron, "PHP_SAPI !== 'cli'") !== false,
      'الكرونُ قائمٌ بنمطه (CLI حصرًا · العلمُ قائمة · بوابةُ النظام بشركة المشروع)');
$env = file_get_contents(dirname(__DIR__) . '/.env');
check(strpos($env, 'EMS_ROTATION_AUTOTRANSFER=') !== false,
      'العلمُ معلَنٌ في .env (فارغًا حتى استكمال دورات الرائد — مدوَّنٌ سببُه)');

// ═══ النتيجة ═══
fwrite(STDOUT, "\n══ النتيجة: {$PASS} نجاح · {$FAIL} فشل ══\n");
exit($FAIL > 0 ? 1 : 0);
