<?php
/**
 * Suppliers/supplier_rules.php — قواعدُ تحميل المورد وجزاءاته (M-15)
 * ───────────────────────────────────────────────────────────────────────────
 * CON-03 §2-⑥: «ما يُحمَّل عليه من مصروفاتنا … **بأسعارٍ وقواعدَ مكتوبة**».
 * §2-⑦: «جزاءاتُ العجز والجاهزية والتأخر … **سقوفُها**».
 * §4: «وله أن **يشدّد جزاءَه لا أن يعكس إسنادًا**».
 *
 * كلُّ حفظٍ عبر `SupplierRuleService` — فحارسُ «قاعدةٌ بلا سعرٍ مكتوب» وحارسُ
 * «نقضُ الإسناد يلزمه سبب» يسريان من الشاشة ومن أي مستدعٍ آخر معًا.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}
include '../config.php';
require_once __DIR__ . '/../app/Services/Settlement/SupplierRuleService.php';

use App\Services\Settlement\SupplierRuleService as SRS;

$current_role   = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$company_id     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$uid            = isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0;

if (!$is_super_admin && $company_id <= 0) {
    header("Location: ../login.php?msg=لا+توجد+بيئة+شركة+صالحة+للمستخدم+❌");
    exit();
}

$MODULE_CODE = 'Suppliers/supplier_rules.php';
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
    header("Location: ../main/dashboard.php?msg=لا+توجد+صلاحية+عرض+قواعد+المورد+❌");
    exit();
}

$gate = $is_super_admin ? ems_tenant_db()->forAllTenants('supplier rules super') : ems_tenant_db();

$selected = intval($_GET['contract_id'] ?? 0);
$redirect = function ($msg, $cid) { header("Location: supplier_rules.php?contract_id=" . intval($cid)
    . "&msg=" . rawurlencode($msg)); exit(); };

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = strval($_POST['sr_action'] ?? '');
    $cid = intval($_POST['contract_id'] ?? 0);
    if (!$can_add && !$can_edit) { $redirect('لا توجد صلاحية لهذا الإجراء ❌', $cid); }

    if ($action === 'charge_rule') {
        $r = SRS::saveChargeRule($conn, $gate, $company_id, $cid, array(
            'charge_type' => $_POST['charge_type'] ?? '',
            'pricing'     => $_POST['pricing'] ?? 'cost',
            'rate'        => $_POST['rate'] ?? '',
            'cap'         => $_POST['cap'] ?? '',
            'currency'    => $_POST['currency'] ?? '',
            'valid_from'  => $_POST['valid_from'] ?? '',
            'valid_to'    => $_POST['valid_to'] ?? '',
            'note'        => $_POST['note'] ?? '',
        ), $uid);
        $redirect($r['ok'] ? 'حُفظت قاعدةُ التحميل ✅' : ($r['code'] . ' — ' . $r['reason'] . ' ❌'), $cid);
    }

    if ($action === 'penalty_rule') {
        $r = SRS::savePenaltyRule($conn, $gate, $company_id, $cid, array(
            'kind'                 => $_POST['kind'] ?? '',
            'threshold'            => $_POST['threshold'] ?? '',
            'rate'                 => $_POST['prate'] ?? 0,
            'rate_basis'           => $_POST['rate_basis'] ?? 'per_unit',
            'cap_percent'          => $_POST['cap_percent'] ?? '',
            'periodicity'          => $_POST['periodicity'] ?? 'monthly',
            'inherits_attribution' => isset($_POST['inherits']) ? 1 : 0,
            'override_reason'      => $_POST['override_reason'] ?? '',
            'formula_note'         => $_POST['formula_note'] ?? '',
            'valid_from'           => $_POST['pvalid_from'] ?? '',
            'valid_to'             => $_POST['pvalid_to'] ?? '',
        ), $uid);
        $redirect($r['ok'] ? 'حُفظت قاعدةُ الجزاء ✅' : ($r['code'] . ' — ' . $r['reason'] . ' ❌'), $cid);
    }
}

// ── القراءة ────────────────────────────────────────────────────────────────
$contracts = array();
try {
    $contracts = $gate->scopedQuery(array(
        'scope'  => array('h' => 'supplier_contracts'),
        'enrich' => array('s' => 'suppliers'),
    ), "SELECT h.id, h.state, h.start_date, h.end_date, h.currency, s.name AS supplier_name
          FROM supplier_contracts h
          LEFT JOIN suppliers s ON s.id = h.supplier_id
         WHERE {TENANT_SCOPE} AND COALESCE(h.is_deleted,0)=0
         ORDER BY h.id DESC");
} catch (\Throwable $t) { $contracts = array(); }
if ($selected <= 0 && $contracts) { $selected = intval($contracts[0]['id']); }

$chargeRules  = $selected > 0 ? SRS::chargeRulesOf($gate, $selected) : array();
$penaltyRules = $selected > 0 ? SRS::penaltyRulesOf($gate, $selected) : array();

$page_title = 'إيكوبيشن | قواعد تحميل المورد وجزاءاته';
include '../inheader.php';
include '../insidebar.php';
// NAV-01 §8 (update0006-b): الشاشةُ قسمٌ من ملف المورد الأم
$sf_supplier_id = intval($_GET['supplier_id'] ?? $_GET['id'] ?? 0); $sf_active = 'rules';
if ($sf_supplier_id > 0) include __DIR__ . '/../includes/supplier_file_tabs.php';
?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'قواعد تحميل المورد وجزاءاته'; $header_icon = 'fa fa-scale-unbalanced';
    $header_actions = array();
    $header_back = array('href' => 'supplier_contract_lines.php', 'class' => '',
                         'icon' => 'fas fa-arrow-right', 'label' => 'بنود عقد المورد');
    include('../includes/page_header.php');
    if (isset($_GET['msg'])) {
        echo '<div class="alert alert-info">' . htmlspecialchars($_GET['msg']) . '</div>';
    }
    ?>

    <div class="card"><div class="card-body">
        <form method="get" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
            <strong>عقد المورد:</strong>
            <select name="contract_id" onchange="this.form.submit()" style="min-width:360px">
                <?php foreach ($contracts as $c): ?>
                    <option value="<?php echo intval($c['id']); ?>" <?php echo $selected === intval($c['id']) ? 'selected' : ''; ?>>
                        #<?php echo intval($c['id']); ?> — <?php echo htmlspecialchars((string)($c['supplier_name'] ?? '—')); ?>
                        · <?php echo htmlspecialchars((string)$c['state']); ?>
                        · <?php echo htmlspecialchars((string)($c['start_date'] ?? '')); ?>
                        → <?php echo htmlspecialchars((string)($c['end_date'] ?? 'مفتوح')); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
        <p style="color:#666;margin-top:10px">
            القاعدةُ تسري بسريانها <strong>وبمدة عقدها معًا</strong> — فقاعدةٌ مفتوحةُ النهاية
            لا تعيش بعد انتهاء عقدها: لا عقدَ ⇒ لا تحميلَ بقاعدته.
        </p>
    </div></div>

    <?php if ($can_add): ?>
    <div class="card"><div class="card-header"><h5><i class="fa fa-coins"></i> قاعدةُ تحميلٍ مسعَّرة</h5></div>
    <div class="card-body">
        <p style="color:#666">«ما يُحمَّل عليه من مصروفاتنا … <strong>بأسعارٍ وقواعدَ مكتوبة</strong>» (§2-⑥)
            — و<strong>قاعدةٌ بلا سعرٍ مكتوبٍ مرفوضة</strong>.</p>
        <form method="post" class="ems-form">
            <input type="hidden" name="sr_action" value="charge_rule">
            <input type="hidden" name="contract_id" value="<?php echo $selected; ?>">
            <div class="form-grid">
                <div class="form-group">
                    <label>نوع التحميل <span style="color:#c00">*</span></label>
                    <select name="charge_type" required>
                        <?php foreach (SRS::CHARGE_LABELS as $k => $lbl): ?>
                            <option value="<?php echo $k; ?>"><?php echo $lbl; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>طريقة التسعير <span style="color:#c00">*</span></label>
                    <select name="pricing" required>
                        <?php foreach (SRS::PRICING_LABELS as $k => $lbl): ?>
                            <option value="<?php echo $k; ?>"><?php echo $lbl; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label>المعدل <small>— نسبةٌ أو مبلغٌ بحسب الطريقة</small></label>
                    <input type="number" step="0.001" min="0" name="rate"></div>
                <div class="form-group"><label>السقف <small>— فارغٌ = بلا سقفٍ مكتوب</small></label>
                    <input type="number" step="0.01" min="0" name="cap"></div>
                <div class="form-group"><label>سريان من <span style="color:#c00">*</span></label>
                    <input type="date" name="valid_from" required></div>
                <div class="form-group"><label>سريان إلى</label><input type="date" name="valid_to"></div>
                <div class="form-group"><label>ملاحظة</label><input type="text" name="note" maxlength="255"></div>
            </div>
            <div style="margin-top:12px"><button type="submit" class="btn-save"><i class="fa fa-save"></i> حفظ القاعدة</button></div>
        </form>
    </div></div>
    <?php endif; ?>

    <div class="card"><div class="card-header"><h5><i class="fa fa-list"></i> قواعدُ التحميل</h5></div>
    <div class="card-body"><div class="table-container">
        <table class="alltables display nowrap" style="width:100%">
            <thead><tr><th>نوع التحميل</th><th>التسعير</th><th>المعدل</th><th>السقف الأقصى</th>
                <th>تاريخ السريان</th><th>الحالة</th><th>ملاحظة</th>
                <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
                <th class="ems-fn-th" data-fn="1">رقم القاعدة</th>
                <th class="ems-fn-th" data-fn="1">المورد أو العام</th>
                <th class="ems-fn-th" data-fn="1">بند العقد المرجعي</th>
                <th class="ems-fn-th" data-fn="1">حالة الاستحقاق</th>
                <th class="ems-fn-th" data-fn="1">معادلة الاحتساب</th>
                <th class="ems-fn-th" data-fn="1">يحتاج موافقة المورد؟</th>
                <th class="ems-fn-th" data-fn="1">دورة الاعتماد</th>
                <th class="ems-fn-th" data-fn="1">عرّفها</th>
                <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
                <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
                <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
                <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
                <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
                <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
                <th class="ems-gov-th" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
                <th class="ems-gov-th" data-gov="cost_center" data-slice="3" title="وجهة التحميل">مركز التكلفة</th>
                <th class="ems-gov-th none" data-gov="fx_rate_source" data-slice="3" title="ما خالف عملة الدفاتر يحمل السعر ومصدره">سعر الصرف ومصدره</th>
                <th class="ems-gov-th none" data-gov="currency" data-slice="3" title="لا مبلغ بلا عملة">العملة</th>
                </tr></thead>
            <tbody>
            <?php foreach ($chargeRules as $r): ?>
                <tr>
                    <td><?php echo htmlspecialchars(SRS::CHARGE_LABELS[$r['charge_type']] ?? $r['charge_type']); ?></td>
                    <td><?php echo htmlspecialchars(SRS::PRICING_LABELS[$r['pricing']] ?? $r['pricing']); ?></td>
                    <td><?php echo $r['rate'] !== null ? htmlspecialchars((string)$r['rate'])
                        . ((string)$r['pricing'] === 'cost_plus' ? '٪' : '') : '—'; ?></td>
                    <td><?php echo $r['cap'] !== null ? htmlspecialchars((string)$r['cap'])
                        : '<span class="badge badge-warning">بلا سقف</span>'; ?></td>
                    <td><?php echo htmlspecialchars((string)$r['valid_from']); ?>
                        → <?php echo htmlspecialchars((string)($r['valid_to'] ?? 'مفتوح')); ?></td>
                    <td><?php echo (string)$r['state'] === 'active'
                        ? "<span class='badge badge-success'>نافذة</span>"
                        : "<span class='badge badge-secondary'>منتهية</span>"; ?></td>
                    <td><small><?php echo htmlspecialchars((string)($r['note'] ?? '')); ?></small></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div></div></div>

    <?php if ($can_add): ?>
    <div class="card"><div class="card-header"><h5><i class="fa fa-gavel"></i> قاعدةُ جزاءٍ بسقفها</h5></div>
    <div class="card-body">
        <p style="color:#666">«الجزاءاتُ … <strong>وسقوفُها</strong>» (§2-⑦) — و<strong>«له أن يشدّد جزاءَه
            لا أن يعكس إسنادًا»</strong> (§4): نقضُ الإسناد الموروث يلزمه سببٌ مكتوب.</p>
        <form method="post" class="ems-form">
            <input type="hidden" name="sr_action" value="penalty_rule">
            <input type="hidden" name="contract_id" value="<?php echo $selected; ?>">
            <div class="form-grid">
                <div class="form-group">
                    <label>نوع الجزاء <span style="color:#c00">*</span></label>
                    <select name="kind" required>
                        <?php foreach (SRS::PENALTY_LABELS as $k => $lbl): ?>
                            <option value="<?php echo $k; ?>"><?php echo $lbl; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label>الحد/العتبة <small>— نسبةُ جاهزيةٍ دنيا · ساعاتُ إحلال …</small></label>
                    <input type="number" step="0.001" name="threshold"></div>
                <div class="form-group"><label>المعدل <span style="color:#c00">*</span></label>
                    <input type="number" step="0.001" min="0.001" name="prate" required></div>
                <div class="form-group">
                    <label>أساس المعدل</label>
                    <select name="rate_basis">
                        <option value="per_unit">لكل وحدةِ عجز</option>
                        <option value="percent_of_base">نسبةٌ من الأساس</option>
                    </select>
                </div>
                <div class="form-group"><label>سقف الجزاء ٪ <small>— فارغٌ = بلا سقفٍ مكتوب (يُعلَن)</small></label>
                    <input type="number" step="0.01" min="0" max="100" name="cap_percent"></div>
                <div class="form-group">
                    <label>الدورية</label>
                    <select name="periodicity">
                        <option value="monthly">شهري</option>
                        <option value="daily">يومي</option>
                        <option value="contract">للعقد</option>
                    </select>
                </div>
                <div class="form-group">
                    <label><input type="checkbox" name="inherits" value="1" checked>
                        يرث إسنادَ عقد العميل (CON-02)</label>
                </div>
                <div class="form-group"><label>سبب نقض الإسناد <small>— إلزاميٌّ متى نُقض</small></label>
                    <input type="text" name="override_reason" maxlength="255"></div>
                <div class="form-group"><label>توثيقُ الصيغة <small>— نصٌّ لا يُقيَّم</small></label>
                    <input type="text" name="formula_note" maxlength="255"></div>
                <div class="form-group"><label>سريان من <span style="color:#c00">*</span></label>
                    <input type="date" name="pvalid_from" required></div>
                <div class="form-group"><label>سريان إلى</label><input type="date" name="pvalid_to"></div>
            </div>
            <div style="margin-top:12px"><button type="submit" class="btn-save"><i class="fa fa-save"></i> حفظ القاعدة</button></div>
        </form>
    </div></div>
    <?php endif; ?>

    <div class="card"><div class="card-header"><h5><i class="fa fa-list"></i> قواعدُ الجزاء</h5></div>
    <div class="card-body"><div class="table-container">
        <table class="alltables display nowrap" style="width:100%">
            <thead><tr><th>النوع</th><th>العتبة</th><th>المعدل</th><th>الأساس</th>
                <th>السقف</th><th>الإسناد</th><th>السريان</th><th>الصيغة</th></tr></thead>
            <tbody>
            <?php foreach ($penaltyRules as $r): ?>
                <tr>
                    <td><?php echo htmlspecialchars(SRS::PENALTY_LABELS[$r['kind']] ?? $r['kind']); ?></td>
                    <td><?php echo $r['threshold'] !== null ? htmlspecialchars((string)$r['threshold']) : '—'; ?></td>
                    <td><?php echo htmlspecialchars((string)$r['rate']); ?></td>
                    <td><?php echo (string)$r['rate_basis'] === 'percent_of_base' ? 'نسبةٌ من الأساس' : 'لكل وحدة'; ?></td>
                    <td><?php echo $r['cap_percent'] !== null ? htmlspecialchars((string)$r['cap_percent']) . '٪'
                        : '<span class="badge badge-warning">بلا سقف</span>'; ?></td>
                    <td><?php if (intval($r['inherits_attribution']) === 1): ?>
                            <span class="badge badge-success">يرث</span>
                        <?php else: ?>
                            <span class="badge badge-danger"
                                  title="<?php echo htmlspecialchars((string)$r['override_reason']); ?>">ينقض بسببه</span>
                        <?php endif; ?></td>
                    <td><?php echo htmlspecialchars((string)$r['valid_from']); ?>
                        → <?php echo htmlspecialchars((string)($r['valid_to'] ?? 'مفتوح')); ?></td>
                    <td><small><?php echo htmlspecialchars((string)($r['formula_note'] ?? '')); ?></small></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div></div></div>
</div>

<script src="../includes/js/jquery-3.7.1.main.js"></script>
</body>
</html>
