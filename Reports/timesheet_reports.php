<?php
session_start();
include "../config.php";

// جلب بيانات المستخدم
$userName = isset($_SESSION['user']['name']) ? $_SESSION['user']['name'] : "مستخدم غير معروف";

// الإحصائيات العامة 
$totalExecuted = $conn->query("SELECT SUM(executed_hours) AS s FROM timesheet")->fetch_assoc()['s'];
$totalFault = $conn->query("SELECT SUM(total_fault_hours) AS s FROM timesheet")->fetch_assoc()['s'];
$totalOperator = $conn->query("SELECT SUM(operator_hours) AS s FROM timesheet")->fetch_assoc()['s'];
$totalCounter = $conn->query("SELECT SUM(counter_diff) AS s FROM timesheet")->fetch_assoc()['s'];

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
    LEFT JOIN drivers d ON ed.driver_id = d.id
    ORDER BY t.id DESC
    LIMIT 50
");

?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <title>تقرير ساعات العمل</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap + DataTables -->
    <link rel="stylesheet" href="/ems/assets/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="/ems/assets/vendor/datatables/css/dataTables.bootstrap5.min.css">
        <!-- Call font awsome libary -->
    <link rel="stylesheet" href="/ems/assets/css/all.min.css">
    <!-- Call local style -->
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
            background: #000022;
            color: #fff;
            text-align: center;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2);
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
</head>

<body>
    <?php include('../insidebar.php'); ?>
    <div class="main">
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
                <button onclick="window.print()" class="btn btn-primary">ðŸ–¨ طباعة التقرير</button>
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
            <table id="reportTable" class="table table-bordered table-striped">
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
                    <?php while ($row = $timesheets->fetch_assoc()): ?>
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
                    <?php endwhile; ?>
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
            <script>
                $(document).ready(function () {
                    $('#reportTable').DataTable({
                        pageLength: 10,
                        lengthMenu: [10, 25, 50],
                        language: { url: "/ems/assets/i18n/datatables/ar.json" }
                    });
                });
            </script>
        </div>
    </div>
</body>

</html>

