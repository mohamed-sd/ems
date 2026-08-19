<?php
/**
 * Operations/shift_log.php — سجل الوردية (CMP-03 ⑥ v2 — بتصميم SCR-DES حرفيًّا + إدخال حي)
 * ───────────────────────────────────────────────────────────────────────────
 * الورقة المالكة: 01 · إدارة الموقع · الأعمدة 23 بترتيب المستند وطبقة
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

$CANONICAL = 'shift_log.php';
$COLS   = array (
  0 => 'رقم السجل',
  1 => 'التاريخ',
  2 => 'الموقع',
  3 => 'الوردية',
  4 => 'وقت الفتح',
  5 => 'وقت الإقفال',
  6 => 'عدد المعدات',
  7 => 'عدد المشغّلين الحاضرين',
  8 => 'الغياب',
  9 => 'قراءة العدّاد عند الفتح',
  10 => 'قراءة العدّاد عند الإقفال',
  11 => 'الملاحظات',
  12 => 'المسلِّم',
  13 => 'المستلِم',
  14 => 'حالة السجل',
  15 => 'الكيان',
  16 => 'المُنشئ — الاسم والصفة',
  17 => 'تاريخ الإنشاء',
  18 => 'المعتمِد — الاسم والصفة',
  19 => 'تاريخ الاعتماد',
  20 => 'مرجع التفويض',
  21 => 'المرجع الأب',
  22 => 'المرفق',
);
$FIELDS = array (
  0 => 'رقم السجل',
  1 => 'التاريخ',
  2 => 'الموقع',
  3 => 'الوردية',
  4 => 'وقت الفتح',
  5 => 'وقت الإقفال',
  6 => 'عدد المعدات',
  7 => 'عدد المشغّلين الحاضرين',
  8 => 'الغياب',
  9 => 'قراءة العدّاد عند الفتح',
  10 => 'قراءة العدّاد عند الإقفال',
  11 => 'الملاحظات',
  12 => 'المسلِّم',
  13 => 'المستلِم',
  14 => 'حالة السجل',
  15 => 'المعتمِد — الاسم والصفة',
  16 => 'تاريخ الاعتماد',
  17 => 'مرجع التفويض',
  18 => 'المرجع الأب',
  19 => 'المرفق',
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
    ems_gov_flash_redirect(basename(__FILE__), $ok ? 'حُفظ الصف ✅' : 'تعذر الحفظ ❌', 'GOV-OK-200', '');
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
    if ($n === cmp03_screen_norm('المُنشئ — الاسم والصفة') || $n === cmp03_screen_norm('الجهة المُنشئة')) {
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
    return preg_replace('/[ًٌٍَُِّْ]/u', '', $s);
}

$page_title = 'إيكوبيشن | سجل الوردية';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell" dir="rtl">
    <?php
    $header_title = 'سجل الوردية';
    $header_icon = 'fa fa-clipboard';
    $header_actions = array(
        array('tag' => 'button', 'id' => 'cmp03AddBtn', 'class' => '', 'icon' => 'fa fa-plus',
              'label' => 'إضافة', 'title' => 'إضافة صف جديد', 'attrs' => 'type="button"'),
    );
    $header_back = false;
    include '../includes/page_header.php';
    if (isset($_GET['msg'])) {
        echo '<div class="alert alert-info">' . htmlspecialchars((string) $_GET['msg'], ENT_QUOTES, 'UTF-8') . '</div>';
    }
    echo ems_states_bundle('لا سجلاتِ ورديّاتٍ مسجَّلةً بعدُ', 'أضف أولَ صفٍّ بزرِّ «إضافة» في رأسِ الشاشة');
    ?>
    <?php /* نُقلت أنماطُ هذه الشاشةِ إلى assets/css/ems-screens.css (UXUI-01 البند ٦: صفرُ نمطٍ محليّ) */ ?>

    <!-- فورم الإضافة الموحد (ems-forms) — مطويٌّ حتى زرِّ الرأس -->
    <form method="post" action="" class="allforms" id="cmp03AddForm">
        <?= csrf_field() ?>
        <input type="hidden" name="cmp03_action" value="add">
        <div class="card"><div class="card-header">
            <h5><i class="fa fa-plus"></i> إضافة — سجل الوردية</h5>
        </div><div class="card-body">
            <div class="form-section"><div class="form-grid">
                <div class="form-group"><label for="emsf_898_43f89">رقم السجل</label>
                    <input type="text" name="f0" required maxlength="190" id="emsf_898_43f89"></div>
                <div class="form-group"><label for="emsf_899_dcdbf">التاريخ</label>
                    <input type="date" name="f1" id="emsf_899_dcdbf"></div>
                <div class="form-group"><label for="emsf_900_a958f">الموقع</label>
                    <input type="text" name="f2" maxlength="190" id="emsf_900_a958f"></div>
                <div class="form-group"><label for="emsf_901_7359c">الوردية</label>
                    <input type="text" name="f3" maxlength="190" id="emsf_901_7359c"></div>
                <div class="form-group"><label for="emsf_902_55a78">وقت الفتح</label>
                    <input type="text" name="f4" maxlength="190" id="emsf_902_55a78"></div>
                <div class="form-group"><label for="emsf_903_a244e">وقت الإقفال</label>
                    <input type="text" name="f5" maxlength="190" id="emsf_903_a244e"></div>
                <div class="form-group"><label for="emsf_904_a70e5">عدد المعدات</label>
                    <input type="text" inputmode="decimal" name="f6" placeholder="0" id="emsf_904_a70e5"></div>
                <div class="form-group"><label for="emsf_905_268a8">عدد المشغّلين الحاضرين</label>
                    <input type="text" inputmode="decimal" name="f7" placeholder="0" id="emsf_905_268a8"></div>
                <div class="form-group"><label for="emsf_906_51116">الغياب</label>
                    <input type="text" name="f8" maxlength="190" id="emsf_906_51116"></div>
                <div class="form-group"><label for="emsf_907_93e18">قراءة العدّاد عند الفتح</label>
                    <input type="text" name="f9" maxlength="190" id="emsf_907_93e18"></div>
                <div class="form-group"><label for="emsf_908_ea674">قراءة العدّاد عند الإقفال</label>
                    <input type="text" name="f10" maxlength="190" id="emsf_908_ea674"></div>
                <div class="form-group"><label for="emsf_909_89e75">الملاحظات</label>
                    <input type="text" name="f11" maxlength="190" id="emsf_909_89e75"></div>
                <div class="form-group"><label for="emsf_910_56346">المسلِّم</label>
                    <input type="text" name="f12" maxlength="190" id="emsf_910_56346"></div>
                <div class="form-group"><label for="emsf_911_3f9ba">المستلِم</label>
                    <input type="text" name="f13" maxlength="190" id="emsf_911_3f9ba"></div>
                <div class="form-group"><label for="emsf_912_fb4eb">حالة السجل</label>
                    <input type="text" name="f14" maxlength="190" id="emsf_912_fb4eb"></div>
                <div class="form-group"><label for="emsf_913_41329">المعتمِد — الاسم والصفة</label>
                    <input type="text" name="f15" maxlength="190" id="emsf_913_41329"></div>
                <div class="form-group"><label for="emsf_914_407e7">تاريخ الاعتماد</label>
                    <input type="date" name="f16" id="emsf_914_407e7"></div>
                <div class="form-group"><label for="emsf_915_030e4">مرجع التفويض</label>
                    <input type="text" name="f17" maxlength="190" id="emsf_915_030e4"></div>
                <div class="form-group"><label for="emsf_916_61182">المرجع الأب</label>
                    <input type="text" name="f18" maxlength="190" id="emsf_916_61182"></div>
                <div class="form-group"><label for="emsf_917_8ed98">المرفق</label>
                    <input type="text" name="f19" maxlength="190" id="emsf_917_8ed98"></div>
            </div></div>
            <div class="cmp03-form-actions">
                <button type="submit" class="btn-primary"><i class="fa fa-save"></i> حفظ</button>
                <button type="button" class="btn-secondary" id="cmp03CancelBtn"><i class="fa fa-times"></i> إلغاء</button>
            </div>
        </div></div>
    </form>

    <div class="card"><div class="card-body">
        <div class="table-responsive">
        <table class="alltables display" id="shift_logTable">
            <thead><tr>
            <th>رقم السجل</th>
            <th>التاريخ</th>
            <th>الموقع</th>
            <th>الوردية</th>
            <th>وقت الفتح</th>
            <th>وقت الإقفال</th>
            <th>عدد المعدات</th>
            <th>عدد المشغّلين الحاضرين</th>
            <th>الغياب</th>
            <th>قراءة العدّاد عند الفتح</th>
            <th>قراءة العدّاد عند الإقفال</th>
            <th>الملاحظات</th>
            <th>المسلِّم</th>
            <th>المستلِم</th>
            <th>حالة السجل</th>
            <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
            <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
            <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
            <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
            <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
            <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
            <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
            <th class="ems-gov-th none" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
            </tr></thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="23" class="text-center text-muted">لا بياناتَ بعدُ — أضف أول صفٍّ بزر «إضافة»</td></tr>
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
