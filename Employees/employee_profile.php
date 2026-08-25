<?php
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}

include '../config.php';
include '../includes/permissions_helper.php';
require_once '../includes/driver_contract_dates.php';
require_once __DIR__ . '/../includes/sensitive_read_log.php'; // INJ-FIX-01 §أ② — نقطةُ قرارِ الحقلِ الحساسِ في العرض

$current_role = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;

if (!$is_super_admin && $company_id <= 0) {
    ems_gov_flash_redirect('../login.php', 'لا توجد بيئة شركة صالحة للمستخدم ❌', 'GOV-SCOPE-403', '');
    exit();
}

// العزل عبر بوابة المستأجر — والسوبر عبر forAllTenants المسجَّل (سلوك الأصل: بلا تنطيق).
$emp_gate = $is_super_admin ? ems_tenant_db()->forAllTenants('employee profile super') : ems_tenant_db();

$employee_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($employee_id <= 0) {
    ems_gov_flash_redirect('employees.php', 'معرف السائق غير صحيح ❌', 'GOV-REF-404', '');
    exit();
}

$driver = null;
try {
    $drv_rows = $emp_gate->scopedQuery(
        array('scope' => array('d' => 'employees'), 'enrich' => array('s' => 'suppliers')),
        "SELECT d.*, s.name AS supplier_name
               FROM employees d
               LEFT JOIN suppliers s ON d.supplier_id = s.id
               WHERE d.id = ? AND {TENANT_SCOPE}
               LIMIT 1", array($employee_id));
    $driver = $drv_rows ? $drv_rows[0] : null;
} catch (\Throwable $t) { error_log('employee_profile card: ' . $t->getMessage()); }

if (!$driver) {
    ems_gov_flash_redirect('employees.php', 'السائق غير موجود او خارج نطاق الشركة ❌', 'GOV-SCOPE-403', '');
    exit();
}

// ════════════════════════════════════════════════════════════════════════════
// 🔗 حساب الدخول المرتبط بهذا الموظف (الخيار ب: users.employee_id)
// ════════════════════════════════════════════════════════════════════════════
// معالج «سحب الحساب» (POST) — يجب أن يسبق أي إخراج. (فكّ الرابط وتعطيل الحساب عبر البوابة —
// العزل وشرط الارتباط بالموظف يفرضهما where، والشركة تحقنها البوابة.)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['account_action'] ?? '') === 'revoke') {
    $target_uid = intval($_POST['target_uid'] ?? 0);
    $revoked = 0;
    try {
        $revoked = $emp_gate->update('users',
            array('employee_id' => null, 'status' => 'inactive', 'updated_at' => date('Y-m-d H:i:s')),
            array('id' => $target_uid, 'employee_id' => $employee_id));
    } catch (\Throwable $t) { error_log('employee_profile revoke: ' . $t->getMessage()); }
    if ($revoked > 0) {
        ems_gov_redirect("Location: employee_profile.php?id=$employee_id&msg=" . urlencode('✅ تم سحب الحساب من الموظف وتعطيله'));
    } else {
        ems_gov_redirect("Location: employee_profile.php?id=$employee_id&msg=" . urlencode('❌ تعذر سحب الحساب أو لا توجد صلاحية'));
    }
    exit();
}

// ميزة ربط الحساب قائمة (users.employee_id عمود قائم — علم التوافق الرجعي مسطَّح)
$users_has_employee_link = true;

// جلب الحساب المرتبط (إن وُجد) — البوابة تستثني المحذوف ناعمًا (سلوك الأصل نفسه)
$linked_user = null;
try {
    $linked_user = $emp_gate->selectOne('users', array(
        'columns' => array('id', 'name', 'username', 'role', 'status'),
        'where'   => array('employee_id' => $employee_id),
    ));
} catch (\Throwable $t) { error_log('employee_profile linked user: ' . $t->getMessage()); }

// خريطة أسماء الأدوار للعرض (roles جدول مرجعي عالمي)
$roles_map = array();
try {
    foreach ($emp_gate->select('roles', array('columns' => array('id', 'name'))) as $rm) {
        $roles_map[(string) $rm['id']] = $rm['name'];
    }
} catch (\Throwable $t) { error_log('employee_profile roles: ' . $t->getMessage()); }

// صلاحية إدارة الحسابات = صلاحية تعديل شاشة المستخدمين
$acc_perms = check_page_permissions($conn, 'main/users.php');
$can_manage_accounts = !empty($acc_perms['can_edit']);

// النطاقات: العزل يُحقن عبر {TENANT_SCOPE} — تبقى شروط الحالة/المعرّف مُمعلَمةً
$timesheet_scope = "t.employee_id = ? AND t.status = 1";
$operations_scope = "o.status = 1";

$stats = array();
try {
    $rows_ = $emp_gate->scopedQuery(array('scope' => array('t' => 'timesheet')),
        "SELECT
                COUNT(*) AS shifts_count,
                IFNULL(SUM(t.operator_hours), 0) AS total_operator_hours,
                                IFNULL(SUM(t.operator_standby_hours), 0) AS total_standby_hours,
                COUNT(DISTINCT t.operator) AS operations_count,
                MIN(t.date) AS first_shift_date,
                MAX(t.date) AS last_shift_date
              FROM timesheet t
              WHERE $timesheet_scope AND {TENANT_SCOPE}", array($employee_id));
    $stats = $rows_ ? $rows_[0] : array();
} catch (\Throwable $t) { error_log('employee_profile stats: ' . $t->getMessage()); }

$projects_row = array('projects_count' => 0);
try {
    $rows_ = $emp_gate->scopedQuery(array('scope' => array('t' => 'timesheet', 'o' => 'operations')),
        "SELECT COUNT(DISTINCT o.project_id) AS projects_count
                 FROM timesheet t
                 INNER JOIN operations o ON o.id = t.operator
                 WHERE $timesheet_scope AND $operations_scope AND {TENANT_SCOPE}", array($employee_id));
    if ($rows_) { $projects_row = $rows_[0]; }
} catch (\Throwable $t) { error_log('employee_profile projects count: ' . $t->getMessage()); }

$equipments_count_row = array('equipments_count' => 0);
try {
    $rows_ = $emp_gate->scopedQuery(array('scope' => array('t' => 'timesheet', 'o' => 'operations')),
        "SELECT COUNT(DISTINCT o.equipment) AS equipments_count
                         FROM timesheet t
                         INNER JOIN operations o ON o.id = t.operator
                         WHERE $timesheet_scope AND $operations_scope AND {TENANT_SCOPE}", array($employee_id));
    if ($rows_) { $equipments_count_row = $rows_[0]; }
} catch (\Throwable $t) { error_log('employee_profile equip count: ' . $t->getMessage()); }

$top_equipment = null;
try {
    $rows_ = $emp_gate->scopedQuery(array('scope' => array('t' => 'timesheet', 'o' => 'operations', 'e' => 'equipments')),
        "SELECT
                        e.id,
                        e.name,
                        e.code,
                                                IFNULL(SUM(t.operator_hours), 0) AS total_hours,
                        COUNT(t.id) AS times_used
                      FROM timesheet t
                      INNER JOIN operations o ON o.id = t.operator
                      INNER JOIN equipments e ON e.id = o.equipment
                      WHERE $timesheet_scope AND $operations_scope AND {TENANT_SCOPE}
                      GROUP BY e.id, e.name, e.code
                      ORDER BY total_hours DESC
                      LIMIT 1", array($employee_id));
    $top_equipment = $rows_ ? $rows_[0] : null;
} catch (\Throwable $t) { error_log('employee_profile top equip: ' . $t->getMessage()); }

$equipment_labels = array();
$equipment_hours = array();
try {
    $rows_ = $emp_gate->scopedQuery(array('scope' => array('t' => 'timesheet', 'o' => 'operations', 'e' => 'equipments')),
        "SELECT
                              CONCAT(IFNULL(e.name, 'بدون اسم'), ' (', IFNULL(e.code, '-'), ')') AS equipment_label,
                                                            IFNULL(SUM(t.operator_hours), 0) AS total_hours
                            FROM timesheet t
                            INNER JOIN operations o ON o.id = t.operator
                            INNER JOIN equipments e ON e.id = o.equipment
                            WHERE $timesheet_scope AND $operations_scope AND {TENANT_SCOPE}
                            GROUP BY e.id, e.name, e.code
                            ORDER BY total_hours DESC
                            LIMIT 8", array($employee_id));
    foreach ($rows_ as $row) {
        $equipment_labels[] = $row['equipment_label'];
        $equipment_hours[] = floatval($row['total_hours']);
    }
} catch (\Throwable $t) { error_log('employee_profile equip breakdown: ' . $t->getMessage()); }

$monthly_labels = array();
$monthly_total = array();
$monthly_operator = array();
$monthly_standby = array();
try {
    $rows_ = $emp_gate->scopedQuery(array('scope' => array('t' => 'timesheet', 'o' => 'operations')),
        "SELECT
                  DATE_FORMAT(STR_TO_DATE(t.date, '%Y-%m-%d'), '%Y-%m') AS ym,
                                    IFNULL(SUM(t.operator_hours + t.operator_standby_hours), 0) AS total_hours,
                                    IFNULL(SUM(t.operator_hours), 0) AS operator_hours,
                                    IFNULL(SUM(t.operator_standby_hours), 0) AS standby_hours
                FROM timesheet t
                INNER JOIN operations o ON o.id = t.operator
                WHERE $timesheet_scope AND $operations_scope AND {TENANT_SCOPE}
                GROUP BY ym
                ORDER BY ym", array($employee_id));
    foreach ($rows_ as $row) {
        $monthly_labels[] = $row['ym'] ? $row['ym'] : 'غير محدد';
        $monthly_total[] = floatval($row['total_hours']);
        $monthly_operator[] = floatval($row['operator_hours']);
        $monthly_standby[] = floatval($row['standby_hours']);
    }
} catch (\Throwable $t) { error_log('employee_profile monthly: ' . $t->getMessage()); }

$project_labels = array();
$project_hours = array();
$project_shifts = array();
try {
    $rows_ = $emp_gate->scopedQuery(
        array('scope' => array('t' => 'timesheet', 'o' => 'operations'), 'enrich' => array('p' => 'project')),
        "SELECT
                            IFNULL(p.name, 'مشروع غير محدد') AS project_name,
                                                        IFNULL(SUM(t.operator_hours), 0) AS total_hours,
                            COUNT(t.id) AS shifts_count
                          FROM timesheet t
                          INNER JOIN operations o ON o.id = t.operator
                          LEFT JOIN project p ON p.id = o.project_id
                          WHERE $timesheet_scope AND $operations_scope AND {TENANT_SCOPE}
                          GROUP BY p.id, p.name
                          ORDER BY total_hours DESC
                          LIMIT 8", array($employee_id));
    foreach ($rows_ as $row) {
        $project_labels[] = $row['project_name'];
        $project_hours[] = floatval($row['total_hours']);
        $project_shifts[] = intval($row['shifts_count']);
    }
} catch (\Throwable $t) { error_log('employee_profile project breakdown: ' . $t->getMessage()); }

$movement_result = array();
try {
    $movement_result = $emp_gate->scopedQuery(
        array('scope' => array('t' => 'timesheet', 'o' => 'operations'),
              'enrich' => array('p' => 'project', 'm' => 'mines', 'e' => 'equipments')),
        "SELECT
                   t.date,
                   t.shift,
                   t.operator_hours,
                   t.operator_standby_hours,
                   IFNULL(p.name, 'مشروع غير محدد') AS project_name,
                   IFNULL(m.mine_name, 'منجم غير محدد') AS mine_name,
                   IFNULL(e.name, 'معدة غير محددة') AS equipment_name,
                   IFNULL(e.code, '-') AS equipment_code
                 FROM timesheet t
                 INNER JOIN operations o ON o.id = t.operator
                 LEFT JOIN project p ON p.id = o.project_id
                 LEFT JOIN mines m ON m.id = o.mine_id
                 LEFT JOIN equipments e ON e.id = o.equipment
                 WHERE $timesheet_scope AND $operations_scope AND {TENANT_SCOPE}
                 ORDER BY STR_TO_DATE(t.date, '%Y-%m-%d') DESC, t.id DESC
                 LIMIT 12", array($employee_id));
} catch (\Throwable $t) { error_log('employee_profile movement: ' . $t->getMessage()); }

$assignments_result = array();
try {
    $assignments_result = $emp_gate->scopedQuery(
        array('scope' => array('ed' => 'equipment_drivers'), 'enrich' => array('e' => 'equipments', 's' => 'suppliers')),
        "SELECT
                      ed.start_date,
                      ed.end_date,
                      ed.status,
                      IFNULL(e.name, 'معدة غير محددة') AS equipment_name,
                      IFNULL(e.code, '-') AS equipment_code,
                      IFNULL(s.name, '-') AS supplier_name
                    FROM equipment_drivers ed
                    LEFT JOIN equipments e ON e.id = ed.equipment_id
                    LEFT JOIN suppliers s ON s.id = e.suppliers
                    WHERE ed.employee_id = ? AND {TENANT_SCOPE}
                    ORDER BY ed.id DESC
                    LIMIT 8", array($employee_id));
} catch (\Throwable $t) { error_log('employee_profile assignments: ' . $t->getMessage()); }

$driver_status_class = (isset($driver['status']) && strval($driver['status']) === '1') ? 'active' : 'inactive';
$driver_status_text = (isset($driver['status']) && strval($driver['status']) === '1') ? 'مفعل في النظام' : 'موقوف في النظام';

$page_title = "إيكوبيشن | بطاقة السائق";
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include("../inheader.php");
include("../insidebar.php");
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
/* ── عُدّةُ بطاقةِ الكِيان — المصدرُ نفسُه الذي تستعمله بطاقةُ العميل ─────────
   وكانت بِنيةُ هذه الشاشةِ ٣٢٠ سطرًا مُنطاقةً بـ`.driver-profile-page` في
   `ems.main.all.style.css`: لغةٌ بصريةٌ ثانيةٌ تقول ما تقوله الأولى بمفرداتٍ
   أخرى. أُسقطت، وصارت الشاشتانِ مكوّنًا واحدًا. */
require_once __DIR__ . '/../includes/profile_kit.php';
?>

<script src="/ems/assets/vendor/chartjs/chart.umd.min.js"></script>

<div class="main employee-profile-page ems-profile ems-unified-page-shell">

    <?php
    // Unified page header (structure: includes/page_header.php · styling: ems.main.all.style.css)
    $header_title   = 'بطاقة وبيانات السائق التفصيلية';
    $header_icon    = 'fas fa-id-card-alt';
    $header_actions = array(
        array('href' => 'employee_contracts.php?id=' . intval($employee_id), 'class' => 'add-btn', 'icon' => 'fas fa-file-contract', 'label' => 'عقود السائق'),
        array('href' => 'employee_equipment_history.php?id=' . intval($employee_id), 'class' => 'add-btn', 'icon' => 'fas fa-history', 'label' => 'سجل حركة الآليات'),
    );
    $header_back = array('href' => 'employees.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    // UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
    echo ems_states_bundle('لا ورديات ولا ساعات تشغيل مسجلة لهذا السائق', 'سجل وردية في كشف الدوام لتظهر إحصاءاته ورسومه هنا');
    ?>

    <?php if (isset($_GET['msg']) && trim($_GET['msg']) !== ''): ?>
        <div class="alert alert-info eprof-flash">
            <?php echo htmlspecialchars($_GET['msg'], ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <?php
    /* ══ لوحُ الهوية ═══════════════════════════════════════════════════════
       نفسُ مكوّنِ بطاقةِ العميلِ حرفًا (`ems_profile_hero`) — فالبطاقتانِ لا
       تتشابهان بالمصادفةِ بل لأنهما **المكوّنُ نفسُه**، وأيُّ تحسينٍ يقع على
       إحداهما يقع على الأخرى وعلى ما يأتي بعدَهما بلا نسخ. */
    $ep_facts = array();
    $ep_pf = function ($k) use ($driver) {
        return (isset($driver[$k]) && $driver[$k] !== '' && $driver[$k] !== null) ? $driver[$k] : '';
    };
    $ep_facts[] = array('label' => 'نوع الهوية / رقمها',
        'value' => trim(($ep_pf('identity_type') ?: '—') . ' / ' . ($ep_pf('identity_number') ?: '—')));
    $ep_facts[] = array('label' => 'رقم الهاتف',        'value' => ems_sensitive_display($conn, 'employees.phone', $ep_pf('phone'), 'employee:' . (int)($emp_id ?? 0), 'ملف الموظف'));
    $ep_facts[] = array('label' => 'واتساب',            'value' => $ep_pf('whatsapp'));
    $ep_facts[] = array('label' => 'الجنسية',           'value' => $ep_pf('nationality'));
    $ep_facts[] = array('label' => 'تاريخ الميلاد',     'value' => $ep_pf('birth_date'));
    $ep_facts[] = array('label' => 'فصيلة الدم',        'value' => $ep_pf('blood_type'));
    /* جهةُ الطوارئ اسمٌ ورقمٌ في حقلَين — يُدمجان سطرًا واحدًا، والفارغُ يُعلَن */
    $ep_facts[] = array('label' => 'جهة الطوارئ',
        'value' => trim($ep_pf('emergency_contact_name') . ' ' . $ep_pf('emergency_contact_phone')));
    $ep_facts[] = array('label' => 'المورد',            'value' => $ep_pf('supplier_name'));
    $ep_facts[] = array('label' => 'مستوى الكفاءة',     'value' => $ep_pf('skill_level'));
    $ep_facts[] = array('label' => 'تاريخ بداية العمل', 'value' => $ep_pf('start_date'));

    $ep_active = ($driver_status_class === 'active');
    echo ems_profile_hero(array(
        'name'   => $driver['name'],
        'photo'  => !empty($driver['employee_photo']) ? $driver['employee_photo'] : '',
        'alt'    => 'صورة الموظف ' . $driver['name'],
        'icon'   => 'fas fa-user-tie',
        'note'   => 'بطاقة تعريف الموظف داخل النظام',
        'status' => array(
            'text' => $driver_status_text,
            'tone' => $ep_active ? 'ok' : 'danger',
            'icon' => $ep_active ? 'fas fa-circle-check' : 'fas fa-circle-minus',
        ),
        'chips'  => array(
            array('text' => $ep_pf('employee_code'), 'icon' => 'fas fa-hashtag', 'mono' => true),
            array('text' => $ep_pf('employee_type') ?: 'سائق/مشغّل', 'icon' => 'fas fa-user-gear'),
            array('text' => $ep_pf('nickname'), 'icon' => 'fas fa-quote-right'),
        ),
        'facts'  => $ep_facts,
    ));
    ?>

    <?php if ($users_has_employee_link): ?>
    <section class="ems-profile__section">
        <div class="ems-profile__section-head">
            <h3 class="ems-profile__section-title"><i class="fas fa-user-shield"></i> حساب الدخول للنظام</h3>
        </div>
        <div class="ems-profile__section-body">
            <?php if ($linked_user): ?>
                <?php
                $acc_active = in_array(strtolower(trim((string) $linked_user['status'])), array('1', 'active', 'true', 'نشط'), true);
                $acc_role_name = isset($roles_map[(string) $linked_user['role']]) ? $roles_map[(string) $linked_user['role']] : ('دور #' . $linked_user['role']);
                ?>
                <div class="ems-profile__facts ems-profile__facts--wide">
                    <div class="ems-profile__fact">
                        <span class="ems-profile__fact-label">اسم المستخدم</span>
                        <span class="ems-profile__fact-value"><?php echo htmlspecialchars($linked_user['username']); ?></span>
                    </div>
                    <div class="ems-profile__fact">
                        <span class="ems-profile__fact-label">الدور / الصلاحية</span>
                        <span class="ems-profile__fact-value"><?php echo htmlspecialchars($acc_role_name); ?></span>
                    </div>
                    <div class="ems-profile__fact">
                        <span class="ems-profile__fact-label">حالة الحساب</span>
                        <span class="ems-profile__fact-value">
                            <span class="ems-profile__pill ems-profile__pill--<?php echo $acc_active ? 'ok' : 'neutral'; ?>">
                                <?php echo $acc_active ? 'نشط' : 'موقوف'; ?>
                            </span>
                        </span>
                    </div>
                </div>
                <?php if ($can_manage_accounts): ?>
                    <div class="ems-profile__actions">
                        <a href="../main/users.php?employee_id=<?php echo intval($employee_id); ?>" class="add-btn">
                            <i class="fas fa-user-gear"></i> إدارة الحساب / تغيير الدور
                        </a>
                        <form method="post" class="eprof-inline-form"
                              onsubmit="return confirm('هل تريد سحب حساب الدخول من هذا الموظف وتعطيله؟');">
        <?= csrf_field() ?>
                            <input type="hidden" name="account_action" value="revoke">
                            <input type="hidden" name="target_uid" value="<?php echo intval($linked_user['id']); ?>">
                            <button type="submit" class="add-btn eprof-btn-revoke">
                                <i class="fas fa-user-slash"></i> سحب الحساب
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="alert alert-warning mb-0">لا يملك هذا الموظف حساب دخول للنظام حاليا.</div>
                <?php if ($can_manage_accounts): ?>
                    <div class="ems-profile__actions">
                        <a href="../main/users.php?employee_id=<?php echo intval($employee_id); ?>" class="add-btn">
                            <i class="fas fa-user-plus"></i> إنشاء حساب لهذا الموظف
                        </a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php
    /* شريطُ المؤشرات — نفسُ مكوّنِ بطاقةِ العميل: القيمةُ فوقَ عنوانِها،
       والوحدةُ لاحقةٌ أصغرُ فلا يُقرأ «١٢٤ ساعة» رقمًا واحدًا طويلًا. */
    $ep_op_hours = floatval(isset($stats['total_operator_hours']) ? $stats['total_operator_hours'] : 0);
    $ep_sb_hours = floatval(isset($stats['total_standby_hours']) ? $stats['total_standby_hours'] : 0);
    echo ems_profile_stats(array(
        array('value' => number_format($ep_op_hours, 2), 'unit' => 'ساعة',
              'label' => 'إجمالي ساعات التنفيذ', 'tone' => $ep_op_hours > 0 ? 'ok' : 'muted'),
        /* ساعةُ استعدادٍ ليست ساعةَ تنفيذ — تُعرض على حِدةٍ ولا تُجمع معها */
        array('value' => number_format($ep_sb_hours, 2), 'unit' => 'ساعة',
              'label' => 'ساعات الاستعداد', 'tone' => $ep_sb_hours > 0 ? 'warn' : 'muted'),
        array('value' => intval(isset($stats['shifts_count']) ? $stats['shifts_count'] : 0),
              'label' => 'مرات التشغيل (عدد الورديات)', 'unit' => 'وردية'),
        array('value' => intval($equipments_count_row['equipments_count']),
              'label' => 'عدد الآليات التي عمل عليها'),
        array('value' => intval($projects_row['projects_count']),
              'label' => 'عدد المشاريع التي عمل بها'),
        array('value' => intval(isset($stats['operations_count']) ? $stats['operations_count'] : 0),
              'label' => 'عدد العمليات المختلفة'),
    ));
    ?>

    <section class="ems-profile__section">
        <div class="ems-profile__section-head">
            <h3 class="ems-profile__section-title"><i class="fas fa-trophy"></i> أفضل آلية حقق عليها السائق أعلى ساعات</h3>
        </div>
        <div class="ems-profile__section-body">
            <?php if ($top_equipment): ?>
                <div class="ems-profile__facts ems-profile__facts--wide">
                    <div class="ems-profile__fact">
                        <span class="ems-profile__fact-label">الآلية</span>
                        <span class="ems-profile__fact-value">
                            <?php echo htmlspecialchars($top_equipment['name'] ? $top_equipment['name'] : '-'); ?></span>
                    </div>
                    <div class="ems-profile__fact">
                        <span class="ems-profile__fact-label">كود الآلية</span>
                        <span class="ems-profile__fact-value">
                            <?php echo htmlspecialchars($top_equipment['code'] ? $top_equipment['code'] : '-'); ?></span>
                    </div>
                    <div class="ems-profile__fact">
                        <span class="ems-profile__fact-label">إجمالي الساعات عليها</span>
                        <span class="ems-profile__fact-value"><?php echo number_format(floatval($top_equipment['total_hours']), 2); ?></span>
                    </div>
                    <div class="ems-profile__fact">
                        <span class="ems-profile__fact-label">عدد مرات التشغيل عليها</span>
                        <span class="ems-profile__fact-value"><?php echo intval($top_equipment['times_used']); ?></span>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-warning mb-0">لا توجد بيانات تشغيل كافية لاستخراج أفضل آلية حاليا.</div>
            <?php endif; ?>
        </div>
    </section>

    <section class="ems-profile__section">
        <div class="ems-profile__section-head">
            <h3 class="ems-profile__section-title"><i class="fas fa-id-card"></i> البيانات التفصيلية (مقسمة حسب الأقسام)</h3>
        </div>
        <div class="ems-profile__section-body">
            <div class="ems-profile__facts ems-profile__facts--wide">
                <div class="ems-profile__fact">
                    <span class="ems-profile__fact-label">1) البيانات الأساسية</span>
                    <span class="ems-profile__fact-value">الاسم: <?php echo htmlspecialchars($driver['name']); ?><br>الكنية:
                        <?php echo htmlspecialchars($driver['nickname'] ? $driver['nickname'] : '-'); ?><br>الكود:
                        <?php echo htmlspecialchars($driver['employee_code'] ? $driver['employee_code'] : '-'); ?></span>
                </div>
                <div class="ems-profile__fact">
                    <span class="ems-profile__fact-label">2) الهوية والتوثيق</span>
                    <span class="ems-profile__fact-value">النوع:
                        <?php echo htmlspecialchars($driver['identity_type'] ? $driver['identity_type'] : '-'); ?><br>الرقم:
                        <?php echo htmlspecialchars($driver['identity_number'] ? $driver['identity_number'] : '-'); ?><br>انتهاء
                        الهوية:
                        <?php echo htmlspecialchars($driver['identity_expiry_date'] ? $driver['identity_expiry_date'] : '-'); ?>
                    </span>
                </div>
                <div class="ems-profile__fact">
                    <span class="ems-profile__fact-label">3) الرخصة</span>
                    <span class="ems-profile__fact-value">رقم الرخصة:
                        <?php echo htmlspecialchars($driver['license_number'] ? $driver['license_number'] : '-'); ?><br>النوع:
                        <?php echo htmlspecialchars($driver['license_type'] ? $driver['license_type'] : '-'); ?><br>انتهاء
                        الرخصة:
                        <?php echo htmlspecialchars($driver['license_expiry_date'] ? $driver['license_expiry_date'] : '-'); ?>
                    </span>
                </div>
                <div class="ems-profile__fact">
                    <span class="ems-profile__fact-label">4) التخصص والخبرة</span>
                    <span class="ems-profile__fact-value">المعدات المتخصصة:
                        <?php echo htmlspecialchars($driver['specialized_equipment'] ? $driver['specialized_equipment'] : '-'); ?><br>سنوات
                        المجال:
                        <?php echo htmlspecialchars($driver['years_in_field'] !== null && $driver['years_in_field'] !== '' ? $driver['years_in_field'] : '-'); ?><br>سنوات
                        على المعدة:
                        <?php echo htmlspecialchars($driver['years_on_equipment'] !== null && $driver['years_on_equipment'] !== '' ? $driver['years_on_equipment'] : '-'); ?>
                    </span>
                </div>
                <div class="ems-profile__fact">
                    <span class="ems-profile__fact-label">5) العلاقة الوظيفية</span>
                    <span class="ems-profile__fact-value">المشرف:
                        <?php echo htmlspecialchars($driver['owner_supervisor'] ? $driver['owner_supervisor'] : '-'); ?><br>التبعية:
                        <?php echo htmlspecialchars($driver['employment_affiliation'] ? $driver['employment_affiliation'] : '-'); ?><br>نوع
                        الراتب: <?php echo htmlspecialchars($driver['salary_type'] ? $driver['salary_type'] : '-'); ?>
                    </span>
                </div>
                <div class="ems-profile__fact">
                    <span class="ems-profile__fact-label">6) التواصل</span>
                    <span class="ems-profile__fact-value">الهاتف الأساسي:
                        <?php echo htmlspecialchars(ems_sensitive_display($conn, 'employees.phone', $driver['phone'] ? $driver['phone'] : '-', 'employee:' . (int)($driver['id'] ?? 0), 'ملف الموظف — السائق')); ?><br>الهاتف البديل:
                        <?php echo htmlspecialchars($driver['phone_alternative'] ? $driver['phone_alternative'] : '-'); ?><br>البريد:
                        <?php echo htmlspecialchars($driver['email'] ? $driver['email'] : '-'); ?></span>
                </div>
                <div class="ems-profile__fact">
                    <span class="ems-profile__fact-label">7) الأداء والسلوك</span>
                    <span class="ems-profile__fact-value">تقييم الأداء:
                        <?php echo htmlspecialchars($driver['performance_rating'] ? $driver['performance_rating'] : '-'); ?><br>سجل
                        السلوك:
                        <?php echo htmlspecialchars($driver['behavior_record'] ? $driver['behavior_record'] : '-'); ?><br>سجل
                        الحوادث:
                        <?php echo htmlspecialchars($driver['accident_record'] ? $driver['accident_record'] : '-'); ?>
                    </span>
                </div>
                <div class="ems-profile__fact">
                    <span class="ems-profile__fact-label">8) الصحة والسلامة</span>
                    <span class="ems-profile__fact-value">الحالة الصحية:
                        <?php echo htmlspecialchars($driver['health_status'] ? $driver['health_status'] : '-'); ?><br>المشاكل
                        الصحية:
                        <?php echo htmlspecialchars($driver['health_issues'] ? $driver['health_issues'] : '-'); ?><br>التطعيمات:
                        <?php echo htmlspecialchars($driver['vaccinations_status'] ? $driver['vaccinations_status'] : '-'); ?>
                    </span>
                </div>
                <div class="ems-profile__fact">
                    <span class="ems-profile__fact-label">9) المراجع</span>
                    <span class="ems-profile__fact-value">جهة سابقة:
                        <?php echo htmlspecialchars($driver['previous_employer'] ? $driver['previous_employer'] : '-'); ?><br>مدة
                        العمل:
                        <?php echo htmlspecialchars($driver['employment_duration'] ? $driver['employment_duration'] : '-'); ?><br>مرجع
                        اتصال:
                        <?php echo htmlspecialchars($driver['reference_contact'] ? $driver['reference_contact'] : '-'); ?>
                    </span>
                </div>
                <div class="ems-profile__fact">
                    <span class="ems-profile__fact-label">10) ملاحظات عامة</span>
                    <span class="ems-profile__fact-value">
                        <?php echo nl2br(htmlspecialchars($driver['general_notes'] ? $driver['general_notes'] : '-')); ?>
                    </span>
                </div>
            </div>
        </div>
    </section>

    <section class="ems-profile__section">
        <div class="ems-profile__section-head">
            <h3 class="ems-profile__section-title"><i class="fas fa-images"></i> صور السائق والمستندات (تجهيز مبدئي)</h3>
        </div>
        <div class="ems-profile__section-body">
            <div class="ems-profile__docs">
                <div class="ems-profile__doc">
                    <?php if (!empty($driver['employee_photo'])): ?>
                        <img src="<?php echo htmlspecialchars($driver['employee_photo']); ?>" alt="صورة السائق">
                    <?php else: ?>
                        <div class="ems-profile__doc-empty"><i class="fas fa-camera"></i>صورة السائق<br>قيد التفعيل حاليا</div>
                    <?php endif; ?>
                    <span class="ems-profile__doc-caption">صورة السائق</span>
                </div>
                <div class="ems-profile__doc">
                    <?php if (!empty($driver['identity_photo'])): ?>
                        <img src="<?php echo htmlspecialchars($driver['identity_photo']); ?>" alt="صورة هوية السائق">
                    <?php else: ?>
                        <div class="ems-profile__doc-empty"><i class="fas fa-id-card"></i>صورة الهوية<br>قيد التفعيل حاليا</div>
                    <?php endif; ?>
                    <span class="ems-profile__doc-caption">صورة الهوية</span>
                </div>
            </div>
        </div>
    </section>

    <section class="ems-profile__section">
        <div class="ems-profile__section-head">
            <h3 class="ems-profile__section-title"><i class="fas fa-chart-pie"></i> مخططات إحصائية سريعة</h3>
        </div>
        <div class="ems-profile__section-body">
            <div class="ems-profile__charts">
                <div class="ems-profile__chart">
                    <canvas id="monthlyHoursChart" height="170"></canvas>
                </div>
                <div class="ems-profile__chart">
                    <canvas id="equipmentHoursChart" height="170"></canvas>
                </div>
                <div class="ems-profile__chart">
                    <canvas id="projectsChart" height="170"></canvas>
                </div>
            </div>
        </div>
    </section>

    <section class="ems-profile__section">
        <div class="ems-profile__section-head">
            <h3 class="ems-profile__section-title"><i class="fas fa-route"></i> حركة السائق داخل المشاريع (من مشروع لآخر)</h3>
        </div>
        <div class="ems-profile__section-body">
            <ul class="ems-profile__timeline">
                <?php if ($movement_result && count($movement_result) > 0): ?>
                    <?php foreach ($movement_result as $mv): ?>
                        <li class="ems-profile__timeline-item">
                            <div class="ems-profile__timeline-top">
                                <span><?php echo htmlspecialchars($mv['date'] ? $mv['date'] : '-'); ?></span>
                                <span><?php echo htmlspecialchars($mv['shift'] ? $mv['shift'] : '-'); ?></span>
                            </div>
                            <div class="ems-profile__timeline-meta">
                                مشروع: <?php echo htmlspecialchars($mv['project_name']); ?> |
                                منجم: <?php echo htmlspecialchars($mv['mine_name']); ?> |
                                آلية: <?php echo htmlspecialchars($mv['equipment_name']); ?>
                                (<?php echo htmlspecialchars($mv['equipment_code']); ?>) |
                                تنفيذ: <?php echo number_format(floatval($mv['operator_hours']), 2); ?> |
                                استعداد: <?php echo number_format(floatval($mv['operator_standby_hours']), 2); ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <li class="ems-profile__timeline-item">لا توجد بيانات حركة داخل المشاريع لهذا السائق حتى الآن.</li>
                <?php endif; ?>
            </ul>
        </div>
    </section>

    <section class="ems-profile__section">
        <div class="ems-profile__section-head">
            <h3 class="ems-profile__section-title"><i class="fas fa-truck"></i> آخر ربط للآليات مع السائق</h3>
        </div>
        <div class="ems-profile__section-body table-responsive">
            <table class="table table-striped table-bordered align-middle text-center">
                <thead>
                    <tr>
                        <th>الآلية</th>
                        <th>المورد</th>
                        <th>من تاريخ</th>
                        <th>إلى تاريخ</th>
                        <th>الحالة</th>
                        <!-- E-03 موجة ٤: النواة الحاكمة (gov_columns) — الخلايا يحشوها ui-unification.js -->
                        <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صف بلا كيان مالك">الكيان</th>
                        <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المنشئ — الاسم والصفة</th>
                        <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمد — الاسم والصفة</th>
                        <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمد — تفويض أو سلطة أصلية">مرجع التفويض</th>
                        <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
                        <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
                        <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
                        </tr>
                </thead>
                <tbody>
                    <?php if ($assignments_result && count($assignments_result) > 0): ?>
                        <?php foreach ($assignments_result as $as): ?>
                            <?php $is_active_assignment = (intval($as['status']) === 1); ?>
                            <tr>
                                <td><?php echo htmlspecialchars($as['equipment_name'] . ' (' . $as['equipment_code'] . ')'); ?>
                                </td>
                                <td><?php echo htmlspecialchars($as['supplier_name']); ?></td>
                                <td><?php echo htmlspecialchars($as['start_date'] ? $as['start_date'] : '-'); ?></td>
                                <td><?php echo htmlspecialchars(ems_format_open_end($as['end_date'])); ?></td>
                                <td>
                                    <span class="ems-profile__pill ems-profile__pill--<?php echo $is_active_assignment ? 'ok' : 'neutral'; ?>">
                                        <?php echo $is_active_assignment ? 'يعمل حاليا' : 'سابق'; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5">لا يوجد ربط آليات مسجل لهذا السائق.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<script>
    (function () {
        /* UXW-01 ①: ألوانُ الرسومِ تُقرأ من رموزِ CSS المعرَّفةِ في كتلةِ الأنماطِ
           أعلاه (‎--eprof-*‎) بدلَ قيمٍ مثبَّتةٍ هنا — والقيمُ نفسُها لم تتغير. */
        const emsPalette = getComputedStyle(document.documentElement);
        const eprofColor = function (name) {
            return (emsPalette.getPropertyValue('--eprof-' + name) || '').trim();
        };

        const monthlyLabels = <?php echo json_encode($monthly_labels); ?>;
        const monthlyTotal = <?php echo json_encode($monthly_total); ?>;
        const monthlyOperator = <?php echo json_encode($monthly_operator); ?>;
        const monthlyStandby = <?php echo json_encode($monthly_standby); ?>;

        const equipmentLabels = <?php echo json_encode($equipment_labels); ?>;
        const equipmentHours = <?php echo json_encode($equipment_hours); ?>;

        const projectLabels = <?php echo json_encode($project_labels); ?>;
        const projectHours = <?php echo json_encode($project_hours); ?>;
        const projectShifts = <?php echo json_encode($project_shifts); ?>;

        const hasMonthlyData = monthlyLabels.length > 0;
        const hasEquipmentData = equipmentLabels.length > 0;
        const hasProjectData = projectLabels.length > 0;

        /* UI-DEF-07 (L4): لا رسمَ بمحاورَ افتراضيةٍ وبياناتٍ صفرية — حالةٌ
           فارغةٌ مفسَّرةٌ بدلَه (emsChartGuard يستعمل EmsUI متى حضر). */
        /* INJ-0238 · INJ-0432: كان الحارسُ مكتوبًا هنا نسخةً محليةً — وهو نفسُه
           ما تشتكي منه الملاحظة (مكوّنٌ مشتركٌ بمتبنّينَ قلائل). صار يفوّض
           للمكوّنِ المركزيِّ `EmsUI.chartGuard`، ويبقى ارتدادٌ أدنى إن لم
           يُحمَّل — بلا بناءِ مظهرٍ ثانٍ. */
        function emsChartGuard(ctx, hasData, renderFn) {
            if (window.EmsUI && typeof EmsUI.chartGuard === 'function') {
                return EmsUI.chartGuard(ctx, hasData, renderFn);
            }
            if (hasData) { return renderFn(); }
            var host = ctx && ctx.parentNode ? ctx.parentNode : null;
            if (host) { host.setAttribute('data-ems-chart-state', 'empty'); }
            return null;
        }

        const monthlyCtx = document.getElementById('monthlyHoursChart');
        emsChartGuard(monthlyCtx, hasMonthlyData, function () {
        return new Chart(monthlyCtx, {
            type: 'bar',
            data: {
                labels: hasMonthlyData ? monthlyLabels : ['لا توجد بيانات'],
                datasets: [
                    {
                        label: 'إجمالي الساعات',
                        data: hasMonthlyData ? monthlyTotal : [0],
                        backgroundColor: eprofColor('bar-total'),
                        borderRadius: 8
                    },
                    {
                        label: 'ساعات المشغل المنفذة',
                        data: hasMonthlyData ? monthlyOperator : [0],
                        backgroundColor: eprofColor('bar-operator'),
                        borderRadius: 8
                    },
                    {
                        label: 'ساعات الاستعداد',
                        data: hasMonthlyData ? monthlyStandby : [0],
                        backgroundColor: eprofColor('bar-standby'),
                        borderRadius: 8
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'bottom' },
                    title: { display: true, text: 'تطور ساعات العمل شهريا' }
                },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
        });

        const equipmentCtx = document.getElementById('equipmentHoursChart');
        emsChartGuard(equipmentCtx, hasEquipmentData, function () {
        return new Chart(equipmentCtx, {
            type: 'doughnut',
            data: {
                labels: hasEquipmentData ? equipmentLabels : ['لا توجد بيانات'],
                datasets: [{
                    data: hasEquipmentData ? equipmentHours : [1],
                    backgroundColor: hasEquipmentData
                        ? ['slice-1','slice-2','slice-3','slice-4','slice-5','slice-6','slice-7','slice-8'].map(eprofColor)
                        : [eprofColor('slice-none')]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'bottom' },
                    title: { display: true, text: 'توزيع الساعات حسب الآلية' }
                }
            }
        });
        });

        const projectsCtx = document.getElementById('projectsChart');
        emsChartGuard(projectsCtx, hasProjectData, function () {
        return new Chart(projectsCtx, {
            type: 'line',
            data: {
                labels: hasProjectData ? projectLabels : ['لا توجد بيانات'],
                datasets: [
                    {
                        label: 'إجمالي ساعات كل مشروع',
                        data: hasProjectData ? projectHours : [0],
                        borderColor: eprofColor('line-hours'),
                        backgroundColor: eprofColor('line-hours-fill'),
                        tension: 0.35,
                        fill: true
                    },
                    {
                        label: 'عدد الورديات في المشروع',
                        data: hasProjectData ? projectShifts : [0],
                        borderColor: eprofColor('line-shifts'),
                        backgroundColor: eprofColor('line-shifts-fill'),
                        tension: 0.35,
                        fill: false,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'bottom' },
                    title: { display: true, text: 'الأداء عبر المشاريع' }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        position: 'left',
                        title: { display: true, text: 'ساعات' }
                    },
                    y1: {
                        beginAtZero: true,
                        position: 'right',
                        grid: { drawOnChartArea: false },
                        title: { display: true, text: 'ورديات' }
                    }
                }
            }
        });
        });
    })();
</script>

</body>

</html>
