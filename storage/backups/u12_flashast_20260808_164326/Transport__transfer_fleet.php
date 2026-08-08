<?php
/**
 * Transport/transfer_fleet.php — اللوابد والمركبات الناقلة (CMP-03 ⑥ v2 — بتصميم SCR-DES حرفيًّا + إدخال حي)
 * ───────────────────────────────────────────────────────────────────────────
 * الورقة المالكة: 04 · النقل والترحيل · الأعمدة 24 بترتيب المستند وطبقة
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
    ems_gov_flash_redirect('../login.php', 'غير مصرح', 'GOV-INFO-200', '');
    exit();
}

require_once __DIR__ . '/../includes/cmp03_local_store.php'; // الموجة ٢ — الجدول الأصلي

$CANONICAL = 'transfer_fleet.php';
$COLS   = array (
  0 => 'كود الناقل',
  1 => 'النوع',
  2 => 'الوصف',
  3 => 'الملكية',
  4 => 'المالك',
  5 => 'السعة القصوى',
  6 => 'رقم اللوحة',
  7 => 'الرخصة',
  8 => 'تاريخ انتهاء الرخصة',
  9 => 'التأمين',
  10 => 'تاريخ انتهاء التأمين',
  11 => 'السائق المكلَّف',
  12 => 'التعرفة',
  13 => 'العملة',
  14 => 'الحالة',
  15 => 'الكيان',
  16 => 'المُنشئ — الاسم والصفة',
  17 => 'تاريخ الإنشاء',
  18 => 'المعتمِد — الاسم والصفة',
  19 => 'تاريخ الاعتماد',
  20 => 'مرجع التفويض',
  21 => 'المرجع الأب',
  22 => 'المرفق',
  23 => 'سجل الاطّلاع',
);
$FIELDS = array (
  0 => 'كود الناقل',
  1 => 'النوع',
  2 => 'الوصف',
  3 => 'الملكية',
  4 => 'المالك',
  5 => 'السعة القصوى',
  6 => 'رقم اللوحة',
  7 => 'الرخصة',
  8 => 'تاريخ انتهاء الرخصة',
  9 => 'التأمين',
  10 => 'تاريخ انتهاء التأمين',
  11 => 'السائق المكلَّف',
  12 => 'التعرفة',
  13 => 'العملة',
  14 => 'الحالة',
  15 => 'المعتمِد — الاسم والصفة',
  16 => 'تاريخ الاعتماد',
  17 => 'مرجع التفويض',
  18 => 'المرجع الأب',
  19 => 'المرفق',
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

$page_title = 'إيكوبيشن | اللوابد والمركبات الناقلة';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell" dir="rtl">
    <?php
    $header_title = 'اللوابد والمركبات الناقلة';
    $header_icon = 'fa fa-trailer';
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
            <h5><i class="fa fa-plus"></i> إضافة — اللوابد والمركبات الناقلة</h5>
        </div><div class="card-body">
            <div class="form-section"><div class="form-grid">
                <div class="form-group"><label>كود الناقل</label>
                    <input type="text" name="f0" required maxlength="190"></div>
                <div class="form-group"><label>النوع</label>
                    <input type="text" name="f1" maxlength="190"></div>
                <div class="form-group"><label>الوصف</label>
                    <input type="text" name="f2" maxlength="190"></div>
                <div class="form-group"><label>الملكية</label>
                    <input type="text" name="f3" maxlength="190"></div>
                <div class="form-group"><label>المالك</label>
                    <input type="text" name="f4" maxlength="190"></div>
                <div class="form-group"><label>السعة القصوى</label>
                    <input type="text" name="f5" maxlength="190"></div>
                <div class="form-group"><label>رقم اللوحة</label>
                    <input type="text" name="f6" maxlength="190"></div>
                <div class="form-group"><label>الرخصة</label>
                    <input type="text" name="f7" maxlength="190"></div>
                <div class="form-group"><label>تاريخ انتهاء الرخصة</label>
                    <input type="date" name="f8"></div>
                <div class="form-group"><label>التأمين</label>
                    <input type="text" name="f9" maxlength="190"></div>
                <div class="form-group"><label>تاريخ انتهاء التأمين</label>
                    <input type="date" name="f10"></div>
                <div class="form-group"><label>السائق المكلَّف</label>
                    <input type="text" name="f11" maxlength="190"></div>
                <div class="form-group"><label>التعرفة</label>
                    <input type="text" name="f12" maxlength="190"></div>
                <div class="form-group"><label>العملة</label>
                    <input type="text" name="f13" maxlength="190"></div>
                <div class="form-group"><label>الحالة</label>
                    <select name="f14"><option value="مسودة">مسودة</option><option value="قيد المراجعة">قيد المراجعة</option><option value="معتمد">معتمد</option><option value="موقوف">موقوف</option><option value="ملغي">ملغي</option></select></div>
                <div class="form-group"><label>المعتمِد — الاسم والصفة</label>
                    <input type="text" name="f15" maxlength="190"></div>
                <div class="form-group"><label>تاريخ الاعتماد</label>
                    <input type="date" name="f16"></div>
                <div class="form-group"><label>مرجع التفويض</label>
                    <input type="text" name="f17" maxlength="190"></div>
                <div class="form-group"><label>المرجع الأب</label>
                    <input type="text" name="f18" maxlength="190"></div>
                <div class="form-group"><label>المرفق</label>
                    <input type="text" name="f19" maxlength="190"></div>
            </div></div>
            <div style="margin-top:12px;display:flex;gap:10px">
                <button type="submit" class="btn-save"><i class="fa fa-save"></i> حفظ</button>
                <button type="button" class="btn-cancel" id="cmp03CancelBtn"><i class="fa fa-times"></i> إلغاء</button>
            </div>
        </div></div>
    </form>

    <div class="card"><div class="card-body">
        <div class="table-responsive">
        <table class="alltables display" id="transfer_fleetTable">
            <thead><tr>
            <th>كود الناقل</th>
            <th>النوع</th>
            <th>الوصف</th>
            <th>الملكية</th>
            <th>المالك</th>
            <th>السعة القصوى</th>
            <th>رقم اللوحة</th>
            <th>الرخصة</th>
            <th>تاريخ انتهاء الرخصة</th>
            <th>التأمين</th>
            <th>تاريخ انتهاء التأمين</th>
            <th>السائق المكلَّف</th>
            <th>التعرفة</th>
            <th class="ems-gov-th" data-gov="currency" data-slice="3" title="لا مبلغ بلا عملة">العملة</th>
            <th class="ems-gov-th" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
            <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
            <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
            <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
            <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
            <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
            <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
            <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
            <th class="ems-gov-th none" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
            <th class="ems-gov-th none" data-gov="view_log" data-slice="2" title="من قرأ البيان الحساس ومتى">سجل الاطّلاع</th>
            </tr></thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="24" class="text-center text-muted">لا بياناتَ بعدُ — أضف أول صفٍّ بزر «إضافة»</td></tr>
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
