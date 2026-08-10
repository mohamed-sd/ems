<?php
/**
 * Procurement/consumption_rate.php — استهلاك المعدة ومعدله (CMP-03 ⑥ v2 — بتصميم SCR-DES حرفيًّا + إدخال حي)
 * ───────────────────────────────────────────────────────────────────────────
 * الورقة المالكة: 06 · المخازن · الأعمدة 25 بترتيب المستند وطبقة
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

$CANONICAL = 'consumption_rate.php';
$COLS   = array (
  0 => 'الكيان',
  1 => 'رقم السجل',
  2 => 'الفترة',
  3 => 'كود المعدة',
  4 => 'نوع المعدة',
  5 => 'الموقع',
  6 => 'الوحدة التعاقدية',
  7 => 'ساعات التشغيل',
  8 => 'صنف الاستهلاك',
  9 => 'الكمية المصروفة',
  10 => 'الوحدة',
  11 => 'معدل الاستهلاك للساعة',
  12 => 'المعدل المرجعي للموديل',
  13 => 'الانحراف',
  14 => 'نسبة الانحراف',
  15 => 'حد الشذوذ',
  16 => 'حالة الشذوذ',
  17 => 'السبب المرجَّح',
  18 => 'البلاغ المفتوح',
  19 => 'تكلفة الاستهلاك',
  20 => 'العملة',
  21 => 'مركز التكلفة',
  22 => 'المُنشئ — الاسم والصفة',
  23 => 'تاريخ الإنشاء',
  24 => 'الحالة',
);
$FIELDS = array (
  0 => 'رقم السجل',
  1 => 'الفترة',
  2 => 'كود المعدة',
  3 => 'نوع المعدة',
  4 => 'الموقع',
  5 => 'الوحدة التعاقدية',
  6 => 'ساعات التشغيل',
  7 => 'صنف الاستهلاك',
  8 => 'الكمية المصروفة',
  9 => 'الوحدة',
  10 => 'معدل الاستهلاك للساعة',
  11 => 'المعدل المرجعي للموديل',
  12 => 'الانحراف',
  13 => 'نسبة الانحراف',
  14 => 'حد الشذوذ',
  15 => 'حالة الشذوذ',
  16 => 'السبب المرجَّح',
  17 => 'البلاغ المفتوح',
  18 => 'تكلفة الاستهلاك',
  19 => 'العملة',
  20 => 'مركز التكلفة',
  21 => 'الحالة',
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

$page_title = 'إيكوبيشن | استهلاك المعدة ومعدله';
// CM-00 (DEC-E · U10): بذرُ محاورِ الغلافِ من الخادم — AX-2/3 من محرك الصلاحيات
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : (isset($permissions) ? $permissions : null));
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell" dir="rtl">
    <?php
    $header_title = 'استهلاك المعدة ومعدله';
    $header_icon = 'fa fa-gas-pump';
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
            <h5><i class="fa fa-plus"></i> إضافة — استهلاك المعدة ومعدله</h5>
        </div><div class="card-body">
            <div class="form-section"><div class="form-grid">
                <div class="form-group"><label for="emsf_1255_ae451">رقم السجل</label>
                    <input type="text" name="f0" required maxlength="190" id="emsf_1255_ae451"></div>
                <div class="form-group"><label for="emsf_1256_5566b">الفترة</label>
                    <input type="text" name="f1" maxlength="190" id="emsf_1256_5566b"></div>
                <div class="form-group"><label for="emsf_1257_cfaf6">كود المعدة</label>
                    <input type="text" name="f2" maxlength="190" id="emsf_1257_cfaf6"></div>
                <div class="form-group"><label for="emsf_1258_b3c03">نوع المعدة</label>
                    <input type="text" name="f3" maxlength="190" id="emsf_1258_b3c03"></div>
                <div class="form-group"><label for="emsf_1259_9db98">الموقع</label>
                    <input type="text" name="f4" maxlength="190" id="emsf_1259_9db98"></div>
                <div class="form-group"><label for="emsf_1260_74f0d">الوحدة التعاقدية</label>
                    <input type="text" name="f5" maxlength="190" id="emsf_1260_74f0d"></div>
                <div class="form-group"><label for="emsf_1261_b0264">ساعات التشغيل</label>
                    <input type="text" inputmode="decimal" name="f6" placeholder="0" id="emsf_1261_b0264"></div>
                <div class="form-group"><label for="emsf_1262_9e234">صنف الاستهلاك</label>
                    <input type="text" name="f7" maxlength="190" id="emsf_1262_9e234"></div>
                <div class="form-group"><label for="emsf_1263_4f64d">الكمية المصروفة</label>
                    <input type="text" inputmode="decimal" name="f8" placeholder="0" id="emsf_1263_4f64d"></div>
                <div class="form-group"><label for="emsf_1264_5dcac">الوحدة</label>
                    <input type="text" name="f9" maxlength="190" id="emsf_1264_5dcac"></div>
                <div class="form-group"><label for="emsf_1265_7da18">معدل الاستهلاك للساعة</label>
                    <input type="text" inputmode="decimal" name="f10" placeholder="0" id="emsf_1265_7da18"></div>
                <div class="form-group"><label for="emsf_1266_b2a7e">المعدل المرجعي للموديل</label>
                    <input type="text" inputmode="decimal" name="f11" placeholder="0" id="emsf_1266_b2a7e"></div>
                <div class="form-group"><label for="emsf_1267_ddbf9">الانحراف</label>
                    <input type="text" name="f12" maxlength="190" id="emsf_1267_ddbf9"></div>
                <div class="form-group"><label for="emsf_1268_11b60">نسبة الانحراف</label>
                    <input type="text" inputmode="decimal" name="f13" placeholder="0" id="emsf_1268_11b60"></div>
                <div class="form-group"><label for="emsf_1269_49f09">حد الشذوذ</label>
                    <input type="text" name="f14" maxlength="190" id="emsf_1269_49f09"></div>
                <div class="form-group"><label for="emsf_1270_45075">حالة الشذوذ</label>
                    <input type="text" name="f15" maxlength="190" id="emsf_1270_45075"></div>
                <div class="form-group"><label for="emsf_1271_f3ed5">السبب المرجَّح</label>
                    <input type="text" name="f16" maxlength="190" id="emsf_1271_f3ed5"></div>
                <div class="form-group"><label for="emsf_1272_431ae">البلاغ المفتوح</label>
                    <input type="text" name="f17" maxlength="190" id="emsf_1272_431ae"></div>
                <div class="form-group"><label for="emsf_1273_61ed9">تكلفة الاستهلاك</label>
                    <input type="text" inputmode="decimal" name="f18" placeholder="0" id="emsf_1273_61ed9"></div>
                <div class="form-group"><label for="emsf_1274_17332">العملة</label>
                    <input type="text" name="f19" maxlength="190" id="emsf_1274_17332"></div>
                <div class="form-group"><label for="emsf_1275_25463">مركز التكلفة</label>
                    <input type="text" inputmode="decimal" name="f20" placeholder="0" id="emsf_1275_25463"></div>
                <div class="form-group"><label for="emsf_1276_71296">الحالة</label>
                    <select name="f21" id="emsf_1276_71296"><option value="مسودة">مسودة</option><option value="قيد المراجعة">قيد المراجعة</option><option value="معتمد">معتمد</option><option value="موقوف">موقوف</option><option value="ملغي">ملغي</option></select></div>
            </div></div>
            <div style="margin-top:12px;display:flex;gap:10px">
                <button type="submit" class="btn-primary"><i class="fa fa-save"></i> حفظ</button>
                <button type="button" class="btn-secondary" id="cmp03CancelBtn"><i class="fa fa-times"></i> إلغاء</button>
            </div>
        </div></div>
    </form>

    <div class="card"><div class="card-body">
        <div class="table-responsive">
        <table class="alltables display" id="consumption_rateTable">
            <thead><tr>
            <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
            <th>رقم السجل</th>
            <th>الفترة</th>
            <th>كود المعدة</th>
            <th>نوع المعدة</th>
            <th>الموقع</th>
            <th>الوحدة التعاقدية</th>
            <th>ساعات التشغيل</th>
            <th>صنف الاستهلاك</th>
            <th>الكمية المصروفة</th>
            <th>الوحدة</th>
            <th>معدل الاستهلاك للساعة</th>
            <th>المعدل المرجعي للموديل</th>
            <th>الانحراف</th>
            <th>نسبة الانحراف</th>
            <th>حد الشذوذ</th>
            <th>حالة الشذوذ</th>
            <th>السبب المرجَّح</th>
            <th>البلاغ المفتوح</th>
            <th>تكلفة الاستهلاك</th>
            <th class="ems-gov-th" data-gov="currency" data-slice="3" title="لا مبلغ بلا عملة">العملة</th>
            <th class="ems-gov-th" data-gov="cost_center" data-slice="3" title="وجهة التحميل">مركز التكلفة</th>
            <th class="ems-gov-th none" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
            <th class="ems-gov-th none" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
            <th class="ems-gov-th none" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
            </tr></thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="25" class="text-center text-muted">لا بياناتَ بعدُ — أضف أول صفٍّ بزر «إضافة»</td></tr>
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
