<?php
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit();
}

// التحقق من صلاحية الوصول (مدير الأسطول = 4 أو المدير العام = -1)
$user_role = $_SESSION['user']['role'];
// if ($user_role != "-1" && $user_role != "4") {
//     die("غير مصرح لك بالوصول إلى هذه الصفحة");
// }

require_once '../config.php';

// الحصول على معرف الشركة للمستخدم
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$is_super_admin = ($user_role == "-1");

// التحقق من اسم عمود المشروع في جدول operations
$ops_project_col = (function_exists('db_table_has_column') && db_table_has_column($conn, 'operations', 'project_id'))
    ? 'project_id' : 'project';

// إعداد عنوان الصفحة
$page_title = "تقرير الأعطال";

$is_export_request = (isset($_GET['export']) && $_GET['export'] == 'excel');

// معالجة تصدير Excel
if ($is_export_request) {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="failures_report_' . date('Y-m-d') . '.xls"');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo "\xEF\xBB\xBF"; // UTF-8 BOM
}

// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }

?>

<link rel="stylesheet" href="/ems/assets/vendor/datatables/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="/ems/assets/vendor/datatables/css/buttons.dataTables.min.css">

<?php

// استعلام البيانات مع ربط الجداول — العزل بالشركة يُحقنه {TENANT_SCOPE} عبر البوابة
$ff_gate = $is_super_admin ? ems_tenant_db()->forAllTenants('fleet failures super view') : ems_tenant_db();
$where_conditions = ["tfh.status = 1"];

// فلاتر البحث
$filter_equipment_type = isset($_GET['equipment_type']) ? intval($_GET['equipment_type']) : 0;
$filter_date_from = isset($_GET['date_from']) ? mysqli_real_escape_string($conn, $_GET['date_from']) : '';
$filter_date_to = isset($_GET['date_to']) ? mysqli_real_escape_string($conn, $_GET['date_to']) : '';
$filter_main_category = isset($_GET['main_category']) ? mysqli_real_escape_string($conn, $_GET['main_category']) : '';
$filter_event_type = isset($_GET['event_type']) ? mysqli_real_escape_string($conn, $_GET['event_type']) : '';
$filter_project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;

$has_active_filters = (
    $filter_equipment_type > 0 ||
    !empty($filter_date_from) ||
    !empty($filter_date_to) ||
    !empty($filter_main_category) ||
    !empty($filter_event_type) ||
    $filter_project_id > 0
);

$default_rows_limit = 1000;
$apply_default_limit = (!$has_active_filters && !$is_export_request);
$limit_clause = $apply_default_limit ? " LIMIT $default_rows_limit" : '';

if ($filter_equipment_type > 0) {
    $where_conditions[] = "tfh.equipment_type = " . $filter_equipment_type;
}

if (!empty($filter_date_from)) {
    $where_conditions[] = "tfh.timesheet_date >= '" . $filter_date_from . "'";
}

if (!empty($filter_date_to)) {
    $where_conditions[] = "tfh.timesheet_date <= '" . $filter_date_to . "'";
}

if (!empty($filter_main_category)) {
    $where_conditions[] = "tfh.main_category_code = '" . $filter_main_category . "'";
}

if (!empty($filter_event_type)) {
    $where_conditions[] = "tfh.event_type_code = '" . $filter_event_type . "'";
}

if ($filter_project_id > 0) {
    $where_conditions[] = "op.$ops_project_col = " . $filter_project_id;
}

$where_clause = implode(" AND ", $where_conditions);

$failure_rows = $ff_gate->scopedQuery(
    array('scope' => array('tfh' => 'timesheet_failure_hours'),
          'enrich' => array('e' => 'equipments', 's' => 'suppliers', 'op' => 'operations', 'p' => 'project', 't' => 'timesheet')),
    "SELECT tfh.*, e.code AS equipment_code, e.name AS equipment_name, s.name AS supplier_name,
            p.name AS project_name, op.id AS operation_id, t.date AS timesheet_actual_date, t.shift AS timesheet_shift
     FROM timesheet_failure_hours tfh
     LEFT JOIN equipments e ON tfh.equipment_id = e.id
     LEFT JOIN suppliers s ON e.suppliers = s.id
     LEFT JOIN operations op ON tfh.operation_id = op.id
     LEFT JOIN project p ON op.$ops_project_col = p.id
     LEFT JOIN timesheet t ON tfh.timesheet_id = t.id
     WHERE {TENANT_SCOPE} AND $where_clause
     ORDER BY tfh.timesheet_date DESC, tfh.id DESC
     $limit_clause");

// الحصول على قوائم الفلاتر
$suppliers_has_company = (function_exists('db_table_has_column') && db_table_has_column($conn, 'suppliers', 'company_id'));

// قوائم الفلاتر — العزل بالشركة عبر {TENANT_SCOPE} على tfh (المستأجَر)
$projects_rows = $ff_gate->scopedQuery(
    array('scope' => array('tfh' => 'timesheet_failure_hours'), 'enrich' => array('op' => 'operations', 'p' => 'project')),
    "SELECT DISTINCT p.id, p.name FROM timesheet_failure_hours tfh
       LEFT JOIN operations op ON op.id = tfh.operation_id
       LEFT JOIN project p ON p.id = op.$ops_project_col
      WHERE {TENANT_SCOPE} AND op.id IS NOT NULL AND p.id IS NOT NULL AND p.status = 1 ORDER BY p.name");

$main_categories_rows = $ff_gate->scopedQuery(
    array('scope' => array('tfh' => 'timesheet_failure_hours')),
    "SELECT DISTINCT tfh.main_category_code, tfh.main_category_name FROM timesheet_failure_hours tfh
      WHERE {TENANT_SCOPE} AND tfh.status = 1 ORDER BY tfh.main_category_name");

$event_types_rows = $ff_gate->scopedQuery(
    array('scope' => array('tfh' => 'timesheet_failure_hours')),
    "SELECT DISTINCT tfh.event_type_code, tfh.event_type_name FROM timesheet_failure_hours tfh
      WHERE {TENANT_SCOPE} AND tfh.status = 1 ORDER BY tfh.event_type_name");
?>

<div class="main failures-page fleet-failures-main">
    <?php
    // Unified page header (structure: includes/page_header.php · styling: ems.main.all.style.css)
    $header_title   = 'تقرير الأعطال';
    $header_icon    = 'fas fa-tools';
    $header_actions = array(
        array('tag' => 'button', 'class' => 'add-btn btn', 'attrs' => 'type="button" onclick="exportToExcel()"', 'icon' => 'fas fa-file-excel', 'label' => 'تصدير Excel'),
    );
    $header_back = array('href' => 'manage_failure_codes.php', 'class' => 'back-btn', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    // UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
    if (function_exists('ems_states_bundle')) {
        echo ems_states_bundle('لا عطلَ أسطولٍ مطابقًا لهذه الفلاترِ في المدةِ المحددة',
                               'وسِّع مدةَ التاريخِ أو أزِل فلترَ المشروعِ ونوعِ الحدث، ثم اضغط «بحث»');
    }
    ?>
    <style>
    /* UXW-01 بوابة ٢: عرضُ جدولِ الأعطال صنفًا بدل style= */
    .ff-table-full { width: 100%; }
    </style>

    <div class="hero-note">
        <i class="fas fa-chart-line"></i>
        شاشة متابعة وتحليل أعطال الأسطول مع فلترة ذكية وسريعة
    </div>

    <div class="card mb-3">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5><i class="fas fa-filter"></i> فلاتر البحث</h5>
                <span class="filter-counter">
                    <i class="fas fa-sliders-h"></i>
                    الفلاتر النشطة: <?php echo $has_active_filters ? 'نعم' : 'لا'; ?>
                </span>
            </div>
        </div>
        <div class="card-body fc-filter-body">
                        <!-- صندوقُ الفلاترِ الموحَّد — التصميمُ في assets/css/ems-filters.css -->
            <div class="filter">
                <div class="filter-title"><span class="filter-title-icon"><i class="fa-solid fa-sliders"></i></span> فلاتر البحث</div>
                <div class="filter-body">
            <form method="GET" action="" id="filterForm">
            <div class="row fc-filter-bar">
                    <div class="col-md-3 mb-3">
                        <label class="form-label fc-filter-label" for="emsf_129_c5f87">نوع المعدة</label>
                        <select name="equipment_type" class="form-select" id="emsf_129_c5f87">
                            <option value="">-- الكل --</option>
                            <option value="1" <?php echo ($filter_equipment_type == 1) ? 'selected' : ''; ?>>حفار</option>
                            <option value="2" <?php echo ($filter_equipment_type == 2) ? 'selected' : ''; ?>>قلاب</option>
                            <option value="3" <?php echo ($filter_equipment_type == 3) ? 'selected' : ''; ?>>خرامة</option>
                        </select>
                    </div>

                    <div class="col-md-3 mb-3">
                        <label class="form-label fc-filter-label" for="emsf_130_87149">المشروع</label>
                        <select name="project_id" class="form-select" id="emsf_130_87149">
                            <option value="">-- الكل --</option>
                            <?php
                            foreach ($projects_rows as $proj):
                                    ?>
                                    <option value="<?php echo $proj['id']; ?>"
                                        <?php echo ($filter_project_id == $proj['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($proj['name']); ?>
                                    </option>
                                    <?php
                                endforeach;
                            ?>
                        </select>
                    </div>

                    <div class="col-md-3 mb-3">
                        <label class="form-label fc-filter-label" for="emsf_131_41a0b">نوع الحدث</label>
                        <select name="event_type" class="form-select" id="emsf_131_41a0b">
                            <option value="">-- الكل --</option>
                            <?php
                            foreach ($event_types_rows as $et):
                                    ?>
                                    <option value="<?php echo $et['event_type_code']; ?>"
                                        <?php echo ($filter_event_type == $et['event_type_code']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($et['event_type_name']); ?>
                                    </option>
                                    <?php
                                endforeach;
                            ?>
                        </select>
                    </div>

                    <div class="col-md-3 mb-3">
                        <label class="form-label fc-filter-label" for="emsf_132_fff92">الفئة الرئيسية</label>
                        <select name="main_category" class="form-select" id="emsf_132_fff92">
                            <option value="">-- الكل --</option>
                            <?php
                            foreach ($main_categories_rows as $mc):
                                    ?>
                                    <option value="<?php echo $mc['main_category_code']; ?>"
                                        <?php echo ($filter_main_category == $mc['main_category_code']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($mc['main_category_name']); ?>
                                    </option>
                                    <?php
                                endforeach;
                            ?>
                        </select>
                    </div>

                    <div class="col-md-3 mb-3">
                        <label class="form-label fc-filter-label" for="emsf_133_a9f70">التاريخ من</label>
                        <input type="date" name="date_from" class="form-control" id="emsf_133_a9f70"
                            value="<?php echo htmlspecialchars($filter_date_from); ?>">
                    </div>

                    <div class="col-md-3 mb-3">
                        <label class="form-label fc-filter-label" for="emsf_134_22878">التاريخ إلى</label>
                        <input type="date" name="date_to" class="form-control" id="emsf_134_22878"
                            value="<?php echo htmlspecialchars($filter_date_to); ?>">
                    </div>

                    <div class="col-md-3 mb-3 d-flex align-items-end">
                        <div class="filter-actions fc-filter-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> بحث
                            </button>
                            <a href="fleet_failures.php" class="btn btn-secondary border">
                                <i class="fas fa-redo"></i> إعادة تعيين
                            </a>
                        </div>
                    </div>
                </div>
            </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-3">
        <?php
        $total_failures = count($failure_rows);
        $equipment_types_count = [];

        foreach ($failure_rows as $row) {
            $eq_type = $row['equipment_type'];
            if (!isset($equipment_types_count[$eq_type])) {
                $equipment_types_count[$eq_type] = 0;
            }
            $equipment_types_count[$eq_type]++;
        }

        $equipment_type_names = [1 => 'حفار', 2 => 'قلاب', 3 => 'خرامة'];
        ?>

        <div class="col-md-3 mb-3">
            <div class="summary-card summary-primary">
                <h6>إجمالي الأعطال</h6>
                <div class="summary-value"><?php echo number_format($total_failures); ?></div>
            </div>
        </div>

        <?php foreach ($equipment_types_count as $type => $count): ?>
            <div class="col-md-3 mb-3">
                <div class="summary-card summary-gold">
                    <h6>أعطال <?php echo $equipment_type_names[$type] ?? 'غير محدد'; ?></h6>
                    <div class="summary-value"><?php echo number_format($count); ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5><i class="fas fa-table"></i> قائمة الأعطال</h5>
                <?php if ($apply_default_limit): ?>
                    <span class="initial-load-badge">
                        <i class="fas fa-bolt"></i>
                        عرض آخر <?php echo number_format($default_rows_limit); ?> عطل (فتح أولي)
                    </span>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-body">
            <div class="search-toolbar">
                <div class="search-input-wrap">
                    <i class="fas fa-search"></i>
                    <input type="text" id="quickTableSearch" class="form-control" placeholder="بحث سريع داخل النتائج المعروضة..." aria-label="بحث سريع داخل النتائج المعروضة...">
                </div>
            </div>
            <div class="table-responsive">
                <table id="failuresTable" class="display table table-bordered table-striped table-hover ff-table-full"
                       data-order='[[1,"desc"]]' data-page-length="50" data-state-save="true">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>التاريخ</th>
                            <th>المشروع</th>
                            <th>المعدة</th>
                            <th>نوع المعدة</th>
                            <th>المورد</th>
                            <th>نوع الحدث</th>
                            <th>الفئة الرئيسية</th>
                            <th>الفئة الفرعية</th>
                            <th>تفاصيل العطل</th>
                            <th>الكود الكامل</th>
                            <th>الوردية</th>
                            <!-- E-03 موجة ٤: النواة الحاكمة (gov_columns) — الخلايا يحشوها ui-unification.js -->
                            <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
                            <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
                            <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
                            <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
                            <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
                            <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
                            <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
                            <th class="ems-gov-th" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
                            </tr>
                    </thead>
                    <tbody>
                        <?php
                        $counter = 1;
                        if (count($failure_rows) > 0):
                            foreach ($failure_rows as $row):
                                $equipment_type_name = $equipment_type_names[$row['equipment_type']] ?? 'غير محدد';
                                ?>
                                <tr>
                                    <td><?php echo $counter++; ?></td>
                                    <td><?php echo htmlspecialchars($row['timesheet_date']); ?></td>
                                    <td><?php echo htmlspecialchars($row['project_name'] ?? 'غير محدد'); ?></td>
                                    <td><?php echo htmlspecialchars($row['equipment_code'] . ' - ' . $row['equipment_name']); ?></td>
                                    <td><?php echo htmlspecialchars($equipment_type_name); ?></td>
                                    <td><?php echo htmlspecialchars($row['supplier_name'] ?? 'غير محدد'); ?></td>
                                    <td><?php echo htmlspecialchars($row['event_type_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['main_category_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['sub_category']); ?></td>
                                    <td><?php echo htmlspecialchars($row['failure_detail']); ?></td>
                                    <td><code><?php echo htmlspecialchars($row['full_code']); ?></code></td>
                                    <td><?php echo htmlspecialchars($row['timesheet_shift'] ?? '-'); ?></td>
                                </tr>
                                <?php
                            endforeach;
                        else:
                            ?>
                            <tr>
                                <td colspan="12" class="text-center">لا توجد بيانات متاحة</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="/ems/assets/vendor/datatables/js/jquery.dataTables.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/dataTables.buttons.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/buttons.html5.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/buttons.print.min.js"></script>
<script src="/ems/assets/vendor/jszip/jszip.min.js"></script>
<script src="/ems/assets/vendor/pdfmake/pdfmake.min.js"></script>
<script src="/ems/assets/vendor/pdfmake/vfs_fonts.js"></script>

<script>
$(document).ready(function() {
    /* UXW-01 بوابة ٥: تهيئةُ جدولِ الأعطال انتقلت إلى المكوّنِ المركزي
       (assets/js/ui-unification.js) — والسلوكُ محفوظٌ بسماتٍ على وسمِ الجدول:
       data-order='[[1,"desc"]]' · data-page-length="50" · data-state-save="true".
       وهنا يُربَط البحثُ السريعُ بعد التهيئةِ المركزية. */
    function bindFailuresQuickSearch() {
        var table = $('#failuresTable').DataTable();
        $('#quickTableSearch').on('keyup', function() {
            table.search(this.value).draw();
        });
    }

    if ($.fn.dataTable && $.fn.dataTable.isDataTable('#failuresTable')) {
        bindFailuresQuickSearch();
    } else {
        $('#failuresTable').one('init.dt', bindFailuresQuickSearch);
    }
});

function exportToExcel() {
    var currentUrl = window.location.href;
    if (currentUrl.indexOf('?') > -1) {
        window.location.href = currentUrl + '&export=excel';
    } else {
        window.location.href = currentUrl + '?export=excel';
    }
}
</script>

<?php
mysqli_close($conn);
?>
