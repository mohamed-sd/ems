<?php
session_start();
include "../config.php";

// جلب بيانات المستخدم
$userName = isset($_SESSION['user']['name']) ? $_SESSION['user']['name'] : "مستخدم غير معروف";


// إحصائيات عامة
$totalProjects = $conn->query("SELECT COUNT(*) AS c FROM project")->fetch_assoc()['c'];
$completed = $conn->query("SELECT COUNT(*) AS c FROM project WHERE status='منجز'")->fetch_assoc()['c'];
$inProgress = $conn->query("SELECT COUNT(*) AS c FROM project WHERE status='جاري'")->fetch_assoc()['c'];
$totalAmount = $conn->query("SELECT SUM(total) AS s FROM project")->fetch_assoc()['s'];

// جلب أول 50 مشروع
$projects = $conn->query("SELECT id, name, client, location, total, status, create_at 
                          FROM project ORDER BY id DESC LIMIT 50");
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <title>تقرير المشاريع</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap + DataTables -->
    <link rel="stylesheet" href="/ems/assets/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="/ems/assets/vendor/datatables/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="/ems/assets/vendor/datatables/css/buttons.bootstrap5.min.css">
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
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
            text-align: center;
            color : #fff;
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
            <table id="reportTable" class="table table-bordered table-striped" id="projectsTable">
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
                    <?php while ($row = $projects->fetch_assoc()): ?>
                        <tr>
                            <td><?= $row['id'] ?></td>
                            <td><?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($row['client'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($row['location'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= number_format($row['total'], 2) ?></td>
                            <td><?= htmlspecialchars($row['status'] == "1" ? "جاري" : "منتهى", ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= $row['create_at'] ?></td>
                        </tr>
                    <?php endwhile; ?>
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

