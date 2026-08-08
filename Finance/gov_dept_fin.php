<?php
/**
 * Finance/gov_dept_fin.php — حوكمة المالية والخزينة (M-10 · الشاشة ٢٨)
 * ─────────────────────────────────────────────────────────────────────────
 * ظهورٌ نطاقيٌّ لمكوّن «حوكمة الإدارة» الواحد (includes/dept_gov_space.php)
 * بزاوية المالية والخزينة: الحساباتُ التابعة (17..22) وصلاحياتُ شاشاتِها
 * وفصلُ واجباتِها المقيسُ على المستندات الحية وسجلاتُ تدقيقها.
 * الفعلُ الكاتبُ الوحيد: gov.fin.attest — يشهد ولا يمنح.
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

$__pp = check_page_permissions($conn, 'Finance/gov_dept_fin.php');
if (!$is_super_admin && empty($__pp['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا تملك صلاحية عرض حوكمة الإدارة', 'GOV-PERM-403', 'صلاحيات الحوكمة يمنحها مدير الصلاحيات');
}
$__ro = $__pp;
ems_shell_axes($__ro);

/* فصلُ الواجبات المقيسُ بعقود M-10 §9-3 — على المستندات الحية لا نصًّا */
$GOV_DEPT = array(
    'title' => 'حوكمة المالية والخزينة',
    'icon' => 'fas fa-scale-balanced',
    'module_like' => array('Finance/', 'FinRequests/'),
    'team_roles' => array(17, 18, 19, 20, 21, 22),
    'sensitive_like' => 'fin%',
    'events_module' => 'finance',
    'attest_endpoint' => 'fin_m10_actions.php',
    'attest_code' => 'gov.fin.attest',
    'attest_scope' => 'gov_dept_fin',
    'sod_queries' => array(
        array(
            'title' => 'إنشاءُ الموازنةِ واعتمادُها بيدٍ واحدة',
            'sql' => "SELECT CONCAT('BUD-', b.id) doc, u.name person
                        FROM fin_budgets b LEFT JOIN users u ON u.id = b.approved_by
                       WHERE b.company_id = {$company_id} AND b.approved_by IS NOT NULL
                         AND b.approved_by = b.created_by LIMIT 20",
        ),
        array(
            'title' => 'إعدادُ القيدِ ونشرُه بيدٍ واحدة',
            'sql' => "SELECT j.entry_no doc, u.name person
                        FROM fin_journal_entries j LEFT JOIN users u ON u.id = j.posted_by
                       WHERE j.company_id = {$company_id} AND j.posted_by IS NOT NULL
                         AND j.posted_by = j.created_by AND COALESCE(j.is_deleted,0) = 0 LIMIT 20",
        ),
        array(
            'title' => 'إنشاءُ الصرفِ وتنفيذُه بيدٍ واحدة',
            'sql' => "SELECT p.payment_no doc, u.name person
                        FROM fin_payments p LEFT JOIN users u ON u.id = p.executed_by
                       WHERE p.company_id = {$company_id} AND p.executed_by IS NOT NULL
                         AND p.executed_by = p.created_by AND COALESCE(p.is_deleted,0) = 0 LIMIT 20",
        ),
    ),
);

require __DIR__ . '/../includes/dept_gov_space.php';
