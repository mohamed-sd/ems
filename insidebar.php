<?php
//session_start();
?>
<!-- زر القائمة في الموبايل -->
<button class="mobile-menu-btn" id="mobileMenuBtn">
  <i class="fa fa-bars"></i>
</button>

<div class="sidebar" id="sidebar">
  <div>
    <div class="toggle-btn" id="toggleBtn"><i class="fa fa-bars"></i></div>
    <h2 class="logo">Equipation</h2>

    <ul>
      <li><a href="../main/dashboard.php"><i class="fa fa-tachometer-alt"></i> <span>الرئيسية</span></a></li>

      <?php // صلاحيات الادارة العليا == -1
      if ($_SESSION['user']['role'] == "-1") {
        ?>
        <li><a href="../Projects/add_project.php"><i class="fa fa-plus-circle"></i> <span>إضافة مشروع</span></a></li>
        <li><a href="../Projects/view_projects.php"><i class="fa fa-list-alt"></i> <span>قائمة المشاريع</span></a></li>
        <li><a href="../Projects/add_client.php"><i class="fa fa-user-plus"></i> <span>إضافة عميل</span></a></li>
        <li><a href="../Clients/clients.php"><i class="fa fa-users"></i> <span>قائمة العملاء</span></a></li>
        <li><a href="../Projects/oprationprojects.php"><i class="fa fa-folder-open"></i> <span>المشاريع</span></a></li>
        <li><a href="../Suppliers/suppliers.php"><i class="fa fa-truck-loading"></i> <span>الموردين</span></a></li>
        <li><a href="../Equipments/equipments.php"><i class="fa fa-tractor"></i> <span>الآليات</span></a></li>
        <li><a href="../Drivers/drivers.php"><i class="fa fa-id-card"></i> <span>المشغلين</span></a></li>
        <li><a href="../Oprators/oprators.php"><i class="fa fa-cogs"></i> <span>التشغيل</span></a></li>
        <li><a href="../Timesheet/timesheet_type.php"><i class="fa fa-business-time"></i> <span>ساعات العمل</span></a>
        </li>
        <li><a href="../Timesheet/view_timesheet.php"><i class="fa fa-calendar-days"></i> <span>ساعات اليوم</span></a>
        </li>
        <li><a href="../main/users.php"><i class="fa fa-users-cog"></i> <span>المستخدمين</span></a></li>
        <li><a href="../Reports/new_reports.php"><i class="fa fa-chart-pie"></i> <span>التقارير</span></a></li>
        <li><a href="../Reports/reports.php"><i class="fa fa-chart-pie"></i> <span>تقارير العقود</span></a></li>
        <?php
      }
      ?>

      <?php // صلاحيات مدير المشاريع === 1
      if ($_SESSION['user']['role'] == "1") { ?>
        <li><a href="../Clients/clients.php"><i class="fa fa-users"></i> <span>قائمة العملاء</span></a></li>
        <li><a href="../Projects/project_mines.php"><i class="fa fa-list-alt"></i> <span> المشاريع </span></a></li>
        <!-- <li><a href="../Projects/oprationprojects.php"><i class="fa fa-folder-open"></i> <span></span></a></li> -->
        <li><a href="../main/users.php"><i class="fa fa-users-cog"></i> <span>المستخدمين</span></a></li>
        <!-- <li><a href="../Reports/new_reports.php"><i class="fa fa-chart-pie"></i> <span>التقارير</span></a></li> -->
        <li><a href="../Reports/reports.php"><i class="fa fa-chart-pie"></i> <span>تقارير العقد</span></a></li>
        <li><a href="../Equipments/equipments_types.php"><i class="fa-solid fa-screwdriver-wrench"></i> <span>  انواع المعدات</span></a></li>
      <?php } ?>

      <?php // صلاحيات مدير الموردين === 2
      if ($_SESSION['user']['role'] == "2") { ?>
        <li><a href="../Suppliers/suppliers.php"><i class="fa fa-truck-loading"></i> <span>الموردين</span></a></li>
        <!-- <li><a href="../Equipments/equipments.php"><i class="fa fa-tractor"></i> <span>الآليات</span></a></li> -->
        <li><a href="../Reports/reports.php"><i class="fa fa-chart-pie"></i> <span>التقارير</span></a></li>

      <?php } ?>

      <?php // صلاحيات مدير المشغلين === 3
      if ($_SESSION['user']['role'] == "3") { ?>
        <li><a href="../Equipments/equipments.php"><i class="fa fa-tractor"></i> <span>الآليات</span></a></li>
        <li><a href="../Drivers/drivers.php"><i class="fa fa-id-card"></i> <span>المشغلين</span></a></li>
        <li><a href="../Reports/reports.php"><i class="fa fa-chart-pie"></i> <span>التقارير</span></a></li>

      <?php } ?>

      <?php // صلاحيات مدير الاسطول === 4
      if ($_SESSION['user']['role'] == "4") { ?>
        <li><a href="../Equipments/equipments.php"><i class="fa fa-tractor"></i> <span>الآليات</span></a></li>
        <li><a href="../Oprators/oprators.php"><i class="fa fa-cogs"></i> <span>التشغيل</span></a></li>
        <li><a href="../Reports/reports.php"><i class="fa fa-chart-pie"></i> <span>التقارير</span></a></li>

      <?php } ?>

      <?php // صلاحيات مدير الحركة والتشغيل === 10
      if ($_SESSION['user']['role'] == "10") { ?>
        <li><a href="../Oprators/oprators.php"><i class="fa fa-cogs"></i> <span>التشغيل</span></a></li>
        <li><a href="../Equipments/equipments.php"><i class="fa fa-tractor"></i> <span>الآليات</span></a></li>

      <?php } ?>

      <?php // صلاحيات مدير الموقع === 5
      if ($_SESSION['user']['role'] == "5") { ?>
        <li><a href="../project_users.php"><i class="fa fa-users-cog"></i> <span> مستخدمين المدير </span></a></li>
        <li><a href="../Timesheet/timesheet_type.php"><i class="fa fa-business-time"></i> <span>ساعات العمل</span></a>
        </li>
        <li><a href="../Timesheet/view_timesheet.php"><i class="fa fa-calendar-days"></i> <span>ساعات اليوم</span></a>
        </li>
        <li><a href="../Reports/reports.php"><i class="fa fa-chart-pie"></i> <span>التقارير</span></a></li>
      <?php } ?>

      <?php // صلاحيات  مدخل الساعات === 6 
      if ($_SESSION['user']['role'] == "6") { ?>
        <li><a href="../Timesheet/timesheet_type.php"><i class="fa fa-business-time"></i> <span>ساعات العمل</span></a>
        </li>
        <li><a href="../Timesheet/view_timesheet.php"><i class="fa fa-calendar-days"></i> <span>ساعات اليوم</span></a>
        </li>
      <?php } ?>

      <?php // صلاحيات مراجع ساعات المورد والمشغل === 7 8 
      if ($_SESSION['user']['role'] == "7" || $_SESSION['user']['role'] == "8") { ?>
        <!-- <li><a href="../Reports/reports.php"><i class="fa fa-chart-pie"></i> <span>التقارير</span></a></li> -->
        <li><a href="../Timesheet/timesheet_type.php"><i class="fa fa-business-time"></i> <span>ساعات العمل</span></a>
        <?php } ?>

        <?php // صلاحيات مراجع الاعطال === 9 
        if ($_SESSION['user']['role'] == "9") { ?>
        <li><a href="../Timesheet/timesheet_type.php"><i class="fa fa-business-time"></i> <span>ساعات العمل</span></a>
        <?php } ?>

      <li><a href="../Settings/settings.php"><i class="fa fa-cog"></i> <span>الإعدادات</span></a></li>


      <!-- 
      <?php if ($_SESSION['user']['role'] == "1") {
        // المدير
        ?>
      <li><a href="../Projects/projects.php"><i class="fa fa-folder-open"></i> <span>المشاريع</span></a></li>
      <li><a href="../Oprators/oprators.php"><i class="fa fa-cogs"></i> <span>التشغيل</span></a></li>
      <li><a href="../users.php"><i class="fa fa-users-cog"></i> <span>المستخدمين</span></a></li>
      <?php } else
        if ($_SESSION['user']['role'] == "2") { ?>
      <li><a href="../Suppliers/suppliers.php"><i class="fa fa-truck-loading"></i> <span>الموردين</span></a></li>
      <li><a href="../Equipments/equipments.php"><i class="fa fa-tractor"></i> <span>الآليات</span></a></li>
      <li><a href="../Timesheet/timesheet_type.php"><i class="fa fa-business-time"></i> <span>ساعات العمل</span></a></li>
      <li><a href="../Drivers/drivers.php"><i class="fa fa-id-card"></i> <span>المشغلين</span></a></li>
      <li><a href="../users.php"><i class="fa fa-users-cog"></i> <span>المستخدمين</span></a></li>
      <li><a href="../Reports/reports.php"><i class="fa fa-chart-pie"></i> <span>التقارير</span></a></li>
      <li><a href="../settings.php"><i class="fa fa-cog"></i> <span>الإعدادات</span></a></li>
      <?php } ?>
      <?php if ($_SESSION['user']['role'] == "3") { ?>
      <li><a href="../Suppliers/suppliers.php"><i class="fa fa-truck-loading"></i> <span>الموردين</span></a></li>
      <li><a href="../Equipments/equipments.php"><i class="fa fa-tractor"></i> <span>الآليات</span></a></li>
      <?php } ?>
      <?php if ($_SESSION['user']['role'] == "4") { ?>
      <li><a href="../Timesheet/timesheet_type.php"><i class="fa fa-business-time"></i> <span>ساعات العمل</span></a></li>
      <?php } ?>

      <?php if ($_SESSION['user']['role'] == "5") { ?>
      <li><a href="../Drivers/drivers.php"><i class="fa fa-id-card"></i> <span>المشغلين</span></a></li>
      <li><a href="project_users.php"><i class="fa fa-users-cog"></i> <span> مستخدمين المدير </span></a></li>
       <?php } ?> -->
    </ul>
  </div>

  <!-- زر تسجيل الخروج -->
  <a href="../logout.php" class="logout">
    <i class="fa fa-sign-out-alt"></i>
    <span>تسجيل الخروج</span>
  </a>
</div>

<script>
  const sidebar = document.getElementById('sidebar');
  const toggleBtn = document.getElementById('toggleBtn');
  const mobileMenuBtn = document.getElementById('mobileMenuBtn');

  // للحاسوب
  toggleBtn.addEventListener('click', () => {
    if (window.innerWidth > 768) {
      sidebar.classList.toggle('closed');
    }
  });

  // للموبايل
  mobileMenuBtn.addEventListener('click', () => {
    sidebar.classList.toggle('active');
  });
</script>