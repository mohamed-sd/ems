<?php
/**
 * Operations/unbilled.php — الأعمال غير المفوترة (CMP-03 ⑥ v2 — بتصميم SCR-DES حرفيًّا + إدخال حي)
 * ───────────────────────────────────────────────────────────────────────────
 * الورقة المالكة: 02 · إدارة التشغيل · الأعمدة 29 بترتيب المستند وطبقة
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

$CANONICAL = 'unbilled.php';
$COLS   = array (
  0 => 'رقم البند',
  1 => 'العقد',
  2 => 'الشهر',
  3 => 'الوحدة',
  4 => 'وصف العمل',
  5 => 'الكمية',
  6 => 'القيمة',
  7 => 'العملة',
  8 => 'تاريخ التنفيذ',
  9 => 'سبب الاحتباس',
  10 => 'عمر الاحتباس بالأيام',
  11 => 'احتمال الاعتماد',
  12 => 'إجراء المتابعة',
  13 => 'تاريخ آخر مطالبة',
  14 => 'المسؤول',
  15 => 'الحالة',
  16 => 'الكيان',
  17 => 'المُنشئ — الاسم والصفة',
  18 => 'تاريخ الإنشاء',
  19 => 'تاريخ الاعتماد',
  20 => 'مرجع التفويض',
  21 => 'المرجع الأب',
  22 => 'المرفق',
  23 => 'مفتاح منع التكرار',
  24 => 'درجة الأثر',
  25 => 'معكوس بـ',
  26 => 'عكس عن',
  27 => 'مركز التكلفة',
  28 => 'سعر الصرف ومصدره',
);
$FIELDS = array (
  0 => 'رقم البند',
  1 => 'العقد',
  2 => 'الشهر',
  3 => 'الوحدة',
  4 => 'وصف العمل',
  5 => 'الكمية',
  6 => 'القيمة',
  7 => 'العملة',
  8 => 'تاريخ التنفيذ',
  9 => 'سبب الاحتباس',
  10 => 'عمر الاحتباس بالأيام',
  11 => 'احتمال الاعتماد',
  12 => 'إجراء المتابعة',
  13 => 'تاريخ آخر مطالبة',
  14 => 'المسؤول',
  15 => 'الحالة',
  16 => 'تاريخ الاعتماد',
  17 => 'مرجع التفويض',
  18 => 'المرجع الأب',
  19 => 'المرفق',
  20 => 'درجة الأثر',
  21 => 'مركز التكلفة',
  22 => 'سعر الصرف ومصدره',
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
    /* عمودا العكس — مصدرُهما نصُّ الحالةِ الذي يكتبه cmp03_store_reverse
       (لا عمودَ لهما في scr_*، والوصلُ مكتوبٌ هناك). */
    if ($n === cmp03_screen_norm('معكوس بـ')) {
        return cmp03_reversal_ref($row['status'], 'reversed_by') ?: '—';
    }
    if ($n === cmp03_screen_norm('عكس عن')) {
        return cmp03_reversal_ref($row['status'], 'reversal_of') ?: '—';
    }
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

$page_title = 'إيكوبيشن | الأعمال غير المفوترة';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell" dir="rtl">
    <?php
    $header_title = 'الأعمال غير المفوترة';
    $header_icon = 'fa fa-file-circle-exclamation';
    $header_actions = array(
        array('tag' => 'button', 'id' => 'cmp03AddBtn', 'class' => '', 'icon' => 'fa fa-plus',
              'label' => 'إضافة', 'title' => 'إضافة صف جديد', 'attrs' => 'type="button"'),
    );
    $header_back = false;
    include '../includes/page_header.php';
    if (isset($_GET['msg'])) {
        echo '<div class="alert alert-info">' . htmlspecialchars((string) $_GET['msg'], ENT_QUOTES, 'UTF-8') . '</div>';
    }
    echo ems_states_bundle('لا أعمالَ غيرَ مفوترةٍ مسجَّلةً بعدُ', 'أضف أولَ صفٍّ بزرِّ «إضافة» في رأسِ الشاشة');
    ?>
    <?php /* نُقلت أنماطُ هذه الشاشةِ إلى assets/css/ems-screens.css (UXUI-01 البند ٦: صفرُ نمطٍ محليّ) */ ?>

    <!-- فورم الإضافة الموحد (ems-forms) — مطويٌّ حتى زرِّ الرأس -->
    <form method="post" action="" class="allforms" id="cmp03AddForm">
        <?= csrf_field() ?>
        <input type="hidden" name="cmp03_action" value="add">
        <div class="card"><div class="card-header">
            <h5><i class="fa fa-plus"></i> إضافة — الأعمال غير المفوترة</h5>
        </div><div class="card-body">
            <div class="form-section"><div class="form-grid">
                <div class="form-group"><label for="emsf_1003_461c5">رقم البند</label>
                    <input type="text" name="f0" required maxlength="190" id="emsf_1003_461c5"></div>
                <div class="form-group"><label for="emsf_1004_19601">العقد</label>
                    <input type="text" name="f1" maxlength="190" id="emsf_1004_19601"></div>
                <div class="form-group"><label for="emsf_1005_16882">الشهر</label>
                    <input type="text" name="f2" maxlength="190" id="emsf_1005_16882"></div>
                <div class="form-group"><label for="emsf_1006_b8882">الوحدة</label>
                    <input type="text" name="f3" maxlength="190" id="emsf_1006_b8882"></div>
                <div class="form-group"><label for="emsf_1007_c8fb6">وصف العمل</label>
                    <input type="text" name="f4" maxlength="190" id="emsf_1007_c8fb6"></div>
                <div class="form-group"><label for="emsf_1008_b1ddc">الكمية</label>
                    <input type="text" inputmode="decimal" name="f5" placeholder="0" id="emsf_1008_b1ddc"></div>
                <div class="form-group"><label for="emsf_1009_24cfd">القيمة</label>
                    <input type="text" inputmode="decimal" name="f6" placeholder="0" id="emsf_1009_24cfd"></div>
                <div class="form-group"><label for="emsf_1010_bea68">العملة</label>
                    <input type="text" name="f7" maxlength="190" id="emsf_1010_bea68"></div>
                <div class="form-group"><label for="emsf_1011_42bae">تاريخ التنفيذ</label>
                    <input type="date" name="f8" id="emsf_1011_42bae"></div>
                <div class="form-group"><label for="emsf_1012_8c043">سبب الاحتباس</label>
                    <input type="text" name="f9" maxlength="190" id="emsf_1012_8c043"></div>
                <div class="form-group"><label for="emsf_1013_102c2">عمر الاحتباس بالأيام</label>
                    <input type="text" name="f10" maxlength="190" id="emsf_1013_102c2"></div>
                <div class="form-group"><label for="emsf_1014_25b9a">احتمال الاعتماد</label>
                    <input type="text" name="f11" maxlength="190" id="emsf_1014_25b9a"></div>
                <div class="form-group"><label for="emsf_1015_c03ff">إجراء المتابعة</label>
                    <input type="text" name="f12" maxlength="190" id="emsf_1015_c03ff"></div>
                <div class="form-group"><label for="emsf_1016_404c1">تاريخ آخر مطالبة</label>
                    <input type="date" name="f13" id="emsf_1016_404c1"></div>
                <div class="form-group"><label for="emsf_1017_368dc">المسؤول</label>
                    <input type="text" name="f14" maxlength="190" id="emsf_1017_368dc"></div>
                <div class="form-group"><label for="emsf_1018_b5a87">الحالة</label>
                    <select name="f15" id="emsf_1018_b5a87"><option value="مسودة">مسودة</option><option value="قيد المراجعة">قيد المراجعة</option><option value="معتمد">معتمد</option><option value="موقوف">موقوف</option><option value="ملغي">ملغي</option></select></div>
                <div class="form-group"><label for="emsf_1019_61f35">تاريخ الاعتماد</label>
                    <input type="date" name="f16" id="emsf_1019_61f35"></div>
                <div class="form-group"><label for="emsf_1020_5b38b">مرجع التفويض</label>
                    <input type="text" name="f17" maxlength="190" id="emsf_1020_5b38b"></div>
                <div class="form-group"><label for="emsf_1021_cd2f6">المرجع الأب</label>
                    <input type="text" name="f18" maxlength="190" id="emsf_1021_cd2f6"></div>
                <div class="form-group"><label for="emsf_1022_71457">المرفق</label>
                    <input type="text" name="f19" maxlength="190" id="emsf_1022_71457"></div>
                <div class="form-group"><label for="emsf_1023_138d0">درجة الأثر</label>
                    <input type="text" name="f20" maxlength="190" id="emsf_1023_138d0"></div>
                <div class="form-group"><label for="emsf_1024_35a62">مركز التكلفة</label>
                    <input type="text" inputmode="decimal" name="f21" placeholder="0" id="emsf_1024_35a62"></div>
                <div class="form-group"><label for="emsf_1025_98b58">سعر الصرف ومصدره</label>
                    <input type="text" inputmode="decimal" name="f22" placeholder="0" id="emsf_1025_98b58"></div>
            </div></div>
            <div class="cmp03-form-actions">
                <button type="submit" class="btn-primary"><i class="fa fa-save"></i> حفظ</button>
                <button type="button" class="btn-secondary" id="cmp03CancelBtn"><i class="fa fa-times"></i> إلغاء</button>
            </div>
        </div></div>
    </form>

    <div class="card"><div class="card-body">
        <div class="table-responsive">
        <table class="alltables display" id="unbilledTable">
            <thead><tr>
            <th>رقم البند</th>
            <th>العقد</th>
            <th>الشهر</th>
            <th>الوحدة</th>
            <th>وصف العمل</th>
            <th>الكمية</th>
            <th>القيمة</th>
            <th class="ems-gov-th" data-gov="currency" data-slice="3" title="لا مبلغ بلا عملة">العملة</th>
            <th>تاريخ التنفيذ</th>
            <th>سبب الاحتباس</th>
            <th>عمر الاحتباس بالأيام</th>
            <th>احتمال الاعتماد</th>
            <th>إجراء المتابعة</th>
            <th>تاريخ آخر مطالبة</th>
            <th>المسؤول</th>
            <th class="ems-gov-th" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
            <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
            <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
            <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
            <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
            <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
            <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
            <th class="ems-gov-th none" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
            <th class="ems-gov-th none" data-gov="idem_key" data-slice="2" title="يمنع وقوع الأثر مرتين بمفتاح مركب">مفتاح منع التكرار</th>
            <th class="ems-gov-th none" data-gov="impact_grade" data-slice="2" title="مبدئي أم نهائي — فلا يقفل مبدئي ماليًّا">درجة الأثر</th>
            <th class="ems-gov-th none" data-gov="reversed_by" data-slice="2" title="مرجع الحركة التي عكسته">معكوس بـ</th>
            <th class="ems-gov-th none" data-gov="reversal_of" data-slice="2" title="مرجع الحركة التي عكسها">عكس عن</th>
            <th class="ems-gov-th none" data-gov="cost_center" data-slice="3" title="وجهة التحميل">مركز التكلفة</th>
            <th class="ems-gov-th none" data-gov="fx_rate_source" data-slice="3" title="ما خالف عملة الدفاتر يحمل السعر ومصدره">سعر الصرف ومصدره</th>
            </tr></thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="29" class="text-center text-muted">لا بياناتَ بعدُ — أضف أول صفٍّ بزر «إضافة»</td></tr>
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
