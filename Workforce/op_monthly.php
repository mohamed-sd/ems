<?php
/**
 * Workforce/op_monthly.php — الأداء الشهري للمشغّل (CMP-03 ⑥ v2 — بتصميم SCR-DES حرفيًّا + إدخال حي)
 * ───────────────────────────────────────────────────────────────────────────
 * الورقة المالكة: 09 · القوى التشغيلية · الأعمدة 25 بترتيب المستند وطبقة
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

$CANONICAL = 'op_monthly.php';
$COLS   = array (
  0 => 'الشهر',
  1 => 'كود المشغّل',
  2 => 'المعدة',
  3 => 'الموقع',
  4 => 'أيام العمل',
  5 => 'أيام الإجازة',
  6 => 'أيام الغياب',
  7 => 'ساعات التشغيل',
  8 => 'ساعات إضافية',
  9 => 'ساعات الاستعداد',
  10 => 'الإنتاج المنسوب',
  11 => 'نسبة الإنجاز',
  12 => 'أساس الحافز',
  13 => 'قيمة الحافز',
  14 => 'العملة',
  15 => 'اعتمده',
  16 => 'الحالة',
  17 => 'الكيان',
  18 => 'مفتاح منع التكرار',
  19 => 'درجة الأثر',
  20 => 'معكوس بـ',
  21 => 'عكس عن',
  22 => 'مركز التكلفة',
  23 => 'سعر الصرف ومصدره',
  24 => 'نسخة القاعدة المستعملة',
);
$FIELDS = array (
  0 => 'الشهر',
  1 => 'كود المشغّل',
  2 => 'المعدة',
  3 => 'الموقع',
  4 => 'أيام العمل',
  5 => 'أيام الإجازة',
  6 => 'أيام الغياب',
  7 => 'ساعات التشغيل',
  8 => 'ساعات إضافية',
  9 => 'ساعات الاستعداد',
  10 => 'الإنتاج المنسوب',
  11 => 'نسبة الإنجاز',
  12 => 'أساس الحافز',
  13 => 'قيمة الحافز',
  14 => 'العملة',
  15 => 'اعتمده',
  16 => 'الحالة',
  17 => 'درجة الأثر',
  18 => 'مركز التكلفة',
  19 => 'سعر الصرف ومصدره',
  20 => 'نسخة القاعدة المستعملة',
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

$page_title = 'إيكوبيشن | الأداء الشهري للمشغّل';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell" dir="rtl">
    <?php
    $header_title = 'الأداء الشهري للمشغّل';
    $header_icon = 'fa fa-chart-simple';
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
            <h5><i class="fa fa-plus"></i> إضافة — الأداء الشهري للمشغّل</h5>
        </div><div class="card-body">
            <div class="form-section"><div class="form-grid">
                <div class="form-group"><label for="emsf_1712_ee536">الشهر</label>
                    <input type="text" name="f0" required maxlength="190" id="emsf_1712_ee536"></div>
                <div class="form-group"><label for="emsf_1713_4aa48">كود المشغّل</label>
                    <input type="text" name="f1" maxlength="190" id="emsf_1713_4aa48"></div>
                <div class="form-group"><label for="emsf_1714_01193">المعدة</label>
                    <input type="text" name="f2" maxlength="190" id="emsf_1714_01193"></div>
                <div class="form-group"><label for="emsf_1715_acf45">الموقع</label>
                    <input type="text" name="f3" maxlength="190" id="emsf_1715_acf45"></div>
                <div class="form-group"><label for="emsf_1716_fbc00">أيام العمل</label>
                    <input type="text" name="f4" maxlength="190" id="emsf_1716_fbc00"></div>
                <div class="form-group"><label for="emsf_1717_f0a6f">أيام الإجازة</label>
                    <input type="text" name="f5" maxlength="190" id="emsf_1717_f0a6f"></div>
                <div class="form-group"><label for="emsf_1718_48bf5">أيام الغياب</label>
                    <input type="text" name="f6" maxlength="190" id="emsf_1718_48bf5"></div>
                <div class="form-group"><label for="emsf_1719_a2584">ساعات التشغيل</label>
                    <input type="text" inputmode="decimal" name="f7" placeholder="0" id="emsf_1719_a2584"></div>
                <div class="form-group"><label for="emsf_1720_39e7c">ساعات إضافية</label>
                    <input type="text" inputmode="decimal" name="f8" placeholder="0" id="emsf_1720_39e7c"></div>
                <div class="form-group"><label for="emsf_1721_cfa1c">ساعات الاستعداد</label>
                    <input type="text" inputmode="decimal" name="f9" placeholder="0" id="emsf_1721_cfa1c"></div>
                <div class="form-group"><label for="emsf_1722_9e9e5">الإنتاج المنسوب</label>
                    <input type="text" name="f10" maxlength="190" id="emsf_1722_9e9e5"></div>
                <div class="form-group"><label for="emsf_1723_acaa6">نسبة الإنجاز</label>
                    <input type="text" inputmode="decimal" name="f11" placeholder="0" id="emsf_1723_acaa6"></div>
                <div class="form-group"><label for="emsf_1724_cd2d9">أساس الحافز</label>
                    <input type="text" name="f12" maxlength="190" id="emsf_1724_cd2d9"></div>
                <div class="form-group"><label for="emsf_1725_72261">قيمة الحافز</label>
                    <input type="text" inputmode="decimal" name="f13" placeholder="0" id="emsf_1725_72261"></div>
                <div class="form-group"><label for="emsf_1726_69ddf">العملة</label>
                    <input type="text" name="f14" maxlength="190" id="emsf_1726_69ddf"></div>
                <div class="form-group"><label for="emsf_1727_64fb7">اعتمده</label>
                    <input type="text" name="f15" maxlength="190" id="emsf_1727_64fb7"></div>
                <div class="form-group"><label for="emsf_1728_6c4a4">الحالة</label>
                    <select name="f16" id="emsf_1728_6c4a4"><option value="مسودة">مسودة</option><option value="قيد المراجعة">قيد المراجعة</option><option value="معتمد">معتمد</option><option value="موقوف">موقوف</option><option value="ملغي">ملغي</option></select></div>
                <div class="form-group"><label for="emsf_1729_ffd74">درجة الأثر</label>
                    <input type="text" name="f17" maxlength="190" id="emsf_1729_ffd74"></div>
                <div class="form-group"><label for="emsf_1730_3afb2">مركز التكلفة</label>
                    <input type="text" inputmode="decimal" name="f18" placeholder="0" id="emsf_1730_3afb2"></div>
                <div class="form-group"><label for="emsf_1731_931c5">سعر الصرف ومصدره</label>
                    <input type="text" inputmode="decimal" name="f19" placeholder="0" id="emsf_1731_931c5"></div>
                <div class="form-group"><label for="emsf_1732_1d8a4">نسخة القاعدة المستعملة</label>
                    <input type="text" name="f20" maxlength="190" id="emsf_1732_1d8a4"></div>
            </div></div>
            <div style="margin-top:12px;display:flex;gap:10px">
                <button type="submit" class="btn-save"><i class="fa fa-save"></i> حفظ</button>
                <button type="button" class="btn-cancel" id="cmp03CancelBtn"><i class="fa fa-times"></i> إلغاء</button>
            </div>
        </div></div>
    </form>

    <div class="card"><div class="card-body">
        <div class="table-responsive">
        <table class="alltables display" id="op_monthlyTable">
            <thead><tr>
            <th>الشهر</th>
            <th>كود المشغّل</th>
            <th>المعدة</th>
            <th>الموقع</th>
            <th>أيام العمل</th>
            <th>أيام الإجازة</th>
            <th>أيام الغياب</th>
            <th>ساعات التشغيل</th>
            <th>ساعات إضافية</th>
            <th>ساعات الاستعداد</th>
            <th>الإنتاج المنسوب</th>
            <th>نسبة الإنجاز</th>
            <th>أساس الحافز</th>
            <th>قيمة الحافز</th>
            <th class="ems-gov-th" data-gov="currency" data-slice="3" title="لا مبلغ بلا عملة">العملة</th>
            <th>اعتمده</th>
            <th class="ems-gov-th" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
            <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
            <th class="ems-gov-th" data-gov="idem_key" data-slice="2" title="يمنع وقوع الأثر مرتين بمفتاح مركب">مفتاح منع التكرار</th>
            <th class="ems-gov-th" data-gov="impact_grade" data-slice="2" title="مبدئي أم نهائي — فلا يقفل مبدئي ماليًّا">درجة الأثر</th>
            <th class="ems-gov-th" data-gov="reversed_by" data-slice="2" title="مرجع الحركة التي عكسته">معكوس بـ</th>
            <th class="ems-gov-th" data-gov="reversal_of" data-slice="2" title="مرجع الحركة التي عكسها">عكس عن</th>
            <th class="ems-gov-th none" data-gov="cost_center" data-slice="3" title="وجهة التحميل">مركز التكلفة</th>
            <th class="ems-gov-th none" data-gov="fx_rate_source" data-slice="3" title="ما خالف عملة الدفاتر يحمل السعر ومصدره">سعر الصرف ومصدره</th>
            <th class="ems-fn-th none" data-fn="1">نسخة القاعدة المستعملة</th>
            </tr></thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="25" class="text-center text-muted">لا بياناتَ بعدُ — أضف أول صفٍّ بزر «إضافة»</td></tr>
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
