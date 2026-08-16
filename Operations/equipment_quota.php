<?php
/**
 * Operations/equipment_quota.php — توزيع وحدات المورد على معداته (CMP-03 ⑥ v2 — بتصميم SCR-DES حرفيًّا + إدخال حي)
 * ───────────────────────────────────────────────────────────────────────────
 * الورقة المالكة: 02 · إدارة التشغيل · الأعمدة 28 بترتيب المستند وطبقة
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

$CANONICAL = 'equipment_quota.php';
$COLS   = array (
  0 => 'الكيان',
  1 => 'رقم الحاوية',
  2 => 'المورد',
  3 => 'العقد العميل',
  4 => 'عقد المورد',
  5 => 'نموذج العمل',
  6 => 'وحدة العمل',
  7 => 'نوع المعدة',
  8 => 'كود المعدة',
  9 => 'دور المعدة',
  10 => 'حصة المورد الكلية',
  11 => 'الحصة المخصَّصة للمعدة',
  12 => 'عدد الورديات',
  13 => 'وحدات الوردية',
  14 => 'الوحدات الشهرية للمعدة',
  15 => 'مجموع حصص معدات المورد',
  16 => 'المتبقي من حصة المورد',
  17 => 'المنفَّذ فعليًّا',
  18 => 'الفارق',
  19 => 'سبب الفارق',
  20 => 'تاريخ السريان',
  21 => 'تاريخ الانتهاء',
  22 => 'مفتاح منع التكرار',
  23 => 'المُنشئ — الاسم والصفة',
  24 => 'المعتمِد — الاسم والصفة',
  25 => 'تاريخ الاعتماد',
  26 => 'معكوس بـ',
  27 => 'الحالة',
);
$FIELDS = array (
  0 => 'رقم الحاوية',
  1 => 'المورد',
  2 => 'العقد العميل',
  3 => 'عقد المورد',
  4 => 'نموذج العمل',
  5 => 'وحدة العمل',
  6 => 'نوع المعدة',
  7 => 'كود المعدة',
  8 => 'دور المعدة',
  9 => 'حصة المورد الكلية',
  10 => 'الحصة المخصَّصة للمعدة',
  11 => 'عدد الورديات',
  12 => 'وحدات الوردية',
  13 => 'الوحدات الشهرية للمعدة',
  14 => 'مجموع حصص معدات المورد',
  15 => 'المتبقي من حصة المورد',
  16 => 'المنفَّذ فعليًّا',
  17 => 'الفارق',
  18 => 'سبب الفارق',
  19 => 'تاريخ السريان',
  20 => 'تاريخ الانتهاء',
  21 => 'المعتمِد — الاسم والصفة',
  22 => 'تاريخ الاعتماد',
  23 => 'الحالة',
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

$page_title = 'إيكوبيشن | توزيع وحدات المورد على معداته';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell" dir="rtl">
    <?php
    $header_title = 'توزيع وحدات المورد على معداته';
    $header_icon = 'fa fa-chart-pie';
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
        <?= csrf_field() ?>
        <input type="hidden" name="cmp03_action" value="add">
        <div class="card"><div class="card-header">
            <h5><i class="fa fa-plus"></i> إضافة — توزيع وحدات المورد على معداته</h5>
        </div><div class="card-body">
            <div class="form-section"><div class="form-grid">
                <div class="form-group"><label for="emsf_809_041ac">رقم الحاوية</label>
                    <input type="text" name="f0" required maxlength="190" id="emsf_809_041ac"></div>
                <div class="form-group"><label for="emsf_810_aba42">المورد</label>
                    <input type="text" name="f1" maxlength="190" id="emsf_810_aba42"></div>
                <div class="form-group"><label for="emsf_811_f09d2">العقد العميل</label>
                    <input type="text" name="f2" maxlength="190" id="emsf_811_f09d2"></div>
                <div class="form-group"><label for="emsf_812_e1d72">عقد المورد</label>
                    <input type="text" name="f3" maxlength="190" id="emsf_812_e1d72"></div>
                <div class="form-group"><label for="emsf_813_cc398">نموذج العمل</label>
                    <input type="text" name="f4" maxlength="190" id="emsf_813_cc398"></div>
                <div class="form-group"><label for="emsf_814_515cc">وحدة العمل</label>
                    <input type="text" name="f5" maxlength="190" id="emsf_814_515cc"></div>
                <div class="form-group"><label for="emsf_815_fa0e6">نوع المعدة</label>
                    <input type="text" name="f6" maxlength="190" id="emsf_815_fa0e6"></div>
                <div class="form-group"><label for="emsf_816_f539a">كود المعدة</label>
                    <input type="text" name="f7" maxlength="190" id="emsf_816_f539a"></div>
                <div class="form-group"><label for="emsf_817_c4750">دور المعدة</label>
                    <input type="text" name="f8" maxlength="190" id="emsf_817_c4750"></div>
                <div class="form-group"><label for="emsf_818_015a4">حصة المورد الكلية</label>
                    <input type="text" name="f9" maxlength="190" id="emsf_818_015a4"></div>
                <div class="form-group"><label for="emsf_819_4dbe7">الحصة المخصَّصة للمعدة</label>
                    <input type="text" name="f10" maxlength="190" id="emsf_819_4dbe7"></div>
                <div class="form-group"><label for="emsf_820_1bbbd">عدد الورديات</label>
                    <input type="text" inputmode="decimal" name="f11" placeholder="0" id="emsf_820_1bbbd"></div>
                <div class="form-group"><label for="emsf_821_84f20">وحدات الوردية</label>
                    <input type="text" name="f12" maxlength="190" id="emsf_821_84f20"></div>
                <div class="form-group"><label for="emsf_822_52de3">الوحدات الشهرية للمعدة</label>
                    <input type="text" name="f13" maxlength="190" id="emsf_822_52de3"></div>
                <div class="form-group"><label for="emsf_823_41d11">مجموع حصص معدات المورد</label>
                    <input type="text" name="f14" maxlength="190" id="emsf_823_41d11"></div>
                <div class="form-group"><label for="emsf_824_2e0dd">المتبقي من حصة المورد</label>
                    <input type="text" name="f15" maxlength="190" id="emsf_824_2e0dd"></div>
                <div class="form-group"><label for="emsf_825_3d32b">المنفَّذ فعليًّا</label>
                    <input type="text" name="f16" maxlength="190" id="emsf_825_3d32b"></div>
                <div class="form-group"><label for="emsf_826_99c99">الفارق</label>
                    <input type="text" name="f17" maxlength="190" id="emsf_826_99c99"></div>
                <div class="form-group"><label for="emsf_827_8f3fd">سبب الفارق</label>
                    <input type="text" name="f18" maxlength="190" id="emsf_827_8f3fd"></div>
                <div class="form-group"><label for="emsf_828_04740">تاريخ السريان</label>
                    <input type="date" name="f19" id="emsf_828_04740"></div>
                <div class="form-group"><label for="emsf_829_e527f">تاريخ الانتهاء</label>
                    <input type="date" name="f20" id="emsf_829_e527f"></div>
                <div class="form-group"><label for="emsf_830_cd476">المعتمِد — الاسم والصفة</label>
                    <input type="text" name="f21" maxlength="190" id="emsf_830_cd476"></div>
                <div class="form-group"><label for="emsf_831_968a9">تاريخ الاعتماد</label>
                    <input type="date" name="f22" id="emsf_831_968a9"></div>
                <div class="form-group"><label for="emsf_832_f2366">الحالة</label>
                    <select name="f23" id="emsf_832_f2366"><option value="مسودة">مسودة</option><option value="قيد المراجعة">قيد المراجعة</option><option value="معتمد">معتمد</option><option value="موقوف">موقوف</option><option value="ملغي">ملغي</option></select></div>
            </div></div>
            <div style="margin-top:12px;display:flex;gap:10px">
                <button type="submit" class="btn-primary"><i class="fa fa-save"></i> حفظ</button>
                <button type="button" class="btn-secondary" id="cmp03CancelBtn"><i class="fa fa-times"></i> إلغاء</button>
            </div>
        </div></div>
    </form>

    <div class="card"><div class="card-body">
        <div class="table-responsive">
        <table class="alltables display" id="equipment_quotaTable">
            <thead><tr>
            <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
            <th>رقم الحاوية</th>
            <th>المورد</th>
            <th>العقد العميل</th>
            <th>عقد المورد</th>
            <th>نموذج العمل</th>
            <th>وحدة العمل</th>
            <th>نوع المعدة</th>
            <th>كود المعدة</th>
            <th>دور المعدة</th>
            <th>حصة المورد الكلية</th>
            <th>الحصة المخصَّصة للمعدة</th>
            <th>عدد الورديات</th>
            <th>وحدات الوردية</th>
            <th>الوحدات الشهرية للمعدة</th>
            <th>مجموع حصص معدات المورد</th>
            <th>المتبقي من حصة المورد</th>
            <th>المنفَّذ فعليًّا</th>
            <th>الفارق</th>
            <th>سبب الفارق</th>
            <th>تاريخ السريان</th>
            <th>تاريخ الانتهاء</th>
            <th class="ems-gov-th none" data-gov="idem_key" data-slice="2" title="يمنع وقوع الأثر مرتين بمفتاح مركب">مفتاح منع التكرار</th>
            <th class="ems-gov-th none" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
            <th class="ems-gov-th none" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
            <th class="ems-gov-th none" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
            <th class="ems-gov-th none" data-gov="reversed_by" data-slice="2" title="مرجع الحركة التي عكسته">معكوس بـ</th>
            <th class="ems-gov-th none" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
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
