<?php
/**
 * Operations/attendance.php — الحضور والانصراف (CMP-03 ⑥ v2 — بتصميم SCR-DES حرفيًّا + إدخال حي)
 * ───────────────────────────────────────────────────────────────────────────
 * الورقة المالكة: 01 · إدارة الموقع · الأعمدة 26 بترتيب المستند وطبقة
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

$CANONICAL = 'attendance.php';
$COLS   = array (
  0 => 'الشهر',
  1 => 'كود الموظف',
  2 => 'التاريخ',
  3 => 'رمز الحالة',
  4 => 'وصف الحالة',
  5 => 'وقت الدخول',
  6 => 'وقت الخروج',
  7 => 'ساعات الدوام',
  8 => 'تأخير بالدقائق',
  9 => 'أثر الراتب',
  10 => 'أثر الحافز',
  11 => 'أثر التواجد',
  12 => 'أثر الفوترة',
  13 => 'أثر استحقاق المورد',
  14 => 'المستند المؤيد',
  15 => 'سجّله',
  16 => 'الحالة',
  17 => 'الكيان',
  18 => 'تاريخ الإنشاء',
  19 => 'المعتمِد — الاسم والصفة',
  20 => 'تاريخ الاعتماد',
  21 => 'مرجع التفويض',
  22 => 'المرجع الأب',
  23 => 'مركز التكلفة',
  24 => 'سعر الصرف ومصدره',
  25 => 'سجل الاطّلاع',
);
$FIELDS = array (
  0 => 'الشهر',
  1 => 'كود الموظف',
  2 => 'التاريخ',
  3 => 'رمز الحالة',
  4 => 'وصف الحالة',
  5 => 'وقت الدخول',
  6 => 'وقت الخروج',
  7 => 'ساعات الدوام',
  8 => 'تأخير بالدقائق',
  9 => 'أثر الراتب',
  10 => 'أثر الحافز',
  11 => 'أثر التواجد',
  12 => 'أثر الفوترة',
  13 => 'أثر استحقاق المورد',
  14 => 'المستند المؤيد',
  15 => 'سجّله',
  16 => 'الحالة',
  17 => 'المعتمِد — الاسم والصفة',
  18 => 'تاريخ الاعتماد',
  19 => 'مرجع التفويض',
  20 => 'المرجع الأب',
  21 => 'مركز التكلفة',
  22 => 'سعر الصرف ومصدره',
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

$page_title = 'إيكوبيشن | الحضور والانصراف';
// CM-00 (DEC-E · U10): بذرُ محاورِ الغلافِ من الخادم — AX-2/3 من محرك الصلاحيات
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : (isset($permissions) ? $permissions : null));
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<?php /* نُقلت أنماطُ هذه الشاشةِ إلى assets/css/ems-screens.css (UXUI-01 البند ٦: صفرُ نمطٍ محليّ) */ ?>
<div class="main ems-unified-page-shell" dir="rtl">
    <?php
    $header_title = 'الحضور والانصراف';
    $header_icon = 'fa fa-fingerprint';
    $header_actions = array(
        array('tag' => 'button', 'id' => 'cmp03AddBtn', 'class' => '', 'icon' => 'fa fa-plus',
              'label' => 'إضافة', 'title' => 'إضافة صف جديد', 'attrs' => 'type="button"'),
    );
    $header_back = false;
    include '../includes/page_header.php';
    if (isset($_GET['msg'])) {
        echo '<div class="alert alert-info">' . htmlspecialchars((string) $_GET['msg'], ENT_QUOTES, 'UTF-8') . '</div>';
    }
    echo ems_states_bundle('لا سجلاتِ حضورٍ وانصرافٍ بعدُ', 'أضف أولَ صفٍّ بزرِّ «إضافة» في رأسِ الشاشة');
    ?>
<?php require_once __DIR__ . '/../includes/entity_tabs.php'; echo ems_entity_tabs('employee', 'الحضور'); ?>

    <!-- فورم الإضافة الموحد (ems-forms) — مطويٌّ حتى زرِّ الرأس -->
    <form method="post" action="" class="allforms" id="cmp03AddForm">
        <?= csrf_field() ?>
        <input type="hidden" name="cmp03_action" value="add">
        <div class="card"><div class="card-header">
            <h5><i class="fa fa-plus"></i> إضافة — الحضور والانصراف</h5>
        </div><div class="card-body">
            <div class="form-section"><div class="form-grid">
                <div class="form-group"><label for="emsf_776_4545a">الشهر</label>
                    <input type="text" name="f0" required maxlength="190" id="emsf_776_4545a"></div>
                <div class="form-group"><label for="emsf_777_7d3ab">كود الموظف</label>
                    <input type="text" name="f1" maxlength="190" id="emsf_777_7d3ab"></div>
                <div class="form-group"><label for="emsf_778_24c85">التاريخ</label>
                    <input type="date" name="f2" id="emsf_778_24c85"></div>
                <div class="form-group"><label for="emsf_779_afa46">رمز الحالة</label>
                    <input type="text" name="f3" maxlength="190" id="emsf_779_afa46"></div>
                <div class="form-group"><label for="emsf_780_c7b66">وصف الحالة</label>
                    <input type="text" name="f4" maxlength="190" id="emsf_780_c7b66"></div>
                <div class="form-group"><label for="emsf_781_38aa2">وقت الدخول</label>
                    <input type="text" name="f5" maxlength="190" id="emsf_781_38aa2"></div>
                <div class="form-group"><label for="emsf_782_3d51c">وقت الخروج</label>
                    <input type="text" name="f6" maxlength="190" id="emsf_782_3d51c"></div>
                <div class="form-group"><label for="emsf_783_ec93a">ساعات الدوام</label>
                    <input type="text" inputmode="decimal" name="f7" placeholder="0" id="emsf_783_ec93a"></div>
                <div class="form-group"><label for="emsf_784_69d90">تأخير بالدقائق</label>
                    <input type="text" name="f8" maxlength="190" id="emsf_784_69d90"></div>
                <div class="form-group"><label for="emsf_785_070c7">أثر الراتب</label>
                    <input type="text" inputmode="decimal" name="f9" placeholder="0" id="emsf_785_070c7"></div>
                <div class="form-group"><label for="emsf_786_69217">أثر الحافز</label>
                    <input type="text" name="f10" maxlength="190" id="emsf_786_69217"></div>
                <div class="form-group"><label for="emsf_787_2254c">أثر التواجد</label>
                    <input type="text" name="f11" maxlength="190" id="emsf_787_2254c"></div>
                <div class="form-group"><label for="emsf_788_cdc27">أثر الفوترة</label>
                    <input type="text" name="f12" maxlength="190" id="emsf_788_cdc27"></div>
                <div class="form-group"><label for="emsf_789_be208">أثر استحقاق المورد</label>
                    <input type="text" name="f13" maxlength="190" id="emsf_789_be208"></div>
                <div class="form-group"><label for="emsf_790_728f7">المستند المؤيد</label>
                    <input type="text" name="f14" maxlength="190" id="emsf_790_728f7"></div>
                <div class="form-group"><label for="emsf_791_12115">سجّله</label>
                    <input type="text" name="f15" maxlength="190" id="emsf_791_12115"></div>
                <div class="form-group"><label for="emsf_792_e0a86">الحالة</label>
                    <select name="f16" id="emsf_792_e0a86"><option value="مسودة">مسودة</option><option value="قيد المراجعة">قيد المراجعة</option><option value="معتمد">معتمد</option><option value="موقوف">موقوف</option><option value="ملغي">ملغي</option></select></div>
                <div class="form-group"><label for="emsf_793_2fd3b">المعتمِد — الاسم والصفة</label>
                    <input type="text" name="f17" maxlength="190" id="emsf_793_2fd3b"></div>
                <div class="form-group"><label for="emsf_794_e776a">تاريخ الاعتماد</label>
                    <input type="date" name="f18" id="emsf_794_e776a"></div>
                <div class="form-group"><label for="emsf_795_787da">مرجع التفويض</label>
                    <input type="text" name="f19" maxlength="190" id="emsf_795_787da"></div>
                <div class="form-group"><label for="emsf_796_3f743">المرجع الأب</label>
                    <input type="text" name="f20" maxlength="190" id="emsf_796_3f743"></div>
                <div class="form-group"><label for="emsf_797_297f1">مركز التكلفة</label>
                    <input type="text" inputmode="decimal" name="f21" placeholder="0" id="emsf_797_297f1"></div>
                <div class="form-group"><label for="emsf_798_43cc5">سعر الصرف ومصدره</label>
                    <input type="text" inputmode="decimal" name="f22" placeholder="0" id="emsf_798_43cc5"></div>
            </div></div>
            <div class="cmp03-form-actions">
                <button type="submit" class="btn-primary"><i class="fa fa-save"></i> حفظ</button>
                <button type="button" class="btn-secondary" id="cmp03CancelBtn"><i class="fa fa-times"></i> إلغاء</button>
            </div>
        </div></div>
    </form>

    <div class="card"><div class="card-body">
        <div class="table-responsive">
        <table class="alltables display" id="attendanceTable">
            <thead><tr>
            <th>الشهر</th>
            <th>كود الموظف</th>
            <th>التاريخ</th>
            <th>رمز الحالة</th>
            <th>وصف الحالة</th>
            <th>وقت الدخول</th>
            <th>وقت الخروج</th>
            <th>ساعات الدوام</th>
            <th>تأخير بالدقائق</th>
            <th>أثر الراتب</th>
            <th>أثر الحافز</th>
            <th>أثر التواجد</th>
            <th>أثر الفوترة</th>
            <th>أثر استحقاق المورد</th>
            <th>المستند المؤيد</th>
            <th>سجّله</th>
            <th class="ems-gov-th" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
            <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
            <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
            <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
            <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
            <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
            <th class="ems-gov-th none" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
            <th class="ems-gov-th none" data-gov="cost_center" data-slice="3" title="وجهة التحميل">مركز التكلفة</th>
            <th class="ems-gov-th none" data-gov="fx_rate_source" data-slice="3" title="ما خالف عملة الدفاتر يحمل السعر ومصدره">سعر الصرف ومصدره</th>
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
