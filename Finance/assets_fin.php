<?php
/**
 * Finance/assets_fin.php — الأصول الثابتة والإهلاك (fin_assets + fin_depreciation).
 * سجلّ الأصول (القيمة الدفترية عمود مولّد) + احتساب إهلاك القسط الثابت شهريًا.
 * شاشة مستقلة — عزل شركة + حذف ناعم. المعدات تُقرأ قراءةً فقط (ربط اختياري).
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/fin_helpers.php';

$ctx = fin_ctx();
$is_super_admin = $ctx['is_super']; $company_id = $ctx['company_id']; $current_user_id = $ctx['user_id'];
if (!$is_super_admin && $company_id <= 0) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد بيئة شركة صالحة ❌', 'GOV-SCOPE-403', ''); exit(); }

$perms = fin_page_perms($conn, 'Finance/assets_fin.php', $is_super_admin);
$can_view = $perms['can_view']; $can_add = $perms['can_add']; $can_edit = $perms['can_edit']; $can_delete = $perms['can_delete'];
if (!$can_view) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض الأصول ❌', 'GOV-PERM-403', ''); exit(); }
$company_scope_sql = fin_scope('company_id', $is_super_admin, $company_id);

require_once __DIR__ . '/../app/Services/Finance/DepreciationService.php';
use App\Services\Finance\DepreciationService as DEP;

// ── احتساب إهلاك فترةٍ (M-30) ──
// كان المحرّكُ هنا داخلَ الشاشة، وبثلاثة خلالٍ مقيسة: يتجاهل `acquisition_date`
// تجاهلًا تامًّا · ويحتسب **الشهرَ الحاضرَ جبرًا** فما فات لا يُدرَك · ولا يفحص
// قفلَ الفترة ولا يُنتج حدثًا ماليًّا. فصار استدعاءً للخدمة، **والفترةُ مُدخَل**.
if (isset($_GET['run_dep'])) {
    if (!$can_edit) { ems_gov_flash_redirect('assets_fin.php', 'لا توجد صلاحية الاحتساب ❌', 'GOV-PERM-403', ''); exit(); }
    if (!fin_verify_action_token()) { ems_gov_flash_redirect('assets_fin.php', 'رمز الحماية غير صالح ❌', 'GOV-FAIL-409', ''); exit(); }
    $period = (isset($_GET['period']) && preg_match('/^\d{4}-\d{2}$/', (string) $_GET['period']))
              ? (string) $_GET['period'] : date('Y-m', strtotime('first day of last month'));
    $r = DEP::runPeriod($conn, fin_gate($is_super_admin), $company_id, $period, $current_user_id, 'screen');
    $msg = $r['ok']
        ? ($r['reason'] . ' · أحداثٌ منشورة: ' . $r['events'] . ' ✅')
        : ($r['code'] . ' — ' . $r['reason'] . ' ❌');
    ems_gov_flash_redirect('assets_fin.php', $msg, 'GOV-INFO-200', ''); exit();
}

// ── الاستدراك: من شهر الاقتناء حتى الشهر المنقضي ──
if (isset($_GET['catch_up'])) {
    if (!$can_edit) { ems_gov_flash_redirect('assets_fin.php', 'لا توجد صلاحية الاحتساب ❌', 'GOV-PERM-403', ''); exit(); }
    if (!fin_verify_action_token()) { ems_gov_flash_redirect('assets_fin.php', 'رمز الحماية غير صالح ❌', 'GOV-FAIL-409', ''); exit(); }
    $r = DEP::catchUp($conn, fin_gate($is_super_admin), $company_id, $current_user_id, null, 'screen');
    ems_gov_flash_redirect('assets_fin.php', $r['reason'] . ' ✅', 'GOV-OK-200', ''); exit();
}

// ── حفظ أصل ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['code'])) {
    if (!$can_add) { ems_gov_flash_redirect('assets_fin.php', 'لا توجد صلاحية إضافة ❌', 'GOV-PERM-403', ''); exit(); }
    $code = trim($_POST['code'] ?? ''); $name = trim($_POST['name'] ?? ''); $cat = trim($_POST['category'] ?? '');
    $eqid = intval($_POST['equipment_id'] ?? 0) ?: null;
    $adate = trim($_POST['acquisition_date'] ?? '') ?: null;
    $cost = round(floatval($_POST['acquisition_cost'] ?? 0), 2);
    $salv = round(floatval($_POST['salvage_value'] ?? 0), 2);
    $life = max(1, intval($_POST['useful_life_months'] ?? 60));
    if ($code === '' || $name === '' || $cost <= 0) { ems_gov_flash_redirect('assets_fin.php', 'بيانات الأصل غير مكتملة ❌', 'GOV-FAIL-409', ''); exit(); }
    try {
        fin_gate($is_super_admin)->insert('fin_assets', array(
            'code' => $code, 'name' => $name, 'category' => $cat, 'equipment_id' => $eqid,
            'acquisition_date' => $adate, 'acquisition_cost' => $cost, 'salvage_value' => $salv,
            'useful_life_months' => $life, 'created_by' => $current_user_id,
        ));
    } catch (\App\Core\TenantGateException $e) {
        if (strpos($e->getMessage(), 'Duplicate entry') !== false) { ems_gov_flash_redirect('assets_fin.php', 'كود الأصل مكرر ❌', 'GOV-FAIL-409', ''); exit(); }
        error_log('fin_assets insert refused: ' . $e->getMessage());
        ems_gov_flash_redirect('assets_fin.php', 'حدث خطأ أثناء الحفظ ❌', 'GOV-FAIL-409', ''); exit();
    }
    ems_gov_flash_redirect('assets_fin.php', 'تمت إضافة الأصل ✅', 'GOV-OK-200', ''); exit();
}

// ── حذف ناعم ──
if (isset($_GET['delete_id'])) {
    if (!$can_delete) { ems_gov_flash_redirect('assets_fin.php', 'لا توجد صلاحية حذف ❌', 'GOV-PERM-403', ''); exit(); }
    $d = intval($_GET['delete_id']);
    fin_gate($is_super_admin)->softDelete('fin_assets', $d);
    ems_gov_flash_redirect('assets_fin.php', 'تم حذف الأصل ✅', 'GOV-OK-200', ''); exit();
}

$page_title = 'إيكوبيشن | الأصول والإهلاك';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
$state_lbl = array('active' => 'نشط', 'fully_depreciated' => 'مُهلَك بالكامل', 'disposed' => 'مستبعَد');
?>
<?php /* نُقلت أنماطُ هذه الشاشةِ إلى assets/css/ems-screens.css (UXUI-01 البند ٦: صفرُ نمطٍ محليّ) */ ?>
<div class="main fin-assets-main ems-unified-page-shell">
    <?php
    $header_title = 'الأصول والإهلاك'; $header_icon = 'fa fa-building-flag';
    $header_actions = array();
    if ($can_add) { $header_actions[] = array('id' => 'toggleForm', 'class' => 'add-btn', 'icon' => 'fas fa-plus-circle', 'label' => 'إضافة أصل'); }
    if ($can_edit) {
        $lastMonth = date('Y-m', strtotime('first day of last month'));
        $header_actions[] = array('href' => '?run_dep=1&period=' . $lastMonth . '&_t=' . fin_action_token(),
            'class' => 'add-btn', 'icon' => 'fas fa-calculator', 'label' => 'احتساب إهلاك ' . $lastMonth);
        $header_actions[] = array('href' => '?catch_up=1&_t=' . fin_action_token(),
            'class' => 'add-btn', 'icon' => 'fas fa-clock-rotate-left', 'label' => 'استدراك الشهور الفائتة');
    }
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    // UXW-01 ٩: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
    echo ems_states_bundle('لا أصولَ ثابتةً مسجَّلةً بعدُ', 'أضفْ أصلًا بزرِّ «إضافة أصل» في رأسِ الشاشة ثمّ شغّلْ «استدراك الشهور الفائتة» لبناءِ سجلِّ إهلاكِه');
    ?>
    <?php fin_msg_banner(); ?>

    <form id="finForm" action="" method="post" class="allforms">
        <?php echo csrf_field(); ?>
        <div class="card-header"><h5><i class="fas fa-edit"></i> إضافة أصل ثابت</h5></div>
        <div class="card"><div class="card-body"><div class="form-section"><div class="form-grid">
            <div class="form-group"><label for="emsf_199_0b09d">الكود <span class="required">*</span></label><input type="text" name="code" required id="emsf_199_0b09d"></div>
            <div class="form-group"><label for="emsf_200_b6b52">اسم الأصل <span class="required">*</span></label><input type="text" name="name" required id="emsf_200_b6b52"></div>
            <div class="form-group"><label for="emsf_201_d1522">الفئة</label><input type="text" name="category" placeholder="معدات/مباني/سيارات" id="emsf_201_d1522"></div>
            <div class="form-group"><label for="emsf_202_b8ed1">المعدة المرتبطة</label><select name="equipment_id" id="emsf_202_b8ed1"><?php echo fin_equipment_options($conn, $is_super_admin, $company_id); ?></select></div>
            <div class="form-group"><label for="emsf_203_c79d2">تاريخ الاقتناء</label><input type="date" name="acquisition_date" aria-label="تاريخُ اقتناءِ الأصل" id="emsf_203_c79d2" value="<?php echo date('Y-m-d'); ?>"></div>
            <div class="form-group"><label for="emsf_204_98031">تكلفة الاقتناء <span class="required">*</span></label><input type="number" step="0.01" min="0" name="acquisition_cost" required id="emsf_204_98031"></div>
            <div class="form-group"><label for="emsf_205_1a728">القيمة التخريدية</label><input type="number" step="0.01" min="0" name="salvage_value" value="0" id="emsf_205_1a728"></div>
            <div class="form-group"><label for="emsf_206_d25e1">العمر الإنتاجي (شهر)</label><input type="number" min="1" name="useful_life_months" value="60" id="emsf_206_d25e1"></div>
        </div></div>
        <div class="form-actions"><button type="submit" class="btn-primary"><i class="fas fa-save"></i> حفظ</button>
            <button type="button" class="btn-secondary" onclick="$('#finForm').removeClass('allforms-visible')">إلغاء</button></div>
        </div></div>
    </form>

    <div class="card"><div class="card-body">
        <p class="text-muted fin-ast-note"><i class="fas fa-circle-info"></i>
            الإهلاك بطريقة القسط الثابت: (التكلفة − التخريدية) ÷ العمر الإنتاجي شهريًا —
            <strong>حدثٌ دوريٌّ بمفتاح (الأصل × الفترة)</strong> لا يتكرر، ولا يقع
            <strong>قبل شهر الاقتناء</strong> ولا في <strong>فترةٍ مقفلة</strong>.
            وعمودُ «شهورٌ غير محتسَبة» يُري الفجوةَ — و«الاستدراك» يسدّها من شهر الاقتناء.</p>
        <div class="table-container">
            <table id="finTable" class="display nowrap alltables fin-ast-tbl" data-scroll-x="1" data-state-save="false">
                <thead><tr><th>الإجراءات</th><th>كود المعدة</th><th>الأصل</th><th>الفئة</th><th>مركز التكلفة</th><th>مجمّع الإهلاك</th><th>القيمة الدفترية</th><th>العمر(شهر)</th><th>شهورٌ غير محتسَبة</th><th>الحالة</th>
              <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
              <th class="ems-fn-th" data-fn="1">رقم القيد</th>
              <th class="ems-fn-th" data-fn="1">طريقة الإهلاك</th>
              <th class="ems-fn-th" data-fn="1">القاعدة القابلة للإهلاك</th>
              <th class="ems-fn-th" data-fn="1">العمر الإنتاجي بالساعات</th>
              <th class="ems-fn-th" data-fn="1">معدل الساعة</th>
              <th class="ems-fn-th" data-fn="1">ساعات الفترة</th>
              <th class="ems-fn-th" data-fn="1">إهلاك الفترة</th>
              <th class="ems-fn-th" data-fn="1">المتراكم قبل</th>
              <th class="ems-fn-th" data-fn="1">المتراكم بعد</th>
              <th class="ems-fn-th" data-fn="1">حصة المالك</th>
              <th class="ems-fn-th" data-fn="1">قيمة الحصة</th>
              <th class="ems-fn-th" data-fn="1">احتسبه</th>
              <th class="ems-fn-th none" data-fn="1">اعتمده</th>
              <th class="ems-fn-th none" data-fn="1">رقم القيد المحاسبي</th>
              <th class="ems-fn-th none" data-fn="1">نسخة القاعدة المستعملة</th>
              <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
              <th class="ems-gov-th none" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
              <th class="ems-gov-th none" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
              <th class="ems-gov-th none" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
              <th class="ems-gov-th none" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
              <th class="ems-gov-th none" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
              <th class="ems-gov-th none" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
              <th class="ems-gov-th none" data-gov="idem_key" data-slice="2" title="يمنع وقوع الأثر مرتين بمفتاح مركب">مفتاح منع التكرار</th>
              <th class="ems-gov-th none" data-gov="reversed_by" data-slice="2" title="مرجع الحركة التي عكسته">معكوس بـ</th>
              <th class="ems-gov-th none" data-gov="reversal_of" data-slice="2" title="مرجع الحركة التي عكسها">عكس عن</th>
              <th class="ems-gov-th none" data-gov="impact_grade" data-slice="2" title="مبدئي أم نهائي — فلا يقفل مبدئي ماليًّا">درجة الأثر</th>
              <th class="ems-gov-th none" data-gov="view_log" data-slice="2" title="من قرأ البيان الحساس ومتى">سجل الاطّلاع</th>
              <th class="ems-gov-th none" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
              <th class="ems-gov-th none" data-gov="fx_rate_source" data-slice="3" title="ما خالف عملة الدفاتر يحمل السعر ومصدره">سعر الصرف ومصدره</th>
              </tr></thead>
                <tbody>
                <?php
                $asset_rows = fin_gate($is_super_admin)->select('fin_assets', array('orderBy' => 'code ASC'));
                { foreach ($asset_rows as $row) {
                    $st = (string)$row['state'];
                    $tone = $st === 'active' ? 'success' : ($st === 'fully_depreciated' ? 'secondary' : 'dark');
                    echo "<tr><td><div class='action-btns'>";
                    if ($can_delete) echo "<a href='?delete_id=" . intval($row['id']) . "' class='action-btn delete' onclick='return confirm(\"حذف؟\")' title='حذف'><i class='fas fa-trash-alt'></i></a>";
                    echo "</div></td>";
                    echo "<td>" . htmlspecialchars((string)$row['code']) . "</td>";
                    echo "<td>" . htmlspecialchars((string)$row['name']) . "</td>";
                    echo "<td>" . htmlspecialchars((string)($row['category'] ?? '—')) . "</td>";
                    echo "<td>" . number_format((float)$row['acquisition_cost'], 2) . "</td>";
                    echo "<td>" . number_format((float)$row['accumulated_depreciation'], 2) . "</td>";
                    echo "<td><strong>" . number_format((float)$row['book_value'], 2) . "</strong></td>";
                    echo "<td>" . intval($row['useful_life_months']) . "</td>";
                    // M-30: الفجوةُ تُرى — شهورٌ بين الاقتناء والشهر المنقضي بلا قسط
                    $miss = ($st === 'active') ? DEP::missingPeriods(fin_gate($is_super_admin), $row) : array();
                    echo "<td>" . (count($miss) > 0
                        ? ("<span class='badge badge-warning' title='" . htmlspecialchars(implode(' · ', array_slice($miss, 0, 24)))
                           . "'>" . count($miss) . "</span>")
                        : "<span class='badge badge-success'>0</span>") . "</td>";
                    echo "<td><span class='badge badge-" . $tone . "'>" . htmlspecialchars($state_lbl[$st] ?? $st) . "</span></td>";
                    echo "</tr>";
                } }
                ?>
                </tbody>
            </table>
        </div>

        <h5 class="fin-ast-h5-next"><i class="fas fa-clock-rotate-left"></i> سجلّ الإهلاك</h5>
        <div class="table-container">
            <table id="depTable" class="display nowrap alltables fin-ast-tbl" data-scroll-x="1" data-state-save="false">
                <thead><tr><th>الأصل</th><th>الفترة</th><th>مبلغ الإهلاك</th><th>تاريخ الاحتساب</th></tr></thead>
                <tbody>
                <?php
                $dep_rows = fin_gate($is_super_admin)->scopedQuery(
                    array('scope' => array('d' => 'fin_depreciation'), 'enrich' => array('a' => 'fin_assets')),
                    "SELECT d.*, a.name AS asset_name FROM fin_depreciation d
                     LEFT JOIN fin_assets a ON a.id=d.asset_id
                     WHERE {TENANT_SCOPE} ORDER BY d.id DESC LIMIT 200");
                { foreach ($dep_rows as $row) {
                    echo "<tr><td>" . htmlspecialchars((string)($row['asset_name'] ?? '')) . "</td>";
                    echo "<td>" . htmlspecialchars((string)$row['period_ref']) . "</td>";
                    echo "<td>" . number_format((float)$row['depreciation_amount'], 2) . "</td>";
                    echo "<td>" . htmlspecialchars((string)$row['run_date']) . "</td></tr>";
                } }
                ?>
                </tbody>
            </table>
        </div>
    </div></div>
</div>

<script src="/ems/assets/vendor/jquery-3.7.1.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/jquery.dataTables.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/dataTables.buttons.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/buttons.html5.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/buttons.print.min.js"></script>
<script src="/ems/assets/vendor/jszip/jszip.min.js"></script>
<script src="/ems/assets/vendor/pdfmake/pdfmake.min.js"></script>
<script src="/ems/assets/vendor/pdfmake/vfs_fonts.js"></script>
<script>
$(document).ready(function () {
    // جداولُ العرضِ يهيّئُها المكوّنُ المركزيُّ (assets/js/ui-unification.js)
    $('#toggleForm').on('click', function () { $('#finForm').toggleClass('allforms-visible'); });
});
</script>
</body>
</html>
