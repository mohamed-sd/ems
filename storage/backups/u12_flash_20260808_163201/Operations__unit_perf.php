<?php
/**
 * Operations/unit_perf.php — الأداء الشهري للوحدة (CMP-03 ⑥ v2 — بتصميم SCR-DES حرفيًّا + إدخال حي)
 * ───────────────────────────────────────────────────────────────────────────
 * الورقة المالكة: 02 · إدارة التشغيل · الأعمدة 31 بترتيب المستند وطبقة
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
    header("Location: ../login.php?msg=غير+مصرح");
    exit();
}

require_once __DIR__ . '/../includes/cmp03_local_store.php'; // الموجة ٢ — الجدول الأصلي

$CANONICAL = 'unit_perf.php';
$COLS   = array (
  0 => 'الشهر',
  1 => 'العقد',
  2 => 'الوحدة التعاقدية',
  3 => 'كود المعدة',
  4 => 'الساعات التعاقدية',
  5 => 'ساعات التشغيل',
  6 => 'ساعات الاستعداد المفوتر',
  7 => 'ساعات التوقف — عميل',
  8 => 'ساعات التوقف — مورد',
  9 => 'ساعات التوقف — نحن',
  10 => 'فاقد غير منفَّذ',
  11 => 'توقف صيانة',
  12 => 'توقف موارد بشرية',
  13 => 'توقف تسويات',
  14 => 'قوة قاهرة',
  15 => 'صيانة مجدولة',
  16 => 'إجمالي التعطل',
  17 => 'الطرف المتحمل الأغلب',
  18 => 'نسبة الجاهزية',
  19 => 'العجز عن التعاقدي',
  20 => 'بند الجزاء',
  21 => 'قيمة الجزاء',
  22 => 'الحالة',
  23 => 'الكيان',
  24 => 'مفتاح منع التكرار',
  25 => 'درجة الأثر',
  26 => 'معكوس بـ',
  27 => 'عكس عن',
  28 => 'مركز التكلفة',
  29 => 'سعر الصرف ومصدره',
  30 => 'نسخة القاعدة المستعملة',
);
$FIELDS = array (
  0 => 'الشهر',
  1 => 'العقد',
  2 => 'الوحدة التعاقدية',
  3 => 'كود المعدة',
  4 => 'الساعات التعاقدية',
  5 => 'ساعات التشغيل',
  6 => 'ساعات الاستعداد المفوتر',
  7 => 'ساعات التوقف — عميل',
  8 => 'ساعات التوقف — مورد',
  9 => 'ساعات التوقف — نحن',
  10 => 'فاقد غير منفَّذ',
  11 => 'توقف صيانة',
  12 => 'توقف موارد بشرية',
  13 => 'توقف تسويات',
  14 => 'قوة قاهرة',
  15 => 'صيانة مجدولة',
  16 => 'إجمالي التعطل',
  17 => 'الطرف المتحمل الأغلب',
  18 => 'نسبة الجاهزية',
  19 => 'العجز عن التعاقدي',
  20 => 'بند الجزاء',
  21 => 'قيمة الجزاء',
  22 => 'الحالة',
  23 => 'درجة الأثر',
  24 => 'مركز التكلفة',
  25 => 'سعر الصرف ومصدره',
  26 => 'نسخة القاعدة المستعملة',
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
    header('Location: ' . basename(__FILE__) . '?msg=' . rawurlencode($ok ? 'حُفظ الصف ✅' : 'تعذر الحفظ ❌'));
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

$page_title = 'إيكوبيشن | الأداء الشهري للوحدة';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell" dir="rtl">
    <?php
    $header_title = 'الأداء الشهري للوحدة';
    $header_icon = 'fa fa-chart-area';
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
            <h5><i class="fa fa-plus"></i> إضافة — الأداء الشهري للوحدة</h5>
        </div><div class="card-body">
            <div class="form-section"><div class="form-grid">
                <div class="form-group"><label>الشهر</label>
                    <input type="text" name="f0" required maxlength="190"></div>
                <div class="form-group"><label>العقد</label>
                    <input type="text" name="f1" maxlength="190"></div>
                <div class="form-group"><label>الوحدة التعاقدية</label>
                    <input type="text" name="f2" maxlength="190"></div>
                <div class="form-group"><label>كود المعدة</label>
                    <input type="text" name="f3" maxlength="190"></div>
                <div class="form-group"><label>الساعات التعاقدية</label>
                    <input type="text" inputmode="decimal" name="f4" placeholder="0"></div>
                <div class="form-group"><label>ساعات التشغيل</label>
                    <input type="text" inputmode="decimal" name="f5" placeholder="0"></div>
                <div class="form-group"><label>ساعات الاستعداد المفوتر</label>
                    <input type="text" inputmode="decimal" name="f6" placeholder="0"></div>
                <div class="form-group"><label>ساعات التوقف — عميل</label>
                    <input type="text" inputmode="decimal" name="f7" placeholder="0"></div>
                <div class="form-group"><label>ساعات التوقف — مورد</label>
                    <input type="text" inputmode="decimal" name="f8" placeholder="0"></div>
                <div class="form-group"><label>ساعات التوقف — نحن</label>
                    <input type="text" inputmode="decimal" name="f9" placeholder="0"></div>
                <div class="form-group"><label>فاقد غير منفَّذ</label>
                    <input type="text" name="f10" maxlength="190"></div>
                <div class="form-group"><label>توقف صيانة</label>
                    <input type="text" name="f11" maxlength="190"></div>
                <div class="form-group"><label>توقف موارد بشرية</label>
                    <input type="text" name="f12" maxlength="190"></div>
                <div class="form-group"><label>توقف تسويات</label>
                    <input type="text" name="f13" maxlength="190"></div>
                <div class="form-group"><label>قوة قاهرة</label>
                    <input type="text" name="f14" maxlength="190"></div>
                <div class="form-group"><label>صيانة مجدولة</label>
                    <input type="text" name="f15" maxlength="190"></div>
                <div class="form-group"><label>إجمالي التعطل</label>
                    <input type="text" inputmode="decimal" name="f16" placeholder="0"></div>
                <div class="form-group"><label>الطرف المتحمل الأغلب</label>
                    <input type="text" name="f17" maxlength="190"></div>
                <div class="form-group"><label>نسبة الجاهزية</label>
                    <input type="text" inputmode="decimal" name="f18" placeholder="0"></div>
                <div class="form-group"><label>العجز عن التعاقدي</label>
                    <input type="text" name="f19" maxlength="190"></div>
                <div class="form-group"><label>بند الجزاء</label>
                    <input type="text" name="f20" maxlength="190"></div>
                <div class="form-group"><label>قيمة الجزاء</label>
                    <input type="text" inputmode="decimal" name="f21" placeholder="0"></div>
                <div class="form-group"><label>الحالة</label>
                    <select name="f22"><option value="مسودة">مسودة</option><option value="قيد المراجعة">قيد المراجعة</option><option value="معتمد">معتمد</option><option value="موقوف">موقوف</option><option value="ملغي">ملغي</option></select></div>
                <div class="form-group"><label>درجة الأثر</label>
                    <input type="text" name="f23" maxlength="190"></div>
                <div class="form-group"><label>مركز التكلفة</label>
                    <input type="text" inputmode="decimal" name="f24" placeholder="0"></div>
                <div class="form-group"><label>سعر الصرف ومصدره</label>
                    <input type="text" inputmode="decimal" name="f25" placeholder="0"></div>
                <div class="form-group"><label>نسخة القاعدة المستعملة</label>
                    <input type="text" name="f26" maxlength="190"></div>
            </div></div>
            <div style="margin-top:12px;display:flex;gap:10px">
                <button type="submit" class="btn-save"><i class="fa fa-save"></i> حفظ</button>
                <button type="button" class="btn-cancel" id="cmp03CancelBtn"><i class="fa fa-times"></i> إلغاء</button>
            </div>
        </div></div>
    </form>

    <div class="card"><div class="card-body">
        <div class="table-responsive">
        <table class="alltables display" id="unit_perfTable">
            <thead><tr>
            <th>الشهر</th>
            <th>العقد</th>
            <th>الوحدة التعاقدية</th>
            <th>كود المعدة</th>
            <th>الساعات التعاقدية</th>
            <th>ساعات التشغيل</th>
            <th>ساعات الاستعداد المفوتر</th>
            <th>ساعات التوقف — عميل</th>
            <th>ساعات التوقف — مورد</th>
            <th>ساعات التوقف — نحن</th>
            <th>فاقد غير منفَّذ</th>
            <th>توقف صيانة</th>
            <th>توقف موارد بشرية</th>
            <th>توقف تسويات</th>
            <th>قوة قاهرة</th>
            <th>صيانة مجدولة</th>
            <th>إجمالي التعطل</th>
            <th>الطرف المتحمل الأغلب</th>
            <th>نسبة الجاهزية</th>
            <th>العجز عن التعاقدي</th>
            <th>بند الجزاء</th>
            <th>قيمة الجزاء</th>
            <th class="ems-gov-th none" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
            <th class="ems-gov-th none" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
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
