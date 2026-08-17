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
$page_title = 'إيكوبيشن | تفاصيل المورد';
include __DIR__ . '/../inheader.php';
?>
<!-- أنماطٌ تنفرد بها هذه الشاشة (لا يحمّلها inheader) -->
<link rel="stylesheet" type="text/css" href="../assets/css/style.css" />


    <?php 
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include('../insidebar.php'); ?>
<?php require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); } ?>

    <div class="main">
<?php
/* AS-04/AS-05 (UXR-01): رأسُ الصفحةِ الموحَّدُ — الشاشةُ كانت بلا رأسٍ معلَن. */
$header_icon = 'fas fa-window-maximize';
$header_title_html = htmlspecialchars('Suppliers Details', ENT_QUOTES, 'UTF-8');
$header_actions = array();
$header_back = false;
include __DIR__ . '/../includes/page_header.php';
// UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
echo ems_states_bundle('لا آلياتِ ولا عقودَ مسجَّلةً لهذا المورد', 'أضِف عقدَ موردٍ بزرِّ «عقودات المورد» أعلاه، أو اربطْ آلياتِه من سجلِّ المعدات');
?>
        <style>
            .sup-det-strong { font-weight: 600; }
            .sup-det-hours  { font-weight: 700; color: var(--c-667eea, #667eea); font-size: 1.1rem; }
            .sup-det-table  { width: 100%; margin-top: 20px; }
            .sup-det-th-r   { text-align: right; }
            .sup-det-th-c   { text-align: center; }
            .sup-det-type-a { color: green; }
            .sup-det-type-b { color: red; }
            .sup-det-target { font-weight: 600; color: var(--c-28a745, #28a745); }
            .sup-det-total  { font-weight: 700; color: var(--c-667eea, #667eea); font-size: 1.05rem; }
            .sup-det-view   { color: var(--c-28a745, #28a745); }
        </style>

        <!-- عنوانُ الشاشةِ يأتي من رأسِ الصفحةِ الموحَّد -->
        <div class="aligin">
            <a href="supplierscontracts.php?id=<?php echo $_GET['id']; ?>" id="toggleForm" class="add">
                <i class="fa fa-plus"></i> عقودات المورد
            </a>
        </div>
        <!-- <a href="../Equipments/equipments.php?id=<?php echo $_GET['id']; ?>" id="toggleForm" class="add">
        <i class="fa fa-plus"></i> اضافة آلية
    </a> -->
        <!--  <a href="../Contracts/contracts.php?id=<?php echo $_GET['id']; ?>" id="toggleForm" class="add">
        <i class="fa fa-plus"></i> العقودات
    </a> -->

        <h3> تفاصير المورد : </h3>
        <br />

        <?php
        // insidebar.php أعلاه حمّل config.php بـ require_once؛ فـ include عارٍ هنا يُعيد
        // تنفيذ الملف ويُسقط الصفحة بـ«Cannot redeclare ems_table_has_column_raw».
        require_once __DIR__ . '/../config.php';

        // H-20: جلسةُ مشرف المورد تُقصر على موردها — 403 مسجَّلةٌ لغيره
        require_once __DIR__ . '/../app/Services/Portal/SupplierPortalGuard.php';
        \App\Services\Portal\SupplierPortalGuard::enforce($conn, $_SESSION['user'], intval($_GET['id'] ?? 0), 'Suppliers/suppliers_details.php');

        $project = intval($_GET['id']);

        // العزل عبر البوابة؛ العدّادات المترابطة تُقيَّد بمراسلة suppliers.id المُنطَّق
        // (equipments/supplierscontracts تُعلنان enrich — إعلانٌ بلا تنطيقٍ إضافي، سلوك الأصل)
        try {
            $sup_rows = ems_tenant_db()->scopedQuery(array(
                'scope'  => array('suppliers' => 'suppliers'),
                'enrich' => array('equipments' => 'equipments', 'supplierscontracts' => 'supplierscontracts'),
            ), "SELECT * ,
                  (SELECT COUNT(*) FROM equipments WHERE equipments.suppliers = suppliers.id ) as 'equipments',
                  (SELECT COUNT(*) FROM supplierscontracts WHERE supplierscontracts.supplier_id = suppliers.id ) as 'num_contracts',
                  (SELECT COALESCE(SUM(forecasted_contracted_hours), 0) FROM supplierscontracts WHERE supplierscontracts.supplier_id = suppliers.id ) as 'total_hours'
                  FROM `suppliers` WHERE {TENANT_SCOPE} AND `id` = ? ORDER BY id DESC", array($project));
        } catch (\Throwable $t) { $sup_rows = array(); }
        foreach ($sup_rows as $row) {
            ?>
            <div class="report">
                <div class="row">
                    <div class="col-lg-2 col-5">اسم المورد </div>
                    <div class="col-lg-4 col-7"><?php echo $row['name']; ?></div>
                    <div class="col-lg-2 col-5"> رقم الهاتف </div>
                    <div class="col-lg-4 col-7"><?php echo $row['phone']; ?></div>
                    <div class="col-lg-2 col-5"> عدد الآليات </div>
                    <div class="col-lg-4 col-7"> <?php echo $row['equipments']; ?> </div>
                    <div class="col-lg-2 col-5"> عدد العقود </div>
                    <div class="col-lg-4 col-7 sup-det-strong"> <?php echo $row['num_contracts']; ?> </div>
                    <div class="col-lg-2 col-5"> إجمالي الساعات المتعاقد عليها </div>
                    <div class="col-lg-4 col-7 sup-det-hours"> <?php echo number_format($row['total_hours']); ?> ساعة </div>
                    <div class="col-lg-2 col-5"> الحالة </div>
                    <div class="col-lg-4 col-7"><?php echo $row['status'] == "1" ? "نشط" : "معلق"; ?></div>
                </div>
            </div>
            <?php
        } // end loop
        ?>


        <br /> <br /> <br />

        <!-- جدول المشاريع -->
        <h3> الآليات </h3>
        <br />
        <table id="projectsTable" class="display sup-det-table">
            <thead>
                <tr>
                    <th class="sup-det-th-r">كود المعدة</th>
                    <th class="sup-det-th-r"> الاسم </th>
                    <th class="sup-det-th-r">نوع الآليه</th>
                    <!-- عمودُ اسمِ العميل معطَّلٌ في هذه الشاشة -->
                    <!-- عمودُ الإجراءات معطَّلٌ في هذه الشاشة -->
                </tr>
            </thead>
            <tbody>
                <?php

                // جلب آليات المورد — العزل عبر البوابة
                try {
                    $eq_rows = ems_tenant_db()->scopedQuery(array(
                        'scope' => array('equipments' => 'equipments'),
                    ), "SELECT `id`, `code`, `type`, `name`, `status` FROM `equipments` WHERE {TENANT_SCOPE} AND suppliers = ? ORDER BY id DESC", array($project));
                } catch (\Throwable $t) { $eq_rows = array(); }
                foreach ($eq_rows as $row) {
                    echo "<tr>";
                    echo "<td>" . $row['code'] . "</td>";
                    echo "<td>" . $row['name'] . "</td>";
                    echo $row['type'] == "1" ? "<td class='sup-det-type-a'> حفار </td>" : "<td class='sup-det-type-b'> قلاب </td>";

                    // echo "<td>".$row['status']."</td>";
                    // echo "<td>
                    //         <a href='edit.php?id=".$row['id']."'>تعديل</a> |
                    //         <a href='delete.php?id=".$row['id']."' onclick='return confirm(\"هل أنت متأكد؟\")'>حذف</a> | <a href=''> عرض </a>
                    //       </td>";
                    echo "</tr>";
                }
                ?>
            </tbody>
        </table>

        <br />

         <br />
        <h3> العقود </h3>
        <br />
        <table id="projectsTable1" class="projectsTable sup-det-table">
            <thead>
                <tr>
                    <th class="sup-det-th-c">إجراءات</th>
                    <th>المشروع</th>
                    <th class="sup-det-th-c">تاريخ البداية</th>
                    <th class="sup-det-th-c">المستهدف شهرياً</th>
                    <th class="sup-det-th-c">إجمالي ساعات العقد</th>
                    <th class="sup-det-th-c">الحالة</th>
                    <!-- E-03 موجة ٤: النواة الحاكمة (gov_columns) — الخلايا يحشوها ui-unification.js -->
                    <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
                    <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
                    <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
                    <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
                    <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
                    <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
                    <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
                    </tr>
            </thead>
            <tbody>
                <?php
                require_once __DIR__ . '/../config.php'; // محمَّلٌ سلفًا — التكرار يُسقط الصفحة

                try {
                    $sc_rows = ems_tenant_db()->scopedQuery(array(
                        'scope'  => array('sc' => 'supplierscontracts'),
                        'enrich' => array('op' => 'project'), // اسم المشروع — LEFT بلا تنطيق (سلوك الأصل)
                    ), "SELECT sc.*, op.name as project_name
                        FROM `supplierscontracts` sc
                        LEFT JOIN project op ON sc.project_id = op.id
                        WHERE {TENANT_SCOPE} AND sc.supplier_id = ?
                        ORDER BY sc.id DESC", array($project));
                } catch (\Throwable $t) { $sc_rows = array(); }
                foreach ($sc_rows as $row) {
                     $status = $row['status']=="1" ? "<font color='green'>ساري</font>" : "
                    <font color='red'>منتهي</font>";

                    echo "<tr>";
                    echo "<td><a href='../Contracts/contracts_details.php?id=" . $row['id'] . "' class='sup-det-view'><i class='fa fa-eye'></i></a></td>";
                    echo "<td><strong>" . ($row['project_name'] ?? 'غير محدد') . "</strong></td>";
                    echo "<td>" . $row['contract_signing_date'] . "</td>";
                    echo "<td class='sup-det-target'>" . number_format($row['hours_monthly_target']) . " ساعة</td>";
                    echo "<td class='sup-det-total'>" . number_format($row['forecasted_contracted_hours']) . " ساعة</td>";
                    echo "<td>" . $status . "</td>";
                    // echo "<td>
                    //         <a href='edit.php?id=".$row['id']."'>تعديل</a> | 
                    //         <a href='delete.php?id=".$row['id']."' onclick='return confirm(\"هل أنت متأكد؟\")'>حذف</a> | <a href=''> عرض </a>
                    //       </td>";
                    echo "</tr>";
                }
                ?>
            </tbody>
        </table>




    </div>

    <!-- jQuery -->
    <script src="/ems/assets/vendor/jquery-3.7.1.min.js"></script>
    <!-- DataTables JS -->
    <script src="/ems/assets/vendor/datatables/js/jquery.dataTables.min.js"></script>

    <script>
        (function () {
            /* UXW-01 ⑤: تهيئةُ الجدولَين المحليةُ رُفعت — المكوّنُ المركزيُّ في
               assets/js/ui-unification.js يلتقط الجداولَ آليًّا ويضبط اللغةَ
               العربيةَ من المصدرِ نفسِه (/ems/assets/i18n/datatables/ar.json)،
               فلا سلوكَ ضائعٌ ولا سمةَ ترتيبٍ أو طولِ صفحةٍ كانت معلَنةً هنا. */

            // التحكم في إظهار وإخفاء الفورم
            const toggleProjectFormBtn = document.getElementById('toggleForm');
            const projectForm = document.getElementById('projectForm');

            toggleProjectFormBtn.addEventListener('click', function () {
                projectForm.style.display = projectForm.style.display === "none" ? "block" : "none";
            });
        })();
    </script>

</body>

</html>


