<?php
/**
 * Governance/gov_dept_gov.php — حوكمة الحوكمة والالتزام (M-14 · الشاشة ٣٣)
 * ─────────────────────────────────────────────────────────────────────────
 * «من يراقب المراقب؟» — الظهورُ النطاقيُّ لمكوّن «حوكمة الإدارة» الواحد على
 * إدارة الحوكمة نفسِها: حساباتُها (الدور 15) وصلاحياتُ شاشاتِها وفصلُ
 * واجباتِها وسجلاتُ تدقيقها. الفعلُ الكاتبُ الوحيد: gov.gov.attest.
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

$__pp = check_page_permissions($conn, 'Governance/gov_dept_gov.php');
if (!$is_super_admin && empty($__pp['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا تملك صلاحية عرض حوكمة الإدارة', 'GOV-PERM-403', 'صلاحيات الحوكمة يمنحها مدير الصلاحيات');
}
ems_shell_axes($__pp);

/* فصلُ واجباتِ الحوكمة المقيس (M-14 §9-3): من وضع القيدَ لا يمنح استثناءَه
   لنفسه · ومانحُ الاستثناء ليس طالبَه */
$GOV_DEPT = array(
    'title' => 'حوكمة الحوكمة والالتزام',
    'icon' => 'fas fa-scale-unbalanced-flip',
    'module_like' => array('Governance/', 'admin/', 'Settings/'),
    'team_roles' => array(15),
    'sensitive_like' => 'gov%',
    'events_module' => 'governance',
    'attest_endpoint' => 'gov_m14_actions.php',
    'attest_code' => 'gov.gov.attest',
    'attest_scope' => 'gov_dept_gov',
    'sod_queries' => array(
        array(
            'title' => 'طالب الاستثناء هو مانحه',
            'sql' => "SELECT CONCAT('EXC-', e.req_id) doc, u.name person
                        FROM exception_requests e
                        JOIN exception_approvals a ON a.req_id = e.req_id AND a.decision = 'approve'
                        LEFT JOIN users u ON u.id = a.approver_person_id
                       WHERE e.company_id = {$company_id}
                         AND a.approver_person_id = e.requester_person_id LIMIT 20",
        ),
        array(
            'title' => 'مراجع المنع هو المحاول نفسه',
            'sql' => "SELECT r.review_code doc, u.name person
                        FROM gov_denial_reviews r
                        JOIN guard_denials d ON d.deny_id = r.denial_id AND d.company_id = r.company_id
                        LEFT JOIN users u ON u.id = r.reviewed_by
                       WHERE r.company_id = {$company_id} AND r.reviewed_by = d.person_id LIMIT 20",
        ),
    ),
);

require __DIR__ . '/../includes/dept_gov_space.php';
/* حزمةُ الحالاتِ الدنيا (بوابة ٩): تحميلٌ وفراغٌ وخطأٌ — مخفيةٌ افتراضًا
   ويُظهرها منطقُ الشاشةِ عند حالِها. الدالةُ من ux_components التي تُحمِّلها القشرة. */
if (function_exists('ems_states_bundle')) {
    echo ems_states_bundle('لا قياسات فصل واجبات ضمن نطاق الحوكمة والالتزام',
                           'تقاس القياسات على طلبات الاستثناء ومراجعات المنع الحية — راجع الفترة أو حسابات الدور 15');
}
