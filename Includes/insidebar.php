<!-- زر القائمة في الموبايل -->
<button class="mobile-menu-btn" id="mobileMenuBtn">
  <i class="fa fa-bars"></i>
</button>

<div class="sidebar closed" id="sidebar">
  <div>
    <div class="toggle-btn" id="toggleBtn"><i class="fa fa-bars"></i></div>
    <h2 class="logo">Equipation</h2>

    <ul>
      <li><a href="../dashbourd.php"><i class="fa fa-home"></i> <span>الرئيسية</span></a></li>
      <li><a href="../Projects/projects.php"><i class="fa fa-project-diagram"></i> <span>المشاريع</span></a></li>
      <li><a href="../Suppliers/suppliers.php"><i class="fa fa-truck"></i> <span>الموردين</span></a></li>
      <li><a href="../Equipments/equipments.php"><i class="fa fa-tools"></i> <span>الآليات</span></a></li>
      <li><a href="../Drivers/drivers.php"><i class="fa fa-id-badge"></i> <span>المشغلين</span></a></li>
      <li><a href="../Oprators/oprators.php"><i class="fa fa-play-circle"></i> <span>التشغيل</span></a></li>
      <li><a href="../Timesheet/timesheet_type.php"><i class="fa fa-clock"></i> <span>ساعات العمل</span></a></li>
      <li><a href="../users.php"><i class="fa fa-users"></i> <span>المستخدمين</span></a></li>
      <li><a href="../Reports/reports.php"><i class="fa fa-chart-line"></i> <span>التقارير</span></a></li>
      <li><a href="../settings.php"><i class="fa fa-cog"></i> <span>الإعدادات</span></a></li>
    </ul>
  </div>

  <!-- زر تسجيل الخروج -->
  <a href="../index.php" class="logout">
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
