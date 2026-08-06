<?php
/**
 * Contracts/price_terms.php — شروطُ تعديل السعر (M-09 · CON-02 §2-③)
 * ───────────────────────────────────────────────────────────────────────────
 * ثلاثةُ أقسام: **الشرطُ** كما كُتب في العقد · **قراءاتُ المؤشر** بمراجعها ·
 * **سجلُّ المراجعات** باعتمادها. والسعرُ الأساسيُّ في `contractequipments`
 * لا يُمسّ من هنا ولا من أي مكان — التعديلُ طبقةٌ بتاريخها فوقه.
 *
 * كلُّ فعلٍ عبر `PriceAdjustmentService` — لا كتابةَ خامًا في الشاشة.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}
include '../config.php';
require_once __DIR__ . '/../app/Services/Contract/PriceAdjustmentService.php';

use App\Services\Contract\PriceAdjustmentService as PAS;

$current_role   = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$company_id     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$uid            = isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0;

if (!$is_super_admin && $company_id <= 0) {
    header("Location: ../login.php?msg=لا+توجد+بيئة+شركة+صالحة+للمستخدم+❌");
    exit();
}

$MODULE_CODE = 'Contracts/price_terms.php';
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
    header("Location: ../main/dashboard.php?msg=لا+توجد+صلاحية+عرض+شروط+تعديل+السعر+❌");
    exit();
}

$gate = $is_super_admin ? ems_tenant_db()->forAllTenants('price terms super') : ems_tenant_db();

$TRIGGER_LABELS = array('fuel' => 'وقود', 'inflation' => 'تضخم', 'fx' => 'سعر صرف');
$PERIOD_LABELS  = array('monthly' => 'شهري', 'quarterly' => 'ربع سنوي',
                        'semiannual' => 'نصف سنوي', 'annual' => 'سنوي');
$OUTCOME_LABELS = array('amended' => 'تعديلٌ مولَّد', 'capped' => 'تعديلٌ مقصوصٌ بالسقف',
                        'below_threshold' => 'دون العتبة — لا تعديل', 'no_reading' => 'لا قراءةَ مؤشر',
                        'no_base_price' => 'لا سعرَ أساسٍ للبند');

$selected = intval($_GET['contract_id'] ?? 0);
$redirect = function ($msg, $cid) { header("Location: price_terms.php?contract_id=" . intval($cid)
    . "&msg=" . rawurlencode($msg)); exit(); };

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = strval($_POST['pt_action'] ?? '');
    $cid = intval($_POST['contract_id'] ?? 0);

    if ($action === 'save_term') {
        $isEdit = intval($_POST['term_id'] ?? 0) > 0;
        if (($isEdit && !$can_edit) || (!$isEdit && !$can_add)) { $redirect('لا توجد صلاحية لهذا الإجراء ❌', $cid); }
        $r = PAS::saveTerm($conn, $gate, $company_id, $cid, array(
            'term_id'              => $_POST['term_id'] ?? 0,
            'contract_item_id'     => $_POST['contract_item_id'] ?? '',
            'trigger_kind'         => $_POST['trigger_kind'] ?? '',
            'index_code'           => $_POST['index_code'] ?? '',
            'base_index'           => $_POST['base_index'] ?? 0,
            'base_date'            => $_POST['base_date'] ?? '',
            'threshold_percent'    => $_POST['threshold_percent'] ?? '',
            'pass_through_percent' => $_POST['pass_through_percent'] ?? '',
            'cap_percent'          => $_POST['cap_percent'] ?? '',
            'periodicity'          => $_POST['periodicity'] ?? 'quarterly',
            'valid_from'           => $_POST['valid_from'] ?? '',
            'valid_to'             => $_POST['valid_to'] ?? '',
            'note'                 => $_POST['note'] ?? '',
        ), $uid);
        $redirect($r['ok'] ? 'حُفظ شرطُ التعديل ✅' : ($r['code'] . ' — ' . $r['reason'] . ' ❌'), $cid);
    }

    if ($action === 'add_reading') {
        if (!$can_add) { $redirect('لا توجد صلاحية لهذا الإجراء ❌', $cid); }
        $r = PAS::recordIndexReading($conn, $gate, $company_id, array(
            'index_code'   => $_POST['index_code'] ?? '',
            'reading_date' => $_POST['reading_date'] ?? '',
            'value'        => $_POST['value'] ?? 0,
            'source_ref'   => $_POST['source_ref'] ?? '',
            'note'         => $_POST['reading_note'] ?? '',
        ), $uid);
        $redirect($r['ok'] ? 'سُجّلت قراءةُ المؤشر بمرجعها ✅' : ($r['code'] . ' — ' . $r['reason'] . ' ❌'), $cid);
    }

    if ($action === 'run_review') {
        if (!$can_edit) { $redirect('لا توجد صلاحية لهذا الإجراء ❌', $cid); }
        $r = PAS::applyDue($conn, $gate, $company_id, $cid, strval($_POST['as_of'] ?? date('Y-m-d')), $uid);
        $redirect('المراجعة: وُلّد ' . intval($r['created']) . ' · عاطلٌ ' . intval($r['skipped'])
                  . ' — والمعتمَدُ وحدَه يصير مالًا ✅', $cid);
    }

    if ($action === 'approve_revision') {
        if (!$can_edit) { $redirect('لا توجد صلاحية لهذا الإجراء ❌', $cid); }
        $r = PAS::approve($conn, $gate, $company_id, intval($_POST['revision_id'] ?? 0), $uid);
        $redirect($r['ok'] ? 'اعتُمدت المراجعة — السعرُ يسري من تاريخه ✅'
                           : ($r['code'] . ' — ' . $r['reason'] . ' ❌'), $cid);
    }
}

// ── القراءة ────────────────────────────────────────────────────────────────
$contracts = array();
try {
    $contracts = $gate->scopedQuery(array('scope' => array('c' => 'contracts')),
        "SELECT c.id, c.first_party, c.contract_status,
                (SELECT COUNT(*) FROM contract_price_terms t
                  WHERE t.contract_id = c.id AND COALESCE(t.is_deleted,0)=0) AS terms_count
           FROM contracts c
          WHERE {TENANT_SCOPE} AND COALESCE(c.is_deleted,0)=0
          ORDER BY c.id DESC");
} catch (\Throwable $t) { $contracts = array(); }
if ($selected <= 0 && $contracts) { $selected = intval($contracts[0]['id']); }

$terms     = $selected > 0 ? PAS::activeTerms($gate, $selected, date('Y-m-d')) : array();
$allTerms  = array();
try {
    $allTerms = $gate->scopedQuery(array('scope' => array('t' => 'contract_price_terms')),
        "SELECT t.* FROM contract_price_terms t
          WHERE {TENANT_SCOPE} AND t.contract_id = ? AND COALESCE(t.is_deleted,0)=0
          ORDER BY t.id DESC", array($selected));
} catch (\Throwable $t) { $allTerms = array(); }
$revisions = $selected > 0 ? PAS::revisionsOf($gate, $selected) : array();
$items     = $selected > 0 ? PAS::itemsOf($gate, $selected, null) : array();

$readings = array();
try {
    $readings = $gate->scopedQuery(array('scope' => array('r' => 'contract_price_index_readings')),
        "SELECT r.* FROM contract_price_index_readings r
          WHERE {TENANT_SCOPE} AND COALESCE(r.is_deleted,0)=0
          ORDER BY r.index_code, r.reading_date DESC LIMIT 50");
} catch (\Throwable $t) { $readings = array(); }

$page_title = 'إيكوبيشن | شروط تعديل السعر';
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
// NAV-01 §8 (update0006-b): الشاشةُ قسمٌ من ملف العقد الأم لا صفحةٌ يتيمة
$cf_contract_id = intval($_GET['contract'] ?? $_GET['id'] ?? 0); $cf_active = 'price';
if ($cf_contract_id > 0) include __DIR__ . '/../includes/contract_file_tabs.php';
?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'شروط تعديل السعر'; $header_icon = 'fa fa-arrow-trend-up';
    $header_actions = array();
    if ($can_add) {
        $header_actions[] = array('href' => 'javascript:void(0)', 'id' => 'toggleTermForm',
            'icon' => 'fa fa-plus', 'label' => 'شرط جديد', 'class' => 'add');
    }
    $header_back = array('href' => 'contracts.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'العقود');
    include('../includes/page_header.php');
    if (isset($_GET['msg'])) {
        echo '<div class="alert alert-info">' . htmlspecialchars($_GET['msg']) . '</div>';
    }
    ?>

    <div class="card"><div class="card-body">
        <form method="get" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
            <strong>العقد:</strong>
            <select name="contract_id" onchange="this.form.submit()" style="min-width:340px">
                <?php foreach ($contracts as $c): ?>
                    <option value="<?php echo intval($c['id']); ?>" <?php echo $selected === intval($c['id']) ? 'selected' : ''; ?>>
                        #<?php echo intval($c['id']); ?> — <?php echo htmlspecialchars((string)($c['first_party'] ?? '')); ?>
                        (<?php echo htmlspecialchars((string)$c['contract_status']); ?>) · شروط: <?php echo intval($c['terms_count']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
        <p style="margin-top:10px;color:#666">
            السعرُ الأساسيُّ في بنود العقد <strong>لا يُمسّ</strong> — والتعديلُ طبقةٌ بتاريخها فوقه،
            فوقائعُ ما قبل السريان تبقى بسعرها القديم (CON-02 §6 · «لا رجعية»).
        </p>
    </div></div>

    <?php if ($can_add || $can_edit): ?>
    <form method="post" class="allforms" id="termForm">
        <input type="hidden" name="pt_action" value="save_term">
        <input type="hidden" name="contract_id" value="<?php echo $selected; ?>">
        <input type="hidden" name="term_id" id="f_term_id" value="">
        <div class="card"><div class="card-header"><h5><i class="fa fa-arrow-trend-up"></i> شرطُ تعديل السعر</h5></div>
        <div class="card-body"><div class="form-grid">
            <div class="form-group">
                <label>المحفِّز <span style="color:#c00">*</span></label>
                <select name="trigger_kind" id="f_trigger" required>
                    <?php foreach ($TRIGGER_LABELS as $k => $lbl): ?>
                        <option value="<?php echo $k; ?>"><?php echo $lbl; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>رمز المؤشر <span style="color:#c00">*</span>
                    <small>— للصرف: رمزُ العملة (المصدر fin_fx_rates)</small></label>
                <input type="text" name="index_code" id="f_index" required maxlength="32" placeholder="DIESEL_SD · CPI_SD · SDG">
            </div>
            <div class="form-group">
                <label>بند التسعير</label>
                <select name="contract_item_id" id="f_item">
                    <option value="">— كلُّ بنود العقد —</option>
                    <?php foreach ($items as $it): ?>
                        <option value="<?php echo intval($it['id']); ?>">
                            بند #<?php echo intval($it['id']); ?> — <?php echo htmlspecialchars((string)($it['equip_unit'] ?? '')); ?>
                            · <?php echo htmlspecialchars((string)$it['equip_price']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label>القيمة المرجعية <span style="color:#c00">*</span></label>
                <input type="number" step="0.00000001" min="0.00000001" name="base_index" id="f_base" required></div>
            <div class="form-group"><label>تاريخ المرجع</label><input type="date" name="base_date" id="f_basedate"></div>
            <div class="form-group"><label>عتبة التفعيل ٪ <small>— دونها لا تعديل</small></label>
                <input type="number" step="0.001" min="0" name="threshold_percent" id="f_threshold" value="0"></div>
            <div class="form-group"><label>نسبة التمرير ٪ <span style="color:#c00">*</span></label>
                <input type="number" step="0.001" min="0.001" max="100" name="pass_through_percent" id="f_pass" value="100" required></div>
            <div class="form-group"><label>سقف المراجعة ٪ <small>— فارغٌ = بلا سقفٍ مكتوب</small></label>
                <input type="number" step="0.001" min="0.001" name="cap_percent" id="f_cap"></div>
            <div class="form-group">
                <label>دورية المراجعة</label>
                <select name="periodicity" id="f_period">
                    <?php foreach ($PERIOD_LABELS as $k => $lbl): ?>
                        <option value="<?php echo $k; ?>" <?php echo $k === 'quarterly' ? 'selected' : ''; ?>><?php echo $lbl; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label>سريان من <span style="color:#c00">*</span></label>
                <input type="date" name="valid_from" id="f_from" required></div>
            <div class="form-group"><label>سريان إلى</label><input type="date" name="valid_to" id="f_to"></div>
            <div class="form-group"><label>ملاحظة</label><input type="text" name="note" id="f_note" maxlength="255"></div>
        </div>
        <div style="margin-top:12px"><button type="submit" class="btn-save"><i class="fa fa-save"></i> حفظ الشرط</button></div>
        </div></div>
    </form>
    <?php endif; ?>

    <div class="card"><div class="card-header"><h5><i class="fa fa-list"></i> شروطُ العقد</h5></div>
    <div class="card-body"><div class="table-container">
        <table class="alltables display nowrap" style="width:100%">
            <thead><tr>
                <th>الإجراءات</th><th>المتغير المحفّز</th><th>المؤشر</th><th>مرجع التفويض</th><th>البند المتأثر</th>
                <th>عتبة</th><th>تمرير</th><th>السقف الأقصى للتعديل</th><th>دورية المراجعة</th><th>السريان</th><th>الحالة</th>
                <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
                <th class="ems-fn-th" data-fn="1">رقم الآلية</th>
                <th class="ems-fn-th" data-fn="1">العقد</th>
                <th class="ems-fn-th" data-fn="1">الحد المحفّز</th>
                <th class="ems-fn-th" data-fn="1">اتجاه التعديل</th>
                <th class="ems-fn-th" data-fn="1">معادلة التعديل</th>
                <th class="ems-fn-th" data-fn="1">هل بلغ الحد؟</th>
                <th class="ems-fn-th" data-fn="1">القرار</th>
                <th class="ems-fn-th" data-fn="1">عرّفها</th>
                <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
                <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
                <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
                <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
                <th class="ems-gov-th none" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
                <th class="ems-gov-th none" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
                <th class="ems-gov-th none" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
                <th class="ems-gov-th none" data-gov="cost_center" data-slice="3" title="وجهة التحميل">مركز التكلفة</th>
                <th class="ems-gov-th none" data-gov="fx_rate_source" data-slice="3" title="ما خالف عملة الدفاتر يحمل السعر ومصدره">سعر الصرف ومصدره</th>
                </tr></thead>
            <tbody>
            <?php foreach ($allTerms as $t): ?>
                <tr>
                    <td><?php if ($can_edit): ?>
                        <a href="javascript:void(0)" class="editTerm action-btn edit"
                           data-id="<?php echo intval($t['id']); ?>"
                           data-trigger="<?php echo htmlspecialchars((string)$t['trigger_kind'], ENT_QUOTES); ?>"
                           data-index="<?php echo htmlspecialchars((string)$t['index_code'], ENT_QUOTES); ?>"
                           data-item="<?php echo intval($t['contract_item_id']); ?>"
                           data-base="<?php echo htmlspecialchars((string)$t['base_index'], ENT_QUOTES); ?>"
                           data-basedate="<?php echo htmlspecialchars((string)($t['base_date'] ?? ''), ENT_QUOTES); ?>"
                           data-threshold="<?php echo htmlspecialchars((string)$t['threshold_percent'], ENT_QUOTES); ?>"
                           data-pass="<?php echo htmlspecialchars((string)$t['pass_through_percent'], ENT_QUOTES); ?>"
                           data-cap="<?php echo htmlspecialchars((string)($t['cap_percent'] ?? ''), ENT_QUOTES); ?>"
                           data-period="<?php echo htmlspecialchars((string)$t['periodicity'], ENT_QUOTES); ?>"
                           data-from="<?php echo htmlspecialchars((string)$t['valid_from'], ENT_QUOTES); ?>"
                           data-to="<?php echo htmlspecialchars((string)($t['valid_to'] ?? ''), ENT_QUOTES); ?>"
                           data-note="<?php echo htmlspecialchars((string)($t['note'] ?? ''), ENT_QUOTES); ?>"
                           title="تعديل"><i class="fas fa-edit"></i></a>
                    <?php endif; ?></td>
                    <td><?php echo htmlspecialchars($TRIGGER_LABELS[$t['trigger_kind']] ?? $t['trigger_kind']); ?></td>
                    <td><?php echo htmlspecialchars((string)$t['index_code']); ?></td>
                    <td><?php echo htmlspecialchars((string)$t['base_index']); ?></td>
                    <td><?php echo $t['contract_item_id'] ? ('#' . intval($t['contract_item_id'])) : 'الكل'; ?></td>
                    <td><?php echo htmlspecialchars((string)$t['threshold_percent']); ?>٪</td>
                    <td><?php echo htmlspecialchars((string)$t['pass_through_percent']); ?>٪</td>
                    <td><?php echo $t['cap_percent'] !== null ? htmlspecialchars((string)$t['cap_percent']) . '٪' : '—'; ?></td>
                    <td><?php echo htmlspecialchars($PERIOD_LABELS[$t['periodicity']] ?? $t['periodicity']); ?></td>
                    <td><?php echo htmlspecialchars((string)$t['valid_from']); ?>
                        → <?php echo htmlspecialchars((string)($t['valid_to'] ?? 'مفتوح')); ?></td>
                    <td><?php echo (string)$t['state'] === 'active'
                        ? "<span class='badge badge-success'>نافذ</span>"
                        : "<span class='badge badge-secondary'>منتهٍ</span>"; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div></div></div>

    <?php if ($can_add): ?>
    <div class="card"><div class="card-header"><h5><i class="fa fa-file-import"></i> قراءةُ مؤشرٍ بمرجعها</h5></div>
    <div class="card-body">
        <form method="post" class="ems-form">
            <input type="hidden" name="pt_action" value="add_reading">
            <input type="hidden" name="contract_id" value="<?php echo $selected; ?>">
            <div class="form-grid">
                <div class="form-group"><label>رمز المؤشر <span style="color:#c00">*</span></label>
                    <input type="text" name="index_code" required maxlength="32"></div>
                <div class="form-group"><label>تاريخ القراءة <span style="color:#c00">*</span></label>
                    <input type="date" name="reading_date" required></div>
                <div class="form-group"><label>القيمة <span style="color:#c00">*</span></label>
                    <input type="number" step="0.00000001" min="0.00000001" name="value" required></div>
                <div class="form-group"><label>مرجع المستند <span style="color:#c00">*</span>
                        <small>— رقمٌ بلا مرجعٍ يحرّك مالًا مرفوض</small></label>
                    <input type="text" name="source_ref" required maxlength="160"
                           placeholder="نشرةُ الأسعار الرسمية 2026/07 — ص3"></div>
                <div class="form-group"><label>ملاحظة</label><input type="text" name="reading_note" maxlength="255"></div>
            </div>
            <div style="margin-top:12px"><button type="submit" class="btn-save"><i class="fa fa-save"></i> تسجيل القراءة</button></div>
        </form>
        <div class="table-container" style="margin-top:14px">
            <table class="alltables display nowrap" style="width:100%">
                <thead><tr><th>المؤشر</th><th>تاريخ آخر مراجعة</th><th>القيمة الحالية للمتغير</th><th>المرجع</th></tr></thead>
                <tbody>
                <?php foreach ($readings as $rd): ?>
                    <tr>
                        <td><?php echo htmlspecialchars((string)$rd['index_code']); ?></td>
                        <td><?php echo htmlspecialchars((string)$rd['reading_date']); ?></td>
                        <td><?php echo htmlspecialchars((string)$rd['value']); ?></td>
                        <td><?php echo htmlspecialchars((string)$rd['source_ref']); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div></div>
    <?php endif; ?>

    <div class="card"><div class="card-header"><h5><i class="fa fa-clock-rotate-left"></i> سجلُّ المراجعات</h5></div>
    <div class="card-body">
        <?php if ($can_edit): ?>
        <form method="post" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;margin-bottom:12px">
            <input type="hidden" name="pt_action" value="run_review">
            <input type="hidden" name="contract_id" value="<?php echo $selected; ?>">
            <div><label>تاريخ المراجعة</label>
                <input type="date" name="as_of" value="<?php echo date('Y-m-d'); ?>" required></div>
            <button type="submit" class="btn-save"><i class="fa fa-play"></i> شغّل المراجعة</button>
            <span style="color:#666">تُولّد مراجعةً وملحقًا بسريانه — والاعتمادُ بيدٍ أخرى.</span>
        </form>
        <?php endif; ?>
        <div class="table-container">
            <table class="alltables display nowrap" style="width:100%">
                <thead><tr>
                    <th>الإجراءات</th><th>الدورة</th><th>البند</th><th>المؤشر</th><th>المصدر</th>
                    <th>الفارق</th><th>المطبَّق</th><th>قبل</th><th>بعد</th><th>السريان</th><th>النتيجة</th><th>تاريخ الاعتماد</th>
                </tr></thead>
                <tbody>
                <?php foreach ($revisions as $rv): ?>
                    <tr>
                        <td>
                        <?php if ($can_edit && $rv['approved_at'] === null
                                  && in_array((string)$rv['outcome'], array('amended','capped'), true)): ?>
                            <form method="post" style="display:inline">
                                <input type="hidden" name="pt_action" value="approve_revision">
                                <input type="hidden" name="contract_id" value="<?php echo $selected; ?>">
                                <input type="hidden" name="revision_id" value="<?php echo intval($rv['id']); ?>">
                                <button type="submit" class="action-btn edit" title="اعتماد (من أنشأ لا يعتمد)">
                                    <i class="fas fa-check"></i></button>
                            </form>
                        <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars((string)$rv['period_key']); ?></td>
                        <td>#<?php echo intval($rv['contract_item_id']); ?></td>
                        <td><?php echo $rv['index_value'] !== null ? htmlspecialchars((string)$rv['index_value']) : '—'; ?></td>
                        <td><small><?php echo htmlspecialchars((string)($rv['index_source'] ?? '—')); ?></small></td>
                        <td><?php echo $rv['delta_percent'] !== null ? htmlspecialchars((string)$rv['delta_percent']) . '٪' : '—'; ?></td>
                        <td><?php echo $rv['applied_percent'] !== null ? htmlspecialchars((string)$rv['applied_percent']) . '٪' : '—'; ?></td>
                        <td><?php echo htmlspecialchars((string)($rv['old_price'] ?? '—')); ?></td>
                        <td><strong><?php echo htmlspecialchars((string)($rv['new_price'] ?? '—')); ?></strong></td>
                        <td><?php echo htmlspecialchars((string)$rv['effective_from']); ?></td>
                        <td><?php $oc = (string)$rv['outcome']; ?>
                            <span class="badge <?php echo in_array($oc, array('amended','capped'), true)
                                ? 'badge-success' : 'badge-secondary'; ?>">
                                <?php echo htmlspecialchars($OUTCOME_LABELS[$oc] ?? $oc); ?></span></td>
                        <td><?php echo $rv['approved_at'] !== null
                            ? "<span class='badge badge-success'>معتمَدة</span>"
                            : "<span class='badge badge-warning'>تنتظر</span>"; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div></div>
</div>

<script src="../includes/js/jquery-3.7.1.main.js"></script>
<script>
(function () {
    document.addEventListener('DOMContentLoaded', function () {
        var toggleBtn = document.getElementById('toggleTermForm');
        var form = document.getElementById('termForm');
        if (toggleBtn && form) {
            toggleBtn.addEventListener('click', function () {
                form.reset();
                document.getElementById('f_term_id').value = '';
                form.classList.toggle('allforms-visible');
            });
        }
        document.addEventListener('click', function (ev) {
            var btn = ev.target.closest('.editTerm');
            if (!btn || !form) return;
            var d = btn.dataset;
            document.getElementById('f_term_id').value = d.id;
            document.getElementById('f_trigger').value = d.trigger;
            document.getElementById('f_index').value = d.index;
            document.getElementById('f_item').value = d.item > 0 ? d.item : '';
            document.getElementById('f_base').value = d.base;
            document.getElementById('f_basedate').value = d.basedate || '';
            document.getElementById('f_threshold').value = d.threshold;
            document.getElementById('f_pass').value = d.pass;
            document.getElementById('f_cap').value = d.cap || '';
            document.getElementById('f_period').value = d.period;
            document.getElementById('f_from').value = d.from;
            document.getElementById('f_to').value = d.to || '';
            document.getElementById('f_note').value = d.note || '';
            form.classList.add('allforms-visible');
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    });
})();
</script>
</body>
</html>
