<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}

include '../config.php';
include '../includes/permissions_helper.php';

if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
}

// ══════════════════════════════════════════════════════════════════════════════
// دوال مساعدة
// ══════════════════════════════════════════════════════════════════════════════
if (!function_exists('cmt_e')) {
    function cmt_e($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
if (!function_exists('cmt_num')) {
    function cmt_num($value)
    {
        if ($value === null || $value === '') {
            return '—';
        }
        return number_format((float) $value, 2);
    }
}
if (!function_exists('cmt_redirect_with_msg')) {
    function cmt_redirect_with_msg($msg)
    {
        header('Location: contract_commitments.php?msg=' . urlencode($msg));
        exit();
    }
}

// ══════════════════════════════════════════════════════════════════════════════
// خرائط العرض العربي (القيم تُخزَّن إنجليزيةً في ENUM — العرض عربيٌّ فقط)
// المرجع: INJAZ-S05 §ت.2
// ══════════════════════════════════════════════════════════════════════════════
$CMT_PARTY_SCOPE = array(
    'client'   => 'عميل',
    'supplier' => 'مورد',
);
$CMT_TYPE = array(
    'equipment_count'          => 'عدد المعدات',
    'daily_availability_hours' => 'ساعات الإتاحة اليومية',
    'period_hours'             => 'ساعات المدة',
    'min_guaranteed'           => 'حدٌّ أدنى مضمون',
    'period_qty'               => 'كمية دورية',
    'total_qty'                => 'إجمالي الكمية (السقف)',
    'capacity_support'         => 'طاقة مساندة',
);
$CMT_UNIT = array(
    'hour'  => 'ساعة',
    'ton'   => 'طن',
    'meter' => 'متر',
    'cbm'   => 'متر مكعب',
    'day'   => 'يوم',
    'shift' => 'وردية',
    'trip'  => 'نقلة',
);
$CMT_PERIOD = array(
    'daily'    => 'يومي',
    'monthly'  => 'شهري',
    'contract' => 'كامل العقد',
);
$CMT_OBLIGED = array(
    'company'  => 'الشركة',
    'client'   => 'العميل',
    'supplier' => 'المورد',
);
$CMT_SHORTFALL = array(
    'invoice_actual' => 'فوترة المنجَز',
    'penalty'        => 'غرامة',
    'carry_over'     => 'ترحيل',
    'extend_term'    => 'تمديد المدة',
    'waive_if_client' => 'إعفاء إن كان على العميل',
    'negotiate'      => 'تفاوض',
);
$CMT_SURPLUS = array(
    'same_price'     => 'نفس السعر',
    'different_price' => 'سعر مختلف',
    'pre_approval'   => 'موافقة مسبقة',
    'open'           => 'مفتوح',
    'not_billable'   => 'غير قابلة للفوترة',
);

// ══════════════════════════════════════════════════════════════════════════════
// التحقق من معرف الشركة (عزل الشركات)
// ══════════════════════════════════════════════════════════════════════════════
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
if ($company_id <= 0) {
    header('Location: ../login.php?msg=' . urlencode('الحساب غير مرتبط بشركة.'));
    exit();
}

// العزل عبر بوابة المستأجر — النطاق والحذف الناعم مسؤولية البوابة
$cmt_gate = ems_tenant_db();

// رمز CSRF
if (empty($_SESSION['cmt_csrf_token'])) {
    $_SESSION['cmt_csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
}
$cmt_csrf_token = $_SESSION['cmt_csrf_token'];

// توليد الكود المقترح التالي (CMT-NNNN) — للعرض فقط
$next_cmt_code = 'CMT-0001';
try {
    $last_code_rows = $cmt_gate->scopedQuery(array(
        'scope' => array('c' => 'contract_commitments'),
    ), "SELECT c.commitment_code FROM contract_commitments c
        WHERE {TENANT_SCOPE} AND c.commitment_code REGEXP '^CMT-[0-9]+$' AND c.is_deleted = 0
        ORDER BY CAST(SUBSTRING(c.commitment_code, 5) AS UNSIGNED) DESC LIMIT 1");
} catch (\Throwable $t) {
    $last_code_rows = array();
}
if (!empty($last_code_rows)) {
    $last_num = intval(substr($last_code_rows[0]['commitment_code'], 4));
    $next_cmt_code = 'CMT-' . str_pad($last_num + 1, 4, '0', STR_PAD_LEFT);
}

// صلاحيات المستخدم على وحدة التزامات العقد (modules جدول عام — قراءة عبر البوابة)
try {
    $module_info = $cmt_gate->selectOne('modules', array(
        'columns' => array('id'),
        'where'   => array('code' => 'Clients/contract_commitments.php'),
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
    header('Location: ../login.php?msg=' . urlencode('لا توجد صلاحية عرض التزامات العقد ❌'));
    exit();
}

// ══════════════════════════════════════════════════════════════════════════════
// معالجة إضافة / تعديل التزام عبر POST
// ══════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['commitment_code'])) {
    $posted_csrf = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if (empty($posted_csrf) || !hash_equals($cmt_csrf_token, $posted_csrf)) {
        cmt_redirect_with_msg('جلسة النموذج غير صالحة، يرجى إعادة المحاولة ❌');
    }

    $cmt_id     = isset($_POST['cmt_id']) ? intval($_POST['cmt_id']) : 0;
    $is_editing = $cmt_id > 0;

    if ($is_editing && !$can_edit) {
        cmt_redirect_with_msg('لا توجد صلاحية تعديل الالتزامات ❌');
    } elseif (!$is_editing && !$can_add) {
        cmt_redirect_with_msg('لا توجد صلاحية إضافة التزامات جديدة ❌');
    }

    // الكود
    $cmt_code_raw = isset($_POST['commitment_code']) ? trim($_POST['commitment_code']) : '';
    if ($cmt_code_raw === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $cmt_code_raw)) {
        cmt_redirect_with_msg('كود الالتزام غير صالح. استخدم أحرفًا وأرقامًا و - أو _ فقط ❌');
    }

    // التحقق من قيم ENUM مقابل القوائم البيضاء
    $party_scope_raw = isset($_POST['party_scope']) ? trim($_POST['party_scope']) : '';
    if (!isset($CMT_PARTY_SCOPE[$party_scope_raw])) {
        cmt_redirect_with_msg('الطرف الملتزَم غير صالح ❌');
    }
    $commitment_type_raw = isset($_POST['commitment_type']) ? trim($_POST['commitment_type']) : '';
    if (!isset($CMT_TYPE[$commitment_type_raw])) {
        cmt_redirect_with_msg('نوع الالتزام غير صالح ❌');
    }
    $unit_type_raw = isset($_POST['unit_type']) ? trim($_POST['unit_type']) : '';
    $unit_type_val = ($unit_type_raw !== '' && isset($CMT_UNIT[$unit_type_raw])) ? $unit_type_raw : null;
    $period_raw = isset($_POST['period']) ? trim($_POST['period']) : '';
    if (!isset($CMT_PERIOD[$period_raw])) {
        cmt_redirect_with_msg('الدورية غير صالحة ❌');
    }
    $obliged_raw = isset($_POST['obliged_party']) ? trim($_POST['obliged_party']) : '';
    if (!isset($CMT_OBLIGED[$obliged_raw])) {
        cmt_redirect_with_msg('الجهة الملتزمة غير صالحة ❌');
    }
    $shortfall_raw = isset($_POST['shortfall_rule']) ? trim($_POST['shortfall_rule']) : '';
    if (!isset($CMT_SHORTFALL[$shortfall_raw])) {
        cmt_redirect_with_msg('حكم العجز غير صالح ❌');
    }
    $surplus_raw = isset($_POST['surplus_rule']) ? trim($_POST['surplus_rule']) : '';
    if (!isset($CMT_SURPLUS[$surplus_raw])) {
        cmt_redirect_with_msg('حكم الزيادة غير صالح ❌');
    }

    // العقد المرتبط — إلزاميٌّ في الالتزام (§ت.2: الالتزامُ لعقدٍ بعينه) + تحقق النطاق عبر البوابة
    $contract_in = isset($_POST['contract_ref']) ? intval($_POST['contract_ref']) : 0;
    if ($contract_in <= 0) {
        cmt_redirect_with_msg('العقد المرتبط إلزامي لكل التزام ❌');
    }
    try {
        $cchk = $cmt_gate->selectOne('contracts', array(
            'columns' => array('id'), 'where' => array('id' => $contract_in),
        ));
    } catch (\Throwable $t) { $cchk = null; }
    if (!$cchk) {
        cmt_redirect_with_msg('العقد المرتبط غير موجود أو خارج نطاق شركتك ❌');
    }
    $contract_val = $contract_in;

    // الكمية والملاحظة
    $qty_raw = isset($_POST['qty']) ? trim($_POST['qty']) : '';
    $qty_val = $qty_raw === '' ? 0.0 : (float) $qty_raw;
    if ($qty_val < 0) {
        cmt_redirect_with_msg('الكمية لا يمكن أن تكون سالبة ❌');
    }
    $note_raw = isset($_POST['note']) ? trim($_POST['note']) : '';
    if (mb_strlen($note_raw) > 160) {
        $note_raw = mb_substr($note_raw, 0, 160);
    }
    $created_by = intval($_SESSION['user']['id']);

    if ($is_editing) {
        try {
            $owner = $cmt_gate->selectOne('contract_commitments', array(
                'columns' => array('id'), 'where' => array('id' => $cmt_id),
            ));
        } catch (\Throwable $t) { $owner = null; }
        if (!$owner) {
            cmt_redirect_with_msg('لا يمكنك تعديل التزام لا يتبع لشركتك ❌');
        }
        try {
            $dup = $cmt_gate->scopedQuery(array(
                'scope' => array('c' => 'contract_commitments'),
            ), "SELECT c.id FROM contract_commitments c
                WHERE {TENANT_SCOPE} AND c.commitment_code = ? AND c.id != ? AND c.is_deleted = 0",
                array($cmt_code_raw, $cmt_id));
        } catch (\Throwable $t) { $dup = array(); }
        if (!empty($dup)) {
            cmt_redirect_with_msg('كود الالتزام موجود مسبقاً داخل شركتك ❌');
        }

        try {
            $cmt_gate->update('contract_commitments', array(
                'commitment_code' => $cmt_code_raw,
                'party_scope'     => $party_scope_raw,
                'contract_ref'    => $contract_val,
                'commitment_type' => $commitment_type_raw,
                'unit_type'       => $unit_type_val,
                'qty'             => $qty_val,
                'period'          => $period_raw,
                'obliged_party'   => $obliged_raw,
                'shortfall_rule'  => $shortfall_raw,
                'surplus_rule'    => $surplus_raw,
                'note'            => $note_raw,
            ), array('id' => $cmt_id), 'is_deleted = 0');
            if (class_exists('\\App\\Services\\ActivityLogService')) {
                \App\Services\ActivityLogService::logUpdate('contract_commitments', 'contract_commitments', $cmt_id, null, ['commitment_code' => $cmt_code_raw]);
            }
            cmt_redirect_with_msg('تم تعديل الالتزام بنجاح ✅');
        } catch (\Throwable $t) {
            error_log('contract_commitments.php update failed: ' . $t->getMessage());
            cmt_redirect_with_msg('حدث خطأ أثناء التعديل ❌');
        }
    } else {
        try {
            $dup = $cmt_gate->scopedQuery(array(
                'scope' => array('c' => 'contract_commitments'),
            ), "SELECT c.id FROM contract_commitments c
                WHERE {TENANT_SCOPE} AND c.commitment_code = ? AND c.is_deleted = 0",
                array($cmt_code_raw));
        } catch (\Throwable $t) { $dup = array(); }
        if (!empty($dup)) {
            cmt_redirect_with_msg('كود الالتزام موجود مسبقاً داخل شركتك ❌');
        }

        try {
            $new_id = (int) $cmt_gate->insert('contract_commitments', array(
                'commitment_code' => $cmt_code_raw,
                'party_scope'     => $party_scope_raw,
                'contract_ref'    => $contract_val,
                'commitment_type' => $commitment_type_raw,
                'unit_type'       => $unit_type_val,
                'qty'             => $qty_val,
                'period'          => $period_raw,
                'obliged_party'   => $obliged_raw,
                'shortfall_rule'  => $shortfall_raw,
                'surplus_rule'    => $surplus_raw,
                'note'            => $note_raw,
                'created_by'      => $created_by,
            ));
            if (class_exists('\\App\\Services\\ActivityLogService')) {
                \App\Services\ActivityLogService::logCreate('contract_commitments', 'contract_commitments', $new_id, ['commitment_code' => $cmt_code_raw]);
            }
            cmt_redirect_with_msg('تم إضافة الالتزام بنجاح ✅');
        } catch (\Throwable $t) {
            error_log('contract_commitments.php insert failed: ' . $t->getMessage());
            cmt_redirect_with_msg('حدث خطأ أثناء الإضافة ❌');
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
        cmt_redirect_with_msg('لا توجد صلاحية حذف الالتزامات ❌');
    }
    if (empty($delete_csrf) || !hash_equals($cmt_csrf_token, $delete_csrf)) {
        cmt_redirect_with_msg('جلسة الحذف غير صالحة، يرجى إعادة المحاولة ❌');
    }
    try {
        $chk = $cmt_gate->selectOne('contract_commitments', array(
            'columns' => array('id'), 'where' => array('id' => $delete_id),
        ));
    } catch (\Throwable $t) { $chk = null; }
    if (!$chk) {
        cmt_redirect_with_msg('لا يمكنك حذف التزام لا يتبع لشركتك ❌');
    }
    try {
        $cmt_gate->softDelete('contract_commitments', $delete_id); // يختم deleted_at/deleted_by من السياق
        if (class_exists('\\App\\Services\\ActivityLogService')) {
            \App\Services\ActivityLogService::logDelete('contract_commitments', 'contract_commitments', $delete_id);
        }
        cmt_redirect_with_msg('تم حذف الالتزام بنجاح ✅');
    } catch (\Throwable $t) {
        error_log('contract_commitments.php soft delete failed: ' . $t->getMessage());
        cmt_redirect_with_msg('حدث خطأ أثناء الحذف ❌');
    }
}

// ══════════════════════════════════════════════════════════════════════════════
// قوائم الاختيار (ضمن نطاق الشركة — العزل عبر البوابة)
// ══════════════════════════════════════════════════════════════════════════════
$contract_options = array();
$contracts_map = array();
try {
    $c_rows = $cmt_gate->scopedQuery(array(
        'scope'  => array('c' => 'contracts'),
        'enrich' => array('p' => 'project'), // اسم المشروع — LEFT بلا تنطيق
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
// جلب الالتزامات + الإحصائيات
// ══════════════════════════════════════════════════════════════════════════════
$rows = array();
$stat_total = 0;
$stat_client = 0;
$stat_supplier = 0;
$stat_min_guar = 0;

try {
    $cmt_list = $cmt_gate->scopedQuery(array(
        'scope'  => array('c' => 'contract_commitments'),
        'enrich' => array('u' => 'users'), // إثراء اسم المُنشئ — LEFT بلا تنطيق
    ), "SELECT c.*, u.name AS creator_name
        FROM contract_commitments c
        LEFT JOIN users u ON u.id = c.created_by
        WHERE {TENANT_SCOPE} AND c.is_deleted = 0
        ORDER BY c.id DESC");
} catch (\Throwable $t) {
    $cmt_list = array();
    error_log('contract_commitments.php list load: ' . $t->getMessage());
}
foreach ($cmt_list as $row) {
    $rows[] = $row;
    $stat_total++;
    if ($row['party_scope'] === 'client') $stat_client++;
    if ($row['party_scope'] === 'supplier') $stat_supplier++;
    if ($row['commitment_type'] === 'min_guaranteed') $stat_min_guar++;
}

$page_title = "التزامات العقد";
include("../inheader.php");
include('../insidebar.php');
?>

<div class="main cmt-main ems-unified-page-shell">

    <?php
    $header_title = 'التزامات العقد';
    $header_icon = 'fas fa-handshake';
    $header_actions = array();
    if ($can_add) {
        $header_actions[] = array('id' => 'toggleForm', 'class' => 'add-btn', 'icon' => 'fa fa-solid fa-plus', 'label' => '', 'label_class' => 'cmt-toggle-form-text');
    } else {
        $header_actions[] = array('tag' => 'button', 'class' => '', 'disabled' => true, 'icon' => 'fas fa-plus-circle', 'label' => 'إضافة (بدون صلاحيات)');
    }
    $header_actions[] = array('id' => 'toggleStats', 'class' => 'btn', 'title' => 'إظهار أو إخفاء الإحصائيات', 'icon' => 'fas fa-eye', 'label' => 'إظهار الإحصائيات', 'label_class' => 'cmt-toggle-stats-text');
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fa-solid fa-share', 'label' => '');
    include('../includes/page_header.php');
    ?>

    <?php if (!empty($_GET['msg'])):
        $isSuccess = strpos($_GET['msg'], '✅') !== false; ?>
        <div class="success-message <?= $isSuccess ? 'is-success' : 'is-error' ?>">
            <i class="fas <?= $isSuccess ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?php echo cmt_e($_GET['msg']); ?>
        </div>
    <?php endif; ?>

    <div class="stats-section cmt-hidden" id="cmtStatsSection">
        <div class="stats-grid">
            <div class="stats-card">
                <div class="stats-icon"><i class="fas fa-handshake"></i></div>
                <div class="stats-value"><?php echo $stat_total; ?></div>
                <div class="stats-title">إجمالي الالتزامات</div>
            </div>
            <div class="stats-card">
                <div class="stats-icon"><i class="fas fa-user-tie"></i></div>
                <div class="stats-value"><?php echo $stat_client; ?></div>
                <div class="stats-title">التزامات تجاه العميل</div>
            </div>
            <div class="stats-card">
                <div class="stats-icon"><i class="fas fa-truck-field"></i></div>
                <div class="stats-value"><?php echo $stat_supplier; ?></div>
                <div class="stats-title">التزامات تجاه المورد</div>
            </div>
            <div class="stats-card">
                <div class="stats-icon"><i class="fas fa-shield-halved"></i></div>
                <div class="stats-value"><?php echo $stat_min_guar; ?></div>
                <div class="stats-title">حدٌّ أدنى مضمون</div>
            </div>
        </div>
    </div>

    <!-- فورم إضافة / تعديل التزام -->
    <form id="cmtForm" action="" method="post" class="allforms">
        <div class="card-header">
            <h5><i class="fas fa-edit"></i> <span id="formTitle">إضافة التزام جديد</span></h5>
        </div>
        <input type="hidden" name="cmt_id" id="cmt_id" value="">
        <input type="hidden" name="csrf_token" value="<?php echo cmt_e($cmt_csrf_token); ?>">
        <div class="card shadow-sm pu-form-card">
            <div class="card-body">
                <div class="form-grid">
                    <div id="generated_code_wrapper" class="auto">
                        <label><i class="fas fa-magic"></i> كود الالتزام المولد <i class="fas fa-info-circle cmt-info-icon"></i></label>
                        <input type="text" id="generated_cmt_code" class="generated-code-field" value="<?php echo cmt_e($next_cmt_code); ?>" readonly tabindex="-1" title="هذا الكود للعرض فقط، انسخه إلى حقل كود الالتزام" />
                        <div class="generated-code-hint"></div>
                    </div>

                    <div>
                        <label><i class="fas fa-barcode"></i> كود الالتزام *</label>
                        <input type="text" name="commitment_code" id="commitment_code" placeholder="مثال: CMT-001" required pattern="[A-Za-z0-9-_]+" />
                    </div>
                    <div>
                        <label><i class="fas fa-file-contract"></i> العقد المرتبط *</label>
                        <select name="contract_ref" id="contract_ref" required>
                            <option value="">-- اختر العقد --</option>
                            <?php foreach ($contract_options as $co): ?>
                                <option value="<?php echo intval($co['id']); ?>"><?php echo cmt_e($co['label']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label><i class="fas fa-user-group"></i> الطرف الملتزَم له</label>
                        <select name="party_scope" id="party_scope">
                            <?php foreach ($CMT_PARTY_SCOPE as $k => $v): ?>
                                <option value="<?php echo cmt_e($k); ?>"><?php echo cmt_e($v); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label><i class="fas fa-list-check"></i> نوع الالتزام</label>
                        <select name="commitment_type" id="commitment_type">
                            <?php foreach ($CMT_TYPE as $k => $v): ?>
                                <option value="<?php echo cmt_e($k); ?>"><?php echo cmt_e($v); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label><i class="fas fa-ruler"></i> وحدة القياس</label>
                        <select name="unit_type" id="unit_type">
                            <option value="">-- بدون / غير محدد --</option>
                            <?php foreach ($CMT_UNIT as $k => $v): ?>
                                <option value="<?php echo cmt_e($k); ?>"><?php echo cmt_e($v); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label><i class="fas fa-hashtag"></i> الكمية</label>
                        <input type="number" step="0.01" min="0" name="qty" id="qty" placeholder="0.00" />
                    </div>
                    <div>
                        <label><i class="fas fa-calendar-week"></i> الدورية</label>
                        <select name="period" id="period">
                            <?php foreach ($CMT_PERIOD as $k => $v): $sel = $k === 'monthly' ? 'selected' : ''; ?>
                                <option value="<?php echo cmt_e($k); ?>" <?php echo $sel; ?>><?php echo cmt_e($v); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label><i class="fas fa-user-shield"></i> الجهة الملتزِمة</label>
                        <select name="obliged_party" id="obliged_party">
                            <?php foreach ($CMT_OBLIGED as $k => $v): ?>
                                <option value="<?php echo cmt_e($k); ?>"><?php echo cmt_e($v); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label><i class="fas fa-arrow-trend-down"></i> حكم العجز</label>
                        <select name="shortfall_rule" id="shortfall_rule">
                            <?php foreach ($CMT_SHORTFALL as $k => $v): ?>
                                <option value="<?php echo cmt_e($k); ?>"><?php echo cmt_e($v); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label><i class="fas fa-arrow-trend-up"></i> حكم الزيادة</label>
                        <select name="surplus_rule" id="surplus_rule">
                            <?php foreach ($CMT_SURPLUS as $k => $v): ?>
                                <option value="<?php echo cmt_e($k); ?>"><?php echo cmt_e($v); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="cmt-col-full">
                        <label><i class="fas fa-note-sticky"></i> ملاحظة (بند العقد / سبب الحكم)</label>
                        <textarea name="note" id="note" rows="2" maxlength="160" placeholder="بندُ العقد أو سببُ الحكم (حتى 160 حرفًا)"></textarea>
                    </div>
                </div>
                <div class="pu-form-actions">
                    <button type="submit" class="btn-submit"><i class="fas fa-save"></i> <span id="submitBtnText">حفظ الالتزام</span></button>
                    <button type="button" id="cmtFormCancelBtn" class="btn-cancel"><i class="fas fa-times"></i> إلغاء</button>
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
                <label><i class="fa fa-user-group"></i> الطرف</label>
                <select id="filterParty" class="form-control">
                    <option value="">-- كل الأطراف --</option>
                </select>
            </div>
            <div class="filter-field">
                <label><i class="fa fa-list-check"></i> نوع الالتزام</label>
                <select id="filterType" class="form-control">
                    <option value="">-- كل الأنواع --</option>
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
                <table id="cmtTable" class="display cmt-table-nowrap no-datatable">
                    <thead>
                        <tr>
                            <th>إجراءات</th>
                            <th width="90">الكود</th>
                            <th>العقد</th>
                            <th>الطرف</th>
                            <th>نوع الالتزام</th>
                            <th>الوحدة</th>
                            <th>الكمية</th>
                            <th>الدورية</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row):
                            $cid = intval($row['contract_ref']);
                            $contract_label = ($cid > 0 && isset($contracts_map[$cid])) ? $contracts_map[$cid] : '';
                            $party_label = isset($CMT_PARTY_SCOPE[$row['party_scope']]) ? $CMT_PARTY_SCOPE[$row['party_scope']] : $row['party_scope'];
                            $type_label = isset($CMT_TYPE[$row['commitment_type']]) ? $CMT_TYPE[$row['commitment_type']] : $row['commitment_type'];
                            $unit_label = ($row['unit_type'] !== null && isset($CMT_UNIT[$row['unit_type']])) ? $CMT_UNIT[$row['unit_type']] : '';
                            $period_label = isset($CMT_PERIOD[$row['period']]) ? $CMT_PERIOD[$row['period']] : $row['period'];
                            $obliged_label = isset($CMT_OBLIGED[$row['obliged_party']]) ? $CMT_OBLIGED[$row['obliged_party']] : $row['obliged_party'];
                            $shortfall_label = isset($CMT_SHORTFALL[$row['shortfall_rule']]) ? $CMT_SHORTFALL[$row['shortfall_rule']] : $row['shortfall_rule'];
                            $surplus_label = isset($CMT_SURPLUS[$row['surplus_rule']]) ? $CMT_SURPLUS[$row['surplus_rule']] : $row['surplus_rule'];
                            $created_label = function_exists('ems_actor_label') ? ems_actor_label($conn, intval($row['created_by'])) : ($row['creator_name'] ?? '');
                            ?>
                            <tr>
                                <td>
                                    <div class="action-btns">
                                        <a href="javascript:void(0)" class="action-btn view viewCmtBtn"
                                            data-id="<?php echo intval($row['id']); ?>"
                                            data-code="<?php echo cmt_e($row['commitment_code']); ?>"
                                            data-contract="<?php echo cmt_e($contract_label); ?>"
                                            data-party="<?php echo cmt_e($party_label); ?>"
                                            data-type="<?php echo cmt_e($type_label); ?>"
                                            data-unit="<?php echo cmt_e($unit_label); ?>"
                                            data-qty="<?php echo cmt_e(cmt_num($row['qty'])); ?>"
                                            data-period="<?php echo cmt_e($period_label); ?>"
                                            data-obliged="<?php echo cmt_e($obliged_label); ?>"
                                            data-shortfall="<?php echo cmt_e($shortfall_label); ?>"
                                            data-surplus="<?php echo cmt_e($surplus_label); ?>"
                                            data-note="<?php echo cmt_e($row['note']); ?>"
                                            data-created="<?php echo cmt_e($created_label); ?>"
                                            title="عرض التفاصيل"><i class="fas fa-eye"></i></a>
                                        <?php if ($can_edit): ?>
                                            <a href="javascript:void(0)" class="action-btn edit editCmtBtn"
                                                data-id="<?php echo intval($row['id']); ?>"
                                                data-code="<?php echo cmt_e($row['commitment_code']); ?>"
                                                data-contract-id="<?php echo intval($row['contract_ref']); ?>"
                                                data-party="<?php echo cmt_e($row['party_scope']); ?>"
                                                data-type="<?php echo cmt_e($row['commitment_type']); ?>"
                                                data-unit="<?php echo cmt_e($row['unit_type']); ?>"
                                                data-qty="<?php echo cmt_e($row['qty']); ?>"
                                                data-period="<?php echo cmt_e($row['period']); ?>"
                                                data-obliged="<?php echo cmt_e($row['obliged_party']); ?>"
                                                data-shortfall="<?php echo cmt_e($row['shortfall_rule']); ?>"
                                                data-surplus="<?php echo cmt_e($row['surplus_rule']); ?>"
                                                data-note="<?php echo cmt_e($row['note']); ?>"
                                                title="تعديل"><i class="fas fa-edit"></i></a>
                                        <?php endif; ?>
                                        <?php if ($can_delete): ?>
                                            <a href="?delete_id=<?php echo urlencode($row['id']); ?>&csrf_token=<?php echo urlencode($cmt_csrf_token); ?>"
                                                class="action-btn delete"
                                                onclick="return confirm('هل أنت متأكد من حذف هذا الالتزام؟')" title="حذف"><i class="fas fa-trash-alt"></i></a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><strong class="cmt-code-cell"><?php echo cmt_e($row['commitment_code']); ?></strong></td>
                                <td><?php echo $contract_label !== '' ? cmt_e($contract_label) : '<span class="cmt-muted">—</span>'; ?></td>
                                <td><?php echo cmt_e($party_label); ?></td>
                                <td><?php echo cmt_e($type_label); ?></td>
                                <td><?php echo $unit_label !== '' ? cmt_e($unit_label) : '<span class="cmt-muted">—</span>'; ?></td>
                                <td class="cmt-num"><?php echo cmt_e(cmt_num($row['qty'])); ?></td>
                                <td><?php echo cmt_e($period_label); ?></td>
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
        const cmtTable = $('#cmtTable').DataTable({
            autoWidth: false,
            stateSave: false,
            language: { url: '/ems/assets/i18n/datatables/ar.json' }
        });

        function fillFilterOptions(columnIndex, selectId) {
            const select = $(selectId);
            const values = [];
            cmtTable.column(columnIndex).data().each(function (value) {
                const text = $('<div>').html(value).text().trim();
                if (text !== '' && text !== '—' && values.indexOf(text) === -1) values.push(text);
            });
            values.sort();
            values.forEach(function (val) {
                select.append('<option value="' + val.replace(/"/g, '&quot;') + '">' + val + '</option>');
            });
        }
        fillFilterOptions(3, '#filterParty');
        fillFilterOptions(4, '#filterType');

        $('#filterParty').on('change', function () {
            const value = $.fn.dataTable.util.escapeRegex($(this).val());
            cmtTable.column(3).search(value ? '^' + value + '$' : '', true, false).draw();
        });
        $('#filterType').on('change', function () {
            const value = $.fn.dataTable.util.escapeRegex($(this).val());
            cmtTable.column(4).search(value ? '^' + value + '$' : '', true, false).draw();
        });
    });

    // ── إظهار/إخفاء الفورم والإحصائيات ──
    const formToggleBtn = $('#toggleForm');
    const cmtForm = $('#cmtForm');
    const formTitle = $('#formTitle');
    const submitBtnText = $('#submitBtnText');
    const generatedCodeWrapper = $('#generated_code_wrapper');
    const formCancelBtn = $('#cmtFormCancelBtn');
    const statsToggleBtn = $('#toggleStats');
    const statsSection = $('#cmtStatsSection');

    function setAddMode() { formTitle.text('إضافة التزام جديد'); submitBtnText.text('حفظ الالتزام'); generatedCodeWrapper.show(); }
    function setEditMode() { formTitle.text('تعديل الالتزام'); submitBtnText.text('تحديث الالتزام'); generatedCodeWrapper.hide(); }
    function resetForm() { if (!cmtForm.length) return; cmtForm[0].reset(); $('#cmt_id').val(''); setAddMode(); if (window.EmsSelect) EmsSelect.refresh(); }

    function updateFormToggleState(isOpen) {
        if (!formToggleBtn.length) return;
        formToggleBtn.toggleClass('is-active', isOpen);
        formToggleBtn.attr('aria-expanded', isOpen ? 'true' : 'false');
    }
    function updateStatsToggleState(isVisible) {
        if (!statsToggleBtn.length) return;
        statsToggleBtn.toggleClass('is-active', isVisible);
        statsToggleBtn.find('.cmt-toggle-stats-text').text('إظهار الإحصائيات');
        const icon = statsToggleBtn.find('i').first();
        icon.toggleClass('fa-chart-pie', isVisible);
        icon.toggleClass('fa-eye', !isVisible);
    }

    setAddMode();
    updateFormToggleState(cmtForm.is(':visible'));
    updateStatsToggleState(statsSection.is(':visible'));

    formToggleBtn.on('click', function (e) {
        e.preventDefault();
        if (!cmtForm.length) return;
        if (cmtForm.is(':visible')) {
            cmtForm.stop(true, true).slideUp(250, function () { cmtForm.removeClass('allforms-visible'); resetForm(); updateFormToggleState(false); });
        } else {
            resetForm();
            cmtForm.addClass('allforms-visible').hide();
            cmtForm.stop(true, true).slideDown(250, function () { updateFormToggleState(true); });
        }
    });
    formCancelBtn.on('click', function () {
        if (!cmtForm.length || !cmtForm.is(':visible')) return;
        cmtForm.stop(true, true).slideUp(250, function () { cmtForm.removeClass('allforms-visible'); resetForm(); updateFormToggleState(false); });
    });
    statsToggleBtn.on('click', function (e) {
        e.preventDefault();
        if (!statsSection.length) return;
        if (statsSection.is(':visible')) {
            statsSection.stop(true, true).slideUp(250, function () { statsSection.addClass('cmt-hidden'); updateStatsToggleState(false); });
        } else {
            statsSection.removeClass('cmt-hidden').hide();
            statsSection.stop(true, true).slideDown(250, function () { updateStatsToggleState(true); });
        }
    });

    // ── تعبئة الفورم للتعديل ──
    function fillCmtForm(d) {
        $('#cmt_id').val(d.id);
        $('#commitment_code').val(d.code);
        $('#contract_ref').val(d.contractId ? String(d.contractId) : '');
        $('#party_scope').val(d.party || 'client');
        $('#commitment_type').val(d.type || 'total_qty');
        $('#unit_type').val(d.unit || '');
        $('#qty').val((d.qty !== undefined && d.qty !== null && d.qty !== '') ? d.qty : '');
        $('#period').val(d.period || 'monthly');
        $('#obliged_party').val(d.obliged || 'company');
        $('#shortfall_rule').val(d.shortfall || 'invoice_actual');
        $('#surplus_rule').val(d.surplus || 'same_price');
        $('#note').val(d.note || '');
        if (window.EmsSelect) EmsSelect.refresh();
        setEditMode();
        if (!cmtForm.is(':visible')) {
            cmtForm.addClass('allforms-visible').hide();
            cmtForm.stop(true, true).slideDown(250, function () { updateFormToggleState(true); });
        } else { updateFormToggleState(true); }
        $('html, body').animate({ scrollTop: $('#cmtForm').offset().top - 100 }, 500);
    }

    $(document).on('click', '.editCmtBtn', function () {
        fillCmtForm({
            id: $(this).data('id'), code: $(this).data('code'), contractId: $(this).data('contract-id'),
            party: $(this).data('party'), type: $(this).data('type'), unit: $(this).data('unit'),
            qty: $(this).data('qty'), period: $(this).data('period'), obliged: $(this).data('obliged'),
            shortfall: $(this).data('shortfall'), surplus: $(this).data('surplus'), note: $(this).data('note')
        });
    });

    // ── عرض التفاصيل عبر EmsDetailsModal الموحّد ──
    $(document).on('click', '.viewCmtBtn', function () {
        const d = $(this).data();
        const fields = [
            { label: 'كود الالتزام', value: d.code, icon: 'fas fa-barcode' },
            { label: 'العقد المرتبط', value: d.contract || '—', icon: 'fas fa-file-contract', size: 'lg' },
            { label: 'الطرف الملتزَم له', value: d.party || '—', icon: 'fas fa-user-group', type: 'status' },
            { label: 'نوع الالتزام', value: d.type || '—', icon: 'fas fa-list-check' },
            { label: 'وحدة القياس', value: d.unit || '—', icon: 'fas fa-ruler' },
            { label: 'الكمية', value: (d.qty !== undefined && d.qty !== null && d.qty !== '') ? d.qty : '—', icon: 'fas fa-hashtag' },
            { label: 'الدورية', value: d.period || '—', icon: 'fas fa-calendar-week' },
            { label: 'الجهة الملتزِمة', value: d.obliged || '—', icon: 'fas fa-user-shield' },
            { label: 'حكم العجز', value: d.shortfall || '—', icon: 'fas fa-arrow-trend-down' },
            { label: 'حكم الزيادة', value: d.surplus || '—', icon: 'fas fa-arrow-trend-up' },
            { label: 'ملاحظة', value: d.note || '—', icon: 'fas fa-note-sticky', size: 'lg' },
            { label: 'أضيف بواسطة', value: d.created || '—', icon: 'fas fa-user-plus' }
        ];

        const actions = [];
        <?php if ($can_edit): ?>
            actions.push({ label: 'تعديل الالتزام', icon: 'fas fa-edit', variant: 'primary', onClick: function () {
                EmsDetailsModal.close();
                $('.editCmtBtn[data-id="' + d.id + '"]').trigger('click');
            }});
        <?php endif; ?>
        actions.push({ label: 'إغلاق', icon: 'fas fa-times', variant: 'secondary', close: true });

        EmsDetailsModal.open({ title: 'تفاصيل الالتزام', icon: 'fas fa-handshake', fields: fields, actions: actions });
    });
</script>

<style>
    .cmt-main .stats-grid { display: grid; grid-template-columns: repeat(4, minmax(170px, 1fr)); gap: 12px; }
    .cmt-main .stats-section {
        border: 1px solid var(--bdr); border-radius: var(--rl);
        background: linear-gradient(180deg, rgba(255,255,255,.95) 0%, var(--s2) 100%);
        box-shadow: var(--sh); padding: 14px; margin-bottom: 14px;
    }
    .cmt-main .stats-card { background: #eee; border: 1px solid #aaa; border-radius: 35px; padding: 18px; box-shadow: 0 2px 8px rgba(26,18,8,.07); position: relative; overflow: hidden; }
    .cmt-main .stats-card .stats-icon { width: 55px; height: 55px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.3rem; margin-bottom: 10px; float: left; margin-top: 15px; border: 1px solid #999; background:#fff; color:#000; }
    .cmt-main .stats-card .stats-title { color: #555; font-size: .92rem; font-weight: 700; margin-top: 5px; line-height: 1.3; }
    .cmt-main .stats-card .stats-value { color: #222; line-height: 1; font-weight: 900; font-variant-numeric: tabular-nums; margin-top: 10px; font-size: 30px; }
    @media (max-width: 900px) { .cmt-main .stats-grid { grid-template-columns: repeat(2, minmax(150px,1fr)); } }
    @media (max-width: 560px) { .cmt-main .stats-grid { grid-template-columns: 1fr; } }

    .cmt-main .cmt-hidden { display: none; }
    .cmt-main .cmt-col-full { grid-column: 1 / -1; }
    .cmt-main .table-container { overflow-x: auto; }
    #cmtTable.cmt-table-nowrap, #cmtTable.cmt-table-nowrap th, #cmtTable.cmt-table-nowrap td { white-space: nowrap; }
    #cmtTable .action-btns { flex-wrap: nowrap; white-space: nowrap; }
    .cmt-main .cmt-num { font-variant-numeric: tabular-nums; font-weight: 700; }
    .cmt-main .cmt-muted { color: #999; }
</style>

</body>

</html>
