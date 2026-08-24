<?php
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}

include '../config.php';
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
if (!function_exists('amd_e')) {
    function amd_e($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
if (!function_exists('amd_money')) {
    function amd_money($value)
    {
        if ($value === null || $value === '') {
            return '—';
        }
        return number_format((float) $value, 2);
    }
}
if (!function_exists('amd_redirect_with_msg')) {
    function amd_redirect_with_msg($msg)
    {
        ems_gov_flash_redirect('contract_amendments.php', $msg, 'GOV-INFO-200', '');
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
$amd_gate = ems_tenant_db();

// رمز CSRF
// [ع-0أ] اعتماد الرمز المركزي بدل رمزٍ محلّيٍّ منفصل: الحاقنُ المركزي يضع حقل
// csrf_token برمز الجلسة، وهذا كان يطبع حقلًا ثانيًا بالاسم نفسه وقيمةٍ أخرى —
// وPHP يأخذ الأخير فيفشل الحارس المركزي (403). توحيدُ القيمة يُبقي الفحص المحلّي
// أدناه فعّالًا ويُمرّر الحارس المركزي أيًّا كان الفائز.
$amd_csrf_token = generate_csrf_token();

// القوائم الثابتة (ENUM)
$AMD_TYPES = array('تجديد', 'تمديد', 'زيادة نطاق', 'تخفيض نطاق', 'تغيير أسعار', 'إضافة معدات', 'إضافة خدمات');

// توليد الكود المقترح التالي (AMD-NNNN) — للعرض فقط
$next_amd_code = 'AMD-0001';
$last_code_sql = "SELECT amendment_code FROM contract_amendments
                  WHERE amendment_code REGEXP '^AMD-[0-9]+$' AND company_id = $company_id AND is_deleted = 0
                  ORDER BY CAST(SUBSTRING(amendment_code, 5) AS UNSIGNED) DESC LIMIT 1";
try {
    $last_code_rows = $amd_gate->scopedQuery(array(
        'scope' => array('a' => 'contract_amendments'),
    ), "SELECT a.amendment_code FROM contract_amendments a
        WHERE {TENANT_SCOPE} AND a.amendment_code REGEXP '^AMD-[0-9]+$' AND a.is_deleted = 0
        ORDER BY CAST(SUBSTRING(a.amendment_code, 5) AS UNSIGNED) DESC LIMIT 1");
} catch (\Throwable $t) {
    $last_code_rows = array();
}
if (!empty($last_code_rows)) {
    $last_num = intval(substr($last_code_rows[0]['amendment_code'], 4));
    $next_amd_code = 'AMD-' . str_pad($last_num + 1, 4, '0', STR_PAD_LEFT);
}

// صلاحيات المستخدم على وحدة الملاحق والتجديدات (modules جدول عام — قراءة عبر البوابة)
try {
    $module_info = $amd_gate->selectOne('modules', array(
        'columns' => array('id'),
        'where'   => array('code' => 'Clients/contract_amendments.php'),
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
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض الملاحق والتجديدات ❌', 'GOV-PERM-403', '');
    exit();
}

// ══════════════════════════════════════════════════════════════════════════════
// السجلّ صار للقراءة فقط (D02 · توحيد مصدر الحقيقة): الملاحق تُغذّى تلقائيًّا من
// إجراءات العقد (Contracts/contract_actions_handler.php) داخل معاملةٍ ذرّية. لا
// كتابةَ يدويّة — أي POST أو حذفٍ يُرفض هنا (دفاعٌ خادميّ فوق إخفاء الواجهة).
// ══════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['delete_id'])) {
    amd_redirect_with_msg('سجلّ الملاحق للقراءة فقط — يُغذّى تلقائيًّا من إجراءات العقد ℹ️');
}

// ══════════════════════════════════════════════════════════════════════════════
// معالجة إضافة / تعديل ملحق عبر POST
// ══════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['amendment_code'])) {
    $posted_csrf = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if (empty($posted_csrf) || !hash_equals($amd_csrf_token, $posted_csrf)) {
        amd_redirect_with_msg('جلسة النموذج غير صالحة، يرجى إعادة المحاولة ❌');
    }

    $amd_id     = isset($_POST['amd_id']) ? intval($_POST['amd_id']) : 0;
    $is_editing = $amd_id > 0;

    if ($is_editing && !$can_edit) {
        amd_redirect_with_msg('لا توجد صلاحية تعديل الملاحق ❌');
    } elseif (!$is_editing && !$can_add) {
        amd_redirect_with_msg('لا توجد صلاحية إضافة ملاحق جديدة ❌');
    }

    // الكود
    $amd_code_raw = isset($_POST['amendment_code']) ? trim($_POST['amendment_code']) : '';
    if ($amd_code_raw === '' || !preg_match('/^[A-Za-z0-9_\-]+$/', $amd_code_raw)) {
        amd_redirect_with_msg('كود الملحق غير صالح. استخدم أحرفًا وأرقامًا و - أو _ فقط ❌');
    }

    // التحقق من نوع التعديل (ENUM)
    $amend_type_raw = isset($_POST['amend_type']) ? trim($_POST['amend_type']) : '';
    if (!in_array($amend_type_raw, $AMD_TYPES, true)) {
        amd_redirect_with_msg('نوع التعديل غير صالح ❌');
    }

    // العقد المرتبط — التحقق من النطاق (إن حُدِّد) عبر البوابة
    $contract_in = isset($_POST['contract_id']) ? intval($_POST['contract_id']) : 0;
    if ($contract_in > 0) {
        try {
            $cchk = $amd_gate->selectOne('contracts', array(
                'columns' => array('id'), 'where' => array('id' => $contract_in),
            ));
        } catch (\Throwable $t) { $cchk = null; }
        if (!$cchk) {
            amd_redirect_with_msg('العقد المرتبط غير موجود أو خارج نطاق شركتك ❌');
        }
    }
    $contract_val = $contract_in > 0 ? $contract_in : null;

    // الجهة الطالبة — التحقق من النطاق (إن حُدِّدت) — الأصل بلا فلتر حذفٍ ناعم
    $requested_by_in = isset($_POST['requested_by']) ? intval($_POST['requested_by']) : 0;
    if ($requested_by_in > 0) {
        try {
            $rchk = $amd_gate->selectOne('users', array(
                'columns' => array('id'), 'where' => array('id' => $requested_by_in),
                'includeDeleted' => true,
            ));
        } catch (\Throwable $t) { $rchk = null; }
        if (!$rchk) {
            amd_redirect_with_msg('الجهة الطالبة غير موجودة أو خارج نطاق شركتك ❌');
        }
    }
    $requested_by_val = $requested_by_in > 0 ? $requested_by_in : null;

    // الأثر الرقمي — NULL إن فراغ
    $effect_price_raw = isset($_POST['effect_price']) ? trim($_POST['effect_price']) : '';
    $effect_price_val = $effect_price_raw === '' ? null : (float) $effect_price_raw;
    $effect_qty_raw   = isset($_POST['effect_qty']) ? trim($_POST['effect_qty']) : '';
    $effect_qty_val   = $effect_qty_raw === '' ? null : (float) $effect_qty_raw;
    $effect_dur_raw   = isset($_POST['effect_duration']) ? trim($_POST['effect_duration']) : '';
    $effect_dur_val   = $effect_dur_raw === '' ? null : (int) $effect_dur_raw;

    // القيم تُمرَّر خامًا — البوابة prepared بالكامل (لا escape يدوي)
    $reason_raw         = isset($_POST['reason']) ? trim($_POST['reason']) : '';
    $old_value_raw      = isset($_POST['old_value']) ? trim($_POST['old_value']) : '';
    $new_value_raw      = isset($_POST['new_value']) ? trim($_POST['new_value']) : '';
    $effect_summary_raw = isset($_POST['effect_summary']) ? trim($_POST['effect_summary']) : '';
    $adate_raw = isset($_POST['amend_date']) ? trim($_POST['amend_date']) : '';
    $adate_val = preg_match('/^\d{4}-\d{2}-\d{2}$/', $adate_raw) ? $adate_raw : null;
    $created_by = intval($_SESSION['user']['id']);

    if ($is_editing) {
        try {
            $owner = $amd_gate->selectOne('contract_amendments', array(
                'columns' => array('id'), 'where' => array('id' => $amd_id),
            ));
        } catch (\Throwable $t) { $owner = null; }
        if (!$owner) {
            amd_redirect_with_msg('لا يمكنك تعديل ملحق لا يتبع لشركتك ❌');
        }
        try {
            $dup = $amd_gate->scopedQuery(array(
                'scope' => array('a' => 'contract_amendments'),
            ), "SELECT a.id FROM contract_amendments a
                WHERE {TENANT_SCOPE} AND a.amendment_code = ? AND a.id != ? AND a.is_deleted = 0",
                array($amd_code_raw, $amd_id));
        } catch (\Throwable $t) { $dup = array(); }
        if (!empty($dup)) {
            amd_redirect_with_msg('كود الملحق موجود مسبقاً داخل شركتك ❌');
        }

        try {
            $amd_gate->update('contract_amendments', array(
                'amendment_code'  => $amd_code_raw,
                'contract_id'     => $contract_val,
                'amend_type'      => $amend_type_raw,
                'amend_date'      => $adate_val,
                'requested_by'    => $requested_by_val,
                'reason'          => $reason_raw,
                'old_value'       => $old_value_raw,
                'new_value'       => $new_value_raw,
                'effect_price'    => $effect_price_val,
                'effect_qty'      => $effect_qty_val,
                'effect_duration' => $effect_dur_val,
                'effect_summary'  => $effect_summary_raw,
            ), array('id' => $amd_id), 'is_deleted = 0');
            if (class_exists('\\App\\Services\\ActivityLogService')) {
                \App\Services\ActivityLogService::logUpdate('contract_amendments', 'contract_amendments', $amd_id, null, ['amendment_code' => $amd_code_raw]);
            }
            amd_redirect_with_msg('تم تعديل الملحق بنجاح ✅');
        } catch (\Throwable $t) {
            error_log('contract_amendments.php update failed: ' . $t->getMessage());
            amd_redirect_with_msg('حدث خطأ أثناء التعديل ❌');
        }
    } else {
        try {
            $dup = $amd_gate->scopedQuery(array(
                'scope' => array('a' => 'contract_amendments'),
            ), "SELECT a.id FROM contract_amendments a
                WHERE {TENANT_SCOPE} AND a.amendment_code = ? AND a.is_deleted = 0",
                array($amd_code_raw));
        } catch (\Throwable $t) { $dup = array(); }
        if (!empty($dup)) {
            amd_redirect_with_msg('كود الملحق موجود مسبقاً داخل شركتك ❌');
        }

        try {
            $new_id = (int) $amd_gate->insert('contract_amendments', array(
                'amendment_code'  => $amd_code_raw,
                'contract_id'     => $contract_val,
                'amend_type'      => $amend_type_raw,
                'amend_date'      => $adate_val,
                'requested_by'    => $requested_by_val,
                'reason'          => $reason_raw,
                'old_value'       => $old_value_raw,
                'new_value'       => $new_value_raw,
                'effect_price'    => $effect_price_val,
                'effect_qty'      => $effect_qty_val,
                'effect_duration' => $effect_dur_val,
                'effect_summary'  => $effect_summary_raw,
                'created_by'      => $created_by,
            ));
            if (class_exists('\\App\\Services\\ActivityLogService')) {
                \App\Services\ActivityLogService::logCreate('contract_amendments', 'contract_amendments', $new_id, ['amendment_code' => $amd_code_raw]);
            }
            amd_redirect_with_msg('تم إضافة الملحق بنجاح ✅');
        } catch (\Throwable $t) {
            error_log('contract_amendments.php insert failed: ' . $t->getMessage());
            amd_redirect_with_msg('حدث خطأ أثناء الإضافة ❌');
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
        amd_redirect_with_msg('لا توجد صلاحية حذف الملاحق ❌');
    }
    if (empty($delete_csrf) || !hash_equals($amd_csrf_token, $delete_csrf)) {
        amd_redirect_with_msg('جلسة الحذف غير صالحة، يرجى إعادة المحاولة ❌');
    }
    try {
        $chk = $amd_gate->selectOne('contract_amendments', array(
            'columns' => array('id'), 'where' => array('id' => $delete_id),
        ));
    } catch (\Throwable $t) { $chk = null; }
    if (!$chk) {
        amd_redirect_with_msg('لا يمكنك حذف ملحق لا يتبع لشركتك ❌');
    }
    try {
        $amd_gate->softDelete('contract_amendments', $delete_id); // يختم deleted_at/deleted_by من السياق
        if (class_exists('\\App\\Services\\ActivityLogService')) {
            \App\Services\ActivityLogService::logDelete('contract_amendments', 'contract_amendments', $delete_id);
        }
        amd_redirect_with_msg('تم حذف الملحق بنجاح ✅');
    } catch (\Throwable $t) {
        error_log('contract_amendments.php soft delete failed: ' . $t->getMessage());
        amd_redirect_with_msg('حدث خطأ أثناء الحذف ❌');
    }
}

// ══════════════════════════════════════════════════════════════════════════════
// قوائم الاختيار (ضمن نطاق الشركة — العزل عبر البوابة)
// ══════════════════════════════════════════════════════════════════════════════
$contract_options = array();
$contracts_map = array();
try {
    $c_rows = $amd_gate->scopedQuery(array(
        'scope'  => array('c' => 'contracts'),
        'enrich' => array('p' => 'project'), // اسم المشروع — LEFT بلا تنطيق (سلوك الأصل)
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

$user_options = array();
$users_map = array();
try {
    // الأصل بلا فلتر حذفٍ ناعم على المستخدمين — includeDeleted يحفظ السلوك حرفيًّا
    $u_rows = $amd_gate->select('users', array(
        'columns' => array('id', 'name'),
        'includeDeleted' => true,
    ));
} catch (\Throwable $t) { $u_rows = array(); }
foreach ($u_rows as $u) {
    $uid = intval($u['id']);
    $user_options[] = array('id' => $uid, 'name' => $u['name']);
    $users_map[$uid] = $u['name'];
}

// ══════════════════════════════════════════════════════════════════════════════
// جلب الملاحق + الإحصائيات
// ══════════════════════════════════════════════════════════════════════════════
$rows = array();
$stat_total = 0;
$stat_renew = 0;
$stat_extend = 0;
$stat_price = 0;

try {
    $amd_list = $amd_gate->scopedQuery(array(
        'scope'  => array('a' => 'contract_amendments'),
        'enrich' => array('u' => 'users'), // إثراء اسم المُنشئ — LEFT بلا تنطيق (سلوك الأصل)
    ), "SELECT a.*, u.name AS creator_name
        FROM contract_amendments a
        LEFT JOIN users u ON u.id = a.created_by
        WHERE {TENANT_SCOPE} AND a.is_deleted = 0
        ORDER BY a.id DESC");
} catch (\Throwable $t) {
    $amd_list = array();
    error_log('contract_amendments.php list load: ' . $t->getMessage()); // [م-5]
}
foreach ($amd_list as $row) {
        $rows[] = $row;
        $stat_total++;
        if ($row['amend_type'] === 'تجديد') $stat_renew++;
        if ($row['amend_type'] === 'تمديد') $stat_extend++;
        if ($row['amend_type'] === 'تغيير أسعار') $stat_price++;
}

$page_title = "ملاحق العقود وتجديداتها";
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include("../inheader.php");
include('../insidebar.php');
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
// NAV-01 §8 (update0006-b): الشاشةُ قسمٌ من ملف العقد الأم لا صفحةٌ يتيمة
$cf_contract_id = intval($_GET['contract'] ?? $_GET['id'] ?? 0); $cf_active = 'amendments';
if ($cf_contract_id > 0) include __DIR__ . '/../includes/contract_file_tabs.php';
?>

<div class="main amd-main ems-unified-page-shell">

    <?php
    $header_title = 'ملاحق العقود وتجديداتها';
    $header_icon = 'fas fa-file-pen';
    $header_actions = array();
    // السجلّ للقراءة فقط (D02): لا زرَّ إضافةٍ — يُغذّى تلقائيًّا من إجراءات العقد
    $header_actions[] = array('id' => 'toggleStats', 'class' => 'btn', 'title' => 'إظهار أو إخفاء الإحصائيات', 'icon' => 'fas fa-eye', 'label' => 'إظهار الإحصائيات', 'label_class' => 'amd-toggle-stats-text');
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fa-solid fa-share', 'label' => '');
    include('../includes/page_header.php');
    ?>
<?php require_once __DIR__ . '/../includes/entity_tabs.php'; echo ems_entity_tabs('contract', 'سجلُّ التغييرات'); ?>

    <?php if (!empty($_GET['msg'])):
        $isSuccess = strpos($_GET['msg'], '✅') !== false; ?>
        <div class="success-message <?= $isSuccess ? 'is-success' : 'is-error' ?>">
            <i class="fas <?= $isSuccess ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?php echo amd_e($_GET['msg']); ?>
        </div>
    <?php endif; ?>

    <?php echo ems_states_bundle('لا ملاحقَ مسجَّلةً ضمن هذا الترشيح', 'الملاحقُ تُغذَّى تلقائيًّا من إجراءات العقد — أو غيّر المرشِّحات'); ?>

    <div class="stats-section amd-hidden" id="amdStatsSection">
        <div class="stats-grid">
            <div class="stats-card">
                <div class="stats-icon"><i class="fas fa-file-pen"></i></div>
                <div class="stats-value"><?php echo $stat_total; ?></div>
                <div class="stats-title">إجمالي الملاحق</div>
            </div>
            <div class="stats-card">
                <div class="stats-icon"><i class="fas fa-rotate"></i></div>
                <div class="stats-value"><?php echo $stat_renew; ?></div>
                <div class="stats-title">تجديدات</div>
            </div>
            <div class="stats-card">
                <div class="stats-icon"><i class="fas fa-clock"></i></div>
                <div class="stats-value"><?php echo $stat_extend; ?></div>
                <div class="stats-title">تمديدات</div>
            </div>
            <div class="stats-card">
                <div class="stats-icon"><i class="fas fa-tag"></i></div>
                <div class="stats-value"><?php echo $stat_price; ?></div>
                <div class="stats-title">تغيير أسعار</div>
            </div>
        </div>
    </div>

    <!-- فورم إضافة / تعديل ملحق -->
    <form id="amdForm" action="" method="post" class="allforms">
        <div class="card-header">
            <h5><i class="fas fa-edit"></i> <span id="formTitle">إضافة ملحق جديد</span></h5>
        </div>
        <input type="hidden" name="amd_id" id="amd_id" value="">
        <input type="hidden" name="csrf_token" value="<?php echo amd_e($amd_csrf_token); ?>">
        <div class="card shadow-sm pu-form-card">
            <div class="card-body">
                <div class="form-grid">
                    <div id="generated_code_wrapper" class="auto">
                        <label for="generated_amd_code"><i class="fas fa-magic"></i> كود الملحق المولد <i class="fas fa-info-circle amd-info-icon"></i></label>
                        <input type="text" id="generated_amd_code" class="generated-code-field" value="<?php echo amd_e($next_amd_code); ?>" readonly tabindex="-1" title="هذا الكود للعرض فقط، يمكنك نسخه واستخدامه في حقل كود الملحق" />
                        <div class="generated-code-hint"></div>
                    </div>

                    <div>
                        <label for="amendment_code"><i class="fas fa-barcode"></i> كود الملحق *</label>
                        <!-- مكتوبٌ سلفًا بالكودِ المولَّد **وقابلٌ للتعديل** (نظيرُ كودِ العميلِ والمشروع):
                             أكثرُ الحالاتِ تقبله كما هو، ومَن أراد كودَه الخاصَّ كتبه فوقه. ووضعُه في
                             السمةِ `value` لا بجافاسكربت مقصود: `resetForm()` تستدعي `reset()` الأصليَّ
                             وهو يعيد كلَّ حقلٍ إلى سمتِه — فيعود الكودُ المولَّدُ تلقائيًّا بعد كلِّ
                             إلغاءٍ أو خروجٍ من وضعِ التعديل. -->
                        <input type="text" name="amendment_code" id="amendment_code" placeholder="مثال: AMD-001" required
                            value="<?php echo amd_e($next_amd_code); ?>"
                            pattern="[A-Za-z0-9_\-]+" />
                    </div>
                    <div>
                        <label for="contract_id"><i class="fas fa-file-contract"></i> العقد المرتبط</label>
                        <select name="contract_id" id="contract_id">
                            <option value="">-- بدون / غير محدد --</option>
                            <?php foreach ($contract_options as $co): ?>
                                <option value="<?php echo intval($co['id']); ?>"><?php echo amd_e($co['label']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="amend_type"><i class="fas fa-list"></i> نوع التعديل</label>
                        <select name="amend_type" id="amend_type">
                            <?php foreach ($AMD_TYPES as $t): ?>
                                <option value="<?php echo amd_e($t); ?>"><?php echo amd_e($t); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="amend_date"><i class="fas fa-calendar-day"></i> تاريخ التعديل</label>
                        <input type="date" name="amend_date" id="amend_date" />
                    </div>
                    <div>
                        <label for="requested_by"><i class="fas fa-user-tie"></i> الجهة الطالبة</label>
                        <select name="requested_by" id="requested_by">
                            <option value="">-- بدون / غير محدد --</option>
                            <?php foreach ($user_options as $uo): ?>
                                <option value="<?php echo intval($uo['id']); ?>"><?php echo amd_e($uo['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="old_value"><i class="fas fa-arrow-left-long"></i> القيمة قبل</label>
                        <input type="text" name="old_value" id="old_value" placeholder="القيمة قبل التعديل" />
                    </div>
                    <div>
                        <label for="new_value"><i class="fas fa-arrow-right-long"></i> القيمة بعد</label>
                        <input type="text" name="new_value" id="new_value" placeholder="القيمة بعد التعديل" />
                    </div>
                    <div>
                        <label for="effect_price"><i class="fas fa-money-bill-wave"></i> الأثر على السعر</label>
                        <input type="number" step="0.01" name="effect_price" id="effect_price" placeholder="0.00" />
                    </div>
                    <div>
                        <label for="effect_qty"><i class="fas fa-boxes-stacked"></i> الأثر على الكمية</label>
                        <input type="number" step="0.01" name="effect_qty" id="effect_qty" placeholder="0.00" />
                    </div>
                    <div>
                        <label for="effect_duration"><i class="fas fa-calendar-plus"></i> الأثر على المدة (أيام)</label>
                        <input type="number" step="1" name="effect_duration" id="effect_duration" placeholder="0" />
                    </div>
                    <div class="amd-col-full">
                        <label for="reason"><i class="fas fa-comment-dots"></i> سبب التغيير</label>
                        <textarea name="reason" id="reason" rows="2" placeholder="سبب التغيير"></textarea>
                    </div>
                    <div class="amd-col-full">
                        <label for="effect_summary"><i class="fas fa-note-sticky"></i> ملخص الأثر</label>
                        <textarea name="effect_summary" id="effect_summary" rows="2" placeholder="ملخص الأثر"></textarea>
                    </div>
                </div>
                <div class="pu-form-actions">
                    <button type="submit" class="btn-primary"><i class="fas fa-save"></i> <span id="submitBtnText">حفظ الملحق</span></button>
                    <button type="button" id="amdFormCancelBtn" class="btn-secondary"><i class="fas fa-times"></i> إلغاء</button>
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
                <label for="filterType"><i class="fa fa-list"></i> نوع التعديل</label>
                <select id="filterType" class="form-control">
                    <option value="">-- كل الأنواع --</option>
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
                <table id="amdTable" class="display amd-table-nowrap no-datatable" data-state-save="false">
                    <thead>
                        <tr>
                            <th>إجراءات</th>
                            <th width="90">الكود</th>
                            <th>العقد الأصل</th>
                            <th>نوع التعديل</th>
                            <th>تاريخ التوقيع</th>
                            <th>أثر على الكميات</th>
                            <th>أثر على الحصص</th>
                            <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
                            <th class="ems-fn-th" data-fn="1">رقم الملحق</th>
                            <th class="ems-fn-th" data-fn="1">رقم النسخة</th>
                            <th class="ems-fn-th" data-fn="1">تاريخ السريان</th>
                            <th class="ems-fn-th" data-fn="1">البند المعدَّل</th>
                            <th class="ems-fn-th" data-fn="1">القيمة قبل</th>
                            <th class="ems-fn-th" data-fn="1">القيمة بعد</th>
                            <th class="ems-fn-th" data-fn="1">الفرق</th>
                            <th class="ems-fn-th" data-fn="1">مبرر التعديل</th>
                            <th class="ems-fn-th" data-fn="1">وقّعه عنّا</th>
                            <th class="ems-fn-th" data-fn="1">وقّعه العميل</th>
                            <th class="ems-fn-th" data-fn="1">اعتمدته المالية</th>
                            <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
                            <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
                            <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
                            <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
                            <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
                            <th class="ems-gov-th none" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
                            <th class="ems-gov-th none" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
                            <th class="ems-gov-th none" data-gov="idem_key" data-slice="2" title="يمنع وقوع الأثر مرتين بمفتاح مركب">مفتاح منع التكرار</th>
                            <th class="ems-gov-th none" data-gov="reversed_by" data-slice="2" title="مرجع الحركة التي عكسته">معكوس بـ</th>
                            <th class="ems-gov-th none" data-gov="reversal_of" data-slice="2" title="مرجع الحركة التي عكسها">عكس عن</th>
                            <th class="ems-gov-th none" data-gov="impact_grade" data-slice="2" title="مبدئي أم نهائي — فلا يقفل مبدئي ماليًّا">درجة الأثر</th>
                            <th class="ems-gov-th none" data-gov="cost_center" data-slice="3" title="وجهة التحميل">مركز التكلفة</th>
                            <th class="ems-gov-th none" data-gov="fx_rate_source" data-slice="3" title="ما خالف عملة الدفاتر يحمل السعر ومصدره">سعر الصرف ومصدره</th>
                            <th class="ems-gov-th none" data-gov="currency" data-slice="3" title="لا مبلغ بلا عملة">العملة</th>
                            <th class="ems-gov-th none" data-gov="attached_doc" data-slice="3" title="مستند الإثبات المرفق">المستند المرفق</th>
                            </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row):
                            $cid = intval($row['contract_id']);
                            $contract_label = ($cid > 0 && isset($contracts_map[$cid])) ? $contracts_map[$cid] : '';
                            $rby = intval($row['requested_by']);
                            $requested_label = ($rby > 0 && isset($users_map[$rby])) ? $users_map[$rby] : '';
                            $created_label = function_exists('ems_actor_label') ? ems_actor_label($conn, intval($row['created_by'])) : ($row['creator_name'] ?? '');
                            ?>
                            <tr>
                                <td>
                                    <div class="action-btns">
                                        <a href="javascript:void(0)" class="action-btn view viewAmdBtn"
                                            data-id="<?php echo intval($row['id']); ?>"
                                            data-code="<?php echo amd_e($row['amendment_code']); ?>"
                                            data-contract="<?php echo amd_e($contract_label); ?>"
                                            data-type="<?php echo amd_e($row['amend_type']); ?>"
                                            data-date="<?php echo amd_e($row['amend_date']); ?>"
                                            data-requested="<?php echo amd_e($requested_label); ?>"
                                            data-reason="<?php echo amd_e($row['reason']); ?>"
                                            data-old="<?php echo amd_e($row['old_value']); ?>"
                                            data-new="<?php echo amd_e($row['new_value']); ?>"
                                            data-effect-price="<?php echo amd_e(amd_money($row['effect_price'])); ?>"
                                            data-effect-qty="<?php echo amd_e($row['effect_qty']); ?>"
                                            data-effect-duration="<?php echo amd_e($row['effect_duration']); ?>"
                                            data-effect-summary="<?php echo amd_e($row['effect_summary']); ?>"
                                            data-created="<?php echo amd_e($created_label); ?>"
                                            title="عرض التفاصيل"><i class="fas fa-eye"></i></a>
                                        <?php // السجلّ للقراءة فقط (D02): لا تعديلَ/حذفَ يدويّ — العرض وحده ?>
                                    </div>
                                </td>
                                <td><strong class="amd-code-cell"><?php echo amd_e($row['amendment_code']); ?></strong></td>
                                <td><?php echo $contract_label !== '' ? amd_e($contract_label) : '<span class="amd-muted">—</span>'; ?></td>
                                <td><?php echo amd_e($row['amend_type']); ?></td>
                                <td class="amd-num"><?php echo $row['amend_date'] !== null ? amd_e($row['amend_date']) : '<span class="amd-muted">—</span>'; ?></td>
                                <td class="amd-num"><?php echo amd_e(amd_money($row['effect_price'])); ?></td>
                                <td class="amd-num"><?php echo $row['effect_duration'] !== null && $row['effect_duration'] !== '' ? amd_e($row['effect_duration']) . ' يوم' : '<span class="amd-muted">—</span>'; ?></td>
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
        // تهيئةُ الجدول انتقلت إلى المكوّنِ المركزي (ui-unification.js) —
        // وتعطيلُ حفظِ الحالة بقي بسمةِ data-state-save="false" على وسمِ الجدول.
        function bindAmdFilters() {
        const amdTable = $('#amdTable').DataTable();

        function fillFilterOptions(columnIndex, selectId) {
            const select = $(selectId);
            const values = [];
            amdTable.column(columnIndex).data().each(function (value) {
                const text = $('<div>').html(value).text().trim();
                if (text !== '' && values.indexOf(text) === -1) values.push(text);
            });
            values.sort();
            values.forEach(function (val) {
                select.append('<option value="' + val.replace(/"/g, '&quot;') + '">' + val + '</option>');
            });
        }
        fillFilterOptions(3, '#filterType');

        $('#filterType').on('change', function () {
            const value = $.fn.dataTable.util.escapeRegex($(this).val());
            amdTable.column(3).search(value ? '^' + value + '$' : '', true, false).draw();
        });
        }

        // الربطُ بعد تهيئةِ المكوّنِ المركزي للجدول (أو فورًا إن سبقنا)
        if ($.fn.dataTable && $.fn.dataTable.isDataTable('#amdTable')) {
            bindAmdFilters();
        } else {
            $('#amdTable').one('init.dt', bindAmdFilters);
        }
    });

    // ── إظهار/إخفاء الفورم والإحصائيات ──
    const formToggleBtn = $('#toggleForm');
    const amdForm = $('#amdForm');
    const formTitle = $('#formTitle');
    const submitBtnText = $('#submitBtnText');
    const generatedCodeWrapper = $('#generated_code_wrapper');
    const formCancelBtn = $('#amdFormCancelBtn');
    const statsToggleBtn = $('#toggleStats');
    const statsSection = $('#amdStatsSection');

    /**
     * إظهارُ حقلِ الكودِ المولَّد وإخفاؤه.
     *
     * ⚠️ **لا تستعمل `jQuery.hide()` هنا** — `assets/css/ems-forms.css` يحمل:
     *     :is(.allforms, .ems-form) .form-grid > div { display: block !important }
     * والغلافُ ابنٌ مباشرٌ لـ`.form-grid`، فـ`!important` من ورقةِ الأنماطِ تهزم
     * الإخفاءَ السطريَّ **بلا أولوية**: السمةُ تُكتب فعلًا والحقلُ يبقى ظاهرًا، بلا
     * خطأٍ في وحدةِ التحكم ولا سطرٍ في أيِّ سجل. (نظيرُ شاشتَي العملاءِ والمشاريع.)
     */
    function setGeneratedCodeShown(shown) {
        var el = generatedCodeWrapper[0];
        if (!el) { return; }
        if (shown) { el.style.removeProperty('display'); }
        else       { el.style.setProperty('display', 'none', 'important'); }
    }
    function setAddMode() {
        formTitle.text('إضافة ملحق جديد'); submitBtnText.text('حفظ الملحق');
        setGeneratedCodeShown(true);
        // الكودُ المولَّدُ يعود إلى خانتِه كلَّما دخلنا وضعَ الإضافة — ومصدرُه حقلُ
        // العرضِ نفسُه لا نسخةٌ ثانيةٌ منه (مصدرُ حقيقةٍ واحد). و`reset()` يكفي
        // للإلغاء، لكنَّ الانتقالَ من «تعديل» إلى «إضافة» قد يقع بلا reset.
        var genCode = $('#generated_amd_code').val();
        if (genCode) { $('#amendment_code').val(genCode); }
    }
    function setEditMode() { formTitle.text('تعديل الملحق'); submitBtnText.text('تحديث الملحق'); setGeneratedCodeShown(false); }
    function resetForm() { if (!amdForm.length) return; amdForm[0].reset(); $('#amd_id').val(''); setAddMode(); if (window.EmsSelect) EmsSelect.refresh(); }

    function updateFormToggleState(isOpen) {
        if (!formToggleBtn.length) return;
        formToggleBtn.toggleClass('is-active', isOpen);
        formToggleBtn.attr('aria-expanded', isOpen ? 'true' : 'false');
    }
    function updateStatsToggleState(isVisible) {
        if (!statsToggleBtn.length) return;
        statsToggleBtn.toggleClass('is-active', isVisible);
        statsToggleBtn.find('.amd-toggle-stats-text').text('إظهار الإحصائيات');
        const icon = statsToggleBtn.find('i').first();
        icon.toggleClass('fa-chart-pie', isVisible);
        icon.toggleClass('fa-eye', !isVisible);
    }

    setAddMode();
    updateFormToggleState(amdForm.is(':visible'));
    updateStatsToggleState(statsSection.is(':visible'));

    formToggleBtn.on('click', function (e) {
        e.preventDefault();
        if (!amdForm.length) return;
        if (amdForm.is(':visible')) {
            amdForm.stop(true, true).slideUp(250, function () { amdForm.removeClass('allforms-visible'); resetForm(); updateFormToggleState(false); });
        } else {
            resetForm();
            amdForm.addClass('allforms-visible').hide();
            amdForm.stop(true, true).slideDown(250, function () { updateFormToggleState(true); });
        }
    });
    formCancelBtn.on('click', function () {
        if (!amdForm.length || !amdForm.is(':visible')) return;
        amdForm.stop(true, true).slideUp(250, function () { amdForm.removeClass('allforms-visible'); resetForm(); updateFormToggleState(false); });
    });
    statsToggleBtn.on('click', function (e) {
        e.preventDefault();
        if (!statsSection.length) return;
        if (statsSection.is(':visible')) {
            statsSection.stop(true, true).slideUp(250, function () { statsSection.addClass('amd-hidden'); updateStatsToggleState(false); });
        } else {
            statsSection.removeClass('amd-hidden').hide();
            statsSection.stop(true, true).slideDown(250, function () { updateStatsToggleState(true); });
        }
    });

    // ── تعبئة الفورم للتعديل ──
    function fillAmdForm(d) {
        $('#amd_id').val(d.id);
        $('#amendment_code').val(d.code);
        $('#contract_id').val(d.contractId ? String(d.contractId) : '');
        $('#amend_type').val(d.type || 'تجديد');
        $('#amend_date').val(d.date || '');
        $('#requested_by').val(d.requestedId ? String(d.requestedId) : '');
        $('#old_value').val(d.old || '');
        $('#new_value').val(d.new || '');
        $('#effect_price').val((d.effectPrice !== undefined && d.effectPrice !== null && d.effectPrice !== '') ? d.effectPrice : '');
        $('#effect_qty').val((d.effectQty !== undefined && d.effectQty !== null && d.effectQty !== '') ? d.effectQty : '');
        $('#effect_duration').val((d.effectDuration !== undefined && d.effectDuration !== null && d.effectDuration !== '') ? d.effectDuration : '');
        $('#reason').val(d.reason || '');
        $('#effect_summary').val(d.effectSummary || '');
        if (window.EmsSelect) EmsSelect.refresh();
        setEditMode();
        if (!amdForm.is(':visible')) {
            amdForm.addClass('allforms-visible').hide();
            amdForm.stop(true, true).slideDown(250, function () { updateFormToggleState(true); });
        } else { updateFormToggleState(true); }
        $('html, body').animate({ scrollTop: $('#amdForm').offset().top - 100 }, 500);
    }

    $(document).on('click', '.editAmdBtn', function () {
        fillAmdForm({
            id: $(this).data('id'), code: $(this).data('code'), contractId: $(this).data('contract-id'),
            type: $(this).data('type'), date: $(this).data('date'), requestedId: $(this).data('requested-id'),
            old: $(this).data('old'), new: $(this).data('new'),
            effectPrice: $(this).data('effect-price'), effectQty: $(this).data('effect-qty'),
            effectDuration: $(this).data('effect-duration'), reason: $(this).data('reason'),
            effectSummary: $(this).data('effect-summary')
        });
    });

    // ── عرض التفاصيل عبر EmsDetailsModal الموحّد ──
    $(document).on('click', '.viewAmdBtn', function () {
        const d = $(this).data();
        const fields = [
            { label: 'كود الملحق', value: d.code, icon: 'fas fa-barcode' },
            { label: 'العقد المرتبط', value: d.contract || '—', icon: 'fas fa-file-contract', size: 'lg' },
            { label: 'نوع التعديل', value: d.type || '—', icon: 'fas fa-list', type: 'status' },
            { label: 'تاريخ التعديل', value: d.date || '—', icon: 'fas fa-calendar-day' },
            { label: 'الجهة الطالبة', value: d.requested || '—', icon: 'fas fa-user-tie' },
            { label: 'سبب التغيير', value: d.reason || '—', icon: 'fas fa-comment-dots', size: 'lg' },
            { label: 'القيمة قبل', value: d.old || '—', icon: 'fas fa-arrow-left-long' },
            { label: 'القيمة بعد', value: d.new || '—', icon: 'fas fa-arrow-right-long' },
            { label: 'الأثر على السعر', value: d.effectPrice || '—', icon: 'fas fa-money-bill-wave' },
            { label: 'الأثر على الكمية', value: (d.effectQty !== undefined && d.effectQty !== null && d.effectQty !== '') ? d.effectQty : '—', icon: 'fas fa-boxes-stacked' },
            { label: 'الأثر على المدة', value: (d.effectDuration !== undefined && d.effectDuration !== null && d.effectDuration !== '') ? (d.effectDuration + ' يوم') : '—', icon: 'fas fa-calendar-plus' },
            { label: 'ملخص الأثر', value: d.effectSummary || '—', icon: 'fas fa-note-sticky', size: 'lg' },
            { label: 'أضيف بواسطة', value: d.created || '—', icon: 'fas fa-user-plus' }
        ];

        const actions = [];
        <?php if ($can_edit): ?>
            actions.push({ label: 'تعديل الملحق', icon: 'fas fa-edit', variant: 'primary', onClick: function () {
                EmsDetailsModal.close();
                $('.editAmdBtn[data-id="' + d.id + '"]').trigger('click');
            }});
        <?php endif; ?>
        actions.push({ label: 'إغلاق', icon: 'fas fa-times', variant: 'secondary', close: true });

        EmsDetailsModal.open({ title: 'تفاصيل الملحق', icon: 'fas fa-file-pen', fields: fields, actions: actions });
    });
</script>

<?php /* نُقلت أنماطُ هذه الشاشةِ إلى assets/css/ems-screens.css (UXUI-01 البند ٦: صفرُ نمطٍ محليّ) */ ?>

</body>

</html>
