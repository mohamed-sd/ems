<?php
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}
/* ── حارسُ الشاشة (B2) — الشاشةُ مسجَّلةٌ في `modules` وكانت تُفتح لأيِّ
   مستخدمٍ مسجَّلِ الدخول لأنها لا تنادي الحارس. والتسجيلُ لا يحرس: الحارسُ
   نداءٌ لا صفة. وموضعُه هنا قبلَ أيِّ تصييرٍ — فرفضٌ بعدَ خروجِ الرأسِ ليس رفضًا. */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/permissions_helper.php';
enforce_current_page_view_permission($conn, '../main/dashboard.php');
?>

<?php
/* AC-U1 · SH-01 — قشرةٌ واحدةٌ: كان هنا رأسٌ محليٌّ كاملٌ بـ<!DOCTYPE>
   و<head> وقائمةِ أنماطٍ خاصة. صار `inheader.php` مصدرَ القشرةِ، فيصل
   هذه الشاشةَ كلُّ تحسينٍ فيها (كاسرُ الذاكرةِ · الرموزُ · الأزرار).
   وما تنفرد به من أنماطٍ منقولٌ أدناه ولم يُنزع. */
$page_title = 'إيكوبيشن | تقرير وحدات المشغّلين';
include __DIR__ . '/../inheader.php';
?>
<!-- أنماطٌ تنفرد بها هذه الشاشة (لا يحمّلها inheader) -->
<link rel="stylesheet" href="../assets/css/admin-style.css">
<link rel="stylesheet" href="../assets/css/main_admin_style.css">
<link rel="stylesheet" type="text/css" href="../assets/css/style.css" />
<style>
        .main { font-family: 'Cairo', sans-serif; }

        .report-table thead th {
            background: var(--c-f8fafc, #f8fafc);
            color: var(--c-0c1c3e, #0c1c3e);
            font-weight: 800;
            border-color: var(--c-rgba12286201, rgba(12, 28, 62, 0.1));
        }

        .report-table td {
            border-color: var(--c-rgba122862008, rgba(12, 28, 62, 0.08));
            color: var(--c-0c1c3e, #0c1c3e);
            font-weight: 600;
        }

        .total-hours-box {
            background: linear-gradient(135deg, var(--c-rgba13148136012, rgba(13, 148, 136, 0.12)), var(--c-rgba13148136006, rgba(13, 148, 136, 0.06)));
            border: 1px solid var(--c-rgba13148136025, rgba(13, 148, 136, 0.25));
            border-radius: 14px;
            padding: 14px 16px;
            color: var(--c-0f766e, #0f766e);
            font-weight: 800;
            box-shadow: 0 4px 14px var(--c-rgba15118110012, rgba(15, 118, 110, 0.12));
        }

        .form-grid { align-items: end; }

        /* UXW-01 ②: نمطٌ كان موضعيًا — rpt-dsc */
        .rpt-dsc-actions { display: flex; gap: 10px; flex-wrap: wrap; }
    </style>


<?php
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include('../insidebar.php');
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }

// العزل عبر بوابة المستأجر — والسوبر عبر forAllTenants المسجَّل (سلوك الأصل: بلا تنطيق).
$is_super = ((isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '') === '-1');
$dsc_gate = $is_super ? ems_tenant_db()->forAllTenants('report super') : ems_tenant_db();

$project_filter = isset($_GET['project']) ? intval($_GET['project']) : 0;
$driver_filter  = isset($_GET['driver']) ? intval($_GET['driver']) : 0;
$start_date     = isset($_GET['start_date']) ? mysqli_real_escape_string($conn, $_GET['start_date']) : '';
$start_date_raw = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date       = isset($_GET['end_date']) ? mysqli_real_escape_string($conn, $_GET['end_date']) : '';
$end_date_raw   = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// فلاتر مُمعلَمة (?) — العزل يُحقن عبر {TENANT_SCOPE} (الأصل كان بلا تنطيق شركةٍ = تسريب تعزله البوابة الآن).
$dsc_filter = '';
$dsc_params = array();

// فلترة بالتاريخ
if (!empty($start_date) && !empty($end_date)) {
    $dsc_filter .= " AND t.date BETWEEN ? AND ? "; $dsc_params[] = $start_date_raw; $dsc_params[] = $end_date_raw;
} elseif (!empty($start_date)) {
    $dsc_filter .= " AND t.date = ? "; $dsc_params[] = $start_date_raw;
}

// فلترة بالمشروع
if (!empty($project_filter)) {
    $dsc_filter .= " AND p.id = ? "; $dsc_params[] = $project_filter;
}

// فلترة بالسائق
if (!empty($driver_filter)) {
    $dsc_filter .= " AND d.id = ? "; $dsc_params[] = $driver_filter;
}

$result = array();
try {
    $result = $dsc_gate->scopedQuery(
        array('scope' => array('t' => 'timesheet', 'd' => 'employees', 'o' => 'operations', 'e' => 'equipments', 'p' => 'project')),
        "
SELECT
    d.name AS driver_name,
    p.name AS project_name,
    e.name AS equipment_name,
    t.date,
    SUM(t.total_work_hours) AS total_hours
FROM timesheet t
JOIN employees d ON t.employee_id = d.id
JOIN operations o ON t.operator = o.id
JOIN equipments e ON o.equipment = e.id
JOIN project p ON o.project_id = p.id
WHERE 1=1$dsc_filter AND {TENANT_SCOPE} GROUP BY d.name, p.name, e.name, t.date ORDER BY t.date, d.name", $dsc_params);
} catch (\Throwable $t) {
    error_log('driverAndsupplerscontract.php report query failed: ' . $t->getMessage());
}
?>

<div class="main">

    <div class="header">
        <?php
/* AS-04/AS-05 (UXR-01): رأسُ الصفحةِ الموحَّدُ بدلَ العنوانِ اليدويّ. */
$header_icon = 'fa-solid fa-truck';
$header_title_html = htmlspecialchars('تقرير ساعات عمل السائقين', ENT_QUOTES, 'UTF-8');
$header_actions = array();
$header_back = false;
include __DIR__ . '/../includes/page_header.php';
// UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
echo ems_states_bundle('لا ساعاتِ عملِ سائقين مطابقةً لهذه الفلاتر', 'وسّع مدى التاريخِ أو اختر «الكل» في المشروعِ والسائقِ ثمّ اضغط بحث');
?>
        <div class="rpt-dsc-actions">
            <a href="reports.php" class="back-btn">
                <i class="fas fa-arrow-right"></i> رجوع
            </a>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h5><i class="fas fa-filter"></i> فلاتر التقرير</h5>
        </div>
        <div class="card-body">
            <form method="GET" class="form-grid">
                <div>
                    <label for="emsf_458_82abe"><i class="fas fa-diagram-project"></i> المشروع</label>
                    <select name="project" id="emsf_458_82abe">
                        <option value="">-- الكل --</option>
                        <?php
                        $prj = array();
                        try {
                            $prj = $dsc_gate->select('project', array('columns' => array('id', 'name')));
                        } catch (\Throwable $t) { error_log('driverAndsupplerscontract.php projects: ' . $t->getMessage()); }
                        foreach ($prj as $row) {
                            $selected = ($project_filter == $row['id']) ? "selected" : "";
                            echo "<option value='" . $row['id'] . "' $selected>" . $row['name'] . "</option>";
                        }
                        ?>
                    </select>
                </div>

                <div>
                    <label for="emsf_459_17b1a"><i class="fas fa-user-gear"></i> السائق</label>
                    <select name="driver" id="emsf_459_17b1a">
                        <option value="">-- الكل --</option>
                        <?php
                        $drv = array();
                        try {
                            $drv = $dsc_gate->select('employees', array('columns' => array('id', 'name')));
                        } catch (\Throwable $t) { error_log('driverAndsupplerscontract.php drivers: ' . $t->getMessage()); }
                        foreach ($drv as $row) {
                            $selected = ($driver_filter == $row['id']) ? "selected" : "";
                            echo "<option value='" . $row['id'] . "' $selected>" . $row['name'] . "</option>";
                        }
                        ?>
                    </select>
                </div>

                <div>
                    <label for="emsf_460_b5a51"><i class="fas fa-calendar-day"></i> من</label>
                    <input type="date" name="start_date" id="emsf_460_b5a51" value="<?php echo $start_date; ?>">
                </div>

                <div>
                    <label for="emsf_461_a0061"><i class="fas fa-calendar-check"></i> إلى</label>
                    <input type="date" name="end_date" id="emsf_461_a0061" value="<?php echo $end_date; ?>">
                </div>

                <button type="submit"><i class="fa fa-search"></i> بحث</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5><i class="fas fa-table"></i> نتائج التقرير</h5>
        </div>
        <div class="card-body table-container">
            <div class="table-responsive">
                <table class="table table-bordered table-striped align-middle report-table">
                    <thead>
                    <tr>
                        <th>السائق</th>
                        <th>المشروع</th>
                        <th>الآلية</th>
                        <th>التاريخ</th>
                        <th>⏱️ مجموع الساعات</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php
                    $grand_total = 0;
                    if ($result) {
                    foreach ($result as $row) {
                        $grand_total += $row['total_hours'];
                        echo "<tr>";
                        echo "<td>" . $row['driver_name'] . "</td>";
                        echo "<td>" . $row['project_name'] . "</td>";
                        echo "<td>" . $row['equipment_name'] . "</td>";
                        echo "<td>" . $row['date'] . "</td>";
                        echo "<td>" . $row['total_hours'] . "</td>";
                        echo "</tr>";
                    }
                    }
                    ?>
                    </tbody>
                </table>
            </div>

            <div class="total-hours-box mt-3">
                <i class="fas fa-check-circle"></i>
                إجمالي الساعات: <?php echo $grand_total; ?> ساعة
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap 5 JS -->
<script src="/ems/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

</body>
</html>



