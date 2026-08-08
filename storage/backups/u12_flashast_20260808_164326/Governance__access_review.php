<?php
// شواهد المتطلبات (AC-E06-03 · موجة ٣): SCN-882 · SCN-884 · SCN-886 · SCN-887 · SCN-888 · SCN-889 · SCN-890 · SCN-891 · SCN-892 · SCN-893 · SCN-894 · SCN-895 · SCN-896 · SCN-897 · SCN-898
/**
 * Governance/access_review.php — دورة المراجعة الدورية للصلاحيات (CMP-03 ⑥ v2 — بتصميم SCR-DES حرفيًّا + إدخال حي)
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
    ems_gov_flash_redirect('../login.php', 'غير مصرح', 'GOV-INFO-200', '');
    exit();
}

require_once __DIR__ . '/../includes/cmp03_local_store.php'; // الموجة ٢ — الجدول الأصلي

$CANONICAL = 'access_review.php';

// حارس الشاشة (M-14 BR-GOV-01): can_view من modules — والسوبر يمر
$__pp = check_page_permissions($conn, 'Governance/access_review.php');
if (!$is_super_admin && empty($__pp['can_view'])) {
    require_once __DIR__ . '/../includes/perm_explain_live.php';
    $__why = ems_deny_message($conn, intval($_SESSION['user']['role'] ?? 0), 'Governance/access_review.php');
    ems_gov_flash_redirect('../main/dashboard.php', $__why, 'GOV-INFO-200', '');
    exit();
}
if (!$is_super_admin && $_SERVER['REQUEST_METHOD'] === 'POST' && empty($__pp['can_add']) && empty($__pp['can_edit'])) {
    http_response_code(403);
    exit('غير مصرح بالكتابة في هذه الشاشة');
}
$COLS   = array (
  0 => 'الكيان',
  1 => 'رقم الدورة',
  2 => 'الفترة',
  3 => 'تاريخ الإطلاق',
  4 => 'الإدارة',
  5 => 'عدد الحسابات المراجَعة',
  6 => 'المؤكَّدة',
  7 => 'المطلوب سحبها',
  8 => 'المسحوبة آليًّا',
  9 => 'الحسابات الخاملة',
  10 => 'المعطَّلة',
  11 => 'تعارضات واجبات مكتشفة',
  12 => 'استثناءات قائمة',
  13 => 'نسبة الاستجابة',
  14 => 'مراجع الإدارة',
  15 => 'تاريخ الإقفال',
  16 => 'المعتمِد — الاسم والصفة',
  17 => 'الحالة',
);
$FIELDS = array (
  0 => 'رقم الدورة',
  1 => 'الفترة',
  2 => 'تاريخ الإطلاق',
  3 => 'الإدارة',
  4 => 'عدد الحسابات المراجَعة',
  5 => 'المؤكَّدة',
  6 => 'المطلوب سحبها',
  7 => 'المسحوبة آليًّا',
  8 => 'الحسابات الخاملة',
  9 => 'المعطَّلة',
  10 => 'تعارضات واجبات مكتشفة',
  11 => 'استثناءات قائمة',
  12 => 'نسبة الاستجابة',
  13 => 'مراجع الإدارة',
  14 => 'تاريخ الإقفال',
  15 => 'المعتمِد — الاسم والصفة',
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

$page_title = 'إيكوبيشن | دورة المراجعة الدورية للصلاحيات';
// CM-00 (DEC-E · U10): بذرُ محاورِ الغلافِ من الخادم — AX-2/3 من محرك الصلاحيات
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : (isset($permissions) ? $permissions : null));
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell" dir="rtl">
    <?php
    $header_title = 'دورة المراجعة الدورية للصلاحيات';
    $header_icon = 'fa fa-user-check';
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
            <h5><i class="fa fa-plus"></i> إضافة — دورة المراجعة الدورية للصلاحيات</h5>
        </div><div class="card-body">
            <div class="form-section"><div class="form-grid">
                <div class="form-group"><label>رقم الدورة</label>
                    <input type="text" name="f0" required maxlength="190"></div>
                <div class="form-group"><label>الفترة</label>
                    <input type="text" name="f1" maxlength="190"></div>
                <div class="form-group"><label>تاريخ الإطلاق</label>
                    <input type="date" name="f2"></div>
                <div class="form-group"><label>الإدارة</label>
                    <input type="text" name="f3" maxlength="190"></div>
                <div class="form-group"><label>عدد الحسابات المراجَعة</label>
                    <input type="text" inputmode="decimal" name="f4" placeholder="0"></div>
                <div class="form-group"><label>المؤكَّدة</label>
                    <input type="text" name="f5" maxlength="190"></div>
                <div class="form-group"><label>المطلوب سحبها</label>
                    <input type="text" name="f6" maxlength="190"></div>
                <div class="form-group"><label>المسحوبة آليًّا</label>
                    <input type="text" name="f7" maxlength="190"></div>
                <div class="form-group"><label>الحسابات الخاملة</label>
                    <input type="text" name="f8" maxlength="190"></div>
                <div class="form-group"><label>المعطَّلة</label>
                    <input type="text" name="f9" maxlength="190"></div>
                <div class="form-group"><label>تعارضات واجبات مكتشفة</label>
                    <input type="text" name="f10" maxlength="190"></div>
                <div class="form-group"><label>استثناءات قائمة</label>
                    <input type="text" name="f11" maxlength="190"></div>
                <div class="form-group"><label>نسبة الاستجابة</label>
                    <input type="text" inputmode="decimal" name="f12" placeholder="0"></div>
                <div class="form-group"><label>مراجع الإدارة</label>
                    <input type="text" name="f13" maxlength="190"></div>
                <div class="form-group"><label>تاريخ الإقفال</label>
                    <input type="date" name="f14"></div>
                <div class="form-group"><label>المعتمِد — الاسم والصفة</label>
                    <input type="text" name="f15" maxlength="190"></div>
                <div class="form-group"><label>الحالة</label>
                    <select name="f16"><option value="مسودة">مسودة</option><option value="قيد المراجعة">قيد المراجعة</option><option value="معتمد">معتمد</option><option value="موقوف">موقوف</option><option value="ملغي">ملغي</option></select></div>
            </div></div>
            <div style="margin-top:12px;display:flex;gap:10px">
                <button type="submit" class="btn-save"><i class="fa fa-save"></i> حفظ</button>
                <button type="button" class="btn-cancel" id="cmp03CancelBtn"><i class="fa fa-times"></i> إلغاء</button>
            </div>
        </div></div>
    </form>

    <div class="card"><div class="card-body">
        <div class="table-responsive">
        <table class="alltables display" id="access_reviewTable">
            <thead><tr>
            <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
            <th>رقم الدورة</th>
            <th>الفترة</th>
            <th>تاريخ الإطلاق</th>
            <th>الإدارة</th>
            <th>عدد الحسابات المراجَعة</th>
            <th>المؤكَّدة</th>
            <th>المطلوب سحبها</th>
            <th>المسحوبة آليًّا</th>
            <th>الحسابات الخاملة</th>
            <th>المعطَّلة</th>
            <th>تعارضات واجبات مكتشفة</th>
            <th>استثناءات قائمة</th>
            <th>نسبة الاستجابة</th>
            <th>مراجع الإدارة</th>
            <th>تاريخ الإقفال</th>
            <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
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
