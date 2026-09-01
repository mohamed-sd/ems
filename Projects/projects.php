<?php
// تحميل الإعدادات والأمان
include '../config.php';
require_once __DIR__ . '/../includes/w14_grid.php';
require_once '../includes/approval_workflow.php';
require_once '../includes/permissions_helper.php';

// ── RF-02 · CS-01 — حارسُ الشاشةِ فوقَ أيِّ معالجٍ يكتب ────────────────────
// كان هذا السطحُ يعتمد على insidebar.php وحدَه في الحجب، وinsidebar يقع
// **بعدَ** معالجِ الكتابة — فيُرحَّل الأثرُ ثم يُعاد التوجيهُ برسالةِ «لا صلاحية».
// الدالةُ نفسُها ولا تغييرَ في مَن يُمنع — التغييرُ في **متى**: قبلَ الكتابة.
if (function_exists('enforce_current_page_view_permission') && isset($conn)) {
    enforce_current_page_view_permission($conn, '../main/dashboard.php');
}

if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
}

if (!function_exists('projects_fix_mojibake_output')) {
    function projects_fix_mojibake_output($buffer)
    {
        $pattern = '/[\x{00C2}\x{00C3}\x{00D8}\x{00D9}\x{00E2}\x{00F0}][^\s<>{}\[\]"\'=]{0,24}/u';
        $fixed = preg_replace_callback($pattern, function ($m) {
            if (function_exists('mb_convert_encoding')) {
                $decoded = @mb_convert_encoding($m[0], 'UTF-8', 'Windows-1252');
                if ($decoded !== false && $decoded !== '') {
                    return $decoded;
                }
            }

            return $m[0];
        }, $buffer);

        return is_string($fixed) ? $fixed : $buffer;
    }
}

ob_start('projects_fix_mojibake_output');

// التحقق من تسجيل الدخول
require_login();

$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
if ($company_id <= 0) {
    ems_gov_flash_redirect('../main/dashboard.php', 'الحساب غير مرتبط بشركة.', 'GOV-INFO-200', '');
    exit();
}

// أعمدة project (client_id/company_id/is_deleted/deleted_at/deleted_by) وclients (company_id/
// is_deleted) قائمةٌ كلها منذ ترحيلها — سقطت فحوص db_table_has_column والهجرة الذاتية
// (نمط ems_runtime_ddl الساقط)، وسقط معها بديلا النطاق عبر created_by (كانا لغياب company_id)
// وأسطر mines (الجدول غير موجود في القاعدة أصلًا). العزل كله عبر بوابة المستأجر الآن.
$project_client_column = 'client_id';
$prj_gate = ems_tenant_db();

function projects_redirect_with_msg($msg)
{
    ems_gov_flash_redirect('projects.php', $msg, 'GOV-INFO-200', '');
    exit();
}

// ════════════════════════════════════════════════════════════════════════════
// 🔒    
// ════════════════════════════════════════════════════════════════════════════
$page_permissions = check_page_permissions($conn, 'Projects/projects.php');
if (!isset($page_permissions['can_view']) || !$page_permissions['can_view']) {
    $legacy_page_permissions = check_page_permissions($conn, 'Projects/oprationprojects.php');
    if (isset($legacy_page_permissions['can_view']) && $legacy_page_permissions['can_view']) {
        $page_permissions = $legacy_page_permissions;
    }
}
$can_view = $page_permissions['can_view'];
$can_add = $page_permissions['can_add'];
$can_edit = $page_permissions['can_edit'];
$can_delete = $page_permissions['can_delete'];

// منع الوصول إذا لم تكن هناك صلاحية عرض
if (!$can_view) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض المشاريع ❌', 'GOV-PERM-403', '');
    exit();
}

// معالجة حذف المشروع
if (isset($_GET['delete_id']) && isset($_GET['csrf_token'])) {
    if (!$can_delete) {
        projects_redirect_with_msg('لا توجد صلاحية حذف المشاريع ❌');
    }

    // التحقق من CSRF Token
    if (!verify_csrf_token($_GET['csrf_token'])) {
        header('Location: projects.php?error=' . urlencode('خطأ أمني'));
        exit();
    }

    $delete_id = intval($_GET['delete_id']);

    $old_project = null;
    try {
        $prj_old_rows = $prj_gate->scopedQuery(array('scope' => array('op' => 'project')),
            "SELECT op.* FROM project op WHERE op.id = ? AND {TENANT_SCOPE} AND op.is_deleted = 0 LIMIT 1",
            array($delete_id));
        $old_project = !empty($prj_old_rows) ? $prj_old_rows[0] : null;
    } catch (\Throwable $t) { error_log('projects.php delete precheck: ' . $t->getMessage()); }

    if (!$old_project) {
        projects_redirect_with_msg('المشروع غير موجود أو لا يتبع لشركتك ❌');
    }

    // الأصل: status='0' + is_deleted/deleted_at/deleted_by معًا — عبر البوابة: تحديث الحالة
    // ثم softDelete (يختم الأعمدة الثلاثة من سياق الجلسة نفسها).
    try {
        $prj_gate->update('project', array('status' => '0'), array('id' => $delete_id, 'is_deleted' => 0));
        $prj_gate->softDelete('project', $delete_id);
        log_security_event('PROJECT_SOFT_DELETED', "Soft deleted project ID: $delete_id");
        projects_redirect_with_msg('تم حذف المشروع (حذف ناعم) بنجاح ✅');
    } catch (\Throwable $t) {
        error_log('projects.php delete: ' . $t->getMessage());
    }

    projects_redirect_with_msg('حدث خطأ أثناء حذف المشروع ❌');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['project_name'])) {
    // التحقق من CSRF Token
    if (!verify_csrf_token(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '')) {
        die('خطأ في التحقق من الأمان - CSRF Token غير صحيح');
    }

    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $is_editing = $id > 0;

    if ($is_editing && !$can_edit) {
        projects_redirect_with_msg('لا توجد صلاحية تعديل المشاريع ❌');
    } elseif (!$is_editing && !$can_add) {
        projects_redirect_with_msg('لا توجد صلاحية إضافة مشاريع جديدة ❌');
    }

    $client_id = !empty($_POST['client_id']) ? intval($_POST['client_id']) : 0;
    if ($client_id <= 0 && !empty($_POST['company_client_id'])) {
        $client_id = intval($_POST['company_client_id']);
    }

    // جلب البيانات المدخولة بشكل آمن
    $name = sanitize_input($_POST['project_name']);
    $project_code = sanitize_input(isset($_POST['project_code']) ? $_POST['project_code'] : '');
    $mine_code = sanitize_input(isset($_POST['mine_code']) ? $_POST['mine_code'] : '');
    $category = sanitize_input(isset($_POST['category']) ? $_POST['category'] : '');
    $sub_sector = sanitize_input(isset($_POST['sub_sector']) ? $_POST['sub_sector'] : '');
    $state = sanitize_input(isset($_POST['state']) ? $_POST['state'] : '');
    $region = sanitize_input(isset($_POST['region']) ? $_POST['region'] : '');
    $nearest_market = sanitize_input(isset($_POST['nearest_market']) ? $_POST['nearest_market'] : '');
    $latitude = sanitize_input(isset($_POST['latitude']) ? $_POST['latitude'] : '');
    $longitude = sanitize_input(isset($_POST['longitude']) ? $_POST['longitude'] : '');
    $location = sanitize_input(isset($_POST['location']) ? $_POST['location'] : '');
    $status = sanitize_input($_POST['status']);
    $total = floatval(isset($_POST['total']) ? $_POST['total'] : 0);

    // جلب اسم العميل إذا تم اختياره
    $client = '';
    if ($client_id > 0) {
        try {
            $prj_cli_rows = $prj_gate->scopedQuery(array('scope' => array('c' => 'clients')),
                "SELECT c.client_name FROM clients c WHERE c.id = ? AND {TENANT_SCOPE} AND c.is_deleted = 0",
                array($client_id));
            if (!empty($prj_cli_rows)) {
                $client = sanitize_input($prj_cli_rows[0]['client_name']);
            }
        } catch (\Throwable $t) { error_log('projects.php client lookup: ' . $t->getMessage()); }

        if ($client === '') {
            projects_redirect_with_msg('العميل المحدد لا يتبع لشركتك ❌');
        }
    } else {
        $client = sanitize_input(isset($_POST['client_name']) ? $_POST['client_name'] : '');
    }

    $created_by = intval(isset($_SESSION['user']['id']) ? $_SESSION['user']['id'] : 0);
    if ($created_by <= 0) {
        projects_redirect_with_msg('هوية المستخدم غير صالحة ❌');
    }

    if ($id > 0) {
        $old_project = null;
        try {
            $prj_old_rows = $prj_gate->scopedQuery(array('scope' => array('op' => 'project')),
                "SELECT op.* FROM project op WHERE op.id = ? AND {TENANT_SCOPE} AND op.is_deleted = 0 LIMIT 1",
                array($id));
            $old_project = !empty($prj_old_rows) ? $prj_old_rows[0] : null;
        } catch (\Throwable $t) { error_log('projects.php update precheck: ' . $t->getMessage()); }

        if (!$old_project) {
            projects_redirect_with_msg('المشروع غير موجود أو لا يتبع لشركتك ❌');
        }

        // (كان الأصل يعيد كتابة company_id بقيمة الجلسة نفسها — الصف معزولٌ عبر البوابة
        // أصلًا فالإعادة لغوٌ، وتمريره في بيانات التحديث يُرفض تعاقديًا كتزوير هوية)
        $executed = false;
        $error_message = '';
        try {
            $prj_gate->update('project', array(
                $project_client_column => $client_id,
                'name' => $name,
                'client' => $client,
                'location' => $location,
                'project_code' => $project_code,
                'mine_code' => $mine_code,
                'category' => $category,
                'sub_sector' => $sub_sector,
                'state' => $state,
                'region' => $region,
                'nearest_market' => $nearest_market,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'total' => $total,
                'status' => $status,
                'updated_at' => date('Y-m-d H:i:s'),
            ), array('id' => $id, 'is_deleted' => 0));
            $executed = true;
        } catch (\Throwable $t) {
            $error_message = $t->getMessage();
            error_log('projects.php update: ' . $error_message);
        }

        if ($executed) {
            log_security_event('PROJECT_UPDATED', "Updated project: $name (ID: $id)");
            projects_redirect_with_msg('تم تعديل المشروع بنجاح ✅');
        }

        projects_redirect_with_msg('حدث خطأ أثناء تعديل المشروع ❌' . (!empty($error_message) ? ' - ' . $error_message : ''));
    } else {
        // إضافة عبر البوابة (company_id تحقنه من سياق الجلسة، وcreate_at بتاريخ PHP بدل NOW)
        $inserted = false;
        $new_project_id = 0;
        try {
            $new_project_id = (int) $prj_gate->insert('project', array(
                $project_client_column => $client_id,
                'name' => $name,
                'client' => $client,
                'location' => $location,
                'project_code' => $project_code,
                'mine_code' => $mine_code,
                'category' => $category,
                'sub_sector' => $sub_sector,
                'state' => $state,
                'region' => $region,
                'nearest_market' => $nearest_market,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'total' => $total,
                'status' => $status,
                'created_by' => $created_by,
                'create_at' => date('Y-m-d H:i:s'),
            ));
            $inserted = true;
        } catch (\Throwable $t) { error_log('projects.php insert: ' . $t->getMessage()); }

        if ($inserted) {
            log_security_event('PROJECT_CREATED', "Created project: $name");

            /* ══ **الموقعُ الافتراضيُّ خطّافٌ لا تعبئةٌ لمرةٍ واحدة.**
                 ثابتٌ يحرسه `sites_entity_test`: «صفرُ مشروعٍ حيٍّ بلا موقعٍ
                 افتراضي». وكان الترحيلُ عبّأه مرةً (P-01) **ولا كاتبَ حيًّا له
                 في مسارِ المنتجِ إلا `Portal/project_charter.php`** — فمشروعٌ
                 يُفتح من هذه الشاشةِ يُنشأ بلا موقعٍ افتراضيٍّ فينكسر الثابتُ
                 صمتًا، ويتبعه أن العقودَ عليه لا تجد موقعًا تُنسَب إليه.
                 (مقيسٌ: 30 مشروعًا حيًّا بلا افتراضيٍّ عُبّئت في هجرة
                  `2027_02_17`، ومنها مشاريعُ أنشأها مستخدمون بعدَ الترحيل.)
                 ◆ والاسمُ **اسمُ المشروعِ حرفيًّا** — وهو نصُّ الثابتِ الثاني:
                   «الافتراضيُّ باسمِ مشروعِه لا اختراع».
                 ◆ والنمطُ منقولٌ من `Portal/project_charter.php:215-224`. */
            if ($new_project_id > 0) {
                try {
                    $prj_gate->insert('sites', array(
                        'project_id' => $new_project_id,
                        'name'       => $name,
                        'site_kind'  => 'site',
                        'status'     => 1,
                        'is_default' => 1,
                    ));
                } catch (\Throwable $t) {
                    /* لا يُسقَط فتحُ المشروعِ لأجلِ موقعٍ — لكن لا يُبلَع صمتًا:
                       الثابتُ يبقى مثقوبًا وهجرةُ التعبئةِ تكشفه، والسطرُ هنا
                       يسمّي المشروعَ الذي انكسر عنده. */
                    error_log('project default site #' . $new_project_id . ': ' . $t->getMessage());
                }
            }

            // M-00 §11 (ProjectChartered): فتحُ مشروعٍ حقيقةٌ تُنشر من نقطة الحدث —
            // يستهلكها ميثاقُ المشروع ومراكزُ التكلفة ومديرو المواقع. لا تلفيق:
            // الحمولة مما أدخله المستخدم فعلًا، والعميل إن وُجد هو أقصى ربطِ العقد المتاح هنا.
            if ($new_project_id > 0) {
                try {
                    require_once dirname(__DIR__) . '/app/Core/EventPublisher.php';
                    \App\Core\EventPublisher::publishFact($conn, array(
                        'event_key'       => 'project.chartered',
                        'category'        => 'operational',
                        'source_module'   => 'system',
                        'company_id'      => $company_id,
                        'entity_type'     => 'project',
                        'entity_id'       => $new_project_id,
                        'occurred_at'     => gmdate('Y-m-d H:i:s'),
                        'created_by'      => $created_by ?: 1,
                        'idempotency_key' => 'project_chartered:' . $new_project_id,
                        'project_id'      => $new_project_id,
                        'notes'           => 'فتح مشروع: ' . $name,
                        'payload'         => array(
                            'project_id'   => $new_project_id,
                            'name'         => (string) $name,
                            'project_code' => (string) $project_code,
                            'client_id'    => (int) $client_id,
                            'client'       => (string) $client,
                            'location'     => (string) $location,
                            'status'       => (string) $status,
                        ),
                    ));
                } catch (\Throwable $t) { error_log('projects.php charter fact #' . $new_project_id . ': ' . $t->getMessage()); }
            }
            projects_redirect_with_msg('تم إضافة المشروع بنجاح ✅');
        } else {
            projects_redirect_with_msg('حدث خطأ أثناء الإضافة ❌');
        }
    }
}
?>


<?php
// ── كودُ المشروع التالي (PRJ-####) — بنفسِ نمط كود العميل في Clients/clients.php ──
// المصدرُ أعلى كودٍ قائمٍ على النمط، لا عدَّادٌ مستقلٌّ يفترق عن الواقع. والصيغةُ
// المحفوظةُ في القاعدة اليوم `PRJ-0001…`، فما خالفها من أكوادٍ يدويةٍ لا يُحتسب.
$next_project_code = 'PRJ-0001';
try {
    $prj_last_code = $prj_gate->scopedQuery(array('scope' => array('p' => 'project')),
        "SELECT p.project_code FROM project p
          WHERE {TENANT_SCOPE} AND p.project_code REGEXP '^PRJ-[0-9]+$' AND p.is_deleted = 0
          ORDER BY CAST(SUBSTRING(p.project_code, 5) AS UNSIGNED) DESC
          LIMIT 1");
    if (!empty($prj_last_code)) {
        $next_project_code = 'PRJ-' . str_pad(
            (string) (intval(substr($prj_last_code[0]['project_code'], 4)) + 1), 4, '0', STR_PAD_LEFT);
    }
} catch (\Throwable $t) { error_log('projects.php next code: ' . $t->getMessage()); }

$projects_total_count = 0;
$projects_active_count = 0;
$projects_inactive_count = 0;
$projects_with_contracts_count = 0;
$projects_active_contracts_count = 0;

$project_active_status_case_sql = "(status = 1 OR status = '1' OR TRIM(status) = 'نشط' OR TRIM(LOWER(status)) = 'active' OR TRIM(LOWER(status)) = 'true')";

try {
    $prj_stats = $prj_gate->scopedQuery(array('scope' => array('p' => 'project')),
        "SELECT
        COUNT(*) AS total_projects,
        SUM(CASE WHEN $project_active_status_case_sql THEN 1 ELSE 0 END) AS active_projects
    FROM project p
    WHERE {TENANT_SCOPE} AND p.is_deleted = 0");
    if (!empty($prj_stats)) {
        $projects_total_count = intval($prj_stats[0]['total_projects']);
        $projects_active_count = intval($prj_stats[0]['active_projects']);
    }
} catch (\Throwable $t) { error_log('projects.php stats: ' . $t->getMessage()); }
$projects_inactive_count = max(0, $projects_total_count - $projects_active_count);

try {
    $prj_wc = $prj_gate->scopedQuery(array('scope' => array('c' => 'contracts', 'p' => 'project')),
        "SELECT COUNT(DISTINCT c.project_id) AS projects_with_contracts
    FROM contracts c
    INNER JOIN project p ON p.id = c.project_id
    WHERE {TENANT_SCOPE} AND p.is_deleted = 0 AND c.status = 1");
    if (!empty($prj_wc)) {
        $projects_with_contracts_count = intval($prj_wc[0]['projects_with_contracts']);
    }
    $prj_ac = $prj_gate->scopedQuery(array('scope' => array('c' => 'contracts', 'p' => 'project')),
        "SELECT COUNT(*) AS total_active_contracts
    FROM contracts c
    INNER JOIN project p ON p.id = c.project_id
    WHERE {TENANT_SCOPE} AND p.is_deleted = 0 AND c.status = 1");
    if (!empty($prj_ac)) {
        $projects_active_contracts_count = intval($prj_ac[0]['total_active_contracts']);
    }
} catch (\Throwable $t) { error_log('projects.php contract stats: ' . $t->getMessage()); }
?>


<?php
$page_title = "إيكوبيشن | المشاريع";
// CM-00 (DEC-E · U10): بذرُ محاورِ الغلافِ من الخادم — AX-2/3 من محرك الصلاحيات
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : (isset($permissions) ? $permissions : null));
include("../inheader.php");
include('../insidebar.php');
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>

<link rel="stylesheet" href="/ems/assets/css/all.min.css">
<link href="/ems/assets/css/local-fonts.css" rel="stylesheet">
<div class="main projects-main ems-unified-page-shell scr-projects">

    <?php
    // Unified page header (structure: includes/page_header.php · styling: ems.main.all.style.css)
    $header_title = 'المشاريع';
    $header_icon = 'fas fa-project-diagram';
    $header_actions = array();
    if ($can_add) {
        $header_actions[] = array('id' => 'toggleForm', 'class' => 'add-btn', 'icon' => 'fa fa-solid fa-plus', 'label' => '');
    } else {
        $header_actions[] = array('tag' => 'button', 'class' => 'add-btn', 'disabled' => true, 'icon' => 'fas fa-plus-circle', 'label' => 'إضافة (بدون صلاحية)');
    }
    $header_actions[] = array('id' => 'toggleStats', 'class' => 'btn', 'title' => 'إظهار أو إخفاء الإحصائيات', 'icon' => 'fas fa-eye', 'label' => 'إظهار الإحصائيات', 'label_class' => 'projects-toggle-stats-text');
    // ── نظام Excel الموحّد (Unified Excel Framework) ──
    require_once __DIR__ . '/../includes/excel_ui.php';
    foreach (ems_excel_header_actions('projects', 'المشاريع', $can_add) as $__xlAction) {
        $header_actions[] = $__xlAction;
    }
    $header_back = array('href' => '../main/dashboard.php', 'class' => 'back-btn', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php'); ?>
    <!-- سجلُّ حقولِ الورقةِ بحبّتِه — يُضاف بجانبِ ما بُني لا بدلًا منه،
         فالمبنيُّ له أفعالُه والورقةُ تطلب السجلَّ بحقولِه كلِّها -->
    <div class="card"><div class="card-header"><h5><i class="fa fa-clipboard-list"></i> سجل حقول الورقة</h5></div>
    <div class="card-body"><div class="table-container">
        <?php /* GUIDE_COLS:govui_field_close:emsList_sal_projects
             الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
             والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
             ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
        $GUIDE_COLS = array(
            'رقم المشروع' => 'g212',
            'رقم العميل' => 'g213',
            'اسم العميل (بحث)' => 'g214',
            'اسم المشروع' => 'g215',
            'الوصف' => 'g216',
            'الموقع (نطاق تنفيذ)' => 'g217',
            'القطاع' => 'g218',
            'حالة المشروع' => 'g219',
            'تاريخ البداية' => 'g220',
            'المسؤول التجاري' => 'g221',
            'القيمة التقديرية ($)' => 'g222',
            'القيمة التقديرية (ج.س)' => 'g223',
            'عدد العقود' => 'g224',
            'ملاحظات' => 'g225',
            'كود المشروع لدى العميل' => 'g226',
            'الإقليم/الولاية' => 'g227',
            'تسلسل مشروع العميل' => 'g228',
            'نوع الخدمة' => 'g229',
            'نموذج العمل' => 'g230',
            'أساس التسمية والحدود' => 'g231',
            'قاعدة التجميع' => 'g232',
            'مستوى الحجية' => 'g233',
        );
        $D = array();
        $__gridRows = ems_w14_guide_rows('sal_projects');
        echo ems_w14_grid('emsList_sal_projects', $GUIDE_COLS, $__gridRows, $D, 'لا سطر مسجل بعد في سجل المشاريع'); /* /GUIDE_COLS */ ?>
    </div></div></div>
    <?php 
    ?>
<?php require_once __DIR__ . '/../includes/entity_tabs.php'; echo ems_entity_tabs('project', 'نظرة عامة'); ?>

    <!-- <div class="header projects-header-shell">
        <a href="../main/dashboard.php" class="back-btn">
            <i class="fas fa-arrow-right"></i> رجوع
        </a>
        <h1 class="page-title">
            <div class="title-icon"><i class="fas fa-project-diagram"></i></div>
            إدارة المشاريع
        </h1>
        <div class="header -actions">
            <?php if ($can_add): ?>
                <a href="javascript:void(0)" id="toggleForm" class="add-btn projects-header-add">
                    <i class="fas fa-plus-circle"></i> إضافة مشروع
                </a>
            <?php else: ?>
                <button class="add-btn projects-btn-secondary" disabled>
                    <i class="fas fa-plus-circle"></i> إضافة (بدون صلاحية)
                </button>
            <?php endif; ?>
        </div>
    </div> -->

    <?php if (!empty($_GET['msg'])): ?>
        <?php $isSuccess = strpos($_GET['msg'], '✅') !== false; ?>
        <div class="success-message <?= $isSuccess ? 'is-success' : 'is-error' ?>">
            <i class="fas <?= $isSuccess ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?php echo htmlspecialchars($_GET['msg']); ?>
        </div>
    <?php endif; ?>

    <?php echo ems_states_bundle('لا مشاريع مسجلة ضمن هذا الترشيح', 'أضف مشروعا جديدا أو غير المرشحات'); ?>

    <div class="stats-section projects-hidden" id="projectsStatsSection">
        <div class="stats-grid">
            <div class="stats-card stats-primary">
                <div class="stats-icon"><i class="fas fa-project-diagram"></i></div>
                 <div class="stats-value"><?php echo $projects_total_count; ?></div>
                <div class="stats-title">إجمالي المشاريع</div>
            </div>
            <div class="stats-card stats-success">
                <div class="stats-icon"><i class="fas fa-check-circle"></i></div>
                 <div class="stats-value"><?php echo $projects_active_count; ?></div>
                <div class="stats-title">المشاريع النشطة</div>
            </div>
            <div class="stats-card stats-danger">
                <div class="stats-icon"><i class="fas fa-times-circle"></i></div>
                <div class="stats-value"><?php echo $projects_inactive_count; ?></div>
                <div class="stats-title">المشاريع غير النشطة</div>
            </div>
            <div class="stats-card stats-cyan">
                <div class="stats-icon"><i class="fas fa-file-contract"></i></div>
                <div class="stats-value"><?php echo $projects_with_contracts_count; ?></div>
                <div class="stats-title">المشاريع التي لها عقود</div>
            </div>
            <div class="stats-card stats-purple">
                <div class="stats-icon"><i class="fas fa-clipboard-list"></i></div>
                <div class="stats-value"><?php echo $projects_active_contracts_count; ?></div>
                <div class="stats-title">إجمالي العقود النشطة</div>
            </div>
        </div>
    </div>



    <!-- فورم إضافة / تعديل مشروع -->
    <form id="projectForm" action="" method="post" class="allforms">
           <div class="card-header">
                <h5><i class="fas fa-edit"></i> <span id="formTitle">إضافة مشروع جديد</span></h5>
            </div>
        <div class="card shadow-sm pu-form-card">

            <div class="card-body">
                <input type="hidden" name="id" id="project_id" value="">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <div class="form-grid">
                    <div>
                        <label for="client_id"><i class="fas fa-user-tie"></i> اسم العميل (اختياري)</label>
                        <select name="client_id" id="client_id" required>
                            <option value="">-- اختر العميل --</option>
                            <?php
                            $clients_query = array();
                            try {
                                $clients_query = $prj_gate->scopedQuery(array('scope' => array('c' => 'clients')),
                                    "SELECT c.id, c.client_code, c.client_name FROM clients c WHERE c.status = 'نشط' AND {TENANT_SCOPE} AND c.is_deleted = 0 ORDER BY c.client_name ASC");
                            } catch (\Throwable $t) { error_log('projects.php clients options: ' . $t->getMessage()); }
                            foreach ($clients_query as $cli) {
                                echo "<option value='" . intval($cli['id']) . "'>[" . e($cli['client_code']) . "] " . e($cli['client_name']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <!-- ══ الكودُ المولَّد (عرضٌ فقط · يُخفى في وضع التعديل) — نظيرُ شاشة العملاء ══ -->
                    <div id="generated_project_code_wrapper" class="auto">
                        <label for="generated_project_code"><i class="fas fa-magic"></i> كود المشروع المولد <i
                                class="fas fa-info-circle clients-info-icon"></i></label>
                        <input type="text" id="generated_project_code" class="generated-code-field"
                            value="<?php echo e($next_project_code); ?>" readonly tabindex="-1"
                            title="هذا الكود للعرض فقط، يمكنك نسخه واستخدامه في حقل كود المشروع" />
                    </div>
                    <div>
                        <label for="mine_code"><i class="fas fa-barcode"></i> كود المشروع</label>
                        <!-- مكتوبٌ سلفًا بالكود المولَّد **وقابلٌ للتعديل** (نظيرُ كود العميل):
                             أكثرُ الحالات تقبله كما هو، ومَن أراد كودَه الخاصّ كتبه فوقه. -->
                        <input type="text" name="project_code" placeholder="كود المشروع" id="project_code"
                            value="<?php echo e($next_project_code); ?>" />
                    </div>
                    <div>
                        <label><i class="fas fa-mountain"></i> كود المنجم</label>
                        <!-- مكتوبٌ سلفًا بالكود المولَّد **وقابلٌ للتعديل** (نظيرُ كود العميل):
                             أكثرُ الحالات تقبله كما هو، ومَن أراد كودَه الخاصّ كتبه فوقه. -->
                        <input type="text" name="project_code" placeholder="كود المشروع" id="project_code"
                            value="<?php echo e($next_project_code); ?>" />
                    </div>
                    <div>
                        <label><i class="fas fa-mountain"></i> كود المنجم</label>
                        <input type="text" name="mine_code" placeholder="كود المنجم" id="mine_code" />
                    </div>
                    <div>
                        <label for="project_name"><i class="fas fa-file-signature"></i> اسم المشروع</label>
                        <input type="text" name="project_name" id="project_name" placeholder="أدخل اسم المشروع"
                            required />
                    </div>
                    <div>
                        <label for="project_location"><i class="fas fa-map-marker-alt"></i> موقع المشروع</label>
                        <input type="text" name="location" placeholder="أدخل موقع المشروع" id="project_location" />
                        <input type="hidden" name="total" value="0" />
                    </div>
                    <div>
                        <label for="project_category"><i class="fas fa-layer-group"></i> الفئة</label>
                        <input type="text" name="category" placeholder="الفئة" id="project_category" />
                    </div>
                    <div>
                        <label for="project_sub_sector"><i class="fas fa-industry"></i> القطاع الفرعي</label>
                        <input type="text" name="sub_sector" placeholder="القطاع الفرعي" id="project_sub_sector" />
                    </div>
                    <div>
                        <label for="project_state"><i class="fas fa-map-marked-alt"></i> الولاية</label>
                        <input type="text" name="state" placeholder="الولاية" id="project_state" />
                    </div>
                    <div>
                        <label for="project_region"><i class="fas fa-map-pin"></i> المنطقة</label>
                        <input type="text" name="region" placeholder="المنطقة" id="project_region" />
                    </div>
                    <div>
                        <label for="project_nearest_market"><i class="fas fa-store"></i> أقرب سوق</label>
                        <input type="text" name="nearest_market" placeholder="أقرب سوق" id="project_nearest_market" />
                    </div>
                    <div>
                        <label for="project_latitude"><i class="fas fa-map-marker"></i> خط العرض</label>
                        <input type="text" name="latitude" placeholder="خط العرض" id="project_latitude" />
                    </div>
                    <div>
                        <label for="project_longitude"><i class="fas fa-map-marker"></i> خط الطول</label>
                        <input type="text" name="longitude" placeholder="خط الطول" id="project_longitude" />
                    </div>
                    <div>
                        <label for="project_status"><i class="fas fa-toggle-on"></i> حالة المشروع</label>
                        <select name="status" id="project_status" required>
                            <option value="">-- اختر الحالة --</option>
                            <option value="1">✅ نشط</option>
                            <option value="0">❌ غير نشط</option>
                        </select>
                    </div>
                </div>
                <div class="pu-form-actions">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-save"></i> <span id="submitBtnText">حفظ المشروع</span>
                    </button>
                    <button type="button" id="projectFormCancelBtn" class="btn-secondary">
                        <i class="fas fa-times"></i> إلغاء
                    </button>
                </div>
            </div>
        </div>
    </form>

    <!-- جدول المشاريع -->
    <div class="card">
        <div class="card-body">
            <div class="table-container">
                <table id="projectsTable" class="display projects-table-nowrap projects-w100">
                    <thead>
                        <tr>
                            <th> إجراءات</th>
                            <th> اسم المشروع</th>
                            <th> تاريخ الإضافة</th>
                            <th> العميل</th>
                            <th> كود المشروع</th>
                            <th> كود المنجم</th>
                            <th> عدد الموردين</th>
                            <th> الحالة</th>
                            <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
                            <th class="ems-fn-th" data-fn="1">العقود المرتبطة</th>
                            <th class="ems-fn-th" data-fn="1">تاريخ البدء</th>
                            <th class="ems-fn-th" data-fn="1">تاريخ الانتهاء المخطط</th>
                            <th class="ems-fn-th" data-fn="1">المواقع</th>
                            <th class="ems-fn-th" data-fn="1">مدير المشروع</th>
                            <th class="ems-fn-th" data-fn="1">القيمة التعاقدية</th>
                            <th class="ems-fn-th" data-fn="1">نسبة الإنجاز</th>
                            <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
                            <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صف بلا كيان مالك">الكيان</th>
                            <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمد — تفويض أو سلطة أصلية">مرجع التفويض</th>
                            <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
                            <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
                            <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
                            <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المنشئ — الاسم والصفة</th>
                            <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمد — الاسم والصفة</th>
                            <th class="ems-gov-th none" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
                            <th class="ems-gov-th none" data-gov="cost_center" data-slice="3" title="وجهة التحميل">مركز التكلفة</th>
                            <th class="ems-gov-th none" data-gov="fx_rate_source" data-slice="3" title="ما خالف عملة الدفاتر يحمل السعر ومصدره">سعر الصرف ومصدره</th>
                            <th class="ems-gov-th none" data-gov="currency" data-slice="3" title="لا مبلغ بلا عملة">العملة</th>
                            </tr>
                    </thead>
                    <tbody>
                        <?php
                        // جلب جميع المشاريع من جدول project
                        // (عدّادا العقود والموردين كانا استعلامين مرتبطين لكل صف؛ INNER JOIN داخل
                        // استعلامٍ مرتبط لا يجوز إثراءً في البوابة — أُعيدت هيكلتهما استعلامين
                        // مجمَّعين معزولين ثم دمجًا في PHP بنفس القيم حرفيًا.)
                        $prj_list_filter = '';
                        $prj_list_params = array();
                        if (isset($_GET['client_id']) && is_numeric($_GET['client_id'])) {
                            $client_id = intval($_GET['client_id']);
                            $prj_list_filter = " AND op.`$project_client_column` = ?";
                            $prj_list_params[] = $client_id;
                        }

                        $result = array();
                        $prj_cmap = array();
                        $prj_smap = array();
                        try {
                            $result = $prj_gate->scopedQuery(array('scope' => array('op' => 'project'), 'enrich' => array('cc' => 'clients')),
                                "SELECT op.`id`, op.`name`, op.`client`, op.`location`, op.`total`, op.`status`, op.`create_at`,
                      op.`project_code`, op.`mine_code`, op.`category`, op.`sub_sector`, op.`state`, op.`region`,
                                            op.`nearest_market`, op.`latitude`, op.`longitude`, op.`$project_client_column` AS `client_id`,
                      cc.`client_name`
                      FROM project op
                          LEFT JOIN clients cc ON op.$project_client_column = cc.id
                      WHERE {TENANT_SCOPE} AND op.is_deleted = 0$prj_list_filter
                      ORDER BY op.id DESC", $prj_list_params);
                            $prj_crows = $prj_gate->scopedQuery(array('scope' => array('c' => 'contracts')),
                                "SELECT c.project_id AS pid, COUNT(*) AS n FROM contracts c WHERE c.status = 1 AND {TENANT_SCOPE} GROUP BY c.project_id");
                            foreach ($prj_crows as $prj_cr) { $prj_cmap[intval($prj_cr['pid'])] = intval($prj_cr['n']); }
                            $prj_srows = $prj_gate->scopedQuery(array('scope' => array('pm' => 'equipments', 'm' => 'operations')),
                                "SELECT m.project_id AS pid, COUNT(DISTINCT pm.suppliers) AS n
                          FROM equipments pm
                          JOIN operations m ON pm.id = m.equipment
                          WHERE 1=1 AND {TENANT_SCOPE} GROUP BY m.project_id");
                            foreach ($prj_srows as $prj_sr) { $prj_smap[intval($prj_sr['pid'])] = intval($prj_sr['n']); }
                        } catch (\Throwable $t) { error_log('projects.php list: ' . $t->getMessage()); }
                        if ($result) {
                            foreach ($result as $row) {
                                $row['contracts'] = isset($prj_cmap[intval($row['id'])]) ? $prj_cmap[intval($row['id'])] : 0;
                                $row['total_suppliers'] = isset($prj_smap[intval($row['id'])]) ? $prj_smap[intval($row['id'])] : 0;
                                $project_name_cell = "<a class='client-name-link' href='project_profile.php?id=" . intval($row['id']) . "'><strong>" . e($row['name']) . "</strong></a>";
                                // تنبيه: المشروع ليس لديه عقد ساري (status = 1) — بنفس نمط تنبيه العملاء بلا مشاريع
                                //
                                // ⚠️ والمشروعُ **الموقوف** لا تنبيهَ له (طلبُ المالك): «بلا عقدٍ ساري»
                                // ملاحظةٌ تستدعي عملًا — ومشروعٌ أُوقف عمدًا لا يُنتظر منه عقد، فالتنبيهُ
                                // عليه ضجيجٌ يُغرق التنبيهاتِ الحقيقية. الشرطُ على المشروع لا على العقد.
                                if (intval($row['contracts']) === 0 && intval($row['status']) === 1) {
                                    $project_name_cell .= " <span class='link-alert-chip' title='المشروع ليس لديه عقد ساري'><i class='fas fa-exclamation-triangle'></i>تنبيه</span>";
                                }

                                echo "<tr>";
                                echo "<td>
                            <div class='action-btns'>
                                <a href='javascript:void(0)'
                                   class='action-btn view viewBtn'
                                   data-id='" . $row['id'] . "'
                                   data-client-id='" . intval(isset($row['client_id']) ? $row['client_id'] : 0) . "'
                                   data-project-name='" . htmlspecialchars($row['name']) . "'
                                   data-client-name='" . htmlspecialchars(isset($row['client_name']) && $row['client_name'] !== '' ? $row['client_name'] : $row['client']) . "'
                                   data-location='" . htmlspecialchars($row['location']) . "'
                                   data-project-code='" . htmlspecialchars(isset($row['project_code']) ? $row['project_code'] : '') . "'
                                   data-mine-code='" . htmlspecialchars(isset($row['mine_code']) ? $row['mine_code'] : '') . "'
                                   data-category='" . htmlspecialchars(isset($row['category']) ? $row['category'] : '') . "'
                                   data-sub-sector='" . htmlspecialchars(isset($row['sub_sector']) ? $row['sub_sector'] : '') . "'
                                   data-state='" . htmlspecialchars(isset($row['state']) ? $row['state'] : '') . "'
                                   data-region='" . htmlspecialchars(isset($row['region']) ? $row['region'] : '') . "'
                                   data-nearest-market='" . htmlspecialchars(isset($row['nearest_market']) ? $row['nearest_market'] : '') . "'
                                   data-latitude='" . htmlspecialchars(isset($row['latitude']) ? $row['latitude'] : '') . "'
                                   data-longitude='" . htmlspecialchars(isset($row['longitude']) ? $row['longitude'] : '') . "'
                                   data-status='" . $row['status'] . "'
                                   data-contracts='" . $row['contracts'] . "'
                                   data-suppliers='" . $row['total_suppliers'] . "'
                                   title='عرض التفاصيل'>
                                   <i class='fas fa-eye'></i>
                                          </a>";

                                if ($can_edit) {
                                    echo "<a href='javascript:void(0)'
                                                    class='action-btn edit editBtn'
                                                    data-id='" . intval($row['id']) . "'
                                                    data-client-id='" . intval(isset($row['client_id']) ? $row['client_id'] : 0) . "'
                                                    data-project-name='" . htmlspecialchars($row['name']) . "'
                                                    data-location='" . htmlspecialchars($row['location']) . "'
                                                    data-project-code='" . htmlspecialchars(isset($row['project_code']) ? $row['project_code'] : '') . "'
                                                    data-mine-code='" . htmlspecialchars(isset($row['mine_code']) ? $row['mine_code'] : '') . "'
                                                    data-category='" . htmlspecialchars(isset($row['category']) ? $row['category'] : '') . "'
                                                    data-sub-sector='" . htmlspecialchars(isset($row['sub_sector']) ? $row['sub_sector'] : '') . "'
                                                    data-state='" . htmlspecialchars(isset($row['state']) ? $row['state'] : '') . "'
                                                    data-region='" . htmlspecialchars(isset($row['region']) ? $row['region'] : '') . "'
                                                    data-nearest-market='" . htmlspecialchars(isset($row['nearest_market']) ? $row['nearest_market'] : '') . "'
                                                    data-latitude='" . htmlspecialchars(isset($row['latitude']) ? $row['latitude'] : '') . "'
                                                    data-longitude='" . htmlspecialchars(isset($row['longitude']) ? $row['longitude'] : '') . "'
                                                    data-status='" . htmlspecialchars($row['status']) . "'
                                                    title='تعديل'>
                                                    <i class='fas fa-edit'></i>
                                                </a>";
                                }

                                if ($can_delete) {
                                    echo "<a href='projects.php?delete_id=" . intval($row['id']) . "&csrf_token=" . urlencode(generate_csrf_token()) . "'
                                                    class='action-btn delete'
                                                    onclick='return confirm(\"هل أنت متأكد من حذف هذا المشروع؟\")'
                                                    title='حذف'>
                                                    <i class='fas fa-trash-alt'></i>
                                                </a>";
                                }

                                // العقودات — زرّ إجراء مدمج من عمود «عقود المشروع» (مع عدد العقود السارية)
                                echo "<a href='../Contracts/contracts.php?filter_project_id=" . intval($row['id']) . "'
                                                class='action-btn view projectContractsBtn'
                                                title='عقودات المشروع'>
                                                <i class='fas fa-file-contract'></i><span class='projects-contract-count'>" . intval($row['contracts']) . "</span>
                                            </a>";

                                echo "</div>
                      </td>";
                                echo "<td>" . $project_name_cell . "</td>";
                                echo "<td>" . e($row['create_at']) . "</td>";
                                echo "<td>" . e(isset($row['client_name']) && $row['client_name'] !== '' ? $row['client_name'] : $row['client']) . "</td>";
                                echo "<td>" . e(isset($row['project_code']) && $row['project_code'] !== '' ? $row['project_code'] : '-') . "</td>";
                                echo "<td>" . e(isset($row['mine_code']) && $row['mine_code'] !== '' ? $row['mine_code'] : '-') . "</td>";
                                echo "<td><span class='action-btn'>" . intval($row['total_suppliers']) . "</span></td>";
                                if ($row['status'] == "1") {
                                    echo "<td><span class='status-active'><i class='fa-regular fa-circle-check'></i> نشط</span></td>";
                                } else {
                                    echo "<td><span class='status-inactive'><i class='fas fa-times-circle'></i> غير نشط</span></td>";
                                }

                                echo "</tr>";
                            }
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- نافذة عرض تفاصيل المشروع تُولَّد ديناميكياً عبر النظام الموحّد EmsDetailsModal (assets/js/ems-details-modal.js) -->

<!-- jQuery -->
<script src="/ems/assets/vendor/jquery-3.7.1.min.js"></script>
<!-- Bootstrap JS (Bundle includes Popper) -->
<script src="/ems/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<!-- DataTables JS -->
<script src="/ems/assets/vendor/datatables/js/jquery.dataTables.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/dataTables.buttons.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/buttons.html5.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/buttons.print.min.js"></script>
<script src="/ems/assets/vendor/jszip/jszip.min.js"></script>
<script src="/ems/assets/vendor/pdfmake/pdfmake.min.js"></script>
<script src="/ems/assets/vendor/pdfmake/vfs_fonts.js"></script>

<script>
    // إغلاق نافذة العرض الموحّدة (متوافق مع الاستدعاءات القديمة)
    function closeViewModal() {
        if (window.EmsDetailsModal) EmsDetailsModal.close();
    }

    (function () {
        // تهيئةُ جدولِ المشاريع انتقلت إلى المكوّنِ المركزي
        // (assets/js/ui-unification.js — initializeMissingDataTables):
        // لغةٌ عربية وتمريرٌ أفقيٌّ وزرُّ إكسل موحَّد — لا تهيئةَ محليةً هنا.

        // اظهار/اخفاء الفورم
        const toggleProjectFormBtn = document.getElementById('toggleForm');
        const projectForm = document.getElementById('projectForm');
        const projectFormCancelBtn = document.getElementById('projectFormCancelBtn');
        const projectFormTitle = document.getElementById('formTitle');
        const projectSubmitBtnText = document.getElementById('submitBtnText');
        const statsToggleBtn = $('#toggleStats');
        const statsSection = $('#projectsStatsSection');

        /**
         * إظهارُ حقل «كود المشروع المولد» وإخفاؤه.
         *
         * ⚠️ **لا تستعمل `jQuery.hide()`/`style.display='none'` هنا** —
         * `assets/css/ems-forms.css` يحمل:
         *     :is(.allforms, .ems-form) .form-grid > div { display: block !important }
         * والحقلُ ابنٌ مباشرٌ لـ`.form-grid`، فـ`!important` من ورقة الأنماط تهزم
         * الإخفاءَ السطريَّ **بلا أولوية**: السمةُ تُكتب فعلًا والحقلُ يبقى ظاهرًا،
         * بلا خطأٍ في وحدة التحكم ولا سطرٍ في أي سجل. (وقعت في شاشة العملاء.)
         */
        function setGeneratedProjectCodeShown(shown) {
            var el = document.getElementById('generated_project_code_wrapper');
            if (!el) { return; }
            if (shown) { el.style.removeProperty('display'); }
            else       { el.style.setProperty('display', 'none', 'important'); }
        }

        function setProjectFormAddMode() {
            if (projectFormTitle) {
                projectFormTitle.textContent = 'إضافة مشروع جديد';
            }
            if (projectSubmitBtnText) {
                projectSubmitBtnText.textContent = 'حفظ المشروع';
            }
            setGeneratedProjectCodeShown(true);
            // الكودُ المولَّد يعود إلى خانته كلَّما دخلنا وضعَ الإضافة — ومصدرُه حقلُ
            // العرض نفسُه لا نسخةٌ ثانيةٌ منه (مصدرُ حقيقةٍ واحد). وهذه الشاشةُ تُفرّغ
            // حقولَها يدويًّا لا بـ`reset()`، فالتعبئةُ هنا لا في السمة وحدها.
            var gen = document.getElementById('generated_project_code');
            if (gen && gen.value) { $("#project_code").val(gen.value); }
        }

        function setProjectFormEditMode() {
            if (projectFormTitle) {
                projectFormTitle.textContent = 'تعديل المشروع';
            }
            if (projectSubmitBtnText) {
                projectSubmitBtnText.textContent = 'تحديث المشروع';
            }
            setGeneratedProjectCodeShown(false);
        }

        function resetProjectForm() {
            $("#project_id").val("");
            $("#project_name").val("");
            $("#client_id").val("");
            $("#project_location").val("");
            $("#project_code").val("");
            $("#mine_code").val("");
            $("#project_category").val("");
            $("#project_sub_sector").val("");
            $("#project_state").val("");
            $("#project_region").val("");
            $("#project_nearest_market").val("");
            $("#project_latitude").val("");
            $("#project_longitude").val("");
            $("#project_status").val("");
            setProjectFormAddMode();
        }

        if (toggleProjectFormBtn) {
            toggleProjectFormBtn.addEventListener('click', function () {
                if (!projectForm) {
                    return;
                }

                const $projectForm = $('#projectForm');
                if ($projectForm.hasClass('allforms-visible')) {
                    $projectForm.removeClass('allforms-visible');
                    resetProjectForm();
                } else {
                    resetProjectForm();
                    $projectForm.addClass('allforms-visible');
                }
            });
        }

        if (projectFormCancelBtn) {
            projectFormCancelBtn.addEventListener('click', function () {
                const $projectForm = $('#projectForm');
                if (!$projectForm.hasClass('allforms-visible')) {
                    return;
                }

                $projectForm.removeClass('allforms-visible');
                resetProjectForm();
            });
        }

        function updateStatsToggleState(isVisible) {
            if (!statsToggleBtn.length) {
                return;
            }

            const icon = statsToggleBtn.find('i').first();
            const text = statsToggleBtn.find('.projects-toggle-stats-text');

            if (isVisible) {
                icon.removeClass('fa-eye').addClass('fa-eye-slash');
                text.text('إخفاء الإحصائيات');
            } else {
                icon.removeClass('fa-eye-slash').addClass('fa-eye');
                text.text('إظهار الإحصائيات');
            }
        }

        updateStatsToggleState(statsSection.is(':visible'));

        statsToggleBtn.on('click', function () {
            if (statsSection.is(':visible')) {
                statsSection.stop(true, true).slideUp(250, function () {
                    statsSection.addClass('projects-hidden');
                    updateStatsToggleState(false);
                });
            } else {
                statsSection.removeClass('projects-hidden').hide();
                statsSection.stop(true, true).slideDown(250, function () {
                    updateStatsToggleState(true);
                });
            }
        });

        setProjectFormAddMode();

        // تعبئة فورم تعديل المشروع من بيانات العرض
        function fillProjectEditForm(d) {
            $("#project_id").val(d.id);
            $("#company_project_id").val(d.companyProjectId);
            $("#client_id").val(d.clientId);
            $("#project_name").val(d.projectName);
            $("#project_location").val(d.location);
            $("#project_code").val(d.projectCode);
            $("#mine_code").val(d.mineCode);
            $("#project_category").val(d.category);
            $("#project_sub_sector").val(d.subSector);
            $("#project_state").val(d.state);
            $("#project_region").val(d.region);
            $("#project_nearest_market").val(d.nearestMarket);
            $("#project_latitude").val(d.latitude);
            $("#project_longitude").val(d.longitude);
            $("#project_status").val(d.status);

            setProjectFormEditMode();
            $("#projectForm").addClass('allforms-visible');
            $("html, body").animate({ scrollTop: $("#projectForm").offset().top }, 500);
        }

        // عرض تفاصيل المشروع عبر النظام الموحّد
        $(document).on("click", ".viewBtn", function () {
            const d = {
                id: $(this).data('id'),
                companyProjectId: $(this).data('company-project-id'),
                clientId: $(this).data('client-id'),
                projectName: $(this).data('project-name'),
                clientName: $(this).data('client-name'),
                location: $(this).data('location'),
                projectCode: $(this).data('project-code'),
                mineCode: $(this).data('mine-code'),
                category: $(this).data('category'),
                subSector: $(this).data('sub-sector'),
                state: $(this).data('state'),
                region: $(this).data('region'),
                nearestMarket: $(this).data('nearest-market'),
                latitude: $(this).data('latitude'),
                longitude: $(this).data('longitude'),
                status: $(this).data('status'),
                contracts: $(this).data('contracts'),
                suppliers: $(this).data('suppliers')
            };

            const isActive = (d.status === '1' || d.status === 1);
            let coordsText = (d.latitude && d.longitude) ? (d.latitude + ' / ' + d.longitude) : '-';

            const actions = [];
            actions.push({
                label: 'عقود المشروع', icon: 'fas fa-file-contract', variant: 'primary',
                onClick: function () { window.location.href = '../Contracts/contracts.php?filter_project_id=' + d.id; }
            });
            <?php if ($can_edit): ?>
                actions.push({
                    label: 'تعديل المشروع', icon: 'fas fa-edit', variant: 'primary',
                    onClick: function () { EmsDetailsModal.close(); fillProjectEditForm(d); }
                });
            <?php endif; ?>
            actions.push({ label: 'إغلاق', icon: 'fas fa-times', variant: 'secondary', close: true });

            EmsDetailsModal.open({
                title: 'تفاصيل المشروع',
                icon: 'fas fa-project-diagram',
                fields: [
                    { label: 'العميل', value: d.clientName, icon: 'fas fa-user-tie', size: 'lg' },
                    { label: 'كود المشروع', value: d.projectCode, icon: 'fas fa-barcode' },
                    { label: 'كود المنجم', value: d.mineCode, icon: 'fas fa-mountain' },
                    { label: 'اسم المشروع', value: d.projectName, icon: 'fas fa-project-diagram', size: 'lg' },
                    { label: 'الفئة', value: d.category, icon: 'fas fa-layer-group' },
                    { label: 'القطاع الفرعي', value: d.subSector, icon: 'fas fa-industry' },
                    { label: 'الولاية', value: d.state, icon: 'fas fa-map-marked-alt' },
                    { label: 'المنطقة', value: d.region, icon: 'fas fa-map-pin' },
                    { label: 'موقع المشروع', value: d.location, icon: 'fas fa-map-marker-alt', size: 'lg' },
                    { label: 'أقرب سوق', value: d.nearestMarket, icon: 'fas fa-store' },
                    { label: 'الإحداثيات (خط العرض / خط الطول)', value: coordsText, icon: 'fas fa-map-marker', size: 'lg' },
                    { label: 'عدد العقود', value: d.contracts || '0', icon: 'fas fa-file-contract' },
                    { label: 'عدد الموردين', value: d.suppliers || '0', icon: 'fas fa-truck' },
                    { label: 'حالة المشروع', value: isActive ? 'نشط' : 'غير نشط', icon: 'fas fa-toggle-on', type: 'status', tone: isActive ? 'active' : 'inactive' }
                ],
                actions: actions
            });
        });

        // عند الضغط على زر تعديل من الجدول
        $(document).on("click", ".editBtn:not(#viewEditBtn)", function () {
            $("#project_id").val($(this).data("id"));
            $("#project_name").val($(this).data("project-name"));
            $("#client_id").val($(this).data("client-id"));
            $("#project_location").val($(this).data("location"));
            $("#project_code").val($(this).data("project-code"));
            $("#mine_code").val($(this).data("mine-code"));
            $("#project_category").val($(this).data("category"));
            $("#project_sub_sector").val($(this).data("sub-sector"));
            $("#project_state").val($(this).data("state"));
            $("#project_region").val($(this).data("region"));
            $("#project_nearest_market").val($(this).data("nearest-market"));
            $("#project_latitude").val($(this).data("latitude"));
            $("#project_longitude").val($(this).data("longitude"));
            $("#project_status").val($(this).data("status"));

            setProjectFormEditMode();

            $("#projectForm").addClass('allforms-visible');
            $("html, body").animate({ scrollTop: $("#projectForm").offset().top }, 500);
        });

        // عند تمرير رقم العميل قي ال url
        $(document).ready(function () {
            // إذا تم تمرير client_id في الرابط، افتح الفورم تلقائيًا
            const urlParams = new URLSearchParams(window.location.search);
            const clientId = urlParams.get('client_id');

            if (clientId) {
                setProjectFormAddMode();
                $('#projectForm').addClass('allforms-visible');
                $('#client_id').val(clientId);
            }
        });

    })();
</script>

<?php /* نُقلت أنماطُ هذه الشاشةِ إلى assets/css/ems-screens.css (UXUI-01 البند ٦: صفرُ نمطٍ محليّ) */ ?>

<?php if (function_exists('ems_excel_render')) {
    ems_excel_render();
} ?>
</body>

</html>
