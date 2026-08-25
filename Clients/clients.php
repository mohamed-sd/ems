<?php
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}


include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/../includes/extra_fields.php'; // XF-01 — طبقةُ البياناتِ الإضافيةِ المركزية

// هويةُ الشاشةِ في سجلِّ البياناتِ الإضافية — تُكتب مرّةً وتُقرأ في كلِّ نداء
$XF_SCREEN = 'Clients/clients.php';

// ── RF-02 · CS-01 — حارسُ الشاشةِ فوقَ أيِّ معالجٍ يكتب ────────────────────
// كان هذا السطحُ يعتمد على insidebar.php وحدَه في الحجب، وinsidebar يقع
// **بعدَ** معالجِ الكتابة — فيُرحَّل الأثرُ ثم يُعاد التوجيهُ برسالةِ «لا صلاحية».
// الدالةُ نفسُها ولا تغييرَ في مَن يُمنع — التغييرُ في **متى**: قبلَ الكتابة.
if (function_exists('enforce_current_page_view_permission') && isset($conn)) {
    enforce_current_page_view_permission($conn, '../main/dashboard.php');
}

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
            '👋' => '👋',
            '🚀' => '🚀',
            'ðŸ†' => 'ðŸ†'
        );
        return strtr($buffer, $map);
    }
}

ob_start('clients_fix_mojibake_output');

// ══════════════════════════════════════════════════════════════════════════════
// دوال مساعدة
// ══════════════════════════════════════════════════════════════════════════════

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
        ems_gov_flash_redirect('clients.php', $msg, 'GOV-INFO-200', '');
        exit();
    }
}

// ══════════════════════════════════════════════════════════════════════════════
// التحقق من معرف الشركة
// ══════════════════════════════════════════════════════════════════════════════
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
if ($company_id <= 0) {
    ems_gov_flash_redirect('../main/dashboard.php', 'الحساب غير مرتبط بشركة.', 'GOV-INFO-200', '');
    exit();
}

// ══════════════════════════════════════════════════════════════════════════════
// العزل عبر بوابة المستأجر (K9 · هجرة 2026-07-15)
// كشفُ الأعمدة القديم والـDDL وقت التشغيل أُسقطا: أعمدة العزل والحذف الناعم على
// clients/project مضمونةٌ بالترحيلات وسجل البوابة (وDDL التشغيل مجمَّد أصلًا)،
// وعمود ربط المشروع الفعلي هو client_id (company_client_id غير موجود — مقيس).
// شرط الشركة صار مسؤولية {TENANT_SCOPE}؛ الاستعلامات الفرعية المترابطة على
// project تُقيَّد بمراسلة cc.id المُنطَّق (سلامة FK داخل الشركة الواحدة).
// ══════════════════════════════════════════════════════════════════════════════
$clients_gate = ems_tenant_db();

$project_client_link_column = 'client_id';
$project_active_status_sql = "(
    p.status = 1
    OR p.status = '1'
    OR TRIM(p.status) = 'نشط'
    OR TRIM(LOWER(p.status)) = 'active'
    OR TRIM(LOWER(p.status)) = 'true'
)";

$projects_count_select_sql = "(
    SELECT COUNT(*)
    FROM project p
    WHERE p.client_id = cc.id
      AND p.is_deleted = 0
)";

$projects_active_count_select_sql = "(
    SELECT COUNT(*)
    FROM project p
    WHERE p.client_id = cc.id
      AND p.is_deleted = 0
      AND $project_active_status_sql
)";

$projects_inactive_count_select_sql = "(
    (
        SELECT COUNT(*)
        FROM project p
        WHERE p.client_id = cc.id
          AND p.is_deleted = 0
    )
    -
    (
        SELECT COUNT(*)
        FROM project p
        WHERE p.client_id = cc.id
          AND p.is_deleted = 0
          AND $project_active_status_sql
    )
)";

// ══════════════════════════════════════════════════════════════════════════════
// توليد رمز CSRF لحماية النماذج
// ══════════════════════════════════════════════════════════════════════════════
 // [ع-0أ] اعتماد الرمز المركزي بدل رمزٍ محلّيٍّ منفصل: المُحقِن المركزي
// (security.php) يزرع حقل csrf_token بقيمة $_SESSION['csrf_token']. رمزٌ محلّيٌّ
// مختلفٌ بنفس الاسم كان يُنتج حقلين بقيمتين ⇒ الحارس المركزي يفشل دائمًا (12
// مخالفة كاذبة) وينفجر عند تشديد CSRF. توحيدُ القيمة يُبقي الفحص المحلّي فعّالًا
// ويجعل حقلَي المُحقِن والفورم متطابقَين ⇒ الحارس يمرّ أيًّا كان الفائز.
$clients_csrf_token = generate_csrf_token();

// ══════════════════════════════════════════════════════════════════════════════
// توليد الكود المقترح التالي للعميل (CLT-NNNN)
// يجلب آخر كود من جدول العملاء بصيغة CLT-NNNN ويزيده بمقدار 1
// هذا للعرض فقط ولا يُخزَّن في قاعدة البيانات
// ══════════════════════════════════════════════════════════════════════════════
$next_client_code = 'CLT-0001'; // القيمة الافتراضية
try {
    $last_code_rows = $clients_gate->scopedQuery(array(
        'scope' => array('cc' => 'clients'),
    ), "SELECT cc.client_code FROM clients cc
        WHERE {TENANT_SCOPE} AND cc.client_code REGEXP '^CLT-[0-9]+$' AND cc.is_deleted = 0
        ORDER BY CAST(SUBSTRING(cc.client_code, 5) AS UNSIGNED) DESC
        LIMIT 1");
} catch (\Throwable $t) {
    $last_code_rows = array();
}
if (!empty($last_code_rows)) {
    $last_num = intval(substr($last_code_rows[0]['client_code'], 4)); // بعد "CLT-"
    $next_num = $last_num + 1;
    $next_client_code = 'CLT-' . str_pad($next_num, 4, '0', STR_PAD_LEFT);
}

// ══════════════════════════════════════════════════════════════════════════════
// ðŸ” التحقق من صلاحيات المستخدم على وحدة العملاء
// ══════════════════════════════════════════════════════════════════════════════

// الحصول على معرف وحدة العملاء من جدول modules (جدول عام — قراءة عبر البوابة)
try {
    // [ع-0ب] مطابقةٌ دقيقةٌ حتميّة بدل whereRaw الفضفاض (name LIKE '%عملاء%' كان
    // يخاطر بأسر وحدةٍ أخرى). جدول modules فيه صفّان بهذا الـcode (id=1 «حالة
    // العملاء» owner 1 · id=35 «إدارة العملاء» owner 12، كلٌّ رابطُ سايدبار
    // لدوره). orderBy id ASC يثبّت الحاكمة على الوحدة 1 (كما تحلّ اليوم). الدمج
    // الكامل مؤجّلٌ لتبعية dynamic_nav (يبني من owner_role_id لا role_permissions).
    $module_info = $clients_gate->selectOne('modules', array(
        'columns'  => array('id'),
        'where'    => array('code' => 'Clients/clients.php'),
        'orderBy'  => 'id ASC',
    ));
} catch (\Throwable $t) {
    $module_info = null;
}
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
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض العملاء ❌', 'GOV-PERM-403', '');
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
    if ($client_code_raw === '' || !preg_match('/^[A-Za-z0-9_\-]+$/', $client_code_raw)) {
        clients_redirect_with_msg('كود العميل غير صالح. استخدم أحرفا وأرقاما و - أو _ فقط ❌');
    }

    // التحقق من صحة حالة العميل
    $status_raw = isset($_POST['status']) ? trim($_POST['status']) : '';
    $allowed_status = array('نشط', 'متوقف');
    if (!in_array($status_raw, $allowed_status, true)) {
        clients_redirect_with_msg('حالة العميل غير صالحة ❌');
    }

    // القيم تُمرَّر خامًا — البوابة prepared بالكامل (لا escape يدوي)
    $client_name_raw = trim($_POST['client_name']);
    $entity_type_raw = trim($_POST['entity_type']);
    $sector_category_raw = trim($_POST['sector_category']);
    $phone_raw = trim($_POST['phone']);
    $email_raw = trim($_POST['email']);
    $whatsapp_raw = trim($_POST['whatsapp']);
    $created_by = intval($_SESSION['user']['id']);

    // ── XF-01 · البياناتُ الإضافيةُ الاختيارية ──────────────────────────────
    // تُجمَع من السجلِّ المركزيِّ لا من حمولةِ الطلب: مفتاحٌ ليس في السجلِّ لا
    // يُكتب، وقائمةٌ خارجَ خياراتِها تُردُّ NULL. والغيابُ ليس محوًا (انظر
    // ems_xf_collect) — فحفظٌ من نموذجٍ لا يحمل الحقلَ لا يمحو ما أدخله غيرُه.
    $xf_values = ems_xf_collect($XF_SCREEN, $_POST);

    if ($client_id > 0) {
        // ── تعديل عميل موجود ────────────────────────────────────────────────

        // التحقق من ملكية العميل للشركة الحالية (العزل عبر البوابة)
        try {
            $owner_check = $clients_gate->selectOne('clients', array(
                'columns' => array('id'), 'where' => array('id' => $client_id),
            ));
        } catch (\Throwable $t) { $owner_check = null; }
        if (!$owner_check) {
            clients_redirect_with_msg('لا يمكنك تعديل عميل لا يتبع لشركتك ❌');
        }

        // التحقق من عدم تكرار كود العميل
        try {
            $dup = $clients_gate->scopedQuery(array(
                'scope' => array('cc' => 'clients'),
            ), "SELECT cc.id FROM clients cc
                WHERE {TENANT_SCOPE} AND cc.client_code = ? AND cc.id != ? AND cc.is_deleted = 0",
                array($client_code_raw, $client_id));
        } catch (\Throwable $t) { $dup = array(); }
        if (!empty($dup)) {
            clients_redirect_with_msg('كود العميل موجود مسبقا داخل شركتك ❌');
        }

        // (إعادة ختم company_id في الأصل كانت لا-عمل بنفس القيمة — البوابة تمنع
        //  تمريره في التعديل أصلًا وتضمن بقاءه بشرط النطاق)
        try {
            $clients_gate->update('clients', array_merge(array(
                'client_code'     => $client_code_raw,
                'client_name'     => $client_name_raw,
                'entity_type'     => $entity_type_raw,
                'sector_category' => $sector_category_raw,
                'phone'           => $phone_raw,
                'email'           => $email_raw,
                'whatsapp'        => $whatsapp_raw,
                'status'          => $status_raw,
            ), $xf_values), array('id' => $client_id), 'is_deleted = 0');
            \App\Services\ActivityLogService::logUpdate(
                'clients',
                'clients',
                $client_id,
                null,
                ['client_code' => $client_code_raw, 'client_name' => $client_name_raw]
            );
            clients_redirect_with_msg('تم تعديل العميل بنجاح ✅');
        } catch (\Throwable $t) {
            error_log('clients.php update failed: ' . $t->getMessage());
            clients_redirect_with_msg('حدث خطأ أثناء التعديل ❌');
        }

    } else {
        // ── إضافة عميل جديد ─────────────────────────────────────────────────

        // التحقق من عدم تكرار كود العميل
        try {
            $dup = $clients_gate->scopedQuery(array(
                'scope' => array('cc' => 'clients'),
            ), "SELECT cc.id FROM clients cc
                WHERE {TENANT_SCOPE} AND cc.client_code = ? AND cc.is_deleted = 0",
                array($client_code_raw));
        } catch (\Throwable $t) { $dup = array(); }
        if (!empty($dup)) {
            clients_redirect_with_msg('كود العميل موجود مسبقا داخل شركتك ❌');
        }

        try {
            $new_client_id = (int) $clients_gate->insert('clients', array_merge(array(
                'client_code'     => $client_code_raw,
                'client_name'     => $client_name_raw,
                'entity_type'     => $entity_type_raw,
                'sector_category' => $sector_category_raw,
                'phone'           => $phone_raw,
                'email'           => $email_raw,
                'whatsapp'        => $whatsapp_raw,
                'status'          => $status_raw,
                'created_by'      => $created_by,
            ), $xf_values));
            \App\Services\ActivityLogService::logCreate(
                'clients',
                'clients',
                $new_client_id,
                ['client_code' => $client_code_raw, 'client_name' => $client_name_raw]
            );
            clients_redirect_with_msg('تم إضافة العميل بنجاح ✅');
        } catch (\Throwable $t) {
            error_log('clients.php insert failed: ' . $t->getMessage());
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

    // التحقق من رمز CSRF
    if (empty($delete_csrf) || !hash_equals($clients_csrf_token, $delete_csrf)) {
        clients_redirect_with_msg('جلسة الحذف غير صالحة، يرجى إعادة المحاولة ❌');
    }

    // التحقق من أن العميل تابع لشركة المستخدم (العزل عبر البوابة)
    try {
        $can_delete_scope = $clients_gate->selectOne('clients', array(
            'columns' => array('id'), 'where' => array('id' => $delete_id),
        ));
    } catch (\Throwable $t) { $can_delete_scope = null; }
    if (!$can_delete_scope) {
        clients_redirect_with_msg('لا يمكنك حذف عميل لا يتبع لشركتك ❌');
    }

    // الحذف الناعم مع تعطيل الحالة (سلوك الأصل: status='متوقف' + أعمدة الحذف الثلاثة)
    try {
        $clients_gate->update('clients', array(
            'status'     => 'متوقف',
            'is_deleted' => 1,
            'deleted_at' => date('Y-m-d H:i:s'),
            'deleted_by' => intval($_SESSION['user']['id']),
        ), array('id' => $delete_id), 'is_deleted = 0');
        \App\Services\ActivityLogService::logDelete(
            'clients',
            'clients',
            $delete_id
        );
        clients_redirect_with_msg('تم حذف العميل بنجاح ✅');
    } catch (\Throwable $t) {
        error_log('clients.php soft delete failed: ' . $t->getMessage());
        clients_redirect_with_msg('حدث خطأ أثناء الحذف ❌');
    }
}

if (isset($_GET['ajax']) && $_GET['ajax'] === 'client_projects') {
    header('Content-Type: application/json; charset=UTF-8');

    $client_id = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;
    if ($client_id <= 0) {
        echo json_encode(array('success' => false, 'message' => 'معرف العميل غير صالح'));
        exit();
    }

    try {
        $client_check = $clients_gate->selectOne('clients', array(
            'columns' => array('id'), 'where' => array('id' => $client_id),
        ));
    } catch (\Throwable $t) { $client_check = null; }
    if (!$client_check) {
        echo json_encode(array('success' => false, 'message' => 'العميل غير موجود أو خارج نطاق الشركة'));
        exit();
    }

    // الاستعلامات الفرعية المترابطة على operations/equipment_drivers تُقيَّد بمراسلة
    // p.id المُنطَّق عبر {TENANT_SCOPE} (سلامة FK داخل الشركة)؛ إعلانهما enrich،
    // وJOIN المشغّلين صار LEFT (مكافئ حرفيًّا مع شرط employee_id IS NOT NULL القائم).
    try {
        $projects_rows = $clients_gate->scopedQuery(array(
            'scope'  => array('p' => 'project'),
            'enrich' => array('o' => 'operations', 'ed' => 'equipment_drivers'),
        ), "
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
            ) AS suppliers_count,
            (
                SELECT COUNT(DISTINCT o.equipment)
                FROM operations o
                WHERE o.project_id = p.id
                  AND o.equipment IS NOT NULL
                  AND o.equipment <> ''
                  AND o.equipment <> '0'
            ) AS equipments_total,
            (
                SELECT COUNT(DISTINCT o.equipment)
                FROM operations o
                WHERE o.project_id = p.id
                  AND o.status = 1
                  AND o.equipment IS NOT NULL
                  AND o.equipment <> ''
                  AND o.equipment <> '0'
            ) AS equipments_working,
            (
                SELECT COUNT(DISTINCT ed.employee_id)
                FROM operations o
                LEFT JOIN equipment_drivers ed ON ed.equipment_id = o.equipment
                WHERE o.project_id = p.id
                  AND ed.employee_id IS NOT NULL
            ) AS operators_total,
            (
                SELECT COUNT(DISTINCT ed.employee_id)
                FROM operations o
                LEFT JOIN equipment_drivers ed ON ed.equipment_id = o.equipment
                WHERE o.project_id = p.id
                  AND ed.status = 1
                  AND ed.employee_id IS NOT NULL
            ) AS operators_working
        FROM project p
        WHERE {TENANT_SCOPE}
          AND p.client_id = ?
          AND p.is_deleted = 0
        ORDER BY p.id DESC
        ", array($client_id));
    } catch (\Throwable $t) {
        echo json_encode(array('success' => false, 'message' => 'تعذر تحميل بيانات المشاريع'));
        exit();
    }

    $projects = array();
    foreach ($projects_rows as $project_row) {
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

// عدّادات المشاريع استعلاماتٌ فرعيةٌ مترابطة على cc.id المُنطَّق — project تُعلن
// enrich (إعلانٌ بلا تنطيقٍ إضافي: المراسلة تضمن العزل بسلامة FK داخل الشركة).
try {
    $clients_list = $clients_gate->scopedQuery(array(
        'scope'  => array('cc' => 'clients'),
        'enrich' => array('u' => 'users', 'p' => 'project'),
    ), "SELECT cc.*, u.name as creator_name,
               $projects_count_select_sql AS projects_count,
               $projects_active_count_select_sql AS projects_active_count,
               $projects_inactive_count_select_sql AS projects_inactive_count
        FROM clients cc
        LEFT JOIN users u ON cc.created_by = u.id
        WHERE {TENANT_SCOPE} AND cc.is_deleted = 0
        ORDER BY cc.id DESC");
} catch (\Throwable $t) {
    $clients_list = array();
    $clients_load_error = true; // [م-5] فشل الجلب يُميَّز عن «لا بيانات»
    error_log('clients.php list load: ' . $t->getMessage());
}

foreach ($clients_list as $row) {
    {
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

$page_title = "العملاء";
// CM-00 (DEC-E · U10): بذرُ محاورِ الغلافِ من الخادم — AX-2/3 من محرك الصلاحيات
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : (isset($permissions) ? $permissions : null));
include("../inheader.php");
include('../insidebar.php');
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>

<div class="main clients-main ems-unified-page-shell">

    <?php
    // Unified page header (structure: includes/page_header.php · styling: ems.main.all.style.css)
    $header_title = 'العملاء';
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
<?php require_once __DIR__ . '/../includes/entity_tabs.php'; echo ems_entity_tabs('client', 'البيانات الأساسية'); ?>

    <?php if (!empty($_GET['msg'])):
        $isSuccess = strpos($_GET['msg'], '✅') !== false;
        ?>
        <div class="success-message <?= $isSuccess ? 'is-success' : 'is-error' ?>">
            <i class="fas <?= $isSuccess ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?php echo clients_e($_GET['msg']); ?>
        </div>
    <?php endif; ?>

    <?php echo ems_states_bundle('لا عملاء مسجلين ضمن هذا الترشيح', 'أضف عميلا جديدا أو غير المرشحات'); ?>

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
         <div class="card-header">
                <h5><i class="fas fa-edit"></i> <span id="formTitle">إضافة عميل جديد</span></h5>
        </div>
        <input type="hidden" name="client_id" id="client_id" value="">
        <input type="hidden" name="csrf_token" value="<?php echo clients_e($clients_csrf_token); ?>">
        <div class="card shadow-sm pu-form-card">

            <div class="card-body">
                <div class="form-grid">

                    <!-- ══ حقل الكود المولد تلقائياً (قراءة فقط - لا يُرسَل لقاعدة البيانات) ══ -->
                    <div id="generated_code_wrapper" class="auto">
                        <label for="generated_client_code"><i class="fas fa-magic"></i> كود العميل المولد <i
                                class="fas fa-info-circle clients-info-icon"></i></label>
                        <input type="text" id="generated_client_code" class="generated-code-field"
                            value="<?php echo clients_e($next_client_code); ?>" readonly tabindex="-1"
                            title="هذا الكود للعرض فقط، يمكنك نسخه واستخدامه في حقل كود العميل" />
                        <div class="generated-code-hint">

                        </div>
                    </div>
                    <!-- ══════════════════════════════════════════════════════ -->

                    <div>
                        <label for="client_code"><i class="fas fa-barcode"></i> كود العميل *</label>
                        <!-- الكودُ المولَّدُ مكتوبٌ سلفًا **وقابلٌ للتعديل** (طلبُ المالك):
                             فأكثرُ الحالات تقبله كما هو، ومَن أراد كودَه الخاصّ كتبه فوقه.
                             ووضعُه في السمة `value` لا بجافاسكربت مقصود: `resetClientForm()`
                             تستدعي `reset()` الأصليّ، وهو يعيد كلَّ حقلٍ إلى سمته — فيعود
                             الكودُ المولَّدُ تلقائيًّا بعد كل إلغاءٍ أو خروجٍ من وضع التعديل. -->
                        <input type="text" name="client_code" id="client_code" placeholder="مثال: CL-001" required
                            value="<?php echo clients_e($next_client_code); ?>"
                            pattern="[A-Za-z0-9_\-]+" />
                    </div>
                    <div>
                        <label for="client_name"><i class="fas fa-user"></i> اسم العميل *</label>
                        <input type="text" name="client_name" id="client_name" placeholder="أدخل اسم العميل" required />
                    </div>
                    <div>
                        <label for="entity_type"><i class="fas fa-building"></i> نوع الكيان</label>
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
                        <label for="sector_category"><i class="fas fa-industry"></i> تصنيف القطاع</label>
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
                        <label for="phone"><i class="fas fa-phone"></i> رقم الهاتف</label>
                        <input type="tel" name="phone" id="phone" placeholder="مثال: +249123456789" />
                    </div>
                    <div>
                        <label for="email"><i class="fas fa-envelope"></i> البريد الإلكتروني</label>
                        <input type="email" name="email" id="email" placeholder="example@company.com" />
                    </div>
                    <div>
                        <label for="whatsapp"><i class="fab fa-whatsapp"></i> واتساب</label>
                        <input type="tel" name="whatsapp" id="whatsapp" placeholder="مثال: +249123456789" />
                    </div>
                    <div>
                        <label for="status"><i class="fas fa-toggle-on"></i> حالة العميل *</label>
                        <select name="status" id="status" required>
                            <option value="نشط" selected>نشط ✅</option>
                            <option value="متوقف">متوقف ⏸</option>
                        </select>
                    </div>
                </div>

                <?php
                // ══ XF-01 · «المزيد» — بياناتٌ إضافيةٌ اختيارية ══════════════════════
                // الحقولُ فوقُ هي **الحدُّ الأدنى لإضافةِ عميل**، وهذه تحتُها تُستكمَل
                // متى توفّرت. مطويةٌ ابتداءً فلا تُطيل النموذجَ على من لا يحتاجها،
                // وبلا `required` واحدٍ فيها — والقسمُ يُرسم من السجلِّ المركزيِّ
                // (`includes/extra_fields.php`) لا يُكتب هنا، كي يسريَ أيُّ تصحيحٍ
                // على كلِّ شاشةٍ تُسجَّل فيه لاحقًا.
                ems_xf_render_form($XF_SCREEN);
                ?>

                <div class="pu-form-actions">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-save"></i> <span id="submitBtnText">حفظ العميل</span>
                    </button>
                    <button type="button" id="clientFormCancelBtn" class="btn-secondary">
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
                <label for="filterEntityType"><i class="fa fa-calendar"></i> نوع الكيان </label>
               <select id="filterEntityType" class="form-control" placeholder="">
                        <option value="">-- حدد نوع الكيان --</option>
                    </select>
            </div>
            <div class="filter-field">
                <label for="filterSectorCategory"><i class="fa fa-calendar"></i> تصنيف القطاع</label>
                <select id="filterSectorCategory" class="form-control">
                        <option value=""> -- حدد تصنيف القطاع -- </option>
                    </select>
            </div>
            <!-- كرّر .filter-field بقدر ما تريد من الحقول -->
            <div class="filter-actions">
                <button type="button" class="btn-primary"><i class="fa fa-search"></i> تطبيق</button>
                <button type="button" class="btn-secondary" title="إعادة تعيين"><i class="fa fa-rotate-right"></i></button>
            </div>

        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <?php if (!empty($clients_load_error)): ?>
                <div class="alert alert-danger clients-table-empty-error cl-1">
                    ⚠ تعذر تحميل بيانات العملاء — قد يكون هناك خلل مؤقت. يرجى إعادة تحميل الصفحة.
                </div>
            <?php endif; ?>
            <div class="table-container">
                <table id="clientsTable" class="display clients-table-nowrap no-datatable" data-state-save="false">
                    <thead>
                        <tr>
                            <th> إجراءات</th>
                            <th width="100"> كود العميل</th>
                            <th> اسم العميل</th>
                            <th> الكيان</th>
                            <th> تصنيف القطاع</th>
                            <th> عدد المشاريع</th>
                            <th> الهاتف</th>
                            <th> الحالة</th>
                            <!-- ══ CMP-03 ⑤ · XF-01 — الأعمدةُ الوظيفيةُ بتصميم المستند، **موصولةً بمصدرها** ══
                                 كانت هذه الثلاثةَ عشرَ رؤوسًا محقونةً بلا مصدر: كلُّ خليةٍ «—» ورأسٌ
                                 موسومٌ «بلا مصدر» في منتقي الأعمدة. صارت الآن أعمدةً حقيقية:
                                   · عشرةٌ أُنشئت اختياريةً في الجدول (هجرة 2027_07_16)
                                   · وثلاثٌ وُصلت بمصدرِها القائم (email · created_by · created_at)
                                 و`ems_xf_th_attrs()` تطبع `data-fn-src` (فلا يحشوها JS) ووسومَ
                                 المجموعة (فتُطوى ابتداءً وتُفتح بنقرةٍ من منتقي الأعمدة).
                                 والنصُّ يبقى مكتوبًا حرفيًّا — أدواتُ جردِ وثيقةِ الأعمدة تقرأ الملفَّ نصًّا. -->
                            <th class="ems-fn-th" data-fn="1"<?php echo ems_xf_th_attrs($XF_SCREEN, 'legal_name'); ?>>الاسم القانوني الكامل</th>
                            <th class="ems-fn-th" data-fn="1"<?php echo ems_xf_th_attrs($XF_SCREEN, 'legal_form'); ?>>الشكل النظامي</th>
                            <th class="ems-fn-th" data-fn="1"<?php echo ems_xf_th_attrs($XF_SCREEN, 'registration_country'); ?>>بلد التسجيل</th>
                            <th class="ems-fn-th" data-fn="1"<?php echo ems_xf_th_attrs($XF_SCREEN, 'commercial_reg_no'); ?>>رقم السجل التجاري</th>
                            <th class="ems-fn-th" data-fn="1"<?php echo ems_xf_th_attrs($XF_SCREEN, 'tax_id'); ?>>الرقم الضريبي</th>
                            <th class="ems-fn-th" data-fn="1"<?php echo ems_xf_th_attrs($XF_SCREEN, 'registered_address'); ?>>العنوان المسجل</th>
                            <th class="ems-fn-th" data-fn="1"<?php echo ems_xf_th_attrs($XF_SCREEN, 'contact_person'); ?>>جهة الاتصال</th>
                            <th class="ems-fn-th" data-fn="1"<?php echo ems_xf_th_attrs($XF_SCREEN, 'contact_title'); ?>>المنصب</th>
                            <th class="ems-fn-th" data-fn="1"<?php echo ems_xf_th_attrs($XF_SCREEN, 'email'); ?>>البريد</th>
                            <th class="ems-fn-th" data-fn="1"<?php echo ems_xf_th_attrs($XF_SCREEN, 'client_classification'); ?>>تصنيف العميل</th>
                            <th class="ems-fn-th" data-fn="1"<?php echo ems_xf_th_attrs($XF_SCREEN, 'importance_tier'); ?>>شريحة الأهمية</th>
                            <th class="ems-fn-th" data-fn="1"<?php echo ems_xf_th_attrs($XF_SCREEN, 'created_by'); ?>>سجله</th>
                            <th class="ems-fn-th" data-fn="1"<?php echo ems_xf_th_attrs($XF_SCREEN, 'created_at'); ?>>تاريخ التسجيل</th>
                            <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
                            <th class="ems-gov-th" data-gov="base_currency" data-slice="3" title="عملة دفاتر الكيان">العملة الأساسية</th>
                            </tr>
                    </thead>
                    <tbody>
                        <?php
                        foreach ($clients_rows as $row) {
                            // سماتُ `data-xf-*` — منها تُبنى نافذةُ التفاصيلِ ويُملأ الفورمُ عند
                            // التعديل. تُحسب مرّةً للصفِّ وتُلصق على زرَّي العرضِ والتعديل معًا.
                            $xf_attrs = ems_xf_data_attrs($XF_SCREEN, $row, array('conn' => $conn));
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
                                       data-created='" . clients_e(ems_actor_label($conn, isset($row['created_by']) ? $row['created_by'] : 0)) . "'"
                                       . $xf_attrs . "
                                       title='عرض التفاصيل'>
                                        <i class='fas fa-eye'></i>
                                    </a>
                                    <a href='../movement/client_tree.php?client_id=" . urlencode($row['id']) . "'
                                       class='action-btn view clientTreeBtn'
                                       target='_blank' rel='noopener'
                                       title='شجرة العميل (مشاريعه وموردوه ومعداته ومشغلوه)'>
                                        <i class='fas fa-sitemap'></i>
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
                                           data-status='" . clients_e($row['status']) . "'"
                                           . $xf_attrs . "
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

                            // ── XF-01 · خلايا البياناتِ الإضافيةِ الثلاثةَ عشرَ ────────────────
                            // تُطبع **بترتيبِ رؤوسها** ودائمًا وإن كانت فارغة: عددُ الخلايا
                            // يجب أن يساويَ عددَ الرؤوس وإلّا رمى DataTables «Incorrect column
                            // count» فسقط الجدولُ كلُّه. والفارغُ «—» بصنفِ `ems-gov-empty` —
                            // نفسُ مظهرِ الفراغِ في النظام، فلا يُقرأ فراغُ بيانٍ عطلًا.
                            // ويبقى عمودُ الحوكمةِ الأخير (`base_currency`) يحشوه JS كما كان.
                            echo ems_xf_tds($XF_SCREEN, $row, array('conn' => $conn));

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
<script src="/ems/assets/vendor/datatables/js/dataTables.buttons.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/buttons.html5.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/buttons.print.min.js"></script>
<script src="/ems/assets/vendor/jszip/jszip.min.js"></script>
<script src="/ems/assets/vendor/pdfmake/pdfmake.min.js"></script>
<script src="/ems/assets/vendor/pdfmake/vfs_fonts.js"></script>

<script>
    $(document).ready(function () {
        // تهيئةُ جدولِ العملاء انتقلت إلى المكوّنِ المركزي
        // (assets/js/ui-unification.js — initializeMissingDataTables):
        // لغةٌ عربية وتمريرٌ أفقيٌّ وزرُّ إكسل موحَّد.
        // تعطيلُ حفظِ الحالة بقي كما كان لكن بسمةِ data-state-save="false" على
        // وسمِ الجدول (يقرؤها DataTables بنفسه): الفلاتر هنا تُدار عبر قوائم
        // اختيارٍ منفصلة (fillFilterOptions) تملأ بحثَ الأعمدة، وحالةُ قوائمِ
        // الاختيار ليست جزءاً من حالة DataTables. مع stateSave العام
        // (performance-boost.js) كان بحثُ عمودٍ محفوظٌ يُستعاد فيُخفي كل الصفوف
        // («مرشّحة من 4 ← 0») والقوائم تبدو فارغة. (ظهر في Edge)
        function bindClientsFilters() {
        const clientsTable = $('#clientsTable').DataTable();

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
        }

        // الربط بعد تهيئة المكون المركزي: إن كان الجدول مهيأ ربطنا فورا،
        // وإلا انتظرنا حدث init.dt الذي يطلقه DataTables عند التهيئة.
        if ($.fn.dataTable && $.fn.dataTable.isDataTable('#clientsTable')) {
            bindClientsFilters();
        } else {
            $('#clientsTable').one('init.dt', bindClientsFilters);
        }
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

    /**
     * إظهار حقل «كود العميل المولد» وإخفاؤه.
     *
     * ⚠️ **لا تستعمل `jQuery.hide()` هنا** — `assets/css/ems-forms.css` يحمل:
     *     :is(.allforms, .ems-form) .form-grid > div { display: block !important }
     * والحقل ابن مباشر ل`.form-grid`، ف`!important` من ورقة الأنماط تهزم
     * `display:none` التي يكتبها jQuery **سطريا بلا أولوية**. النتيجة أن
     * `hide()` «تنجح» (السمة تكتب فعلا) والحقل يبقى ظاهرا — عطب صامت
     * لا يظهر في أي سجل. والعلاج: أولوية سطرية تغلب أولوية الورقة.
     * (وهي گوتشا المشروع المسجلة: «ems-forms.css يهزم jQuery.hide()».)
     */
    function setGeneratedCodeShown(shown) {
        var el = generatedCodeWrapper[0];
        if (!el) { return; }
        if (shown) { el.style.removeProperty('display'); }
        else       { el.style.setProperty('display', 'none', 'important'); }
    }

    function setClientFormAddMode() {
        formTitle.text('إضافة عميل جديد');
        submitBtnText.text('حفظ العميل');
        setGeneratedCodeShown(true);
        // الكود المولد يعود إلى خانته كلما دخلنا وضع الإضافة — ومصدره حقل
        // العرض نفسه لا نسخة ثانية منه (مصدر حقيقة واحد). و`reset()` يكفي
        // للخروج من الإلغاء، لكن الانتقال من «تعديل» إلى «إضافة» قد يقع بلا
        // reset فيبقى كود العميل المعدل ظاهرا — وهذا السطر يسد تلك الحالة.
        var genCode = $('#generated_client_code').val();
        if (genCode) { $('#client_code').val(genCode); }
    }

    function setClientFormEditMode() {
        formTitle.text('تعديل العميل');
        submitBtnText.text('تحديث العميل');
        setGeneratedCodeShown(false);
    }

    function resetClientForm() {
        if (!clientForm.length) {
            return;
        }

        clientForm[0].reset();
        $('#client_id').val('');
        setClientFormAddMode();
        emsXfCollapse();
    }

    /* ══════════════════════════════════════════════════════════════════════
     * XF-01 · البيانات الإضافية — جسر الواجهة
     * ──────────────────────────────────────────────────────────────────────
     * الخريطة تبث من السجل المركزي (`includes/extra_fields.php`) ولا تكتب
     * هنا: تسمية واحدة للحقل في الجدول والفورم ونافذة التفاصيل. فإن أضيف
     * حقل أو غيرت تسميته، تغير الثلاثة معا بلا تعديل في هذا الملف.
     * ══════════════════════════════════════════════════════════════════════ */
    const EMS_XF_MAP  = <?php echo json_encode(ems_xf_js_map($XF_SCREEN), JSON_UNESCAPED_UNICODE); ?>;
    const EMS_XF_OWN  = <?php
        $__own = array();
        foreach (ems_xf_own_columns($XF_SCREEN) as $__c) { $__own[] = $__c['key']; }
        echo json_encode($__own, JSON_UNESCAPED_UNICODE);
    ?>;

    /** `legal_name` ⇒ `legal-name` — jQuery `.data()` يطبع الشرطات لا الشرط السفلية. */
    function emsXfDataKey(k) { return String(k).replace(/_/g, '-'); }

    /** قراءة كل قيم `data-xf-*` من زر إلى كائن بمفاتيح السجل. */
    function emsXfRead($btn) {
        const o = {};
        Object.keys(EMS_XF_MAP).forEach(function (k) {
            const v = $btn.data('xf-' + emsXfDataKey(k));
            o[k] = (v === undefined || v === null) ? '' : String(v);
        });
        return o;
    }

    /** طي قسم «المزيد» — `reset()` يمسح الحقول ولا يطوي `<details>`. */
    function emsXfCollapse() {
        const d = document.getElementById('emsXfMore');
        if (d) { d.open = false; }
    }

    /**
     * ملء حقول «المزيد» عند التعديل — **وفتح القسم إن كان فيه بيان**.
     * فلو بقي مطويا على بيانات موجودة ظن المستخدم أنها ضاعت، ولو فتح
     * دائما أطال النموذج على من لا يستعمله. فالفتح تابع للمحتوى لا للحالة.
     */
    function emsXfFill(o) {
        let any = false;
        EMS_XF_OWN.forEach(function (k) {
            const v = (o && o[k]) ? o[k] : '';
            const $f = $('#' + k);
            if (!$f.length) { return; }
            $f.val(v);
            if (String(v).trim() !== '') { any = true; }
        });
        const d = document.getElementById('emsXfMore');
        if (d) { d.open = any; }
    }

    /**
     * قسم «البيانات الإضافية» في نافذة التفاصيل.
     *
     * ◆ **يبنى بأصناف النافذة نفسها لا بأصناف خاصة به**: `ems-dmodal__grid`
     *   و`ems-dcard` و`ems-dcard__head` و`ems-dcard__value` هي البطاقة المعتمدة
     *   لكل حقل في هذه النافذة. فيطابق القسم بقية البطاقات **تلقائيا**،
     *   وأي تغيير في سمة النافذة لاحقا يسري عليه بلا لمسه. وقاعدة خاصة به
     *   كانت ستفرقه عنها عند أول تعديل.
     * ◆ ويبنى **فقط** مما له قيمة — وبطاقة قيمتها شرطة ليست بيانا يعرض.
     *   وإن خلا كله أعلن ذلك سطرا صريحا لا فراغا يقرأ عطلا.
     * ◆ والعرض تابع لطول القيمة (`--w-lg` للعناوين) — وهو منطق النافذة نفسه.
     */
    function emsXfSection(o) {
        const cards = [];
        Object.keys(EMS_XF_MAP).forEach(function (k) {
            const v = (o && o[k]) ? String(o[k]).trim() : '';
            if (v === '') { return; }
            const w = v.length > 28 ? ' ems-dcard--w-lg' : '';
            cards.push('<div class="ems-dcard' + w + '">'
                     + '<div class="ems-dcard__head"><i class="' + clientEscapeHtml(EMS_XF_MAP[k].icon) + '"></i>'
                     + '<span>' + clientEscapeHtml(EMS_XF_MAP[k].label) + '</span></div>'
                     + '<div class="ems-dcard__value">' + clientEscapeHtml(v) + '</div></div>');
        });
        const filled = cards.length, total = Object.keys(EMS_XF_MAP).length;
        const html = filled
            ? '<div class="ems-dmodal__grid">' + cards.join('') + '</div>'
              + '<div class="ems-dsection__pills"><span class="ems-dsection__pill">'
              + 'مكتمل <strong>' + filled + '</strong> من <strong>' + total + '</strong>'
              + ' — والباقي اختياري يُضاف متى توفّر</span></div>'
            : '<div class="ems-dsection__pills"><span class="ems-dsection__pill">'
              + 'لم تُدخَل بيانات إضافية بعد — كلها اختيارية وتُضاف من زر «تعديل»</span></div>';
        return { title: 'بيانات إضافية', icon: 'fas fa-circle-plus', html: html };
    }

    function updateFormToggleState(isOpen) {
        if (!formToggleBtn.length) {
            return;
        }

        formToggleBtn.toggleClass('is-active', isOpen);
        formToggleBtn.attr('aria-expanded', isOpen ? 'true' : 'false');
        // زر الإضافة موحد: أيقونة fa-solid fa-plus دائما وبدون نص — لا نبدل
        // الأيقونة ولا نحقن نصا عند الفتح/الإغلاق.
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
        emsXfFill(emsXfRead($(this)));      // XF-01 — البياناتُ الإضافيةُ من سماتِ الزرِّ نفسِه
        if (window.EmsSelect) EmsSelect.refresh();
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
    // عرض تفاصيل العميل — عبر النظام الموحد EmsDetailsModal
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
        // ⚠️ مسارُ تعديلٍ ثانٍ — ولم يكن يُعلن نفسَه تعديلًا (أُصلح بطلب المالك):
        // هذه الدالةُ تُستدعى من زر «تعديل» داخل نافذة التفاصيل، وكانت تملأ الفورم
        // بعميلٍ قائمٍ **وتتركه في وضع الإضافة**: العنوانُ «إضافة عميل جديد»،
        // والزرُّ «حفظ العميل»، وحقلُ «كود العميل المولد» ظاهرٌ بكودٍ لا يخصّ
        // المعروض. والفارقُ ليس تجميليًّا — المستخدمُ يظنّ أنه يُنشئ وهو يعدّل.
        // (وضعُ التعديل يُخفي الحقلَ المولَّد أصلًا — فالإصلاحُ استدعاءٌ لا شرطٌ جديد.)
        setClientFormEditMode();
        $('#client_id').val(c.id);
        $('#client_code').val(c.code);
        $('#client_name').val(c.name);
        $('#entity_type').val(c.entity);
        $('#sector_category').val(c.sector);
        $('#phone').val(c.phone);
        $('#email').val(c.email);
        $('#whatsapp').val(c.whatsapp);
        $('#status').val(c.status);
        emsXfFill(c.xf);                    // XF-01 — المسارُ الثاني للتعديل يملأ الإضافيةَ أيضًا
        if (window.EmsSelect) EmsSelect.refresh();

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
            created: $(this).data('created'),
            xf: emsXfRead($(this))          // XF-01 — تُقرأ مرّةً وتخدم العرضَ والتعديلَ معًا
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
            // القسمُ الأولُ يُستبدَل لاحقًا بنتيجةِ المشاريع (setSection(0)) — فالإضافيةُ
            // بعدَه بفهرسٍ ثابت، ولا يدوسها التحديثُ غيرُ المتزامن.
            sections: [buildClientProjectsSection([], true), emsXfSection(c.xf)],
            actions: actions
        });

        loadClientProjectsStats(c.id);
    });
</script>

<?php
// ── نافذة معالج الاستيراد الموحّد + أصول Excel (تُطبع مرّة واحدة) ──
if (function_exists('ems_excel_render')) {
    ems_excel_render();
}
?>

</body>

</html>
