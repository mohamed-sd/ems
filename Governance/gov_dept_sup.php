<?php
/**
 * Governance/gov_dept_sup.php — حوكمةُ الموردين (M-09 · gov_dept_sup)
 * ─────────────────────────────────────────────────────────────────────────
 * الشاشةُ الحاملةُ تضبط عقدَ $GOV_DEPT والمكوّنُ `includes/dept_gov_space.php`
 * واحدٌ — على نمطِ أغلفةِ sit/ops/flt/mnt.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }
include '../config.php';
require_once __DIR__ . '/../includes/permissions_helper.php';
require_once __DIR__ . '/../includes/screen_contract.php';

$current_role   = strval($_SESSION['user']['role'] ?? '');
$is_super_admin = ($current_role === '-1');
$company_id     = intval($_SESSION['user']['company_id'] ?? 0);
if (!$is_super_admin && $company_id <= 0) { header('Location: ../login.php'); exit(); }

$__pp = check_page_permissions($conn, 'Governance/gov_dept_sup.php');
if (!$is_super_admin && empty($__pp['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا تملك صلاحية عرض حوكمة الإدارة', 'GOV-PERM-403', 'صلاحيات الحوكمة يمنحها مدير الصلاحيات');
}
ems_shell_axes($__pp);

$GOV_DEPT = array(
    'title'           => 'حوكمة الموردين',
    'icon'            => 'fa fa-scale-balanced',
    'module_like'     => array('Suppliers/'),
    'team_roles'      => array(2, 8),
    'sensitive_like'  => 'supplier%',
    'events_module'   => 'suppliers',
    'attest_endpoint' => '../Governance/gov_m14_actions.php',
    'attest_code'     => 'gov.sup.attest',
    'attest_scope'    => 'gov_dept_sup',
    'sod_queries'     => array(),
);

require __DIR__ . '/../includes/dept_gov_space.php';
/* حزمةُ الحالاتِ الدنيا (بوابة ٩): تحميلٌ وفراغٌ وخطأٌ — مخفيةٌ افتراضًا
   ويُظهرها منطقُ الشاشةِ عند حالِها. الدالةُ من ux_components التي تُحمِّلها القشرة. */
if (function_exists('ems_states_bundle')) {
    echo ems_states_bundle('لا قياسات فصل واجبات ضمن نطاق الموردين',
                           'قياسات هذه الإدارة تعرف على مستندات الموردين الحية — راجع الفترة أو حسابات الدورين 2 و8');
}
