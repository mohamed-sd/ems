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
    @import url('https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700;900&display=swap');

    :root {
      --primary-color: #1a1a2e;
      --secondary-color: #16213e;
      --accent-color: #d8ae02;
      --text-color: #010326;
      --light-color: #f5f5f5;
      --shadow-color: rgba(0, 0, 0, 0.1);
      --gold-color: #ffcc00;
    }
    
    * {
      font-family: 'Cairo', sans-serif;
    }
    
  
    
    .main {
      padding: 1rem;
      width: 100%;
      background-color: white;
    }
    
    /* ====== رسالة الترحيب ====== */
    .welcome-container {
      text-align: center;
      margin: 10px auto 10px;
      position: relative;
      overflow: hidden;
    }

    .welcome-message {
      width: 100%;
      display: inline-block;
      font-size: 20px;
      font-weight: 700;
      color: white;
      padding: 20px 30px;
      border-radius: 20px;
      background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
      box-shadow: 0 10px 30px var(--shadow-color);
      animation: slideIn 0.6s ease-out;
    }

    @keyframes slideIn {
      from {
        opacity: 0;
        transform: translateY(-20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .welcome-message span {
      opacity: 0;
      display: inline-block;
      transform: translateY(20px);
      animation: fadeInUp 0.6s forwards;
      margin: 0 0.2em;
      white-space: nowrap;
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
      color: var(--gold-color);
      font-size: 16px;
      animation: fall 3s linear infinite;
      opacity: 0.9;
    }

    @keyframes fall {
      0% {
        transform: translateY(-20px) scale(1) rotate(0deg);
        opacity: 1;
      }
      100% {
        transform: translateY(100px) scale(0.5) rotate(360deg);
        opacity: 0;
      }
    }

    /* ====== أزرار الوصول السريع ====== */
    .quick-access {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
      gap: 1rem;
      margin: 2rem 0;
      max-width: 1200px;
      margin-left: auto;
      margin-right: auto;
      animation: fadeIn 0.8s ease-out 0.2s both;
    }

    @keyframes fadeIn {
      from {
        opacity: 0;
      }
      to {
        opacity: 1;
      }
    }
    
    .quick-btn {
      background: white;
      border-radius: 15px;
      padding: 1.5rem;
      text-align: center;
      text-decoration: none;
      color: var(--text-color);
      transition: all 0.3s ease;
      box-shadow: 0 4px 15px rgba(0,0,0,0.08);
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 0.75rem;
      animation: scaleIn 0.5s ease-out backwards;
    }

    @keyframes scaleIn {
      from {
        opacity: 0;
        transform: scale(0.9);
      }
      to {
        opacity: 1;
        transform: scale(1);
      }
    }

    .quick-btn:nth-child(1) { animation-delay: 0.3s; }
    .quick-btn:nth-child(2) { animation-delay: 0.35s; }
    .quick-btn:nth-child(3) { animation-delay: 0.4s; }
    .quick-btn:nth-child(4) { animation-delay: 0.45s; }
    .quick-btn:nth-child(5) { animation-delay: 0.5s; }
    .quick-btn:nth-child(6) { animation-delay: 0.55s; }
    
    .quick-btn:hover {
      transform: translateY(-5px);
      box-shadow: 0 8px 25px var(--shadow-color);
      color: var(--gold-color);
    }
    
    .quick-btn i {
      font-size: 2.5rem;
      background: linear-gradient(135deg, var(--gold-color) 50%, var(--primary-color) 50%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      transition: all 0.3s ease;
    }
    
    .quick-btn:hover i {
      transform: scale(1.1) rotate(5deg);
    }
    
    .quick-btn span {
      font-weight: 600;
      font-size: 1rem;
    }

    /* ====== عنوان القسم ====== */
    .section-title {
      text-align: center;
      font-size: 1.8rem;
      font-weight: 500;
      background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.30rem;
      animation: fadeIn 0.8s ease-out 0.1s both;
    }

    /* ====== الكروت ====== */
    .cards {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
      gap: 1.5rem;
      max-width: 1400px;
      margin: 0 auto;
    }

    .card {
      background: white;
      padding: 1rem;
      border-radius: 20px;
      text-align: center;
      box-shadow: 0 5px 20px var(--shadow-color);
      transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
      cursor: pointer;
      position: relative;
      overflow: hidden;
      animation: popIn 0.6s ease-out backwards;
    }

    @keyframes popIn {
      from {
        opacity: 0;
        transform: scale(0.8);
      }
      to {
        opacity: 1;
        transform: scale(1);
      }
    }

    .card:nth-child(1) { animation-delay: 0.1s; }
    .card:nth-child(2) { animation-delay: 0.15s; }
    .card:nth-child(3) { animation-delay: 0.2s; }
    .card:nth-child(4) { animation-delay: 0.25s; }
    
    .card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 4px;
      background: linear-gradient(135deg, var(--secondary-color) 50%, var(--gold-color) 50%);
      transform: scaleX(0);
      transition: transform 0.3s ease;
    }
    
    .card:hover::before {
      transform: scaleX(1);
    }

    .card:hover {
      transform: translateY(-10px) scale(1.03);
      box-shadow: 0 15px 40px var(--shadow-color);
    }

    .card i {
      font-size: 2rem;
      background: linear-gradient(135deg, var(--secondary-color) 50%, var(--accent-color) 50%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      margin-bottom: 0rem;
      transition: all 0.3s ease;
    }

    .card:hover i {
      transform: scale(1.15) rotate(-5deg);
    }

    .card h3 {
      font-size: 2rem;
      margin: 0.5rem 0;
      color: var(--primary-color);
      font-weight: 500;
    }

    .card p {
      color: #6c757d;
      font-weight: 600;
      font-size: 1rem;
      margin: 0;
    }
    
    .card a {
      text-decoration: none;
      color: inherit;
      display: block;
    }

    @media (max-width: 768px) {
      .main {
        padding: 1rem;
      }
      
      .welcome-message {
        font-size: 18px;
        padding: 15px 20px;
        max-width: 90%;
      }
      
      .cards {
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 1rem;
      }
      
      .card {
        padding: 1.5rem 1rem;
      }
      
      .card h3 {
        font-size: 2rem;
      }
      
      .quick-access {
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
      }
      
      .section-title {
        font-size: 1.4rem;
      }
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
    $welcomeText = " مرحباً بك ".$roleText." ".$userName." في نظام إيكوبيشن 🚀 نتمنى لك يوماً مليئاً بالإنجازات!";
    ?>

    <!-- رسالة ترحيب متحركة -->
    <div class="welcome-container">
      <div class="welcome-message" id="welcome"></div>
    </div>
    <?php if ($_SESSION['user']['role'] == "1") { ?>
    <!-- أزرار الوصول السريع لمدير المشاريع -->
    <h2 class="section-title">
      <i class="fas fa-bolt"></i> الوصول السريع
    </h2>
    <div class="quick-access">
       <a href="Projects/view_clients.php" class="quick-btn">
        <i class="fas fa-users"></i>
        <span>العملاء</span>
      </a>
      <a href="Projects/view_projects.php" class="quick-btn">
        <i class="fas fa-list-alt"></i>
        <span>مشاريع الشركة</span>
      </a>
      <a href="Projects/oprationprojects.php" class="quick-btn">
        <i class="fas fa-project-diagram"></i>
        <span>المشاريع التشغيلية</span>
      </a>
      <a href="Reports/reports.php" class="quick-btn">
        <i class="fas fa-chart-line"></i>
        <span>التقارير</span>
      </a>
      <a href="users.php" class="quick-btn">
        <i class="fas fa-user-shield"></i>
        <span>المستخدمين</span>
      </a>
      <a href="settings.php" class="quick-btn">
        <i class="fas fa-cog"></i>
        <span>الإعدادات</span>
      </a>
    </div>
    <?php } ?>

    <!-- الإحصائيات -->
    <h2 class="section-title">
      <i class="fas fa-chart-bar"></i> الإحصائيات
    </h2>
    <div class="cards">
      <?php
      // ******************************** احصائيات المدير ******************************************************
      if ($_SESSION['user']['role'] == "1") {
        // كارد المشاريع التشغيلية
        $projects = $conn->query("SELECT COUNT(*) AS total FROM operationproject")->fetch_assoc()['total'];
        echo "<a href='Projects/oprationprojects.php' style='text-decoration: none;'>
                <div class='card'>
                  <i class='fas fa-project-diagram'></i>
                  <h3>$projects</h3>
                  <p>المشاريع التشغيلية</p>
                </div>
              </a>";

        // كارد المشاريع الأساسية
        $company_projects = $conn->query("SELECT COUNT(*) AS total FROM company_project WHERE status = 'نشط'")->fetch_assoc()['total'];
        echo "<a href='Projects/view_projects.php' style='text-decoration: none;'>
                <div class='card'>
                  <i class='fas fa-list-alt'></i>
                  <h3>$company_projects</h3>
                  <p>المشاريع النشطة</p>
                </div>
              </a>";

        // كارد العملاء
        $clients = $conn->query("SELECT COUNT(*) AS total FROM company_clients WHERE status = 'نشط'")->fetch_assoc()['total'];
        echo "<a href='Projects/view_clients.php' style='text-decoration: none;'>
                <div class='card'>
                  <i class='fas fa-users'></i>
                  <h3>$clients</h3>
                  <p> عملاء الشركة </p>
                </div>
              </a>";

    
    
  
     

        // كارد المستخدمين
        $users = $conn->query("SELECT COUNT(*) AS total FROM users")->fetch_assoc()['total'];
        echo "<a href='users.php' style='text-decoration: none;'>
                <div class='card'>
                  <i class='fas fa-user-shield'></i>
                  <h3>$users</h3>
                  <p>المستخدمين</p>
                </div>
              </a>";
     
      }
      ?>

      <?php
      // ******************************** احصائيات مدير الموردين ******************************************************
      if ($_SESSION['user']['role'] == "2") {
        $suppliers = $conn->query("SELECT COUNT(*) AS total FROM suppliers")->fetch_assoc()['total'];
        echo "<a href='Suppliers/suppliers.php' style='text-decoration: none;'>
                <div class='card'>
                  <i class='fas fa-truck'></i>
                  <h3>$suppliers</h3>
                  <p>الموردين</p>
                </div>
              </a>";

        $equipments = $conn->query("SELECT COUNT(*) AS total FROM equipments")->fetch_assoc()['total'];
        echo "<a href='Equipments/equipments.php' style='text-decoration: none;'>
                <div class='card'>
                  <i class='fas fa-tools'></i>
                  <h3>$equipments</h3>
                  <p>المعدات</p>
                </div>
              </a>";
      }
      ?>

      <?php
      // ******************************** احصائيات مدير المشغلين ******************************************************
      if ($_SESSION['user']['role'] == "3") {
        $equipments = $conn->query("SELECT COUNT(*) AS total FROM equipments")->fetch_assoc()['total'];
        echo "<a href='Equipments/equipments.php' style='text-decoration: none;'>
                <div class='card'>
                  <i class='fas fa-tractor'></i>
                  <h3>$equipments</h3>
                  <p>المعدات</p>
                </div>
              </a>";

        $drivers = $conn->query("SELECT COUNT(*) AS total FROM drivers")->fetch_assoc()['total'];
        echo "<a href='Drivers/drivers.php' style='text-decoration: none;'>
                <div class='card'>
                  <i class='fas fa-id-badge'></i>
                  <h3>$drivers</h3>
                  <p>السائقين</p>
                </div>
              </a>";
      }
      ?>

      <?php
      // ******************************** احصائيات مدير الاسطول ******************************************************
      if ($_SESSION['user']['role'] == "4") {
        $projects = $conn->query("SELECT COUNT(*) AS total FROM operationproject")->fetch_assoc()['total'];
        echo "<a href='Projects/projects.php' style='text-decoration: none;'>
                <div class='card'>
                  <i class='fas fa-building'></i>
                  <h3>$projects</h3>
                  <p>المشاريع</p>
                </div>
              </a>";

        $equipments = $conn->query("SELECT COUNT(*) AS total FROM equipments")->fetch_assoc()['total'];
        echo "<a href='Equipments/equipments.php' style='text-decoration: none;'>
                <div class='card'>
                  <i class='fas fa-tools'></i>
                  <h3>$equipments</h3>
                  <p>المعدات</p>
                </div>
              </a>";

        $activeOps = $conn->query("SELECT COUNT(*) AS total FROM `operations` WHERE `status` LIKE '1'")->fetch_assoc()['total'];
        echo "<div class='card'>
                <i class='fas fa-play-circle'></i>
                <h3>$activeOps</h3>
                <p>معدات تعمل الآن</p>
              </div>";
      }
      ?>

      <?php
      // ******************************** احصائيات مدير الموقع ******************************************************
      if ($_SESSION['user']['role'] == "5") {
        $equipments = $conn->query("SELECT COUNT(*) AS total FROM equipments")->fetch_assoc()['total'];
        echo "<a href='Equipments/equipments.php' style='text-decoration: none;'>
                <div class='card'>
                  <i class='fas fa-tools'></i>
                  <h3>$equipments</h3>
                  <p>المعدات</p>
                </div>
              </a>";

        $hours = $conn->query("SELECT SUM(total_work_hours) AS total FROM timesheet")->fetch_assoc()['total'];
        $hours = $hours ? number_format($hours, 0) : '0';
        echo "<a href='Timesheet/timesheet.php' style='text-decoration: none;'>
                <div class='card'>
                  <i class='fas fa-clock'></i>
                  <h3>$hours</h3>
                  <p>إجمالي ساعات العمل</p>
                </div>
              </a>";
      }
      ?>

      <?php
      // ******************************** احصائيات مشرفين ******************************************************
      if ($_SESSION['user']['role'] == "6" || $_SESSION['user']['role'] == "7" || $_SESSION['user']['role'] == "8" || $_SESSION['user']['role'] == "9") {
        $hours = $conn->query("SELECT SUM(total_work_hours) AS total FROM timesheet")->fetch_assoc()['total'];
        $hours = $hours ? number_format($hours, 0) : '0';
        echo "<a href='Timesheet/timesheet.php' style='text-decoration: none;'>
                <div class='card'>
                  <i class='fas fa-clock'></i>
                  <h3>$hours</h3>
                  <p>إجمالي ساعات العمل</p>
                </div>
              </a>";
      }
      ?>
    </div>
  </div>

  <script>
    // كتابة الرسالة كلمة كلمة
    const text = "<?php echo $welcomeText; ?>".trim().split(" ");
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
