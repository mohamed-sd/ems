<?php
/**
 * Workforce/project_contracts.php — عقود المشاريع المؤقتة (CMP-03 ⑥ v2 — بتصميم SCR-DES حرفيًّا + إدخال حي)
 * ───────────────────────────────────────────────────────────────────────────
 * الورقة المالكة: 09 · القوى التشغيلية · الأعمدة 28 بترتيب المستند وطبقة
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

$CANONICAL = 'project_contracts.php';
$COLS   = array (
  0 => 'الكيان',
  1 => 'رقم العقد',
  2 => 'فئة العقد',
  3 => 'المتعاقَد معه',
  4 => 'التبعية',
  5 => 'المورد المرتبط',
  6 => 'عقد المورد',
  7 => 'المشروع',
  8 => 'الموقع',
  9 => 'المسمى',
  10 => 'نموذج الأجر',
  11 => 'الأجر الشهري',
  12 => 'العملة',
  13 => 'تاريخ البدء',
  14 => 'تاريخ الانتهاء المخطط',
  15 => 'محفّز الانتهاء 1',
  16 => 'محفّز الانتهاء 2',
  17 => 'محفّز الانتهاء 3',
  18 => 'المحفّز الواقع',
  19 => 'تاريخ الانتهاء الفعلي',
  20 => 'مهلة التنبيه قبل الانتهاء',
  21 => 'حالة التصفية',
  22 => 'إذن الخروج',
  23 => 'المُنشئ — الاسم والصفة',
  24 => 'المعتمِد — الاسم والصفة',
  25 => 'تاريخ الاعتماد',
  26 => 'مرجع التفويض',
  27 => 'الحالة',
);
$FIELDS = array (
  0 => 'رقم العقد',
  1 => 'فئة العقد',
  2 => 'المتعاقَد معه',
  3 => 'التبعية',
  4 => 'المورد المرتبط',
  5 => 'عقد المورد',
  6 => 'المشروع',
  7 => 'الموقع',
  8 => 'المسمى',
  9 => 'نموذج الأجر',
  10 => 'الأجر الشهري',
  11 => 'العملة',
  12 => 'تاريخ البدء',
  13 => 'تاريخ الانتهاء المخطط',
  14 => 'محفّز الانتهاء 1',
  15 => 'محفّز الانتهاء 2',
  16 => 'محفّز الانتهاء 3',
  17 => 'المحفّز الواقع',
  18 => 'تاريخ الانتهاء الفعلي',
  19 => 'مهلة التنبيه قبل الانتهاء',
  20 => 'حالة التصفية',
  21 => 'إذن الخروج',
  22 => 'المعتمِد — الاسم والصفة',
  23 => 'تاريخ الاعتماد',
  24 => 'مرجع التفويض',
  25 => 'الحالة',
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

$page_title = 'إيكوبيشن | عقود المشاريع المؤقتة';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell" dir="rtl">
    <?php
    $header_title = 'عقود المشاريع المؤقتة';
    $header_icon = 'fa fa-file-contract';
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
            <h5><i class="fa fa-plus"></i> إضافة — عقود المشاريع المؤقتة</h5>
        </div><div class="card-body">
            <div class="form-section"><div class="form-grid">
                <div class="form-group"><label>رقم العقد</label>
                    <input type="text" name="f0" required maxlength="190"></div>
                <div class="form-group"><label>فئة العقد</label>
                    <input type="text" name="f1" maxlength="190"></div>
                <div class="form-group"><label>المتعاقَد معه</label>
                    <input type="text" name="f2" maxlength="190"></div>
                <div class="form-group"><label>التبعية</label>
                    <input type="text" name="f3" maxlength="190"></div>
                <div class="form-group"><label>المورد المرتبط</label>
                    <input type="text" name="f4" maxlength="190"></div>
                <div class="form-group"><label>عقد المورد</label>
                    <input type="text" name="f5" maxlength="190"></div>
                <div class="form-group"><label>المشروع</label>
                    <input type="text" name="f6" maxlength="190"></div>
                <div class="form-group"><label>الموقع</label>
                    <input type="text" name="f7" maxlength="190"></div>
                <div class="form-group"><label>المسمى</label>
                    <input type="text" name="f8" maxlength="190"></div>
                <div class="form-group"><label>نموذج الأجر</label>
                    <input type="text" inputmode="decimal" name="f9" placeholder="0"></div>
                <div class="form-group"><label>الأجر الشهري</label>
                    <input type="text" inputmode="decimal" name="f10" placeholder="0"></div>
                <div class="form-group"><label>العملة</label>
                    <input type="text" name="f11" maxlength="190"></div>
                <div class="form-group"><label>تاريخ البدء</label>
                    <input type="date" name="f12"></div>
                <div class="form-group"><label>تاريخ الانتهاء المخطط</label>
                    <input type="date" name="f13"></div>
                <div class="form-group"><label>محفّز الانتهاء 1</label>
                    <input type="text" name="f14" maxlength="190"></div>
                <div class="form-group"><label>محفّز الانتهاء 2</label>
                    <input type="text" name="f15" maxlength="190"></div>
                <div class="form-group"><label>محفّز الانتهاء 3</label>
                    <input type="text" name="f16" maxlength="190"></div>
                <div class="form-group"><label>المحفّز الواقع</label>
                    <input type="text" name="f17" maxlength="190"></div>
                <div class="form-group"><label>تاريخ الانتهاء الفعلي</label>
                    <input type="date" name="f18"></div>
                <div class="form-group"><label>مهلة التنبيه قبل الانتهاء</label>
                    <input type="text" name="f19" maxlength="190"></div>
                <div class="form-group"><label>حالة التصفية</label>
                    <input type="text" name="f20" maxlength="190"></div>
                <div class="form-group"><label>إذن الخروج</label>
                    <input type="text" name="f21" maxlength="190"></div>
                <div class="form-group"><label>المعتمِد — الاسم والصفة</label>
                    <input type="text" name="f22" maxlength="190"></div>
                <div class="form-group"><label>تاريخ الاعتماد</label>
                    <input type="date" name="f23"></div>
                <div class="form-group"><label>مرجع التفويض</label>
                    <input type="text" name="f24" maxlength="190"></div>
                <div class="form-group"><label>الحالة</label>
                    <select name="f25"><option value="مسودة">مسودة</option><option value="قيد المراجعة">قيد المراجعة</option><option value="معتمد">معتمد</option><option value="موقوف">موقوف</option><option value="ملغي">ملغي</option></select></div>
            </div></div>
            <div style="margin-top:12px;display:flex;gap:10px">
                <button type="submit" class="btn-save"><i class="fa fa-save"></i> حفظ</button>
                <button type="button" class="btn-cancel" id="cmp03CancelBtn"><i class="fa fa-times"></i> إلغاء</button>
            </div>
        </div></div>
    </form>

    <div class="card"><div class="card-body">
        <div class="table-responsive">
        <table class="alltables display" id="project_contractsTable">
            <thead><tr>
            <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
            <th>رقم العقد</th>
            <th>فئة العقد</th>
            <th>المتعاقَد معه</th>
            <th>التبعية</th>
            <th>المورد المرتبط</th>
            <th>عقد المورد</th>
            <th>المشروع</th>
            <th>الموقع</th>
            <th>المسمى</th>
            <th>نموذج الأجر</th>
            <th>الأجر الشهري</th>
            <th class="ems-gov-th" data-gov="currency" data-slice="3" title="لا مبلغ بلا عملة">العملة</th>
            <th>تاريخ البدء</th>
            <th>تاريخ الانتهاء المخطط</th>
            <th>محفّز الانتهاء 1</th>
            <th>محفّز الانتهاء 2</th>
            <th>محفّز الانتهاء 3</th>
            <th>المحفّز الواقع</th>
            <th>تاريخ الانتهاء الفعلي</th>
            <th>مهلة التنبيه قبل الانتهاء</th>
            <th>حالة التصفية</th>
            <th class="ems-fn-th none" data-fn="1">إذن الخروج</th>
            <th class="ems-gov-th none" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
            <th class="ems-gov-th none" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
            <th class="ems-gov-th none" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
            <th class="ems-gov-th none" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
            <th class="ems-gov-th none" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
            </tr></thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="28" class="text-center text-muted">لا بياناتَ بعدُ — أضف أول صفٍّ بزر «إضافة»</td></tr>
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
