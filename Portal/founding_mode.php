<?php
/**
 * Portal/founding_mode.php — وضع التأسيس وإغلاقه (CMP-03 ⑥ v2 — بتصميم SCR-DES حرفيًّا + إدخال حي)
 * ───────────────────────────────────────────────────────────────────────────
 * الورقة المالكة: 00 · الإدارة التنفيذية · الأعمدة 16 بترتيب المستند وطبقة
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

$CANONICAL = 'founding_mode.php';

// حارس الشاشة (M-14 BR-GOV-01): can_view من modules — والسوبر يمر
$__pp = check_page_permissions($conn, 'Portal/founding_mode.php');
if (!$is_super_admin && empty($__pp['can_view'])) {
    require_once __DIR__ . '/../includes/perm_explain_live.php';
    $__why = ems_deny_message($conn, intval($_SESSION['user']['role'] ?? 0), 'Portal/founding_mode.php');
    ems_gov_flash_redirect('../main/dashboard.php', $__why, 'GOV-INFO-200', '');
    exit();
}
if (!$is_super_admin && $_SERVER['REQUEST_METHOD'] === 'POST' && empty($__pp['can_add']) && empty($__pp['can_edit'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'غير مصرح بالكتابة في هذه الشاشة ❌', 'GOV-PERM-403', 'اطلب المنحةَ من مدير الصلاحيات إن كانت ضمن عملك');
}
$COLS   = array (
  0 => 'الكيان',
  1 => 'رقم الوضع',
  2 => 'تاريخ الفتح',
  3 => 'سبب الفتح',
  4 => 'النطاق المسموح',
  5 => 'الجداول المتأثرة',
  6 => 'المدة المصرَّح بها',
  7 => 'تاريخ الإغلاق المخطط',
  8 => 'تاريخ الإغلاق الفعلي',
  9 => 'عدد السجلات المُدخَلة',
  10 => 'وسم السجلات',
  11 => 'المُدخِلون',
  12 => 'الموافق على الفتح',
  13 => 'الموافق على الإغلاق',
  14 => 'تقرير المراجعة بعد الإغلاق',
  15 => 'الحالة',
);
$FIELDS = array (
  0 => 'رقم الوضع',
  1 => 'تاريخ الفتح',
  2 => 'سبب الفتح',
  3 => 'النطاق المسموح',
  4 => 'الجداول المتأثرة',
  5 => 'المدة المصرَّح بها',
  6 => 'تاريخ الإغلاق المخطط',
  7 => 'تاريخ الإغلاق الفعلي',
  8 => 'عدد السجلات المُدخَلة',
  9 => 'وسم السجلات',
  10 => 'المُدخِلون',
  11 => 'الموافق على الفتح',
  12 => 'الموافق على الإغلاق',
  13 => 'تقرير المراجعة بعد الإغلاق',
  14 => 'الحالة',
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

$page_title = 'إيكوبيشن | وضع التأسيس وإغلاقه';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($__pp) ? $__pp : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell" dir="rtl">
    <?php
    $header_title = 'وضع التأسيس وإغلاقه';
    $header_icon = 'fa fa-seedling';
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
            <h5><i class="fa fa-plus"></i> إضافة — وضع التأسيس وإغلاقه</h5>
        </div><div class="card-body">
            <div class="form-section"><div class="form-grid">
                <div class="form-group"><label for="emsf_1175_81fee">رقم الوضع</label>
                    <input type="text" name="f0" required maxlength="190" id="emsf_1175_81fee"></div>
                <div class="form-group"><label for="emsf_1176_ad8a6">تاريخ الفتح</label>
                    <input type="date" name="f1" id="emsf_1176_ad8a6"></div>
                <div class="form-group"><label for="emsf_1177_ee3f6">سبب الفتح</label>
                    <input type="text" name="f2" maxlength="190" id="emsf_1177_ee3f6"></div>
                <div class="form-group"><label for="emsf_1178_39c1d">النطاق المسموح</label>
                    <input type="text" name="f3" maxlength="190" id="emsf_1178_39c1d"></div>
                <div class="form-group"><label for="emsf_1179_8c86e">الجداول المتأثرة</label>
                    <input type="text" name="f4" maxlength="190" id="emsf_1179_8c86e"></div>
                <div class="form-group"><label for="emsf_1180_0a2d0">المدة المصرَّح بها</label>
                    <input type="text" inputmode="decimal" name="f5" placeholder="0" id="emsf_1180_0a2d0"></div>
                <div class="form-group"><label for="emsf_1181_93e14">تاريخ الإغلاق المخطط</label>
                    <input type="date" name="f6" id="emsf_1181_93e14"></div>
                <div class="form-group"><label for="emsf_1182_a0439">تاريخ الإغلاق الفعلي</label>
                    <input type="date" name="f7" id="emsf_1182_a0439"></div>
                <div class="form-group"><label for="emsf_1183_44c6e">عدد السجلات المُدخَلة</label>
                    <input type="text" inputmode="decimal" name="f8" placeholder="0" id="emsf_1183_44c6e"></div>
                <div class="form-group"><label for="emsf_1184_d9722">وسم السجلات</label>
                    <input type="text" name="f9" maxlength="190" id="emsf_1184_d9722"></div>
                <div class="form-group"><label for="emsf_1185_f1bd5">المُدخِلون</label>
                    <input type="text" name="f10" maxlength="190" id="emsf_1185_f1bd5"></div>
                <div class="form-group"><label for="emsf_1186_36236">الموافق على الفتح</label>
                    <input type="text" name="f11" maxlength="190" id="emsf_1186_36236"></div>
                <div class="form-group"><label for="emsf_1187_8bb0d">الموافق على الإغلاق</label>
                    <input type="text" name="f12" maxlength="190" id="emsf_1187_8bb0d"></div>
                <div class="form-group"><label for="emsf_1188_f2001">تقرير المراجعة بعد الإغلاق</label>
                    <input type="text" name="f13" maxlength="190" id="emsf_1188_f2001"></div>
                <div class="form-group"><label for="emsf_1189_d00d1">الحالة</label>
                    <select name="f14" id="emsf_1189_d00d1"><option value="مسودة">مسودة</option><option value="قيد المراجعة">قيد المراجعة</option><option value="معتمد">معتمد</option><option value="موقوف">موقوف</option><option value="ملغي">ملغي</option></select></div>
            </div></div>
            <div style="margin-top:12px;display:flex;gap:10px">
                <button type="submit" class="btn-primary"><i class="fa fa-save"></i> حفظ</button>
                <button type="button" class="btn-secondary" id="cmp03CancelBtn"><i class="fa fa-times"></i> إلغاء</button>
            </div>
        </div></div>
    </form>

    <div class="card"><div class="card-body">
        <div class="table-responsive">
        <table class="alltables display" id="founding_modeTable">
            <thead><tr>
            <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
            <th>رقم الوضع</th>
            <th>تاريخ الفتح</th>
            <th>سبب الفتح</th>
            <th>النطاق المسموح</th>
            <th>الجداول المتأثرة</th>
            <th>المدة المصرَّح بها</th>
            <th>تاريخ الإغلاق المخطط</th>
            <th>تاريخ الإغلاق الفعلي</th>
            <th>عدد السجلات المُدخَلة</th>
            <th>وسم السجلات</th>
            <th>المُدخِلون</th>
            <th>الموافق على الفتح</th>
            <th>الموافق على الإغلاق</th>
            <th>تقرير المراجعة بعد الإغلاق</th>
            <th class="ems-gov-th" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
            </tr></thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="16" class="text-center text-muted">لا بياناتَ بعدُ — أضف أول صفٍّ بزر «إضافة»</td></tr>
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
