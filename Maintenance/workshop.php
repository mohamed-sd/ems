<?php
/**
 * Maintenance/workshop.php — الورش والفنيون (CMP-03 ⑥ v2 — بتصميم SCR-DES حرفيًّا + إدخال حي)
 * ───────────────────────────────────────────────────────────────────────────
 * الورقة المالكة: 03 · إدارة الصيانة · الأعمدة 23 بترتيب المستند وطبقة
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

$CANONICAL = 'workshop.php';
$COLS   = array (
  0 => 'رقم التكليف',
  1 => 'أمر العمل',
  2 => 'الفني',
  3 => 'الوظيفة',
  4 => 'التخصص',
  5 => 'الدور في الأمر',
  6 => 'تاريخ البدء',
  7 => 'تاريخ الانتهاء',
  8 => 'الساعات الفعلية',
  9 => 'تكلفة الساعة',
  10 => 'إجمالي التكلفة',
  11 => 'الورشة',
  12 => 'كلفه',
  13 => 'الحالة',
  14 => 'الكيان',
  15 => 'تاريخ الإنشاء',
  16 => 'المعتمد — الاسم والصفة',
  17 => 'تاريخ الاعتماد',
  18 => 'مرجع التفويض',
  19 => 'المرجع الأب',
  20 => 'المرفق',
  21 => 'مركز التكلفة',
  22 => 'سعر الصرف ومصدره',
);
$FIELDS = array (
  0 => 'رقم التكليف',
  1 => 'أمر العمل',
  2 => 'الفني',
  3 => 'الوظيفة',
  4 => 'التخصص',
  5 => 'الدور في الأمر',
  6 => 'تاريخ البدء',
  7 => 'تاريخ الانتهاء',
  8 => 'الساعات الفعلية',
  9 => 'تكلفة الساعة',
  10 => 'إجمالي التكلفة',
  11 => 'الورشة',
  12 => 'كلفه',
  13 => 'الحالة',
  14 => 'المعتمد — الاسم والصفة',
  15 => 'تاريخ الاعتماد',
  16 => 'مرجع التفويض',
  17 => 'المرجع الأب',
  18 => 'المرفق',
  19 => 'مركز التكلفة',
  20 => 'سعر الصرف ومصدره',
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

$page_title = 'إيكوبيشن | الورش والفنيون';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/entity_tabs.php'; echo ems_entity_tabs('mnt_order', '');
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell" dir="rtl">
    <!-- سجلُّ حقولِ الورقةِ بحبّتِه — يُضاف بجانبِ ما بُني لا بدلًا منه،
         فالمبنيُّ له أفعالُه والورقةُ تطلب السجلَّ بحقولِه كلِّها -->
    <div class="card"><div class="card-header"><h5><i class="fa fa-clipboard-list"></i> سجل حقول الورقة</h5></div>
    <div class="card-body"><div class="table-container">
        <table id="emsList_mnt_workshop"></table>
    </div></div></div>

    <!-- سجلُّ حقولِ الورقةِ بحبّتِه — يُضاف بجانبِ ما بُني لا بدلًا منه،
         فالمبنيُّ له أفعالُه والورقةُ تطلب السجلَّ بحقولِه كلِّها -->
    <div class="card"><div class="card-header"><h5><i class="fa fa-clipboard-list"></i> سجل حقول الورقة</h5></div>
    <div class="card-body"><div class="table-container">
        <?php /* GUIDE_COLS:govui_field_close:emsList_mnt_workshop
             الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
             والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
             ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
        $GUIDE_COLS = array(
            'كود القدرة' => 'g43',
            'النوع' => 'g44',
            'الاسم' => 'g45',
            'الموقع' => 'g46',
            'التخصصات' => 'g47',
            'مستوى الفني' => 'g48',
            'الشهادات وصلاحيتها' => 'g49',
            'الطاقة اليومية (ساعات/أوامر)' => 'g50',
            'متاح الآن؟' => 'g51',
            'التبعية' => 'g52',
            'مرجع العقد عند الخارجي' => 'g53',
            'حالة القدرة' => 'g54',
            'المنشئ' => 'g55',
            'تاريخ الإنشاء' => 'g56',
            'حالة البيانات' => 'g57',
            'مرجع المصدر' => 'g58',
        );
        $D = array();
        $__gridRows = ems_w14_guide_rows('mnt_workshop');
        echo ems_w14_grid('emsList_mnt_workshop', $GUIDE_COLS, $__gridRows, $D, 'لا سطر مسجل بعد في الورش والفنيون'); /* /GUIDE_COLS */ ?>
    </div></div></div>

    <?php
    $header_title = 'الورش والفنيون';
    $header_icon = 'fa fa-toolbox';
    $header_actions = array(
        array('tag' => 'button', 'id' => 'cmp03AddBtn', 'class' => '', 'icon' => 'fa fa-plus',
              'label' => 'إضافة', 'title' => 'إضافة صف جديد', 'attrs' => 'type="button"'),
    );
    $header_back = false;
    include '../includes/page_header.php';
    if (isset($_GET['msg'])) {
        echo '<div class="alert alert-info">' . htmlspecialchars((string) $_GET['msg'], ENT_QUOTES, 'UTF-8') . '</div>';
    }
    // UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
    echo ems_states_bundle('لا تكليفات ورشة مسجلة بعد', 'أضف أول تكليف فني بزر «إضافة» في رأس الشاشة');
    ?>

    <!-- فورم الإضافة الموحد (ems-forms) — مطويٌّ حتى زرِّ الرأس -->
    <form method="post" action="" class="allforms" id="cmp03AddForm">
        <?= csrf_field() ?>
        <input type="hidden" name="cmp03_action" value="add">
        <div class="card"><div class="card-header">
            <h5><i class="fa fa-plus"></i> إضافة — الورش والفنيون</h5>
        </div><div class="card-body">
            <div class="form-section"><div class="form-grid">
                <div class="form-group"><label for="emsf_755_0c662">رقم التكليف</label>
                    <input type="text" name="f0" required maxlength="190" id="emsf_755_0c662"></div>
                <div class="form-group"><label for="emsf_756_3b0de">أمر العمل</label>
                    <input type="text" name="f1" maxlength="190" id="emsf_756_3b0de"></div>
                <div class="form-group"><label for="emsf_757_ceadf">الفني</label>
                    <input type="text" name="f2" maxlength="190" id="emsf_757_ceadf"></div>
                <div class="form-group"><label for="emsf_758_0e053">الوظيفة</label>
                    <input type="text" name="f3" maxlength="190" id="emsf_758_0e053"></div>
                <div class="form-group"><label for="emsf_759_d8197">التخصص</label>
                    <input type="text" name="f4" maxlength="190" id="emsf_759_d8197"></div>
                <div class="form-group"><label for="emsf_760_b9345">الدور في الأمر</label>
                    <input type="text" name="f5" maxlength="190" id="emsf_760_b9345"></div>
                <div class="form-group"><label for="emsf_761_9b7dd">تاريخ البدء</label>
                    <input type="date" name="f6" id="emsf_761_9b7dd"></div>
                <div class="form-group"><label for="emsf_762_53655">تاريخ الانتهاء</label>
                    <input type="date" name="f7" id="emsf_762_53655"></div>
                <div class="form-group"><label for="emsf_763_16c08">الساعات الفعلية</label>
                    <input type="text" inputmode="decimal" name="f8" placeholder="0" id="emsf_763_16c08"></div>
                <div class="form-group"><label for="emsf_764_ca1e0">تكلفة الساعة</label>
                    <input type="text" inputmode="decimal" name="f9" placeholder="0" id="emsf_764_ca1e0"></div>
                <div class="form-group"><label for="emsf_765_39755">إجمالي التكلفة</label>
                    <input type="text" inputmode="decimal" name="f10" placeholder="0" id="emsf_765_39755"></div>
                <div class="form-group"><label for="emsf_766_fd855">الورشة</label>
                    <input type="text" name="f11" maxlength="190" id="emsf_766_fd855"></div>
                <div class="form-group"><label for="emsf_767_557f1">كلفه</label>
                    <input type="text" name="f12" maxlength="190" id="emsf_767_557f1"></div>
                <div class="form-group"><label for="emsf_768_a8c31">الحالة</label>
                    <select name="f13" id="emsf_768_a8c31"><option value="مسودة">مسودة</option><option value="قيد المراجعة">قيد المراجعة</option><option value="معتمد">معتمد</option><option value="موقوف">موقوف</option><option value="ملغي">ملغي</option></select></div>
                <div class="form-group"><label for="emsf_769_72186">المعتمد — الاسم والصفة</label>
                    <input type="text" name="f14" maxlength="190" id="emsf_769_72186"></div>
                <div class="form-group"><label for="emsf_770_ed4fb">تاريخ الاعتماد</label>
                    <input type="date" name="f15" id="emsf_770_ed4fb"></div>
                <div class="form-group"><label for="emsf_771_8da60">مرجع التفويض</label>
                    <input type="text" name="f16" maxlength="190" id="emsf_771_8da60"></div>
                <div class="form-group"><label for="emsf_772_dac92">المرجع الأب</label>
                    <input type="text" name="f17" maxlength="190" id="emsf_772_dac92"></div>
                <div class="form-group"><label for="emsf_773_dbb5c">المرفق</label>
                    <input type="text" name="f18" maxlength="190" id="emsf_773_dbb5c"></div>
                <div class="form-group"><label for="emsf_774_b2523">مركز التكلفة</label>
                    <input type="text" inputmode="decimal" name="f19" placeholder="0" id="emsf_774_b2523"></div>
                <div class="form-group"><label for="emsf_775_74725">سعر الصرف ومصدره</label>
                    <input type="text" inputmode="decimal" name="f20" placeholder="0" id="emsf_775_74725"></div>
            </div></div>
            <div class="cmp03-form-actions">
                <button type="submit" class="btn-primary"><i class="fa fa-save"></i> حفظ</button>
                <button type="button" class="btn-secondary" id="cmp03CancelBtn"><i class="fa fa-times"></i> إلغاء</button>
            </div>
        </div></div>
    </form>

    <div class="card"><div class="card-body">
        <div class="table-responsive">
        <table class="alltables display" id="workshopTable">
            <thead><tr>
            <th>رقم التكليف</th>
            <th>أمر العمل</th>
            <th>الفني</th>
            <th>الوظيفة</th>
            <th>التخصص</th>
            <th>الدور في الأمر</th>
            <th>تاريخ البدء</th>
            <th>تاريخ الانتهاء</th>
            <th>الساعات الفعلية</th>
            <th>تكلفة الساعة</th>
            <th>إجمالي التكلفة</th>
            <th>الورشة</th>
            <th>كلفه</th>
            <th class="ems-gov-th" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
            <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صف بلا كيان مالك">الكيان</th>
            <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
            <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمد — الاسم والصفة</th>
            <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
            <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمد — تفويض أو سلطة أصلية">مرجع التفويض</th>
            <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
            <th class="ems-gov-th" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
            <th class="ems-gov-th" data-gov="cost_center" data-slice="3" title="وجهة التحميل">مركز التكلفة</th>
            <th class="ems-gov-th none" data-gov="fx_rate_source" data-slice="3" title="ما خالف عملة الدفاتر يحمل السعر ومصدره">سعر الصرف ومصدره</th>
            </tr></thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="23" class="text-center text-muted">لا بيانات بعد — أضف أول صف بزر «إضافة»</td></tr>
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

<?php /* نُقلت أنماطُ هذه الشاشةِ إلى assets/css/ems-screens.css (UXUI-01 البند ٦: صفرُ نمطٍ محليّ) */ ?>
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
