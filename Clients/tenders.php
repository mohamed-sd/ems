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
if (!function_exists('tnd_e')) {
    function tnd_e($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
if (!function_exists('tnd_redirect_with_msg')) {
    function tnd_redirect_with_msg($msg)
    {
        header('Location: tenders.php?msg=' . urlencode($msg));
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
$scope_sql        = "t.company_id = $company_id";
$scope_update_sql = "company_id = $company_id";
$not_deleted_sql  = "t.is_deleted = 0";

// رمز CSRF
if (empty($_SESSION['tnd_csrf_token'])) {
    $_SESSION['tnd_csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
}
$tnd_csrf_token = $_SESSION['tnd_csrf_token'];

// القوائم الثابتة (ENUM)
$TND_PARTICIPATION = array('إعداد', 'مقدمة', 'مسحوبة');
$TND_RESULT = array('قيد التقييم', 'فوز', 'خسارة', 'إلغاء');

// توليد الكود المقترح التالي (TND-NNNN) — للعرض فقط
$next_tnd_code = 'TND-0001';
$last_code_sql = "SELECT tender_code FROM tenders
                  WHERE tender_code REGEXP '^TND-[0-9]+$' AND company_id = $company_id AND is_deleted = 0
                  ORDER BY CAST(SUBSTRING(tender_code, 5) AS UNSIGNED) DESC LIMIT 1";
$last_code_res = @mysqli_query($conn, $last_code_sql);
if ($last_code_res && mysqli_num_rows($last_code_res) > 0) {
    $last_code_row = mysqli_fetch_assoc($last_code_res);
    $last_num = intval(substr($last_code_row['tender_code'], 4));
    $next_tnd_code = 'TND-' . str_pad($last_num + 1, 4, '0', STR_PAD_LEFT);
}

// صلاحيات المستخدم على وحدة المناقصات
$module_query = "SELECT id FROM modules WHERE code = 'Clients/tenders.php' LIMIT 1";
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
    header('Location: ../login.php?msg=' . urlencode('لا توجد صلاحية عرض المناقصات ❌'));
    exit();
}

// ══════════════════════════════════════════════════════════════════════════════
// معالجة إضافة / تعديل مناقصة عبر POST
// ══════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tender_code'])) {
    $posted_csrf = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if (empty($posted_csrf) || !hash_equals($tnd_csrf_token, $posted_csrf)) {
        tnd_redirect_with_msg('جلسة النموذج غير صالحة، يرجى إعادة المحاولة ❌');
    }

    $tnd_id     = isset($_POST['tnd_id']) ? intval($_POST['tnd_id']) : 0;
    $is_editing = $tnd_id > 0;

    if ($is_editing && !$can_edit) {
        tnd_redirect_with_msg('لا توجد صلاحية تعديل المناقصات ❌');
    } elseif (!$is_editing && !$can_add) {
        tnd_redirect_with_msg('لا توجد صلاحية إضافة مناقصات جديدة ❌');
    }

    // الكود
    $tnd_code_raw = isset($_POST['tender_code']) ? trim($_POST['tender_code']) : '';
    if ($tnd_code_raw === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $tnd_code_raw)) {
        tnd_redirect_with_msg('كود المناقصة غير صالح. استخدم أحرفًا وأرقامًا و - أو _ فقط ❌');
    }

    // الاسم / رقم الدعوة
    $name_raw = isset($_POST['name']) ? trim($_POST['name']) : '';
    if ($name_raw === '') {
        tnd_redirect_with_msg('رقم الدعوة / العنوان مطلوب ❌');
    }

    // التحقق من القوائم الثابتة (ENUM)
    $participation_raw = isset($_POST['participation_state']) ? trim($_POST['participation_state']) : 'إعداد';
    if (!in_array($participation_raw, $TND_PARTICIPATION, true)) {
        tnd_redirect_with_msg('حالة المشاركة غير صالحة ❌');
    }
    $result_raw = isset($_POST['result']) ? trim($_POST['result']) : 'قيد التقييم';
    if (!in_array($result_raw, $TND_RESULT, true)) {
        tnd_redirect_with_msg('قيمة النتيجة غير صالحة ❌');
    }

    // الجهة الطارحة — التحقق من النطاق (إن حُدِّدت)
    $authority_in = isset($_POST['authority_id']) ? intval($_POST['authority_id']) : 0;
    if ($authority_in > 0) {
        $achk = mysqli_query($conn, "SELECT id FROM clients WHERE id = $authority_in AND company_id = $company_id AND is_deleted = 0 LIMIT 1");
        if (!$achk || mysqli_num_rows($achk) === 0) {
            tnd_redirect_with_msg('الجهة الطارحة غير موجودة أو خارج نطاق شركتك ❌');
        }
    }
    $authority_sql = $authority_in > 0 ? "'$authority_in'" : 'NULL';

    // الفرصة المرتبطة — التحقق من النطاق (إن حُدِّدت)
    $opportunity_in = isset($_POST['opportunity_id']) ? intval($_POST['opportunity_id']) : 0;
    if ($opportunity_in > 0) {
        $ochk = mysqli_query($conn, "SELECT id FROM opportunities WHERE id = $opportunity_in AND company_id = $company_id AND is_deleted = 0 LIMIT 1");
        if (!$ochk || mysqli_num_rows($ochk) === 0) {
            tnd_redirect_with_msg('الفرصة المرتبطة غير موجودة أو خارج نطاق شركتك ❌');
        }
    }
    $opportunity_sql = $opportunity_in > 0 ? "'$opportunity_in'" : 'NULL';

    // تنظيف بقية الحقول
    $tender_code   = mysqli_real_escape_string($conn, $tnd_code_raw);
    $name          = mysqli_real_escape_string($conn, $name_raw);
    $participation = mysqli_real_escape_string($conn, $participation_raw);
    $result        = mysqli_real_escape_string($conn, $result_raw);
    $result_reason = mysqli_real_escape_string($conn, isset($_POST['result_reason']) ? trim($_POST['result_reason']) : '');
    $notes         = mysqli_real_escape_string($conn, isset($_POST['notes']) ? trim($_POST['notes']) : '');
    $cdate_raw     = isset($_POST['closing_date']) ? trim($_POST['closing_date']) : '';
    $cdate_sql     = preg_match('/^\d{4}-\d{2}-\d{2}$/', $cdate_raw) ? "'$cdate_raw'" : 'NULL';
    $created_by    = intval($_SESSION['user']['id']);

    if ($is_editing) {
        $owner = mysqli_query($conn, "SELECT id FROM tenders WHERE id = $tnd_id AND company_id = $company_id AND is_deleted = 0 LIMIT 1");
        if (!$owner || mysqli_num_rows($owner) === 0) {
            tnd_redirect_with_msg('لا يمكنك تعديل مناقصة لا تتبع لشركتك ❌');
        }
        $dup = mysqli_query($conn, "SELECT id FROM tenders WHERE tender_code = '$tender_code' AND id != $tnd_id AND company_id = $company_id AND is_deleted = 0");
        if ($dup && mysqli_num_rows($dup) > 0) {
            tnd_redirect_with_msg('كود المناقصة موجود مسبقاً داخل شركتك ❌');
        }

        $update_query = "UPDATE tenders SET
            tender_code = '$tender_code', name = '$name', authority_id = $authority_sql,
            opportunity_id = $opportunity_sql, closing_date = $cdate_sql,
            participation_state = '$participation', result = '$result',
            result_reason = '$result_reason', notes = '$notes'
            WHERE id = $tnd_id AND $scope_update_sql AND is_deleted = 0";

        if (mysqli_query($conn, $update_query)) {
            if (class_exists('\\App\\Services\\ActivityLogService')) {
                \App\Services\ActivityLogService::logUpdate('tenders', 'tenders', $tnd_id, null, ['tender_code' => $tnd_code_raw]);
            }
            tnd_redirect_with_msg('تم تعديل المناقصة بنجاح ✅');
        }
        error_log('tenders.php update failed: ' . mysqli_error($conn));
        tnd_redirect_with_msg('حدث خطأ أثناء التعديل ❌');
    } else {
        $dup = mysqli_query($conn, "SELECT id FROM tenders WHERE tender_code = '$tender_code' AND company_id = $company_id AND is_deleted = 0");
        if ($dup && mysqli_num_rows($dup) > 0) {
            tnd_redirect_with_msg('كود المناقصة موجود مسبقاً داخل شركتك ❌');
        }

        $insert_query = "INSERT INTO tenders
            (company_id, tender_code, name, authority_id, opportunity_id, closing_date,
             participation_state, result, result_reason, notes, created_by)
            VALUES
            ('$company_id', '$tender_code', '$name', $authority_sql, $opportunity_sql, $cdate_sql,
             '$participation', '$result', '$result_reason', '$notes', '$created_by')";

        if (mysqli_query($conn, $insert_query)) {
            $new_id = (int) mysqli_insert_id($conn);
            if (class_exists('\\App\\Services\\ActivityLogService')) {
                \App\Services\ActivityLogService::logCreate('tenders', 'tenders', $new_id, ['tender_code' => $tnd_code_raw]);
            }
            tnd_redirect_with_msg('تم إضافة المناقصة بنجاح ✅');
        }
        error_log('tenders.php insert failed: ' . mysqli_error($conn));
        tnd_redirect_with_msg('حدث خطأ أثناء الإضافة ❌');
    }
}

// ══════════════════════════════════════════════════════════════════════════════
// معالجة الحذف الناعم
// ══════════════════════════════════════════════════════════════════════════════
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $delete_csrf = isset($_GET['csrf_token']) ? $_GET['csrf_token'] : '';

    if (!$can_delete) {
        tnd_redirect_with_msg('لا توجد صلاحية حذف المناقصات ❌');
    }
    if (empty($delete_csrf) || !hash_equals($tnd_csrf_token, $delete_csrf)) {
        tnd_redirect_with_msg('جلسة الحذف غير صالحة، يرجى إعادة المحاولة ❌');
    }
    $chk = mysqli_query($conn, "SELECT id FROM tenders WHERE id = $delete_id AND company_id = $company_id AND is_deleted = 0 LIMIT 1");
    if (!$chk || mysqli_num_rows($chk) === 0) {
        tnd_redirect_with_msg('لا يمكنك حذف مناقصة لا تتبع لشركتك ❌');
    }
    $deleted_by = intval($_SESSION['user']['id']);
    $del = "UPDATE tenders SET is_deleted = 1, deleted_at = NOW(), deleted_by = $deleted_by
            WHERE id = $delete_id AND $scope_update_sql AND is_deleted = 0";
    if (mysqli_query($conn, $del)) {
        if (class_exists('\\App\\Services\\ActivityLogService')) {
            \App\Services\ActivityLogService::logDelete('tenders', 'tenders', $delete_id);
        }
        tnd_redirect_with_msg('تم حذف المناقصة بنجاح ✅');
    }
    error_log('tenders.php soft delete failed: ' . mysqli_error($conn));
    tnd_redirect_with_msg('حدث خطأ أثناء الحذف ❌');
}

// ══════════════════════════════════════════════════════════════════════════════
// قوائم الاختيار (ضمن نطاق الشركة)
// ══════════════════════════════════════════════════════════════════════════════
$authority_options = array();
$au_res = mysqli_query($conn, "SELECT id, client_code, client_name FROM clients WHERE company_id = $company_id AND is_deleted = 0 ORDER BY client_name ASC");
if ($au_res) { while ($au = mysqli_fetch_assoc($au_res)) { $authority_options[] = $au; } }

$opp_options = array();
$op_res = mysqli_query($conn, "SELECT id, opp_code, title FROM opportunities WHERE company_id = $company_id AND is_deleted = 0 ORDER BY id DESC");
if ($op_res) { while ($op = mysqli_fetch_assoc($op_res)) { $opp_options[] = $op; } }

// خرائط سريعة للأسماء
$authority_names = array();
foreach ($authority_options as $au) { $authority_names[intval($au['id'])] = $au['client_name']; }
$opp_titles = array();
foreach ($opp_options as $op) { $opp_titles[intval($op['id'])] = $op['title']; }

// ══════════════════════════════════════════════════════════════════════════════
// جلب المناقصات + الإحصائيات
// ══════════════════════════════════════════════════════════════════════════════
$rows = array();
$stat_total = 0;
$stat_submitted = 0;
$stat_won = 0;
$stat_evaluating = 0;

$q = "SELECT t.*, u.name AS creator_name, c.client_name AS authority_name, o.title AS opportunity_title
      FROM tenders t
      LEFT JOIN users u ON u.id = t.created_by
      LEFT JOIN clients c ON c.id = t.authority_id
      LEFT JOIN opportunities o ON o.id = t.opportunity_id
      WHERE $scope_sql AND $not_deleted_sql
      ORDER BY t.id DESC";
$res = mysqli_query($conn, $q);
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $rows[] = $row;
        $stat_total++;
        if ($row['participation_state'] === 'مقدمة') $stat_submitted++;
        if ($row['result'] === 'فوز') $stat_won++;
        if ($row['result'] === 'قيد التقييم') $stat_evaluating++;
    }
}

$page_title = "المناقصات";
include("../inheader.php");
include('../insidebar.php');

// لون شارة النتيجة
function tnd_result_class($result)
{
    switch ($result) {
        case 'فوز':   return 'tnd-badge-win';
        case 'خسارة': return 'tnd-badge-lose';
        case 'إلغاء': return 'tnd-badge-cancel';
        default:      return 'tnd-badge-eval';
    }
}
// لون تفاصيل النتيجة (EmsDetailsModal tone)
function tnd_result_tone($result)
{
    switch ($result) {
        case 'فوز':   return 'active';
        case 'خسارة': return 'inactive';
        case 'إلغاء': return 'inactive';
        default:      return 'pending';
    }
}
?>

<div class="main tnd-main ems-unified-page-shell">

    <?php
    $header_title = 'المناقصات';
    $header_icon = 'fas fa-gavel';
    $header_actions = array();
    if ($can_add) {
        $header_actions[] = array('id' => 'toggleForm', 'class' => 'add-btn', 'icon' => 'fa fa-solid fa-plus', 'label' => '', 'label_class' => 'tnd-toggle-form-text');
    } else {
        $header_actions[] = array('tag' => 'button', 'class' => '', 'disabled' => true, 'icon' => 'fas fa-plus-circle', 'label' => 'إضافة (بدون صلاحيات)');
    }
    $header_actions[] = array('id' => 'toggleStats', 'class' => 'btn', 'title' => 'إظهار أو إخفاء الإحصائيات', 'icon' => 'fas fa-eye', 'label' => 'إظهار الإحصائيات', 'label_class' => 'tnd-toggle-stats-text');
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fa-solid fa-share', 'label' => '');
    include('../includes/page_header.php');
    ?>

    <?php if (!empty($_GET['msg'])):
        $isSuccess = strpos($_GET['msg'], '✅') !== false; ?>
        <div class="success-message <?= $isSuccess ? 'is-success' : 'is-error' ?>">
            <i class="fas <?= $isSuccess ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?php echo tnd_e($_GET['msg']); ?>
        </div>
    <?php endif; ?>

    <div class="stats-section tnd-hidden" id="tndStatsSection">
        <div class="stats-grid">
            <div class="stats-card">
                <div class="stats-icon"><i class="fas fa-gavel"></i></div>
                <div class="stats-value"><?php echo $stat_total; ?></div>
                <div class="stats-title">إجمالي المناقصات</div>
            </div>
            <div class="stats-card">
                <div class="stats-icon"><i class="fas fa-paper-plane"></i></div>
                <div class="stats-value"><?php echo $stat_submitted; ?></div>
                <div class="stats-title">مقدَّمة</div>
            </div>
            <div class="stats-card">
                <div class="stats-icon"><i class="fas fa-trophy"></i></div>
                <div class="stats-value"><?php echo $stat_won; ?></div>
                <div class="stats-title">فائزة</div>
            </div>
            <div class="stats-card">
                <div class="stats-icon"><i class="fas fa-hourglass-half"></i></div>
                <div class="stats-value"><?php echo $stat_evaluating; ?></div>
                <div class="stats-title">قيد التقييم</div>
            </div>
        </div>
    </div>

    <!-- فورم إضافة / تعديل مناقصة -->
    <form id="tndForm" action="" method="post" class="allforms">
        <div class="card-header">
            <h5><i class="fas fa-edit"></i> <span id="formTitle">إضافة مناقصة جديدة</span></h5>
        </div>
        <input type="hidden" name="tnd_id" id="tnd_id" value="">
        <input type="hidden" name="csrf_token" value="<?php echo tnd_e($tnd_csrf_token); ?>">
        <div class="card shadow-sm pu-form-card">
            <div class="card-body">
                <div class="form-grid">
                    <div id="generated_code_wrapper" class="auto">
                        <label><i class="fas fa-magic"></i> كود المناقصة المولد <i class="fas fa-info-circle tnd-info-icon"></i></label>
                        <input type="text" id="generated_tnd_code" class="generated-code-field" value="<?php echo tnd_e($next_tnd_code); ?>" readonly tabindex="-1" title="هذا الكود للعرض فقط، انسخه إلى حقل كود المناقصة" />
                        <div class="generated-code-hint"></div>
                    </div>

                    <div>
                        <label><i class="fas fa-barcode"></i> كود المناقصة *</label>
                        <input type="text" name="tender_code" id="tender_code" placeholder="مثال: TND-001" required pattern="[A-Za-z0-9-_]+" />
                    </div>
                    <div>
                        <label><i class="fas fa-heading"></i> رقم الدعوة / العنوان *</label>
                        <input type="text" name="name" id="name" placeholder="رقم الدعوة أو عنوان المناقصة" required />
                    </div>
                    <div>
                        <label><i class="fas fa-building"></i> الجهة الطارحة</label>
                        <select name="authority_id" id="authority_id">
                            <option value="">-- بدون / غير محدد --</option>
                            <?php foreach ($authority_options as $au): ?>
                                <option value="<?php echo intval($au['id']); ?>"><?php echo tnd_e($au['client_name']); ?> (<?php echo tnd_e($au['client_code']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label><i class="fas fa-lightbulb"></i> الفرصة المرتبطة</label>
                        <select name="opportunity_id" id="opportunity_id">
                            <option value="">-- بدون / غير محدد --</option>
                            <?php foreach ($opp_options as $op): ?>
                                <option value="<?php echo intval($op['id']); ?>"><?php echo tnd_e($op['title']); ?> (<?php echo tnd_e($op['opp_code']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label><i class="fas fa-calendar-xmark"></i> تاريخ الإغلاق</label>
                        <input type="date" name="closing_date" id="closing_date" />
                    </div>
                    <div>
                        <label><i class="fas fa-flag"></i> حالة المشاركة</label>
                        <select name="participation_state" id="participation_state">
                            <?php foreach ($TND_PARTICIPATION as $ps): ?>
                                <option value="<?php echo tnd_e($ps); ?>"><?php echo tnd_e($ps); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label><i class="fas fa-award"></i> النتيجة</label>
                        <select name="result" id="result">
                            <?php foreach ($TND_RESULT as $rs): ?>
                                <option value="<?php echo tnd_e($rs); ?>"><?php echo tnd_e($rs); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label><i class="fas fa-comment-dots"></i> سبب النتيجة</label>
                        <input type="text" name="result_reason" id="result_reason" placeholder="سبب النتيجة" />
                    </div>
                    <div class="tnd-col-full">
                        <label><i class="fas fa-note-sticky"></i> ملاحظات</label>
                        <textarea name="notes" id="notes" rows="2" placeholder="أي ملاحظات إضافية"></textarea>
                    </div>
                </div>
                <div class="pu-form-actions">
                    <button type="submit" class="btn-submit"><i class="fas fa-save"></i> <span id="submitBtnText">حفظ المناقصة</span></button>
                    <button type="button" id="tndFormCancelBtn" class="btn-cancel"><i class="fas fa-times"></i> إلغاء</button>
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
                <label><i class="fa fa-flag"></i> حالة المشاركة</label>
                <select id="filterParticipation" class="form-control">
                    <option value="">-- كل الحالات --</option>
                </select>
            </div>
            <div class="filter-field">
                <label><i class="fa fa-award"></i> النتيجة</label>
                <select id="filterResult" class="form-control">
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
                <table id="tndTable" class="display tnd-table-nowrap no-datatable">
                    <thead>
                        <tr>
                            <th>إجراءات</th>
                            <th width="90">الكود</th>
                            <th>الاسم / الدعوة</th>
                            <th>الجهة الطارحة</th>
                            <th>تاريخ الإغلاق</th>
                            <th>حالة المشاركة</th>
                            <th>النتيجة</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row):
                            $authority_label = $row['authority_name'] !== null ? $row['authority_name'] : '';
                            $opp_label = $row['opportunity_title'] !== null ? $row['opportunity_title'] : '';
                            $created_label = function_exists('ems_actor_label') ? ems_actor_label($conn, intval($row['created_by'])) : ($row['creator_name'] ?? '');
                            ?>
                            <tr>
                                <td>
                                    <div class="action-btns">
                                        <a href="javascript:void(0)" class="action-btn view viewTndBtn"
                                            data-id="<?php echo intval($row['id']); ?>"
                                            data-code="<?php echo tnd_e($row['tender_code']); ?>"
                                            data-name="<?php echo tnd_e($row['name']); ?>"
                                            data-authority="<?php echo tnd_e($authority_label); ?>"
                                            data-opportunity="<?php echo tnd_e($opp_label); ?>"
                                            data-closing="<?php echo tnd_e($row['closing_date']); ?>"
                                            data-participation="<?php echo tnd_e($row['participation_state']); ?>"
                                            data-result="<?php echo tnd_e($row['result']); ?>"
                                            data-result-reason="<?php echo tnd_e($row['result_reason']); ?>"
                                            data-notes="<?php echo tnd_e($row['notes']); ?>"
                                            data-created="<?php echo tnd_e($created_label); ?>"
                                            title="عرض التفاصيل"><i class="fas fa-eye"></i></a>
                                        <?php if ($can_edit): ?>
                                            <a href="javascript:void(0)" class="action-btn edit editTndBtn"
                                                data-id="<?php echo intval($row['id']); ?>"
                                                data-code="<?php echo tnd_e($row['tender_code']); ?>"
                                                data-name="<?php echo tnd_e($row['name']); ?>"
                                                data-authority-id="<?php echo intval($row['authority_id']); ?>"
                                                data-opportunity-id="<?php echo intval($row['opportunity_id']); ?>"
                                                data-closing="<?php echo tnd_e($row['closing_date']); ?>"
                                                data-participation="<?php echo tnd_e($row['participation_state']); ?>"
                                                data-result="<?php echo tnd_e($row['result']); ?>"
                                                data-result-reason="<?php echo tnd_e($row['result_reason']); ?>"
                                                data-notes="<?php echo tnd_e($row['notes']); ?>"
                                                title="تعديل"><i class="fas fa-edit"></i></a>
                                        <?php endif; ?>
                                        <?php if ($can_delete): ?>
                                            <a href="?delete_id=<?php echo urlencode($row['id']); ?>&csrf_token=<?php echo urlencode($tnd_csrf_token); ?>"
                                                class="action-btn delete"
                                                onclick="return confirm('هل أنت متأكد من حذف هذه المناقصة؟')" title="حذف"><i class="fas fa-trash-alt"></i></a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><strong class="tnd-code-cell"><?php echo tnd_e($row['tender_code']); ?></strong></td>
                                <td><?php echo $row['name'] !== '' ? tnd_e($row['name']) : '<span class="tnd-muted">—</span>'; ?></td>
                                <td><?php echo $authority_label !== '' ? tnd_e($authority_label) : '<span class="tnd-muted">—</span>'; ?></td>
                                <td class="tnd-num"><?php echo $row['closing_date'] !== null ? tnd_e($row['closing_date']) : '<span class="tnd-muted">—</span>'; ?></td>
                                <td><?php echo tnd_e($row['participation_state']); ?></td>
                                <td><span class="tnd-badge <?php echo tnd_result_class($row['result']); ?>"><?php echo tnd_e($row['result']); ?></span></td>
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
        const tndTable = $('#tndTable').DataTable({
            autoWidth: false,
            stateSave: false,
            language: { url: '/ems/assets/i18n/datatables/ar.json' }
        });

        function fillFilterOptions(columnIndex, selectId) {
            const select = $(selectId);
            const values = [];
            tndTable.column(columnIndex).data().each(function (value) {
                const text = $('<div>').html(value).text().trim();
                if (text !== '' && values.indexOf(text) === -1) values.push(text);
            });
            values.sort();
            values.forEach(function (val) {
                select.append('<option value="' + val.replace(/"/g, '&quot;') + '">' + val + '</option>');
            });
        }
        fillFilterOptions(5, '#filterParticipation');
        fillFilterOptions(6, '#filterResult');

        $('#filterParticipation').on('change', function () {
            const value = $.fn.dataTable.util.escapeRegex($(this).val());
            tndTable.column(5).search(value ? '^' + value + '$' : '', true, false).draw();
        });
        $('#filterResult').on('change', function () {
            const value = $.fn.dataTable.util.escapeRegex($(this).val());
            tndTable.column(6).search(value ? value : '', true, false).draw();
        });
    });

    // ── إظهار/إخفاء الفورم والإحصائيات ──
    const formToggleBtn = $('#toggleForm');
    const tndForm = $('#tndForm');
    const formTitle = $('#formTitle');
    const submitBtnText = $('#submitBtnText');
    const generatedCodeWrapper = $('#generated_code_wrapper');
    const formCancelBtn = $('#tndFormCancelBtn');
    const statsToggleBtn = $('#toggleStats');
    const statsSection = $('#tndStatsSection');

    function setAddMode() { formTitle.text('إضافة مناقصة جديدة'); submitBtnText.text('حفظ المناقصة'); generatedCodeWrapper.show(); }
    function setEditMode() { formTitle.text('تعديل المناقصة'); submitBtnText.text('تحديث المناقصة'); generatedCodeWrapper.hide(); }
    function resetForm() { if (!tndForm.length) return; tndForm[0].reset(); $('#tnd_id').val(''); setAddMode(); if (window.EmsSelect) EmsSelect.refresh(); }

    function updateFormToggleState(isOpen) {
        if (!formToggleBtn.length) return;
        formToggleBtn.toggleClass('is-active', isOpen);
        formToggleBtn.attr('aria-expanded', isOpen ? 'true' : 'false');
    }
    function updateStatsToggleState(isVisible) {
        if (!statsToggleBtn.length) return;
        statsToggleBtn.toggleClass('is-active', isVisible);
        statsToggleBtn.find('.tnd-toggle-stats-text').text('إظهار الإحصائيات');
        const icon = statsToggleBtn.find('i').first();
        icon.toggleClass('fa-chart-pie', isVisible);
        icon.toggleClass('fa-eye', !isVisible);
    }

    setAddMode();
    updateFormToggleState(tndForm.is(':visible'));
    updateStatsToggleState(statsSection.is(':visible'));

    formToggleBtn.on('click', function (e) {
        e.preventDefault();
        if (!tndForm.length) return;
        if (tndForm.is(':visible')) {
            tndForm.stop(true, true).slideUp(250, function () { tndForm.removeClass('allforms-visible'); resetForm(); updateFormToggleState(false); });
        } else {
            resetForm();
            tndForm.addClass('allforms-visible').hide();
            tndForm.stop(true, true).slideDown(250, function () { updateFormToggleState(true); });
        }
    });
    formCancelBtn.on('click', function () {
        if (!tndForm.length || !tndForm.is(':visible')) return;
        tndForm.stop(true, true).slideUp(250, function () { tndForm.removeClass('allforms-visible'); resetForm(); updateFormToggleState(false); });
    });
    statsToggleBtn.on('click', function (e) {
        e.preventDefault();
        if (!statsSection.length) return;
        if (statsSection.is(':visible')) {
            statsSection.stop(true, true).slideUp(250, function () { statsSection.addClass('tnd-hidden'); updateStatsToggleState(false); });
        } else {
            statsSection.removeClass('tnd-hidden').hide();
            statsSection.stop(true, true).slideDown(250, function () { updateStatsToggleState(true); });
        }
    });

    // ── تعبئة الفورم للتعديل ──
    function fillTndForm(d) {
        $('#tnd_id').val(d.id);
        $('#tender_code').val(d.code);
        $('#name').val(d.name || '');
        $('#authority_id').val(d.authorityId ? String(d.authorityId) : '');
        $('#opportunity_id').val(d.opportunityId ? String(d.opportunityId) : '');
        $('#closing_date').val(d.closing || '');
        $('#participation_state').val(d.participation || 'إعداد');
        $('#result').val(d.result || 'قيد التقييم');
        $('#result_reason').val(d.resultReason || '');
        $('#notes').val(d.notes || '');
        if (window.EmsSelect) EmsSelect.refresh();
        setEditMode();
        if (!tndForm.is(':visible')) {
            tndForm.addClass('allforms-visible').hide();
            tndForm.stop(true, true).slideDown(250, function () { updateFormToggleState(true); });
        } else { updateFormToggleState(true); }
        $('html, body').animate({ scrollTop: $('#tndForm').offset().top - 100 }, 500);
    }

    $(document).on('click', '.editTndBtn', function () {
        fillTndForm({
            id: $(this).data('id'), code: $(this).data('code'), name: $(this).data('name'),
            authorityId: $(this).data('authority-id'), opportunityId: $(this).data('opportunity-id'),
            closing: $(this).data('closing'), participation: $(this).data('participation'),
            result: $(this).data('result'), resultReason: $(this).data('result-reason'), notes: $(this).data('notes')
        });
    });

    // ── عرض التفاصيل عبر EmsDetailsModal الموحّد ──
    $(document).on('click', '.viewTndBtn', function () {
        const d = $(this).data();
        const resultTone = {
            'فوز': 'active',
            'خسارة': 'inactive',
            'إلغاء': 'inactive',
            'قيد التقييم': 'pending'
        }[String(d.result)] || 'pending';
        const fields = [
            { label: 'كود المناقصة', value: d.code, icon: 'fas fa-barcode' },
            { label: 'الاسم / الدعوة', value: d.name || '—', icon: 'fas fa-heading', size: 'lg' },
            { label: 'الجهة الطارحة', value: d.authority || '—', icon: 'fas fa-building' },
            { label: 'الفرصة المرتبطة', value: d.opportunity || '—', icon: 'fas fa-lightbulb', size: 'lg' },
            { label: 'تاريخ الإغلاق', value: d.closing || '—', icon: 'fas fa-calendar-xmark' },
            { label: 'حالة المشاركة', value: d.participation || '—', icon: 'fas fa-flag', type: 'status' },
            { label: 'النتيجة', value: d.result || '—', icon: 'fas fa-award', type: 'status', tone: resultTone },
            { label: 'سبب النتيجة', value: d.resultReason || '—', icon: 'fas fa-comment-dots', size: 'lg' },
            { label: 'ملاحظات', value: d.notes || '—', icon: 'fas fa-note-sticky', size: 'lg' },
            { label: 'أضيف بواسطة', value: d.created || '—', icon: 'fas fa-user-plus' }
        ];

        const actions = [];
        <?php if ($can_edit): ?>
            actions.push({ label: 'تعديل المناقصة', icon: 'fas fa-edit', variant: 'primary', onClick: function () {
                EmsDetailsModal.close();
                $('.editTndBtn[data-id="' + d.id + '"]').trigger('click');
            }});
        <?php endif; ?>
        actions.push({ label: 'إغلاق', icon: 'fas fa-times', variant: 'secondary', close: true });

        EmsDetailsModal.open({ title: 'تفاصيل المناقصة', icon: 'fas fa-gavel', fields: fields, actions: actions });
    });
</script>

<style>
    .tnd-main .stats-grid { display: grid; grid-template-columns: repeat(4, minmax(170px, 1fr)); gap: 12px; }
    .tnd-main .stats-section {
        border: 1px solid var(--bdr); border-radius: var(--rl);
        background: linear-gradient(180deg, rgba(255,255,255,.95) 0%, var(--s2) 100%);
        box-shadow: var(--sh); padding: 14px; margin-bottom: 14px;
    }
    .tnd-main .stats-card { background: #eee; border: 1px solid #aaa; border-radius: 35px; padding: 18px; box-shadow: 0 2px 8px rgba(26,18,8,.07); position: relative; overflow: hidden; }
    .tnd-main .stats-card .stats-icon { width: 55px; height: 55px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.3rem; margin-bottom: 10px; float: left; margin-top: 15px; border: 1px solid #999; background:#fff; color:#000; }
    .tnd-main .stats-card .stats-title { color: #555; font-size: .92rem; font-weight: 700; margin-top: 5px; line-height: 1.3; }
    .tnd-main .stats-card .stats-value { color: #222; line-height: 1; font-weight: 900; font-variant-numeric: tabular-nums; margin-top: 10px; font-size: 30px; }
    @media (max-width: 900px) { .tnd-main .stats-grid { grid-template-columns: repeat(2, minmax(150px,1fr)); } }
    @media (max-width: 560px) { .tnd-main .stats-grid { grid-template-columns: 1fr; } }

    .tnd-main .tnd-hidden { display: none; }
    .tnd-main .tnd-col-full { grid-column: 1 / -1; }
    .tnd-main .table-container { overflow-x: auto; }
    #tndTable.tnd-table-nowrap, #tndTable.tnd-table-nowrap th, #tndTable.tnd-table-nowrap td { white-space: nowrap; }
    #tndTable .action-btns { flex-wrap: nowrap; white-space: nowrap; }
    .tnd-main .tnd-num { font-variant-numeric: tabular-nums; font-weight: 700; }
    .tnd-main .tnd-muted { color: #999; }
    .tnd-main .tnd-badge { display:inline-block; padding:2px 10px; border-radius:999px; font-size:.75rem; font-weight:800; border:1px solid transparent; }
    .tnd-main .tnd-badge-win { background:rgba(34,197,94,.14); color:#15803d; border-color:rgba(34,197,94,.3); }
    .tnd-main .tnd-badge-lose { background:rgba(239,68,68,.14); color:#b91c1c; border-color:rgba(239,68,68,.3); }
    .tnd-main .tnd-badge-cancel { background:rgba(107,114,128,.14); color:#4b5563; border-color:rgba(107,114,128,.3); }
    .tnd-main .tnd-badge-eval { background:rgba(245,158,11,.14); color:#b45309; border-color:rgba(245,158,11,.3); }
</style>

</body>

</html>
