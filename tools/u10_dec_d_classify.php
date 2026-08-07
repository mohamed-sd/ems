<?php
/**
 * tools/u10_dec_d_classify.php — تصنيف جداول العزل الأربعة (U10-D23 · DEC-D)
 * ───────────────────────────────────────────────────────────────────────────
 * «لا يُفرَض العمود على الكل — بل يُصنَّف كلٌّ» بدليل حي لكل جدول:
 *   GLOBAL     عامٌّ للمنصة (هوية موحدة · قواميس · بنية نظام) — دليله طبيعته
 *              المرجعية وتسجيله في العقد
 *   CHILD      معزولٌ عبر أبيه — دليله عمود FK لأبٍ يحمل company_id وصفرُ يتيم
 *   QUARANTINE موروثٌ محجور (نسخ تراجع · صفر صفوف بلا كاتب) — دليله المحتوى
 *   TENANT     مستأجرٌ يجب ترحيله — ما بقي، ولكلٍّ مسارُ ترحيله
 * المخرج: docs/update0010/DEC_D_CLASSIFICATION.csv — ويستهلكه e05_checks.
 * php tools/u10_dec_d_classify.php
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');
$ROOT = dirname(__DIR__);
$o = function ($s) { fwrite(STDOUT, $s . "\n"); };

/* قائمة الدين الحالية — منطق e05 نفسه (المستثنى المشترك يبقى خارجها) */
$SHARED_PREFIX = array('nav_', 'cmp03_', 'sod_', 'hr_dictionaries', 'permission_template', 'template_permissions',
    'ems_event_', 'ems_processed_', 'admin_', 'super_admin', 'migrations', 'schema_');
$SHARED_EXACT = array('roles', 'modules', 'actions', 'role_permissions', 'link_groups', 'job_titles',
    'org_units', 'org_assignment_types', 'nav09_file_map', 'nav09_action_map', 'nav09_action_alias',
    'permission_templates', 'guard_override_policies', 'sensitive_field_policies',
    'currencies', 'units_of_measure', 'failure_codes', 'doc_types', 'countries');
$isShared = function ($t) use ($SHARED_PREFIX, $SHARED_EXACT) {
    if (in_array($t, $SHARED_EXACT, true)) { return true; }
    foreach ($SHARED_PREFIX as $p) { if (strpos($t, $p) === 0) { return true; } }
    return false;
};

/* ① جداول بلا عمود كيان */
$debt = array();
$r = mysqli_query($conn,
    "SELECT t.TABLE_NAME nm FROM information_schema.TABLES t
      WHERE t.TABLE_SCHEMA = DATABASE() AND t.TABLE_TYPE = 'BASE TABLE'
        AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS c
                         WHERE c.TABLE_SCHEMA = DATABASE() AND c.TABLE_NAME = t.TABLE_NAME
                           AND c.COLUMN_NAME = 'company_id')
      ORDER BY t.TABLE_NAME");
while ($x = mysqli_fetch_assoc($r)) { if (!$isShared($x['nm'])) { $debt[] = $x['nm']; } }

/* ② الأعمدة والآباء المحتملون */
$tablesWithCompany = array();
$r = mysqli_query($conn, "SELECT TABLE_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME = 'company_id'");
while ($x = mysqli_fetch_assoc($r)) { $tablesWithCompany[$x['TABLE_NAME']] = 1; }

/* عامٌّ بطبيعته المعلنة (هوية المنصة الموحدة · كيانات عابرة · قواميس · بنية) */
$GLOBAL_BY_DESIGN = array(
    'persons' => 'هوية المنصة الموحدة — EN-01: شخص واحد بمعرف واحد عبر النظام كله',
    'legal_entities' => 'سجل الكيانات العابر — الملكية تربط كيانات عدة (المجال المقيد يحكمه OwnershipDomainGuard)',
    'entity_ownership' => 'علاقات ملكية بين كيانات عابرة — المجال المقيد',
    'entity_roles' => 'أدوار الكيانات — تابع سجل الكيانات العابر',
    'entity_licenses' => 'تراخيص الكيانات — تابع السجل العابر',
    'tenants' => 'جدول المستأجرين نفسه — بنية المنصة',
    'ems_sequences' => 'عدادات النظام — بنية',
    'ems_sessions' => 'جلسات النظام — بنية',
    'request_types' => 'قاموس أنواع الطلبات الـ62 — مرجع منصة (WFM)',
    'request_routes' => 'قواعد توجيه الطلبات — مرجع منصة',
    'decision_reasons' => 'قاموس أسباب القرار — مرجع',
    'deduction_types' => 'قاموس أنواع الخصومات — مرجع',
    'pay_models' => 'قاموس نماذج الأجر — مرجع',
    'permit_types' => 'قاموس أنواع الأذون — مرجع',
    'stop_reason_codes' => 'قاموس أسباب التوقف — مرجع',
    'unified_fault_taxonomy' => 'شجرة الأعطال الموحدة — مرجع',
    'equipments_types' => 'قاموس أنواع المعدات — مرجع منصة (كتالوج)',
    'financing_models' => 'قاموس نماذج التمويل — المجال المقيد',
    'sec_actions' => 'قاموس أفعال SEC-013 الـ16 — مرجع',
    'sec_scopes' => 'قاموس نطاقات SEC-013 التسعة — مرجع',
    'policy_rules' => 'قواعد سياسة المنصة — حوكمة',
    'guard_policies' => 'سياسات الحراس — حوكمة',
    'shift_period_defs' => 'تعريفات فترات الورديات — قاموس',
    'approval_workflow_rules' => 'قواعد مسارات الاعتماد — حوكمة مركزية (قناة المحرك المقيدة)',
    'impact_matrix' => 'مصفوفة الأثر المرجعية (CON-02) — مرجع',
    'portal_elements' => 'عناصر البوابة المرجعية — نظام',
    'workspace_layouts' => 'قوالب تخطيط مساحة العمل — مرجع',
    'workspace_cards' => 'بطاقات مساحة العمل المرجعية — مرجع',
    'founding_mode' => 'وضع التأسيس — بنية منصة',
    'company_user_password_resets' => 'جدول منصي لإعادة تعيين كلمات المرور (بريد المستخدم مفتاحه)',
    'report_role_permissions' => 'صلاحيات التقارير بالدور — حوكمة مركزية (نظير role_permissions)',
    'permission_review_lines' => 'سطور حملة المراجعة الربعية — حوكمة مركزية',
    'permission_approval_steps' => 'خطوات اعتماد الصلاحيات — حوكمة مركزية',
    'template_permission_dims' => 'أبعاد بنود القوالب (SEC-013) — حوكمة مركزية',
    'screen_view_rows' => 'صفوف العرض المولدة من NAV-09 — مرجع نظام الشاشات',
    'uat_evidence' => 'أدلة UAT — سجل تسليم منصي',
    'api_tokens' => 'رموز API — تُعزل عبر مستخدمها (users.company_id) وقناتها forSystem',
    'action_events' => 'عقد الفعل: أحداثه المنشورة — قاموس مرجعي تابع لسجل actions العام',
    'action_impacts' => 'عقد الفعل: آثاره — قاموس مرجعي',
    'action_writes' => 'عقد الفعل: ما يكتبه — قاموس مرجعي',
    'event_consumers' => 'سجل مستهلكي الأحداث — قاموس تكامل منصي',
    'permit_required_approvals' => 'موافقات الأذون اللازمة بحسب النوع — قاموس إعداد',
);
require_once dirname(__DIR__) . '/app/Core/TenantRegistry.php';
/* محجورٌ موروث بالاسم */
$QUAR_PATTERNS = array('_bak', 'backup', '_old', '_legacy');

/* FK فعلية من info_schema */
$fkParent = array(); // table => [ [col, ref_table] ]
$r = mysqli_query($conn,
    "SELECT TABLE_NAME t, COLUMN_NAME c, REFERENCED_TABLE_NAME p
       FROM information_schema.KEY_COLUMN_USAGE
      WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL");
while ($x = mysqli_fetch_assoc($r)) { $fkParent[$x['t']][] = array($x['c'], $x['p']); }
/* أبوة بالاسم (الوحدات الجديدة بلا FK عمدًا): col foo_id → جدول foo/foos... */
$allTables = array();
$r = mysqli_query($conn, "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE='BASE TABLE'");
while ($x = mysqli_fetch_assoc($r)) { $allTables[$x['TABLE_NAME']] = 1; }
$guessParent = function ($table) use ($conn, $allTables, $tablesWithCompany) {
    $r = mysqli_query($conn, "SELECT COLUMN_NAME c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . mysqli_real_escape_string($conn, $table) . "' AND COLUMN_NAME LIKE '%\\_id' ORDER BY ORDINAL_POSITION");
    $out = array();
    while ($x = mysqli_fetch_assoc($r)) {
        $base = substr($x['c'], 0, -3);
        foreach (array($base, $base . 's', $base . 'es', 'proc_' . $base, 'fin_' . $base, 'ticket' . ($base === 'tk' ? 's' : ''), $base === 'tk' ? 'tickets' : null, $base === 'ws' ? 'ticket_workstreams' : null, $base === 'asg' ? 'org_assignments' : null, $base === 'op' ? 'financing_operations' : null, $base === 'app' ? 'rec_applications' : null, $base === 'evaluation' ? 'worker_evaluation' : null, $base === 'settlement' ? 'worker_settlement' : null) as $cand) {
            if ($cand !== null && isset($allTables[$cand]) && isset($tablesWithCompany[$cand])) { $out[] = array($x['c'], $cand); break; }
        }
    }
    return $out;
};

$csv = $ROOT . '/docs/update0010/DEC_D_CLASSIFICATION.csv';
$fh = fopen($csv, 'w');
fwrite($fh, "\xEF\xBB\xBF");
fputcsv($fh, array('table', 'rows', 'class', 'evidence', 'migration_path'));
$cnt = array('GLOBAL' => 0, 'CHILD' => 0, 'QUARANTINE' => 0, 'TENANT' => 0);
$tenantList = array();
foreach ($debt as $t) {
    $rows = 0;
    $rr = @mysqli_query($conn, "SELECT COUNT(*) c FROM `$t`");
    if ($rr) { $rows = (int) mysqli_fetch_assoc($rr)['c']; }

    $class = null; $ev = ''; $path = '—';
    /* ⓪ العقد أولًا: المصنف في TenantRegistry مساره معلن سلفًا */
    $reg = \App\Core\TenantRegistry::get($t);
    if (is_array($reg) && isset($reg['type'])) {
        if ($reg['type'] === \App\Core\TenantRegistry::T_GLOBAL || $reg['type'] === \App\Core\TenantRegistry::T_PLATFORM || $reg['type'] === \App\Core\TenantRegistry::T_CATALOG) {
            $class = 'GLOBAL'; $ev = 'مصنف في العقد: ' . $reg['type']; $path = 'العقد نافذ — لا عمود';
        } elseif ($reg['type'] === \App\Core\TenantRegistry::T_RESTRICTED) {
            $class = 'CHILD'; $ev = 'مجال مقيد في العقد — العزل عبر قناته المحروسة حصرًا'; $path = 'القناة المقيدة نافذة — وإن رُحّل: اشتقاق من سياق قناته';
        } elseif ($reg['type'] === \App\Core\TenantRegistry::T_CHILD) {
            $class = 'CHILD'; $ev = 'ابن معلن في العقد (الأب: ' . (isset($reg['parent']) ? $reg['parent'] : '?') . ')'; $path = 'العزل عبر الأب المعلن';
        }
    }
    if ($class === null) foreach ($QUAR_PATTERNS as $p) {
        if (strpos($t, $p) !== false) { $class = 'QUARANTINE'; $ev = 'نسخة تراجع/موروث بالاسم'; $path = 'يبقى محجورًا للقراءة — يُؤرشف ملفًّا عند GA'; break; }
    }
    if ($class === null && isset($GLOBAL_BY_DESIGN[$t])) {
        $class = 'GLOBAL'; $ev = $GLOBAL_BY_DESIGN[$t]; $path = 'يسجل T_GLOBAL/T_RESTRICTED في العقد — لا عمود';
    }
    if ($class === null) {
        $parents = isset($fkParent[$t]) ? $fkParent[$t] : $guessParent($t);
        foreach ($parents as $pp) {
            list($col, $ptab) = $pp;
            if (!isset($tablesWithCompany[$ptab])) { continue; }
            $rr = mysqli_query($conn, "SELECT COUNT(*) c FROM `$t` x LEFT JOIN `$ptab` p ON p." .
                (function ($conn, $ptab) { $r = mysqli_query($conn, "SHOW KEYS FROM `$ptab` WHERE Key_name='PRIMARY'"); $k = mysqli_fetch_assoc($r); return $k['Column_name']; })($conn, $ptab)
                . " = x.`$col` WHERE x.`$col` IS NOT NULL AND p." .
                (function ($conn, $ptab) { $r = mysqli_query($conn, "SHOW KEYS FROM `$ptab` WHERE Key_name='PRIMARY'"); $k = mysqli_fetch_assoc($r); return $k['Column_name']; })($conn, $ptab)
                . " IS NULL");
            $orphans = $rr ? (int) mysqli_fetch_assoc($rr)['c'] : -1;
            if ($orphans === 0) {
                $class = 'CHILD'; $ev = "معزول عبر أبيه $ptab ($col) — صفر يتيم من $rows صفًّا";
                $path = "العزل عبر JOIN الأب — وإن رُحّل مستقبلًا: اشتقاق من $ptab.company_id";
                break;
            }
        }
    }
    if ($class === null && $rows === 0) {
        $class = 'QUARANTINE'; $ev = 'صفر صفوف — بنية بلا كاتب بعد'; $path = 'يصنف عند أول كاتب — والحارس يفرض العمود على الكاتب الجديد';
    }
    if ($class === null) { $class = 'TENANT'; $ev = "بيانات مستأجر بلا اشتقاق أب ($rows صفًّا)"; $path = 'يُرحَّل: عمود + backfill بقاعدة معلنة + فهرس'; $tenantList[] = $t; }
    $cnt[$class]++;
    fputcsv($fh, array($t, $rows, $class, $ev, $path));
}
fclose($fh);

$o('══ DEC-D — تصنيف ' . count($debt) . ' جدولًا ══');
foreach ($cnt as $k => $v) { $o("  $k: $v"); }
if ($tenantList) { $o('  TENANT: ' . implode(' · ', $tenantList)); }
$o('الدفتر: ' . $csv);
