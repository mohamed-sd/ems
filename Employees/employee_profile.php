<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}

include '../config.php';
include '../includes/permissions_helper.php';
require_once '../includes/driver_contract_dates.php';

$current_role = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;

if (!$is_super_admin && $company_id <= 0) {
    header("Location: ../login.php?msg=لا+توجد+بيئة+شركة+صالحة+للمستخدم+❌");
    exit();
}

$drivers_has_company = db_table_has_column($conn, 'employees', 'company_id');
$timesheet_has_company = db_table_has_column($conn, 'timesheet', 'company_id');
$operations_has_company = db_table_has_column($conn, 'operations', 'company_id');
$equipment_drivers_has_company = db_table_has_column($conn, 'equipment_drivers', 'company_id');
$suppliers_has_company = db_table_has_column($conn, 'suppliers', 'company_id');

$employee_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($employee_id <= 0) {
    header("Location: employees.php?msg=معرف+السائق+غير+صحيح+❌");
    exit();
}

$driver_scope = "d.id = $employee_id";
if (!$is_super_admin && $drivers_has_company) {
    $driver_scope .= " AND d.company_id = $company_id";
}

$supplier_join_scope = '';
if (!$is_super_admin && $suppliers_has_company) {
    $supplier_join_scope = " AND s.company_id = $company_id";
}

$driver_sql = "SELECT d.*, s.name AS supplier_name
               FROM employees d
               LEFT JOIN suppliers s ON d.supplier_id = s.id$supplier_join_scope
               WHERE $driver_scope
               LIMIT 1";
$driver_result = mysqli_query($conn, $driver_sql);
$driver = ($driver_result && mysqli_num_rows($driver_result) > 0) ? mysqli_fetch_assoc($driver_result) : null;

if (!$driver) {
    header("Location: employees.php?msg=السائق+غير+موجود+او+خارج+نطاق+الشركة+❌");
    exit();
}

$timesheet_scope = "t.employee_id = '$employee_id' AND t.status = 1";
$operations_scope = "o.status = 1";
$equipment_drivers_scope = "ed.employee_id = $employee_id";

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

<script src="/ems/assets/vendor/chartjs/chart.umd.min.js"></script>

<div class="main driver-profile-page ems-unified-page-shell">

    <?php
    // Unified page header (structure: includes/page_header.php · styling: ems.main.all.style.css)
    $header_title   = 'بطاقة وبيانات السائق التفصيلية';
    $header_icon    = 'fas fa-id-card-alt';
    $header_actions = array(
        array('href' => 'employee_contracts.php?id=' . intval($employee_id), 'class' => 'add-btn driver-profile-link-btn', 'icon' => 'fas fa-file-contract', 'label' => 'عقود السائق'),
        array('href' => 'employee_equipment_history.php?id=' . intval($employee_id), 'class' => 'add-btn driver-profile-link-btn', 'icon' => 'fas fa-history', 'label' => 'سجل حركة الآليات'),
    );
    $header_back = array('href' => 'employees.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    ?>

    <div class="identity-card">
        <div class="id-grid">
            <div class="photo-box">
                <?php if (!empty($driver['employee_photo'])): ?>
                    <img src="<?php echo htmlspecialchars($driver['employee_photo']); ?>" alt="صورة السائق">
                <?php else: ?>
                    <div class="photo-placeholder">
                        <i class="fas fa-user-circle"></i>
                        صورة السائق
                        <div class="driver-profile-photo-note">قيد التفعيل</div>
                    </div>
                <?php endif; ?>
            </div>
            <div>
                <div class="id-head">
                    <h2><?php echo htmlspecialchars($driver['name']); ?></h2>
                    <span
                        class="driver-badge <?php echo $driver_status_class; ?>"><?php echo htmlspecialchars($driver_status_text); ?></span>
                </div>
                <div class="driver-profile-id-note">بطاقة تعريف الموظف داخل النظام</div>
                <div class="id-meta">
                    <div class="item">
                        <div class="label">نوع الموظف</div>
                        <div class="value">
                            <?php echo htmlspecialchars(!empty($driver['employee_type']) ? $driver['employee_type'] : 'سائق/مشغّل'); ?>
                        </div>
                    </div>
                    <div class="item">
                        <div class="label">كود الموظف</div>
                        <div class="value">
                            <?php echo htmlspecialchars($driver['employee_code'] ? $driver['employee_code'] : 'غير محدد'); ?>
                        </div>
                    </div>
                    <div class="item">
                        <div class="label">رقم الهاتف</div>
                        <div class="value"><?php echo htmlspecialchars($driver['phone'] ? $driver['phone'] : '-'); ?>
                        </div>
                    </div>
                    <div class="item">
                        <div class="label">نوع الهوية / رقمها</div>
                        <div class="value">
                            <?php echo htmlspecialchars(($driver['identity_type'] ? $driver['identity_type'] : '-') . ' / ' . ($driver['identity_number'] ? $driver['identity_number'] : '-')); ?>
                        </div>
                    </div>
                    <?php
                    $__pf = function ($k) use ($driver) {
                        return htmlspecialchars((isset($driver[$k]) && $driver[$k] !== '' && $driver[$k] !== null) ? $driver[$k] : '-');
                    };
                    ?>
                    <div class="item"><div class="label">الجنسية</div><div class="value"><?php echo $__pf('nationality'); ?></div></div>
                    <div class="item"><div class="label">تاريخ الميلاد</div><div class="value"><?php echo $__pf('birth_date'); ?></div></div>
                    <div class="item"><div class="label">فصيلة الدم</div><div class="value"><?php echo $__pf('blood_type'); ?></div></div>
                    <div class="item"><div class="label">واتساب</div><div class="value"><?php echo $__pf('whatsapp'); ?></div></div>
                    <div class="item"><div class="label">جهة الطوارئ</div><div class="value"><?php echo htmlspecialchars(trim(((isset($driver['emergency_contact_name']) ? $driver['emergency_contact_name'] : '') . ' ' . (isset($driver['emergency_contact_phone']) ? $driver['emergency_contact_phone'] : ''))) ?: '-'); ?></div></div>
                    <div class="item">
                        <div class="label">المورد</div>
                        <div class="value">
                            <?php echo htmlspecialchars($driver['supplier_name'] ? $driver['supplier_name'] : '-'); ?>
                        </div>
                    </div>
                    <div class="item">
                        <div class="label">مستوى الكفاءة</div>
                        <div class="value">
                            <?php echo htmlspecialchars($driver['skill_level'] ? $driver['skill_level'] : 'غير محدد'); ?>
                        </div>
                    </div>
                    <div class="item">
                        <div class="label">تاريخ بداية العمل</div>
                        <div class="value">
                            <?php echo htmlspecialchars($driver['start_date'] ? $driver['start_date'] : '-'); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">إجمالي ساعات التنفيذ</div>
            <div class="stat-value">
                <?php echo number_format(floatval(isset($stats['total_operator_hours']) ? $stats['total_operator_hours'] : 0), 2); ?>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-label">ساعات الاستعداد</div>
            <div class="stat-value">
                <?php echo number_format(floatval(isset($stats['total_standby_hours']) ? $stats['total_standby_hours'] : 0), 2); ?>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-label">مرات التشغيل (عدد الورديات)</div>
            <div class="stat-value"><?php echo intval(isset($stats['shifts_count']) ? $stats['shifts_count'] : 0); ?>
            </div>
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
            <div class="stat-value">
                <?php echo intval(isset($stats['operations_count']) ? $stats['operations_count'] : 0); ?></div>
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
                        <div class="value">
                            <?php echo htmlspecialchars($top_equipment['name'] ? $top_equipment['name'] : '-'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="label">كود الآلية</div>
                        <div class="value">
                            <?php echo htmlspecialchars($top_equipment['code'] ? $top_equipment['code'] : '-'); ?></div>
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
            <div class="info-grid driver-profile-info-grid-gap">
                <div class="info-item">
                    <div class="label">1) البيانات الأساسية</div>
                    <div class="value">الاسم: <?php echo htmlspecialchars($driver['name']); ?><br>الكنية:
                        <?php echo htmlspecialchars($driver['nickname'] ? $driver['nickname'] : '-'); ?><br>الكود:
                        <?php echo htmlspecialchars($driver['employee_code'] ? $driver['employee_code'] : '-'); ?></div>
                </div>
                <div class="info-item">
                    <div class="label">2) الهوية والتوثيق</div>
                    <div class="value">النوع:
                        <?php echo htmlspecialchars($driver['identity_type'] ? $driver['identity_type'] : '-'); ?><br>الرقم:
                        <?php echo htmlspecialchars($driver['identity_number'] ? $driver['identity_number'] : '-'); ?><br>انتهاء
                        الهوية:
                        <?php echo htmlspecialchars($driver['identity_expiry_date'] ? $driver['identity_expiry_date'] : '-'); ?>
                    </div>
                </div>
                <div class="info-item">
                    <div class="label">3) الرخصة</div>
                    <div class="value">رقم الرخصة:
                        <?php echo htmlspecialchars($driver['license_number'] ? $driver['license_number'] : '-'); ?><br>النوع:
                        <?php echo htmlspecialchars($driver['license_type'] ? $driver['license_type'] : '-'); ?><br>انتهاء
                        الرخصة:
                        <?php echo htmlspecialchars($driver['license_expiry_date'] ? $driver['license_expiry_date'] : '-'); ?>
                    </div>
                </div>
                <div class="info-item">
                    <div class="label">4) التخصص والخبرة</div>
                    <div class="value">المعدات المتخصصة:
                        <?php echo htmlspecialchars($driver['specialized_equipment'] ? $driver['specialized_equipment'] : '-'); ?><br>سنوات
                        المجال:
                        <?php echo htmlspecialchars($driver['years_in_field'] !== null && $driver['years_in_field'] !== '' ? $driver['years_in_field'] : '-'); ?><br>سنوات
                        على المعدة:
                        <?php echo htmlspecialchars($driver['years_on_equipment'] !== null && $driver['years_on_equipment'] !== '' ? $driver['years_on_equipment'] : '-'); ?>
                    </div>
                </div>
                <div class="info-item">
                    <div class="label">5) العلاقة الوظيفية</div>
                    <div class="value">المشرف:
                        <?php echo htmlspecialchars($driver['owner_supervisor'] ? $driver['owner_supervisor'] : '-'); ?><br>التبعية:
                        <?php echo htmlspecialchars($driver['employment_affiliation'] ? $driver['employment_affiliation'] : '-'); ?><br>نوع
                        الراتب: <?php echo htmlspecialchars($driver['salary_type'] ? $driver['salary_type'] : '-'); ?>
                    </div>
                </div>
                <div class="info-item">
                    <div class="label">6) التواصل</div>
                    <div class="value">الهاتف الأساسي:
                        <?php echo htmlspecialchars($driver['phone'] ? $driver['phone'] : '-'); ?><br>الهاتف البديل:
                        <?php echo htmlspecialchars($driver['phone_alternative'] ? $driver['phone_alternative'] : '-'); ?><br>البريد:
                        <?php echo htmlspecialchars($driver['email'] ? $driver['email'] : '-'); ?></div>
                </div>
                <div class="info-item">
                    <div class="label">7) الأداء والسلوك</div>
                    <div class="value">تقييم الأداء:
                        <?php echo htmlspecialchars($driver['performance_rating'] ? $driver['performance_rating'] : '-'); ?><br>سجل
                        السلوك:
                        <?php echo htmlspecialchars($driver['behavior_record'] ? $driver['behavior_record'] : '-'); ?><br>سجل
                        الحوادث:
                        <?php echo htmlspecialchars($driver['accident_record'] ? $driver['accident_record'] : '-'); ?>
                    </div>
                </div>
                <div class="info-item">
                    <div class="label">8) الصحة والسلامة</div>
                    <div class="value">الحالة الصحية:
                        <?php echo htmlspecialchars($driver['health_status'] ? $driver['health_status'] : '-'); ?><br>المشاكل
                        الصحية:
                        <?php echo htmlspecialchars($driver['health_issues'] ? $driver['health_issues'] : '-'); ?><br>التطعيمات:
                        <?php echo htmlspecialchars($driver['vaccinations_status'] ? $driver['vaccinations_status'] : '-'); ?>
                    </div>
                </div>
                <div class="info-item">
                    <div class="label">9) المراجع</div>
                    <div class="value">جهة سابقة:
                        <?php echo htmlspecialchars($driver['previous_employer'] ? $driver['previous_employer'] : '-'); ?><br>مدة
                        العمل:
                        <?php echo htmlspecialchars($driver['employment_duration'] ? $driver['employment_duration'] : '-'); ?><br>مرجع
                        اتصال:
                        <?php echo htmlspecialchars($driver['reference_contact'] ? $driver['reference_contact'] : '-'); ?>
                    </div>
                </div>
                <div class="info-item">
                    <div class="label">10) ملاحظات عامة</div>
                    <div class="value">
                        <?php echo nl2br(htmlspecialchars($driver['general_notes'] ? $driver['general_notes'] : '-')); ?>
                    </div>
                </div>
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
                    <?php if (!empty($driver['employee_photo'])): ?>
                        <img src="<?php echo htmlspecialchars($driver['employee_photo']); ?>" alt="صورة السائق">
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
                                آلية: <?php echo htmlspecialchars($mv['equipment_name']); ?>
                                (<?php echo htmlspecialchars($mv['equipment_code']); ?>) |
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
                                <td><?php echo htmlspecialchars($as['equipment_name'] . ' (' . $as['equipment_code'] . ')'); ?>
                                </td>
                                <td><?php echo htmlspecialchars($as['supplier_name']); ?></td>
                                <td><?php echo htmlspecialchars($as['start_date'] ? $as['start_date'] : '-'); ?></td>
                                <td><?php echo htmlspecialchars(ems_format_open_end($as['end_date'])); ?></td>
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
    (function () {
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
