<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: ../login.php');
    exit();
}

include '../config.php';
include '../includes/permissions_helper.php';
require_once '../includes/driver_contract_dates.php';

$current_role = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;

if (!$is_super_admin && $company_id <= 0) {
    header('Location: ../login.php?msg=لا+توجد+بيئة+شركة+صالحة+للمستخدم+❌');
    exit();
}

$equipment_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($equipment_id <= 0) {
    header('Location: equipments.php?msg=معرف+المعدة+غير+صحيح+❌');
    exit();
}

$equipments_has_company = db_table_has_column($conn, 'equipments', 'company_id');
$operations_has_company = db_table_has_column($conn, 'operations', 'company_id');
$equipment_drivers_has_company = db_table_has_column($conn, 'equipment_drivers', 'company_id');

// صلاحية اعتماد الكرت = صلاحية تعديل المعدات (دور الأسطول)
$__pp = function_exists('check_page_permissions') ? check_page_permissions($conn, 'equipments_fleet') : ['can_edit' => true];
$can_edit = !empty($__pp['can_edit']);

$scope = "e.id = $equipment_id";
if (!$is_super_admin && $equipments_has_company) {
    $scope .= " AND e.company_id = $company_id";
}

$equipment_query = "SELECT e.*, s.name AS supplier_name, et.type AS equipment_type_name
                    FROM equipments e
                    LEFT JOIN suppliers s ON s.id = e.suppliers
                    LEFT JOIN equipments_types et ON et.id = e.type
                    WHERE $scope
                    LIMIT 1";
$equipment_result = mysqli_query($conn, $equipment_query);
$equipment = ($equipment_result && mysqli_num_rows($equipment_result) > 0) ? mysqli_fetch_assoc($equipment_result) : null;

if (!$equipment) {
    header('Location: equipments.php?msg=المعدة+غير+موجودة+او+خارج+نطاق+الشركة+❌');
    exit();
}

$ops_scope = "o.equipment = $equipment_id";
if (!$is_super_admin && $operations_has_company) {
    $ops_scope .= " AND o.company_id = $company_id";
}
$ed_scope = "ed.equipment_id = $equipment_id";
if (!$is_super_admin && $equipment_drivers_has_company) {
    $ed_scope .= " AND ed.company_id = $company_id";
}

$operations_count = 0;
$active_operations = 0;
$projects_count = 0;
$drivers_count = 0;
$hours_sum = 0;
$standby_sum = 0;

$r = mysqli_query($conn, "SELECT COUNT(*) AS c FROM operations o WHERE $ops_scope");
if ($r) {
    $operations_count = intval(mysqli_fetch_assoc($r)['c']);
}
$r = mysqli_query($conn, "SELECT COUNT(*) AS c FROM operations o WHERE $ops_scope AND o.status = 1");
if ($r) {
    $active_operations = intval(mysqli_fetch_assoc($r)['c']);
}
$r = mysqli_query($conn, "SELECT COUNT(DISTINCT o.project_id) AS c FROM operations o WHERE $ops_scope");
if ($r) {
    $projects_count = intval(mysqli_fetch_assoc($r)['c']);
}
$r = mysqli_query($conn, "SELECT COUNT(DISTINCT ed.driver_id) AS c FROM equipment_drivers ed WHERE $ed_scope AND ed.status = 1");
if ($r) {
    $drivers_count = intval(mysqli_fetch_assoc($r)['c']);
}
$r = mysqli_query($conn, "SELECT IFNULL(SUM(t.operator_hours),0) AS op_hours,
                                IFNULL(SUM(t.operator_standby_hours),0) AS standby_hours
                         FROM timesheet t
                         INNER JOIN operations o ON o.id = t.operator
                         WHERE $ops_scope AND t.status = 1");
if ($r) {
    $hours_row = mysqli_fetch_assoc($r);
    $hours_sum = floatval($hours_row['op_hours']);
    $standby_sum = floatval($hours_row['standby_hours']);
}

$projects_list = mysqli_query($conn, "SELECT
                            p.id,
                            p.name,
                            p.project_code,
                            IFNULL(SUM(t.operator_hours + t.operator_standby_hours),0) AS total_hours,
                            COUNT(t.id) AS shifts_count
                        FROM operations o
                        LEFT JOIN project p ON p.id = o.project_id
                        LEFT JOIN timesheet t ON t.operator = o.id AND t.status = 1
                        WHERE $ops_scope
                        GROUP BY p.id, p.name, p.project_code
                        ORDER BY total_hours DESC
                        LIMIT 10");

$drivers_list = mysqli_query($conn, "SELECT
                           d.id,
                           d.name,
                           ed.start_date,
                           ed.end_date,
                           ed.status
                        FROM equipment_drivers ed
                        INNER JOIN drivers d ON d.id = ed.driver_id
                        WHERE $ed_scope
                        ORDER BY ed.id DESC
                        LIMIT 10");

$page_title = 'إيكوبيشن | بطاقة المعدة';
include '../inheader.php';
include '../insidebar.php';
?>

<style>
.equipment-profile-page .profile-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:12px; margin-bottom:14px; }
.equipment-profile-page .profile-card { background:#fff; border:1px solid #ece6d8; border-radius:12px; padding:12px; }
.equipment-profile-page .kpi { font-weight:800; font-size:1.4rem; color:#0f766e; }
.equipment-profile-page .label { color:#6b7280; font-size:.9rem; }
</style>

<div class="main equipment-profile-page ems-unified-page-shell">
    <?php
    // Unified page header (structure: includes/page_header.php · styling: ems.main.all.style.css)
    $header_title   = 'بطاقة المعدة / الشاحنة';
    $header_icon    = 'fas fa-id-card';
    $header_actions = array(
        array('href' => 'add_drivers.php?equipment_id=' . intval($equipment_id), 'class' => 'add-btn', 'icon' => 'fas fa-user-cog', 'label' => 'إدارة المشغلين'),
        array('href' => 'equipments.php?edit=' . intval($equipment_id), 'class' => 'add-btn', 'icon' => 'fas fa-edit', 'label' => 'تعديل المعدة'),
    );
    $header_back = array('href' => 'equipments.php', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    ?>

    <div class="profile-card" style="margin-bottom:12px;">
        <h2 style="margin:0 0 8px 0;"><?php echo htmlspecialchars($equipment['name']); ?></h2>
        <div class="label">
            الكود: <?php echo htmlspecialchars($equipment['code']); ?> |
            النوع: <?php echo htmlspecialchars($equipment['equipment_type_name'] ?: $equipment['type']); ?> |
            المورد: <?php echo htmlspecialchars($equipment['supplier_name'] ?: '-'); ?> |
            الحالة: <?php echo intval($equipment['status']) === 1 ? 'متاحة' : 'مشغولة'; ?>
        </div>
        <div class="label" style="margin-top:6px;">
            الموديل: <?php echo htmlspecialchars($equipment['model'] ?: '-'); ?> |
            سنة الصنع: <?php echo htmlspecialchars($equipment['manufacturing_year'] ?: '-'); ?> |
            رقم الهيكل: <?php echo htmlspecialchars($equipment['chassis_number'] ?: '-'); ?>
        </div>
        <?php
        // حالة الكرت (حوكمة خفيفة)
        $card_state = isset($equipment['card_state']) ? $equipment['card_state'] : 'active';
        $card_is_active = ($card_state === 'active');
        ?>
        <div style="margin-top:10px;">
            <?php if ($card_is_active): ?>
                <span class="status-active" style="padding:4px 10px;border-radius:6px;background:#e7f7ee;color:#1f9d55;font-weight:700;">
                    <i class="fas fa-id-card"></i> كرت نشط (معتمد)
                </span>
            <?php else: ?>
                <span class="status-inactive" style="padding:4px 10px;border-radius:6px;background:#fdeaea;color:#c0392b;font-weight:700;">
                    <i class="fas fa-id-card"></i> كرت مسودة
                </span>
                <?php if (!empty($can_edit ?? true)): ?>
                    <form method="post" action="approve_card.php" class="d-inline" style="margin-inline-start:8px"
                          onsubmit="return confirm('اعتماد كرت هذه المعدة؟');">
                        <input type="hidden" name="equipment_id" value="<?php echo intval($equipment_id); ?>">
                        <input type="hidden" name="return" value="equipment_profile.php">
                        <input type="hidden" name="return_id" value="<?php echo intval($equipment_id); ?>">
                        <button type="submit" class="btn btn-success" style="padding:4px 12px;">
                            <i class="fas fa-circle-check"></i> اعتماد الكرت
                        </button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <?php
    // ── بطاقة: الهوية والمصدر + العدّاد (كرت المعدة) ──
    $pf = function ($k) use ($equipment) {
        return isset($equipment[$k]) && $equipment[$k] !== '' && $equipment[$k] !== null
            ? htmlspecialchars((string) $equipment[$k]) : '—';
    };
    $cap = (isset($equipment['capacity']) && $equipment['capacity'] !== '' && $equipment['capacity'] !== null)
        ? (htmlspecialchars((string) $equipment['capacity']) . ' ' . htmlspecialchars((string) ($equipment['capacity_uom'] ?? ''))) : '—';
    $acq = (isset($equipment['acquisition_cost']) && $equipment['acquisition_cost'] !== '' && $equipment['acquisition_cost'] !== null)
        ? (htmlspecialchars((string) $equipment['acquisition_cost']) . ' ' . htmlspecialchars((string) ($equipment['acquisition_currency'] ?? ''))) : '—';
    $meter = (isset($equipment['opening_meter']) && $equipment['opening_meter'] !== '' && $equipment['opening_meter'] !== null)
        ? (htmlspecialchars((string) $equipment['opening_meter']) . ' ' . htmlspecialchars((string) ($equipment['meter_uom'] ?? ''))) : '—';
    ?>
    <div class="card" style="margin-bottom:14px;">
        <div class="card-header"><h5><i class="fas fa-id-badge"></i> الهوية والمصدر والعدّاد</h5></div>
        <div class="card-body">
            <div class="profile-grid">
                <div class="profile-card"><div class="label">الفئة التشغيلية</div><div><?php echo $pf('operating_category'); ?></div></div>
                <div class="profile-card"><div class="label">بلد الصنع</div><div><?php echo $pf('origin_country'); ?></div></div>
                <div class="profile-card"><div class="label">رقم الموتور</div><div><?php echo $pf('engine_no'); ?></div></div>
                <div class="profile-card"><div class="label">رقم اللوحة</div><div><?php echo $pf('plate_no'); ?></div></div>
                <div class="profile-card"><div class="label">السعة/القدرة</div><div><?php echo $cap; ?></div></div>
                <div class="profile-card"><div class="label">المقاسات الفنية</div><div><?php echo $pf('dimensions'); ?></div></div>
                <div class="profile-card"><div class="label">نوع المصدر</div><div><?php echo $pf('source_type'); ?></div></div>
                <div class="profile-card"><div class="label">تاريخ الدخول</div><div><?php echo $pf('entry_date'); ?></div></div>
                <div class="profile-card"><div class="label">تكلفة الشراء</div><div><?php echo $acq; ?></div></div>
                <div class="profile-card"><div class="label">العدّاد الافتتاحي</div><div><?php echo $meter; ?></div></div>
                <div class="profile-card"><div class="label">مصدر العدّاد</div><div><?php echo $pf('meter_source'); ?></div></div>
            </div>
        </div>
    </div>

    <div class="profile-grid">
        <div class="profile-card"><div class="kpi"><?php echo $operations_count; ?></div><div class="label">إجمالي عمليات التشغيل</div></div>
        <div class="profile-card"><div class="kpi"><?php echo $active_operations; ?></div><div class="label">عمليات نشطة</div></div>
        <div class="profile-card"><div class="kpi"><?php echo $projects_count; ?></div><div class="label">المشاريع المرتبطة</div></div>
        <div class="profile-card"><div class="kpi"><?php echo $drivers_count; ?></div><div class="label">المشغلون النشطون</div></div>
        <div class="profile-card"><div class="kpi"><?php echo number_format($hours_sum, 0); ?></div><div class="label">ساعات التشغيل</div></div>
        <div class="profile-card"><div class="kpi"><?php echo number_format($standby_sum, 0); ?></div><div class="label">ساعات الاستعداد</div></div>
    </div>

    <div class="card" style="margin-bottom:14px;">
        <div class="card-header"><h5><i class="fas fa-project-diagram"></i> المشاريع المرتبطة بالمعدة</h5></div>
        <div class="card-body">
            <table id="equipmentProjectsTable" class="display" style="width:100%;">
                <thead><tr><th>المشروع</th><th>كود المشروع</th><th>الساعات</th><th>عدد الورديات</th></tr></thead>
                <tbody>
                    <?php if ($projects_list): while ($row = mysqli_fetch_assoc($projects_list)): ?>
                        <tr>
                            <td><?php if (!empty($row['id'])): ?><a href="../Projects/project_profile.php?id=<?php echo intval($row['id']); ?>"><?php echo htmlspecialchars($row['name']); ?></a><?php else: ?>غير محدد<?php endif; ?></td>
                            <td><?php echo htmlspecialchars($row['project_code'] ?: '-'); ?></td>
                            <td><?php echo number_format($row['total_hours'], 0); ?></td>
                            <td><?php echo intval($row['shifts_count']); ?></td>
                        </tr>
                    <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h5><i class="fas fa-users"></i> آخر المشغلين المرتبطين</h5></div>
        <div class="card-body">
            <table id="equipmentDriversTable" class="display" style="width:100%;">
                <thead><tr><th>المشغل</th><th>تاريخ البداية</th><th>تاريخ النهاية</th><th>الحالة</th></tr></thead>
                <tbody>
                    <?php if ($drivers_list): while ($row = mysqli_fetch_assoc($drivers_list)): ?>
                        <tr>
                            <td><a href="../Drivers/driver_profile.php?id=<?php echo intval($row['id']); ?>"><?php echo htmlspecialchars($row['name']); ?></a></td>
                            <td><?php echo htmlspecialchars($row['start_date']); ?></td>
                            <td><?php echo htmlspecialchars(ems_format_open_end($row['end_date'])); ?></td>
                            <td><?php echo intval($row['status']) === 1 ? 'نشط' : 'متوقف'; ?></td>
                        </tr>
                    <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="/ems/assets/vendor/jquery-3.7.1.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/jquery.dataTables.min.js"></script>
<script>
$(function () {
    $('#equipmentProjectsTable').DataTable({ language: { url: '/ems/assets/i18n/datatables/ar.json' } });
    $('#equipmentDriversTable').DataTable({ language: { url: '/ems/assets/i18n/datatables/ar.json' } });
});
</script>
