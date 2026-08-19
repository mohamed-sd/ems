<?php
/**
 * Operations/monthly_close.php — الإقفال الشهري للوحدة (CMP-03 ⑥ v2 — بتصميم SCR-DES حرفيًّا + إدخال حي)
 * ───────────────────────────────────────────────────────────────────────────
 * الورقة المالكة: 01 · إدارة الموقع · الأعمدة 31 بترتيب المستند وطبقة
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

$CANONICAL = 'monthly_close.php';
$COLS   = array (
  0 => 'رقم المحضر',
  1 => 'الشهر',
  2 => 'العقد',
  3 => 'الموقع',
  4 => 'الوحدة التعاقدية',
  5 => 'الساعات التعاقدية',
  6 => 'الساعات حسب سجلنا',
  7 => 'الساعات المعتمدة من العميل',
  8 => 'الفرق',
  9 => 'سبب الفرق',
  10 => 'الكمية المنفَّذة',
  11 => 'الكمية المعتمدة',
  12 => 'قيمة الاستحقاق',
  13 => 'العملة',
  14 => 'أعدّه',
  15 => 'اعتمده التشغيل',
  16 => 'اعتمدته المالية',
  17 => 'تاريخ الإقفال',
  18 => 'الحالة',
  19 => 'الكيان',
  20 => 'تاريخ الإنشاء',
  21 => 'تاريخ الاعتماد',
  22 => 'مرجع التفويض',
  23 => 'المرجع الأب',
  24 => 'المرفق',
  25 => 'مفتاح منع التكرار',
  26 => 'درجة الأثر',
  27 => 'معكوس بـ',
  28 => 'عكس عن',
  29 => 'مركز التكلفة',
  30 => 'سعر الصرف ومصدره',
);
$FIELDS = array (
  0 => 'رقم المحضر',
  1 => 'الشهر',
  2 => 'العقد',
  3 => 'الموقع',
  4 => 'الوحدة التعاقدية',
  5 => 'الساعات التعاقدية',
  6 => 'الساعات حسب سجلنا',
  7 => 'الساعات المعتمدة من العميل',
  8 => 'الفرق',
  9 => 'سبب الفرق',
  10 => 'الكمية المنفَّذة',
  11 => 'الكمية المعتمدة',
  12 => 'قيمة الاستحقاق',
  13 => 'العملة',
  14 => 'أعدّه',
  15 => 'اعتمده التشغيل',
  16 => 'اعتمدته المالية',
  17 => 'تاريخ الإقفال',
  18 => 'الحالة',
  19 => 'تاريخ الاعتماد',
  20 => 'مرجع التفويض',
  21 => 'المرجع الأب',
  22 => 'المرفق',
  23 => 'درجة الأثر',
  24 => 'مركز التكلفة',
  25 => 'سعر الصرف ومصدره',
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

$page_title = 'إيكوبيشن | الإقفال الشهري للوحدة';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<?php /* نُقلت أنماطُ هذه الشاشةِ إلى assets/css/ems-screens.css (UXUI-01 البند ٦: صفرُ نمطٍ محليّ) */ ?>
<div class="main ems-unified-page-shell ems-doc-cycle" dir="rtl">
    <?php
    $header_title = 'الإقفال الشهري للوحدة';
    $header_icon = 'fa fa-lock';
    $header_actions = array(
        array('tag' => 'button', 'id' => 'cmp03AddBtn', 'class' => '', 'icon' => 'fa fa-plus',
              'label' => 'إضافة', 'title' => 'إضافة صف جديد', 'attrs' => 'type="button"'),
    );
    $header_back = false;
    include '../includes/page_header.php';
    if (isset($_GET['msg'])) {
        echo '<div class="alert alert-info">' . htmlspecialchars((string) $_GET['msg'], ENT_QUOTES, 'UTF-8') . '</div>';
    }
    echo ems_states_bundle('لا محاضرَ إقفالٍ شهريٍّ بعدُ', 'أضف أولَ محضرٍ بزرِّ «إضافة» في رأسِ الشاشة');
    /* بوابة ١٢: الإقفالُ الشهريُّ دورةٌ مستندية — خطوتُها التاليةُ معلَنة */
    echo ems_next_step('اعتمادُ التشغيلِ ثم اعتمادُ الماليةِ لمحضرِ الإقفال');
    ?>

    <!-- فورم الإضافة الموحد (ems-forms) — مطويٌّ حتى زرِّ الرأس -->
    <form method="post" action="" class="allforms" id="cmp03AddForm">
        <?= csrf_field() ?>
        <input type="hidden" name="cmp03_action" value="add">
        <div class="card"><div class="card-header">
            <h5><i class="fa fa-plus"></i> إضافة — الإقفال الشهري للوحدة</h5>
        </div><div class="card-body">
            <div class="form-section"><div class="form-grid">
                <div class="form-group"><label for="emsf_848_12e0d">رقم المحضر</label>
                    <input type="text" name="f0" required maxlength="190" id="emsf_848_12e0d"></div>
                <div class="form-group"><label for="emsf_849_8ca81">الشهر</label>
                    <input type="text" name="f1" maxlength="190" id="emsf_849_8ca81"></div>
                <div class="form-group"><label for="emsf_850_96f14">العقد</label>
                    <input type="text" name="f2" maxlength="190" id="emsf_850_96f14"></div>
                <div class="form-group"><label for="emsf_851_fd630">الموقع</label>
                    <input type="text" name="f3" maxlength="190" id="emsf_851_fd630"></div>
                <div class="form-group"><label for="emsf_852_4348a">الوحدة التعاقدية</label>
                    <input type="text" name="f4" maxlength="190" id="emsf_852_4348a"></div>
                <div class="form-group"><label for="emsf_853_41e17">الساعات التعاقدية</label>
                    <input type="text" inputmode="decimal" name="f5" placeholder="0" id="emsf_853_41e17"></div>
                <div class="form-group"><label for="emsf_854_c33ff">الساعات حسب سجلنا</label>
                    <input type="text" inputmode="decimal" name="f6" placeholder="0" id="emsf_854_c33ff"></div>
                <div class="form-group"><label for="emsf_855_01f3c">الساعات المعتمدة من العميل</label>
                    <input type="text" inputmode="decimal" name="f7" placeholder="0" id="emsf_855_01f3c"></div>
                <div class="form-group"><label for="emsf_856_421b4">الفرق</label>
                    <input type="text" name="f8" maxlength="190" id="emsf_856_421b4"></div>
                <div class="form-group"><label for="emsf_857_945ad">سبب الفرق</label>
                    <input type="text" name="f9" maxlength="190" id="emsf_857_945ad"></div>
                <div class="form-group"><label for="emsf_858_f638c">الكمية المنفَّذة</label>
                    <input type="text" inputmode="decimal" name="f10" placeholder="0" id="emsf_858_f638c"></div>
                <div class="form-group"><label for="emsf_859_e0ebd">الكمية المعتمدة</label>
                    <input type="text" inputmode="decimal" name="f11" placeholder="0" id="emsf_859_e0ebd"></div>
                <div class="form-group"><label for="emsf_860_8f998">قيمة الاستحقاق</label>
                    <input type="text" inputmode="decimal" name="f12" placeholder="0" id="emsf_860_8f998"></div>
                <div class="form-group"><label for="emsf_861_ab6d1">العملة</label>
                    <input type="text" name="f13" maxlength="190" id="emsf_861_ab6d1"></div>
                <div class="form-group"><label for="emsf_862_94110">أعدّه</label>
                    <input type="text" name="f14" maxlength="190" id="emsf_862_94110"></div>
                <div class="form-group"><label for="emsf_863_1d451">اعتمده التشغيل</label>
                    <input type="text" name="f15" maxlength="190" id="emsf_863_1d451"></div>
                <div class="form-group"><label for="emsf_864_6ee4d">اعتمدته المالية</label>
                    <input type="text" name="f16" maxlength="190" id="emsf_864_6ee4d"></div>
                <div class="form-group"><label for="emsf_865_6611e">تاريخ الإقفال</label>
                    <input type="date" name="f17" id="emsf_865_6611e"></div>
                <div class="form-group"><label for="emsf_866_e8142">الحالة</label>
                    <select name="f18" id="emsf_866_e8142"><option value="مسودة">مسودة</option><option value="قيد المراجعة">قيد المراجعة</option><option value="معتمد">معتمد</option><option value="موقوف">موقوف</option><option value="ملغي">ملغي</option></select></div>
                <div class="form-group"><label for="emsf_867_74c47">تاريخ الاعتماد</label>
                    <input type="date" name="f19" id="emsf_867_74c47"></div>
                <div class="form-group"><label for="emsf_868_d402a">مرجع التفويض</label>
                    <input type="text" name="f20" maxlength="190" id="emsf_868_d402a"></div>
                <div class="form-group"><label for="emsf_869_c4b07">المرجع الأب</label>
                    <input type="text" name="f21" maxlength="190" id="emsf_869_c4b07"></div>
                <div class="form-group"><label for="emsf_870_32b88">المرفق</label>
                    <input type="text" name="f22" maxlength="190" id="emsf_870_32b88"></div>
                <div class="form-group"><label for="emsf_871_5cd9d">درجة الأثر</label>
                    <input type="text" name="f23" maxlength="190" id="emsf_871_5cd9d"></div>
                <div class="form-group"><label for="emsf_872_a5331">مركز التكلفة</label>
                    <input type="text" inputmode="decimal" name="f24" placeholder="0" id="emsf_872_a5331"></div>
                <div class="form-group"><label for="emsf_873_9b1ab">سعر الصرف ومصدره</label>
                    <input type="text" inputmode="decimal" name="f25" placeholder="0" id="emsf_873_9b1ab"></div>
            </div></div>
            <div class="cmp03-form-actions">
                <button type="submit" class="btn-primary"><i class="fa fa-save"></i> حفظ</button>
                <button type="button" class="btn-secondary" id="cmp03CancelBtn"><i class="fa fa-times"></i> إلغاء</button>
            </div>
        </div></div>
    </form>

    <div class="card"><div class="card-body">
        <div class="table-responsive">
        <table class="alltables display" id="monthly_closeTable">
            <thead><tr>
            <th>رقم المحضر</th>
            <th>الشهر</th>
            <th>العقد</th>
            <th>الموقع</th>
            <th>الوحدة التعاقدية</th>
            <th>الساعات التعاقدية</th>
            <th>الساعات حسب سجلنا</th>
            <th>الساعات المعتمدة من العميل</th>
            <th>الفرق</th>
            <th>سبب الفرق</th>
            <th>الكمية المنفَّذة</th>
            <th>الكمية المعتمدة</th>
            <th>قيمة الاستحقاق</th>
            <th class="ems-gov-th" data-gov="currency" data-slice="3" title="لا مبلغ بلا عملة">العملة</th>
            <th>أعدّه</th>
            <th>اعتمده التشغيل</th>
            <th>اعتمدته المالية</th>
            <th>تاريخ الإقفال</th>
            <th class="ems-gov-th" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
            <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
            <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
            <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
            <th class="ems-gov-th none" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
            <th class="ems-gov-th none" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
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
                <tr><td colspan="31" class="text-center text-muted">لا بياناتَ بعدُ — أضف أول صفٍّ بزر «إضافة»</td></tr>
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
