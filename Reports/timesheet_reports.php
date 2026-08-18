<?php
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
include "../config.php";

// جلب بيانات المستخدم
$userName = isset($_SESSION['user']['name']) ? $_SESSION['user']['name'] : "مستخدم غير معروف";

// الإحصائيات العامة
$totalExecuted_res = $conn->query("SELECT SUM(executed_hours) AS s FROM timesheet");
$totalExecuted = $totalExecuted_res ? ($totalExecuted_res->fetch_assoc()['s'] ?? null) : null;
$totalFault_res = $conn->query("SELECT SUM(total_fault_hours) AS s FROM timesheet");
$totalFault = $totalFault_res ? ($totalFault_res->fetch_assoc()['s'] ?? null) : null;
$totalOperator_res = $conn->query("SELECT SUM(operator_hours) AS s FROM timesheet");
$totalOperator = $totalOperator_res ? ($totalOperator_res->fetch_assoc()['s'] ?? null) : null;
$totalCounter_res = $conn->query("SELECT SUM(counter_diff) AS s FROM timesheet");
$totalCounter = $totalCounter_res ? ($totalCounter_res->fetch_assoc()['s'] ?? null) : null;

// جلب أول 50 سجل
// جلب أول 50 سجل مع اسم المعدة واسم السائق
$timesheets = $conn->query("
    SELECT 
        t.id, 
        t.date, 
        t.executed_hours, 
        t.total_fault_hours, 
        t.operator_hours, 
        t.counter_diff, 
        t.work_notes,
        e.name AS equipment_name,
        d.name AS driver_name
    FROM timesheet t
    LEFT JOIN operations o ON t.operator = o.id
    LEFT JOIN equipments e ON o.equipment = e.id
    LEFT JOIN equipment_drivers ed ON o.equipment = ed.equipment_id
    LEFT JOIN employees d ON ed.employee_id = d.id
    ORDER BY t.id DESC
    LIMIT 50
");

?>

<?php
/* AC-U1 · SH-01 — قشرةٌ واحدةٌ: كان هنا رأسٌ محليٌّ كاملٌ بـ<!DOCTYPE>
   و<head> وقائمةِ أنماطٍ خاصة. صار `inheader.php` مصدرَ القشرةِ، فيصل
   هذه الشاشةَ كلُّ تحسينٍ فيها (كاسرُ الذاكرةِ · الرموزُ · الأزرار).
   وما تنفرد به من أنماطٍ منقولٌ أدناه ولم يُنزع. */
$page_title = 'تقرير ساعات العمل';
include __DIR__ . '/../inheader.php';
?>
<!-- أنماطٌ تنفرد بها هذه الشاشة (لا يحمّلها inheader) -->
<link rel="stylesheet" href="/ems/assets/vendor/datatables/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" type="text/css" href="../assets/css/style.css" />
<style>
        .cards {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 30px;
        }

        .card-box {
            flex: 1;
            min-width: 200px;
            padding: 20px;
            border-radius: 15px;
            background: var(--c-000022);
            color: var(--white);
            text-align: center;
            box-shadow: 0 3px 10px var(--c-shadow-soft, rgba(0, 0, 0, 0.2));
        }

        .print-header,
        .print-footer {
            display: none;
            text-align: center;
        }

        .print-header img {
            height: 60px;
        }

        .print-footer {
            margin-top: 30px;
            font-size: 14px;
        }

        @media print {
            body * {
                visibility: hidden;
            }

            #reportTable_wrapper,
            #reportTable_wrapper * {
                visibility: visible;
            }

            #reportTable_wrapper {
                position: absolute;
                top: 200px;
                right: 0;
                width: 100%;
            }

            .cards,
            .btns,
            .dataTables_filter,
            .dataTables_length,
            .dataTables_info,
            .dataTables_paginate {
                display: none !important;
            }

            .print-header,
            .print-footer {
                display: block !important;
            }
        }
    </style>

    <?php 
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include('../insidebar.php'); ?>
<?php require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); } ?>
    <div class="main">
<?php
/* AS-04/AS-05 (UXR-01): رأسُ الصفحةِ الموحَّدُ — الشاشةُ كانت بلا رأسٍ معلَن. */
$header_icon = 'fas fa-window-maximize';
$header_title_html = htmlspecialchars('Timesheet Reports', ENT_QUOTES, 'UTF-8');
$header_actions = array();
$header_back = false;
include __DIR__ . '/../includes/page_header.php';
// UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
echo ems_states_bundle('لا سجلاتِ ساعاتِ عملٍ مقيَّدةً بعدُ', 'قيِّدْ ساعاتِ اليومِ من شاشةِ «ساعات العمل اليومية» ثمّ أعِدْ فتحَ التقرير');
?>

        <div class="container py-4">

            <!-- الكاردات -->
            <div class="cards">
                <div class="card-box">
                    <h4>إجمالي الساعات المنفذة</h4>
                    <p class="fs-4 fw-bold"><?= number_format($totalExecuted, 2) ?></p>
                </div>
                <div class="card-box">
                    <h4>إجمالي ساعات التعطل</h4>
                    <p class="fs-4 fw-bold"><?= number_format($totalFault, 2) ?></p>
                </div>
                <div class="card-box">
                    <h4>إجمالي ساعات المشغلين</h4>
                    <p class="fs-4 fw-bold"><?= number_format($totalOperator, 2) ?></p>
                </div>
                <div class="card-box">
                    <h4>إجمالي فرق العدادات</h4>
                    <p class="fs-4 fw-bold"><?= number_format($totalCounter, 2) ?></p>
                </div>
            </div>

            <!-- زر الطباعة -->
            <div class="btns mb-3">
                <button onclick="window.print()" class="btn btn-primary">🖨  </button>
            </div>

            <!-- الهيدر للطباعة -->
            <div class="print-header d-flex justify-content-between align-items-center">
                <img src="../assets/img/logo-right.png" alt="شعار يمين">
                <div>
                    <h3>تقرير ساعات العمل</h3>
                    <p>تاريخ الإصدار: <?= date("Y-m-d H:i") ?></p>
                    <p>تم إعداده بواسطة: <?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <img src="../assets/img/logo-left.png" alt="شعار يسار">
            </div>

            <!-- الجدول -->
            <table id="reportTable" class="table table-bordered table-striped" data-page-length="10">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>التاريخ</th>
                        <th>المعدة</th>
                        <th>السائق</th>
                        <th>الساعات المنفذة</th>
                        <th>ساعات التعطل</th>
                        <th>ساعات المشغلين</th>
                        <th>فرق العدادات</th>
                        <th>ملاحظات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($timesheets): while ($row = $timesheets->fetch_assoc()): ?>
                        <tr>
                            <td><?= $row['id'] ?></td>
                            <td><?= $row['date'] ?></td>
                            <td><?= htmlspecialchars($row['equipment_name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($row['driver_name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= number_format($row['executed_hours'], 2) ?></td>
                            <td><?= number_format($row['total_fault_hours'], 2) ?></td>
                            <td><?= number_format($row['operator_hours'], 2) ?></td>
                            <td><?= htmlspecialchars($row['counter_diff'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($row['work_notes'], ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                    <?php endwhile; endif; ?>
                </tbody>
            </table>

            <!-- الفوتر للطباعة -->
            <div class="print-footer text-center">
                <p>تقرير ساعات العمل - إيكوبيشن © <?= date("Y") ?></p>
            </div>

            <!-- Scripts -->
            <script src="/ems/assets/vendor/jquery-3.7.1.min.js"></script>
            <script src="/ems/assets/vendor/datatables/js/jquery.dataTables.min.js"></script>
            <script src="/ems/assets/vendor/datatables/js/dataTables.bootstrap5.min.js"></script>
        </div>
    </div>
</body>

</html>

