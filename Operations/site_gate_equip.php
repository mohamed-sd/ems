<?php
/**
 * Operations/site_gate_equip.php — أذون دخول وخروج المعدات (CMP-03 ⑥ v2 — بتصميم SCR-DES حرفيًّا + إدخال حي)
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
require_once __DIR__ . '/../includes/w14_grid.php';
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

$CANONICAL = 'site_gate_equip.php';
$COLS   = array (
  0 => 'الكيان',
  1 => 'رقم الإذن',
  2 => 'نوع الإذن',
  3 => 'الموقع',
  4 => 'كود المعدة',
  5 => 'نوع المعدة',
  6 => 'مصدر المعدة',
  7 => 'الجهة المرافقة',
  8 => 'سبب الحركة',
  9 => 'المستند المرجعي',
  10 => 'تاريخ الحركة المخطط',
  11 => 'تاريخ الحركة الفعلي',
  12 => 'قراءة العداد عند الحركة',
  13 => 'رحلة الترحيل',
  14 => 'حالة الجاهزية',
  15 => 'حالة الوثائق',
  16 => 'المنشئ — الاسم والصفة',
  17 => 'اعتماد مدير الموقع',
  18 => 'اعتماد مدير التشغيل',
  19 => 'تاريخ الاعتماد',
  20 => 'مرجع التفويض',
  21 => 'الحالة',
);
$FIELDS = array (
  0 => 'رقم الإذن',
  1 => 'نوع الإذن',
  2 => 'الموقع',
  3 => 'كود المعدة',
  4 => 'نوع المعدة',
  5 => 'مصدر المعدة',
  6 => 'الجهة المرافقة',
  7 => 'سبب الحركة',
  8 => 'المستند المرجعي',
  9 => 'تاريخ الحركة المخطط',
  10 => 'تاريخ الحركة الفعلي',
  11 => 'قراءة العداد عند الحركة',
  12 => 'رحلة الترحيل',
  13 => 'حالة الجاهزية',
  14 => 'حالة الوثائق',
  15 => 'اعتماد مدير الموقع',
  16 => 'اعتماد مدير التشغيل',
  17 => 'تاريخ الاعتماد',
  18 => 'مرجع التفويض',
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

$page_title = 'إيكوبيشن | أذون دخول وخروج المعدات';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell" dir="rtl">
    <!-- سجلُّ حقولِ الورقةِ بحبّتِه — يُضاف بجانبِ ما بُني لا بدلًا منه،
         فالمبنيُّ له أفعالُه والورقةُ تطلب السجلَّ بحقولِه كلِّها -->
    <div class="card"><div class="card-header"><h5><i class="fa fa-clipboard-list"></i> سجل حقول الورقة</h5></div>
    <div class="card-body"><div class="table-container">
        <?php /* GUIDE_COLS:govui_field_close:emsList_site_gate_equip
             الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
             والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
             ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
        $GUIDE_COLS = array(
            'رقم الإذن' => 'g1',
            'نوع الكيان' => 'g2',
            'مرجع الكيان' => 'g3',
            'اتجاه الحركة' => 'g4',
            'وقت الحركة' => 'g5',
            'كود الموقع' => 'g6',
            'مرجع التخصيص الساري' => 'g7',
            'مطابقة التخصيص' => 'g8',
            'مرافق/سائق' => 'g9',
            'الغرض' => 'g10',
            'مصدر الإذن' => 'g11',
            'واقعة بلا إذن؟' => 'g12',
            'حالة الإذن' => 'g13',
            'المنشئ' => 'g14',
            'تاريخ الإنشاء' => 'g15',
            'حالة البيانات' => 'g16',
            'مرجع المصدر' => 'g17',
        );
        $D = array();
        $__gridRows = ems_w14_guide_rows('site_gate_equip');
        echo ems_w14_grid('emsList_site_gate_equip', $GUIDE_COLS, $__gridRows, $D, 'لا سطر مسجل بعد في أذون دخول وخروج المعدات والمشغلين'); /* /GUIDE_COLS */ ?>
    </div></div></div>

    <?php
    $header_title = 'أذون دخول وخروج المعدات';
    $header_icon = 'fa fa-truck-moving';
    $header_actions = array(
        array('tag' => 'button', 'id' => 'cmp03AddBtn', 'class' => '', 'icon' => 'fa fa-plus',
              'label' => 'إضافة', 'title' => 'إضافة صف جديد', 'attrs' => 'type="button"'),
    );
    $header_back = false;
    include '../includes/page_header.php';
    if (isset($_GET['msg'])) {
        echo '<div class="alert alert-info">' . htmlspecialchars((string) $_GET['msg'], ENT_QUOTES, 'UTF-8') . '</div>';
    }
    echo ems_states_bundle('لا أذون دخول أو خروج لمعدات الموقع مسجلة بعد', 'أضف أول صف بزر «إضافة» في رأس الشاشة');
    ?>
    <?php /* نُقلت أنماطُ هذه الشاشةِ إلى assets/css/ems-screens.css (UXUI-01 البند ٦: صفرُ نمطٍ محليّ) */ ?>

    <!-- فورم الإضافة الموحد (ems-forms) — مطويٌّ حتى زرِّ الرأس -->
    <form method="post" action="" class="allforms" id="cmp03AddForm">
        <?= csrf_field() ?>
        <input type="hidden" name="cmp03_action" value="add">
        <div class="card"><div class="card-header">
            <h5><i class="fa fa-plus"></i> إضافة — أذون دخول وخروج المعدات</h5>
        </div><div class="card-body">
            <div class="form-section"><div class="form-grid">
                <div class="form-group"><label for="emsf_918_79776">رقم الإذن</label>
                    <input type="text" name="f0" required maxlength="190" id="emsf_918_79776"></div>
                <div class="form-group"><label for="emsf_919_f6962">نوع الإذن</label>
                    <input type="text" name="f1" maxlength="190" id="emsf_919_f6962"></div>
                <div class="form-group"><label for="emsf_920_b4541">الموقع</label>
                    <input type="text" name="f2" maxlength="190" id="emsf_920_b4541"></div>
                <div class="form-group"><label for="emsf_921_2ecbd">كود المعدة</label>
                    <input type="text" name="f3" maxlength="190" id="emsf_921_2ecbd"></div>
                <div class="form-group"><label for="emsf_922_e7c5e">نوع المعدة</label>
                    <input type="text" name="f4" maxlength="190" id="emsf_922_e7c5e"></div>
                <div class="form-group"><label for="emsf_923_0c1ad">مصدر المعدة</label>
                    <input type="text" name="f5" maxlength="190" id="emsf_923_0c1ad"></div>
                <div class="form-group"><label for="emsf_924_8fbd9">الجهة المرافقة</label>
                    <input type="text" name="f6" maxlength="190" id="emsf_924_8fbd9"></div>
                <div class="form-group"><label for="emsf_925_db269">سبب الحركة</label>
                    <input type="text" name="f7" maxlength="190" id="emsf_925_db269"></div>
                <div class="form-group"><label for="emsf_926_4533a">المستند المرجعي</label>
                    <input type="text" name="f8" maxlength="190" id="emsf_926_4533a"></div>
                <div class="form-group"><label for="emsf_927_02305">تاريخ الحركة المخطط</label>
                    <input type="date" name="f9" id="emsf_927_02305"></div>
                <div class="form-group"><label for="emsf_928_43f14">تاريخ الحركة الفعلي</label>
                    <input type="date" name="f10" id="emsf_928_43f14"></div>
                <div class="form-group"><label for="emsf_929_c4e1e">قراءة العداد عند الحركة</label>
                    <input type="text" name="f11" maxlength="190" id="emsf_929_c4e1e"></div>
                <div class="form-group"><label for="emsf_930_81e59">رحلة الترحيل</label>
                    <input type="text" name="f12" maxlength="190" id="emsf_930_81e59"></div>
                <div class="form-group"><label for="emsf_931_db271">حالة الجاهزية</label>
                    <input type="text" name="f13" maxlength="190" id="emsf_931_db271"></div>
                <div class="form-group"><label for="emsf_932_144db">حالة الوثائق</label>
                    <input type="text" name="f14" maxlength="190" id="emsf_932_144db"></div>
                <div class="form-group"><label for="emsf_933_206f3">اعتماد مدير الموقع</label>
                    <input type="text" name="f15" maxlength="190" id="emsf_933_206f3"></div>
                <div class="form-group"><label for="emsf_934_1a927">اعتماد مدير التشغيل</label>
                    <input type="text" name="f16" maxlength="190" id="emsf_934_1a927"></div>
                <div class="form-group"><label for="emsf_935_34aed">تاريخ الاعتماد</label>
                    <input type="date" name="f17" id="emsf_935_34aed"></div>
                <div class="form-group"><label for="emsf_936_273e1">مرجع التفويض</label>
                    <input type="text" name="f18" maxlength="190" id="emsf_936_273e1"></div>
                <div class="form-group"><label for="emsf_937_e155a">الحالة</label>
                    <select name="f19" id="emsf_937_e155a"><option value="مسودة">مسودة</option><option value="قيد المراجعة">قيد المراجعة</option><option value="معتمد">معتمد</option><option value="موقوف">موقوف</option><option value="ملغي">ملغي</option></select></div>
            </div></div>
            <div class="cmp03-form-actions">
                <button type="submit" class="btn-primary"><i class="fa fa-save"></i> حفظ</button>
                <button type="button" class="btn-secondary" id="cmp03CancelBtn"><i class="fa fa-times"></i> إلغاء</button>
            </div>
        </div></div>
    </form>

    <div class="card"><div class="card-body">
        <div class="table-responsive">
        <table class="alltables display" id="site_gate_equipTable">
            <thead><tr>
            <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صف بلا كيان مالك">الكيان</th>
            <th>رقم الإذن</th>
            <th>نوع الإذن</th>
            <th>الموقع</th>
            <th>كود المعدة</th>
            <th>نوع المعدة</th>
            <th>مصدر المعدة</th>
            <th>الجهة المرافقة</th>
            <th>سبب الحركة</th>
            <th>المستند المرجعي</th>
            <th>تاريخ الحركة المخطط</th>
            <th>تاريخ الحركة الفعلي</th>
            <th>قراءة العداد عند الحركة</th>
            <th>رحلة الترحيل</th>
            <th>حالة الجاهزية</th>
            <th>حالة الوثائق</th>
            <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المنشئ — الاسم والصفة</th>
            <th>اعتماد مدير الموقع</th>
            <th>اعتماد مدير التشغيل</th>
            <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
            <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمد — تفويض أو سلطة أصلية">مرجع التفويض</th>
            <th class="ems-gov-th" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
            </tr></thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="22" class="text-center text-muted">لا بيانات بعد — أضف أول صف بزر «إضافة»</td></tr>
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
