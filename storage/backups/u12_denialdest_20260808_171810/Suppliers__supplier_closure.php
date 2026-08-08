<?php
require_once __DIR__ . '/../includes/permissions_helper.php';
/**
 * Suppliers/supplier_closure.php — تصفيةُ إنهاء عقد المورد (M-18)
 * ───────────────────────────────────────────────────────────────────────────
 * ENT-02 §4: «عند الإنهاء: **إقفالُ الحصة** · **تسويةُ العهد والسلف** · **ردُّ
 * الضمان بعد مهلته** · و**شهادةُ إخلاءٍ موثَّقة**».
 *
 * الشاشةُ **قائمةُ خطواتٍ بترتيبها**: كلُّ خطوةٍ بزرّها وحالتِها ومصدرِ رقمها،
 * والإخلاءُ آخرُها — ولا يُفتح زرُّه قبل إتمام ما قبله.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}
include '../config.php';
require_once __DIR__ . '/../app/Services/Contract/SupplierClosureService.php';

use App\Services\Contract\SupplierClosureService as SCL;

$current_role   = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$company_id     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$uid            = isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0;

if (!$is_super_admin && $company_id <= 0) {
    ems_gov_flash_redirect('../login.php', 'لا توجد بيئة شركة صالحة للمستخدم ❌', 'GOV-SCOPE-403', '');
    exit();
}

$MODULE_CODE = 'Suppliers/supplier_closure.php';
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
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض تصفية العقود ❌', 'GOV-PERM-403', '');
    exit();
}

$gate = $is_super_admin ? ems_tenant_db()->forAllTenants('supplier closure super') : ems_tenant_db();
$redirect = function ($msg) { ems_gov_flash_redirect('supplier_closure.php', $msg, 'GOV-INFO-200', ''); exit(); };

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = strval($_POST['cl_action'] ?? '');
    if (!$can_add && !$can_edit) { $redirect('لا توجد صلاحية لهذا الإجراء ❌'); }
    $cid = intval($_POST['closure_id'] ?? 0);

    if ($action === 'open') {
        $r = SCL::open($conn, $gate, $company_id, intval($_POST['contract_id'] ?? 0), $uid);
        $redirect($r['ok'] ? 'فُتحت تصفيةُ الإنهاء ✅' : ($r['code'] . ' — ' . $r['reason'] . ' ❌'));
    }
    if ($action === 'quota') {
        $r = SCL::closeQuota($conn, $gate, $company_id, $cid, strval($_POST['reason'] ?? ''), $uid);
        $redirect($r['ok'] ? ('أُقفلت ' . $r['closed'] . ' حاوية ✅') : ($r['code'] . ' — ' . $r['reason'] . ' ❌'));
    }
    if ($action === 'advances') {
        $r = SCL::settleAdvances($conn, $gate, $company_id, $cid, $uid);
        $redirect($r['ok'] ? 'العهدُ والسلفُ مسوّاة ✅' : ($r['code'] . ' — ' . $r['reason'] . ' ❌'));
    }
    if ($action === 'guarantee') {
        $r = SCL::releaseGuarantee($conn, $gate, $company_id, $cid, date('Y-m-d'), $uid);
        $redirect($r['ok'] ? ('رُدَّ الضمانُ بذمّةٍ دائنة #' . $r['due_id'] . ' ✅')
                           : ($r['code'] . ' — ' . $r['reason'] . ' ❌'));
    }
    if ($action === 'close') {
        $r = SCL::close($conn, $gate, $company_id, $cid, strval($_POST['clearance_doc'] ?? ''), $uid);
        $redirect($r['ok'] ? 'أُقفلت التصفيةُ بشهادة الإخلاء ✅' : ($r['code'] . ' — ' . $r['reason'] . ' ❌'));
    }
}

// ── القراءة ────────────────────────────────────────────────────────────────
$closures = SCL::listAll($gate);
$endedContracts = array();
try {
    $endedContracts = $gate->scopedQuery(array(
        'scope'  => array('h' => 'supplier_contracts'),
        'enrich' => array('s' => 'suppliers'),
    ), "SELECT h.id, h.state, h.end_date, h.currency, h.performance_guarantee,
               h.guarantee_retention_days, s.name AS supplier_name
          FROM supplier_contracts h
          LEFT JOIN suppliers s ON s.id = h.supplier_id
         WHERE {TENANT_SCOPE} AND COALESCE(h.is_deleted,0)=0
           AND h.state IN ('منتهٍ','مقفل')
           AND NOT EXISTS (SELECT 1 FROM (SELECT contract_id FROM supplier_contract_closures) c
                            WHERE c.contract_id = h.id)
         ORDER BY h.id DESC");
} catch (\Throwable $t) { $endedContracts = array(); }

$page_title = 'إيكوبيشن | تصفية إنهاء عقد المورد';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
// NAV-01 §8 (update0006-b): الشاشةُ قسمٌ من ملف المورد الأم
$sf_supplier_id = intval($_GET['supplier_id'] ?? $_GET['id'] ?? 0); $sf_active = 'closure';
if ($sf_supplier_id > 0) include __DIR__ . '/../includes/supplier_file_tabs.php';
?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'تصفية إنهاء عقد المورد'; $header_icon = 'fa fa-file-circle-check';
    $header_actions = array();
    $header_back = array('href' => 'supplier_evaluation.php', 'class' => '',
                         'icon' => 'fas fa-arrow-right', 'label' => 'تقييم المورد');
    include('../includes/page_header.php');
    if (isset($_GET['msg'])) {
        echo '<div class="alert alert-info">' . htmlspecialchars($_GET['msg']) . '</div>';
    }
    ?>

    <div class="card"><div class="card-body">
        <p style="color:#666">
            «عند الإنهاء: <strong>إقفالُ الحصة</strong> · <strong>تسويةُ العهد والسلف</strong> ·
            <strong>ردُّ الضمان بعد مهلته</strong> · و<strong>شهادةُ إخلاءٍ موثَّقة</strong>» (ENT-02 §4)
            — والخطواتُ <strong>بترتيبها لا بالنيّة</strong>: لا إخلاءَ وحاويةٌ مفتوحةٌ أو سلفةٌ برصيد،
            ولا ردَّ ضمانٍ قبل مهلته. و<strong>تصفيةٌ واحدةٌ للعقد</strong>.
        </p>
    </div></div>

    <?php if ($can_add): ?>
    <div class="card"><div class="card-header"><h5><i class="fa fa-folder-open"></i> عقودٌ منتهيةٌ بلا تصفية</h5></div>
    <div class="card-body">
        <?php if (!$endedContracts): ?>
            <p style="color:#666">لا عقدَ منتهيًا ينتظر تصفيةً — <strong>والتصفيةُ عند الإنهاء لا قبله</strong>.</p>
        <?php else: ?>
        <div class="table-container">
        <table class="alltables display nowrap" style="width:100%">
            <thead><tr><th>العقد</th><th>المورد</th><th>الحالة</th><th>ينتهي</th>
                <th>ضمان الأداء</th><th>مهلة الردّ</th><th></th>
                <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
                <th class="ems-fn-th" data-fn="1">رقم المحضر</th>
                <th class="ems-fn-th" data-fn="1">تاريخ الإنهاء</th>
                <th class="ems-fn-th" data-fn="1">سبب الإنهاء</th>
                <th class="ems-fn-th" data-fn="1">إجمالي المستحقات</th>
                <th class="ems-fn-th" data-fn="1">التحميلات علينا</th>
                <th class="ems-fn-th" data-fn="1">التحميلات على المورد</th>
                <th class="ems-fn-th" data-fn="1">الجزاءات</th>
                <th class="ems-fn-th" data-fn="1">السلف غير المسدَّدة</th>
                <th class="ems-fn-th" data-fn="1">الصافي المستحق</th>
                <th class="ems-fn-th" data-fn="1">حالة الكفالة</th>
                <th class="ems-fn-th" data-fn="1">المعدات المُخرَجة</th>
                <th class="ems-fn-th" data-fn="1">تاريخ إخلاء المعدات</th>
                <th class="ems-fn-th" data-fn="1">اعتماد الموردين</th>
                <th class="ems-fn-th" data-fn="1">الاعتماد المالي</th>
                <th class="ems-fn-th" data-fn="1">تاريخ السداد النهائي</th>
                <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
                <th class="ems-gov-th none" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
                <th class="ems-gov-th none" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
                <th class="ems-gov-th none" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
                <th class="ems-gov-th none" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
                <th class="ems-gov-th none" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
                <th class="ems-gov-th none" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
                <th class="ems-gov-th none" data-gov="idem_key" data-slice="2" title="يمنع وقوع الأثر مرتين بمفتاح مركب">مفتاح منع التكرار</th>
                <th class="ems-gov-th none" data-gov="reversed_by" data-slice="2" title="مرجع الحركة التي عكسته">معكوس بـ</th>
                <th class="ems-gov-th none" data-gov="reversal_of" data-slice="2" title="مرجع الحركة التي عكسها">عكس عن</th>
                <th class="ems-gov-th none" data-gov="impact_grade" data-slice="2" title="مبدئي أم نهائي — فلا يقفل مبدئي ماليًّا">درجة الأثر</th>
                <th class="ems-gov-th none" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
                <th class="ems-gov-th none" data-gov="cost_center" data-slice="3" title="وجهة التحميل">مركز التكلفة</th>
                <th class="ems-gov-th none" data-gov="fx_rate_source" data-slice="3" title="ما خالف عملة الدفاتر يحمل السعر ومصدره">سعر الصرف ومصدره</th>
                <th class="ems-gov-th none" data-gov="currency" data-slice="3" title="لا مبلغ بلا عملة">العملة</th>
                </tr></thead>
            <tbody>
            <?php foreach ($endedContracts as $c): ?>
                <tr>
                    <td>#<?php echo intval($c['id']); ?></td>
                    <td><?php echo htmlspecialchars((string)($c['supplier_name'] ?? '—')); ?></td>
                    <td><?php echo htmlspecialchars((string)$c['state']); ?></td>
                    <td><?php echo htmlspecialchars((string)($c['end_date'] ?? 'مفتوح')); ?></td>
                    <td><?php echo $c['performance_guarantee'] !== null
                        ? htmlspecialchars((string)$c['performance_guarantee'] . ' ' . $c['currency'])
                        : '<span class="badge badge-secondary">لم يُشترط</span>'; ?></td>
                    <td><?php echo $c['guarantee_retention_days'] !== null
                        ? htmlspecialchars((string)$c['guarantee_retention_days']) . ' يومًا' : '—'; ?></td>
                    <td>
                        <form method="post" style="margin:0">
                            <input type="hidden" name="cl_action" value="open">
                            <input type="hidden" name="contract_id" value="<?php echo intval($c['id']); ?>">
                            <button type="submit" class="btn-save"><i class="fa fa-play"></i> افتح التصفية</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div></div>
    <?php endif; ?>

    <?php foreach ($closures as $cl):
        $missing = SCL::missingSteps($cl);
        $openQ = SCL::openQuotas($gate, intval($cl['supplier_id']));
    ?>
    <div class="card"><div class="card-header"><h5>
        <i class="fa fa-file-circle-check"></i>
        تصفية #<?php echo intval($cl['id']); ?> — عقد #<?php echo intval($cl['contract_id']); ?>
        · <?php echo htmlspecialchars((string)($cl['supplier_name'] ?? '')); ?>
        <?php echo (string)$cl['state'] === 'closed'
            ? "<span class='badge badge-success'>مقفلة</span>"
            : "<span class='badge badge-secondary'>مفتوحة</span>"; ?>
    </h5></div>
    <div class="card-body">
        <?php if ($missing): ?>
            <div class="alert alert-warning">الناقصُ لإتمام الإخلاء:
                <strong><?php echo htmlspecialchars(implode(' · ', $missing)); ?></strong></div>
        <?php endif; ?>

        <div class="table-container">
        <table class="alltables display nowrap" style="width:100%" data-no-dt="1">
            <thead><tr><th>الخطوة</th><th>القياس ومصدره</th><th>الحال</th><th>الفعل</th></tr></thead>
            <tbody>
            <tr>
                <td>① إقفالُ الحصة</td>
                <td><?php echo count($openQ); ?> حاويةً مفتوحةً الآن (<code>op_containers</code> بمورده)</td>
                <td><?php echo $cl['quota_closed_at'] !== null
                    ? '<span class="badge badge-success">أُقفلت</span>' : '<span class="badge badge-warning">مفتوحة</span>'; ?>
                    <?php if ($cl['quota_close_reason'] !== null): ?>
                        <br><small><?php echo htmlspecialchars((string)$cl['quota_close_reason']); ?></small>
                    <?php endif; ?></td>
                <td><?php if ($can_edit && $cl['quota_closed_at'] === null && (string)$cl['state'] !== 'closed'): ?>
                    <form method="post" style="margin:0;display:flex;gap:6px">
                        <input type="hidden" name="cl_action" value="quota">
                        <input type="hidden" name="closure_id" value="<?php echo intval($cl['id']); ?>">
                        <input type="text" name="reason" maxlength="255" placeholder="سببُ إقفال ما لم يُستهلك">
                        <button type="submit" class="btn-save">أقفِل الحصة</button>
                    </form>
                <?php endif; ?></td>
            </tr>
            <tr>
                <td>② تسويةُ العهد والسلف</td>
                <td>رصيدٌ مفتوحٌ <?php echo htmlspecialchars((string)$cl['advances_balance']); ?>
                    (<code>supplier_advance_requests</code>)</td>
                <td><?php echo $cl['advances_settled_at'] !== null
                    ? '<span class="badge badge-success">مسوّاة</span>' : '<span class="badge badge-warning">قائمة</span>'; ?></td>
                <td><?php if ($can_edit && $cl['advances_settled_at'] === null && (string)$cl['state'] !== 'closed'): ?>
                    <form method="post" style="margin:0">
                        <input type="hidden" name="cl_action" value="advances">
                        <input type="hidden" name="closure_id" value="<?php echo intval($cl['id']); ?>">
                        <button type="submit" class="btn-save">تحقّق وسوِّ</button>
                    </form>
                <?php endif; ?></td>
            </tr>
            <tr>
                <td>③ ردُّ الضمان بعد مهلته</td>
                <td><?php echo $cl['guarantee_amount'] !== null
                    ? htmlspecialchars((string)$cl['guarantee_amount'] . ' ' . (string)$cl['guarantee_currency']
                        . ' · يُردُّ في ' . (string)$cl['guarantee_due_date'])
                    : '<span class="badge badge-secondary">لا ضمانَ مكتوب — ولا يُردُّ ما لم يُؤخذ</span>'; ?></td>
                <td><?php echo $cl['guarantee_released_at'] !== null
                    ? ('<span class="badge badge-success">رُدَّ بذمّة #' . intval($cl['guarantee_due_ref']) . '</span>')
                    : '<span class="badge badge-warning">محتجز</span>'; ?></td>
                <td><?php if ($can_edit && $cl['guarantee_amount'] !== null
                            && $cl['guarantee_released_at'] === null && (string)$cl['state'] !== 'closed'): ?>
                    <form method="post" style="margin:0">
                        <input type="hidden" name="cl_action" value="guarantee">
                        <input type="hidden" name="closure_id" value="<?php echo intval($cl['id']); ?>">
                        <button type="submit" class="btn-save">ردَّ الضمان</button>
                    </form>
                <?php endif; ?></td>
            </tr>
            <tr>
                <td>④ شهادةُ الإخلاء</td>
                <td><?php echo $cl['clearance_doc'] !== null
                    ? htmlspecialchars((string)$cl['clearance_doc']) : 'مستندٌ إلزاميٌّ للإقفال'; ?></td>
                <td><?php echo (string)$cl['state'] === 'closed'
                    ? '<span class="badge badge-success">أُخلي</span>' : '<span class="badge badge-warning">قيد التصفية</span>'; ?></td>
                <td><?php if ($can_edit && (string)$cl['state'] !== 'closed'): ?>
                    <form method="post" style="margin:0;display:flex;gap:6px">
                        <input type="hidden" name="cl_action" value="close">
                        <input type="hidden" name="closure_id" value="<?php echo intval($cl['id']); ?>">
                        <input type="text" name="clearance_doc" maxlength="120" placeholder="مرجعُ شهادة الإخلاء" required>
                        <button type="submit" class="btn-save">أقفِل بالشهادة</button>
                    </form>
                <?php endif; ?></td>
            </tr>
            </tbody>
        </table>
        </div>
    </div></div>
    <?php endforeach; ?>
</div>

<script src="../includes/js/jquery-3.7.1.main.js"></script>
</body>
</html>
