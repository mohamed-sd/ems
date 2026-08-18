<?php
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
include "../config.php";

// جلب بيانات المستخدم
$userName = isset($_SESSION['user']['name']) ? $_SESSION['user']['name'] : "مستخدم غير معروف";

// إحصائيات عامة
$totalEquipments_res = $conn->query("SELECT COUNT(*) AS c FROM equipments");
$totalEquipments = $totalEquipments_res ? ($totalEquipments_res->fetch_assoc()['c'] ?? null) : null;
$activeEquipments_res = $conn->query("SELECT COUNT(*) AS c FROM equipments WHERE status='نشط'");
$activeEquipments = $activeEquipments_res ? ($activeEquipments_res->fetch_assoc()['c'] ?? null) : null;
$inactiveEquipments_res = $conn->query("SELECT COUNT(*) AS c FROM equipments WHERE status='متوقف'");
$inactiveEquipments = $inactiveEquipments_res ? ($inactiveEquipments_res->fetch_assoc()['c'] ?? null) : null;
$totalSuppliers_res = $conn->query("SELECT COUNT(DISTINCT suppliers) AS c FROM equipments");
$totalSuppliers = $totalSuppliers_res ? ($totalSuppliers_res->fetch_assoc()['c'] ?? null) : null;

// جلب أول 50 معدة مع اسم المورد
$equipments = $conn->query("
    SELECT e.id, e.code, e.type, e.name, e.status, s.name AS supplier_name
    FROM equipments e
    LEFT JOIN suppliers s ON e.suppliers = s.id
    ORDER BY e.id DESC
    LIMIT 50
");
?>

<?php
/* AC-U1 · SH-01 — قشرةٌ واحدةٌ: كان هنا رأسٌ محليٌّ كاملٌ بـ<!DOCTYPE>
   و<head> وقائمةِ أنماطٍ خاصة. صار `inheader.php` مصدرَ القشرةِ، فيصل
   هذه الشاشةَ كلُّ تحسينٍ فيها (كاسرُ الذاكرةِ · الرموزُ · الأزرار).
   وما تنفرد به من أنماطٍ منقولٌ أدناه ولم يُنزع. */
$page_title = 'تقرير المعدات';
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
            background: var(--c-000022, #000022);
            color: var(--c-fff, #fff);
            text-align: center;
            box-shadow: 0 3px 10px var(--c-rgba00002, rgba(0, 0, 0, 0.2));
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
$header_title_html = htmlspecialchars('Equipments Reports', ENT_QUOTES, 'UTF-8');
$header_actions = array();
$header_back = false;
include __DIR__ . '/../includes/page_header.php';
// UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
echo ems_states_bundle('لا معداتِ مسجلةً لهذا الكيان', 'سجّل معدةً من شاشةِ المعدات ثمّ عاود فتح هذا التقرير');
?>

        <div class="container py-4">

            <!-- الكاردات -->
            <div class="cards">
                <div class="card-box">
                    <h4>إجمالي المعدات</h4>
                    <p class="fs-4 fw-bold"><?= $totalEquipments ?></p>
                </div>
                <div class="card-box">
                    <h4>معدات نشطة</h4>
                    <p class="fs-4 fw-bold"><?= $activeEquipments ?></p>
                </div>
                <div class="card-box">
                    <h4>معدات متوقفة</h4>
                    <p class="fs-4 fw-bold"><?= $inactiveEquipments ?></p>
                </div>
                <div class="card-box">
                    <h4>إجمالي الموردين</h4>
                    <p class="fs-4 fw-bold"><?= $totalSuppliers ?></p>
                </div>
            </div>

            <!-- زر الطباعة -->
            <div class="btns mb-3">
                <button onclick="window.print()" class="btn btn-primary">ðŸ–¨ طباعة التقرير</button>
            </div>

            <!-- الهيدر للطباعة -->
            <div class="print-header d-flex justify-content-between align-items-center">
                <img src="../assets/img/logo-right.png" alt="شعار يمين">
                <div>
                    <h3>تقرير المعدات</h3>
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
                        <th>الكود</th>
                        <th>النوع</th>
                        <th>الاسم</th>
                        <th>المورد</th>
                        <th>الحالة</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($equipments): while ($row = $equipments->fetch_assoc()): ?>
                        <tr>
                            <td><?= $row['id'] ?></td>
                            <td><?= htmlspecialchars($row['code'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($row['type'] == "1" ? "حفار" : "قلاب" , ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($row['supplier_name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($row['status'] == "1" ? "في مشروع" : "خارج الخدمة", ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                    <?php endwhile; endif; ?>
                </tbody>
            </table>

            <!-- الفوتر للطباعة -->
            <div class="print-footer text-center">
                <p>تقرير المعدات - إيكوبيشن © <?= date("Y") ?></p>
            </div>

            <!-- Scripts -->
            <script src="/ems/assets/vendor/jquery-3.7.1.min.js"></script>
            <script src="/ems/assets/vendor/datatables/js/jquery.dataTables.min.js"></script>
            <script src="/ems/assets/vendor/datatables/js/dataTables.bootstrap5.min.js"></script>
        </div>
    </div>
</body>
</html>


