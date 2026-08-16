<?php
// شواهد المتطلبات (AC-E06-03 · موجة ٣): SCN-1073 · SCN-1074 · SCN-1075 · SCN-1076 · SCN-1077 · SCN-1078 · SCN-1079 · SCN-1080
/**
 * Governance/canonical_names.php — سجل الأسماء المعتمدة (CMP-03 ⑥ v2 — بتصميم SCR-DES حرفيًّا + إدخال حي)
 * ───────────────────────────────────────────────────────────────────────────
 * الورقة المالكة: 15 · الحوكمة والالتزام · الأعمدة 19 بترتيب المستند وطبقة
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

$CANONICAL = 'canonical_names.php';

// حارس الشاشة (M-14 BR-GOV-01): can_view من modules — والسوبر يمر
$__pp = check_page_permissions($conn, 'Governance/canonical_names.php');
if (!$is_super_admin && empty($__pp['can_view'])) {
    require_once __DIR__ . '/../includes/perm_explain_live.php';
    $__why = ems_deny_message($conn, intval($_SESSION['user']['role'] ?? 0), 'Governance/canonical_names.php');
    ems_gov_flash_redirect('../main/dashboard.php', $__why, 'GOV-INFO-200', '');
    exit();
}
if (!$is_super_admin && $_SERVER['REQUEST_METHOD'] === 'POST' && empty($__pp['can_add']) && empty($__pp['can_edit'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'غير مصرح بالكتابة في هذه الشاشة ❌', 'GOV-PERM-403', 'اطلب المنحةَ من مدير الصلاحيات إن كانت ضمن عملك');
}
$COLS   = array (
  0 => 'الكيان',
  1 => 'رقم السجل',
  2 => 'نوع الكيان',
  3 => 'الاسم المعتمد',
  4 => 'الاسم القانوني الكامل',
  5 => 'المرادفات المسجَّلة',
  6 => 'عدد المرادفات',
  7 => 'الاسم في السجل التجاري',
  8 => 'الرقم الضريبي',
  9 => 'كود الكيان الموحَّد',
  10 => 'حالة الفحص',
  11 => 'تكرار مكتشف',
  12 => 'قرار الدمج',
  13 => 'السجلات المحوَّلة',
  14 => 'تاريخ الدمج',
  15 => 'المُنشئ — الاسم والصفة',
  16 => 'المعتمِد — الاسم والصفة',
  17 => 'تاريخ الاعتماد',
  18 => 'الحالة',
);
$FIELDS = array (
  0 => 'رقم السجل',
  1 => 'نوع الكيان',
  2 => 'الاسم المعتمد',
  3 => 'الاسم القانوني الكامل',
  4 => 'المرادفات المسجَّلة',
  5 => 'عدد المرادفات',
  6 => 'الاسم في السجل التجاري',
  7 => 'الرقم الضريبي',
  8 => 'كود الكيان الموحَّد',
  9 => 'حالة الفحص',
  10 => 'تكرار مكتشف',
  11 => 'قرار الدمج',
  12 => 'السجلات المحوَّلة',
  13 => 'تاريخ الدمج',
  14 => 'المعتمِد — الاسم والصفة',
  15 => 'تاريخ الاعتماد',
  16 => 'الحالة',
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

$page_title = 'إيكوبيشن | سجل الأسماء المعتمدة';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($__pp) ? $__pp : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell" dir="rtl">
    <?php
    $header_title = 'سجل الأسماء المعتمدة';
    $header_icon = 'fa fa-spell-check';
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
            <h5><i class="fa fa-plus"></i> إضافة — سجل الأسماء المعتمدة</h5>
        </div><div class="card-body">
            <div class="form-section"><div class="form-grid">
                <div class="form-group"><label for="emsf_543_0e0ad">رقم السجل</label>
                    <input type="text" name="f0" required maxlength="190" id="emsf_543_0e0ad"></div>
                <div class="form-group"><label for="emsf_544_af64f">نوع الكيان</label>
                    <input type="text" name="f1" maxlength="190" id="emsf_544_af64f"></div>
                <div class="form-group"><label for="emsf_545_54b64">الاسم المعتمد</label>
                    <input type="text" name="f2" maxlength="190" id="emsf_545_54b64"></div>
                <div class="form-group"><label for="emsf_546_150b2">الاسم القانوني الكامل</label>
                    <input type="text" name="f3" maxlength="190" id="emsf_546_150b2"></div>
                <div class="form-group"><label for="emsf_547_22598">المرادفات المسجَّلة</label>
                    <input type="text" name="f4" maxlength="190" id="emsf_547_22598"></div>
                <div class="form-group"><label for="emsf_548_70bdf">عدد المرادفات</label>
                    <input type="text" inputmode="decimal" name="f5" placeholder="0" id="emsf_548_70bdf"></div>
                <div class="form-group"><label for="emsf_549_fc4ee">الاسم في السجل التجاري</label>
                    <input type="text" name="f6" maxlength="190" id="emsf_549_fc4ee"></div>
                <div class="form-group"><label for="emsf_550_23e01">الرقم الضريبي</label>
                    <input type="text" name="f7" maxlength="190" id="emsf_550_23e01"></div>
                <div class="form-group"><label for="emsf_551_3b0ae">كود الكيان الموحَّد</label>
                    <input type="text" name="f8" maxlength="190" id="emsf_551_3b0ae"></div>
                <div class="form-group"><label for="emsf_552_54334">حالة الفحص</label>
                    <input type="text" name="f9" maxlength="190" id="emsf_552_54334"></div>
                <div class="form-group"><label for="emsf_553_3301a">تكرار مكتشف</label>
                    <input type="text" name="f10" maxlength="190" id="emsf_553_3301a"></div>
                <div class="form-group"><label for="emsf_554_59684">قرار الدمج</label>
                    <input type="text" name="f11" maxlength="190" id="emsf_554_59684"></div>
                <div class="form-group"><label for="emsf_555_e0d4d">السجلات المحوَّلة</label>
                    <input type="text" name="f12" maxlength="190" id="emsf_555_e0d4d"></div>
                <div class="form-group"><label for="emsf_556_8e6bb">تاريخ الدمج</label>
                    <input type="date" name="f13" id="emsf_556_8e6bb"></div>
                <div class="form-group"><label for="emsf_557_3da1c">المعتمِد — الاسم والصفة</label>
                    <input type="text" name="f14" maxlength="190" id="emsf_557_3da1c"></div>
                <div class="form-group"><label for="emsf_558_051eb">تاريخ الاعتماد</label>
                    <input type="date" name="f15" id="emsf_558_051eb"></div>
                <div class="form-group"><label for="emsf_559_e8561">الحالة</label>
                    <select name="f16" id="emsf_559_e8561"><option value="مسودة">مسودة</option><option value="قيد المراجعة">قيد المراجعة</option><option value="معتمد">معتمد</option><option value="موقوف">موقوف</option><option value="ملغي">ملغي</option></select></div>
            </div></div>
            <div style="margin-top:12px;display:flex;gap:10px">
                <button type="submit" class="btn-primary"><i class="fa fa-save"></i> حفظ</button>
                <button type="button" class="btn-secondary" id="cmp03CancelBtn"><i class="fa fa-times"></i> إلغاء</button>
            </div>
        </div></div>
    </form>

    <div class="card"><div class="card-body">
        <div class="table-responsive">
        <table class="alltables display" id="canonical_namesTable">
            <thead><tr>
            <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
            <th>رقم السجل</th>
            <th>نوع الكيان</th>
            <th>الاسم المعتمد</th>
            <th>الاسم القانوني الكامل</th>
            <th>المرادفات المسجَّلة</th>
            <th>عدد المرادفات</th>
            <th>الاسم في السجل التجاري</th>
            <th>الرقم الضريبي</th>
            <th>كود الكيان الموحَّد</th>
            <th>حالة الفحص</th>
            <th>تكرار مكتشف</th>
            <th>قرار الدمج</th>
            <th>السجلات المحوَّلة</th>
            <th>تاريخ الدمج</th>
            <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
            <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
            <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
            <th class="ems-gov-th" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
            </tr></thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="19" class="text-center text-muted">لا بياناتَ بعدُ — أضف أول صفٍّ بزر «إضافة»</td></tr>
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
