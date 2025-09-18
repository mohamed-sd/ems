<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit();
}
include "config.php";
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
  <meta charset="UTF-8">
  <title>إيكوبيشن | الرئيسية</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <!-- Font awsome -->
  <link rel="stylesheet" href="assets/css/all.min.css">
  <link rel="stylesheet" type="text/css" href="assets/css/style.css" />

  <style>
    /* ====== رسالة الترحيب ====== */
    .welcome-container {
      text-align: center;
      margin: 40px auto;
      position: relative;
      overflow: hidden;
      margin-top: 100px;
    }

    .welcome-message {
      width: 550px;
      display: inline-block;
      font-size: 20px;
      font-weight: 400;
      color: #ffcc00;
      padding: 10px 20px;
      border-radius: 12px;
      background: linear-gradient(135deg, #000022, #242435ff);
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    }

    .welcome-message span {
      opacity: 0;
      display: inline-block;
      transform: translateY(20px);
      animation: fadeInUp 0.6s forwards;
    }

    @keyframes fadeInUp {
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    /* النجوم المتطايرة */
    .star {
      position: absolute;
      color: #f1c40f;
      font-size: 14px;
      animation: fall 3s linear infinite;
      opacity: 0.8;
    }

    @keyframes fall {
      0% {
        transform: translateY(-20px) scale(1);
        opacity: 1;
      }

      100% {
        transform: translateY(100px) scale(0.5);
        opacity: 0;
      }
    }

    @media (max-width: 768px) {
      .welcome-message {
        font-size: 18px;
        padding: 8px 15px;
         width: 350px;
      }
    }

    /* ====== الكروت ====== */
    .cards {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 280px));
      gap: 20px;
      justify-content: center;
    }

    .card {
      background: #fff;
      padding: 20px;
      border-radius: 15px;
      text-align: center;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
      transition: all 0.3s ease;
      cursor: pointer;
    }

    .card:hover {
      transform: translateY(-6px) scale(1.02);
      box-shadow: 0 6px 18px rgba(0, 0, 0, 0.12);
    }

    .card i {
      font-size: 34px;
      color: #1976d2;
      margin-bottom: 12px;
    }

    .card h3 {
      font-size: 26px;
      margin: 8px 0;
      color: #2c3e50;
    }

    .card p {
      color: #555;
      font-weight: 600;
      font-size: 15px;
    }
  </style>
</head>

<body>

  <?php include('sidebar.php'); ?>

  <div class="main">

    <?php
    $roles = array(
      "0" => "مدير",
      "1" => "مدير المشاريع",
      "2" => "مدير الموردين",
      "3" => "مدير المشغلين",
      "4" => "مدير الأسطول",
      "5" => "مدير موقع",
      "6" => "مدخل ساعات عمل",
      "7" => "مراجع ساعات مورد",
      "8" => "مراجع ساعات مشغل",
      "9" => "مراجع الاعطال"
    );

    $userRole = $_SESSION['user']['role'];
    $userName = $_SESSION['user']['name'];
    $roleText = isset($roles[$userRole]) ? $roles[$userRole] : "غير معروف";
    $welcomeText = "مرحباً بك $roleText $userName في نظام إيكوبيشن 🚀 نتمنى لك يوماً مليئاً بالإنجازات!";
    ?>

    <!-- رسالة ترحيب متحركة -->
    <div class="welcome-container">
      <div class="welcome-message" id="welcome"></div>
    </div>

    <br><br>

    <!-- الكروت -->
    <div class="cards">
      <?php
      // ******************************** احصائيات المدير ******************************************************
      if ($_SESSION['user']['role'] == "1") {
        // كارد المشاريع
        $projects = $conn->query("SELECT COUNT(*) AS total FROM projects")->fetch_assoc()['total'];
        echo "<div class='card'><i class='fa fa-building'></i><h3>$projects</h3><p>المشاريع</p></div>";

        // كارد العقود
        $contracts = $conn->query("SELECT COUNT(*) AS total FROM contracts")->fetch_assoc()['total'];
        echo "<div class='card'><i class='fa fa-file-contract'></i><h3>$contracts</h3><p>العقود</p></div>";
      }
      ?>

      <?php
      // ******************************** احصائيات مدير الموردين ******************************************************
      if ($_SESSION['user']['role'] == "2") {
        $suppliers = $conn->query("SELECT COUNT(*) AS total FROM suppliers")->fetch_assoc()['total'];
        echo "<div class='card'><i class='fa fa-truck'></i><h3>$suppliers</h3><p>الموردين</p></div>";

        $equipments = $conn->query("SELECT COUNT(*) AS total FROM equipments")->fetch_assoc()['total'];
        echo "<div class='card'><i class='fa fa-tools'></i><h3>$equipments</h3><p>المعدات</p></div>";
      }
      ?>

      <?php
      // ******************************** احصائيات مدير المشغلين ******************************************************
      if ($_SESSION['user']['role'] == "3") {
        $equipments = $conn->query("SELECT COUNT(*) AS total FROM equipments")->fetch_assoc()['total'];
        echo "<div class='card'><i class='fa fa-tractor'></i><h3>$equipments</h3><p>المعدات</p></div>";

        $drivers = $conn->query("SELECT COUNT(*) AS total FROM drivers")->fetch_assoc()['total'];
        echo "<div class='card'><i class='fa fa-id-badge'></i><h3>$drivers</h3><p>السائقين</p></div>";
      }
      ?>

      <?php
      // ******************************** احصائيات مدير الاسطول ******************************************************
      if ($_SESSION['user']['role'] == "4") {
        $projects = $conn->query("SELECT COUNT(*) AS total FROM projects")->fetch_assoc()['total'];
        echo "<div class='card'><i class='fa fa-building'></i><h3>$projects</h3><p>المشاريع</p></div>";

        $equipments = $conn->query("SELECT COUNT(*) AS total FROM equipments")->fetch_assoc()['total'];
        echo "<div class='card'><i class='fa fa-tools'></i><h3>$equipments</h3><p>المعدات</p></div>";

        $activeOps = $conn->query("SELECT COUNT(*) AS total FROM operations WHERE status='active'")->fetch_assoc()['total'];
        echo "<div class='card'><i class='fa fa-play-circle'></i><h3>$activeOps</h3><p>معدات تعمل الآن</p></div>";
      }
      ?>

      <?php
      // ******************************** احصائيات مدير الموقع ******************************************************
      if ($_SESSION['user']['role'] == "5") {
        $equipments = $conn->query("SELECT COUNT(*) AS total FROM equipments")->fetch_assoc()['total'];
        echo "<div class='card'><i class='fa fa-tools'></i><h3>$equipments</h3><p>المعدات</p></div>";

        $hours = $conn->query("SELECT SUM(total_work_hours) AS total FROM timesheet")->fetch_assoc()['total'];
        echo "<div class='card'><i class='fa fa-clock'></i><h3>$hours</h3><p>إجمالي ساعات العمل</p></div>";
      }
      ?>

      <?php
      // ******************************** احصائيات مشرفين ******************************************************
      if ($_SESSION['user']['role'] == "6" || $_SESSION['user']['role'] == "7" || $_SESSION['user']['role'] == "8" || $_SESSION['user']['role'] == "9") {
        $hours = $conn->query("SELECT SUM(total_work_hours) AS total FROM timesheet")->fetch_assoc()['total'];
        echo "<div class='card'><i class='fa fa-clock'></i><h3>$hours</h3><p>إجمالي ساعات العمل</p></div>";
      }
      ?>
    </div>
  </div>

  <script>
    // كتابة الرسالة كلمة كلمة
    const text = "<?php echo $welcomeText; ?>".split(" ");
    const container = document.getElementById("welcome");

    text.forEach((word, index) => {
      const span = document.createElement("span");
      span.textContent = word + " ";
      span.style.animationDelay = (index * 0.25) + "s";
      container.appendChild(span);
    });

    // توليد نجوم عشوائية
    function createStar() {
      const star = document.createElement("i");
      star.classList.add("fa-solid", "fa-star", "star");
      star.style.left = Math.random() * 100 + "%";
      star.style.top = Math.random() * 30 + "px";
      star.style.animationDuration = (2 + Math.random() * 2) + "s";
      document.querySelector(".welcome-container").appendChild(star);

      setTimeout(() => star.remove(), 3000);
    }

    setInterval(createStar, 600);
  </script>
</body>

</html>
