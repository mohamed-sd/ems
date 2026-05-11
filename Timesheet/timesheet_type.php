<?php
session_start();
if (!isset($_SESSION['user'])) {
  header("Location: ../login.php");
  exit();
}
$page_title = "إيكوبيشن | اختر نوع الآلية";
include("../inheader.php");
include('../insidebar.php');
?>

<div class="main timesheet-type-page ems-unified-page-shell">

  <div class="main_head">

    <div class="head_actions">
      <!-- <a href="timesheet.php?type=1" class="add-btn ts-header-action ts-action-heavy">
        <i class="fas fa-tractor"></i> معدات ثقيلة
      </a>
      <a href="timesheet.php?type=2" class="add-btn ts-header-action ts-action-truck">
        <i class="fas fa-truck"></i> الشاحنات
      </a>
      <a href="timesheet.php?type=3" class="add-btn ts-header-action ts-action-drill">
        <i class="fas fa-hammer"></i> الخرامات
      </a> -->
    </div>

    <h1 class="head-title">
      <span class="title-icon"><i class="fas fa-clock"></i></span>
      اختيار نوع الآلية
    <i class="fas fa-layer-group"></i>
    اختر التصنيف المناسب للبدء في إدخال ساعات العمل
    </h1>

    <div class="head_back">
      <a href="../main/dashboard.php" class="">
        <i class="fas fa-arrow-right"></i> رجوع
      </a>
    </div>
  </div>

  <div class="ts-type-cards">
    <a href="timesheet.php?type=1" class="ts-type-card ts-type-heavy">
      <div class="ts-type-card-inner">
        <div class="ts-type-card-icon"><i class="fas fa-tractor"></i></div>
        <div class="ts-type-card-label">معدات ثقيلة</div>
        <div class="ts-type-card-desc">حفارات، لودرات، جريدرات وجميع المعدات الثقيلة</div>
        <span class="ts-type-card-arrow"><i class="fas fa-arrow-left"></i> ابدأ الإدخال</span>
      </div>
    </a>

    <a href="timesheet.php?type=2" class="ts-type-card ts-type-truck">
      <div class="ts-type-card-inner">
        <div class="ts-type-card-icon"><i class="fas fa-truck"></i></div>
        <div class="ts-type-card-label">الشاحنات</div>
        <div class="ts-type-card-desc">قلابات، شاحنات نقل وجميع مركبات النقل</div>
        <span class="ts-type-card-arrow"><i class="fas fa-arrow-left"></i> ابدأ الإدخال</span>
      </div>
    </a>

    <a href="timesheet.php?type=3" class="ts-type-card ts-type-drill">
      <div class="ts-type-card-inner">
        <div class="ts-type-card-icon"><i class="fas fa-hammer"></i></div>
        <div class="ts-type-card-label">الخرامات</div>
        <div class="ts-type-card-desc">آلات حفر، آلات ثقب وجميع معدات الحفر العمودي</div>
        <span class="ts-type-card-arrow"><i class="fas fa-arrow-left"></i> ابدأ الإدخال</span>
      </div>
    </a>
  </div>
</div>

</body>

</html>
