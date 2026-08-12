<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * H-08-① — اختبار قبول سجل العقود الموحّد: رأسُ العقد + ترحيلُ الثلاثة قراءةً
 * (CON-01 §2/§3.1/§4/§7.1-7.2 · PLAN-01 §5.2-①)
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/employee_contract_registry_test.php
 *
 * ما يُثبته:
 *   ① البنية: employee_contracts بحالاتها الـ18 ومفاتيحها (UQ الشخص×الكيان×البداية ·
 *      UQ المصدر · فهرس التنبيه) وFKاتها + كتالوج pay_models الخمسة عشر نصًّا.
 *   ② الترحيلُ الصادق: worker_contract مرآةً (حالةً وفئةً ونموذجًا) · drivercontracts
 *      بعدده (صفرٌ مقيس) · سياساتُ المشغّلين رأسًا لكل (شركة×مشغّل) والمعتمدُ وحدَه
 *      active والمتعددُ الأسس composite — وعطالةُ إعادة التشغيل.
 *   ③ آلةُ الحالات: قائمةُ السماح (422) · اعتمادُ المنشئ (403) · القفلُ التفاؤلي (409) ·
 *      النهائية (423) · التعليقُ/الإعارةُ بسببٍ والعودةُ إلى حيث كان · بوابةُ القراءة.
 *   ④ الخدمة: نموذجٌ من خارج الخمسة عشرة (422) · تداخلُ المدة (409 بمرجعه) ·
 *      تعديلُ نافذٍ (423 «التغيير بملحق») · **المرحَّلُ قراءةً محصَّنٌ** (423 بمصدره).
 *   ⑤ العزل والشاشة: التسجيلُ في TenantRegistry · الموديول 151 للقوى (4) ·
 *      الصلاحيةُ الصارمة · scopedQuery · درسُ stateSave.
 *
 * البذرُ معزول: عقودُ اختبارٍ بوسم H08T_<pid> وتواريخَ 2031 تُكنس في النهاية.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/app/Core/TenantRegistry.php';
require_once dirname(__DIR__) . '/app/Core/TenantContext.php';
require_once dirname(__DIR__) . '/app/Core/TenantDb.php';
require_once dirname(__DIR__) . '/app/Services/Contract/EmployeeContractStateMachine.php';
require_once dirname(__DIR__) . '/app/Services/Contract/EmployeeContractService.php';

use App\Core\TenantDb;
use App\Core\TenantContext;
use App\Services\Contract\EmployeeContractStateMachine as ECSM;
use App\Services\Contract\EmployeeContractService as ECS;

while (ob_get_level() > 0) { ob_end_clean(); }

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$MARK = 'H08T_' . getmypid();
$CO = 4; $CREATOR = 999901; $APPROVER = 999902;

$teardown = function () use ($conn, $MARK) {
    $conn->query("DELETE FROM employee_contracts WHERE relation_type LIKE '{$MARK}%'");
};
register_shutdown_function($teardown);
$conn->query("DELETE FROM employee_contracts WHERE relation_type LIKE 'H08T_%'"); // بقايا جولاتٍ سابقة

fwrite(STDOUT, "\n══ H-08-① — سجلُّ العقود الموحّد: الرأسُ والترحيلُ قراءةً ══\n");

// ═══ ① البنية ═══
head('① البنية — الرأسُ بحالاته ومفاتيحه والكتالوجُ الخمسة عشر');
$r = $conn->query("SHOW TABLES LIKE 'employee_contracts'");
check($r && $r->num_rows === 1, 'جدول employee_contracts قائم');
$r = $conn->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS
                   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='employee_contracts' AND COLUMN_NAME='state'");
$enum = $r ? strval($r->fetch_assoc()['COLUMN_TYPE']) : '';
check(substr_count($enum, "','") === 17 && strpos($enum, "'draft'") !== false && strpos($enum, "'archived'") !== false,
      'state بحالات جدول §4 الثماني عشرة (لاتينيةً — گوتشا الترميز)');
$r = $conn->query("SELECT COUNT(*) c FROM information_schema.STATISTICS
                   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='employee_contracts' AND INDEX_NAME='uq_ec_person_company_start'");
check($r && intval($r->fetch_assoc()['c']) === 3, 'UQ (الشخص × الكيان × البداية) — CON-01 §7.1 نصًّا');
$r = $conn->query("SELECT COUNT(*) c FROM information_schema.STATISTICS
                   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='employee_contracts' AND INDEX_NAME='uq_ec_source'");
check($r && intval($r->fetch_assoc()['c']) === 3, 'UQ المصدر (الجدولُ × الصفُّ × الشركة) — عطالةُ الترحيل');
$r = $conn->query("SELECT COUNT(*) c FROM information_schema.STATISTICS
                   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='employee_contracts' AND INDEX_NAME='ix_ec_state_end'");
check($r && intval($r->fetch_assoc()['c']) === 2, 'فهرسُ التنبيه (state, end_date)');
$r = $conn->query("SELECT COUNT(*) c FROM information_schema.KEY_COLUMN_USAGE
                   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='employee_contracts'
                     AND REFERENCED_TABLE_NAME IN ('employees','project','pay_models')");
check($r && intval($r->fetch_assoc()['c']) === 3, 'FK → employees + project + pay_models');
$r = $conn->query("SELECT COUNT(*) c FROM pay_models WHERE is_active = 1");
check($r && intval($r->fetch_assoc()['c']) === 15, 'الكتالوجُ المحكوم: 15 نموذجًا (CON-01 §3.1)');
/* ◆ **الكتالوجُ هو الحيُّ منه.** السطرُ أعلاه يقيس المحكومَ بـ`is_active = 1`،
     وهذا كان يقرأ **كلَّ** صفوفِ الجدولِ — فأيُّ صفٍّ معطَّلٍ يكسره وإن كان
     الكتالوجُ سليمًا. وقد وقع: خمسةُ صفوفٍ `label_ar` فيها **أسماءُ أشخاصٍ**
     كتبها مستوردٌ (عينُ عطبِ `job_titles`) حُجزت معطَّلةً بوسمِ `quarantined_`
     (هجرة 2027_02_28) — فهي شاهدٌ على ما جرى لا عضوٌ في الكتالوج. */
$codes = array();
$r = $conn->query("SELECT code FROM pay_models WHERE is_active = 1 ORDER BY id");
while ($row = $r->fetch_assoc()) { $codes[] = $row['code']; }
$expected = array('fixed_only','fixed_allowances','fixed_incentive','incentive_only','hourly','daily',
                  'per_shift','per_trip','per_ton','per_meter','lump_sum','commission','performance_bonus','composite','other');
check($codes === $expected, 'الخمسةَ عشرَ بأكوادها الخمسة عشر نصًّا وترتيبًا');

// ═══ ② الترحيلُ الصادق ═══
head('② الترحيلُ قراءةً — مرآةُ المصادر الثلاثة بلا اختراع');
$wcSrc = intval($conn->query("SELECT COUNT(*) c FROM worker_contract wc JOIN employees e ON e.id=wc.employee_id
                              WHERE wc.company_id IS NOT NULL")->fetch_assoc()['c']);
$wcMig = intval($conn->query("SELECT COUNT(*) c FROM employee_contracts WHERE source_table='worker_contract'")->fetch_assoc()['c']);
check($wcSrc === $wcMig && $wcMig >= 1, "worker_contract: {$wcSrc} مصدرًا = {$wcMig} مرحَّلًا");
$dcSrc = intval($conn->query("SELECT COUNT(*) c FROM drivercontracts dc JOIN employees e ON e.id=dc.employee_id
                              WHERE dc.company_id IS NOT NULL")->fetch_assoc()['c']);
$dcMig = intval($conn->query("SELECT COUNT(*) c FROM employee_contracts WHERE source_table='drivercontracts'")->fetch_assoc()['c']);
check($dcSrc === $dcMig, "drivercontracts: {$dcSrc} مصدرًا = {$dcMig} مرحَّلًا (صفرٌ مقيسٌ يومَ البناء — شبهُ ميتة)");
$opSrc = intval($conn->query("SELECT COUNT(*) c FROM (SELECT DISTINCT chp.company_id, chp.operator_id
                                FROM contract_hour_policies chp JOIN employees e ON e.id=chp.operator_id
                               WHERE chp.party_scope='operator' AND chp.is_deleted=0 AND chp.operator_id IS NOT NULL) g")->fetch_assoc()['c']);
$opMig = intval($conn->query("SELECT COUNT(*) c FROM employee_contracts WHERE source_table='fin_operator_policies'")->fetch_assoc()['c']);
check($opSrc === $opMig && $opMig >= 1, "سياساتُ المشغّلين: {$opSrc} مجموعةً (شركة×مشغّل) = {$opMig} رأسًا");

// مرآةُ الحالة والفئة والنموذج — صفرُ انحرافٍ عن خريطة الترحيل المعلنة
$r = $conn->query("SELECT COUNT(*) c FROM employee_contracts ec JOIN worker_contract wc ON wc.id=ec.source_id
                   WHERE ec.source_table='worker_contract' AND (
                     ec.state <> CASE wc.state WHEN 'نافذ' THEN 'active' WHEN 'منتهٍ' THEN 'expired' ELSE 'draft' END
                     OR ec.category <> CASE WHEN wc.contract_type IN ('مشروع','مؤقت','موسمي','تغطية مؤقتة','تجاري مؤقت')
                                            THEN 'project' ELSE 'permanent' END
                     OR COALESCE(ec.start_date,'') <> COALESCE(wc.date_start,'')
                     OR COALESCE(ec.end_date,'')   <> COALESCE(wc.date_end,''))");
check(intval($r->fetch_assoc()['c']) === 0, 'worker_contract مرآةٌ حرفية: الحالةُ والفئةُ والمدةُ بلا إعادة حكم');
$r = $conn->query("SELECT COUNT(*) c FROM employee_contracts ec
                   JOIN (SELECT chp.company_id, chp.operator_id,
                                MAX(CASE WHEN chp.approved_at IS NOT NULL THEN 1 ELSE 0 END) AS has_appr,
                                COUNT(DISTINCT chp.pay_basis) AS nb, MIN(chp.pay_basis) AS ob
                           FROM contract_hour_policies chp
                          WHERE chp.party_scope='operator' AND chp.is_deleted=0 AND chp.operator_id IS NOT NULL
                          GROUP BY chp.company_id, chp.operator_id) g
                     ON g.company_id=ec.company_id AND g.operator_id=ec.source_id
                   JOIN pay_models pm ON pm.id=ec.pay_model_id
                   WHERE ec.source_table='fin_operator_policies' AND (
                     ec.state <> CASE WHEN g.has_appr=1 THEN 'active' ELSE 'draft' END
                     OR ec.category <> 'operator'
                     OR pm.code <> CASE WHEN g.nb > 1 THEN 'composite'
                                        WHEN g.ob='ton' THEN 'per_ton' WHEN g.ob='meter' THEN 'per_meter'
                                        WHEN g.ob IN ('actual','standby') THEN 'hourly' ELSE 'other' END)");
check(intval($r->fetch_assoc()['c']) === 0, 'رؤوسُ المشغّلين: المعتمدُ وحدَه active · المتعددُ composite · الواحدُ بخريطته');

// عطالةُ إعادة الترحيل — الجملةُ نفسُها (③-أ) تعيد صفرًا لا ازدواجًا
$before = intval($conn->query("SELECT COUNT(*) c FROM employee_contracts")->fetch_assoc()['c']);
$conn->query("INSERT INTO employee_contracts
    (company_id, employee_id, category, relation_type, start_date, end_date,
     pay_model_id, currency, state, version, source_table, source_id, created_by, created_at)
SELECT wc.company_id, wc.employee_id,
       CASE WHEN wc.contract_type IN ('مشروع','مؤقت','موسمي','تغطية مؤقتة','تجاري مؤقت') THEN 'project' ELSE 'permanent' END,
       CONCAT('worker_contract:', wc.contract_type), wc.date_start, wc.date_end,
       (SELECT pm.id FROM pay_models pm WHERE pm.code = 'other'), NULL,
       CASE wc.state WHEN 'نافذ' THEN 'active' WHEN 'منتهٍ' THEN 'expired' ELSE 'draft' END,
       1, 'worker_contract', wc.id, wc.created_by, COALESCE(wc.created_at, NOW())
FROM worker_contract wc JOIN employees e ON e.id = wc.employee_id
WHERE wc.company_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM (SELECT * FROM employee_contracts) ec
                   WHERE ec.source_table = 'worker_contract' AND ec.source_id = wc.id)");
$after = intval($conn->query("SELECT COUNT(*) c FROM employee_contracts")->fetch_assoc()['c']);
check($before === $after, 'إعادةُ تشغيل الترحيل عاطلة — حارسُ المصدر يمنع الازدواج');

// ═══ ③+④ الآلةُ والخدمة ═══
head('③ آلةُ الحالات والخدمة — قائمةُ السماح والحراسُ الأربعة');
$gate = new TenantDb($conn, TenantContext::forSystem($CO, $CREATOR, '', true));
$gateApprover = new TenantDb($conn, TenantContext::forSystem($CO, $APPROVER, '', true));

// شخصٌ بلا عقودٍ قائمة — بذرٌ لا يشارك فيه غيرُنا (تواريخُ 2031)
$emp = $conn->query("SELECT e.id FROM employees e WHERE e.company_id = {$CO}
                      AND NOT EXISTS (SELECT 1 FROM employee_contracts ec WHERE ec.employee_id = e.id)
                      LIMIT 1")->fetch_assoc();
$EID = $emp ? intval($emp['id']) : 0;
check($EID > 0, "شخصُ اختبارٍ بلا عقود (#{$EID})");

$pmHourly = intval($conn->query("SELECT id FROM pay_models WHERE code='hourly'")->fetch_assoc()['id']);

// 422: نموذجٌ من خارج القائمة الخمس عشرة
$r = ECS::createHead($conn, $gate, $CO, array('employee_id' => $EID, 'category' => 'permanent',
    'pay_model_id' => 999999, 'relation_type' => $MARK), $CREATOR);
check(!$r['ok'] && $r['code'] === 422, 'نموذجٌ غيرُ مذكورٍ في القائمة → 422');
// 422: فئةٌ أجنبية
$r = ECS::createHead($conn, $gate, $CO, array('employee_id' => $EID, 'category' => 'freelancer',
    'pay_model_id' => $pmHourly, 'relation_type' => $MARK), $CREATOR);
check(!$r['ok'] && $r['code'] === 422, 'فئةٌ من خارج قائمة §2 → 422');
// الإنشاءُ السليم — مسودة
$r = ECS::createHead($conn, $gate, $CO, array('employee_id' => $EID, 'category' => 'operator',
    'pay_model_id' => $pmHourly, 'relation_type' => $MARK,
    'start_date' => '2031-01-01', 'end_date' => '2031-12-31', 'currency' => 'SDG'), $CREATOR);
check($r['ok'] && intval($r['id']) > 0, 'رأسُ عقدٍ جديدٌ مسودةً');
$CID = intval($r['id']);
$stateOf = function ($id) use ($conn) {
    $row = $conn->query("SELECT state, version FROM employee_contracts WHERE id=" . intval($id))->fetch_assoc();
    return $row ?: array('state' => '?', 'version' => 0);
};
check($stateOf($CID)['state'] === 'draft', 'الوليدُ draft — لا يولد عقدٌ نافذًا');
// 409: تداخلُ المدة للشخص نفسه
$r = ECS::createHead($conn, $gate, $CO, array('employee_id' => $EID, 'category' => 'operator',
    'pay_model_id' => $pmHourly, 'relation_type' => $MARK, 'start_date' => '2031-06-01'), $CREATOR);
check(!$r['ok'] && $r['code'] === 409 && strpos($r['reason'], '#' . $CID) !== false,
      'مدةٌ متداخلةٌ → 409 **بمرجع القائم**');
// تعديلُ المسودة مشروع
$r = ECS::updateHead($conn, $gate, $CO, $CID, array('probation_end' => '2031-03-01'), $CREATOR);
check($r['ok'], 'تعديلُ المسودة مشروع');

// قائمةُ السماح: قفزٌ إلى النفاذ مرفوض
$r = ECSM::transition($conn, $gate, $CO, $CID, 'active', '', $CREATOR);
check(!$r['ok'] && $r['code'] === 422, 'draft ← active قفزٌ مرفوض (قائمةُ سماح) → 422');
// الدورة: draft→completed→validated
$r = ECSM::transition($conn, $gate, $CO, $CID, 'completed', '', $CREATOR);
check($r['ok'] && $r['changed'], 'draft ← completed');
$r = ECSM::transition($conn, $gate, $CO, $CID, 'validated', '', $CREATOR);
check($r['ok'], 'completed ← validated (قفلُ نسخة المراجعة)');
// 403: لا اعتمادَ لمن أنشأ
$r = ECSM::transition($conn, $gate, $CO, $CID, 'approved', '', $CREATOR);
check(!$r['ok'] && $r['code'] === 403, 'اعتمادُ المنشئ → 403 (فصلُ الواجبات بنيوي)');
// 409: القفلُ التفاؤلي
$ver = intval($stateOf($CID)['version']);
$r = ECSM::transition($conn, $gateApprover, $CO, $CID, 'approved', '', $APPROVER, $ver + 5);
check(!$r['ok'] && $r['code'] === 409, 'نسخةٌ متغيرة → 409');
// الاعتمادُ بغير المنشئ
$r = ECSM::transition($conn, $gateApprover, $CO, $CID, 'approved', 'اعتماد', $APPROVER, $ver);
check($r['ok'], 'validated ← approved بغير منشئه');
$row = $conn->query("SELECT approved_by FROM employee_contracts WHERE id={$CID}")->fetch_assoc();
check(intval($row['approved_by']) === $APPROVER, 'ختمُ المعتمِد (approved_by)');
ECSM::transition($conn, $gate, $CO, $CID, 'accepted', '', $CREATOR);
// H-10: التوقيعُ يشترط النسخةَ الموقَّعة — تُرفع في accepted
$r = ECSM::transition($conn, $gate, $CO, $CID, 'signed', '', $CREATOR);
check(!$r['ok'] && $r['code'] === 422, 'H-10: التوقيعُ بلا نسخةٍ موقَّعة → 422');
ECS::attachSignedFile($conn, $gate, $CO, $CID, 'signed/' . $MARK . '.pdf', $CREATOR);
foreach (array('signed', 'active') as $to) {
    $r = ECSM::transition($conn, $gate, $CO, $CID, $to, '', $CREATOR);
    if (!$r['ok']) { bad("التقدمُ إلى {$to}: " . $r['reason']); }
}
check($stateOf($CID)['state'] === 'active', 'الدورةُ الكاملة بلغت النفاذ (active)');
check(ECSM::isReadable('active') && ECSM::isReadable('amended') && ECSM::isReadable('seconded')
      && !ECSM::isReadable('draft') && !ECSM::isReadable('suspended'),
      'بوابةُ القراءة: Active/Confirmed/Amended/Seconded وحدَها (CON-01 §4 قيود)');
// 423: لا تعديلَ مباشرًا على نافذ
$r = ECS::updateHead($conn, $gate, $CO, $CID, array('end_date' => '2032-06-30'), $CREATOR);
check(!$r['ok'] && $r['code'] === 423 && strpos($r['reason'], 'ملحق') !== false,
      'تعديلٌ مباشرٌ على نافذ → 423 «التغيير بملحق»');
// التعليق: سببٌ إلزامي
$r = ECSM::hold($conn, $gate, $CO, $CID, 'suspended', '', $CREATOR);
check(!$r['ok'] && $r['code'] === 422, 'تعليقٌ بلا سبب → 422');
$r = ECSM::hold($conn, $gate, $CO, $CID, 'suspended', 'قرارٌ موثَّق — إجازةٌ طويلة', $CREATOR);
check($r['ok'] && $stateOf($CID)['state'] === 'suspended', 'التعليقُ بسببٍ يمضي');
$r = ECSM::transition($conn, $gate, $CO, $CID, 'terminated', '', $CREATOR);
check(!$r['ok'] && $r['code'] === 422, 'لا انتقالَ من المعلَّق إلا بالاستئناف → 422');
$r = ECSM::resume($conn, $gate, $CO, $CID, 'عودة', $CREATOR);
check($r['ok'] && $r['to'] === 'active', 'الاستئنافُ يعود **إلى حيث كان** (active)');
// الإعارةُ وعودتُها
$r = ECSM::hold($conn, $gate, $CO, $CID, 'seconded', 'إعارةٌ لكيانٍ شقيقٍ بمدةٍ ونسب', $CREATOR);
check($r['ok'] && $stateOf($CID)['state'] === 'seconded', 'الإعارةُ ببيانها تمضي — وصاحبُ العمل ثابت');
$r = ECSM::resume($conn, $gate, $CO, $CID, 'انتهاء الإعارة', $CREATOR);
check($r['ok'] && $stateOf($CID)['state'] === 'active', 'عودةُ الإعارة إلى حيث كان');
// الخاتمة: confirmed → terminated → settled → closed → archived
foreach (array('confirmed', 'terminated', 'settled', 'closed', 'archived') as $to) {
    $r = ECSM::transition($conn, $gate, $CO, $CID, $to, 'خاتمة', $CREATOR);
    if (!$r['ok']) { bad("التقدمُ إلى {$to}: " . $r['reason']); }
}
check($stateOf($CID)['state'] === 'archived', 'السلسلةُ النهائية بلغت الأرشفة');
$r = ECSM::transition($conn, $gate, $CO, $CID, 'draft', '', $CREATOR);
check(!$r['ok'] && $r['code'] === 423, 'المؤرشفُ نهائيٌّ بلا رجوع → 423');

// المرحَّلُ قراءةً محصَّن — كاتبُه مصدرُه القديم
head('④ المرحَّلُ قراءةً — الكتابةُ في مصدره حتى إقفال القديم (N-04)');
$mig = $conn->query("SELECT id, state FROM employee_contracts WHERE source_table IS NOT NULL AND state='draft' LIMIT 1")->fetch_assoc();
if ($mig) {
    $mid = intval($mig['id']);
    $r = ECSM::transition($conn, $gate, $CO, $mid, 'completed', '', $CREATOR);
    check(!$r['ok'] && $r['code'] === 423 && strpos($r['reason'], 'مرحَّل') !== false,
          'انتقالُ حالةِ المرحَّل → 423 بذكر مصدره');
    $r = ECS::updateHead($conn, $gate, $CO, $mid, array('currency' => 'USD'), $CREATOR);
    check(!$r['ok'] && $r['code'] === 423, 'تعديلُ المرحَّل → 423');
} else { bad('لا صفَّ مرحَّلًا للفحص — الترحيلُ لم يجرِ؟'); }

// ═══ ⑤ العزل والشاشة ═══
head('⑤ العزلُ والشاشة — التسجيلُ والصلاحيةُ الصارمة');
$src = file_get_contents(dirname(__DIR__) . '/app/Core/TenantRegistry.php');
check(strpos($src, "'employee_contracts' => array('type' => self::T_TENANT, 'soft' => true)") !== false,
      'employee_contracts في السجل: T_TENANT بحذفٍ ناعم');
check(strpos($src, "'pay_models' => array('type' => self::T_GLOBAL, 'soft' => false)") !== false,
      'pay_models في السجل: T_GLOBAL (كتالوجٌ محكومٌ عام)');
$r = $conn->query("SELECT id, owner_role_id FROM modules WHERE code = 'Workforce/contract_registry.php'");
$m = $r->fetch_assoc();
check($m && intval($m['id']) === 151 && intval($m['owner_role_id']) === 4, 'الموديول 151 مملوكٌ للقوى (4)');
$r = $conn->query("SELECT COUNT(*) c FROM role_permissions WHERE module_id = 151 AND role_id = 4 AND can_view=1 AND can_add=1 AND can_edit=1 AND can_delete=0");
check(intval($r->fetch_assoc()['c']) === 1, 'منحُ المالك: عرضٌ وإضافةٌ وتعديلٌ — ولا حذف');
$r = $conn->query("SELECT COUNT(*) c FROM nav_items WHERE route = 'Workforce/contract_registry.php' AND role_id = 4 AND active = 1");
check(intval($r->fetch_assoc()['c']) === 1, 'رابطُ التنقل للدور 4 (باب السجلات — بجوار كاتبه القديم)');
$scr = file_get_contents(dirname(__DIR__) . '/Workforce/contract_registry.php');
check(strpos($scr, "m.code = ?") !== false && strpos($scr, 'الصلاحيةُ صارمة') !== false,
      'الشاشةُ بصلاحيةٍ صارمة (الكودُ الحرفي وغيابُه منع)');
check(strpos($scr, 'scopedQuery') !== false && strpos($scr, '{TENANT_SCOPE}') !== false,
      'القراءةُ عبر scopedQuery بنطاق المستأجر');
check(strpos($scr, 'no-datatable') !== false && strpos($scr, 'stateSave: false') !== false,
      'درسُ الفلاتر الخارجية: no-datatable + stateSave:false');
check(strpos($scr, 'allowedFrom') !== false && strpos($scr, 'مرحَّل قراءةً') !== false,
      'الأزرارُ من قائمة السماح — والمرحَّلُ بشارته بلا أزرار');
check(strpos($scr, 'createHead') !== false && strpos($scr, 'ems_audit_change') === false,
      'الكتابةُ عبر الخدمة حصرًا (التدقيقُ في نقطة الخنق لا الشاشة)');

// ═══ النتيجة ═══
fwrite(STDOUT, "\n══ النتيجة: {$PASS} نجاح · {$FAIL} فشل ══\n");
exit($FAIL > 0 ? 1 : 0);
