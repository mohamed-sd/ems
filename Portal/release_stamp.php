<?php
// شواهد المتطلبات (AC-E06-03 · موجة ٣): SCN-942
/**
 * Portal/release_stamp.php — بصمة الإصدار وتقرير النشر (CMP-03 ⑥ v2 — بتصميم SCR-DES حرفيًّا + إدخال حي)
 * ───────────────────────────────────────────────────────────────────────────
 * الورقة المالكة: 00 · الإدارة التنفيذية · الأعمدة 18 بترتيب المستند وطبقة
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

$CANONICAL = 'release_stamp.php';
$COLS   = array (
  0 => 'الكيان',
  1 => 'رقم الإصدار',
  2 => 'بصمة الإصدار',
  3 => 'تاريخ النشر',
  4 => 'نوع الإصدار',
  5 => 'الشاشات المضافة',
  6 => 'الشاشات المعدَّلة',
  7 => 'الأعمدة المضافة',
  8 => 'الأفعال المضافة',
  9 => 'القواعد المتغيرة',
  10 => 'الهجرات المنفَّذة',
  11 => 'تقرير الاكتمال',
  12 => 'الاختبارات المجتازة',
  13 => 'الاختبارات الراسبة',
  14 => 'علَم الرجوع',
  15 => 'الناشر — الاسم والصفة',
  16 => 'المعتمِد — الاسم والصفة',
  17 => 'الحالة',
);
$FIELDS = array (
  0 => 'رقم الإصدار',
  1 => 'بصمة الإصدار',
  2 => 'تاريخ النشر',
  3 => 'نوع الإصدار',
  4 => 'الشاشات المضافة',
  5 => 'الشاشات المعدَّلة',
  6 => 'الأعمدة المضافة',
  7 => 'الأفعال المضافة',
  8 => 'القواعد المتغيرة',
  9 => 'الهجرات المنفَّذة',
  10 => 'تقرير الاكتمال',
  11 => 'الاختبارات المجتازة',
  12 => 'الاختبارات الراسبة',
  13 => 'علَم الرجوع',
  14 => 'الناشر — الاسم والصفة',
  15 => 'المعتمِد — الاسم والصفة',
  16 => 'الحالة',
);

/* ══ INJ-0124 · البصمةُ تُولَّد خادميًّا ولا تُدخَل يدويًّا ═══════════════════════
     نصُّ القبول: «فتحُ شاشة بصمة الإصدار يعرض **بصمةً مولَّدةً تطابق النسخةَ
     المنشورة**، **ولا يوجد فيها فورمُ إدخالٍ يدوي**».
     والمقيسُ قبلَه: فورمٌ عامٌّ بثلاثةَ عشرَ حقلًا يكتبها المُدخِل — **فبصمةُ
     الإصدارِ رأيُ من يكتبها لا حقيقةُ ما نُشر**. وبصمةٌ تُكتب باليدِ لا تُثبت
     شيئًا: تُطابق ما يريده الكاتبُ لا ما يعمل على الخادم.
   ◆ **والبصمةُ تُشتقُّ ممّا نُشر فعلًا**: بصمةُ مخطَّطِ القاعدةِ المُصدَّرِ
     للمثبِّت · وعددُ الهجراتِ المطبَّقة · وعددُ الشاشاتِ الحيّة. فتغيُّرُ أيٍّ
     منها يغيّر البصمةَ حتمًا، وثباتُها يعني أنَّ النسخةَ لم تتغيّر.
   ◆ ولا تُخزَّن: تُحسب عند كلِّ فتح — فلا صفٌّ يتقادم عن مصدرِه. */
if (!function_exists('release_stamp_compute')) {
    function release_stamp_compute($conn, $root)
    {
        $parts = array();
        $man = $root . '/database/schema/MANIFEST.json';
        $sch = $root . '/database/schema/schema.sql';
        $parts['schema_manifest'] = is_file($man) ? substr(md5_file($man), 0, 12) : '—';
        $parts['schema_sql']      = is_file($sch) ? substr(md5_file($sch), 0, 12) : '—';
        $parts['schema_bytes']    = is_file($sch) ? (int) filesize($sch) : 0;

        $mig = glob($root . '/database/migrations/*.php');
        $parts['migrations'] = is_array($mig) ? count($mig) : 0;

        $screens = 0;
        $r = @$conn->query("SELECT COUNT(*) FROM modules");
        if ($r && ($x = $r->fetch_row())) { $screens = (int) $x[0]; }
        $parts['modules'] = $screens;

        $tables = 0;
        $r2 = @$conn->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()");
        if ($r2 && ($x2 = $r2->fetch_row())) { $tables = (int) $x2[0]; }
        $parts['tables'] = $tables;

        $seed = implode('|', array($parts['schema_manifest'], $parts['schema_sql'],
            (string) $parts['schema_bytes'], (string) $parts['migrations'],
            (string) $parts['modules'], (string) $parts['tables']));
        $parts['stamp'] = strtoupper(substr(sha1($seed), 0, 16));
        $parts['computed_at'] = date('Y-m-d H:i:s');
        return $parts;
    }
}
$RELEASE = release_stamp_compute($conn, dirname(__DIR__));

/* ◆ **ولا مسلكَ إدخالٍ يدويّ**: مَن أرسل الفورمَ القديمَ يُردُّ برمزٍ يُقرأ. */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['cmp03_action'] ?? '') === 'add') {
    ems_gov_flash_redirect(basename(__FILE__),
        'REL-422-NOMANUAL: بصمةُ الإصدارِ تُولَّد من النسخةِ المنشورةِ ولا تُدخَل يدويًّا ❌',
        'GOV-FAIL-422', 'البصمةُ حقيقةٌ تُقاس لا رأيٌ يُكتب');
    exit();
}
/* ◆ **والمسلكُ القديمُ نُزع لا عُطِّل**: شفرةٌ ميتةٌ خلف `if (false)` دَينٌ
     يُقرأ يومًا على أنه مسلكٌ قائم. فما لا يُستعمل يُحذف. */

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

$page_title = 'إيكوبيشن | بصمة الإصدار وتقرير النشر';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<?php /* نُقلت أنماطُ هذه الشاشةِ إلى assets/css/ems-screens.css (UXUI-01 البند ٦: صفرُ نمطٍ محليّ) */ ?>
<div class="main ems-unified-page-shell" dir="rtl">
    <?php
    $header_title = 'بصمة الإصدار وتقرير النشر';
    $header_icon = 'fa fa-rocket';
    $header_actions = array(
        array('tag' => 'button', 'id' => 'cmp03AddBtn', 'class' => '', 'icon' => 'fa fa-plus',
              'label' => 'إضافة', 'title' => 'إضافة صف جديد', 'attrs' => 'type="button"'),
    );
    $header_back = false;
    include '../includes/page_header.php';
    // حزمةُ الحالاتِ الدنيا (بوابة ٩) — مخفيةٌ افتراضًا ويُظهرها منطقُ الشاشة
    echo ems_states_bundle('لا سجلاتِ نشرٍ مدوَّنةً بعدُ', 'ختمُ الإصدارِ يُحسب حيًّا في اللوحةِ أعلاه — وسجلُّ النشرِ يُضاف بزرِّ «إضافة»');
    if (isset($_GET['msg'])) {
        echo '<div class="alert alert-info">' . htmlspecialchars((string) $_GET['msg'], ENT_QUOTES, 'UTF-8') . '</div>';
    }
    ?>

    <?php
    /* ══ INJ-0124 · لوحةُ البصمةِ المولَّدة — قراءةٌ فقط ═══════════════════════════
         «يعرض بصمةً **مولَّدةً تطابق النسخةَ المنشورة**، **ولا يوجد فيها فورمُ
         إدخالٍ يدوي**». فالفورمُ ذو الثلاثةَ عشرَ حقلًا أُزيل، وحلَّت محلَّه
         بصمةٌ تُحسب عند كلِّ فتحٍ من مخطَّطِ القاعدةِ المُصدَّرِ وعددِ الهجراتِ
         والشاشاتِ والجداول — فتغيُّرُ أيٍّ منها يغيّرها حتمًا. */
    ?>
    <div class="card"><div class="card-header">
        <h5><i class="fa fa-fingerprint"></i> بصمةُ الإصدارِ الحالية — مولَّدةٌ من النسخةِ المنشورة</h5>
    </div><div class="card-body">
        <p class="text-muted ems-pts-note">
            هذه البصمةُ <strong>تُحسب ولا تُكتب</strong>: تُشتقُّ من مخطَّطِ القاعدةِ
            المُصدَّرِ للمثبِّت وعددِ الهجراتِ المطبَّقةِ والشاشاتِ الحيّةِ وجداولِ
            القاعدة. فبصمةٌ تُدخَل يدويًّا تُطابق ما يريده كاتبُها لا ما يعمل على الخادم.
        </p>
        <table class="table table-striped" data-no-dt>
            <tbody>
                <tr><th class="ems-pts-th-key">البصمة</th>
                    <td><strong class="ems-pts-stamp"><?php
                        echo htmlspecialchars((string) $RELEASE['stamp'], ENT_QUOTES, 'UTF-8'); ?></strong></td></tr>
                <tr><th>بصمةُ بيانِ المخطَّط</th>
                    <td><?php echo htmlspecialchars((string) $RELEASE['schema_manifest'], ENT_QUOTES, 'UTF-8'); ?></td></tr>
                <tr><th>بصمةُ ملفِّ المخطَّط</th>
                    <td><?php echo htmlspecialchars((string) $RELEASE['schema_sql'], ENT_QUOTES, 'UTF-8'); ?>
                        · <?php echo number_format((int) $RELEASE['schema_bytes']); ?> بايت</td></tr>
                <tr><th>الهجراتُ في المستودع</th>
                    <td><?php echo (int) $RELEASE['migrations']; ?></td></tr>
                <tr><th>الشاشاتُ المسجَّلة</th>
                    <td><?php echo (int) $RELEASE['modules']; ?></td></tr>
                <tr><th>جداولُ القاعدة</th>
                    <td><?php echo (int) $RELEASE['tables']; ?></td></tr>
                <tr><th>لحظةُ الاحتساب</th>
                    <td><?php echo htmlspecialchars((string) $RELEASE['computed_at'], ENT_QUOTES, 'UTF-8'); ?></td></tr>
            </tbody>
        </table>
    </div></div>

    <div class="card"><div class="card-body">
        <div class="table-responsive">
        <table class="alltables display" id="release_stampTable">
            <thead><tr>
            <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
            <th>رقم الإصدار</th>
            <th>بصمة الإصدار</th>
            <th>تاريخ النشر</th>
            <th>نوع الإصدار</th>
            <th>الشاشات المضافة</th>
            <th>الشاشات المعدَّلة</th>
            <th>الأعمدة المضافة</th>
            <th>الأفعال المضافة</th>
            <th>القواعد المتغيرة</th>
            <th>الهجرات المنفَّذة</th>
            <th>تقرير الاكتمال</th>
            <th>الاختبارات المجتازة</th>
            <th>الاختبارات الراسبة</th>
            <th>علَم الرجوع</th>
            <th>الناشر — الاسم والصفة</th>
            <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
            <th class="ems-gov-th" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
            </tr></thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="18" class="text-center text-muted">لا بياناتَ بعدُ — أضف أول صفٍّ بزر «إضافة»</td></tr>
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
