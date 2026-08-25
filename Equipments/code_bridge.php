<?php
/**
 * Equipments/code_bridge.php — جسر ترقيم المعدات (CMP-03 ⑥ v2 — بتصميم SCR-DES حرفيًّا + إدخال حي)
 * ───────────────────────────────────────────────────────────────────────────
 * الورقة المالكة: 10 · إدارة الأسطول · الأعمدة 19 بترتيب المستند وطبقة
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

$CANONICAL = 'code_bridge.php';
$COLS   = array (
  0 => 'الكيان',
  1 => 'رقم الجسر',
  2 => 'الكود الموحد',
  3 => 'اسم المعدة',
  4 => 'رقم اللوحة',
  5 => 'الكود القديم',
  6 => 'كود المورد',
  7 => 'كود الشركة المصنعة',
  8 => 'الرقم التسلسلي',
  9 => 'مصدر الكود',
  10 => 'حالة التطابق',
  11 => 'تعارض مكتشف',
  12 => 'وصف التعارض',
  13 => 'قرار الفض',
  14 => 'تاريخ الربط',
  15 => 'المنشئ — الاسم والصفة',
  16 => 'المعتمد — الاسم والصفة',
  17 => 'تاريخ الاعتماد',
  18 => 'الحالة',
);
$FIELDS = array (
  0 => 'رقم الجسر',
  1 => 'الكود الموحد',
  2 => 'اسم المعدة',
  3 => 'رقم اللوحة',
  4 => 'الكود القديم',
  5 => 'كود المورد',
  6 => 'كود الشركة المصنعة',
  7 => 'الرقم التسلسلي',
  8 => 'مصدر الكود',
  9 => 'حالة التطابق',
  10 => 'تعارض مكتشف',
  11 => 'وصف التعارض',
  12 => 'قرار الفض',
  13 => 'تاريخ الربط',
  14 => 'المعتمد — الاسم والصفة',
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
    ems_gov_flash_redirect(basename(__FILE__), $ok ? 'حفظ الصف ✅' : 'تعذر الحفظ ❌', 'GOV-OK-200', '');
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
    if ($n === cmp03_screen_norm('المنشئ — الاسم والصفة') || $n === cmp03_screen_norm('الجهة المنشئة')) {
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
    return preg_replace('/[]/u', '', $s);
}

$page_title = 'إيكوبيشن | جسر ترقيم المعدات';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell" dir="rtl">
    <?php
    $header_title = 'جسر ترقيم المعدات';
    $header_icon = 'fa fa-bridge';
    $header_actions = array(
        array('tag' => 'button', 'id' => 'cmp03AddBtn', 'class' => '', 'icon' => 'fa fa-plus',
              'label' => 'إضافة', 'title' => 'إضافة صف جديد', 'attrs' => 'type="button"'),
    );
    $header_back = false;
    include '../includes/page_header.php';
    if (isset($_GET['msg'])) {
        echo '<div class="alert alert-info">' . htmlspecialchars((string) $_GET['msg'], ENT_QUOTES, 'UTF-8') . '</div>';
    }
    /* UXW-01 بوابة ٩: حزمةُ الحالاتِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ
       افتراضًا ويُظهرها منطقُ الشاشةِ عند حالِها. الدالةُ من ux_components
       التي تُحمِّلها القشرة. */
    echo ems_states_bundle('لا روابط في جسر ترقيم المعدات بعد',
                           'أضف أول ربط ترقيم بزر «إضافة» أو تحقق من توفر السجلات');
    ?>

    <!-- فورم الإضافة الموحد (ems-forms) — مطويٌّ حتى زرِّ الرأس -->
    <form method="post" action="" class="allforms" id="cmp03AddForm">
        <?= csrf_field() ?>
        <input type="hidden" name="cmp03_action" value="add">
        <div class="card"><div class="card-header">
            <h5><i class="fa fa-plus"></i> إضافة — جسر ترقيم المعدات</h5>
        </div><div class="card-body">
            <div class="form-section"><div class="form-grid">
                <div class="form-group"><label for="emsf_148_213df">رقم الجسر</label>
                    <input type="text" name="f0" required maxlength="190" id="emsf_148_213df"></div>
                <div class="form-group"><label for="emsf_149_3066d">الكود الموحد</label>
                    <input type="text" name="f1" maxlength="190" id="emsf_149_3066d"></div>
                <div class="form-group"><label for="emsf_150_4a392">اسم المعدة</label>
                    <input type="text" name="f2" maxlength="190" id="emsf_150_4a392"></div>
                <div class="form-group"><label for="emsf_151_7c017">رقم اللوحة</label>
                    <input type="text" name="f3" maxlength="190" id="emsf_151_7c017"></div>
                <div class="form-group"><label for="emsf_152_2430b">الكود القديم</label>
                    <input type="text" name="f4" maxlength="190" id="emsf_152_2430b"></div>
                <div class="form-group"><label for="emsf_153_64578">كود المورد</label>
                    <input type="text" name="f5" maxlength="190" id="emsf_153_64578"></div>
                <div class="form-group"><label for="emsf_154_3354c">كود الشركة المصنعة</label>
                    <input type="text" name="f6" maxlength="190" id="emsf_154_3354c"></div>
                <div class="form-group"><label for="emsf_155_4ebdd">الرقم التسلسلي</label>
                    <input type="text" name="f7" maxlength="190" id="emsf_155_4ebdd"></div>
                <div class="form-group"><label for="emsf_156_a1bca">مصدر الكود</label>
                    <input type="text" name="f8" maxlength="190" id="emsf_156_a1bca"></div>
                <div class="form-group"><label for="emsf_157_c7cdf">حالة التطابق</label>
                    <input type="text" name="f9" maxlength="190" id="emsf_157_c7cdf"></div>
                <div class="form-group"><label for="emsf_158_00b9f">تعارض مكتشف</label>
                    <input type="text" name="f10" maxlength="190" id="emsf_158_00b9f"></div>
                <div class="form-group"><label for="emsf_159_da667">وصف التعارض</label>
                    <input type="text" name="f11" maxlength="190" id="emsf_159_da667"></div>
                <div class="form-group"><label for="emsf_160_e3cd5">قرار الفض</label>
                    <input type="text" name="f12" maxlength="190" id="emsf_160_e3cd5"></div>
                <div class="form-group"><label for="emsf_161_2fa53">تاريخ الربط</label>
                    <input type="date" name="f13" id="emsf_161_2fa53"></div>
                <div class="form-group"><label for="emsf_162_7ee21">المعتمد — الاسم والصفة</label>
                    <input type="text" name="f14" maxlength="190" id="emsf_162_7ee21"></div>
                <div class="form-group"><label for="emsf_163_c7af2">تاريخ الاعتماد</label>
                    <input type="date" name="f15" id="emsf_163_c7af2"></div>
                <div class="form-group"><label for="emsf_164_71d36">الحالة</label>
                    <select name="f16" id="emsf_164_71d36"><option value="مسودة">مسودة</option><option value="قيد المراجعة">قيد المراجعة</option><option value="معتمد">معتمد</option><option value="موقوف">موقوف</option><option value="ملغي">ملغي</option></select></div>
            </div></div>
            <!-- UXW-01 بوابة ٢: صفُّ الأزرارِ بصنفِ المكوّنِ الموحَّد بدل style= -->
            <div class="form-actions">
                <button type="submit" class="btn-primary"><i class="fa fa-save"></i> حفظ</button>
                <button type="button" class="btn-secondary" id="cmp03CancelBtn"><i class="fa fa-times"></i> إلغاء</button>
            </div>
        </div></div>
    </form>

    <div class="card"><div class="card-body">
        <div class="table-responsive">
        <table class="alltables display" id="code_bridgeTable">
            <thead><tr>
            <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صف بلا كيان مالك">الكيان</th>
            <th>رقم الجسر</th>
            <th>الكود الموحد</th>
            <th>اسم المعدة</th>
            <th>رقم اللوحة</th>
            <th>الكود القديم</th>
            <th>كود المورد</th>
            <th>كود الشركة المصنعة</th>
            <th>الرقم التسلسلي</th>
            <th>مصدر الكود</th>
            <th>حالة التطابق</th>
            <th>تعارض مكتشف</th>
            <th>وصف التعارض</th>
            <th>قرار الفض</th>
            <th>تاريخ الربط</th>
            <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المنشئ — الاسم والصفة</th>
            <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمد — الاسم والصفة</th>
            <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
            <th class="ems-gov-th" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
            </tr></thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="19" class="text-center text-muted">لا بيانات بعد — أضف أول صف بزر «إضافة»</td></tr>
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
