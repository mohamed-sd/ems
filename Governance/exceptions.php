<?php
// شواهد المتطلبات (AC-E06-03 · موجة ٣): SCN-700 · SCN-701 · SCN-702 · SCN-715 · SCN-830
/**
 * Governance/exceptions.php — طلبات الاستثناء (CMP-03 ⑥ v2 — بتصميم SCR-DES حرفيًّا + إدخال حي)
 * ───────────────────────────────────────────────────────────────────────────
 * الورقة المالكة: 15 · الحوكمة والالتزام · الأعمدة 24 بترتيب المستند وطبقة
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
require_once __DIR__ . '/../includes/ux_components.php'; // UXW-01: حالات الشاشة الموحدة

$company_id     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$is_super_admin = (strval($_SESSION['user']['role'] ?? '') === '-1');
$uid            = intval($_SESSION['user']['id'] ?? 0);
if (!$is_super_admin && $company_id <= 0) {
    ems_gov_flash_redirect('../main/dashboard.php', 'غير مصرح', 'GOV-PERM-403', '');
    exit();
}

require_once __DIR__ . '/../includes/cmp03_local_store.php'; // الموجة ٢ — الجدول الأصلي

$CANONICAL = 'exceptions.php';

// حارس الشاشة (M-14 BR-GOV-01): can_view من modules — والسوبر يمر
$__pp = check_page_permissions($conn, 'Governance/exceptions.php');
if (!$is_super_admin && empty($__pp['can_view'])) {
    require_once __DIR__ . '/../includes/perm_explain_live.php';
    $__why = ems_deny_message($conn, intval($_SESSION['user']['role'] ?? 0), 'Governance/exceptions.php');
    ems_gov_flash_redirect('../main/dashboard.php', $__why, 'GOV-INFO-200', '');
    exit();
}
if (!$is_super_admin && $_SERVER['REQUEST_METHOD'] === 'POST' && empty($__pp['can_add']) && empty($__pp['can_edit'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'غير مصرح بالكتابة في هذه الشاشة ❌', 'GOV-PERM-403', 'اطلب المنحةَ من مدير الصلاحيات إن كانت ضمن عملك');
}
$COLS   = array (
  0 => 'رقم الطلب',
  1 => 'تاريخ الطلب',
  2 => 'الحماية المستثناة',
  3 => 'الإدارة الطالبة',
  4 => 'سبب الاستثناء',
  5 => 'المستندات المؤيدة',
  6 => 'درجة الخطورة',
  7 => 'الأثر المتوقع',
  8 => 'النطاق',
  9 => 'المدة من',
  10 => 'المدة إلى',
  11 => 'الموافقات المطلوبة',
  12 => 'الموافقون',
  13 => 'تاريخ الاعتماد',
  14 => 'عدد مرات الاستعمال',
  15 => 'تاريخ الانتهاء',
  16 => 'قرار الإقفال',
  17 => 'الحالة',
  18 => 'الكيان',
  19 => 'المُنشئ — الاسم والصفة',
  20 => 'تاريخ الإنشاء',
  21 => 'مرجع التفويض',
  22 => 'المرجع الأب',
  23 => 'المرفق',
);
$FIELDS = array (
  0 => 'رقم الطلب',
  1 => 'تاريخ الطلب',
  2 => 'الحماية المستثناة',
  3 => 'الإدارة الطالبة',
  4 => 'سبب الاستثناء',
  5 => 'المستندات المؤيدة',
  6 => 'درجة الخطورة',
  7 => 'الأثر المتوقع',
  8 => 'النطاق',
  9 => 'المدة من',
  10 => 'المدة إلى',
  11 => 'الموافقات المطلوبة',
  12 => 'الموافقون',
  13 => 'تاريخ الاعتماد',
  14 => 'عدد مرات الاستعمال',
  15 => 'تاريخ الانتهاء',
  16 => 'قرار الإقفال',
  17 => 'الحالة',
  18 => 'مرجع التفويض',
  19 => 'المرجع الأب',
  20 => 'المرفق',
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

$page_title = 'إيكوبيشن | طلبات الاستثناء';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($__pp) ? $__pp : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell ems-doc-cycle" dir="rtl">
    <?php
    $header_title = 'طلبات الاستثناء';
    $header_icon = 'fa fa-hand';
    $header_actions = array(
        array('tag' => 'button', 'id' => 'cmp03AddBtn', 'class' => '', 'icon' => 'fa fa-plus',
              'label' => 'إضافة', 'title' => 'إضافة صف جديد', 'attrs' => 'type="button"'),
    );
    $header_back = false;
    include '../includes/page_header.php';
    echo ems_next_step('استيفاءُ الموافقاتِ المطلوبةِ ثم إقفالُ الاستثناءِ عند انتهاءِ مدتِه');
    if (isset($_GET['msg'])) {
        echo '<div class="alert alert-info">' . htmlspecialchars((string) $_GET['msg'], ENT_QUOTES, 'UTF-8') . '</div>';
    }
    echo ems_states_bundle('لا طلباتِ استثناءٍ قائمة', 'طلبُ الاستثناءِ يبدأ بزرِّ «إضافة» ويمرُّ بموافقاتِه المطلوبة');
    ?>

    <!-- فورم الإضافة الموحد (ems-forms) — مطويٌّ حتى زرِّ الرأس -->
    <form method="post" action="" class="allforms" id="cmp03AddForm">
        <?= csrf_field() ?>
        <input type="hidden" name="cmp03_action" value="add">
        <div class="card"><div class="card-header">
            <h5><i class="fa fa-plus"></i> إضافة — طلبات الاستثناء</h5>
        </div><div class="card-body">
            <div class="form-section"><div class="form-grid">
                <div class="form-group"><label for="emsf_576_258e1">رقم الطلب</label>
                    <input type="text" name="f0" required maxlength="190" id="emsf_576_258e1"></div>
                <div class="form-group"><label for="emsf_577_0c11e">تاريخ الطلب</label>
                    <input type="date" name="f1" id="emsf_577_0c11e"></div>
                <div class="form-group"><label for="emsf_578_56072">الحماية المستثناة</label>
                    <input type="text" name="f2" maxlength="190" id="emsf_578_56072"></div>
                <div class="form-group"><label for="emsf_579_0f203">الإدارة الطالبة</label>
                    <input type="text" name="f3" maxlength="190" id="emsf_579_0f203"></div>
                <div class="form-group"><label for="emsf_580_9c17a">سبب الاستثناء</label>
                    <input type="text" name="f4" maxlength="190" id="emsf_580_9c17a"></div>
                <div class="form-group"><label for="emsf_581_c9838">المستندات المؤيدة</label>
                    <input type="text" name="f5" maxlength="190" id="emsf_581_c9838"></div>
                <div class="form-group"><label for="emsf_582_5f705">درجة الخطورة</label>
                    <input type="text" name="f6" maxlength="190" id="emsf_582_5f705"></div>
                <div class="form-group"><label for="emsf_583_7fb5c">الأثر المتوقع</label>
                    <input type="text" name="f7" maxlength="190" id="emsf_583_7fb5c"></div>
                <div class="form-group"><label for="emsf_584_67fc9">النطاق</label>
                    <input type="text" name="f8" maxlength="190" id="emsf_584_67fc9"></div>
                <div class="form-group"><label for="emsf_585_65136">المدة من</label>
                    <input type="text" inputmode="decimal" name="f9" placeholder="0" id="emsf_585_65136"></div>
                <div class="form-group"><label for="emsf_586_e4e1f">المدة إلى</label>
                    <input type="text" inputmode="decimal" name="f10" placeholder="0" id="emsf_586_e4e1f"></div>
                <div class="form-group"><label for="emsf_587_2619e">الموافقات المطلوبة</label>
                    <input type="text" name="f11" maxlength="190" id="emsf_587_2619e"></div>
                <div class="form-group"><label for="emsf_588_6b562">الموافقون</label>
                    <input type="text" name="f12" maxlength="190" id="emsf_588_6b562"></div>
                <div class="form-group"><label for="emsf_589_d7a3d">تاريخ الاعتماد</label>
                    <input type="date" name="f13" id="emsf_589_d7a3d"></div>
                <div class="form-group"><label for="emsf_590_588da">عدد مرات الاستعمال</label>
                    <input type="text" inputmode="decimal" name="f14" placeholder="0" id="emsf_590_588da"></div>
                <div class="form-group"><label for="emsf_591_4114f">تاريخ الانتهاء</label>
                    <input type="date" name="f15" id="emsf_591_4114f"></div>
                <div class="form-group"><label for="emsf_592_896ef">قرار الإقفال</label>
                    <input type="text" name="f16" maxlength="190" id="emsf_592_896ef"></div>
                <div class="form-group"><label for="emsf_593_11f24">الحالة</label>
                    <select name="f17" id="emsf_593_11f24"><option value="مسودة">مسودة</option><option value="قيد المراجعة">قيد المراجعة</option><option value="معتمد">معتمد</option><option value="موقوف">موقوف</option><option value="ملغي">ملغي</option></select></div>
                <div class="form-group"><label for="emsf_594_42689">مرجع التفويض</label>
                    <input type="text" name="f18" maxlength="190" id="emsf_594_42689"></div>
                <div class="form-group"><label for="emsf_595_a0a86">المرجع الأب</label>
                    <input type="text" name="f19" maxlength="190" id="emsf_595_a0a86"></div>
                <div class="form-group"><label for="emsf_596_c60ca">المرفق</label>
                    <input type="text" name="f20" maxlength="190" id="emsf_596_c60ca"></div>
            </div></div>
            <div class="form-actions">
                <button type="submit" class="btn-primary"><i class="fa fa-save"></i> حفظ</button>
                <button type="button" class="btn-secondary" id="cmp03CancelBtn"><i class="fa fa-times"></i> إلغاء</button>
            </div>
        </div></div>
    </form>

    <div class="card"><div class="card-body">
        <div class="table-responsive">
        <table class="alltables display" id="exceptionsTable">
            <thead><tr>
            <th>رقم الطلب</th>
            <th>تاريخ الطلب</th>
            <th>الحماية المستثناة</th>
            <th>الإدارة الطالبة</th>
            <th>سبب الاستثناء</th>
            <th>المستندات المؤيدة</th>
            <th>درجة الخطورة</th>
            <th>الأثر المتوقع</th>
            <th>النطاق</th>
            <th>المدة من</th>
            <th>المدة إلى</th>
            <th>الموافقات المطلوبة</th>
            <th>الموافقون</th>
            <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
            <th>عدد مرات الاستعمال</th>
            <th>تاريخ الانتهاء</th>
            <th>قرار الإقفال</th>
            <th class="ems-gov-th" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
            <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
            <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
            <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
            <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
            <th class="ems-gov-th none" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
            <th class="ems-gov-th none" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
            </tr></thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="24" class="text-center text-muted">لا بياناتَ بعدُ — أضف أول صفٍّ بزر «إضافة»</td></tr>
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
