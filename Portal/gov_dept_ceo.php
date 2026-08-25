<?php
/**
 * Portal/gov_dept_ceo.php — حوكمة مكتب الرئيس التنفيذي والنواب
 * ─────────────────────────────────────────────────────────────────────────
 * ⇐ INJ-0123
 *
 * «من يراقب المراقب؟» مطبَّقةً على أعلى مكتبٍ: حساباتُ مكتبِ الرئيسِ وصلاحياتُ
 * شاشاتِه وسجلاتُ تدقيقِه معروضةً كما تُعرض لأيِّ إدارةٍ أخرى — مكوّنٌ واحدٌ
 * لا نسخةٌ لكلٍّ (INJAZ-CORE-01 §12-1 الباب ١١).
 *
 * ◆ ومكتبُ الرئيسِ **ليس وحدةً في `org_units`** — فلا زاويةَ نطاقيةً تُثبَّت
 *   هنا؛ والعقدُ يقوم على **الدورِ 9** وشاشاتِ `Portal/` التي يملكها.
 * ◆ وعدّادُ الأحداثِ مقصورٌ على `source_module = 'system'` — وهي الوحدةُ التي
 *   تُنشر تحتها أفعالُ المنصّةِ العابرةُ للإدارات. ولم أُسنِده إلى وحدةٍ أكبرَ
 *   لتظهر أرقامٌ ضخمة: عدّادٌ يعرض أحداثَ غيرِه أسوأُ من رقمٍ صادقٍ صغير.
 * ◆ ولا قياسَ فصلِ واجباتٍ معلَنًا بعدُ — والمكوّنُ يقولها صراحةً ولا يعرض
 *   «صفرَ خرق»؛ فبراءةٌ عمّا لم يُقَس أخطرُ من غيابِ اللوحة.
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

$__pp = check_page_permissions($conn, 'Portal/gov_dept_ceo.php');
if (!$is_super_admin && empty($__pp['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا تملك صلاحية عرض حوكمة مكتب الرئيس', 'GOV-PERM-403', 'صلاحيات الحوكمة يمنحها مدير الصلاحيات');
}
ems_shell_axes($__pp);

$GOV_DEPT = array(
    'title'           => 'حوكمة مكتب الرئيس التنفيذي والنواب',
    'icon'            => 'fa fa-scale-balanced',
    'module_like'     => array('Portal/'),
    'team_roles'      => array(9),
    'sensitive_like'  => 'exec%',
    'events_module'   => 'system',
    'attest_endpoint' => '../Governance/gov_m14_actions.php',
    'attest_code'     => 'gov.ceo.attest',
    'attest_scope'    => 'gov_dept_ceo',
    /* لا قياسَ فصلِ واجباتٍ معرَّفًا بعقودِ المكتب — يُعلَن ولا يُلفَّق */
    'sod_queries'     => array(),
);

require __DIR__ . '/../includes/dept_gov_space.php';
/* حزمةُ الحالاتِ الدنيا (بوابة ٩): تحميلٌ وفراغٌ وخطأٌ — مخفيةٌ افتراضًا
   ويُظهرها منطقُ الشاشةِ عند حالِها. الدالةُ من ux_components التي تُحمِّلها القشرة. */
if (function_exists('ems_states_bundle')) {
    echo ems_states_bundle('لا سجلات حوكمة لمكتب الرئيس ضمن هذا النطاق',
                           'تقرأ الحسابات والصلاحيات وسجلات التدقيق من مصادرها الحية — راجع الدور أو النطاق');
}
