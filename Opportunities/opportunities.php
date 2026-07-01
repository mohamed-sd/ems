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
if (!function_exists('opp_table_has_column')) {
    function opp_table_has_column($conn, $tableName, $columnName)
    {
        $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $tableName);
        $safeCol   = preg_replace('/[^a-zA-Z0-9_]/', '', $columnName);
        $sql = "SHOW COLUMNS FROM " . $safeTable . " LIKE '" . mysqli_real_escape_string($conn, $safeCol) . "'";
        $res = @mysqli_query($conn, $sql);
        return $res && mysqli_num_rows($res) > 0;
    }
}

if (!function_exists('opp_e')) {
    function opp_e($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('opp_redirect_with_msg')) {
    function opp_redirect_with_msg($msg)
    {
        header('Location: opportunities.php?msg=' . urlencode($msg));
        exit();
    }
}

if (!function_exists('opp_money')) {
    function opp_money($value)
    {
        return number_format((float) $value, 2);
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

// ══════════════════════════════════════════════════════════════════════════════
// شروط النطاق والحذف الناعم
// ══════════════════════════════════════════════════════════════════════════════
$scope_sql        = "o.company_id = $company_id";
$scope_update_sql = "company_id = $company_id";
$not_deleted_sql  = "o.is_deleted = 0";

// ══════════════════════════════════════════════════════════════════════════════
// رمز CSRF
// ══════════════════════════════════════════════════════════════════════════════
if (empty($_SESSION['opp_csrf_token'])) {
    $_SESSION['opp_csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
}
$opp_csrf_token = $_SESSION['opp_csrf_token'];

// ══════════════════════════════════════════════════════════════════════════════
// القوائم الثابتة (مراحل المسار ونماذج الإيراد)
// ══════════════════════════════════════════════════════════════════════════════
$OPP_STAGES = array('جديدة', 'قيد الدراسة', 'مؤهلة', 'عرض مقدم', 'تفاوض', 'فوز', 'خسارة', 'مستبعدة');
$OPP_OPEN_STAGES   = array('جديدة', 'قيد الدراسة', 'مؤهلة', 'عرض مقدم', 'تفاوض');
$OPP_CLOSED_STAGES = array('فوز', 'خسارة', 'مستبعدة');
$OPP_REVENUE_MODELS = array(
    'hourly' => 'تأجير بالساعة',
    'ton'    => 'نقل بالطن',
    'meter'  => 'تخريم بالمتر',
    'mixed'  => 'مزيج',
);
$OPP_ATTRACT = array('منخفضة', 'متوسطة', 'عالية');
$OPP_FIT     = array('منخفض', 'متوسط', 'عالي');
$OPP_DECISION = array('متابعة', 'تعليق', 'استبعاد');
$OPP_SOURCES = array('سوق', 'إحالة', 'مناقصة', 'عميل قائم');
$OPP_CURRENCIES = array('USD', 'SDG');
// احتمال الفوز الإرشادي لكل مرحلة (§7.1)
$OPP_STAGE_PROB = array(
    'جديدة' => 10, 'قيد الدراسة' => 20, 'مؤهلة' => 35,
    'عرض مقدم' => 55, 'تفاوض' => 75, 'فوز' => 100, 'خسارة' => 0, 'مستبعدة' => 0,
);

// ══════════════════════════════════════════════════════════════════════════════
// توليد الكود المقترح التالي (OPP-NNNN) — للعرض فقط
// ══════════════════════════════════════════════════════════════════════════════
$next_opp_code = 'OPP-0001';
$last_code_sql = "SELECT opp_code FROM opportunities
                  WHERE opp_code REGEXP '^OPP-[0-9]+$' AND company_id = $company_id AND is_deleted = 0
                  ORDER BY CAST(SUBSTRING(opp_code, 5) AS UNSIGNED) DESC LIMIT 1";
$last_code_res = @mysqli_query($conn, $last_code_sql);
if ($last_code_res && mysqli_num_rows($last_code_res) > 0) {
    $last_code_row = mysqli_fetch_assoc($last_code_res);
    $last_num = intval(substr($last_code_row['opp_code'], 4));
    $next_opp_code = 'OPP-' . str_pad($last_num + 1, 4, '0', STR_PAD_LEFT);
}

// ══════════════════════════════════════════════════════════════════════════════
// صلاحيات المستخدم على وحدة الفرص
// ══════════════════════════════════════════════════════════════════════════════
$module_query = "SELECT id FROM modules WHERE code = 'Opportunities/opportunities.php' LIMIT 1";
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
    header('Location: ../login.php?msg=' . urlencode('لا توجد صلاحية عرض الفرص ❌'));
    exit();
}

// ══════════════════════════════════════════════════════════════════════════════
// معالجة إضافة / تعديل فرصة عبر POST
// ══════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['title'])) {
    $posted_csrf = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if (empty($posted_csrf) || !hash_equals($opp_csrf_token, $posted_csrf)) {
        opp_redirect_with_msg('جلسة النموذج غير صالحة، يرجى إعادة المحاولة ❌');
    }

    $opp_id     = isset($_POST['opp_id']) ? intval($_POST['opp_id']) : 0;
    $is_editing = $opp_id > 0;

    if ($is_editing && !$can_edit) {
        opp_redirect_with_msg('لا توجد صلاحية تعديل الفرص ❌');
    } elseif (!$is_editing && !$can_add) {
        opp_redirect_with_msg('لا توجد صلاحية إضافة فرص جديدة ❌');
    }

    // كود الفرصة
    $opp_code_raw = isset($_POST['opp_code']) ? trim($_POST['opp_code']) : '';
    if ($opp_code_raw === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $opp_code_raw)) {
        opp_redirect_with_msg('كود الفرصة غير صالح. استخدم أحرفًا وأرقامًا و - أو _ فقط ❌');
    }

    // التحقق من القوائم الثابتة
    $stage_raw = isset($_POST['stage']) ? trim($_POST['stage']) : 'جديدة';
    if (!in_array($stage_raw, $OPP_STAGES, true)) {
        opp_redirect_with_msg('مرحلة المسار غير صالحة ❌');
    }
    $revenue_model_raw = isset($_POST['revenue_model']) ? trim($_POST['revenue_model']) : '';
    if ($revenue_model_raw !== '' && !isset($OPP_REVENUE_MODELS[$revenue_model_raw])) {
        opp_redirect_with_msg('نموذج الإيراد غير صالح ❌');
    }
    $currency_raw = isset($_POST['currency']) ? trim($_POST['currency']) : 'USD';
    if (!in_array($currency_raw, $OPP_CURRENCIES, true)) {
        $currency_raw = 'USD';
    }
    $attractiveness_raw = isset($_POST['attractiveness']) ? trim($_POST['attractiveness']) : '';
    if ($attractiveness_raw !== '' && !in_array($attractiveness_raw, $OPP_ATTRACT, true)) {
        $attractiveness_raw = '';
    }
    $strategy_fit_raw = isset($_POST['strategy_fit']) ? trim($_POST['strategy_fit']) : '';
    if ($strategy_fit_raw !== '' && !in_array($strategy_fit_raw, $OPP_FIT, true)) {
        $strategy_fit_raw = '';
    }
    $study_decision_raw = isset($_POST['study_decision']) ? trim($_POST['study_decision']) : '';
    if ($study_decision_raw !== '' && !in_array($study_decision_raw, $OPP_DECISION, true)) {
        $study_decision_raw = '';
    }

    // العميل المرتبط — يجب أن يتبع الشركة نفسها (إن حُدِّد)
    $client_id_in = isset($_POST['client_id']) ? intval($_POST['client_id']) : 0;
    if ($client_id_in > 0) {
        $client_ok = mysqli_query($conn, "SELECT id FROM clients WHERE id = $client_id_in AND company_id = $company_id AND is_deleted = 0 LIMIT 1");
        if (!$client_ok || mysqli_num_rows($client_ok) === 0) {
            opp_redirect_with_msg('العميل المحدد غير موجود أو خارج نطاق شركتك ❌');
        }
    }
    $client_sql = $client_id_in > 0 ? "'$client_id_in'" : 'NULL';

    // تنظيف بقية الحقول
    $opp_code        = mysqli_real_escape_string($conn, $opp_code_raw);
    $title           = mysqli_real_escape_string($conn, trim($_POST['title']));
    $source          = mysqli_real_escape_string($conn, isset($_POST['source']) ? trim($_POST['source']) : '');
    $sector_category = mysqli_real_escape_string($conn, isset($_POST['sector_category']) ? trim($_POST['sector_category']) : '');
    $state_region    = mysqli_real_escape_string($conn, isset($_POST['state_region']) ? trim($_POST['state_region']) : '');
    $revenue_model   = $revenue_model_raw !== '' ? "'" . mysqli_real_escape_string($conn, $revenue_model_raw) . "'" : 'NULL';
    $expected_revenue = isset($_POST['expected_revenue']) ? (float) $_POST['expected_revenue'] : 0;
    $funding_needed   = isset($_POST['funding_needed']) ? (float) $_POST['funding_needed'] : 0;
    $probability      = isset($_POST['probability']) && $_POST['probability'] !== ''
        ? max(0, min(100, (float) $_POST['probability']))
        : (isset($OPP_STAGE_PROB[$stage_raw]) ? $OPP_STAGE_PROB[$stage_raw] : 0);
    $currency        = mysqli_real_escape_string($conn, $currency_raw);
    $stage           = mysqli_real_escape_string($conn, $stage_raw);
    $attractiveness  = $attractiveness_raw !== '' ? "'" . mysqli_real_escape_string($conn, $attractiveness_raw) . "'" : 'NULL';
    $strategy_fit    = $strategy_fit_raw !== '' ? "'" . mysqli_real_escape_string($conn, $strategy_fit_raw) . "'" : 'NULL';
    $study_decision  = $study_decision_raw !== '' ? "'" . mysqli_real_escape_string($conn, $study_decision_raw) . "'" : 'NULL';
    $capacity_summary = mysqli_real_escape_string($conn, isset($_POST['capacity_summary']) ? trim($_POST['capacity_summary']) : '');
    $lost_reason     = mysqli_real_escape_string($conn, isset($_POST['lost_reason']) ? trim($_POST['lost_reason']) : '');
    $win_reason      = mysqli_real_escape_string($conn, isset($_POST['win_reason']) ? trim($_POST['win_reason']) : '');
    $review_notes    = mysqli_real_escape_string($conn, isset($_POST['review_notes']) ? trim($_POST['review_notes']) : '');
    $notes           = mysqli_real_escape_string($conn, isset($_POST['notes']) ? trim($_POST['notes']) : '');
    $close_date_raw  = isset($_POST['expected_close_date']) ? trim($_POST['expected_close_date']) : '';
    $close_date_sql  = preg_match('/^\d{4}-\d{2}-\d{2}$/', $close_date_raw) ? "'$close_date_raw'" : 'NULL';
    $created_by      = intval($_SESSION['user']['id']);

    if ($is_editing) {
        // تحقق من الملكية
        $owner = mysqli_query($conn, "SELECT id FROM opportunities WHERE id = $opp_id AND company_id = $company_id AND is_deleted = 0 LIMIT 1");
        if (!$owner || mysqli_num_rows($owner) === 0) {
            opp_redirect_with_msg('لا يمكنك تعديل فرصة لا تتبع لشركتك ❌');
        }
        // منع تكرار الكود
        $dup = mysqli_query($conn, "SELECT id FROM opportunities WHERE opp_code = '$opp_code' AND id != $opp_id AND company_id = $company_id AND is_deleted = 0");
        if ($dup && mysqli_num_rows($dup) > 0) {
            opp_redirect_with_msg('كود الفرصة موجود مسبقاً داخل شركتك ❌');
        }

        $update_query = "UPDATE opportunities SET
            opp_code = '$opp_code', title = '$title', client_id = $client_sql, source = '$source',
            sector_category = '$sector_category', state_region = '$state_region', revenue_model = $revenue_model,
            expected_revenue = '$expected_revenue', currency = '$currency', probability = '$probability',
            stage = '$stage', attractiveness = $attractiveness, strategy_fit = $strategy_fit,
            capacity_summary = '$capacity_summary', funding_needed = '$funding_needed', study_decision = $study_decision,
            expected_close_date = $close_date_sql, lost_reason = '$lost_reason', win_reason = '$win_reason',
            review_notes = '$review_notes', notes = '$notes'
            WHERE id = $opp_id AND $scope_update_sql AND is_deleted = 0";

        if (mysqli_query($conn, $update_query)) {
            if (class_exists('\\App\\Services\\ActivityLogService')) {
                \App\Services\ActivityLogService::logUpdate('opportunities', 'opportunities', $opp_id, null, ['opp_code' => $opp_code_raw, 'title' => trim($_POST['title'])]);
            }
            opp_redirect_with_msg('تم تعديل الفرصة بنجاح ✅');
        }
        error_log('opportunities.php update failed: ' . mysqli_error($conn));
        opp_redirect_with_msg('حدث خطأ أثناء التعديل ❌');
    } else {
        // منع تكرار الكود
        $dup = mysqli_query($conn, "SELECT id FROM opportunities WHERE opp_code = '$opp_code' AND company_id = $company_id AND is_deleted = 0");
        if ($dup && mysqli_num_rows($dup) > 0) {
            opp_redirect_with_msg('كود الفرصة موجود مسبقاً داخل شركتك ❌');
        }

        $insert_query = "INSERT INTO opportunities
            (company_id, opp_code, title, client_id, source, sector_category, state_region, revenue_model,
             expected_revenue, currency, probability, stage, attractiveness, strategy_fit, capacity_summary,
             funding_needed, study_decision, expected_close_date, lost_reason, win_reason, review_notes, notes, created_by)
            VALUES
            ('$company_id', '$opp_code', '$title', $client_sql, '$source', '$sector_category', '$state_region', $revenue_model,
             '$expected_revenue', '$currency', '$probability', '$stage', $attractiveness, $strategy_fit, '$capacity_summary',
             '$funding_needed', $study_decision, $close_date_sql, '$lost_reason', '$win_reason', '$review_notes', '$notes', '$created_by')";

        if (mysqli_query($conn, $insert_query)) {
            $new_id = (int) mysqli_insert_id($conn);
            if (class_exists('\\App\\Services\\ActivityLogService')) {
                \App\Services\ActivityLogService::logCreate('opportunities', 'opportunities', $new_id, ['opp_code' => $opp_code_raw, 'title' => trim($_POST['title'])]);
            }
            opp_redirect_with_msg('تم إضافة الفرصة بنجاح ✅');
        }
        error_log('opportunities.php insert failed: ' . mysqli_error($conn));
        opp_redirect_with_msg('حدث خطأ أثناء الإضافة ❌');
    }
}

// ══════════════════════════════════════════════════════════════════════════════
// معالجة الحذف الناعم
// ══════════════════════════════════════════════════════════════════════════════
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $delete_csrf = isset($_GET['csrf_token']) ? $_GET['csrf_token'] : '';

    if (!$can_delete) {
        opp_redirect_with_msg('لا توجد صلاحية حذف الفرص ❌');
    }
    if (empty($delete_csrf) || !hash_equals($opp_csrf_token, $delete_csrf)) {
        opp_redirect_with_msg('جلسة الحذف غير صالحة، يرجى إعادة المحاولة ❌');
    }
    $chk = mysqli_query($conn, "SELECT id FROM opportunities WHERE id = $delete_id AND company_id = $company_id AND is_deleted = 0 LIMIT 1");
    if (!$chk || mysqli_num_rows($chk) === 0) {
        opp_redirect_with_msg('لا يمكنك حذف فرصة لا تتبع لشركتك ❌');
    }
    $deleted_by = intval($_SESSION['user']['id']);
    $del = "UPDATE opportunities SET is_deleted = 1, deleted_at = NOW(), deleted_by = $deleted_by
            WHERE id = $delete_id AND $scope_update_sql AND is_deleted = 0";
    if (mysqli_query($conn, $del)) {
        if (class_exists('\\App\\Services\\ActivityLogService')) {
            \App\Services\ActivityLogService::logDelete('opportunities', 'opportunities', $delete_id);
        }
        opp_redirect_with_msg('تم حذف الفرصة بنجاح ✅');
    }
    error_log('opportunities.php soft delete failed: ' . mysqli_error($conn));
    opp_redirect_with_msg('حدث خطأ أثناء الحذف ❌');
}

// ══════════════════════════════════════════════════════════════════════════════
// قائمة العملاء (للقائمة المنسدلة) — ضمن نطاق الشركة
// ══════════════════════════════════════════════════════════════════════════════
$clients_options = array();
$clients_map = array();
$cl_res = mysqli_query($conn, "SELECT id, client_code, client_name FROM clients WHERE company_id = $company_id AND is_deleted = 0 ORDER BY client_name ASC");
if ($cl_res) {
    while ($cl = mysqli_fetch_assoc($cl_res)) {
        $clients_options[] = $cl;
        $clients_map[intval($cl['id'])] = $cl['client_name'];
    }
}

// ══════════════════════════════════════════════════════════════════════════════
// جلب الفرص + الإحصائيات
// ══════════════════════════════════════════════════════════════════════════════
$rows = array();
$stat_total = 0;
$stat_open = 0;
$stat_won = 0;
$stat_lost = 0;
$stat_excluded = 0;
$stat_qualified_plus = 0;
$pipeline_value = 0.0;    // قيمة المسار (المفتوحة)
$negotiation_value = 0.0; // قيمة تحت التفاوض
$won_value = 0.0;

$q = "SELECT o.*, c.client_name, u.name AS creator_name
      FROM opportunities o
      LEFT JOIN clients c ON c.id = o.client_id
      LEFT JOIN users u ON u.id = o.created_by
      WHERE $scope_sql AND $not_deleted_sql
      ORDER BY o.id DESC";
$res = mysqli_query($conn, $q);
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $rows[] = $row;
        $stat_total++;
        $stg = trim($row['stage']);
        $rev = (float) $row['expected_revenue'];
        if (in_array($stg, $OPP_OPEN_STAGES, true)) {
            $stat_open++;
            $pipeline_value += $rev;
        }
        if (in_array($stg, array('مؤهلة', 'عرض مقدم', 'تفاوض'), true)) {
            $stat_qualified_plus++;
        }
        if ($stg === 'تفاوض') {
            $negotiation_value += $rev;
        }
        if ($stg === 'فوز') {
            $stat_won++;
            $won_value += $rev;
        }
        if ($stg === 'خسارة') {
            $stat_lost++;
        }
        if ($stg === 'مستبعدة') {
            $stat_excluded++;
        }
    }
}
$decided = $stat_won + $stat_lost;
$conversion_rate = $decided > 0 ? round(($stat_won / $decided) * 100, 1) : 0;

$page_title = "مسار الفرص";
include("../inheader.php");
include('../insidebar.php');

// أدوات عرض المرحلة
function opp_stage_tone($stage)
{
    switch (trim($stage)) {
        case 'فوز':      return 'won';
        case 'خسارة':    return 'lost';
        case 'مستبعدة':  return 'excluded';
        case 'تفاوض':    return 'negotiation';
        case 'عرض مقدم': return 'quoted';
        case 'مؤهلة':    return 'qualified';
        case 'قيد الدراسة': return 'study';
        default:          return 'new';
    }
}
?>

<div class="main opp-main ems-unified-page-shell">

    <?php
    $header_title = 'مسار الفرص';
    $header_icon = 'fas fa-filter';
    $header_actions = array();
    if ($can_add) {
        $header_actions[] = array('id' => 'toggleForm', 'class' => 'add-btn', 'icon' => 'fa fa-solid fa-plus', 'label' => '', 'label_class' => 'opp-toggle-form-text');
    } else {
        $header_actions[] = array('tag' => 'button', 'class' => '', 'disabled' => true, 'icon' => 'fas fa-plus-circle', 'label' => 'إضافة (بدون صلاحيات)');
    }
    $header_actions[] = array('id' => 'toggleStats', 'class' => 'btn', 'title' => 'إظهار أو إخفاء الإحصائيات', 'icon' => 'fas fa-eye', 'label' => 'إظهار الإحصائيات', 'label_class' => 'opp-toggle-stats-text');
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fa-solid fa-share', 'label' => '');
    include('../includes/page_header.php');
    ?>

    <?php if (!empty($_GET['msg'])):
        $isSuccess = strpos($_GET['msg'], '✅') !== false; ?>
        <div class="success-message <?= $isSuccess ? 'is-success' : 'is-error' ?>">
            <i class="fas <?= $isSuccess ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?php echo opp_e($_GET['msg']); ?>
        </div>
    <?php endif; ?>

    <div class="stats-section opp-hidden" id="oppStatsSection">
        <div class="stats-grid">
            <div class="stats-card">
                <div class="stats-icon"><i class="fas fa-filter"></i></div>
                <div class="stats-value"><?php echo $stat_total; ?></div>
                <div class="stats-title">إجمالي الفرص</div>
            </div>
            <div class="stats-card">
                <div class="stats-icon"><i class="fas fa-folder-open"></i></div>
                <div class="stats-value"><?php echo $stat_open; ?></div>
                <div class="stats-title">الفرص المفتوحة</div>
            </div>
            <div class="stats-card">
                <div class="stats-icon"><i class="fas fa-sack-dollar"></i></div>
                <div class="stats-value"><?php echo opp_money($pipeline_value); ?></div>
                <div class="stats-title">قيمة المسار</div>
            </div>
            <div class="stats-card">
                <div class="stats-icon"><i class="fas fa-handshake"></i></div>
                <div class="stats-value"><?php echo opp_money($negotiation_value); ?></div>
                <div class="stats-title">تحت التفاوض</div>
            </div>
            <div class="stats-card">
                <div class="stats-icon"><i class="fas fa-star"></i></div>
                <div class="stats-value"><?php echo $stat_qualified_plus; ?></div>
                <div class="stats-title">مؤهّلة فأكثر</div>
            </div>
            <div class="stats-card">
                <div class="stats-icon"><i class="fas fa-trophy"></i></div>
                <div class="stats-value"><?php echo $stat_won; ?></div>
                <div class="stats-title">فرص فائزة</div>
            </div>
            <div class="stats-card">
                <div class="stats-icon"><i class="fas fa-circle-xmark"></i></div>
                <div class="stats-value"><?php echo $stat_lost; ?></div>
                <div class="stats-title">فرص خاسرة</div>
            </div>
            <div class="stats-card">
                <div class="stats-icon"><i class="fas fa-percent"></i></div>
                <div class="stats-value"><?php echo $conversion_rate; ?>%</div>
                <div class="stats-title">معدل التحويل</div>
            </div>
        </div>
    </div>

    <!-- فورم إضافة / تعديل فرصة -->
    <form id="oppForm" action="" method="post" class="allforms">
        <div class="card-header">
            <h5><i class="fas fa-edit"></i> <span id="formTitle">إضافة فرصة جديدة</span></h5>
        </div>
        <input type="hidden" name="opp_id" id="opp_id" value="">
        <input type="hidden" name="csrf_token" value="<?php echo opp_e($opp_csrf_token); ?>">
        <div class="card shadow-sm pu-form-card">
            <div class="card-body">
                <div class="form-grid">
                    <div id="generated_code_wrapper" class="auto">
                        <label><i class="fas fa-magic"></i> كود الفرصة المولد <i class="fas fa-info-circle opp-info-icon"></i></label>
                        <input type="text" id="generated_opp_code" class="generated-code-field" value="<?php echo opp_e($next_opp_code); ?>" readonly tabindex="-1" title="هذا الكود للعرض فقط، انسخه إلى حقل كود الفرصة" />
                        <div class="generated-code-hint"></div>
                    </div>

                    <div>
                        <label><i class="fas fa-barcode"></i> كود الفرصة *</label>
                        <input type="text" name="opp_code" id="opp_code" placeholder="مثال: OPP-001" required pattern="[A-Za-z0-9-_]+" />
                    </div>
                    <div>
                        <label><i class="fas fa-lightbulb"></i> عنوان الفرصة *</label>
                        <input type="text" name="title" id="title" placeholder="وصف مختصر للفرصة" required />
                    </div>
                    <div>
                        <label><i class="fas fa-user-tie"></i> العميل المستهدف</label>
                        <select name="client_id" id="client_id">
                            <option value="">-- بدون / عميل محتمل --</option>
                            <?php foreach ($clients_options as $cl): ?>
                                <option value="<?php echo intval($cl['id']); ?>"><?php echo opp_e($cl['client_name']) . ' (' . opp_e($cl['client_code']) . ')'; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label><i class="fas fa-signs-post"></i> مصدر الفرصة</label>
                        <select name="source" id="source">
                            <option value="">-- اختر المصدر --</option>
                            <?php foreach ($OPP_SOURCES as $s): ?>
                                <option value="<?php echo opp_e($s); ?>"><?php echo opp_e($s); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label><i class="fas fa-layer-group"></i> مرحلة المسار *</label>
                        <select name="stage" id="stage" required>
                            <?php foreach ($OPP_STAGES as $s): ?>
                                <option value="<?php echo opp_e($s); ?>"><?php echo opp_e($s); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label><i class="fas fa-coins"></i> نموذج الإيراد</label>
                        <select name="revenue_model" id="revenue_model">
                            <option value="">-- اختر النموذج --</option>
                            <?php foreach ($OPP_REVENUE_MODELS as $k => $v): ?>
                                <option value="<?php echo opp_e($k); ?>"><?php echo opp_e($v); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label><i class="fas fa-industry"></i> تصنيف القطاع</label>
                        <select name="sector_category" id="sector_category">
                            <option value="">-- اختر التصنيف --</option>
                            <option value="تعدين">تعدين</option>
                            <option value="مقاولات">مقاولات</option>
                            <option value="نقل ومواصلات">نقل ومواصلات</option>
                            <option value="نفط وغاز">نفط وغاز</option>
                            <option value="بنية تحتية">بنية تحتية</option>
                            <option value="خدمات">خدمات</option>
                            <option value="أخرى">أخرى</option>
                        </select>
                    </div>
                    <div>
                        <label><i class="fas fa-map-location-dot"></i> الولاية / الموقع</label>
                        <input type="text" name="state_region" id="state_region" placeholder="مثال: نهر النيل" />
                    </div>
                    <div>
                        <label><i class="fas fa-money-bill-wave"></i> القيمة التقديرية</label>
                        <input type="number" step="0.01" min="0" name="expected_revenue" id="expected_revenue" placeholder="0.00" />
                    </div>
                    <div>
                        <label><i class="fas fa-coins"></i> العملة</label>
                        <select name="currency" id="currency">
                            <option value="USD">دولار (USD)</option>
                            <option value="SDG">جنيه (SDG)</option>
                        </select>
                    </div>
                    <div>
                        <label><i class="fas fa-percent"></i> احتمال الفوز (%)</label>
                        <input type="number" step="0.1" min="0" max="100" name="probability" id="probability" placeholder="يُشتق من المرحلة إن تُرك فارغاً" />
                    </div>
                    <div>
                        <label><i class="fas fa-calendar-day"></i> تاريخ الإغلاق المتوقع</label>
                        <input type="date" name="expected_close_date" id="expected_close_date" />
                    </div>
                    <div>
                        <label><i class="fas fa-fire"></i> الجاذبية</label>
                        <select name="attractiveness" id="attractiveness">
                            <option value="">-- غير محددة --</option>
                            <?php foreach ($OPP_ATTRACT as $a): ?>
                                <option value="<?php echo opp_e($a); ?>"><?php echo opp_e($a); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label><i class="fas fa-bullseye"></i> التوافق الاستراتيجي</label>
                        <select name="strategy_fit" id="strategy_fit">
                            <option value="">-- غير محدد --</option>
                            <?php foreach ($OPP_FIT as $f): ?>
                                <option value="<?php echo opp_e($f); ?>"><?php echo opp_e($f); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label><i class="fas fa-clipboard-check"></i> قرار الدراسة</label>
                        <select name="study_decision" id="study_decision">
                            <option value="">-- لم يُتخذ --</option>
                            <?php foreach ($OPP_DECISION as $d): ?>
                                <option value="<?php echo opp_e($d); ?>"><?php echo opp_e($d); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label><i class="fas fa-hand-holding-dollar"></i> الحاجة للتمويل</label>
                        <input type="number" step="0.01" min="0" name="funding_needed" id="funding_needed" placeholder="0.00" />
                    </div>
                    <div class="opp-col-full">
                        <label><i class="fas fa-boxes-stacked"></i> المتطلبات المبدئية (معدات · مشغّلون · موردون)</label>
                        <textarea name="capacity_summary" id="capacity_summary" rows="2" placeholder="ملخص القدرة المطلوبة"></textarea>
                    </div>
                    <div>
                        <label><i class="fas fa-trophy"></i> سبب الفوز</label>
                        <input type="text" name="win_reason" id="win_reason" placeholder="عند الفوز" />
                    </div>
                    <div>
                        <label><i class="fas fa-circle-xmark"></i> سبب الخسارة</label>
                        <input type="text" name="lost_reason" id="lost_reason" placeholder="عند الخسارة" />
                    </div>
                    <div class="opp-col-full">
                        <label><i class="fas fa-clipboard-list"></i> ملاحظات المراجعة (بعد الحسم)</label>
                        <textarea name="review_notes" id="review_notes" rows="2" placeholder="خلاصة مراجعة ما بعد الفوز/الخسارة"></textarea>
                    </div>
                    <div class="opp-col-full">
                        <label><i class="fas fa-note-sticky"></i> ملاحظات عامة</label>
                        <textarea name="notes" id="notes" rows="2" placeholder="أي ملاحظات إضافية"></textarea>
                    </div>
                </div>
                <div class="pu-form-actions">
                    <button type="submit" class="btn-submit"><i class="fas fa-save"></i> <span id="submitBtnText">حفظ الفرصة</span></button>
                    <button type="button" id="oppFormCancelBtn" class="btn-cancel"><i class="fas fa-times"></i> إلغاء</button>
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
                <label><i class="fa fa-layer-group"></i> مرحلة المسار</label>
                <select id="filterStage" class="form-control">
                    <option value="">-- كل المراحل --</option>
                </select>
            </div>
            <div class="filter-field">
                <label><i class="fa fa-industry"></i> تصنيف القطاع</label>
                <select id="filterSector" class="form-control">
                    <option value="">-- كل القطاعات --</option>
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
                <table id="oppTable" class="display opp-table-nowrap no-datatable">
                    <thead>
                        <tr>
                            <th>إجراءات</th>
                            <th width="90">الكود</th>
                            <th>عنوان الفرصة</th>
                            <th>العميل</th>
                            <th>القطاع</th>
                            <th>المرحلة</th>
                            <th>القيمة التقديرية</th>
                            <th>الاحتمال</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row):
                            $tone = opp_stage_tone($row['stage']);
                            $client_name = $row['client_name'] !== null ? $row['client_name'] : '';
                            $rev_label = isset($OPP_REVENUE_MODELS[$row['revenue_model']]) ? $OPP_REVENUE_MODELS[$row['revenue_model']] : '';
                            $created_label = function_exists('ems_actor_label') ? ems_actor_label($conn, intval($row['created_by'])) : ($row['creator_name'] ?? '');
                            ?>
                            <tr>
                                <td>
                                    <div class="action-btns">
                                        <a href="javascript:void(0)" class="action-btn view viewOppBtn"
                                            data-id="<?php echo intval($row['id']); ?>"
                                            data-code="<?php echo opp_e($row['opp_code']); ?>"
                                            data-title="<?php echo opp_e($row['title']); ?>"
                                            data-client="<?php echo opp_e($client_name); ?>"
                                            data-source="<?php echo opp_e($row['source']); ?>"
                                            data-sector="<?php echo opp_e($row['sector_category']); ?>"
                                            data-region="<?php echo opp_e($row['state_region']); ?>"
                                            data-revenue-model="<?php echo opp_e($rev_label); ?>"
                                            data-expected="<?php echo opp_e(opp_money($row['expected_revenue'])); ?>"
                                            data-currency="<?php echo opp_e($row['currency']); ?>"
                                            data-probability="<?php echo opp_e($row['probability']); ?>"
                                            data-stage="<?php echo opp_e($row['stage']); ?>"
                                            data-attractiveness="<?php echo opp_e($row['attractiveness']); ?>"
                                            data-fit="<?php echo opp_e($row['strategy_fit']); ?>"
                                            data-capacity="<?php echo opp_e($row['capacity_summary']); ?>"
                                            data-funding="<?php echo opp_e(opp_money($row['funding_needed'])); ?>"
                                            data-decision="<?php echo opp_e($row['study_decision']); ?>"
                                            data-close="<?php echo opp_e($row['expected_close_date']); ?>"
                                            data-win="<?php echo opp_e($row['win_reason']); ?>"
                                            data-lost="<?php echo opp_e($row['lost_reason']); ?>"
                                            data-review="<?php echo opp_e($row['review_notes']); ?>"
                                            data-notes="<?php echo opp_e($row['notes']); ?>"
                                            data-created="<?php echo opp_e($created_label); ?>"
                                            title="عرض التفاصيل"><i class="fas fa-eye"></i></a>
                                        <?php if ($can_edit): ?>
                                            <a href="javascript:void(0)" class="action-btn edit editOppBtn"
                                                data-id="<?php echo intval($row['id']); ?>"
                                                data-code="<?php echo opp_e($row['opp_code']); ?>"
                                                data-title="<?php echo opp_e($row['title']); ?>"
                                                data-client-id="<?php echo intval($row['client_id']); ?>"
                                                data-source="<?php echo opp_e($row['source']); ?>"
                                                data-sector="<?php echo opp_e($row['sector_category']); ?>"
                                                data-region="<?php echo opp_e($row['state_region']); ?>"
                                                data-revenue-model="<?php echo opp_e($row['revenue_model']); ?>"
                                                data-expected="<?php echo opp_e($row['expected_revenue']); ?>"
                                                data-currency="<?php echo opp_e($row['currency']); ?>"
                                                data-probability="<?php echo opp_e($row['probability']); ?>"
                                                data-stage="<?php echo opp_e($row['stage']); ?>"
                                                data-attractiveness="<?php echo opp_e($row['attractiveness']); ?>"
                                                data-fit="<?php echo opp_e($row['strategy_fit']); ?>"
                                                data-capacity="<?php echo opp_e($row['capacity_summary']); ?>"
                                                data-funding="<?php echo opp_e($row['funding_needed']); ?>"
                                                data-decision="<?php echo opp_e($row['study_decision']); ?>"
                                                data-close="<?php echo opp_e($row['expected_close_date']); ?>"
                                                data-win="<?php echo opp_e($row['win_reason']); ?>"
                                                data-lost="<?php echo opp_e($row['lost_reason']); ?>"
                                                data-review="<?php echo opp_e($row['review_notes']); ?>"
                                                data-notes="<?php echo opp_e($row['notes']); ?>"
                                                title="تعديل"><i class="fas fa-edit"></i></a>
                                        <?php endif; ?>
                                        <?php if ($can_delete): ?>
                                            <a href="?delete_id=<?php echo urlencode($row['id']); ?>&csrf_token=<?php echo urlencode($opp_csrf_token); ?>"
                                                class="action-btn delete"
                                                onclick="return confirm('هل أنت متأكد من حذف هذه الفرصة؟')" title="حذف"><i class="fas fa-trash-alt"></i></a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><strong class="opp-code-cell"><?php echo opp_e($row['opp_code']); ?></strong></td>
                                <td><?php echo opp_e($row['title']); ?></td>
                                <td><?php echo $client_name !== '' ? opp_e($client_name) : '<span class="opp-muted">—</span>'; ?></td>
                                <td><?php echo opp_e($row['sector_category']); ?></td>
                                <td><span class="opp-stage opp-stage-<?php echo $tone; ?>"><?php echo opp_e($row['stage']); ?></span></td>
                                <td class="opp-num"><?php echo opp_e(opp_money($row['expected_revenue'])) . ' ' . opp_e($row['currency']); ?></td>
                                <td class="opp-num"><?php echo opp_e(rtrim(rtrim($row['probability'], '0'), '.')); ?>%</td>
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
        const oppTable = $('#oppTable').DataTable({
            autoWidth: false,
            stateSave: false,
            language: { url: '/ems/assets/i18n/datatables/ar.json' }
        });

        function fillFilterOptions(columnIndex, selectId) {
            const select = $(selectId);
            const values = [];
            oppTable.column(columnIndex).data().each(function (value) {
                const text = $('<div>').html(value).text().trim();
                if (text !== '' && values.indexOf(text) === -1) values.push(text);
            });
            values.sort();
            values.forEach(function (val) {
                select.append('<option value="' + val.replace(/"/g, '&quot;') + '">' + val + '</option>');
            });
        }
        fillFilterOptions(5, '#filterStage');
        fillFilterOptions(4, '#filterSector');

        $('#filterStage').on('change', function () {
            const value = $.fn.dataTable.util.escapeRegex($(this).val());
            oppTable.column(5).search(value ? '^' + value + '$' : '', true, false).draw();
        });
        $('#filterSector').on('change', function () {
            const value = $.fn.dataTable.util.escapeRegex($(this).val());
            oppTable.column(4).search(value ? '^' + value + '$' : '', true, false).draw();
        });
    });

    // ── إظهار/إخفاء الفورم والإحصائيات ──
    const formToggleBtn = $('#toggleForm');
    const oppForm = $('#oppForm');
    const formTitle = $('#formTitle');
    const submitBtnText = $('#submitBtnText');
    const generatedCodeWrapper = $('#generated_code_wrapper');
    const formCancelBtn = $('#oppFormCancelBtn');
    const statsToggleBtn = $('#toggleStats');
    const statsSection = $('#oppStatsSection');

    function setAddMode() { formTitle.text('إضافة فرصة جديدة'); submitBtnText.text('حفظ الفرصة'); generatedCodeWrapper.show(); }
    function setEditMode() { formTitle.text('تعديل الفرصة'); submitBtnText.text('تحديث الفرصة'); generatedCodeWrapper.hide(); }
    function resetForm() { if (!oppForm.length) return; oppForm[0].reset(); $('#opp_id').val(''); setAddMode(); if (window.EmsSelect) EmsSelect.refresh(); }

    function updateFormToggleState(isOpen) {
        if (!formToggleBtn.length) return;
        formToggleBtn.toggleClass('is-active', isOpen);
        formToggleBtn.attr('aria-expanded', isOpen ? 'true' : 'false');
    }
    function updateStatsToggleState(isVisible) {
        if (!statsToggleBtn.length) return;
        statsToggleBtn.toggleClass('is-active', isVisible);
        statsToggleBtn.find('.opp-toggle-stats-text').text('إظهار الإحصائيات');
        const icon = statsToggleBtn.find('i').first();
        icon.toggleClass('fa-chart-pie', isVisible);
        icon.toggleClass('fa-eye', !isVisible);
    }

    setAddMode();
    updateFormToggleState(oppForm.is(':visible'));
    updateStatsToggleState(statsSection.is(':visible'));

    formToggleBtn.on('click', function (e) {
        e.preventDefault();
        if (!oppForm.length) return;
        if (oppForm.is(':visible')) {
            oppForm.stop(true, true).slideUp(250, function () { oppForm.removeClass('allforms-visible'); resetForm(); updateFormToggleState(false); });
        } else {
            resetForm();
            oppForm.addClass('allforms-visible').hide();
            oppForm.stop(true, true).slideDown(250, function () { updateFormToggleState(true); });
        }
    });
    formCancelBtn.on('click', function () {
        if (!oppForm.length || !oppForm.is(':visible')) return;
        oppForm.stop(true, true).slideUp(250, function () { oppForm.removeClass('allforms-visible'); resetForm(); updateFormToggleState(false); });
    });
    statsToggleBtn.on('click', function (e) {
        e.preventDefault();
        if (!statsSection.length) return;
        if (statsSection.is(':visible')) {
            statsSection.stop(true, true).slideUp(250, function () { statsSection.addClass('opp-hidden'); updateStatsToggleState(false); });
        } else {
            statsSection.removeClass('opp-hidden').hide();
            statsSection.stop(true, true).slideDown(250, function () { updateStatsToggleState(true); });
        }
    });

    // ── تعبئة الفورم للتعديل ──
    function fillOppForm(d) {
        $('#opp_id').val(d.id);
        $('#opp_code').val(d.code);
        $('#title').val(d.title);
        $('#client_id').val(d.clientId || '');
        $('#source').val(d.source || '');
        $('#sector_category').val(d.sector || '');
        $('#state_region').val(d.region || '');
        $('#revenue_model').val(d.revenueModel || '');
        $('#expected_revenue').val(d.expected || '');
        $('#currency').val(d.currency || 'USD');
        $('#probability').val(d.probability || '');
        $('#stage').val(d.stage || 'جديدة');
        $('#attractiveness').val(d.attractiveness || '');
        $('#strategy_fit').val(d.fit || '');
        $('#capacity_summary').val(d.capacity || '');
        $('#funding_needed').val(d.funding || '');
        $('#study_decision').val(d.decision || '');
        $('#expected_close_date').val(d.close || '');
        $('#win_reason').val(d.win || '');
        $('#lost_reason').val(d.lost || '');
        $('#review_notes').val(d.review || '');
        $('#notes').val(d.notes || '');
        if (window.EmsSelect) EmsSelect.refresh();
        setEditMode();
        if (!oppForm.is(':visible')) {
            oppForm.addClass('allforms-visible').hide();
            oppForm.stop(true, true).slideDown(250, function () { updateFormToggleState(true); });
        } else { updateFormToggleState(true); }
        $('html, body').animate({ scrollTop: $('#oppForm').offset().top - 100 }, 500);
    }

    $(document).on('click', '.editOppBtn', function () {
        fillOppForm({
            id: $(this).data('id'), code: $(this).data('code'), title: $(this).data('title'),
            clientId: $(this).data('client-id'), source: $(this).data('source'), sector: $(this).data('sector'),
            region: $(this).data('region'), revenueModel: $(this).data('revenue-model'), expected: $(this).data('expected'),
            currency: $(this).data('currency'), probability: $(this).data('probability'), stage: $(this).data('stage'),
            attractiveness: $(this).data('attractiveness'), fit: $(this).data('fit'), capacity: $(this).data('capacity'),
            funding: $(this).data('funding'), decision: $(this).data('decision'), close: $(this).data('close'),
            win: $(this).data('win'), lost: $(this).data('lost'), review: $(this).data('review'), notes: $(this).data('notes')
        });
    });

    // ── عرض التفاصيل عبر EmsDetailsModal الموحّد ──
    $(document).on('click', '.viewOppBtn', function () {
        const d = $(this).data();
        const stage = String(d.stage || '');
        let tone = 'inactive';
        if (stage === 'فوز') tone = 'active';
        else if (stage === 'خسارة' || stage === 'مستبعدة') tone = 'inactive';
        else tone = 'pending';

        const fields = [
            { label: 'كود الفرصة', value: d.code, icon: 'fas fa-barcode' },
            { label: 'عنوان الفرصة', value: d.title, icon: 'fas fa-lightbulb', size: 'lg' },
            { label: 'العميل', value: d.client || '—', icon: 'fas fa-user-tie' },
            { label: 'المصدر', value: d.source || '—', icon: 'fas fa-signs-post' },
            { label: 'المرحلة', value: stage, icon: 'fas fa-layer-group', type: 'status', tone: tone },
            { label: 'نموذج الإيراد', value: d.revenueModel || '—', icon: 'fas fa-coins' },
            { label: 'القطاع', value: d.sector || '—', icon: 'fas fa-industry' },
            { label: 'الولاية/الموقع', value: d.region || '—', icon: 'fas fa-map-location-dot' },
            { label: 'القيمة التقديرية', value: (d.expected || '0.00') + ' ' + (d.currency || ''), icon: 'fas fa-money-bill-wave', size: 'lg' },
            { label: 'احتمال الفوز', value: (d.probability || 0) + '%', icon: 'fas fa-percent' },
            { label: 'تاريخ الإغلاق المتوقع', value: d.close || '—', icon: 'fas fa-calendar-day' },
            { label: 'الجاذبية', value: d.attractiveness || '—', icon: 'fas fa-fire' },
            { label: 'التوافق الاستراتيجي', value: d.fit || '—', icon: 'fas fa-bullseye' },
            { label: 'قرار الدراسة', value: d.decision || '—', icon: 'fas fa-clipboard-check' },
            { label: 'الحاجة للتمويل', value: d.funding || '0.00', icon: 'fas fa-hand-holding-dollar' },
            { label: 'المتطلبات المبدئية', value: d.capacity || '—', icon: 'fas fa-boxes-stacked', size: 'lg' },
            { label: 'سبب الفوز', value: d.win || '—', icon: 'fas fa-trophy' },
            { label: 'سبب الخسارة', value: d.lost || '—', icon: 'fas fa-circle-xmark' },
            { label: 'ملاحظات المراجعة', value: d.review || '—', icon: 'fas fa-clipboard-list', size: 'lg' },
            { label: 'ملاحظات عامة', value: d.notes || '—', icon: 'fas fa-note-sticky', size: 'lg' },
            { label: 'أضيفت بواسطة', value: d.created || '—', icon: 'fas fa-user-plus' }
        ];

        const actions = [];
        <?php if ($can_edit): ?>
            actions.push({ label: 'تعديل الفرصة', icon: 'fas fa-edit', variant: 'primary', onClick: function () {
                EmsDetailsModal.close();
                fillOppForm({
                    id: d.id, code: d.code, title: d.title, clientId: '', source: d.source, sector: d.sector,
                    region: d.region, revenueModel: '', expected: (d.expected || '').replace(/,/g, ''), currency: d.currency,
                    probability: d.probability, stage: d.stage, attractiveness: d.attractiveness, fit: d.fit,
                    capacity: d.capacity, funding: (d.funding || '').replace(/,/g, ''), decision: d.decision, close: d.close,
                    win: d.win, lost: d.lost, review: d.review, notes: d.notes
                });
            }});
        <?php endif; ?>
        actions.push({ label: 'إغلاق', icon: 'fas fa-times', variant: 'secondary', close: true });

        EmsDetailsModal.open({ title: 'تفاصيل الفرصة', icon: 'fas fa-lightbulb', fields: fields, actions: actions });
    });
</script>

<style>
    .opp-main .stats-grid { display: grid; grid-template-columns: repeat(4, minmax(170px, 1fr)); gap: 12px; }
    .opp-main .stats-section {
        border: 1px solid var(--bdr); border-radius: var(--rl);
        background: linear-gradient(180deg, rgba(255,255,255,.95) 0%, var(--s2) 100%);
        box-shadow: var(--sh); padding: 14px; margin-bottom: 14px;
    }
    .opp-main .stats-card { background: #eee; border: 1px solid #aaa; border-radius: 35px; padding: 18px; box-shadow: 0 2px 8px rgba(26,18,8,.07); position: relative; overflow: hidden; }
    .opp-main .stats-card .stats-icon { width: 55px; height: 55px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.3rem; margin-bottom: 10px; float: left; margin-top: 15px; border: 1px solid #999; background:#fff; color:#000; }
    .opp-main .stats-card .stats-title { color: #555; font-size: .92rem; font-weight: 700; margin-top: 5px; line-height: 1.3; }
    .opp-main .stats-card .stats-value { color: #222; line-height: 1; font-weight: 900; font-variant-numeric: tabular-nums; margin-top: 10px; font-size: 30px; }
    @media (max-width: 900px) { .opp-main .stats-grid { grid-template-columns: repeat(2, minmax(150px,1fr)); } }
    @media (max-width: 560px) { .opp-main .stats-grid { grid-template-columns: 1fr; } }

    .opp-main .opp-hidden { display: none; }
    .opp-main .opp-col-full { grid-column: 1 / -1; }
    .opp-main .table-container { overflow-x: auto; }
    #oppTable.opp-table-nowrap, #oppTable.opp-table-nowrap th, #oppTable.opp-table-nowrap td { white-space: nowrap; }
    #oppTable .action-btns { flex-wrap: nowrap; white-space: nowrap; }
    .opp-main .opp-num { font-variant-numeric: tabular-nums; font-weight: 700; }
    .opp-main .opp-muted { color: #999; }

    /* شارات المراحل */
    .opp-stage { display: inline-block; padding: 3px 12px; border-radius: 999px; font-size: .82rem; font-weight: 800; border: 1px solid transparent; }
    .opp-stage-new { background: rgba(59,130,246,.12); color: #1d4ed8; border-color: rgba(59,130,246,.28); }
    .opp-stage-study { background: rgba(139,92,246,.12); color: #6d28d9; border-color: rgba(139,92,246,.28); }
    .opp-stage-qualified { background: rgba(14,165,233,.12); color: #0369a1; border-color: rgba(14,165,233,.28); }
    .opp-stage-quoted { background: rgba(234,179,8,.15); color: #a16207; border-color: rgba(234,179,8,.30); }
    .opp-stage-negotiation { background: rgba(249,115,22,.14); color: #c2410c; border-color: rgba(249,115,22,.30); }
    .opp-stage-won { background: rgba(34,197,94,.15); color: #15803d; border-color: rgba(34,197,94,.30); }
    .opp-stage-lost { background: rgba(220,38,38,.12); color: #b91c1c; border-color: rgba(220,38,38,.28); }
    .opp-stage-excluded { background: rgba(107,114,128,.14); color: #4b5563; border-color: rgba(107,114,128,.30); }
</style>

</body>

</html>
