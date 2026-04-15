<?php
session_start();
include "../config.php";

// جلب بيانات المستخدم
$userName = isset($_SESSION['user']['name']) ? $_SESSION['user']['name'] : "مستخدم غير معروف";

// إحصائيات عامة
$totalEquipments = $conn->query("SELECT COUNT(*) AS c FROM equipments")->fetch_assoc()['c'];
$activeEquipments = $conn->query("SELECT COUNT(*) AS c FROM equipments WHERE status='نشط'")->fetch_assoc()['c'];
$inactiveEquipments = $conn->query("SELECT COUNT(*) AS c FROM equipments WHERE status='متوقف'")->fetch_assoc()['c'];
$totalSuppliers = $conn->query("SELECT COUNT(DISTINCT suppliers) AS c FROM equipments")->fetch_assoc()['c'];

// جلب أول 50 معدة مع اسم المورد
$equipments = $conn->query("
    SELECT e.id, e.code, e.type, e.name, e.status, s.name AS supplier_name
    FROM equipments e
    LEFT JOIN suppliers s ON e.suppliers = s.id
    ORDER BY e.id DESC
    LIMIT 50
");
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <title>تقرير المعدات</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap + DataTables -->
    <link rel="stylesheet" href="/ems/assets/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="/ems/assets/vendor/datatables/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="/ems/assets/vendor/datatables/css/buttons.bootstrap5.min.css">

    <!-- FontAwesome -->
    <link rel="stylesheet" href="/ems/assets/css/all.min.css">

    <!-- Local style -->
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
</head>

<body>
    <?php include('../insidebar.php'); ?>
    <div class="main">
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
            <table id="reportTable" class="table table-bordered table-striped">
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
                    <?php while ($row = $equipments->fetch_assoc()): ?>
                        <tr>
                            <td><?= $row['id'] ?></td>
                            <td><?= htmlspecialchars($row['code'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($row['type'] == "1" ? "حفار" : "قلاب" , ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($row['supplier_name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($row['status'] == "1" ? "في مشروع" : "خارج الخدمة", ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                    <?php endwhile; ?>
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
            <script>
                $(document).ready(function () {
                    $('#reportTable').DataTable({
                        pageLength: 10,
                        lengthMenu: [10, 25, 50],
                        language: {
                            url: "/ems/assets/i18n/datatables/ar.json"
                        }
                    });
                });
            </script>
        </div>
    </div>
</body>
</html>


