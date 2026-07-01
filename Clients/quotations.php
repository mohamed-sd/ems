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
if (!function_exists('quo_e')) {
    function quo_e($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
if (!function_exists('quo_money')) {
    function quo_money($v)
    {
        return number_format((float) $v, 2);
    }
}
if (!function_exists('quo_redirect_with_msg')) {
    function quo_redirect_with_msg($msg)
    {
        header('Location: quotations.php?msg=' . urlencode($msg));
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
$scope_sql        = "q.company_id = $company_id";
$scope_update_sql = "company_id = $company_id";
$not_deleted_sql  = "q.is_deleted = 0";

// رمز CSRF
if (empty($_SESSION['quo_csrf_token'])) {
    $_SESSION['quo_csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
}
$quo_csrf_token = $_SESSION['quo_csrf_token'];

// القوائم الثابتة
$QUO_CURRENCIES = array('USD', 'SDG');
$QUO_STATES     = array('مسودة', 'مقدم', 'مقبول', 'مرفوض');

// توليد الكود المقترح التالي (QUO-NNNN) — للعرض فقط
$next_quo_code = 'QUO-0001';
$last_code_sql = "SELECT quotation_code FROM quotations
                  WHERE quotation_code REGEXP '^QUO-[0-9]+$' AND company_id = $company_id AND is_deleted = 0
                  ORDER BY CAST(SUBSTRING(quotation_code, 5) AS UNSIGNED) DESC LIMIT 1";
$last_code_res = @mysqli_query($conn, $last_code_sql);
if ($last_code_res && mysqli_num_rows($last_code_res) > 0) {
    $last_code_row = mysqli_fetch_assoc($last_code_res);
    $last_num = intval(substr($last_code_row['quotation_code'], 4));
    $next_quo_code = 'QUO-' . str_pad($last_num + 1, 4, '0', STR_PAD_LEFT);
}

// صلاحيات المستخدم على وحدة العروض
$module_query = "SELECT id FROM modules WHERE code = 'Clients/quotations.php' LIMIT 1";
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
    header('Location: ../login.php?msg=' . urlencode('لا توجد صلاحية عرض العروض ❌'));
    exit();
}

// ══════════════════════════════════════════════════════════════════════════════
// معالجة إضافة / تعديل عرض عبر POST
// ══════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['quotation_code'])) {
    $posted_csrf = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if (empty($posted_csrf) || !hash_equals($quo_csrf_token, $posted_csrf)) {
        quo_redirect_with_msg('جلسة النموذج غير صالحة، يرجى إعادة المحاولة ❌');
    }

    $quo_id     = isset($_POST['quo_id']) ? intval($_POST['quo_id']) : 0;
    $is_editing = $quo_id > 0;

    if ($is_editing && !$can_edit) {
        quo_redirect_with_msg('لا توجد صلاحية تعديل العروض ❌');
    } elseif (!$is_editing && !$can_add) {
        quo_redirect_with_msg('لا توجد صلاحية إضافة عروض جديدة ❌');
    }

    // الكود
    $quo_code_raw = isset($_POST['quotation_code']) ? trim($_POST['quotation_code']) : '';
    if ($quo_code_raw === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $quo_code_raw)) {
        quo_redirect_with_msg('كود العرض غير صالح. استخدم أحرفًا وأرقامًا و - أو _ فقط ❌');
    }

    // التحقق من القوائم الثابتة
    $currency_raw = isset($_POST['currency']) ? trim($_POST['currency']) : 'USD';
    if (!in_array($currency_raw, $QUO_CURRENCIES, true)) {
        quo_redirect_with_msg('العملة غير صالحة ❌');
    }
    $state_raw = isset($_POST['state']) ? trim($_POST['state']) : 'مسودة';
    if (!in_array($state_raw, $QUO_STATES, true)) {
        quo_redirect_with_msg('حالة العرض غير صالحة ❌');
    }

    // العميل — التحقق من النطاق (إن حُدِّد)
    $client_id_in = isset($_POST['client_id']) ? intval($_POST['client_id']) : 0;
    if ($client_id_in > 0) {
        $cchk = mysqli_query($conn, "SELECT id FROM clients WHERE id = $client_id_in AND company_id = $company_id AND is_deleted = 0 LIMIT 1");
        if (!$cchk || mysqli_num_rows($cchk) === 0) {
            quo_redirect_with_msg('العميل غير موجود أو خارج نطاق شركتك ❌');
        }
    }
    $client_id_sql = $client_id_in > 0 ? "'$client_id_in'" : 'NULL';

    // الفرصة المصدر — التحقق من النطاق (إن حُدِّدت)
    $opp_id_in = isset($_POST['opportunity_id']) ? intval($_POST['opportunity_id']) : 0;
    if ($opp_id_in > 0) {
        $ochk = mysqli_query($conn, "SELECT id FROM opportunities WHERE id = $opp_id_in AND company_id = $company_id AND is_deleted = 0 LIMIT 1");
        if (!$ochk || mysqli_num_rows($ochk) === 0) {
            quo_redirect_with_msg('الفرصة المصدر غير موجودة أو خارج نطاق شركتك ❌');
        }
    }
    $opp_id_sql = $opp_id_in > 0 ? "'$opp_id_in'" : 'NULL';

    // تنظيف بقية الحقول
    $quotation_code = mysqli_real_escape_string($conn, $quo_code_raw);
    $currency       = mysqli_real_escape_string($conn, $currency_raw);
    $state          = mysqli_real_escape_string($conn, $state_raw);
    $payment_terms  = mysqli_real_escape_string($conn, isset($_POST['payment_terms']) ? trim($_POST['payment_terms']) : '');
    $notes          = mysqli_real_escape_string($conn, isset($_POST['notes']) ? trim($_POST['notes']) : '');
    $amount_total   = isset($_POST['amount_total']) ? (float) $_POST['amount_total'] : 0;
    if ($amount_total < 0) $amount_total = 0;
    $vdate_raw      = isset($_POST['validity_date']) ? trim($_POST['validity_date']) : '';
    $vdate_sql      = preg_match('/^\d{4}-\d{2}-\d{2}$/', $vdate_raw) ? "'$vdate_raw'" : 'NULL';
    $created_by     = intval($_SESSION['user']['id']);

    if ($is_editing) {
        $owner = mysqli_query($conn, "SELECT id FROM quotations WHERE id = $quo_id AND company_id = $company_id AND is_deleted = 0 LIMIT 1");
        if (!$owner || mysqli_num_rows($owner) === 0) {
            quo_redirect_with_msg('لا يمكنك تعديل عرض لا يتبع لشركتك ❌');
        }
        $dup = mysqli_query($conn, "SELECT id FROM quotations WHERE quotation_code = '$quotation_code' AND id != $quo_id AND company_id = $company_id AND is_deleted = 0");
        if ($dup && mysqli_num_rows($dup) > 0) {
            quo_redirect_with_msg('كود العرض موجود مسبقاً داخل شركتك ❌');
        }

        $update_query = "UPDATE quotations SET
            quotation_code = '$quotation_code', client_id = $client_id_sql, opportunity_id = $opp_id_sql,
            currency = '$currency', amount_total = $amount_total, validity_date = $vdate_sql,
            payment_terms = '$payment_terms', state = '$state', notes = '$notes'
            WHERE id = $quo_id AND $scope_update_sql AND is_deleted = 0";

        if (mysqli_query($conn, $update_query)) {
            if (class_exists('\\App\\Services\\ActivityLogService')) {
                \App\Services\ActivityLogService::logUpdate('quotations', 'quotations', $quo_id, null, ['quotation_code' => $quo_code_raw]);
            }
            quo_redirect_with_msg('تم تعديل العرض بنجاح ✅');
        }
        error_log('quotations.php update failed: ' . mysqli_error($conn));
        quo_redirect_with_msg('حدث خطأ أثناء التعديل ❌');
    } else {
        $dup = mysqli_query($conn, "SELECT id FROM quotations WHERE quotation_code = '$quotation_code' AND company_id = $company_id AND is_deleted = 0");
        if ($dup && mysqli_num_rows($dup) > 0) {
            quo_redirect_with_msg('كود العرض موجود مسبقاً داخل شركتك ❌');
        }

        $insert_query = "INSERT INTO quotations
            (company_id, quotation_code, client_id, opportunity_id, currency, amount_total, validity_date,
             payment_terms, state, notes, created_by)
            VALUES
            ('$company_id', '$quotation_code', $client_id_sql, $opp_id_sql, '$currency', $amount_total, $vdate_sql,
             '$payment_terms', '$state', '$notes', '$created_by')";

        if (mysqli_query($conn, $insert_query)) {
            $new_id = (int) mysqli_insert_id($conn);
            if (class_exists('\\App\\Services\\ActivityLogService')) {
                \App\Services\ActivityLogService::logCreate('quotations', 'quotations', $new_id, ['quotation_code' => $quo_code_raw]);
            }
            quo_redirect_with_msg('تم إضافة العرض بنجاح ✅');
        }
        error_log('quotations.php insert failed: ' . mysqli_error($conn));
        quo_redirect_with_msg('حدث خطأ أثناء الإضافة ❌');
    }
}

// ══════════════════════════════════════════════════════════════════════════════
// معالجة الحذف الناعم
// ══════════════════════════════════════════════════════════════════════════════
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $delete_csrf = isset($_GET['csrf_token']) ? $_GET['csrf_token'] : '';

    if (!$can_delete) {
        quo_redirect_with_msg('لا توجد صلاحية حذف العروض ❌');
    }
    if (empty($delete_csrf) || !hash_equals($quo_csrf_token, $delete_csrf)) {
        quo_redirect_with_msg('جلسة الحذف غير صالحة، يرجى إعادة المحاولة ❌');
    }
    $chk = mysqli_query($conn, "SELECT id FROM quotations WHERE id = $delete_id AND company_id = $company_id AND is_deleted = 0 LIMIT 1");
    if (!$chk || mysqli_num_rows($chk) === 0) {
        quo_redirect_with_msg('لا يمكنك حذف عرض لا يتبع لشركتك ❌');
    }
    $deleted_by = intval($_SESSION['user']['id']);
    $del = "UPDATE quotations SET is_deleted = 1, deleted_at = NOW(), deleted_by = $deleted_by
            WHERE id = $delete_id AND $scope_update_sql AND is_deleted = 0";
    if (mysqli_query($conn, $del)) {
        if (class_exists('\\App\\Services\\ActivityLogService')) {
            \App\Services\ActivityLogService::logDelete('quotations', 'quotations', $delete_id);
        }
        quo_redirect_with_msg('تم حذف العرض بنجاح ✅');
    }
    error_log('quotations.php soft delete failed: ' . mysqli_error($conn));
    quo_redirect_with_msg('حدث خطأ أثناء الحذف ❌');
}

// ══════════════════════════════════════════════════════════════════════════════
// قوائم السجلات المرتبطة (ضمن نطاق الشركة)
// ══════════════════════════════════════════════════════════════════════════════
$clients_options = array();
$cl_res = mysqli_query($conn, "SELECT id, client_code, client_name FROM clients WHERE company_id = $company_id AND is_deleted = 0 ORDER BY client_name ASC");
if ($cl_res) { while ($cl = mysqli_fetch_assoc($cl_res)) { $clients_options[] = $cl; } }

$opp_options = array();
$op_res = mysqli_query($conn, "SELECT id, opp_code, title FROM opportunities WHERE company_id = $company_id AND is_deleted = 0 ORDER BY id DESC");
if ($op_res) { while ($op = mysqli_fetch_assoc($op_res)) { $opp_options[] = $op; } }

// ══════════════════════════════════════════════════════════════════════════════
// جلب العروض + الإحصائيات
// ══════════════════════════════════════════════════════════════════════════════
$rows = array();
$stat_total = 0;
$stat_offered = 0;
$stat_accepted = 0;
$stat_amount = 0.0;

$q = "SELECT q.*, u.name AS creator_name, c.client_name AS client_name, o.title AS opp_title
      FROM quotations q
      LEFT JOIN users u ON u.id = q.created_by
      LEFT JOIN clients c ON c.id = q.client_id
      LEFT JOIN opportunities o ON o.id = q.opportunity_id
      WHERE $scope_sql AND $not_deleted_sql
      ORDER BY q.id DESC";
$res = mysqli_query($conn, $q);
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $rows[] = $row;
        $stat_total++;
        if ($row['state'] === 'مقدم') $stat_offered++;
        if ($row['state'] === 'مقبول') $stat_accepted++;
        $stat_amount += (float) $row['amount_total'];
    }
}

$page_title = "العروض";
include("../inheader.php");
include('../insidebar.php');

function quo_state_class($state)
{
    switch ($state) {
        case 'مقبول': return 'quo-badge-green';
        case 'مرفوض': return 'quo-badge-red';
        case 'مقدم':  return 'quo-badge-amber';
        default:      return 'quo-badge-gray';
    }
}
function quo_state_tone($state)
{
    switch ($state) {
        case 'مقبول': return 'active';
        case 'مرفوض': return 'inactive';
        case 'مقدم':  return 'warning';
        default:      return 'neutral';
    }
}
?>

<div class="main quo-main ems-unified-page-shell">

    <?php
    $header_title = 'العروض';
    $header_icon = 'fas fa-file-invoice-dollar';
    $header_actions = array();
    if ($can_add) {
        $header_actions[] = array('id' => 'toggleForm', 'class' => 'add-btn', 'icon' => 'fa fa-solid fa-plus', 'label' => '', 'label_class' => 'quo-toggle-form-text');
    } else {
        $header_actions[] = array('tag' => 'button', 'class' => '', 'disabled' => true, 'icon' => 'fas fa-plus-circle', 'label' => 'إضافة (بدون صلاحيات)');
    }
    $header_actions[] = array('id' => 'toggleStats', 'class' => 'btn', 'title' => 'إظهار أو إخفاء الإحصائيات', 'icon' => 'fas fa-eye', 'label' => 'إظهار الإحصائيات', 'label_class' => 'quo-toggle-stats-text');
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fa-solid fa-share', 'label' => '');
    include('../includes/page_header.php');
    ?>

    <?php if (!empty($_GET['msg'])):
        $isSuccess = strpos($_GET['msg'], '✅') !== false; ?>
        <div class="success-message <?= $isSuccess ? 'is-success' : 'is-error' ?>">
            <i class="fas <?= $isSuccess ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?php echo quo_e($_GET['msg']); ?>
        </div>
    <?php endif; ?>

    <div class="stats-section quo-hidden" id="quoStatsSection">
        <div class="stats-grid">
            <div class="stats-card">
                <div class="stats-icon"><i class="fas fa-file-invoice-dollar"></i></div>
                <div class="stats-value"><?php echo $stat_total; ?></div>
                <div class="stats-title">إجمالي العروض</div>
            </div>
            <div class="stats-card">
                <div class="stats-icon"><i class="fas fa-paper-plane"></i></div>
                <div class="stats-value"><?php echo $stat_offered; ?></div>
                <div class="stats-title">مقدَّمة</div>
            </div>
            <div class="stats-card">
                <div class="stats-icon"><i class="fas fa-circle-check"></i></div>
                <div class="stats-value"><?php echo $stat_accepted; ?></div>
                <div class="stats-title">مقبولة</div>
            </div>
            <div class="stats-card">
                <div class="stats-icon"><i class="fas fa-coins"></i></div>
                <div class="stats-value"><?php echo quo_money($stat_amount); ?></div>
                <div class="stats-title">إجمالي القيمة</div>
            </div>
        </div>
    </div>

    <!-- فورم إضافة / تعديل عرض -->
    <form id="quoForm" action="" method="post" class="allforms">
        <div class="card-header">
            <h5><i class="fas fa-edit"></i> <span id="formTitle">إضافة عرض جديد</span></h5>
        </div>
        <input type="hidden" name="quo_id" id="quo_id" value="">
        <input type="hidden" name="csrf_token" value="<?php echo quo_e($quo_csrf_token); ?>">
        <div class="card shadow-sm pu-form-card">
            <div class="card-body">
                <div class="form-grid">
                    <div id="generated_code_wrapper" class="auto">
                        <label><i class="fas fa-magic"></i> كود العرض المولد <i class="fas fa-info-circle quo-info-icon"></i></label>
                        <input type="text" id="generated_quo_code" class="generated-code-field" value="<?php echo quo_e($next_quo_code); ?>" readonly tabindex="-1" title="هذا الكود للعرض فقط، انسخه إلى حقل كود العرض" />
                        <div class="generated-code-hint"></div>
                    </div>

                    <div>
                        <label><i class="fas fa-barcode"></i> كود العرض *</label>
                        <input type="text" name="quotation_code" id="quotation_code" placeholder="مثال: QUO-001" required pattern="[A-Za-z0-9-_]+" />
                    </div>
                    <div>
                        <label><i class="fas fa-user-tie"></i> العميل</label>
                        <select name="client_id" id="client_id">
                            <option value="">-- بدون / غير محدد --</option>
                            <?php foreach ($clients_options as $cl): ?>
                                <option value="<?php echo intval($cl['id']); ?>"><?php echo quo_e($cl['client_name']) . ' (' . quo_e($cl['client_code']) . ')'; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label><i class="fas fa-lightbulb"></i> الفرصة المصدر</label>
                        <select name="opportunity_id" id="opportunity_id">
                            <option value="">-- بدون / غير محدد --</option>
                            <?php foreach ($opp_options as $op): ?>
                                <option value="<?php echo intval($op['id']); ?>"><?php echo quo_e($op['title']) . ' (' . quo_e($op['opp_code']) . ')'; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label><i class="fas fa-money-bill-wave"></i> العملة</label>
                        <select name="currency" id="currency">
                            <?php foreach ($QUO_CURRENCIES as $cur): ?>
                                <option value="<?php echo quo_e($cur); ?>"><?php echo quo_e($cur); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label><i class="fas fa-coins"></i> إجمالي العرض</label>
                        <input type="number" step="0.01" min="0" name="amount_total" id="amount_total" placeholder="0.00" />
                    </div>
                    <div>
                        <label><i class="fas fa-calendar-day"></i> صلاحية العرض</label>
                        <input type="date" name="validity_date" id="validity_date" />
                    </div>
                    <div>
                        <label><i class="fas fa-hand-holding-usd"></i> شروط الدفع</label>
                        <input type="text" name="payment_terms" id="payment_terms" placeholder="مثال: دفعة مقدمة 30%" />
                    </div>
                    <div>
                        <label><i class="fas fa-flag"></i> الحالة</label>
                        <select name="state" id="state">
                            <?php foreach ($QUO_STATES as $st): ?>
                                <option value="<?php echo quo_e($st); ?>"><?php echo quo_e($st); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="quo-col-full">
                        <label><i class="fas fa-note-sticky"></i> ملاحظات</label>
                        <textarea name="notes" id="notes" rows="2" placeholder="أي ملاحظات إضافية"></textarea>
                    </div>
                </div>
                <div class="pu-form-actions">
                    <button type="submit" class="btn-submit"><i class="fas fa-save"></i> <span id="submitBtnText">حفظ العرض</span></button>
                    <button type="button" id="quoFormCancelBtn" class="btn-cancel"><i class="fas fa-times"></i> إلغاء</button>
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
                <label><i class="fa fa-flag"></i> الحالة</label>
                <select id="filterState" class="form-control">
                    <option value="">-- كل الحالات --</option>
                </select>
            </div>
            <div class="filter-field">
                <label><i class="fa fa-money-bill-wave"></i> العملة</label>
                <select id="filterCurrency" class="form-control">
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
                <table id="quoTable" class="display quo-table-nowrap no-datatable">
                    <thead>
                        <tr>
                            <th>إجراءات</th>
                            <th width="90">الكود</th>
                            <th>العميل</th>
                            <th>الفرصة</th>
                            <th>القيمة</th>
                            <th>الصلاحية</th>
                            <th>الحالة</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row):
                            $client_label = $row['client_name'] !== null ? $row['client_name'] : '';
                            $opp_label = $row['opp_title'] !== null ? $row['opp_title'] : '';
                            $created_label = function_exists('ems_actor_label') ? ems_actor_label($conn, intval($row['created_by'])) : ($row['creator_name'] ?? '');
                            $amount_disp = quo_money($row['amount_total']) . ' ' . $row['currency'];
                            ?>
                            <tr>
                                <td>
                                    <div class="action-btns">
                                        <a href="javascript:void(0)" class="action-btn view viewQuoBtn"
                                            data-id="<?php echo intval($row['id']); ?>"
                                            data-code="<?php echo quo_e($row['quotation_code']); ?>"
                                            data-client="<?php echo quo_e($client_label); ?>"
                                            data-opportunity="<?php echo quo_e($opp_label); ?>"
                                            data-currency="<?php echo quo_e($row['currency']); ?>"
                                            data-amount="<?php echo quo_e($amount_disp); ?>"
                                            data-validity="<?php echo quo_e($row['validity_date']); ?>"
                                            data-payment="<?php echo quo_e($row['payment_terms']); ?>"
                                            data-state="<?php echo quo_e($row['state']); ?>"
                                            data-notes="<?php echo quo_e($row['notes']); ?>"
                                            data-created="<?php echo quo_e($created_label); ?>"
                                            title="عرض التفاصيل"><i class="fas fa-eye"></i></a>
                                        <?php if ($can_edit): ?>
                                            <a href="javascript:void(0)" class="action-btn edit editQuoBtn"
                                                data-id="<?php echo intval($row['id']); ?>"
                                                data-code="<?php echo quo_e($row['quotation_code']); ?>"
                                                data-client-id="<?php echo intval($row['client_id']); ?>"
                                                data-opportunity-id="<?php echo intval($row['opportunity_id']); ?>"
                                                data-currency="<?php echo quo_e($row['currency']); ?>"
                                                data-amount="<?php echo quo_e($row['amount_total']); ?>"
                                                data-validity="<?php echo quo_e($row['validity_date']); ?>"
                                                data-payment="<?php echo quo_e($row['payment_terms']); ?>"
                                                data-state="<?php echo quo_e($row['state']); ?>"
                                                data-notes="<?php echo quo_e($row['notes']); ?>"
                                                title="تعديل"><i class="fas fa-edit"></i></a>
                                        <?php endif; ?>
                                        <?php if ($can_delete): ?>
                                            <a href="?delete_id=<?php echo urlencode($row['id']); ?>&csrf_token=<?php echo urlencode($quo_csrf_token); ?>"
                                                class="action-btn delete"
                                                onclick="return confirm('هل أنت متأكد من حذف هذا العرض؟')" title="حذف"><i class="fas fa-trash-alt"></i></a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><strong class="quo-code-cell"><?php echo quo_e($row['quotation_code']); ?></strong></td>
                                <td><?php echo $client_label !== '' ? quo_e($client_label) : '<span class="quo-muted">—</span>'; ?></td>
                                <td><?php echo $opp_label !== '' ? quo_e($opp_label) : '<span class="quo-muted">—</span>'; ?></td>
                                <td class="quo-num"><?php echo quo_e($amount_disp); ?></td>
                                <td class="quo-num"><?php echo $row['validity_date'] !== null ? quo_e($row['validity_date']) : '<span class="quo-muted">—</span>'; ?></td>
                                <td><span class="quo-badge <?php echo quo_state_class($row['state']); ?>"><?php echo quo_e($row['state']); ?></span></td>
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
        const quoTable = $('#quoTable').DataTable({
            autoWidth: false,
            stateSave: false,
            language: { url: '/ems/assets/i18n/datatables/ar.json' }
        });

        function fillFilterOptions(columnIndex, selectId) {
            const select = $(selectId);
            const values = [];
            quoTable.column(columnIndex).data().each(function (value) {
                const text = $('<div>').html(value).text().trim();
                if (text !== '' && values.indexOf(text) === -1) values.push(text);
            });
            values.sort();
            values.forEach(function (val) {
                select.append('<option value="' + val.replace(/"/g, '&quot;') + '">' + val + '</option>');
            });
        }
        fillFilterOptions(6, '#filterState');

        // العملة تُستخرج من نص القيمة (آخر كلمة)
        (function () {
            const select = $('#filterCurrency');
            const values = [];
            quoTable.column(4).data().each(function (value) {
                const text = $('<div>').html(value).text().trim();
                const parts = text.split(/\s+/);
                const cur = parts[parts.length - 1];
                if (cur !== '' && values.indexOf(cur) === -1) values.push(cur);
            });
            values.sort();
            values.forEach(function (val) {
                select.append('<option value="' + val.replace(/"/g, '&quot;') + '">' + val + '</option>');
            });
        })();

        $('#filterState').on('change', function () {
            const value = $.fn.dataTable.util.escapeRegex($(this).val());
            quoTable.column(6).search(value ? '^' + value + '$' : '', true, false).draw();
        });
        $('#filterCurrency').on('change', function () {
            const value = $.fn.dataTable.util.escapeRegex($(this).val());
            quoTable.column(4).search(value ? value : '', true, false).draw();
        });
    });

    // ── إظهار/إخفاء الفورم والإحصائيات ──
    const formToggleBtn = $('#toggleForm');
    const quoForm = $('#quoForm');
    const formTitle = $('#formTitle');
    const submitBtnText = $('#submitBtnText');
    const generatedCodeWrapper = $('#generated_code_wrapper');
    const formCancelBtn = $('#quoFormCancelBtn');
    const statsToggleBtn = $('#toggleStats');
    const statsSection = $('#quoStatsSection');

    function setAddMode() { formTitle.text('إضافة عرض جديد'); submitBtnText.text('حفظ العرض'); generatedCodeWrapper.show(); }
    function setEditMode() { formTitle.text('تعديل العرض'); submitBtnText.text('تحديث العرض'); generatedCodeWrapper.hide(); }
    function resetForm() { if (!quoForm.length) return; quoForm[0].reset(); $('#quo_id').val(''); setAddMode(); if (window.EmsSelect) EmsSelect.refresh(); }

    function updateFormToggleState(isOpen) {
        if (!formToggleBtn.length) return;
        formToggleBtn.toggleClass('is-active', isOpen);
        formToggleBtn.attr('aria-expanded', isOpen ? 'true' : 'false');
    }
    function updateStatsToggleState(isVisible) {
        if (!statsToggleBtn.length) return;
        statsToggleBtn.toggleClass('is-active', isVisible);
        statsToggleBtn.find('.quo-toggle-stats-text').text('إظهار الإحصائيات');
        const icon = statsToggleBtn.find('i').first();
        icon.toggleClass('fa-chart-pie', isVisible);
        icon.toggleClass('fa-eye', !isVisible);
    }

    setAddMode();
    updateFormToggleState(quoForm.is(':visible'));
    updateStatsToggleState(statsSection.is(':visible'));

    formToggleBtn.on('click', function (e) {
        e.preventDefault();
        if (!quoForm.length) return;
        if (quoForm.is(':visible')) {
            quoForm.stop(true, true).slideUp(250, function () { quoForm.removeClass('allforms-visible'); resetForm(); updateFormToggleState(false); });
        } else {
            resetForm();
            quoForm.addClass('allforms-visible').hide();
            quoForm.stop(true, true).slideDown(250, function () { updateFormToggleState(true); });
        }
    });
    formCancelBtn.on('click', function () {
        if (!quoForm.length || !quoForm.is(':visible')) return;
        quoForm.stop(true, true).slideUp(250, function () { quoForm.removeClass('allforms-visible'); resetForm(); updateFormToggleState(false); });
    });
    statsToggleBtn.on('click', function (e) {
        e.preventDefault();
        if (!statsSection.length) return;
        if (statsSection.is(':visible')) {
            statsSection.stop(true, true).slideUp(250, function () { statsSection.addClass('quo-hidden'); updateStatsToggleState(false); });
        } else {
            statsSection.removeClass('quo-hidden').hide();
            statsSection.stop(true, true).slideDown(250, function () { updateStatsToggleState(true); });
        }
    });

    // ── تعبئة الفورم للتعديل ──
    function fillQuoForm(d) {
        $('#quo_id').val(d.id);
        $('#quotation_code').val(d.code);
        $('#client_id').val(d.clientId ? String(d.clientId) : '');
        $('#opportunity_id').val(d.opportunityId ? String(d.opportunityId) : '');
        $('#currency').val(d.currency || 'USD');
        $('#amount_total').val(d.amount || '');
        $('#validity_date').val(d.validity || '');
        $('#payment_terms').val(d.payment || '');
        $('#state').val(d.state || 'مسودة');
        $('#notes').val(d.notes || '');
        if (window.EmsSelect) EmsSelect.refresh();
        setEditMode();
        if (!quoForm.is(':visible')) {
            quoForm.addClass('allforms-visible').hide();
            quoForm.stop(true, true).slideDown(250, function () { updateFormToggleState(true); });
        } else { updateFormToggleState(true); }
        $('html, body').animate({ scrollTop: $('#quoForm').offset().top - 100 }, 500);
    }

    $(document).on('click', '.editQuoBtn', function () {
        fillQuoForm({
            id: $(this).data('id'), code: $(this).data('code'),
            clientId: $(this).data('client-id'), opportunityId: $(this).data('opportunity-id'),
            currency: $(this).data('currency'), amount: $(this).data('amount'),
            validity: $(this).data('validity'), payment: $(this).data('payment'),
            state: $(this).data('state'), notes: $(this).data('notes')
        });
    });

    // ── عرض التفاصيل عبر EmsDetailsModal الموحّد ──
    $(document).on('click', '.viewQuoBtn', function () {
        const d = $(this).data();
        const stateVal = String(d.state || '');
        let tone = 'neutral';
        if (stateVal === 'مقبول') tone = 'active';
        else if (stateVal === 'مرفوض') tone = 'inactive';
        else if (stateVal === 'مقدم') tone = 'warning';
        const fields = [
            { label: 'كود العرض', value: d.code, icon: 'fas fa-barcode' },
            { label: 'العميل', value: d.client || '—', icon: 'fas fa-user-tie' },
            { label: 'الفرصة المصدر', value: d.opportunity || '—', icon: 'fas fa-lightbulb', size: 'lg' },
            { label: 'العملة', value: d.currency || '—', icon: 'fas fa-money-bill-wave' },
            { label: 'إجمالي العرض', value: d.amount || '—', icon: 'fas fa-coins', size: 'lg' },
            { label: 'صلاحية العرض', value: d.validity || '—', icon: 'fas fa-calendar-day' },
            { label: 'شروط الدفع', value: d.payment || '—', icon: 'fas fa-hand-holding-usd' },
            { label: 'الحالة', value: stateVal || '—', icon: 'fas fa-flag', type: 'status', tone: tone },
            { label: 'ملاحظات', value: d.notes || '—', icon: 'fas fa-note-sticky', size: 'lg' },
            { label: 'أضيف بواسطة', value: d.created || '—', icon: 'fas fa-user-plus' }
        ];

        const actions = [];
        <?php if ($can_edit): ?>
            actions.push({ label: 'تعديل العرض', icon: 'fas fa-edit', variant: 'primary', onClick: function () {
                EmsDetailsModal.close();
                $('.editQuoBtn[data-id="' + d.id + '"]').trigger('click');
            }});
        <?php endif; ?>
        actions.push({ label: 'إغلاق', icon: 'fas fa-times', variant: 'secondary', close: true });

        EmsDetailsModal.open({ title: 'تفاصيل العرض', icon: 'fas fa-file-invoice-dollar', fields: fields, actions: actions });
    });
</script>

<style>
    .quo-main .stats-grid { display: grid; grid-template-columns: repeat(4, minmax(170px, 1fr)); gap: 12px; }
    .quo-main .stats-section {
        border: 1px solid var(--bdr); border-radius: var(--rl);
        background: linear-gradient(180deg, rgba(255,255,255,.95) 0%, var(--s2) 100%);
        box-shadow: var(--sh); padding: 14px; margin-bottom: 14px;
    }
    .quo-main .stats-card { background: #eee; border: 1px solid #aaa; border-radius: 35px; padding: 18px; box-shadow: 0 2px 8px rgba(26,18,8,.07); position: relative; overflow: hidden; }
    .quo-main .stats-card .stats-icon { width: 55px; height: 55px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.3rem; margin-bottom: 10px; float: left; margin-top: 15px; border: 1px solid #999; background:#fff; color:#000; }
    .quo-main .stats-card .stats-title { color: #555; font-size: .92rem; font-weight: 700; margin-top: 5px; line-height: 1.3; }
    .quo-main .stats-card .stats-value { color: #222; line-height: 1; font-weight: 900; font-variant-numeric: tabular-nums; margin-top: 10px; font-size: 30px; }
    @media (max-width: 900px) { .quo-main .stats-grid { grid-template-columns: repeat(2, minmax(150px,1fr)); } }
    @media (max-width: 560px) { .quo-main .stats-grid { grid-template-columns: 1fr; } }

    .quo-main .quo-hidden { display: none; }
    .quo-main .quo-col-full { grid-column: 1 / -1; }
    .quo-main .table-container { overflow-x: auto; }
    #quoTable.quo-table-nowrap, #quoTable.quo-table-nowrap th, #quoTable.quo-table-nowrap td { white-space: nowrap; }
    #quoTable .action-btns { flex-wrap: nowrap; white-space: nowrap; }
    .quo-main .quo-num { font-variant-numeric: tabular-nums; font-weight: 700; }
    .quo-main .quo-muted { color: #999; }
    .quo-main .quo-badge { display:inline-block; padding:2px 10px; border-radius:999px; font-size:.75rem; font-weight:800; border:1px solid transparent; }
    .quo-main .quo-badge-green { background:rgba(34,197,94,.14); color:#15803d; border-color:rgba(34,197,94,.3); }
    .quo-main .quo-badge-red { background:rgba(239,68,68,.14); color:#b91c1c; border-color:rgba(239,68,68,.3); }
    .quo-main .quo-badge-amber { background:rgba(245,158,11,.16); color:#b45309; border-color:rgba(245,158,11,.3); }
    .quo-main .quo-badge-gray { background:rgba(107,114,128,.14); color:#4b5563; border-color:rgba(107,114,128,.3); }
</style>

</body>

</html>
