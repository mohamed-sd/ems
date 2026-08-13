<?php
/**
 * Equipments/gov_dept_flt.php — حوكمة الأسطول
 * ─────────────────────────────────────────────────────────────────────────
 * ظهورٌ نطاقيٌّ لمكوّن «حوكمة الإدارة» الواحد على الأسطول:
 * حساباتُها وصلاحياتُ شاشاتِها وفصلُ واجباتِها وسجلاتُ تدقيقها
 * (INJAZ-CORE-01 §12-1 الباب ١١ — «مكوّنٌ واحدٌ لا نسخةٌ لكلٍّ»).
 * قراءةٌ لا كتابة، والفعلُ الكاتبُ الوحيدُ تصديقُ مراجعةِ الوصول — يشهد ولا
 * يمنح، ونطاقُه `gov_dept_flt` يُطابَق على سجلِّ الشاشاتِ في المعالج.
 *
 * ◆ عدّادُ الأحداثِ مقصورٌ على `source_module = 'fleet'` — فما ظهر صفرًا
 *   يعني «لا حدثَ منشورٌ باسمِ هذه الإدارة»، لا «لا أحداثَ في النظام».
 * ◆ ولا قياسَ فصلِ واجباتٍ معلَنًا بعدُ لهذه الإدارة — والمكوّنُ يُعلن ذلك
 *   صراحةً ولا يعرض «صفرَ خرق»، فبراءةٌ عمّا لم يُقَس أخطرُ من غيابِ اللوحة.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }
include '../config.php';
require_once __DIR__ . '/../includes/permissions_helper.php';
require_once __DIR__ . '/../includes/screen_contract.php';

$current_role   = strval($_SESSION['user']['role'] ?? '');
$is_super_admin = ($current_role === '-1');
$company_id     = intval($_SESSION['user']['company_id'] ?? 0);
if (!$is_super_admin && $company_id <= 0) { header('Location: ../login.php'); exit(); }

$__pp = check_page_permissions($conn, 'Equipments/gov_dept_flt.php');
if (!$is_super_admin && empty($__pp['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا تملك صلاحية عرض حوكمة الإدارة', 'GOV-PERM-403', 'صلاحيات الحوكمة يمنحها مدير الصلاحيات');
}
ems_shell_axes($__pp);

$GOV_DEPT = array(
    'title'           => 'حوكمة الأسطول',
    'icon'            => 'fa fa-scale-balanced',
    'module_like'     => array('Equipments/', 'Fleet/'),
    'team_roles'      => array(3, 10, 11),
    'sensitive_like'  => 'equipment%',
    'events_module'   => 'fleet',
    'attest_endpoint' => '../Governance/gov_m14_actions.php',
    'attest_code'     => 'gov.flt.attest',
    'attest_scope'    => 'gov_dept_flt',
    /* لا قياسَ فصلِ واجباتٍ معرَّفًا بعقودِ هذه الإدارة — يُعلَن ولا يُلفَّق */
    'sod_queries'     => array(),
);

require __DIR__ . '/../includes/dept_gov_space.php';
