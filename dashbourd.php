<?php
session_start();
include "config.php";
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title>إيكوبيشن | الرئيسية</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  
  <!-- Font awsome icon link مكتبة الايقونات -->
  <link rel="stylesheet" href="assets/css/all.min.css">

  
  <link rel="stylesheet" type="text/css" href="assets/css/style.css"/>
  <style>
    .cards {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 15px;
    }
    .card {
      background:#fff;
      padding:20px;
      border-radius:15px;
      text-align:center;
      box-shadow:0 3px 10px rgba(0,0,0,0.1);
      transition: transform 0.2s;
    }
    .card:hover { transform: translateY(-5px); }
    .card i { font-size:28px; color:#123; margin-bottom:10px; }
    .card h3 { font-size:24px; margin:10px 0; }
    .card p { color:#555; font-weight:600; }
  </style>
</head>
<body>

<?php include('sidebar.php'); ?>

<div class="main">

<h2>لوحة التحكم الرئيسية</h2>

<br/><br/><br/>

<div class="cards">
  <?php
  // كارد العقود
  $contracts = $conn->query("SELECT COUNT(*) AS total FROM contracts")->fetch_assoc()['total'];
  echo "<div class='card'><i class='fa fa-file-contract'></i><h3>$contracts</h3><p>العقود</p></div>";

  // كارد المشاريع
  $projects = $conn->query("SELECT COUNT(*) AS total FROM projects")->fetch_assoc()['total'];
  echo "<div class='card'><i class='fa fa-building'></i><h3>$projects</h3><p>المشاريع</p></div>";

  // كارد المعدات
  $equipments = $conn->query("SELECT COUNT(*) AS total FROM equipments")->fetch_assoc()['total'];
  echo "<div class='card'><i class='fa fa-tools'></i><h3>$equipments</h3><p>المعدات</p></div>";

  // كارد السائقين
  $drivers = $conn->query("SELECT COUNT(*) AS total FROM drivers")->fetch_assoc()['total'];
  echo "<div class='card'><i class='fa fa-id-badge'></i><h3>$drivers</h3><p>السائقين</p></div>";

  // كارد الموردين
  $suppliers = $conn->query("SELECT COUNT(*) AS total FROM suppliers")->fetch_assoc()['total'];
  echo "<div class='card'><i class='fa fa-truck'></i><h3>$suppliers</h3><p>الموردين</p></div>";

  // كارد ساعات العمل
  $hours = $conn->query("SELECT SUM(total_work_hours) AS total FROM timesheet")->fetch_assoc()['total'];
  echo "<div class='card'><i class='fa fa-clock'></i><h3>$hours</h3><p>إجمالي ساعات العمل</p></div>";

  // كارد المعدات العاملة الآن
  $activeOps = $conn->query("SELECT COUNT(*) AS total FROM operations WHERE status='active'")->fetch_assoc()['total'];
  echo "<div class='card'><i class='fa fa-play-circle'></i><h3>$activeOps</h3><p>معدات تعمل الآن</p></div>";

  // كارد آخر عملية تشغيل
  $lastOp = $conn->query("SELECT equipment, project, start FROM operations ORDER BY id DESC LIMIT 1")->fetch_assoc();
  if($lastOp){
    echo "<div class='card'><i class='fa fa-history'></i>
            <h3>".$lastOp['equipment']."</h3>
            <p>آخر تشغيل في مشروع: ".$lastOp['project']."<br>بداية: ".$lastOp['start']."</p>
          </div>";
  } else {
    echo "<div class='card'><i class='fa fa-history'></i><h3>-</h3><p>لا يوجد تشغيل</p></div>";
  }
  ?>
</div>

</div>

</body>
</html>
