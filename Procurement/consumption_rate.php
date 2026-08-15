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
    /* ══ INJ-0356 · «الشاشةُ تعرض معدلَ الاستهلاك **محسوبًا من الحركات** لكل
         معدةٍ وفترة، **ولا يوجد فيها حقلُ إدخالٍ يدويٍّ للمعدل**» ══════════════
         ── ما كان: نموذجٌ مسطَّحٌ بخمسةٍ وعشرين حقلًا نصيًّا حرًّا يُدخلها المستخدمُ
         بيدِه — بينما المعدلُ **محسوبٌ فعلًا** في
         `ProcReorderService::consumption` من حركاتِ `proc_stock_move`.
         فرقمٌ يُقرَّر عليه بالتوريدِ مصدرُه أصابعُ موظفٍ لا دفترُ حركة.
       ◆ فالكتابةُ اليدويةُ **تُردُّ برمزٍ محكوم**، والشاشةُ صارت قارئةً حاسبة. */
    ems_gov_flash_redirect(basename(__FILE__),
        'معدلُ الاستهلاكِ **محسوبٌ من حركاتِ المخزن** ولا يُكتب بيد — '
        . 'سجّلِ الصرفَ في «حركات المخزون» (استلام/صرف) ويُحتسب المعدلُ آليًّا ❌',
        'GOV-FAIL-409',
        'افتحْ «حركات المخزون» (استلام/صرف) وسجّلِ الصرفَ — والمعدلُ يظهر هنا فورًا');
    exit();
}

/* ══ القراءة: المعدلُ محسوبٌ من الحركاتِ لكلِّ صنفٍ وفترة (INJ-0356) ══════════
     ◆ والصفوفُ الموروثةُ في `scr_consumption_rate` **لا تُحذف**: تُعرض تحت
       الجدولِ المحسوبِ موسومةً «سجلٌّ سابقٌ للربط» — «المخزنُ الملغى يُحوَّل
       إلى قارئٍ ولا يُحذف». */
require_once __DIR__ . '/../app/Services/Procurement/ProcReorderService.php';
$WINDOW = 90;
$computed = array();
$__items = $conn->query('SELECT id, name, unit FROM proc_item
                          WHERE company_id = ' . (int) $company_id . ' ORDER BY name LIMIT 200');
if ($__items === false) {
    $computeErr = 'PRC-500 · تعذّرت قراءةُ الأصناف — ' . $conn->error;
} else {
    while ($it = $__items->fetch_assoc()) {
        $c = \App\Services\Procurement\ProcReorderService::consumption(
            $conn, $company_id, (int) $it['id'], $WINDOW);
        if ((float) $c['consumed'] <= 0) { continue; }   // لا يُعرض صنفٌ بلا حركة
        $computed[] = array(
            'item'    => (string) $it['name'],
            'unit'    => (string) ($it['unit'] ?? ''),
            'window'  => (int) $c['window_days'],
            'consumed' => (float) $c['consumed'],
            'daily'   => (float) $c['avg_daily'],
            'lead'    => (int) $c['lead_time_days'],
            'safety'  => (float) $c['safety_stock'],
            'trigger' => (float) $c['suggested_trigger'],
        );
    }
}

/* الموروثُ — يُعرض قارئًا لا يُكتب */
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
    /* INJ-0356: لا زرَّ إضافةٍ — الشاشةُ قارئةٌ حاسبة، والصرفُ يُسجَّل في بابه */
    $header_actions = array(
        array('tag' => 'a', 'href' => 'wh_receipt.php', 'class' => '', 'icon' => 'fa fa-right-left',
              'label' => 'حركات المخزون', 'title' => 'سجّلِ الصرفَ هناك — والمعدلُ يُحتسب هنا آليًّا'),
    );
    $header_back = false;
    include '../includes/page_header.php';
    if (isset($_GET['msg'])) {
        echo '<div class="alert alert-info">' . htmlspecialchars((string) $_GET['msg'], ENT_QUOTES, 'UTF-8') . '</div>';
    }
    ?>

    <?php /* ⇐ INJ-0356 · «**ولا يوجد فيها حقلُ إدخالٍ يدويٍّ للمعدل**» —
             فورمُ الإدخالِ المسطَّحُ ذو الخمسةِ والعشرين حقلًا رُفع كلُّه:
             المعدلُ محسوبٌ من حركاتِ المخزن، وما يُحسب لا يُكتب بيد. */ ?>
    <div class="card"><div class="card-header">
        <h5><i class="fa fa-calculator"></i> معدلُ الاستهلاك — محسوبًا من الحركات (آخر <?php echo (int) $WINDOW; ?> يومًا)</h5>
    </div><div class="card-body">
        <?php if (isset($computeErr)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($computeErr, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <p class="text-muted" style="font-size:.9em">
            المصدرُ <code>proc_stock_move</code> — حركاتُ الصرفِ وحدَها. ولا يُعرض صنفٌ بلا حركةٍ في المدة.
        </p>
        <div class="table-responsive">
        <table class="alltables display" id="consumptionComputedTable">
            <thead><tr>
                <th>الصنف</th><th>الوحدة</th><th>المدة (يومًا)</th><th>المنصرف</th>
                <th>المعدل اليومي</th><th>مهلة التوريد</th><th>مخزون الأمان</th><th>حدُّ الطلب المقترح</th>
            </tr></thead><tbody>
            <?php if (!$computed): ?>
                <tr><td colspan="8" class="text-center text-muted">لا حركةَ صرفٍ في المدة — فلا معدلَ يُحسب (ولا يُخترع)</td></tr>
            <?php else: foreach ($computed as $c): ?>
                <tr>
                    <td><?php echo htmlspecialchars($c['item'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($c['unit'] !== '' ? $c['unit'] : '—', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo (int) $c['window']; ?></td>
                    <td><?php echo number_format($c['consumed'], 2); ?></td>
                    <td><strong><?php echo number_format($c['daily'], 3); ?></strong></td>
                    <td><?php echo (int) $c['lead']; ?></td>
                    <td><?php echo number_format($c['safety'], 2); ?></td>
                    <td><?php echo number_format($c['trigger'], 2); ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody></table>
        </div>
    </div></div>

    <?php if ($rows): ?>
    <div class="alert alert-warning" style="margin-top:14px">
        <strong>سجلٌّ سابقٌ للربط.</strong> الصفوفُ أدناه أُدخلت يدويًّا قبلَ ربطِ الشاشةِ
        بحركاتِ المخزن — تبقى للقراءةِ التاريخيةِ ولا يُكتب فيها بعد (لم يُحذف صفٌّ).
    </div>
    <?php endif; ?>

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
/* INJ-0356: رُفع مبدِّلُ فورمِ الإضافة — لا فورمَ إدخالٍ في شاشةٍ حاسبة. */
(function () {})();
</script>
