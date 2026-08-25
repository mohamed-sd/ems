<?php
/**
 * Governance/gov_dept.php — حوكمةُ الإدارةِ لأيِّ إدارة (INJ-0171)
 * ─────────────────────────────────────────────────────────────────────────
 * مدخلٌ عامٌّ لمكوّنِ «حوكمة الإدارة» الواحد: يقرأ الإدارةَ من **دورِ الجلسة**
 * عبر `includes/dept_gov_registry.php` بدل غلافِ نطاقٍ لكلِّ إدارة.
 *
 * ◆ ولا يقبل نطاقًا من الرابط: `?dept=fin` من مستخدمِ الأسطولِ يعرض سجلاتِ
 *   المالية. النطاقُ من الدورِ حصرًا — و«صفرُ سجلٍّ من إدارةٍ أخرى» شرطُ قبولٍ
 *   منصوصٌ عليه، لا تحسينٌ اختياريّ.
 * ◆ والسوبر أدمن وحدَه يجوز له اختيارُ الإدارةِ صراحةً — فنطاقُه الكلُّ أصلًا.
 * ◆ ودورٌ بلا نطاقٍ مُعلَنٍ يُردُّ برسالةٍ تسمّي السبب (فشلٌ مغلق) ولا يرى
 *   شاشةً فارغةً يظنُّها عطلًا.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }
include '../config.php';
require_once __DIR__ . '/../includes/permissions_helper.php';
require_once __DIR__ . '/../includes/screen_contract.php';
require_once __DIR__ . '/../includes/dept_gov_registry.php';
require_once __DIR__ . '/../includes/ux_components.php'; // مكوّنُ الحالات — inheader يُحمَّل لاحقًا داخلَ المكوّن

$current_role   = strval($_SESSION['user']['role'] ?? '');
$is_super_admin = ($current_role === '-1');
$company_id     = intval($_SESSION['user']['company_id'] ?? 0);
if (!$is_super_admin && $company_id <= 0) { header('Location: ../login.php'); exit(); }

$__pp = check_page_permissions($conn, 'Governance/gov_dept.php');
if (!$is_super_admin && empty($__pp['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا تملك صلاحية عرض حوكمة الإدارة',
        'GOV-PERM-403', 'صلاحيات الحوكمة يمنحها مدير الصلاحيات');
}
$__ro = $__pp;
ems_shell_axes($__ro);

$REG = ems_dept_gov_registry();

/* النطاق: من الدورِ حصرًا — إلا السوبر أدمن فله الاختيار (نطاقُه الكلُّ أصلًا) */
$deptCode = ems_dept_gov_of_role($current_role);
if ($is_super_admin) {
    $asked = preg_replace('/[^a-z]/', '', (string) ($_GET['dept'] ?? ''));
    if ($asked !== '' && isset($REG[$asked])) { $deptCode = $asked; }
    if ($deptCode === null) { $deptCode = 'flt'; }
}
if ($deptCode === null || !isset($REG[$deptCode])) {
    ems_gov_flash_redirect('../main/dashboard.php',
        'لا نطاق حوكمة معلن لدورك — ولا يخمن لك نطاق إدارة أخرى',
        'GOV-SCOPE-404', 'يضاف نطاق الإدارة في includes/dept_gov_registry.php');
}

$GOV_DEPT = $REG[$deptCode];

// UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
// ويُخرجها includes/dept_gov_space.php بعدَ ترويسةِ الصفحةِ مباشرة
$GOV_DEPT_STATES = ems_states_bundle('لا سجلات حوكمة في نطاق هذه الإدارة', 'راجع حسابات الإدارة التابعة وصلاحياتها — والنطاق من دور الجلسة لا من الرابط');

/* فصلُ الواجباتِ يُقاس على المستنداتِ الحيةِ لا نصًّا — وكلُّ قياسٍ مقيَّدٌ
   بشركةِ الجلسة، فلا يتسرّب صفٌّ من شركةٍ أخرى فضلًا عن إدارةٍ أخرى. */
$GOV_DEPT['sod_queries'] = array();
if ($deptCode === 'flt') {
    $GOV_DEPT['sod_queries'] = array(
        array(
            'title' => 'تسجيل عطل المعدة وإغلاقه بيد واحدة',
            'sql' => "SELECT CONCAT('FLT-', f.id) doc, u.name person
                        FROM fleet_failures f LEFT JOIN users u ON u.id = f.closed_by
                       WHERE f.company_id = {$company_id} AND f.closed_by IS NOT NULL
                         AND f.closed_by = f.created_by LIMIT 20",
        ),
    );
} elseif ($deptCode === 'trp') {
    $GOV_DEPT['sod_queries'] = array(
        array(
            'title' => 'إنشاء أمر الترحيل واعتماده بيد واحدة',
            'sql' => "SELECT t.order_no doc, u.name person
                        FROM transfer_orders t LEFT JOIN users u ON u.id = t.created_by
                       WHERE t.company_id = {$company_id} AND t.stage <> 'request'
                         AND COALESCE(t.is_deleted,0) = 0 AND t.created_by IS NOT NULL LIMIT 20",
        ),
    );
}

require __DIR__ . '/../includes/dept_gov_space.php';
