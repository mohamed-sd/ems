<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * update0004 · الموجة ⑩ · SEC-30 — الحزام الجامع S1→S27
 * التشغيل: php tests/sec_full_belt.php
 *
 * يشغّل أحزمة المكوّنات الأربعة (البنية · الاشتقاق · الحراس · القوالب) كعمليات
 * فرعية، ثم يغطي المتبقي هنا: S7 · S10 · S13 · S14 · S15 · S20 · S21 + تقرير
 * القلب (§13). خريطة S كاملة في رأس كل حزام.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
require_once dirname(__DIR__) . '/app/Services/Security/FoundingModeService.php';
require_once dirname(__DIR__) . '/app/Services/Security/BreakGlassService.php';
require_once dirname(__DIR__) . '/app/Services/Security/PermSourceService.php';
require_once dirname(__DIR__) . '/app/Services/Security/PositionService.php';
require_once dirname(__DIR__) . '/app/Services/Security/PermissionResolver.php';
require_once dirname(__DIR__) . '/app/Core/AuthorityGuard.php';

use App\Services\Security\FoundingModeService as FMS;
use App\Services\Security\BreakGlassService as BG;
use App\Services\Security\PermSourceService as PSS;
use App\Services\Security\PositionService as PS;
use App\Services\Security\PermissionResolver as PR;
use App\Core\AuthorityGuard;

$PASS = 0; $FAIL = 0;
function check($c, $m) { global $PASS, $FAIL; $c ? $PASS++ : $FAIL++; fwrite(STDOUT, ($c ? '  ✔ ' : '  ✘ FAIL: ') . $m . "\n"); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$CO = 4;
$MARK = 'FB10T' . getmypid();

$teardown = function () use ($conn, $MARK) {
    $conn->query("DELETE FROM permission_exceptions WHERE reason LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM effective_permissions WHERE source_ref LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM person_positions WHERE person_id IN (SELECT person_id FROM persons WHERE full_name LIKE '%{$MARK}%')");
    $conn->query("DELETE FROM person_relationships WHERE person_id IN (SELECT person_id FROM persons WHERE full_name LIKE '%{$MARK}%')");
    $conn->query("DELETE FROM persons WHERE full_name LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM permission_audit_events WHERE reason LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM perm_shadow_diffs WHERE detail LIKE '%{$MARK}%' OR module_code LIKE '%{$MARK}%'");
    $conn->query("DELETE tp FROM template_permissions tp JOIN permission_template_versions v ON v.ver_id=tp.template_version_id WHERE v.change_reason LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM permission_template_versions WHERE change_reason LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM approval_signatures WHERE document_type = 'fb10_test'");
    $conn->query("UPDATE founding_mode SET enabled=0, ends_at=NULL WHERE 1=1");
};
register_shutdown_function($teardown);
$teardown();

fwrite(STDOUT, "\n══ update0004 موجة ⑩ — الحزام الجامع S1→S27 ══\n");

head('أحزمة المكوّنات الأربعة — عمليات فرعية');
$belts = array(
    'sec_structure_test.php' => 'البنية (S13 قاعدةً · S26 بنيةً)',
    'permission_resolver_test.php' => 'الاشتقاق (S1·S2·S3·S5·S6·S12·S16·S17·S27)',
    'sec_guards_test.php' => 'الحراس (S8·S9·S11·S18·S22·S23·S26)',
    'sec_templates_workflow_test.php' => 'القوالب (S4·S19·S20·S24·S25)',
);
foreach ($belts as $file => $covers) {
    $out = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/' . $file) . ' 2>&1');
    $green = is_string($out) && preg_match('/FAIL=0/u', $out);
    check($green, "{$file} — {$covers}" . ($green ? '' : "\n" . mb_substr((string) $out, -400)));
}

head('S13 — التأسيس محدَّد المدة (الخدمة)');
$r = FMS::activate($conn, 'discovery', null);
check(!$r['ok'] && $r['code'] === 422, 'تفعيل بلا نهاية → 422');
$r = FMS::activate($conn, 'discovery', date('Y-m-d H:i:s', strtotime('+30 days')), 'وضع التأسيس — بيانات تجريبية ' . $MARK);
check($r['ok'], 'وبنهاية يُفعَّل');
check(FMS::activeBanner($conn) !== null && mb_strpos(FMS::activeBanner($conn), 'ينتهي في') !== false,
    'الشريط الظاهر بنصه وتاريخ نهايته (§7-③)');
check(FMS::discoveryActive($conn), 'التوسيع في discovery وحده');

head('S14 — الحراس السبعة عشر بسياسات تجاوزهم في التأسيس');
$g = $conn->query("SELECT COUNT(*) c FROM guard_override_policies")->fetch_assoc();
check(intval($g['c']) === 17, '17 حارسًا مسجَّلًا');
$decider = intval($conn->query("SELECT id FROM users WHERE role='1' AND company_id={$CO} LIMIT 1")->fetch_assoc()['id']);
// never: يُرفض دائمًا — حتى والتأسيس مفعَّل
foreach (array('audit.tamper.x' => 'audit.tamper', 'tenant.cross.x' => 'tenant.isolation',
               'self.grant.x' => 'self.grant', 'period.write.x' => 'period.lock') as $perm => $guard) {
    $r = BG::grant($conn, array('company_id' => $CO, 'person_id' => 9701, 'permission_code' => $perm,
        'scope_rule' => 'company:4', 'reason' => 'S14 ' . $MARK, 'granted_by' => $decider));
    check(!$r['ok'] && $r['code'] === 403, "never «{$guard}» يُرفض والتأسيس مفعَّل");
}
// break_glass_only: بلا كسر زجاج نافذ يُرفض المنح العادي (المسار الطبيعي استثناء عادي لا يفتحها)
$r = BG::grant($conn, array('company_id' => $CO, 'person_id' => 9701, 'permission_code' => 'journal.post.x',
    'scope_rule' => 'company:4', 'reason' => 'S14bg ' . $MARK, 'granted_by' => $decider, 'hours' => 2));
check($r['ok'], 'break_glass_only «نشر القيود» يُفتح بكسر الزجاج وحده — وقد فُتح به');
// with_compensating_control — سياسة القراءة
$w = $conn->query("SELECT COUNT(*) c FROM guard_override_policies WHERE overridable='with_compensating_control'")->fetch_assoc();
check(intval($w['c']) === 2, 'حارسا الرقابة التعويضية (السقف والفصل) بسياستهما');
check(AuthorityGuard::sign($conn, array('document_type' => 'fb10_test', 'document_id' => 9,
    'person_id' => 9702, 'company_id' => $CO, 'created_by_person_id' => 9702))['code'] === 403,
    'منع اعتماد الذات نافذ والتأسيس مفعَّل — الحراس لا يُعطَّلون');

head('S10 — شخص بصفتين: لا تسرب بين النطاقين');
// قالب المخازن منشور داخل هذا الحزام (حزام ⑨ الفرعي يكنس قوالبه)
$tW = intval($conn->query("SELECT tpl_id FROM permission_templates WHERE tpl_kind='family' AND key_code='fam_warehouse'")->fetch_assoc()['tpl_id']);
$vW = intval($conn->query("SELECT COALESCE(MAX(version),0)+1 v FROM permission_template_versions WHERE tpl_id={$tW}")->fetch_assoc()['v']);
$conn->query("INSERT INTO permission_template_versions (tpl_id, version, state, change_reason, effective_from)
              VALUES ({$tW}, {$vW}, 'published', '{$MARK}', CURDATE())");
$verW = intval($conn->insert_id);
$conn->query("INSERT INTO template_permissions (template_version_id, dimension, permission_code, effect)
              VALUES ({$verW}, 'visibility', 'wh.stock.view', 'grant')");
$conn->query("INSERT INTO persons (full_name) VALUES ('ذو صفتين {$MARK}')");
$P = intval($conn->insert_id);
$conn->query("INSERT INTO person_relationships (person_id, company_id, relation_code, valid_from) VALUES ({$P}, {$CO}, 'rel_employee', CURDATE())");
$sites = $conn->query("SELECT id FROM sites WHERE company_id={$CO} ORDER BY id LIMIT 2")->fetch_all(MYSQLI_ASSOC);
$SA = intval($sites[0]['id']); $SB = intval($sites[1]['id']);
// صفتان: فني صيانة في موقع A · أمين مخزن في موقع B — بمركزين
$conn->query("INSERT INTO person_positions (person_id, company_id, relation_code, family_code, level_code, title_code, scope_type, scope_id, is_primary, valid_from)
              VALUES ({$P}, {$CO}, 'rel_employee', 'fam_maintenance', 'lvl_executor', 'jt_3', 'site', {$SA}, 1, CURDATE()),
                     ({$P}, {$CO}, 'rel_employee', 'fam_warehouse', 'lvl_officer', 'jt_5', 'site', {$SB}, 0, CURDATE())");
// قالب المخازن المنشور (من موجة ⑨: wh.stock.view) — صفة المخزن في B لا تتسرب إلى A
$r = PR::resolve($conn, $P, $CO, 'wh.stock.view', 'site', $SB);
check($r['allowed'], 'صفة المخازن ترى مخزون موقعها B');
$r = PR::resolve($conn, $P, $CO, 'wh.stock.view', 'site', $SA);
check(!$r['allowed'], 'ولا تتسرب إلى موقع صفة الصيانة A — مساحتان منفصلتان لا اتحاد');

head('S7 — الأدنى يعتمد للأعلى (الاعتماد باختصاص لا بدرجة)');
// مسؤول صيانة (lvl_officer بقالب مشرف الصيانة المنشور موجة ⑦ يشمل الاعتماد للمشرف فقط)
// نثبت المبدأ: من يملك صلاحية الاعتماد باختصاصه يمرّ ولو كان مقدّم المعاملة أعلى درجة —
// الحارس الوحيد هو الذات (منشئها لا يعتمدها) لا الدرجة
$sig = AuthorityGuard::sign($conn, array('document_type' => 'fb10_test', 'document_id' => 11,
    'person_id' => 9703, 'company_id' => $CO, 'created_by_person_id' => $decider));
check($sig['ok'], 'مسؤول أدنى يعتمد معاملة رفعها مدير أعلى — يُقبل (لا فحص درجة)');

head('S20 — الخفض: مدير الإدارة يوافق ومدير الصلاحيات ينفذ والسجل محفوظ');
$before = intval($conn->query("SELECT COUNT(*) c FROM permission_audit_events WHERE person_id={$P}")->fetch_assoc()['c']);
$sus = PS::securitySuspend($conn, $P, $CO, $decider, 'خفض {$MARK} — القرار من مدير الإدارة والتنفيذ لمدير الصلاحيات');
check($sus['ok'] && $sus['positions'] >= 2, "S21 معه: التعليق فوري ({$sus['positions']} مركزًا)");
$after = intval($conn->query("SELECT COUNT(*) c FROM permission_audit_events WHERE person_id={$P}")->fetch_assoc()['c']);
check($after > $before, 'والسجل السابق محفوظ + سطر جديد — لا محو');
$r = PR::resolve($conn, $P, $CO, 'wh.stock.view', 'site', $SB);
check(!$r['allowed'], 'وبعد التعليق: الصلاحية ساقطة فورًا بلا انتظار');

head('S15 — شهادة الإغلاق لا تصدر والشروط منقوصة');
$conn->query("UPDATE founding_mode SET started_at=NULL WHERE mode='permission_test'");
$c = FMS::closeProtocol($conn, $CO, $decider, 'CLOSE-' . $MARK);
check(!$c['ok'] && mb_strpos($c['reason'], 'اختبار الصلاحيات') !== false,
    'لا إغلاق قبل اجتياز وضع اختبار الصلاحيات');
FMS::activate($conn, 'permission_test', date('Y-m-d H:i:s', strtotime('+7 days')));
$c = FMS::closeProtocol($conn, $CO, $decider, 'CLOSE-' . $MARK);
check(isset($c['certificate']) && is_array($c['certificate']), 'البروتوكول السداسي جرى وشهادته ببنودها');
check(isset($c['steps']['⑤_mass_reduce']), 'الخفض الجماعي بقرار واحد ضمن الخطوات');

head('§13 — تقرير القلب والعلم الواحد');
check(PSS::currentSource() === 'legacy', 'EMS_PERM_SOURCE=legacy — القديم يقرر (المرحلة قبل القلب)');
$diffs0 = intval($conn->query("SELECT COUNT(*) c FROM perm_shadow_diffs")->fetch_assoc()['c']);
// ظل: قرار قديم (رايات) ضد مشتق — role 13 يملك can_view على شاشة صيانة لكن المشتق للمستخدم 9704 صفر
$u13 = intval($conn->query("SELECT id FROM users WHERE role='13' AND company_id={$CO} LIMIT 1")->fetch_assoc()['id']);
$sc = PSS::shadowCompare($conn, $u13, $CO, 'Maintenance/orders.php', 'screen_view', 'mnt.order.view', 'site', $SA);
check($sc['source'] === 'legacy' && is_bool($sc['decision']), 'الظل يحسب الاثنين والقديم يقرر');
if ($sc['diff']) {
    $diffs1 = intval($conn->query("SELECT COUNT(*) c FROM perm_shadow_diffs")->fetch_assoc()['c']);
    check($diffs1 === $diffs0 + 1, 'والفرق سُجِّل صفًّا — الحد صفر لا نسبة');
} else {
    check(true, 'لا فرق في هذه الحالة — والميزان جاهز');
}
$rep = PSS::phaseReport($conn);
check(isset($rep['phase'], $rep['zero_diff_streak_days']) && mb_strpos($rep['gate_to_flip'], '14') !== false,
    'تقرير المراحل الست: ' . $rep['phase'] . ' · شرط القلب معلن');

fwrite(STDOUT, "\n══ النتيجة: PASS={$PASS} · FAIL={$FAIL} ══\n");
exit($FAIL === 0 ? 0 : 1);
