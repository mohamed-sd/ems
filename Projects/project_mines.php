<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}

include '../config.php';
require_once '../includes/permissions_helper.php';

if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
}

if (!function_exists('project_mines_fix_mojibake_output')) {
    function project_mines_fix_mojibake_output($buffer)
    {
        $map = array(
            'Ø§' => 'ا', 'Ø¨' => 'ب', 'Øª' => 'ت', 'Ø«' => 'ث', 'Ø¬' => 'ج', 'Ø­' => 'ح',
            'Ø®' => 'خ', 'Ø¯' => 'د', 'Ø°' => 'ذ', 'Ø±' => 'ر', 'Ø²' => 'ز', 'Ø³' => 'س',
            'Ø´' => 'ش', 'Øµ' => 'ص', 'Ø¶' => 'ض', 'Ø·' => 'ط', 'Ø¸' => 'ظ', 'Ø¹' => 'ع',
            'Øº' => 'غ', 'Ù' => 'ف', 'Ù‚' => 'ق', 'Ùƒ' => 'ك', 'Ù„' => 'ل', 'Ù…' => 'م',
            'Ù†' => 'ن', 'Ù‡' => 'ه', 'Ùˆ' => 'و', 'ÙŠ' => 'ي', 'Ù‰' => 'ى', 'Ø©' => 'ة',
            'Ø¡' => 'ء', 'Ø£' => 'أ', 'Ø¥' => 'إ', 'Ø¢' => 'آ', 'Ø¤' => 'ؤ', 'Ø¦' => 'ئ',
            'ØŒ' => '،', 'Ø›' => '؛', 'ØŸ' => '؟', 'âœ…' => '✅', 'âŒ' => '❌', 'â¸' => '⏸',
            'ðŸ”' => '🔐', 'ðŸ‘‹' => '👋', 'ðŸš€' => '🚀', 'ðŸ†' => '🏆'
        );

        return strtr($buffer, $map);
    }
}

ob_start('project_mines_fix_mojibake_output');

if (!function_exists('project_mines_table_has_column')) {
    function project_mines_table_has_column($conn, $tableName, $columnName)
    {
        $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $tableName);
        $safeCol = preg_replace('/[^a-zA-Z0-9_]/', '', $columnName);
        $sql = "SHOW COLUMNS FROM " . $safeTable . " LIKE '" . mysqli_real_escape_string($conn, $safeCol) . "'";
        $res = @mysqli_query($conn, $sql);

        return $res && mysqli_num_rows($res) > 0;
    }
}

if (!function_exists('project_mines_redirect_with_msg')) {
    function project_mines_redirect_with_msg($project_id, $msg)
    {
        header('Location: project_mines.php?project_id=' . intval($project_id) . '&msg=' . urlencode($msg));
        exit();
    }
}

// 🔐 التحقق من صلاحيات المستخدم
$page_permissions = check_page_permissions($conn, 'Projects/project_mines.php');
$can_view = $page_permissions['can_view'];
$can_add = $page_permissions['can_add'];
$can_edit = $page_permissions['can_edit'];
$can_delete = $page_permissions['can_delete'];
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;

if (!$can_view) {
    header("Location: ../login.php?msg=لا+توجد+صلاحية+عرض+المناجم+❌");
    exit();
}

if (empty($_SESSION['project_mines_csrf_token'])) {
    $_SESSION['project_mines_csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
}
$project_mines_csrf_token = $_SESSION['project_mines_csrf_token'];

$mines_has_is_deleted = project_mines_table_has_column($conn, 'mines', 'is_deleted');
$mines_has_deleted_at = project_mines_table_has_column($conn, 'mines', 'deleted_at');
$mines_has_deleted_by = project_mines_table_has_column($conn, 'mines', 'deleted_by');
$mines_has_company_id = project_mines_table_has_column($conn, 'mines', 'company_id');

if (!$mines_has_is_deleted || !$mines_has_deleted_at || !$mines_has_deleted_by) {
    $alter_parts = array();
    if (!$mines_has_is_deleted) {
        $alter_parts[] = "ADD COLUMN is_deleted TINYINT(1) NOT NULL DEFAULT 0";
    }
    if (!$mines_has_deleted_at) {
        $alter_parts[] = "ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL";
    }
    if (!$mines_has_deleted_by) {
        $alter_parts[] = "ADD COLUMN deleted_by INT(11) NULL DEFAULT NULL";
    }
    if (!empty($alter_parts)) {
        @mysqli_query($conn, "ALTER TABLE mines " . implode(', ', $alter_parts));
    }

    $mines_has_is_deleted = project_mines_table_has_column($conn, 'mines', 'is_deleted');
    $mines_has_deleted_at = project_mines_table_has_column($conn, 'mines', 'deleted_at');
    $mines_has_deleted_by = project_mines_table_has_column($conn, 'mines', 'deleted_by');
}

$project_has_is_deleted = project_mines_table_has_column($conn, 'project', 'is_deleted');
$project_has_deleted_at = project_mines_table_has_column($conn, 'project', 'deleted_at');
$project_has_company_id = project_mines_table_has_column($conn, 'project', 'company_id');

$project_not_deleted_sql = '1=1';
if ($project_has_is_deleted) {
    $project_not_deleted_sql = 'is_deleted = 0';
} elseif ($project_has_deleted_at) {
    $project_not_deleted_sql = 'deleted_at IS NULL';
}

$mines_not_deleted_sql = '1=1';
if ($mines_has_is_deleted) {
    $mines_not_deleted_sql = 'is_deleted = 0';
} elseif ($mines_has_deleted_at) {
    $mines_not_deleted_sql = 'deleted_at IS NULL';
}

$mines_not_deleted_m_sql = '1=1';
if ($mines_has_is_deleted) {
    $mines_not_deleted_m_sql = 'm.is_deleted = 0';
} elseif ($mines_has_deleted_at) {
    $mines_not_deleted_m_sql = 'm.deleted_at IS NULL';
}

$mine_scope_sql = '';
if ($mines_has_company_id && $company_id > 0) {
    $mine_scope_sql = "m.company_id = $company_id";
} elseif ($project_has_company_id && $company_id > 0) {
    $mine_scope_sql = "EXISTS (SELECT 1 FROM project p_scope WHERE p_scope.id = m.project_id AND p_scope.company_id = $company_id)";
} elseif ($company_id > 0) {
    $mine_scope_sql = "EXISTS (SELECT 1 FROM project p_scope INNER JOIN users u_scope ON u_scope.id = p_scope.created_by WHERE p_scope.id = m.project_id AND u_scope.company_id = $company_id)";
}

if ($mine_scope_sql === '') {
    $mine_scope_sql = '1=1';
}

// الحصول على معرف المشروع من URL
$project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;

if ($project_id <= 0) {
    header("Location: projects.php");
    exit();
}

// جلب بيانات المشروع
$project_query = "SELECT * FROM project WHERE id = $project_id AND $project_not_deleted_sql LIMIT 1";
$project_result = mysqli_query($conn, $project_query);
$project = mysqli_fetch_assoc($project_result);

if (!$project) {
    die("المشروع غير موجود");
}

// معالجة إضافة/تعديل منجم عبر POST (بدون AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['mine_name'])) {
    $posted_csrf = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if (empty($posted_csrf) || !hash_equals($project_mines_csrf_token, $posted_csrf)) {
        project_mines_redirect_with_msg($project_id, 'جلسة النموذج غير صالحة، يرجى إعادة المحاولة ❌');
    }

    $mine_id = isset($_POST['mine_id']) ? intval($_POST['mine_id']) : 0;

    if ($mine_id > 0 && !$can_edit) {
        header("Location: project_mines.php?project_id=$project_id&msg=لا+توجد+صلاحية+تعديل+المناجم+❌");
        exit();
    } elseif ($mine_id <= 0 && !$can_add) {
        header("Location: project_mines.php?project_id=$project_id&msg=لا+توجد+صلاحية+إضافة+مناجم+جديدة+❌");
        exit();
    }

    $mine_code = mysqli_real_escape_string($conn, trim($_POST['mine_code']));
    $mine_name = mysqli_real_escape_string($conn, trim($_POST['mine_name']));
    $manager_name = mysqli_real_escape_string($conn, trim($_POST['manager_name']));
    $mineral_type = mysqli_real_escape_string($conn, trim($_POST['mineral_type']));
    $mine_type = mysqli_real_escape_string($conn, $_POST['mine_type']);
    $mine_type_other = mysqli_real_escape_string($conn, trim($_POST['mine_type_other']));
    $ownership_type = mysqli_real_escape_string($conn, $_POST['ownership_type']);
    $ownership_type_other = mysqli_real_escape_string($conn, trim($_POST['ownership_type_other']));
    $mine_area = !empty($_POST['mine_area']) ? floatval($_POST['mine_area']) : null;
    $mine_area_unit = mysqli_real_escape_string($conn, $_POST['mine_area_unit']);
    $mining_depth = !empty($_POST['mining_depth']) ? floatval($_POST['mining_depth']) : null;
    $contract_nature = mysqli_real_escape_string($conn, $_POST['contract_nature']);
    $status = intval($_POST['status']);
    $notes = mysqli_real_escape_string($conn, trim($_POST['notes']));

    $dup_name_sql = "SELECT m.id FROM mines m WHERE m.mine_name = '$mine_name' AND $mines_not_deleted_m_sql AND $mine_scope_sql";
    if ($mine_id > 0) {
        $dup_name_sql .= " AND m.id != $mine_id";
    }
    $dup_name_sql .= " LIMIT 1";
    $dup_name_res = mysqli_query($conn, $dup_name_sql);
    if ($dup_name_res && mysqli_num_rows($dup_name_res) > 0) {
        header("Location: project_mines.php?project_id=$project_id&msg=اسم+المنجم+موجود+مسبقاً+داخل+شركتك+❌");
        exit();
    }

    if ($mine_id > 0) {
        // تعديل منجم موجود
        $mine_area_value = $mine_area !== null ? $mine_area : "NULL";
        $mining_depth_value = $mining_depth !== null ? $mining_depth : "NULL";

        $update_query = "UPDATE mines SET 
            mine_code = '$mine_code',
            mine_name = '$mine_name',
            manager_name = '$manager_name',
            mineral_type = '$mineral_type',
            mine_type = '$mine_type',
            mine_type_other = '$mine_type_other',
            ownership_type = '$ownership_type',
            ownership_type_other = '$ownership_type_other',
            mine_area = $mine_area_value,
            mine_area_unit = '$mine_area_unit',
            mining_depth = $mining_depth_value,
            contract_nature = '$contract_nature',
            status = $status,
            notes = '$notes'
            WHERE id = $mine_id AND project_id = $project_id AND $mines_not_deleted_sql";

        if ($mines_has_company_id && $company_id > 0) {
            $update_query = "UPDATE mines SET 
            mine_code = '$mine_code',
            mine_name = '$mine_name',
            manager_name = '$manager_name',
            mineral_type = '$mineral_type',
            mine_type = '$mine_type',
            mine_type_other = '$mine_type_other',
            ownership_type = '$ownership_type',
            ownership_type_other = '$ownership_type_other',
            mine_area = $mine_area_value,
            mine_area_unit = '$mine_area_unit',
            mining_depth = $mining_depth_value,
            contract_nature = '$contract_nature',
            status = $status,
            notes = '$notes',
            company_id = $company_id
            WHERE id = $mine_id AND project_id = $project_id AND $mines_not_deleted_sql";
        }
        
        if (mysqli_query($conn, $update_query)) {
            header("Location: project_mines.php?project_id=$project_id&msg=تم+تعديل+المنجم+بنجاح+✅");
            exit();
        } else {
            header("Location: project_mines.php?project_id=$project_id&msg=حدث+خطأ+أثناء+التعديل+❌");
            exit();
        }
    } else {
        // إضافة منجم جديد
        $mine_area_value = $mine_area !== null ? $mine_area : "NULL";
        $mining_depth_value = $mining_depth !== null ? $mining_depth : "NULL";
        $created_by = $_SESSION['user']['id'];

        $insert_query = "INSERT INTO mines 
            (project_id, mine_code, mine_name, manager_name, mineral_type, mine_type, mine_type_other, 
             ownership_type, ownership_type_other, mine_area, mine_area_unit, mining_depth, contract_nature, 
             status, notes, created_by) 
            VALUES 
            ($project_id, '$mine_code', '$mine_name', '$manager_name', '$mineral_type', '$mine_type', 
             '$mine_type_other', '$ownership_type', '$ownership_type_other', $mine_area_value, '$mine_area_unit', 
             $mining_depth_value, '$contract_nature', $status, '$notes', $created_by)";

        if ($mines_has_company_id && $company_id > 0) {
            $insert_query = "INSERT INTO mines 
            (company_id, project_id, mine_code, mine_name, manager_name, mineral_type, mine_type, mine_type_other, 
             ownership_type, ownership_type_other, mine_area, mine_area_unit, mining_depth, contract_nature, 
             status, notes, created_by) 
            VALUES 
            ($company_id, $project_id, '$mine_code', '$mine_name', '$manager_name', '$mineral_type', '$mine_type', 
             '$mine_type_other', '$ownership_type', '$ownership_type_other', $mine_area_value, '$mine_area_unit', 
             $mining_depth_value, '$contract_nature', $status, '$notes', $created_by)";
        }

        if (mysqli_query($conn, $insert_query)) {
            header("Location: project_mines.php?project_id=$project_id&msg=تم+إضافة+المنجم+بنجاح+✅");
            exit();
        } else {
            header("Location: project_mines.php?project_id=$project_id&msg=حدث+خطأ+أثناء+الإضافة+❌");
            exit();
        }
    }
}

// حذف منجم
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    if (!$can_delete) {
        header("Location: project_mines.php?project_id=$project_id&msg=لا+توجد+صلاحية+حذف+المناجم+❌");
        exit();
    }

    $delete_csrf = isset($_GET['csrf_token']) ? $_GET['csrf_token'] : '';
    if (empty($delete_csrf) || !hash_equals($project_mines_csrf_token, $delete_csrf)) {
        project_mines_redirect_with_msg($project_id, 'جلسة الحذف غير صالحة، يرجى إعادة المحاولة ❌');
    }

    $mine_id = intval($_GET['delete']);
    if (!$mines_has_is_deleted && !$mines_has_deleted_at) {
        project_mines_redirect_with_msg($project_id, 'تعذر تفعيل الحذف الناعم للمناجم حالياً ❌');
    }

    $delete_set = array("status = 0");
    if ($mines_has_is_deleted) {
        $delete_set[] = "is_deleted = 1";
    }
    if ($mines_has_deleted_at) {
        $delete_set[] = "deleted_at = NOW()";
    }
    if ($mines_has_deleted_by) {
        $deleted_by = intval($_SESSION['user']['id']);
        $delete_set[] = "deleted_by = $deleted_by";
    }
    $delete_query = "UPDATE mines SET " . implode(', ', $delete_set) . " WHERE id = $mine_id AND project_id = $project_id AND $mines_not_deleted_sql";

    if (mysqli_query($conn, $delete_query)) {
        project_mines_redirect_with_msg($project_id, 'تم حذف المنجم بنجاح ✅');
    } else {
        project_mines_redirect_with_msg($project_id, 'حدث خطأ أثناء الحذف ❌');
    }
    exit();
}

$page_title = "المناجم - " . $project['name'];
include '../inheader.php';
// include '../insidebar.php';
?>

<link rel="stylesheet" href="../assets/css/main_admin_style.css" />

<style>
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(3, 16, 39, 0.6);
        backdrop-filter: blur(4px);
    }

    .modal-content {
        background-color: var(--surface);
        margin: 2% auto;
        padding: 0;
        border-radius: var(--radius-lg);
        width: 92%;
        max-width: 900px;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: var(--shadow-lg);
    }

    .modal-header {
        background: linear-gradient(135deg, var(--navy), var(--navy-l));
        color: #fff;
        padding: 16px 20px;
        border-radius: var(--radius-lg) var(--radius-lg) 0 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .modal-header h3,
    .modal-header h5 {
        margin: 0;
        font-size: 1.1rem;
        font-weight: 800;
    }

    .close,
    .close-modal {
        background: rgba(255, 255, 255, 0.2);
        border: none;
        color: #fff;
        font-size: 26px;
        font-weight: bold;
        cursor: pointer;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: transform var(--ease), background var(--ease);
    }

    .close:hover,
    .close-modal:hover {
        background: rgba(255, 255, 255, 0.3);
        transform: rotate(90deg);
    }

    .modal-body {
        padding: 20px;
    }

    .form-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 14px;
        margin-bottom: 14px;
    }

    .form-group {
        display: flex;
        flex-direction: column;
    }

    .form-group label {
        font-weight: 700;
        margin-bottom: 6px;
        color: var(--sub);
        font-size: .9rem;
    }

    .form-group label .required { color: var(--red); }

    .form-group input,
    .form-group select,
    .form-group textarea {
        padding: 10px 12px;
        border: 1.5px solid var(--border);
        border-radius: var(--radius);
        font-size: .95rem;
        transition: border-color var(--ease), box-shadow var(--ease);
        font-family: 'Cairo', sans-serif;
        background: var(--bg);
    }

    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: var(--gold);
        box-shadow: 0 0 0 3px rgba(232,184,0,.14);
        background: #fff;
    }

    .form-group textarea {
        resize: vertical;
        min-height: 100px;
    }

    .btn-submit {
        background: linear-gradient(135deg, var(--navy), var(--navy-l));
        color: #fff;
        padding: 12px 20px;
        border: none;
        border-radius: var(--radius);
        cursor: pointer;
        font-size: 1rem;
        font-weight: 800;
        width: 100%;
        margin-top: 8px;
        transition: transform var(--ease), box-shadow var(--ease);
    }

    .btn-submit:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
    }

    .view-modal-body {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 12px;
    }

    .view-item {
        padding: 12px 14px;
        background: var(--bg);
        border-radius: var(--radius);
        border-right: 3px solid var(--gold);
        box-shadow: var(--shadow-sm);
    }

    .view-item-label {
        font-size: 11px;
        font-weight: 700;
        color: var(--sub);
        margin-bottom: 4px;
        display: flex;
        align-items: center;
        gap: 6px;
        letter-spacing: 0.3px;
    }

    .view-item-value {
        font-size: .95rem;
        font-weight: 800;
        color: var(--navy);
        word-break: break-word;
    }

    .conditional-field { display: none; }

    .alert {
        padding: 12px 16px;
        border-radius: var(--radius);
        margin-bottom: 14px;
        font-weight: 700;
        box-shadow: var(--shadow-sm);
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .alert-success {
        background: var(--green-soft);
        color: var(--green);
        border-right: 4px solid var(--green);
    }

    .alert-error {
        background: var(--red-soft);
        color: var(--red);
        border-right: 4px solid var(--red);
    }

    .modal-footer .action-btn {
        width: auto;
        height: auto;
        padding: 8px 14px;
        border-radius: 10px;
        gap: 6px;
    }
</style>

<div class="main">
    <div class="page-header">
        <h1 class="page-title">
            <div class="title-icon"><i class="fas fa-mountain"></i></div>
            إدارة المناجم - <?php echo htmlspecialchars($project['name']); ?>
        </h1>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <a href="projects.php" class="back-btn">
                <i class="fas fa-arrow-right"></i> رجوع
            </a>
            <?php if ($can_add): ?>
            <a href="javascript:void(0)" id="toggleForm" class="add-btn">
                <i class="fas fa-plus-circle"></i> إضافة منجم جديد
            </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($_GET['msg'])):
        $isSuccess = strpos($_GET['msg'], '✅') !== false;
    ?>
        <div class="success-message <?= $isSuccess ? 'is-success' : 'is-error' ?>">
            <i class="fas <?= $isSuccess ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?php echo htmlspecialchars($_GET['msg']); ?>
        </div>
    <?php endif; ?>

    <!-- فورم إضافة / تعديل منجم -->
    <?php if ($can_add || $can_edit): ?>
    <form id="mineForm" action="" method="post" style="display:none; margin-bottom:20px;">
        <div class="card shadow-sm">
            <div class="card-header">
                <h5><i class="fas fa-edit"></i> <span id="formTitle">إضافة منجم جديد</span></h5>
            </div>
            <div class="card-body">
                <input type="hidden" name="mine_id" id="mine_id" value="">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($project_mines_csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                <div class="form-grid">
                    <div>
                        <label><i class="fas fa-barcode"></i> كود المنجم *</label>
                        <input type="text" name="mine_code" id="mine_code" placeholder="مثال: MINE-001" required />
                    </div>
                    <div>
                        <label><i class="fas fa-mountain"></i> اسم المنجم *</label>
                        <input type="text" name="mine_name" id="mine_name" placeholder="أدخل اسم المنجم" required />
                    </div>
                    <div>
                        <label><i class="fas fa-user-tie"></i> اسم مدير المنجم</label>
                        <input type="text" name="manager_name" id="manager_name" placeholder="أدخل اسم المدير" />
                    </div>
                    <div>
                        <label><i class="fas fa-gem"></i> نوع المعدن</label>
                        <input type="text" name="mineral_type" id="mineral_type" placeholder="مثال: ذهب، فضة، نحاس" />
                    </div>
                    <div>
                        <label><i class="fas fa-industry"></i> نوع المنجم *</label>
                        <select name="mine_type" id="mine_type" required onchange="toggleOtherField('mine_type')">
                            <option value="">-- اختر --</option>
                            <option value="حفرة مفتوحة">حفرة مفتوحة</option>
                            <option value="تحت أرضي">تحت أرضي</option>
                            <option value="آبار">آبار</option>
                            <option value="مهجور">مهجور</option>
                            <option value="مجمع معالجة/تركيز">مجمع معالجة/تركيز</option>
                            <option value="موقع تخزين/مستودع">موقع تخزين/مستودع</option>
                            <option value="أخرى">أخرى</option>
                        </select>
                    </div>
                    <div id="mine_type_other_div" style="display: none;">
                        <label><i class="fas fa-info-circle"></i> تفاصيل نوع المنجم</label>
                        <input type="text" name="mine_type_other" id="mine_type_other" />
                    </div>
                    <div>
                        <label><i class="fas fa-building"></i> نوع الملكية *</label>
                        <select name="ownership_type" id="ownership_type" required onchange="toggleOtherField('ownership_type')">
                            <option value="">-- اختر --</option>
                            <option value="تعدين أهلي/تقليدي">تعدين أهلي/تقليدي</option>
                            <option value="شركة سودانية خاصة">شركة سودانية خاصة</option>
                            <option value="شركة حكومية/قطاع عام">شركة حكومية/قطاع عام</option>
                            <option value="شركة أجنبية">شركة أجنبية</option>
                            <option value="مشروع مشترك (سوداني-أجنبي)">مشروع مشترك (سوداني-أجنبي)</option>
                            <option value="أخرى">أخرى</option>
                        </select>
                    </div>
                    <div id="ownership_type_other_div" style="display: none;">
                        <label><i class="fas fa-info-circle"></i> تفاصيل نوع الملكية</label>
                        <input type="text" name="ownership_type_other" id="ownership_type_other" />
                    </div>
                    <div>
                        <label><i class="fas fa-ruler-combined"></i> مساحة المنجم</label>
                        <input type="number" step="0.01" name="mine_area" id="mine_area" placeholder="0.00" />
                    </div>
                    <div>
                        <label><i class="fas fa-arrows-alt-v"></i> وحدة قياس المساحة</label>
                        <select name="mine_area_unit" id="mine_area_unit">
                            <option value="هكتار">هكتار</option>
                            <option value="كم²">كم²</option>
                        </select>
                    </div>
                    <div>
                        <label><i class="fas fa-height"></i> عمق التعدين (متر)</label>
                        <input type="number" step="0.01" name="mining_depth" id="mining_depth" placeholder="0.00" />
                    </div>
                    <div>
                        <label><i class="fas fa-file-signature"></i> طبيعة التعاقد</label>
                        <select name="contract_nature" id="contract_nature">
                            <option value="">-- اختر --</option>
                            <option value="موظف مباشر لدى المالك">موظف مباشر لدى المالك</option>
                            <option value="مقاول/شركة مقاولات">مقاول/شركة مقاولات</option>
                        </select>
                    </div>
                    <div>
                        <label><i class="fas fa-toggle-on"></i> الحالة *</label>
                        <select name="status" id="status" required>
                            <option value="1">نشط ✅</option>
                            <option value="0">غير نشط</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label><i class="fas fa-sticky-note"></i> ملاحظات إضافية</label>
                    <textarea name="notes" id="notes" placeholder="أي معلومات إضافية عن المنجم..." style="width: 100%; padding: 10px; border: 1.5px solid var(--border); border-radius: var(--radius); font-family: 'Cairo', sans-serif; resize: vertical; min-height: 100px;"></textarea>
                </div>
                <div style="display: flex; gap: 10px; margin-top: 15px;">
                    <button type="submit" style="background: linear-gradient(135deg, var(--navy), var(--navy-l)); color: #fff; padding: 12px 20px; border: none; border-radius: var(--radius); cursor: pointer; font-size: 1rem; font-weight: 800; flex: 1;">
                        <i class="fas fa-save"></i> حفظ البيانات
                    </button>
                    <button type="button" onclick="toggleForm()" style="background: #dc3545; color: #fff; padding: 12px 20px; border: none; border-radius: var(--radius); cursor: pointer; font-size: 1rem; font-weight: 800;">
                        <i class="fas fa-times"></i> إلغاء
                    </button>
                </div>
            </div>
        </div>
    </form>
    <?php endif; ?>
            <div class="card-header">
                <h5><i class="fas fa-list-alt"></i> قائمة المناجم</h5>
            </div>
            <div class="card-body" style="overflow-x: auto;">
                <table id="minesTable" class="display nowrap" style="width:100%; margin-top: 10px;">
          <thead>
                <tr>
                    <th>#</th>
                    <th>كود المنجم</th>
                    <th>اسم المنجم</th>
                    <th>المدير</th>
                    <th>المعدن</th>
                    <th>نوع المنجم</th>
                    <th>المساحة</th>
                    <th>العمق (م)</th>
                    <th> عدد العقود </th>
                    <th> العقود </th>
                    <th>الحالة</th>
                    <th>الإجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $mines_query = "SELECT m.*, 
                               (SELECT COUNT(*) FROM contracts c WHERE c.mine_id = m.id) AS contract_count
                               FROM mines m
                               WHERE m.project_id = $project_id AND " . str_replace('is_deleted', 'm.is_deleted', str_replace('deleted_at', 'm.deleted_at', $mines_not_deleted_sql)) . "
                               ORDER BY m.created_at DESC";
                $mines_result = mysqli_query($conn, $mines_query);
                $counter = 1;

                while ($mine = mysqli_fetch_assoc($mines_result)) {
                    $status_badge = $mine['status'] == 1 ?
                        '<span class="status-active">نشط</span>' :
                        '<span class="status-inactive">غير نشط</span>';

                    $area_display = $mine['mine_area'] ?
                        number_format($mine['mine_area'], 2) . ' ' . $mine['mine_area_unit'] :
                        '-';

                    $depth_display = $mine['mining_depth'] ?
                        number_format($mine['mining_depth'], 2) . ' م' :
                        '-';

                    echo "<tr>";
                    echo "<td>{$counter}</td>";
                    echo "<td>{$mine['mine_code']}</td>";
                    echo "<td>{$mine['mine_name']}</td>";
                    echo "<td>" . ($mine['manager_name'] ?: '-') . "</td>";
                    echo "<td>" . ($mine['mineral_type'] ?: '-') . "</td>";
                    echo "<td>{$mine['mine_type']}</td>";
                    echo "<td>{$area_display}</td>";
                    echo "<td>{$depth_display}</td>";
                    $contracts_count = isset($mine['contract_count']) ? intval($mine['contract_count']) : 0;
                    echo "<td>{$contracts_count}</td>"; 
                    echo "<td> 
                     <a href='../Contracts/contracts.php?id=" . $mine['id'] . "' 
                               class='action-btn contracts'
                               title='عرض عقود المنجم'>
                               <i class='fas fa-file-contract'></i>
                            </a>
                    </td>";
                    echo "<td>{$status_badge}</td>";
                    echo "<td>
                            <div class='action-btns'>
                                <a href='javascript:void(0)' class='action-btn view' onclick='openViewModal(" . json_encode($mine) . ")' title='عرض'>
                                    <i class='fas fa-eye'></i>
                                </a>";

                    if ($can_edit) {
                        echo "<a href='javascript:void(0)' class='action-btn edit' onclick='editMine(" . json_encode($mine) . ")' title='تعديل'>
                                    <i class='fas fa-edit'></i>
                                </a>";
                    }

                    if ($can_delete) {
                        echo "<a href='javascript:void(0)' class='action-btn delete' onclick='deleteMine({$mine['id']})' title='حذف'>
                                    <i class='fas fa-trash-alt'></i>
                                </a>";
                    }

                    echo "</div>
                          </td>";
                    echo "</tr>";
                    $counter++;
                }
                ?>
            </tbody>
        </table>
            </div>
        </div>

<!-- Modal عرض المنجم -->
<div id="viewMineModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h5><i class="fas fa-eye"></i> عرض بيانات المنجم</h5>
            <button class="close-modal" onclick="closeViewModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="view-modal-body">
                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-barcode"></i> كود المنجم</div>
                    <div class="view-item-value" id="view_mine_code">-</div>
                </div>

                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-mountain"></i> اسم المنجم</div>
                    <div class="view-item-value" id="view_mine_name">-</div>
                </div>

                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-user-tie"></i> مدير المنجم</div>
                    <div class="view-item-value" id="view_manager_name">-</div>
                </div>

                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-gem"></i> نوع المعدن</div>
                    <div class="view-item-value" id="view_mineral_type">-</div>
                </div>

                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-industry"></i> نوع المنجم</div>
                    <div class="view-item-value" id="view_mine_type">-</div>
                </div>

                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-info-circle"></i> تفاصيل نوع المنجم</div>
                    <div class="view-item-value" id="view_mine_type_other">-</div>
                </div>

                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-building"></i> نوع الملكية</div>
                    <div class="view-item-value" id="view_ownership_type">-</div>
                </div>

                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-info-circle"></i> تفاصيل نوع الملكية</div>
                    <div class="view-item-value" id="view_ownership_type_other">-</div>
                </div>

                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-ruler-combined"></i> مساحة المنجم</div>
                    <div class="view-item-value" id="view_mine_area">-</div>
                </div>

                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-arrows-alt-v"></i> عمق التعدين</div>
                    <div class="view-item-value" id="view_mining_depth">-</div>
                </div>

                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-file-signature"></i> طبيعة التعاقد</div>
                    <div class="view-item-value" id="view_contract_nature">-</div>
                </div>

                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-toggle-on"></i> الحالة</div>
                    <div class="view-item-value" id="view_status">-</div>
                </div>

                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-sticky-note"></i> ملاحظات</div>
                    <div class="view-item-value" id="view_notes">-</div>
                </div>
            </div>
        </div>
        <div class="modal-footer" style="display: flex; gap: 10px; justify-content: flex-end; padding: 0 20px 20px;">
            <a id="view_contracts_btn" class="action-btn contracts" style="text-decoration: none;">
                <i class="fas fa-file-contract"></i> عقودات المنجم
            </a>
            <?php if ($can_edit): ?>
            <button type="button" class="action-btn edit" onclick="openEditFromView()">
                <i class="fas fa-edit"></i> تعديل المنجم
            </button>
            <?php endif; ?>
            <button type="button" class="action-btn delete" onclick="closeViewModal()">
                <i class="fas fa-times"></i> إغلاق
            </button>
        </div>
    </div>
</div>

<!-- Modal -->
<div id="mineModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">إضافة منجم جديد</h3>
            <span class="close" onclick="closeModal()">&times;</span>
        </div>
        <div class="modal-body">
            <form id="mineForm">
                <input type="hidden" id="mine_id" name="mine_id">
                <input type="hidden" id="action" name="action" value="create">

                <div class="form-grid">
                    <div class="form-group">
                        <label>كود/رمز المنجم <span class="required">*</span></label>
                        <input type="text" id="mine_code" name="mine_code" required>
                    </div>

                    <div class="form-group">
                        <label>اسم المنجم <span class="required">*</span></label>
                        <input type="text" id="mine_name" name="mine_name" required>
                    </div>

                    <div class="form-group">
                        <label>اسم مدير المنجم</label>
                        <input type="text" id="manager_name" name="manager_name">
                    </div>

                    <div class="form-group">
                        <label>نوع المعدن</label>
                        <input type="text" id="mineral_type" name="mineral_type" placeholder="مثال: ذهب، فضة، نحاس">
                    </div>

                    <div class="form-group">
                        <label>نوع المنجم <span class="required">*</span></label>
                        <select id="mine_type" name="mine_type" required onchange="toggleOtherField('mine_type')">
                            <option value="">-- اختر --</option>
                            <option value="حفرة مفتوحة">حفرة مفتوحة</option>
                            <option value="تحت أرضي">تحت أرضي</option>
                            <option value="آبار">آبار</option>
                            <option value="مهجور">مهجور</option>
                            <option value="مجمع معالجة/تركيز">مجمع معالجة/تركيز</option>
                            <option value="موقع تخزين/مستودع">موقع تخزين/مستودع</option>
                            <option value="أخرى">أخرى</option>
                        </select>
                    </div>

                    <div class="form-group conditional-field" id="mine_type_other_div">
                        <label>تفاصيل نوع المنجم</label>
                        <input type="text" id="mine_type_other" name="mine_type_other">
                    </div>

                    <div class="form-group">
                        <label>نوع الملكية <span class="required">*</span></label>
                        <select id="ownership_type" name="ownership_type" required
                            onchange="toggleOtherField('ownership_type')">
                            <option value="">-- اختر --</option>
                            <option value="تعدين أهلي/تقليدي">تعدين أهلي/تقليدي</option>
                            <option value="شركة سودانية خاصة">شركة سودانية خاصة</option>
                            <option value="شركة حكومية/قطاع عام">شركة حكومية/قطاع عام</option>
                            <option value="شركة أجنبية">شركة أجنبية</option>
                            <option value="مشروع مشترك (سوداني-أجنبي)">مشروع مشترك (سوداني-أجنبي)</option>
                            <option value="أخرى">أخرى</option>
                        </select>
                    </div>

                    <div class="form-group conditional-field" id="ownership_type_other_div">
                        <label>تفاصيل نوع الملكية</label>
                        <input type="text" id="ownership_type_other" name="ownership_type_other">
                    </div>

                    <div class="form-group">
                        <label>مساحة المنجم</label>
                        <input type="number" step="0.01" id="mine_area" name="mine_area" placeholder="0.00">
                    </div>

                    <div class="form-group">
                        <label>وحدة قياس المساحة</label>
                        <select id="mine_area_unit" name="mine_area_unit">
                            <option value="هكتار">هكتار</option>
                            <option value="كم²">كم²</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>عمق التعدين (متر)</label>
                        <input type="number" step="0.01" id="mining_depth" name="mining_depth" placeholder="0.00">
                    </div>

                    <div class="form-group">
                        <label>طبيعة التعاقد</label>
                        <select id="contract_nature" name="contract_nature">
                            <option value="">-- اختر --</option>
                            <option value="موظف مباشر لدى المالك">موظف مباشر لدى المالك</option>
                            <option value="مقاول/شركة مقاولات">مقاول/شركة مقاولات</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>الحالة <span class="required">*</span></label>
                        <select id="status" name="status" required>
                            <option value="1">نشط</option>
                            <option value="0">غير نشط</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>ملاحظات إضافية</label>
                    <textarea id="notes" name="notes" placeholder="أي معلومات إضافية عن المنجم..."></textarea>
                </div>

                <button type="submit" class="btn-submit">
                    <i class="fas fa-save"></i> حفظ البيانات
                </button>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

<script>
    $(document).ready(function () {
        $('#minesTable').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/ar.json'
            },
            order: [[0, 'desc']]
        });

        // Toggle Form
        $('#toggleForm').click(function() {
            resetForm();
            $('#mineForm').slideDown(400);
            $('html, body').animate({
                scrollTop: $('#mineForm').offset().top - 100
            }, 500);
        });
    });

    // Toggle Form Show/Hide
    function toggleForm() {
        $('#mineForm').slideUp(300);
    }

    // Reset Form
    function resetForm() {
        $('#mineForm')[0].reset();
        $('#mine_id').val('');
        $('#formTitle').text('إضافة منجم جديد');
        document.querySelectorAll('[id*="_other_div"]').forEach(field => {
            field.style.display = 'none';
        });
    }

    // Edit Mine - Load data and show form
    function editMine(mine) {
        const canEdit = <?php echo $can_edit ? 'true' : 'false'; ?>;
        if (!canEdit) {
            alert('لا توجد صلاحية تعديل المناجم');
            return;
        }

        $('#mine_id').val(mine.id);
        $('#mine_code').val(mine.mine_code);
        $('#mine_name').val(mine.mine_name);
        $('#manager_name').val(mine.manager_name || '');
        $('#mineral_type').val(mine.mineral_type || '');
        $('#mine_type').val(mine.mine_type);
        $('#mine_type_other').val(mine.mine_type_other || '');
        $('#ownership_type').val(mine.ownership_type);
        $('#ownership_type_other').val(mine.ownership_type_other || '');
        $('#mine_area').val(mine.mine_area || '');
        $('#mine_area_unit').val(mine.mine_area_unit);
        $('#mining_depth').val(mine.mining_depth || '');
        $('#contract_nature').val(mine.contract_nature || '');
        $('#status').val(mine.status);
        $('#notes').val(mine.notes || '');

        toggleOtherField('mine_type');
        toggleOtherField('ownership_type');

        $('#formTitle').text('تعديل بيانات المنجم');
        closeViewModal();
        $('#mineForm').slideDown(400);
        $('html, body').animate({
            scrollTop: $('#mineForm').offset().top - 100
        }, 500);
    }

    // Open View Modal
    function openViewModal(mine) {
        window.currentViewMine = mine;
        $('#view_mine_code').text(mine.mine_code || '-');
        $('#view_mine_name').text(mine.mine_name || '-');
        $('#view_manager_name').text(mine.manager_name || '-');
        $('#view_mineral_type').text(mine.mineral_type || '-');
        $('#view_mine_type').text(mine.mine_type || '-');
        $('#view_mine_type_other').text(mine.mine_type_other || '-');
        $('#view_ownership_type').text(mine.ownership_type || '-');
        $('#view_ownership_type_other').text(mine.ownership_type_other || '-');

        const areaText = mine.mine_area ? `${parseFloat(mine.mine_area).toFixed(2)} ${mine.mine_area_unit || ''}` : '-';
        const depthText = mine.mining_depth ? `${parseFloat(mine.mining_depth).toFixed(2)} م` : '-';

        $('#view_mine_area').text(areaText.trim() || '-');
        $('#view_mining_depth').text(depthText);
        $('#view_contract_nature').text(mine.contract_nature || '-');
        $('#view_status').text((String(mine.status) === '1') ? 'نشط' : 'غير نشط');
        $('#view_notes').text(mine.notes || '-');

        $('#view_contracts_btn').attr('href', '../Contracts/contracts.php?id=' + mine.id);
        $('#viewMineModal').fadeIn(300);
    }

    // Edit from View Modal
    function openEditFromView() {
        if (window.currentViewMine) {
            closeViewModal();
            editMine(window.currentViewMine);
        }
    }

    // Close View Modal
    function closeViewModal() {
        $('#viewMineModal').fadeOut(300);
    }

    // Delete Mine
    function deleteMine(id) {
        const canDelete = <?php echo $can_delete ? 'true' : 'false'; ?>;
        if (!canDelete) {
            alert('لا توجد صلاحية حذف المناجم');
            return;
        }

        if (confirm('هل أنت متأكد من حذف هذا المنجم؟')) {
            window.location.href = 'project_mines.php?project_id=<?php echo $project_id; ?>&delete=' + id + '&csrf_token=<?php echo urlencode($project_mines_csrf_token); ?>';
        }
    }

    // Toggle Conditional Fields
    function toggleOtherField(fieldType) {
        const select = document.getElementById(fieldType);
        const otherDiv = document.getElementById(fieldType + '_other_div');

        if (select.value === 'أخرى') {
            otherDiv.style.display = 'block';
        } else {
            otherDiv.style.display = 'none';
        }
    }

    // Close modal when clicking outside
    window.onclick = function (event) {
        const viewModal = document.getElementById('viewMineModal');
        if (event.target == viewModal) {
            closeViewModal();
        }
    }
</script>

</body>

</html>
