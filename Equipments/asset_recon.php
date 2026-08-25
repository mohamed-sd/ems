<?php
/**
 * Equipments/asset_recon.php — مطابقة سجل الأصول بالتشغيل (CMP-03 ⑥ v2 — بتصميم SCR-DES حرفيًّا + إدخال حي)
 * ───────────────────────────────────────────────────────────────────────────
 * الورقة المالكة: 10 · إدارة الأسطول · الأعمدة 16 بترتيب المستند وطبقة
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

$CANONICAL = 'asset_recon.php';
$COLS   = array (
  0 => 'رقم المحضر',
  1 => 'الفترة',
  2 => 'كود المعدة',
  3 => 'الساعات حسب سجل الأصول',
  4 => 'الساعات حسب التايم شيت',
  5 => 'الفرق',
  6 => 'نسبة الفرق',
  7 => 'تصنيف الفرق',
  8 => 'تفسير الفرق',
  9 => 'التصحيح المعتمد',
  10 => 'أثر التصحيح على الإهلاك',
  11 => 'طابقه',
  12 => 'اعتمده',
  13 => 'تاريخ الإقفال',
  14 => 'الحالة',
  15 => 'الكيان',
);
$FIELDS = array (
  0 => 'رقم المحضر',
  1 => 'الفترة',
  2 => 'كود المعدة',
  3 => 'الساعات حسب سجل الأصول',
  4 => 'الساعات حسب التايم شيت',
  5 => 'الفرق',
  6 => 'نسبة الفرق',
  7 => 'تصنيف الفرق',
  8 => 'تفسير الفرق',
  9 => 'التصحيح المعتمد',
  10 => 'أثر التصحيح على الإهلاك',
  11 => 'طابقه',
  12 => 'اعتمده',
  13 => 'تاريخ الإقفال',
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

$page_title = 'إيكوبيشن | مطابقة سجل الأصول بالتشغيل';
// CM-00 (DEC-E · U10): بذرُ محاورِ الغلافِ من الخادم — AX-2/3 من محرك الصلاحيات
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : (isset($permissions) ? $permissions : null));
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell" dir="rtl">
    <?php
    $header_title = 'مطابقة سجل الأصول بالتشغيل';
    $header_icon = 'fa fa-scale-balanced';
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
    echo ems_states_bundle('لا محاضر مطابقة بين سجل الأصول والتشغيل بعد',
                           'أضف أول محضر بزر «إضافة» أو راجع الفترة المختارة');
    ?>

    <!-- فورم الإضافة الموحد (ems-forms) — مطويٌّ حتى زرِّ الرأس -->
    <form method="post" action="" class="allforms" id="cmp03AddForm">
        <?= csrf_field() ?>
        <input type="hidden" name="cmp03_action" value="add">
        <div class="card"><div class="card-header">
            <h5><i class="fa fa-plus"></i> إضافة — مطابقة سجل الأصول بالتشغيل</h5>
        </div><div class="card-body">
            <div class="form-section"><div class="form-grid">
                <div class="form-group"><label for="emsf_133_403ed">رقم المحضر</label>
                    <input type="text" name="f0" required maxlength="190" id="emsf_133_403ed"></div>
                <div class="form-group"><label for="emsf_134_c0c6b">الفترة</label>
                    <input type="text" name="f1" maxlength="190" id="emsf_134_c0c6b"></div>
                <div class="form-group"><label for="emsf_135_b7bf6">كود المعدة</label>
                    <input type="text" name="f2" maxlength="190" id="emsf_135_b7bf6"></div>
                <div class="form-group"><label for="emsf_136_be90d">الساعات حسب سجل الأصول</label>
                    <input type="text" inputmode="decimal" name="f3" placeholder="0" id="emsf_136_be90d"></div>
                <div class="form-group"><label for="emsf_137_b7da5">الساعات حسب التايم شيت</label>
                    <input type="text" inputmode="decimal" name="f4" placeholder="0" id="emsf_137_b7da5"></div>
                <div class="form-group"><label for="emsf_138_b326e">الفرق</label>
                    <input type="text" name="f5" maxlength="190" id="emsf_138_b326e"></div>
                <div class="form-group"><label for="emsf_139_f1837">نسبة الفرق</label>
                    <input type="text" inputmode="decimal" name="f6" placeholder="0" id="emsf_139_f1837"></div>
                <div class="form-group"><label for="emsf_140_01ea0">تصنيف الفرق</label>
                    <input type="text" name="f7" maxlength="190" id="emsf_140_01ea0"></div>
                <div class="form-group"><label for="emsf_141_341a4">تفسير الفرق</label>
                    <input type="text" name="f8" maxlength="190" id="emsf_141_341a4"></div>
                <div class="form-group"><label for="emsf_142_20832">التصحيح المعتمد</label>
                    <input type="text" name="f9" maxlength="190" id="emsf_142_20832"></div>
                <div class="form-group"><label for="emsf_143_a0903">أثر التصحيح على الإهلاك</label>
                    <input type="text" name="f10" maxlength="190" id="emsf_143_a0903"></div>
                <div class="form-group"><label for="emsf_144_36267">طابقه</label>
                    <input type="text" name="f11" maxlength="190" id="emsf_144_36267"></div>
                <div class="form-group"><label for="emsf_145_d68a4">اعتمده</label>
                    <input type="text" name="f12" maxlength="190" id="emsf_145_d68a4"></div>
                <div class="form-group"><label for="emsf_146_ae92a">تاريخ الإقفال</label>
                    <input type="date" name="f13" id="emsf_146_ae92a"></div>
                <div class="form-group"><label for="emsf_147_ccd36">الحالة</label>
                    <select name="f14" id="emsf_147_ccd36"><option value="مسودة">مسودة</option><option value="قيد المراجعة">قيد المراجعة</option><option value="معتمد">معتمد</option><option value="موقوف">موقوف</option><option value="ملغي">ملغي</option></select></div>
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
        <table class="alltables display" id="asset_reconTable">
            <thead><tr>
            <th>رقم المحضر</th>
            <th>الفترة</th>
            <th>كود المعدة</th>
            <th>الساعات حسب سجل الأصول</th>
            <th>الساعات حسب التايم شيت</th>
            <th>الفرق</th>
            <th>نسبة الفرق</th>
            <th>تصنيف الفرق</th>
            <th>تفسير الفرق</th>
            <th>التصحيح المعتمد</th>
            <th>أثر التصحيح على الإهلاك</th>
            <th>طابقه</th>
            <th>اعتمده</th>
            <th>تاريخ الإقفال</th>
            <th class="ems-gov-th" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
            <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صف بلا كيان مالك">الكيان</th>
            </tr></thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="16" class="text-center text-muted">لا بيانات بعد — أضف أول صف بزر «إضافة»</td></tr>
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
