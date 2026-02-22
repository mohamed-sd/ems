<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit();
}

include("../config.php"); // ملف الاتصال بقاعدة البيانات

// المشاريع
$projects_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(id) AS c FROM project"))['c'];

// الموردين
$suppliers_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(id) AS c FROM suppliers"))['c'];

// الآليات
$equipments_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(id) AS c FROM equipments"))['c'];

// المشغلين (drivers)
$operators_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(id) AS c FROM drivers"))['c'];

// المستخدمين
$users_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(id) AS c FROM users"))['c'];

// ساعات العمل (مجموع total_work_hours)
$workhours_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(total_work_hours) AS total FROM timesheet"))['total'];
if(!$workhours_count) $workhours_count = 0;
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>لوحة التقارير</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" type="text/css" href="../assets/css/style.css" />
  <style>
    h1 {
      margin: 20px 0;
      font-weight: bold;
      font-size: 30px;
      color: #333;
    }
    .report-card {
      position: relative;
      border-radius: 15px;
      padding: 10px;
      color: #ffcc00;
      transition: all 0.3s ease;
      cursor: pointer;
      text-align: center;
    }
    .report-card:hover {
      background: #ffcc00 !important;
      color: #000022 !important;
      transform: translateY(-5px);
      box-shadow: 0 6px 18px rgba(0,0,0,0.2);
    }
    .report-icon-wrapper {
      width: 60px;
      height: 60px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 15px auto;
      background: rgba(204, 199, 199, 0.36);
      font-size: 20px;
      float: right
    }
    .report-card:hover .report-icon-wrapper {
      background: #d7d7cdff;
      color: #000;
    }
    .report-title {
      font-size: 18px;
      font-weight: 600;
      margin-bottom: 8px;
    }
    .report-number {
      font-size: 28px;
      font-weight: bold;
    }
    /* ألوان مختلفة لكل كارد */
    .bg-projects   { background: #000022; }
    .bg-suppliers  { background: #000022; }
    .bg-equipments { background: #000022; }
    .bg-operators  { background: #000022; }
    .bg-users      { background: #000022; }
    .bg-workhours  { background: #000022; }

    /* Back Button (زر الرجوع) */
    .back-btn {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 10px 20px;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      text-decoration: none;
      border-radius: 12px;
      font-weight: 600;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
      font-size: 14px;
    }

    .back-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(102, 126, 234, 0.5);
      color: white;
      background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
    }

    .back-btn i {
      font-size: 14px;
      transition: transform 0.3s ease;
    }

    .back-btn:hover i {
      transform: translateX(3px);
    }
  </style>
</head>
<body>

<?php include('../insidebar.php');?> 

 <div class="main">

  <div class="container py-4">
    <div style="text-align: left; margin-bottom: 1.5rem;">
      <a href="../main/dashboard.php" class="back-btn">
        <i class="fas fa-arrow-right"></i> رجوع للرئيسية
      </a>
    </div>
    <h1 class="text-center mb-5"> لوحة التقارير </h1>
    <div class="row g-4">

      <div class="col-md-4 col-sm-6">
        <div class="report-card bg-projects" onclick="location.href='projects_reports.php'">
          <div class="report-icon-wrapper"><i class="fa-solid fa-diagram-project"></i></div>
          <div class="report-title">تقرير المشاريع</div>
          <div class="report-number"><?= $projects_count ?></div>
        </div>
      </div>

      <div class="col-md-4 col-sm-6">
        <div class="report-card bg-suppliers" onclick="location.href='#'">
          <div class="report-icon-wrapper"><i class="fa-solid fa-truck"></i></div>
          <div class="report-title">تقرير الموردين</div>
          <div class="report-number"><?= $suppliers_count ?></div>
        </div>
      </div>

      <div class="col-md-4 col-sm-6">
        <div class="report-card bg-equipments" onclick="location.href='equipments_reports.php'">
          <div class="report-icon-wrapper"><i class="fa-solid fa-tractor"></i></div>
          <div class="report-title">تقرير الآليات</div>
          <div class="report-number"><?= $equipments_count ?></div>
        </div>
      </div>

      <div class="col-md-4 col-sm-6">
        <div class="report-card bg-operators" onclick="location.href='#'">
          <div class="report-icon-wrapper"><i class="fa-solid fa-user-gear"></i></div>
          <div class="report-title">تقرير المشغلين</div>
          <div class="report-number"><?= $operators_count ?></div>
        </div>
      </div>

      <div class="col-md-4 col-sm-6">
        <div class="report-card bg-users" onclick="location.href='#'">
          <div class="report-icon-wrapper"><i class="fa-solid fa-users"></i></div>
          <div class="report-title">تقرير المستخدمين</div>
          <div class="report-number"><?= $users_count ?></div>
        </div>
      </div>

      <div class="col-md-4 col-sm-6">
        <div class="report-card bg-workhours" onclick="location.href='timesheet_reports.php'">
          <div class="report-icon-wrapper"><i class="fa-solid fa-clock"></i></div>
          <div class="report-title">تقرير ساعات العمل</div>
          <div class="report-number"><?= $workhours_count ?></div>
        </div>
      </div>

    </div>
  </div>
</div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
