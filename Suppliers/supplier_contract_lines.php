<?php
require_once __DIR__ . '/../includes/permissions_helper.php';
/**
 * Suppliers/supplier_contract_lines.php — بنودُ عقد المورد (H-07 · CON-03 §2-②④)
 * ───────────────────────────────────────────────────────────────────────────
 * «سعرُ الوحدة لكل نموذجٍ وبند · العملةُ · **أساسُ احتساب الاستعداد إن استُحق**».
 *
 * الشاشةُ **لا تكتب سطرًا بيدها** — كلُّ حفظٍ يمرّ بـ`SupplierContractService`
 * فحراسُها (نموذجٌ خارج القائمة 422 · نافذٌ 423 «بملحق» · مرحَّلٌ 423 بمصدره ·
 * مكررٌ 409) تسري من الشاشة ومن أي مستدعٍ آخر معًا — لا حارسَ في الواجهة وحدَها.
 *
 * لا حذفَ: البندُ يُنهى بسريانٍ (`ended` + `valid_to`) ولا يُمحى.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}
include '../config.php';
require_once __DIR__ . '/../includes/w14_grid.php';
require_once __DIR__ . '/../app/Services/Contract/SupplierContractService.php';
require_once __DIR__ . '/../app/Services/Contract/ContractStateMachine.php';

use App\Services\Contract\SupplierContractService as SCS;
use App\Services\Contract\ContractStateMachine as CSM;

$current_role   = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$company_id     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$uid            = isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0;

if (!$is_super_admin && $company_id <= 0) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد بيئة شركة صالحة للمستخدم ❌', 'GOV-SCOPE-403', '');
    exit();
}

// ── صلاحيةٌ صارمة: الوحدةُ بكودها الحرفي وغيابُها منع (نمطُ H-05) ──────────
$MODULE_CODE = 'Suppliers/supplier_contract_lines.php';
$can_view = $can_add = $can_edit = false;
if ($is_super_admin) {
    $can_view = $can_add = $can_edit = true;
} else {
    /* `RPR-03` §٦ — **المسارُ الواحد**: القرارُ من `check_page_permissions()`
           لا من استعلامٍ خاصٍّ بهذا الملفّ. **والفرقُ طبقةُ القوالب**
           (`GOV-AUTH-01`): القراءةُ الخامّةُ لا ترى القالبَ النافذَ، فتُخفى
           الشاشةُ من السايدبارِ وتُفتح بالرابطِ المباشر.
        ⛔ **وفرعُ السوبر أدمن أعلاه لم يُمَسّ** — والأسماءُ كما كانت. */
    $__perm = check_page_permissions($conn, $MODULE_CODE);
    $can_view = (bool) $__perm['can_view'];
    $can_add = (bool) $__perm['can_add'];
    $can_edit = (bool) $__perm['can_edit'];
}
if (!$can_view) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض بنود عقود الموردين ❌', 'GOV-PERM-403', '');
    exit();
}

$gate = $is_super_admin ? ems_tenant_db()->forAllTenants('supplier contract lines super') : ems_tenant_db();

$MODEL_LABELS  = array('hour' => 'ساعة', 'ton' => 'طن', 'trip' => 'نقلة', 'meter' => 'متر');
$BASIS_LABELS  = array('none' => 'لا استعداد', 'rate' => 'معدل ساعة', 'percent' => 'نسبة من سعر الوحدة');
$LINE_STATES   = array('active' => 'نافذ', 'replaced' => 'مستبدل', 'ended' => 'منتهٍ');

$selected = intval($_GET['contract_id'] ?? 0);
$redirect = function ($msg, $cid) { ems_gov_redirect("Location: supplier_contract_lines.php?contract_id=" . intval($cid)
    . "&msg=" . rawurlencode($msg)); exit(); };

// ── الأفعالُ كلُّها عبر الخدمة ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = strval($_POST['sc_action'] ?? '');

    if ($action === 'create_contract') {
        if (!$can_add) { $redirect('لا توجد صلاحية لهذا الإجراء ❌', 0); }
        $r = SCS::createContract($conn, $gate, $company_id, array(
            'supplier_id'        => $_POST['supplier_id'] ?? 0,
            'client_contract_id' => $_POST['client_contract_id'] ?? '',
            'project_id'         => $_POST['project_id'] ?? '',
            'start_date'         => $_POST['start_date'] ?? '',
            'end_date'           => $_POST['end_date'] ?? '',
            'currency'           => $_POST['currency'] ?? '',
            'notes'              => $_POST['notes'] ?? '',
        ), $uid);
        $redirect($r['ok'] ? 'أنشئ عقد المورد (مسودة) ✅' : ($r['code'] . ' — ' . $r['reason'] . ' ❌'),
                  $r['ok'] ? $r['contract_id'] : 0);
    }

    if ($action === 'save_line') {
        $cid = intval($_POST['contract_id'] ?? 0);
        $isEdit = intval($_POST['line_id'] ?? 0) > 0;
        if (($isEdit && !$can_edit) || (!$isEdit && !$can_add)) { $redirect('لا توجد صلاحية لهذا الإجراء ❌', $cid); }
        // نظيرٌ خادميٌّ لسمة pattern في النموذج — الواجهةُ وحدَها لا تحرس (الحقل اختياري)
        $etype_raw = trim((string) ($_POST['equipment_type_code'] ?? ''));
        if ($etype_raw !== '' && !preg_match('/^[A-Za-z0-9_\-]+$/', $etype_raw)) {
            $redirect('رمز نوع المعدة غير صالح. استخدم أحرفا وأرقاما و - أو _ فقط ❌', $cid);
        }
        $r = SCS::saveLine($conn, $gate, $company_id, $cid, array(
            'line_id'       => $_POST['line_id'] ?? 0,
            'work_model'    => $_POST['work_model'] ?? '',
            'unit'          => $_POST['unit'] ?? '',
            'unit_price'    => $_POST['unit_price'] ?? 0,
            'currency'      => $_POST['currency'] ?? '',
            'standby_basis' => $_POST['standby_basis'] ?? 'none',
            'standby_rate'  => $_POST['standby_rate'] ?? '',
            'valid_from'    => $_POST['valid_from'] ?? '',
            'valid_to'      => $_POST['valid_to'] ?? '',
            // CAP-01 §8.2 — بندُ نوع المعدة والاحتياطي في عقد المورد
            'contract_obligation_ref'  => $_POST['contract_obligation_ref'] ?? 0,
            'equipment_type_code'      => $etype_raw,
            'primary_units_committed'  => $_POST['primary_units_committed'] ?? '',
            'standby_units_required'   => $_POST['standby_units_required'] ?? '',
            'standby_units_allowed'    => $_POST['standby_units_allowed'] ?? '',
            'replacement_sla_hours'    => $_POST['replacement_sla_hours'] ?? '',
            'standby_activation_terms' => $_POST['standby_activation_terms'] ?? '',
            'standby_payment_terms'    => $_POST['standby_payment_terms'] ?? '',
        ), $uid);
        $redirect($r['ok'] ? 'حفظ البند ✅' : ($r['code'] . ' — ' . $r['reason'] . ' ❌'), $cid);
    }

    if ($action === 'end_line') {
        $cid = intval($_POST['contract_id'] ?? 0);
        if (!$can_edit) { $redirect('لا توجد صلاحية لهذا الإجراء ❌', $cid); }
        $r = SCS::endLine($conn, $gate, $company_id, $cid,
            intval($_POST['line_id'] ?? 0), strval($_POST['end_date'] ?? ''), $uid);
        $redirect($r['ok'] ? 'أنهي البند بسريانه ✅' : ($r['code'] . ' — ' . $r['reason'] . ' ❌'), $cid);
    }

    /* ══ INJ-0152 · دورةُ حياةِ عقدِ المورد — بابُها في شاشتِها المالكة ═══════════
         نصُّ القبول: «إنهاءُ عقدِ موردٍ **ينقل حالتَه في آلة الحالة**، وينشر
         حدثًا واحدًا يُقفل حاوياتِه، ويكتب صفَّ تدقيقٍ بقيمةٍ قبل وبعد، **وله
         فعلُ نقضٍ**».
         والمقيسُ قبلَه: `SupplierContractService::transition` مبنيةٌ ومحروسةٌ
         (قفلٌ تفاؤليٌّ · حارسُ تجديدٍ · حارسُ إقفال) — و**لا شاشةَ تنادِيها**.
         فالحالةُ تُعرض في الترويسةِ ولا سبيلَ إلى تغييرها إلا بيدٍ في القاعدة.
       ◆ والأفعالُ من السجلِّ الواحدِ نفسِه (`ContractLifecycleActions`) —
         فعقدُ الموردِ وعقدُ العميلِ يتشاركان الآلةَ فيتشاركان بابَها. */
    if ($action === 'sc_lifecycle') {
        $cid = intval($_POST['contract_id'] ?? 0);
        if (!$can_edit) { $redirect('لا توجد صلاحية لهذا الإجراء ❌', $cid); }
        require_once __DIR__ . '/../app/Services/Contract/ContractLifecycleActions.php';
        $r = \App\Services\Contract\ContractLifecycleActions::run(
            $conn, $gate, $company_id, 'supplier', $cid, strval($_POST['sc_action'] ?? ''),
            strval($_POST['sc_note'] ?? ''), $uid, 0, intval($_POST['sc_version'] ?? 0));
        $redirect($r['ok'] ? ($r['reason'] . ' ✅') : ($r['code'] . ' — ' . $r['reason'] . ' ❌'), $cid);
    }

    /* ◆ **والنقضُ بابٌ مستقلٌّ**: ليس انتقالًا في الجدولِ بل حركةٌ معوِّضةٌ
         محكومةٌ بنافذةٍ زمنيةٍ وبالحالةِ السابقةِ المقروءةِ من سجلِّ التدقيق. */
    if ($action === 'sc_revoke_end') {
        $cid = intval($_POST['contract_id'] ?? 0);
        if (!$can_edit) { $redirect('لا توجد صلاحية لهذا الإجراء ❌', $cid); }
        $r = SCS::revokeTermination($conn, $gate, $company_id, $cid, strval($_POST['sc_note'] ?? ''), $uid);
        $redirect($r['ok'] ? ($r['reason'] . ' ✅') : ($r['code'] . ' — ' . $r['reason'] . ' ❌'), $cid);
    }
}

// ── القراءة ────────────────────────────────────────────────────────────────
$heads = array();
try {
    $heads = $gate->scopedQuery(array(
        'scope'  => array('h' => 'supplier_contracts'),
        'enrich' => array('s' => 'suppliers', 'c' => 'contracts'),
    ), "SELECT h.*, s.name AS supplier_name, c.first_party AS client_contract_label,
               (SELECT COUNT(*) FROM supplier_contract_lines l
                 WHERE l.contract_id = h.id AND COALESCE(l.is_deleted,0)=0) AS lines_count
          FROM supplier_contracts h
          LEFT JOIN suppliers s ON s.id = h.supplier_id
          LEFT JOIN contracts c ON c.id = h.client_contract_id
         WHERE {TENANT_SCOPE} AND COALESCE(h.is_deleted,0)=0
         ORDER BY h.id DESC");
} catch (\Throwable $t) { $heads = array(); }

$head = null;
foreach ($heads as $h) { if (intval($h['id']) === $selected) { $head = $h; } }
if ($head === null && $heads) { $head = $heads[0]; $selected = intval($head['id']); }

$lines = $selected > 0 ? SCS::linesOf($gate, $selected) : array();
$blocked = $head !== null ? SCS::assertEditable($head) : array('code' => 0, 'reason' => 'لا عقد مختارا');

$suppliers_options = array();
try {
    $suppliers_options = $gate->scopedQuery(array('scope' => array('s' => 'suppliers')),
        "SELECT s.id, s.name FROM suppliers s WHERE {TENANT_SCOPE} AND COALESCE(s.is_deleted,0)=0 ORDER BY s.name");
} catch (\Throwable $t) { $suppliers_options = array(); }

$client_contracts = array();
try {
    $client_contracts = $gate->scopedQuery(array('scope' => array('c' => 'contracts')),
        "SELECT c.id, c.first_party, c.contract_status FROM contracts c
          WHERE {TENANT_SCOPE} AND COALESCE(c.is_deleted,0)=0 ORDER BY c.id DESC");
} catch (\Throwable $t) { $client_contracts = array(); }

// CAP-01 §8.2 — التزاماتُ أنواع المعدات في عقد العميل المرتبط (لا حصةَ بلا التزام)
$obligation_options = array();
if ($head !== null && intval($head['client_contract_id'] ?? 0) > 0) {
    try {
        $obligation_options = $gate->scopedQuery(array('scope' => array('cc' => 'contract_commitments')),
            "SELECT cc.id, cc.commitment_code, cc.equipment_type_code, cc.primary_units_contracted,
                    cc.standby_units_required, cc.standby_units_allowed
               FROM contract_commitments cc
              WHERE {TENANT_SCOPE} AND cc.contract_ref = ? AND cc.is_deleted = 0
                AND cc.equipment_type_code IS NOT NULL
              ORDER BY cc.id DESC", array(intval($head['client_contract_id'])));
    } catch (\Throwable $t) { $obligation_options = array(); }
}

$page_title = 'إيكوبيشن | بنود عقد المورد';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
// NAV-01 §8 (update0006-b): الشاشةُ قسمٌ من ملف المورد الأم
$sf_supplier_id = intval($_GET['supplier_id'] ?? $_GET['id'] ?? 0); $sf_active = 'contracts';
if ($sf_supplier_id > 0) include __DIR__ . '/../includes/supplier_file_tabs.php';
?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'بنود عقد المورد'; $header_icon = 'fa fa-file-invoice-dollar';
    $header_actions = array();
    if ($can_add) {
        $header_actions[] = array('href' => 'javascript:void(0)', 'id' => 'toggleContractForm',
            'icon' => 'fa fa-plus', 'label' => 'عقد مورد جديد', 'class' => 'add');
    }
    $header_back = array('href' => 'supplierscontracts.php', 'class' => '',
                         'icon' => 'fas fa-arrow-right', 'label' => 'عقود الموردين');
    include('../includes/page_header.php'); ?>
    <!-- سجلُّ حقولِ الورقةِ بحبّتِه — يُضاف بجانبِ ما بُني لا بدلًا منه،
         فالمبنيُّ له أفعالُه والورقةُ تطلب السجلَّ بحقولِه كلِّها -->
    <div class="card"><div class="card-header"><h5><i class="fa fa-clipboard-list"></i> بنود عقود الموردين بحقول الورقة</h5></div>
    <div class="card-body"><div class="table-container">
        <?php /* GUIDE_COLS:govui_field_close
             الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
             والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
             ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
        $GUIDE_COLS = array(
            'رقم البند' => 'c152',
            'كود عقد المورد' => 'c153',
            'رقم المورد' => 'c154',
            'اسم المورد (بحث)' => 'c155',
            'رقم بند عقد العميل المقابل' => 'c156',
            'نموذج العمل' => 'c157',
            'نوع الآلية/البند' => 'c158',
            'وحدة القياس' => 'c159',
            'الحصة الشهرية المتعاقدة' => 'c160',
            'سعر الوحدة الأساسي' => 'c161',
            'سعر الوحدة الإضافي' => 'c162',
            'سعر السداد ≤15 يوما' => 'c163',
            'سعر السداد 15 45' => 'c164',
            'سعر السداد >45' => 'c165',
            'المبلغ الشهري الأساسي' => 'c166',
            'عدد الورديات' => 'c167',
            'العملة' => 'c168',
            'سريان التركيبة من' => 'c169',
            'إلى' => 'c170',
            'Pricing_Model' => 'c171',
            'Billing_UOM' => 'c172',
            'Minimum_Qty (أساس الوحدة التعاقدية)' => 'c173',
            'Minimum_Period' => 'c174',
            'Guaranteed_Qty' => 'c175',
            'Threshold_Qty' => 'c176',
            'Shortfall_Bearer' => 'c177',
            'Pricing_Reference' => 'c178',
            'النسخة السعرية' => 'c179',
            'حالة البند' => 'c180',
            'مصدر السعر/الحجية' => 'c181',
            'ملاحظات' => 'c182',
        );
        $D = array();
        $__gridRows = ems_w14_guide_rows('sup_contract_line');
        echo ems_w14_grid('emsList_sup_lines', $GUIDE_COLS, $__gridRows, $D, 'لا بند عقد مسجل بعد'); /* /GUIDE_COLS */ ?>
    </div></div></div>
    <?php 
    if (isset($_GET['msg'])) {
        echo '<div class="alert alert-info">' . htmlspecialchars($_GET['msg']) . '</div>';
    }
    // UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
    echo ems_states_bundle('لا بند تسعير مسجلا في هذا العقد', 'أضف أول بند بنموذج «بند جديد / تعديل» أسفل الجدول — نموذج التشغيل والوحدة والسعر إلزامية');
    ?>
    <style>
        .sup-scl-req           { color: var(--c-state-danger-strong, #c00); }
        .sup-scl-actions       { margin-top: 12px; }
        .sup-scl-filter-row    { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; margin-bottom: 10px; }
        .sup-scl-picker        { min-width: 340px; }
        .sup-scl-headline      { margin-bottom: 12px; line-height: 1.9; }
        .sup-scl-lifecycle     { display: flex; gap: 6px; flex-wrap: wrap; align-items: center; margin-bottom: 12px;
                                 padding: 8px 10px; border: 1px solid var(--c-e0d7bd, #e0d7bd); border-radius: 8px;
                                 background: var(--c-fffdf3, #fffdf3); }
        .sup-scl-inline-form   { display: inline; }
        .sup-scl-revoke-form   { display: flex; gap: 4px; align-items: center; }
        .sup-scl-note-input    { width: 150px; }
        .sup-scl-table         { width: 100%; }
        .sup-scl-line-form     { margin-top: 16px; }
        .sup-scl-section-split { grid-column: 1 / -1; border-top: 1px dashed var(--c-bbb, #bbb); padding-top: 10px; }
    </style>

    <?php if ($can_add): ?>
    <form method="post" class="allforms" id="contractForm">
        <?= csrf_field() ?>
        <input type="hidden" name="sc_action" value="create_contract">
        <div class="card"><div class="card-header"><h5><i class="fa fa-handshake"></i> عقد مورد جديد (ينشأ مسودة)</h5></div>
        <div class="card-body"><div class="form-grid">
            <div class="form-group">
                <label for="emsf_1437_0825d">المورد <span class="sup-scl-req">*</span></label>
                <select name="supplier_id" required id="emsf_1437_0825d">
                    <option value="">— اختر المورد —</option>
                    <?php foreach ($suppliers_options as $s): ?>
                        <option value="<?php echo intval($s['id']); ?>"><?php echo htmlspecialchars($s['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="emsf_1438_c7d1e">عقد العميل (L1) <small>— الحصة تقتطع منه</small></label>
                <select name="client_contract_id" id="emsf_1438_c7d1e">
                    <option value="">— بلا —</option>
                    <?php foreach ($client_contracts as $c): ?>
                        <option value="<?php echo intval($c['id']); ?>">
                            #<?php echo intval($c['id']); ?> — <?php echo htmlspecialchars((string)($c['first_party'] ?? '')); ?>
                            (<?php echo htmlspecialchars((string)$c['contract_status']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label for="emsf_1439_c99c8">تاريخ البدء <span class="sup-scl-req">*</span></label>
                <input type="date" name="start_date" required id="emsf_1439_c99c8"></div>
            <div class="form-group"><label for="emsf_1440_a35df">تاريخ الانتهاء</label><input type="date" name="end_date" id="emsf_1440_a35df"></div>
            <div class="form-group">
                <label for="emsf_1441_ff415">العملة</label>
                <select name="currency" id="emsf_1441_ff415">
                    <option value="">— بلا —</option>
                    <?php foreach (SCS::CURRENCIES as $cur): ?>
                        <option value="<?php echo $cur; ?>"><?php echo $cur; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label for="emsf_1442_2a0d8">ملاحظات</label><input type="text" name="notes" maxlength="255" id="emsf_1442_2a0d8"></div>
        </div>
        <div class="sup-scl-actions"><button type="submit" class="btn-primary"><i class="fa fa-save"></i> حفظ</button></div>
        </div></div>
    </form>
    <?php endif; ?>

    <div class="card"><div class="card-body">
        <form method="get" class="sup-scl-filter-row">
            <strong>عقد المورد:</strong>
            <select name="contract_id" aria-label="عقد المورد" onchange="this.form.submit()" class="sup-scl-picker">
                <?php foreach ($heads as $h): ?>
                    <option value="<?php echo intval($h['id']); ?>" <?php echo $selected === intval($h['id']) ? 'selected' : ''; ?>>
                        #<?php echo intval($h['id']); ?> — <?php echo htmlspecialchars((string)($h['supplier_name'] ?? '—')); ?>
                        · <?php echo htmlspecialchars((string)$h['state']); ?>
                        · بنود: <?php echo intval($h['lines_count']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>

        <?php if ($head !== null): ?>
        <div class="sup-scl-headline">
            <strong><?php echo htmlspecialchars((string)($head['supplier_name'] ?? '—')); ?></strong>
            · <span class="badge badge-info"><?php echo htmlspecialchars((string)$head['state']); ?></span>
            · المدة: <?php echo htmlspecialchars((string)($head['start_date'] ?? '—')); ?>
              → <?php echo htmlspecialchars((string)($head['end_date'] ?? 'مفتوح')); ?>
            · العملة: <?php echo htmlspecialchars((string)($head['currency'] ?? '—')); ?>
            <?php if ($head['client_contract_id']): ?>
                · عقد العميل L1: #<?php echo intval($head['client_contract_id']); ?>
            <?php endif; ?>
            <?php if (trim((string)$head['source_table']) !== ''): ?>
                <span class="badge badge-secondary"
                      title="الكتابة تبقى في المصدر حتى تكتمل مطابقة فترة بصفر فرق (N-04 مرحلة ①)">
                    مرحل قراءة من <?php echo htmlspecialchars((string)$head['source_table']); ?>#<?php echo intval($head['source_id']); ?>
                </span>
            <?php endif; ?>
        </div>
        <?php
        /* ══ INJ-0152 · أزرارُ دورةِ الحياةِ من السجلِّ لا من رأيِ الشاشة ═════════
             تُصيَّر الأفعالُ المشروعةُ **من الحالةِ الراهنة** وحدَها، ويحمل كلُّ
             زرٍّ في `title` جوابَ سؤالِ العكس: له عكسٌ باسمِه، أو لا عكسَ له
             بسببِه. والعقدُ المرحَّلُ لا يُقاد من هنا — حالتُه مرآةُ مصدرِه. */
        if ($head !== null && $can_edit && trim((string) $head['source_table']) === '') {
            require_once __DIR__ . '/../app/Services/Contract/ContractLifecycleActions.php';
            $__scActs = \App\Services\Contract\ContractLifecycleActions::availableFor('supplier', (string) $head['state']);
            $__isEnded = ((string) $head['state'] === \App\Services\Contract\ContractStateMachine::ENDED);
            if ($__scActs || $__isEnded) { ?>
            <div class="sup-scl-lifecycle">
                <strong>دورة الحياة:</strong>
                <?php foreach ($__scActs as $__c => $__a):
                    $__rv = \App\Services\Contract\ContractLifecycleActions::reverseOf('supplier', $__c); ?>
                    <form method="post" class="sup-scl-inline-form">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="sc_lifecycle">
                        <input type="hidden" name="contract_id" value="<?php echo intval($head['id']); ?>">
                        <input type="hidden" name="sc_action" value="<?php echo htmlspecialchars($__c, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="sc_version" value="<?php echo intval($head['version']); ?>">
                        <button class="action-btn" type="submit"
                                title="<?php echo htmlspecialchars($__a['label'] . ' — '
                                    . ($__rv['has'] ? ('له عكس: ' . $__rv['label'])
                                                    : ('لا عكس له: ' . (string) $__rv['why'])), ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars($__a['label'], ENT_QUOTES, 'UTF-8'); ?>
                            <?php if (!$__rv['has']): ?><small>⛒</small><?php endif; ?>
                        </button>
                    </form>
                <?php endforeach; ?>
                <?php if ($__isEnded): ?>
                    <form method="post" class="sup-scl-revoke-form">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="sc_revoke_end">
                        <input type="hidden" name="contract_id" value="<?php echo intval($head['id']); ?>">
                        <input type="text" name="sc_note" required minlength="3" class="sup-scl-note-input"
                               placeholder="سبب النقض">
                        <button class="action-btn" type="submit"
                                title="نقض الإنهاء — حركة معوضة داخل سبعة أيام تعيد الحالة السابقة وتفتح حاوياتها">
                            نقض الإنهاء
                        </button>
                    </form>
                <?php endif; ?>
            </div>
            <?php }
        } ?>
        <?php if ($blocked !== null): ?>
            <div class="alert alert-warning">
                <i class="fa fa-lock"></i> <?php echo htmlspecialchars($blocked['reason']); ?>
                (<?php echo intval($blocked['code']); ?>)
            </div>
        <?php endif; ?>
        <?php endif; ?>

        <div class="table-container">
            <table class="alltables display nowrap sup-scl-table" id="linesTable">
                <thead><tr>
                    <th>الإجراءات</th><th>النموذج</th><th>الوحدة</th><th>سعر الوحدة</th>
                    <th>العملة</th><th>أساس الاستعداد</th><th>المعدل</th><th>السريان</th><th>الحالة</th>
                    <!-- E-03 موجة ٤: النواة الحاكمة (gov_columns) — الخلايا يحشوها ui-unification.js -->
                    <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صف بلا كيان مالك">الكيان</th>
                    <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المنشئ — الاسم والصفة</th>
                    <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمد — الاسم والصفة</th>
                    <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمد — تفويض أو سلطة أصلية">مرجع التفويض</th>
                    <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
                    <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
                    <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
                    </tr></thead>
                <tbody>
                <?php foreach ($lines as $l): ?>
                    <tr>
                        <td>
                            <?php if ($can_edit && $blocked === null && trim((string)$l['source_table']) === ''): ?>
                            <a href="javascript:void(0)" class="editLine action-btn edit"
                               data-id="<?php echo intval($l['id']); ?>"
                               data-model="<?php echo htmlspecialchars((string)$l['work_model'], ENT_QUOTES); ?>"
                               data-unit="<?php echo htmlspecialchars((string)$l['unit'], ENT_QUOTES); ?>"
                               data-price="<?php echo htmlspecialchars((string)$l['unit_price'], ENT_QUOTES); ?>"
                               data-currency="<?php echo htmlspecialchars((string)($l['currency'] ?? ''), ENT_QUOTES); ?>"
                               data-basis="<?php echo htmlspecialchars((string)$l['standby_basis'], ENT_QUOTES); ?>"
                               data-rate="<?php echo htmlspecialchars((string)($l['standby_rate'] ?? ''), ENT_QUOTES); ?>"
                               data-from="<?php echo htmlspecialchars((string)($l['valid_from'] ?? ''), ENT_QUOTES); ?>"
                               data-to="<?php echo htmlspecialchars((string)($l['valid_to'] ?? ''), ENT_QUOTES); ?>"
                               data-obl="<?php echo htmlspecialchars((string)($l['contract_obligation_ref'] ?? ''), ENT_QUOTES); ?>"
                               data-etype="<?php echo htmlspecialchars((string)($l['equipment_type_code'] ?? ''), ENT_QUOTES); ?>"
                               data-pcommit="<?php echo htmlspecialchars((string)($l['primary_units_committed'] ?? ''), ENT_QUOTES); ?>"
                               data-sbreq="<?php echo htmlspecialchars((string)($l['standby_units_required'] ?? ''), ENT_QUOTES); ?>"
                               data-sbalw="<?php echo htmlspecialchars((string)($l['standby_units_allowed'] ?? ''), ENT_QUOTES); ?>"
                               data-sla="<?php echo htmlspecialchars((string)($l['replacement_sla_hours'] ?? ''), ENT_QUOTES); ?>"
                               data-sbact="<?php echo htmlspecialchars((string)($l['standby_activation_terms'] ?? ''), ENT_QUOTES); ?>"
                               data-sbpay="<?php echo htmlspecialchars((string)($l['standby_payment_terms'] ?? ''), ENT_QUOTES); ?>"
                               title="تعديل"><i class="fas fa-edit"></i></a>
                            <?php elseif (trim((string)$l['source_table']) !== ''): ?>
                                <span class="badge badge-secondary" title="مرحل قراءة — الكتابة في مصدره">مرحل</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($MODEL_LABELS[$l['work_model']] ?? $l['work_model']); ?></td>
                        <td><?php echo htmlspecialchars((string)$l['unit']); ?></td>
                        <td><strong><?php echo htmlspecialchars((string)$l['unit_price']); ?></strong></td>
                        <td><?php echo htmlspecialchars((string)($l['currency'] ?? '—')); ?></td>
                        <td>
                            <?php $b = (string)$l['standby_basis']; ?>
                            <?php if ($b === 'none'): ?>
                                <span class="badge badge-secondary" title="لا استعداد مشترطا — ولا يخترع له سعر">لا استعداد</span>
                            <?php else: ?>
                                <span class="badge badge-success"><?php echo htmlspecialchars($BASIS_LABELS[$b] ?? $b); ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $l['standby_rate'] !== null
                                ? htmlspecialchars((string)$l['standby_rate']) . ($b === 'percent' ? '٪' : '')
                                : '—'; ?></td>
                        <td><?php echo htmlspecialchars((string)($l['valid_from'] ?? '—')); ?>
                            → <?php echo htmlspecialchars((string)($l['valid_to'] ?? 'مفتوح')); ?></td>
                        <td><?php echo htmlspecialchars($LINE_STATES[$l['state']] ?? $l['state']); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if (($can_add || $can_edit) && $head !== null && $blocked === null): ?>
        <form method="post" class="ems-form sup-scl-line-form" id="lineForm">
        <?= csrf_field() ?>
            <input type="hidden" name="sc_action" value="save_line">
            <input type="hidden" name="contract_id" value="<?php echo $selected; ?>">
            <input type="hidden" name="line_id" id="f_line_id" value="">
            <div class="card"><div class="card-header"><h5><i class="fa fa-plus"></i> بند جديد / تعديل</h5></div>
            <div class="card-body"><div class="form-grid">
                <div class="form-group">
                    <label for="f_model">نموذج التشغيل <span class="sup-scl-req">*</span></label>
                    <select name="work_model" id="f_model" required>
                        <?php foreach ($MODEL_LABELS as $k => $lbl): ?>
                            <option value="<?php echo $k; ?>"><?php echo $lbl; ?> (<?php echo $k; ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="f_unit">الوحدة <span class="sup-scl-req">*</span> <small>— كما يقرؤها محرك الفوترة</small></label>
                    <select name="unit" id="f_unit" required></select>
                </div>
                <div class="form-group"><label for="f_price">سعر الوحدة <span class="sup-scl-req">*</span></label>
                    <input type="number" step="0.01" min="0.01" name="unit_price" id="f_price" required></div>
                <div class="form-group">
                    <label for="f_currency">العملة</label>
                    <select name="currency" id="f_currency">
                        <option value="">— من الرأس —</option>
                        <?php foreach (SCS::CURRENCIES as $cur): ?>
                            <option value="<?php echo $cur; ?>"><?php echo $cur; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="f_basis">أساس الاستعداد</label>
                    <select name="standby_basis" id="f_basis">
                        <?php foreach ($BASIS_LABELS as $k => $lbl): ?>
                            <option value="<?php echo $k; ?>"><?php echo $lbl; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="f_rate">معدل الاستعداد <small>— إلزامي متى أعلن أساس</small></label>
                    <input type="number" step="0.0001" min="0" name="standby_rate" id="f_rate">
                </div>
                <div class="form-group"><label for="f_from">سريان من</label><input type="date" name="valid_from" id="f_from"></div>
                <div class="form-group"><label for="f_to">سريان إلى</label><input type="date" name="valid_to" id="f_to"></div>
                <div class="form-group sup-scl-section-split">
                    <strong><i class="fa fa-shield-halved"></i> التغطية والاحتياطي — بند نوع المعدة (CAP-01 §8.2)</strong>
                </div>
                <div class="form-group">
                    <label for="f_obl">التزام نوع المعدة في عقد العميل <small>— لا حصة بلا التزام</small></label>
                    <?php /* ◆ **الملصقُ كان يقول القاعدةَ والحقلُ ينقضها** (البند ٢-١):
                             «— غير مرتبط —» كان **الخيارَ الأول** وبلا `required`،
                             فالقيمةُ الافتراضيةُ للحقلِ كانت نقضَ القاعدةِ المكتوبةِ فوقه.
                             والآن: `required` · وخيارُ الفراغِ عنوانُ اختيارٍ لا قيمةٌ
                             مقبولة (`disabled` فلا يُرسَل، و`selected` فلا يُختار غيرُه
                             صدفةً). والخادمُ يردُّ ٤٢٢ على الغيابِ أيًّا كانت الواجهة —
                             فهذا تيسيرٌ لا حراسة. */ ?>
                    <select name="contract_obligation_ref" id="f_obl" required>
                        <option value="" disabled selected>— اختر التزام نوع المعدة —</option>
                        <?php foreach ($obligation_options as $ob): ?>
                            <option value="<?php echo intval($ob['id']); ?>">
                                <?php echo htmlspecialchars($ob['commitment_code'] . ' · ' . $ob['equipment_type_code']
                                    . ' (أساسية ' . ($ob['primary_units_contracted'] ?? '—')
                                    . ' · احتياطي ≤ ' . ($ob['standby_units_allowed'] ?? '—') . ')', ENT_QUOTES); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label for="f_etype">رمز نوع المعدة</label>
                    <input type="text" name="equipment_type_code" id="f_etype" pattern="[A-Za-z0-9_\-]+" placeholder="EXCAVATOR"></div>
                <div class="form-group"><label for="f_pcommit">الأساسية الملتزم بها</label>
                    <input type="number" step="1" min="0" name="primary_units_committed" id="f_pcommit"></div>
                <div class="form-group"><label for="f_sbreq">الاحتياطي المطلوب منه</label>
                    <input type="number" step="1" min="0" name="standby_units_required" id="f_sbreq"></div>
                <div class="form-group"><label for="f_sbalw">سقفه الأقصى للاحتياطي</label>
                    <input type="number" step="1" min="0" name="standby_units_allowed" id="f_sbalw"></div>
                <div class="form-group"><label for="f_sla">مهلة الإحلال (ساعات)</label>
                    <input type="number" step="0.5" min="0" name="replacement_sla_hours" id="f_sla"></div>
                <div class="form-group"><label for="f_sbact">شروط تفعيل احتياطيه</label>
                    <input type="text" name="standby_activation_terms" id="f_sbact" maxlength="255"></div>
                <div class="form-group"><label for="f_sbpay">مقابل احتياطيه <small>— فارغ = لم ينص ولا يفترض</small></label>
                    <input type="text" name="standby_payment_terms" id="f_sbpay" maxlength="255"></div>
            </div>
            <div class="sup-scl-actions"><button type="submit" class="btn-primary"><i class="fa fa-save"></i> حفظ البند</button></div>
            </div></div>
        </form>
        <?php endif; ?>
    </div></div>
</div>

<script src="../includes/js/jquery-3.7.1.main.js"></script>
<script>
(function () {
    // تسمياتُ الوحدة المسموحة لكل نموذج — مرآةُ SupplierContractService::UNIT_LABELS
    var UNITS = <?php echo json_encode(SCS::UNIT_LABELS, JSON_UNESCAPED_UNICODE); ?>;
    function fillUnits(model, keep) {
        var sel = document.getElementById('f_unit');
        if (!sel) return;
        sel.innerHTML = '';
        (UNITS[model] || []).forEach(function (u) {
            var o = document.createElement('option');
            o.value = u; o.textContent = u;
            if (keep && keep === u) { o.selected = true; }
            sel.appendChild(o);
        });
    }
    document.addEventListener('DOMContentLoaded', function () {
        var toggleBtn = document.getElementById('toggleContractForm');
        var cForm = document.getElementById('contractForm');
        if (toggleBtn && cForm) {
            toggleBtn.addEventListener('click', function () { cForm.classList.toggle('allforms-visible'); });
        }
        var model = document.getElementById('f_model');
        if (model) {
            fillUnits(model.value, null);
            model.addEventListener('change', function () { fillUnits(model.value, null); });
        }
        // المعدلُ يُفتح متى أُعلن أساس — والقيدُ البنيويُّ هو الحاكم لا هذا
        var basis = document.getElementById('f_basis'), rate = document.getElementById('f_rate');
        function syncRate() {
            if (!basis || !rate) return;
            var on = (basis.value !== 'none');
            rate.disabled = !on;
            if (!on) { rate.value = ''; }
        }
        if (basis) { basis.addEventListener('change', syncRate); syncRate(); }

        document.addEventListener('click', function (ev) {
            var btn = ev.target.closest('.editLine');
            if (!btn) return;
            document.getElementById('f_line_id').value = btn.dataset.id;
            document.getElementById('f_model').value = btn.dataset.model;
            fillUnits(btn.dataset.model, btn.dataset.unit);
            document.getElementById('f_price').value = btn.dataset.price;
            document.getElementById('f_currency').value = btn.dataset.currency || '';
            document.getElementById('f_basis').value = btn.dataset.basis;
            document.getElementById('f_rate').value = btn.dataset.rate || '';
            document.getElementById('f_from').value = btn.dataset.from || '';
            document.getElementById('f_to').value = btn.dataset.to || '';
            // CAP-01 §8.2 — الفارغُ يبقى فارغًا: مقابلُ الاحتياطي لا يُفترض (DEC-CAP-A)
            var setIf = function (id, v) { var el = document.getElementById(id); if (el) { el.value = v || ''; } };
            setIf('f_obl', btn.dataset.obl);
            setIf('f_etype', btn.dataset.etype);
            setIf('f_pcommit', btn.dataset.pcommit);
            setIf('f_sbreq', btn.dataset.sbreq);
            setIf('f_sbalw', btn.dataset.sbalw);
            setIf('f_sla', btn.dataset.sla);
            setIf('f_sbact', btn.dataset.sbact);
            setIf('f_sbpay', btn.dataset.sbpay);
            syncRate();
            document.getElementById('lineForm').scrollIntoView({ behavior: 'smooth' });
        });
    });
})();
</script>
</body>
</html>
