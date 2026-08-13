<?php
//session_start();
// تضمين ملف الإعدادات والروابط الديناميكية
require_once dirname(__FILE__) . '/config.php';
require_once dirname(__FILE__) . '/includes/dynamic_nav.php';
require_once dirname(__FILE__) . '/includes/unified_nav.php';
require_once dirname(__FILE__) . '/includes/permissions_helper.php';
// التشغيل المزدوج (UX-01 §10.4-②): الدور المذكور في EMS_NAV_UNIFIED_ROLES يرى
// المصدر الموحّد بأبوابه الستة، وسائر الأدوار على مصادرها القديمة حرفيًّا.
$__nav_unified = isset($_SESSION['user']['role']) && unifiedNavEnabled($_SESSION['user']['role']);

if (isset($_SESSION['user']) && isset($conn)) {
  enforce_current_page_view_permission($conn, '../main/dashboard.php');
}

// TKT-01 §2-⑧ + NAV-01 §5-② (update0006-b): زرُّ الإبلاغ الاحتياطيُّ العالمي —
// يُبثُّ آخرَ الصفحة إن لم تستدعِ الشاشةُ زرًّا سياقيًّا أغنى بنفسها،
// فتتحقق «صفرُ شاشةٍ تشغيليةٍ بلا زرِّ إبلاغ» بلا ازدواج.
// CMP-03 ②: سياق طبقة الحوكمة المشتركة — قيم عامة (الكيان · العملة الأساسية)
// يقرأها ui-unification.js لحشو خلايا أعمدة الحوكمة المحقونة قبل تهيئة الجداول.
if (isset($_SESSION['user'])) {
  require_once dirname(__FILE__) . '/includes/gov_columns.php';
  ems_gov_emit_assets();
}

if (isset($_SESSION['user'])) {
  require_once dirname(__FILE__) . '/includes/report_button.php';
  if (empty($GLOBALS['__ems_rb_shutdown'])) {
    $GLOBALS['__ems_rb_shutdown'] = true;
    register_shutdown_function('ems_report_button_fallback');
  }
}
?>
<?php
// Match inheader.php cache-busting so the dedup check below recognises the
// already-loaded versioned stylesheets (avoids appending a stale duplicate).
$__sb_css_dir = __DIR__ . '/assets/css/';
$__sb_ver = function ($f) use ($__sb_css_dir) {
    $p = $__sb_css_dir . $f;
    return is_file($p) ? ('?v=' . filemtime($p)) : '';
};
?>
<script>
  (function () {
    var head = document.head || document.getElementsByTagName('head')[0];
    if (!head) return;

    var cssFiles = [
      '/ems/assets/css/local-fonts.css',
      // كاسرُ الذاكرةِ إلزاميّ — بدونه تُخدَم لوحةُ ألوانٍ قديمةٌ فتسقط كلُّ var()
      '/ems/assets/css/design-tokens.css<?php echo $__sb_ver('design-tokens.css'); ?>',
      '/ems/assets/css/ems.main.all.style.css<?php echo $__sb_ver('ems.main.all.style.css'); ?>',
      '/ems/assets/css/ems-tables.css<?php echo $__sb_ver('ems-tables.css'); ?>',
      '/ems/assets/css/ems-forms.css<?php echo $__sb_ver('ems-forms.css'); ?>',
      // أنماطُ الأزرارِ الأربعةُ — بعد بوتستراب لتَغلبَ أزرقَه بذهبيِّ العلامة
      '/ems/assets/css/ems-buttons.css<?php echo $__sb_ver('ems-buttons.css'); ?>',
      '/ems/assets/css/ems-nav-groups.css<?php echo $__sb_ver('ems-nav-groups.css'); ?>',
      // واجهةُ الجوّال — آخرُ الملفات كي تفوز على طبقات التجاوز المتراكمة،
      // وكلُّ ما فيها محبوسٌ في @media (max-width:768px) فلا تمسّ الحاسوب.
      '/ems/assets/css/ems-sidebar-mobile.css<?php echo $__sb_ver('ems-sidebar-mobile.css'); ?>',
      '/ems/assets/css/ems-topbar-mobile.css<?php echo $__sb_ver('ems-topbar-mobile.css'); ?>'
    ];

    cssFiles.forEach(function (href) {
      if (document.querySelector('link[rel="stylesheet"][href="' + href + '"]')) return;
      var link = document.createElement('link');
      link.rel = 'stylesheet';
      link.href = href;
      head.appendChild(link);
    });

    if (!document.querySelector('script[src^="/ems/assets/js/ui-unification.js"]')) {
      var script = document.createElement('script');
      script.src = '/ems/assets/js/ui-unification.js<?php $__uijs=__DIR__.'/assets/js/ui-unification.js'; echo is_file($__uijs)?('?v='.filemtime($__uijs)):''; ?>';
      script.defer = true;
      head.appendChild(script);
    }

    if (!document.querySelector('script[src^="/ems/assets/js/ems-select.js"]')) {
      var emsSelectScript = document.createElement('script');
      emsSelectScript.src = '/ems/assets/js/ems-select.js';
      emsSelectScript.defer = true;
      head.appendChild(emsSelectScript);
    }

    if (document.body) {
      document.body.classList.add('ems-site');
    } else {
      document.addEventListener('DOMContentLoaded', function () {
        document.body.classList.add('ems-site');
      }, { once: true });
    }
  })();
</script>

<!-- الهيدر/التوببار المشترك (مكوّن واحد لكل الصفحات التي بها Sidebar) -->
<?php require_once __DIR__ . '/includes/topbar.php'; ?>

<!-- زرُّ القائمة في الموبايل: انتقل إلى داخل الشريط العلويّ (includes/topbar.php)
     ليحجز مكانَه في صفّه بدل أن يطفو فوق أيقونات الإجراءات. المعرّفُ نفسُه
     `mobileMenuBtn`، وهو مطبوعٌ قبل هذا الموضع فيبقى سكربتُ الأسفل يجده. -->

<!-- طبقة الخلفية المعتمة (للموبايل) -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<div class="sidebar closed" id="sidebar">
  <?php
  /* رأسُ اللوح على الجوّال (NAV — واجهة الجوّال): هويّةٌ + مَن أنت + إغلاقٌ صريح.
     مخفيٌّ افتراضًا (display:none في ems.main.all.style.css؟ لا — هنا صراحةً)،
     ويظهر داخل @media (max-width:768px) وحدَه في ems-sidebar-mobile.css.
     اسمُ الإدارة من خبيئة الجلسة التي يملؤها includes/topbar.php أعلاه — بلا
     استعلامٍ إضافي، وبلا كسرٍ إن غاب. */
  $__sb_mrole = isset($_SESSION['ems_topbar_role_label']['text'])
              ? (string) $_SESSION['ems_topbar_role_label']['text'] : '';
  $__sb_micon = function_exists('ems_url') ? ems_url('assets/images/icon.png') : '/ems/assets/images/icon.png';
  ?>
  <div class="sidebar-mobile-head" id="sidebarMobileHead">
    <img src="<?php echo htmlspecialchars($__sb_micon, ENT_QUOTES, 'UTF-8'); ?>" alt="" aria-hidden="true">
    <span class="sidebar-mobile-id">
      <span class="sidebar-mobile-brand">إيكوبيشن</span>
      <?php if ($__sb_mrole !== ''): ?>
        <span class="sidebar-mobile-role"><?php echo htmlspecialchars($__sb_mrole, ENT_QUOTES, 'UTF-8'); ?></span>
      <?php endif; ?>
    </span>
    <button type="button" class="sidebar-mobile-close" id="sidebarMobileClose" aria-label="إغلاق القائمة">
      <i class="fa fa-xmark" aria-hidden="true"></i>
    </button>
  </div>
  <div>
    <div class="toggle-btn" id="toggleBtn"><i class="fa fa-bars"></i></div>
    <!-- <h2 class="logo">Equipation</h2> -->

    <?php /* AS-03 (UXR-0035): حالتا السايدبار على toggle-btn القائم — القياسات
             264/68 في ems-shell.css، وهنا ثباتُ الاختيار عبر الصفحات. */ ?>
    <script>
    (function () {
      try {
        var sb = document.getElementById('sidebar');
        if (!sb || window.matchMedia('(max-width: 768px)').matches) return;
        var saved = localStorage.getItem('ems.nav.closed');
        if (saved === '0') { sb.classList.remove('closed'); }
        var tb = document.getElementById('toggleBtn');
        if (tb) {
          tb.addEventListener('click', function () {
            // القراءة بعد معالِج التبديل نفسه (مسجَّل لاحقًا في هذا الملف)
            setTimeout(function () {
              try { localStorage.setItem('ems.nav.closed', sb.classList.contains('closed') ? '1' : '0'); } catch (e) {}
            }, 0);
          });
        }
      } catch (e) {}
    })();
    </script>

    <?php
    $hoursApprovalPendingCount = 0;
    if (isset($_SESSION['user']) && isset($_SESSION['user']['role']) && isset($conn)) {
      $sb_role         = strval($_SESSION['user']['role']);
      $sb_company_id   = intval($_SESSION['user']['company_id'] ?? 0);
      $sb_session_proj = intval($_SESSION['user']['project_id'] ?? 0);

      $sb_allowed_roles = ['-1', '1', '2', '3', '4', '5'];
      if (in_array($sb_role, $sb_allowed_roles)) {
        $sb_cache_key = implode('|', [$sb_role, $sb_company_id, $sb_session_proj]);
        $sb_cache_ttl = 60;
        $sb_cache_ok  = false;
        if (isset($_SESSION['hours_approval_pending_cache']) && is_array($_SESSION['hours_approval_pending_cache'])) {
          $sb_cache = $_SESSION['hours_approval_pending_cache'];
          if (
            isset($sb_cache['key'], $sb_cache['value'], $sb_cache['ts']) &&
            $sb_cache['key'] === $sb_cache_key &&
            (time() - intval($sb_cache['ts'])) <= $sb_cache_ttl
          ) {
            $hoursApprovalPendingCount = intval($sb_cache['value']);
            $sb_cache_ok = true;
          }
        }

        if (!$sb_cache_ok) {
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
          $_SESSION['hours_approval_pending_cache'] = [
            'key' => $sb_cache_key,
            'value' => $hoursApprovalPendingCount,
            'ts' => time()
          ];
        }
      }
    }
    ?>

    <ul>
      <?php
      /* «الرئيسية» لم تعد رابطًا ثابتًا (2026-07-27): انتقلت صفًّا في باب HOME
         بالمصدر الموحّد `nav_items` لكل الأدوار الـ23 — تحقيقًا لقاعدة الدستور
         §6 «مصدرٌ واحدٌ محكوم»، ولتفتح لوحةَ الدور مباشرةً بلا تحويلٍ وسيط
         فيتلوّن رابطُها النشط. والمراسلاتُ تبقى ثابتةً بقرار المالك حتى جولةِ
         تصفية السايدبار — وتُحقن بعد باب HOME لا قبله، فتبقى «الرئيسية» أولَ
         ما يُرى (§6) ولا تهبط المراسلاتُ إلى ذيل القائمة. */
      $__sb_chats_li = '<li>'
        . '<a href="../chats/index.php" id="sidebarChatLink">'
        . '<i class="fa fa-comments"></i>'
        . '<span class="sidebar-link-text">المراسلات</span>'
        . '<span id="nav-unread-badge" class="nav-count-badge" style="display:none;"></span>'
        . '</a></li>' . "\n";
      ?>
      <?php
      // عرض الروابط الديناميكية من جدول modules بناءً على دور المستخدم
      // (+ شارات عدّ بوابة الطلبات المالية D05 — صناديق المراجعة والمحاسب والمعاد)
      if ($__nav_unified && isset($conn)) {
        // ── المصدر الموحّد: بابٌ واحدٌ محكوم بدل المصادر الخمسة ──
        require_once __DIR__ . '/includes/finreq_badges.php';
        /* E-20: الشاراتُ المعمَّمةُ من `nav_items.counter_source` **مع** المثبَّتة —
           العمودُ كان يُكتَب ولا يُقرأ، فلا شارةَ تظهر لأيِّ صفٍّ يحمل مفتاحًا. */
        $__un_badges = ems_nav_all_badges($conn, $_SESSION['user']['role']); // مفاتيحها أكواد الشاشات = المسارات
        if ($hoursApprovalPendingCount > 0) {
          $__un_badges['Approvals/hours_approval.php'] = $hoursApprovalPendingCount;
        }
        // المصيِّر يعيد false لدورٍ بلا عناصرَ أصلًا — فلا تُفقد المراسلات حينها
        if (!renderUnifiedNavigationV2($conn, $_SESSION['user']['role'], '../', $__un_badges, $__sb_chats_li)) {
          echo $__sb_chats_li;
        }
      } elseif (isset($_SESSION['user']) && isset($_SESSION['user']['role']) && isset($conn)) {
        // المسارُ القديم (السوبر ومن خارج العلم): المراسلاتُ ثابتةٌ في صدر القائمة
        echo $__sb_chats_li;
        require_once __DIR__ . '/includes/finreq_badges.php';
        $__fr_badges = ems_nav_all_badges($conn, $_SESSION['user']['role']);
        $__sb_links  = getDynamicNavLinks($conn, $_SESSION['user']['role']);
        $__sb_groups = getNavGroups($conn, $_SESSION['user']['role']);
        $__sb_tree   = buildNavTree($__sb_links, $__sb_groups);

        // ما طبعته الشجرة يُستثنى مما يليها منعًا للتكرار.
        $__fr_dyn = array();
        foreach ($__sb_links as $__l) {
          if (isset($__l['code'])) { $__fr_dyn[$__l['code']] = true; }
        }

        // بوابة الطلبات المالية (D05): الروابط تُشتق من جدول التوجيه المفعّل لا من
        // مالك الوحدة الواحد — فيراها كل دورٍ له طريقٌ في أي إدارةٍ مفعّلة. ولأنها
        // ليست صفوفًا في modules فلا group_id لها: تُجمع في مجموعةٍ اصطناعية ثابتة.
        $__fr_group_links = array();
        foreach (ems_finreq_nav_links($conn) as $__code => $__meta) {
          if (isset($__fr_dyn[$__code])) { continue; }
          $__fr_group_links[] = array('code' => $__code, 'name' => $__meta['label'], 'icon' => $__meta['icon']);
          $__fr_dyn[$__code] = true;
        }
        $__fr_node = makeNavGroupNode('finreq', 'بوابة الطلبات المالية', 'fa fa-file-invoice-dollar', 9000, $__fr_group_links);
        if ($__fr_node !== null) { $__sb_tree[] = $__fr_node; }

        // شاشات المالية الممنوحة عرضًا للأدوار التشغيلية (من role_permissions لا
        // الملكية): صفّها مملوكٌ للمالية فمجموعتُه مجموعةُ المالية — لذلك تُعرض
        // للدور المستعير تحت مجموعةٍ محايدةٍ واحدة بدل اسمٍ لا يخصّه.
        $__fin_group_links = array();
        if (function_exists('ems_finance_nav_links')) {
          foreach (ems_finance_nav_links($conn) as $__code => $__meta) {
            if (isset($__fr_dyn[$__code])) { continue; }
            $__fin_group_links[] = array('code' => $__code, 'name' => $__meta['label'], 'icon' => $__meta['icon']);
            $__fr_dyn[$__code] = true;
          }
        }
        $__fin_node = makeNavGroupNode('finshared', 'شاشات مالية', 'fa fa-coins', 9001, $__fin_group_links);
        if ($__fin_node !== null) { $__sb_tree[] = $__fin_node; }

        printNavTree($__sb_tree, '../', $__fr_badges);
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
        <li><a href="../Employees/employees.php"><i class="fa fa-id-card"></i> <span>المشغلين</span></a></li>
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

      <?php if (!$__nav_unified && in_array($_SESSION['user']['role'], ["-1", "1", "2", "3", "4", "5"])) { ?>
      <li><a href="../Approvals/hours_approval.php"><i class="fa fa-check-double"></i> <span>اعتماد الوحدات التشغيلية
        </span>
        <?php if ($hoursApprovalPendingCount > 0): ?>
        <span class="nav-count-badge"><?php echo ($hoursApprovalPendingCount > 99 ? '99+' : $hoursApprovalPendingCount); ?></span>
        <?php endif; ?>
      </a></li>
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

      <?php // صلاحيات إدارة الأسطول === 3 (Fleet)
      if ($_SESSION['user']['role'] == "3") { ?>
        <!-- <li><a href="../Equipments/equipments.php"><i class="fa fa-tractor"></i> <span>الآليات</span></a></li> -->
        <!-- <li><a href="../Employees/employees.php"><i class="fa fa-id-card"></i> <span>المشغلين</span></a></li>
        <li><a href="../Reports/reports.php"><i class="fa fa-chart-pie"></i> <span>التقارير</span></a></li>
        <li><a href="../Approvals/requests.php"><i class="fa fa-check-double"></i> <span>طلبات الموافقات</span></a></li> -->

      <?php } ?>

      <?php // صلاحيات إدارة الموارد البشرية === 4 (HR)
      // شاشات طبقة القوى التشغيلية (Workforce/) تظهر ديناميكياً من جدول modules عبر
      // renderDynamicNavigation أعلاه — لا تُضاف يدوياً هنا (السلوك يقوده جدول الشاشات).
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

      <?php
      // رابط «التقارير» الذكي: يحترم الصلاحيات القائمة فلا يقود لصفحة مرفوضة.
      //   • من يملك نظام التقارير الجديد (report_role_permissions) → emsreports.
      //   • وإلا من يملك عرض التقارير القديمة (Reports/reports.php) → Reports/reports.php.
      //   • وإلا يُخفى الرابط. (لا يُمنح أي وصول جديد هنا.)
      // الموحّد: التقاريرُ والإعداداتُ من بابيهما في المصدر الموحّد لا من هنا —
      // (الرابط الثابت للإعدادات كان يظهر بلا فحص صلاحيةٍ لكل الأدوار، وهو
      //  مصدرُ الرابط الميت المرصود في v8 §16-و).
      if (!$__nav_unified) {
      $__sb_role_id = isset($_SESSION['user']['role']) ? intval($_SESSION['user']['role']) : 0;
      $__sb_is_super = isset($_SESSION['user']['role']) && strval($_SESSION['user']['role']) === '-1';
      $__sb_has_emsreports = $__sb_is_super;
      if (!$__sb_has_emsreports && isset($conn) && $__sb_role_id !== 0) {
        $__sb_rr = @mysqli_query($conn, "SELECT 1 FROM report_role_permissions WHERE role_id = " . $__sb_role_id . " LIMIT 1");
        $__sb_has_emsreports = ($__sb_rr && mysqli_num_rows($__sb_rr) > 0);
      }
      if ($__sb_has_emsreports) {
        echo '<li><a href="../emsreports/index.php"><i class="fas fa-chart-pie"></i> <span>التقارير</span></a></li>';
      } else {
        $__sb_old = function_exists('check_page_permissions') ? check_page_permissions($conn, 'Reports/reports.php') : array('can_view' => false);
        if ($__sb_is_super || !empty($__sb_old['can_view'])) {
          echo '<li><a href="../Reports/reports.php"><i class="fas fa-chart-pie"></i> <span>مركز التقارير</span></a></li>';
        }
      }
      echo '<li><a href="../Settings/settings.php"><i class="fa fa-cog"></i> <span>الإعدادات</span></a></li>';
      }
      ?>


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
      <li><a href="../Employees/employees.php"><i class="fa fa-id-card"></i> <span>المشغلين</span></a></li>
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
      <li><a href="../Employees/employees.php"><i class="fa fa-id-card"></i> <span>المشغلين</span></a></li>
      <li><a href="project_users.php"><i class="fa fa-users-cog"></i> <span> مستخدمين المدير </span></a></li>
       <?php } ?> -->
    </ul>
  </div>

  <!-- زر تسجيل الخروج -->
  <!-- <a href="../logout.php" class="logout">
    <i class="fa fa-sign-out-alt"></i>
    <span>تسجيل الخروج</span>
  </a> -->
</div>

<style>
/* الصفحة الحالية في الشريط الجانبي — لون الأيقونة والاسم #E0AE2E (عدا عدّاد الرقم) */
.ems-site .sidebar ul li.active > a,
.ems-site .sidebar ul li.active > a .sidebar-link-text,
.ems-site .sidebar ul li.active > a span:not(.nav-count-badge) { color: #E0AE2E !important; }
.ems-site .sidebar ul li.active > a i { color: #E0AE2E !important; }
/* عدّاد الرقم (الرسائل/الاعتمادات) يبقى بلونٍ ثابت (عادي + hover + active) كي يظل ظاهراً.
   SH-08/4: كان أبيضَ على البرتقاليِّ = 2.3:1 — أسوأُ زوجٍ في الشجرة، ونصُّه رقمٌ
   يُقرأ لا زخرفة. والبرتقاليُّ لونُ هُويةٍ لا يُمَسّ، فتغيّر الحبرُ إلى الداكن:
   8.9:1. ويبقى `!important` لأن قواعدَ الحالةِ أعلاه تلوّن كلَّ span في الصف. */
.ems-site .sidebar ul li .nav-count-badge,
.ems-site .sidebar ul li:hover .nav-count-badge,
.ems-site .sidebar ul li.active .nav-count-badge { color: #1f1f1f !important; }
</style>

<script>
  // تحديد الصفحة المفتوحة حالياً في الشريط الجانبي (تلوين أيقونتها واسمها)
  (function () {
    var sb = document.getElementById('sidebar');
    if (sb) {
      var current = (window.location.pathname || '').replace(/\/+$/, '').toLowerCase();
      sb.querySelectorAll('li a[href]').forEach(function (a) {
        var p = (a.pathname || '').replace(/\/+$/, '').toLowerCase();
        if (p && p === current) {
          var li = a.closest('li');
          if (li) li.classList.add('active');
          // المجموعة الحاوية تتلوّن ذهبيًّا وهي مطويّة — الافتراض أن الكل مطويّ،
          // ولولا ذلك لَما عرف المستخدم موضعه.
          var grp = a.closest('li.nav-group');
          if (grp) grp.classList.add('has-active');
        }
      });
    }
  })();

  /* ═════════════════════════════════════════════════════════════════════════
   * مجموعات الروابط: الطيّ، وحفظ الحالة، وقيادة بلاطات الوصول السريع
   * ─────────────────────────────────────────────────────────────────────────
   * الافتراض عند أول فتح: كل المجموعات مطويّة (قرار المستخدم). ثم تُحفظ
   * اختيارات الطيّ في localStorage فتصمد بين الصفحات.
   *
   * «المجموعة المختارة» تقود بلاطات الوصول السريع في لوحة التحكم: افتراضها
   * أول مجموعة، وتتغيّر بنقر رأس أي مجموعة — يُبثّ ذلك حدثًا تلتقطه اللوحة.
   * ═════════════════════════════════════════════════════════════════════════ */
  (function () {
    var sb = document.getElementById('sidebar');
    if (!sb) return;

    var groups = Array.prototype.slice.call(sb.querySelectorAll('li.nav-group'));
    if (!groups.length) return;

    var OPEN_KEY = 'ems.navGroups.open';
    var SEL_KEY  = 'ems.navGroups.selected';

    function readOpen() {
      try {
        var raw = window.localStorage.getItem(OPEN_KEY);
        var arr = raw ? JSON.parse(raw) : [];
        return Array.isArray(arr) ? arr : [];
      } catch (e) { return []; }
    }

    function writeOpen(keys) {
      try { window.localStorage.setItem(OPEN_KEY, JSON.stringify(keys)); } catch (e) {}
    }

    function currentOpenKeys() {
      return groups.filter(function (g) { return g.classList.contains('open'); })
                   .map(function (g) { return g.getAttribute('data-group-key'); });
    }

    function setSelected(key, broadcast) {
      groups.forEach(function (g) {
        g.classList.toggle('is-selected', g.getAttribute('data-group-key') === key);
      });
      try { window.localStorage.setItem(SEL_KEY, key); } catch (e) {}
      if (broadcast) {
        document.dispatchEvent(new CustomEvent('ems:navgroup-selected', { detail: { key: key } }));
      }
    }

    function toggleGroup(g) {
      var head = g.querySelector('.nav-group-head');
      var open = !g.classList.contains('open');
      // أكورديون (قرار المالك 2026-08-02): مجموعةٌ واحدةٌ مفتوحةٌ لا غير —
      // فتحُ واحدةٍ يطوي سواها، والقائمةُ تبقى بسيطةً مهما طالت.
      if (open) {
        groups.forEach(function (other) {
          if (other === g) return;
          if (other.classList.contains('open')) {
            other.classList.remove('open');
            var oh = other.querySelector('.nav-group-head');
            if (oh) oh.setAttribute('aria-expanded', 'false');
          }
        });
      }
      g.classList.toggle('open', open);
      if (head) head.setAttribute('aria-expanded', open ? 'true' : 'false');
      writeOpen(currentOpenKeys());
      return open;
    }

    // الاسترجاع (قرار المالك 2026-08-02): أولُ زيارةٍ = الكلُّ مطويٌّ ·
    // والعائدُ يجد آخرَ مجموعةٍ فتحها وحدَها (أكورديون — واحدةٌ على الأكثر)
    /* ═══════════════════════════════════════════════════════════════════════
     * INJ-0527 — أوّلُ زيارةٍ تحترم افتراضَ الخادم · والعائدُ يجد ما تركه
     * ═══════════════════════════════════════════════════════════════════════
     * ◆ **أوّلُ زيارةٍ** (لا شيءَ محفوظ): لا نلمس شيئًا — فالخادمُ يفتح المراحلَ
     *   ٠ و١ و٢ (`unified_nav.php`) وهو حكمُ الملفِّ المكتوب. وكان المستخدمُ
     *   الجديدُ يفتح النظامَ على **كلِّ** المجموعاتِ مطويّةً فلا يرى رابطًا.
     * ◆ **والعائدُ**: يُسترجَع **كلُّ** ما كان مفتوحًا لا آخرُه وحدَه، و**يُغلَق
     *   ما فتحه الخادمُ ولم يكن في المحفوظ** — وإلا عادت مرحلةٌ طواها المستخدمُ
     *   مفتوحةً في كلِّ زيارة، وهو أسوأُ من ألّا نحفظ.
     * ◆ ولا يُمَسُّ سلوكُ النقرِ (أكورديون بقرارِ المالك 2026-08-02) — هذا
     *   **الافتراضُ والاسترجاعُ** لا التفاعل.
     * ═══════════════════════════════════════════════════════════════════════ */
    var saved = readOpen();
    if (saved.length) {
      groups.forEach(function (g) {
        var k = g.getAttribute('data-group-key');
        var want = saved.indexOf(k) !== -1;
        g.classList.toggle('open', want);
        var h = g.querySelector('.nav-group-head');
        if (h) h.setAttribute('aria-expanded', want ? 'true' : 'false');
      });
    }

    // المجموعة المختارة: المحفوظة إن كانت لا تزال مرئيّة، وإلا فأوّل مجموعة.
    var savedSel = null;
    try { savedSel = window.localStorage.getItem(SEL_KEY); } catch (e) {}
    var keys = groups.map(function (g) { return g.getAttribute('data-group-key'); });
    setSelected((savedSel && keys.indexOf(savedSel) !== -1) ? savedSel : keys[0], false);

    groups.forEach(function (g) {
      var head = g.querySelector('.nav-group-head');
      if (!head) return;
      head.addEventListener('click', function () {
        // في الوضع المطويّ (شريط أيقونات) النقرُ يوسّع السايدبار أولًا ثم يفتح.
        if (sb.classList.contains('closed') && !isMobile()) {
          sb.classList.remove('closed');
          if (!g.classList.contains('open')) { toggleGroup(g); }
          refreshPageLayoutAfterAnimation();
        } else {
          toggleGroup(g);
        }
        setSelected(g.getAttribute('data-group-key'), true);
      });
    });
  })();

  const sidebar       = document.getElementById('sidebar');
  const toggleBtn     = document.getElementById('toggleBtn');
  const mobileMenuBtn = document.getElementById('mobileMenuBtn');
  const sidebarOverlay = document.getElementById('sidebarOverlay');
  const SIDEBAR_ANIMATION_MS = 420;
  let layoutRefreshTimer = null;
  let lastLayoutRefreshAt = 0;

  function isMobile() { return window.innerWidth <= 768; }

  function refreshPageLayout() {
    // Recalculate DataTables widths after layout changes.
    // محصَّنة: جدولٌ واحدٌ برأسٍ لا يطابق خلاياه (علة عدّ الأعمدة tn/18) كان
    // يرمي «reading 'style'» فيقتل المعالجَ كلَّه لكل الجداول — الخطأ يُسجَّل ولا يُفشل.
    if (window.jQuery && window.jQuery.fn && window.jQuery.fn.dataTable) {
      try {
        const tablesApi = window.jQuery.fn.dataTable.tables({ visible: true, api: true });
        tablesApi.columns.adjust();
        // إضافةُ Responsive مرفوعة: لا recalc — التمرير الأفقي يتكفّل بالعرض.
      } catch (e) {
        if (window.console && console.debug) { console.debug('refreshPageLayout skipped:', e.message); }
      }
    }
  }

  function refreshPageLayoutAfterAnimation() {
    if (layoutRefreshTimer) {
      clearTimeout(layoutRefreshTimer);
    }
    layoutRefreshTimer = setTimeout(function() {
      layoutRefreshTimer = null;
      refreshPageLayout();
      lastLayoutRefreshAt = Date.now();
    }, SIDEBAR_ANIMATION_MS);
  }

  function openSidebar() {
    sidebar.classList.add('active');
    sidebarOverlay.classList.add('active');
    document.body.style.overflow = 'hidden'; // منع التمرير خلف السايدبار
    // صنفٌ على <body> يقود إخفاءَ الزرّ العائم على الجوّال — الزرّ يسبق
    // السايدبار في الـDOM فلا يصله محدِّدُ الشقيق من حالة اللوح.
    document.body.classList.add('ems-sidebar-open');
    if (mobileMenuBtn) { mobileMenuBtn.setAttribute('aria-expanded', 'true'); }
    refreshPageLayoutAfterAnimation();
  }

  function closeSidebar() {
    sidebar.classList.remove('active');
    sidebarOverlay.classList.remove('active');
    document.body.style.overflow = '';
    document.body.classList.remove('ems-sidebar-open');
    if (mobileMenuBtn) { mobileMenuBtn.setAttribute('aria-expanded', 'false'); }
    refreshPageLayoutAfterAnimation();
  }

  // زر السهم داخل السايدبار (للحاسوب فقط)
  if (toggleBtn) {
    toggleBtn.addEventListener('click', () => {
      if (!isMobile()) {
        sidebar.classList.toggle('closed');
        refreshPageLayoutAfterAnimation();
      }
    });
  }

  // زر الهامبرغر الخارجي (للموبايل)
  if (mobileMenuBtn) {
    mobileMenuBtn.addEventListener('click', () => {
      if (sidebar.classList.contains('active')) {
        closeSidebar();
      } else {
        openSidebar();
      }
    });
  }

  // إغلاق عند النقر على الخلفية المعتمة
  if (sidebarOverlay) {
    sidebarOverlay.addEventListener('click', () => {
      closeSidebar();
    });
  }

  // إغلاق عند الضغط على أي رابط داخل السايدبار (موبايل)
  sidebar.querySelectorAll('a').forEach(link => {
    link.addEventListener('click', () => {
      if (isMobile()) closeSidebar();
    });
  });

  /* ── إغلاقُ لوح الجوّال: زرٌّ صريحٌ في رأسه · Escape · سحبةٌ نحو اليمين ──
     الخلفيةُ المعتمة وحدَها لم تكن كافية: زرُّ الطيّ مخفيٌّ على الهاتف،
     والزرُّ العائم يختفي عند الفتح. كلُّ ما يلي محروسٌ بـisMobile() فلا
     يغيّر حرفًا من سلوك الحاسوب. */
  const sidebarMobileClose = document.getElementById('sidebarMobileClose');
  if (sidebarMobileClose) {
    sidebarMobileClose.addEventListener('click', closeSidebar);
  }

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && sidebar.classList.contains('active')) {
      closeSidebar();
    }
  });

  (function () {
    // سحبةٌ أفقيّةٌ نحو اليمين تغلق اللوح (اتجاه انزلاقه في RTL).
    // العتبةُ 60px، ويُلغى الالتقاطُ إن غلب الميلُ الرأسيُّ — كي لا تُختطف
    // حركةُ تمرير القائمة الطويلة.
    let sx = 0, sy = 0, tracking = false;
    sidebar.addEventListener('touchstart', (e) => {
      if (!isMobile() || !sidebar.classList.contains('active') || e.touches.length !== 1) return;
      sx = e.touches[0].clientX;
      sy = e.touches[0].clientY;
      tracking = true;
    }, { passive: true });

    sidebar.addEventListener('touchmove', (e) => {
      if (!tracking || e.touches.length !== 1) return;
      const dx = e.touches[0].clientX - sx;
      const dy = e.touches[0].clientY - sy;
      if (Math.abs(dy) > Math.abs(dx)) { tracking = false; return; }
      if (dx > 60) { tracking = false; closeSidebar(); }
    }, { passive: true });

    sidebar.addEventListener('touchend', () => { tracking = false; }, { passive: true });
  })();

  // إغلاق عند تغيير حجم الشاشة للكمبيوتر
  window.addEventListener('resize', () => {
    if (!isMobile()) {
      closeSidebar();
    }
    // Prevent resize storms from triggering endless expensive reflows.
    if (Date.now() - lastLayoutRefreshAt > 250) {
      refreshPageLayoutAfterAnimation();
    }
  });

  // ===== شارة الرسائل غير المقروءة =====
  let navBadgeReqInFlight = false;

  function updateChatNavBadge() {
    if (document.hidden || navBadgeReqInFlight) {
      return;
    }
    navBadgeReqInFlight = true;
    var xhr = new XMLHttpRequest();
    xhr.open('GET', '/ems/chats/get_unread_count.php', true);
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    xhr.onload = function() {
      try {
        var data = JSON.parse(xhr.responseText);
        var badge = document.getElementById('nav-unread-badge');
        if (badge) {
          if (data.count > 0) {
            badge.textContent = data.count > 99 ? '99+' : String(data.count);
            badge.style.display = 'inline-flex';
            badge.setAttribute('aria-label', data.count > 99 ? 'أكثر من 99 رسالة غير مقروءة' : (String(data.count) + ' رسائل غير مقروءة'));
          } else {
            badge.style.display = 'none';
            badge.removeAttribute('aria-label');
          }
        }
      } catch(e) {}
      navBadgeReqInFlight = false;
    };
    xhr.onerror = function() { navBadgeReqInFlight = false; };
    xhr.onabort = function() { navBadgeReqInFlight = false; };
    xhr.send();
  }
  updateChatNavBadge();
  document.addEventListener('visibilitychange', function() {
    if (!document.hidden) {
      updateChatNavBadge();
    }
  });
  setInterval(updateChatNavBadge, 60000);

  // Initial pass after page load to ensure any table wrapper starts with correct width.
  refreshPageLayoutAfterAnimation();
</script>
