<?php
/**
 * Equipments/equipment_sourcing.php — مصدر المعدة ونمط تملّكها (CMP-03 ⑥ v2 — بتصميم SCR-DES حرفيًّا + إدخال حي)
 * ───────────────────────────────────────────────────────────────────────────
 * الورقة المالكة: 10 · إدارة الأسطول · الأعمدة 24 بترتيب المستند وطبقة
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

$CANONICAL = 'equipment_sourcing.php';
$COLS   = array (
  0 => 'الكيان',
  1 => 'كود المعدة',
  2 => 'نمط المصدر',
  3 => 'المورد أو الممول',
  4 => 'نموذج التمويل',
  5 => 'عملية التمويل',
  6 => 'تاريخ الدخول',
  7 => 'تاريخ نقل الملكية المتوقع',
  8 => 'المالك القانوني الحالي',
  9 => 'المنتفع الاقتصادي',
  10 => 'حامل الإهلاك',
  11 => 'حامل الصيانة',
  12 => 'حامل التأمين',
  13 => 'مرتهن الضمان',
  14 => 'قيمة الأصل',
  15 => 'العملة',
  16 => 'الالتزام القائم',
  17 => 'المعالجة المحاسبية',
  18 => 'درجة السرية',
  19 => 'سجل الاطّلاع',
  20 => 'المُنشئ — الاسم والصفة',
  21 => 'المعتمِد — الاسم والصفة',
  22 => 'تاريخ الاعتماد',
  23 => 'الحالة',
);
$FIELDS = array (
  0 => 'كود المعدة',
  1 => 'نمط المصدر',
  2 => 'المورد أو الممول',
  3 => 'نموذج التمويل',
  4 => 'عملية التمويل',
  5 => 'تاريخ الدخول',
  6 => 'تاريخ نقل الملكية المتوقع',
  7 => 'المالك القانوني الحالي',
  8 => 'المنتفع الاقتصادي',
  9 => 'حامل الإهلاك',
  10 => 'حامل الصيانة',
  11 => 'حامل التأمين',
  12 => 'مرتهن الضمان',
  13 => 'قيمة الأصل',
  14 => 'العملة',
  15 => 'الالتزام القائم',
  16 => 'المعالجة المحاسبية',
  17 => 'درجة السرية',
  18 => 'المعتمِد — الاسم والصفة',
  19 => 'تاريخ الاعتماد',
  20 => 'الحالة',
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

$page_title = 'إيكوبيشن | مصدر المعدة ونمط تملّكها';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell" dir="rtl">
    <?php
    $header_title = 'مصدر المعدة ونمط تملّكها';
    $header_icon = 'fa fa-arrows-down-to-people';
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
    echo ems_states_bundle('لا بياناتِ مصدرٍ أو نمطِ تملّكٍ للمعداتِ بعدُ',
                           'أضف أولَ سجلِّ توريدٍ بزرِّ «إضافة» أو تحقق من توفرِ السجلات');
    ?>

    <!-- فورم الإضافة الموحد (ems-forms) — مطويٌّ حتى زرِّ الرأس -->
    <form method="post" action="" class="allforms" id="cmp03AddForm">
        <?= csrf_field() ?>
        <input type="hidden" name="cmp03_action" value="add">
        <div class="card"><div class="card-header">
            <h5><i class="fa fa-plus"></i> إضافة — مصدر المعدة ونمط تملّكها</h5>
        </div><div class="card-body">
            <div class="form-section"><div class="form-grid">
                <div class="form-group"><label for="emsf_193_b80d2">كود المعدة</label>
                    <input type="text" name="f0" required maxlength="190" id="emsf_193_b80d2"></div>
                <div class="form-group"><label for="emsf_194_b27c6">نمط المصدر</label>
                    <input type="text" name="f1" maxlength="190" id="emsf_194_b27c6"></div>
                <div class="form-group"><label for="emsf_195_59ad1">المورد أو الممول</label>
                    <input type="text" name="f2" maxlength="190" id="emsf_195_59ad1"></div>
                <div class="form-group"><label for="emsf_196_90134">نموذج التمويل</label>
                    <input type="text" name="f3" maxlength="190" id="emsf_196_90134"></div>
                <div class="form-group"><label for="emsf_197_e51a7">عملية التمويل</label>
                    <input type="text" name="f4" maxlength="190" id="emsf_197_e51a7"></div>
                <div class="form-group"><label for="emsf_198_680ff">تاريخ الدخول</label>
                    <input type="date" name="f5" id="emsf_198_680ff"></div>
                <div class="form-group"><label for="emsf_199_59a98">تاريخ نقل الملكية المتوقع</label>
                    <input type="date" name="f6" id="emsf_199_59a98"></div>
                <div class="form-group"><label for="emsf_200_b1e4d">المالك القانوني الحالي</label>
                    <input type="text" name="f7" maxlength="190" id="emsf_200_b1e4d"></div>
                <div class="form-group"><label for="emsf_201_e6740">المنتفع الاقتصادي</label>
                    <input type="text" name="f8" maxlength="190" id="emsf_201_e6740"></div>
                <div class="form-group"><label for="emsf_202_3b203">حامل الإهلاك</label>
                    <input type="text" name="f9" maxlength="190" id="emsf_202_3b203"></div>
                <div class="form-group"><label for="emsf_203_81d9b">حامل الصيانة</label>
                    <input type="text" name="f10" maxlength="190" id="emsf_203_81d9b"></div>
                <div class="form-group"><label for="emsf_204_8e933">حامل التأمين</label>
                    <input type="text" name="f11" maxlength="190" id="emsf_204_8e933"></div>
                <div class="form-group"><label for="emsf_205_8a66a">مرتهن الضمان</label>
                    <input type="text" name="f12" maxlength="190" id="emsf_205_8a66a"></div>
                <div class="form-group"><label for="emsf_206_6c511">قيمة الأصل</label>
                    <input type="text" inputmode="decimal" name="f13" placeholder="0" id="emsf_206_6c511"></div>
                <div class="form-group"><label for="emsf_207_0d726">العملة</label>
                    <input type="text" name="f14" maxlength="190" id="emsf_207_0d726"></div>
                <div class="form-group"><label for="emsf_208_3a3a4">الالتزام القائم</label>
                    <input type="text" name="f15" maxlength="190" id="emsf_208_3a3a4"></div>
                <div class="form-group"><label for="emsf_209_800bc">المعالجة المحاسبية</label>
                    <input type="text" name="f16" maxlength="190" id="emsf_209_800bc"></div>
                <div class="form-group"><label for="emsf_210_a044b">درجة السرية</label>
                    <input type="text" name="f17" maxlength="190" id="emsf_210_a044b"></div>
                <div class="form-group"><label for="emsf_211_3fe8c">المعتمِد — الاسم والصفة</label>
                    <input type="text" name="f18" maxlength="190" id="emsf_211_3fe8c"></div>
                <div class="form-group"><label for="emsf_212_b455d">تاريخ الاعتماد</label>
                    <input type="date" name="f19" id="emsf_212_b455d"></div>
                <div class="form-group"><label for="emsf_213_1563c">الحالة</label>
                    <select name="f20" id="emsf_213_1563c"><option value="مسودة">مسودة</option><option value="قيد المراجعة">قيد المراجعة</option><option value="معتمد">معتمد</option><option value="موقوف">موقوف</option><option value="ملغي">ملغي</option></select></div>
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
        <table class="alltables display" id="equipment_sourcingTable">
            <thead><tr>
            <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
            <th>كود المعدة</th>
            <th>نمط المصدر</th>
            <th>المورد أو الممول</th>
            <th>نموذج التمويل</th>
            <th>عملية التمويل</th>
            <th>تاريخ الدخول</th>
            <th>تاريخ نقل الملكية المتوقع</th>
            <th>المالك القانوني الحالي</th>
            <th>المنتفع الاقتصادي</th>
            <th>حامل الإهلاك</th>
            <th>حامل الصيانة</th>
            <th>حامل التأمين</th>
            <th>مرتهن الضمان</th>
            <th>قيمة الأصل</th>
            <th class="ems-gov-th" data-gov="currency" data-slice="3" title="لا مبلغ بلا عملة">العملة</th>
            <th>الالتزام القائم</th>
            <th>المعالجة المحاسبية</th>
            <th>درجة السرية</th>
            <th class="ems-gov-th" data-gov="view_log" data-slice="2" title="من قرأ البيان الحساس ومتى">سجل الاطّلاع</th>
            <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
            <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
            <th class="ems-gov-th none" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
            <th class="ems-gov-th none" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
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
