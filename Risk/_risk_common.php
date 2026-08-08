<?php
/**
 * Risk/_risk_common.php — المشترك لشاشات M-16 (update0011 · DEC-G)
 * ─────────────────────────────────────────────────────────────────────────
 * الحراسة والبوابة وسلطة القبول وقوائم المرجع — الشاشات تضمّنه أولًا.
 * «السجل مركزي والإدارة زاوية عرض» (RK-02): النطاق الإداري يُفرض هنا
 * للأدوار غير المخولة بالمحفظة الكاملة.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: ../login.php');
    exit();
}
include_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/permissions_helper.php';
require_once __DIR__ . '/../app/Services/Risk/RiskService.php';

use App\Services\Risk\RiskService;

$company_id     = intval($_SESSION['user']['company_id'] ?? 0);
$uid            = intval($_SESSION['user']['id'] ?? 0);
$role           = strval($_SESSION['user']['role'] ?? '');
$is_super_admin = ($role === '-1');
if (!$is_super_admin && $company_id <= 0) {
    header('Location: ../login.php');
    exit();
}

require_once __DIR__ . '/_risk_authority.php';

/** سلطة القبول (ورقة 27) — الخريطة الواحدة في _risk_authority.php */
function risk_actor_authority($role)
{
    return risk_actor_authority_map($role);
}

/** المحفظة الكاملة لمن؟ الرئيس والمخاطر والسوبر — والبقية زاوية إدارتهم */
function risk_full_portfolio($role)
{
    return in_array($role, array('-1', '9', '28', '29'), true);
}

/** وحدة الهيكل التي يملكها الدور (زاوية العرض النطاقية) — من خريطة الأدوار */
function risk_role_org_unit($role)
{
    $map = array(
        '1' => 1, '7' => 1,               // التشغيل
        '2' => 15, '8' => 15,             // الموردون
        '3' => 5, '10' => 5, '11' => 5,   // الأسطول
        '4' => 14,                        // الموارد البشرية
        '5' => 8, '6' => 8,               // المواقع
        '12' => 2,                        // المبيعات والعقود
        '13' => 9, '14' => 9,             // الصيانة
        '16' => 11,                       // المشتريات
        '17' => 3, '18' => 3, '19' => 3, '20' => 3, '21' => 3, '22' => 3, // المالية
        '23' => 13,                       // النقل
        '24' => 7,                        // البلاغات
        '25' => 12,                       // المخازن
        '26' => 4,                        // التمويل
        '27' => 10,                       // القوى التشغيلية
        '15' => 6,                        // الصلاحيات → الحوكمة
    );
    return isset($map[$role]) ? $map[$role] : 0;
}

$RISK_AUTHORITY = risk_actor_authority($role);
$RISK_FULL      = $is_super_admin || risk_full_portfolio($role);
$RISK_ORG_UNIT  = $RISK_FULL ? 0 : risk_role_org_unit($role);

/** حارس الشاشة المركزي — can_view من modules (المسار النسبي للشاشة الحالية) */
function risk_guard_screen($conn, $is_super_admin)
{
    $script = 'Risk/' . basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $pp = check_page_permissions($conn, $script);
    if (!$is_super_admin && empty($pp['can_view'])) {
        ems_gov_flash_redirect('../main/dashboard.php', 'لا تملك صلاحية عرض شاشة المخاطر هذه', 'GOV-PERM-403', 'صلاحيات المخاطر يمنحها مدير الصلاحيات — والإدارات ترى زاويتها من مساحة مخاطر الإدارة');
    }
    return $pp;
}

/** وحدات المخاطر الإحدى عشرة (مرجع) */
function risk_units_list($conn, $company_id)
{
    $out = array();
    $st = $conn->prepare('SELECT id, ru_code, name_ar, dedup_window_days FROM risk_units WHERE company_id = ? AND active = 1 ORDER BY ru_code');
    $st->bind_param('i', $company_id);
    $st->execute();
    $r = $st->get_result();
    while ($x = $r->fetch_assoc()) { $out[] = $x; }
    $st->close();
    return $out;
}

/** شرط الزاوية الإدارية على السجل — يُلحق بجمل WHERE (معدّ آمنًا للأرقام) */
function risk_scope_sql($RISK_FULL, $RISK_ORG_UNIT, $alias = 'rr')
{
    if ($RISK_FULL) { return ''; }
    $u = (int) $RISK_ORG_UNIT;
    if ($u <= 0) { return " AND 1=0 "; } // دور بلا وحدة — لا يرى شيئًا (fail-closed)
    return " AND {$alias}.owner_unit_id = {$u} ";
}

$RISK_LEVELS = array('منخفض', 'متوسط', 'مرتفع', 'حرج', 'محظور');
$RISK_IMPACT_DIMS = array(
    'safety' => 'السلامة والصحة', 'operations' => 'التشغيل والإنتاج',
    'financial' => 'المال والتعرض', 'legal' => 'العقد والقانون',
    'reputation' => 'السمعة', 'environment' => 'البيئة',
    'data' => 'البيانات والتقنية', 'people' => 'الأفراد والقدرة',
);
