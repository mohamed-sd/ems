<?php
// شواهد المتطلبات (AC-E06-03 · موجة ٣): SCN-670 · SCN-677 · SCN-684 · SCN-831 · SCN-834 · SCN-835 · SCN-836
/**
 * Governance/portal_users.php — حسابات بوابة الأطراف الخارجية (CMP-03 ⑥ v2 — بتصميم SCR-DES حرفيًّا + إدخال حي)
 * ───────────────────────────────────────────────────────────────────────────
 * الورقة المالكة: 15 · الحوكمة والالتزام · الأعمدة 20 بترتيب المستند وطبقة
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

$CANONICAL = 'portal_users.php';

// حارس الشاشة (M-14 BR-GOV-01): can_view من modules — والسوبر يمر
$__pp = check_page_permissions($conn, 'Governance/portal_users.php');
if (!$is_super_admin && empty($__pp['can_view'])) {
    require_once __DIR__ . '/../includes/perm_explain_live.php';
    $__why = ems_deny_message($conn, intval($_SESSION['user']['role'] ?? 0), 'Governance/portal_users.php');
    ems_gov_flash_redirect('../main/dashboard.php', $__why, 'GOV-INFO-200', '');
    exit();
}
if (!$is_super_admin && $_SERVER['REQUEST_METHOD'] === 'POST' && empty($__pp['can_add']) && empty($__pp['can_edit'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'غير مصرح بالكتابة في هذه الشاشة ❌', 'GOV-PERM-403', 'اطلب المنحةَ من مدير الصلاحيات إن كانت ضمن عملك');
}
$COLS   = array (
  0 => 'كود الحساب',
  1 => 'نوع الطرف',
  2 => 'الكيان',
  3 => 'الشخص',
  4 => 'الصفة لدى الكيان',
  5 => 'نطاق الرؤية',
  6 => 'الأفعال المسموحة',
  7 => 'الأفعال المحجوبة',
  8 => 'مستند التخويل',
  9 => 'تاريخ الإنشاء',
  10 => 'آخر دخول',
  11 => 'تاريخ الانتهاء',
  12 => 'أنشأه',
  13 => 'الحالة',
  14 => 'المعتمِد — الاسم والصفة',
  15 => 'تاريخ الاعتماد',
  16 => 'مرجع التفويض',
  17 => 'المرجع الأب',
  18 => 'المرفق',
  19 => 'سجل الاطّلاع',
);
$FIELDS = array (
  0 => 'كود الحساب',
  1 => 'نوع الطرف',
  2 => 'الشخص',
  3 => 'الصفة لدى الكيان',
  4 => 'نطاق الرؤية',
  5 => 'الأفعال المسموحة',
  6 => 'الأفعال المحجوبة',
  7 => 'مستند التخويل',
  8 => 'آخر دخول',
  9 => 'تاريخ الانتهاء',
  10 => 'أنشأه',
  11 => 'الحالة',
  12 => 'المعتمِد — الاسم والصفة',
  13 => 'تاريخ الاعتماد',
  14 => 'مرجع التفويض',
  15 => 'المرجع الأب',
  16 => 'المرفق',
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

$page_title = 'إيكوبيشن | حسابات بوابة الأطراف الخارجية';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($__pp) ? $__pp : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell" dir="rtl">
    <?php
    $header_title = 'حسابات بوابة الأطراف الخارجية';
    $header_icon = 'fa fa-users-rectangle';
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
            <h5><i class="fa fa-plus"></i> إضافة — حسابات بوابة الأطراف الخارجية</h5>
        </div><div class="card-body">
            <div class="form-section"><div class="form-grid">
                <div class="form-group"><label for="emsf_632_93408">كود الحساب</label>
                    <input type="text" name="f0" required maxlength="190" id="emsf_632_93408"></div>
                <div class="form-group"><label for="emsf_633_18f91">نوع الطرف</label>
                    <input type="text" name="f1" maxlength="190" id="emsf_633_18f91"></div>
                <div class="form-group"><label for="emsf_634_847b8">الشخص</label>
                    <input type="text" name="f2" maxlength="190" id="emsf_634_847b8"></div>
                <div class="form-group"><label for="emsf_635_429ce">الصفة لدى الكيان</label>
                    <input type="text" name="f3" maxlength="190" id="emsf_635_429ce"></div>
                <div class="form-group"><label for="emsf_636_370aa">نطاق الرؤية</label>
                    <input type="text" name="f4" maxlength="190" id="emsf_636_370aa"></div>
                <div class="form-group"><label for="emsf_637_f1dfa">الأفعال المسموحة</label>
                    <input type="text" name="f5" maxlength="190" id="emsf_637_f1dfa"></div>
                <div class="form-group"><label for="emsf_638_7ec1d">الأفعال المحجوبة</label>
                    <input type="text" name="f6" maxlength="190" id="emsf_638_7ec1d"></div>
                <div class="form-group"><label for="emsf_639_2f633">مستند التخويل</label>
                    <input type="text" name="f7" maxlength="190" id="emsf_639_2f633"></div>
                <div class="form-group"><label for="emsf_640_ee520">آخر دخول</label>
                    <input type="text" name="f8" maxlength="190" id="emsf_640_ee520"></div>
                <div class="form-group"><label for="emsf_641_1c70d">تاريخ الانتهاء</label>
                    <input type="date" name="f9" id="emsf_641_1c70d"></div>
                <div class="form-group"><label for="emsf_642_dd92d">أنشأه</label>
                    <input type="text" name="f10" maxlength="190" id="emsf_642_dd92d"></div>
                <div class="form-group"><label for="emsf_643_94772">الحالة</label>
                    <select name="f11" id="emsf_643_94772"><option value="مسودة">مسودة</option><option value="قيد المراجعة">قيد المراجعة</option><option value="معتمد">معتمد</option><option value="موقوف">موقوف</option><option value="ملغي">ملغي</option></select></div>
                <div class="form-group"><label for="emsf_644_c191a">المعتمِد — الاسم والصفة</label>
                    <input type="text" name="f12" maxlength="190" id="emsf_644_c191a"></div>
                <div class="form-group"><label for="emsf_645_c1bf4">تاريخ الاعتماد</label>
                    <input type="date" name="f13" id="emsf_645_c1bf4"></div>
                <div class="form-group"><label for="emsf_646_9a336">مرجع التفويض</label>
                    <input type="text" name="f14" maxlength="190" id="emsf_646_9a336"></div>
                <div class="form-group"><label for="emsf_647_a545b">المرجع الأب</label>
                    <input type="text" name="f15" maxlength="190" id="emsf_647_a545b"></div>
                <div class="form-group"><label for="emsf_648_80bfe">المرفق</label>
                    <input type="text" name="f16" maxlength="190" id="emsf_648_80bfe"></div>
            </div></div>
            <div style="margin-top:12px;display:flex;gap:10px">
                <button type="submit" class="btn-primary"><i class="fa fa-save"></i> حفظ</button>
                <button type="button" class="btn-secondary" id="cmp03CancelBtn"><i class="fa fa-times"></i> إلغاء</button>
            </div>
        </div></div>
    </form>

    <div class="card"><div class="card-body">
        <div class="table-responsive">
        <table class="alltables display" id="portal_usersTable">
            <thead><tr>
            <th>كود الحساب</th>
            <th>نوع الطرف</th>
            <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
            <th>الشخص</th>
            <th>الصفة لدى الكيان</th>
            <th>نطاق الرؤية</th>
            <th>الأفعال المسموحة</th>
            <th>الأفعال المحجوبة</th>
            <th>مستند التخويل</th>
            <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
            <th>آخر دخول</th>
            <th>تاريخ الانتهاء</th>
            <th>أنشأه</th>
            <th class="ems-gov-th" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
            <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
            <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
            <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
            <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
            <th class="ems-gov-th" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
            <th class="ems-gov-th" data-gov="view_log" data-slice="2" title="من قرأ البيان الحساس ومتى">سجل الاطّلاع</th>
            </tr></thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="20" class="text-center text-muted">لا بياناتَ بعدُ — أضف أول صفٍّ بزر «إضافة»</td></tr>
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
