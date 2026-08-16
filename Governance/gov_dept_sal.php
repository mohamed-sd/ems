<?php
/**
 * Governance/gov_dept_sal.php — حوكمةُ المبيعاتِ والعقود (M-08 · gov_dept_sal)
 * ─────────────────────────────────────────────────────────────────────────
 * الشاشةُ الحاملةُ تضبط عقدَ $GOV_DEPT والمكوّنُ `includes/dept_gov_space.php`
 * واحدٌ (INJAZ-CORE-01 §12-1 الباب ١١) — على نمطِ أغلفةِ sit/ops/flt/mnt.
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

$__pp = check_page_permissions($conn, 'Governance/gov_dept_sal.php');
if (!$is_super_admin && empty($__pp['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا تملك صلاحية عرض حوكمة الإدارة', 'GOV-PERM-403', 'صلاحيات الحوكمة يمنحها مدير الصلاحيات');
}
ems_shell_axes($__pp);

$GOV_DEPT = array(
    'title'           => 'حوكمة المبيعات والعقود',
    'icon'            => 'fa fa-scale-balanced',
    'module_like'     => array('Contracts/', 'Opportunities/', 'Clients/'),
    'team_roles'      => array(12),
    'sensitive_like'  => 'contract%',
    'events_module'   => 'sales',
    'attest_endpoint' => '../Governance/gov_m14_actions.php',
    'attest_code'     => 'gov.sal.attest',
    'attest_scope'    => 'gov_dept_sal',
    /* لا قياسَ فصلِ واجباتٍ معرَّفًا بعقودِ هذه الإدارة — يُعلَن ولا يُلفَّق */
    'sod_queries'     => array(),
);

require __DIR__ . '/../includes/dept_gov_space.php';
