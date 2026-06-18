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

  <?php
  // Unified page header (structure: includes/page_header.php · styling: ems.main.all.style.css)
  $header_icon     = 'fas fa-clock';
  $header_icon_tag = 'span';
  $header_title    = 'اختيار نوع الآلية';
  $header_actions  = array();
  $header_back     = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
  include('../includes/page_header.php');
  ?>


  <div class="ts-type-cards">
    <a href="timesheet.php?type=1" class="ts-type-card ts-type-heavy">
      <div class="ts-type-card-inner">
        <div class="ts-type-card-icon"><i class="fas fa-tractor"></i></div>
        <div class="ts-type-card-label">معدات ثقيلة</div>
        <div class="ts-type-card-desc">حفارات، لودرات، جريدرات وجميع المعدات الثقيلة</div>
        <ul class="ts-type-tags">
          <li>حفارات</li>
          <li>لودرات</li>
          <li>جريدرات</li>
        </ul>
        <span class="ts-type-card-arrow">ابدأ الإدخال <i class="fas fa-arrow-left"></i></span>
      </div>
    </a>

    <a href="timesheet.php?type=2" class="ts-type-card ts-type-truck">
      <div class="ts-type-card-inner">
        <div class="ts-type-card-icon"><i class="fas fa-truck"></i></div>
        <div class="ts-type-card-label">الشاحنات</div>
        <div class="ts-type-card-desc">قلابات، شاحنات نقل وجميع مركبات النقل</div>
        <ul class="ts-type-tags">
          <li>قلابات</li>
          <li>شاحنات نقل</li>
          <li>مقطورات</li>
        </ul>
        <span class="ts-type-card-arrow">ابدأ الإدخال <i class="fas fa-arrow-left"></i></span>
      </div>
    </a>

    <a href="timesheet.php?type=3" class="ts-type-card ts-type-drill">
      <div class="ts-type-card-inner">
        <div class="ts-type-card-icon"><i class="fas fa-hammer"></i></div>
        <div class="ts-type-card-label">الخرامات</div>
        <div class="ts-type-card-desc">آلات حفر، آلات ثقب وجميع معدات الحفر العمودي</div>
        <ul class="ts-type-tags">
          <li>آلات حفر</li>
          <li>آلات ثقب</li>
          <li>حفر عمودي</li>
        </ul>
        <span class="ts-type-card-arrow">ابدأ الإدخال <i class="fas fa-arrow-left"></i></span>
      </div>
    </a>
  </div>

  <section class="tst-steps">
    <div class="tst-step">
      <span class="tst-step-num">1</span>
      <div class="tst-step-txt">
        <b>اختر نوع الآلية</b>
        <span>حدّد التصنيف من البطاقات أعلاه</span>
      </div>
    </div>
    <div class="tst-step">
      <span class="tst-step-num">2</span>
      <div class="tst-step-txt">
        <b>أدخل ساعات العمل</b>
        <span>سجّل ساعات التشغيل والتعطل</span>
      </div>
    </div>
    <div class="tst-step">
      <span class="tst-step-num">3</span>
      <div class="tst-step-txt">
        <b>احفظ السجل</b>
        <span>راجع البيانات ثم احفظها</span>
      </div>
    </div>
  </section>
</div>

</body>

</html>
