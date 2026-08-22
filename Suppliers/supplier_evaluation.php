<?php
require_once __DIR__ . '/../includes/permissions_helper.php';
/**
 * Suppliers/supplier_evaluation.php — تقييمُ المورد الدوري (M-17)
 * ───────────────────────────────────────────────────────────────────────────
 * CON-03 §5: «لوحة: مؤشراتُ الجاهزية والتغطية والتوقفات المسندة والحوادث —
 * **كلُّ رقمٍ ينقر لمصدره**» · §4: «دوريٌّ بمؤشراتٍ من سجلات النظام **لا
 * انطباعًا** — **ونتيجتُه شرطٌ في التجديد**».
 *
 * لا حقلَ لكتابة نتيجةٍ ولا قيمةِ مؤشر: الشاشةُ **تولّد وتقرأ وتقرّر** فقط.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}
include '../config.php';
require_once __DIR__ . '/../app/Services/Contract/SupplierEvaluationService.php';

use App\Services\Contract\SupplierEvaluationService as SES;

$current_role   = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$company_id     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$uid            = isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0;

if (!$is_super_admin && $company_id <= 0) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد بيئة شركة صالحة للمستخدم ❌', 'GOV-SCOPE-403', '');
    exit();
}

$MODULE_CODE = 'Suppliers/supplier_evaluation.php';
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
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض تقييم الموردين ❌', 'GOV-PERM-403', '');
    exit();
}

$gate = $is_super_admin ? ems_tenant_db()->forAllTenants('supplier evaluation super') : ems_tenant_db();

$selected = intval($_GET['supplier_id'] ?? 0);
$redirect = function ($msg, $sid) { ems_gov_redirect("Location: supplier_evaluation.php?supplier_id=" . intval($sid)
    . "&msg=" . rawurlencode($msg)); exit(); };

$pFrom = isset($_GET['from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from'])
         ? $_GET['from'] : date('Y-m-01');
$pTo   = isset($_GET['to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to'])
         ? $_GET['to'] : date('Y-m-t');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = strval($_POST['ev_action'] ?? '');
    $sid = intval($_POST['supplier_id'] ?? 0);
    if (!$can_add && !$can_edit) { $redirect('لا توجد صلاحية لهذا الإجراء ❌', $sid); }

    if ($action === 'weight') {
        $r = SES::saveWeight($conn, $gate, $company_id, array(
            'indicator' => $_POST['indicator'] ?? '',
            'weight'    => $_POST['weight'] ?? '',
            'scale_max' => $_POST['scale_max'] ?? '',
            'note'      => $_POST['wnote'] ?? '',
        ), $uid);
        $redirect($r['ok'] ? 'حُفظ وزنُ المؤشر ✅' : ($r['code'] . ' — ' . $r['reason'] . ' ❌'), $sid);
    }

    if ($action === 'generate') {
        $r = SES::generate($conn, $gate, $company_id, $sid,
            strval($_POST['from'] ?? ''), strval($_POST['to'] ?? ''), $uid);
        $redirect($r['ok'] ? ('وُلّد التقييم — النتيجة ' . ($r['score'] ?? '—')
                              . ' بتغطيةِ وزنٍ ' . $r['coverage'] . '٪ ✅')
                           : ($r['code'] . ' — ' . $r['reason'] . ' ❌'), $sid);
    }

    if ($action === 'decide') {
        $r = SES::decide($conn, $gate, $company_id, intval($_POST['evaluation_id'] ?? 0),
            strval($_POST['renewal_flag'] ?? ''), strval($_POST['decision_note'] ?? ''), $uid);
        $redirect($r['ok'] ? 'اعتُمد التقييمُ بقراره ✅' : ($r['code'] . ' — ' . $r['reason'] . ' ❌'), $sid);
    }
}

// ── القراءة ────────────────────────────────────────────────────────────────
$suppliers = array();
try {
    $suppliers = $gate->scopedQuery(array('scope' => array('s' => 'suppliers')),
        "SELECT s.id, s.name FROM suppliers s
          WHERE {TENANT_SCOPE} AND COALESCE(s.is_deleted,0)=0 ORDER BY s.name");
} catch (\Throwable $t) { $suppliers = array(); }
if ($selected <= 0 && $suppliers) { $selected = intval($suppliers[0]['id']); }

$weights   = SES::weights($gate);
$wSum      = SES::weightsSum($gate);
$evals     = $selected > 0 ? SES::evaluationsOf($gate, $selected) : array();
$openId    = intval($_GET['open'] ?? 0);
if ($openId <= 0 && $evals) { $openId = intval($evals[0]['id']); }
$openEval  = null;
foreach ($evals as $e) { if (intval($e['id']) === $openId) { $openEval = $e; break; } }
$openLines = $openId > 0 ? SES::linesOf($gate, $openId) : array();
$gateInfo  = $selected > 0 ? SES::renewalGate($gate, $selected) : array('ok' => false, 'reason' => '—');

$page_title = 'إيكوبيشن | تقييم المورد الدوري';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/entity_tabs.php'; echo ems_entity_tabs('supplier', 'التقييمُ والمخاطر');
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
// NAV-01 §8 (update0006-b): الشاشةُ قسمٌ من ملف المورد الأم
$sf_supplier_id = intval($_GET['supplier_id'] ?? $_GET['id'] ?? 0); $sf_active = 'evaluation';
if ($sf_supplier_id > 0) include __DIR__ . '/../includes/supplier_file_tabs.php';
?>
<?php /* نُقلت أنماطُ هذه الشاشةِ إلى assets/css/ems-screens.css (UXUI-01 البند ٦: صفرُ نمطٍ محليّ) */ ?>
<div class="main ems-unified-page-shell ems-doc-cycle">
    <?php
    $header_title = 'تقييم المورد الدوري'; $header_icon = 'fa fa-star-half-stroke';
    $header_actions = array();
    $header_back = array('href' => 'supplier_capacity.php', 'class' => '',
                         'icon' => 'fas fa-arrow-right', 'label' => 'الطاقة والجاهزية');
    include('../includes/page_header.php');
    if (isset($_GET['msg'])) {
        echo '<div class="alert alert-info">' . htmlspecialchars($_GET['msg']) . '</div>';
    }
    echo ems_next_step('اعتمادُ قرارِ التجديدِ على التقييمِ المسودةِ — الرقمُ يخبر والإنسانُ يقرّر');
    echo ems_states_bundle('لا تقييماتٍ لهذا الموردِ في الفترة', 'ولّدِ التقييمَ من سجلاتِ النظامِ أو غيّرِ الفترة');
    ?>

    <div class="card"><div class="card-body">
                <!-- صندوقُ الفلاترِ الموحَّد — التصميمُ في assets/css/ems-filters.css -->
        <div class="filter">
            <div class="filter-title"><span class="filter-title-icon"><i class="fa-solid fa-sliders"></i></span> فلاتر البحث</div>
            <div class="filter-body">
        <form method="get" class="sev-filter">
            <strong>المورد:</strong>
            <select name="supplier_id" onchange="this.form.submit()" class="sev-supplier-select" aria-label="اختيارُ الموردِ المُقيَّم">
                <?php foreach ($suppliers as $s): ?>
                    <option value="<?php echo intval($s['id']); ?>" <?php echo $selected === intval($s['id']) ? 'selected' : ''; ?>>
                        #<?php echo intval($s['id']); ?> — <?php echo htmlspecialchars((string)$s['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <label for="emsf_501_419f9">من</label><input type="date" name="from" id="emsf_501_419f9" value="<?php echo htmlspecialchars($pFrom); ?>">
            <label for="emsf_502_5b9fd">إلى</label><input type="date" name="to" id="emsf_502_5b9fd" value="<?php echo htmlspecialchars($pTo); ?>">
            <button type="submit" class="btn-primary"><i class="fa fa-filter"></i> اضبط الفترة</button>
        </form>
            </div>
        </div>
        <p class="sev-quote">
            «دوريٌّ <strong>بمؤشراتٍ من سجلات النظام لا انطباعًا</strong>» (§4) — فلا حقلَ هنا
            لكتابة نتيجةٍ ولا قيمةِ مؤشر: كلُّ رقمٍ <strong>يُقرأ من مصدره</strong>، والقرارُ وحدَه إنسانيّ.
        </p>
        <div class="alert <?php echo $gateInfo['ok'] ? 'alert-success' : 'alert-warning'; ?>">
            <strong>بوابةُ التجديد:</strong> <?php echo htmlspecialchars((string)$gateInfo['reason']); ?>
        </div>
    </div></div>

    <div class="card"><div class="card-header"><h5><i class="fa fa-scale-balanced"></i>
        أوزانُ المؤشرات — Σ = <?php echo htmlspecialchars((string)$wSum); ?>٪
        <?php if (abs($wSum - 100.0) > 0.005): ?>
            <span class="badge badge-danger">والواجبُ 100٪ — لا تقييمَ قبل ضبطها</span>
        <?php else: ?><span class="badge badge-success">مضبوطة</span><?php endif; ?>
    </h5></div>
    <div class="card-body">
        <?php if ($can_add): ?>
        <form method="post" class="ems-form">
        <?= csrf_field() ?>
            <input type="hidden" name="ev_action" value="weight">
            <input type="hidden" name="supplier_id" value="<?php echo $selected; ?>">
            <div class="form-grid">
                <div class="form-group"><label for="emsf_503_295f9">المؤشر <span class="sev-req">*</span></label>
                    <select name="indicator" required id="emsf_503_295f9">
                        <?php foreach (SES::INDICATOR_LABELS as $k => $lbl): ?>
                            <option value="<?php echo $k; ?>"><?php echo $lbl; ?></option>
                        <?php endforeach; ?>
                    </select></div>
                <div class="form-group"><label for="emsf_504_462de">الوزن ٪ <span class="sev-req">*</span></label>
                    <input type="number" step="0.01" min="0.01" max="100" name="weight" required id="emsf_504_462de"></div>
                <div class="form-group"><label for="emsf_505_1f9c5">المقياس <small>— للحوادث: العددُ الذي تبلغ عنده النتيجةُ صفرًا</small></label>
                    <input type="number" step="0.01" min="0" name="scale_max" id="emsf_505_1f9c5"></div>
                <div class="form-group"><label for="emsf_506_2aa6a">ملاحظة</label><input type="text" name="wnote" maxlength="255" id="emsf_506_2aa6a"></div>
            </div>
            <div class="sev-actions"><button type="submit" class="btn-primary"><i class="fa fa-save"></i> حفظ الوزن</button></div>
        </form>
        <?php endif; ?>
        <div class="table-container sev-table-gap">
        <table class="alltables display nowrap sev-table">
            <thead><tr><th>المؤشر</th><th>الوزن</th><th>المقياس</th><th>ملاحظة</th></tr></thead>
            <tbody>
            <?php foreach ($weights as $w): ?>
                <tr>
                    <td><?php echo htmlspecialchars(SES::INDICATOR_LABELS[$w['indicator']] ?? $w['indicator']); ?></td>
                    <td><?php echo htmlspecialchars((string)$w['weight']); ?>٪</td>
                    <td><?php echo $w['scale_max'] !== null ? htmlspecialchars((string)$w['scale_max'])
                        : '<span class="badge badge-secondary">—</span>'; ?></td>
                    <td><small><?php echo htmlspecialchars((string)($w['note'] ?? '')); ?></small></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div></div>

    <?php if ($can_add && $selected > 0): ?>
    <div class="card"><div class="card-body">
        <form method="post" class="sev-filter">
        <?= csrf_field() ?>
            <input type="hidden" name="ev_action" value="generate">
            <input type="hidden" name="supplier_id" value="<?php echo $selected; ?>">
            <input type="hidden" name="from" value="<?php echo htmlspecialchars($pFrom); ?>">
            <input type="hidden" name="to" value="<?php echo htmlspecialchars($pTo); ?>">
            <button type="submit" class="btn-primary"><i class="fa fa-gears"></i>
                ولّد تقييمَ <?php echo htmlspecialchars($pFrom); ?> → <?php echo htmlspecialchars($pTo); ?></button>
            <span class="sev-muted">— يُقرأ من السجلات، ولا يُكتب رقمٌ يدويًّا</span>
        </form>
    </div></div>
    <?php endif; ?>

    <div class="card"><div class="card-header"><h5><i class="fa fa-list"></i> تقييماتُ المورد</h5></div>
    <div class="card-body"><div class="table-container">
        <table class="alltables display nowrap sev-table">
            <thead><tr><th>الفترة</th><th>النتيجة</th><th>تغطيةُ الوزن</th><th>الحالة</th>
                <th>قرار التجديد</th><th>السبب</th><th></th>
                <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
                <th class="ems-fn-th" data-fn="1">رقم التقييم</th>
                <th class="ems-fn-th" data-fn="1">المورد</th>
                <th class="ems-fn-th" data-fn="1">محور الجاهزية</th>
                <th class="ems-fn-th" data-fn="1">محور الالتزام بالمهل</th>
                <th class="ems-fn-th" data-fn="1">محور جودة المعدات</th>
                <th class="ems-fn-th" data-fn="1">محور الاستجابة</th>
                <th class="ems-fn-th" data-fn="1">محور الوثائق</th>
                <th class="ems-fn-th" data-fn="1">الدرجة الممنوحة</th>
                <th class="ems-fn-th" data-fn="1">الدرجة القصوى</th>
                <th class="ems-fn-th" data-fn="1">التصنيف الناتج</th>
                <th class="ems-fn-th" data-fn="1">أثر التقييم</th>
                <th class="ems-fn-th" data-fn="1">قيّمه</th>
                <th class="ems-fn-th" data-fn="1">اعتمده</th>
                <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
                <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
                <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
                <th class="ems-gov-th none" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
                <th class="ems-gov-th none" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
                <th class="ems-gov-th none" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
                <th class="ems-gov-th none" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
                <th class="ems-gov-th none" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
                </tr></thead>
            <tbody>
            <?php foreach ($evals as $e): ?>
                <tr>
                    <td><?php echo htmlspecialchars((string)$e['period_from']); ?>
                        → <?php echo htmlspecialchars((string)$e['period_to']); ?></td>
                    <td><strong><?php echo $e['score'] !== null ? htmlspecialchars((string)$e['score'])
                        : '—'; ?></strong></td>
                    <td><?php echo htmlspecialchars((string)$e['weight_measured']); ?>٪
                        <?php if ((float)$e['weight_measured'] < 100): ?>
                            <small class="sev-partial-note">(الباقي بلا مصدرٍ — معلَن)</small>
                        <?php endif; ?></td>
                    <td><?php echo (string)$e['state'] === 'decided'
                        ? "<span class='badge badge-success'>معتمَد</span>"
                        : "<span class='badge badge-secondary'>مسودة</span>"; ?></td>
                    <td><?php echo $e['renewal_flag'] !== null
                        ? htmlspecialchars(SES::RENEWAL_LABELS[$e['renewal_flag']] ?? (string)$e['renewal_flag'])
                        : '—'; ?></td>
                    <td><small><?php echo htmlspecialchars((string)($e['decision_note'] ?? '')); ?></small></td>
                    <td><a class="btn-primary" href="supplier_evaluation.php?supplier_id=<?php echo $selected; ?>&open=<?php echo intval($e['id']); ?>&from=<?php echo htmlspecialchars($pFrom); ?>&to=<?php echo htmlspecialchars($pTo); ?>">المؤشرات</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div></div></div>

    <?php if ($openEval !== null): ?>
    <div class="card"><div class="card-header"><h5><i class="fa fa-magnifying-glass-chart"></i>
        مؤشراتُ تقييم <?php echo htmlspecialchars((string)$openEval['period_from']); ?>
        → <?php echo htmlspecialchars((string)$openEval['period_to']); ?>
        · النتيجة <strong><?php echo $openEval['score'] !== null ? htmlspecialchars((string)$openEval['score']) : '—'; ?></strong>
        من 100 بتغطيةِ وزنٍ <?php echo htmlspecialchars((string)$openEval['weight_measured']); ?>٪</h5></div>
    <div class="card-body">
        <div class="table-container">
        <table class="alltables display nowrap sev-table">
            <thead><tr><th>المؤشر</th><th>القياس</th><th>الأساس</th><th>النسبة</th>
                <th>الوزن</th><th>المكتسَب</th><th>المصدر</th></tr></thead>
            <tbody>
            <?php foreach ($openLines as $l): ?>
                <tr>
                    <td><?php echo htmlspecialchars(SES::INDICATOR_LABELS[$l['indicator']] ?? $l['indicator']); ?></td>
                    <td><?php echo intval($l['measurable']) === 1
                        ? htmlspecialchars((string)$l['measured_value'])
                        : '<span class="badge badge-warning">بلا مصدر</span>'; ?></td>
                    <td><?php echo $l['basis_value'] !== null ? htmlspecialchars((string)$l['basis_value']) : '—'; ?></td>
                    <td><?php echo $l['ratio'] !== null
                        ? htmlspecialchars((string)round((float)$l['ratio'] * 100, 2)) . '٪' : '—'; ?></td>
                    <td><?php echo htmlspecialchars((string)$l['weight']); ?>٪</td>
                    <td><?php echo htmlspecialchars((string)$l['earned']); ?></td>
                    <td><small><?php echo htmlspecialchars((string)($l['source_note'] ?? '')); ?></small></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>

        <?php if ($can_edit && (string)$openEval['state'] === 'draft'): ?>
        <form method="post" class="ems-form sev-decide-form">
        <?= csrf_field() ?>
            <input type="hidden" name="ev_action" value="decide">
            <input type="hidden" name="supplier_id" value="<?php echo $selected; ?>">
            <input type="hidden" name="evaluation_id" value="<?php echo intval($openEval['id']); ?>">
            <div class="form-grid">
                <div class="form-group"><label for="emsf_507_b93ae">قرار التجديد <span class="sev-req">*</span></label>
                    <select name="renewal_flag" required id="emsf_507_b93ae">
                        <?php foreach (SES::RENEWAL_LABELS as $k => $lbl): ?>
                            <option value="<?php echo $k; ?>"><?php echo $lbl; ?></option>
                        <?php endforeach; ?>
                    </select></div>
                <div class="form-group"><label for="emsf_508_d31f7">السبب <small>— إلزاميٌّ عند منع التجديد</small></label>
                    <input type="text" name="decision_note" maxlength="255" id="emsf_508_d31f7"></div>
            </div>
            <p class="sev-decision-note">الرقمُ يخبر و<strong>الإنسانُ يقرّر</strong> — والقرارُ يصير
                <strong>شرطًا في التجديد</strong> بعد اعتماده، ولا يُعاد.</p>
            <div><button type="submit" class="btn-primary"><i class="fa fa-gavel"></i> اعتمد القرار</button></div>
        </form>
        <?php endif; ?>
    </div></div>
    <?php endif; ?>
</div>

<script src="../includes/js/jquery-3.7.1.main.js"></script>
</body>
</html>
