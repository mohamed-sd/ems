<?php
/**
 * Workforce/deductions.php — الخصومات والجزاءات (CMP-03 ⑥ v2 — بتصميم SCR-DES حرفيًّا + إدخال حي)
 * ───────────────────────────────────────────────────────────────────────────
 * الورقة المالكة: 09 · القوى التشغيلية · الأعمدة 30 بترتيب المستند وطبقة
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

$CANONICAL = 'deductions.php';
$COLS   = array (
  0 => 'رقم القرار',
  1 => 'كود الموظف',
  2 => 'الشهر',
  3 => 'نوع الخصم',
  4 => 'سبب الخصم',
  5 => 'بند السياسة المرجعي',
  6 => 'المستند المؤيد',
  7 => 'الأساس',
  8 => 'المعادلة',
  9 => 'قيمة الخصم',
  10 => 'العملة',
  11 => 'نسبة من الصافي',
  12 => 'اقترحه',
  13 => 'راجعته الموارد',
  14 => 'اعتماد الإدارة',
  15 => 'الاعتماد المالي',
  16 => 'اعتماد الإدارة العامة',
  17 => 'المسيّر',
  18 => 'الحالة',
  19 => 'الكيان',
  20 => 'تاريخ الإنشاء',
  21 => 'تاريخ الاعتماد',
  22 => 'مرجع التفويض',
  23 => 'مفتاح منع التكرار',
  24 => 'درجة الأثر',
  25 => 'معكوس بـ',
  26 => 'عكس عن',
  27 => 'مركز التكلفة',
  28 => 'سعر الصرف ومصدره',
  29 => 'نسخة القاعدة المستعملة',
);
$FIELDS = array (
  0 => 'رقم القرار',
  1 => 'كود الموظف',
  2 => 'الشهر',
  3 => 'نوع الخصم',
  4 => 'سبب الخصم',
  5 => 'بند السياسة المرجعي',
  6 => 'المستند المؤيد',
  7 => 'الأساس',
  8 => 'المعادلة',
  9 => 'قيمة الخصم',
  10 => 'العملة',
  11 => 'نسبة من الصافي',
  12 => 'اقترحه',
  13 => 'راجعته الموارد',
  14 => 'اعتماد الإدارة',
  15 => 'الاعتماد المالي',
  16 => 'اعتماد الإدارة العامة',
  17 => 'المسيّر',
  18 => 'الحالة',
  19 => 'تاريخ الاعتماد',
  20 => 'مرجع التفويض',
  21 => 'درجة الأثر',
  22 => 'مركز التكلفة',
  23 => 'سعر الصرف ومصدره',
  24 => 'نسخة القاعدة المستعملة',
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

$page_title = 'إيكوبيشن | الخصومات والجزاءات';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell" dir="rtl">
    <?php
    $header_title = 'الخصومات والجزاءات';
    $header_icon = 'fa fa-circle-minus';
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
            <h5><i class="fa fa-plus"></i> إضافة — الخصومات والجزاءات</h5>
        </div><div class="card-body">
            <div class="form-section"><div class="form-grid">
                <div class="form-group"><label for="emsf_1650_2d6ba">رقم القرار</label>
                    <input type="text" name="f0" required maxlength="190" id="emsf_1650_2d6ba"></div>
                <div class="form-group"><label for="emsf_1651_30502">كود الموظف</label>
                    <input type="text" name="f1" maxlength="190" id="emsf_1651_30502"></div>
                <div class="form-group"><label for="emsf_1652_99cec">الشهر</label>
                    <input type="text" name="f2" maxlength="190" id="emsf_1652_99cec"></div>
                <div class="form-group"><label for="emsf_1653_ab52e">نوع الخصم</label>
                    <input type="text" name="f3" maxlength="190" id="emsf_1653_ab52e"></div>
                <div class="form-group"><label for="emsf_1654_30700">سبب الخصم</label>
                    <input type="text" name="f4" maxlength="190" id="emsf_1654_30700"></div>
                <div class="form-group"><label for="emsf_1655_ae373">بند السياسة المرجعي</label>
                    <input type="text" name="f5" maxlength="190" id="emsf_1655_ae373"></div>
                <div class="form-group"><label for="emsf_1656_35627">المستند المؤيد</label>
                    <input type="text" name="f6" maxlength="190" id="emsf_1656_35627"></div>
                <div class="form-group"><label for="emsf_1657_d73c6">الأساس</label>
                    <input type="text" name="f7" maxlength="190" id="emsf_1657_d73c6"></div>
                <div class="form-group"><label for="emsf_1658_77501">المعادلة</label>
                    <input type="text" name="f8" maxlength="190" id="emsf_1658_77501"></div>
                <div class="form-group"><label for="emsf_1659_48d7f">قيمة الخصم</label>
                    <input type="text" inputmode="decimal" name="f9" placeholder="0" id="emsf_1659_48d7f"></div>
                <div class="form-group"><label for="emsf_1660_2ee30">العملة</label>
                    <input type="text" name="f10" maxlength="190" id="emsf_1660_2ee30"></div>
                <div class="form-group"><label for="emsf_1661_ff066">نسبة من الصافي</label>
                    <input type="text" inputmode="decimal" name="f11" placeholder="0" id="emsf_1661_ff066"></div>
                <div class="form-group"><label for="emsf_1662_032f4">اقترحه</label>
                    <input type="text" name="f12" maxlength="190" id="emsf_1662_032f4"></div>
                <div class="form-group"><label for="emsf_1663_5329b">راجعته الموارد</label>
                    <input type="text" name="f13" maxlength="190" id="emsf_1663_5329b"></div>
                <div class="form-group"><label for="emsf_1664_023a3">اعتماد الإدارة</label>
                    <input type="text" name="f14" maxlength="190" id="emsf_1664_023a3"></div>
                <div class="form-group"><label for="emsf_1665_64451">الاعتماد المالي</label>
                    <input type="text" name="f15" maxlength="190" id="emsf_1665_64451"></div>
                <div class="form-group"><label for="emsf_1666_15b60">اعتماد الإدارة العامة</label>
                    <input type="text" name="f16" maxlength="190" id="emsf_1666_15b60"></div>
                <div class="form-group"><label for="emsf_1667_ab63c">المسيّر</label>
                    <input type="text" name="f17" maxlength="190" id="emsf_1667_ab63c"></div>
                <div class="form-group"><label for="emsf_1668_33208">الحالة</label>
                    <select name="f18" id="emsf_1668_33208"><option value="مسودة">مسودة</option><option value="قيد المراجعة">قيد المراجعة</option><option value="معتمد">معتمد</option><option value="موقوف">موقوف</option><option value="ملغي">ملغي</option></select></div>
                <div class="form-group"><label for="emsf_1669_842f5">تاريخ الاعتماد</label>
                    <input type="date" name="f19" id="emsf_1669_842f5"></div>
                <div class="form-group"><label for="emsf_1670_a4f09">مرجع التفويض</label>
                    <input type="text" name="f20" maxlength="190" id="emsf_1670_a4f09"></div>
                <div class="form-group"><label for="emsf_1671_67ac6">درجة الأثر</label>
                    <input type="text" name="f21" maxlength="190" id="emsf_1671_67ac6"></div>
                <div class="form-group"><label for="emsf_1672_21169">مركز التكلفة</label>
                    <input type="text" inputmode="decimal" name="f22" placeholder="0" id="emsf_1672_21169"></div>
                <div class="form-group"><label for="emsf_1673_940ef">سعر الصرف ومصدره</label>
                    <input type="text" inputmode="decimal" name="f23" placeholder="0" id="emsf_1673_940ef"></div>
                <div class="form-group"><label for="emsf_1674_7fb2a">نسخة القاعدة المستعملة</label>
                    <input type="text" name="f24" maxlength="190" id="emsf_1674_7fb2a"></div>
            </div></div>
            <div style="margin-top:12px;display:flex;gap:10px">
                <button type="submit" class="btn-primary"><i class="fa fa-save"></i> حفظ</button>
                <button type="button" class="btn-secondary" id="cmp03CancelBtn"><i class="fa fa-times"></i> إلغاء</button>
            </div>
        </div></div>
    </form>

    <div class="card"><div class="card-body">
        <div class="table-responsive">
        <table class="alltables display" id="deductionsTable">
            <thead><tr>
            <th>رقم القرار</th>
            <th>كود الموظف</th>
            <th>الشهر</th>
            <th>نوع الخصم</th>
            <th>سبب الخصم</th>
            <th>بند السياسة المرجعي</th>
            <th>المستند المؤيد</th>
            <th>الأساس</th>
            <th>المعادلة</th>
            <th>قيمة الخصم</th>
            <th class="ems-gov-th" data-gov="currency" data-slice="3" title="لا مبلغ بلا عملة">العملة</th>
            <th>نسبة من الصافي</th>
            <th>اقترحه</th>
            <th>راجعته الموارد</th>
            <th>اعتماد الإدارة</th>
            <th>الاعتماد المالي</th>
            <th>اعتماد الإدارة العامة</th>
            <th>المسيّر</th>
            <th class="ems-gov-th" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
            <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
            <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
            <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
            <th class="ems-gov-th none" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
            <th class="ems-gov-th none" data-gov="idem_key" data-slice="2" title="يمنع وقوع الأثر مرتين بمفتاح مركب">مفتاح منع التكرار</th>
            <th class="ems-gov-th none" data-gov="impact_grade" data-slice="2" title="مبدئي أم نهائي — فلا يقفل مبدئي ماليًّا">درجة الأثر</th>
            <th class="ems-gov-th none" data-gov="reversed_by" data-slice="2" title="مرجع الحركة التي عكسته">معكوس بـ</th>
            <th class="ems-gov-th none" data-gov="reversal_of" data-slice="2" title="مرجع الحركة التي عكسها">عكس عن</th>
            <th class="ems-gov-th none" data-gov="cost_center" data-slice="3" title="وجهة التحميل">مركز التكلفة</th>
            <th class="ems-gov-th none" data-gov="fx_rate_source" data-slice="3" title="ما خالف عملة الدفاتر يحمل السعر ومصدره">سعر الصرف ومصدره</th>
            <th class="ems-fn-th none" data-fn="1">نسخة القاعدة المستعملة</th>
            </tr></thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="30" class="text-center text-muted">لا بياناتَ بعدُ — أضف أول صفٍّ بزر «إضافة»</td></tr>
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
