<?php
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}

include("../config.php"); // ملف الاتصال بقاعدة البيانات

// العزل عبر بوابة المستأجر — والسوبر عبر forAllTenants المسجَّل (سلوك الأصل: بلا تنطيق).
$is_super = ((isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '') === '-1');
$nr_gate = $is_super ? ems_tenant_db()->forAllTenants('report super') : ems_tenant_db();

// المشاريع
try { $projects_count = $nr_gate->count('project'); } catch (\Throwable $t) { $projects_count = null; error_log('new_reports projects: ' . $t->getMessage()); }

// الموردين
try { $suppliers_count = $nr_gate->count('suppliers'); } catch (\Throwable $t) { $suppliers_count = null; error_log('new_reports suppliers: ' . $t->getMessage()); }

// الآليات
try { $equipments_count = $nr_gate->count('equipments'); } catch (\Throwable $t) { $equipments_count = null; error_log('new_reports equipments: ' . $t->getMessage()); }

// المشغلين (drivers)
try { $operators_count = $nr_gate->count('employees'); } catch (\Throwable $t) { $operators_count = null; error_log('new_reports operators: ' . $t->getMessage()); }

// المستخدمين
try { $users_count = $nr_gate->count('users'); } catch (\Throwable $t) { $users_count = null; error_log('new_reports users: ' . $t->getMessage()); }

// ساعات العمل (مجموع total_work_hours)
$workhours_count = null;
try {
    $workhours_rows = $nr_gate->scopedQuery(array('scope' => array('timesheet' => 'timesheet')),
        "SELECT SUM(total_work_hours) AS total FROM timesheet WHERE 1=1 AND {TENANT_SCOPE}");
    $workhours_count = $workhours_rows ? ($workhours_rows[0]['total'] ?? null) : null;
} catch (\Throwable $t) { error_log('new_reports workhours: ' . $t->getMessage()); }
if(!$workhours_count) $workhours_count = 0;
?>
<?php
/* AC-U1 · SH-01 — قشرةٌ واحدةٌ: كان هنا رأسٌ محليٌّ كاملٌ بـ<!DOCTYPE>
   و<head> وقائمةِ أنماطٍ خاصة. صار `inheader.php` مصدرَ القشرةِ، فيصل
   هذه الشاشةَ كلُّ تحسينٍ فيها (كاسرُ الذاكرةِ · الرموزُ · الأزرار).
   وما تنفرد به من أنماطٍ منقولٌ أدناه ولم يُنزع. */
$page_title = 'لوحة التقارير';
include __DIR__ . '/../inheader.php';
?>
<!-- أنماطٌ تنفرد بها هذه الشاشة (لا يحمّلها inheader) -->
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


<?php 
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include('../insidebar.php');?> 
<?php require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); } ?>

 <div class="main">
<?php
/* AS-04/AS-05 (UXR-01): رأسُ الصفحةِ الموحَّدُ — الشاشةُ كانت بلا رأسٍ معلَن. */
$header_icon = 'fas fa-window-maximize';
$header_title_html = htmlspecialchars('New Reports', ENT_QUOTES, 'UTF-8');
$header_actions = array();
$header_back = false;
include __DIR__ . '/../includes/page_header.php';
?>


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
  <script src="/ems/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>



