<?php
// شواهد المتطلبات (AC-E06-03 · موجة ٣): SCN-899 · SCN-901 · SCN-902 · SCN-903 · SCN-904 · SCN-905
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
    ems_gov_flash_redirect('../login.php', 'غير مصرح', 'GOV-INFO-200', '');
    exit();
}

require_once __DIR__ . '/../includes/cmp03_local_store.php'; // الموجة ٢ — الجدول الأصلي

$CANONICAL = 'perm_explain.php';

// حارس الشاشة (M-14 BR-GOV-01): can_view من modules — والسوبر يمر
$__pp = check_page_permissions($conn, 'Governance/perm_explain.php');
if (!$is_super_admin && empty($__pp['can_view'])) {
    require_once __DIR__ . '/../includes/perm_explain_live.php';
    $__why = ems_deny_message($conn, intval($_SESSION['user']['role'] ?? 0), 'Governance/perm_explain.php');
    ems_gov_flash_redirect('../main/dashboard.php', $__why, 'GOV-INFO-200', '');
    exit();
}
if (!$is_super_admin && $_SERVER['REQUEST_METHOD'] === 'POST' && empty($__pp['can_add']) && empty($__pp['can_edit'])) {
    http_response_code(403);
    exit('غير مصرح بالكتابة في هذه الشاشة');
}
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
    // الموجة ٢: الحفظ في الجدول الأصلي للشاشة (الفارغ NULL — لا مخزن بينيًّا)
    $ok = cmp03_store_insert($conn, $company_id, $CANONICAL, $payload, $status, $uid, $creator);
    header('Location: ' . basename(__FILE__) . '?msg=' . rawurlencode($ok ? 'حُفظ الصف ✅' : 'تعذر الحفظ ❌'));
    exit();
}

/* ── المفسِّر الحي (E-04 SEC-001-د · قرار المالك 2026-08-06): «كلُّ مصدرٍ
   بحكمه والنتيجةُ النهائية» — يُحسب من حيث يقع المنعُ فعلًا (modules ×
   role_permissions) لا من طبقة SEC-01 الفارغة. ───────────────────────── */
require_once __DIR__ . '/../includes/perm_explain_live.php';
$px_role   = isset($_GET['px_role']) ? intval($_GET['px_role']) : 0;
$px_screen = isset($_GET['px_screen']) ? trim((string) $_GET['px_screen']) : '';
$px_result = ($px_role > 0 && $px_screen !== '')
    ? ems_explain_screen_access($conn, $px_role, $px_screen, false) : null;
$px_roles = array();
$__rr = mysqli_query($conn, "SELECT id, name FROM roles ORDER BY id");
while ($__rr && ($__x = mysqli_fetch_assoc($__rr))) { $px_roles[] = $__x; }
$px_screens = array();
$__sr = mysqli_query($conn, "SELECT code, name FROM modules WHERE code LIKE '%.php' ORDER BY name LIMIT 400");
while ($__sr && ($__x = mysqli_fetch_assoc($__sr))) { $px_screens[] = $__x; }

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

$page_title = 'إيكوبيشن | تفسير مصدر الصلاحية';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($__pp) ? $__pp : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
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

    <!-- ── المفسِّر الحي: دورٌ × شاشة ← كلُّ مصدرٍ بحكمه والنتيجة ── -->
    <div class="card"><div class="card-header">
        <h5><i class="fa fa-question-circle"></i> لماذا يرى هذا الدورُ هذه الشاشة — أو لا يراها؟</h5>
    </div><div class="card-body">
        <form method="get" action="" class="ems-form" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
            <div class="form-group" style="min-width:220px"><label>الدور</label>
                <select name="px_role" class="form-control">
                    <option value="">— اختر —</option>
                    <?php foreach ($px_roles as $r0): ?>
                    <option value="<?php echo (int) $r0['id']; ?>"<?php echo $px_role === (int) $r0['id'] ? ' selected' : ''; ?>>
                        <?php echo htmlspecialchars($r0['id'] . ' — ' . $r0['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select></div>
            <div class="form-group" style="min-width:320px"><label>الشاشة</label>
                <select name="px_screen" class="form-control">
                    <option value="">— اختر —</option>
                    <?php foreach ($px_screens as $s0): ?>
                    <option value="<?php echo htmlspecialchars($s0['code'], ENT_QUOTES, 'UTF-8'); ?>"<?php echo $px_screen === $s0['code'] ? ' selected' : ''; ?>>
                        <?php echo htmlspecialchars($s0['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select></div>
            <button type="submit" class="btn-save"><i class="fa fa-search"></i> فسِّر</button>
        </form>
        <?php if ($px_result !== null): ?>
        <div style="margin-top:14px;padding:12px;border-radius:8px;border:1px solid #ddd">
            <div style="font-weight:800;margin-bottom:8px">
                <?php echo $px_result['allowed'] ? '✔ مسموح' : '✘ ممنوع'; ?>
                — <?php echo htmlspecialchars($px_result['reason'], ENT_QUOTES, 'UTF-8'); ?>
            </div>
            <table class="alltables no-datatable" data-no-dt="1" style="width:100%">
                <thead><tr><th style="width:30%">المصدر</th><th>حكمُه</th></tr></thead>
                <tbody>
                <?php foreach ($px_result['chain'] as $step): ?>
                    <tr><td><?php echo htmlspecialchars($step['step'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($step['verdict'], ENT_QUOTES, 'UTF-8'); ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php if (!empty($px_result['grantor'])): ?>
            <div style="margin-top:8px;color:#555">المنحُ من: <?php echo htmlspecialchars($px_result['grantor'], ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div></div>

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
