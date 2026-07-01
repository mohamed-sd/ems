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
        header('Location: contract_amendments.php?msg=' . urlencode($msg));
        exit();
    }
}

// ══════════════════════════════════════════════════════════════════════════════
// التحقق من معرف الشركة (عزل الشركات)
// ══════════════════════════════════════════════════════════════════════════════
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
if ($company_id <= 0) {
    header('Location: ../login.php?msg=' . urlencode('الحساب غير مرتبط بشركة.'));
    exit();
}

// شروط النطاق والحذف الناعم
$scope_sql        = "a.company_id = $company_id";
$scope_update_sql = "company_id = $company_id";
$not_deleted_sql  = "a.is_deleted = 0";

// رمز CSRF
if (empty($_SESSION['amd_csrf_token'])) {
    $_SESSION['amd_csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
}
$amd_csrf_token = $_SESSION['amd_csrf_token'];

// القوائم الثابتة (ENUM)
$AMD_TYPES = array('تجديد', 'تمديد', 'زيادة نطاق', 'تخفيض نطاق', 'تغيير أسعار', 'إضافة معدات', 'إضافة خدمات');

// توليد الكود المقترح التالي (AMD-NNNN) — للعرض فقط
$next_amd_code = 'AMD-0001';
$last_code_sql = "SELECT amendment_code FROM contract_amendments
                  WHERE amendment_code REGEXP '^AMD-[0-9]+$' AND company_id = $company_id AND is_deleted = 0
                  ORDER BY CAST(SUBSTRING(amendment_code, 5) AS UNSIGNED) DESC LIMIT 1";
$last_code_res = @mysqli_query($conn, $last_code_sql);
if ($last_code_res && mysqli_num_rows($last_code_res) > 0) {
    $last_code_row = mysqli_fetch_assoc($last_code_res);
    $last_num = intval(substr($last_code_row['amendment_code'], 4));
    $next_amd_code = 'AMD-' . str_pad($last_num + 1, 4, '0', STR_PAD_LEFT);
}

// صلاحيات المستخدم على وحدة الملاحق والتجديدات
$module_query = "SELECT id FROM modules WHERE code = 'Clients/contract_amendments.php' LIMIT 1";
$module_result = $conn->query($module_query);
$module_info = $module_result ? $module_result->fetch_assoc() : null;
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
    header('Location: ../login.php?msg=' . urlencode('لا توجد صلاحية عرض الملاحق والتجديدات ❌'));
    exit();
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
    if ($amd_code_raw === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $amd_code_raw)) {
        amd_redirect_with_msg('كود الملحق غير صالح. استخدم أحرفًا وأرقامًا و - أو _ فقط ❌');
    }

    // التحقق من نوع التعديل (ENUM)
    $amend_type_raw = isset($_POST['amend_type']) ? trim($_POST['amend_type']) : '';
    if (!in_array($amend_type_raw, $AMD_TYPES, true)) {
        amd_redirect_with_msg('نوع التعديل غير صالح ❌');
    }

    // العقد المرتبط — التحقق من النطاق (إن حُدِّد)
    $contract_in = isset($_POST['contract_id']) ? intval($_POST['contract_id']) : 0;
    if ($contract_in > 0) {
        $cchk = mysqli_query($conn, "SELECT id FROM contracts WHERE id = $contract_in AND company_id = $company_id AND is_deleted = 0 LIMIT 1");
        if (!$cchk || mysqli_num_rows($cchk) === 0) {
            amd_redirect_with_msg('العقد المرتبط غير موجود أو خارج نطاق شركتك ❌');
        }
    }
    $contract_sql = $contract_in > 0 ? "'$contract_in'" : 'NULL';

    // الجهة الطالبة — التحقق من النطاق (إن حُدِّدت)
    $requested_by_in = isset($_POST['requested_by']) ? intval($_POST['requested_by']) : 0;
    if ($requested_by_in > 0) {
        $rchk = mysqli_query($conn, "SELECT id FROM users WHERE id = $requested_by_in AND company_id = $company_id LIMIT 1");
        if (!$rchk || mysqli_num_rows($rchk) === 0) {
            amd_redirect_with_msg('الجهة الطالبة غير موجودة أو خارج نطاق شركتك ❌');
        }
    }
    $requested_by_sql = $requested_by_in > 0 ? "'$requested_by_in'" : 'NULL';

    // الأثر الرقمي — NULL إن فراغ
    $effect_price_raw = isset($_POST['effect_price']) ? trim($_POST['effect_price']) : '';
    $effect_price_sql = $effect_price_raw === '' ? 'NULL' : "'" . (float) $effect_price_raw . "'";
    $effect_qty_raw   = isset($_POST['effect_qty']) ? trim($_POST['effect_qty']) : '';
    $effect_qty_sql   = $effect_qty_raw === '' ? 'NULL' : "'" . (float) $effect_qty_raw . "'";
    $effect_dur_raw   = isset($_POST['effect_duration']) ? trim($_POST['effect_duration']) : '';
    $effect_dur_sql   = $effect_dur_raw === '' ? 'NULL' : "'" . (int) $effect_dur_raw . "'";

    // تنظيف بقية الحقول
    $amendment_code = mysqli_real_escape_string($conn, $amd_code_raw);
    $amend_type     = mysqli_real_escape_string($conn, $amend_type_raw);
    $reason         = mysqli_real_escape_string($conn, isset($_POST['reason']) ? trim($_POST['reason']) : '');
    $old_value      = mysqli_real_escape_string($conn, isset($_POST['old_value']) ? trim($_POST['old_value']) : '');
    $new_value      = mysqli_real_escape_string($conn, isset($_POST['new_value']) ? trim($_POST['new_value']) : '');
    $effect_summary = mysqli_real_escape_string($conn, isset($_POST['effect_summary']) ? trim($_POST['effect_summary']) : '');
    $adate_raw      = isset($_POST['amend_date']) ? trim($_POST['amend_date']) : '';
    $adate_sql      = preg_match('/^\d{4}-\d{2}-\d{2}$/', $adate_raw) ? "'$adate_raw'" : 'NULL';
    $created_by     = intval($_SESSION['user']['id']);

    if ($is_editing) {
        $owner = mysqli_query($conn, "SELECT id FROM contract_amendments WHERE id = $amd_id AND company_id = $company_id AND is_deleted = 0 LIMIT 1");
        if (!$owner || mysqli_num_rows($owner) === 0) {
            amd_redirect_with_msg('لا يمكنك تعديل ملحق لا يتبع لشركتك ❌');
        }
        $dup = mysqli_query($conn, "SELECT id FROM contract_amendments WHERE amendment_code = '$amendment_code' AND id != $amd_id AND company_id = $company_id AND is_deleted = 0");
        if ($dup && mysqli_num_rows($dup) > 0) {
            amd_redirect_with_msg('كود الملحق موجود مسبقاً داخل شركتك ❌');
        }

        $update_query = "UPDATE contract_amendments SET
            amendment_code = '$amendment_code', contract_id = $contract_sql, amend_type = '$amend_type',
            amend_date = $adate_sql, requested_by = $requested_by_sql, reason = '$reason',
            old_value = '$old_value', new_value = '$new_value',
            effect_price = $effect_price_sql, effect_qty = $effect_qty_sql, effect_duration = $effect_dur_sql,
            effect_summary = '$effect_summary'
            WHERE id = $amd_id AND $scope_update_sql AND is_deleted = 0";

        if (mysqli_query($conn, $update_query)) {
            if (class_exists('\\App\\Services\\ActivityLogService')) {
                \App\Services\ActivityLogService::logUpdate('contract_amendments', 'contract_amendments', $amd_id, null, ['amendment_code' => $amd_code_raw]);
            }
            amd_redirect_with_msg('تم تعديل الملحق بنجاح ✅');
        }
        error_log('contract_amendments.php update failed: ' . mysqli_error($conn));
        amd_redirect_with_msg('حدث خطأ أثناء التعديل ❌');
    } else {
        $dup = mysqli_query($conn, "SELECT id FROM contract_amendments WHERE amendment_code = '$amendment_code' AND company_id = $company_id AND is_deleted = 0");
        if ($dup && mysqli_num_rows($dup) > 0) {
            amd_redirect_with_msg('كود الملحق موجود مسبقاً داخل شركتك ❌');
        }

        $insert_query = "INSERT INTO contract_amendments
            (company_id, amendment_code, contract_id, amend_type, amend_date, requested_by, reason,
             old_value, new_value, effect_price, effect_qty, effect_duration, effect_summary, created_by)
            VALUES
            ('$company_id', '$amendment_code', $contract_sql, '$amend_type', $adate_sql, $requested_by_sql, '$reason',
             '$old_value', '$new_value', $effect_price_sql, $effect_qty_sql, $effect_dur_sql, '$effect_summary', '$created_by')";

        if (mysqli_query($conn, $insert_query)) {
            $new_id = (int) mysqli_insert_id($conn);
            if (class_exists('\\App\\Services\\ActivityLogService')) {
                \App\Services\ActivityLogService::logCreate('contract_amendments', 'contract_amendments', $new_id, ['amendment_code' => $amd_code_raw]);
            }
            amd_redirect_with_msg('تم إضافة الملحق بنجاح ✅');
        }
        error_log('contract_amendments.php insert failed: ' . mysqli_error($conn));
        amd_redirect_with_msg('حدث خطأ أثناء الإضافة ❌');
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
    $chk = mysqli_query($conn, "SELECT id FROM contract_amendments WHERE id = $delete_id AND company_id = $company_id AND is_deleted = 0 LIMIT 1");
    if (!$chk || mysqli_num_rows($chk) === 0) {
        amd_redirect_with_msg('لا يمكنك حذف ملحق لا يتبع لشركتك ❌');
    }
    $deleted_by = intval($_SESSION['user']['id']);
    $del = "UPDATE contract_amendments SET is_deleted = 1, deleted_at = NOW(), deleted_by = $deleted_by
            WHERE id = $delete_id AND $scope_update_sql AND is_deleted = 0";
    if (mysqli_query($conn, $del)) {
        if (class_exists('\\App\\Services\\ActivityLogService')) {
            \App\Services\ActivityLogService::logDelete('contract_amendments', 'contract_amendments', $delete_id);
        }
        amd_redirect_with_msg('تم حذف الملحق بنجاح ✅');
    }
    error_log('contract_amendments.php soft delete failed: ' . mysqli_error($conn));
    amd_redirect_with_msg('حدث خطأ أثناء الحذف ❌');
}

// ══════════════════════════════════════════════════════════════════════════════
// قوائم الاختيار (ضمن نطاق الشركة)
// ══════════════════════════════════════════════════════════════════════════════
$contract_options = array();
$contracts_map = array();
$c_res = mysqli_query($conn, "SELECT c.id, p.name AS project_name
                              FROM contracts c
                              LEFT JOIN project p ON p.id = c.project_id
                              WHERE c.company_id = $company_id AND c.is_deleted = 0
                              ORDER BY c.id DESC");
if ($c_res) {
    while ($c = mysqli_fetch_assoc($c_res)) {
        $cid = intval($c['id']);
        $label = 'عقد #' . $cid . ' - ' . (string) $c['project_name'];
        $contract_options[] = array('id' => $cid, 'label' => $label);
        $contracts_map[$cid] = $label;
    }
}

$user_options = array();
$users_map = array();
$u_res = mysqli_query($conn, "SELECT id, name FROM users WHERE company_id = $company_id");
if ($u_res) {
    while ($u = mysqli_fetch_assoc($u_res)) {
        $uid = intval($u['id']);
        $user_options[] = array('id' => $uid, 'name' => $u['name']);
        $users_map[$uid] = $u['name'];
    }
}

// ══════════════════════════════════════════════════════════════════════════════
// جلب الملاحق + الإحصائيات
// ══════════════════════════════════════════════════════════════════════════════
$rows = array();
$stat_total = 0;
$stat_renew = 0;
$stat_extend = 0;
$stat_price = 0;

$q = "SELECT a.*, u.name AS creator_name
      FROM contract_amendments a
      LEFT JOIN users u ON u.id = a.created_by
      WHERE $scope_sql AND $not_deleted_sql
      ORDER BY a.id DESC";
$res = mysqli_query($conn, $q);
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $rows[] = $row;
        $stat_total++;
        if ($row['amend_type'] === 'تجديد') $stat_renew++;
        if ($row['amend_type'] === 'تمديد') $stat_extend++;
        if ($row['amend_type'] === 'تغيير أسعار') $stat_price++;
    }
}

$page_title = "الملاحق والتجديدات";
include("../inheader.php");
include('../insidebar.php');
?>

<div class="main amd-main ems-unified-page-shell">

    <?php
    $header_title = 'الملاحق والتجديدات';
    $header_icon = 'fas fa-file-pen';
    $header_actions = array();
    if ($can_add) {
        $header_actions[] = array('id' => 'toggleForm', 'class' => 'add-btn', 'icon' => 'fa fa-solid fa-plus', 'label' => '', 'label_class' => 'amd-toggle-form-text');
    } else {
        $header_actions[] = array('tag' => 'button', 'class' => '', 'disabled' => true, 'icon' => 'fas fa-plus-circle', 'label' => 'إضافة (بدون صلاحيات)');
    }
    $header_actions[] = array('id' => 'toggleStats', 'class' => 'btn', 'title' => 'إظهار أو إخفاء الإحصائيات', 'icon' => 'fas fa-eye', 'label' => 'إظهار الإحصائيات', 'label_class' => 'amd-toggle-stats-text');
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fa-solid fa-share', 'label' => '');
    include('../includes/page_header.php');
    ?>

    <?php if (!empty($_GET['msg'])):
        $isSuccess = strpos($_GET['msg'], '✅') !== false; ?>
        <div class="success-message <?= $isSuccess ? 'is-success' : 'is-error' ?>">
            <i class="fas <?= $isSuccess ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?php echo amd_e($_GET['msg']); ?>
        </div>
    <?php endif; ?>

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
                        <label><i class="fas fa-magic"></i> كود الملحق المولد <i class="fas fa-info-circle amd-info-icon"></i></label>
                        <input type="text" id="generated_amd_code" class="generated-code-field" value="<?php echo amd_e($next_amd_code); ?>" readonly tabindex="-1" title="هذا الكود للعرض فقط، انسخه إلى حقل كود الملحق" />
                        <div class="generated-code-hint"></div>
                    </div>

                    <div>
                        <label><i class="fas fa-barcode"></i> كود الملحق *</label>
                        <input type="text" name="amendment_code" id="amendment_code" placeholder="مثال: AMD-001" required pattern="[A-Za-z0-9-_]+" />
                    </div>
                    <div>
                        <label><i class="fas fa-file-contract"></i> العقد المرتبط</label>
                        <select name="contract_id" id="contract_id">
                            <option value="">-- بدون / غير محدد --</option>
                            <?php foreach ($contract_options as $co): ?>
                                <option value="<?php echo intval($co['id']); ?>"><?php echo amd_e($co['label']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label><i class="fas fa-list"></i> نوع التعديل</label>
                        <select name="amend_type" id="amend_type">
                            <?php foreach ($AMD_TYPES as $t): ?>
                                <option value="<?php echo amd_e($t); ?>"><?php echo amd_e($t); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label><i class="fas fa-calendar-day"></i> تاريخ التعديل</label>
                        <input type="date" name="amend_date" id="amend_date" />
                    </div>
                    <div>
                        <label><i class="fas fa-user-tie"></i> الجهة الطالبة</label>
                        <select name="requested_by" id="requested_by">
                            <option value="">-- بدون / غير محدد --</option>
                            <?php foreach ($user_options as $uo): ?>
                                <option value="<?php echo intval($uo['id']); ?>"><?php echo amd_e($uo['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label><i class="fas fa-arrow-left-long"></i> القيمة قبل</label>
                        <input type="text" name="old_value" id="old_value" placeholder="القيمة قبل التعديل" />
                    </div>
                    <div>
                        <label><i class="fas fa-arrow-right-long"></i> القيمة بعد</label>
                        <input type="text" name="new_value" id="new_value" placeholder="القيمة بعد التعديل" />
                    </div>
                    <div>
                        <label><i class="fas fa-money-bill-wave"></i> الأثر على السعر</label>
                        <input type="number" step="0.01" name="effect_price" id="effect_price" placeholder="0.00" />
                    </div>
                    <div>
                        <label><i class="fas fa-boxes-stacked"></i> الأثر على الكمية</label>
                        <input type="number" step="0.01" name="effect_qty" id="effect_qty" placeholder="0.00" />
                    </div>
                    <div>
                        <label><i class="fas fa-calendar-plus"></i> الأثر على المدة (أيام)</label>
                        <input type="number" step="1" name="effect_duration" id="effect_duration" placeholder="0" />
                    </div>
                    <div class="amd-col-full">
                        <label><i class="fas fa-comment-dots"></i> سبب التغيير</label>
                        <textarea name="reason" id="reason" rows="2" placeholder="سبب التغيير"></textarea>
                    </div>
                    <div class="amd-col-full">
                        <label><i class="fas fa-note-sticky"></i> ملخص الأثر</label>
                        <textarea name="effect_summary" id="effect_summary" rows="2" placeholder="ملخص الأثر"></textarea>
                    </div>
                </div>
                <div class="pu-form-actions">
                    <button type="submit" class="btn-submit"><i class="fas fa-save"></i> <span id="submitBtnText">حفظ الملحق</span></button>
                    <button type="button" id="amdFormCancelBtn" class="btn-cancel"><i class="fas fa-times"></i> إلغاء</button>
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
                <label><i class="fa fa-list"></i> نوع التعديل</label>
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
                <table id="amdTable" class="display amd-table-nowrap no-datatable">
                    <thead>
                        <tr>
                            <th>إجراءات</th>
                            <th width="90">الكود</th>
                            <th>العقد</th>
                            <th>نوع التعديل</th>
                            <th>التاريخ</th>
                            <th>الأثر على السعر</th>
                            <th>الأثر على المدة</th>
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
                                        <?php if ($can_edit): ?>
                                            <a href="javascript:void(0)" class="action-btn edit editAmdBtn"
                                                data-id="<?php echo intval($row['id']); ?>"
                                                data-code="<?php echo amd_e($row['amendment_code']); ?>"
                                                data-contract-id="<?php echo intval($row['contract_id']); ?>"
                                                data-type="<?php echo amd_e($row['amend_type']); ?>"
                                                data-date="<?php echo amd_e($row['amend_date']); ?>"
                                                data-requested-id="<?php echo intval($row['requested_by']); ?>"
                                                data-reason="<?php echo amd_e($row['reason']); ?>"
                                                data-old="<?php echo amd_e($row['old_value']); ?>"
                                                data-new="<?php echo amd_e($row['new_value']); ?>"
                                                data-effect-price="<?php echo amd_e($row['effect_price']); ?>"
                                                data-effect-qty="<?php echo amd_e($row['effect_qty']); ?>"
                                                data-effect-duration="<?php echo amd_e($row['effect_duration']); ?>"
                                                data-effect-summary="<?php echo amd_e($row['effect_summary']); ?>"
                                                title="تعديل"><i class="fas fa-edit"></i></a>
                                        <?php endif; ?>
                                        <?php if ($can_delete): ?>
                                            <a href="?delete_id=<?php echo urlencode($row['id']); ?>&csrf_token=<?php echo urlencode($amd_csrf_token); ?>"
                                                class="action-btn delete"
                                                onclick="return confirm('هل أنت متأكد من حذف هذا الملحق؟')" title="حذف"><i class="fas fa-trash-alt"></i></a>
                                        <?php endif; ?>
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
        const amdTable = $('#amdTable').DataTable({
            autoWidth: false,
            stateSave: false,
            language: { url: '/ems/assets/i18n/datatables/ar.json' }
        });

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

    function setAddMode() { formTitle.text('إضافة ملحق جديد'); submitBtnText.text('حفظ الملحق'); generatedCodeWrapper.show(); }
    function setEditMode() { formTitle.text('تعديل الملحق'); submitBtnText.text('تحديث الملحق'); generatedCodeWrapper.hide(); }
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

<style>
    .amd-main .stats-grid { display: grid; grid-template-columns: repeat(4, minmax(170px, 1fr)); gap: 12px; }
    .amd-main .stats-section {
        border: 1px solid var(--bdr); border-radius: var(--rl);
        background: linear-gradient(180deg, rgba(255,255,255,.95) 0%, var(--s2) 100%);
        box-shadow: var(--sh); padding: 14px; margin-bottom: 14px;
    }
    .amd-main .stats-card { background: #eee; border: 1px solid #aaa; border-radius: 35px; padding: 18px; box-shadow: 0 2px 8px rgba(26,18,8,.07); position: relative; overflow: hidden; }
    .amd-main .stats-card .stats-icon { width: 55px; height: 55px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.3rem; margin-bottom: 10px; float: left; margin-top: 15px; border: 1px solid #999; background:#fff; color:#000; }
    .amd-main .stats-card .stats-title { color: #555; font-size: .92rem; font-weight: 700; margin-top: 5px; line-height: 1.3; }
    .amd-main .stats-card .stats-value { color: #222; line-height: 1; font-weight: 900; font-variant-numeric: tabular-nums; margin-top: 10px; font-size: 30px; }
    @media (max-width: 900px) { .amd-main .stats-grid { grid-template-columns: repeat(2, minmax(150px,1fr)); } }
    @media (max-width: 560px) { .amd-main .stats-grid { grid-template-columns: 1fr; } }

    .amd-main .amd-hidden { display: none; }
    .amd-main .amd-col-full { grid-column: 1 / -1; }
    .amd-main .table-container { overflow-x: auto; }
    #amdTable.amd-table-nowrap, #amdTable.amd-table-nowrap th, #amdTable.amd-table-nowrap td { white-space: nowrap; }
    #amdTable .action-btns { flex-wrap: nowrap; white-space: nowrap; }
    .amd-main .amd-num { font-variant-numeric: tabular-nums; font-weight: 700; }
    .amd-main .amd-muted { color: #999; }
</style>

</body>

</html>
