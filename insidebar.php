<?php
//session_start();
// تضمين ملف الإعدادات والروابط الديناميكية
require_once dirname(__FILE__) . '/config.php';
require_once dirname(__FILE__) . '/includes/dynamic_nav.php';
require_once dirname(__FILE__) . '/includes/permissions_helper.php';

if (isset($_SESSION['user']) && isset($conn)) {
  enforce_current_page_view_permission($conn, '../main/dashboard.php');
}
?>
<!-- زر القائمة في الموبايل -->
<button class="mobile-menu-btn" id="mobileMenuBtn">
  <i class="fa fa-bars"></i>
</button>

<!-- طبقة الخلفية المعتمة (للموبايل) -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<div class="sidebar" id="sidebar">
  <div>
    <div class="toggle-btn" id="toggleBtn"><i class="fa fa-bars"></i></div>
    <h2 class="logo">Equipation</h2>

    <?php
    $hoursApprovalPendingCount = 0;
    if (isset($_SESSION['user']) && isset($_SESSION['user']['role']) && isset($conn)) {
      $sb_role         = strval($_SESSION['user']['role']);
      $sb_company_id   = intval($_SESSION['user']['company_id'] ?? 0);
      $sb_session_proj = intval($_SESSION['user']['project_id'] ?? 0);
      $sb_session_mine = intval($_SESSION['user']['mine_id'] ?? 0);

      $sb_allowed_roles = ['-1', '1', '2', '3', '4', '5'];
      if (in_array($sb_role, $sb_allowed_roles)) {
        $sb_role_level_map = ['1' => 1, '2' => 2, '3' => 3, '4' => 4];
        $sb_my_level       = $sb_role_level_map[$sb_role] ?? 0;
        $sb_prev_level     = $sb_my_level - 1;
        $sb_is_admin       = ($sb_role === '-1');
        $sb_is_site_manager = ($sb_role === '5');

        $sb_company_scope = '';
        if (!$sb_is_admin && $sb_company_id > 0) {
          $sb_company_scope = " AND (t.company_id = $sb_company_id OR t.company_id IS NULL)";
        }

        $sb_ops_project_col = (function_exists('db_table_has_column') && db_table_has_column($conn, 'operations', 'project_id'))
          ? 'project_id' : 'project';

        $sb_site_scope = '';
        if ($sb_is_site_manager) {
          if ($sb_session_proj > 0) {
            $sb_site_scope .= " AND o.$sb_ops_project_col = $sb_session_proj";
          }
          if ($sb_session_mine > 0 && function_exists('db_table_has_column') && db_table_has_column($conn, 'operations', 'mine_id')) {
            $sb_site_scope .= " AND o.mine_id = $sb_session_mine";
          }
        }

        if ($sb_is_site_manager) {
          $sb_pending_condition = '1=1';
        } elseif ($sb_is_admin) {
          $sb_pending_condition = "NOT EXISTS (
            SELECT 1 FROM timesheet_approvals ta2
            WHERE ta2.timesheet_id = t.id AND ta2.approval_level = 4 AND ta2.status = 1
          )";
        } elseif ($sb_my_level === 1) {
          $sb_pending_condition = "NOT EXISTS (
            SELECT 1 FROM timesheet_approvals ta2
            WHERE ta2.timesheet_id = t.id AND ta2.approval_level = 1 AND ta2.status = 1
          )";
        } elseif ($sb_my_level > 1) {
          $sb_pending_condition = "EXISTS (
            SELECT 1 FROM timesheet_approvals ta2
            WHERE ta2.timesheet_id = t.id AND ta2.approval_level = $sb_prev_level AND ta2.status = 1
          ) AND NOT EXISTS (
            SELECT 1 FROM timesheet_approvals ta3
            WHERE ta3.timesheet_id = t.id AND ta3.approval_level = $sb_my_level AND ta3.status = 1
          )";
        } else {
          $sb_pending_condition = '0=1';
        }

        $sb_cnt_sql = "
          SELECT COUNT(*) AS cnt
          FROM timesheet t
          LEFT JOIN operations o ON o.id = t.operator
          WHERE t.status = 1
            AND $sb_pending_condition
            $sb_company_scope
            $sb_site_scope
        ";
        $sb_cnt_res = $conn->query($sb_cnt_sql);
        if ($sb_cnt_res && ($sb_cnt_row = $sb_cnt_res->fetch_assoc())) {
          $hoursApprovalPendingCount = intval($sb_cnt_row['cnt'] ?? 0);
        }
      }
    }
    ?>

    <ul>
      <li><a href="../main/dashboard.php"><i class="fa-solid fa-house"></i> <span>الرئيسية</span></a></li>

      <li>
        <a href="../chats/index.php" id="sidebarChatLink">
          <i class="fa fa-comments"></i>
          <span>المراسلات
            <span id="nav-unread-badge"
              style="display:none; background:#dc3545; color:#fff; font-size:0.65rem; font-weight:700; border-radius:10px; padding:1px 5px; margin-right:4px; vertical-align:middle;"></span>
          </span>
        </a>
      </li>

      <?php
      // عرض الروابط الديناميكية من جدول modules بناءً على دور المستخدم
      if (isset($_SESSION['user']) && isset($_SESSION['user']['role']) && isset($conn)) {
        renderDynamicNavigation($conn, $_SESSION['user']['role'], '../');
      }
      ?>

      <?php // صلاحيات الادارة العليا == -1
      if ($_SESSION['user']['role'] == "-1") {
        ?>
        <!-- <li><a href="../Projects/add_project.php"><i class="fa fa-plus-circle"></i> <span>إضافة مشروع</span></a></li>
        <li><a href="../Projects/view_projects.php"><i class="fa fa-list-alt"></i> <span>قائمة المشاريع</span></a></li>
        <li><a href="../Projects/add_client.php"><i class="fa fa-user-plus"></i> <span>إضافة عميل</span></a></li>
        <li><a href="../Clients/clients.php"><i class="fa fa-users"></i> <span>قائمة العملاء</span></a></li>
        <li><a href="../Projects/projects.php"><i class="fa fa-folder-open"></i> <span>المشاريع</span></a></li>
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

      <?php if (in_array($_SESSION['user']['role'], ["-1", "1", "2", "3", "4", "5"])) { ?>
      <li><a href="../Approvals/hours_approval.php"><i class="fa fa-check-double"></i> <span>اعتماد الساعات
        <?php if ($hoursApprovalPendingCount > 0): ?>
        <span style="display:inline-block; background:#dc3545; color:#fff; font-size:0.65rem; font-weight:700; border-radius:10px; padding:1px 5px; margin-right:4px; vertical-align:middle;"><?php echo ($hoursApprovalPendingCount > 99 ? '99+' : $hoursApprovalPendingCount); ?></span>
        <?php endif; ?>
      </span></a></li>
      <?php } ?>

      <?php // صلاحيات مدير المشاريع === 1
      if ($_SESSION['user']['role'] == "1") { ?>
        <!-- <li><a href="../Clients/clients.php"><i class="fa fa-users"></i> <span>قائمة العملاء</span></a></li> -->
        <!-- <li><a href="../Projects/project_mines.php"><i class="fa fa-list-alt"></i> <span> المشاريع </span></a></li> -->
        <!-- <li><a href="../Projects/projects.php"><i class="fa fa-folder-open"></i> <span></span></a></li> -->
        <!-- <li><a href="../main/users.php"><i class="fa fa-users-cog"></i> <span>المستخدمين</span></a></li> -->
        <!-- <li><a href="../Reports/new_reports.php"><i class="fa fa-chart-pie"></i> <span>التقارير</span></a></li> -->
        <!-- <li><a href="../Reports/reports.php"><i class="fa fa-chart-pie"></i> <span>تقارير العقد</span></a></li>
        <li><a href="../Equipments/equipments_types.php"><i class="fa-solid fa-screwdriver-wrench"></i> <span> انواع
              المعدات</span></a></li> -->
      <?php } ?>

      <?php // صلاحيات مدير الموردين === 2
      if ($_SESSION['user']['role'] == "2") { ?>
        <!-- <li><a href="../Suppliers/suppliers.php"><i class="fa fa-truck-loading"></i> <span>الموردين</span></a></li> -->
        <!-- <li><a href="../Equipments/equipments.php"><i class="fa fa-tractor"></i> <span>الآليات</span></a></li> -->
        <!-- <li><a href="../Reports/reports.php"><i class="fa fa-chart-pie"></i> <span>التقارير</span></a></li> -->

      <?php } ?>

      <?php // صلاحيات مدير المشغلين === 3
      if ($_SESSION['user']['role'] == "3") { ?>
        <!-- <li><a href="../Equipments/equipments.php"><i class="fa fa-tractor"></i> <span>الآليات</span></a></li> -->
        <!-- <li><a href="../Drivers/drivers.php"><i class="fa fa-id-card"></i> <span>المشغلين</span></a></li>
        <li><a href="../Reports/reports.php"><i class="fa fa-chart-pie"></i> <span>التقارير</span></a></li>
        <li><a href="../Approvals/requests.php"><i class="fa fa-check-double"></i> <span>طلبات الموافقات</span></a></li> -->

      <?php } ?>

      <?php // صلاحيات مدير الاسطول === 4
      if ($_SESSION['user']['role'] == "4") { ?>
        <!-- <li><a href="../Equipments/equipments.php"><i class="fa fa-tractor"></i> <span>الآليات</span></a></li> -->
        <!-- <li><a href="../Oprators/oprators.php"><i class="fa fa-cogs"></i> <span>التشغيل</span></a></li>
        <li><a href="../Reports/reports.php"><i class="fa fa-chart-pie"></i> <span>التقارير</span></a></li>
        <li><a href="../Approvals/requests.php"><i class="fa fa-check-double"></i> <span>طلبات الموافقات</span></a></li> -->
      <?php } ?>

      <?php // صلاحيات مدير الحركة والتشغيل === 10
      if ($_SESSION['user']['role'] == "10") { ?>
        <!-- <li><a href="../Oprators/oprators.php"><i class="fa fa-cogs"></i> <span>التشغيل</span></a></li>
        <li><a href="../Equipments/equipments.php"><i class="fa fa-tractor"></i> <span>الآليات</span></a></li> -->
      <?php } ?>

      <?php // صلاحيات مدير الموقع === 5
      if ($_SESSION['user']['role'] == "5") { ?>
        <!-- <li><a href="../main/project_users.php"><i class="fa fa-users-cog"></i> <span> المشرفين </span></a></li>
        <li><a href="../Timesheet/timesheet_type.php"><i class="fa fa-business-time"></i> <span>ساعات العمل</span></a>
        </li>
        <li><a href="../Timesheet/view_timesheet.php"><i class="fa fa-calendar-days"></i> <span>ساعات اليوم</span></a>
        </li>
        <li><a href="../Reports/reports.php"><i class="fa fa-chart-pie"></i> <span>التقارير</span></a></li> -->
      <?php } ?>

      <?php // صلاحيات  مدخل الساعات === 6 
      if ($_SESSION['user']['role'] == "6") { ?>
        <!-- <li><a href="../Timesheet/timesheet_type.php"><i class="fa fa-business-time"></i> <span>ساعات العمل</span></a>
        </li>
        <li><a href="../Timesheet/view_timesheet.php"><i class="fa fa-calendar-days"></i> <span>ساعات اليوم</span></a>
        </li> -->
      <?php } ?>

      <?php // صلاحيات مراجع ساعات المورد والمشغل === 7 8 
      if ($_SESSION['user']['role'] == "7" || $_SESSION['user']['role'] == "8") { ?>
        <!-- <li><a href="../Reports/reports.php"><i class="fa fa-chart-pie"></i> <span>التقارير</span></a></li> -->
        <!-- <li><a href="../Timesheet/timesheet_type.php"><i class="fa fa-business-time"></i> <span>ساعات العمل</span></a> -->
        <?php } ?>

        <?php // صلاحيات مراجع الاعطال === 9 
        if ($_SESSION['user']['role'] == "9") { ?>
        <!-- <li><a href="../Timesheet/timesheet_type.php"><i class="fa fa-business-time"></i> <span>ساعات العمل</span></a> -->
        <?php } ?>

      <li><a href="../emsreports/index.php"><i class="fas fa-chart-pie"></i> <span>التقارير</span></a></li>

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
  const sidebar       = document.getElementById('sidebar');
  const toggleBtn     = document.getElementById('toggleBtn');
  const mobileMenuBtn = document.getElementById('mobileMenuBtn');
  const sidebarOverlay = document.getElementById('sidebarOverlay');

  function isMobile() { return window.innerWidth <= 768; }

  function openSidebar() {
    sidebar.classList.add('active');
    sidebarOverlay.classList.add('active');
    document.body.style.overflow = 'hidden'; // منع التمرير خلف السايدبار
  }

  function closeSidebar() {
    sidebar.classList.remove('active');
    sidebarOverlay.classList.remove('active');
    document.body.style.overflow = '';
  }

  // زر السهم داخل السايدبار (للحاسوب فقط)
  if (toggleBtn) {
    toggleBtn.addEventListener('click', () => {
      if (!isMobile()) {
        sidebar.classList.toggle('closed');
      }
    });
  }

  // زر الهامبرغر الخارجي (للموبايل)
  mobileMenuBtn.addEventListener('click', () => {
    if (sidebar.classList.contains('active')) {
      closeSidebar();
    } else {
      openSidebar();
    }
  });

  // إغلاق عند النقر على الخلفية المعتمة
  sidebarOverlay.addEventListener('click', () => {
    closeSidebar();
  });

  // إغلاق عند الضغط على أي رابط داخل السايدبار (موبايل)
  sidebar.querySelectorAll('a').forEach(link => {
    link.addEventListener('click', () => {
      if (isMobile()) closeSidebar();
    });
  });

  // إغلاق عند تغيير حجم الشاشة للكمبيوتر
  window.addEventListener('resize', () => {
    if (!isMobile()) {
      closeSidebar();
    }
  });

  // ===== شارة الرسائل غير المقروءة =====
  function updateChatNavBadge() {
    var xhr = new XMLHttpRequest();
    xhr.open('GET', '/ems/chats/get_unread_count.php', true);
    xhr.onload = function() {
      try {
        var data = JSON.parse(xhr.responseText);
        var badge = document.getElementById('nav-unread-badge');
        if (badge) {
          if (data.count > 0) {
            badge.textContent = data.count > 99 ? '99+' : data.count;
            badge.style.display = 'inline';
          } else {
            badge.style.display = 'none';
          }
        }
      } catch(e) {}
    };
    xhr.send();
  }
  updateChatNavBadge();
  setInterval(updateChatNavBadge, 30000);
</script>