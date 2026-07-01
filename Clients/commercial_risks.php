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
if (!function_exists('risk_e')) {
    function risk_e($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
if (!function_exists('risk_redirect_with_msg')) {
    function risk_redirect_with_msg($msg)
    {
        header('Location: commercial_risks.php?msg=' . urlencode($msg));
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
$scope_sql        = "r.company_id = $company_id";
$scope_update_sql = "company_id = $company_id";
$not_deleted_sql  = "r.is_deleted = 0";

// رمز CSRF
if (empty($_SESSION['risk_csrf_token'])) {
    $_SESSION['risk_csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
}
$risk_csrf_token = $_SESSION['risk_csrf_token'];

// القوائم الثابتة
$RISK_TYPES = array('عميل', 'موقع', 'تمويل', 'تحصيل', 'تشغيل', 'موردون');
$RISK_SEVERITIES = array('منخفضة', 'متوسطة', 'عالية');
$RISK_STATES = array('مفتوح', 'تحت المعالجة', 'مغلق');
$RISK_ENTITY_TYPES = array(
    'opportunity' => 'فرصة',
    'contract'    => 'عقد',
);

// توليد الكود المقترح التالي (RSK-NNNN) — للعرض فقط
$next_risk_code = 'RSK-0001';
$last_code_sql = "SELECT risk_code FROM commercial_risks
                  WHERE risk_code REGEXP '^RSK-[0-9]+$' AND company_id = $company_id AND is_deleted = 0
                  ORDER BY CAST(SUBSTRING(risk_code, 5) AS UNSIGNED) DESC LIMIT 1";
$last_code_res = @mysqli_query($conn, $last_code_sql);
if ($last_code_res && mysqli_num_rows($last_code_res) > 0) {
    $last_code_row = mysqli_fetch_assoc($last_code_res);
    $last_num = intval(substr($last_code_row['risk_code'], 4));
    $next_risk_code = 'RSK-' . str_pad($last_num + 1, 4, '0', STR_PAD_LEFT);
}

// صلاحيات المستخدم على وحدة المخاطر التجارية
$module_query = "SELECT id FROM modules WHERE code = 'Clients/commercial_risks.php' LIMIT 1";
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
    header('Location: ../login.php?msg=' . urlencode('لا توجد صلاحية عرض المخاطر التجارية ❌'));
    exit();
}

// ══════════════════════════════════════════════════════════════════════════════
// معالجة إضافة / تعديل خطر عبر POST
// ══════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['risk_type'])) {
    $posted_csrf = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if (empty($posted_csrf) || !hash_equals($risk_csrf_token, $posted_csrf)) {
        risk_redirect_with_msg('جلسة النموذج غير صالحة، يرجى إعادة المحاولة ❌');
    }

    $risk_id    = isset($_POST['risk_id']) ? intval($_POST['risk_id']) : 0;
    $is_editing = $risk_id > 0;

    if ($is_editing && !$can_edit) {
        risk_redirect_with_msg('لا توجد صلاحية تعديل المخاطر ❌');
    } elseif (!$is_editing && !$can_add) {
        risk_redirect_with_msg('لا توجد صلاحية إضافة مخاطر جديدة ❌');
    }

    // الكود
    $risk_code_raw = isset($_POST['risk_code']) ? trim($_POST['risk_code']) : '';
    if ($risk_code_raw === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $risk_code_raw)) {
        risk_redirect_with_msg('كود الخطر غير صالح. استخدم أحرفًا وأرقامًا و - أو _ فقط ❌');
    }

    // وصف الخطر
    $name_raw = isset($_POST['name']) ? trim($_POST['name']) : '';
    if ($name_raw === '') {
        risk_redirect_with_msg('وصف الخطر مطلوب ❌');
    }

    // التحقق من القوائم الثابتة
    $type_raw = isset($_POST['risk_type']) ? trim($_POST['risk_type']) : '';
    if (!in_array($type_raw, $RISK_TYPES, true)) {
        risk_redirect_with_msg('نوع الخطر غير صالح ❌');
    }
    $severity_raw = isset($_POST['severity']) ? trim($_POST['severity']) : 'متوسطة';
    if (!in_array($severity_raw, $RISK_SEVERITIES, true)) {
        $severity_raw = 'متوسطة';
    }
    $state_raw = isset($_POST['state']) ? trim($_POST['state']) : 'مفتوح';
    if (!in_array($state_raw, $RISK_STATES, true)) {
        $state_raw = 'مفتوح';
    }
    $entity_type_raw = isset($_POST['entity_type']) ? trim($_POST['entity_type']) : 'opportunity';
    if (!isset($RISK_ENTITY_TYPES[$entity_type_raw])) {
        $entity_type_raw = 'opportunity';
    }

    // السجل المرتبط — التحقق من النطاق حسب النوع (إن حُدِّد)
    $entity_id_in = isset($_POST['entity_id']) ? intval($_POST['entity_id']) : 0;
    if ($entity_id_in > 0) {
        if ($entity_type_raw === 'opportunity') {
            $chk = mysqli_query($conn, "SELECT id FROM opportunities WHERE id = $entity_id_in AND company_id = $company_id AND is_deleted = 0 LIMIT 1");
        } else {
            $chk = mysqli_query($conn, "SELECT id FROM contracts WHERE id = $entity_id_in AND company_id = $company_id AND is_deleted = 0 LIMIT 1");
        }
        if (!$chk || mysqli_num_rows($chk) === 0) {
            risk_redirect_with_msg('السجل المرتبط غير موجود أو خارج نطاق شركتك ❌');
        }
    }
    $entity_id_sql = $entity_id_in > 0 ? "'$entity_id_in'" : 'NULL';

    // المستخدم المسؤول (إن حُدِّد) ضمن الشركة
    $owner_in = isset($_POST['owner_user_id']) ? intval($_POST['owner_user_id']) : 0;
    if ($owner_in > 0) {
        $uchk = mysqli_query($conn, "SELECT id FROM users WHERE id = $owner_in AND company_id = $company_id LIMIT 1");
        if (!$uchk || mysqli_num_rows($uchk) === 0) {
            $owner_in = 0;
        }
    }
    $owner_sql = $owner_in > 0 ? "'$owner_in'" : 'NULL';

    // تنظيف بقية الحقول
    $risk_code   = mysqli_real_escape_string($conn, $risk_code_raw);
    $name        = mysqli_real_escape_string($conn, $name_raw);
    $risk_type   = mysqli_real_escape_string($conn, $type_raw);
    $severity    = mysqli_real_escape_string($conn, $severity_raw);
    $state       = mysqli_real_escape_string($conn, $state_raw);
    $entity_type = mysqli_real_escape_string($conn, $entity_type_raw);
    $mitigation  = mysqli_real_escape_string($conn, isset($_POST['mitigation']) ? trim($_POST['mitigation']) : '');
    $notes       = mysqli_real_escape_string($conn, isset($_POST['notes']) ? trim($_POST['notes']) : '');
    $created_by  = intval($_SESSION['user']['id']);

    if ($is_editing) {
        $owner = mysqli_query($conn, "SELECT id FROM commercial_risks WHERE id = $risk_id AND company_id = $company_id AND is_deleted = 0 LIMIT 1");
        if (!$owner || mysqli_num_rows($owner) === 0) {
            risk_redirect_with_msg('لا يمكنك تعديل خطر لا يتبع لشركتك ❌');
        }
        $dup = mysqli_query($conn, "SELECT id FROM commercial_risks WHERE risk_code = '$risk_code' AND id != $risk_id AND company_id = $company_id AND is_deleted = 0");
        if ($dup && mysqli_num_rows($dup) > 0) {
            risk_redirect_with_msg('كود الخطر موجود مسبقاً داخل شركتك ❌');
        }

        $update_query = "UPDATE commercial_risks SET
            risk_code = '$risk_code', name = '$name', risk_type = '$risk_type', severity = '$severity',
            mitigation = '$mitigation', owner_user_id = $owner_sql, state = '$state',
            entity_type = '$entity_type', entity_id = $entity_id_sql, notes = '$notes'
            WHERE id = $risk_id AND $scope_update_sql AND is_deleted = 0";

        if (mysqli_query($conn, $update_query)) {
            if (class_exists('\\App\\Services\\ActivityLogService')) {
                \App\Services\ActivityLogService::logUpdate('commercial_risks', 'commercial_risks', $risk_id, null, ['risk_code' => $risk_code_raw]);
            }
            risk_redirect_with_msg('تم تعديل الخطر بنجاح ✅');
        }
        error_log('commercial_risks.php update failed: ' . mysqli_error($conn));
        risk_redirect_with_msg('حدث خطأ أثناء التعديل ❌');
    } else {
        $dup = mysqli_query($conn, "SELECT id FROM commercial_risks WHERE risk_code = '$risk_code' AND company_id = $company_id AND is_deleted = 0");
        if ($dup && mysqli_num_rows($dup) > 0) {
            risk_redirect_with_msg('كود الخطر موجود مسبقاً داخل شركتك ❌');
        }

        $insert_query = "INSERT INTO commercial_risks
            (company_id, risk_code, name, risk_type, severity, mitigation, owner_user_id, state,
             entity_type, entity_id, notes, created_by)
            VALUES
            ('$company_id', '$risk_code', '$name', '$risk_type', '$severity', '$mitigation', $owner_sql, '$state',
             '$entity_type', $entity_id_sql, '$notes', '$created_by')";

        if (mysqli_query($conn, $insert_query)) {
            $new_id = (int) mysqli_insert_id($conn);
            if (class_exists('\\App\\Services\\ActivityLogService')) {
                \App\Services\ActivityLogService::logCreate('commercial_risks', 'commercial_risks', $new_id, ['risk_code' => $risk_code_raw]);
            }
            risk_redirect_with_msg('تم إضافة الخطر بنجاح ✅');
        }
        error_log('commercial_risks.php insert failed: ' . mysqli_error($conn));
        risk_redirect_with_msg('حدث خطأ أثناء الإضافة ❌');
    }
}

// ══════════════════════════════════════════════════════════════════════════════
// معالجة الحذف الناعم
// ══════════════════════════════════════════════════════════════════════════════
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $delete_csrf = isset($_GET['csrf_token']) ? $_GET['csrf_token'] : '';

    if (!$can_delete) {
        risk_redirect_with_msg('لا توجد صلاحية حذف المخاطر ❌');
    }
    if (empty($delete_csrf) || !hash_equals($risk_csrf_token, $delete_csrf)) {
        risk_redirect_with_msg('جلسة الحذف غير صالحة، يرجى إعادة المحاولة ❌');
    }
    $chk = mysqli_query($conn, "SELECT id FROM commercial_risks WHERE id = $delete_id AND company_id = $company_id AND is_deleted = 0 LIMIT 1");
    if (!$chk || mysqli_num_rows($chk) === 0) {
        risk_redirect_with_msg('لا يمكنك حذف خطر لا يتبع لشركتك ❌');
    }
    $deleted_by = intval($_SESSION['user']['id']);
    $del = "UPDATE commercial_risks SET is_deleted = 1, deleted_at = NOW(), deleted_by = $deleted_by
            WHERE id = $delete_id AND $scope_update_sql AND is_deleted = 0";
    if (mysqli_query($conn, $del)) {
        if (class_exists('\\App\\Services\\ActivityLogService')) {
            \App\Services\ActivityLogService::logDelete('commercial_risks', 'commercial_risks', $delete_id);
        }
        risk_redirect_with_msg('تم حذف الخطر بنجاح ✅');
    }
    error_log('commercial_risks.php soft delete failed: ' . mysqli_error($conn));
    risk_redirect_with_msg('حدث خطأ أثناء الحذف ❌');
}

// ══════════════════════════════════════════════════════════════════════════════
// قوائم السجلات المرتبطة (ضمن نطاق الشركة)
// ══════════════════════════════════════════════════════════════════════════════
$opp_options = array();
$op_res = mysqli_query($conn, "SELECT id, opp_code, title FROM opportunities WHERE company_id = $company_id AND is_deleted = 0 ORDER BY id DESC");
if ($op_res) { while ($op = mysqli_fetch_assoc($op_res)) { $opp_options[] = $op; } }

$contract_options = array();
$ct_res = mysqli_query($conn, "SELECT c.id, p.name AS project_name FROM contracts c LEFT JOIN project p ON p.id = c.project_id WHERE c.company_id = $company_id AND c.is_deleted = 0 ORDER BY c.id DESC");
if ($ct_res) { while ($ct = mysqli_fetch_assoc($ct_res)) { $contract_options[] = $ct; } }

$users_options = array();
$us_res = mysqli_query($conn, "SELECT id, name FROM users WHERE company_id = $company_id ORDER BY name ASC");
if ($us_res) { while ($us = mysqli_fetch_assoc($us_res)) { $users_options[intval($us['id'])] = $us['name']; } }

// ══════════════════════════════════════════════════════════════════════════════
// جلب المخاطر + الإحصائيات
// ══════════════════════════════════════════════════════════════════════════════
$rows = array();
$stat_total = 0;
$stat_open = 0;
$stat_high = 0;
$stat_closed = 0;

$q = "SELECT r.*, u.name AS creator_name, ou.name AS owner_name
      FROM commercial_risks r
      LEFT JOIN users u ON u.id = r.created_by
      LEFT JOIN users ou ON ou.id = r.owner_user_id
      WHERE $scope_sql AND $not_deleted_sql
      ORDER BY r.id DESC";
$res = mysqli_query($conn, $q);
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        // اسم السجل المرتبط
        $linked = '';
        if (!empty($row['entity_id'])) {
            $eid = intval($row['entity_id']);
            if ($row['entity_type'] === 'opportunity') {
                $lr = mysqli_query($conn, "SELECT title FROM opportunities WHERE id = $eid LIMIT 1");
                $linked = ($lr && $lo = mysqli_fetch_assoc($lr)) ? $lo['title'] : '';
            } else {
                $linked = 'عقد #' . $eid;
            }
        }
        $row['linked_label'] = $linked;
        $rows[] = $row;

        $stat_total++;
        if ($row['state'] === 'مفتوح') $stat_open++;
        if ($row['severity'] === 'عالية') $stat_high++;
        if ($row['state'] === 'مغلق') $stat_closed++;
    }
}

$page_title = "المخاطر التجارية";
include("../inheader.php");
include('../insidebar.php');

function risk_entity_label($type, $map)
{
    return isset($map[$type]) ? $map[$type] : $type;
}
?>

<div class="main risk-main ems-unified-page-shell">

    <?php
    $header_title = 'المخاطر التجارية';
    $header_icon = 'fas fa-triangle-exclamation';
    $header_actions = array();
    if ($can_add) {
        $header_actions[] = array('id' => 'toggleForm', 'class' => 'add-btn', 'icon' => 'fa fa-solid fa-plus', 'label' => '', 'label_class' => 'risk-toggle-form-text');
    } else {
        $header_actions[] = array('tag' => 'button', 'class' => '', 'disabled' => true, 'icon' => 'fas fa-plus-circle', 'label' => 'إضافة (بدون صلاحيات)');
    }
    $header_actions[] = array('id' => 'toggleStats', 'class' => 'btn', 'title' => 'إظهار أو إخفاء الإحصائيات', 'icon' => 'fas fa-eye', 'label' => 'إظهار الإحصائيات', 'label_class' => 'risk-toggle-stats-text');
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fa-solid fa-share', 'label' => '');
    include('../includes/page_header.php');
    ?>

    <?php if (!empty($_GET['msg'])):
        $isSuccess = strpos($_GET['msg'], '✅') !== false; ?>
        <div class="success-message <?= $isSuccess ? 'is-success' : 'is-error' ?>">
            <i class="fas <?= $isSuccess ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?php echo risk_e($_GET['msg']); ?>
        </div>
    <?php endif; ?>

    <div class="stats-section risk-hidden" id="riskStatsSection">
        <div class="stats-grid">
            <div class="stats-card">
                <div class="stats-icon"><i class="fas fa-triangle-exclamation"></i></div>
                <div class="stats-value"><?php echo $stat_total; ?></div>
                <div class="stats-title">إجمالي المخاطر</div>
            </div>
            <div class="stats-card">
                <div class="stats-icon"><i class="fas fa-folder-open"></i></div>
                <div class="stats-value"><?php echo $stat_open; ?></div>
                <div class="stats-title">مفتوحة</div>
            </div>
            <div class="stats-card">
                <div class="stats-icon"><i class="fas fa-fire"></i></div>
                <div class="stats-value"><?php echo $stat_high; ?></div>
                <div class="stats-title">خطورة عالية</div>
            </div>
            <div class="stats-card">
                <div class="stats-icon"><i class="fas fa-circle-check"></i></div>
                <div class="stats-value"><?php echo $stat_closed; ?></div>
                <div class="stats-title">مغلقة</div>
            </div>
        </div>
    </div>

    <!-- فورم إضافة / تعديل خطر -->
    <form id="riskForm" action="" method="post" class="allforms">
        <div class="card-header">
            <h5><i class="fas fa-edit"></i> <span id="formTitle">إضافة خطر جديد</span></h5>
        </div>
        <input type="hidden" name="risk_id" id="risk_id" value="">
        <input type="hidden" name="csrf_token" value="<?php echo risk_e($risk_csrf_token); ?>">
        <div class="card shadow-sm pu-form-card">
            <div class="card-body">
                <div class="form-grid">
                    <div id="generated_code_wrapper" class="auto">
                        <label><i class="fas fa-magic"></i> كود الخطر المولد <i class="fas fa-info-circle risk-info-icon"></i></label>
                        <input type="text" id="generated_risk_code" class="generated-code-field" value="<?php echo risk_e($next_risk_code); ?>" readonly tabindex="-1" title="هذا الكود للعرض فقط، انسخه إلى حقل كود الخطر" />
                        <div class="generated-code-hint"></div>
                    </div>

                    <div>
                        <label><i class="fas fa-barcode"></i> كود الخطر *</label>
                        <input type="text" name="risk_code" id="risk_code" placeholder="مثال: RSK-001" required pattern="[A-Za-z0-9-_]+" />
                    </div>
                    <div>
                        <label><i class="fas fa-heading"></i> وصف الخطر *</label>
                        <input type="text" name="name" id="name" placeholder="وصف موجز للخطر" required />
                    </div>
                    <div>
                        <label><i class="fas fa-list-check"></i> نوع الخطر *</label>
                        <select name="risk_type" id="risk_type" required>
                            <?php foreach ($RISK_TYPES as $t): ?>
                                <option value="<?php echo risk_e($t); ?>"><?php echo risk_e($t); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label><i class="fas fa-gauge-high"></i> الخطورة *</label>
                        <select name="severity" id="severity" required>
                            <?php foreach ($RISK_SEVERITIES as $s): ?>
                                <option value="<?php echo risk_e($s); ?>" <?php echo $s === 'متوسطة' ? 'selected' : ''; ?>><?php echo risk_e($s); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label><i class="fas fa-flag"></i> الحالة *</label>
                        <select name="state" id="state" required>
                            <?php foreach ($RISK_STATES as $st): ?>
                                <option value="<?php echo risk_e($st); ?>" <?php echo $st === 'مفتوح' ? 'selected' : ''; ?>><?php echo risk_e($st); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label><i class="fas fa-link"></i> نوع الارتباط</label>
                        <select name="entity_type" id="entity_type">
                            <?php foreach ($RISK_ENTITY_TYPES as $k => $v): ?>
                                <option value="<?php echo risk_e($k); ?>"><?php echo risk_e($v); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label><i class="fas fa-folder-tree"></i> السجل المرتبط</label>
                        <select name="entity_id" id="entity_id">
                            <option value="">-- بدون / غير محدد --</option>
                        </select>
                    </div>
                    <div>
                        <label><i class="fas fa-user-check"></i> المسؤول</label>
                        <select name="owner_user_id" id="owner_user_id">
                            <option value="">-- غير محدد --</option>
                            <?php foreach ($users_options as $uid => $uname): ?>
                                <option value="<?php echo intval($uid); ?>"><?php echo risk_e($uname); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="risk-col-full">
                        <label><i class="fas fa-clipboard-check"></i> خطة المعالجة</label>
                        <textarea name="mitigation" id="mitigation" rows="2" placeholder="خطة المعالجة والإجراءات المتخذة"></textarea>
                    </div>
                    <div class="risk-col-full">
                        <label><i class="fas fa-note-sticky"></i> ملاحظات</label>
                        <textarea name="notes" id="notes" rows="2" placeholder="أي ملاحظات إضافية"></textarea>
                    </div>
                </div>
                <div class="pu-form-actions">
                    <button type="submit" class="btn-submit"><i class="fas fa-save"></i> <span id="submitBtnText">حفظ الخطر</span></button>
                    <button type="button" id="riskFormCancelBtn" class="btn-cancel"><i class="fas fa-times"></i> إلغاء</button>
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
                <label><i class="fa fa-list-check"></i> نوع الخطر</label>
                <select id="filterType" class="form-control">
                    <option value="">-- كل الأنواع --</option>
                </select>
            </div>
            <div class="filter-field">
                <label><i class="fa fa-gauge-high"></i> الخطورة</label>
                <select id="filterSeverity" class="form-control">
                    <option value="">-- الكل --</option>
                </select>
            </div>
            <div class="filter-field">
                <label><i class="fa fa-flag"></i> الحالة</label>
                <select id="filterState" class="form-control">
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
                <table id="riskTable" class="display risk-table-nowrap no-datatable">
                    <thead>
                        <tr>
                            <th>إجراءات</th>
                            <th width="90">الكود</th>
                            <th>وصف الخطر</th>
                            <th>النوع</th>
                            <th>الخطورة</th>
                            <th>الحالة</th>
                            <th>الارتباط</th>
                            <th>المسؤول</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row):
                            $ent_label = risk_entity_label($row['entity_type'], $RISK_ENTITY_TYPES);
                            $owner_label = $row['owner_name'] !== null ? $row['owner_name'] : '';
                            $created_label = function_exists('ems_actor_label') ? ems_actor_label($conn, intval($row['created_by'])) : ($row['creator_name'] ?? '');
                            $sev = $row['severity'];
                            $sev_class = $sev === 'عالية' ? 'risk-sev-high' : ($sev === 'متوسطة' ? 'risk-sev-mid' : 'risk-sev-low');
                            ?>
                            <tr>
                                <td>
                                    <div class="action-btns">
                                        <a href="javascript:void(0)" class="action-btn view viewRiskBtn"
                                            data-id="<?php echo intval($row['id']); ?>"
                                            data-code="<?php echo risk_e($row['risk_code']); ?>"
                                            data-name="<?php echo risk_e($row['name']); ?>"
                                            data-type="<?php echo risk_e($row['risk_type']); ?>"
                                            data-severity="<?php echo risk_e($row['severity']); ?>"
                                            data-state="<?php echo risk_e($row['state']); ?>"
                                            data-entity-type="<?php echo risk_e($row['entity_type']); ?>"
                                            data-entity-label="<?php echo risk_e($ent_label); ?>"
                                            data-linked="<?php echo risk_e($row['linked_label']); ?>"
                                            data-mitigation="<?php echo risk_e($row['mitigation']); ?>"
                                            data-notes="<?php echo risk_e($row['notes']); ?>"
                                            data-owner="<?php echo risk_e($owner_label); ?>"
                                            data-created="<?php echo risk_e($created_label); ?>"
                                            title="عرض التفاصيل"><i class="fas fa-eye"></i></a>
                                        <?php if ($can_edit): ?>
                                            <a href="javascript:void(0)" class="action-btn edit editRiskBtn"
                                                data-id="<?php echo intval($row['id']); ?>"
                                                data-code="<?php echo risk_e($row['risk_code']); ?>"
                                                data-name="<?php echo risk_e($row['name']); ?>"
                                                data-type="<?php echo risk_e($row['risk_type']); ?>"
                                                data-severity="<?php echo risk_e($row['severity']); ?>"
                                                data-state="<?php echo risk_e($row['state']); ?>"
                                                data-entity-type="<?php echo risk_e($row['entity_type']); ?>"
                                                data-entity-id="<?php echo intval($row['entity_id']); ?>"
                                                data-owner-id="<?php echo intval($row['owner_user_id']); ?>"
                                                data-mitigation="<?php echo risk_e($row['mitigation']); ?>"
                                                data-notes="<?php echo risk_e($row['notes']); ?>"
                                                title="تعديل"><i class="fas fa-edit"></i></a>
                                        <?php endif; ?>
                                        <?php if ($can_delete): ?>
                                            <a href="?delete_id=<?php echo urlencode($row['id']); ?>&csrf_token=<?php echo urlencode($risk_csrf_token); ?>"
                                                class="action-btn delete"
                                                onclick="return confirm('هل أنت متأكد من حذف هذا الخطر؟')" title="حذف"><i class="fas fa-trash-alt"></i></a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><strong class="risk-code-cell"><?php echo risk_e($row['risk_code']); ?></strong></td>
                                <td><?php echo $row['name'] !== '' ? risk_e($row['name']) : '<span class="risk-muted">—</span>'; ?></td>
                                <td><?php echo risk_e($row['risk_type']); ?></td>
                                <td><span class="risk-sev-badge <?php echo $sev_class; ?>"><?php echo risk_e($sev); ?></span></td>
                                <td><?php echo risk_e($row['state']); ?></td>
                                <td>
                                    <?php echo risk_e($ent_label); ?>
                                    <?php if ($row['linked_label'] !== ''): ?><span class="risk-muted">— <?php echo risk_e($row['linked_label']); ?></span><?php endif; ?>
                                </td>
                                <td><?php echo $owner_label !== '' ? risk_e($owner_label) : '<span class="risk-muted">—</span>'; ?></td>
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
    const RISK_LINKED = {
        opportunity: [<?php foreach ($opp_options as $op) { echo '{id:' . intval($op['id']) . ',label:"' . risk_e(addslashes($op['title'])) . ' (' . risk_e(addslashes($op['opp_code'])) . ')"},'; } ?>],
        contract: [<?php foreach ($contract_options as $ct) { echo '{id:' . intval($ct['id']) . ',label:"عقد #' . intval($ct['id']) . ' - ' . risk_e(addslashes($ct['project_name'] ?? '')) . '"},'; } ?>]
    };

    function riskFillEntityOptions(entityType, selectedId) {
        const sel = $('#entity_id');
        sel.empty().append('<option value="">-- بدون / غير محدد --</option>');
        const list = RISK_LINKED[entityType] || [];
        list.forEach(function (it) {
            sel.append('<option value="' + it.id + '">' + it.label + '</option>');
        });
        if (selectedId) sel.val(String(selectedId));
        if (window.EmsSelect) EmsSelect.refresh();
    }

    $(document).ready(function () {
        const riskTable = $('#riskTable').DataTable({
            autoWidth: false,
            stateSave: false,
            language: { url: '/ems/assets/i18n/datatables/ar.json' }
        });

        function fillFilterOptions(columnIndex, selectId) {
            const select = $(selectId);
            const values = [];
            riskTable.column(columnIndex).data().each(function (value) {
                const text = $('<div>').html(value).text().trim();
                if (text !== '' && values.indexOf(text) === -1) values.push(text);
            });
            values.sort();
            values.forEach(function (val) {
                select.append('<option value="' + val.replace(/"/g, '&quot;') + '">' + val + '</option>');
            });
        }
        fillFilterOptions(3, '#filterType');
        fillFilterOptions(4, '#filterSeverity');
        fillFilterOptions(5, '#filterState');

        $('#filterType').on('change', function () {
            const value = $.fn.dataTable.util.escapeRegex($(this).val());
            riskTable.column(3).search(value ? '^' + value + '$' : '', true, false).draw();
        });
        $('#filterSeverity').on('change', function () {
            const value = $.fn.dataTable.util.escapeRegex($(this).val());
            riskTable.column(4).search(value ? '^' + value + '$' : '', true, false).draw();
        });
        $('#filterState').on('change', function () {
            const value = $.fn.dataTable.util.escapeRegex($(this).val());
            riskTable.column(5).search(value ? '^' + value + '$' : '', true, false).draw();
        });

        $('#entity_type').on('change', function () { riskFillEntityOptions($(this).val(), ''); });
        riskFillEntityOptions($('#entity_type').val() || 'opportunity', '');
    });

    // ── إظهار/إخفاء الفورم والإحصائيات ──
    const formToggleBtn = $('#toggleForm');
    const riskForm = $('#riskForm');
    const formTitle = $('#formTitle');
    const submitBtnText = $('#submitBtnText');
    const generatedCodeWrapper = $('#generated_code_wrapper');
    const formCancelBtn = $('#riskFormCancelBtn');
    const statsToggleBtn = $('#toggleStats');
    const statsSection = $('#riskStatsSection');

    function setAddMode() { formTitle.text('إضافة خطر جديد'); submitBtnText.text('حفظ الخطر'); generatedCodeWrapper.show(); }
    function setEditMode() { formTitle.text('تعديل الخطر'); submitBtnText.text('تحديث الخطر'); generatedCodeWrapper.hide(); }
    function resetForm() { if (!riskForm.length) return; riskForm[0].reset(); $('#risk_id').val(''); riskFillEntityOptions($('#entity_type').val() || 'opportunity', ''); setAddMode(); if (window.EmsSelect) EmsSelect.refresh(); }

    function updateFormToggleState(isOpen) {
        if (!formToggleBtn.length) return;
        formToggleBtn.toggleClass('is-active', isOpen);
        formToggleBtn.attr('aria-expanded', isOpen ? 'true' : 'false');
    }
    function updateStatsToggleState(isVisible) {
        if (!statsToggleBtn.length) return;
        statsToggleBtn.toggleClass('is-active', isVisible);
        statsToggleBtn.find('.risk-toggle-stats-text').text('إظهار الإحصائيات');
        const icon = statsToggleBtn.find('i').first();
        icon.toggleClass('fa-chart-pie', isVisible);
        icon.toggleClass('fa-eye', !isVisible);
    }

    setAddMode();
    updateFormToggleState(riskForm.is(':visible'));
    updateStatsToggleState(statsSection.is(':visible'));

    formToggleBtn.on('click', function (e) {
        e.preventDefault();
        if (!riskForm.length) return;
        if (riskForm.is(':visible')) {
            riskForm.stop(true, true).slideUp(250, function () { riskForm.removeClass('allforms-visible'); resetForm(); updateFormToggleState(false); });
        } else {
            resetForm();
            riskForm.addClass('allforms-visible').hide();
            riskForm.stop(true, true).slideDown(250, function () { updateFormToggleState(true); });
        }
    });
    formCancelBtn.on('click', function () {
        if (!riskForm.length || !riskForm.is(':visible')) return;
        riskForm.stop(true, true).slideUp(250, function () { riskForm.removeClass('allforms-visible'); resetForm(); updateFormToggleState(false); });
    });
    statsToggleBtn.on('click', function (e) {
        e.preventDefault();
        if (!statsSection.length) return;
        if (statsSection.is(':visible')) {
            statsSection.stop(true, true).slideUp(250, function () { statsSection.addClass('risk-hidden'); updateStatsToggleState(false); });
        } else {
            statsSection.removeClass('risk-hidden').hide();
            statsSection.stop(true, true).slideDown(250, function () { updateStatsToggleState(true); });
        }
    });

    // ── تعبئة الفورم للتعديل ──
    function fillRiskForm(d) {
        $('#risk_id').val(d.id);
        $('#risk_code').val(d.code);
        $('#name').val(d.name || '');
        $('#risk_type').val(d.type || 'عميل');
        $('#severity').val(d.severity || 'متوسطة');
        $('#state').val(d.state || 'مفتوح');
        $('#entity_type').val(d.entityType || 'opportunity');
        riskFillEntityOptions(d.entityType || 'opportunity', d.entityId || '');
        $('#owner_user_id').val(d.ownerId || '');
        $('#mitigation').val(d.mitigation || '');
        $('#notes').val(d.notes || '');
        if (window.EmsSelect) EmsSelect.refresh();
        setEditMode();
        if (!riskForm.is(':visible')) {
            riskForm.addClass('allforms-visible').hide();
            riskForm.stop(true, true).slideDown(250, function () { updateFormToggleState(true); });
        } else { updateFormToggleState(true); }
        $('html, body').animate({ scrollTop: $('#riskForm').offset().top - 100 }, 500);
    }

    $(document).on('click', '.editRiskBtn', function () {
        fillRiskForm({
            id: $(this).data('id'), code: $(this).data('code'), name: $(this).data('name'), type: $(this).data('type'),
            severity: $(this).data('severity'), state: $(this).data('state'),
            entityType: $(this).data('entity-type'), entityId: $(this).data('entity-id'), ownerId: $(this).data('owner-id'),
            mitigation: $(this).data('mitigation'), notes: $(this).data('notes')
        });
    });

    // ── عرض التفاصيل عبر EmsDetailsModal الموحّد ──
    $(document).on('click', '.viewRiskBtn', function () {
        const d = $(this).data();
        const sevTone = d.severity === 'عالية' ? 'active' : (d.severity === 'متوسطة' ? 'pending' : 'inactive');
        const stateTone = d.state === 'مغلق' ? 'inactive' : (d.state === 'تحت المعالجة' ? 'pending' : 'active');
        const fields = [
            { label: 'كود الخطر', value: d.code, icon: 'fas fa-barcode' },
            { label: 'وصف الخطر', value: d.name || '—', icon: 'fas fa-heading', size: 'lg' },
            { label: 'نوع الخطر', value: d.type, icon: 'fas fa-list-check' },
            { label: 'الخطورة', value: d.severity, icon: 'fas fa-gauge-high', type: 'status', tone: sevTone },
            { label: 'الحالة', value: d.state, icon: 'fas fa-flag', type: 'status', tone: stateTone },
            { label: 'نوع الارتباط', value: d.entityLabel || '—', icon: 'fas fa-link' },
            { label: 'السجل المرتبط', value: d.linked || '—', icon: 'fas fa-folder-tree' },
            { label: 'خطة المعالجة', value: d.mitigation || '—', icon: 'fas fa-clipboard-check', size: 'lg' },
            { label: 'ملاحظات', value: d.notes || '—', icon: 'fas fa-note-sticky', size: 'lg' },
            { label: 'المسؤول', value: d.owner || '—', icon: 'fas fa-user-check' },
            { label: 'أضيف بواسطة', value: d.created || '—', icon: 'fas fa-user-plus' }
        ];

        const actions = [];
        <?php if ($can_edit): ?>
            actions.push({ label: 'تعديل الخطر', icon: 'fas fa-edit', variant: 'primary', onClick: function () {
                EmsDetailsModal.close();
                $('.editRiskBtn[data-id="' + d.id + '"]').trigger('click');
            }});
        <?php endif; ?>
        actions.push({ label: 'إغلاق', icon: 'fas fa-times', variant: 'secondary', close: true });

        EmsDetailsModal.open({ title: 'تفاصيل الخطر', icon: 'fas fa-triangle-exclamation', fields: fields, actions: actions });
    });
</script>

<style>
    .risk-main .stats-grid { display: grid; grid-template-columns: repeat(4, minmax(170px, 1fr)); gap: 12px; }
    .risk-main .stats-section {
        border: 1px solid var(--bdr); border-radius: var(--rl);
        background: linear-gradient(180deg, rgba(255,255,255,.95) 0%, var(--s2) 100%);
        box-shadow: var(--sh); padding: 14px; margin-bottom: 14px;
    }
    .risk-main .stats-card { background: #eee; border: 1px solid #aaa; border-radius: 35px; padding: 18px; box-shadow: 0 2px 8px rgba(26,18,8,.07); position: relative; overflow: hidden; }
    .risk-main .stats-card .stats-icon { width: 55px; height: 55px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.3rem; margin-bottom: 10px; float: left; margin-top: 15px; border: 1px solid #999; background:#fff; color:#000; }
    .risk-main .stats-card .stats-title { color: #555; font-size: .92rem; font-weight: 700; margin-top: 5px; line-height: 1.3; }
    .risk-main .stats-card .stats-value { color: #222; line-height: 1; font-weight: 900; font-variant-numeric: tabular-nums; margin-top: 10px; font-size: 30px; }
    @media (max-width: 900px) { .risk-main .stats-grid { grid-template-columns: repeat(2, minmax(150px,1fr)); } }
    @media (max-width: 560px) { .risk-main .stats-grid { grid-template-columns: 1fr; } }

    .risk-main .risk-hidden { display: none; }
    .risk-main .risk-col-full { grid-column: 1 / -1; }
    .risk-main .table-container { overflow-x: auto; }
    #riskTable.risk-table-nowrap, #riskTable.risk-table-nowrap th, #riskTable.risk-table-nowrap td { white-space: nowrap; }
    #riskTable .action-btns { flex-wrap: nowrap; white-space: nowrap; }
    .risk-main .risk-muted { color: #999; }
    .risk-main .risk-sev-badge { display:inline-block; padding:2px 12px; border-radius:999px; font-size:.78rem; font-weight:800; border:1px solid transparent; }
    .risk-main .risk-sev-high { background:rgba(220,38,38,.14); color:#b91c1c; border-color:rgba(220,38,38,.35); }
    .risk-main .risk-sev-mid { background:rgba(245,158,11,.16); color:#b45309; border-color:rgba(245,158,11,.35); }
    .risk-main .risk-sev-low { background:rgba(22,163,74,.14); color:#15803d; border-color:rgba(22,163,74,.35); }
</style>

</body>

</html>
