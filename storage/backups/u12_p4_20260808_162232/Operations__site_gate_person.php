<?php
/**
 * Operations/site_gate_person.php — أذون دخول وخروج المشغّلين (CMP-03 ⑥ v2 — بتصميم SCR-DES حرفيًّا + إدخال حي)
 * ───────────────────────────────────────────────────────────────────────────
 * الورقة المالكة: 01 · إدارة الموقع · الأعمدة 22 بترتيب المستند وطبقة
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
    header("Location: ../login.php?msg=غير+مصرح");
    exit();
}

require_once __DIR__ . '/../includes/cmp03_local_store.php'; // الموجة ٢ — الجدول الأصلي

$CANONICAL = 'site_gate_person.php';
$COLS   = array (
  0 => 'الكيان',
  1 => 'رقم الإذن',
  2 => 'نوع الإذن',
  3 => 'الموقع',
  4 => 'كود المشغّل',
  5 => 'الاسم',
  6 => 'التبعية',
  7 => 'المورد التابع له',
  8 => 'سبب الحركة',
  9 => 'دورة التناوب',
  10 => 'تاريخ بداية العمل',
  11 => 'تاريخ نهاية العمل',
  12 => 'رحلة الدخول أو الخروج',
  13 => 'السكن المخصَّص',
  14 => 'حالة الرخصة',
  15 => 'حالة الفحص الطبي',
  16 => 'المصادقة الأمنية',
  17 => 'المُنشئ — الاسم والصفة',
  18 => 'اعتماد مدير الموقع',
  19 => 'اعتماد مدير التشغيل',
  20 => 'تاريخ الاعتماد',
  21 => 'الحالة',
);
$FIELDS = array (
  0 => 'رقم الإذن',
  1 => 'نوع الإذن',
  2 => 'الموقع',
  3 => 'كود المشغّل',
  4 => 'الاسم',
  5 => 'التبعية',
  6 => 'المورد التابع له',
  7 => 'سبب الحركة',
  8 => 'دورة التناوب',
  9 => 'تاريخ بداية العمل',
  10 => 'تاريخ نهاية العمل',
  11 => 'رحلة الدخول أو الخروج',
  12 => 'السكن المخصَّص',
  13 => 'حالة الرخصة',
  14 => 'حالة الفحص الطبي',
  15 => 'المصادقة الأمنية',
  16 => 'اعتماد مدير الموقع',
  17 => 'اعتماد مدير التشغيل',
  18 => 'تاريخ الاعتماد',
  19 => 'الحالة',
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
    header('Location: ' . basename(__FILE__) . '?msg=' . rawurlencode($ok ? 'حُفظ الصف ✅' : 'تعذر الحفظ ❌'));
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

$page_title = 'إيكوبيشن | أذون دخول وخروج المشغّلين';
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell" dir="rtl">
    <?php
    $header_title = 'أذون دخول وخروج المشغّلين';
    $header_icon = 'fa fa-door-open';
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
            <h5><i class="fa fa-plus"></i> إضافة — أذون دخول وخروج المشغّلين</h5>
        </div><div class="card-body">
            <div class="form-section"><div class="form-grid">
                <div class="form-group"><label>رقم الإذن</label>
                    <input type="text" name="f0" required maxlength="190"></div>
                <div class="form-group"><label>نوع الإذن</label>
                    <input type="text" name="f1" maxlength="190"></div>
                <div class="form-group"><label>الموقع</label>
                    <input type="text" name="f2" maxlength="190"></div>
                <div class="form-group"><label>كود المشغّل</label>
                    <input type="text" name="f3" maxlength="190"></div>
                <div class="form-group"><label>الاسم</label>
                    <input type="text" name="f4" maxlength="190"></div>
                <div class="form-group"><label>التبعية</label>
                    <input type="text" name="f5" maxlength="190"></div>
                <div class="form-group"><label>المورد التابع له</label>
                    <input type="text" name="f6" maxlength="190"></div>
                <div class="form-group"><label>سبب الحركة</label>
                    <input type="text" name="f7" maxlength="190"></div>
                <div class="form-group"><label>دورة التناوب</label>
                    <input type="text" name="f8" maxlength="190"></div>
                <div class="form-group"><label>تاريخ بداية العمل</label>
                    <input type="date" name="f9"></div>
                <div class="form-group"><label>تاريخ نهاية العمل</label>
                    <input type="date" name="f10"></div>
                <div class="form-group"><label>رحلة الدخول أو الخروج</label>
                    <input type="text" name="f11" maxlength="190"></div>
                <div class="form-group"><label>السكن المخصَّص</label>
                    <input type="text" name="f12" maxlength="190"></div>
                <div class="form-group"><label>حالة الرخصة</label>
                    <input type="text" name="f13" maxlength="190"></div>
                <div class="form-group"><label>حالة الفحص الطبي</label>
                    <input type="text" name="f14" maxlength="190"></div>
                <div class="form-group"><label>المصادقة الأمنية</label>
                    <input type="text" name="f15" maxlength="190"></div>
                <div class="form-group"><label>اعتماد مدير الموقع</label>
                    <input type="text" name="f16" maxlength="190"></div>
                <div class="form-group"><label>اعتماد مدير التشغيل</label>
                    <input type="text" name="f17" maxlength="190"></div>
                <div class="form-group"><label>تاريخ الاعتماد</label>
                    <input type="date" name="f18"></div>
                <div class="form-group"><label>الحالة</label>
                    <select name="f19"><option value="مسودة">مسودة</option><option value="قيد المراجعة">قيد المراجعة</option><option value="معتمد">معتمد</option><option value="موقوف">موقوف</option><option value="ملغي">ملغي</option></select></div>
            </div></div>
            <div style="margin-top:12px;display:flex;gap:10px">
                <button type="submit" class="btn-save"><i class="fa fa-save"></i> حفظ</button>
                <button type="button" class="btn-cancel" id="cmp03CancelBtn"><i class="fa fa-times"></i> إلغاء</button>
            </div>
        </div></div>
    </form>

    <div class="card"><div class="card-body">
        <div class="table-responsive">
        <table class="alltables display" id="site_gate_personTable">
            <thead><tr>
            <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
            <th>رقم الإذن</th>
            <th>نوع الإذن</th>
            <th>الموقع</th>
            <th>كود المشغّل</th>
            <th>الاسم</th>
            <th>التبعية</th>
            <th>المورد التابع له</th>
            <th>سبب الحركة</th>
            <th>دورة التناوب</th>
            <th>تاريخ بداية العمل</th>
            <th>تاريخ نهاية العمل</th>
            <th>رحلة الدخول أو الخروج</th>
            <th>السكن المخصَّص</th>
            <th>حالة الرخصة</th>
            <th>حالة الفحص الطبي</th>
            <th>المصادقة الأمنية</th>
            <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
            <th>اعتماد مدير الموقع</th>
            <th>اعتماد مدير التشغيل</th>
            <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
            <th class="ems-gov-th" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
            </tr></thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="22" class="text-center text-muted">لا بياناتَ بعدُ — أضف أول صفٍّ بزر «إضافة»</td></tr>
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
