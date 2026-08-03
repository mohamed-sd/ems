<?php
/**
 * Procurement/po_match.php — مطابقة الفاتورة بالأمر والاستلام (CMP-03 ⑥ v2 — بتصميم SCR-DES حرفيًّا + إدخال حي)
 * ───────────────────────────────────────────────────────────────────────────
 * الورقة المالكة: 05 · المشتريات · الأعمدة 21 بترتيب المستند وطبقة
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

$CANONICAL = 'po_match.php';
$COLS   = array (
  0 => 'رقم المحضر',
  1 => 'أمر الشراء',
  2 => 'سند الاستلام',
  3 => 'رقم فاتورة المورد',
  4 => 'تاريخ الفاتورة',
  5 => 'الكمية بالأمر',
  6 => 'الكمية المستلَمة',
  7 => 'الكمية بالفاتورة',
  8 => 'السعر بالأمر',
  9 => 'السعر بالفاتورة',
  10 => 'فرق الكمية',
  11 => 'فرق السعر',
  12 => 'تصنيف الفرق',
  13 => 'تفسير الفرق',
  14 => 'نتيجة المطابقة',
  15 => 'طابقه',
  16 => 'اعتمده',
  17 => 'الحالة',
  18 => 'الكيان',
  19 => 'مركز التكلفة',
  20 => 'سعر الصرف ومصدره',
);
$FIELDS = array (
  0 => 'رقم المحضر',
  1 => 'أمر الشراء',
  2 => 'سند الاستلام',
  3 => 'رقم فاتورة المورد',
  4 => 'تاريخ الفاتورة',
  5 => 'الكمية بالأمر',
  6 => 'الكمية المستلَمة',
  7 => 'الكمية بالفاتورة',
  8 => 'السعر بالأمر',
  9 => 'السعر بالفاتورة',
  10 => 'فرق الكمية',
  11 => 'فرق السعر',
  12 => 'تصنيف الفرق',
  13 => 'تفسير الفرق',
  14 => 'نتيجة المطابقة',
  15 => 'طابقه',
  16 => 'اعتمده',
  17 => 'الحالة',
  18 => 'مركز التكلفة',
  19 => 'سعر الصرف ومصدره',
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

$page_title = 'إيكوبيشن | مطابقة الفاتورة بالأمر والاستلام';
include '../inheader.php';
include '../insidebar.php';
?>
<div class="main ems-unified-page-shell" dir="rtl">
    <?php
    $header_title = 'مطابقة الفاتورة بالأمر والاستلام';
    $header_icon = 'fa fa-check-double';
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
            <h5><i class="fa fa-plus"></i> إضافة — مطابقة الفاتورة بالأمر والاستلام</h5>
        </div><div class="card-body">
            <div class="form-section"><div class="form-grid">
                <div class="form-group"><label>رقم المحضر</label>
                    <input type="text" name="f0" required maxlength="190"></div>
                <div class="form-group"><label>أمر الشراء</label>
                    <input type="text" name="f1" maxlength="190"></div>
                <div class="form-group"><label>سند الاستلام</label>
                    <input type="text" name="f2" maxlength="190"></div>
                <div class="form-group"><label>رقم فاتورة المورد</label>
                    <input type="text" name="f3" maxlength="190"></div>
                <div class="form-group"><label>تاريخ الفاتورة</label>
                    <input type="date" name="f4"></div>
                <div class="form-group"><label>الكمية بالأمر</label>
                    <input type="text" inputmode="decimal" name="f5" placeholder="0"></div>
                <div class="form-group"><label>الكمية المستلَمة</label>
                    <input type="text" inputmode="decimal" name="f6" placeholder="0"></div>
                <div class="form-group"><label>الكمية بالفاتورة</label>
                    <input type="text" inputmode="decimal" name="f7" placeholder="0"></div>
                <div class="form-group"><label>السعر بالأمر</label>
                    <input type="text" inputmode="decimal" name="f8" placeholder="0"></div>
                <div class="form-group"><label>السعر بالفاتورة</label>
                    <input type="text" inputmode="decimal" name="f9" placeholder="0"></div>
                <div class="form-group"><label>فرق الكمية</label>
                    <input type="text" inputmode="decimal" name="f10" placeholder="0"></div>
                <div class="form-group"><label>فرق السعر</label>
                    <input type="text" inputmode="decimal" name="f11" placeholder="0"></div>
                <div class="form-group"><label>تصنيف الفرق</label>
                    <input type="text" name="f12" maxlength="190"></div>
                <div class="form-group"><label>تفسير الفرق</label>
                    <input type="text" name="f13" maxlength="190"></div>
                <div class="form-group"><label>نتيجة المطابقة</label>
                    <input type="text" name="f14" maxlength="190"></div>
                <div class="form-group"><label>طابقه</label>
                    <input type="text" name="f15" maxlength="190"></div>
                <div class="form-group"><label>اعتمده</label>
                    <input type="text" name="f16" maxlength="190"></div>
                <div class="form-group"><label>الحالة</label>
                    <select name="f17"><option value="مسودة">مسودة</option><option value="قيد المراجعة">قيد المراجعة</option><option value="معتمد">معتمد</option><option value="موقوف">موقوف</option><option value="ملغي">ملغي</option></select></div>
                <div class="form-group"><label>مركز التكلفة</label>
                    <input type="text" inputmode="decimal" name="f18" placeholder="0"></div>
                <div class="form-group"><label>سعر الصرف ومصدره</label>
                    <input type="text" inputmode="decimal" name="f19" placeholder="0"></div>
            </div></div>
            <div style="margin-top:12px;display:flex;gap:10px">
                <button type="submit" class="btn-save"><i class="fa fa-save"></i> حفظ</button>
                <button type="button" class="btn-cancel" id="cmp03CancelBtn"><i class="fa fa-times"></i> إلغاء</button>
            </div>
        </div></div>
    </form>

    <div class="card"><div class="card-body">
        <div class="table-responsive">
        <table class="alltables display" id="po_matchTable">
            <thead><tr>
            <th>رقم المحضر</th>
            <th>أمر الشراء</th>
            <th>سند الاستلام</th>
            <th>رقم فاتورة المورد</th>
            <th>تاريخ الفاتورة</th>
            <th>الكمية بالأمر</th>
            <th>الكمية المستلَمة</th>
            <th>الكمية بالفاتورة</th>
            <th>السعر بالأمر</th>
            <th>السعر بالفاتورة</th>
            <th>فرق الكمية</th>
            <th>فرق السعر</th>
            <th>تصنيف الفرق</th>
            <th>تفسير الفرق</th>
            <th>نتيجة المطابقة</th>
            <th>طابقه</th>
            <th>اعتمده</th>
            <th class="ems-gov-th" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
            <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
            <th class="ems-gov-th" data-gov="cost_center" data-slice="3" title="وجهة التحميل">مركز التكلفة</th>
            <th class="ems-gov-th" data-gov="fx_rate_source" data-slice="3" title="ما خالف عملة الدفاتر يحمل السعر ومصدره">سعر الصرف ومصدره</th>
            </tr></thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="21" class="text-center text-muted">لا بياناتَ بعدُ — أضف أول صفٍّ بزر «إضافة»</td></tr>
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
