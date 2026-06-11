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
            'ا' => 'ا',
            'ب' => 'ب',
            'ت' => 'ت',
            'ث' => 'ث',
            'ج' => 'ج',
            'ح' => 'ح',
            'خ' => 'خ',
            'د' => 'د',
            'ذ' => 'ذ',
            'ر' => 'ر',
            'ز' => 'ز',
            'س' => 'س',
            'ش' => 'ش',
            'ص' => 'ص',
            'ض' => 'ض',
            'ط' => 'ط',
            'ظ' => 'ظ',
            'ع' => 'ع',
            'غ' => 'غ',
            'ف' => 'ف',
            'ق' => 'ق',
            'ك' => 'ك',
            'ل' => 'ل',
            'م' => 'م',
            'ن' => 'ن',
            'ه' => 'ه',
            'و' => 'و',
            'ي' => 'ي',
            'ى' => 'ى',
            'ة' => 'ة',
            'ء' => 'ء',
            'أ' => 'أ',
            'إ' => 'إ',
            'آ' => 'آ',
            'ؤ' => 'ؤ',
            'ئ' => 'ئ',
            '،' => '،',
            '؛' => '؛',
            '؟' => '؟',
            '✅' => '✅',
            '❌' => '❌',
            '⏸' => '⏸',
            'ðŸ”' => 'ðŸ”',
            'ðŸ‘‹' => 'ðŸ‘‹',
            'ðŸš€' => 'ðŸš€',
            'ðŸ†' => 'ðŸ†'
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
        $safeCol = preg_replace('/[^a-zA-Z0-9_]/', '', $columnName);
        $sql = "SHOW COLUMNS FROM " . $safeTable . " LIKE '" . mysqli_real_escape_string($conn, $safeCol) . "'";
        $res = @mysqli_query($conn, $sql);

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

$project_has_client_id = clients_table_has_column($conn, 'project', 'client_id');
$project_has_company_client_id = clients_table_has_column($conn, 'project', 'company_client_id');
$project_has_company_id = clients_table_has_column($conn, 'project', 'company_id');
$project_has_is_deleted = clients_table_has_column($conn, 'project', 'is_deleted');
$project_has_deleted_at = clients_table_has_column($conn, 'project', 'deleted_at');

$operations_has_company_id = clients_table_has_column($conn, 'operations', 'company_id');
$equipment_drivers_has_company_id = clients_table_has_column($conn, 'equipment_drivers', 'company_id');

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
$scope_clients_sql = clients_build_scope_sql($company_id, $clients_has_company_id, 'cc');
$scope_clients_update_sql = clients_build_scope_sql($company_id, $clients_has_company_id, '');
$not_deleted_cc_sql = clients_not_deleted_sql('cc', $clients_has_is_deleted, $clients_has_deleted_at);
$not_deleted_plain_sql = clients_not_deleted_sql('', $clients_has_is_deleted, $clients_has_deleted_at);

$scope_project_sql = clients_build_scope_sql($company_id, $project_has_company_id, 'p');
$not_deleted_project_sql = clients_not_deleted_sql('p', $project_has_is_deleted, $project_has_deleted_at);
$project_active_status_sql = "(
    p.status = 1
    OR p.status = '1'
    OR TRIM(p.status) = 'نشط'
    OR TRIM(LOWER(p.status)) = 'active'
    OR TRIM(LOWER(p.status)) = 'true'
)";

$projects_count_select_sql = '0';
$projects_active_count_select_sql = '0';
$projects_inactive_count_select_sql = '0';
if ($project_client_link_column !== '') {
    $projects_count_select_sql = "(
        SELECT COUNT(*)
        FROM project p
        WHERE p.$project_client_link_column = cc.id
          AND $scope_project_sql
          AND $not_deleted_project_sql
    )";

    $projects_active_count_select_sql = "(
        SELECT COUNT(*)
        FROM project p
        WHERE p.$project_client_link_column = cc.id
          AND $scope_project_sql
          AND $not_deleted_project_sql
          AND $project_active_status_sql
    )";

    $projects_inactive_count_select_sql = "(
        (
            SELECT COUNT(*)
            FROM project p
            WHERE p.$project_client_link_column = cc.id
              AND $scope_project_sql
              AND $not_deleted_project_sql
        )
        -
        (
            SELECT COUNT(*)
            FROM project p
            WHERE p.$project_client_link_column = cc.id
              AND $scope_project_sql
              AND $not_deleted_project_sql
              AND $project_active_status_sql
        )
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
$last_code_scope = $clients_has_company_id ? "AND company_id = $company_id" : '';
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
    $last_num = intval(substr($last_code_row['client_code'], 4)); // بعد "CLT-"
    $next_num = $last_num + 1;
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
$module_info = $module_result ? $module_result->fetch_assoc() : null;
$module_id = $module_info ? $module_info['id'] : null;

// تحديد صلاحيات المستخدم على هذه الوحدة
$can_view = false;
$can_add = false;
$can_edit = false;
$can_delete = false;

if ($module_id) {
    $perms = get_module_permissions($conn, $module_id);
    $can_view = $perms['can_view'];
    $can_add = $perms['can_add'];
    $can_edit = $perms['can_edit'];
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
    $client_id = isset($_POST['client_id']) ? intval($_POST['client_id']) : 0;
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
    $status_raw = isset($_POST['status']) ? trim($_POST['status']) : '';
    $allowed_status = array('نشط', 'متوقف');
    if (!in_array($status_raw, $allowed_status, true)) {
        clients_redirect_with_msg('حالة العميل غير صالحة ❌');
    }

    // تنظيف البيانات المدخلة
    $client_code = mysqli_real_escape_string($conn, $client_code_raw);
    $client_name = mysqli_real_escape_string($conn, trim($_POST['client_name']));
    $entity_type = mysqli_real_escape_string($conn, trim($_POST['entity_type']));
    $sector_category = mysqli_real_escape_string($conn, trim($_POST['sector_category']));
    $phone = mysqli_real_escape_string($conn, trim($_POST['phone']));
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $whatsapp = mysqli_real_escape_string($conn, trim($_POST['whatsapp']));
    $status = mysqli_real_escape_string($conn, $status_raw);
    $created_by = intval($_SESSION['user']['id']);

    if ($client_id > 0) {
        // ── تعديل عميل موجود ────────────────────────────────────────────────

        // التحقق من ملكية العميل للشركة الحالية
        $owner_check_query = "SELECT cc.id FROM clients cc WHERE cc.id = $client_id AND $scope_clients_sql AND $not_deleted_cc_sql LIMIT 1";
        $owner_check_result = mysqli_query($conn, $owner_check_query);
        if (!$owner_check_result || mysqli_num_rows($owner_check_result) === 0) {
            clients_redirect_with_msg('لا يمكنك تعديل عميل لا يتبع لشركتك ❌');
        }

        // التحقق من عدم تكرار كود العميل
        $check_query = "SELECT cc.id FROM clients cc WHERE cc.client_code = '$client_code' AND cc.id != $client_id AND $scope_clients_sql AND $not_deleted_cc_sql";
        $check_result = mysqli_query($conn, $check_query);

        if ($check_result && mysqli_num_rows($check_result) > 0) {
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
            \App\Services\ActivityLogService::logUpdate(
                'clients',
                'clients',
                $client_id,
                null,
                ['client_code' => $client_code_raw, 'client_name' => trim($_POST['client_name'])]
            );
            clients_redirect_with_msg('تم تعديل العميل بنجاح ✅');
        } else {
            error_log('clients.php update failed: ' . mysqli_error($conn));
            clients_redirect_with_msg('حدث خطأ أثناء التعديل ❌');
        }

    } else {
        // ── إضافة عميل جديد ─────────────────────────────────────────────────

        // التحقق من عدم تكرار كود العميل
        $check_query = "SELECT cc.id FROM clients cc WHERE cc.client_code = '$client_code' AND $scope_clients_sql AND $not_deleted_cc_sql";
        $check_result = mysqli_query($conn, $check_query);

        if ($check_result && mysqli_num_rows($check_result) > 0) {
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
            $new_client_id = (int) mysqli_insert_id($conn);
            \App\Services\ActivityLogService::logCreate(
                'clients',
                'clients',
                $new_client_id,
                ['client_code' => $client_code_raw, 'client_name' => trim($_POST['client_name'])]
            );
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
    $delete_id = intval($_GET['delete_id']);
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
        $deleted_by = intval($_SESSION['user']['id']);
        $delete_set[] = "deleted_by = $deleted_by";
    }

    $soft_delete_query = "UPDATE clients SET " . implode(', ', $delete_set) . " WHERE id = $delete_id AND $scope_clients_update_sql";
    if ($clients_has_is_deleted) {
        $soft_delete_query .= " AND is_deleted = 0";
    } elseif ($clients_has_deleted_at) {
        $soft_delete_query .= " AND deleted_at IS NULL";
    }

    if (mysqli_query($conn, $soft_delete_query)) {
        \App\Services\ActivityLogService::logDelete(
            'clients',
            'clients',
            $delete_id
        );
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
    $client_check_res = mysqli_query($conn, $client_check_query);
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

    $projects_query = "
        SELECT
            p.id,
            p.name,
            p.project_code,
            p.status,
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
        $equipments_total = intval($project_row['equipments_total']);
        $equipments_working = intval($project_row['equipments_working']);
        $operators_total = intval($project_row['operators_total']);
        $operators_working = intval($project_row['operators_working']);

        $project_row['equipments_total'] = $equipments_total;
        $project_row['equipments_working'] = $equipments_working;
        $project_row['equipments_stopped'] = max(0, $equipments_total - $equipments_working);
        $project_row['operators_total'] = $operators_total;
        $project_row['operators_working'] = $operators_working;
        $project_row['operators_stopped'] = max(0, $operators_total - $operators_working);
        $project_row['suppliers_count'] = intval($project_row['suppliers_count']);

        $projects[] = $project_row;
    }

    echo json_encode(array('success' => true, 'projects' => $projects));
    exit();
}

// ══════════════════════════════════════════════════════════════════════════════
// بيانات العملاء + الإحصائيات العامة
// ══════════════════════════════════════════════════════════════════════════════
$clients_rows = array();

$clients_total_count = 0;
$clients_active_count = 0;
$clients_stopped_count = 0;
$clients_companies_count = 0;
$clients_individuals_count = 0;
$clients_unknown_entity_count = 0;
$clients_projects_total = 0;
$clients_projects_active_total = 0;
$clients_projects_inactive_total = 0;
$clients_without_projects = 0;

$sector_counts = array();

$clients_query = "SELECT cc.*, u.name as creator_name,
                         $projects_count_select_sql AS projects_count,
                         $projects_active_count_select_sql AS projects_active_count,
                         $projects_inactive_count_select_sql AS projects_inactive_count
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
        $projects_active_count_value = intval($row['projects_active_count']);
        $projects_inactive_count_value = intval($row['projects_inactive_count']);

        if ($projects_active_count_value + $projects_inactive_count_value !== $projects_count_value) {
            $projects_inactive_count_value = max(0, $projects_count_value - $projects_active_count_value);
        }

        $clients_projects_total += $projects_count_value;
        $clients_projects_active_total += $projects_active_count_value;
        $clients_projects_inactive_total += $projects_inactive_count_value;
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
$sector_mining_count = isset($sector_counts['تعدين']) ? intval($sector_counts['تعدين']) : 0;
$sector_contracting_count = isset($sector_counts['مقاولات']) ? intval($sector_counts['مقاولات']) : 0;
$sector_services_count = isset($sector_counts['خدمات']) ? intval($sector_counts['خدمات']) : 0;

arsort($sector_counts);

$page_title = "قائمة العملاء";
include("../inheader.php");
include('../insidebar.php');
?>

<div class="main clients-main ems-unified-page-shell">

    <?php
    // Unified page header (structure: includes/page_header.php · styling: ems.main.all.style.css)
    $header_title = 'إدارة العملاء';
    $header_icon = 'fas fa-users';
    $header_actions = array();
    if ($can_add) {
        $header_actions[] = array('id' => 'toggleForm', 'class' => 'add-btn', 'icon' => 'fa fa-solid fa-plus', 'label' => '', 'label_class' => 'clients-toggle-form-text');
    } else {
        $header_actions[] = array('tag' => 'button', 'class' => '', 'disabled' => true, 'icon' => 'fas fa-plus-circle', 'label' => 'إضافة (بدون صلاحيات)');
    }
    $header_actions[] = array('id' => 'toggleStats', 'class' => 'btn', 'title' => 'إظهار أو إخفاء الإحصائيات', 'icon' => 'fas fa-eye', 'label' => 'إظهار الإحصائيات', 'label_class' => 'clients-toggle-stats-text');
    // ── نظام Excel الموحّد (Unified Excel Framework) ──
    // يستبدل أزرار النموذج/التصدير/الاستيراد القديمة بالطبقة الموحّدة.
    // الملفات القديمة (download_*/import_*/export_*) تبقى كما هي دون كسر.
    require_once __DIR__ . '/../includes/excel_ui.php';
    foreach (ems_excel_header_actions('clients', 'العملاء', $can_add) as $__xlAction) {
        $header_actions[] = $__xlAction;
    }
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fa-solid fa-share', 'label' => '');
    include('../includes/page_header.php');
    ?>

    <?php if (!empty($_GET['msg'])):
        $isSuccess = strpos($_GET['msg'], '✅') !== false;
        ?>
        <div class="success-message <?= $isSuccess ? 'is-success' : 'is-error' ?>">
            <i class="fas <?= $isSuccess ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?php echo clients_e($_GET['msg']); ?>
        </div>
    <?php endif; ?>

    <div class="stats-section clients-hidden" id="clientsStatsSection">
        <div class="stats-grid">
            <div class="stats-card stats-primary">
                <div class="stats-icon"><i class="fas fa-users"></i></div>
                <div class="stats-value"><?php echo $clients_total_count; ?></div>
                <div class="stats-title">إجمالي العملاء</div>
            </div>
            <div class="stats-card stats-success">
                <div class="stats-icon"><i class="fas fa-user-check"></i></div>
                <div class="stats-value"><?php echo $clients_active_count; ?></div>
                <div class="stats-title">العملاء النشطون</div>
            </div>
            <div class="stats-card stats-danger">
                <div class="stats-icon"><i class="fas fa-user-slash"></i></div>
                <div class="stats-value"><?php echo $clients_stopped_count; ?></div>
                <div class="stats-title">العملاء المتوقفون</div>
            </div>
            <div class="stats-card stats-purple">
                <div class="stats-icon"><i class="fas fa-diagram-project"></i></div>
                <div class="stats-value"><?php echo $clients_projects_total; ?></div>
                <div class="stats-title">إجمالي المشاريع المرتبطة</div>
            </div>
            <div class="stats-card stats-success">
                <div class="stats-icon"><i class="fas fa-folder-open"></i></div>
                <div class="stats-value"><?php echo $clients_projects_active_total; ?></div>
                <div class="stats-title">المشاريع النشطة</div>
            </div>
            <div class="stats-card stats-danger">
                <div class="stats-icon"><i class="fas fa-folder"></i></div>
                <div class="stats-value"><?php echo $clients_projects_inactive_total; ?></div>
                <div class="stats-title clients-danger-text">المشاريع غير النشطة</div>
            </div>
            <div class="stats-card stats-orange">
                <div class="stats-icon"><i class="fas fa-building"></i></div>
                <div class="stats-value"><?php echo $clients_companies_count; ?></div>
                <div class="stats-title">عدد الشركات</div>
            </div>
            <div class="stats-card stats-cyan">
                <div class="stats-icon"><i class="fas fa-user"></i></div>
                <div class="stats-value"><?php echo $clients_individuals_count; ?></div>
                <div class="stats-title">عدد الأفراد</div>
            </div>
            <div class="stats-card stats-slate">
                <div class="stats-icon"><i class="fas fa-question-circle"></i></div>
                <div class="stats-value"><?php echo $clients_unknown_entity_count; ?></div>
                <div class="stats-title">كيان غير محدد</div>
            </div>
            <div class="stats-card stats-emerald">
                <div class="stats-icon"><i class="fas fa-link-slash"></i></div>
                <div class="stats-value"><?php echo $clients_without_projects; ?></div>
                <div class="stats-title">عملاء بلا مشاريع</div>
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
    <form id="clientForm" action="" method="post" class="allforms">
        <input type="hidden" name="client_id" id="client_id" value="">
        <input type="hidden" name="csrf_token" value="<?php echo clients_e($clients_csrf_token); ?>">
        <div class="card shadow-sm pu-form-card">
            <div class="card-header">
                <h5><i class="fas fa-edit"></i> <span id="formTitle">إضافة عميل جديد</span></h5>
            </div>
            <div class="card-body">
                <div class="form-grid">

                    <!-- ══ حقل الكود المولد تلقائياً (قراءة فقط - لا يُرسَل لقاعدة البيانات) ══ -->
                    <div id="generated_code_wrapper">
                        <label><i class="fas fa-magic"></i> كود العميل المولد <i
                                class="fas fa-info-circle clients-info-icon"></i></label>
                        <input type="text" id="generated_client_code" class="generated-code-field"
                            value="<?php echo clients_e($next_client_code); ?>" readonly tabindex="-1"
                            title="هذا الكود للعرض فقط، يمكنك نسخه واستخدامه في حقل كود العميل" />
                        <div class="generated-code-hint">

                        </div>
                    </div>
                    <!-- ══════════════════════════════════════════════════════ -->

                    <div>
                        <label><i class="fas fa-barcode"></i> كود العميل *</label>
                        <input type="text" name="client_code" id="client_code" placeholder="مثال: CL-001" required
                            pattern="[A-Za-z0-9-_]+" />
                    </div>
                    <div>
                        <label><i class="fas fa-user"></i> اسم العميل *</label>
                        <input type="text" name="client_name" id="client_name" placeholder="أدخل اسم العميل" required />
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
                        <input type="tel" name="phone" id="phone" placeholder="مثال: +249123456789" />
                    </div>
                    <div>
                        <label><i class="fas fa-envelope"></i> البريد الإلكتروني</label>
                        <input type="email" name="email" id="email" placeholder="example@company.com" />
                    </div>
                    <div>
                        <label><i class="fab fa-whatsapp"></i> واتساب</label>
                        <input type="tel" name="whatsapp" id="whatsapp" placeholder="مثال: +249123456789" />
                    </div>
                    <div>
                        <label><i class="fas fa-toggle-on"></i> حالة العميل *</label>
                        <select name="status" id="status" required>
                            <option value="نشط" selected>نشط ✅</option>
                            <option value="متوقف">متوقف ⏸</option>
                        </select>
                    </div>
                </div>
                <div class="pu-form-actions">
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-save"></i> <span id="submitBtnText">حفظ العميل</span>
                    </button>
                    <button type="button" id="clientFormCancelBtn" class="btn-cancel">
                        <i class="fas fa-times"></i> إلغاء
                    </button>
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
                <label><i class="fa fa-calendar"></i> نوع الكيان </label>
               <select id="filterEntityType" class="form-control" placeholder="">
                        <option value="">-- حدد نوع الكيان --</option>
                    </select>
            </div>
            <div class="filter-field">
                <label><i class="fa fa-calendar"></i> تصنيف القطاع</label>
                <select id="filterSectorCategory" class="form-control">
                        <option value=""> -- حدد تصنيف القطاع -- </option>
                    </select>
            </div>
            <!-- كرّر .filter-field بقدر ما تريد من الحقول -->
            <div class="filter-actions">
                <button type="button" class="btn-ok"><i class="fa fa-search"></i> تطبيق</button>
                <button type="button" class="btn-reset" title="إعادة تعيين"><i class="fa fa-rotate-right"></i></button>
            </div>

        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-container">
                <table id="clientsTable" class="display clients-table-nowrap no-datatable">
                    <thead>
                        <tr>
                            <th> إجراءات</th>
                            <th width="100"> كود العميل</th>
                            <th> اسم العميل</th>
                            <th> نوع الكيان</th>
                            <th> تصنيف القطاع</th>
                            <th> عدد المشاريع</th>
                            <th> الهاتف</th>
                            <th> الحالة</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        foreach ($clients_rows as $row) {
                            $client_name_cell = "<a class='client-name-link' href='client_profile.php?id=" . urlencode($row['id']) . "'>" . clients_e($row['client_name']) . "</a>";
                            if (intval($row['projects_count']) === 0) {
                                $client_name_cell .= " <span class='link-alert-chip' title='العميل ليس مشترك في مشروع'><i class='fas fa-exclamation-triangle'></i>تنبيه</span>";
                            }

                            echo "<tr>";
                            // أزرار الإجراءات في أول عمود
                            echo "<td>
                                <div class='action-btns'>
                                    <a href='javascript:void(0)'
                                       class='action-btn view viewClientBtn'
                                       data-id='" . $row['id'] . "'
                                       data-code='" . clients_e($row['client_code']) . "'
                                       data-name='" . clients_e($row['client_name']) . "'
                                       data-entity='" . clients_e($row['entity_type']) . "'
                                       data-sector='" . clients_e($row['sector_category']) . "'
                                       data-phone='" . clients_e($row['phone']) . "'
                                       data-email='" . clients_e($row['email']) . "'
                                       data-whatsapp='" . clients_e($row['whatsapp']) . "'
                                       data-status='" . clients_e($row['status']) . "'
                                       data-projects-count='" . intval($row['projects_count']) . "'
                                       data-created='" . clients_e(isset($row['creator_name']) ? $row['creator_name'] : 'غير محدد') . "'
                                       title='عرض التفاصيل'>
                                        <i class='fas fa-eye'></i>
                                    </a>";

                            if ($can_edit) {
                                echo "<a href='javascript:void(0)'
                                           class='action-btn edit editClientBtn'
                                           data-id='" . $row['id'] . "'
                                           data-code='" . clients_e($row['client_code']) . "'
                                           data-name='" . clients_e($row['client_name']) . "'
                                           data-entity='" . clients_e($row['entity_type']) . "'
                                           data-sector='" . clients_e($row['sector_category']) . "'
                                           data-phone='" . clients_e($row['phone']) . "'
                                           data-email='" . clients_e($row['email']) . "'
                                           data-whatsapp='" . clients_e($row['whatsapp']) . "'
                                           data-status='" . clients_e($row['status']) . "'
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
                            echo "<td><strong class='clients-code-cell'>" . clients_e($row['client_code']) . "</strong></td>";
                            echo "<td>" . $client_name_cell . "</td>";
                            echo "<td>" . clients_e($row['entity_type']) . "</td>";
                            echo "<td>" . clients_e($row['sector_category']) . "</td>";
                            $row_projects_total = intval($row['projects_count']);
                            $row_projects_active = intval(isset($row['projects_active_count']) ? $row['projects_active_count'] : 0);
                            $row_projects_inactive = intval(isset($row['projects_inactive_count']) ? $row['projects_inactive_count'] : 0);
                            if ($row_projects_active + $row_projects_inactive !== $row_projects_total) {
                                $row_projects_inactive = max(0, $row_projects_total - $row_projects_active);
                            }

                            echo "<td>";
                            echo "<span class='status-active clients-inline-pill' title='إجمالي المشاريع'><i class='fas fa-briefcase'></i> " . $row_projects_total . "</span> ";
                            echo "<span class='status-active clients-inline-pill' title='المشاريع النشطة'><i class='fas fa-folder-open'></i> " . $row_projects_active . "</span> ";
                            echo "<span class='status-inactive clients-inline-pill clients-inline-pill-danger' title='المشاريع غير النشطة'><i class='fas fa-folder'></i> " . $row_projects_inactive . "</span>";
                            echo "</td>";
                            echo "<td>" . clients_e($row['phone']) . "</td>";

                            // عرض الحالة بألوان
                            if ($row['status'] == 'نشط') {
                                echo "<td><span class='status-active'><i class='fa-regular fa-circle-check'></i> نشط</span></td>";
                            } else {
                                echo "<td><span class='status-inactive'><i class='fas fa-times-circle'></i> متوقف</span></td>";
                            }

                            echo "</tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- نافذة عرض العميل تُولَّد ديناميكياً عبر النظام الموحّد EmsDetailsModal (assets/js/ems-details-modal.js) -->

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
            // responsive (لا scrollX) لتوحيد الشكل مع جدول المشاريع وبقية الجداول:
            // scrollX كان يفصل الرأس عن الجسم (جدولا scrollHead/scrollBody) ويُظهر
            // شريطاً رمادياً أعلى الجسم. responsive يبقي الجدول قطعة واحدة موحّدة.
            responsive: true,
            autoWidth: false,
            // تعطيل حفظ الحالة: الفلاتر هنا تُدار عبر قوائم منفصلة (fillFilterOptions)
            // تملأ بحث الأعمدة، وحالة الـ <select> ليست جزءاً من حالة DataTables.
            // مع stateSave العام (performance-boost.js) كان بحث عمود محفوظ يُستعاد
            // فيُخفي كل الصفوف («مرشّحة من 4 ← 0») والقوائم تبدو فارغة. (ظهر في Edge)
            stateSave: false,
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

        fillFilterOptions(3, '#filterEntityType');
        fillFilterOptions(4, '#filterSectorCategory');

        $('#filterEntityType').on('change', function () {
            const value = $.fn.dataTable.util.escapeRegex($(this).val());
            clientsTable.column(3).search(value ? '^' + value + '$' : '', true, false).draw();
        });

        $('#filterSectorCategory').on('change', function () {
            const value = $.fn.dataTable.util.escapeRegex($(this).val());
            clientsTable.column(4).search(value ? '^' + value + '$' : '', true, false).draw();
        });
    });

    // إظهار / إخفاء فورم الإضافة + إظهار / إخفاء الإحصائيات
    const formToggleBtn = $('#toggleForm');
    const clientForm = $('#clientForm');
    const formTitle = $('#formTitle');
    const submitBtnText = $('#submitBtnText');
    const generatedCodeWrapper = $('#generated_code_wrapper');
    const formCancelBtn = $('#clientFormCancelBtn');
    const statsToggleBtn = $('#toggleStats');
    const statsSection = $('#clientsStatsSection');

    function setClientFormAddMode() {
        formTitle.text('إضافة عميل جديد');
        submitBtnText.text('حفظ العميل');
        generatedCodeWrapper.show();
    }

    function setClientFormEditMode() {
        formTitle.text('تعديل العميل');
        submitBtnText.text('تحديث العميل');
        generatedCodeWrapper.hide();
    }

    function resetClientForm() {
        if (!clientForm.length) {
            return;
        }

        clientForm[0].reset();
        $('#client_id').val('');
        setClientFormAddMode();
    }

    function updateFormToggleState(isOpen) {
        if (!formToggleBtn.length) {
            return;
        }

        formToggleBtn.toggleClass('is-active', isOpen);
        formToggleBtn.attr('aria-expanded', isOpen ? 'true' : 'false');
        // زر الإضافة موحّد: أيقونة fa-solid fa-plus دائماً وبدون نص — لا نبدّل
        // الأيقونة ولا نحقن نصاً عند الفتح/الإغلاق.
    }

    function updateStatsToggleState(isVisible) {
        if (!statsToggleBtn.length) {
            return;
        }

        statsToggleBtn.toggleClass('is-active', isVisible);
        statsToggleBtn.attr('aria-expanded', isVisible ? 'true' : 'false');
        statsToggleBtn.find('.clients-toggle-stats-text').text(isVisible ? 'إخفاء الإحصائيات' : 'إظهار الإحصائيات');

        const icon = statsToggleBtn.find('i').first();
        icon.toggleClass('fa-chart-pie', isVisible);
        icon.toggleClass('fa-eye', !isVisible);
    }

    setClientFormAddMode();
    updateFormToggleState(clientForm.is(':visible'));
    updateStatsToggleState(statsSection.is(':visible'));

    formToggleBtn.on('click', function (e) {
        e.preventDefault();

        if (!clientForm.length) {
            return;
        }

        if (clientForm.is(':visible')) {
            clientForm.stop(true, true).slideUp(250, function () {
                clientForm.removeClass('allforms-visible');
                resetClientForm();
                updateFormToggleState(false);
            });
        } else {
            resetClientForm();
            clientForm.addClass('allforms-visible').hide();
            clientForm.stop(true, true).slideDown(250, function () {
                updateFormToggleState(true);
            });
        }
    });

    formCancelBtn.on('click', function () {
        if (!clientForm.length || !clientForm.is(':visible')) {
            return;
        }

        clientForm.stop(true, true).slideUp(250, function () {
            clientForm.removeClass('allforms-visible');
            resetClientForm();
            updateFormToggleState(false);
        });
    });

    statsToggleBtn.on('click', function (e) {
        e.preventDefault();

        if (!statsSection.length) {
            return;
        }

        if (statsSection.is(':visible')) {
            statsSection.stop(true, true).slideUp(250, function () {
                statsSection.addClass('clients-hidden');
                updateStatsToggleState(false);
            });
        } else {
            statsSection.removeClass('clients-hidden').hide();
            statsSection.stop(true, true).slideDown(250, function () {
                updateStatsToggleState(true);
            });
        }
    });

    // تعديل عميل — تحميل بياناته في الفورم
    $(document).on('click', '.editClientBtn', function () {
        const clientData = {
            id: $(this).data('id'),
            code: $(this).data('code'),
            name: $(this).data('name'),
            entity: $(this).data('entity'),
            sector: $(this).data('sector'),
            phone: $(this).data('phone'),
            email: $(this).data('email'),
            whatsapp: $(this).data('whatsapp'),
            status: $(this).data('status')
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
        setClientFormEditMode();

        // عرض الفورم إذا كان مخفياً
        if (!clientForm.is(':visible')) {
            clientForm.addClass('allforms-visible').hide();
            clientForm.stop(true, true).slideDown(250, function () {
                updateFormToggleState(true);
            });
        } else {
            updateFormToggleState(true);
        }

        // التمرير إلى الفورم
        $('html, body').animate({
            scrollTop: $('#clientForm').offset().top - 100
        }, 500);
    });

    // ════════════════════════════════════════════════
    // عرض تفاصيل العميل — عبر النظام الموحّد EmsDetailsModal
    // ════════════════════════════════════════════════
    function clientIsActiveStatus(statusValue) {
        const normalized = String(statusValue === null || typeof statusValue === 'undefined' ? '' : statusValue)
            .trim()
            .toLowerCase()
            .replace(/✅|✔/g, '')
            .trim();
        return normalized === '1' || normalized === 'active' || normalized === 'نشط' || normalized === 'true';
    }

    function clientEscapeHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    // يبني قسم "المشاريع المرتبطة" (حالة تحميل / فارغ / بيانات)
    function buildClientProjectsSection(projects, loading) {
        const base = { title: 'المشاريع المرتبطة بالعميل', icon: 'fas fa-folder-open' };

        if (loading) {
            base.html = '<div class="clients-projects-loading"><i class="fas fa-spinner fa-spin"></i> جاري تحميل بيانات المشاريع...</div>';
            return base;
        }

        projects = projects || [];
        let suppliers = 0, equipments = 0, operators = 0, activeProjects = 0;
        projects.forEach(function (p) {
            suppliers += parseInt(p.suppliers_count || 0, 10);
            equipments += parseInt(p.equipments_total || 0, 10);
            operators += parseInt(p.operators_total || 0, 10);
            if (clientIsActiveStatus(p.status)) activeProjects += 1;
        });

        base.pills = [
            { label: 'المشاريع', value: projects.length },
            { label: 'المشاريع النشطة', value: activeProjects },
            { label: 'المشاريع غير النشطة', value: Math.max(0, projects.length - activeProjects) },
            { label: 'الموردون', value: suppliers },
            { label: 'الآليات', value: equipments },
            { label: 'المشغلون', value: operators }
        ];

        base.table = {
            columns: ['المشروع', 'الموردون', 'الآليات', 'الآليات العاملة', 'الآليات المتوقفة', 'المشغلون', 'المشغلون النشطون', 'المشغلون المتوقفون'],
            rows: projects.map(function (p) {
                const label = (p.name || '-') + (p.project_code ? ' (' + p.project_code + ')' : '');
                const cls = clientIsActiveStatus(p.status) ? 'clients-project-label-active' : 'clients-project-label-inactive';
                return [
                    { html: '<span class="' + cls + '">' + clientEscapeHtml(label) + '</span>' },
                    p.suppliers_count || 0,
                    p.equipments_total || 0,
                    { html: '<span class="clients-num-positive">' + (p.equipments_working || 0) + '</span>' },
                    { html: '<span class="clients-num-negative">' + (p.equipments_stopped || 0) + '</span>' },
                    p.operators_total || 0,
                    { html: '<span class="clients-num-positive">' + (p.operators_working || 0) + '</span>' },
                    { html: '<span class="clients-num-negative">' + (p.operators_stopped || 0) + '</span>' }
                ];
            })
        };
        base.empty = 'لا توجد مشاريع مرتبطة بهذا العميل';
        return base;
    }

    function loadClientProjectsStats(clientId) {
        $.ajax({
            url: 'clients.php',
            type: 'GET',
            dataType: 'json',
            data: { ajax: 'client_projects', client_id: clientId },
            success: function (response) {
                if (!response || !response.success) {
                    EmsDetailsModal.setSection(0, { title: 'المشاريع المرتبطة بالعميل', icon: 'fas fa-folder-open', html: '<div class="clients-table-empty-error">تعذر تحميل بيانات المشاريع</div>' });
                    return;
                }
                EmsDetailsModal.setSection(0, buildClientProjectsSection(response.projects || [], false));
            },
            error: function () {
                EmsDetailsModal.setSection(0, { title: 'المشاريع المرتبطة بالعميل', icon: 'fas fa-folder-open', html: '<div class="clients-table-empty-error">حدث خطأ أثناء تحميل بيانات المشاريع</div>' });
            }
        });
    }

    // تعبئة الفورم بالبيانات (تُستدعى من زر التعديل داخل نافذة العرض)
    function fillClientForm(c) {
        $('#client_id').val(c.id);
        $('#client_code').val(c.code);
        $('#client_name').val(c.name);
        $('#entity_type').val(c.entity);
        $('#sector_category').val(c.sector);
        $('#phone').val(c.phone);
        $('#email').val(c.email);
        $('#whatsapp').val(c.whatsapp);
        $('#status').val(c.status);

        if (!clientForm.is(':visible')) {
            clientForm.addClass('allforms-visible').hide();
            clientForm.stop(true, true).slideDown(250, function () { updateFormToggleState(true); });
        } else {
            updateFormToggleState(true);
        }
        $('html, body').animate({ scrollTop: $('#clientForm').offset().top - 100 }, 500);
    }

    $(document).on('click', '.viewClientBtn', function () {
        const c = {
            id: $(this).data('id'),
            code: $(this).data('code'),
            name: $(this).data('name'),
            entity: $(this).data('entity'),
            sector: $(this).data('sector'),
            phone: $(this).data('phone'),
            email: $(this).data('email'),
            whatsapp: $(this).data('whatsapp'),
            status: $(this).data('status'),
            projectsCount: $(this).data('projects-count'),
            created: $(this).data('created')
        };

        const statusTone = (c.status === 'نشط') ? 'active' : 'inactive';

        const actions = [];
        <?php if ($can_edit): ?>
            actions.push({
                label: 'تعديل البيانات', icon: 'fas fa-edit', variant: 'primary',
                onClick: function () { EmsDetailsModal.close(); fillClientForm(c); }
            });
        <?php endif; ?>
        actions.push({ label: 'إغلاق', icon: 'fas fa-times', variant: 'secondary', close: true });

        EmsDetailsModal.open({
            title: 'تفاصيل العميل',
            icon: 'fas fa-user-tie',
            fields: [
                { label: 'كود العميل', value: c.code, icon: 'fas fa-barcode' },
                { label: 'اسم العميل', value: c.name, icon: 'fas fa-user', size: 'lg' },
                { label: 'نوع الكيان', value: c.entity, icon: 'fas fa-building' },
                { label: 'تصنيف القطاع', value: c.sector, icon: 'fas fa-industry', size: 'lg' },
                { label: 'عدد المشاريع المرتبطة', value: c.projectsCount || 0, icon: 'fas fa-project-diagram' },
                { label: 'الهاتف', value: c.phone, icon: 'fas fa-phone' },
                { label: 'البريد الإلكتروني', value: c.email, icon: 'fas fa-envelope', size: 'lg' },
                { label: 'واتساب', value: c.whatsapp, icon: 'fab fa-whatsapp' },
                { label: 'الحالة', value: c.status, icon: 'fas fa-toggle-on', type: 'status', tone: statusTone },
                { label: 'أضيف بواسطة', value: c.created, icon: 'fas fa-user-plus' }
            ],
            sections: [buildClientProjectsSection([], true)],
            actions: actions
        });

        loadClientProjectsStats(c.id);
    });
</script>

<style>
    .clients-main .clients-summary-pill-danger,
    .clients-main .clients-inline-pill-danger {
        background: rgba(220, 38, 38, 0.12) !important;
        color: #b91c1c !important;
        border: 1px solid rgba(220, 38, 38, 0.28) !important;
    }

    .clients-main .clients-danger-text {
        color: #b91c1c !important;
        font-weight: 800;
    }

    .clients-main .clients-project-label-active {
        color: #15803d;
        font-weight: 700;
    }

    .clients-main .clients-project-label-inactive {
        color: #b91c1c;
        font-weight: 700;
    }

    .clients-main .table-container {
        padding-top: -50px !important;
        overflow-x: auto;
    }

    #clientsTable.clients-table-nowrap,
    #clientsTable.clients-table-nowrap th,
    #clientsTable.clients-table-nowrap td {
        white-space: nowrap;
    }

    #clientsTable .action-btns {
        flex-wrap: nowrap;
        white-space: nowrap;
    }
</style>


<?php
// ── نافذة معالج الاستيراد الموحّد + أصول Excel (تُطبع مرّة واحدة) ──
if (function_exists('ems_excel_render')) {
    ems_excel_render();
}
?>

</body>

</html>
