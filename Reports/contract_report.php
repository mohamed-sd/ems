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
$page_title = 'إيكوبيشن | تقرير العقود';
include __DIR__ . '/../inheader.php';
?>
<!-- أنماطٌ تنفرد بها هذه الشاشة (لا يحمّلها inheader) -->
<link rel="stylesheet" href="../assets/css/admin-style.css">
<link rel="stylesheet" href="../assets/css/main_admin_style.css">
<link rel="stylesheet" type="text/css" href="../assets/css/style.css"/>
<style>
        .main { font-family: 'Cairo', sans-serif; }
        .report-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 12px;
            margin-bottom: 18px;
        }
        .summary-item {
            background: var(--c-rgba122862003, rgba(12, 28, 62, 0.03));
            border: 1px solid var(--c-rgba122862008, rgba(12, 28, 62, 0.08));
            border-radius: 12px;
            padding: 12px 14px;
        }
        .summary-item .label {
            font-size: 13px;
            color: var(--c-64748b, #64748b);
            font-weight: 600;
            margin-bottom: 4px;
        }
        .summary-item .value {
            font-size: 16px;
            color: var(--c-0c1c3e, #0c1c3e);
            font-weight: 800;
        }
        .report-progress .progress,
        .progress.report-progress {
            height: 20px;
            border-radius: 999px;
            background: var(--c-eef2f7, #eef2f7);
        }
        .report-progress .progress-bar {
            font-weight: 700;
            font-size: 12px;
        }
        .card-header h5 { margin: 0; }
        .table thead th {
            background: var(--c-f8fafc, #f8fafc);
            color: var(--c-0c1c3e, #0c1c3e);
            font-weight: 800;
        }
        /* UXW-01 ②: أنماطٌ كانت موضعيةً — بادئةُ الشاشةِ rpt-con */
        .rpt-con-actions { display: flex; gap: 10px; flex-wrap: wrap; }
        .rpt-con-filter { align-items: end; }
        .rpt-con-progress-title { font-weight: 700; margin-bottom: 8px; color: var(--c-0c1c3e, #0c1c3e); }
        .rpt-con-progress-wrap { max-width: 480px; }
        .rpt-con-chart-empty { padding: 24px; text-align: center; opacity: .75; font-size: .85rem; }
        /* ألوانُ سلاسلِ الرسمِ — تُقرأ في جافاسكربت لأن اللوحةَ لا تحلُّ var() بنفسِها */
        #chart {
            --rpt-con-series-total:  var(--c-rgba3799235075, rgba(37, 99, 235, 0.75));
            --rpt-con-series-target: var(--c-rgba2321840075, rgba(232, 184, 0, 0.75));
            --rpt-con-series-pct:    var(--c-0c1c3e, #0c1c3e);
        }
    </style>


<?php
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include('../insidebar.php');
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }

// العزل عبر بوابة المستأجر — والسوبر عبر forAllTenants (سلوك الأصل: بلا تنطيق).
// (operations.project_id عمودٌ قائم فسقط فحصه)
$cr_is_super = ((isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '') === '-1');
$cr_gate = $cr_is_super ? ems_tenant_db()->forAllTenants('contract report super') : ems_tenant_db();
$operations_project_column = 'project_id';

$contract_filter = isset($_GET['contract']) ? intval($_GET['contract']) : 0;

// قائمة العقود (contracts نطاق، project إثراء LEFT) — الأصل بلا تنطيق = تسريب تعزله البوابة الآن.
$contracts = array();
try {
    $contracts = $cr_gate->scopedQuery(array('scope' => array('c' => 'contracts'), 'enrich' => array('p' => 'project')),
        "SELECT c.id, p.name AS project_name
                  FROM contracts c
                  LEFT JOIN project p ON c.project_id = p.id WHERE 1=1 AND {TENANT_SCOPE}");
} catch (\Throwable $t) { error_log('contract_report.php contracts: ' . $t->getMessage()); }

$contract_data = null;
$monthly_stats = null;

if ($contract_filter > 0) {
    // بيانات العقد الأساسية (contracts نطاق؛ project/operations/equipments/timesheet إثراء LEFT)
    try {
        $cr_info = $cr_gate->scopedQuery(
            array('scope' => array('c' => 'contracts'),
                  'enrich' => array('p' => 'project', 'o' => 'operations', 'e' => 'equipments', 't' => 'timesheet')),
            "SELECT
        c.id AS contract_id,
        c.id AS contract_id,
        p.name AS project_name,
        c.contract_signing_date,
        c.contract_duration_months,
        c.hours_monthly_target,
        c.forecasted_contracted_hours,
        IFNULL(SUM(t.total_work_hours),0) AS actual_hours,
        (c.forecasted_contracted_hours - IFNULL(SUM(t.executed_hours),0)) AS remaining_hours
    FROM contracts c
    LEFT JOIN project p ON c.project_id = p.id
    LEFT JOIN operations o ON o.$operations_project_column = p.id
    LEFT JOIN equipments e ON e.id = o.equipment
    LEFT JOIN timesheet t ON t.operator = o.id
    WHERE c.id = ? AND {TENANT_SCOPE}
    GROUP BY c.id, p.name, c.contract_signing_date, c.contract_duration_months", array($contract_filter));
        $contract_data = !empty($cr_info) ? $cr_info[0] : null;
    } catch (\Throwable $t) { error_log('contract_report.php sql_info: ' . $t->getMessage()); }

    // إحصائية شهرية
    try {
        $monthly_stats = $cr_gate->scopedQuery(
            array('scope' => array('c' => 'contracts'),
                  'enrich' => array('p' => 'project', 'o' => 'operations', 'e' => 'equipments', 't' => 'timesheet')),
            "SELECT
        YEAR(t.date) AS year,
        MONTH(t.date) AS month,
        IFNULL(SUM(t.executed_hours),0) AS executed_hours,
        IFNULL(SUM(t.standby_hours),0) AS standby_hours,
        IFNULL(SUM(t.executed_hours),0) + IFNULL(SUM(t.standby_hours),0) AS total_hours,
        c.hours_monthly_target
    FROM contracts c
    LEFT JOIN project p ON c.project_id = p.id
    LEFT JOIN operations o ON o.$operations_project_column = p.id
    LEFT JOIN equipments e ON e.id = o.equipment
    LEFT JOIN timesheet t ON t.operator = o.id
    WHERE c.id = ? AND {TENANT_SCOPE}
    GROUP BY YEAR(t.date), MONTH(t.date), c.hours_monthly_target
    ORDER BY year, month", array($contract_filter));
    } catch (\Throwable $t) { error_log('contract_report.php sql_monthly: ' . $t->getMessage()); }
}
?>

<div class="main">
    <div class="header">
        <?php
/* AS-04/AS-05 (UXR-01): رأسُ الصفحةِ الموحَّدُ بدلَ العنوانِ اليدويّ. */
$header_icon = 'fa-solid fa-file-contract';
$header_title_html = htmlspecialchars('تقرير إحصائية العقود', ENT_QUOTES, 'UTF-8');
$header_actions = array();
$header_back = false;
include __DIR__ . '/../includes/page_header.php';
// UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضيًا
echo ems_states_bundle('لا ساعاتِ تنفيذٍ مسجلةً لهذا العقد', 'اختر عقدًا من القائمةِ أعلاه أو سجّل ساعاتِ الوردياتِ أولًا');
?>
        <div class="rpt-con-actions">
            <a href="reports.php" class="back-btn">
                <i class="fas fa-arrow-right"></i> رجوع
            </a>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h5><i class="fas fa-filter"></i> اختيار العقد</h5>
        </div>
        <div class="card-body">
            <form method="GET" class="form-grid rpt-con-filter">
                <div>
                    <label for="emsf_1354_a59d4"><i class="fas fa-file-signature"></i> اختر العقد</label>
                    <select name="contract" id="emsf_1354_a59d4">
                        <option value="">-- اختر --</option>
                        <?php foreach ($contracts as $row) {
                            $selected = ($contract_filter == $row['id']) ? "selected" : "";
                            echo "<option value='{$row['id']}' $selected>عقد #{$row['id']} - {$row['project_name']}</option>";
                        } ?>
                    </select>
                </div>
                <button type="submit"><i class="fa fa-eye"></i> عرض التقرير</button>
            </form>
        </div>
    </div>

    <?php if ($contract_data) { ?>
        <!-- تفاصيل العقد -->
        <div class="card mb-4">
            <div class="card-header">
                <h5><i class="fas fa-thumbtack"></i> تفاصيل العقد</h5>
            </div>
            <div class="card-body">
                <div class="report-summary">
                    <div class="summary-item"><div class="label">المشروع</div><div class="value"><?php echo $contract_data['project_name']; ?></div></div>
                    <div class="summary-item"><div class="label">تاريخ التوقيع</div><div class="value"><?php echo $contract_data['contract_signing_date']; ?></div></div>
                    <div class="summary-item"><div class="label">مدة العقد</div><div class="value"><?php echo $contract_data['contract_duration_months']; ?> شهور</div></div>
                    <div class="summary-item"><div class="label">الهدف الشهري</div><div class="value"><?php echo $contract_data['hours_monthly_target']; ?></div></div>
                    <div class="summary-item"><div class="label">إجمالي الساعات المتوقعة</div><div class="value"><?php echo $contract_data['forecasted_contracted_hours']; ?></div></div>
                    <div class="summary-item"><div class="label">المنفذ فعلياً</div><div class="value"><?php echo $contract_data['actual_hours']; ?></div></div>
                    <div class="summary-item"><div class="label">المتبقي</div><div class="value"><?php echo $contract_data['remaining_hours']; ?></div></div>
                </div>
                <div class="report-progress">
                    <div class="rpt-con-progress-title">نسبة الإنجاز الكلية</div>
                        <?php
                        $overall_percent = ($contract_data['forecasted_contracted_hours'] > 0)
                            ? round(($contract_data['actual_hours'] / $contract_data['forecasted_contracted_hours']) * 100, 2)
                            : 0;

                        $color = "bg-danger";
                        if ($overall_percent >= 80) {
                            $color = "bg-success";
                        } elseif ($overall_percent >= 50) {
                            $color = "bg-warning";
                        }
                        ?>
                        <div class="progress rpt-con-progress-wrap">
                            <div class="progress-bar <?php echo $color; ?>"
                                 role="progressbar" data-allow-style style="width: <?php echo $overall_percent; ?>%;">
                                <?php echo $overall_percent; ?> %
                            </div>
                        </div>
                </div>
            </div>
        </div>

        <!-- الأداء الشهري -->
        <div class="card mb-4">
            <div class="card-header">
                <h5><i class="fas fa-chart-column"></i> الأداء الشهري</h5>
            </div>
            <div class="card-body table-responsive">
                <table class="table table-bordered text-center align-middle">
                    <thead>
                        <tr>
                            <th>السنة</th>
                            <th>الشهر</th>
                            <th>المنفذ</th>
                            <th>الاستعداد</th>
                            <th>الإجمالي</th>
                            <th>الهدف الشهري</th>
                            <th>نسبة الإنجاز</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $labels = array();
                        $actual = array();
                        $target = array();
                        $percentages = array();

                        if ($monthly_stats) {
                        foreach ($monthly_stats as $row) {
                            $labels[] = $row['year']."-".$row['month'];
                            $actual[] = isset($row['total_hours']) ? $row['total_hours'] : 0;
                            $target[] = isset($row['hours_monthly_target']) ? $row['hours_monthly_target'] : 0;

                            $percent = ($row['hours_monthly_target'] > 0)
                                       ? round(($row['total_hours'] / $row['hours_monthly_target']) * 100, 2)
                                       : 0;
                            $percentages[] = $percent;

                            $color = "bg-danger";
                            if ($percent >= 80) {
                                $color = "bg-success";
                            } elseif ($percent >= 50) {
                                $color = "bg-warning";
                            }
                        ?>
                        <tr>
                            <td><?php echo $row['year']; ?></td>
                            <td><?php echo $row['month']; ?></td>
                            <td><?php echo $row['executed_hours']; ?></td>
                            <td><?php echo $row['standby_hours']; ?></td>
                            <td><?php echo $row['total_hours']; ?></td>
                            <td><?php echo $row['hours_monthly_target']; ?></td>
                            <td>
                                <div class="progress report-progress">
                                    <div class="progress-bar <?php echo $color; ?>" role="progressbar"
                                         data-allow-style style="width: <?php echo $percent; ?>%;">
                                        <?php echo $percent; ?> %
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php }
                        } ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- الرسم البياني -->
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-chart-line"></i> الرسم البياني</h5>
            </div>
            <div class="card-body">
                <canvas id="chart" height="100"></canvas>
            </div>
        </div>

        <script>
        const ctx = document.getElementById('chart');
        /* UXW-01 ①: ألوانُ السلاسلِ من الرموزِ المعلَنةِ على #chart — لا قيمةَ لونٍ مثبَّتةً هنا */
        const emsSeriesCss = ctx ? getComputedStyle(ctx) : null;
        const emsSeriesColor = function (name) {
            return emsSeriesCss ? emsSeriesCss.getPropertyValue(name).trim() : '';
        };
        /* UI-DEF-07 (L4): لا رسمَ بلا بياناتٍ بمحاورَ افتراضية — حالةٌ مفسَّرة */
        function emsChartGuard(c, seriesArrays, renderFn) {
            var t = 0;
            (seriesArrays || []).forEach(function (a) { (a || []).forEach(function (v) { t += Math.abs(parseFloat(v) || 0); }); });
            if (t > 0) { return renderFn(); }
            var host = c && c.parentNode ? c.parentNode : null;
            if (host) {
                host.innerHTML = '<div class="rpt-con-chart-empty">'
                    + 'لا بيانات في الفترة المعروضة — الرسم لا يُعرض بمحاور افتراضية</div>';
            }
            return null;
        }
        emsChartGuard(ctx, [<?php echo json_encode($actual); ?>, <?php echo json_encode($target); ?>], function () {
        return new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($labels); ?>,
                datasets: [
                    {
                        label: 'الإجمالي',
                        data: <?php echo json_encode($actual); ?>,
                        backgroundColor: emsSeriesColor('--rpt-con-series-total')
                    },
                    {
                        label: 'الهدف الشهري',
                        data: <?php echo json_encode($target); ?>,
                        backgroundColor: emsSeriesColor('--rpt-con-series-target')
                    },
                    {
                        label: 'نسبة الإنجاز (%)',
                        data: <?php echo json_encode($percentages); ?>,
                        type: 'line',
                        borderColor: emsSeriesColor('--rpt-con-series-pct'),
                        backgroundColor: 'transparent',
                        yAxisID: 'percentage'
                    }
                ]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true,
                        title: { display: true, text: 'ساعات' }
                    },
                    percentage: {
                        beginAtZero: true,
                        position: 'right',
                        title: { display: true, text: 'نسبة الإنجاز %' },
                        ticks: { callback: (value) => value + "%" }
                    }
                }
            }
        });
        });
        </script>
    <?php } ?>

</div>

<script src="/ems/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
