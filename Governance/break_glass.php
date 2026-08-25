<?php
// شواهد المتطلبات (AC-E06-03 · موجة ٣): SCN-875 · SCN-877 · SCN-878 · SCN-879 · SCN-880 · SCN-881
/**
 * Governance/break_glass.php — صلاحية الطوارئ اللحظية (كسر الزجاج) (CMP-03 ⑥ v2 — بتصميم SCR-DES حرفيًّا + إدخال حي)
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

$CANONICAL = 'break_glass.php';
$COLS   = array (
  0 => 'الكيان',
  1 => 'رقم الطلب',
  2 => 'التاريخ والوقت',
  3 => 'الطالب — الاسم والصفة',
  4 => 'الصلاحية المطلوبة',
  5 => 'الشاشة أو الفعل',
  6 => 'سبب الطوارئ',
  7 => 'الأثر المتوقع لو لم تمنح',
  8 => 'الموافق الأول',
  9 => 'الموافق الثاني',
  10 => 'وقت المنح',
  11 => 'مدة الصلاحية',
  12 => 'وقت الانتهاء',
  13 => 'عدد الأفعال المنفذة تحتها',
  14 => 'تقرير المراجعة',
  15 => 'تاريخ المراجعة',
  16 => 'نتيجة المراجعة',
  17 => 'الحالة',
);
$FIELDS = array (
  0 => 'رقم الطلب',
  1 => 'التاريخ والوقت',
  2 => 'الطالب — الاسم والصفة',
  3 => 'الصلاحية المطلوبة',
  4 => 'الشاشة أو الفعل',
  5 => 'سبب الطوارئ',
  6 => 'الأثر المتوقع لو لم تمنح',
  7 => 'الموافق الأول',
  8 => 'الموافق الثاني',
  9 => 'وقت المنح',
  10 => 'مدة الصلاحية',
  11 => 'وقت الانتهاء',
  12 => 'عدد الأفعال المنفذة تحتها',
  13 => 'تقرير المراجعة',
  14 => 'تاريخ المراجعة',
  15 => 'نتيجة المراجعة',
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

$page_title = 'إيكوبيشن | صلاحية الطوارئ اللحظية (كسر الزجاج)';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell ems-doc-cycle" dir="rtl">
    <?php
    $header_title = 'صلاحية الطوارئ اللحظية (كسر الزجاج)';
    $header_icon = 'fa fa-hammer';
    $header_actions = array(
        array('tag' => 'button', 'id' => 'cmp03AddBtn', 'class' => '', 'icon' => 'fa fa-plus',
              'label' => 'إضافة', 'title' => 'إضافة صف جديد', 'attrs' => 'type="button"'),
    );
    $header_back = false;
    include '../includes/page_header.php';
    echo ems_next_step('اعتماد الموافقين ثم مراجعة بعدية لكل استعمال للصلاحية الطارئة');
    if (isset($_GET['msg'])) {
        echo '<div class="alert alert-info">' . htmlspecialchars((string) $_GET['msg'], ENT_QUOTES, 'UTF-8') . '</div>';
    }
    echo ems_states_bundle('لا طلبات كسر زجاج مسجلة', 'صلاحيات الطوارئ تطلب هنا لحظة الحاجة وتوثق بموافقيها');
    ?>

    <!-- فورم الإضافة الموحد (ems-forms) — مطويٌّ حتى زرِّ الرأس -->
    <form method="post" action="" class="allforms" id="cmp03AddForm">
        <?= csrf_field() ?>
        <input type="hidden" name="cmp03_action" value="add">
        <div class="card"><div class="card-header">
            <h5><i class="fa fa-plus"></i> إضافة — صلاحية الطوارئ اللحظية (كسر الزجاج)</h5>
        </div><div class="card-body">
            <div class="form-section"><div class="form-grid">
                <div class="form-group"><label for="emsf_526_c5635">رقم الطلب</label>
                    <input type="text" name="f0" required maxlength="190" id="emsf_526_c5635"></div>
                <div class="form-group"><label for="emsf_527_c9fc2">التاريخ والوقت</label>
                    <input type="date" name="f1" id="emsf_527_c9fc2"></div>
                <div class="form-group"><label for="emsf_528_7ea1d">الطالب — الاسم والصفة</label>
                    <input type="text" name="f2" maxlength="190" id="emsf_528_7ea1d"></div>
                <div class="form-group"><label for="emsf_529_27841">الصلاحية المطلوبة</label>
                    <input type="text" name="f3" maxlength="190" id="emsf_529_27841"></div>
                <div class="form-group"><label for="emsf_530_fb1cd">الشاشة أو الفعل</label>
                    <input type="text" name="f4" maxlength="190" id="emsf_530_fb1cd"></div>
                <div class="form-group"><label for="emsf_531_dbe36">سبب الطوارئ</label>
                    <input type="text" name="f5" maxlength="190" id="emsf_531_dbe36"></div>
                <div class="form-group"><label for="emsf_532_f45ef">الأثر المتوقع لو لم تمنح</label>
                    <input type="text" name="f6" maxlength="190" id="emsf_532_f45ef"></div>
                <div class="form-group"><label for="emsf_533_0ac55">الموافق الأول</label>
                    <input type="text" name="f7" maxlength="190" id="emsf_533_0ac55"></div>
                <div class="form-group"><label for="emsf_534_56b32">الموافق الثاني</label>
                    <input type="text" name="f8" maxlength="190" id="emsf_534_56b32"></div>
                <div class="form-group"><label for="emsf_535_56cbf">وقت المنح</label>
                    <input type="text" name="f9" maxlength="190" id="emsf_535_56cbf"></div>
                <div class="form-group"><label for="emsf_536_eed79">مدة الصلاحية</label>
                    <input type="text" inputmode="decimal" name="f10" placeholder="0" id="emsf_536_eed79"></div>
                <div class="form-group"><label for="emsf_537_bb268">وقت الانتهاء</label>
                    <input type="text" name="f11" maxlength="190" id="emsf_537_bb268"></div>
                <div class="form-group"><label for="emsf_538_d25e0">عدد الأفعال المنفذة تحتها</label>
                    <input type="text" inputmode="decimal" name="f12" placeholder="0" id="emsf_538_d25e0"></div>
                <div class="form-group"><label for="emsf_539_169eb">تقرير المراجعة</label>
                    <input type="text" name="f13" maxlength="190" id="emsf_539_169eb"></div>
                <div class="form-group"><label for="emsf_540_e000b">تاريخ المراجعة</label>
                    <input type="date" name="f14" id="emsf_540_e000b"></div>
                <div class="form-group"><label for="emsf_541_009b0">نتيجة المراجعة</label>
                    <input type="text" name="f15" maxlength="190" id="emsf_541_009b0"></div>
                <div class="form-group"><label for="emsf_542_1c550">الحالة</label>
                    <select name="f16" id="emsf_542_1c550"><option value="مسودة">مسودة</option><option value="قيد المراجعة">قيد المراجعة</option><option value="معتمد">معتمد</option><option value="موقوف">موقوف</option><option value="ملغي">ملغي</option></select></div>
            </div></div>
            <div class="form-actions">
                <button type="submit" class="btn-primary"><i class="fa fa-save"></i> حفظ</button>
                <button type="button" class="btn-secondary" id="cmp03CancelBtn"><i class="fa fa-times"></i> إلغاء</button>
            </div>
        </div></div>
    </form>

    <div class="card"><div class="card-body">
        <div class="table-responsive">
        <table class="alltables display" id="break_glassTable">
            <thead><tr>
            <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صف بلا كيان مالك">الكيان</th>
            <th>رقم الطلب</th>
            <th>التاريخ والوقت</th>
            <th>الطالب — الاسم والصفة</th>
            <th>الصلاحية المطلوبة</th>
            <th>الشاشة أو الفعل</th>
            <th>سبب الطوارئ</th>
            <th>الأثر المتوقع لو لم تمنح</th>
            <th>الموافق الأول</th>
            <th>الموافق الثاني</th>
            <th>وقت المنح</th>
            <th>مدة الصلاحية</th>
            <th>وقت الانتهاء</th>
            <th>عدد الأفعال المنفذة تحتها</th>
            <th>تقرير المراجعة</th>
            <th>تاريخ المراجعة</th>
            <th>نتيجة المراجعة</th>
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
