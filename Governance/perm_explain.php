<?php
/**
 * Governance/perm_explain.php — تفسير مصدر الصلاحية (CMP-03 ⑥ v2 — بتصميم SCR-DES حرفيًّا + إدخال حي)
 * ───────────────────────────────────────────────────────────────────────────
 * الورقة المالكة: 15 · الحوكمة والالتزام · الأعمدة 16 بترتيب المستند وطبقة
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

$CANONICAL = 'perm_explain.php';
$COLS   = array (
  0 => 'الكيان',
  1 => 'الحساب',
  2 => 'الشاشة',
  3 => 'الفعل',
  4 => 'النتيجة النهائية',
  5 => 'مصدر المنح 1',
  6 => 'حكمه',
  7 => 'مصدر المنح 2',
  8 => 'حكمه',
  9 => 'مصدر المنع',
  10 => 'حكمه',
  11 => 'قاعدة الدمج المطبَّقة',
  12 => 'النطاق الناتج',
  13 => 'السقف الناتج',
  14 => 'تاريخ الفحص',
  15 => 'الفاحص',
);
$FIELDS = array (
  0 => 'الحساب',
  1 => 'الشاشة',
  2 => 'الفعل',
  3 => 'النتيجة النهائية',
  4 => 'مصدر المنح 1',
  5 => 'حكمه',
  6 => 'مصدر المنح 2',
  7 => 'حكمه',
  8 => 'مصدر المنع',
  9 => 'حكمه',
  10 => 'قاعدة الدمج المطبَّقة',
  11 => 'النطاق الناتج',
  12 => 'السقف الناتج',
  13 => 'تاريخ الفحص',
  14 => 'الفاحص',
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
    header('Location: ' . basename(__FILE__) . '?msg=' . rawurlencode($ok ? 'حُفظ الصف ✅' : 'تعذر الحفظ ❌'));
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

$page_title = 'إيكوبيشن | تفسير مصدر الصلاحية';
include '../inheader.php';
include '../insidebar.php';
?>
<div class="main ems-unified-page-shell" dir="rtl">
    <?php
    $header_title = 'تفسير مصدر الصلاحية';
    $header_icon = 'fa fa-circle-question';
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
            <h5><i class="fa fa-plus"></i> إضافة — تفسير مصدر الصلاحية</h5>
        </div><div class="card-body">
            <div class="form-section"><div class="form-grid">
                <div class="form-group"><label>الحساب</label>
                    <input type="text" name="f0" required maxlength="190"></div>
                <div class="form-group"><label>الشاشة</label>
                    <input type="text" name="f1" maxlength="190"></div>
                <div class="form-group"><label>الفعل</label>
                    <input type="text" name="f2" maxlength="190"></div>
                <div class="form-group"><label>النتيجة النهائية</label>
                    <input type="text" name="f3" maxlength="190"></div>
                <div class="form-group"><label>مصدر المنح 1</label>
                    <input type="text" name="f4" maxlength="190"></div>
                <div class="form-group"><label>حكمه</label>
                    <input type="text" name="f5" maxlength="190"></div>
                <div class="form-group"><label>مصدر المنح 2</label>
                    <input type="text" name="f6" maxlength="190"></div>
                <div class="form-group"><label>حكمه</label>
                    <input type="text" name="f7" maxlength="190"></div>
                <div class="form-group"><label>مصدر المنع</label>
                    <input type="text" name="f8" maxlength="190"></div>
                <div class="form-group"><label>حكمه</label>
                    <input type="text" name="f9" maxlength="190"></div>
                <div class="form-group"><label>قاعدة الدمج المطبَّقة</label>
                    <input type="text" name="f10" maxlength="190"></div>
                <div class="form-group"><label>النطاق الناتج</label>
                    <input type="text" name="f11" maxlength="190"></div>
                <div class="form-group"><label>السقف الناتج</label>
                    <input type="text" name="f12" maxlength="190"></div>
                <div class="form-group"><label>تاريخ الفحص</label>
                    <input type="date" name="f13"></div>
                <div class="form-group"><label>الفاحص</label>
                    <input type="text" name="f14" maxlength="190"></div>
            </div></div>
            <div style="margin-top:12px;display:flex;gap:10px">
                <button type="submit" class="btn-save"><i class="fa fa-save"></i> حفظ</button>
                <button type="button" class="btn-cancel" id="cmp03CancelBtn"><i class="fa fa-times"></i> إلغاء</button>
            </div>
        </div></div>
    </form>

    <div class="card"><div class="card-body">
        <div class="table-responsive">
        <table class="alltables display" id="perm_explainTable">
            <thead><tr>
            <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
            <th>الحساب</th>
            <th>الشاشة</th>
            <th>الفعل</th>
            <th>النتيجة النهائية</th>
            <th>مصدر المنح 1</th>
            <th>حكمه</th>
            <th>مصدر المنح 2</th>
            <th>حكمه</th>
            <th>مصدر المنع</th>
            <th>حكمه</th>
            <th>قاعدة الدمج المطبَّقة</th>
            <th>النطاق الناتج</th>
            <th>السقف الناتج</th>
            <th>تاريخ الفحص</th>
            <th>الفاحص</th>
            </tr></thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="16" class="text-center text-muted">لا بياناتَ بعدُ — أضف أول صفٍّ بزر «إضافة»</td></tr>
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
