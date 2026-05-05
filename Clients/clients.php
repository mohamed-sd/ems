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

if (!function_exists('clients_fix_mojibake_output')) {
    function clients_fix_mojibake_output($buffer)
    {
        $map = array(
            'ا' => 'ا', 'ب' => 'ب', 'ت' => 'ت', 'ث' => 'ث', 'ج' => 'ج', 'ح' => 'ح',
            'خ' => 'خ', 'د' => 'د', 'ذ' => 'ذ', 'ر' => 'ر', 'ز' => 'ز', 'س' => 'س',
            'ش' => 'ش', 'ص' => 'ص', 'ض' => 'ض', 'ط' => 'ط', 'ظ' => 'ظ', 'ع' => 'ع',
            'غ' => 'غ', 'ف' => 'ف', 'ق' => 'ق', 'ك' => 'ك', 'ل' => 'ل', 'م' => 'م',
            'ن' => 'ن', 'ه' => 'ه', 'و' => 'و', 'ي' => 'ي', 'ى' => 'ى', 'ة' => 'ة',
            'ء' => 'ء', 'أ' => 'أ', 'إ' => 'إ', 'آ' => 'آ', 'ؤ' => 'ؤ', 'ئ' => 'ئ',
            '،' => '،', '؛' => '؛', '؟' => '؟', '✅' => '✅', '❌' => '❌', '⏸' => '⏸',
            'ðŸ”' => 'ðŸ”', 'ðŸ‘‹' => 'ðŸ‘‹', 'ðŸš€' => 'ðŸš€', 'ðŸ†' => 'ðŸ†'
        );
        return strtr($buffer, $map);
    }
}

ob_start('clients_fix_mojibake_output');

// ══════════════════════════════════════════════════════════════════════════════
// دوال مساعدة
// ══════════════════════════════════════════════════════════════════════════════

if (!function_exists('clients_table_has_column')) {
    // التحقق من وجود عمود في جدول معين
    function clients_table_has_column($conn, $tableName, $columnName)
    {
        $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $tableName);
        $safeCol   = preg_replace('/[^a-zA-Z0-9_]/', '', $columnName);
        $sql       = "SHOW COLUMNS FROM " . $safeTable . " LIKE '" . mysqli_real_escape_string($conn, $safeCol) . "'";
        $res       = @mysqli_query($conn, $sql);

        return $res && mysqli_num_rows($res) > 0;
    }
}

if (!function_exists('clients_build_scope_sql')) {
    // بناء شرط نطاق الشركة للاستعلامات
    function clients_build_scope_sql($company_id, $clients_has_company_id, $alias)
    {
        $prefix = $alias !== '' ? $alias . '.' : '';
        if ($clients_has_company_id) {
            return $prefix . "company_id = $company_id";
        }

        return "EXISTS (SELECT 1 FROM users scope_u WHERE scope_u.id = " . $prefix . "created_by AND scope_u.company_id = $company_id)";
    }
}

if (!function_exists('clients_not_deleted_sql')) {
    // بناء شرط السجلات غير المحذوفة
    function clients_not_deleted_sql($alias, $has_is_deleted, $has_deleted_at)
    {
        $prefix = $alias !== '' ? $alias . '.' : '';
        if ($has_is_deleted) {
            return $prefix . "is_deleted = 0";
        }
        if ($has_deleted_at) {
            return $prefix . "deleted_at IS NULL";
        }

        return "1=1";
    }
}

if (!function_exists('clients_e')) {
    // تنظيف المخرجات لمنع XSS
    function clients_e($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('clients_redirect_with_msg')) {
    // إعادة التوجيه مع رسالة
    function clients_redirect_with_msg($msg)
    {
        header('Location: clients.php?msg=' . urlencode($msg));
        exit();
    }
}

// ══════════════════════════════════════════════════════════════════════════════
// التحقق من معرف الشركة
// ══════════════════════════════════════════════════════════════════════════════
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
if ($company_id <= 0) {
    header('Location: ../login.php?msg=' . urlencode('الحساب غير مرتبط بشركة.'));
    exit();
}

// ══════════════════════════════════════════════════════════════════════════════
// فحص أعمدة الجدول وإضافة الأعمدة المفقودة تلقائياً
// ══════════════════════════════════════════════════════════════════════════════
$clients_has_company_id = clients_table_has_column($conn, 'clients', 'company_id');
$clients_has_is_deleted = clients_table_has_column($conn, 'clients', 'is_deleted');
$clients_has_deleted_at = clients_table_has_column($conn, 'clients', 'deleted_at');
$clients_has_deleted_by = clients_table_has_column($conn, 'clients', 'deleted_by');

$project_has_client_id         = clients_table_has_column($conn, 'project', 'client_id');
$project_has_company_client_id = clients_table_has_column($conn, 'project', 'company_client_id');
$project_has_company_id        = clients_table_has_column($conn, 'project', 'company_id');
$project_has_is_deleted        = clients_table_has_column($conn, 'project', 'is_deleted');
$project_has_deleted_at        = clients_table_has_column($conn, 'project', 'deleted_at');

$operations_has_company_id       = clients_table_has_column($conn, 'operations', 'company_id');
$equipment_drivers_has_company_id = clients_table_has_column($conn, 'equipment_drivers', 'company_id');
$mines_has_company_id            = clients_table_has_column($conn, 'mines', 'company_id');
$mines_has_is_deleted            = clients_table_has_column($conn, 'mines', 'is_deleted');
$mines_has_deleted_at            = clients_table_has_column($conn, 'mines', 'deleted_at');

$project_client_link_column = '';
if ($project_has_company_client_id) {
    $project_client_link_column = 'company_client_id';
} elseif ($project_has_client_id) {
    $project_client_link_column = 'client_id';
}

if (!$clients_has_is_deleted || !$clients_has_deleted_at || !$clients_has_deleted_by) {
    $alter_parts = array();
    if (!$clients_has_is_deleted) {
        $alter_parts[] = "ADD COLUMN is_deleted TINYINT(1) NOT NULL DEFAULT 0";
    }
    if (!$clients_has_deleted_at) {
        $alter_parts[] = "ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL";
    }
    if (!$clients_has_deleted_by) {
        $alter_parts[] = "ADD COLUMN deleted_by INT(11) NULL DEFAULT NULL";
    }

    if (!empty($alter_parts)) {
        @mysqli_query($conn, "ALTER TABLE clients " . implode(', ', $alter_parts));
    }

    // إعادة الفحص بعد التعديل
    $clients_has_is_deleted = clients_table_has_column($conn, 'clients', 'is_deleted');
    $clients_has_deleted_at = clients_table_has_column($conn, 'clients', 'deleted_at');
    $clients_has_deleted_by = clients_table_has_column($conn, 'clients', 'deleted_by');
}

// ══════════════════════════════════════════════════════════════════════════════
// بناء شروط SQL للنطاق والحذف الناعم
// ══════════════════════════════════════════════════════════════════════════════
$scope_clients_sql        = clients_build_scope_sql($company_id, $clients_has_company_id, 'cc');
$scope_clients_update_sql = clients_build_scope_sql($company_id, $clients_has_company_id, '');
$not_deleted_cc_sql       = clients_not_deleted_sql('cc', $clients_has_is_deleted, $clients_has_deleted_at);
$not_deleted_plain_sql    = clients_not_deleted_sql('', $clients_has_is_deleted, $clients_has_deleted_at);

$scope_project_sql      = clients_build_scope_sql($company_id, $project_has_company_id, 'p');
$not_deleted_project_sql = clients_not_deleted_sql('p', $project_has_is_deleted, $project_has_deleted_at);

$projects_count_select_sql = '0';
if ($project_client_link_column !== '') {
    $projects_count_select_sql = "(
        SELECT COUNT(*)
        FROM project p
        WHERE p.$project_client_link_column = cc.id
          AND $scope_project_sql
          AND $not_deleted_project_sql
    )";
}

// ══════════════════════════════════════════════════════════════════════════════
// توليد رمز CSRF لحماية النماذج
// ══════════════════════════════════════════════════════════════════════════════
if (empty($_SESSION['clients_csrf_token'])) {
    $_SESSION['clients_csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
}
$clients_csrf_token = $_SESSION['clients_csrf_token'];

// ══════════════════════════════════════════════════════════════════════════════
// توليد الكود المقترح التالي للعميل (CLT-NNNN)
// يجلب آخر كود من جدول العملاء بصيغة CLT-NNNN ويزيده بمقدار 1
// هذا للعرض فقط ولا يُخزَّن في قاعدة البيانات
// ══════════════════════════════════════════════════════════════════════════════
$next_client_code = 'CLT-0001'; // القيمة الافتراضية
$last_code_scope  = $clients_has_company_id ? "AND company_id = $company_id" : '';
$last_code_deleted = $clients_has_is_deleted ? "AND is_deleted = 0" : ($clients_has_deleted_at ? "AND deleted_at IS NULL" : "");
$last_code_sql = "SELECT client_code FROM clients
                  WHERE client_code REGEXP '^CLT-[0-9]+$'
                  $last_code_scope
                  $last_code_deleted
                  ORDER BY CAST(SUBSTRING(client_code, 5) AS UNSIGNED) DESC
                  LIMIT 1";
$last_code_res = @mysqli_query($conn, $last_code_sql);
if ($last_code_res && mysqli_num_rows($last_code_res) > 0) {
    $last_code_row = mysqli_fetch_assoc($last_code_res);
    $last_num      = intval(substr($last_code_row['client_code'], 4)); // بعد "CLT-"
    $next_num      = $last_num + 1;
    $next_client_code = 'CLT-' . str_pad($next_num, 4, '0', STR_PAD_LEFT);
}

// ══════════════════════════════════════════════════════════════════════════════
// ðŸ” التحقق من صلاحيات المستخدم على وحدة العملاء
// ══════════════════════════════════════════════════════════════════════════════

// الحصول على معرف وحدة العملاء من جدول modules
$module_query = "SELECT id FROM modules 
                      WHERE code = 'Clients/clients.php' 
                          OR code = 'clients' 
                          OR code LIKE '%clients.php%'
                          OR name LIKE '%عملاء%'
                      LIMIT 1";
$module_result = $conn->query($module_query);
$module_info   = $module_result ? $module_result->fetch_assoc() : null;
$module_id     = $module_info ? $module_info['id'] : null;

// تحديد صلاحيات المستخدم على هذه الوحدة
$can_view   = false;
$can_add    = false;
$can_edit   = false;
$can_delete = false;

if ($module_id) {
    $perms      = get_module_permissions($conn, $module_id);
    $can_view   = $perms['can_view'];
    $can_add    = $perms['can_add'];
    $can_edit   = $perms['can_edit'];
    $can_delete = $perms['can_delete'];
}

// منع الوصول إذا لم تكن هناك صلاحية عرض
if (!$can_view) {
    header('Location: ../login.php?msg=' . urlencode('لا توجد صلاحية عرض العملاء ❌'));
    exit();
}

// ══════════════════════════════════════════════════════════════════════════════
// معالجة إضافة / تعديل عميل عبر POST
// ══════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['client_name'])) {
    // التحقق من رمز CSRF
    $posted_csrf = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if (empty($posted_csrf) || !hash_equals($clients_csrf_token, $posted_csrf)) {
        clients_redirect_with_msg('جلسة النموذج غير صالحة، يرجى إعادة المحاولة ❌');
    }

    // التحقق من صلاحية التعديل أو الإضافة
    $client_id  = isset($_POST['client_id']) ? intval($_POST['client_id']) : 0;
    $is_editing = $client_id > 0;
    
    if ($is_editing && !$can_edit) {
        clients_redirect_with_msg('لا توجد صلاحية تعديل العملاء ❌');
    } elseif (!$is_editing && !$can_add) {
        clients_redirect_with_msg('لا توجد صلاحية إضافة عملاء جدد ❌');
    }

    // التحقق من صحة كود العميل
    $client_code_raw = isset($_POST['client_code']) ? trim($_POST['client_code']) : '';
    if ($client_code_raw === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $client_code_raw)) {
        clients_redirect_with_msg('كود العميل غير صالح. استخدم أحرفًا وأرقامًا و - أو _ فقط ❌');
    }

    // التحقق من صحة حالة العميل
    $status_raw     = isset($_POST['status']) ? trim($_POST['status']) : '';
    $allowed_status = array('نشط', 'متوقف');
    if (!in_array($status_raw, $allowed_status, true)) {
        clients_redirect_with_msg('حالة العميل غير صالحة ❌');
    }

    // تنظيف البيانات المدخلة
    $client_code     = mysqli_real_escape_string($conn, $client_code_raw);
    $client_name     = mysqli_real_escape_string($conn, trim($_POST['client_name']));
    $entity_type     = mysqli_real_escape_string($conn, trim($_POST['entity_type']));
    $sector_category = mysqli_real_escape_string($conn, trim($_POST['sector_category']));
    $phone           = mysqli_real_escape_string($conn, trim($_POST['phone']));
    $email           = mysqli_real_escape_string($conn, trim($_POST['email']));
    $whatsapp        = mysqli_real_escape_string($conn, trim($_POST['whatsapp']));
    $status          = mysqli_real_escape_string($conn, $status_raw);
    $created_by      = intval($_SESSION['user']['id']);

    if ($client_id > 0) {
        // ── تعديل عميل موجود ────────────────────────────────────────────────

        // التحقق من ملكية العميل للشركة الحالية
        $owner_check_query  = "SELECT cc.id FROM clients cc WHERE cc.id = $client_id AND $scope_clients_sql AND $not_deleted_cc_sql LIMIT 1";
        $owner_check_result = mysqli_query($conn, $owner_check_query);
        if (!$owner_check_result || mysqli_num_rows($owner_check_result) === 0) {
            clients_redirect_with_msg('لا يمكنك تعديل عميل لا يتبع لشركتك ❌');
        }

        // التحقق من عدم تكرار كود العميل
        $check_query  = "SELECT cc.id FROM clients cc WHERE cc.client_code = '$client_code' AND cc.id != $client_id AND $scope_clients_sql AND $not_deleted_cc_sql";
        $check_result = mysqli_query($conn, $check_query);

        if (mysqli_num_rows($check_result) > 0) {
            clients_redirect_with_msg('كود العميل موجود مسبقاً داخل شركتك ❌');
        }

        $update_query = "UPDATE clients SET 
            client_code      = '$client_code',
            client_name      = '$client_name',
            entity_type      = '$entity_type',
            sector_category  = '$sector_category',
            phone            = '$phone',
            email            = '$email',
            whatsapp         = '$whatsapp',
            status           = '$status'
            WHERE id = $client_id AND $scope_clients_update_sql AND $not_deleted_plain_sql";

        // إذا كان عمود company_id موجوداً، أضفه للتحديث
        if ($clients_has_company_id) {
            $update_query = "UPDATE clients SET 
            client_code      = '$client_code',
            client_name      = '$client_name',
            entity_type      = '$entity_type',
            sector_category  = '$sector_category',
            phone            = '$phone',
            email            = '$email',
            whatsapp         = '$whatsapp',
            status           = '$status',
            company_id       = '$company_id'
            WHERE id = $client_id AND $scope_clients_update_sql AND $not_deleted_plain_sql";
        }
        
        if (mysqli_query($conn, $update_query)) {
            clients_redirect_with_msg('تم تعديل العميل بنجاح ✅');
        } else {
            error_log('clients.php update failed: ' . mysqli_error($conn));
            clients_redirect_with_msg('حدث خطأ أثناء التعديل ❌');
        }

    } else {
        // ── إضافة عميل جديد ─────────────────────────────────────────────────

        // التحقق من عدم تكرار كود العميل
        $check_query  = "SELECT cc.id FROM clients cc WHERE cc.client_code = '$client_code' AND $scope_clients_sql AND $not_deleted_cc_sql";
        $check_result = mysqli_query($conn, $check_query);

        if (mysqli_num_rows($check_result) > 0) {
            clients_redirect_with_msg('كود العميل موجود مسبقاً داخل شركتك ❌');
        }

        $insert_query = "INSERT INTO clients 
            (client_code, client_name, entity_type, sector_category, phone, email, whatsapp, status, created_by) 
            VALUES 
            ('$client_code', '$client_name', '$entity_type', '$sector_category', '$phone', '$email', '$whatsapp', '$status', '$created_by')";

        // إذا كان عمود company_id موجوداً، أضفه للإدراج
        if ($clients_has_company_id) {
            $insert_query = "INSERT INTO clients 
            (client_code, client_name, entity_type, sector_category, phone, email, whatsapp, status, created_by, company_id) 
            VALUES 
            ('$client_code', '$client_name', '$entity_type', '$sector_category', '$phone', '$email', '$whatsapp', '$status', '$created_by', '$company_id')";
        }

        if (mysqli_query($conn, $insert_query)) {
            clients_redirect_with_msg('تم إضافة العميل بنجاح ✅');
        } else {
            error_log('clients.php insert failed: ' . mysqli_error($conn));
            clients_redirect_with_msg('حدث خطأ أثناء الإضافة ❌');
        }
    }
}

// ══════════════════════════════════════════════════════════════════════════════
// معالجة حذف العميل (حذف ناعم)
// ══════════════════════════════════════════════════════════════════════════════
if (isset($_GET['delete_id'])) {
    $delete_id   = intval($_GET['delete_id']);
    $delete_csrf = isset($_GET['csrf_token']) ? $_GET['csrf_token'] : '';
    
    // التحقق من صلاحية الحذف
    if (!$can_delete) {
        clients_redirect_with_msg('لا توجد صلاحية حذف العملاء ❌');
    }

    if (!$clients_has_is_deleted && !$clients_has_deleted_at) {
        clients_redirect_with_msg('تعذر تفعيل الحذف الناعم حالياً. راجع صلاحيات قاعدة البيانات ❌');
    }

    // التحقق من رمز CSRF
    if (empty($delete_csrf) || !hash_equals($clients_csrf_token, $delete_csrf)) {
        clients_redirect_with_msg('جلسة الحذف غير صالحة، يرجى إعادة المحاولة ❌');
    }

    // التحقق من أن العميل تابع لشركة المستخدم
    $can_delete_scope_result = mysqli_query($conn, "SELECT cc.id FROM clients cc WHERE cc.id = $delete_id AND $scope_clients_sql AND $not_deleted_cc_sql LIMIT 1");
    if (!$can_delete_scope_result || mysqli_num_rows($can_delete_scope_result) === 0) {
        clients_redirect_with_msg('لا يمكنك حذف عميل لا يتبع لشركتك ❌');
    }

    // بناء استعلام الحذف الناعم
    $delete_set = array("status = 'متوقف'");
    if ($clients_has_is_deleted) {
        $delete_set[] = "is_deleted = 1";
    }
    if ($clients_has_deleted_at) {
        $delete_set[] = "deleted_at = NOW()";
    }
    if ($clients_has_deleted_by) {
        $deleted_by   = intval($_SESSION['user']['id']);
        $delete_set[] = "deleted_by = $deleted_by";
    }

    $soft_delete_query = "UPDATE clients SET " . implode(', ', $delete_set) . " WHERE id = $delete_id AND $scope_clients_update_sql";
    if ($clients_has_is_deleted) {
        $soft_delete_query .= " AND is_deleted = 0";
    } elseif ($clients_has_deleted_at) {
        $soft_delete_query .= " AND deleted_at IS NULL";
    }

    if (mysqli_query($conn, $soft_delete_query)) {
        clients_redirect_with_msg('تم حذف العميل بنجاح ✅');
    }

    error_log('clients.php soft delete failed: ' . mysqli_error($conn));
    clients_redirect_with_msg('حدث خطأ أثناء الحذف ❌');
}

if (isset($_GET['ajax']) && $_GET['ajax'] === 'client_projects') {
    header('Content-Type: application/json; charset=UTF-8');

    $client_id = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;
    if ($client_id <= 0) {
        echo json_encode(array('success' => false, 'message' => 'معرف العميل غير صالح'));
        exit();
    }

    $client_check_query = "SELECT cc.id FROM clients cc WHERE cc.id = $client_id AND $scope_clients_sql AND $not_deleted_cc_sql LIMIT 1";
    $client_check_res   = mysqli_query($conn, $client_check_query);
    if (!$client_check_res || mysqli_num_rows($client_check_res) === 0) {
        echo json_encode(array('success' => false, 'message' => 'العميل غير موجود أو خارج نطاق الشركة'));
        exit();
    }

    if ($project_client_link_column === '') {
        echo json_encode(array('success' => true, 'projects' => array()));
        exit();
    }

    $operations_company_filter = $operations_has_company_id ? " AND o.company_id = $company_id" : '';
    $equipment_drivers_company_filter = $equipment_drivers_has_company_id ? " AND ed.company_id = $company_id" : '';
    $mines_company_filter = $mines_has_company_id ? " AND m.company_id = $company_id" : '';
    $mines_not_deleted_sql = clients_not_deleted_sql('m', $mines_has_is_deleted, $mines_has_deleted_at);

    $projects_query = "
        SELECT
            p.id,
            p.name,
            p.project_code,
            p.status,
            (
                SELECT COUNT(*)
                FROM mines m
                WHERE m.project_id = p.id
                  AND m.status = 1
                  AND $mines_not_deleted_sql
                  $mines_company_filter
            ) AS mines_count,
            (
                SELECT COUNT(DISTINCT CASE
                    WHEN o.supplier_id IS NOT NULL AND o.supplier_id <> '' AND o.supplier_id <> '0' THEN o.supplier_id
                    ELSE NULL
                END)
                FROM operations o
                WHERE o.project_id = p.id
                  $operations_company_filter
            ) AS suppliers_count,
            (
                SELECT COUNT(DISTINCT o.equipment)
                FROM operations o
                WHERE o.project_id = p.id
                  AND o.equipment IS NOT NULL
                  AND o.equipment <> ''
                  AND o.equipment <> '0'
                  $operations_company_filter
            ) AS equipments_total,
            (
                SELECT COUNT(DISTINCT o.equipment)
                FROM operations o
                WHERE o.project_id = p.id
                  AND o.status = 1
                  AND o.equipment IS NOT NULL
                  AND o.equipment <> ''
                  AND o.equipment <> '0'
                  $operations_company_filter
            ) AS equipments_working,
            (
                SELECT COUNT(DISTINCT ed.driver_id)
                FROM operations o
                JOIN equipment_drivers ed ON ed.equipment_id = o.equipment
                WHERE o.project_id = p.id
                  AND ed.driver_id IS NOT NULL
                  $operations_company_filter
                  $equipment_drivers_company_filter
            ) AS operators_total,
            (
                SELECT COUNT(DISTINCT ed.driver_id)
                FROM operations o
                JOIN equipment_drivers ed ON ed.equipment_id = o.equipment
                WHERE o.project_id = p.id
                  AND ed.status = 1
                  AND ed.driver_id IS NOT NULL
                  $operations_company_filter
                  $equipment_drivers_company_filter
            ) AS operators_working
        FROM project p
        WHERE p.$project_client_link_column = $client_id
          AND $scope_project_sql
          AND $not_deleted_project_sql
        ORDER BY p.id DESC
    ";

    $projects_result = mysqli_query($conn, $projects_query);
    if (!$projects_result) {
        echo json_encode(array('success' => false, 'message' => 'تعذر تحميل بيانات المشاريع'));
        exit();
    }

    $projects = array();
    while ($project_row = mysqli_fetch_assoc($projects_result)) {
        $equipments_total   = intval($project_row['equipments_total']);
        $equipments_working = intval($project_row['equipments_working']);
        $operators_total    = intval($project_row['operators_total']);
        $operators_working  = intval($project_row['operators_working']);

        $project_row['equipments_total']   = $equipments_total;
        $project_row['equipments_working'] = $equipments_working;
        $project_row['equipments_stopped'] = max(0, $equipments_total - $equipments_working);
        $project_row['operators_total']    = $operators_total;
        $project_row['operators_working']  = $operators_working;
        $project_row['operators_stopped']  = max(0, $operators_total - $operators_working);
        $project_row['mines_count']        = intval($project_row['mines_count']);
        $project_row['suppliers_count']    = intval($project_row['suppliers_count']);

        $projects[] = $project_row;
    }

    echo json_encode(array('success' => true, 'projects' => $projects));
    exit();
}

// ══════════════════════════════════════════════════════════════════════════════
// بيانات العملاء + الإحصائيات العامة
// ══════════════════════════════════════════════════════════════════════════════
$clients_rows = array();

$clients_total_count      = 0;
$clients_active_count     = 0;
$clients_stopped_count    = 0;
$clients_companies_count  = 0;
$clients_individuals_count = 0;
$clients_unknown_entity_count = 0;
$clients_projects_total   = 0;
$clients_without_projects = 0;

$sector_counts = array();

$clients_query  = "SELECT cc.*, u.name as creator_name, $projects_count_select_sql AS projects_count
                  FROM clients cc
                  LEFT JOIN users u ON cc.created_by = u.id
                  WHERE $scope_clients_sql AND $not_deleted_cc_sql
                  ORDER BY cc.id DESC";
$clients_result = mysqli_query($conn, $clients_query);

if ($clients_result) {
    while ($row = mysqli_fetch_assoc($clients_result)) {
        $clients_rows[] = $row;

        $clients_total_count++;
        if (isset($row['status']) && trim($row['status']) === 'نشط') {
            $clients_active_count++;
        }

        $projects_count_value = intval($row['projects_count']);
        $clients_projects_total += $projects_count_value;
        if ($projects_count_value === 0) {
            $clients_without_projects++;
        }

        $entity_type_value = isset($row['entity_type']) ? trim($row['entity_type']) : '';
        if ($entity_type_value === '') {
            $clients_unknown_entity_count++;
        } elseif (
            strpos($entity_type_value, 'فرد') !== false ||
            strpos($entity_type_value, 'شخص') !== false ||
            in_array($entity_type_value, array('فرد', 'أفراد', 'فردي', 'شخصي'), true)
        ) {
            $clients_individuals_count++;
        } else {
            $clients_companies_count++;
        }

        $sector_value = isset($row['sector_category']) ? trim($row['sector_category']) : '';
        if ($sector_value === '') {
            $sector_value = 'غير مصنف';
        }
        if (!isset($sector_counts[$sector_value])) {
            $sector_counts[$sector_value] = 0;
        }
        $sector_counts[$sector_value]++;
    }
}

$clients_stopped_count = max(0, $clients_total_count - $clients_active_count);
$sector_mining_count      = isset($sector_counts['تعدين']) ? intval($sector_counts['تعدين']) : 0;
$sector_contracting_count = isset($sector_counts['مقاولات']) ? intval($sector_counts['مقاولات']) : 0;
$sector_services_count    = isset($sector_counts['خدمات']) ? intval($sector_counts['خدمات']) : 0;

arsort($sector_counts);

$page_title = "قائمة العملاء";
include("../inheader.php");
include('../insidebar.php');
?>

<link rel="stylesheet" href="/ems/assets/vendor/datatables/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="/ems/assets/vendor/datatables/css/responsive.dataTables.min.css">
<link rel="stylesheet" href="/ems/assets/vendor/datatables/css/buttons.dataTables.min.css">
<link rel="stylesheet" href="../assets/css/allstyle.css">
<!-- Font Awesome من CDN لضمان ظهور الأيقونات بشكل صحيح -->
<link rel="stylesheet" href="/ems/assets/css/all.min.css">
<link href="/ems/assets/css/local-fonts.css" rel="stylesheet">

<div class="main">
    <div class="page-header">
        <h1 class="page-title">
            <div class="title-icon"><i class="fas fa-users"></i></div>
            إدارة العملاء
        </h1>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <a href="../main/dashboard.php" class="back-btn">
                <i class="fas fa-arrow-right"></i> رجوع
            </a>
            <?php if ($can_add): ?>
                <a href="javascript:void(0)" id="toggleForm" class="add-btn">
                    <i class="fas fa-plus-circle"></i> إضافة عميل جديد
                </a>
                <a href="javascript:void(0)" id="openImportModal" class="add-btn"
                    style="background:linear-gradient(135deg,#064e3b,#065f46);color:#fff;border-color:transparent;">
                    <i class="fas fa-file-upload"></i> استيراد من Excel
                </a>
            <?php else: ?>
                <button class="add-btn" disabled style="opacity: 0.5; cursor: not-allowed;">
                    <i class="fas fa-plus-circle"></i> إضافة (بدون صلاحيات)
                </button>
            <?php endif; ?>
            <!-- تصدير البيانات الحالية إلى Excel -->
            <a href="export_clients_excel.php" class="add-btn"
                style="background:linear-gradient(135deg,#1a6e3c,#2d9656);color:#fff;border-color:transparent;"
                title="تصدير جميع العملاء إلى ملف Excel">
                <i class="fas fa-file-excel"></i> تصدير إلى Excel
            </a>
            <!-- تحميل نموذج فارغ للاستيراد -->
            <a href="download_clients_template.php" class="add-btn"
                style="background:linear-gradient(135deg,var(--orange),#f59e0b);color:#fff;border-color:transparent;"
                title="تحميل نموذج Excel فارغ للاستيراد">
                <i class="fas fa-download"></i> نموذج الاستيراد
            </a>
        </div>
    </div>

    <?php if (!empty($_GET['msg'])):
        $isSuccess = strpos($_GET['msg'], '✅') !== false;
    ?>
        <div class="success-message <?= $isSuccess ? 'is-success' : 'is-error' ?>">
            <i class="fas <?= $isSuccess ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?php echo clients_e($_GET['msg']); ?>
        </div>
    <?php endif; ?>

    <div class="stats-section">
        <div class="stats-grid">
            <div class="stats-card stats-primary">
                <div class="stats-icon"><i class="fas fa-users"></i></div>
                <div class="stats-title">إجمالي العملاء</div>
                <div class="stats-value"><?php echo $clients_total_count; ?></div>
            </div>
            <div class="stats-card stats-success">
                <div class="stats-icon"><i class="fas fa-user-check"></i></div>
                <div class="stats-title">العملاء النشطون</div>
                <div class="stats-value"><?php echo $clients_active_count; ?></div>
            </div>
            <div class="stats-card stats-danger">
                <div class="stats-icon"><i class="fas fa-user-slash"></i></div>
                <div class="stats-title">العملاء المتوقفون</div>
                <div class="stats-value"><?php echo $clients_stopped_count; ?></div>
            </div>
            <div class="stats-card stats-purple">
                <div class="stats-icon"><i class="fas fa-diagram-project"></i></div>
                <div class="stats-title">إجمالي المشاريع المرتبطة</div>
                <div class="stats-value"><?php echo $clients_projects_total; ?></div>
            </div>
            <div class="stats-card stats-orange">
                <div class="stats-icon"><i class="fas fa-building"></i></div>
                <div class="stats-title">عدد الشركات</div>
                <div class="stats-value"><?php echo $clients_companies_count; ?></div>
            </div>
            <div class="stats-card stats-cyan">
                <div class="stats-icon"><i class="fas fa-user"></i></div>
                <div class="stats-title">عدد الأفراد</div>
                <div class="stats-value"><?php echo $clients_individuals_count; ?></div>
            </div>
            <div class="stats-card stats-slate">
                <div class="stats-icon"><i class="fas fa-question-circle"></i></div>
                <div class="stats-title">كيان غير محدد</div>
                <div class="stats-value"><?php echo $clients_unknown_entity_count; ?></div>
            </div>
            <div class="stats-card stats-emerald">
                <div class="stats-icon"><i class="fas fa-link-slash"></i></div>
                <div class="stats-title">عملاء بلا مشاريع</div>
                <div class="stats-value"><?php echo $clients_without_projects; ?></div>
            </div>
        </div>

        <div class="sector-cards-grid">
            <div class="sector-card">
                <div class="label"><i class="fas fa-mountain"></i> قطاع التعدين</div>
                <div class="value"><?php echo $sector_mining_count; ?></div>
            </div>
            <div class="sector-card">
                <div class="label"><i class="fas fa-hard-hat"></i> قطاع المقاولات</div>
                <div class="value"><?php echo $sector_contracting_count; ?></div>
            </div>
            <div class="sector-card">
                <div class="label"><i class="fas fa-handshake"></i> قطاع الخدمات</div>
                <div class="value"><?php echo $sector_services_count; ?></div>
            </div>
        </div>

        <?php if (!empty($sector_counts)): ?>
            <div class="sector-tags">
                <?php foreach ($sector_counts as $sector_name => $sector_count): ?>
                    <span class="sector-tag"><?php echo clients_e($sector_name); ?>: <?php echo intval($sector_count); ?></span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- فورم إضافة / تعديل عميل -->
    <form id="clientForm" action="" method="post" style="display:none; margin-bottom:20px;">
        <div class="card shadow-sm">
            <div class="card-header">
                <h5><i class="fas fa-edit"></i> إضافة / تعديل عميل</h5>
            </div>
            <div class="card-body">
                <input type="hidden" name="client_id"   id="client_id"   value="">
                <input type="hidden" name="csrf_token"  value="<?php echo clients_e($clients_csrf_token); ?>">
                <div class="form-grid">

                 <!-- ══ حقل الكود المولد تلقائياً (قراءة فقط - لا يُرسَل لقاعدة البيانات) ══ -->
                    <div id="generated_code_wrapper">
                        <label><i class="fas fa-magic"></i> كود العميل المولد  <i class="fas fa-info-circle" style="color:#3b82f6;"></i></label>
                        <input type="text"
                               id="generated_client_code"
                               class="generated-code-field"
                               value="<?php echo clients_e($next_client_code); ?>"
                               readonly
                               tabindex="-1"
                               title="هذا الكود للعرض فقط، يمكنك نسخه واستخدامه في حقل كود العميل" />
                        <div class="generated-code-hint">
                           
                        </div>
                    </div>
                    <!-- ══════════════════════════════════════════════════════ -->

                    <div>
                        <label><i class="fas fa-barcode"></i> كود العميل *</label>
                        <input type="text" name="client_code" id="client_code"
                               placeholder="مثال: CL-001" required
                               pattern="[A-Za-z0-9-_]+" />
                    </div>
                    <div>
                        <label><i class="fas fa-user"></i> اسم العميل *</label>
                        <input type="text" name="client_name" id="client_name"
                               placeholder="أدخل اسم العميل" required />
                    </div>
                    <div>
                        <label><i class="fas fa-building"></i> نوع الكيان</label>
                        <select name="entity_type" id="entity_type">
                            <option value="">-- اختر نوع الكيان --</option>
                            <option value="حكومي">حكومي</option>
                            <option value="خاص">خاص</option>
                            <option value="مختلط">مختلط</option>
                            <option value="دولي">دولي</option>
                            <option value="غير ربحي">غير ربحي</option>
                        </select>
                    </div>
                    <div>
                        <label><i class="fas fa-industry"></i> تصنيف القطاع</label>
                        <select name="sector_category" id="sector_category">
                            <option value="">-- اختر التصنيف --</option>
                            <option value="بنية تحتية">بنية تحتية</option>
                            <option value="نفط وغاز">نفط وغاز</option>
                            <option value="تعدين">تعدين</option>
                            <option value="زراعة">زراعة</option>
                            <option value="خدمات">خدمات</option>
                            <option value="تجارة">تجارة</option>
                            <option value="صناعة">صناعة</option>
                            <option value="طاقة">طاقة</option>
                            <option value="مياه وصرف صحي">مياه وصرف صحي</option>
                            <option value="نقل ومواصلات">نقل ومواصلات</option>
                            <option value="مقاولات">مقاولات</option>
                            <option value="أخرى">أخرى</option>
                        </select>
                    </div>
                    <div>
                        <label><i class="fas fa-phone"></i> رقم الهاتف</label>
                        <input type="tel" name="phone" id="phone" placeholder="مثال: +249123456789"/>
                    </div>
                    <div>
                        <label><i class="fas fa-envelope"></i> البريد الإلكتروني</label>
                        <input type="email" name="email" id="email" placeholder="example@company.com"/>
                    </div>
                    <div>
                        <label><i class="fab fa-whatsapp"></i> واتساب</label>
                        <input type="tel" name="whatsapp" id="whatsapp" placeholder="مثال: +249123456789"/>
                    </div>
                    <div>
                        <label><i class="fas fa-toggle-on"></i> حالة العميل *</label>
                        <select name="status" id="status" required>
                            <option value="نشط" selected>نشط ✅</option>
                            <option value="متوقف">متوقف ⏸</option>
                        </select>
                    </div>
                    <button type="submit">
                        <i class="fas fa-save"></i> حفظ العميل
                    </button>
                </div>
            </div>
        </div>
    </form>

    <div class="card">
        <div class="card-header">
            <h5><i class="fas fa-list"></i> جميع العملاء</h5>
        </div>
        <div class="card-body">
            <div class="row" style="margin-bottom: 16px;">
                <div class="col-md-4 col-sm-6" style="margin-bottom:10px;">
                    <label for="filterEntityType" style="font-weight:600;">فلتر نوع الكيان</label>
                    <select id="filterEntityType" class="form-control">
                        <option value="">الكل</option>
                    </select>
                </div>
                <div class="col-md-4 col-sm-6" style="margin-bottom:10px;">
                    <label for="filterSectorCategory" style="font-weight:600;">فلتر تصنيف القطاع</label>
                    <select id="filterSectorCategory" class="form-control">
                        <option value="">الكل</option>
                    </select>
                </div>
            </div>
            <div class="table-container">
                <table id="clientsTable" class="display">
                    <thead>
                        <tr>
                            <th width="100"><i class="fas fa-barcode"></i> كود العميل</th>
                            <th><i class="fas fa-user"></i> اسم العميل</th>
                            <th><i class="fas fa-building"></i> نوع الكيان</th>
                            <th><i class="fas fa-industry"></i> تصنيف القطاع</th>
                            <th><i class="fas fa-project-diagram"></i> عدد المشاريع</th>
                            <th><i class="fas fa-phone"></i> الهاتف</th>
                            <th><i class="fas fa-toggle-on"></i> الحالة</th>
                            <th><i class="fas fa-cogs"></i> إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        foreach ($clients_rows as $row) {
                            $client_name_cell = "<a class='client-name-link' href='../Projects/projects.php?client_id=" . urlencode($row['id']) . "'>" . clients_e($row['client_name']) . "</a>";
                            if (intval($row['projects_count']) === 0) {
                                $client_name_cell .= " <span class='link-alert-chip' title='العميل ليس مشترك في مشروع'><i class='fas fa-exclamation-triangle'></i>تنبيه</span>";
                            }

                            echo "<tr>";
                            echo "<td><strong style='font-family:monospace;letter-spacing:.03em'>" . clients_e($row['client_code']) . "</strong></td>";
                            echo "<td>" . $client_name_cell . "</td>";
                            echo "<td>" . clients_e($row['entity_type']) . "</td>";
                            echo "<td>" . clients_e($row['sector_category']) . "</td>";
                            echo "<td><span class='status-active' style='display:inline-flex;align-items:center;gap:5px'><i class='fas fa-briefcase'></i> " . intval($row['projects_count']) . "</span></td>";
                            echo "<td>" . clients_e($row['phone']) . "</td>";

                            // عرض الحالة بألوان
                            if ($row['status'] == 'نشط') {
                                echo "<td><span class='status-active'><i class='fas fa-check-circle'></i> نشط</span></td>";
                            } else {
                                echo "<td><span class='status-inactive'><i class='fas fa-times-circle'></i> متوقف</span></td>";
                            }

                            // أزرار الإجراءات
                            echo "<td>
                                <div class='action-btns'>
                                    <a href='javascript:void(0)' 
                                       class='action-btn view viewClientBtn' 
                                       data-id='"       . $row['id']                                                       . "'
                                       data-code='"     . clients_e($row['client_code'])                                   . "'
                                       data-name='"     . clients_e($row['client_name'])                                   . "'
                                       data-entity='"   . clients_e($row['entity_type'])                                   . "'
                                       data-sector='"   . clients_e($row['sector_category'])                               . "'
                                       data-phone='"    . clients_e($row['phone'])                                         . "'
                                       data-email='"    . clients_e($row['email'])                                         . "'
                                       data-whatsapp='" . clients_e($row['whatsapp'])                                      . "'
                                       data-status='"   . clients_e($row['status'])                                        . "'
                                       data-projects-count='" . intval($row['projects_count'])                              . "'
                                       data-created='"  . clients_e(isset($row['creator_name']) ? $row['creator_name'] : 'غير محدد') . "'
                                       title='عرض التفاصيل'>
                                        <i class='fas fa-eye'></i>
                                    </a>";
                                    
                                    if ($can_edit) {
                                        echo "<a href='javascript:void(0)' 
                                           class='action-btn edit editClientBtn' 
                                           data-id='"       . $row['id']                        . "'
                                           data-code='"     . clients_e($row['client_code'])    . "'
                                           data-name='"     . clients_e($row['client_name'])    . "'
                                           data-entity='"   . clients_e($row['entity_type'])    . "'
                                           data-sector='"   . clients_e($row['sector_category']). "'
                                           data-phone='"    . clients_e($row['phone'])           . "'
                                           data-email='"    . clients_e($row['email'])           . "'
                                           data-whatsapp='" . clients_e($row['whatsapp'])        . "'
                                           data-status='"   . clients_e($row['status'])          . "'
                                           title='تعديل'>
                                            <i class='fas fa-edit'></i>
                                        </a>";
                                    }
                                    
                                    if ($can_delete) {
                                        echo "<a href='?delete_id=" . urlencode($row['id']) . "&csrf_token=" . urlencode($clients_csrf_token) . "' 
                                           class='action-btn delete' 
                                           onclick='return confirm(\"هل أنت متأكد من حذف هذا العميل؟\")' 
                                           title='حذف'>
                                            <i class='fas fa-trash-alt'></i>
                                        </a>";
                                    }
                                    
                            echo "</div>
                            </td>";
                            echo "</tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ══ Modal استيراد من Excel ════════════════════════════════════════════════ -->
<div id="importExcelModal" class="modal">
    <div class="modal-content" style="max-width: 620px;">
        <div class="modal-header">
            <h5><i class="fas fa-file-upload"></i> استيراد عملاء من Excel / CSV</h5>
            <button class="close-modal" onclick="closeImportModal()">&times;</button>
        </div>
        <form id="importExcelForm" enctype="multipart/form-data">
            <div class="modal-body">
                <!-- معلومات الحقول -->
                <div style="background:#eef6ff;border:1px solid #bfdbfe;padding:14px 16px;border-radius:8px;margin-bottom:16px;font-size:.82rem;">
                    <div style="font-weight:700;color:#1e3a5f;margin-bottom:8px;font-size:.88rem;">
                        <i class="fas fa-table" style="color:#2563eb;"></i> &nbsp;ترتيب الأعمدة في الملف:
                    </div>
                    <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:4px 16px;color:#374151;">
                        <div><span style="color:#dc2626;font-weight:700;">A</span> — كود العميل <span style="color:#dc2626;">(مطلوب)</span></div>
                        <div><span style="color:#dc2626;font-weight:700;">B</span> — اسم العميل <span style="color:#dc2626;">(مطلوب)</span></div>
                        <div><span style="color:#6b7280;">C</span> — نوع الكيان</div>
                        <div><span style="color:#6b7280;">D</span> — تصنيف القطاع</div>
                        <div><span style="color:#6b7280;">E</span> — رقم الهاتف</div>
                        <div><span style="color:#6b7280;">F</span> — البريد الإلكتروني</div>
                        <div><span style="color:#6b7280;">G</span> — واتساب</div>
                        <div><span style="color:#6b7280;">H</span> — الحالة (نشط / متوقف)</div>
                    </div>
                    <div style="margin-top:10px;padding-top:8px;border-top:1px solid #bfdbfe;color:#4b5563;">
                        <i class="fas fa-lightbulb" style="color:#d97706;"></i>
                        حمّل <a href="download_clients_template.php" style="color:#2563eb;font-weight:600;" target="_blank">نموذج Excel</a>
                        أو <a href="download_clients_template_csv.php" style="color:#2563eb;font-weight:600;" target="_blank">نموذج CSV</a>
                        لمعرفة الترتيب الصحيح.
                    </div>
                </div>

                <div class="form-group-modal">
                    <label style="font-weight:600;margin-bottom:6px;display:block;">
                        <i class="fas fa-file-excel" style="color:#16a34a;"></i> اختر ملف Excel أو CSV
                    </label>
                    <input type="file" id="excel_file" name="excel_file" accept=".xlsx,.xls,.csv" required
                        style="padding:14px;border:2px dashed rgba(22,163,74,.4);border-radius:8px;
                               background:rgba(22,163,74,.04);cursor:pointer;width:100%;
                               box-sizing:border-box;font-size:.9rem;">
                    <small style="color:#6b7280;margin-top:4px;display:block;">
                        الصيغ المدعومة: .xlsx, .xls, .csv &nbsp;|&nbsp; الحد الأقصى: 1000 عميل / 5 ميجابايت
                    </small>
                </div>

                <div id="importProgress" style="display:none;margin-top:16px;text-align:center;
                     background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:20px;">
                    <i class="fas fa-spinner fa-spin" style="font-size:1.8rem;color:#2563eb;"></i>
                    <p style="margin:10px 0 0;color:#1e40af;font-weight:700;">جاري معالجة الملف والاستيراد...</p>
                </div>

                <div id="importResult" style="display:none;margin-top:16px;"></div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn-modal btn-modal-save"
                    style="background:linear-gradient(135deg,#064e3b,#059669)!important;">
                    <i class="fas fa-upload"></i> رفع واستيراد
                </button>
                <button type="button" class="btn-modal btn-modal-cancel" onclick="closeImportModal()">
                    <i class="fas fa-times"></i> إلغاء
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ══ Modal عرض العميل ═══════════════════════════════════════════════════════ -->
<div id="viewClientModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h5><i class="fas fa-eye"></i> عرض بيانات العميل</h5>
            <button class="close-modal" onclick="closeViewModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="view-modal-body">
                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-barcode"></i> كود العميل</div>
                    <div class="view-item-value" id="view_client_code">-</div>
                </div>
                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-user"></i> اسم العميل</div>
                    <div class="view-item-value" id="view_client_name">-</div>
                </div>
                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-building"></i> نوع الكيان</div>
                    <div class="view-item-value" id="view_entity_type">-</div>
                </div>
                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-industry"></i> تصنيف القطاع</div>
                    <div class="view-item-value" id="view_sector_category">-</div>
                </div>
                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-project-diagram"></i> عدد المشاريع المرتبطة</div>
                    <div class="view-item-value" id="view_projects_count">0</div>
                </div>
                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-phone"></i> الهاتف</div>
                    <div class="view-item-value" id="view_phone">-</div>
                </div>
                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-envelope"></i> البريد الإلكتروني</div>
                    <div class="view-item-value" id="view_email">-</div>
                </div>
                <div class="view-item">
                    <div class="view-item-label"><i class="fab fa-whatsapp"></i> واتساب</div>
                    <div class="view-item-value" id="view_whatsapp">-</div>
                </div>
                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-toggle-on"></i> الحالة</div>
                    <div class="view-item-value" id="view_status">-</div>
                </div>
                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-user-plus"></i> أضيف بواسطة</div>
                    <div class="view-item-value" id="view_created_by">-</div>
                </div>
            </div>

            <hr style="margin:20px 0;" />
            <h6 style="font-weight:700;margin-bottom:12px;"><i class="fas fa-folder-open"></i> المشاريع المرتبطة بالعميل</h6>

            <div id="clientProjectsSummary" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:12px;">
                <span class="status-active" style="padding:6px 10px;">المشاريع: <strong id="summary_projects_count">0</strong></span>
                <span class="status-active" style="padding:6px 10px;">المناجم: <strong id="summary_mines_count">0</strong></span>
                <span class="status-active" style="padding:6px 10px;">الموردون: <strong id="summary_suppliers_count">0</strong></span>
                <span class="status-active" style="padding:6px 10px;">الآليات: <strong id="summary_equipments_count">0</strong></span>
                <span class="status-active" style="padding:6px 10px;">المشغلون: <strong id="summary_operators_count">0</strong></span>
            </div>

            <div id="clientProjectsLoading" style="display:none;color:#2563eb;font-weight:600;margin-bottom:8px;">
                <i class="fas fa-spinner fa-spin"></i> جاري تحميل بيانات المشاريع...
            </div>

            <div class="table-responsive">
                <table class="table table-bordered table-sm" style="margin-bottom:0;">
                    <thead>
                        <tr>
                            <th>المشروع</th>
                            <th>المناجم</th>
                            <th>الموردون</th>
                            <th>الآليات</th>
                            <th>الآليات العاملة</th>
                            <th>الآليات المتوقفة</th>
                            <th>المشغلون</th>
                            <th>المشغلون النشطون</th>
                            <th>المشغلون المتوقفون</th>
                        </tr>
                    </thead>
                    <tbody id="clientProjectsTableBody">
                        <tr>
                            <td colspan="9" style="text-align:center;color:#6b7280;">لا توجد بيانات بعد</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="modal-footer">
            <?php if ($can_edit): ?>
                <button type="button" class="btn-modal btn-modal-save editClientBtn" id="viewEditBtn">
                    <i class="fas fa-edit"></i> تعديل البيانات
                </button>
            <?php endif; ?>
            <button type="button" class="btn-modal btn-modal-cancel" onclick="closeViewModal()">
                <i class="fas fa-times"></i> إغلاق
            </button>
        </div>
    </div>
</div>

<script src="../includes/js/jquery-3.7.1.main.js"></script>
<script src="/ems/assets/vendor/datatables/js/jquery.dataTables.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/dataTables.responsive.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/dataTables.buttons.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/buttons.html5.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/buttons.print.min.js"></script>
<script src="/ems/assets/vendor/jszip/jszip.min.js"></script>
<script src="/ems/assets/vendor/pdfmake/pdfmake.min.js"></script>
<script src="/ems/assets/vendor/pdfmake/vfs_fonts.js"></script>

<script>
    $(document).ready(function () {
        // تهيئة جدول العملاء بالعربية
        const clientsTable = $('#clientsTable').DataTable({
            language: {
                url: '/ems/assets/i18n/datatables/ar.json'
            }
        });

        function fillFilterOptions(columnIndex, selectId) {
            const select = $(selectId);
            const currentValue = select.val();
            const values = [];

            clientsTable.column(columnIndex).data().each(function (value) {
                const text = $('<div>').html(value).text().trim();
                if (text !== '' && values.indexOf(text) === -1) {
                    values.push(text);
                }
            });

            values.sort();
            values.forEach(function (val) {
                select.append('<option value="' + val.replace(/"/g, '&quot;') + '">' + val + '</option>');
            });

            if (currentValue) {
                select.val(currentValue);
            }
        }

        fillFilterOptions(2, '#filterEntityType');
        fillFilterOptions(3, '#filterSectorCategory');

        $('#filterEntityType').on('change', function () {
            const value = $.fn.dataTable.util.escapeRegex($(this).val());
            clientsTable.column(2).search(value ? '^' + value + '$' : '', true, false).draw();
        });

        $('#filterSectorCategory').on('change', function () {
            const value = $.fn.dataTable.util.escapeRegex($(this).val());
            clientsTable.column(3).search(value ? '^' + value + '$' : '', true, false).draw();
        });
    });

    // إظهار / إخفاء فورم الإضافة
    $('#toggleForm').on('click', function () {
        $('#clientForm').slideToggle(400);
        // إعادة تعيين الفورم عند الإغلاق
        if (!$('#clientForm').is(':visible')) {
            $('#clientForm')[0].reset();
            $('#client_id').val('');
        }
    });

    // تعديل عميل — تحميل بياناته في الفورم
    $(document).on('click', '.editClientBtn', function () {
        const clientData = {
            id:       $(this).data('id'),
            code:     $(this).data('code'),
            name:     $(this).data('name'),
            entity:   $(this).data('entity'),
            sector:   $(this).data('sector'),
            phone:    $(this).data('phone'),
            email:    $(this).data('email'),
            whatsapp: $(this).data('whatsapp'),
            status:   $(this).data('status')
        };

        // ملء الفورم بالبيانات
        $('#client_id').val(clientData.id);
        $('#client_code').val(clientData.code);
        $('#client_name').val(clientData.name);
        $('#entity_type').val(clientData.entity);
        $('#sector_category').val(clientData.sector);
        $('#phone').val(clientData.phone);
        $('#email').val(clientData.email);
        $('#whatsapp').val(clientData.whatsapp);
        $('#status').val(clientData.status);

        // عرض الفورم إذا كان مخفياً
        if (!$('#clientForm').is(':visible')) {
            $('#clientForm').slideDown(400);
        }

        // التمرير إلى الفورم
        $('html, body').animate({
            scrollTop: $('#clientForm').offset().top - 100
        }, 500);
    });

    // ════════════════════════════════════════════════
    // Modal عرض تفاصيل العميل
    // ════════════════════════════════════════════════
    $(document).on('click', '.viewClientBtn', function () {
        const clientData = {
            id:       $(this).data('id'),
            code:     $(this).data('code'),
            name:     $(this).data('name'),
            entity:   $(this).data('entity'),
            sector:   $(this).data('sector'),
            phone:    $(this).data('phone'),
            email:    $(this).data('email'),
            whatsapp: $(this).data('whatsapp'),
            status:   $(this).data('status'),
            projectsCount: $(this).data('projects-count'),
            created:  $(this).data('created')
        };

        // ملء بيانات العرض
        $('#view_client_code').text(clientData.code    || '-');
        $('#view_client_name').text(clientData.name    || '-');
        $('#view_entity_type').text(clientData.entity  || '-');
        $('#view_sector_category').text(clientData.sector  || '-');
        $('#view_phone').text(clientData.phone         || '-');
        $('#view_email').text(clientData.email         || '-');
        $('#view_whatsapp').text(clientData.whatsapp   || '-');
        $('#view_projects_count').text(clientData.projectsCount || 0);

        // عرض الحالة بألوان
        let statusHtml = '';
        if (clientData.status === 'نشط') {
            statusHtml = '<span class="status-active"><i class="fas fa-check-circle"></i> نشط</span>';
        } else {
            statusHtml = '<span class="status-inactive"><i class="fas fa-times-circle"></i> متوقف</span>';
        }
        $('#view_status').html(statusHtml);
        $('#view_created_by').text(clientData.created  || '-');

        // تحضير زر التعديل داخل المودال
        const editBtn = $('#viewEditBtn');
        editBtn.data('id',       clientData.id);
        editBtn.data('code',     clientData.code);
        editBtn.data('name',     clientData.name);
        editBtn.data('entity',   clientData.entity);
        editBtn.data('sector',   clientData.sector);
        editBtn.data('phone',    clientData.phone);
        editBtn.data('email',    clientData.email);
        editBtn.data('whatsapp', clientData.whatsapp);
        editBtn.data('status',   clientData.status);

        loadClientProjectsStats(clientData.id);

        $('#viewClientModal').fadeIn(300);
    });

    function setProjectsSummary(projects) {
        let mines = 0;
        let suppliers = 0;
        let equipments = 0;
        let operators = 0;

        projects.forEach(function (project) {
            mines += parseInt(project.mines_count || 0, 10);
            suppliers += parseInt(project.suppliers_count || 0, 10);
            equipments += parseInt(project.equipments_total || 0, 10);
            operators += parseInt(project.operators_total || 0, 10);
        });

        $('#summary_projects_count').text(projects.length);
        $('#summary_mines_count').text(mines);
        $('#summary_suppliers_count').text(suppliers);
        $('#summary_equipments_count').text(equipments);
        $('#summary_operators_count').text(operators);
    }

    function renderClientProjects(projects) {
        const tbody = $('#clientProjectsTableBody');
        tbody.empty();

        if (!projects.length) {
            tbody.append('<tr><td colspan="9" style="text-align:center;color:#6b7280;">لا توجد مشاريع مرتبطة بهذا العميل</td></tr>');
            setProjectsSummary([]);
            return;
        }

        projects.forEach(function (project) {
            const projectLabel = (project.name || '-') + (project.project_code ? ' (' + project.project_code + ')' : '');
            const rowHtml = '<tr>' +
                '<td>' + projectLabel + '</td>' +
                '<td>' + (project.mines_count || 0) + '</td>' +
                '<td>' + (project.suppliers_count || 0) + '</td>' +
                '<td>' + (project.equipments_total || 0) + '</td>' +
                '<td style="color:#065f46;font-weight:700;">' + (project.equipments_working || 0) + '</td>' +
                '<td style="color:#b91c1c;font-weight:700;">' + (project.equipments_stopped || 0) + '</td>' +
                '<td>' + (project.operators_total || 0) + '</td>' +
                '<td style="color:#065f46;font-weight:700;">' + (project.operators_working || 0) + '</td>' +
                '<td style="color:#b91c1c;font-weight:700;">' + (project.operators_stopped || 0) + '</td>' +
            '</tr>';

            tbody.append(rowHtml);
        });

        setProjectsSummary(projects);
    }

    function loadClientProjectsStats(clientId) {
        $('#clientProjectsLoading').show();
        $('#clientProjectsTableBody').html('<tr><td colspan="9" style="text-align:center;color:#6b7280;">جاري التحميل...</td></tr>');

        $.ajax({
            url: 'clients.php',
            type: 'GET',
            dataType: 'json',
            data: {
                ajax: 'client_projects',
                client_id: clientId
            },
            success: function (response) {
                $('#clientProjectsLoading').hide();
                if (!response || !response.success) {
                    $('#clientProjectsTableBody').html('<tr><td colspan="9" style="text-align:center;color:#b91c1c;">تعذر تحميل بيانات المشاريع</td></tr>');
                    setProjectsSummary([]);
                    return;
                }

                renderClientProjects(response.projects || []);
            },
            error: function () {
                $('#clientProjectsLoading').hide();
                $('#clientProjectsTableBody').html('<tr><td colspan="9" style="text-align:center;color:#b91c1c;">حدث خطأ أثناء تحميل بيانات المشاريع</td></tr>');
                setProjectsSummary([]);
            }
        });
    }

    // إغلاق مودال العرض
    function closeViewModal() {
        $('#viewClientModal').fadeOut(300);
    }

    // إغلاق المودالات عند الضغط خارجها
    $(window).on('click', function (e) {
        if (e.target.id === 'viewClientModal') {
            closeViewModal();
        }
    });

    // إغلاق المودال عند ضغط ESC
    $(document).on('keydown', function (e) {
        if (e.key === 'Escape' && $('#viewClientModal').is(':visible')) {
            closeViewModal();
        }
    });

    // تعديل من مودال العرض — تحميل البيانات في الفورم
    $('#viewEditBtn').on('click', function () {
        const clientData = {
            id:       $(this).data('id'),
            code:     $(this).data('code'),
            name:     $(this).data('name'),
            entity:   $(this).data('entity'),
            sector:   $(this).data('sector'),
            phone:    $(this).data('phone'),
            email:    $(this).data('email'),
            whatsapp: $(this).data('whatsapp'),
            status:   $(this).data('status')
        };

        closeViewModal();

        // ملء الفورم بالبيانات
        $('#client_id').val(clientData.id);
        $('#client_code').val(clientData.code);
        $('#client_name').val(clientData.name);
        $('#entity_type').val(clientData.entity);
        $('#sector_category').val(clientData.sector);
        $('#phone').val(clientData.phone);
        $('#email').val(clientData.email);
        $('#whatsapp').val(clientData.whatsapp);
        $('#status').val(clientData.status);

        // عرض الفورم إذا كان مخفياً
        if (!$('#clientForm').is(':visible')) {
            $('#clientForm').slideDown(400);
        }

        // التمرير إلى الفورم
        $('html, body').animate({
            scrollTop: $('#clientForm').offset().top - 100
        }, 500);
    });
</script>

<script>
    // ════════════════════════════════════════════════
    // Modal استيراد من Excel
    // ════════════════════════════════════════════════

    // فتح مودال الاستيراد
    $('#openImportModal').on('click', function () {
        $('#importExcelModal').fadeIn(300);
    });

    // إغلاق مودال الاستيراد
    function closeImportModal() {
        $('#importExcelModal').fadeOut(300);
        $('#importExcelForm')[0].reset();
        $('#importProgress').hide();
        $('#importResult').hide();
    }

    // إغلاق عند الضغط خارج المودال
    $(window).on('click', function (e) {
        if (e.target.id === 'importExcelModal') {
            closeImportModal();
        }
    });

    // معالجة رفع ملف Excel
    $('#importExcelForm').on('submit', function (e) {
        e.preventDefault();

        const fileInput = $('#excel_file')[0];
        if (!fileInput.files.length) {
            alert('الرجاء اختيار ملف Excel');
            return;
        }

        const formData = new FormData();
        formData.append('excel_file', fileInput.files[0]);

        $('#importProgress').show();
        $('#importResult').hide();
        $(this).find('[type="submit"]').prop('disabled', true);

        $.ajax({
            url:         'import_clients_excel.php',
            type:        'POST',
            data:        formData,
            processData: false,
            contentType: false,
            dataType:    'json',
            success: function (response) {
                $('#importProgress').hide();
                $('#importExcelForm [type="submit"]').prop('disabled', false);

                let resultHtml = '';

                if (response.success) {
                    resultHtml = '<div style="padding:16px;border-radius:8px;border:1.5px solid #a7f3d0;background:#ecfdf5;color:#065f46;">';
                    resultHtml += '<h6 style="font-weight:700;margin-bottom:10px;font-size:.95rem;">'
                               +  '<i class="fas fa-check-circle" style="color:#059669;"></i> &nbsp;تم الاستيراد بنجاح</h6>';
                    resultHtml += '<p style="margin:4px 0;">✅ العملاء المضافون: <strong>' + response.added + '</strong></p>';

                    if (response.skipped > 0) {
                        resultHtml += '<p style="margin:4px 0;color:#92400e;">⚠️ تم تخطي: <strong>' + response.skipped + '</strong> (مكرر أو بيانات ناقصة)</p>';
                    }

                    if (response.errors && response.errors.length > 0) {
                        resultHtml += '<details style="margin-top:8px;">';
                        resultHtml += '<summary style="cursor:pointer;font-weight:600;color:#b45309;">تفاصيل الأخطاء (' + response.errors.length + ')</summary>';
                        resultHtml += '<ul style="margin:6px 0 0;padding-right:20px;font-size:.82rem;max-height:150px;overflow-y:auto;">';
                        response.errors.forEach(function (err) {
                            resultHtml += '<li style="margin:3px 0;">' + $('<span>').text(err).html() + '</li>';
                        });
                        resultHtml += '</ul></details>';
                    }

                    if (response.added > 0) {
                        resultHtml += '<p style="margin-top:10px;font-size:.82rem;color:#6b7280;">سيتم تحديث الصفحة خلال 3 ثوان...</p>';
                        setTimeout(function () { location.reload(); }, 3000);
                    }

                    resultHtml += '</div>';
                } else {
                    resultHtml = '<div style="padding:16px;border-radius:8px;border:1.5px solid #fecaca;background:#fef2f2;color:#991b1b;">';
                    resultHtml += '<h6 style="font-weight:700;margin-bottom:8px;"><i class="fas fa-times-circle"></i> &nbsp;فشل الاستيراد</h6>';
                    resultHtml += '<p style="margin:0;">' + $('<span>').text(response.message).html() + '</p>';
                    resultHtml += '</div>';
                }

                $('#importResult').html(resultHtml).fadeIn(300);
            },
            error: function (xhr) {
                $('#importProgress').hide();
                $('#importExcelForm [type="submit"]').prop('disabled', false);

                let errorMsg = 'حدث خطأ أثناء رفع الملف. الرجاء المحاولة مرة أخرى.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                } else if (xhr.responseText && xhr.responseText.trim() !== '') {
                    // عرض أول جزء من استجابة الخادم غير-JSON للمساعدة في التشخيص
                    errorMsg = xhr.responseText.trim().substring(0, 300);
                }

                const errorHtml = '<div style="padding:16px;border-radius:8px;background:#fef2f2;color:#991b1b;border:1.5px solid #fecaca;">'
                    + '<h6 style="font-weight:700;margin-bottom:8px;"><i class="fas fa-times-circle"></i> &nbsp;خطأ في الرفع</h6>'
                    + '<p style="margin:0 0 8px;">' + $('<span>').text(errorMsg).html() + '</p>'
                    + '<small style="color:#6b7280;">تأكد من: صيغة الملف (xlsx/csv) · الحجم (أقل من 5MB) · البيانات الصحيحة</small>'
                    + '</div>';


                $('#importResult').html(errorHtml).fadeIn(300);
            }
        });
    });
</script>

</body>
</html>

