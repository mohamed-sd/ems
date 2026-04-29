<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}

include '../config.php';
include '../includes/permissions_helper.php';

$current_role = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;

if (!$is_super_admin && $company_id <= 0) {
    header("Location: ../login.php?msg=لا+توجد+بيئة+شركة+صالحة+للمستخدم+❌");
    exit();
}

$drivers_has_company = db_table_has_column($conn, 'drivers', 'company_id');
$timesheet_has_company = db_table_has_column($conn, 'timesheet', 'company_id');
$operations_has_company = db_table_has_column($conn, 'operations', 'company_id');
$equipment_drivers_has_company = db_table_has_column($conn, 'equipment_drivers', 'company_id');
$suppliers_has_company = db_table_has_column($conn, 'suppliers', 'company_id');

$driver_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($driver_id <= 0) {
    header("Location: drivers.php?msg=معرف+السائق+غير+صحيح+❌");
    exit();
}

$driver_scope = "d.id = $driver_id";
if (!$is_super_admin && $drivers_has_company) {
    $driver_scope .= " AND d.company_id = $company_id";
}

$supplier_join_scope = '';
if (!$is_super_admin && $suppliers_has_company) {
    $supplier_join_scope = " AND s.company_id = $company_id";
}

$driver_sql = "SELECT d.*, s.name AS supplier_name
               FROM drivers d
               LEFT JOIN suppliers s ON d.supplier_id = s.id$supplier_join_scope
               WHERE $driver_scope
               LIMIT 1";
$driver_result = mysqli_query($conn, $driver_sql);
$driver = ($driver_result && mysqli_num_rows($driver_result) > 0) ? mysqli_fetch_assoc($driver_result) : null;

if (!$driver) {
    header("Location: drivers.php?msg=السائق+غير+موجود+او+خارج+نطاق+الشركة+❌");
    exit();
}

$timesheet_scope = "t.driver = '$driver_id' AND t.status = 1";
$operations_scope = "o.status = 1";
$equipment_drivers_scope = "ed.driver_id = $driver_id";

if (!$is_super_admin && $timesheet_has_company) {
    $timesheet_scope .= " AND t.company_id = $company_id";
}
if (!$is_super_admin && $operations_has_company) {
    $operations_scope .= " AND o.company_id = $company_id";
}
if (!$is_super_admin && $equipment_drivers_has_company) {
    $equipment_drivers_scope .= " AND ed.company_id = $company_id";
}

$stats_sql = "SELECT
                COUNT(*) AS shifts_count,
                IFNULL(SUM(t.operator_hours), 0) AS total_operator_hours,
                                IFNULL(SUM(t.operator_standby_hours), 0) AS total_standby_hours,
                COUNT(DISTINCT t.operator) AS operations_count,
                MIN(t.date) AS first_shift_date,
                MAX(t.date) AS last_shift_date
              FROM timesheet t
              WHERE $timesheet_scope";
$stats_result = mysqli_query($conn, $stats_sql);
$stats = ($stats_result && mysqli_num_rows($stats_result) > 0) ? mysqli_fetch_assoc($stats_result) : array();

$projects_sql = "SELECT COUNT(DISTINCT o.project_id) AS projects_count
                 FROM timesheet t
                 INNER JOIN operations o ON o.id = t.operator
                 WHERE $timesheet_scope AND $operations_scope";
$projects_result = mysqli_query($conn, $projects_sql);
$projects_row = ($projects_result && mysqli_num_rows($projects_result) > 0) ? mysqli_fetch_assoc($projects_result) : array('projects_count' => 0);

$equipments_count_sql = "SELECT COUNT(DISTINCT o.equipment) AS equipments_count
                         FROM timesheet t
                         INNER JOIN operations o ON o.id = t.operator
                         WHERE $timesheet_scope AND $operations_scope";
$equipments_count_result = mysqli_query($conn, $equipments_count_sql);
$equipments_count_row = ($equipments_count_result && mysqli_num_rows($equipments_count_result) > 0) ? mysqli_fetch_assoc($equipments_count_result) : array('equipments_count' => 0);

$top_equipment_sql = "SELECT
                        e.id,
                        e.name,
                        e.code,
                                                IFNULL(SUM(t.operator_hours), 0) AS total_hours,
                        COUNT(t.id) AS times_used
                      FROM timesheet t
                      INNER JOIN operations o ON o.id = t.operator
                      INNER JOIN equipments e ON e.id = o.equipment
                      WHERE $timesheet_scope AND $operations_scope
                      GROUP BY e.id, e.name, e.code
                      ORDER BY total_hours DESC
                      LIMIT 1";
$top_equipment_result = mysqli_query($conn, $top_equipment_sql);
$top_equipment = ($top_equipment_result && mysqli_num_rows($top_equipment_result) > 0) ? mysqli_fetch_assoc($top_equipment_result) : null;

$equipment_breakdown_sql = "SELECT
                              CONCAT(IFNULL(e.name, 'بدون اسم'), ' (', IFNULL(e.code, '-'), ')') AS equipment_label,
                                                            IFNULL(SUM(t.operator_hours), 0) AS total_hours
                            FROM timesheet t
                            INNER JOIN operations o ON o.id = t.operator
                            INNER JOIN equipments e ON e.id = o.equipment
                            WHERE $timesheet_scope AND $operations_scope
                            GROUP BY e.id, e.name, e.code
                            ORDER BY total_hours DESC
                            LIMIT 8";
$equipment_breakdown_result = mysqli_query($conn, $equipment_breakdown_sql);
$equipment_labels = array();
$equipment_hours = array();
if ($equipment_breakdown_result) {
    while ($row = mysqli_fetch_assoc($equipment_breakdown_result)) {
        $equipment_labels[] = $row['equipment_label'];
        $equipment_hours[] = floatval($row['total_hours']);
    }
}

$monthly_sql = "SELECT
                  DATE_FORMAT(STR_TO_DATE(t.date, '%Y-%m-%d'), '%Y-%m') AS ym,
                                    IFNULL(SUM(t.operator_hours + t.operator_standby_hours), 0) AS total_hours,
                                    IFNULL(SUM(t.operator_hours), 0) AS operator_hours,
                                    IFNULL(SUM(t.operator_standby_hours), 0) AS standby_hours
                FROM timesheet t
                INNER JOIN operations o ON o.id = t.operator
                WHERE $timesheet_scope AND $operations_scope
                GROUP BY ym
                ORDER BY ym";
$monthly_result = mysqli_query($conn, $monthly_sql);
$monthly_labels = array();
$monthly_total = array();
$monthly_operator = array();
$monthly_standby = array();
if ($monthly_result) {
    while ($row = mysqli_fetch_assoc($monthly_result)) {
        $monthly_labels[] = $row['ym'] ? $row['ym'] : 'غير محدد';
        $monthly_total[] = floatval($row['total_hours']);
        $monthly_operator[] = floatval($row['operator_hours']);
        $monthly_standby[] = floatval($row['standby_hours']);
    }
}

$project_breakdown_sql = "SELECT
                            IFNULL(p.name, 'مشروع غير محدد') AS project_name,
                                                        IFNULL(SUM(t.operator_hours), 0) AS total_hours,
                            COUNT(t.id) AS shifts_count
                          FROM timesheet t
                          INNER JOIN operations o ON o.id = t.operator
                          LEFT JOIN project p ON p.id = o.project_id
                          WHERE $timesheet_scope AND $operations_scope
                          GROUP BY p.id, p.name
                          ORDER BY total_hours DESC
                          LIMIT 8";
$project_breakdown_result = mysqli_query($conn, $project_breakdown_sql);
$project_labels = array();
$project_hours = array();
$project_shifts = array();
if ($project_breakdown_result) {
    while ($row = mysqli_fetch_assoc($project_breakdown_result)) {
        $project_labels[] = $row['project_name'];
        $project_hours[] = floatval($row['total_hours']);
        $project_shifts[] = intval($row['shifts_count']);
    }
}

$movement_sql = "SELECT
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
                 WHERE $timesheet_scope AND $operations_scope
                 ORDER BY STR_TO_DATE(t.date, '%Y-%m-%d') DESC, t.id DESC
                 LIMIT 12";
$movement_result = mysqli_query($conn, $movement_sql);

$assignments_sql = "SELECT
                      ed.start_date,
                      ed.end_date,
                      ed.status,
                      IFNULL(e.name, 'معدة غير محددة') AS equipment_name,
                      IFNULL(e.code, '-') AS equipment_code,
                      IFNULL(s.name, '-') AS supplier_name
                    FROM equipment_drivers ed
                    LEFT JOIN equipments e ON e.id = ed.equipment_id
                    LEFT JOIN suppliers s ON s.id = e.suppliers
                    WHERE $equipment_drivers_scope
                    ORDER BY ed.id DESC
                    LIMIT 8";
$assignments_result = mysqli_query($conn, $assignments_sql);

$driver_status_class = (isset($driver['status']) && strval($driver['status']) === '1') ? 'active' : 'inactive';
$driver_status_text = (isset($driver['status']) && strval($driver['status']) === '1') ? 'مفعل في النظام' : 'موقوف في النظام';

$page_title = "إيكوبيشن | بطاقة السائق";
include("../inheader.php");
include("../insidebar.php");
?>

<link rel="stylesheet" href="/ems/assets/css/all.min.css">
<link href="/ems/assets/css/local-fonts.css" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/main_admin_style.css">
<script src="/ems/assets/vendor/chartjs/chart.umd.min.js"></script>

<style>
.driver-profile-page {
    font-family: 'Cairo', sans-serif;
    --brand-navy: #0f1f45;
    --brand-blue: #1f4aa8;
    --brand-sky: #3b82f6;
    --text-strong: #0f172a;
    --text-muted: #64748b;
    --surface: #ffffff;
    --line: #dbe5f2;
    background:
        radial-gradient(circle at 95% 5%, rgba(59, 130, 246, 0.18), transparent 35%),
        radial-gradient(circle at 2% 22%, rgba(22, 163, 74, 0.12), transparent 30%),
        linear-gradient(180deg, #f4f8ff 0%, #f8fbff 40%, #f7f9fc 100%);
    border-radius: 20px;
    padding: 14px;
    animation: pageFadeIn .45s ease;
}

.driver-profile-page .page-header {
    background: var(--surface);
    border: 1px solid var(--line);
    border-radius: 16px;
    padding: 14px 16px;
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
}

.profile-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.profile-actions .add-btn,
.profile-actions .back-btn {
    border-radius: 10px;
    font-weight: 700;
    transition: transform .2s ease, box-shadow .2s ease;
}

.profile-actions .add-btn:hover,
.profile-actions .back-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 8px 18px rgba(15, 23, 42, 0.14);
}

.identity-card {
    position: relative;
    background: linear-gradient(140deg, var(--brand-navy) 0%, #1d3f87 45%, #10244f 100%);
    border-radius: 20px;
    padding: 26px;
    color: #fff;
    overflow: hidden;
    box-shadow: 0 14px 36px rgba(15, 23, 42, 0.28);
    margin: 18px 0 20px;
    border: 1px solid rgba(148, 163, 184, 0.28);
}

.identity-card::before,
.identity-card::after {
    content: '';
    position: absolute;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.08);
}

.identity-card::before {
    width: 240px;
    height: 240px;
    top: -120px;
    left: -80px;
}

.identity-card::after {
    width: 200px;
    height: 200px;
    bottom: -95px;
    right: -60px;
}

.id-grid {
    display: grid;
    grid-template-columns: 170px 1fr;
    gap: 22px;
    position: relative;
    z-index: 2;
}

.photo-box {
    border-radius: 16px;
    background: rgba(255, 255, 255, 0.12);
    border: 1px solid rgba(255, 255, 255, 0.25);
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 190px;
    overflow: hidden;
    box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.08), 0 10px 22px rgba(15, 23, 42, 0.24);
}

.photo-box img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.photo-placeholder {
    text-align: center;
    color: rgba(255, 255, 255, 0.9);
}

.photo-placeholder i {
    font-size: 46px;
    display: block;
    margin-bottom: 10px;
}

.id-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 12px;
}

.id-head h2 {
    margin: 0;
    font-size: 1.68rem;
    letter-spacing: .2px;
    font-weight: 800;
}

.driver-badge {
    padding: 6px 12px;
    border-radius: 999px;
    font-size: .82rem;
    font-weight: 800;
}

.driver-badge.active {
    background: rgba(16, 185, 129, 0.2);
    color: #bbf7d0;
    border: 1px solid rgba(167, 243, 208, 0.45);
}

.driver-badge.inactive {
    background: rgba(239, 68, 68, 0.2);
    color: #fecaca;
    border: 1px solid rgba(252, 165, 165, 0.45);
}

.id-meta {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
    gap: 10px;
}

.id-meta .item {
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 12px;
    padding: 11px 12px;
    backdrop-filter: blur(2px);
}

.id-meta .label {
    font-size: .78rem;
    color: rgba(255, 255, 255, 0.75);
    margin-bottom: 4px;
}

.id-meta .value {
    font-size: .96rem;
    font-weight: 700;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
    gap: 12px;
    margin-bottom: 22px;
}

.stat-card {
    background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
    border: 1px solid #dbe5f2;
    border-radius: 16px;
    padding: 14px 14px 12px;
    box-shadow: 0 7px 20px rgba(15, 23, 42, 0.08);
    position: relative;
    overflow: hidden;
    transition: transform .22s ease, box-shadow .22s ease;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    width: 100%;
    height: 4px;
    background: linear-gradient(90deg, #1f4aa8, #3b82f6);
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 11px 26px rgba(15, 23, 42, 0.14);
}

.stat-label {
    color: var(--text-muted);
    font-weight: 700;
    font-size: .83rem;
    margin-bottom: 5px;
}

.stat-value {
    color: var(--text-strong);
    font-size: 1.42rem;
    font-weight: 800;
}

.section-card {
    background: var(--surface);
    border: 1px solid #dbe5f2;
    border-radius: 18px;
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
    margin-bottom: 16px;
    overflow: hidden;
}

.section-title {
    margin: 0;
    font-size: 1.02rem;
    font-weight: 800;
    color: var(--text-strong);
}

.section-head {
    border-bottom: 1px solid #e9eff7;
    padding: 14px 18px;
    background: linear-gradient(180deg, #fdfefe 0%, #f3f8ff 100%);
    border-radius: 18px 18px 0 0;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.section-title i {
    margin-left: 6px;
    color: var(--brand-blue);
}

.section-body {
    padding: 16px 18px;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 10px;
}

.info-item {
    background: linear-gradient(180deg, #f9fbff 0%, #f4f8ff 100%);
    border: 1px solid #dde6f3;
    border-radius: 12px;
    padding: 11px;
}

.info-item .label {
    color: var(--text-muted);
    font-size: .8rem;
    font-weight: 700;
}

.info-item .value {
    color: #0f172a;
    margin-top: 4px;
    font-weight: 700;
}

.charts-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 14px;
}

.timeline-list {
    list-style: none;
    margin: 0;
    padding: 0;
}

.timeline-item {
    border: 1px solid #dde6f3;
    border-radius: 12px;
    padding: 11px;
    margin-bottom: 10px;
    background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
    position: relative;
}

.timeline-item::before {
    content: '';
    position: absolute;
    right: 0;
    top: 0;
    width: 4px;
    height: 100%;
    border-radius: 0 12px 12px 0;
    background: linear-gradient(180deg, #1f4aa8 0%, #3b82f6 100%);
}

.timeline-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    margin-bottom: 6px;
    font-weight: 700;
    color: #1e293b;
}

.timeline-meta {
    color: #64748b;
    font-size: .85rem;
}

.assignment-status {
    padding: 3px 9px;
    border-radius: 999px;
    font-size: .78rem;
    font-weight: 700;
}

.assignment-status.active {
    background: #dcfce7;
    color: #166534;
    border: 1px solid #86efac;
}

.assignment-status.old {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fca5a5;
}

.photo-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 12px;
}

.doc-photo {
    border: 1px dashed #9db3d4;
    border-radius: 12px;
    min-height: 170px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(180deg, #f9fbff 0%, #f4f8ff 100%);
    position: relative;
    overflow: hidden;
}

.doc-photo img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.doc-caption {
    position: absolute;
    bottom: 8px;
    right: 8px;
    background: rgba(17, 37, 82, 0.82);
    color: #fff;
    border-radius: 8px;
    padding: 4px 8px;
    font-size: .75rem;
}

.doc-placeholder {
    text-align: center;
    color: #64748b;
    font-weight: 700;
}

.doc-placeholder i {
    display: block;
    font-size: 32px;
    margin-bottom: 6px;
}

.driver-profile-page .table {
    margin-bottom: 0;
    border-color: #dbe5f2;
}

.driver-profile-page .table thead th {
    background: #edf4ff;
    color: #17306b;
    border-color: #d5e3f5;
    font-weight: 800;
}

.driver-profile-page .table tbody td {
    border-color: #e5edf8;
    vertical-align: middle;
}

.driver-profile-page .table-striped > tbody > tr:nth-of-type(odd) > * {
    background: #f8fbff;
}

canvas {
    background: #fff;
    border: 1px solid #dbe5f2;
    border-radius: 12px;
    padding: 8px;
}

@keyframes pageFadeIn {
    from {
        opacity: 0;
        transform: translateY(8px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@media (max-width: 768px) {
    .driver-profile-page {
        padding: 8px;
        border-radius: 14px;
    }

    .id-grid {
        grid-template-columns: 1fr;
    }

    .section-head,
    .section-body {
        padding: 12px;
    }
}
</style>

<div class="main driver-profile-page">
    <div class="page-header">
        <h1 class="page-title">
            <div class="title-icon"><i class="fas fa-id-card-alt"></i></div>
            بطاقة وبيانات السائق التفصيلية
        </h1>
        <div class="profile-actions">
            <a href="drivers.php" class="back-btn"><i class="fas fa-arrow-right"></i> رجوع لقائمة السائقين</a>
            <a href="driver_truck_history.php?id=<?php echo intval($driver_id); ?>" class="add-btn" style="text-decoration:none;"><i class="fas fa-history"></i> سجل حركة الآليات</a>
            <a href="drivercontracts.php?id=<?php echo intval($driver_id); ?>" class="add-btn" style="text-decoration:none;"><i class="fas fa-file-contract"></i> عقود السائق</a>
        </div>
    </div>

    <div class="identity-card">
        <div class="id-grid">
            <div class="photo-box">
                <?php if (!empty($driver['driver_photo'])): ?>
                    <img src="<?php echo htmlspecialchars($driver['driver_photo']); ?>" alt="صورة السائق">
                <?php else: ?>
                    <div class="photo-placeholder">
                        <i class="fas fa-user-circle"></i>
                        صورة السائق
                        <div style="font-size:.8rem; opacity:.85; margin-top:4px;">قيد التفعيل</div>
                    </div>
                <?php endif; ?>
            </div>
            <div>
                <div class="id-head">
                    <h2><?php echo htmlspecialchars($driver['name']); ?></h2>
                    <span class="driver-badge <?php echo $driver_status_class; ?>"><?php echo htmlspecialchars($driver_status_text); ?></span>
                </div>
                <div style="margin-bottom:10px; color:rgba(255,255,255,.82); font-weight:600;">بطاقة تعريف المشغل داخل النظام</div>
                <div class="id-meta">
                    <div class="item">
                        <div class="label">كود السائق</div>
                        <div class="value"><?php echo htmlspecialchars($driver['driver_code'] ? $driver['driver_code'] : 'غير محدد'); ?></div>
                    </div>
                    <div class="item">
                        <div class="label">رقم الهاتف</div>
                        <div class="value"><?php echo htmlspecialchars($driver['phone'] ? $driver['phone'] : '-'); ?></div>
                    </div>
                    <div class="item">
                        <div class="label">نوع الهوية / رقمها</div>
                        <div class="value"><?php echo htmlspecialchars(($driver['identity_type'] ? $driver['identity_type'] : '-') . ' / ' . ($driver['identity_number'] ? $driver['identity_number'] : '-')); ?></div>
                    </div>
                    <div class="item">
                        <div class="label">المورد</div>
                        <div class="value"><?php echo htmlspecialchars($driver['supplier_name'] ? $driver['supplier_name'] : '-'); ?></div>
                    </div>
                    <div class="item">
                        <div class="label">مستوى الكفاءة</div>
                        <div class="value"><?php echo htmlspecialchars($driver['skill_level'] ? $driver['skill_level'] : 'غير محدد'); ?></div>
                    </div>
                    <div class="item">
                        <div class="label">تاريخ بداية العمل</div>
                        <div class="value"><?php echo htmlspecialchars($driver['start_date'] ? $driver['start_date'] : '-'); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">إجمالي ساعات التنفيذ</div>
            <div class="stat-value"><?php echo number_format(floatval(isset($stats['total_operator_hours']) ? $stats['total_operator_hours'] : 0), 2); ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">ساعات الاستعداد</div>
            <div class="stat-value"><?php echo number_format(floatval(isset($stats['total_standby_hours']) ? $stats['total_standby_hours'] : 0), 2); ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">مرات التشغيل (عدد الورديات)</div>
            <div class="stat-value"><?php echo intval(isset($stats['shifts_count']) ? $stats['shifts_count'] : 0); ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">عدد الآليات التي عمل عليها</div>
            <div class="stat-value"><?php echo intval($equipments_count_row['equipments_count']); ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">عدد المشاريع التي عمل بها</div>
            <div class="stat-value"><?php echo intval($projects_row['projects_count']); ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">عدد العمليات المختلفة</div>
            <div class="stat-value"><?php echo intval(isset($stats['operations_count']) ? $stats['operations_count'] : 0); ?></div>
        </div>
    </div>

    <div class="section-card">
        <div class="section-head">
            <h3 class="section-title"><i class="fas fa-trophy"></i> أفضل آلية حقق عليها السائق أعلى ساعات</h3>
        </div>
        <div class="section-body">
            <?php if ($top_equipment): ?>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="label">الآلية</div>
                        <div class="value"><?php echo htmlspecialchars($top_equipment['name'] ? $top_equipment['name'] : '-'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="label">كود الآلية</div>
                        <div class="value"><?php echo htmlspecialchars($top_equipment['code'] ? $top_equipment['code'] : '-'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="label">إجمالي الساعات عليها</div>
                        <div class="value"><?php echo number_format(floatval($top_equipment['total_hours']), 2); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="label">عدد مرات التشغيل عليها</div>
                        <div class="value"><?php echo intval($top_equipment['times_used']); ?></div>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-warning mb-0">لا توجد بيانات تشغيل كافية لاستخراج أفضل آلية حالياً.</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="section-card">
        <div class="section-head">
            <h3 class="section-title"><i class="fas fa-id-card"></i> البيانات التفصيلية (مقسمة حسب الأقسام)</h3>
        </div>
        <div class="section-body">
            <div class="info-grid" style="margin-bottom:12px;">
                <div class="info-item"><div class="label">1) البيانات الأساسية</div><div class="value">الاسم: <?php echo htmlspecialchars($driver['name']); ?><br>الكنية: <?php echo htmlspecialchars($driver['nickname'] ? $driver['nickname'] : '-'); ?><br>الكود: <?php echo htmlspecialchars($driver['driver_code'] ? $driver['driver_code'] : '-'); ?></div></div>
                <div class="info-item"><div class="label">2) الهوية والتوثيق</div><div class="value">النوع: <?php echo htmlspecialchars($driver['identity_type'] ? $driver['identity_type'] : '-'); ?><br>الرقم: <?php echo htmlspecialchars($driver['identity_number'] ? $driver['identity_number'] : '-'); ?><br>انتهاء الهوية: <?php echo htmlspecialchars($driver['identity_expiry_date'] ? $driver['identity_expiry_date'] : '-'); ?></div></div>
                <div class="info-item"><div class="label">3) الرخصة</div><div class="value">رقم الرخصة: <?php echo htmlspecialchars($driver['license_number'] ? $driver['license_number'] : '-'); ?><br>النوع: <?php echo htmlspecialchars($driver['license_type'] ? $driver['license_type'] : '-'); ?><br>انتهاء الرخصة: <?php echo htmlspecialchars($driver['license_expiry_date'] ? $driver['license_expiry_date'] : '-'); ?></div></div>
                <div class="info-item"><div class="label">4) التخصص والخبرة</div><div class="value">المعدات المتخصصة: <?php echo htmlspecialchars($driver['specialized_equipment'] ? $driver['specialized_equipment'] : '-'); ?><br>سنوات المجال: <?php echo htmlspecialchars($driver['years_in_field'] !== null && $driver['years_in_field'] !== '' ? $driver['years_in_field'] : '-'); ?><br>سنوات على المعدة: <?php echo htmlspecialchars($driver['years_on_equipment'] !== null && $driver['years_on_equipment'] !== '' ? $driver['years_on_equipment'] : '-'); ?></div></div>
                <div class="info-item"><div class="label">5) العلاقة الوظيفية</div><div class="value">المشرف: <?php echo htmlspecialchars($driver['owner_supervisor'] ? $driver['owner_supervisor'] : '-'); ?><br>التبعية: <?php echo htmlspecialchars($driver['employment_affiliation'] ? $driver['employment_affiliation'] : '-'); ?><br>نوع الراتب: <?php echo htmlspecialchars($driver['salary_type'] ? $driver['salary_type'] : '-'); ?></div></div>
                <div class="info-item"><div class="label">6) التواصل</div><div class="value">الهاتف الأساسي: <?php echo htmlspecialchars($driver['phone'] ? $driver['phone'] : '-'); ?><br>الهاتف البديل: <?php echo htmlspecialchars($driver['phone_alternative'] ? $driver['phone_alternative'] : '-'); ?><br>البريد: <?php echo htmlspecialchars($driver['email'] ? $driver['email'] : '-'); ?></div></div>
                <div class="info-item"><div class="label">7) الأداء والسلوك</div><div class="value">تقييم الأداء: <?php echo htmlspecialchars($driver['performance_rating'] ? $driver['performance_rating'] : '-'); ?><br>سجل السلوك: <?php echo htmlspecialchars($driver['behavior_record'] ? $driver['behavior_record'] : '-'); ?><br>سجل الحوادث: <?php echo htmlspecialchars($driver['accident_record'] ? $driver['accident_record'] : '-'); ?></div></div>
                <div class="info-item"><div class="label">8) الصحة والسلامة</div><div class="value">الحالة الصحية: <?php echo htmlspecialchars($driver['health_status'] ? $driver['health_status'] : '-'); ?><br>المشاكل الصحية: <?php echo htmlspecialchars($driver['health_issues'] ? $driver['health_issues'] : '-'); ?><br>التطعيمات: <?php echo htmlspecialchars($driver['vaccinations_status'] ? $driver['vaccinations_status'] : '-'); ?></div></div>
                <div class="info-item"><div class="label">9) المراجع</div><div class="value">جهة سابقة: <?php echo htmlspecialchars($driver['previous_employer'] ? $driver['previous_employer'] : '-'); ?><br>مدة العمل: <?php echo htmlspecialchars($driver['employment_duration'] ? $driver['employment_duration'] : '-'); ?><br>مرجع اتصال: <?php echo htmlspecialchars($driver['reference_contact'] ? $driver['reference_contact'] : '-'); ?></div></div>
                <div class="info-item"><div class="label">10) ملاحظات عامة</div><div class="value"><?php echo nl2br(htmlspecialchars($driver['general_notes'] ? $driver['general_notes'] : '-')); ?></div></div>
            </div>
        </div>
    </div>

    <div class="section-card">
        <div class="section-head">
            <h3 class="section-title"><i class="fas fa-images"></i> صور السائق والمستندات (تجهيز مبدئي)</h3>
        </div>
        <div class="section-body">
            <div class="photo-grid">
                <div class="doc-photo">
                    <?php if (!empty($driver['driver_photo'])): ?>
                        <img src="<?php echo htmlspecialchars($driver['driver_photo']); ?>" alt="صورة السائق">
                    <?php else: ?>
                        <div class="doc-placeholder"><i class="fas fa-camera"></i>صورة السائق<br>قيد التفعيل حالياً</div>
                    <?php endif; ?>
                    <span class="doc-caption">صورة السائق</span>
                </div>
                <div class="doc-photo">
                    <?php if (!empty($driver['identity_photo'])): ?>
                        <img src="<?php echo htmlspecialchars($driver['identity_photo']); ?>" alt="صورة هوية السائق">
                    <?php else: ?>
                        <div class="doc-placeholder"><i class="fas fa-id-card"></i>صورة الهوية<br>قيد التفعيل حالياً</div>
                    <?php endif; ?>
                    <span class="doc-caption">صورة الهوية</span>
                </div>
            </div>
        </div>
    </div>

    <div class="section-card">
        <div class="section-head">
            <h3 class="section-title"><i class="fas fa-chart-pie"></i> مخططات إحصائية سريعة</h3>
        </div>
        <div class="section-body">
            <div class="charts-grid">
                <div>
                    <canvas id="monthlyHoursChart" height="170"></canvas>
                </div>
                <div>
                    <canvas id="equipmentHoursChart" height="170"></canvas>
                </div>
                <div>
                    <canvas id="projectsChart" height="170"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="section-card">
        <div class="section-head">
            <h3 class="section-title"><i class="fas fa-route"></i> حركة السائق داخل المشاريع (من مشروع لآخر)</h3>
        </div>
        <div class="section-body">
            <ul class="timeline-list">
                <?php if ($movement_result && mysqli_num_rows($movement_result) > 0): ?>
                    <?php while ($mv = mysqli_fetch_assoc($movement_result)): ?>
                        <li class="timeline-item">
                            <div class="timeline-top">
                                <span><?php echo htmlspecialchars($mv['date'] ? $mv['date'] : '-'); ?></span>
                                <span><?php echo htmlspecialchars($mv['shift'] ? $mv['shift'] : '-'); ?></span>
                            </div>
                            <div class="timeline-meta">
                                مشروع: <?php echo htmlspecialchars($mv['project_name']); ?> |
                                منجم: <?php echo htmlspecialchars($mv['mine_name']); ?> |
                                آلية: <?php echo htmlspecialchars($mv['equipment_name']); ?> (<?php echo htmlspecialchars($mv['equipment_code']); ?>) |
                                تنفيذ: <?php echo number_format(floatval($mv['operator_hours']), 2); ?> |
                                استعداد: <?php echo number_format(floatval($mv['operator_standby_hours']), 2); ?>
                            </div>
                        </li>
                    <?php endwhile; ?>
                <?php else: ?>
                    <li class="timeline-item">لا توجد بيانات حركة داخل المشاريع لهذا السائق حتى الآن.</li>
                <?php endif; ?>
            </ul>
        </div>
    </div>

    <div class="section-card">
        <div class="section-head">
            <h3 class="section-title"><i class="fas fa-truck"></i> آخر ربط للآليات مع السائق</h3>
        </div>
        <div class="section-body table-responsive">
            <table class="table table-striped table-bordered align-middle text-center">
                <thead>
                    <tr>
                        <th>الآلية</th>
                        <th>المورد</th>
                        <th>من تاريخ</th>
                        <th>إلى تاريخ</th>
                        <th>الحالة</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($assignments_result && mysqli_num_rows($assignments_result) > 0): ?>
                        <?php while ($as = mysqli_fetch_assoc($assignments_result)): ?>
                            <?php $is_active_assignment = (intval($as['status']) === 1); ?>
                            <tr>
                                <td><?php echo htmlspecialchars($as['equipment_name'] . ' (' . $as['equipment_code'] . ')'); ?></td>
                                <td><?php echo htmlspecialchars($as['supplier_name']); ?></td>
                                <td><?php echo htmlspecialchars($as['start_date'] ? $as['start_date'] : '-'); ?></td>
                                <td><?php echo htmlspecialchars($as['end_date'] ? $as['end_date'] : '-'); ?></td>
                                <td>
                                    <span class="assignment-status <?php echo $is_active_assignment ? 'active' : 'old'; ?>">
                                        <?php echo $is_active_assignment ? 'يعمل حالياً' : 'سابق'; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5">لا يوجد ربط آليات مسجل لهذا السائق.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
(function() {
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

    const monthlyCtx = document.getElementById('monthlyHoursChart');
    new Chart(monthlyCtx, {
        type: 'bar',
        data: {
            labels: hasMonthlyData ? monthlyLabels : ['لا توجد بيانات'],
            datasets: [
                {
                    label: 'إجمالي الساعات',
                    data: hasMonthlyData ? monthlyTotal : [0],
                    backgroundColor: 'rgba(37, 99, 235, 0.78)',
                    borderRadius: 8
                },
                {
                    label: 'ساعات المشغل المنفذة',
                    data: hasMonthlyData ? monthlyOperator : [0],
                    backgroundColor: 'rgba(16, 185, 129, 0.78)',
                    borderRadius: 8
                },
                {
                    label: 'ساعات الاستعداد',
                    data: hasMonthlyData ? monthlyStandby : [0],
                    backgroundColor: 'rgba(245, 158, 11, 0.78)',
                    borderRadius: 8
                }
            ]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'bottom' },
                title: { display: true, text: 'تطور ساعات العمل شهرياً' }
            },
            scales: {
                y: { beginAtZero: true }
            }
        }
    });

    const equipmentCtx = document.getElementById('equipmentHoursChart');
    new Chart(equipmentCtx, {
        type: 'doughnut',
        data: {
            labels: hasEquipmentData ? equipmentLabels : ['لا توجد بيانات'],
            datasets: [{
                data: hasEquipmentData ? equipmentHours : [1],
                backgroundColor: hasEquipmentData
                    ? ['#1d4ed8', '#0ea5e9', '#22c55e', '#f59e0b', '#ef4444', '#8b5cf6', '#14b8a6', '#f97316']
                    : ['#cbd5e1']
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

    const projectsCtx = document.getElementById('projectsChart');
    new Chart(projectsCtx, {
        type: 'line',
        data: {
            labels: hasProjectData ? projectLabels : ['لا توجد بيانات'],
            datasets: [
                {
                    label: 'إجمالي ساعات كل مشروع',
                    data: hasProjectData ? projectHours : [0],
                    borderColor: '#0f172a',
                    backgroundColor: 'rgba(15, 23, 42, 0.15)',
                    tension: 0.35,
                    fill: true
                },
                {
                    label: 'عدد الورديات في المشروع',
                    data: hasProjectData ? projectShifts : [0],
                    borderColor: '#e11d48',
                    backgroundColor: 'rgba(225, 29, 72, 0.2)',
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
})();
</script>

</body>
</html>
