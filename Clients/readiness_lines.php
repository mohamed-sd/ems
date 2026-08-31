<?php
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}

include '../config.php';
include '../includes/permissions_helper.php';
// شريط رحلة الكيان الموحد — UXW-01 8-2 · يُضمُّ هنا لا داخلَ كتلةِ جافاسكربت
require_once __DIR__ . '/../includes/entity_tabs.php';

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
if (!function_exists('rdl_e')) {
    function rdl_e($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
if (!function_exists('rdl_redirect_with_msg')) {
    function rdl_redirect_with_msg($msg)
    {
        ems_gov_flash_redirect('readiness_lines.php', $msg, 'GOV-INFO-200', '');
        exit();
    }
}
// مؤشر جاهزية العقد (§6.6) — يُعاد حسابه من البنود: 0 بنود=لم يبدأ · الكل مجتاز=مجتاز · غير ذلك=جارٍ
if (!function_exists('rdl_recompute_state')) {
    function rdl_recompute_state($gate, $contract_ref)
    {
        $contract_ref = intval($contract_ref);
        if ($contract_ref <= 0) {
            return;
        }
        try {
            $rows = $gate->scopedQuery(array(
                'scope' => array('r' => 'readiness_lines'),
            ), "SELECT COUNT(*) AS total,
                       SUM(CASE WHEN r.state <> 'مجتاز' THEN 1 ELSE 0 END) AS notpass
                FROM readiness_lines r
                WHERE {TENANT_SCOPE} AND r.contract_ref = ? AND r.is_deleted = 0",
                array($contract_ref));
        } catch (\Throwable $t) {
            error_log('readiness_lines.php recompute read: ' . $t->getMessage());
            return;
        }
        $total   = isset($rows[0]['total']) ? intval($rows[0]['total']) : 0;
        $notpass = isset($rows[0]['notpass']) ? intval($rows[0]['notpass']) : 0;
        $state = $total === 0 ? 'لم يبدأ' : ($notpass === 0 ? 'مجتاز' : 'جارٍ');
        try {
            $gate->update('contracts', array('readiness_state' => $state), array('id' => $contract_ref), 'is_deleted = 0');
        } catch (\Throwable $t) {
            error_log('readiness_lines.php recompute update: ' . $t->getMessage());
        }
    }
}

// ══════════════════════════════════════════════════════════════════════════════
// القوائم الثابتة (ENUM عربية — تُخزَّن وتُعرَض كما هي)
// ══════════════════════════════════════════════════════════════════════════════
$RDL_NAMES  = array('جاهزية الأسطول', 'جاهزية الموردين', 'جاهزية القوى', 'جاهزية التمويل', 'جاهزية الصيانة', 'جاهزية الموقع');
$RDL_STATES = array('مجتاز', 'فجوة', 'قيد المعالجة');
// مرجعٌ إرشادي لكل بندٍ (الإدارة المصدر §6.12) — يُقترح في الواجهة، غير إلزامي
$RDL_SOURCE_HINT = array(
    'جاهزية الأسطول'  => 'S03',
    'جاهزية الموردين' => 'S02',
    'جاهزية القوى'    => 'S04',
    'جاهزية التمويل'  => 'S01',
    'جاهزية الصيانة'  => 'S08',
    'جاهزية الموقع'   => 'الموقع',
);

// ══════════════════════════════════════════════════════════════════════════════
// التحقق من معرف الشركة (عزل الشركات)
// ══════════════════════════════════════════════════════════════════════════════
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
if ($company_id <= 0) {
    ems_gov_flash_redirect('../main/dashboard.php', 'الحساب غير مرتبط بشركة.', 'GOV-INFO-200', '');
    exit();
}

// العزل عبر بوابة المستأجر
$rdl_gate = ems_tenant_db();

// رمز CSRF
// [ع-0أ] اعتماد الرمز المركزي بدل رمزٍ محلّيٍّ منفصل: الحاقنُ المركزي يضع حقل
// csrf_token برمز الجلسة، وهذا كان يطبع حقلًا ثانيًا بالاسم نفسه وقيمةٍ أخرى —
// وPHP يأخذ الأخير فيفشل الحارس المركزي (403). توحيدُ القيمة يُبقي الفحص المحلّي
// أدناه فعّالًا ويُمرّر الحارس المركزي أيًّا كان الفائز.
$rdl_csrf_token = generate_csrf_token();

// توليد الكود المقترح التالي (RDL-NNNN) — للعرض فقط
$next_rdl_code = 'RDL-0001';
try {
    $last_code_rows = $rdl_gate->scopedQuery(array(
        'scope' => array('r' => 'readiness_lines'),
    ), "SELECT r.readiness_code FROM readiness_lines r
        WHERE {TENANT_SCOPE} AND r.readiness_code REGEXP '^RDL-[0-9]+$' AND r.is_deleted = 0
        ORDER BY CAST(SUBSTRING(r.readiness_code, 5) AS UNSIGNED) DESC LIMIT 1");
} catch (\Throwable $t) {
    $last_code_rows = array();
}
if (!empty($last_code_rows)) {
    $last_num = intval(substr($last_code_rows[0]['readiness_code'], 4));
    $next_rdl_code = 'RDL-' . str_pad($last_num + 1, 4, '0', STR_PAD_LEFT);
}

// صلاحيات المستخدم على وحدة فحص الجاهزية
try {
    $module_info = $rdl_gate->selectOne('modules', array(
        'columns' => array('id'),
        'where'   => array('code' => 'Clients/readiness_lines.php'),
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
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض فحص الجاهزية ❌', 'GOV-PERM-403', '');
    exit();
}

// ══════════════════════════════════════════════════════════════════════════════
// معالجة إضافة / تعديل بند جاهزية عبر POST
// ══════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['readiness_code'])) {
    $posted_csrf = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if (empty($posted_csrf) || !hash_equals($rdl_csrf_token, $posted_csrf)) {
        rdl_redirect_with_msg('جلسة النموذج غير صالحة، يرجى إعادة المحاولة ❌');
    }

    $rdl_id     = isset($_POST['rdl_id']) ? intval($_POST['rdl_id']) : 0;
    $is_editing = $rdl_id > 0;

    if ($is_editing && !$can_edit) {
        rdl_redirect_with_msg('لا توجد صلاحية تعديل بنود الجاهزية ❌');
    } elseif (!$is_editing && !$can_add) {
        rdl_redirect_with_msg('لا توجد صلاحية إضافة بنود جاهزية ❌');
    }

    // الكود
    $rdl_code_raw = isset($_POST['readiness_code']) ? trim($_POST['readiness_code']) : '';
    if ($rdl_code_raw === '' || !preg_match('/^[A-Za-z0-9_\-]+$/', $rdl_code_raw)) {
        rdl_redirect_with_msg('كود البند غير صالح. استخدم أحرفا وأرقاما و - أو _ فقط ❌');
    }

    // ENUM — القوائم البيضاء
    $name_raw = isset($_POST['name']) ? trim($_POST['name']) : '';
    if (!in_array($name_raw, $RDL_NAMES, true)) {
        rdl_redirect_with_msg('بند الجاهزية غير صالح ❌');
    }
    $state_raw = isset($_POST['state']) ? trim($_POST['state']) : '';
    if (!in_array($state_raw, $RDL_STATES, true)) {
        rdl_redirect_with_msg('حالة الجاهزية غير صالحة ❌');
    }

    // العقد المرتبط — إلزاميٌّ + تحقق النطاق عبر البوابة
    $contract_in = isset($_POST['contract_ref']) ? intval($_POST['contract_ref']) : 0;
    if ($contract_in <= 0) {
        rdl_redirect_with_msg('العقد المرتبط إلزامي لكل بند جاهزية ❌');
    }
    try {
        $cchk = $rdl_gate->selectOne('contracts', array(
            'columns' => array('id'), 'where' => array('id' => $contract_in),
        ));
    } catch (\Throwable $t) { $cchk = null; }
    if (!$cchk) {
        rdl_redirect_with_msg('العقد المرتبط غير موجود أو خارج نطاق شركتك ❌');
    }
    $contract_val = $contract_in;

    // حقول نصية
    $source_raw    = isset($_POST['source_ref']) ? mb_substr(trim($_POST['source_ref']), 0, 60) : '';
    $required_raw  = isset($_POST['required']) ? mb_substr(trim($_POST['required']), 0, 255) : '';
    $available_raw = isset($_POST['available']) ? mb_substr(trim($_POST['available']), 0, 255) : '';
    $gap_raw       = isset($_POST['gap_note']) ? trim($_POST['gap_note']) : '';
    $created_by    = intval($_SESSION['user']['id']);

    if ($is_editing) {
        try {
            $owner = $rdl_gate->selectOne('readiness_lines', array(
                'columns' => array('id', 'contract_ref'), 'where' => array('id' => $rdl_id),
            ));
        } catch (\Throwable $t) { $owner = null; }
        if (!$owner) {
            rdl_redirect_with_msg('لا يمكنك تعديل بند لا يتبع لشركتك ❌');
        }
        $old_contract_ref = intval($owner['contract_ref']);
        try {
            $dup = $rdl_gate->scopedQuery(array(
                'scope' => array('r' => 'readiness_lines'),
            ), "SELECT r.id FROM readiness_lines r
                WHERE {TENANT_SCOPE} AND r.readiness_code = ? AND r.id != ? AND r.is_deleted = 0",
                array($rdl_code_raw, $rdl_id));
        } catch (\Throwable $t) { $dup = array(); }
        if (!empty($dup)) {
            rdl_redirect_with_msg('كود البند موجود مسبقا داخل شركتك ❌');
        }

        try {
            $rdl_gate->update('readiness_lines', array(
                'readiness_code' => $rdl_code_raw,
                'contract_ref'   => $contract_val,
                'name'           => $name_raw,
                'source_ref'     => $source_raw,
                'required'       => $required_raw,
                'available'      => $available_raw,
                'state'          => $state_raw,
                'gap_note'       => $gap_raw,
            ), array('id' => $rdl_id), 'is_deleted = 0');
            if (class_exists('\\App\\Services\\ActivityLogService')) {
                \App\Services\ActivityLogService::logUpdate('readiness_lines', 'readiness_lines', $rdl_id, null, ['readiness_code' => $rdl_code_raw]);
            }
            // إعادة حساب مؤشر العقد (القديم والجديد إن تغيّر)
            rdl_recompute_state($rdl_gate, $contract_val);
            if ($old_contract_ref > 0 && $old_contract_ref !== $contract_val) {
                rdl_recompute_state($rdl_gate, $old_contract_ref);
            }
            rdl_redirect_with_msg('تم تعديل بند الجاهزية بنجاح ✅');
        } catch (\Throwable $t) {
            error_log('readiness_lines.php update failed: ' . $t->getMessage());
            rdl_redirect_with_msg('حدث خطأ أثناء التعديل ❌');
        }
    } else {
        try {
            $dup = $rdl_gate->scopedQuery(array(
                'scope' => array('r' => 'readiness_lines'),
            ), "SELECT r.id FROM readiness_lines r
                WHERE {TENANT_SCOPE} AND r.readiness_code = ? AND r.is_deleted = 0",
                array($rdl_code_raw));
        } catch (\Throwable $t) { $dup = array(); }
        if (!empty($dup)) {
            rdl_redirect_with_msg('كود البند موجود مسبقا داخل شركتك ❌');
        }

        try {
            $new_id = (int) $rdl_gate->insert('readiness_lines', array(
                'readiness_code' => $rdl_code_raw,
                'contract_ref'   => $contract_val,
                'name'           => $name_raw,
                'source_ref'     => $source_raw,
                'required'       => $required_raw,
                'available'      => $available_raw,
                'state'          => $state_raw,
                'gap_note'       => $gap_raw,
                'created_by'     => $created_by,
            ));
            if (class_exists('\\App\\Services\\ActivityLogService')) {
                \App\Services\ActivityLogService::logCreate('readiness_lines', 'readiness_lines', $new_id, ['readiness_code' => $rdl_code_raw]);
            }
            rdl_recompute_state($rdl_gate, $contract_val);
            rdl_redirect_with_msg('تم إضافة بند الجاهزية بنجاح ✅');
        } catch (\Throwable $t) {
            error_log('readiness_lines.php insert failed: ' . $t->getMessage());
            rdl_redirect_with_msg('حدث خطأ أثناء الإضافة ❌');
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
        rdl_redirect_with_msg('لا توجد صلاحية حذف بنود الجاهزية ❌');
    }
    if (empty($delete_csrf) || !hash_equals($rdl_csrf_token, $delete_csrf)) {
        rdl_redirect_with_msg('جلسة الحذف غير صالحة، يرجى إعادة المحاولة ❌');
    }
    try {
        $chk = $rdl_gate->selectOne('readiness_lines', array(
            'columns' => array('id', 'contract_ref'), 'where' => array('id' => $delete_id),
        ));
    } catch (\Throwable $t) { $chk = null; }
    if (!$chk) {
        rdl_redirect_with_msg('لا يمكنك حذف بند لا يتبع لشركتك ❌');
    }
    $deleted_contract_ref = intval($chk['contract_ref']);
    try {
        $rdl_gate->softDelete('readiness_lines', $delete_id);
        if (class_exists('\\App\\Services\\ActivityLogService')) {
            \App\Services\ActivityLogService::logDelete('readiness_lines', 'readiness_lines', $delete_id);
        }
        rdl_recompute_state($rdl_gate, $deleted_contract_ref);
        rdl_redirect_with_msg('تم حذف بند الجاهزية بنجاح ✅');
    } catch (\Throwable $t) {
        error_log('readiness_lines.php soft delete failed: ' . $t->getMessage());
        rdl_redirect_with_msg('حدث خطأ أثناء الحذف ❌');
    }
}

// ══════════════════════════════════════════════════════════════════════════════
// قوائم الاختيار (ضمن نطاق الشركة)
// ══════════════════════════════════════════════════════════════════════════════
$contract_options = array();
$contracts_map = array();
try {
    $c_rows = $rdl_gate->scopedQuery(array(
        'scope'  => array('c' => 'contracts'),
        'enrich' => array('p' => 'project'),
    ), "SELECT c.id, p.name AS project_name
        FROM contracts c
        LEFT JOIN project p ON p.id = c.project_id
        WHERE {TENANT_SCOPE} AND c.is_deleted = 0
        ORDER BY c.id DESC");
} catch (\Throwable $t) { $c_rows = array(); }
foreach ($c_rows as $c) {
    $cid = intval($c['id']);
    $label = 'عقد #' . $cid . ' - ' . (string) $c['project_name'];
    $contract_options[] = array('id' => $cid, 'label' => $label);
    $contracts_map[$cid] = $label;
}

// ══════════════════════════════════════════════════════════════════════════════
// جلب بنود الجاهزية + الإحصائيات (مع مؤشر جاهزية العقد)
// ══════════════════════════════════════════════════════════════════════════════
$rows = array();
$stat_total = 0;
$stat_pass = 0;
$stat_gap = 0;
$ready_contracts = array(); // عقود مكتملة الجاهزية (مجتاز)

try {
    $rdl_list = $rdl_gate->scopedQuery(array(
        'scope'  => array('r' => 'readiness_lines'),
        'enrich' => array('c' => 'contracts', 'p' => 'project', 'u' => 'users'),
    ), "SELECT r.*, c.readiness_state AS contract_readiness, p.name AS project_name, u.name AS creator_name
        FROM readiness_lines r
        LEFT JOIN contracts c ON c.id = r.contract_ref
        LEFT JOIN project p ON p.id = c.project_id
        LEFT JOIN users u ON u.id = r.created_by
        WHERE {TENANT_SCOPE} AND r.is_deleted = 0
        ORDER BY r.id DESC");
} catch (\Throwable $t) {
    $rdl_list = array();
    error_log('readiness_lines.php list load: ' . $t->getMessage());
}
foreach ($rdl_list as $row) {
    $rows[] = $row;
    $stat_total++;
    if ($row['state'] === 'مجتاز') $stat_pass++;
    if ($row['state'] === 'فجوة') $stat_gap++;
    if (($row['contract_readiness'] ?? '') === 'مجتاز') {
        $ready_contracts[intval($row['contract_ref'])] = true;
    }
}
$stat_ready_contracts = count($ready_contracts);

$page_title = "جاهزية العروض";
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include("../inheader.php");
include('../insidebar.php');
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<?php /* شريط رحلة الكيان — بالمسار لا بالاسم، فالاسم يسكن السجل وحده */ echo ems_entity_tabs_for('Clients/readiness_lines.php'); ?>

<div class="main rdl-main ems-unified-page-shell">

    <?php
    $header_title = 'جاهزية العروض';
    $header_icon = 'fas fa-clipboard-check';
    $header_actions = array();
    if ($can_add) {
        $header_actions[] = array('id' => 'toggleForm', 'class' => 'add-btn', 'icon' => 'fa fa-solid fa-plus', 'label' => '', 'label_class' => 'rdl-toggle-form-text');
    } else {
        $header_actions[] = array('tag' => 'button', 'class' => '', 'disabled' => true, 'icon' => 'fas fa-plus-circle', 'label' => 'إضافة (بدون صلاحيات)');
    }
    $header_actions[] = array('id' => 'toggleStats', 'class' => 'btn', 'title' => 'إظهار أو إخفاء الإحصائيات', 'icon' => 'fas fa-eye', 'label' => 'إظهار الإحصائيات', 'label_class' => 'rdl-toggle-stats-text');
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fa-solid fa-share', 'label' => '');
    
/* شريطُ تبويباتِ العائلة — قرارُ وثيقةِ المواءمة (مكوّنٌ مركزيّ) */
$sft_family = 'opportunity'; $sft_active = 'readiness';
include __DIR__ . '/../includes/sales_family_tabs.php';
include('../includes/page_header.php');
    // UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
    echo ems_states_bundle('لا بنود جاهزية مسجلة بعد', 'أضف أول بند جاهزية بزر «إضافة» في رأس الشاشة');
    ?>

    <?php if (!empty($_GET['msg'])):
        $isSuccess = strpos($_GET['msg'], '✅') !== false; ?>
        <div class="success-message <?= $isSuccess ? 'is-success' : 'is-error' ?>">
            <i class="fas <?= $isSuccess ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?php echo rdl_e($_GET['msg']); ?>
        </div>
    <?php endif; ?>

    <div class="stats-section rdl-hidden" id="rdlStatsSection">
        <div class="stats-grid">
            <div class="stats-card">
                <div class="stats-icon"><i class="fas fa-clipboard-list"></i></div>
                <div class="stats-value"><?php echo $stat_total; ?></div>
                <div class="stats-title">إجمالي البنود</div>
            </div>
            <div class="stats-card">
                <div class="stats-icon"><i class="fas fa-circle-check"></i></div>
                <div class="stats-value"><?php echo $stat_pass; ?></div>
                <div class="stats-title">بنود مجتازة</div>
            </div>
            <div class="stats-card">
                <div class="stats-icon"><i class="fas fa-triangle-exclamation"></i></div>
                <div class="stats-value"><?php echo $stat_gap; ?></div>
                <div class="stats-title">فجوات</div>
            </div>
            <div class="stats-card">
                <div class="stats-icon"><i class="fas fa-file-circle-check"></i></div>
                <div class="stats-value"><?php echo $stat_ready_contracts; ?></div>
                <div class="stats-title">عقود مكتملة الجاهزية</div>
            </div>
        </div>
    </div>

    <!-- فورم إضافة / تعديل بند جاهزية -->
    <form id="rdlForm" action="" method="post" class="allforms">
        <div class="card-header">
            <h5><i class="fas fa-edit"></i> <span id="formTitle">إضافة بند جاهزية</span></h5>
        </div>
        <input type="hidden" name="rdl_id" id="rdl_id" value="">
        <input type="hidden" name="csrf_token" value="<?php echo rdl_e($rdl_csrf_token); ?>">
        <div class="card shadow-sm pu-form-card">
            <div class="card-body">
                <div class="form-grid">
                    <div id="generated_code_wrapper" class="auto">
                        <label for="generated_rdl_code"><i class="fas fa-magic"></i> كود البند المولد <i class="fas fa-info-circle rdl-info-icon"></i></label>
                        <input type="text" id="generated_rdl_code" class="generated-code-field" value="<?php echo rdl_e($next_rdl_code); ?>" readonly tabindex="-1" title="هذا الكود للعرض فقط، يمكنك نسخه واستخدامه في حقل كود البند" />
                        <div class="generated-code-hint"></div>
                    </div>

                    <div>
                        <label for="readiness_code"><i class="fas fa-barcode"></i> كود البند *</label>
                        <!-- مكتوبٌ سلفًا بالكودِ المولَّد **وقابلٌ للتعديل** (نظيرُ كودِ العميلِ والمشروع):
                             أكثرُ الحالاتِ تقبله كما هو، ومَن أراد كودَه الخاصَّ كتبه فوقه. ووضعُه في
                             السمةِ `value` لا بجافاسكربت مقصود: `resetForm()` تستدعي `reset()` الأصليَّ
                             وهو يعيد كلَّ حقلٍ إلى سمتِه — فيعود الكودُ المولَّدُ تلقائيًّا بعد كلِّ
                             إلغاءٍ أو خروجٍ من وضعِ التعديل. -->
                        <input type="text" name="readiness_code" id="readiness_code" placeholder="مثال: RDL-001" required
                            value="<?php echo rdl_e($next_rdl_code); ?>"
                            pattern="[A-Za-z0-9_\-]+" />
                    </div>
                    <div>
                        <label for="contract_ref"><i class="fas fa-file-contract"></i> العقد المرتبط *</label>
                        <select name="contract_ref" id="contract_ref" required>
                            <option value="">-- اختر العقد --</option>
                            <?php foreach ($contract_options as $co): ?>
                                <option value="<?php echo intval($co['id']); ?>"><?php echo rdl_e($co['label']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="name"><i class="fas fa-list-check"></i> بند الجاهزية</label>
                        <select name="name" id="name">
                            <?php foreach ($RDL_NAMES as $nm): ?>
                                <option value="<?php echo rdl_e($nm); ?>" data-src="<?php echo rdl_e($RDL_SOURCE_HINT[$nm]); ?>"><?php echo rdl_e($nm); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="state"><i class="fas fa-traffic-light"></i> الحالة</label>
                        <select name="state" id="state">
                            <?php foreach ($RDL_STATES as $st): $sel = $st === 'قيد المعالجة' ? 'selected' : ''; ?>
                                <option value="<?php echo rdl_e($st); ?>" <?php echo $sel; ?>><?php echo rdl_e($st); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="source_ref"><i class="fas fa-building-user"></i> الإدارة المصدر</label>
                        <input type="text" name="source_ref" id="source_ref" maxlength="60" placeholder="مثال: S03 / الموقع" />
                    </div>
                    <div>
                        <label for="required"><i class="fas fa-clipboard"></i> المطلوب (من بنود العقد)</label>
                        <input type="text" name="required" id="required" maxlength="255" placeholder="مثال: 6 حفارات جاهزة" />
                    </div>
                    <div>
                        <label for="available"><i class="fas fa-boxes-packing"></i> المتاح (من نظام الإدارة)</label>
                        <input type="text" name="available" id="available" maxlength="255" placeholder="مثال: 5 حفارات متاحة" />
                    </div>
                    <div class="rdl-col-full">
                        <label for="gap_note"><i class="fas fa-comment-dots"></i> وصف الفجوة وخطة التغطية</label>
                        <textarea name="gap_note" id="gap_note" rows="2" placeholder="وصف الفجوة وخطة معالجتها إن وجدت"></textarea>
                    </div>
                </div>
                <div class="pu-form-actions">
                    <button type="submit" class="btn-primary"><i class="fas fa-save"></i> <span id="submitBtnText">حفظ البند</span></button>
                    <button type="button" id="rdlFormCancelBtn" class="btn-secondary"><i class="fas fa-times"></i> إلغاء</button>
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
                <label for="filterName"><i class="fa fa-list-check"></i> البند</label>
                <select id="filterName" class="form-control">
                    <option value="">-- كل البنود --</option>
                </select>
            </div>
            <div class="filter-field">
                <label for="filterState"><i class="fa fa-traffic-light"></i> الحالة</label>
                <select id="filterState" class="form-control">
                    <option value="">-- كل الحالات --</option>
                </select>
            </div>
            <div class="filter-actions">
                <button type="button" class="btn-primary"><i class="fa fa-search"></i> تطبيق</button>
                <button type="button" class="btn-secondary" title="إعادة تعيين"><i class="fa fa-rotate-right"></i></button>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-container">
                <table id="rdlTable" class="display rdl-table-nowrap no-datatable" data-state-save="false">
                    <thead>
                        <tr>
                            <th>إجراءات</th>
                            <th width="90">الكود</th>
                            <th>العقد</th>
                            <th>جاهزية العقد</th>
                            <th>البند</th>
                            <th>الحالة</th>
                            <th>المرجع</th>
                            <!-- E-03 موجة ٤: النواة الحاكمة (gov_columns) — الخلايا يحشوها ui-unification.js -->
                            <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صف بلا كيان مالك">الكيان</th>
                            <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المنشئ — الاسم والصفة</th>
                            <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمد — الاسم والصفة</th>
                            <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمد — تفويض أو سلطة أصلية">مرجع التفويض</th>
                            <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
                            <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
                            <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
                            </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row):
                            $cid = intval($row['contract_ref']);
                            $contract_label = ($cid > 0 && isset($contracts_map[$cid])) ? $contracts_map[$cid] : '';
                            $contract_readiness = $row['contract_readiness'] ?? 'لم يبدأ';
                            $created_label = function_exists('ems_actor_label') ? ems_actor_label($conn, intval($row['created_by'])) : ($row['creator_name'] ?? '');
                            $state_cls = $row['state'] === 'مجتاز' ? 'rdl-badge-pass' : ($row['state'] === 'فجوة' ? 'rdl-badge-gap' : 'rdl-badge-progress');
                            $roll_cls = $contract_readiness === 'مجتاز' ? 'rdl-badge-pass' : ($contract_readiness === 'جارٍ' ? 'rdl-badge-progress' : 'rdl-badge-none');
                            ?>
                            <tr>
                                <td>
                                    <div class="action-btns">
                                        <a href="javascript:void(0)" class="action-btn view viewRdlBtn"
                                            data-id="<?php echo intval($row['id']); ?>"
                                            data-code="<?php echo rdl_e($row['readiness_code']); ?>"
                                            data-contract="<?php echo rdl_e($contract_label); ?>"
                                            data-rollup="<?php echo rdl_e($contract_readiness); ?>"
                                            data-name="<?php echo rdl_e($row['name']); ?>"
                                            data-state="<?php echo rdl_e($row['state']); ?>"
                                            data-source="<?php echo rdl_e($row['source_ref']); ?>"
                                            data-required="<?php echo rdl_e($row['required']); ?>"
                                            data-available="<?php echo rdl_e($row['available']); ?>"
                                            data-gap="<?php echo rdl_e($row['gap_note']); ?>"
                                            data-created="<?php echo rdl_e($created_label); ?>"
                                            title="عرض التفاصيل"><i class="fas fa-eye"></i></a>
                                        <?php if ($can_edit): ?>
                                            <a href="javascript:void(0)" class="action-btn edit editRdlBtn"
                                                data-id="<?php echo intval($row['id']); ?>"
                                                data-code="<?php echo rdl_e($row['readiness_code']); ?>"
                                                data-contract-id="<?php echo intval($row['contract_ref']); ?>"
                                                data-name="<?php echo rdl_e($row['name']); ?>"
                                                data-state="<?php echo rdl_e($row['state']); ?>"
                                                data-source="<?php echo rdl_e($row['source_ref']); ?>"
                                                data-required="<?php echo rdl_e($row['required']); ?>"
                                                data-available="<?php echo rdl_e($row['available']); ?>"
                                                data-gap="<?php echo rdl_e($row['gap_note']); ?>"
                                                title="تعديل"><i class="fas fa-edit"></i></a>
                                        <?php endif; ?>
                                        <?php if ($can_delete): ?>
                                            <a href="?delete_id=<?php echo urlencode($row['id']); ?>&csrf_token=<?php echo urlencode($rdl_csrf_token); ?>"
                                                class="action-btn delete"
                                                onclick="return confirm('هل أنت متأكد من حذف هذا البند؟')" title="حذف"><i class="fas fa-trash-alt"></i></a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><strong class="rdl-code-cell"><?php echo rdl_e($row['readiness_code']); ?></strong></td>
                                <td><?php echo $contract_label !== '' ? rdl_e($contract_label) : '<span class="rdl-muted">—</span>'; ?></td>
                                <td><span class="rdl-badge <?php echo $roll_cls; ?>"><?php echo rdl_e($contract_readiness); ?></span></td>
                                <td><?php echo rdl_e($row['name']); ?></td>
                                <td><span class="rdl-badge <?php echo $state_cls; ?>"><?php echo rdl_e($row['state']); ?></span></td>
                                <td><?php echo $row['source_ref'] !== null && $row['source_ref'] !== '' ? rdl_e($row['source_ref']) : '<span class="rdl-muted">—</span>'; ?></td>
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
    $(document).ready(function () {
        // UXW-01 ⑤: تهيئةُ الجدولِ للمكوّنِ المركزيِّ وحدَه (ui-unification.js) —
        // وما كانت تضبطه التهيئةُ المحليةُ صار سماتٍ على وسمِ الجدول.
        function fillFilterOptions(columnIndex, selectId) {
            const select = $(selectId);
            const values = [];
            const addValue = function (raw) {
                const text = String(raw).trim();
                if (text !== '' && text !== '—' && values.indexOf(text) === -1) values.push(text);
            };
            if ($.fn.dataTable && $.fn.dataTable.isDataTable('#rdlTable')) {
                $('#rdlTable').DataTable().column(columnIndex).data().each(function (value) {
                    addValue($('<div>').html(value).text());
                });
            } else {
                $('#rdlTable tbody tr').each(function () {
                    const cell = this.cells[columnIndex];
                    if (cell) addValue($(cell).text());
                });
            }
            values.sort();
            values.forEach(function (val) {
                select.append('<option value="' + val.replace(/"/g, '&quot;') + '">' + val + '</option>');
            });
        }
        fillFilterOptions(4, '#filterName');
        fillFilterOptions(5, '#filterState');

        $('#filterName').on('change', function () {
            if (!$.fn.dataTable || !$.fn.dataTable.isDataTable('#rdlTable')) { return; }
            const value = $.fn.dataTable.util.escapeRegex($(this).val());
            $('#rdlTable').DataTable().column(4).search(value ? '^' + value + '$' : '', true, false).draw();
        });
        $('#filterState').on('change', function () {
            if (!$.fn.dataTable || !$.fn.dataTable.isDataTable('#rdlTable')) { return; }
            const value = $.fn.dataTable.util.escapeRegex($(this).val());
            $('#rdlTable').DataTable().column(5).search(value ? '^' + value + '$' : '', true, false).draw();
        });
    });

    // ── إظهار/إخفاء الفورم والإحصائيات ──
    const formToggleBtn = $('#toggleForm');
    const rdlForm = $('#rdlForm');
    const formTitle = $('#formTitle');
    const submitBtnText = $('#submitBtnText');
    const generatedCodeWrapper = $('#generated_code_wrapper');
    const formCancelBtn = $('#rdlFormCancelBtn');
    const statsToggleBtn = $('#toggleStats');
    const statsSection = $('#rdlStatsSection');

    /**
     * إظهار حقل الكود المولد وإخفاؤه.
     *
     * ⚠️ **لا تستعمل `jQuery.hide()` هنا** — `assets/css/ems-forms.css` يحمل:
     *     :is(.allforms, .ems-form) .form-grid > div { display: block !important }
     * والغلاف ابن مباشر ل`.form-grid`، ف`!important` من ورقة الأنماط تهزم
     * الإخفاء السطري **بلا أولوية**: السمة تكتب فعلا والحقل يبقى ظاهرا، بلا
     * خطأ في وحدة التحكم ولا سطر في أي سجل. (نظير شاشتي العملاء والمشاريع.)
     */
    function setGeneratedCodeShown(shown) {
        var el = generatedCodeWrapper[0];
        if (!el) { return; }
        if (shown) { el.style.removeProperty('display'); }
        else       { el.style.setProperty('display', 'none', 'important'); }
    }
    function setAddMode() {
        formTitle.text('إضافة بند جاهزية'); submitBtnText.text('حفظ البند');
        setGeneratedCodeShown(true);
        // الكود المولد يعود إلى خانته كلما دخلنا وضع الإضافة — ومصدره حقل
        // العرض نفسه لا نسخة ثانية منه (مصدر حقيقة واحد). و`reset()` يكفي
        // للإلغاء، لكن الانتقال من «تعديل» إلى «إضافة» قد يقع بلا reset.
        var genCode = $('#generated_rdl_code').val();
        if (genCode) { $('#readiness_code').val(genCode); }
    }
    function setEditMode() { formTitle.text('تعديل بند الجاهزية'); submitBtnText.text('تحديث البند'); setGeneratedCodeShown(false); }
    function resetForm() { if (!rdlForm.length) return; rdlForm[0].reset(); $('#rdl_id').val(''); setAddMode(); if (window.EmsSelect) EmsSelect.refresh(); }

    function updateFormToggleState(isOpen) {
        if (!formToggleBtn.length) return;
        formToggleBtn.toggleClass('is-active', isOpen);
        formToggleBtn.attr('aria-expanded', isOpen ? 'true' : 'false');
    }
    function updateStatsToggleState(isVisible) {
        if (!statsToggleBtn.length) return;
        statsToggleBtn.toggleClass('is-active', isVisible);
        statsToggleBtn.find('.rdl-toggle-stats-text').text('إظهار الإحصائيات');
        const icon = statsToggleBtn.find('i').first();
        icon.toggleClass('fa-chart-pie', isVisible);
        icon.toggleClass('fa-eye', !isVisible);
    }

    setAddMode();
    updateFormToggleState(rdlForm.is(':visible'));
    updateStatsToggleState(statsSection.is(':visible'));

    formToggleBtn.on('click', function (e) {
        e.preventDefault();
        if (!rdlForm.length) return;
        if (rdlForm.is(':visible')) {
            rdlForm.stop(true, true).slideUp(250, function () { rdlForm.removeClass('allforms-visible'); resetForm(); updateFormToggleState(false); });
        } else {
            resetForm();
            rdlForm.addClass('allforms-visible').hide();
            rdlForm.stop(true, true).slideDown(250, function () { updateFormToggleState(true); });
        }
    });
    formCancelBtn.on('click', function () {
        if (!rdlForm.length || !rdlForm.is(':visible')) return;
        rdlForm.stop(true, true).slideUp(250, function () { rdlForm.removeClass('allforms-visible'); resetForm(); updateFormToggleState(false); });
    });
    statsToggleBtn.on('click', function (e) {
        e.preventDefault();
        if (!statsSection.length) return;
        if (statsSection.is(':visible')) {
            statsSection.stop(true, true).slideUp(250, function () { statsSection.addClass('rdl-hidden'); updateStatsToggleState(false); });
        } else {
            statsSection.removeClass('rdl-hidden').hide();
            statsSection.stop(true, true).slideDown(250, function () { updateStatsToggleState(true); });
        }
    });

    // اقتراح المرجع تلقائيا من البند
    const RDL_SRC = <?php echo json_encode($RDL_SOURCE_HINT, JSON_UNESCAPED_UNICODE); ?>;
    $('#name').on('change', function () {
        const src = $('#source_ref');
        if (src.val().trim() === '' && RDL_SRC[$(this).val()]) src.val(RDL_SRC[$(this).val()]);
    });

    // ── تعبئة الفورم للتعديل ──
    function fillRdlForm(d) {
        $('#rdl_id').val(d.id);
        $('#readiness_code').val(d.code);
        $('#contract_ref').val(d.contractId ? String(d.contractId) : '');
        $('#name').val(d.name || 'جاهزية الأسطول');
        $('#state').val(d.state || 'قيد المعالجة');
        $('#source_ref').val(d.source || '');
        $('#required').val(d.required || '');
        $('#available').val(d.available || '');
        $('#gap_note').val(d.gap || '');
        if (window.EmsSelect) EmsSelect.refresh();
        setEditMode();
        if (!rdlForm.is(':visible')) {
            rdlForm.addClass('allforms-visible').hide();
            rdlForm.stop(true, true).slideDown(250, function () { updateFormToggleState(true); });
        } else { updateFormToggleState(true); }
        $('html, body').animate({ scrollTop: $('#rdlForm').offset().top - 100 }, 500);
    }

    $(document).on('click', '.editRdlBtn', function () {
        fillRdlForm({
            id: $(this).data('id'), code: $(this).data('code'), contractId: $(this).data('contract-id'),
            name: $(this).data('name'), state: $(this).data('state'), source: $(this).data('source'),
            required: $(this).data('required'), available: $(this).data('available'), gap: $(this).data('gap')
        });
    });

    // ── عرض التفاصيل عبر EmsDetailsModal الموحد ──
    $(document).on('click', '.viewRdlBtn', function () {
        const d = $(this).data();
        const fields = [
            { label: 'كود البند', value: d.code, icon: 'fas fa-barcode' },
            { label: 'العقد المرتبط', value: d.contract || '—', icon: 'fas fa-file-contract', size: 'lg' },
            { label: 'جاهزية العقد (محسوبة)', value: d.rollup || '—', icon: 'fas fa-gauge-high', type: 'status' },
            { label: 'بند الجاهزية', value: d.name || '—', icon: 'fas fa-list-check' },
            { label: 'الحالة', value: d.state || '—', icon: 'fas fa-traffic-light', type: 'status' },
            { label: 'الإدارة المصدر', value: d.source || '—', icon: 'fas fa-building-user' },
            { label: 'المطلوب', value: d.required || '—', icon: 'fas fa-clipboard', size: 'lg' },
            { label: 'المتاح', value: d.available || '—', icon: 'fas fa-boxes-packing', size: 'lg' },
            { label: 'وصف الفجوة وخطة التغطية', value: d.gap || '—', icon: 'fas fa-comment-dots', size: 'lg' },
            { label: 'أضيف بواسطة', value: d.created || '—', icon: 'fas fa-user-plus' }
        ];

        const actions = [];
        <?php if ($can_edit): ?>
            actions.push({ label: 'تعديل البند', icon: 'fas fa-edit', variant: 'primary', onClick: function () {
                EmsDetailsModal.close();
                $('.editRdlBtn[data-id="' + d.id + '"]').trigger('click');
            }});
        <?php endif; ?>
        actions.push({ label: 'إغلاق', icon: 'fas fa-times', variant: 'secondary', close: true });

        EmsDetailsModal.open({ title: 'تفاصيل بند الجاهزية', icon: 'fas fa-clipboard-check', fields: fields, actions: actions });
    });
</script>

<style>
    .rdl-main .stats-grid { display: grid; grid-template-columns: repeat(4, minmax(170px, 1fr)); gap: 12px; }
    .rdl-main .stats-section {
        border: 1px solid var(--bdr); border-radius: var(--rl);
        background: linear-gradient(180deg, rgba(255,255,255,.95) 0%, var(--s2) 100%);
        box-shadow: var(--sh); padding: 14px; margin-bottom: 14px;
    }
    .rdl-main .stats-card { background: var(--c-s-eee); border: 1px solid var(--c-s-aaa); border-radius: 35px; padding: 18px; box-shadow: 0 2px 8px var(--c-rgba26188007); position: relative; overflow: hidden; }
    .rdl-main .stats-card .stats-icon { width: 55px; height: 55px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.3rem; margin-bottom: 10px; float: left; margin-top: 15px; border: 1px solid var(--c-ink-400); background:var(--c-s-fff); color:var(--c-s-000); }
    .rdl-main .stats-card .stats-title { color: var(--c-s-555); font-size: .92rem; font-weight: 700; margin-top: 5px; line-height: 1.3; }
    .rdl-main .stats-card .stats-value { color: var(--c-s-222); line-height: 1; font-weight: 900; font-variant-numeric: tabular-nums; margin-top: 10px; font-size: 30px; }
    @media (max-width: 900px) { .rdl-main .stats-grid { grid-template-columns: repeat(2, minmax(150px,1fr)); } }
    @media (max-width: 560px) { .rdl-main .stats-grid { grid-template-columns: 1fr; } }

    .rdl-main .rdl-hidden { display: none; }
    .rdl-main .rdl-col-full { grid-column: 1 / -1; }
    .rdl-main .table-container { overflow-x: auto; }
    #rdlTable.rdl-table-nowrap, #rdlTable.rdl-table-nowrap th, #rdlTable.rdl-table-nowrap td { white-space: nowrap; }
    #rdlTable .action-btns { flex-wrap: nowrap; white-space: nowrap; }
    .rdl-main .rdl-muted { color: var(--c-ink-400); }
    .rdl-main .rdl-badge { display: inline-block; padding: 2px 10px; border-radius: 20px; font-size: .82rem; font-weight: 700; border: 1px solid transparent; }
    .rdl-main .rdl-badge-pass { background: var(--c-e6f5ea, #e6f5ea); color: var(--c-1a7a3a, #1a7a3a); border-color: var(--c-9ed6b0, #9ed6b0); }
    .rdl-main .rdl-badge-gap { background: var(--c-fdeaea); color: var(--c-b02a2a, #b02a2a); border-color: var(--c-f0b0b0, #f0b0b0); }
    .rdl-main .rdl-badge-progress { background: var(--c-fff5e0, #fff5e0); color: var(--c-9a6a00, #9a6a00); border-color: var(--c-f0d79a, #f0d79a); }
    .rdl-main .rdl-badge-none { background: var(--c-s-eee); color: var(--c-s-777); border-color: var(--c-s-ccc); }
</style>

</body>

</html>
