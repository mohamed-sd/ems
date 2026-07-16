<?php
/**
 * حزمة اختبار العقد المنصّي — T_PLATFORM وقناة كونسول المزوّد (دفعة هـ-0 · 2026-07-16)
 * ─────────────────────────────────────────────────────────────────────────────
 * تثبت الحدود الثلاثة للعقد:
 *   ① بوابة المستأجر ترفض جداول المزوّد كليًا (قراءةً وكتابةً وإعلانًا).
 *   ② البوابة العابرة (جلسة المدير الأعلى) تفتحها قراءةً وكتابةً — مقيَّدةً بالسجل.
 *   ③ المقيَّد T_RESTRICTED يبقى مرفوضًا حتى للعابرة (لا يفتح العقدُ المنصّي غيرَ بابه).
 * ذاتية التنظيف: كل صفوف الاختبار تُمسح، وعدّادا القبل/البعد يُتحقَّقان.
 */
error_reporting(E_ALL & ~E_DEPRECATED);
mysqli_report(MYSQLI_REPORT_OFF);

require_once dirname(__DIR__) . '/includes/env.php';
require_once dirname(__DIR__) . '/app/Core/TenantGateException.php';
require_once dirname(__DIR__) . '/app/Core/TenantRegistry.php';
require_once dirname(__DIR__) . '/app/Core/TenantContext.php';
require_once dirname(__DIR__) . '/app/Core/TenantDb.php';

use App\Core\TenantDb;
use App\Core\TenantContext;
use App\Core\TenantGateException;

$conn = new mysqli(ems_env('DB_HOST'), 'root', '', ems_env('DB_NAME'));
$conn->set_charset('utf8mb4');

$pass = 0;
$fail = 0;
function chk($name, $cond)
{
    global $pass, $fail;
    if ($cond) { $pass++; echo "  ✔ $name\n"; }
    else { $fail++; echo "  ✘ $name\n"; }
}
function denied(callable $fn)
{
    try { $fn(); return false; }
    catch (TenantGateException $e) { return true; }
}

echo "═══ العقد المنصّي: T_PLATFORM + قناة المزوّد ═══\n";

$MARK = 'PLATFORM_TEST_MARKER_p0';
$conn->query("DELETE FROM admin_audit_log WHERE action_type='$MARK'");
$conn->query("DELETE FROM admin_subscription_requests WHERE company_name='$MARK'");
$audit0 = intval($conn->query("SELECT COUNT(*) FROM admin_audit_log")->fetch_row()[0]);
$subs0  = intval($conn->query("SELECT COUNT(*) FROM admin_subscription_requests")->fetch_row()[0]);

$tenantGate   = new TenantDb($conn, TenantContext::forSystem(4, 999901, '1'), false, 'enforce');
$platformGate = new TenantDb($conn, TenantContext::forSystem(0, 999903, '-1'), true, 'enforce');

echo "── ① بوابة المستأجر ترفض طبقة المزوّد كليًا ──\n";
chk('p1: قراءة admin_companies من بوابة مستأجرٍ = رفض', denied(function () use ($tenantGate) {
    $tenantGate->select('admin_companies', array('columns' => array('id')));
}));
chk('p2: كتابة admin_companies من بوابة مستأجرٍ = رفض', denied(function () use ($tenantGate) {
    $tenantGate->insert('admin_companies', array('company_name' => 'x'));
}));
chk('p3: إعلان admin_companies في scopedQuery مستأجرٍ = رفض', denied(function () use ($tenantGate) {
    $tenantGate->scopedQuery(array('scope' => array('ac' => 'admin_companies')),
        "SELECT ac.id FROM admin_companies ac WHERE 1=1 AND {TENANT_SCOPE}");
}));
chk('p4: قراءة api_tokens من بوابة مستأجرٍ = رفض', denied(function () use ($tenantGate) {
    $tenantGate->select('api_tokens', array('columns' => array('id')));
}));

echo "── ② البوابة العابرة (المزوّد) تفتحها بحوكمة السجل ──\n";
$rows = $platformGate->select('admin_companies', array('columns' => array('id'), 'orderBy' => 'id'));
$ids = array_map(function ($r) { return intval($r['id']); }, $rows);
chk('p5: قراءة admin_companies عبر العابرة تعمل وترى شركة 4', in_array(4, $ids, true) && count($rows) >= 1);

$newAudit = intval($platformGate->insert('admin_audit_log', array(
    'admin_id' => 1, 'action_type' => $MARK, 'target_name' => 'platform test',
    'description' => 'سطر اختبارٍ ذاتي التنظيف', 'ip_address' => '127.0.0.1', 'user_agent' => 'tests',
)));
chk('p6: إدراج admin_audit_log عبر العابرة يعيد معرّفًا وليدًا', $newAudit > 0);

$newReq = intval($platformGate->insert('admin_subscription_requests', array(
    'company_name' => $MARK, 'email' => 'platform@test.local', 'status' => 'pending',
)));
$updated = intval($platformGate->update('admin_subscription_requests',
    array('status' => 'approved', 'review_note' => 'اختبار'), array('id' => $newReq)));
$reqRow = $platformGate->selectOne('admin_subscription_requests', array('where' => array('id' => $newReq)));
chk('p7: دورة طلب اشتراكٍ (إدراج → تحديث → قراءة) عبر العابرة', $newReq > 0 && $updated === 1
    && $reqRow !== null && $reqRow['status'] === 'approved');

chk('p8: scopedQuery منصّي عبر العابرة (عدّ الطلبات المعلقة)', (function () use ($platformGate) {
    $r = $platformGate->scopedQuery(array('scope' => array('asr' => 'admin_subscription_requests')),
        "SELECT COUNT(*) AS t FROM admin_subscription_requests asr WHERE 1=1 AND {TENANT_SCOPE}");
    return isset($r[0]['t']) && intval($r[0]['t']) >= 1;
})());

echo "── ③ حدود العقد لا تتوسع ──\n";
chk('p9: المقيَّد approval_requests يبقى مرفوضًا حتى للعابرة', denied(function () use ($platformGate) {
    $platformGate->select('approval_requests', array('columns' => array('id')));
}));
chk('p10: الحذف الصلب المنصّي مرفوض عبر deleteRow (عقد المستأجر)', denied(function () use ($platformGate, $newReq) {
    $platformGate->deleteRow('admin_subscription_requests', $newReq, 'platform test');
}));

echo "── ④ سياق جلسة المزوّد fail-closed ──\n";
unset($_SESSION['super_admin']);
chk('p11: fromSuperAdminSession بلا جلسة = رفضٌ مغلق', (function () {
    try { TenantContext::fromSuperAdminSession(); return false; }
    catch (TenantGateException $e) { return true; }
})());
$_SESSION['super_admin'] = array('id' => 7, 'name' => 'test');
$ctx = TenantContext::fromSuperAdminSession();
chk('p12: fromSuperAdminSession بجلسةٍ = شركة 0 ودور المدير الأعلى', $ctx->companyId() === 0
    && $ctx->isSuperAdmin() && $ctx->userId() === 7 && !$ctx->hasTenant());
unset($_SESSION['super_admin']);

echo "── teardown ──\n";
$conn->query("DELETE FROM admin_audit_log WHERE action_type='$MARK'");
$conn->query("DELETE FROM admin_subscription_requests WHERE company_name='$MARK'");
$audit1 = intval($conn->query("SELECT COUNT(*) FROM admin_audit_log")->fetch_row()[0]);
$subs1  = intval($conn->query("SELECT COUNT(*) FROM admin_subscription_requests")->fetch_row()[0]);
chk('teardown: عدّا الجدولين عادا لخط الأساس', $audit1 === $audit0 && $subs1 === $subs0);

echo "══════════════════════════════════════════════════\n";
echo "النتيجة: $pass ناجح · $fail فاشل\n";
exit($fail === 0 ? 0 : 1);
