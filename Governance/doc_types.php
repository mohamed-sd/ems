<?php
// شواهد المتطلبات (AC-E06-03 · موجة ٣): SCN-910 · SCN-911 · SCN-912 · SCN-914 · SCN-915 · SCN-916 · SCN-917 · SCN-918 · SCN-919 · SCN-920 · SCN-921 · SCN-922 · SCN-924
/**
 * Governance/doc_types.php — سجل أنواع المستندات (CMP-03 ⑥ v2 — بتصميم SCR-DES حرفيًّا + إدخال حي)
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
require_once __DIR__ . '/../includes/ux_components.php'; // UXW-01: حالات الشاشة الموحدة

$company_id     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$is_super_admin = (strval($_SESSION['user']['role'] ?? '') === '-1');
$uid            = intval($_SESSION['user']['id'] ?? 0);
if (!$is_super_admin && $company_id <= 0) {
    ems_gov_flash_redirect('../main/dashboard.php', 'غير مصرح', 'GOV-PERM-403', '');
    exit();
}

require_once __DIR__ . '/../includes/cmp03_local_store.php'; // الموجة ٢ — الجدول الأصلي

$CANONICAL = 'doc_types.php';

// حارس الشاشة (M-14 BR-GOV-01): can_view من modules — والسوبر يمر
$__pp = check_page_permissions($conn, 'Governance/doc_types.php');
if (!$is_super_admin && empty($__pp['can_view'])) {
    require_once __DIR__ . '/../includes/perm_explain_live.php';
    $__why = ems_deny_message($conn, intval($_SESSION['user']['role'] ?? 0), 'Governance/doc_types.php');
    ems_gov_flash_redirect('../main/dashboard.php', $__why, 'GOV-INFO-200', '');
    exit();
}
if (!$is_super_admin && $_SERVER['REQUEST_METHOD'] === 'POST' && empty($__pp['can_add']) && empty($__pp['can_edit'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'غير مصرح بالكتابة في هذه الشاشة ❌', 'GOV-PERM-403', 'اطلب المنحة من مدير الصلاحيات إن كانت ضمن عملك');
}
$COLS   = array (
  0 => 'الكيان',
  1 => 'كود النوع',
  2 => 'اسم المستند',
  3 => 'الإدارة المالكة',
  4 => 'نمط الترقيم',
  5 => 'بادئة الترقيم',
  6 => 'دورية التسلسل',
  7 => 'آلة الحالة المرتبطة',
  8 => 'يحتاج اعتمادا؟',
  9 => 'عدد حلقات الاعتماد',
  10 => 'له أثر مالي؟',
  11 => 'قابل للعكس؟',
  12 => 'نمط العكس',
  13 => 'مدة الحفظ النظامية',
  14 => 'سياسة الأرشفة',
  15 => 'المنشئ — الاسم والصفة',
  16 => 'تاريخ السريان',
  17 => 'الحالة',
);
$FIELDS = array (
  0 => 'كود النوع',
  1 => 'اسم المستند',
  2 => 'الإدارة المالكة',
  3 => 'نمط الترقيم',
  4 => 'بادئة الترقيم',
  5 => 'دورية التسلسل',
  6 => 'آلة الحالة المرتبطة',
  7 => 'يحتاج اعتمادا؟',
  8 => 'عدد حلقات الاعتماد',
  9 => 'له أثر مالي؟',
  10 => 'قابل للعكس؟',
  11 => 'نمط العكس',
  12 => 'مدة الحفظ النظامية',
  13 => 'سياسة الأرشفة',
  14 => 'تاريخ السريان',
  15 => 'الحالة',
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

$page_title = 'إيكوبيشن | سجل أنواع المستندات';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($__pp) ? $__pp : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell" dir="rtl">
    <?php
    $header_title = 'سجل أنواع المستندات';
    $header_icon = 'fa fa-file-lines';
    $header_actions = array(
        array('tag' => 'button', 'id' => 'cmp03AddBtn', 'class' => '', 'icon' => 'fa fa-plus',
              'label' => 'إضافة', 'title' => 'إضافة صف جديد', 'attrs' => 'type="button"'),
    );
    $header_back = false;
    include '../includes/page_header.php';
    if (isset($_GET['msg'])) {
        echo '<div class="alert alert-info">' . htmlspecialchars((string) $_GET['msg'], ENT_QUOTES, 'UTF-8') . '</div>';
    }
    echo ems_states_bundle('لا أنواع مستندات مسجلة بعد', 'أضف نوع المستند وترقيمه ودورته بزر «إضافة» أعلى الشاشة');
    ?>

    <!-- فورم الإضافة الموحد (ems-forms) — مطويٌّ حتى زرِّ الرأس -->
    <form method="post" action="" class="allforms" id="cmp03AddForm">
        <?= csrf_field() ?>
        <input type="hidden" name="cmp03_action" value="add">
        <div class="card"><div class="card-header">
            <h5><i class="fa fa-plus"></i> إضافة — سجل أنواع المستندات</h5>
        </div><div class="card-body">
            <div class="form-section"><div class="form-grid">
                <div class="form-group"><label for="emsf_560_4a678">كود النوع</label>
                    <input type="text" name="f0" required maxlength="190" id="emsf_560_4a678"></div>
                <div class="form-group"><label for="emsf_561_ea8a3">اسم المستند</label>
                    <input type="text" name="f1" maxlength="190" id="emsf_561_ea8a3"></div>
                <div class="form-group"><label for="emsf_562_aaad9">الإدارة المالكة</label>
                    <input type="text" name="f2" maxlength="190" id="emsf_562_aaad9"></div>
                <div class="form-group"><label for="emsf_563_498a7">نمط الترقيم</label>
                    <input type="text" name="f3" maxlength="190" id="emsf_563_498a7"></div>
                <div class="form-group"><label for="emsf_564_c8067">بادئة الترقيم</label>
                    <input type="text" name="f4" maxlength="190" id="emsf_564_c8067"></div>
                <div class="form-group"><label for="emsf_565_f6532">دورية التسلسل</label>
                    <input type="text" name="f5" maxlength="190" id="emsf_565_f6532"></div>
                <div class="form-group"><label for="emsf_566_f8ae5">آلة الحالة المرتبطة</label>
                    <input type="text" name="f6" maxlength="190" id="emsf_566_f8ae5"></div>
                <div class="form-group"><label for="emsf_567_e96e9">يحتاج اعتمادا؟</label>
                    <input type="text" name="f7" maxlength="190" id="emsf_567_e96e9"></div>
                <div class="form-group"><label for="emsf_568_53c9a">عدد حلقات الاعتماد</label>
                    <input type="text" inputmode="decimal" name="f8" placeholder="0" id="emsf_568_53c9a"></div>
                <div class="form-group"><label for="emsf_569_f00c6">له أثر مالي؟</label>
                    <input type="text" name="f9" maxlength="190" id="emsf_569_f00c6"></div>
                <div class="form-group"><label for="emsf_570_b04f3">قابل للعكس؟</label>
                    <input type="text" name="f10" maxlength="190" id="emsf_570_b04f3"></div>
                <div class="form-group"><label for="emsf_571_3bcee">نمط العكس</label>
                    <input type="text" name="f11" maxlength="190" id="emsf_571_3bcee"></div>
                <div class="form-group"><label for="emsf_572_8af5d">مدة الحفظ النظامية</label>
                    <input type="text" inputmode="decimal" name="f12" placeholder="0" id="emsf_572_8af5d"></div>
                <div class="form-group"><label for="emsf_573_9eede">سياسة الأرشفة</label>
                    <input type="text" name="f13" maxlength="190" id="emsf_573_9eede"></div>
                <div class="form-group"><label for="emsf_574_262af">تاريخ السريان</label>
                    <input type="date" name="f14" id="emsf_574_262af"></div>
                <div class="form-group"><label for="emsf_575_b7a8e">الحالة</label>
                    <select name="f15" id="emsf_575_b7a8e"><option value="مسودة">مسودة</option><option value="قيد المراجعة">قيد المراجعة</option><option value="معتمد">معتمد</option><option value="موقوف">موقوف</option><option value="ملغي">ملغي</option></select></div>
            </div></div>
            <div class="form-actions">
                <button type="submit" class="btn-primary"><i class="fa fa-save"></i> حفظ</button>
                <button type="button" class="btn-secondary" id="cmp03CancelBtn"><i class="fa fa-times"></i> إلغاء</button>
            </div>
        </div></div>
    </form>

    <div class="card"><div class="card-body">
        <div class="table-responsive">
        <table class="alltables display" id="doc_typesTable">
            <thead><tr>
            <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صف بلا كيان مالك">الكيان</th>
            <th>كود النوع</th>
            <th>اسم المستند</th>
            <th>الإدارة المالكة</th>
            <th>نمط الترقيم</th>
            <th>بادئة الترقيم</th>
            <th>دورية التسلسل</th>
            <th>آلة الحالة المرتبطة</th>
            <th>يحتاج اعتمادا؟</th>
            <th>عدد حلقات الاعتماد</th>
            <th>له أثر مالي؟</th>
            <th>قابل للعكس؟</th>
            <th>نمط العكس</th>
            <th>مدة الحفظ النظامية</th>
            <th>سياسة الأرشفة</th>
            <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المنشئ — الاسم والصفة</th>
            <th>تاريخ السريان</th>
            <th class="ems-gov-th" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
            </tr></thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="18" class="text-center text-muted">لا بيانات بعد — أضف أول صف بزر «إضافة»</td></tr>
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
