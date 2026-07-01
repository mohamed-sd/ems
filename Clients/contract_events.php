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
if (!function_exists('evt_e')) {
    function evt_e($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
if (!function_exists('evt_redirect_with_msg')) {
    function evt_redirect_with_msg($msg)
    {
        header('Location: contract_events.php?msg=' . urlencode($msg));
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
$scope_sql        = "e.company_id = $company_id";
$scope_update_sql = "company_id = $company_id";
$not_deleted_sql  = "e.is_deleted = 0";

// رمز CSRF
if (empty($_SESSION['evt_csrf_token'])) {
    $_SESSION['evt_csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
}
$evt_csrf_token = $_SESSION['evt_csrf_token'];

// القوائم الثابتة (ENUM)
$EVT_TYPES = array('انخفاض إنتاج', 'تأخر اعتماد العميل', 'نقص معدات', 'تأخر موردين', 'قوة قاهرة', 'أمر تغيير', 'مطالبة إضافية', 'تمديد محتمل', 'خلاف تشغيلي', 'إخلال طرف');
$EVT_PARTIES = array('الشركة', 'العميل', 'المورد');
$EVT_STATES = array('مفتوح', 'قيد المتابعة', 'مغلق');

// توليد الكود المقترح التالي (EVT-NNNN) — للعرض فقط
$next_evt_code = 'EVT-0001';
$last_code_sql = "SELECT event_code FROM contract_events
                  WHERE event_code REGEXP '^EVT-[0-9]+$' AND company_id = $company_id AND is_deleted = 0
                  ORDER BY CAST(SUBSTRING(event_code, 5) AS UNSIGNED) DESC LIMIT 1";
$last_code_res = @mysqli_query($conn, $last_code_sql);
if ($last_code_res && mysqli_num_rows($last_code_res) > 0) {
    $last_code_row = mysqli_fetch_assoc($last_code_res);
    $last_num = intval(substr($last_code_row['event_code'], 4));
    $next_evt_code = 'EVT-' . str_pad($last_num + 1, 4, '0', STR_PAD_LEFT);
}

// صلاحيات المستخدم على وحدة سجل الأحداث التعاقدية
$module_query = "SELECT id FROM modules WHERE code = 'Clients/contract_events.php' LIMIT 1";
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
    header('Location: ../login.php?msg=' . urlencode('لا توجد صلاحية عرض سجل الأحداث التعاقدية ❌'));
    exit();
}

// ══════════════════════════════════════════════════════════════════════════════
// معالجة إضافة / تعديل حدث عبر POST
// ══════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['event_code'])) {
    $posted_csrf = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if (empty($posted_csrf) || !hash_equals($evt_csrf_token, $posted_csrf)) {
        evt_redirect_with_msg('جلسة النموذج غير صالحة، يرجى إعادة المحاولة ❌');
    }

    $evt_id     = isset($_POST['evt_id']) ? intval($_POST['evt_id']) : 0;
    $is_editing = $evt_id > 0;

    if ($is_editing && !$can_edit) {
        evt_redirect_with_msg('لا توجد صلاحية تعديل الأحداث ❌');
    } elseif (!$is_editing && !$can_add) {
        evt_redirect_with_msg('لا توجد صلاحية إضافة أحداث جديدة ❌');
    }

    // الكود
    $evt_code_raw = isset($_POST['event_code']) ? trim($_POST['event_code']) : '';
    if ($evt_code_raw === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $evt_code_raw)) {
        evt_redirect_with_msg('كود الحدث غير صالح. استخدم أحرفًا وأرقامًا و - أو _ فقط ❌');
    }

    // التحقق من القوائم الثابتة (ENUM)
    $type_raw = isset($_POST['event_type']) ? trim($_POST['event_type']) : '';
    if (!in_array($type_raw, $EVT_TYPES, true)) {
        evt_redirect_with_msg('نوع الحدث غير صالح ❌');
    }
    $party_raw = isset($_POST['party']) ? trim($_POST['party']) : '';
    if ($party_raw !== '' && !in_array($party_raw, $EVT_PARTIES, true)) {
        evt_redirect_with_msg('قيمة الطرف غير صالحة ❌');
    }
    $state_raw = isset($_POST['state']) ? trim($_POST['state']) : 'مفتوح';
    if (!in_array($state_raw, $EVT_STATES, true)) {
        evt_redirect_with_msg('قيمة الحالة غير صالحة ❌');
    }

    // العقد المرتبط — التحقق من النطاق (إن حُدِّد)
    $contract_in = isset($_POST['contract_id']) ? intval($_POST['contract_id']) : 0;
    if ($contract_in > 0) {
        $cchk = mysqli_query($conn, "SELECT id FROM contracts WHERE id = $contract_in AND company_id = $company_id AND is_deleted = 0 LIMIT 1");
        if (!$cchk || mysqli_num_rows($cchk) === 0) {
            evt_redirect_with_msg('العقد المرتبط غير موجود أو خارج نطاق شركتك ❌');
        }
    }
    $contract_sql = $contract_in > 0 ? "'$contract_in'" : 'NULL';

    // تاريخ الحدث — DATETIME (نقبل صيغة datetime-local ونحوّلها)
    $edate_raw = isset($_POST['event_date']) ? trim($_POST['event_date']) : '';
    $edate_sql = 'NULL';
    if ($edate_raw !== '') {
        if (preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}(:\d{2})?$/', $edate_raw)) {
            $edate_norm = str_replace('T', ' ', $edate_raw);
            if (strlen($edate_norm) === 16) {
                $edate_norm .= ':00';
            }
            $edate_sql = "'" . mysqli_real_escape_string($conn, $edate_norm) . "'";
        }
    }

    // تنظيف بقية الحقول
    $event_code   = mysqli_real_escape_string($conn, $evt_code_raw);
    $event_type   = mysqli_real_escape_string($conn, $type_raw);
    $party        = $party_raw !== '' ? mysqli_real_escape_string($conn, $party_raw) : '';
    $party_sql    = $party_raw !== '' ? "'$party'" : 'NULL';
    $state        = mysqli_real_escape_string($conn, $state_raw);
    $description  = mysqli_real_escape_string($conn, isset($_POST['description']) ? trim($_POST['description']) : '');
    $created_by   = intval($_SESSION['user']['id']);

    if ($is_editing) {
        $owner = mysqli_query($conn, "SELECT id FROM contract_events WHERE id = $evt_id AND company_id = $company_id AND is_deleted = 0 LIMIT 1");
        if (!$owner || mysqli_num_rows($owner) === 0) {
            evt_redirect_with_msg('لا يمكنك تعديل حدث لا يتبع لشركتك ❌');
        }
        $dup = mysqli_query($conn, "SELECT id FROM contract_events WHERE event_code = '$event_code' AND id != $evt_id AND company_id = $company_id AND is_deleted = 0");
        if ($dup && mysqli_num_rows($dup) > 0) {
            evt_redirect_with_msg('كود الحدث موجود مسبقاً داخل شركتك ❌');
        }

        $update_query = "UPDATE contract_events SET
            event_code = '$event_code', contract_id = $contract_sql, event_date = $edate_sql,
            event_type = '$event_type', party = $party_sql, description = '$description',
            state = '$state'
            WHERE id = $evt_id AND $scope_update_sql AND is_deleted = 0";

        if (mysqli_query($conn, $update_query)) {
            if (class_exists('\\App\\Services\\ActivityLogService')) {
                \App\Services\ActivityLogService::logUpdate('contract_events', 'contract_events', $evt_id, null, ['event_code' => $evt_code_raw]);
            }
            evt_redirect_with_msg('تم تعديل الحدث بنجاح ✅');
        }
        error_log('contract_events.php update failed: ' . mysqli_error($conn));
        evt_redirect_with_msg('حدث خطأ أثناء التعديل ❌');
    } else {
        $dup = mysqli_query($conn, "SELECT id FROM contract_events WHERE event_code = '$event_code' AND company_id = $company_id AND is_deleted = 0");
        if ($dup && mysqli_num_rows($dup) > 0) {
            evt_redirect_with_msg('كود الحدث موجود مسبقاً داخل شركتك ❌');
        }

        $insert_query = "INSERT INTO contract_events
            (company_id, event_code, contract_id, event_date, event_type, party, description, state, created_by)
            VALUES
            ('$company_id', '$event_code', $contract_sql, $edate_sql, '$event_type', $party_sql, '$description', '$state', '$created_by')";

        if (mysqli_query($conn, $insert_query)) {
            $new_id = (int) mysqli_insert_id($conn);
            if (class_exists('\\App\\Services\\ActivityLogService')) {
                \App\Services\ActivityLogService::logCreate('contract_events', 'contract_events', $new_id, ['event_code' => $evt_code_raw]);
            }
            evt_redirect_with_msg('تم إضافة الحدث بنجاح ✅');
        }
        error_log('contract_events.php insert failed: ' . mysqli_error($conn));
        evt_redirect_with_msg('حدث خطأ أثناء الإضافة ❌');
    }
}

// ══════════════════════════════════════════════════════════════════════════════
// معالجة الحذف الناعم
// ══════════════════════════════════════════════════════════════════════════════
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $delete_csrf = isset($_GET['csrf_token']) ? $_GET['csrf_token'] : '';

    if (!$can_delete) {
        evt_redirect_with_msg('لا توجد صلاحية حذف الأحداث ❌');
    }
    if (empty($delete_csrf) || !hash_equals($evt_csrf_token, $delete_csrf)) {
        evt_redirect_with_msg('جلسة الحذف غير صالحة، يرجى إعادة المحاولة ❌');
    }
    $chk = mysqli_query($conn, "SELECT id FROM contract_events WHERE id = $delete_id AND company_id = $company_id AND is_deleted = 0 LIMIT 1");
    if (!$chk || mysqli_num_rows($chk) === 0) {
        evt_redirect_with_msg('لا يمكنك حذف حدث لا يتبع لشركتك ❌');
    }
    $deleted_by = intval($_SESSION['user']['id']);
    $del = "UPDATE contract_events SET is_deleted = 1, deleted_at = NOW(), deleted_by = $deleted_by
            WHERE id = $delete_id AND $scope_update_sql AND is_deleted = 0";
    if (mysqli_query($conn, $del)) {
        if (class_exists('\\App\\Services\\ActivityLogService')) {
            \App\Services\ActivityLogService::logDelete('contract_events', 'contract_events', $delete_id);
        }
        evt_redirect_with_msg('تم حذف الحدث بنجاح ✅');
    }
    error_log('contract_events.php soft delete failed: ' . mysqli_error($conn));
    evt_redirect_with_msg('حدث خطأ أثناء الحذف ❌');
}

// ══════════════════════════════════════════════════════════════════════════════
// قوائم الاختيار (ضمن نطاق الشركة)
// ══════════════════════════════════════════════════════════════════════════════
$contract_options = array();
$ct_res = mysqli_query($conn, "SELECT c.id, p.name AS project_name FROM contracts c LEFT JOIN project p ON p.id = c.project_id WHERE c.company_id = $company_id AND c.is_deleted = 0 ORDER BY c.id DESC");
if ($ct_res) { while ($ct = mysqli_fetch_assoc($ct_res)) { $contract_options[] = $ct; } }

// خريطة سريعة لعرض العقد (label = "عقد #{id} - {project_name}")
$contracts_map = array();
foreach ($contract_options as $ct) {
    $cid = intval($ct['id']);
    $pname = isset($ct['project_name']) && $ct['project_name'] !== null ? $ct['project_name'] : '';
    $contracts_map[$cid] = 'عقد #' . $cid . ($pname !== '' ? ' - ' . $pname : '');
}

// ══════════════════════════════════════════════════════════════════════════════
// جلب الأحداث + الإحصائيات
// ══════════════════════════════════════════════════════════════════════════════
$rows = array();
$stat_total = 0;
$stat_open = 0;
$stat_progress = 0;
$stat_closed = 0;

$q = "SELECT e.*, u.name AS creator_name
      FROM contract_events e
      LEFT JOIN users u ON u.id = e.created_by
      WHERE $scope_sql AND $not_deleted_sql
      ORDER BY e.id DESC";
$res = mysqli_query($conn, $q);
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $rows[] = $row;
        $stat_total++;
        if ($row['state'] === 'مفتوح') $stat_open++;
        if ($row['state'] === 'قيد المتابعة') $stat_progress++;
        if ($row['state'] === 'مغلق') $stat_closed++;
    }
}

$page_title = "سجل الأحداث التعاقدية";
include("../inheader.php");
include('../insidebar.php');

// لون شارة الحالة
function evt_state_class($state)
{
    switch ($state) {
        case 'مغلق':        return 'evt-badge-closed';
        case 'قيد المتابعة': return 'evt-badge-progress';
        default:            return 'evt-badge-open';
    }
}
// لون تفاصيل الحالة (EmsDetailsModal tone)
function evt_state_tone($state)
{
    switch ($state) {
        case 'مغلق':        return 'active';
        case 'قيد المتابعة': return 'pending';
        default:            return 'inactive';
    }
}
?>

<div class="main evt-main ems-unified-page-shell">

    <?php
    $header_title = 'سجل الأحداث التعاقدية';
    $header_icon = 'fas fa-timeline';
    $header_actions = array();
    if ($can_add) {
        $header_actions[] = array('id' => 'toggleForm', 'class' => 'add-btn', 'icon' => 'fa fa-solid fa-plus', 'label' => '', 'label_class' => 'evt-toggle-form-text');
    } else {
        $header_actions[] = array('tag' => 'button', 'class' => '', 'disabled' => true, 'icon' => 'fas fa-plus-circle', 'label' => 'إضافة (بدون صلاحيات)');
    }
    $header_actions[] = array('id' => 'toggleStats', 'class' => 'btn', 'title' => 'إظهار أو إخفاء الإحصائيات', 'icon' => 'fas fa-eye', 'label' => 'إظهار الإحصائيات', 'label_class' => 'evt-toggle-stats-text');
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fa-solid fa-share', 'label' => '');
    include('../includes/page_header.php');
    ?>

    <?php if (!empty($_GET['msg'])):
        $isSuccess = strpos($_GET['msg'], '✅') !== false; ?>
        <div class="success-message <?= $isSuccess ? 'is-success' : 'is-error' ?>">
            <i class="fas <?= $isSuccess ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?php echo evt_e($_GET['msg']); ?>
        </div>
    <?php endif; ?>

    <div class="stats-section evt-hidden" id="evtStatsSection">
        <div class="stats-grid">
            <div class="stats-card">
                <div class="stats-icon"><i class="fas fa-timeline"></i></div>
                <div class="stats-value"><?php echo $stat_total; ?></div>
                <div class="stats-title">إجمالي الأحداث</div>
            </div>
            <div class="stats-card">
                <div class="stats-icon"><i class="fas fa-folder-open"></i></div>
                <div class="stats-value"><?php echo $stat_open; ?></div>
                <div class="stats-title">مفتوحة</div>
            </div>
            <div class="stats-card">
                <div class="stats-icon"><i class="fas fa-spinner"></i></div>
                <div class="stats-value"><?php echo $stat_progress; ?></div>
                <div class="stats-title">قيد المتابعة</div>
            </div>
            <div class="stats-card">
                <div class="stats-icon"><i class="fas fa-circle-check"></i></div>
                <div class="stats-value"><?php echo $stat_closed; ?></div>
                <div class="stats-title">مغلقة</div>
            </div>
        </div>
    </div>

    <!-- فورم إضافة / تعديل حدث -->
    <form id="evtForm" action="" method="post" class="allforms">
        <div class="card-header">
            <h5><i class="fas fa-edit"></i> <span id="formTitle">إضافة حدث جديد</span></h5>
        </div>
        <input type="hidden" name="evt_id" id="evt_id" value="">
        <input type="hidden" name="csrf_token" value="<?php echo evt_e($evt_csrf_token); ?>">
        <div class="card shadow-sm pu-form-card">
            <div class="card-body">
                <div class="form-grid">
                    <div id="generated_code_wrapper" class="auto">
                        <label><i class="fas fa-magic"></i> كود الحدث المولد <i class="fas fa-info-circle evt-info-icon"></i></label>
                        <input type="text" id="generated_evt_code" class="generated-code-field" value="<?php echo evt_e($next_evt_code); ?>" readonly tabindex="-1" title="هذا الكود للعرض فقط، انسخه إلى حقل كود الحدث" />
                        <div class="generated-code-hint"></div>
                    </div>

                    <div>
                        <label><i class="fas fa-barcode"></i> كود الحدث *</label>
                        <input type="text" name="event_code" id="event_code" placeholder="مثال: EVT-001" required pattern="[A-Za-z0-9-_]+" />
                    </div>
                    <div>
                        <label><i class="fas fa-file-contract"></i> العقد المرتبط</label>
                        <select name="contract_id" id="contract_id">
                            <option value="">-- بدون / غير محدد --</option>
                            <?php foreach ($contract_options as $ct): ?>
                                <option value="<?php echo intval($ct['id']); ?>"><?php echo evt_e($contracts_map[intval($ct['id'])]); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label><i class="fas fa-tags"></i> نوع الحدث *</label>
                        <select name="event_type" id="event_type" required>
                            <?php foreach ($EVT_TYPES as $tp): ?>
                                <option value="<?php echo evt_e($tp); ?>"><?php echo evt_e($tp); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label><i class="fas fa-users"></i> الطرف</label>
                        <select name="party" id="party">
                            <option value="">-- بدون / غير محدد --</option>
                            <?php foreach ($EVT_PARTIES as $pt): ?>
                                <option value="<?php echo evt_e($pt); ?>"><?php echo evt_e($pt); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label><i class="fas fa-calendar-day"></i> تاريخ الحدث</label>
                        <input type="datetime-local" name="event_date" id="event_date" />
                    </div>
                    <div>
                        <label><i class="fas fa-traffic-light"></i> الحالة</label>
                        <select name="state" id="state">
                            <?php foreach ($EVT_STATES as $st): ?>
                                <option value="<?php echo evt_e($st); ?>"><?php echo evt_e($st); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="evt-col-full">
                        <label><i class="fas fa-align-left"></i> وصف الحدث وأثره</label>
                        <textarea name="description" id="description" rows="3" placeholder="وصف الحدث وأثره على العقد"></textarea>
                    </div>
                </div>
                <div class="pu-form-actions">
                    <button type="submit" class="btn-submit"><i class="fas fa-save"></i> <span id="submitBtnText">حفظ الحدث</span></button>
                    <button type="button" id="evtFormCancelBtn" class="btn-cancel"><i class="fas fa-times"></i> إلغاء</button>
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
                <label><i class="fa fa-tags"></i> نوع الحدث</label>
                <select id="filterType" class="form-control">
                    <option value="">-- كل الأنواع --</option>
                </select>
            </div>
            <div class="filter-field">
                <label><i class="fa fa-traffic-light"></i> الحالة</label>
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
                <table id="evtTable" class="display evt-table-nowrap no-datatable">
                    <thead>
                        <tr>
                            <th>إجراءات</th>
                            <th width="90">الكود</th>
                            <th>العقد</th>
                            <th>نوع الحدث</th>
                            <th>الطرف</th>
                            <th>التاريخ</th>
                            <th>الحالة</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row):
                            $contract_id_val = $row['contract_id'] !== null ? intval($row['contract_id']) : 0;
                            $contract_label = ($contract_id_val > 0 && isset($contracts_map[$contract_id_val])) ? $contracts_map[$contract_id_val] : '';
                            $party_label = $row['party'] !== null ? $row['party'] : '';
                            $created_label = function_exists('ems_actor_label') ? ems_actor_label($conn, intval($row['created_by'])) : ($row['creator_name'] ?? '');
                            ?>
                            <tr>
                                <td>
                                    <div class="action-btns">
                                        <a href="javascript:void(0)" class="action-btn view viewEvtBtn"
                                            data-id="<?php echo intval($row['id']); ?>"
                                            data-code="<?php echo evt_e($row['event_code']); ?>"
                                            data-contract="<?php echo evt_e($contract_label); ?>"
                                            data-type="<?php echo evt_e($row['event_type']); ?>"
                                            data-party="<?php echo evt_e($party_label); ?>"
                                            data-date="<?php echo evt_e($row['event_date']); ?>"
                                            data-state="<?php echo evt_e($row['state']); ?>"
                                            data-description="<?php echo evt_e($row['description']); ?>"
                                            data-created="<?php echo evt_e($created_label); ?>"
                                            title="عرض التفاصيل"><i class="fas fa-eye"></i></a>
                                        <?php if ($can_edit): ?>
                                            <a href="javascript:void(0)" class="action-btn edit editEvtBtn"
                                                data-id="<?php echo intval($row['id']); ?>"
                                                data-code="<?php echo evt_e($row['event_code']); ?>"
                                                data-contract-id="<?php echo $contract_id_val; ?>"
                                                data-type="<?php echo evt_e($row['event_type']); ?>"
                                                data-party="<?php echo evt_e($party_label); ?>"
                                                data-date="<?php echo evt_e($row['event_date']); ?>"
                                                data-state="<?php echo evt_e($row['state']); ?>"
                                                data-description="<?php echo evt_e($row['description']); ?>"
                                                title="تعديل"><i class="fas fa-edit"></i></a>
                                        <?php endif; ?>
                                        <?php if ($can_delete): ?>
                                            <a href="?delete_id=<?php echo urlencode($row['id']); ?>&csrf_token=<?php echo urlencode($evt_csrf_token); ?>"
                                                class="action-btn delete"
                                                onclick="return confirm('هل أنت متأكد من حذف هذا الحدث؟')" title="حذف"><i class="fas fa-trash-alt"></i></a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><strong class="evt-code-cell"><?php echo evt_e($row['event_code']); ?></strong></td>
                                <td><?php echo $contract_label !== '' ? evt_e($contract_label) : '<span class="evt-muted">—</span>'; ?></td>
                                <td><?php echo evt_e($row['event_type']); ?></td>
                                <td><?php echo $party_label !== '' ? evt_e($party_label) : '<span class="evt-muted">—</span>'; ?></td>
                                <td class="evt-num"><?php echo $row['event_date'] !== null ? evt_e($row['event_date']) : '<span class="evt-muted">—</span>'; ?></td>
                                <td><span class="evt-badge <?php echo evt_state_class($row['state']); ?>"><?php echo evt_e($row['state']); ?></span></td>
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
        const evtTable = $('#evtTable').DataTable({
            autoWidth: false,
            stateSave: false,
            language: { url: '/ems/assets/i18n/datatables/ar.json' }
        });

        function fillFilterOptions(columnIndex, selectId) {
            const select = $(selectId);
            const values = [];
            evtTable.column(columnIndex).data().each(function (value) {
                const text = $('<div>').html(value).text().trim();
                if (text !== '' && values.indexOf(text) === -1) values.push(text);
            });
            values.sort();
            values.forEach(function (val) {
                select.append('<option value="' + val.replace(/"/g, '&quot;') + '">' + val + '</option>');
            });
        }
        fillFilterOptions(3, '#filterType');
        fillFilterOptions(6, '#filterState');

        $('#filterType').on('change', function () {
            const value = $.fn.dataTable.util.escapeRegex($(this).val());
            evtTable.column(3).search(value ? '^' + value + '$' : '', true, false).draw();
        });
        $('#filterState').on('change', function () {
            const value = $.fn.dataTable.util.escapeRegex($(this).val());
            evtTable.column(6).search(value ? value : '', true, false).draw();
        });
    });

    // ── إظهار/إخفاء الفورم والإحصائيات ──
    const formToggleBtn = $('#toggleForm');
    const evtForm = $('#evtForm');
    const formTitle = $('#formTitle');
    const submitBtnText = $('#submitBtnText');
    const generatedCodeWrapper = $('#generated_code_wrapper');
    const formCancelBtn = $('#evtFormCancelBtn');
    const statsToggleBtn = $('#toggleStats');
    const statsSection = $('#evtStatsSection');

    function setAddMode() { formTitle.text('إضافة حدث جديد'); submitBtnText.text('حفظ الحدث'); generatedCodeWrapper.show(); }
    function setEditMode() { formTitle.text('تعديل الحدث'); submitBtnText.text('تحديث الحدث'); generatedCodeWrapper.hide(); }
    function resetForm() { if (!evtForm.length) return; evtForm[0].reset(); $('#evt_id').val(''); setAddMode(); if (window.EmsSelect) EmsSelect.refresh(); }

    function updateFormToggleState(isOpen) {
        if (!formToggleBtn.length) return;
        formToggleBtn.toggleClass('is-active', isOpen);
        formToggleBtn.attr('aria-expanded', isOpen ? 'true' : 'false');
    }
    function updateStatsToggleState(isVisible) {
        if (!statsToggleBtn.length) return;
        statsToggleBtn.toggleClass('is-active', isVisible);
        statsToggleBtn.find('.evt-toggle-stats-text').text('إظهار الإحصائيات');
        const icon = statsToggleBtn.find('i').first();
        icon.toggleClass('fa-chart-pie', isVisible);
        icon.toggleClass('fa-eye', !isVisible);
    }

    setAddMode();
    updateFormToggleState(evtForm.is(':visible'));
    updateStatsToggleState(statsSection.is(':visible'));

    formToggleBtn.on('click', function (e) {
        e.preventDefault();
        if (!evtForm.length) return;
        if (evtForm.is(':visible')) {
            evtForm.stop(true, true).slideUp(250, function () { evtForm.removeClass('allforms-visible'); resetForm(); updateFormToggleState(false); });
        } else {
            resetForm();
            evtForm.addClass('allforms-visible').hide();
            evtForm.stop(true, true).slideDown(250, function () { updateFormToggleState(true); });
        }
    });
    formCancelBtn.on('click', function () {
        if (!evtForm.length || !evtForm.is(':visible')) return;
        evtForm.stop(true, true).slideUp(250, function () { evtForm.removeClass('allforms-visible'); resetForm(); updateFormToggleState(false); });
    });
    statsToggleBtn.on('click', function (e) {
        e.preventDefault();
        if (!statsSection.length) return;
        if (statsSection.is(':visible')) {
            statsSection.stop(true, true).slideUp(250, function () { statsSection.addClass('evt-hidden'); updateStatsToggleState(false); });
        } else {
            statsSection.removeClass('evt-hidden').hide();
            statsSection.stop(true, true).slideDown(250, function () { updateStatsToggleState(true); });
        }
    });

    // ── تعبئة الفورم للتعديل ──
    function fillEvtForm(d) {
        $('#evt_id').val(d.id);
        $('#event_code').val(d.code);
        $('#contract_id').val(d.contractId ? String(d.contractId) : '');
        $('#event_type').val(d.type || '');
        $('#party').val(d.party || '');
        $('#event_date').val(d.date ? String(d.date).replace(' ', 'T').substring(0, 16) : '');
        $('#state').val(d.state || 'مفتوح');
        $('#description').val(d.description || '');
        if (window.EmsSelect) EmsSelect.refresh();
        setEditMode();
        if (!evtForm.is(':visible')) {
            evtForm.addClass('allforms-visible').hide();
            evtForm.stop(true, true).slideDown(250, function () { updateFormToggleState(true); });
        } else { updateFormToggleState(true); }
        $('html, body').animate({ scrollTop: $('#evtForm').offset().top - 100 }, 500);
    }

    $(document).on('click', '.editEvtBtn', function () {
        fillEvtForm({
            id: $(this).data('id'), code: $(this).data('code'),
            contractId: $(this).data('contract-id'), type: $(this).data('type'),
            party: $(this).data('party'), date: $(this).data('date'),
            state: $(this).data('state'), description: $(this).data('description')
        });
    });

    // ── عرض التفاصيل عبر EmsDetailsModal الموحّد ──
    $(document).on('click', '.viewEvtBtn', function () {
        const d = $(this).data();
        const stateTone = {
            'مغلق': 'active',
            'قيد المتابعة': 'pending',
            'مفتوح': 'inactive'
        }[String(d.state)] || 'inactive';
        const fields = [
            { label: 'كود الحدث', value: d.code, icon: 'fas fa-barcode' },
            { label: 'العقد المرتبط', value: d.contract || '—', icon: 'fas fa-file-contract', size: 'lg' },
            { label: 'نوع الحدث', value: d.type || '—', icon: 'fas fa-tags' },
            { label: 'الطرف', value: d.party || '—', icon: 'fas fa-users' },
            { label: 'تاريخ الحدث', value: d.date || '—', icon: 'fas fa-calendar-day' },
            { label: 'الحالة', value: d.state || '—', icon: 'fas fa-traffic-light', type: 'status', tone: stateTone },
            { label: 'وصف الحدث وأثره', value: d.description || '—', icon: 'fas fa-align-left', size: 'lg' },
            { label: 'أضيف بواسطة', value: d.created || '—', icon: 'fas fa-user-plus' }
        ];

        const actions = [];
        <?php if ($can_edit): ?>
            actions.push({ label: 'تعديل الحدث', icon: 'fas fa-edit', variant: 'primary', onClick: function () {
                EmsDetailsModal.close();
                $('.editEvtBtn[data-id="' + d.id + '"]').trigger('click');
            }});
        <?php endif; ?>
        actions.push({ label: 'إغلاق', icon: 'fas fa-times', variant: 'secondary', close: true });

        EmsDetailsModal.open({ title: 'تفاصيل الحدث التعاقدي', icon: 'fas fa-timeline', fields: fields, actions: actions });
    });
</script>

<style>
    .evt-main .stats-grid { display: grid; grid-template-columns: repeat(4, minmax(170px, 1fr)); gap: 12px; }
    .evt-main .stats-section {
        border: 1px solid var(--bdr); border-radius: var(--rl);
        background: linear-gradient(180deg, rgba(255,255,255,.95) 0%, var(--s2) 100%);
        box-shadow: var(--sh); padding: 14px; margin-bottom: 14px;
    }
    .evt-main .stats-card { background: #eee; border: 1px solid #aaa; border-radius: 35px; padding: 18px; box-shadow: 0 2px 8px rgba(26,18,8,.07); position: relative; overflow: hidden; }
    .evt-main .stats-card .stats-icon { width: 55px; height: 55px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.3rem; margin-bottom: 10px; float: left; margin-top: 15px; border: 1px solid #999; background:#fff; color:#000; }
    .evt-main .stats-card .stats-title { color: #555; font-size: .92rem; font-weight: 700; margin-top: 5px; line-height: 1.3; }
    .evt-main .stats-card .stats-value { color: #222; line-height: 1; font-weight: 900; font-variant-numeric: tabular-nums; margin-top: 10px; font-size: 30px; }
    @media (max-width: 900px) { .evt-main .stats-grid { grid-template-columns: repeat(2, minmax(150px,1fr)); } }
    @media (max-width: 560px) { .evt-main .stats-grid { grid-template-columns: 1fr; } }

    .evt-main .evt-hidden { display: none; }
    .evt-main .evt-col-full { grid-column: 1 / -1; }
    .evt-main .table-container { overflow-x: auto; }
    #evtTable.evt-table-nowrap, #evtTable.evt-table-nowrap th, #evtTable.evt-table-nowrap td { white-space: nowrap; }
    #evtTable .action-btns { flex-wrap: nowrap; white-space: nowrap; }
    .evt-main .evt-num { font-variant-numeric: tabular-nums; font-weight: 700; }
    .evt-main .evt-muted { color: #999; }
    .evt-main .evt-badge { display:inline-block; padding:2px 10px; border-radius:999px; font-size:.75rem; font-weight:800; border:1px solid transparent; }
    .evt-main .evt-badge-open { background:rgba(245,158,11,.14); color:#b45309; border-color:rgba(245,158,11,.3); }
    .evt-main .evt-badge-progress { background:rgba(59,130,246,.14); color:#1d4ed8; border-color:rgba(59,130,246,.3); }
    .evt-main .evt-badge-closed { background:rgba(34,197,94,.14); color:#15803d; border-color:rgba(34,197,94,.3); }
</style>

</body>

</html>
