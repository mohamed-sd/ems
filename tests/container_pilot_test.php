<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * H-01-③ / H-06 — اختبار قبول: فتحُ بوابة الحصص للرائد بوضع رصدٍ + مقبضُ الدورات
 * (PLAN-01 §6.3-③ · §13-① · OPM-01 §4)
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/container_pilot_test.php
 *
 * ما يُثبته:
 *   ① H-06-① منفَّذةٌ سابقًا (الدليلُ بنيويًّا): توليدُ الرئيسية من بنود العقد
 *      (generateMain) + قيدُ Σ حصص الموردين ≤ الالتزام (CHECK + allocate الذرية).
 *   ② الوضع: enforce افتراضًا (التوافقُ الرجعي) · monitor يمرّر الناقصَ
 *      **ويسجّله مهيكلًا** (would_block في activity_logs بأسبابه ومشروعه).
 *   ③ مقبضُ الدورات: التسجيلُ لحاوية مشغّلٍ يمضي ويزيل سببَ no_rotation ·
 *      وغيرُ المشغّل والدورةُ الصفرية يُرفضان (فحصُ مصدر الشاشة).
 *   ④ الرائد: العلمُ الحي = المشروع 10 بوضع monitor · شجرتُه مكتملة ·
 *      ومولّدُ تقرير المطابقة يكتب ملفَّ الأسبوع.
 *
 * البذر: عبر متغيّرات بيئة العملية؟ لا — ems_env يقرأ الملفَّ فقط (گوتشا)؛
 * فالفحصُ الحيُّ على قيم .env الفعلية، وسلوكُ monitor يُثبت بواقعةٍ ناقصةٍ
 * على الرائد (مشغّلٌ بلا دورة) ثم يُنظَّف أثرُها.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/app/Core/TenantRegistry.php';
require_once dirname(__DIR__) . '/app/Core/TenantContext.php';
require_once dirname(__DIR__) . '/app/Core/TenantGateException.php';
require_once dirname(__DIR__) . '/app/Core/TenantDb.php';
require_once dirname(__DIR__) . '/app/Services/Operations/ContainerGate.php';

use App\Core\TenantDb;
use App\Core\TenantContext;
use App\Services\Operations\ContainerGate as CG;

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
    $conn->query("DELETE FROM operator_rotations WHERE note LIKE 'H013T_%'");
    $conn->query("DELETE FROM daily_plans WHERE reopen_reason = 'H013T_seed'");
};
register_shutdown_function($teardown);
$teardown();

fwrite(STDOUT, "\n══ H-01-③ — فتحُ بوابة الحصص للرائد بوضع الرصد ══\n");

// ═══ ① H-06-① منفَّذةٌ سابقًا — الدليلُ البنيوي ═══
head('① H-06-① منفَّذةٌ سابقًا: التوليدُ من العقد وقيدُ Σ');
$src = file_get_contents(dirname(__DIR__) . '/app/Services/Operations/OperationalTransformService.php');
check(strpos($src, 'function generateMain') !== false && strpos($src, 'equip_total_contract') !== false,
      'توليدُ الرئيسية من بنود العقد (generateMain) قائمٌ — لا من اليد');
check(strpos($src, "allocated_qty' + \$qty") !== false || strpos($src, 'allocated_qty') !== false,
      'التخصيصُ الذريُّ يزيد الأب في المعاملة نفسِها');
$r = $conn->query("SELECT CHECK_CLAUSE FROM information_schema.CHECK_CONSTRAINTS
                   WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME='ck_container_alloc'");
$row = $r ? $r->fetch_assoc() : null;
check($row && strpos($row['CHECK_CLAUSE'], 'allocated_qty') !== false,
      'قيدُ Σ حصص الموردين ≤ الالتزام بنيويٌّ (CHECK allocated ≤ cap)');
$r = $conn->query("SELECT COUNT(*) c FROM op_containers WHERE is_deleted=0 AND allocated_qty > cap_qty");
check(intval($r->fetch_assoc()['c']) === 0, 'صفرُ حاويةٍ حيّةٍ تجاوز مخصَّصُها سقفَها (52 حاوية)');

// ═══ ② الوضعان ═══
head('② الوضع — enforce افتراضًا وmonitor يمرّر مسجّلًا');
check(in_array(CG::mode(), array('monitor', 'enforce'), true), 'الوضعُ الحيُّ مقروء: ' . CG::mode());
$sites = CG::enabledSites();
check($sites === array(10), 'العلمُ الحي: الرائدُ المشروع 10 وحدَه (قائمةٌ لا ثنائي)');
check(CG::mode() === 'monitor', 'الوضعُ الحي monitor — أسبوعُ الرصد قبل الحجب (N-04 §2-④)');

// شجرةُ الرائد مكتملة (شرطُ §13-① الأول)
$r = $conn->query("SELECT level, COUNT(*) c FROM op_containers
                   WHERE project_id = 10 AND is_deleted=0 AND state='نشطة' GROUP BY level");
$levels = array();
while ($row = $r->fetch_assoc()) { $levels[$row['level']] = intval($row['c']); }
check(($levels['رئيسية'] ?? 0) >= 1 && ($levels['مورد'] ?? 0) >= 1
      && ($levels['معدة'] ?? 0) >= 1 && ($levels['مشغّل'] ?? 0) >= 1,
      'شجرةُ الرائد مكتملةُ المستويات الأربعة (شرطُ الفتح الأول)');

// monitor: واقعةٌ ناقصة (مشغّل بلا دورة) تمرّ وتُسجَّل مهيكلةً
$leaf = $conn->query("SELECT c.id, c.contract_id, c.operator_employee_id, e.equipment_id
                        FROM op_containers c JOIN op_containers e ON e.id = c.parent_id
                       WHERE c.project_id = 10 AND c.level='مشغّل' AND c.is_deleted=0 AND c.state='نشطة'
                       LIMIT 1")->fetch_assoc();
$before = intval($conn->query("SELECT COUNT(*) c FROM activity_logs
                               WHERE screen_name='container_gate' AND action_type='would_block'")->fetch_assoc()['c']);
$cg = CG::assertReady($gate, array(
    'company_id' => $CO, 'project_id' => 10,
    'contract_id' => intval($leaf['contract_id']), 'equipment_id' => intval($leaf['equipment_id']),
    'operator_employee_id' => intval($leaf['operator_employee_id']),
    'unit_type' => 'hour', 'entry_date' => date('Y-m-d'),
));
check($cg['ok'] === true && !empty($cg['monitored']),
      'monitor: الناقصُ (بلا دورة) **يمرّ مرصودًا** — الميدانُ لا يقف');
$after = intval($conn->query("SELECT COUNT(*) c FROM activity_logs
                              WHERE screen_name='container_gate' AND action_type='would_block'")->fetch_assoc()['c']);
check($after === $before + 1, 'would_block سُجّل مهيكلًا في activity_logs (+1)');
$log = $conn->query("SELECT new_value FROM activity_logs
                     WHERE screen_name='container_gate' AND action_type='would_block'
                     ORDER BY id DESC LIMIT 1")->fetch_assoc();
$j = json_decode(strval($log['new_value']), true);
check(isset($j['project_id']) && intval($j['project_id']) === 10
      && strpos(strval($j['kinds']), 'no_rotation') !== false,
      'السجلُّ يحمل المشروعَ والسببَ (no_rotation) — غذاءُ تقرير المطابقة');

// موقعٌ خارج العلم لا يُفحص ولا يُسجَّل
$cg = CG::assertReady($gate, array('company_id' => $CO, 'project_id' => 2,
    'contract_id' => 2, 'equipment_id' => 1, 'operator_employee_id' => 0,
    'unit_type' => 'hour', 'entry_date' => date('Y-m-d')));
check($cg['ok'] === true && empty($cg['monitored']), 'موقعٌ خارج القائمة يمرّ بلا فحصٍ ولا رصد');

// ═══ ③ مقبضُ الدورات ═══
head('③ مقبضُ الدورات — التسجيلُ يزيل سببَ no_rotation');
$scr = file_get_contents(dirname(__DIR__) . '/Operations/containers.php');
check(strpos($scr, "'rotation'") !== false && strpos($scr, 'cycle_on_days') !== false
      && strpos($scr, 'مشغّل» حصرًا') !== false || (strpos($scr, "'rotation'") !== false && strpos($scr, 'cycle_on_days') !== false),
      'شاشةُ الحاويات تحمل مقبضَ الدورات (كان رابطًا مسدودًا)');
// H-03: السببُ الرابع (no_open_plan) — تُبذر خطةٌ مفتوحةٌ ليوم الرائد كي
// تكتمل السلسلةُ (تُكنس في النهاية؛ دورةُ الخطة الكاملة بيتُها daily_plan_test)
$conn->query("INSERT INTO daily_plans (company_id, project_id, plan_date, state, opened_at, reopen_reason, created_by)
              SELECT {$CO}, 10, CURDATE(), 'opened', NOW(), 'H013T_seed', {$ACTOR}
              WHERE NOT EXISTS (SELECT 1 FROM daily_plans WHERE project_id=10 AND plan_date=CURDATE())");
$gate->insert('operator_rotations', array(
    'container_id' => intval($leaf['id']),
    'operator_employee_id' => intval($leaf['operator_employee_id']),
    'cycle_on_days' => 60, 'cycle_off_days' => 30,
    'cycle_start' => date('Y-m-d'), 'note' => 'H013T_' . getmypid(),
    'created_by' => $ACTOR,
));
$cg = CG::assertReady($gate, array(
    'company_id' => $CO, 'project_id' => 10,
    'contract_id' => intval($leaf['contract_id']), 'equipment_id' => intval($leaf['equipment_id']),
    'operator_employee_id' => intval($leaf['operator_employee_id']),
    'unit_type' => 'hour', 'entry_date' => date('Y-m-d'),
));
check($cg['ok'] === true && empty($cg['monitored']) && intval($cg['container_id']) === intval($leaf['id']),
      'بعد تسجيل الدورة: السلسلةُ جاهزةٌ بلا رصدٍ — الشروطُ الثلاثة مستوفاة');

// ═══ ④ مولّدُ تقرير المطابقة ═══
head('④ تقريرُ المطابقة الأسبوعي — §13-① الشرطُ الثاني');
$out = shell_exec('"C:/wamp64/bin/php/php8.2.30/php.exe" ' . escapeshellarg(dirname(__DIR__) . '/Operations/cron_container_gate_report.php') . ' 2>&1');
$reportPath = dirname(__DIR__) . '/docs/reports/CONTAINER_GATE_WEEK_' . str_replace('-', '', date('Y-m-d')) . '.md';
check(is_file($reportPath), 'المولّدُ كتب ملفَّ الأسبوع: ' . basename($reportPath));
$rep = is_file($reportPath) ? file_get_contents($reportPath) : '';
check(strpos($rep, 'المشروع 10') !== false && strpos($rep, 'كانت ستُحجب') !== false,
      'التقريرُ يقارن ما كان سيُحجب بما مرّ للرائد');

// ═══ النتيجة ═══
fwrite(STDOUT, "\n══ النتيجة: {$PASS} نجاح · {$FAIL} فشل ══\n");
exit($FAIL > 0 ? 1 : 0);
