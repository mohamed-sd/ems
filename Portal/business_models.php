<?php
/**
 * Portal/business_models.php — نماذج العمل ووحدات القياس (CMP-03 ⑥ v2 — بتصميم SCR-DES حرفيًّا + إدخال حي)
 * ───────────────────────────────────────────────────────────────────────────
 * الورقة المالكة: 00 · الإدارة التنفيذية · الأعمدة 19 بترتيب المستند وطبقة
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

$CANONICAL = 'business_models.php';
$COLS   = array (
  0 => 'الكيان',
  1 => 'كود النموذج',
  2 => 'اسم النموذج',
  3 => 'وحدة العمل',
  4 => 'وحدة القياس',
  5 => 'طريقة القياس الميدانية',
  6 => 'المستند المُثبت',
  7 => 'أساس التسعير',
  8 => 'أنواع المعدات المنطبقة',
  9 => 'وحدة عدّاد المعدة',
  10 => 'وحدة حاوية المورد',
  11 => 'وحدة تعاقد المورد',
  12 => 'أساس استحقاق المورد',
  13 => 'أساس أجر المشغّل',
  14 => 'دورة الإقفال',
  15 => 'المُنشئ — الاسم والصفة',
  16 => 'المعتمِد — الاسم والصفة',
  17 => 'تاريخ السريان',
  18 => 'الحالة',
);
$FIELDS = array (
  0 => 'كود النموذج',
  1 => 'اسم النموذج',
  2 => 'وحدة العمل',
  3 => 'وحدة القياس',
  4 => 'طريقة القياس الميدانية',
  5 => 'المستند المُثبت',
  6 => 'أساس التسعير',
  7 => 'أنواع المعدات المنطبقة',
  8 => 'وحدة عدّاد المعدة',
  9 => 'وحدة حاوية المورد',
  10 => 'وحدة تعاقد المورد',
  11 => 'أساس استحقاق المورد',
  12 => 'أساس أجر المشغّل',
  13 => 'دورة الإقفال',
  14 => 'المعتمِد — الاسم والصفة',
  15 => 'تاريخ السريان',
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

$page_title = 'إيكوبيشن | نماذج العمل ووحدات القياس';
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell" dir="rtl">
    <?php
    $header_title = 'نماذج العمل ووحدات القياس';
    $header_icon = 'fa fa-ruler-combined';
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
            <h5><i class="fa fa-plus"></i> إضافة — نماذج العمل ووحدات القياس</h5>
        </div><div class="card-body">
            <div class="form-section"><div class="form-grid">
                <div class="form-group"><label>كود النموذج</label>
                    <input type="text" name="f0" required maxlength="190"></div>
                <div class="form-group"><label>اسم النموذج</label>
                    <input type="text" name="f1" maxlength="190"></div>
                <div class="form-group"><label>وحدة العمل</label>
                    <input type="text" name="f2" maxlength="190"></div>
                <div class="form-group"><label>وحدة القياس</label>
                    <input type="text" name="f3" maxlength="190"></div>
                <div class="form-group"><label>طريقة القياس الميدانية</label>
                    <input type="text" name="f4" maxlength="190"></div>
                <div class="form-group"><label>المستند المُثبت</label>
                    <input type="text" name="f5" maxlength="190"></div>
                <div class="form-group"><label>أساس التسعير</label>
                    <input type="text" name="f6" maxlength="190"></div>
                <div class="form-group"><label>أنواع المعدات المنطبقة</label>
                    <input type="text" name="f7" maxlength="190"></div>
                <div class="form-group"><label>وحدة عدّاد المعدة</label>
                    <input type="text" name="f8" maxlength="190"></div>
                <div class="form-group"><label>وحدة حاوية المورد</label>
                    <input type="text" name="f9" maxlength="190"></div>
                <div class="form-group"><label>وحدة تعاقد المورد</label>
                    <input type="text" name="f10" maxlength="190"></div>
                <div class="form-group"><label>أساس استحقاق المورد</label>
                    <input type="text" name="f11" maxlength="190"></div>
                <div class="form-group"><label>أساس أجر المشغّل</label>
                    <input type="text" inputmode="decimal" name="f12" placeholder="0"></div>
                <div class="form-group"><label>دورة الإقفال</label>
                    <input type="text" name="f13" maxlength="190"></div>
                <div class="form-group"><label>المعتمِد — الاسم والصفة</label>
                    <input type="text" name="f14" maxlength="190"></div>
                <div class="form-group"><label>تاريخ السريان</label>
                    <input type="date" name="f15"></div>
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
        <table class="alltables display" id="business_modelsTable">
            <thead><tr>
            <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
            <th>كود النموذج</th>
            <th>اسم النموذج</th>
            <th>وحدة العمل</th>
            <th>وحدة القياس</th>
            <th>طريقة القياس الميدانية</th>
            <th>المستند المُثبت</th>
            <th>أساس التسعير</th>
            <th>أنواع المعدات المنطبقة</th>
            <th>وحدة عدّاد المعدة</th>
            <th>وحدة حاوية المورد</th>
            <th>وحدة تعاقد المورد</th>
            <th>أساس استحقاق المورد</th>
            <th>أساس أجر المشغّل</th>
            <th>دورة الإقفال</th>
            <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
            <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
            <th>تاريخ السريان</th>
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
