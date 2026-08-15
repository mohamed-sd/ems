<?php
/**
 * Operations/production.php — الإنتاج والقياس (CMP-03 ⑥ v2 — بتصميم SCR-DES حرفيًّا + إدخال حي)
 * ───────────────────────────────────────────────────────────────────────────
 * الورقة المالكة: 01 · إدارة الموقع · الأعمدة 28 بترتيب المستند وطبقة
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

$CANONICAL = 'production.php';
$COLS   = array (
  0 => 'رقم السجل',
  1 => 'التاريخ',
  2 => 'الموقع',
  3 => 'العقد',
  4 => 'بند العقد',
  5 => 'نوع الإنتاج',
  6 => 'جبهة العمل',
  7 => 'نقطة التفريغ',
  8 => 'طريقة القياس',
  9 => 'الكمية حسب سجلنا',
  10 => 'وحدة القياس',
  11 => 'الكمية المعتمدة من العميل',
  12 => 'الفرق',
  13 => 'سبب الفرق',
  14 => 'المستند المرفق',
  15 => 'المُدخِل',
  16 => 'معتمد العميل',
  17 => 'الحالة',
  18 => 'الكيان',
  19 => 'تاريخ الإنشاء',
  20 => 'المعتمِد — الاسم والصفة',
  21 => 'تاريخ الاعتماد',
  22 => 'مرجع التفويض',
  23 => 'المرجع الأب',
  24 => 'مفتاح منع التكرار',
  25 => 'درجة الأثر',
  26 => 'معكوس بـ',
  27 => 'عكس عن',
);
$FIELDS = array (
  0 => 'رقم السجل',
  1 => 'التاريخ',
  2 => 'الموقع',
  3 => 'العقد',
  4 => 'بند العقد',
  5 => 'نوع الإنتاج',
  6 => 'جبهة العمل',
  7 => 'نقطة التفريغ',
  8 => 'طريقة القياس',
  9 => 'الكمية حسب سجلنا',
  10 => 'وحدة القياس',
  11 => 'الكمية المعتمدة من العميل',
  12 => 'الفرق',
  13 => 'سبب الفرق',
  14 => 'المستند المرفق',
  15 => 'المُدخِل',
  16 => 'معتمد العميل',
  17 => 'الحالة',
  18 => 'المعتمِد — الاسم والصفة',
  19 => 'تاريخ الاعتماد',
  20 => 'مرجع التفويض',
  21 => 'المرجع الأب',
  22 => 'درجة الأثر',
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

$page_title = 'إيكوبيشن | الإنتاج والقياس';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell" dir="rtl">
    <?php
    $header_title = 'الإنتاج والقياس';
    $header_icon = 'fa fa-weight-scale';
    $header_actions = array(
        array('tag' => 'button', 'id' => 'cmp03AddBtn', 'class' => '', 'icon' => 'fa fa-plus',
              'label' => 'إضافة', 'title' => 'إضافة صف جديد', 'attrs' => 'type="button"'),
    );
    $header_back = false;
    include '../includes/page_header.php';
    if (isset($_GET['msg'])) {
        echo '<div class="alert alert-info">' . htmlspecialchars((string) $_GET['msg'], ENT_QUOTES, 'UTF-8') . '</div>';
    }
    ?>

    <!-- فورم الإضافة الموحد (ems-forms) — مطويٌّ حتى زرِّ الرأس -->
    <form method="post" action="" class="allforms" id="cmp03AddForm">
        <input type="hidden" name="cmp03_action" value="add">
        <div class="card"><div class="card-header">
            <h5><i class="fa fa-plus"></i> إضافة — الإنتاج والقياس</h5>
        </div><div class="card-body">
            <div class="form-section"><div class="form-grid">
                <div class="form-group"><label for="emsf_875_b818e">رقم السجل</label>
                    <input type="text" name="f0" required maxlength="190" id="emsf_875_b818e"></div>
                <div class="form-group"><label for="emsf_876_e736f">التاريخ</label>
                    <input type="date" name="f1" id="emsf_876_e736f"></div>
                <div class="form-group"><label for="emsf_877_2f891">الموقع</label>
                    <input type="text" name="f2" maxlength="190" id="emsf_877_2f891"></div>
                <div class="form-group"><label for="emsf_878_0a378">العقد</label>
                    <input type="text" name="f3" maxlength="190" id="emsf_878_0a378"></div>
                <div class="form-group"><label for="emsf_879_831e7">بند العقد</label>
                    <input type="text" name="f4" maxlength="190" id="emsf_879_831e7"></div>
                <div class="form-group"><label for="emsf_880_0a128">نوع الإنتاج</label>
                    <input type="text" name="f5" maxlength="190" id="emsf_880_0a128"></div>
                <div class="form-group"><label for="emsf_881_b4760">جبهة العمل</label>
                    <input type="text" name="f6" maxlength="190" id="emsf_881_b4760"></div>
                <div class="form-group"><label for="emsf_882_88345">نقطة التفريغ</label>
                    <input type="text" name="f7" maxlength="190" id="emsf_882_88345"></div>
                <div class="form-group"><label for="emsf_883_c9c2a">طريقة القياس</label>
                    <input type="text" name="f8" maxlength="190" id="emsf_883_c9c2a"></div>
                <div class="form-group"><label for="emsf_884_761b2">الكمية حسب سجلنا</label>
                    <input type="text" inputmode="decimal" name="f9" placeholder="0" id="emsf_884_761b2"></div>
                <div class="form-group"><label for="emsf_885_bd7be">وحدة القياس</label>
                    <input type="text" name="f10" maxlength="190" id="emsf_885_bd7be"></div>
                <div class="form-group"><label for="emsf_886_aec55">الكمية المعتمدة من العميل</label>
                    <input type="text" inputmode="decimal" name="f11" placeholder="0" id="emsf_886_aec55"></div>
                <div class="form-group"><label for="emsf_887_95824">الفرق</label>
                    <input type="text" name="f12" maxlength="190" id="emsf_887_95824"></div>
                <div class="form-group"><label for="emsf_888_da283">سبب الفرق</label>
                    <input type="text" name="f13" maxlength="190" id="emsf_888_da283"></div>
                <div class="form-group"><label for="emsf_889_a8643">المستند المرفق</label>
                    <input type="text" name="f14" maxlength="190" id="emsf_889_a8643"></div>
                <div class="form-group"><label for="emsf_890_d250c">المُدخِل</label>
                    <input type="text" name="f15" maxlength="190" id="emsf_890_d250c"></div>
                <div class="form-group"><label for="emsf_891_24ef6">معتمد العميل</label>
                    <input type="text" name="f16" maxlength="190" id="emsf_891_24ef6"></div>
                <div class="form-group"><label for="emsf_892_d9ad8">الحالة</label>
                    <select name="f17" id="emsf_892_d9ad8"><option value="مسودة">مسودة</option><option value="قيد المراجعة">قيد المراجعة</option><option value="معتمد">معتمد</option><option value="موقوف">موقوف</option><option value="ملغي">ملغي</option></select></div>
                <div class="form-group"><label for="emsf_893_d5139">المعتمِد — الاسم والصفة</label>
                    <input type="text" name="f18" maxlength="190" id="emsf_893_d5139"></div>
                <div class="form-group"><label for="emsf_894_efc68">تاريخ الاعتماد</label>
                    <input type="date" name="f19" id="emsf_894_efc68"></div>
                <div class="form-group"><label for="emsf_895_bedd6">مرجع التفويض</label>
                    <input type="text" name="f20" maxlength="190" id="emsf_895_bedd6"></div>
                <div class="form-group"><label for="emsf_896_b6903">المرجع الأب</label>
                    <input type="text" name="f21" maxlength="190" id="emsf_896_b6903"></div>
                <div class="form-group"><label for="emsf_897_b2e63">درجة الأثر</label>
                    <input type="text" name="f22" maxlength="190" id="emsf_897_b2e63"></div>
            </div></div>
            <div style="margin-top:12px;display:flex;gap:10px">
                <button type="submit" class="btn-primary"><i class="fa fa-save"></i> حفظ</button>
                <button type="button" class="btn-secondary" id="cmp03CancelBtn"><i class="fa fa-times"></i> إلغاء</button>
            </div>
        </div></div>
    </form>

    <div class="card"><div class="card-body">
        <div class="table-responsive">
        <table class="alltables display" id="productionTable">
            <thead><tr>
            <th>رقم السجل</th>
            <th>التاريخ</th>
            <th>الموقع</th>
            <th>العقد</th>
            <th>بند العقد</th>
            <th>نوع الإنتاج</th>
            <th>جبهة العمل</th>
            <th>نقطة التفريغ</th>
            <th>طريقة القياس</th>
            <th>الكمية حسب سجلنا</th>
            <th>وحدة القياس</th>
            <th>الكمية المعتمدة من العميل</th>
            <th>الفرق</th>
            <th>سبب الفرق</th>
            <th class="ems-gov-th" data-gov="attached_doc" data-slice="3" title="مستند الإثبات المرفق">المستند المرفق</th>
            <th>المُدخِل</th>
            <th>معتمد العميل</th>
            <th class="ems-gov-th" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
            <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
            <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
            <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
            <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
            <th class="ems-gov-th none" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
            <th class="ems-gov-th none" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
            <th class="ems-gov-th none" data-gov="idem_key" data-slice="2" title="يمنع وقوع الأثر مرتين بمفتاح مركب">مفتاح منع التكرار</th>
            <th class="ems-gov-th none" data-gov="impact_grade" data-slice="2" title="مبدئي أم نهائي — فلا يقفل مبدئي ماليًّا">درجة الأثر</th>
            <th class="ems-gov-th none" data-gov="reversed_by" data-slice="2" title="مرجع الحركة التي عكسته">معكوس بـ</th>
            <th class="ems-gov-th none" data-gov="reversal_of" data-slice="2" title="مرجع الحركة التي عكسها">عكس عن</th>
            </tr></thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="28" class="text-center text-muted">لا بياناتَ بعدُ — أضف أول صفٍّ بزر «إضافة»</td></tr>
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
