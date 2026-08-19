<?php
require_once __DIR__ . '/../includes/permissions_helper.php';
/**
 * Equipments/meter_readings.php — قراءاتُ العدّادات (M-25 · UX-10 §8)
 * ───────────────────────────────────────────────────────────────────────────
 * «ما يملكه الأسطولُ إدخالًا: سجلُّ المعدة ووثائقُها **وعدّاداتُها** وقراراتُ
 * حياتها» (UX-10 §قاعدة القراءة).
 *
 * ثلاثةُ أقسام: **العدّادُ الحالي بمصدره مسمًّى** · تسجيلُ قراءةٍ · **تصفيرٌ
 * بقرارٍ موثَّق** — ولوحُ «عدّاداتٌ لم تُحدَّث» بعدّادها (§7).
 * كلُّ فعلٍ عبر `MeterReadingService` — لا كتابةَ خامًا في الشاشة.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}
include '../config.php';
require_once __DIR__ . '/../app/Services/Fleet/MeterReadingService.php';

use App\Services\Fleet\MeterReadingService as MRS;

$current_role   = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$company_id     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$uid            = isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0;

if (!$is_super_admin && $company_id <= 0) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد بيئة شركة صالحة للمستخدم ❌', 'GOV-SCOPE-403', '');
    exit();
}

$MODULE_CODE = 'Equipments/meter_readings.php';
$can_view = $can_add = $can_edit = false;
if ($is_super_admin) {
    $can_view = $can_add = $can_edit = true;
} else {
    $st = $conn->prepare("SELECT rp.can_view, rp.can_add, rp.can_edit
                            FROM role_permissions rp
                            JOIN modules m ON m.id = rp.module_id
                           WHERE m.code = ? AND rp.role_id = ? LIMIT 1");
    $rid = intval($current_role);
    $st->bind_param('si', $MODULE_CODE, $rid);
    $st->execute();
    if ($row = $st->get_result()->fetch_assoc()) {
        $can_view = (intval($row['can_view']) === 1);
        $can_add  = (intval($row['can_add'])  === 1);
        $can_edit = (intval($row['can_edit']) === 1);
    }
    $st->close();
}
if (!$can_view) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض قراءات العدّادات ❌', 'GOV-PERM-403', '');
    exit();
}

$gate = $is_super_admin ? ems_tenant_db()->forAllTenants('meter readings super') : ems_tenant_db();

$TYPE_LABELS   = array('hour' => 'ساعات', 'km' => 'كيلومتر');
$SOURCE_LABELS = array('manual' => 'يدوي', 'inspection' => 'فحص', 'timesheet' => 'سجل الدوام', 'reset' => 'تصفير');

$selected = intval($_GET['equipment_id'] ?? 0);
$mtype    = isset($_GET['meter_type']) && isset($TYPE_LABELS[$_GET['meter_type']]) ? $_GET['meter_type'] : 'hour';
$redirect = function ($msg, $eid, $mt) { ems_gov_redirect("Location: meter_readings.php?equipment_id=" . intval($eid)
    . "&meter_type=" . rawurlencode($mt) . "&msg=" . rawurlencode($msg)); exit(); };

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = strval($_POST['mr_action'] ?? '');
    $eid = intval($_POST['equipment_id'] ?? 0);
    $mt  = isset($TYPE_LABELS[$_POST['meter_type'] ?? '']) ? strval($_POST['meter_type']) : 'hour';

    if ($action === 'record') {
        if (!$can_add) { $redirect('لا توجد صلاحية لهذا الإجراء ❌', $eid, $mt); }
        $r = MRS::record($conn, $gate, $company_id, $eid, array(
            'meter_type'   => $mt,
            'reading_date' => $_POST['reading_date'] ?? '',
            'value'        => $_POST['value'] ?? '',
            'source'       => $_POST['source'] ?? 'manual',
            'note'         => $_POST['note'] ?? '',
        ), $uid);
        $redirect($r['ok'] ? ('سُجّلت القراءة ✅' . ($r['delta'] !== null ? (' — الفارق ' . $r['delta']) : ''))
                           : ($r['code'] . ' — ' . $r['reason'] . ' ❌'), $eid, $mt);
    }

    if ($action === 'reset') {
        // التصفيرُ قرارٌ — صلاحيةُ التعديل لا الإضافة (الصيانةُ تسجّل ولا تصفّر)
        if (!$can_edit) { $redirect('التصفيرُ قرارٌ يخصُّ مالكَ السجل ❌', $eid, $mt); }
        $r = MRS::reset($conn, $gate, $company_id, $eid, array(
            'meter_type'    => $mt,
            'reading_date'  => $_POST['reading_date'] ?? '',
            'value'         => $_POST['value'] ?? 0,
            'reset_reason'  => $_POST['reset_reason'] ?? '',
            'reset_doc_ref' => $_POST['reset_doc_ref'] ?? '',
        ), $uid);
        $redirect($r['ok'] ? ('صُفّر العدّاد — فُتحت السلسلة ' . intval($r['chain_no']) . ' ✅')
                           : ($r['code'] . ' — ' . $r['reason'] . ' ❌'), $eid, $mt);
    }
}

// ── القراءة ────────────────────────────────────────────────────────────────
$equipments = array();
try {
    $equipments = $gate->scopedQuery(array('scope' => array('e' => 'equipments')),
        "SELECT e.id, e.name, e.meter_uom,
                (SELECT COUNT(*) FROM meter_readings r WHERE r.equipment_id = e.id) AS readings_count
           FROM equipments e
          WHERE {TENANT_SCOPE} AND COALESCE(e.status,1) = 1
          ORDER BY e.id");
} catch (\Throwable $t) { $equipments = array(); }
if ($selected <= 0 && $equipments) { $selected = intval($equipments[0]['id']); }

$meter   = $selected > 0 ? MRS::currentMeter($conn, $gate, $company_id, $selected, $mtype)
                         : array('value' => 0, 'source' => 'none', 'as_of' => null, 'is_reading' => false, 'note' => '');
$chain   = $selected > 0 ? MRS::chainOf($gate, $selected, $mtype) : array();
$stale   = MRS::staleMeters($gate, 14);

$page_title = 'إيكوبيشن | قراءات العدّادات';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<?php /* نُقلت أنماطُ هذه الشاشةِ إلى assets/css/ems-screens.css (UXUI-01 البند ٦: صفرُ نمطٍ محليّ) */ ?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'قراءات العدّادات'; $header_icon = 'fa fa-gauge-high';
    $header_actions = array();
    $header_back = array('href' => 'equipments.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'المعدات');
    include('../includes/page_header.php');
    // UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
    if (function_exists('ems_states_bundle')) {
        echo ems_states_bundle('لا قراءةَ عدّادٍ مسجَّلةً لهذه المعدةِ بعدُ',
                               'سجِّل أولَ قراءةٍ من بطاقةِ «تسجيلُ قراءة» أعلاه، أو بدِّل المعدةَ من قائمةِ الاختيار');
    }
    if (isset($_GET['msg'])) {
        echo '<div class="alert alert-info">' . htmlspecialchars($_GET['msg']) . '</div>';
    }
    ?>

    <div class="card"><div class="card-body">
        <form method="get" class="mrd-filter">
            <strong>المعدة:</strong>
            <select name="equipment_id" aria-label="اختيارُ المعدةِ المعروضةِ قراءاتُها" onchange="this.form.submit()" class="mrd-eq-select">
                <?php foreach ($equipments as $e): ?>
                    <option value="<?php echo intval($e['id']); ?>" <?php echo $selected === intval($e['id']) ? 'selected' : ''; ?>>
                        #<?php echo intval($e['id']); ?> — <?php echo htmlspecialchars((string)($e['name'] ?? '')); ?>
                        · قراءات: <?php echo intval($e['readings_count']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <strong>العدّاد:</strong>
            <select name="meter_type" aria-label="نوعُ العدّاد — ساعاتٌ أو كيلومترات" onchange="this.form.submit()">
                <?php foreach ($TYPE_LABELS as $k => $lbl): ?>
                    <option value="<?php echo $k; ?>" <?php echo $mtype === $k ? 'selected' : ''; ?>><?php echo $lbl; ?></option>
                <?php endforeach; ?>
            </select>
        </form>

        <div class="mrd-now">
            <strong>العدّادُ الحالي:</strong>
            <span class="mrd-now-value"><?php echo htmlspecialchars((string)$meter['value']); ?></span>
            <?php echo htmlspecialchars($TYPE_LABELS[$mtype]); ?>
            <?php if ($meter['is_reading']): ?>
                <span class="badge badge-success">قراءةٌ مسجَّلة</span>
                <?php if ($meter['as_of']): ?><small>بتاريخ <?php echo htmlspecialchars((string)$meter['as_of']); ?></small><?php endif; ?>
            <?php else: ?>
                <span class="badge badge-warning">ليس قراءةَ عدّاد</span>
            <?php endif; ?>
            <div class="mrd-now-note"><?php echo htmlspecialchars((string)$meter['note']); ?></div>
        </div>
    </div></div>

    <?php if ($can_add): ?>
    <div class="card"><div class="card-header"><h5><i class="fa fa-plus"></i> تسجيلُ قراءة</h5></div>
    <div class="card-body">
        <form method="post" class="ems-form">
        <?= csrf_field() ?>
            <input type="hidden" name="mr_action" value="record">
            <input type="hidden" name="equipment_id" value="<?php echo $selected; ?>">
            <input type="hidden" name="meter_type" value="<?php echo htmlspecialchars($mtype); ?>">
            <div class="form-grid">
                <div class="form-group"><label for="emsf_157_8f657">تاريخ القراءة <span class="mrd-req">*</span></label>
                    <input type="date" name="reading_date" required id="emsf_157_8f657" value="<?php echo date('Y-m-d'); ?>"></div>
                <div class="form-group"><label for="emsf_158_d1eb7">القيمة <span class="mrd-req">*</span>
                        <small>— لا تقلّ عن آخرِ قراءة</small></label>
                    <input type="number" step="0.01" min="0" name="value" required id="emsf_158_d1eb7"></div>
                <div class="form-group">
                    <label for="emsf_159_8f981">المصدر</label>
                    <select name="source" id="emsf_159_8f981">
                        <option value="manual">يدوي</option>
                        <option value="inspection">فحص</option>
                    </select>
                </div>
                <div class="form-group"><label for="emsf_160_5f7c8">ملاحظة</label><input type="text" name="note" maxlength="255" id="emsf_160_5f7c8"></div>
            </div>
            <div class="mrd-actions"><button type="submit" class="btn-primary"><i class="fa fa-save"></i> تسجيل</button></div>
        </form>
    </div></div>
    <?php endif; ?>

    <div class="card"><div class="card-header"><h5><i class="fa fa-list"></i> سلسلةُ القراءات</h5></div>
    <div class="card-body"><div class="table-container">
        <table class="alltables display nowrap mrd-table">
            <thead><tr>
                <th>السلسلة</th><th>تاريخ القراءة</th><th>القيمة</th><th>الفارق</th>
                <th>مصدر القراءة</th><th>مرجع التفويض</th><th>ملاحظة</th>
                <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
                <th class="ems-fn-th" data-fn="1">رقم القراءة</th>
                <th class="ems-fn-th" data-fn="1">القراءة السابقة</th>
                <th class="ems-fn-th" data-fn="1">الفرق</th>
                <th class="ems-fn-th" data-fn="1">الساعات المسجَّلة في التايم شيت</th>
                <th class="ems-fn-th" data-fn="1">فرق المطابقة</th>
                <th class="ems-fn-th" data-fn="1">سبب الفرق</th>
                <th class="ems-fn-th" data-fn="1">الاستحقاق الوقائي التالي</th>
                <th class="ems-fn-th" data-fn="1">الساعات المتبقية للوقائية</th>
                <th class="ems-fn-th" data-fn="1">سجّلها</th>
                <th class="ems-fn-th" data-fn="1">صحّحها</th>
                <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
                <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
                <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
                <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
                <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
                <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
                <th class="ems-gov-th none" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
                <th class="ems-gov-th none" data-gov="idem_key" data-slice="2" title="يمنع وقوع الأثر مرتين بمفتاح مركب">مفتاح منع التكرار</th>
                <th class="ems-gov-th none" data-gov="reversed_by" data-slice="2" title="مرجع الحركة التي عكسته">معكوس بـ</th>
                <th class="ems-gov-th none" data-gov="reversal_of" data-slice="2" title="مرجع الحركة التي عكسها">عكس عن</th>
                <th class="ems-gov-th none" data-gov="impact_grade" data-slice="2" title="مبدئي أم نهائي — فلا يقفل مبدئي ماليًّا">درجة الأثر</th>
                <th class="ems-gov-th none" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
                <th class="ems-gov-th none" data-gov="cost_center" data-slice="3" title="وجهة التحميل">مركز التكلفة</th>
                <th class="ems-gov-th none" data-gov="fx_rate_source" data-slice="3" title="ما خالف عملة الدفاتر يحمل السعر ومصدره">سعر الصرف ومصدره</th>
                </tr></thead>
            <tbody>
            <?php foreach ($chain as $r): ?>
                <tr class="<?php echo intval($r['is_reset']) === 1 ? 'mrd-row-reset' : ''; ?>">
                    <td><?php echo intval($r['chain_no']); ?>
                        <?php if (intval($r['is_reset']) === 1): ?>
                            <span class="badge badge-warning" title="<?php echo htmlspecialchars((string)$r['reset_reason']); ?>">تصفير</span>
                        <?php endif; ?></td>
                    <td><?php echo htmlspecialchars((string)$r['reading_date']); ?></td>
                    <td><strong><?php echo htmlspecialchars((string)$r['value']); ?></strong></td>
                    <td><?php echo $r['delta'] !== null ? htmlspecialchars((string)$r['delta']) : '—'; ?></td>
                    <td><?php echo htmlspecialchars($SOURCE_LABELS[$r['source']] ?? $r['source']); ?></td>
                    <td><small><?php echo htmlspecialchars((string)($r['source_ref'] ?? $r['reset_doc_ref'] ?? '—')); ?></small></td>
                    <td><small><?php echo htmlspecialchars((string)($r['note'] ?? '')); ?></small></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div></div></div>

    <?php if ($can_edit): ?>
    <div class="card"><div class="card-header">
        <h5><i class="fa fa-rotate-left"></i> تصفيرُ العدّاد — <strong>بقرارٍ موثَّق</strong></h5></div>
    <div class="card-body">
        <p class="mrd-caution">
            التصفيرُ لا يمحو ماضيًا: يفتح <strong>سلسلةً جديدة</strong> وتبقى السابقةُ كاملةً للقراءة
            (UX-10 §8). والسببُ ومرجعُ المستند <strong>إلزاميان</strong>.
        </p>
        <form method="post" class="ems-form" onsubmit="return confirm('تأكيدُ التصفير — يفتح سلسلةً جديدة؟');">
        <?= csrf_field() ?>
            <input type="hidden" name="mr_action" value="reset">
            <input type="hidden" name="equipment_id" value="<?php echo $selected; ?>">
            <input type="hidden" name="meter_type" value="<?php echo htmlspecialchars($mtype); ?>">
            <div class="form-grid">
                <div class="form-group"><label for="emsf_161_b922e">تاريخ التصفير <span class="mrd-req">*</span></label>
                    <input type="date" name="reading_date" required id="emsf_161_b922e"></div>
                <div class="form-group"><label for="emsf_162_1d017">قيمةُ بداية السلسلة <span class="mrd-req">*</span></label>
                    <input type="number" step="0.01" min="0" name="value" required value="0" id="emsf_162_1d017"></div>
                <div class="form-group"><label for="emsf_163_87043">السبب <span class="mrd-req">*</span></label>
                    <input type="text" name="reset_reason" required maxlength="255"
                           placeholder="استبدالُ عدّادٍ معطوب" id="emsf_163_87043"></div>
                <div class="form-group"><label for="emsf_164_59aff">مرجع المستند <span class="mrd-req">*</span></label>
                    <input type="text" name="reset_doc_ref" required maxlength="120"
                           placeholder="محضرُ ورشة 2026/114" id="emsf_164_59aff"></div>
            </div>
            <div class="mrd-actions"><button type="submit" class="btn-primary"><i class="fa fa-rotate-left"></i> تصفير</button></div>
        </form>
    </div></div>
    <?php endif; ?>

    <div class="card"><div class="card-header">
        <h5><i class="fa fa-triangle-exclamation"></i> عدّاداتٌ لم تُحدَّث منذ 14 يومًا
            (<?php echo count($stale); ?>)</h5></div>
    <div class="card-body"><div class="table-container">
        <table class="alltables display nowrap mrd-table">
            <thead><tr><th>كود المعدة</th><th>القراءة</th></tr></thead>
            <tbody>
            <?php foreach ($stale as $s): ?>
                <tr>
                    <td>#<?php echo intval($s['id']); ?> — <?php echo htmlspecialchars((string)($s['name'] ?? '')); ?></td>
                    <td><?php echo $s['last_reading'] !== null
                        ? htmlspecialchars((string)$s['last_reading'])
                        : "<span class='badge badge-danger'>بلا قراءةٍ قط</span>"; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div></div></div>
</div>

<script src="../includes/js/jquery-3.7.1.main.js"></script>
</body>
</html>
