<?php
/**
 * Operations/site_work_calendar.php — جدول عمل المنجم — الأسبوعي والشهري (CMP-03 ⑥ v2 — بتصميم SCR-DES حرفيًّا + إدخال حي)
 * ───────────────────────────────────────────────────────────────────────────
 * الورقة المالكة: 01 · إدارة الموقع · الأعمدة 22 بترتيب المستند وطبقة
 * الحوكمة بشرائحها. الصفوف في المخزن البيني `cmp03_screen_rows` (معزول
 * بالكيان) حتى يولد جدول الشاشة الأصلي — مهمة اللحاق في
 * docs/CMP03_FOLLOWUP_SOURCES_ar.md. الفائض فوق 22 عمودًا منهارٌ (توصية ①).
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}
include '../config.php';
require_once '../includes/permissions_helper.php';
require_once '../includes/gov_columns.php';

$company_id     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$is_super_admin = (strval($_SESSION['user']['role'] ?? '') === '-1');
$uid            = intval($_SESSION['user']['id'] ?? 0);
if (!$is_super_admin && $company_id <= 0) {
    ems_gov_flash_redirect('../main/dashboard.php', 'غير مصرح', 'GOV-PERM-403', '');
    exit();
}

require_once __DIR__ . '/../includes/cmp03_local_store.php'; // الموجة ٢ — الجدول الأصلي

$CANONICAL = 'site_work_calendar.php';
$COLS   = array (
  0 => 'الكيان',
  1 => 'رقم الجدول',
  2 => 'الموقع',
  3 => 'المشروع',
  4 => 'العقد',
  5 => 'الشهر',
  6 => 'الأسبوع',
  7 => 'أيام العمل المخططة',
  8 => 'أيام التوقف المخطط',
  9 => 'سبب التوقف',
  10 => 'ساعات التشغيل اليومية',
  11 => 'عدد الورديات',
  12 => 'الكمية المستهدفة الشهرية',
  13 => 'الكمية المستهدفة الأسبوعية',
  14 => 'المعدات المخصصة',
  15 => 'المشغلون المطلوبون',
  16 => 'نوافذ الوقائية',
  17 => 'المنشئ — الاسم والصفة',
  18 => 'المعتمد — الاسم والصفة',
  19 => 'تاريخ الاعتماد',
  20 => 'مرجع التفويض',
  21 => 'الحالة',
);
$FIELDS = array (
  0 => 'رقم الجدول',
  1 => 'الموقع',
  2 => 'المشروع',
  3 => 'العقد',
  4 => 'الشهر',
  5 => 'الأسبوع',
  6 => 'أيام العمل المخططة',
  7 => 'أيام التوقف المخطط',
  8 => 'سبب التوقف',
  9 => 'ساعات التشغيل اليومية',
  10 => 'عدد الورديات',
  11 => 'الكمية المستهدفة الشهرية',
  12 => 'الكمية المستهدفة الأسبوعية',
  13 => 'المعدات المخصصة',
  14 => 'المشغلون المطلوبون',
  15 => 'نوافذ الوقائية',
  16 => 'المعتمد — الاسم والصفة',
  17 => 'تاريخ الاعتماد',
  18 => 'مرجع التفويض',
  19 => 'الحالة',
);

/* ── الحفظ: فورم الإضافة الموحد → المخزن البيني ─────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['cmp03_action'] ?? '') === 'add') {
    $payload = array();
    foreach ($FIELDS as $i => $lbl) {
        $v = trim((string) ($_POST['f' . $i] ?? ''));
        if ($v !== '') { $payload[$lbl] = $v; }
    }
    $status = $payload['الحالة'] ?? 'مسودة';
    $creator = trim((string) ($_SESSION['user']['name'] ?? '')) ?: ('مستخدم #' . $uid);
    // الموجة ٢: الحفظ في الجدول الأصلي للشاشة (الفارغ NULL — لا مخزن بينيًّا)
    $ok = cmp03_store_insert($conn, $company_id, $CANONICAL, $payload, $status, $uid, $creator);
    ems_gov_flash_redirect(basename(__FILE__), $ok ? 'حفظ الصف ✅' : 'تعذر الحفظ ❌', 'GOV-OK-200', '');
    exit();
}

/* ── القراءة: صفوف الكيان لهذه الشاشة ───────────────────────────────────── */
// الموجة ٢: القراءة من الجدول الأصلي — الشكل القديم نفسه (id·payload·status·…)
$rows = cmp03_store_rows($conn, $CANONICAL, ($is_super_admin && $company_id <= 0) ? 0 : $company_id);

$govCtx = ems_gov_ctx();
$entityName = $govCtx['values']['entity'] ?? '—';

/** قيمة خلية العمود من الصف — الحوكمة الآلية حية وسائرها من الحمولة أو «—» */
function cmp03_cell($col, $row, $entityName) {
    $n = cmp03_screen_norm($col);
    if ($n === cmp03_screen_norm('الكيان')) { return $entityName; }
    if ($n === cmp03_screen_norm('المنشئ — الاسم والصفة') || $n === cmp03_screen_norm('الجهة المنشئة')) {
        return $row['created_by_name'] ?: '—';
    }
    if ($n === cmp03_screen_norm('تاريخ الإنشاء')) { return $row['created_at']; }
    if ($n === cmp03_screen_norm('الحالة')) { return $row['status']; }
    if ($n === cmp03_screen_norm('مفتاح منع التكرار')) { return 'CMP03-' . intval($row['id']); }
    if (isset($row['payload'][$col]) && $row['payload'][$col] !== '') { return $row['payload'][$col]; }
    return '—';
}
/** تطبيع محلي خفيف (مرآة cmp03_norm دون جر مكتبة الأدوات للويب) */
function cmp03_screen_norm($s) {
    $s = preg_replace('/\s+/u', ' ', trim((string) $s));
    $s = str_replace(array('أ','إ','آ'), 'ا', $s);
    $s = str_replace('ة', 'ه', $s);
    $s = str_replace('ى', 'ي', $s);
    return preg_replace('/[]/u', '', $s);
}

$page_title = 'إيكوبيشن | جدول عمل المنجم — الأسبوعي والشهري';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell" dir="rtl">
    <?php
    $header_title = 'جدول عمل المنجم — الأسبوعي والشهري';
    $header_icon = 'fa fa-calendar';
    $header_actions = array(
        array('tag' => 'button', 'id' => 'cmp03AddBtn', 'class' => '', 'icon' => 'fa fa-plus',
              'label' => 'إضافة', 'title' => 'إضافة صف جديد', 'attrs' => 'type="button"'),
    );
    $header_back = false;
    include '../includes/page_header.php';
    if (isset($_GET['msg'])) {
        echo '<div class="alert alert-info">' . htmlspecialchars((string) $_GET['msg'], ENT_QUOTES, 'UTF-8') . '</div>';
    }
    echo ems_states_bundle('لا جداول عمل أسبوعية أو شهرية مسجلة بعد', 'أضف أول صف بزر «إضافة» في رأس الشاشة');
    ?>
    <?php /* نُقلت أنماطُ هذه الشاشةِ إلى assets/css/ems-screens.css (UXUI-01 البند ٦: صفرُ نمطٍ محليّ) */ ?>

    <!-- فورم الإضافة الموحد (ems-forms) — مطويٌّ حتى زرِّ الرأس -->
    <form method="post" action="" class="allforms" id="cmp03AddForm">
        <?= csrf_field() ?>
        <input type="hidden" name="cmp03_action" value="add">
        <div class="card"><div class="card-header">
            <h5><i class="fa fa-plus"></i> إضافة — جدول عمل المنجم — الأسبوعي والشهري</h5>
        </div><div class="card-body">
            <div class="form-section"><div class="form-grid">
                <div class="form-group"><label for="emsf_978_bfac4">رقم الجدول</label>
                    <input type="text" name="f0" required maxlength="190" id="emsf_978_bfac4"></div>
                <div class="form-group"><label for="emsf_979_0a4a7">الموقع</label>
                    <input type="text" name="f1" maxlength="190" id="emsf_979_0a4a7"></div>
                <div class="form-group"><label for="emsf_980_d6074">المشروع</label>
                    <input type="text" name="f2" maxlength="190" id="emsf_980_d6074"></div>
                <div class="form-group"><label for="emsf_981_2be9e">العقد</label>
                    <input type="text" name="f3" maxlength="190" id="emsf_981_2be9e"></div>
                <div class="form-group"><label for="emsf_982_8ba84">الشهر</label>
                    <input type="text" name="f4" maxlength="190" id="emsf_982_8ba84"></div>
                <div class="form-group"><label for="emsf_983_92c4f">الأسبوع</label>
                    <input type="text" name="f5" maxlength="190" id="emsf_983_92c4f"></div>
                <div class="form-group"><label for="emsf_984_8be69">أيام العمل المخططة</label>
                    <input type="text" name="f6" maxlength="190" id="emsf_984_8be69"></div>
                <div class="form-group"><label for="emsf_985_3d532">أيام التوقف المخطط</label>
                    <input type="text" name="f7" maxlength="190" id="emsf_985_3d532"></div>
                <div class="form-group"><label for="emsf_986_c807f">سبب التوقف</label>
                    <input type="text" name="f8" maxlength="190" id="emsf_986_c807f"></div>
                <div class="form-group"><label for="emsf_987_4c1ab">ساعات التشغيل اليومية</label>
                    <input type="text" inputmode="decimal" name="f9" placeholder="0" id="emsf_987_4c1ab"></div>
                <div class="form-group"><label for="emsf_988_d1650">عدد الورديات</label>
                    <input type="text" inputmode="decimal" name="f10" placeholder="0" id="emsf_988_d1650"></div>
                <div class="form-group"><label for="emsf_989_db532">الكمية المستهدفة الشهرية</label>
                    <input type="text" inputmode="decimal" name="f11" placeholder="0" id="emsf_989_db532"></div>
                <div class="form-group"><label for="emsf_990_53b08">الكمية المستهدفة الأسبوعية</label>
                    <input type="text" inputmode="decimal" name="f12" placeholder="0" id="emsf_990_53b08"></div>
                <div class="form-group"><label for="emsf_991_e68c9">المعدات المخصصة</label>
                    <input type="text" name="f13" maxlength="190" id="emsf_991_e68c9"></div>
                <div class="form-group"><label for="emsf_992_cc9fe">المشغلون المطلوبون</label>
                    <input type="text" name="f14" maxlength="190" id="emsf_992_cc9fe"></div>
                <div class="form-group"><label for="emsf_993_f7566">نوافذ الوقائية</label>
                    <input type="text" name="f15" maxlength="190" id="emsf_993_f7566"></div>
                <div class="form-group"><label for="emsf_994_d10d5">المعتمد — الاسم والصفة</label>
                    <input type="text" name="f16" maxlength="190" id="emsf_994_d10d5"></div>
                <div class="form-group"><label for="emsf_995_cbd01">تاريخ الاعتماد</label>
                    <input type="date" name="f17" id="emsf_995_cbd01"></div>
                <div class="form-group"><label for="emsf_996_9b4fe">مرجع التفويض</label>
                    <input type="text" name="f18" maxlength="190" id="emsf_996_9b4fe"></div>
                <div class="form-group"><label for="emsf_997_b3681">الحالة</label>
                    <select name="f19" id="emsf_997_b3681"><option value="مسودة">مسودة</option><option value="قيد المراجعة">قيد المراجعة</option><option value="معتمد">معتمد</option><option value="موقوف">موقوف</option><option value="ملغي">ملغي</option></select></div>
            </div></div>
            <div class="cmp03-form-actions">
                <button type="submit" class="btn-primary"><i class="fa fa-save"></i> حفظ</button>
                <button type="button" class="btn-secondary" id="cmp03CancelBtn"><i class="fa fa-times"></i> إلغاء</button>
            </div>
        </div></div>
    </form>

    <div class="card"><div class="card-body">
        <div class="table-responsive">
        <table class="alltables display" id="site_work_calendarTable">
            <thead><tr>
            <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صف بلا كيان مالك">الكيان</th>
            <th>رقم الجدول</th>
            <th>الموقع</th>
            <th>المشروع</th>
            <th>العقد</th>
            <th>الشهر</th>
            <th>الأسبوع</th>
            <th>أيام العمل المخططة</th>
            <th>أيام التوقف المخطط</th>
            <th>سبب التوقف</th>
            <th>ساعات التشغيل اليومية</th>
            <th>عدد الورديات</th>
            <th>الكمية المستهدفة الشهرية</th>
            <th>الكمية المستهدفة الأسبوعية</th>
            <th>المعدات المخصصة</th>
            <th>المشغلون المطلوبون</th>
            <th>نوافذ الوقائية</th>
            <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المنشئ — الاسم والصفة</th>
            <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمد — الاسم والصفة</th>
            <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
            <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمد — تفويض أو سلطة أصلية">مرجع التفويض</th>
            <th class="ems-gov-th" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
            </tr></thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="22" class="text-center text-muted">لا بيانات بعد — أضف أول صف بزر «إضافة»</td></tr>
            <?php else: foreach ($rows as $r): ?>
                <tr<?php echo $r['is_seed'] ? ' data-seed="1"' : ''; ?>>
                    <?php foreach ($COLS as $c): $v = cmp03_cell($c, $r, $entityName); ?>
                    <td<?php echo $v === '—' ? ' class="ems-gov-empty"' : ''; ?>><?php echo htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); ?></td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
        </div>
    </div></div>
</div>

<script>
(function () {
    var btn = document.getElementById('cmp03AddBtn');
    var form = document.getElementById('cmp03AddForm');
    var cancel = document.getElementById('cmp03CancelBtn');
    if (btn && form) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            form.classList.toggle('allforms-visible');
            if (form.classList.contains('allforms-visible')) {
                form.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        });
    }
    if (cancel && form) {
        cancel.addEventListener('click', function () { form.classList.remove('allforms-visible'); });
    }
})();
</script>
