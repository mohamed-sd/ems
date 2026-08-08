<?php
/**
 * Suppliers/supplier_bank.php — حسابات الموردين البنكية (CMP-03 ⑥ v2 — بتصميم SCR-DES حرفيًّا + إدخال حي)
 * ───────────────────────────────────────────────────────────────────────────
 * الورقة المالكة: 08 · إدارة الموردين · الأعمدة 28 بترتيب المستند وطبقة
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
    ems_gov_flash_redirect('../login.php', 'غير مصرح', 'GOV-PERM-403', '');
    exit();
}

$CANONICAL = 'supplier_bank.php';
$COLS   = array (
  0 => 'رقم السجل',
  1 => 'المورد',
  2 => 'اسم المستفيد',
  3 => 'رقم الحساب',
  4 => 'IBAN',
  5 => 'البنك',
  6 => 'الفرع',
  7 => 'العملة',
  8 => 'مستند إثبات الحساب',
  9 => 'حالة التحقق',
  10 => 'تاريخ التحقق',
  11 => 'محقِّق الحساب',
  12 => 'آخر سداد على الحساب',
  13 => 'درجة السرية',
  14 => 'الحالة',
  15 => 'الكيان',
  16 => 'المُنشئ — الاسم والصفة',
  17 => 'تاريخ الإنشاء',
  18 => 'المعتمِد — الاسم والصفة',
  19 => 'تاريخ الاعتماد',
  20 => 'مرجع التفويض',
  21 => 'المرجع الأب',
  22 => 'المرفق',
  23 => 'مفتاح منع التكرار',
  24 => 'درجة الأثر',
  25 => 'معكوس بـ',
  26 => 'عكس عن',
  27 => 'سجل الاطّلاع',
);
$FIELDS = array (
  0 => 'رقم السجل',
  1 => 'المورد',
  2 => 'اسم المستفيد',
  3 => 'رقم الحساب',
  4 => 'IBAN',
  5 => 'البنك',
  6 => 'الفرع',
  7 => 'العملة',
  8 => 'مستند إثبات الحساب',
  9 => 'حالة التحقق',
  10 => 'تاريخ التحقق',
  11 => 'محقِّق الحساب',
  12 => 'آخر سداد على الحساب',
  13 => 'درجة السرية',
  14 => 'الحالة',
  15 => 'المعتمِد — الاسم والصفة',
  16 => 'تاريخ الاعتماد',
  17 => 'مرجع التفويض',
  18 => 'المرجع الأب',
  19 => 'المرفق',
  20 => 'درجة الأثر',
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
    $st = $conn->prepare("INSERT INTO cmp03_screen_rows
        (company_id, canonical_file, payload, status, is_seed, created_by, created_by_name)
        VALUES (?, ?, ?, ?, 0, ?, ?)");
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    $st->bind_param('isssis', $company_id, $CANONICAL, $json, $status, $uid, $creator);
    $ok = $st->execute();
    $st->close();
    ems_gov_flash_redirect(basename(__FILE__), $ok ? 'حُفظ الصف ✅' : 'تعذر الحفظ ❌', 'GOV-OK-200', '');
    exit();
}

/* ── القراءة: صفوف الكيان لهذه الشاشة ───────────────────────────────────── */
$rows = array();
$sql = "SELECT id, payload, status, created_by_name, created_at, is_seed
          FROM cmp03_screen_rows
         WHERE canonical_file = ?" . ($is_super_admin && $company_id <= 0 ? '' : ' AND company_id = ?') . "
         ORDER BY id DESC LIMIT 500";
$st = $conn->prepare($sql);
if ($is_super_admin && $company_id <= 0) { $st->bind_param('s', $CANONICAL); }
else { $st->bind_param('si', $CANONICAL, $company_id); }
$st->execute();
$rs = $st->get_result();
while ($x = $rs->fetch_assoc()) {
    $x['payload'] = json_decode((string) $x['payload'], true) ?: array();
    $rows[] = $x;
}
$st->close();

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

$page_title = 'إيكوبيشن | حسابات الموردين البنكية';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell" dir="rtl">
    <?php
    $header_title = 'حسابات الموردين البنكية';
    $header_icon = 'fa fa-building-columns';
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
            <h5><i class="fa fa-plus"></i> إضافة — حسابات الموردين البنكية</h5>
        </div><div class="card-body">
            <div class="form-section"><div class="form-grid">
                <div class="form-group"><label>رقم السجل</label>
                    <input type="text" name="f0" required maxlength="190"></div>
                <div class="form-group"><label>المورد</label>
                    <input type="text" name="f1" maxlength="190"></div>
                <div class="form-group"><label>اسم المستفيد</label>
                    <input type="text" name="f2" maxlength="190"></div>
                <div class="form-group"><label>رقم الحساب</label>
                    <input type="text" name="f3" maxlength="190"></div>
                <div class="form-group"><label>IBAN</label>
                    <input type="text" name="f4" maxlength="190"></div>
                <div class="form-group"><label>البنك</label>
                    <input type="text" name="f5" maxlength="190"></div>
                <div class="form-group"><label>الفرع</label>
                    <input type="text" name="f6" maxlength="190"></div>
                <div class="form-group"><label>العملة</label>
                    <input type="text" name="f7" maxlength="190"></div>
                <div class="form-group"><label>مستند إثبات الحساب</label>
                    <input type="text" name="f8" maxlength="190"></div>
                <div class="form-group"><label>حالة التحقق</label>
                    <input type="text" name="f9" maxlength="190"></div>
                <div class="form-group"><label>تاريخ التحقق</label>
                    <input type="date" name="f10"></div>
                <div class="form-group"><label>محقِّق الحساب</label>
                    <input type="text" name="f11" maxlength="190"></div>
                <div class="form-group"><label>آخر سداد على الحساب</label>
                    <input type="text" name="f12" maxlength="190"></div>
                <div class="form-group"><label>درجة السرية</label>
                    <input type="text" name="f13" maxlength="190"></div>
                <div class="form-group"><label>الحالة</label>
                    <select name="f14"><option value="مسودة">مسودة</option><option value="قيد المراجعة">قيد المراجعة</option><option value="معتمد">معتمد</option><option value="موقوف">موقوف</option><option value="ملغي">ملغي</option></select></div>
                <div class="form-group"><label>المعتمِد — الاسم والصفة</label>
                    <input type="text" name="f15" maxlength="190"></div>
                <div class="form-group"><label>تاريخ الاعتماد</label>
                    <input type="date" name="f16"></div>
                <div class="form-group"><label>مرجع التفويض</label>
                    <input type="text" name="f17" maxlength="190"></div>
                <div class="form-group"><label>المرجع الأب</label>
                    <input type="text" name="f18" maxlength="190"></div>
                <div class="form-group"><label>المرفق</label>
                    <input type="text" name="f19" maxlength="190"></div>
                <div class="form-group"><label>درجة الأثر</label>
                    <input type="text" name="f20" maxlength="190"></div>
            </div></div>
            <div style="margin-top:12px;display:flex;gap:10px">
                <button type="submit" class="btn-save"><i class="fa fa-save"></i> حفظ</button>
                <button type="button" class="btn-cancel" id="cmp03CancelBtn"><i class="fa fa-times"></i> إلغاء</button>
            </div>
        </div></div>
    </form>

    <div class="card"><div class="card-body">
        <div class="table-responsive">
        <table class="alltables display" id="supplier_bankTable">
            <thead><tr>
            <th>رقم السجل</th>
            <th>المورد</th>
            <th>اسم المستفيد</th>
            <th>رقم الحساب</th>
            <th>IBAN</th>
            <th>البنك</th>
            <th>الفرع</th>
            <th class="ems-gov-th" data-gov="currency" data-slice="3" title="لا مبلغ بلا عملة">العملة</th>
            <th>مستند إثبات الحساب</th>
            <th>حالة التحقق</th>
            <th>تاريخ التحقق</th>
            <th>محقِّق الحساب</th>
            <th>آخر سداد على الحساب</th>
            <th>درجة السرية</th>
            <th class="ems-gov-th" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
            <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
            <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
            <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
            <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
            <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
            <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
            <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
            <th class="ems-gov-th none" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
            <th class="ems-gov-th none" data-gov="idem_key" data-slice="2" title="يمنع وقوع الأثر مرتين بمفتاح مركب">مفتاح منع التكرار</th>
            <th class="ems-gov-th none" data-gov="impact_grade" data-slice="2" title="مبدئي أم نهائي — فلا يقفل مبدئي ماليًّا">درجة الأثر</th>
            <th class="ems-gov-th none" data-gov="reversed_by" data-slice="2" title="مرجع الحركة التي عكسته">معكوس بـ</th>
            <th class="ems-gov-th none" data-gov="reversal_of" data-slice="2" title="مرجع الحركة التي عكسها">عكس عن</th>
            <th class="ems-gov-th none" data-gov="view_log" data-slice="2" title="من قرأ البيان الحساس ومتى">سجل الاطّلاع</th>
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
