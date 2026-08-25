<?php
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
  header("Location: ../login.php");
  exit();
}
/* ── حارسُ الشاشة (B2) — الشاشةُ مسجَّلةٌ في `modules` وكانت تُفتح لأيِّ
   مستخدمٍ مسجَّلِ الدخول لأنها لا تنادي الحارس. والتسجيلُ لا يحرس: الحارسُ
   نداءٌ لا صفة. وموضعُه هنا قبلَ أيِّ تصييرٍ — فرفضٌ بعدَ خروجِ الرأسِ ليس رفضًا. */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/permissions_helper.php';
enforce_current_page_view_permission($conn, '../main/dashboard.php');
?>
<?php
/* AC-U1 · SH-01 — قشرةٌ واحدةٌ: كان هنا رأسٌ محليٌّ كاملٌ بـ<!DOCTYPE>
   و<head> وقائمةِ أنماطٍ خاصة. صار `inheader.php` مصدرَ القشرةِ، فيصل
   هذه الشاشةَ كلُّ تحسينٍ فيها (كاسرُ الذاكرةِ · الرموزُ · الأزرار).
   وما تنفرد به من أنماطٍ منقولٌ أدناه ولم يُنزع. */
$page_title = 'إيكوبيشن | تفاصيل العقد';
include __DIR__ . '/../inheader.php';
?>
<!-- أنماطٌ تنفرد بها هذه الشاشة (لا يحمّلها inheader) -->
<link rel="stylesheet" type="text/css" href="../assets/css/style.css"/>
<link rel="stylesheet" href="../assets/css/main_admin_style.css" />


<?php 
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include('../insidebar.php'); ?>
<?php require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); } ?>

<div class="main">

    <style>
      .sup-shc-head-row { display: flex; align-items: center; gap: 12px; }
    </style>
    <div class="header">
        <div class="sup-shc-head-row">
            <div class="title-icon"><i class="fas fa-file-contract"></i></div>
            <?php
/* AS-04/AS-05 (UXR-01): رأسُ الصفحةِ الموحَّدُ بدلَ العنوانِ اليدويّ. */
$header_icon = 'fas fa-circle';
$header_title_html = htmlspecialchars('تفاصيل عقد المورد', ENT_QUOTES, 'UTF-8');
$header_actions = array();
$header_back = false;
include __DIR__ . '/../includes/page_header.php';
// UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
echo ems_states_bundle('لا تفاصيل لهذا العقد — العقد غير موجود أو خارج نطاق شركتك', 'ارجع إلى سجل عقود الموردين واختر عقدا قائما');
?>
        </div>
        <a href="javascript:history.back()" class="back-btn">
            <i class="fas fa-arrow-right"></i> رجوع
        </a>
    </div>

<?php
// insidebar.php أعلاه حمّل config.php بـ require_once؛ فـ include عارٍ هنا يُعيد
// تنفيذ الملف ويُسقط الصفحة بـ«Cannot redeclare ems_table_has_column_raw».
require_once __DIR__ . '/../config.php';

$contract_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// H-20: المعرّفُ عقدُ موردٍ — يُحلّ إلى موردِه ويُفرض نطاقُ المشرف (403 مسجَّلة)
require_once __DIR__ . '/../app/Services/Portal/SupplierPortalGuard.php';
\App\Services\Portal\SupplierPortalGuard::enforce(
    $conn, $_SESSION['user'],
    \App\Services\Portal\SupplierPortalGuard::supplierOfContract($conn, $contract_id) ?? 0,
    'Suppliers/showcontractsuppliers.php');

// العزل عبر البوابة (الأصل كان بمعرّفٍ فقط — بلا عزل شركة)
try {
    $scs_rows = ems_tenant_db()->scopedQuery(array(
        'scope' => array('sc' => 'supplierscontracts'),
    ), "SELECT
            sc.id, sc.supplier_id, sc.contract_signing_date, sc.grace_period_days, sc.contract_duration_months,
            sc.actual_start, sc.actual_end, sc.transportation, sc.accommodation, sc.place_for_living,
            sc.workshop, sc.equip_type, sc.equip_size, sc.equip_count, sc.equip_target_per_month,
            sc.equip_total_month, sc.equip_total_contract, sc.mach_type, sc.mach_size, sc.mach_count,
            sc.mach_target_per_month, sc.mach_total_month, sc.mach_total_contract,
            sc.hours_monthly_target, sc.forecasted_contracted_hours, sc.created_at, sc.updated_at,
            sc.daily_work_hours, sc.daily_operators, sc.first_party, sc.second_party,
            sc.witness_one, sc.witness_two, sc.project_id
        FROM supplierscontracts sc
        WHERE {TENANT_SCOPE} AND sc.id = ?
        LIMIT 1", array($contract_id));
} catch (\Throwable $t) { $scs_rows = array(); }

foreach ($scs_rows as $row) {
?>
    <div class="report">

        <div class="row mb-2">
            <div class="col-lg-2 col-5">المشروع</div>
            <div class="col-lg-4 col-7"><?php echo $row['project_id']; ?></div>
            <div class="col-lg-2 col-5">المورد</div>
            <div class="col-lg-4 col-7"><?php echo $row['supplier_id']; ?></div>
            <div class="col-lg-2 col-5">تاريخ توقيع العقد</div>
            <div class="col-lg-4 col-7"><?php echo $row['contract_signing_date']; ?></div>
            <div class="col-lg-2 col-5">فترة السماح (أيام)</div>
            <div class="col-lg-4 col-7"><?php echo $row['grace_period_days']; ?></div>
            <div class="col-lg-2 col-5">مدة العقد (شهور)</div>
            <div class="col-lg-4 col-7"><?php echo $row['contract_duration_months']; ?></div>
            <div class="col-lg-2 col-5">تاريخ البدء الفعلي</div>
            <div class="col-lg-4 col-7"><?php echo $row['actual_start']; ?></div>
            <div class="col-lg-2 col-5">تاريخ الانتهاء الفعلي</div>
            <div class="col-lg-4 col-7"><?php echo $row['actual_end']; ?></div>
            <div class="col-lg-2 col-5">النقل</div>
            <div class="col-lg-4 col-7"><?php echo $row['transportation']; ?></div>
            <div class="col-lg-2 col-5">السكن</div>
            <div class="col-lg-4 col-7"><?php echo $row['accommodation']; ?></div>
            <div class="col-lg-2 col-5">مكان السكن</div>
            <div class="col-lg-4 col-7"><?php echo $row['place_for_living']; ?></div>
            <div class="col-lg-2 col-5">الورشة</div>
            <div class="col-lg-4 col-7"><?php echo $row['workshop']; ?></div>
            <div class="col-lg-2 col-5">نوع المعدات</div>
            <div class="col-lg-4 col-7"><?php echo $row['equip_type']; ?></div>
            <div class="col-lg-2 col-5">حجم المعدات</div>
            <div class="col-lg-4 col-7"><?php echo $row['equip_size']; ?></div>
            <div class="col-lg-2 col-5">عدد المعدات</div>
            <div class="col-lg-4 col-7"><?php echo $row['equip_count']; ?></div>
            <div class="col-lg-2 col-5">هدف المعدات شهريا</div>
            <div class="col-lg-4 col-7"><?php echo $row['equip_target_per_month']; ?></div>
            <div class="col-lg-2 col-5">إجمالي المعدات شهريا</div>
            <div class="col-lg-4 col-7"><?php echo $row['equip_total_month']; ?></div>
            <div class="col-lg-2 col-5">إجمالي العقد للمعدات</div>
            <div class="col-lg-4 col-7"><?php echo $row['equip_total_contract']; ?></div>
            <div class="col-lg-2 col-5">نوع الآلية</div>
            <div class="col-lg-4 col-7"><?php echo $row['mach_type']; ?></div>
            <div class="col-lg-2 col-5">حجم الآلية</div>
            <div class="col-lg-4 col-7"><?php echo $row['mach_size']; ?></div>
            <div class="col-lg-2 col-5">عدد الآليات</div>
            <div class="col-lg-4 col-7"><?php echo $row['mach_count']; ?></div>
            <div class="col-lg-2 col-5">هدف الآليات شهريا</div>
            <div class="col-lg-4 col-7"><?php echo $row['mach_target_per_month']; ?></div>
            <div class="col-lg-2 col-5">إجمالي الآليات شهريا</div>
            <div class="col-lg-4 col-7"><?php echo $row['mach_total_month']; ?></div>
            <div class="col-lg-2 col-5">إجمالي العقد للآليات</div>
            <div class="col-lg-4 col-7"><?php echo $row['mach_total_contract']; ?></div>
            <div class="col-lg-2 col-5">الهدف الشهري للساعات</div>
            <div class="col-lg-4 col-7"><?php echo $row['hours_monthly_target']; ?></div>
            <div class="col-lg-2 col-5">الساعات التعاقدية المتوقعة</div>
            <div class="col-lg-4 col-7"><?php echo $row['forecasted_contracted_hours']; ?></div>
            <div class="col-lg-2 col-5">تاريخ الإنشاء</div>
            <div class="col-lg-4 col-7"><?php echo $row['created_at']; ?></div>
            <div class="col-lg-2 col-5">آخر تحديث</div>
            <div class="col-lg-4 col-7"><?php echo $row['updated_at']; ?></div>
            <div class="col-lg-2 col-5">عدد ساعات العمل اليومية</div>
            <div class="col-lg-4 col-7"><?php echo $row['daily_work_hours']; ?></div>
            <div class="col-lg-2 col-5">عدد المشغلين للساعات اليومية</div>
            <div class="col-lg-4 col-7"><?php echo $row['daily_operators']; ?></div>
            <div class="col-lg-2 col-5">الطرف الأول (ممثل الشركة)</div>
            <div class="col-lg-4 col-7"><?php echo $row['first_party']; ?></div>
            <div class="col-lg-2 col-5">الطرف الثاني (ممثل العميل)</div>
            <div class="col-lg-4 col-7"><?php echo $row['second_party']; ?></div>
            <div class="col-lg-2 col-5">الشاهد الأول</div>
            <div class="col-lg-4 col-7"><?php echo $row['witness_one']; ?></div>
            <div class="col-lg-2 col-5">الشاهد الثاني</div>
            <div class="col-lg-4 col-7"><?php echo $row['witness_two']; ?></div>
        </div>

    </div>
<?php } ?>


    <br/><br/><br/>

</div>

</body>
</html>



