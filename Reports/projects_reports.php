<?php
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
include "../config.php";

// جلب بيانات المستخدم
$userName = isset($_SESSION['user']['name']) ? $_SESSION['user']['name'] : "مستخدم غير معروف";


// إحصائيات عامة
$totalProjects_res = $conn->query("SELECT COUNT(*) AS c FROM project");
$totalProjects = $totalProjects_res ? ($totalProjects_res->fetch_assoc()['c'] ?? null) : null;
$completed_res = $conn->query("SELECT COUNT(*) AS c FROM project WHERE status='منجز'");
$completed = $completed_res ? ($completed_res->fetch_assoc()['c'] ?? null) : null;
$inProgress_res = $conn->query("SELECT COUNT(*) AS c FROM project WHERE status='جاري'");
$inProgress = $inProgress_res ? ($inProgress_res->fetch_assoc()['c'] ?? null) : null;
$totalAmount_res = $conn->query("SELECT SUM(total) AS s FROM project");
$totalAmount = $totalAmount_res ? ($totalAmount_res->fetch_assoc()['s'] ?? null) : null;

// جلب أول 50 مشروع
$projects = $conn->query("SELECT id, name, client, location, total, status, create_at 
                          FROM project ORDER BY id DESC LIMIT 50");
?>

<?php
/* AC-U1 · SH-01 — قشرةٌ واحدةٌ: كان هنا رأسٌ محليٌّ كاملٌ بـ<!DOCTYPE>
   و<head> وقائمةِ أنماطٍ خاصة. صار `inheader.php` مصدرَ القشرةِ، فيصل
   هذه الشاشةَ كلُّ تحسينٍ فيها (كاسرُ الذاكرةِ · الرموزُ · الأزرار).
   وما تنفرد به من أنماطٍ منقولٌ أدناه ولم يُنزع. */
$page_title = 'تقرير المشاريع';
include __DIR__ . '/../inheader.php';
?>
<!-- أنماطٌ تنفرد بها هذه الشاشة (لا يحمّلها inheader) -->
<link rel="stylesheet" href="/ems/assets/vendor/datatables/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="/ems/assets/vendor/datatables/css/buttons.bootstrap5.min.css">
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
            box-shadow: 0 3px 10px var(--c-shadow-soft, rgba(0, 0, 0, 0.1));
            text-align: center;
            color : var(--white);
        }

        .print-header {
            display: none;
            text-align: center;
            margin-bottom: 20px;
        }

        .print-header img {
            height: 60px;
        }

        .print-footer {
            display: none;
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
$header_title_html = htmlspecialchars('Projects Reports', ENT_QUOTES, 'UTF-8');
$header_actions = array();
$header_back = false;
include __DIR__ . '/../includes/page_header.php';
// UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
echo ems_states_bundle('لا مشاريعَ مسجّلةً يشملها هذا التقرير', 'سجّلْ أولَ مشروعٍ من شاشةِ المشاريع، ثمّ أعدْ فتحَ التقرير');
?>


        <div class="container py-4">

           

            <!-- الكاردات -->
            <div class="cards">
                <div class="card-box">
                    <h4>إجمالي المشاريع</h4>
                    <p class="fs-4 fw-bold"><?= $totalProjects ?></p>
                </div>
                <div class="card-box">
                    <h4>المشاريع المنجزة</h4>
                    <p class="fs-4 fw-bold"><?= $completed ?></p>
                </div>
                <div class="card-box">
                    <h4>المشاريع الجارية</h4>
                    <p class="fs-4 fw-bold"><?= $inProgress ?></p>
                </div>
                <div class="card-box">
                    <h4>إجمالي العقود</h4>
                    <p class="fs-4 fw-bold"><?= number_format($totalAmount, 2) ?></p>
                </div>
            </div>

            <!-- زر الطباعة -->
            <div class="btns mb-3">
                <button onclick="window.print()" class="btn btn-primary">ðŸ–¨ طباعة التقرير</button>
            </div>

            <!-- الهيدر للطباعة -->
            <div class="print-header d-flex justify-content-between align-items-center">
                <img src="assets/img/logo-right.png" alt="شعار يمين">
                <div>
                    <h3>تقرير المشاريع</h3>
                    <p>تاريخ الإصدار: <?= date("Y-m-d H:i") ?></p>
                    <p>تم إعداده بواسطة: <?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <img src="assets/img/logo-left.png" alt="شعار يسار">
            </div>

            <!-- الجدول -->
            <table id="reportTable" class="table table-bordered table-striped" data-page-length="10">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>اسم المشروع</th>
                        <th>العميل</th>
                        <th>الموقع</th>
                        <th>القيمة</th>
                        <th>الحالة</th>
                        <th>تاريخ الإنشاء</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($projects): while ($row = $projects->fetch_assoc()): ?>
                        <tr>
                            <td><?= $row['id'] ?></td>
                            <td><?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($row['client'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($row['location'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= number_format($row['total'], 2) ?></td>
                            <td><?= htmlspecialchars($row['status'] == "1" ? "جاري" : "منتهى", ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= $row['create_at'] ?></td>
                        </tr>
                    <?php endwhile; endif; ?>
                </tbody>
            </table>

            <!-- الفوتر للطباعة -->
            <div class="print-footer text-center">
                <p>تقرير المشاريع - إيكوبيشن © <?= date("Y") ?></p>
            </div>

            <!-- Scripts -->
            <script src="/ems/assets/vendor/jquery-3.7.1.min.js"></script>
            <script src="/ems/assets/vendor/datatables/js/jquery.dataTables.min.js"></script>
            <script src="/ems/assets/vendor/datatables/js/dataTables.bootstrap5.min.js"></script>
        </div>
    </div>
</body>

</html>

