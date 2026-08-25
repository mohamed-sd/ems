<?php
/**
 * Finance/entitlement.php — توليدُ المستحق من العمل المعتمد (M-10 · الشاشة ٢)
 * ─────────────────────────────────────────────────────────────────────────
 * «إيرادُ العميل وذمةُ المورد وأجرُ المشغّل تتولد من واقعةٍ واحدةٍ بأحكامٍ
 * ثلاثةٍ مستقلة — ولا أثرَ قبل هذه اللحظة» (§7-2 fin.entitle).
 * البوابةُ الرباعيةُ تسبق التوليدَ حتمًا (gate.pass) — وإخفاقُ فحصٍ يردُّ
 * الواقعةَ بسببٍ محكوم. والتوليدُ عبر محرّك المروحة القائم (EffectFanout):
 * لا مكوّنَ جديدًا لوظيفةٍ قائمة — والمتخطَّى يُعلَن بسببِه ولا يُلفَّق رقم.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }
include '../config.php';
require_once __DIR__ . '/../includes/permissions_helper.php';
require_once __DIR__ . '/../includes/screen_contract.php';

$current_role   = strval($_SESSION['user']['role'] ?? '');
$is_super_admin = ($current_role === '-1');
$company_id     = intval($_SESSION['user']['company_id'] ?? 0);
if (!$is_super_admin && $company_id <= 0) { header('Location: ../login.php'); exit(); }

$MODULE_CODE = 'Finance/entitlement.php';
$__pp = check_page_permissions($conn, $MODULE_CODE);
$can_view = $is_super_admin || !empty($__pp['can_view']);
$can_generate = $is_super_admin || !empty($__pp['can_add']) || !empty($__pp['can_edit']);
if (!$can_view) {
    // GOV-403 داخل الشاشة لا في الرابط (UI-DEF-06)
    $_SESSION['ems_gov_flash'] = 'FIN-PERM-403: لا صلاحية عرض لتوليد المستحق — تطلب من الحوكمة';
    header('Location: ../main/dashboard.php');
    exit();
}
ems_shell_axes($__pp);

$fPeriod = isset($_GET['period']) && preg_match('/^\d{4}-\d{2}$/', $_GET['period'])
    ? $_GET['period'] : date('Y-m');

/* ① الوقائعُ المعتمدةُ المرشَّحةُ للتوليد — سلسلتُها اكتملت ولم يُولَّد محضرُها */
$pending = array();
$res = $conn->query("SELECT ur.id, ur.record_no, ur.record_date, ur.work_model,
                            ur.approved_qty, ur.client_unit_price, ur.supplier_unit_price,
                            ur.project_id, ur.supplier_entity_id, ur.equipment_id
                       FROM fin_unit_records ur
                      WHERE ur.company_id = {$company_id}
                        AND ur.match_state = 'approved' AND COALESCE(ur.is_deleted,0) = 0
                        AND ur.approved_qty > 0
                        AND DATE_FORMAT(ur.record_date, '%Y-%m') = '" . $conn->real_escape_string($fPeriod) . "'
                        AND NOT EXISTS (SELECT 1 FROM fin_entitlements e
                                         WHERE e.company_id = ur.company_id
                                           AND e.unit_record_id = ur.id)
                      ORDER BY ur.record_date DESC, ur.id DESC LIMIT 300");
if ($res) { while ($x = $res->fetch_assoc()) { $pending[] = $x; } }

/* ② محاضرُ التوليد المولَّدة — السجلُّ المؤسسيُّ الكامل (لا يُختزل) */
$generated = array();
$res = $conn->query("SELECT e.*, ur.record_no, u.name generator_name, jt.name generator_title
                       FROM fin_entitlements e
                       LEFT JOIN fin_unit_records ur ON ur.id = e.unit_record_id
                       LEFT JOIN users u ON u.id = e.generated_by
                       LEFT JOIN employees emp ON emp.id = u.employee_id
                       LEFT JOIN job_titles jt ON jt.id = emp.job_title_id
                      WHERE e.company_id = {$company_id}
                        AND e.period = '" . $conn->real_escape_string($fPeriod) . "'
                      ORDER BY e.id DESC LIMIT 500");
if ($res) { while ($x = $res->fetch_assoc()) { $generated[] = $x; } }

/* ③ آخرُ محاضرِ البوابة المردودة — السببُ المحكومُ ظاهرٌ لا مدفون */
$rejects = array();
$res = $conn->query("SELECT g.gate_code, g.unit_record_id, g.reject_code, g.created_at, ur.record_no
                       FROM fin_entitlement_gate_log g
                       LEFT JOIN fin_unit_records ur ON ur.id = g.unit_record_id
                      WHERE g.company_id = {$company_id} AND g.result = 'reject'
                        AND g.period = '" . $conn->real_escape_string($fPeriod) . "'
                      ORDER BY g.id DESC LIMIT 100");
if ($res) { while ($x = $res->fetch_assoc()) { $rejects[] = $x; } }

$sumClient = 0.0;
foreach ($generated as $g) { $sumClient += (float) ($g['client_amount'] ?? 0); }

$page_title = 'إيكوبيشن | توليد المستحق من العمل المعتمد';
include '../inheader.php';
include '../insidebar.php';
if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'توليد المستحق من العمل المعتمد';
    $header_icon = 'fas fa-diagram-project';
    $header_actions = array();
    $header_back = array();
    $header_context = array(
        'الفترة' => $fPeriod,
        'وقائعُ تنتظر التوليد' => count($pending),
        'محاضرُ الفترة' => count($generated),
        'مردودةٌ بالبوابة' => count($rejects),
        'إيرادُ العميل المولَّد' => number_format($sumClient, 2),
    );
    include('../includes/page_header.php');
    ems_screen_about(
        'واقعة واحدة اجتازت سلسلتها تولد ثلاثة آثار بأحكام مستقلة: إيراد العميل وذمة المورد '
        . 'وأجر المشغل — عبر البوابة الرباعية ثم محرك المروحة. ولا أثر ماليا بإدخال مباشر (PR-04).',
        array('افحص البوابة أولا — الرد بسبب محكوم يقود لصاحب الحلقة الناقصة',
              'المتخطى في المروحة يعلن بسببه («لا سعر عقد») ولا يخترع له رقم',
              'إعادة التوليد ترجع المحضر الأول — العطالة بنيوية (AR-04)'));
    // UXW-01 ⑨: حالاتُ الشاشةِ الثلاثُ من المكوّنِ المركزيّ
    echo ems_states_bundle('لا وقائع ولا محاضر لهذه الفترة', 'غير الفترة أو تحقق من اعتماد الوقائع');
    ?>
    <?php /* نُقلت أنماطُ هذه الشاشةِ إلى assets/css/ems-screens.css (UXUI-01 البند ٦: صفرُ نمطٍ محليّ) */ ?>

    <form method="get" class="ems-toolbar ent-toolbar-filter">
        <label class="ent-filter-label" for="ent_period_input">الفترة
            <input type="month" name="period" id="ent_period_input" class="form-control form-control-sm" value="<?php echo htmlspecialchars($fPeriod); ?>">
        </label>
        <button class="ems-btn-primary" type="submit">عرض</button>
    </form>

    <div class="card"><div class="card-body table-responsive">
        <h6>وقائع معتمدة تنتظر التوليد (<?php echo count($pending); ?>)</h6>
        <?php if (!$pending): ?>
            <?php if (function_exists('ems_state_empty')) { ems_state_empty('لا وقائع معتمدة بلا محضر في هذه الفترة — المروحة مكتملة ✨'); } else { echo '<p class="ent-dim">لا وقائع.</p>'; } ?>
        <?php else: ?>
        <table class="table table-sm table-striped ent-w100" data-no-dt="1">
            <thead><tr>
                <th>الواقعة</th><th>التاريخ</th><th>النموذج</th><th>الكمية المعتمدة</th>
                <th>حكم العميل</th><th>حكم المورد</th><th></th>
            </tr></thead>
            <tbody>
            <?php foreach ($pending as $x): ?>
                <tr id="unit-row-<?php echo (int) $x['id']; ?>">
                    <td class="ent-mono"><?php echo htmlspecialchars($x['record_no']); ?></td>
                    <td><?php echo htmlspecialchars($x['record_date']); ?></td>
                    <td><?php echo htmlspecialchars($x['work_model']); ?></td>
                    <td><?php echo htmlspecialchars((string) $x['approved_qty']); ?></td>
                    <td class="ent-fs78"><?php echo $x['client_unit_price'] !== null ? 'سعر متاح — يفوتر' : '<span class="ent-reject-note">لا سعر عميل — سيتخطى معلنا</span>'; ?></td>
                    <td class="ent-fs78"><?php
                        if (empty($x['supplier_entity_id'])) { echo 'معدة مملوكة — لا مورد'; }
                        else { echo $x['supplier_unit_price'] !== null ? 'سعر متاح — يستحق' : '<span class="ent-reject-note">لا سعر مورد</span>'; }
                    ?></td>
                    <td class="ent-nowrap">
                        <?php if ($can_generate): ?>
                        <button class="ems-btn-secondary" onclick="m10Gate(<?php echo (int) $x['id']; ?>)">فحص البوابة</button>
                        <button class="ems-btn-primary" onclick="m10Entitle(<?php echo (int) $x['id']; ?>)">توليد المحضر</button>
                        <?php else: ?><span class="ent-readonly-hint">قراءة — التوليد للمخول</span><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div></div>

    <?php if ($rejects): ?>
    <div class="ems-card ent-reject-card">
        <strong class="ent-reject-title">وقائع ردتها البوابة الرباعية — <?php echo count($rejects); ?></strong>
        <table class="table table-sm ent-mt6">
            <thead><tr><th>البوابة</th><th>الواقعة</th><th>السبب المحكوم</th><th>الوقت</th></tr></thead>
            <tbody>
            <?php foreach ($rejects as $x): ?>
                <tr>
                    <td class="ent-mono"><?php echo htmlspecialchars($x['gate_code']); ?></td>
                    <td class="ent-mono"><?php echo htmlspecialchars((string) $x['record_no']); ?></td>
                    <td><span class="badge badge-danger"><?php echo htmlspecialchars($x['reject_code']); ?></span></td>
                    <td><small><?php echo htmlspecialchars($x['created_at']); ?></small></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <div class="card ent-mt12"><div class="card-body table-responsive">
        <h6>محاضر التوليد — السجل الكامل (<?php echo count($generated); ?>)</h6>
        <?php if (!$generated): ?>
            <?php if (function_exists('ems_state_empty')) { ems_state_empty('لا محاضر في هذه الفترة بعد.'); } else { echo '<p class="ent-dim">لا محاضر.</p>'; } ?>
        <?php else: ?>
        <div class="table-container">
        <table class="alltables display nowrap ent-w100">
            <thead><tr>
                <th>رقم المحضر</th><th>الفترة</th><th>الواقعة المرجعية</th>
                <th>حكم العميل</th><th>قيمة إيراد العميل</th>
                <th>حكم المورد</th><th>قيمة استحقاق المورد</th>
                <th>حكم المشغل</th><th>قيمة أجر المشغل</th>
                <th>العملة</th><th>تاريخ اكتمال السلسلة</th><th>رقم الحدث</th>
                <th>الحالة</th><th>المنشئ — الاسم والصفة</th><th>تاريخ الإنشاء</th>
                <th>مرجع التفويض</th><th>المرجع الأب</th><th>مفتاح منع التكرار</th>
                <th>درجة الأثر</th><th>معكوس ب</th><th>نسخة القاعدة</th>
                <?php /* الأعمدةُ الحاكمةُ الخمسةُ التي يطلبها تصميمُ الشاشة (CMP-03) —
                         كلُّها من أعمدةِ `fin_entitlements` نفسِها لا مُلفَّقة:
                         company_id · fx_rate · cost_center_id · reverses_ref ·
                         وjournal_ref مستندُ الإثباتِ المحاسبيِّ للمحضر. */ ?>
                <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صف بلا كيان مالك">الكيان</th>
                <th class="ems-gov-th" data-gov="reversal_of" data-slice="2" title="مرجع الحركة التي عكسها">عكس عن</th>
                <th class="ems-gov-th" data-gov="fx_rate" data-slice="3" title="سعر التحويل لعملة الدفاتر">سعر الصرف</th>
                <th class="ems-gov-th" data-gov="cost_center" data-slice="3" title="وجهة التحميل">مركز التكلفة</th>
                <th class="ems-gov-th" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
            </tr></thead>
            <tbody>
            <?php foreach ($generated as $g): ?>
                <tr>
                    <td class="ent-mono"><?php echo htmlspecialchars($g['entitle_code']); ?></td>
                    <td><?php echo htmlspecialchars($g['period']); ?></td>
                    <td class="ent-mono"><?php echo htmlspecialchars((string) $g['record_no']); ?></td>
                    <td class="ent-fs76"><?php echo htmlspecialchars($g['client_ruling']); ?></td>
                    <td><?php echo $g['client_amount'] !== null ? number_format((float) $g['client_amount'], 2) : '—'; ?></td>
                    <td class="ent-fs76"><?php echo htmlspecialchars($g['supplier_ruling']); ?></td>
                    <td><?php echo $g['supplier_amount'] !== null ? number_format((float) $g['supplier_amount'], 2) : '—'; ?></td>
                    <td class="ent-fs76"><?php echo htmlspecialchars($g['operator_ruling']); ?></td>
                    <td><?php echo $g['operator_amount'] !== null ? number_format((float) $g['operator_amount'], 2) : '—'; ?></td>
                    <td><?php echo htmlspecialchars($g['currency']); ?></td>
                    <td><small><?php echo htmlspecialchars((string) $g['chain_completed_at']); ?></small></td>
                    <td><?php echo $g['fact_event_id'] !== null ? (int) $g['fact_event_id'] : '—'; ?></td>
                    <td><span class="badge badge-info"><?php echo htmlspecialchars($g['state']); ?></span></td>
                    <td class="ent-fs76"><?php echo htmlspecialchars((string) $g['generator_name'])
                        . ' — ' . htmlspecialchars((string) ($g['generator_title'] ?: 'بلا مسمى')); ?></td>
                    <td><small><?php echo htmlspecialchars((string) $g['created_at']); ?></small></td>
                    <td class="ent-fs72"><?php echo htmlspecialchars($g['authority_ref']); ?></td>
                    <td class="ent-mono"><?php echo htmlspecialchars($g['parent_ref']); ?></td>
                    <td class="ent-mono-sm"><?php echo htmlspecialchars($g['idempotency_key']); ?></td>
                    <td><?php echo htmlspecialchars($g['effect_grade']); ?></td>
                    <td><?php echo $g['reversed_by_ref'] !== '' ? htmlspecialchars($g['reversed_by_ref']) : '—'; ?></td>
                    <td class="ent-fs72"><?php echo htmlspecialchars($g['ruleset_version']); ?></td>
                    <td><?php echo (int) $g['company_id']; ?></td>
                    <td class="ent-mono"><?php echo $g['reverses_ref'] !== '' && $g['reverses_ref'] !== null
                        ? htmlspecialchars((string) $g['reverses_ref']) : '—'; ?></td>
                    <td><?php echo $g['fx_rate'] !== null ? number_format((float) $g['fx_rate'], 6) : '—'; ?></td>
                    <td><?php echo $g['cost_center_id'] !== null ? (int) $g['cost_center_id'] : '—'; ?></td>
                    <td class="ent-mono-xs"><?php echo $g['journal_ref'] !== '' && $g['journal_ref'] !== null
                        ? htmlspecialchars((string) $g['journal_ref']) : '—'; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div></div>
</div>

<script src="../includes/js/jquery-3.7.1.main.js"></script>
<script>
function m10Post(fd, done) {
    if (window.csrfToken) { fd.append('csrf_token', window.csrfToken); }
    fetch('fin_m10_actions.php', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(done)
        .catch(function () { alert('تعذر الاتصال — أعد المحاولة'); });
}
function m10Gate(unitId) {
    var fd = new FormData();
    fd.append('do', 'gate_pass');
    fd.append('unit_id', unitId);
    m10Post(fd, function (j) {
        if (!j.ok) { alert(j.code + ': ' + j.msg); return; }
        if (j.result === 'pass') {
            alert('البوابة ' + j.gate_code + ': الفحوص الأربعة مجتازة ✔');
        } else {
            alert('البوابة ' + j.gate_code + ' ردت الواقعة — ' + j.reject_code + ': ' + (j.reject_msg || ''));
        }
        location.reload();
    });
}
function m10Entitle(unitId) {
    if (!confirm('توليد المحضر يفرع الأثر المالي عبر المروحة — تأكيد؟')) { return; }
    var fd = new FormData();
    fd.append('do', 'entitle_generate');
    fd.append('unit_id', unitId);
    m10Post(fd, function (j) {
        if (!j.ok) { alert(j.code + ': ' + j.msg); return; }
        var msg = j.idempotent
            ? 'محضر سابق ' + j.entitle_code + ' — الإعادة ترجع الأول (AR-04)'
            : 'ولد المحضر ' + j.entitle_code + ' — آثار: ' + j.effects + ' · متبناة: ' + j.adopted
              + (j.skipped && j.skipped.length ? ' · متخطاة معلنة: ' + j.skipped.length : '');
        alert(msg);
        location.reload();
    });
}
</script>
</body>
</html>
