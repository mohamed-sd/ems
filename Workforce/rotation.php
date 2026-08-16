<?php
/**
 * Workforce/rotation.php — دورات التناوب والإجازة الميدانية (CMP-03 ⑥ v2 — بتصميم SCR-DES حرفيًّا + إدخال حي)
 * ───────────────────────────────────────────────────────────────────────────
 * الورقة المالكة: 09 · القوى التشغيلية · الأعمدة 30 بترتيب المستند وطبقة
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

$CANONICAL = 'rotation.php';
$COLS   = array (
  0 => 'رقم الدورة',
  1 => 'كود المشغّل',
  2 => 'الموقع',
  3 => 'المعدة',
  4 => 'نمط التناوب',
  5 => 'نوع الإجازة',
  6 => 'تاريخ الدخول',
  7 => 'تاريخ الخروج',
  8 => 'أيام العمل',
  9 => 'أيام الإجازة',
  10 => 'المناوب المتبادل',
  11 => 'حالة التبادل',
  12 => 'تاريخ تبادل المناوب',
  13 => 'البديل عند تعذّر التبادل',
  14 => 'رحلة الدخول',
  15 => 'رحلة الخروج',
  16 => 'رصيد الإجازة قبل',
  17 => 'رصيد الإجازة بعد',
  18 => 'جدولها',
  19 => 'الحالة',
  20 => 'الكيان',
  21 => 'المُنشئ — الاسم والصفة',
  22 => 'تاريخ الإنشاء',
  23 => 'المعتمِد — الاسم والصفة',
  24 => 'تاريخ الاعتماد',
  25 => 'مرجع التفويض',
  26 => 'المرجع الأب',
  27 => 'المرفق',
  28 => 'مركز التكلفة',
  29 => 'سعر الصرف ومصدره',
);
$FIELDS = array (
  0 => 'رقم الدورة',
  1 => 'كود المشغّل',
  2 => 'الموقع',
  3 => 'المعدة',
  4 => 'نمط التناوب',
  5 => 'نوع الإجازة',
  6 => 'تاريخ الدخول',
  7 => 'تاريخ الخروج',
  8 => 'أيام العمل',
  9 => 'أيام الإجازة',
  10 => 'المناوب المتبادل',
  11 => 'حالة التبادل',
  12 => 'تاريخ تبادل المناوب',
  13 => 'البديل عند تعذّر التبادل',
  14 => 'رحلة الدخول',
  15 => 'رحلة الخروج',
  16 => 'رصيد الإجازة قبل',
  17 => 'رصيد الإجازة بعد',
  18 => 'جدولها',
  19 => 'الحالة',
  20 => 'المعتمِد — الاسم والصفة',
  21 => 'تاريخ الاعتماد',
  22 => 'مرجع التفويض',
  23 => 'المرجع الأب',
  24 => 'المرفق',
  25 => 'مركز التكلفة',
  26 => 'سعر الصرف ومصدره',
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

$page_title = 'إيكوبيشن | دورات التناوب والإجازة الميدانية';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell" dir="rtl">
    <?php
    $header_title = 'دورات التناوب والإجازة الميدانية';
    $header_icon = 'fa fa-rotate';
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
        <?= csrf_field() ?>
        <input type="hidden" name="cmp03_action" value="add">
        <div class="card"><div class="card-header">
            <h5><i class="fa fa-plus"></i> إضافة — دورات التناوب والإجازة الميدانية</h5>
        </div><div class="card-body">
            <div class="form-section"><div class="form-grid">
                <div class="form-group"><label for="emsf_1791_ede6d">رقم الدورة</label>
                    <input type="text" name="f0" required maxlength="190" id="emsf_1791_ede6d"></div>
                <div class="form-group"><label for="emsf_1792_2d248">كود المشغّل</label>
                    <input type="text" name="f1" maxlength="190" id="emsf_1792_2d248"></div>
                <div class="form-group"><label for="emsf_1793_758a3">الموقع</label>
                    <input type="text" name="f2" maxlength="190" id="emsf_1793_758a3"></div>
                <div class="form-group"><label for="emsf_1794_a84e3">المعدة</label>
                    <input type="text" name="f3" maxlength="190" id="emsf_1794_a84e3"></div>
                <div class="form-group"><label for="emsf_1795_ad563">نمط التناوب</label>
                    <input type="text" name="f4" maxlength="190" id="emsf_1795_ad563"></div>
                <div class="form-group"><label for="emsf_1796_05c02">نوع الإجازة</label>
                    <input type="text" name="f5" maxlength="190" id="emsf_1796_05c02"></div>
                <div class="form-group"><label for="emsf_1797_14345">تاريخ الدخول</label>
                    <input type="date" name="f6" id="emsf_1797_14345"></div>
                <div class="form-group"><label for="emsf_1798_7b4f6">تاريخ الخروج</label>
                    <input type="date" name="f7" id="emsf_1798_7b4f6"></div>
                <div class="form-group"><label for="emsf_1799_3cbef">أيام العمل</label>
                    <input type="text" name="f8" maxlength="190" id="emsf_1799_3cbef"></div>
                <div class="form-group"><label for="emsf_1800_5a163">أيام الإجازة</label>
                    <input type="text" name="f9" maxlength="190" id="emsf_1800_5a163"></div>
                <div class="form-group"><label for="emsf_1801_7a232">المناوب المتبادل</label>
                    <input type="text" name="f10" maxlength="190" id="emsf_1801_7a232"></div>
                <div class="form-group"><label for="emsf_1802_14bfc">حالة التبادل</label>
                    <input type="text" name="f11" maxlength="190" id="emsf_1802_14bfc"></div>
                <div class="form-group"><label for="emsf_1803_7bc58">تاريخ تبادل المناوب</label>
                    <input type="date" name="f12" id="emsf_1803_7bc58"></div>
                <div class="form-group"><label for="emsf_1804_927ea">البديل عند تعذّر التبادل</label>
                    <input type="text" name="f13" maxlength="190" id="emsf_1804_927ea"></div>
                <div class="form-group"><label for="emsf_1805_54a26">رحلة الدخول</label>
                    <input type="text" name="f14" maxlength="190" id="emsf_1805_54a26"></div>
                <div class="form-group"><label for="emsf_1806_f2c64">رحلة الخروج</label>
                    <input type="text" name="f15" maxlength="190" id="emsf_1806_f2c64"></div>
                <div class="form-group"><label for="emsf_1807_48611">رصيد الإجازة قبل</label>
                    <input type="text" inputmode="decimal" name="f16" placeholder="0" id="emsf_1807_48611"></div>
                <div class="form-group"><label for="emsf_1808_dac51">رصيد الإجازة بعد</label>
                    <input type="text" inputmode="decimal" name="f17" placeholder="0" id="emsf_1808_dac51"></div>
                <div class="form-group"><label for="emsf_1809_ecf90">جدولها</label>
                    <input type="text" name="f18" maxlength="190" id="emsf_1809_ecf90"></div>
                <div class="form-group"><label for="emsf_1810_f9e6b">الحالة</label>
                    <select name="f19" id="emsf_1810_f9e6b"><option value="مسودة">مسودة</option><option value="قيد المراجعة">قيد المراجعة</option><option value="معتمد">معتمد</option><option value="موقوف">موقوف</option><option value="ملغي">ملغي</option></select></div>
                <div class="form-group"><label for="emsf_1811_85fa1">المعتمِد — الاسم والصفة</label>
                    <input type="text" name="f20" maxlength="190" id="emsf_1811_85fa1"></div>
                <div class="form-group"><label for="emsf_1812_59ba0">تاريخ الاعتماد</label>
                    <input type="date" name="f21" id="emsf_1812_59ba0"></div>
                <div class="form-group"><label for="emsf_1813_e0c2e">مرجع التفويض</label>
                    <input type="text" name="f22" maxlength="190" id="emsf_1813_e0c2e"></div>
                <div class="form-group"><label for="emsf_1814_f653b">المرجع الأب</label>
                    <input type="text" name="f23" maxlength="190" id="emsf_1814_f653b"></div>
                <div class="form-group"><label for="emsf_1815_dced8">المرفق</label>
                    <input type="text" name="f24" maxlength="190" id="emsf_1815_dced8"></div>
                <div class="form-group"><label for="emsf_1816_7ebee">مركز التكلفة</label>
                    <input type="text" inputmode="decimal" name="f25" placeholder="0" id="emsf_1816_7ebee"></div>
                <div class="form-group"><label for="emsf_1817_f6202">سعر الصرف ومصدره</label>
                    <input type="text" inputmode="decimal" name="f26" placeholder="0" id="emsf_1817_f6202"></div>
            </div></div>
            <div style="margin-top:12px;display:flex;gap:10px">
                <button type="submit" class="btn-primary"><i class="fa fa-save"></i> حفظ</button>
                <button type="button" class="btn-secondary" id="cmp03CancelBtn"><i class="fa fa-times"></i> إلغاء</button>
            </div>
        </div></div>
    </form>

    <div class="card"><div class="card-body">
        <div class="table-responsive">
        <table class="alltables display" id="rotationTable">
            <thead><tr>
            <th>رقم الدورة</th>
            <th>كود المشغّل</th>
            <th>الموقع</th>
            <th>المعدة</th>
            <th>نمط التناوب</th>
            <th>نوع الإجازة</th>
            <th>تاريخ الدخول</th>
            <th>تاريخ الخروج</th>
            <th>أيام العمل</th>
            <th>أيام الإجازة</th>
            <th>المناوب المتبادل</th>
            <th>حالة التبادل</th>
            <th>تاريخ تبادل المناوب</th>
            <th>البديل عند تعذّر التبادل</th>
            <th>رحلة الدخول</th>
            <th>رحلة الخروج</th>
            <th>رصيد الإجازة قبل</th>
            <th>رصيد الإجازة بعد</th>
            <th>جدولها</th>
            <th class="ems-gov-th" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
            <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
            <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
            <th class="ems-gov-th none" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
            <th class="ems-gov-th none" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
            <th class="ems-gov-th none" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
            <th class="ems-gov-th none" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
            <th class="ems-gov-th none" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
            <th class="ems-gov-th none" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
            <th class="ems-gov-th none" data-gov="cost_center" data-slice="3" title="وجهة التحميل">مركز التكلفة</th>
            <th class="ems-gov-th none" data-gov="fx_rate_source" data-slice="3" title="ما خالف عملة الدفاتر يحمل السعر ومصدره">سعر الصرف ومصدره</th>
            </tr></thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="30" class="text-center text-muted">لا بياناتَ بعدُ — أضف أول صفٍّ بزر «إضافة»</td></tr>
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
