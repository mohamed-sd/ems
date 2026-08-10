<?php
/**
 * Financing/ownership_links.php — علاقات الملكية بين الكيانات (CMP-03 ⑥ v2 — بتصميم SCR-DES حرفيًّا + إدخال حي)
 * ───────────────────────────────────────────────────────────────────────────
 * الورقة المالكة: 13 · التمويل والملكية · الأعمدة 26 بترتيب المستند وطبقة
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

$CANONICAL = 'ownership_links.php';
$COLS   = array (
  0 => 'رقم العلاقة',
  1 => 'الكيان المالك',
  2 => 'نوع المالك',
  3 => 'الكيان المملوك',
  4 => 'نوع الملكية',
  5 => 'النسبة',
  6 => 'من تاريخ',
  7 => 'إلى تاريخ',
  8 => 'تاريخ التخارج',
  9 => 'المالك المشتري',
  10 => 'مستند التخارج',
  11 => 'مستند الملكية',
  12 => 'اكتمال الملكية',
  13 => 'مجموع النسب النشطة',
  14 => 'حالة قيد المئة',
  15 => 'تضارب مصالح مكتشف؟',
  16 => 'سجّلها',
  17 => 'الحالة',
  18 => 'الكيان',
  19 => 'تاريخ الإنشاء',
  20 => 'المعتمِد — الاسم والصفة',
  21 => 'تاريخ الاعتماد',
  22 => 'مرجع التفويض',
  23 => 'المرجع الأب',
  24 => 'المرفق',
  25 => 'سجل الاطّلاع',
);
$FIELDS = array (
  0 => 'رقم العلاقة',
  1 => 'الكيان المالك',
  2 => 'نوع المالك',
  3 => 'الكيان المملوك',
  4 => 'نوع الملكية',
  5 => 'النسبة',
  6 => 'من تاريخ',
  7 => 'إلى تاريخ',
  8 => 'تاريخ التخارج',
  9 => 'المالك المشتري',
  10 => 'مستند التخارج',
  11 => 'مستند الملكية',
  12 => 'اكتمال الملكية',
  13 => 'مجموع النسب النشطة',
  14 => 'حالة قيد المئة',
  15 => 'تضارب مصالح مكتشف؟',
  16 => 'سجّلها',
  17 => 'الحالة',
  18 => 'المعتمِد — الاسم والصفة',
  19 => 'تاريخ الاعتماد',
  20 => 'مرجع التفويض',
  21 => 'المرجع الأب',
  22 => 'المرفق',
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

$page_title = 'إيكوبيشن | علاقات الملكية بين الكيانات';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell" dir="rtl">
    <?php
    $header_title = 'علاقات الملكية بين الكيانات';
    $header_icon = 'fa fa-circle-nodes';
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
            <h5><i class="fa fa-plus"></i> إضافة — علاقات الملكية بين الكيانات</h5>
        </div><div class="card-body">
            <div class="form-section"><div class="form-grid">
                <div class="form-group"><label for="emsf_484_8d961">رقم العلاقة</label>
                    <input type="text" name="f0" required maxlength="190" id="emsf_484_8d961"></div>
                <div class="form-group"><label for="emsf_485_e264b">الكيان المالك</label>
                    <input type="text" name="f1" maxlength="190" id="emsf_485_e264b"></div>
                <div class="form-group"><label for="emsf_486_fa727">نوع المالك</label>
                    <input type="text" name="f2" maxlength="190" id="emsf_486_fa727"></div>
                <div class="form-group"><label for="emsf_487_42a13">الكيان المملوك</label>
                    <input type="text" name="f3" maxlength="190" id="emsf_487_42a13"></div>
                <div class="form-group"><label for="emsf_488_69145">نوع الملكية</label>
                    <input type="text" name="f4" maxlength="190" id="emsf_488_69145"></div>
                <div class="form-group"><label for="emsf_489_441bf">النسبة</label>
                    <input type="text" inputmode="decimal" name="f5" placeholder="0" id="emsf_489_441bf"></div>
                <div class="form-group"><label for="emsf_490_fa29a">من تاريخ</label>
                    <input type="date" name="f6" id="emsf_490_fa29a"></div>
                <div class="form-group"><label for="emsf_491_02c19">إلى تاريخ</label>
                    <input type="date" name="f7" id="emsf_491_02c19"></div>
                <div class="form-group"><label for="emsf_492_94cef">تاريخ التخارج</label>
                    <input type="date" name="f8" id="emsf_492_94cef"></div>
                <div class="form-group"><label for="emsf_493_547f2">المالك المشتري</label>
                    <input type="text" name="f9" maxlength="190" id="emsf_493_547f2"></div>
                <div class="form-group"><label for="emsf_494_3b9e8">مستند التخارج</label>
                    <input type="text" name="f10" maxlength="190" id="emsf_494_3b9e8"></div>
                <div class="form-group"><label for="emsf_495_c2c2f">مستند الملكية</label>
                    <input type="text" name="f11" maxlength="190" id="emsf_495_c2c2f"></div>
                <div class="form-group"><label for="emsf_496_3e86f">اكتمال الملكية</label>
                    <input type="text" name="f12" maxlength="190" id="emsf_496_3e86f"></div>
                <div class="form-group"><label for="emsf_497_d5af1">مجموع النسب النشطة</label>
                    <input type="text" name="f13" maxlength="190" id="emsf_497_d5af1"></div>
                <div class="form-group"><label for="emsf_498_4c646">حالة قيد المئة</label>
                    <input type="text" name="f14" maxlength="190" id="emsf_498_4c646"></div>
                <div class="form-group"><label for="emsf_499_e561d">تضارب مصالح مكتشف؟</label>
                    <input type="text" name="f15" maxlength="190" id="emsf_499_e561d"></div>
                <div class="form-group"><label for="emsf_500_9a542">سجّلها</label>
                    <input type="text" name="f16" maxlength="190" id="emsf_500_9a542"></div>
                <div class="form-group"><label for="emsf_501_a101f">الحالة</label>
                    <select name="f17" id="emsf_501_a101f"><option value="مسودة">مسودة</option><option value="قيد المراجعة">قيد المراجعة</option><option value="معتمد">معتمد</option><option value="موقوف">موقوف</option><option value="ملغي">ملغي</option></select></div>
                <div class="form-group"><label for="emsf_502_15269">المعتمِد — الاسم والصفة</label>
                    <input type="text" name="f18" maxlength="190" id="emsf_502_15269"></div>
                <div class="form-group"><label for="emsf_503_6dc3d">تاريخ الاعتماد</label>
                    <input type="date" name="f19" id="emsf_503_6dc3d"></div>
                <div class="form-group"><label for="emsf_504_42a99">مرجع التفويض</label>
                    <input type="text" name="f20" maxlength="190" id="emsf_504_42a99"></div>
                <div class="form-group"><label for="emsf_505_f9d9c">المرجع الأب</label>
                    <input type="text" name="f21" maxlength="190" id="emsf_505_f9d9c"></div>
                <div class="form-group"><label for="emsf_506_b7f82">المرفق</label>
                    <input type="text" name="f22" maxlength="190" id="emsf_506_b7f82"></div>
            </div></div>
            <div style="margin-top:12px;display:flex;gap:10px">
                <button type="submit" class="btn-primary"><i class="fa fa-save"></i> حفظ</button>
                <button type="button" class="btn-secondary" id="cmp03CancelBtn"><i class="fa fa-times"></i> إلغاء</button>
            </div>
        </div></div>
    </form>

    <div class="card"><div class="card-body">
        <div class="table-responsive">
        <table class="alltables display" id="ownership_linksTable">
            <thead><tr>
            <th>رقم العلاقة</th>
            <th>الكيان المالك</th>
            <th>نوع المالك</th>
            <th>الكيان المملوك</th>
            <th>نوع الملكية</th>
            <th>النسبة</th>
            <th>من تاريخ</th>
            <th>إلى تاريخ</th>
            <th>تاريخ التخارج</th>
            <th>المالك المشتري</th>
            <th>مستند التخارج</th>
            <th>مستند الملكية</th>
            <th>اكتمال الملكية</th>
            <th>مجموع النسب النشطة</th>
            <th>حالة قيد المئة</th>
            <th>تضارب مصالح مكتشف؟</th>
            <th>سجّلها</th>
            <th class="ems-gov-th" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
            <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
            <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
            <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
            <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
            <th class="ems-gov-th none" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
            <th class="ems-gov-th none" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
            <th class="ems-gov-th none" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
            <th class="ems-gov-th none" data-gov="view_log" data-slice="2" title="من قرأ البيان الحساس ومتى">سجل الاطّلاع</th>
            </tr></thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="26" class="text-center text-muted">لا بياناتَ بعدُ — أضف أول صفٍّ بزر «إضافة»</td></tr>
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
