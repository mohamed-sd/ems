<?php
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}

include '../config.php';
require_once __DIR__ . '/../includes/excel_ui.php'; // ح-09 · أزرار Excel الموحّدة
include '../includes/permissions_helper.php';

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

// ══════════════════════════════════════════════════════════════════════════════
// دوال مساعدة
// ══════════════════════════════════════════════════════════════════════════════
if (!function_exists('act_e')) {
    function act_e($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
if (!function_exists('act_redirect_with_msg')) {
    function act_redirect_with_msg($msg)
    {
        ems_gov_flash_redirect('activities.php', $msg, 'GOV-INFO-200', '');
        exit();
    }
}

// ══════════════════════════════════════════════════════════════════════════════
// التحقق من معرف الشركة (عزل الشركات)
// ══════════════════════════════════════════════════════════════════════════════
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
if ($company_id <= 0) {
    ems_gov_flash_redirect('../main/dashboard.php', 'الحساب غير مرتبط بشركة.', 'GOV-INFO-200', '');
    exit();
}

// العزل عبر بوابة المستأجر (K9 · هجرة 2026-07-15) — النطاق والحذف الناعم مسؤولية البوابة
$act_gate = ems_tenant_db();

// رمز CSRF — [ع-0أ] اعتماد الرمز المركزي بدل رمزٍ محلّيٍّ منفصل (نفس ازدواج
// clients.php: حقلان بنفس الاسم وقيمتين ⇒ الحارس المركزي يفشل). توحيدُ القيمة
// يُبقي الفحص المحلّي فعّالًا ويُمرّر الحارس المركزي أيًّا كان الفائز.
$act_csrf_token = generate_csrf_token();

// القوائم الثابتة
$ACT_TYPES = array('زيارة عميل', 'اجتماع موقع', 'افتراضي', 'هاتفي', 'تفاوضي', 'زيارة مناجم');
$ACT_ENTITY_TYPES = array(
    'client'      => 'عميل',
    'opportunity' => 'فرصة',
    'contract'    => 'عقد',
);

// توليد الكود المقترح التالي (ACT-NNNN) — للعرض فقط
$next_act_code = 'ACT-0001';
$last_code_sql = "SELECT activity_code FROM activities
                  WHERE activity_code REGEXP '^ACT-[0-9]+$' AND company_id = $company_id AND is_deleted = 0
                  ORDER BY CAST(SUBSTRING(activity_code, 5) AS UNSIGNED) DESC LIMIT 1";
try {
    $last_code_rows = $act_gate->scopedQuery(array(
        'scope' => array('a' => 'activities'),
    ), "SELECT a.activity_code FROM activities a
        WHERE {TENANT_SCOPE} AND a.activity_code REGEXP '^ACT-[0-9]+$' AND a.is_deleted = 0
        ORDER BY CAST(SUBSTRING(a.activity_code, 5) AS UNSIGNED) DESC LIMIT 1");
} catch (\Throwable $t) {
    $last_code_rows = array();
}
if (!empty($last_code_rows)) {
    $last_num = intval(substr($last_code_rows[0]['activity_code'], 4));
    $next_act_code = 'ACT-' . str_pad($last_num + 1, 4, '0', STR_PAD_LEFT);
}

// صلاحيات المستخدم على وحدة الأنشطة (modules جدول عام — قراءة عبر البوابة)
try {
    $module_info = $act_gate->selectOne('modules', array(
        'columns' => array('id'),
        'where'   => array('code' => 'Clients/activities.php'),
    ));
} catch (\Throwable $t) {
    $module_info = null;
}
$module_id = $module_info ? $module_info['id'] : null;

$can_view = false;
$can_add = false;
$can_edit = false;
$can_delete = false;
if ($module_id) {
    $perms = get_module_permissions($conn, $module_id);
    $can_view   = $perms['can_view'];
    $can_add    = $perms['can_add'];
    $can_edit   = $perms['can_edit'];
    $can_delete = $perms['can_delete'];
}
if (!$can_view) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض الأنشطة ❌', 'GOV-PERM-403', '');
    exit();
}

// ══════════════════════════════════════════════════════════════════════════════
// معالجة إضافة / تعديل نشاط عبر POST
// ══════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['activity_type'])) {
    $posted_csrf = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if (empty($posted_csrf) || !hash_equals($act_csrf_token, $posted_csrf)) {
        act_redirect_with_msg('جلسة النموذج غير صالحة، يرجى إعادة المحاولة ❌');
    }

    $act_id     = isset($_POST['act_id']) ? intval($_POST['act_id']) : 0;
    $is_editing = $act_id > 0;

    if ($is_editing && !$can_edit) {
        act_redirect_with_msg('لا توجد صلاحية تعديل الأنشطة ❌');
    } elseif (!$is_editing && !$can_add) {
        act_redirect_with_msg('لا توجد صلاحية إضافة أنشطة جديدة ❌');
    }

    // الكود
    $act_code_raw = isset($_POST['activity_code']) ? trim($_POST['activity_code']) : '';
    if ($act_code_raw === '' || !preg_match('/^[A-Za-z0-9_\-]+$/', $act_code_raw)) {
        act_redirect_with_msg('كود النشاط غير صالح. استخدم أحرفًا وأرقامًا و - أو _ فقط ❌');
    }

    // التحقق من القوائم الثابتة
    $type_raw = isset($_POST['activity_type']) ? trim($_POST['activity_type']) : '';
    if (!in_array($type_raw, $ACT_TYPES, true)) {
        act_redirect_with_msg('نوع النشاط غير صالح ❌');
    }
    $entity_type_raw = isset($_POST['entity_type']) ? trim($_POST['entity_type']) : 'client';
    if (!isset($ACT_ENTITY_TYPES[$entity_type_raw])) {
        $entity_type_raw = 'client';
    }

    // السجل المرتبط — التحقق من النطاق حسب النوع (إن حُدِّد) عبر البوابة
    $entity_id_in = isset($_POST['entity_id']) ? intval($_POST['entity_id']) : 0;
    if ($entity_id_in > 0) {
        $entity_table = ($entity_type_raw === 'client') ? 'clients'
                      : (($entity_type_raw === 'opportunity') ? 'opportunities' : 'contracts');
        try {
            $chk = $act_gate->selectOne($entity_table, array(
                'columns' => array('id'), 'where' => array('id' => $entity_id_in),
            ));
        } catch (\Throwable $t) { $chk = null; }
        if (!$chk) {
            act_redirect_with_msg('السجل المرتبط غير موجود أو خارج نطاق شركتك ❌');
        }
    }
    $entity_id_val = $entity_id_in > 0 ? $entity_id_in : null;

    // المستخدم المسؤول (إن حُدِّد) ضمن الشركة — الأصل بلا فلتر حذفٍ ناعم
    $assigned_in = isset($_POST['assigned_user_id']) ? intval($_POST['assigned_user_id']) : 0;
    if ($assigned_in > 0) {
        try {
            $uchk = $act_gate->selectOne('users', array(
                'columns' => array('id'), 'where' => array('id' => $assigned_in),
                'includeDeleted' => true,
            ));
        } catch (\Throwable $t) { $uchk = null; }
        if (!$uchk) {
            $assigned_in = 0;
        }
    }
    $assigned_val = $assigned_in > 0 ? $assigned_in : null;

    // القيم تُمرَّر خامًا — البوابة prepared بالكامل (لا escape يدوي)
    $subject_raw = isset($_POST['subject']) ? trim($_POST['subject']) : '';
    $outcome_raw = isset($_POST['outcome']) ? trim($_POST['outcome']) : '';
    $notes_raw   = isset($_POST['notes']) ? trim($_POST['notes']) : '';
    $is_negotiation = (isset($_POST['is_negotiation']) && $_POST['is_negotiation'] == '1') ? 1 : 0;
    $adate_raw = isset($_POST['activity_date']) ? trim($_POST['activity_date']) : '';
    $adate_val = preg_match('/^\d{4}-\d{2}-\d{2}$/', $adate_raw) ? $adate_raw : null;
    $created_by = intval($_SESSION['user']['id']);

    if ($is_editing) {
        try {
            $owner = $act_gate->selectOne('activities', array(
                'columns' => array('id'), 'where' => array('id' => $act_id),
            ));
        } catch (\Throwable $t) { $owner = null; }
        if (!$owner) {
            act_redirect_with_msg('لا يمكنك تعديل نشاط لا يتبع لشركتك ❌');
        }
        try {
            $dup = $act_gate->scopedQuery(array(
                'scope' => array('a' => 'activities'),
            ), "SELECT a.id FROM activities a
                WHERE {TENANT_SCOPE} AND a.activity_code = ? AND a.id != ? AND a.is_deleted = 0",
                array($act_code_raw, $act_id));
        } catch (\Throwable $t) { $dup = array(); }
        if (!empty($dup)) {
            act_redirect_with_msg('كود النشاط موجود مسبقاً داخل شركتك ❌');
        }

        try {
            $act_gate->update('activities', array(
                'activity_code'    => $act_code_raw,
                'activity_type'    => $type_raw,
                'entity_type'      => $entity_type_raw,
                'entity_id'        => $entity_id_val,
                'subject'          => $subject_raw,
                'activity_date'    => $adate_val,
                'assigned_user_id' => $assigned_val,
                'outcome'          => $outcome_raw,
                'is_negotiation'   => $is_negotiation,
                'notes'            => $notes_raw,
            ), array('id' => $act_id), 'is_deleted = 0');
            if (class_exists('\\App\\Services\\ActivityLogService')) {
                \App\Services\ActivityLogService::logUpdate('activities', 'activities', $act_id, null, ['activity_code' => $act_code_raw]);
            }
            act_redirect_with_msg('تم تعديل النشاط بنجاح ✅');
        } catch (\Throwable $t) {
            error_log('activities.php update failed: ' . $t->getMessage());
            act_redirect_with_msg('حدث خطأ أثناء التعديل ❌');
        }
    } else {
        try {
            $dup = $act_gate->scopedQuery(array(
                'scope' => array('a' => 'activities'),
            ), "SELECT a.id FROM activities a
                WHERE {TENANT_SCOPE} AND a.activity_code = ? AND a.is_deleted = 0",
                array($act_code_raw));
        } catch (\Throwable $t) { $dup = array(); }
        if (!empty($dup)) {
            act_redirect_with_msg('كود النشاط موجود مسبقاً داخل شركتك ❌');
        }

        try {
            $new_id = (int) $act_gate->insert('activities', array(
                'activity_code'    => $act_code_raw,
                'activity_type'    => $type_raw,
                'entity_type'      => $entity_type_raw,
                'entity_id'        => $entity_id_val,
                'subject'          => $subject_raw,
                'activity_date'    => $adate_val,
                'assigned_user_id' => $assigned_val,
                'outcome'          => $outcome_raw,
                'is_negotiation'   => $is_negotiation,
                'notes'            => $notes_raw,
                'created_by'       => $created_by,
            ));
            if (class_exists('\\App\\Services\\ActivityLogService')) {
                \App\Services\ActivityLogService::logCreate('activities', 'activities', $new_id, ['activity_code' => $act_code_raw]);
            }
            act_redirect_with_msg('تم إضافة النشاط بنجاح ✅');
        } catch (\Throwable $t) {
            error_log('activities.php insert failed: ' . $t->getMessage());
            act_redirect_with_msg('حدث خطأ أثناء الإضافة ❌');
        }
    }
}

// ══════════════════════════════════════════════════════════════════════════════
// معالجة الحذف الناعم
// ══════════════════════════════════════════════════════════════════════════════
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $delete_csrf = isset($_GET['csrf_token']) ? $_GET['csrf_token'] : '';

    if (!$can_delete) {
        act_redirect_with_msg('لا توجد صلاحية حذف الأنشطة ❌');
    }
    if (empty($delete_csrf) || !hash_equals($act_csrf_token, $delete_csrf)) {
        act_redirect_with_msg('جلسة الحذف غير صالحة، يرجى إعادة المحاولة ❌');
    }
    try {
        $chk = $act_gate->selectOne('activities', array(
            'columns' => array('id'), 'where' => array('id' => $delete_id),
        ));
    } catch (\Throwable $t) { $chk = null; }
    if (!$chk) {
        act_redirect_with_msg('لا يمكنك حذف نشاط لا يتبع لشركتك ❌');
    }
    try {
        $act_gate->softDelete('activities', $delete_id); // يختم deleted_at/deleted_by من السياق
        if (class_exists('\\App\\Services\\ActivityLogService')) {
            \App\Services\ActivityLogService::logDelete('activities', 'activities', $delete_id);
        }
        act_redirect_with_msg('تم حذف النشاط بنجاح ✅');
    } catch (\Throwable $t) {
        error_log('activities.php soft delete failed: ' . $t->getMessage());
        act_redirect_with_msg('حدث خطأ أثناء الحذف ❌');
    }
}

// ══════════════════════════════════════════════════════════════════════════════
// قوائم السجلات المرتبطة (ضمن نطاق الشركة — العزل عبر البوابة)
// ══════════════════════════════════════════════════════════════════════════════
try {
    $clients_options = $act_gate->select('clients', array(
        'columns' => array('id', 'client_code', 'client_name'),
        'orderBy' => 'client_name ASC',
    ));
} catch (\Throwable $t) { $clients_options = array(); }

try {
    $opp_options = $act_gate->select('opportunities', array(
        'columns' => array('id', 'opp_code', 'title'),
        'orderBy' => 'id DESC',
    ));
} catch (\Throwable $t) { $opp_options = array(); }

try {
    $contract_options = $act_gate->scopedQuery(array(
        'scope'  => array('c' => 'contracts'),
        'enrich' => array('p' => 'project'), // اسم المشروع — LEFT بلا تنطيق (سلوك الأصل)
    ), "SELECT c.id, p.name AS project_name FROM contracts c
        LEFT JOIN project p ON p.id = c.project_id
        WHERE {TENANT_SCOPE} AND c.is_deleted = 0 ORDER BY c.id DESC");
} catch (\Throwable $t) { $contract_options = array(); }

$users_options = array();
try {
    // الأصل بلا فلتر حذفٍ ناعم على المستخدمين — includeDeleted يحفظ السلوك حرفيًّا
    $us_rows = $act_gate->select('users', array(
        'columns' => array('id', 'name'),
        'orderBy' => 'name ASC',
        'includeDeleted' => true,
    ));
} catch (\Throwable $t) { $us_rows = array(); }
foreach ($us_rows as $us) { $users_options[intval($us['id'])] = $us['name']; }

// ══════════════════════════════════════════════════════════════════════════════
// جلب الأنشطة + الإحصائيات
// ══════════════════════════════════════════════════════════════════════════════
$rows = array();
$stat_total = 0;
$stat_negotiation = 0;
$stat_month = 0;
$stat_week = 0;
$today = new DateTime('today');

// أسماء السجلات المرتبطة كانت حلقة N+1 بمعرّفٍ فقط (بلا نطاقٍ ولا فلتر حذف) —
// كوفئت بإثراء LEFT JOIN داخل الاستعلام نفسه: نفس الدلالة حرفيًّا وبلا N+1.
try {
    $act_list = $act_gate->scopedQuery(array(
        'scope'  => array('a' => 'activities'),
        'enrich' => array('u' => 'users', 'au' => 'users', 'lc' => 'clients', 'lo' => 'opportunities'),
    ), "SELECT a.*, u.name AS creator_name, au.name AS assigned_name,
               lc.client_name AS linked_client_name, lo.title AS linked_opp_title
        FROM activities a
        LEFT JOIN users u ON u.id = a.created_by
        LEFT JOIN users au ON au.id = a.assigned_user_id
        LEFT JOIN clients lc ON a.entity_type = 'client' AND lc.id = a.entity_id
        LEFT JOIN opportunities lo ON a.entity_type = 'opportunity' AND lo.id = a.entity_id
        WHERE {TENANT_SCOPE} AND a.is_deleted = 0
        ORDER BY a.id DESC");
} catch (\Throwable $t) {
    $act_list = array();
    error_log('activities.php list load: ' . $t->getMessage()); // [م-5]
}
foreach ($act_list as $row) {
    // اسم السجل المرتبط (نفس منطق الأصل: غياب السجل = نص فارغ)
    $linked = '';
    if (!empty($row['entity_id'])) {
        $eid = intval($row['entity_id']);
        if ($row['entity_type'] === 'client') {
            $linked = ($row['linked_client_name'] !== null) ? $row['linked_client_name'] : '';
        } elseif ($row['entity_type'] === 'opportunity') {
            $linked = ($row['linked_opp_title'] !== null) ? $row['linked_opp_title'] : '';
        } else {
            $linked = 'عقد #' . $eid;
        }
    }
    unset($row['linked_client_name'], $row['linked_opp_title']);
    $row['linked_label'] = $linked;
    $rows[] = $row;

    $stat_total++;
    if ((int) $row['is_negotiation'] === 1) $stat_negotiation++;
    if (!empty($row['activity_date'])) {
        $d = DateTime::createFromFormat('Y-m-d', $row['activity_date']);
        if ($d) {
            $diff = (int) $today->diff($d)->format('%r%a');
            if ($d->format('Y-m') === $today->format('Y-m')) $stat_month++;
            if ($diff <= 0 && $diff >= -7) $stat_week++;
        }
    }
}

$page_title = "أنشطة العملاء";
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include("../inheader.php");
include('../insidebar.php');
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }

function act_entity_label($type, $map)
{
    return isset($map[$type]) ? $map[$type] : $type;
}
?>

<div class="main act-main ems-unified-page-shell">

    <?php
    $header_title = 'أنشطة العملاء';
    $header_icon = 'fas fa-handshake';
    $header_actions = array();
    if ($can_add) {
        $header_actions[] = array('id' => 'toggleForm', 'class' => 'add-btn', 'icon' => 'fa fa-solid fa-plus', 'label' => '', 'label_class' => 'act-toggle-form-text');
    } else {
        $header_actions[] = array('tag' => 'button', 'class' => '', 'disabled' => true, 'icon' => 'fas fa-plus-circle', 'label' => 'إضافة (بدون صلاحيات)');
    }
    $header_actions[] = array('id' => 'toggleStats', 'class' => 'btn', 'title' => 'إظهار أو إخفاء الإحصائيات', 'icon' => 'fas fa-eye', 'label' => 'إظهار الإحصائيات', 'label_class' => 'act-toggle-stats-text');
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fa-solid fa-share', 'label' => '');
    // ح-09 · نموذج + تصدير + استيراد (الإطار الموحّد)
    foreach (ems_excel_header_actions('activities', 'الأنشطة التجارية', $can_add) as $__xl) { $header_actions[] = $__xl; }
    include('../includes/page_header.php');
    ?>

    <?php if (!empty($_GET['msg'])):
        $isSuccess = strpos($_GET['msg'], '✅') !== false; ?>
        <div class="success-message <?= $isSuccess ? 'is-success' : 'is-error' ?>">
            <i class="fas <?= $isSuccess ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?php echo act_e($_GET['msg']); ?>
        </div>
    <?php endif; ?>

    <div class="stats-section act-hidden" id="actStatsSection">
        <div class="stats-grid">
            <div class="stats-card">
                <div class="stats-icon"><i class="fas fa-handshake"></i></div>
                <div class="stats-value"><?php echo $stat_total; ?></div>
                <div class="stats-title">إجمالي الأنشطة</div>
            </div>
            <div class="stats-card">
                <div class="stats-icon"><i class="fas fa-comments-dollar"></i></div>
                <div class="stats-value"><?php echo $stat_negotiation; ?></div>
                <div class="stats-title">أنشطة تفاوضية</div>
            </div>
            <div class="stats-card">
                <div class="stats-icon"><i class="fas fa-calendar-days"></i></div>
                <div class="stats-value"><?php echo $stat_month; ?></div>
                <div class="stats-title">هذا الشهر</div>
            </div>
            <div class="stats-card">
                <div class="stats-icon"><i class="fas fa-calendar-week"></i></div>
                <div class="stats-value"><?php echo $stat_week; ?></div>
                <div class="stats-title">خلال 7 أيام قادمة</div>
            </div>
        </div>
    </div>

    <!-- فورم إضافة / تعديل نشاط -->
    <form id="actForm" action="" method="post" class="allforms">
        <div class="card-header">
            <h5><i class="fas fa-edit"></i> <span id="formTitle">إضافة نشاط جديد</span></h5>
        </div>
        <input type="hidden" name="act_id" id="act_id" value="">
        <input type="hidden" name="csrf_token" value="<?php echo act_e($act_csrf_token); ?>">
        <div class="card shadow-sm pu-form-card">
            <div class="card-body">
                <div class="form-grid">
                    <div id="generated_code_wrapper" class="auto">
                        <label for="generated_act_code"><i class="fas fa-magic"></i> كود النشاط المولد <i class="fas fa-info-circle act-info-icon"></i></label>
                        <input type="text" id="generated_act_code" class="generated-code-field" value="<?php echo act_e($next_act_code); ?>" readonly tabindex="-1" title="هذا الكود للعرض فقط، انسخه إلى حقل كود النشاط" />
                        <div class="generated-code-hint"></div>
                    </div>

                    <div>
                        <label for="activity_code"><i class="fas fa-barcode"></i> كود النشاط *</label>
                        <input type="text" name="activity_code" id="activity_code" placeholder="مثال: ACT-001" required pattern="[A-Za-z0-9_\-]+" />
                    </div>
                    <div>
                        <label for="activity_type"><i class="fas fa-list-check"></i> نوع النشاط *</label>
                        <select name="activity_type" id="activity_type" required>
                            <?php foreach ($ACT_TYPES as $t): ?>
                                <option value="<?php echo act_e($t); ?>"><?php echo act_e($t); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="entity_type"><i class="fas fa-link"></i> نوع الارتباط</label>
                        <select name="entity_type" id="entity_type">
                            <?php foreach ($ACT_ENTITY_TYPES as $k => $v): ?>
                                <option value="<?php echo act_e($k); ?>"><?php echo act_e($v); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="entity_id"><i class="fas fa-folder-tree"></i> السجل المرتبط</label>
                        <select name="entity_id" id="entity_id">
                            <option value="">-- بدون / غير محدد --</option>
                        </select>
                    </div>
                    <div>
                        <label for="subject"><i class="fas fa-heading"></i> الموضوع</label>
                        <input type="text" name="subject" id="subject" placeholder="موضوع النشاط والمخرجات" />
                    </div>
                    <div>
                        <label for="activity_date"><i class="fas fa-calendar-day"></i> تاريخ النشاط</label>
                        <input type="date" name="activity_date" id="activity_date" />
                    </div>
                    <div>
                        <label for="assigned_user_id"><i class="fas fa-user-check"></i> المسؤول</label>
                        <select name="assigned_user_id" id="assigned_user_id">
                            <option value="">-- غير محدد --</option>
                            <?php foreach ($users_options as $uid => $uname): ?>
                                <option value="<?php echo intval($uid); ?>"><?php echo act_e($uname); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="is_negotiation"><i class="fas fa-comments-dollar"></i> جولة تفاوض؟</label>
                        <select name="is_negotiation" id="is_negotiation">
                            <option value="0">لا</option>
                            <option value="1">نعم</option>
                        </select>
                    </div>
                    <div class="act-col-full">
                        <label for="outcome"><i class="fas fa-clipboard-check"></i> المخرجات / ما اتُّفق عليه</label>
                        <textarea name="outcome" id="outcome" rows="2" placeholder="الحضور وما اتُّفق عليه"></textarea>
                    </div>
                    <div class="act-col-full">
                        <label for="notes"><i class="fas fa-note-sticky"></i> ملاحظات</label>
                        <textarea name="notes" id="notes" rows="2" placeholder="أي ملاحظات إضافية"></textarea>
                    </div>
                </div>
                <div class="pu-form-actions">
                    <button type="submit" class="btn-submit"><i class="fas fa-save"></i> <span id="submitBtnText">حفظ النشاط</span></button>
                    <button type="button" id="actFormCancelBtn" class="btn-cancel"><i class="fas fa-times"></i> إلغاء</button>
                </div>
            </div>
        </div>
    </form>

    <div class="filter">
        <div class="filter-title">
            <span class="filter-title-icon"><i class="fa-solid fa-sliders"></i></span>
            فلاتر البحث
        </div>
        <div class="filter-body">
            <div class="filter-field">
                <label for="filterType"><i class="fa fa-list-check"></i> نوع النشاط</label>
                <select id="filterType" class="form-control">
                    <option value="">-- كل الأنواع --</option>
                </select>
            </div>
            <div class="filter-field">
                <label for="filterEntity"><i class="fa fa-link"></i> نوع الارتباط</label>
                <select id="filterEntity" class="form-control">
                    <option value="">-- الكل --</option>
                </select>
            </div>
            <div class="filter-actions">
                <button type="button" class="btn-ok"><i class="fa fa-search"></i> تطبيق</button>
                <button type="button" class="btn-reset" title="إعادة تعيين"><i class="fa fa-rotate-right"></i></button>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-container">
                <table id="actTable" class="display act-table-nowrap no-datatable">
                    <thead>
                        <tr>
                            <th>إجراءات</th>
                            <th width="90">الكود</th>
                            <th>النوع</th>
                            <th>الموضوع</th>
                            <th>الارتباط</th>
                            <th>السجل المرتبط</th>
                            <th>التاريخ</th>
                            <th>المسؤول</th>
                            <!-- E-03 موجة ٤: النواة الحاكمة (gov_columns) — الخلايا يحشوها ui-unification.js -->
                            <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
                            <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
                            <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
                            <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
                            <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
                            <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
                            <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
                            <th class="ems-gov-th" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
                            </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row):
                            $ent_label = act_entity_label($row['entity_type'], $ACT_ENTITY_TYPES);
                            $assigned_label = $row['assigned_name'] !== null ? $row['assigned_name'] : '';
                            $created_label = function_exists('ems_actor_label') ? ems_actor_label($conn, intval($row['created_by'])) : ($row['creator_name'] ?? '');
                            ?>
                            <tr>
                                <td>
                                    <div class="action-btns">
                                        <a href="javascript:void(0)" class="action-btn view viewActBtn"
                                            data-id="<?php echo intval($row['id']); ?>"
                                            data-code="<?php echo act_e($row['activity_code']); ?>"
                                            data-type="<?php echo act_e($row['activity_type']); ?>"
                                            data-entity-type="<?php echo act_e($row['entity_type']); ?>"
                                            data-entity-label="<?php echo act_e($ent_label); ?>"
                                            data-linked="<?php echo act_e($row['linked_label']); ?>"
                                            data-subject="<?php echo act_e($row['subject']); ?>"
                                            data-date="<?php echo act_e($row['activity_date']); ?>"
                                            data-assigned="<?php echo act_e($assigned_label); ?>"
                                            data-negotiation="<?php echo intval($row['is_negotiation']); ?>"
                                            data-outcome="<?php echo act_e($row['outcome']); ?>"
                                            data-notes="<?php echo act_e($row['notes']); ?>"
                                            data-created="<?php echo act_e($created_label); ?>"
                                            title="عرض التفاصيل"><i class="fas fa-eye"></i></a>
                                        <?php if ($can_edit): ?>
                                            <a href="javascript:void(0)" class="action-btn edit editActBtn"
                                                data-id="<?php echo intval($row['id']); ?>"
                                                data-code="<?php echo act_e($row['activity_code']); ?>"
                                                data-type="<?php echo act_e($row['activity_type']); ?>"
                                                data-entity-type="<?php echo act_e($row['entity_type']); ?>"
                                                data-entity-id="<?php echo intval($row['entity_id']); ?>"
                                                data-subject="<?php echo act_e($row['subject']); ?>"
                                                data-date="<?php echo act_e($row['activity_date']); ?>"
                                                data-assigned-id="<?php echo intval($row['assigned_user_id']); ?>"
                                                data-negotiation="<?php echo intval($row['is_negotiation']); ?>"
                                                data-outcome="<?php echo act_e($row['outcome']); ?>"
                                                data-notes="<?php echo act_e($row['notes']); ?>"
                                                title="تعديل"><i class="fas fa-edit"></i></a>
                                        <?php endif; ?>
                                        <?php if ($can_delete): ?>
                                            <a href="?delete_id=<?php echo urlencode($row['id']); ?>&csrf_token=<?php echo urlencode($act_csrf_token); ?>"
                                                class="action-btn delete"
                                                onclick="return confirm('هل أنت متأكد من حذف هذا النشاط؟')" title="حذف"><i class="fas fa-trash-alt"></i></a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><strong class="act-code-cell"><?php echo act_e($row['activity_code']); ?></strong></td>
                                <td>
                                    <?php echo act_e($row['activity_type']); ?>
                                    <?php if ((int) $row['is_negotiation'] === 1): ?><span class="act-nego-badge">تفاوض</span><?php endif; ?>
                                </td>
                                <td><?php echo $row['subject'] !== '' ? act_e($row['subject']) : '<span class="act-muted">—</span>'; ?></td>
                                <td><?php echo act_e($ent_label); ?></td>
                                <td><?php echo $row['linked_label'] !== '' ? act_e($row['linked_label']) : '<span class="act-muted">—</span>'; ?></td>
                                <td class="act-num"><?php echo $row['activity_date'] !== null ? act_e($row['activity_date']) : '<span class="act-muted">—</span>'; ?></td>
                                <td><?php echo $assigned_label !== '' ? act_e($assigned_label) : '<span class="act-muted">—</span>'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="../includes/js/jquery-3.7.1.main.js"></script>
<script src="/ems/assets/vendor/datatables/js/jquery.dataTables.min.js"></script>

<script>
    // بيانات السجلات المرتبطة (لتبديل القائمة حسب نوع الارتباط)
    const ACT_LINKED = {
        client: [<?php foreach ($clients_options as $cl) { echo '{id:' . intval($cl['id']) . ',label:"' . act_e(addslashes($cl['client_name'])) . ' (' . act_e(addslashes($cl['client_code'])) . ')"},'; } ?>],
        opportunity: [<?php foreach ($opp_options as $op) { echo '{id:' . intval($op['id']) . ',label:"' . act_e(addslashes($op['title'])) . ' (' . act_e(addslashes($op['opp_code'])) . ')"},'; } ?>],
        contract: [<?php foreach ($contract_options as $ct) { echo '{id:' . intval($ct['id']) . ',label:"عقد #' . intval($ct['id']) . ' - ' . act_e(addslashes($ct['project_name'] ?? '')) . '"},'; } ?>]
    };

    function actFillEntityOptions(entityType, selectedId) {
        const sel = $('#entity_id');
        sel.empty().append('<option value="">-- بدون / غير محدد --</option>');
        const list = ACT_LINKED[entityType] || [];
        list.forEach(function (it) {
            sel.append('<option value="' + it.id + '">' + it.label + '</option>');
        });
        if (selectedId) sel.val(String(selectedId));
        if (window.EmsSelect) EmsSelect.refresh();
    }

    $(document).ready(function () {
        const actTable = $('#actTable').DataTable({
            autoWidth: false,
            stateSave: false,
            language: { url: '/ems/assets/i18n/datatables/ar.json' }
        });

        function fillFilterOptions(columnIndex, selectId) {
            const select = $(selectId);
            const values = [];
            actTable.column(columnIndex).data().each(function (value) {
                const text = $('<div>').html(value).text().trim();
                if (text !== '' && values.indexOf(text) === -1) values.push(text);
            });
            values.sort();
            values.forEach(function (val) {
                select.append('<option value="' + val.replace(/"/g, '&quot;') + '">' + val + '</option>');
            });
        }
        fillFilterOptions(2, '#filterType');
        fillFilterOptions(4, '#filterEntity');

        $('#filterType').on('change', function () {
            const value = $.fn.dataTable.util.escapeRegex($(this).val());
            actTable.column(2).search(value ? value : '', true, false).draw();
        });
        $('#filterEntity').on('change', function () {
            const value = $.fn.dataTable.util.escapeRegex($(this).val());
            actTable.column(4).search(value ? '^' + value + '$' : '', true, false).draw();
        });

        $('#entity_type').on('change', function () { actFillEntityOptions($(this).val(), ''); });
        actFillEntityOptions($('#entity_type').val() || 'client', '');
    });

    // ── إظهار/إخفاء الفورم والإحصائيات ──
    const formToggleBtn = $('#toggleForm');
    const actForm = $('#actForm');
    const formTitle = $('#formTitle');
    const submitBtnText = $('#submitBtnText');
    const generatedCodeWrapper = $('#generated_code_wrapper');
    const formCancelBtn = $('#actFormCancelBtn');
    const statsToggleBtn = $('#toggleStats');
    const statsSection = $('#actStatsSection');

    function setAddMode() { formTitle.text('إضافة نشاط جديد'); submitBtnText.text('حفظ النشاط'); generatedCodeWrapper.show(); }
    function setEditMode() { formTitle.text('تعديل النشاط'); submitBtnText.text('تحديث النشاط'); generatedCodeWrapper.hide(); }
    function resetForm() { if (!actForm.length) return; actForm[0].reset(); $('#act_id').val(''); actFillEntityOptions($('#entity_type').val() || 'client', ''); setAddMode(); if (window.EmsSelect) EmsSelect.refresh(); }

    function updateFormToggleState(isOpen) {
        if (!formToggleBtn.length) return;
        formToggleBtn.toggleClass('is-active', isOpen);
        formToggleBtn.attr('aria-expanded', isOpen ? 'true' : 'false');
    }
    function updateStatsToggleState(isVisible) {
        if (!statsToggleBtn.length) return;
        statsToggleBtn.toggleClass('is-active', isVisible);
        statsToggleBtn.find('.act-toggle-stats-text').text('إظهار الإحصائيات');
        const icon = statsToggleBtn.find('i').first();
        icon.toggleClass('fa-chart-pie', isVisible);
        icon.toggleClass('fa-eye', !isVisible);
    }

    setAddMode();
    updateFormToggleState(actForm.is(':visible'));
    updateStatsToggleState(statsSection.is(':visible'));

    formToggleBtn.on('click', function (e) {
        e.preventDefault();
        if (!actForm.length) return;
        if (actForm.is(':visible')) {
            actForm.stop(true, true).slideUp(250, function () { actForm.removeClass('allforms-visible'); resetForm(); updateFormToggleState(false); });
        } else {
            resetForm();
            actForm.addClass('allforms-visible').hide();
            actForm.stop(true, true).slideDown(250, function () { updateFormToggleState(true); });
        }
    });
    formCancelBtn.on('click', function () {
        if (!actForm.length || !actForm.is(':visible')) return;
        actForm.stop(true, true).slideUp(250, function () { actForm.removeClass('allforms-visible'); resetForm(); updateFormToggleState(false); });
    });
    statsToggleBtn.on('click', function (e) {
        e.preventDefault();
        if (!statsSection.length) return;
        if (statsSection.is(':visible')) {
            statsSection.stop(true, true).slideUp(250, function () { statsSection.addClass('act-hidden'); updateStatsToggleState(false); });
        } else {
            statsSection.removeClass('act-hidden').hide();
            statsSection.stop(true, true).slideDown(250, function () { updateStatsToggleState(true); });
        }
    });

    // ── تعبئة الفورم للتعديل ──
    function fillActForm(d) {
        $('#act_id').val(d.id);
        $('#activity_code').val(d.code);
        $('#activity_type').val(d.type || 'زيارة عميل');
        $('#entity_type').val(d.entityType || 'client');
        actFillEntityOptions(d.entityType || 'client', d.entityId || '');
        $('#subject').val(d.subject || '');
        $('#activity_date').val(d.date || '');
        $('#assigned_user_id').val(d.assignedId || '');
        $('#is_negotiation').val(String(d.negotiation || 0));
        $('#outcome').val(d.outcome || '');
        $('#notes').val(d.notes || '');
        if (window.EmsSelect) EmsSelect.refresh();
        setEditMode();
        if (!actForm.is(':visible')) {
            actForm.addClass('allforms-visible').hide();
            actForm.stop(true, true).slideDown(250, function () { updateFormToggleState(true); });
        } else { updateFormToggleState(true); }
        $('html, body').animate({ scrollTop: $('#actForm').offset().top - 100 }, 500);
    }

    $(document).on('click', '.editActBtn', function () {
        fillActForm({
            id: $(this).data('id'), code: $(this).data('code'), type: $(this).data('type'),
            entityType: $(this).data('entity-type'), entityId: $(this).data('entity-id'),
            subject: $(this).data('subject'), date: $(this).data('date'), assignedId: $(this).data('assigned-id'),
            negotiation: $(this).data('negotiation'), outcome: $(this).data('outcome'), notes: $(this).data('notes')
        });
    });

    // ── عرض التفاصيل عبر EmsDetailsModal الموحّد ──
    $(document).on('click', '.viewActBtn', function () {
        const d = $(this).data();
        const isNego = String(d.negotiation) === '1';
        const fields = [
            { label: 'كود النشاط', value: d.code, icon: 'fas fa-barcode' },
            { label: 'نوع النشاط', value: d.type, icon: 'fas fa-list-check' },
            { label: 'جولة تفاوض', value: isNego ? 'نعم' : 'لا', icon: 'fas fa-comments-dollar', type: 'status', tone: isNego ? 'active' : 'inactive' },
            { label: 'نوع الارتباط', value: d.entityLabel || '—', icon: 'fas fa-link' },
            { label: 'السجل المرتبط', value: d.linked || '—', icon: 'fas fa-folder-tree', size: 'lg' },
            { label: 'الموضوع', value: d.subject || '—', icon: 'fas fa-heading', size: 'lg' },
            { label: 'تاريخ النشاط', value: d.date || '—', icon: 'fas fa-calendar-day' },
            { label: 'المسؤول', value: d.assigned || '—', icon: 'fas fa-user-check' },
            { label: 'المخرجات', value: d.outcome || '—', icon: 'fas fa-clipboard-check', size: 'lg' },
            { label: 'ملاحظات', value: d.notes || '—', icon: 'fas fa-note-sticky', size: 'lg' },
            { label: 'أضيف بواسطة', value: d.created || '—', icon: 'fas fa-user-plus' }
        ];

        const actions = [];
        <?php if ($can_edit): ?>
            actions.push({ label: 'تعديل النشاط', icon: 'fas fa-edit', variant: 'primary', onClick: function () {
                EmsDetailsModal.close();
                $('.editActBtn[data-id="' + d.id + '"]').trigger('click');
            }});
        <?php endif; ?>
        actions.push({ label: 'إغلاق', icon: 'fas fa-times', variant: 'secondary', close: true });

        EmsDetailsModal.open({ title: 'تفاصيل النشاط', icon: 'fas fa-handshake', fields: fields, actions: actions });
    });
</script>

<style>
    .act-main .stats-grid { display: grid; grid-template-columns: repeat(4, minmax(170px, 1fr)); gap: 12px; }
    .act-main .stats-section {
        border: 1px solid var(--bdr); border-radius: var(--rl);
        background: linear-gradient(180deg, rgba(255,255,255,.95) 0%, var(--s2) 100%);
        box-shadow: var(--sh); padding: 14px; margin-bottom: 14px;
    }
    .act-main .stats-card { background: #eee; border: 1px solid #aaa; border-radius: 35px; padding: 18px; box-shadow: 0 2px 8px rgba(26,18,8,.07); position: relative; overflow: hidden; }
    .act-main .stats-card .stats-icon { width: 55px; height: 55px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.3rem; margin-bottom: 10px; float: left; margin-top: 15px; border: 1px solid #999; background:#fff; color:#000; }
    .act-main .stats-card .stats-title { color: #555; font-size: .92rem; font-weight: 700; margin-top: 5px; line-height: 1.3; }
    .act-main .stats-card .stats-value { color: #222; line-height: 1; font-weight: 900; font-variant-numeric: tabular-nums; margin-top: 10px; font-size: 30px; }
    @media (max-width: 900px) { .act-main .stats-grid { grid-template-columns: repeat(2, minmax(150px,1fr)); } }
    @media (max-width: 560px) { .act-main .stats-grid { grid-template-columns: 1fr; } }

    .act-main .act-hidden { display: none; }
    .act-main .act-col-full { grid-column: 1 / -1; }
    .act-main .table-container { overflow-x: auto; }
    #actTable.act-table-nowrap, #actTable.act-table-nowrap th, #actTable.act-table-nowrap td { white-space: nowrap; }
    #actTable .action-btns { flex-wrap: nowrap; white-space: nowrap; }
    .act-main .act-num { font-variant-numeric: tabular-nums; font-weight: 700; }
    .act-main .act-muted { color: #999; }
    .act-main .act-nego-badge { display:inline-block; margin-inline-start:6px; padding:1px 8px; border-radius:999px; font-size:.72rem; font-weight:800; background:rgba(249,115,22,.14); color:#c2410c; border:1px solid rgba(249,115,22,.3); }
</style>

</body>

</html>

<?php
// ح-09 · نافذةُ معالج الاستيراد وأصولُ الإطار (مرة واحدة)
if (function_exists('ems_excel_render')) { ems_excel_render('activities'); }
