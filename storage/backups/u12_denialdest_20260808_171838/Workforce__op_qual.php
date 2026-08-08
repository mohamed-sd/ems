<?php
/**
 * Workforce/op_qual.php — تأهيل المشغّلين على أنواع المعدات (CMP-03 ⑥ v2 — بتصميم SCR-DES حرفيًّا + إدخال حي)
 * ───────────────────────────────────────────────────────────────────────────
 * الورقة المالكة: 09 · القوى التشغيلية · الأعمدة 21 بترتيب المستند وطبقة
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
    ems_gov_flash_redirect('../login.php', 'غير مصرح', 'GOV-PERM-403', '');
    exit();
}

require_once __DIR__ . '/../includes/cmp03_local_store.php'; // الموجة ٢ — الجدول الأصلي

$CANONICAL = 'op_qual.php';
$COLS   = array (
  0 => 'رقم التأهيل',
  1 => 'كود المشغّل',
  2 => 'نوع المعدة',
  3 => 'الموديل',
  4 => 'مستوى التأهيل',
  5 => 'جهة التأهيل',
  6 => 'رقم الشهادة',
  7 => 'تاريخ الإصدار',
  8 => 'تاريخ الانتهاء',
  9 => 'ساعات الخبرة على النوع',
  10 => 'المقيِّم',
  11 => 'تاريخ آخر تقييم',
  12 => 'الحالة',
  13 => 'الكيان',
  14 => 'المُنشئ — الاسم والصفة',
  15 => 'تاريخ الإنشاء',
  16 => 'المعتمِد — الاسم والصفة',
  17 => 'تاريخ الاعتماد',
  18 => 'مرجع التفويض',
  19 => 'المرجع الأب',
  20 => 'المرفق',
);
$FIELDS = array (
  0 => 'رقم التأهيل',
  1 => 'كود المشغّل',
  2 => 'نوع المعدة',
  3 => 'الموديل',
  4 => 'مستوى التأهيل',
  5 => 'جهة التأهيل',
  6 => 'رقم الشهادة',
  7 => 'تاريخ الإصدار',
  8 => 'تاريخ الانتهاء',
  9 => 'ساعات الخبرة على النوع',
  10 => 'المقيِّم',
  11 => 'تاريخ آخر تقييم',
  12 => 'الحالة',
  13 => 'المعتمِد — الاسم والصفة',
  14 => 'تاريخ الاعتماد',
  15 => 'مرجع التفويض',
  16 => 'المرجع الأب',
  17 => 'المرفق',
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

$page_title = 'إيكوبيشن | تأهيل المشغّلين على أنواع المعدات';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell" dir="rtl">
    <?php
    $header_title = 'تأهيل المشغّلين على أنواع المعدات';
    $header_icon = 'fa fa-graduation-cap';
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
            <h5><i class="fa fa-plus"></i> إضافة — تأهيل المشغّلين على أنواع المعدات</h5>
        </div><div class="card-body">
            <div class="form-section"><div class="form-grid">
                <div class="form-group"><label>رقم التأهيل</label>
                    <input type="text" name="f0" required maxlength="190"></div>
                <div class="form-group"><label>كود المشغّل</label>
                    <input type="text" name="f1" maxlength="190"></div>
                <div class="form-group"><label>نوع المعدة</label>
                    <input type="text" name="f2" maxlength="190"></div>
                <div class="form-group"><label>الموديل</label>
                    <input type="text" name="f3" maxlength="190"></div>
                <div class="form-group"><label>مستوى التأهيل</label>
                    <input type="text" name="f4" maxlength="190"></div>
                <div class="form-group"><label>جهة التأهيل</label>
                    <input type="text" name="f5" maxlength="190"></div>
                <div class="form-group"><label>رقم الشهادة</label>
                    <input type="text" name="f6" maxlength="190"></div>
                <div class="form-group"><label>تاريخ الإصدار</label>
                    <input type="date" name="f7"></div>
                <div class="form-group"><label>تاريخ الانتهاء</label>
                    <input type="date" name="f8"></div>
                <div class="form-group"><label>ساعات الخبرة على النوع</label>
                    <input type="text" inputmode="decimal" name="f9" placeholder="0"></div>
                <div class="form-group"><label>المقيِّم</label>
                    <input type="text" name="f10" maxlength="190"></div>
                <div class="form-group"><label>تاريخ آخر تقييم</label>
                    <input type="date" name="f11"></div>
                <div class="form-group"><label>الحالة</label>
                    <select name="f12"><option value="مسودة">مسودة</option><option value="قيد المراجعة">قيد المراجعة</option><option value="معتمد">معتمد</option><option value="موقوف">موقوف</option><option value="ملغي">ملغي</option></select></div>
                <div class="form-group"><label>المعتمِد — الاسم والصفة</label>
                    <input type="text" name="f13" maxlength="190"></div>
                <div class="form-group"><label>تاريخ الاعتماد</label>
                    <input type="date" name="f14"></div>
                <div class="form-group"><label>مرجع التفويض</label>
                    <input type="text" name="f15" maxlength="190"></div>
                <div class="form-group"><label>المرجع الأب</label>
                    <input type="text" name="f16" maxlength="190"></div>
                <div class="form-group"><label>المرفق</label>
                    <input type="text" name="f17" maxlength="190"></div>
            </div></div>
            <div style="margin-top:12px;display:flex;gap:10px">
                <button type="submit" class="btn-save"><i class="fa fa-save"></i> حفظ</button>
                <button type="button" class="btn-cancel" id="cmp03CancelBtn"><i class="fa fa-times"></i> إلغاء</button>
            </div>
        </div></div>
    </form>

    <div class="card"><div class="card-body">
        <div class="table-responsive">
        <table class="alltables display" id="op_qualTable">
            <thead><tr>
            <th>رقم التأهيل</th>
            <th>كود المشغّل</th>
            <th>نوع المعدة</th>
            <th>الموديل</th>
            <th>مستوى التأهيل</th>
            <th>جهة التأهيل</th>
            <th>رقم الشهادة</th>
            <th>تاريخ الإصدار</th>
            <th>تاريخ الانتهاء</th>
            <th>ساعات الخبرة على النوع</th>
            <th>المقيِّم</th>
            <th>تاريخ آخر تقييم</th>
            <th class="ems-gov-th" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
            <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
            <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
            <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
            <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
            <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
            <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
            <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
            <th class="ems-gov-th" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
            </tr></thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="21" class="text-center text-muted">لا بياناتَ بعدُ — أضف أول صفٍّ بزر «إضافة»</td></tr>
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
