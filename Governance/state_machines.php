<?php
// شواهد المتطلبات (AC-E06-03 · موجة ٣): SCN-923 · SCN-927 · SCN-928 · SCN-929 · SCN-930 · SCN-931 · SCN-932 · SCN-933 · SCN-934 · SCN-935
/**
 * Governance/state_machines.php — حالات المستندات وانتقالاتها (CMP-03 ⑥ v2 — بتصميم SCR-DES حرفيًّا + إدخال حي)
 * ───────────────────────────────────────────────────────────────────────────
 * الورقة المالكة: 15 · الحوكمة والالتزام · الأعمدة 18 بترتيب المستند وطبقة
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

$CANONICAL = 'state_machines.php';

// حارس الشاشة (M-14 BR-GOV-01): can_view من modules — والسوبر يمر
$__pp = check_page_permissions($conn, 'Governance/state_machines.php');
if (!$is_super_admin && empty($__pp['can_view'])) {
    require_once __DIR__ . '/../includes/perm_explain_live.php';
    $__why = ems_deny_message($conn, intval($_SESSION['user']['role'] ?? 0), 'Governance/state_machines.php');
    ems_gov_flash_redirect('../main/dashboard.php', $__why, 'GOV-INFO-200', '');
    exit();
}
if (!$is_super_admin && $_SERVER['REQUEST_METHOD'] === 'POST' && empty($__pp['can_add']) && empty($__pp['can_edit'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'غير مصرح بالكتابة في هذه الشاشة ❌', 'GOV-PERM-403', 'اطلب المنحةَ من مدير الصلاحيات إن كانت ضمن عملك');
}
$COLS   = array (
  0 => 'الكيان',
  1 => 'كود الآلة',
  2 => 'نوع المستند',
  3 => 'رقم الانتقال',
  4 => 'من حالة',
  5 => 'إلى حالة',
  6 => 'الفعل المُطلق',
  7 => 'رمز الفعل',
  8 => 'المخوَّل',
  9 => 'الشرط المسبق',
  10 => 'الحارس المطبَّق',
  11 => 'الحدث المنشور',
  12 => 'قابل للعكس؟',
  13 => 'فعل العكس',
  14 => 'تاريخ السريان',
  15 => 'النسخة',
  16 => 'المُنشئ — الاسم والصفة',
  17 => 'الحالة',
);
$FIELDS = array (
  0 => 'كود الآلة',
  1 => 'نوع المستند',
  2 => 'رقم الانتقال',
  3 => 'من حالة',
  4 => 'إلى حالة',
  5 => 'الفعل المُطلق',
  6 => 'رمز الفعل',
  7 => 'المخوَّل',
  8 => 'الشرط المسبق',
  9 => 'الحارس المطبَّق',
  10 => 'الحدث المنشور',
  11 => 'قابل للعكس؟',
  12 => 'فعل العكس',
  13 => 'تاريخ السريان',
  14 => 'النسخة',
  15 => 'الحالة',
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

$page_title = 'إيكوبيشن | حالات المستندات وانتقالاتها';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($__pp) ? $__pp : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
// UXW-01 بوابة ٩: حالاتُ التحميلِ والفراغِ والخطأِ من المكوّنِ المركزيِّ ux_components
if (function_exists('ems_states_bundle')) {
    echo ems_states_bundle('لا انتقالاتِ حالاتٍ مسجَّلةً بعدُ', 'أضف أولَ انتقالٍ بزرِّ «إضافة» أعلى الشاشة');
}
?>
<div class="main ems-unified-page-shell" dir="rtl">
    <?php
    $header_title = 'حالات المستندات وانتقالاتها';
    $header_icon = 'fa fa-shuffle';
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

    <?php /* نُقلت أنماطُ هذه الشاشةِ إلى assets/css/ems-screens.css (UXUI-01 البند ٦: صفرُ نمطٍ محليّ) */ ?>

    <!-- فورم الإضافة الموحد (ems-forms) — مطويٌّ حتى زرِّ الرأس -->
    <form method="post" action="" class="allforms" id="cmp03AddForm">
        <?= csrf_field() ?>
        <input type="hidden" name="cmp03_action" value="add">
        <div class="card"><div class="card-header">
            <h5><i class="fa fa-plus"></i> إضافة — حالات المستندات وانتقالاتها</h5>
        </div><div class="card-body">
            <div class="form-section"><div class="form-grid">
                <div class="form-group"><label for="emsf_663_de4f5">كود الآلة</label>
                    <input type="text" name="f0" required maxlength="190" id="emsf_663_de4f5"></div>
                <div class="form-group"><label for="emsf_664_3a27e">نوع المستند</label>
                    <input type="text" name="f1" maxlength="190" id="emsf_664_3a27e"></div>
                <div class="form-group"><label for="emsf_665_6c1ea">رقم الانتقال</label>
                    <input type="text" name="f2" maxlength="190" id="emsf_665_6c1ea"></div>
                <div class="form-group"><label for="emsf_666_e6416">من حالة</label>
                    <input type="text" name="f3" maxlength="190" id="emsf_666_e6416"></div>
                <div class="form-group"><label for="emsf_667_73f8b">إلى حالة</label>
                    <input type="text" name="f4" maxlength="190" id="emsf_667_73f8b"></div>
                <div class="form-group"><label for="emsf_668_ae33c">الفعل المُطلق</label>
                    <input type="text" name="f5" maxlength="190" id="emsf_668_ae33c"></div>
                <div class="form-group"><label for="emsf_669_c3295">رمز الفعل</label>
                    <input type="text" name="f6" maxlength="190" id="emsf_669_c3295"></div>
                <div class="form-group"><label for="emsf_670_63d80">المخوَّل</label>
                    <input type="text" name="f7" maxlength="190" id="emsf_670_63d80"></div>
                <div class="form-group"><label for="emsf_671_f60ca">الشرط المسبق</label>
                    <input type="text" name="f8" maxlength="190" id="emsf_671_f60ca"></div>
                <div class="form-group"><label for="emsf_672_0a9e4">الحارس المطبَّق</label>
                    <input type="text" name="f9" maxlength="190" id="emsf_672_0a9e4"></div>
                <div class="form-group"><label for="emsf_673_0bb99">الحدث المنشور</label>
                    <input type="text" name="f10" maxlength="190" id="emsf_673_0bb99"></div>
                <div class="form-group"><label for="emsf_674_cc239">قابل للعكس؟</label>
                    <input type="text" name="f11" maxlength="190" id="emsf_674_cc239"></div>
                <div class="form-group"><label for="emsf_675_1b2be">فعل العكس</label>
                    <input type="text" name="f12" maxlength="190" id="emsf_675_1b2be"></div>
                <div class="form-group"><label for="emsf_676_9af51">تاريخ السريان</label>
                    <input type="date" name="f13" id="emsf_676_9af51"></div>
                <div class="form-group"><label for="emsf_677_27f98">النسخة</label>
                    <input type="text" name="f14" maxlength="190" id="emsf_677_27f98"></div>
                <div class="form-group"><label for="emsf_678_ff8d5">الحالة</label>
                    <select name="f15" id="emsf_678_ff8d5"><option value="مسودة">مسودة</option><option value="قيد المراجعة">قيد المراجعة</option><option value="معتمد">معتمد</option><option value="موقوف">موقوف</option><option value="ملغي">ملغي</option></select></div>
            </div></div>
            <div class="cmp03-form-actions">
                <button type="submit" class="btn-primary"><i class="fa fa-save"></i> حفظ</button>
                <button type="button" class="btn-secondary" id="cmp03CancelBtn"><i class="fa fa-times"></i> إلغاء</button>
            </div>
        </div></div>
    </form>

    <div class="card"><div class="card-body">
        <div class="table-responsive">
        <table class="alltables display" id="state_machinesTable">
            <thead><tr>
            <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
            <th>كود الآلة</th>
            <th>نوع المستند</th>
            <th>رقم الانتقال</th>
            <th>من حالة</th>
            <th>إلى حالة</th>
            <th>الفعل المُطلق</th>
            <th>رمز الفعل</th>
            <th>المخوَّل</th>
            <th>الشرط المسبق</th>
            <th>الحارس المطبَّق</th>
            <th>الحدث المنشور</th>
            <th>قابل للعكس؟</th>
            <th>فعل العكس</th>
            <th>تاريخ السريان</th>
            <th>النسخة</th>
            <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
            <th class="ems-gov-th" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
            </tr></thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="18" class="text-center text-muted">لا بياناتَ بعدُ — أضف أول صفٍّ بزر «إضافة»</td></tr>
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
