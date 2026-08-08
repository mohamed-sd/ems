<?php
require_once __DIR__ . '/../includes/permissions_helper.php';
/**
 * Approvals/attribution_board.php — لوحةُ الإسناد اليومي (CON-02 §5 · §7-④ · ق-6)
 * ───────────────────────────────────────────────────────────────────────────
 * **نقطةُ القرار** التي تنصّ عليها ق-4: «الكاتبُ يقترح والمشرفُ يعتمد». فما
 * يكتبه الكاتبُ في شاشة الدوام **مقترحٌ** مشتقٌّ من حالة الساعة، وما يعتمده
 * المشرفُ هنا هو **الإسنادُ الذي يقرؤه المال**.
 *
 * وجدولُ §5 مطبَّقًا على وقائع اليوم: لكل سطرِ زمنٍ بندُ التزامه من **مصفوفة
 * العقد النافذة** (لا من قائمةٍ عامةٍ ثابتة)، ومنه تُشتق الأحكامُ الثلاثة.
 * **والزمنُ بلا مسؤولٍ يظهر أحمرَ ويمنع الإقفال** (422).
 *
 * **الملكية:** التشغيل (1) يُسنِد ويعتمد · مديرُ المالية (19) يحسم الاعتراض
 * (ق-25 — نفسُ من يُجيز المصفوفةَ والجزاءات، فلا يُخترع مالكُ قرارٍ جديد).
 *
 * ⚠️ CSRF مركزيٌّ تلقائيّ — لا رموزَ يدويةً هنا.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: ../login.php');
    exit();
}
include '../config.php';
require_once __DIR__ . '/../app/Services/Contract/AttributionService.php';
require_once __DIR__ . '/../includes/obligation_maps.php';

use App\Services\Contract\AttributionService as ATT;

$current_role   = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$company_id     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$uid            = isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0;

if (!$is_super_admin && $company_id <= 0) {
    ems_gov_flash_redirect('../login.php', 'لا توجد بيئة شركة صالحة ❌', 'GOV-SCOPE-403', '');
    exit();
}

$MODULE_CODE = 'Approvals/attribution_board.php';
$can_view = $can_decide = $can_resolve = false;
if ($is_super_admin) {
    $can_view = $can_decide = $can_resolve = true;
} else {
    $st = $conn->prepare("SELECT rp.can_view, rp.can_add, rp.can_edit
                            FROM role_permissions rp JOIN modules m ON m.id = rp.module_id
                           WHERE m.code = ? AND rp.role_id = ? LIMIT 1");
    $rid = intval($current_role);
    $st->bind_param('si', $MODULE_CODE, $rid);
    $st->execute();
    if ($row = $st->get_result()->fetch_assoc()) {
        $can_view    = (intval($row['can_view']) === 1);
        $can_decide  = (intval($row['can_add'])  === 1);  // الإسنادُ والاعتماد — التشغيل
        $can_resolve = (intval($row['can_edit']) === 1);  // حسمُ الاعتراض — المالية
    }
    $st->close();
}
if (!$can_view) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض لوحة الإسناد ❌', 'GOV-PERM-403', '');
    exit();
}

$gate = ems_tenant_db();
$day  = isset($_GET['day']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['day']) ? $_GET['day'] : date('Y-m-d');

if (!function_exists('atb_e')) {
    function atb_e($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('atb_back')) {
    function atb_back($msg, $day) {
        ems_gov_redirect('Location: attribution_board.php?day=' . urlencode($day) . '&msg=' . rawurlencode($msg));
        exit();
    }
}

// ══════════════════════════════════════════════════════════════════════════════
// الإجراءات
// ══════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['atb_action'])) {
    $act = strval($_POST['atb_action']);
    $entryId = intval($_POST['entry_id'] ?? 0);

    if ($act === 'decide') {
        if (!$can_decide) { atb_back('الإسنادُ صلاحيةُ إدارة التشغيل ❌', $day); }
        $assign = array();
        foreach (($_POST['oblig'] ?? array()) as $lineId => $ob) {
            $assign[intval($lineId)] = ($ob === '' ? null : strval($ob));
        }
        $res = ATT::decide($conn, $gate, $company_id, $entryId, $assign, $uid);
        atb_back($res['ok']
            ? ('اعتُمد إسنادُ ' . $res['decided'] . ' سطرًا — والأحكامُ الثلاثةُ مخزَّنةٌ لقطةً ✅')
            : (implode(' · ', $res['reasons']) . ' ❌'), $day);

    } elseif ($act === 'object') {
        if (!$can_decide) { atb_back('لا توجد صلاحية الاعتراض ❌', $day); }
        $res = ATT::object($conn, $gate, $company_id, intval($_POST['line_id'] ?? 0),
                           strval($_POST['reason'] ?? ''), strval($_POST['ref'] ?? ''), $uid);
        atb_back($res['ok'] ? 'سُجّل الاعتراضُ — والبكتاتُ الأخرى تمضي ✅'
                            : (implode(' · ', $res['reasons']) . ' ❌'), $day);

    } elseif ($act === 'rule_qty') {
        // M-24 ①: حكمُ **الكمية** لا الزمن — «إعادةُ التنفيذ لعيبٍ لا تُفوتر».
        // صلاحيةُ الإسناد نفسُها: مَن يسند الزمنَ يحكم الكمية (كلاهما قرارُ
        // تشغيلٍ على الواقعة نفسِها، وفصلُهما يخلق يدًا ثالثةً بلا داعٍ).
        if (!$can_decide) { atb_back('حكمُ الكمية صلاحيةُ إدارة التشغيل ❌', $day); }
        $qb  = strval($_POST['qty_billable'] ?? '');
        $qbV = ($qb === '') ? null : intval($qb);
        $res = ATT::ruleQty($conn, $gate, $company_id, $entryId, $qbV,
                            strval($_POST['qty_note'] ?? ''), $uid);
        if (!$res['ok']) { atb_back(implode(' · ', $res['reasons']) . ' ❌', $day); }
        atb_back(empty($res['changed'])
            ? 'الحكمُ كما هو — لا تغيير ✅'
            : ($qbV === 0 ? 'حُكم بعدم فوترة الكمية بسببها المكتوب ✅'
                          : ($qbV === 1 ? 'حُكم بفوترة الكمية صراحةً ✅' : 'رُفع حكمُ الكمية ✅')), $day);

    } elseif ($act === 'resolve') {
        if (!$can_resolve) { atb_back('حسمُ الاعتراض صلاحيةُ مدير الإدارة المالية (ق-25) ❌', $day); }
        $res = ATT::resolve($conn, $gate, $company_id, intval($_POST['line_id'] ?? 0),
                            strval($_POST['new_oblig'] ?? ''), $uid);
        atb_back($res['ok']
            ? (!empty($res['overridden']) ? 'حُسم الاعتراضُ بتغيير البند — تجاوزٌ مسجَّل ✅' : 'حُسم الاعتراض ✅')
            : (implode(' · ', $res['reasons']) . ' ❌'), $day);
    }
    atb_back('إجراءٌ غير معروف ❌', $day);
}

// ══════════════════════════════════════════════════════════════════════════════
// وقائعُ اليوم
// ══════════════════════════════════════════════════════════════════════════════
$entries = array();
try {
    $entries = $gate->scopedQuery(array(
        'scope'  => array('e' => 'unit_entries'),
        'enrich' => array('p' => 'project', 'q' => 'equipments'),
    ), "SELECT e.id, e.entry_no, e.entry_date, e.contract_id, e.state, e.unit_type, e.qty,
               e.qty_billable, e.qty_ruling_note,
               p.name AS project_name, q.name AS equipment_name
          FROM unit_entries e
          LEFT JOIN project p ON p.id = e.project_id
          LEFT JOIN equipments q ON q.id = e.equipment_id
         WHERE {TENANT_SCOPE} AND e.entry_date = ?
         ORDER BY e.id ASC", array($day));
} catch (\Throwable $t) {
    $entries = array();
    error_log('attribution_board entries: ' . $t->getMessage());
}

// سطورُ الزمن لكل واقعة + المصفوفةُ النافذةُ لعقدها
$lines = array(); $matrices = array(); $blocked = 0; $objected = 0;
if (!empty($entries)) {
    $ids = array();
    foreach ($entries as $e) { $ids[] = intval($e['id']); }
    $in = implode(',', array_map('intval', $ids));
    try {
        $rows = $gate->scopedQuery(array('scope' => array('l' => 'unit_time_log')),
            "SELECT l.id, l.entry_id, l.ops_state, l.hours, l.resp_party, l.obligation_type,
                    l.billable, l.supplier_countable, l.operator_countable,
                    l.objection_state, l.objection_reason, l.objection_ref,
                    l.decided_at, l.time_from, l.time_to
               FROM unit_time_log l
              WHERE {TENANT_SCOPE} AND l.entry_id IN ({$in})
              ORDER BY l.entry_id, l.id");
    } catch (\Throwable $t) { $rows = array(); }
    foreach ($rows as $r) { $lines[intval($r['entry_id'])][] = $r; }

    foreach ($entries as $e) {
        $cid = intval($e['contract_id']);
        if ($cid > 0 && !isset($matrices[$cid])) {
            try { $matrices[$cid] = ATT::matrixFor($gate, $cid, $day); }
            catch (\Throwable $t) { $matrices[$cid] = array(); }
        }
        foreach (($lines[intval($e['id'])] ?? array()) as $l) {
            if ($l['ops_state'] !== 'actual_work' && (float) $l['hours'] > 0
                && ($l['obligation_type'] === null || $l['obligation_type'] === '')) { $blocked++; }
            if ($l['objection_state'] === 'objected') { $objected++; }
        }
    }
}

$OPS_LABELS = array(
    'actual_work' => 'تشغيلٌ فعلي', 'standby' => 'استعداد', 'tech_breakdown' => 'عطلٌ فني',
    'supplier_stop' => 'توقفٌ على المورد', 'operator_stop' => 'توقفٌ على المشغّل',
    'client_stop' => 'توقفٌ على العميل', 'fuel_logistics_stop' => 'وقود/لوجستيات',
    'planned_stop' => 'توقفٌ مخطط', 'force_majeure' => 'قوةٌ قاهرة', 'unlogged' => 'غيرُ مصنّف',
);
$RESP_LABELS = array('company' => 'الشركة', 'supplier' => 'المورد', 'operator' => 'المشغّل',
                     'client' => 'العميل', 'planned' => 'مخطط', 'force_majeure' => 'قاهرة', 'none' => '—');

function atb_flag($v) {
    if ($v === null) { return '<span class="atb-u">؟</span>'; }
    return ((int) $v === 1) ? '<span class="atb-y">نعم</span>' : '<span class="atb-n">لا</span>';
}

$page_title = 'لوحة الإسناد اليومي';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include('../inheader.php');
include('../insidebar.php');
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>

<div class="main atb-main ems-unified-page-shell">
    <?php
    $header_title = 'لوحة الإسناد اليومي';
    $header_icon = 'fa fa-scale-unbalanced';
    $header_actions = array();
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fa-solid fa-share', 'label' => '');
    include('../includes/page_header.php');
    ?>

    <?php if (!empty($_GET['msg'])):
        $isSuccess = strpos($_GET['msg'], '✅') !== false; ?>
        <div class="success-message <?= $isSuccess ? 'is-success' : 'is-error' ?>">
            <i class="fas <?= $isSuccess ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?php echo atb_e($_GET['msg']); ?>
        </div>
    <?php endif; ?>

    <div class="atb-rule">
        <i class="fas fa-circle-info"></i>
        <div>
            <strong>الكاتبُ يقترح والمشرفُ يعتمد.</strong>
            «المسؤولُ المقترح» مشتقٌّ آليًّا من حالة الساعة — وهو <em>اقتراحٌ لا قرار</em>.
            القرارُ أن تُسنِد كلَّ فترةِ توقفٍ إلى <em>بندِ التزامٍ من مصفوفة العقد</em>،
            ومنه تُشتق الأحكامُ الثلاثة (فوترة · مورد · مشغّل) وتُخزَّن لقطةً لا تتغيّر بملحقٍ لاحق.
        </div>
    </div>

    <div class="filter">
        <div class="filter-title"><span class="filter-title-icon"><i class="fa-solid fa-calendar-day"></i></span> يومُ العمل</div>
        <div class="filter-body">
            <form method="get" action="">
                <div class="filter-field">
                    <label><i class="fa fa-calendar"></i> التاريخ</label>
                    <input type="date" name="day" value="<?php echo atb_e($day); ?>" onchange="this.form.submit()" class="form-control">
                </div>
            </form>
        </div>
    </div>

    <div class="atb-summary <?php echo $blocked > 0 ? 'is-blocked' : 'is-clear'; ?>">
        <div><i class="fas <?php echo $blocked > 0 ? 'fa-ban' : 'fa-circle-check'; ?>"></i>
            <strong><?php echo count($entries); ?></strong> واقعةً في <?php echo atb_e($day); ?>
        </div>
        <?php if ($blocked > 0): ?>
            <span class="atb-chip atb-chip-red"><?php echo $blocked; ?> فترةَ توقفٍ بلا بندٍ مُسنَد — تمنع الإقفال</span>
        <?php else: ?>
            <span class="atb-chip atb-chip-green">لا زمنَ توقفٍ بلا مسؤول</span>
        <?php endif; ?>
        <?php if ($objected > 0): ?>
            <span class="atb-chip atb-chip-amber"><?php echo $objected; ?> اعتراضًا مفتوحًا — يحسمه مديرُ المالية</span>
        <?php endif; ?>
        <span class="atb-chip <?php echo ATT::enforced() ? 'atb-chip-green' : 'atb-chip-grey'; ?>">
            الحارس: <?php echo ATT::enforced() ? 'مُفعَّل (422/423)' : 'رصدٌ فقط — لم يُقلب العَلَم بعد'; ?>
        </span>
    </div>

    <?php if (empty($entries)): ?>
        <div class="card"><div class="card-body atb-empty">لا وقائعَ في هذا اليوم.</div></div>
    <?php endif; ?>

    <?php foreach ($entries as $e):
        $eid = intval($e['id']);
        $cid = intval($e['contract_id']);
        $mx = $matrices[$cid] ?? array();
        $myLines = $lines[$eid] ?? array();
        $noMatrix = ($cid <= 0 || empty($mx)); ?>
        <div class="card atb-entry">
            <div class="card-header atb-entry-head">
                <h5><i class="fas fa-clipboard-list"></i>
                    <?php echo atb_e($e['entry_no']); ?> — <?php echo atb_e($e['project_name']); ?>
                    <span class="atb-muted">· <?php echo atb_e($e['equipment_name']); ?></span>
                </h5>
                <span class="atb-state"><?php echo atb_e($e['state']); ?></span>
            </div>
            <div class="card-body">
                <?php if ($noMatrix): ?>
                    <div class="atb-423">
                        <i class="fas fa-lock"></i>
                        <strong>عقدٌ بلا مصفوفةٍ مُجازة (423).</strong>
                        <?php if ($cid > 0): ?>
                            العقد #<?php echo $cid; ?> لا مصفوفةَ نافذةً له بتاريخ <?php echo atb_e($day); ?>.
                            تُملأ من <a href="../Contracts/contract_obligations.php?contract=<?php echo $cid; ?>">شاشة مصفوفة الالتزامات</a>
                            وتُجيزها المالية — ولا ارتدادَ للسياسة الافتراضية (ق-2).
                        <?php else: ?>
                            الواقعةُ بلا عقدٍ مرتبط، فلا مصفوفةَ تُقرأ منها المسؤولية.
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php
                // ── M-24 ①: حكمُ الكمية — لعقود الطن/المتر وحدَها ─────────────
                // عقدُ الساعة كميتُه **هي** ساعاتُه، وهي محكومةٌ سطرًا سطرًا في
                // الجدول أدناه — فسؤالُ «هل الكميةُ مفوترة؟» لا معنى له فيه.
                // ولذلك لا يُعرض إلا حيث يعني شيئًا.
                $isQtyUnit = in_array((string) $e['unit_type'], array('ton', 'meter', 'cbm', 'trip'), true);
                if ($isQtyUnit):
                    $qb = ($e['qty_billable'] === null) ? '' : (string) intval($e['qty_billable']);
                ?>
                <div class="atb-qty-rule">
                    <div class="atb-qty-head">
                        <i class="fas fa-cubes"></i>
                        <strong>كميةُ الواقعة:</strong>
                        <?php echo atb_e(rtrim(rtrim((string) $e['qty'], '0'), '.')); ?>
                        <?php echo atb_e($e['unit_type']); ?>
                        <?php if ($qb === '0'): ?>
                            <span class="atb-chip atb-chip-red">غيرُ مفوترة</span>
                        <?php elseif ($qb === '1'): ?>
                            <span class="atb-chip atb-chip-green">مفوترةٌ صراحةً</span>
                        <?php else: ?>
                            <span class="atb-chip atb-chip-grey">مفوترة (لم يُحكم)</span>
                        <?php endif; ?>
                    </div>
                    <p class="atb-small" style="margin:6px 0 8px">
                        سؤالان مستقلّان: هذا عن <strong>الكمية نفسِها</strong>
                        (إعادةُ تنفيذٍ لعيبٍ لا تُفوتر)، والجدولُ أدناه عن
                        <strong>زمن التوقف</strong>. حكمُ أحدهما لا يغني عن الآخر.
                        <?php if ($e['qty_ruling_note'] !== null && $e['qty_ruling_note'] !== ''): ?>
                            <br><i class="fas fa-quote-right"></i>
                            <em><?php echo atb_e($e['qty_ruling_note']); ?></em>
                        <?php endif; ?>
                    </p>
                    <?php if ($can_decide): ?>
                    <form method="post" action="" class="atb-qty-form">
                        <input type="hidden" name="atb_action" value="rule_qty">
                        <input type="hidden" name="entry_id" value="<?php echo $eid; ?>">
                        <select name="qty_billable" class="atb-qty-select">
                            <option value=""  <?php echo $qb === ''  ? 'selected' : ''; ?>>لم يُحكم — تُفوتر</option>
                            <option value="1" <?php echo $qb === '1' ? 'selected' : ''; ?>>تُفوتر صراحةً</option>
                            <option value="0" <?php echo $qb === '0' ? 'selected' : ''; ?>>لا تُفوتر (إعادةُ تنفيذٍ لعيب)</option>
                        </select>
                        <input type="text" name="qty_note" maxlength="200"
                               placeholder="السبب — إلزامٌ عند المنع"
                               value="<?php echo atb_e((string) $e['qty_ruling_note']); ?>">
                        <button type="submit" class="btn btn-sm btn-primary">احكم على الكمية</button>
                    </form>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <form method="post" action="">
                    <input type="hidden" name="atb_action" value="decide">
                    <input type="hidden" name="entry_id" value="<?php echo $eid; ?>">
                    <div class="table-container">
                        <table class="display atb-table no-datatable">
                            <thead>
                                <tr>
                                    <th>الفترة</th>
                                    <th>الحالة</th>
                                    <th>الساعات</th>
                                    <th>المسؤولُ المقترح</th>
                                    <th>بندُ الالتزام (من العقد)</th>
                                    <th>الملتزم</th>
                                    <th>يُفوتر</th>
                                    <th>للمورد</th>
                                    <th>للمشغّل</th>
                                    <th>الاعتراض</th>
                                    <!-- E-03 موجة ٤: النواة الحاكمة (gov_columns) — الخلايا يحشوها ui-unification.js -->
                                    <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
                                    <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
                                    <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
                                    <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
                                    <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
                                    <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
                                    <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
                                    </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($myLines as $l):
                                $lid = intval($l['id']);
                                $isWork = ($l['ops_state'] === 'actual_work');
                                $ob = $l['obligation_type'];
                                $needs = (!$isWork && (float) $l['hours'] > 0 && ($ob === null || $ob === ''));
                                $obligor = ($ob !== null && isset($mx[$ob])) ? $mx[$ob]['obligor'] : null; ?>
                                <tr class="<?php echo $needs ? 'atb-row-missing' : ''; ?>">
                                    <td class="atb-small"><?php echo atb_e(substr((string) $l['time_from'], 0, 5) . '–' . substr((string) $l['time_to'], 0, 5)); ?></td>
                                    <td><?php echo atb_e($OPS_LABELS[$l['ops_state']] ?? $l['ops_state']); ?></td>
                                    <td class="atb-num"><?php echo rtrim(rtrim((string) $l['hours'], '0'), '.'); ?></td>
                                    <td class="atb-small atb-muted"><?php echo atb_e($RESP_LABELS[$l['resp_party']] ?? $l['resp_party']); ?></td>
                                    <td>
                                        <?php if ($isWork): ?>
                                            <span class="atb-muted">— لا بندَ للتشغيل الفعلي</span>
                                        <?php elseif ($can_decide && !$noMatrix): ?>
                                            <select name="oblig[<?php echo $lid; ?>]" class="atb-select <?php echo $needs ? 'is-missing' : ''; ?>">
                                                <option value="">— اختر البند —</option>
                                                <?php foreach ($mx as $k => $m): ?>
                                                    <option value="<?php echo atb_e($k); ?>" <?php echo $k === $ob ? 'selected' : ''; ?>>
                                                        <?php echo atb_e($OBL_TYPES[$k] ?? $k); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        <?php else: ?>
                                            <?php echo $ob ? atb_e($OBL_TYPES[$ob] ?? $ob) : '<span class="atb-missing">بلا بندٍ مُسنَد</span>'; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $obligor ? atb_e($OBL_OBLIGORS[$obligor] ?? $obligor) : '<span class="atb-muted">—</span>'; ?></td>
                                    <td><?php echo $isWork ? '<span class="atb-y">نعم</span>' : atb_flag($l['billable']); ?></td>
                                    <td><?php echo $isWork ? '<span class="atb-y">نعم</span>' : atb_flag($l['supplier_countable']); ?></td>
                                    <td><?php echo $isWork ? '<span class="atb-y">نعم</span>' : atb_flag($l['operator_countable']); ?></td>
                                    <td class="atb-small">
                                        <?php if ($l['objection_state'] === 'objected'): ?>
                                            <span class="atb-chip atb-chip-amber" title="<?php echo atb_e($l['objection_reason']); ?>">
                                                معترَضٌ عليه</span>
                                            <?php if ($can_resolve): ?>
                                                <button type="button" class="atb-link atb-resolve"
                                                        data-line="<?php echo $lid; ?>"
                                                        data-reason="<?php echo atb_e($l['objection_reason']); ?>"
                                                        data-ref="<?php echo atb_e($l['objection_ref']); ?>">حسم</button>
                                            <?php endif; ?>
                                        <?php elseif ($l['objection_state'] === 'resolved'): ?>
                                            <span class="atb-muted">حُسم</span>
                                        <?php elseif ($can_decide && !$isWork && $ob): ?>
                                            <button type="button" class="atb-link atb-object" data-line="<?php echo $lid; ?>">اعتراض</button>
                                        <?php else: ?>
                                            <span class="atb-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($can_decide && !$noMatrix): ?>
                        <div class="pu-form-actions atb-actions">
                            <button type="submit" class="btn-submit"><i class="fas fa-stamp"></i> اعتماد الإسناد</button>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    <?php endforeach; ?>

    <!-- نموذجُ الاعتراض والحسم — POST عاديٌّ لا AJAX (فلا حاجةَ لتسجيلٍ في action_guard) -->
    <div id="atbModal" class="atb-modal" hidden>
        <form method="post" action="" class="atb-modal-card">
            <input type="hidden" name="atb_action" id="atbAct" value="object">
            <input type="hidden" name="line_id" id="atbLine" value="">
            <h5 id="atbTitle"><i class="fas fa-flag"></i> اعتراضٌ على الإسناد</h5>
            <div id="atbObjFields">
                <label>سببُ الاعتراض *</label>
                <textarea name="reason" id="atbReason" rows="3" maxlength="255" placeholder="لماذا تعترض على هذا الإسناد؟"></textarea>
                <label>المرجع (محضر / مستند)</label>
                <input type="text" name="ref" id="atbRef" maxlength="60" placeholder="مثال: محضر-2026-07-15">
            </div>
            <div id="atbResFields" hidden>
                <div class="atb-res-note"></div>
                <label>البندُ بعد الحسم (اتركه كما هو للإبقاء عليه)</label>
                <?php
                // ⚠️ الخياراتُ من **مصفوفات عقود اليوم** لا من البنود التسعة كلِّها:
                //    قائمةٌ تعرض بندًا خارجَ العقد تدعو إلى 422 لا لزومَ له. والخدمةُ
                //    تتحقق ثانيةً على الخادم — الشاشةُ لا تكون الحارسَ الوحيد.
                $allowed = array();
                foreach ($matrices as $mx1) { foreach ($mx1 as $k1 => $v1) { $allowed[$k1] = true; } }
                ?>
                <select name="new_oblig" id="atbNewOblig">
                    <option value="">— بلا تغيير —</option>
                    <?php foreach (array_keys($allowed) as $k): ?>
                        <option value="<?php echo atb_e($k); ?>"><?php echo atb_e($OBL_TYPES[$k] ?? $k); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="atb-modal-actions">
                <button type="submit" class="btn-submit"><i class="fas fa-check"></i> تأكيد</button>
                <button type="button" class="btn-cancel" id="atbCancel"><i class="fas fa-times"></i> إلغاء</button>
            </div>
        </form>
    </div>
</div>

<script src="../includes/js/jquery-3.7.1.main.js"></script>
<script>
$(function () {
    const modal = $('#atbModal');
    function open(mode, lineId, reason, ref) {
        $('#atbLine').val(lineId);
        $('#atbAct').val(mode);
        if (mode === 'object') {
            $('#atbTitle').html('<i class="fas fa-flag"></i> اعتراضٌ على الإسناد');
            $('#atbObjFields').prop('hidden', false); $('#atbResFields').prop('hidden', true);
            $('#atbReason').val('').prop('required', true); $('#atbRef').val('');
        } else {
            $('#atbTitle').html('<i class="fas fa-gavel"></i> حسمُ الاعتراض');
            $('#atbObjFields').prop('hidden', true); $('#atbResFields').prop('hidden', false);
            $('#atbReason').prop('required', false);
            $('.atb-res-note').text('الاعتراض: ' + (reason || '—') + (ref ? ' · المرجع: ' + ref : ''));
        }
        modal.prop('hidden', false);
    }
    $(document).on('click', '.atb-object', function () { open('object', $(this).data('line')); });
    $(document).on('click', '.atb-resolve', function () {
        open('resolve', $(this).data('line'), $(this).data('reason'), $(this).data('ref'));
    });
    $('#atbCancel').on('click', function () { modal.prop('hidden', true); });
    modal.on('click', function (e) { if (e.target === this) { modal.prop('hidden', true); } });
});
</script>

<style>
    .atb-main .atb-rule { display:flex; gap:12px; align-items:flex-start; border:1px solid #90caf9;
        border-right:5px solid #1976d2; border-radius:10px; background:#f2f8ff; padding:12px 14px; margin-bottom:14px; line-height:1.7; }
    .atb-main .atb-rule i { color:#1976d2; font-size:1.25rem; margin-top:3px; }
    .atb-main .atb-summary { display:flex; gap:12px; align-items:center; flex-wrap:wrap;
        border:1px solid var(--bdr); border-radius:12px; padding:12px 14px; margin-bottom:14px; background:#fff; }
    .atb-main .atb-summary.is-blocked { border-color:#c62828; background:#fff5f5; }
    .atb-main .atb-summary.is-clear { border-color:#2e7d32; background:#f4fbf4; }
    .atb-main .atb-chip { display:inline-block; padding:3px 10px; border-radius:20px; font-size:.85rem; font-weight:700; }
    .atb-main .atb-chip-red { background:#ffebee; color:#c62828; border:1px solid #ef9a9a; }
    .atb-main .atb-chip-green { background:#e8f5e9; color:#2e7d32; border:1px solid #a5d6a7; }
    .atb-main .atb-chip-amber { background:#fff8e1; color:#a06b00; border:1px solid #ffe082; }
    .atb-main .atb-chip-grey { background:#eceff1; color:#546e7a; border:1px solid #b0bec5; }
    .atb-main .atb-entry { margin-bottom:14px; }
    .atb-main .atb-entry-head { display:flex; justify-content:space-between; align-items:center; }
    .atb-main .atb-state { font-size:.82rem; color:#666; background:#f1f1f1; padding:2px 10px; border-radius:12px; }
    .atb-main .atb-423 { display:flex; gap:10px; align-items:flex-start; border:1px solid #c62828;
        border-right:5px solid #c62828; border-radius:10px; background:#fff5f5; padding:11px 13px; margin-bottom:12px; line-height:1.7; }
    .atb-main .atb-423 i { color:#c62828; margin-top:3px; }
    .atb-main .atb-row-missing { background:#fff5f5; }
    .atb-main .atb-select.is-missing { border-color:#c62828; box-shadow:0 0 0 2px rgba(198,40,40,.12); }
    .atb-main .atb-missing { color:#c62828; font-weight:700; }
    .atb-main .atb-y { color:#2e7d32; font-weight:700; }
    .atb-main .atb-n { color:#c62828; font-weight:700; }
    .atb-main .atb-u { color:#999; font-weight:700; }
    .atb-main .atb-num { font-variant-numeric:tabular-nums; font-weight:700; }
    .atb-main .atb-small { font-size:.84rem; }
    .atb-main .atb-muted { color:#999; }
    .atb-main .atb-empty { text-align:center; color:#888; padding:22px; }
    .atb-main .atb-link { background:none; border:none; color:#1976d2; cursor:pointer; text-decoration:underline; font-size:.84rem; padding:0 4px; }
    /* M-24 ①: لوحُ حكم الكمية — مفصولٌ بصريًّا عن جدول الزمن لأن السؤالين مختلفان */
    .atb-main .atb-qty-rule { background:#fffbeb; border:1px solid #fde68a; border-radius:8px;
                              padding:10px 12px; margin-bottom:12px; }
    .atb-main .atb-qty-head { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
    .atb-main .atb-qty-form { display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
    .atb-main .atb-qty-form input[type=text] { flex:1 1 260px; min-width:200px; padding:4px 8px; }
    .atb-main .atb-qty-select { padding:4px 8px; }
    .atb-main .atb-actions { margin-top:10px; }
    .atb-main .table-container { overflow-x:auto; }
    .atb-modal { position:fixed; inset:0; background:rgba(0,0,0,.45); display:flex; align-items:center;
        justify-content:center; z-index:9999; }
    .atb-modal[hidden] { display:none; }
    .atb-modal-card { background:#fff; border-radius:14px; padding:20px; width:min(520px,92vw); box-shadow:0 10px 40px rgba(0,0,0,.3); }
    .atb-modal-card label { display:block; margin:10px 0 4px; font-weight:700; }
    .atb-modal-card textarea, .atb-modal-card input, .atb-modal-card select { width:100%; }
    .atb-modal-actions { display:flex; gap:10px; margin-top:16px; }
    .atb-res-note { background:#fff8e1; border:1px solid #ffe082; border-radius:8px; padding:8px 10px; font-size:.9rem; }
</style>

</body>

</html>
