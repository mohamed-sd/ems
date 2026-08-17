<?php
/**
 * Transport/transfer_permits.php — تصاريح المسار والحمولة (CMP-03 ⑥ v2 — بتصميم SCR-DES حرفيًّا + إدخال حي)
 * ───────────────────────────────────────────────────────────────────────────
 * الورقة المالكة: 04 · النقل والترحيل · الأعمدة 22 بترتيب المستند وطبقة
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

$CANONICAL = 'transfer_permits.php';
$COLS   = array (
  0 => 'رقم التصريح',
  1 => 'أمر الترحيل',
  2 => 'الجهة المصدِرة',
  3 => 'نوع التصريح',
  4 => 'المسار المصرَّح',
  5 => 'الحمولة المصرَّحة',
  6 => 'الوزن الإجمالي',
  7 => 'تاريخ الإصدار',
  8 => 'تاريخ الانتهاء',
  9 => 'الرسوم',
  10 => 'المرفق',
  11 => 'استخرجه',
  12 => 'الحالة',
  13 => 'الكيان',
  14 => 'المُنشئ — الاسم والصفة',
  15 => 'تاريخ الإنشاء',
  16 => 'المعتمِد — الاسم والصفة',
  17 => 'تاريخ الاعتماد',
  18 => 'مرجع التفويض',
  19 => 'المرجع الأب',
  20 => 'مركز التكلفة',
  21 => 'سعر الصرف ومصدره',
);
$FIELDS = array (
  0 => 'رقم التصريح',
  1 => 'أمر الترحيل',
  2 => 'الجهة المصدِرة',
  3 => 'نوع التصريح',
  4 => 'المسار المصرَّح',
  5 => 'الحمولة المصرَّحة',
  6 => 'الوزن الإجمالي',
  7 => 'تاريخ الإصدار',
  8 => 'تاريخ الانتهاء',
  9 => 'الرسوم',
  10 => 'المرفق',
  11 => 'استخرجه',
  12 => 'الحالة',
  13 => 'المعتمِد — الاسم والصفة',
  14 => 'تاريخ الاعتماد',
  15 => 'مرجع التفويض',
  16 => 'المرجع الأب',
  17 => 'مركز التكلفة',
  18 => 'سعر الصرف ومصدره',
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

$page_title = 'إيكوبيشن | تصاريح المسار والحمولة';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell" dir="rtl">
    <?php
    $header_title = 'تصاريح المسار والحمولة';
    $header_icon = 'fa fa-road';
    $header_actions = array(
        array('tag' => 'button', 'id' => 'cmp03AddBtn', 'class' => '', 'icon' => 'fa fa-plus',
              'label' => 'إضافة', 'title' => 'إضافة صف جديد', 'attrs' => 'type="button"'),
    );
    $header_back = false;
    include '../includes/page_header.php';
    if (isset($_GET['msg'])) {
        echo '<div class="alert alert-info">' . htmlspecialchars((string) $_GET['msg'], ENT_QUOTES, 'UTF-8') . '</div>';
    }
    /* حزمةُ الحالاتِ الدنيا (بوابة ٩): تحميلٌ وفراغٌ وخطأٌ — مخفيةٌ افتراضًا */
    echo ems_states_bundle('لا تصاريحَ نقلٍ مسجَّلةً بعد',
        'أضفِ التصريحَ بزر «إضافة» — برقمِه ومسارِه المصرَّحِ وحمولتِه ومدةِ سريانِه');
    ?>
    <style>
        .tp-actions { margin-top: 12px; display: flex; gap: 10px; }
    </style>

    <!-- فورم الإضافة الموحد (ems-forms) — مطويٌّ حتى زرِّ الرأس -->
    <form method="post" action="" class="allforms" id="cmp03AddForm">
        <?= csrf_field() ?>
        <input type="hidden" name="cmp03_action" value="add">
        <div class="card"><div class="card-header">
            <h5><i class="fa fa-plus"></i> إضافة — تصاريح المسار والحمولة</h5>
        </div><div class="card-body">
            <div class="form-section"><div class="form-grid">
                <div class="form-group"><label for="emsf_1577_195fc">رقم التصريح</label>
                    <input type="text" name="f0" required maxlength="190" id="emsf_1577_195fc"></div>
                <div class="form-group"><label for="emsf_1578_88fa0">أمر الترحيل</label>
                    <input type="text" name="f1" maxlength="190" id="emsf_1578_88fa0"></div>
                <div class="form-group"><label for="emsf_1579_1778d">الجهة المصدِرة</label>
                    <input type="text" name="f2" maxlength="190" id="emsf_1579_1778d"></div>
                <div class="form-group"><label for="emsf_1580_1080e">نوع التصريح</label>
                    <input type="text" name="f3" maxlength="190" id="emsf_1580_1080e"></div>
                <div class="form-group"><label for="emsf_1581_8e40f">المسار المصرَّح</label>
                    <input type="text" name="f4" maxlength="190" id="emsf_1581_8e40f"></div>
                <div class="form-group"><label for="emsf_1582_1660c">الحمولة المصرَّحة</label>
                    <input type="text" inputmode="decimal" name="f5" placeholder="0" id="emsf_1582_1660c"></div>
                <div class="form-group"><label for="emsf_1583_06ef6">الوزن الإجمالي</label>
                    <input type="text" inputmode="decimal" name="f6" placeholder="0" id="emsf_1583_06ef6"></div>
                <div class="form-group"><label for="emsf_1584_4f30d">تاريخ الإصدار</label>
                    <input type="date" name="f7" id="emsf_1584_4f30d"></div>
                <div class="form-group"><label for="emsf_1585_797ac">تاريخ الانتهاء</label>
                    <input type="date" name="f8" id="emsf_1585_797ac"></div>
                <div class="form-group"><label for="emsf_1586_30370">الرسوم</label>
                    <input type="text" name="f9" maxlength="190" id="emsf_1586_30370"></div>
                <div class="form-group"><label for="emsf_1587_b4532">المرفق</label>
                    <input type="text" name="f10" maxlength="190" id="emsf_1587_b4532"></div>
                <div class="form-group"><label for="emsf_1588_38a60">استخرجه</label>
                    <input type="text" name="f11" maxlength="190" id="emsf_1588_38a60"></div>
                <div class="form-group"><label for="emsf_1589_dfe46">الحالة</label>
                    <select name="f12" id="emsf_1589_dfe46"><option value="مسودة">مسودة</option><option value="قيد المراجعة">قيد المراجعة</option><option value="معتمد">معتمد</option><option value="موقوف">موقوف</option><option value="ملغي">ملغي</option></select></div>
                <div class="form-group"><label for="emsf_1590_e9ba6">المعتمِد — الاسم والصفة</label>
                    <input type="text" name="f13" maxlength="190" id="emsf_1590_e9ba6"></div>
                <div class="form-group"><label for="emsf_1591_47193">تاريخ الاعتماد</label>
                    <input type="date" name="f14" id="emsf_1591_47193"></div>
                <div class="form-group"><label for="emsf_1592_6f051">مرجع التفويض</label>
                    <input type="text" name="f15" maxlength="190" id="emsf_1592_6f051"></div>
                <div class="form-group"><label for="emsf_1593_413ef">المرجع الأب</label>
                    <input type="text" name="f16" maxlength="190" id="emsf_1593_413ef"></div>
                <div class="form-group"><label for="emsf_1594_67684">مركز التكلفة</label>
                    <input type="text" inputmode="decimal" name="f17" placeholder="0" id="emsf_1594_67684"></div>
                <div class="form-group"><label for="emsf_1595_e25e9">سعر الصرف ومصدره</label>
                    <input type="text" inputmode="decimal" name="f18" placeholder="0" id="emsf_1595_e25e9"></div>
            </div></div>
            <div class="tp-actions">
                <button type="submit" class="btn-primary"><i class="fa fa-save"></i> حفظ</button>
                <button type="button" class="btn-secondary" id="cmp03CancelBtn"><i class="fa fa-times"></i> إلغاء</button>
            </div>
        </div></div>
    </form>

    <div class="card"><div class="card-body">
        <div class="table-responsive">
        <table class="alltables display" id="transfer_permitsTable">
            <thead><tr>
            <th>رقم التصريح</th>
            <th>أمر الترحيل</th>
            <th>الجهة المصدِرة</th>
            <th>نوع التصريح</th>
            <th>المسار المصرَّح</th>
            <th>الحمولة المصرَّحة</th>
            <th>الوزن الإجمالي</th>
            <th>تاريخ الإصدار</th>
            <th>تاريخ الانتهاء</th>
            <th>الرسوم</th>
            <th class="ems-gov-th" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
            <th>استخرجه</th>
            <th class="ems-gov-th" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
            <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
            <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
            <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
            <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
            <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
            <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
            <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
            <th class="ems-gov-th" data-gov="cost_center" data-slice="3" title="وجهة التحميل">مركز التكلفة</th>
            <th class="ems-gov-th" data-gov="fx_rate_source" data-slice="3" title="ما خالف عملة الدفاتر يحمل السعر ومصدره">سعر الصرف ومصدره</th>
            </tr></thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="22" class="text-center text-muted">لا بياناتَ بعدُ — أضف أول صفٍّ بزر «إضافة»</td></tr>
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
