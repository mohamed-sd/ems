<?php
/**
 * Workforce/op_codes.php — أرقام المشغّلين الشاغرة (CMP-03 ⑥ v2 — بتصميم SCR-DES حرفيًّا + إدخال حي)
 * ───────────────────────────────────────────────────────────────────────────
 * الورقة المالكة: 09 · القوى التشغيلية · الأعمدة 19 بترتيب المستند وطبقة
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

$CANONICAL = 'op_codes.php';
$COLS   = array (
  0 => 'الرقم',
  1 => 'حالة الرقم',
  2 => 'المشغّل السابق',
  3 => 'تاريخ الإخلاء',
  4 => 'سبب الإخلاء',
  5 => 'مدة الشغور بالأيام',
  6 => 'المشغّل الجديد',
  7 => 'تاريخ التخصيص الجديد',
  8 => 'قرار الإدارة',
  9 => 'خصّصه',
  10 => 'الحالة',
  11 => 'الكيان',
  12 => 'المُنشئ — الاسم والصفة',
  13 => 'تاريخ الإنشاء',
  14 => 'المعتمِد — الاسم والصفة',
  15 => 'تاريخ الاعتماد',
  16 => 'مرجع التفويض',
  17 => 'المرجع الأب',
  18 => 'المرفق',
);
$FIELDS = array (
  0 => 'الرقم',
  1 => 'حالة الرقم',
  2 => 'المشغّل السابق',
  3 => 'تاريخ الإخلاء',
  4 => 'سبب الإخلاء',
  5 => 'مدة الشغور بالأيام',
  6 => 'المشغّل الجديد',
  7 => 'تاريخ التخصيص الجديد',
  8 => 'قرار الإدارة',
  9 => 'خصّصه',
  10 => 'الحالة',
  11 => 'المعتمِد — الاسم والصفة',
  12 => 'تاريخ الاعتماد',
  13 => 'مرجع التفويض',
  14 => 'المرجع الأب',
  15 => 'المرفق',
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

$page_title = 'إيكوبيشن | أرقام المشغّلين الشاغرة';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell" dir="rtl">
    <?php
    $header_title = 'أرقام المشغّلين الشاغرة';
    $header_icon = 'fa fa-hashtag';
    $header_actions = array(
        array('tag' => 'button', 'id' => 'cmp03AddBtn', 'class' => '', 'icon' => 'fa fa-plus',
              'label' => 'إضافة', 'title' => 'إضافة صف جديد', 'attrs' => 'type="button"'),
    );
    $header_back = false;
    include '../includes/page_header.php';
    if (isset($_GET['msg'])) {
        echo '<div class="alert alert-info">' . htmlspecialchars((string) $_GET['msg'], ENT_QUOTES, 'UTF-8') . '</div>';
    }
    echo ems_states_bundle('لا أرقامَ مشغّلين شاغرةً مسجَّلةً بعدُ', 'أضف أولَ صفٍّ بزرِّ «إضافة» في رأسِ الشاشة');
    ?>
<?php require_once __DIR__ . '/../includes/entity_tabs.php'; echo ems_entity_tabs('operator', ''); ?>
    <?php /* نُقلت أنماطُ هذه الشاشةِ إلى assets/css/ems-screens.css (UXUI-01 البند ٦: صفرُ نمطٍ محليّ) */ ?>

    <!-- فورم الإضافة الموحد (ems-forms) — مطويٌّ حتى زرِّ الرأس -->
    <form method="post" action="" class="allforms" id="cmp03AddForm">
        <?= csrf_field() ?>
        <input type="hidden" name="cmp03_action" value="add">
        <div class="card"><div class="card-header">
            <h5><i class="fa fa-plus"></i> إضافة — أرقام المشغّلين الشاغرة</h5>
        </div><div class="card-body">
            <div class="form-section"><div class="form-grid">
                <div class="form-group"><label for="emsf_1696_129b9">الرقم</label>
                    <input type="text" name="f0" required maxlength="190" id="emsf_1696_129b9"></div>
                <div class="form-group"><label for="emsf_1697_5f937">حالة الرقم</label>
                    <input type="text" name="f1" maxlength="190" id="emsf_1697_5f937"></div>
                <div class="form-group"><label for="emsf_1698_04563">المشغّل السابق</label>
                    <input type="text" name="f2" maxlength="190" id="emsf_1698_04563"></div>
                <div class="form-group"><label for="emsf_1699_6533a">تاريخ الإخلاء</label>
                    <input type="date" name="f3" id="emsf_1699_6533a"></div>
                <div class="form-group"><label for="emsf_1700_73c88">سبب الإخلاء</label>
                    <input type="text" name="f4" maxlength="190" id="emsf_1700_73c88"></div>
                <div class="form-group"><label for="emsf_1701_551b4">مدة الشغور بالأيام</label>
                    <input type="text" inputmode="decimal" name="f5" placeholder="0" id="emsf_1701_551b4"></div>
                <div class="form-group"><label for="emsf_1702_1885a">المشغّل الجديد</label>
                    <input type="text" name="f6" maxlength="190" id="emsf_1702_1885a"></div>
                <div class="form-group"><label for="emsf_1703_16a64">تاريخ التخصيص الجديد</label>
                    <input type="date" name="f7" id="emsf_1703_16a64"></div>
                <div class="form-group"><label for="emsf_1704_64173">قرار الإدارة</label>
                    <input type="text" name="f8" maxlength="190" id="emsf_1704_64173"></div>
                <div class="form-group"><label for="emsf_1705_c3255">خصّصه</label>
                    <input type="text" name="f9" maxlength="190" id="emsf_1705_c3255"></div>
                <div class="form-group"><label for="emsf_1706_2f205">الحالة</label>
                    <select name="f10" id="emsf_1706_2f205"><option value="مسودة">مسودة</option><option value="قيد المراجعة">قيد المراجعة</option><option value="معتمد">معتمد</option><option value="موقوف">موقوف</option><option value="ملغي">ملغي</option></select></div>
                <div class="form-group"><label for="emsf_1707_7eda0">المعتمِد — الاسم والصفة</label>
                    <input type="text" name="f11" maxlength="190" id="emsf_1707_7eda0"></div>
                <div class="form-group"><label for="emsf_1708_616e8">تاريخ الاعتماد</label>
                    <input type="date" name="f12" id="emsf_1708_616e8"></div>
                <div class="form-group"><label for="emsf_1709_8f2ed">مرجع التفويض</label>
                    <input type="text" name="f13" maxlength="190" id="emsf_1709_8f2ed"></div>
                <div class="form-group"><label for="emsf_1710_cd6b0">المرجع الأب</label>
                    <input type="text" name="f14" maxlength="190" id="emsf_1710_cd6b0"></div>
                <div class="form-group"><label for="emsf_1711_65dcb">المرفق</label>
                    <input type="text" name="f15" maxlength="190" id="emsf_1711_65dcb"></div>
            </div></div>
            <div class="cmp03-form-actions">
                <button type="submit" class="btn-primary"><i class="fa fa-save"></i> حفظ</button>
                <button type="button" class="btn-secondary" id="cmp03CancelBtn"><i class="fa fa-times"></i> إلغاء</button>
            </div>
        </div></div>
    </form>

    <div class="card"><div class="card-body">
        <div class="table-responsive">
        <table class="alltables display" id="op_codesTable">
            <thead><tr>
            <th>الرقم</th>
            <th>حالة الرقم</th>
            <th>المشغّل السابق</th>
            <th>تاريخ الإخلاء</th>
            <th>سبب الإخلاء</th>
            <th>مدة الشغور بالأيام</th>
            <th>المشغّل الجديد</th>
            <th>تاريخ التخصيص الجديد</th>
            <th>قرار الإدارة</th>
            <th>خصّصه</th>
            <th class="ems-gov-th" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
            <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
            <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
            <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
            <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
            <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
            <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
            <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
            <th class="ems-gov-th" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
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
