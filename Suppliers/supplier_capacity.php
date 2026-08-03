<?php
/**
 * Suppliers/supplier_capacity.php — الطاقةُ والجاهزيةُ ومهلةُ الإحلال (M-16)
 * ───────────────────────────────────────────────────────────────────────────
 * CON-03 §5: «إعدادات داخل العقد: **صفٌّ لكل معدةٍ بطاقتها النظرية ونسبةِ
 * الجاهزية الدنيا ومهلةِ الإحلال** · **تحذيرُ الأثر على الجزاءات**».
 * §3: القياسُ **من سجل الزمن الموحّد** لا من تقديرٍ لاحق.
 *
 * كلُّ حفظٍ عبر `SupplierCapacityService` — فحارسُ «طاقةٌ موجبةٌ إلزامًا» يسري
 * من الشاشة ومن أي مستدعٍ آخر معًا.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}
include '../config.php';
require_once __DIR__ . '/../app/Services/Contract/SupplierCapacityService.php';

use App\Services\Contract\SupplierCapacityService as SCAP;

$current_role   = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$company_id     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$uid            = isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0;

if (!$is_super_admin && $company_id <= 0) {
    header("Location: ../login.php?msg=لا+توجد+بيئة+شركة+صالحة+للمستخدم+❌");
    exit();
}

$MODULE_CODE = 'Suppliers/supplier_capacity.php';
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
    header("Location: ../main/dashboard.php?msg=لا+توجد+صلاحية+عرض+الطاقة+والجاهزية+❌");
    exit();
}

$gate = $is_super_admin ? ems_tenant_db()->forAllTenants('supplier capacity super') : ems_tenant_db();

$selected = intval($_GET['contract_id'] ?? 0);
$redirect = function ($msg, $cid) { header("Location: supplier_capacity.php?contract_id=" . intval($cid)
    . "&msg=" . rawurlencode($msg)); exit(); };

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cid = intval($_POST['contract_id'] ?? 0);
    if (!$can_add && !$can_edit) { $redirect('لا توجد صلاحية لهذا الإجراء ❌', $cid); }
    if (strval($_POST['cap_action'] ?? '') === 'capacity') {
        $r = SCAP::saveCapacity($conn, $gate, $company_id, $cid, array(
            'equipment_id'          => $_POST['equipment_id'] ?? 0,
            'work_model'            => $_POST['work_model'] ?? 'hour',
            'theoretical_daily'     => $_POST['theoretical_daily'] ?? '',
            'min_readiness_percent' => $_POST['min_readiness_percent'] ?? '',
            'replace_hours'         => $_POST['replace_hours'] ?? '',
            'valid_from'            => $_POST['valid_from'] ?? '',
            'valid_to'              => $_POST['valid_to'] ?? '',
            'note'                  => $_POST['note'] ?? '',
        ), $uid);
        $redirect($r['ok'] ? 'حُفظت بطاقةُ الطاقة ✅' : ($r['code'] . ' — ' . $r['reason'] . ' ❌'), $cid);
    }
}

// ── القراءة ────────────────────────────────────────────────────────────────
$contracts = array();
try {
    $contracts = $gate->scopedQuery(array(
        'scope'  => array('h' => 'supplier_contracts'),
        'enrich' => array('s' => 'suppliers'),
    ), "SELECT h.id, h.supplier_id, h.state, h.start_date, h.end_date, h.currency, s.name AS supplier_name
          FROM supplier_contracts h
          LEFT JOIN suppliers s ON s.id = h.supplier_id
         WHERE {TENANT_SCOPE} AND COALESCE(h.is_deleted,0)=0
         ORDER BY h.id DESC");
} catch (\Throwable $t) { $contracts = array(); }
if ($selected <= 0 && $contracts) { $selected = intval($contracts[0]['id']); }

$head = null;
foreach ($contracts as $c) { if (intval($c['id']) === $selected) { $head = $c; break; } }

$cards = $selected > 0 ? SCAP::capacityOf($gate, $selected) : array();

$equipments = array();
try {
    $equipments = $gate->scopedQuery(array('scope' => array('e' => 'equipments')),
        "SELECT e.id, e.name, e.code FROM equipments e WHERE {TENANT_SCOPE} ORDER BY e.name");
} catch (\Throwable $t) { $equipments = array(); }

// لوحةُ القياس — «كلُّ رقمٍ ينقر لمصدره» (§5)
$mFrom = isset($_GET['from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from'])
         ? $_GET['from'] : date('Y-m-01');
$mTo   = isset($_GET['to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to'])
         ? $_GET['to'] : date('Y-m-t');
$measure = ($head !== null)
           ? SCAP::measure($gate, intval($head['supplier_id']), $mFrom, $mTo)
           : array('equipment' => array(), 'readiness' => null, 'contract_min' => null,
                   'planned_hours' => 0, 'unfit_hours' => 0, 'unlogged_hours' => 0,
                   'coverage_hours' => 0, 'notes' => array());

$page_title = 'إيكوبيشن | الطاقة والجاهزية ومهلة الإحلال';
include '../inheader.php';
include '../insidebar.php';
// NAV-01 §8 (update0006-b): الشاشةُ قسمٌ من ملف المورد الأم
$sf_supplier_id = intval($_GET['supplier_id'] ?? $_GET['id'] ?? 0); $sf_active = 'capacity';
if ($sf_supplier_id > 0) include __DIR__ . '/../includes/supplier_file_tabs.php';
?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'الطاقة والجاهزية ومهلة الإحلال'; $header_icon = 'fa fa-gauge-high';
    $header_actions = array();
    $header_back = array('href' => 'supplier_rules.php', 'class' => '',
                         'icon' => 'fas fa-arrow-right', 'label' => 'قواعد التحميل والجزاء');
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
            <label>القياس من</label><input type="date" name="from" value="<?php echo htmlspecialchars($mFrom); ?>">
            <label>إلى</label><input type="date" name="to" value="<?php echo htmlspecialchars($mTo); ?>">
            <button type="submit" class="btn-save"><i class="fa fa-magnifying-glass"></i> اقرأ القياس</button>
        </form>
        <p style="color:#a15c00;margin-top:10px;background:#fff8e6;padding:10px;border-radius:6px">
            <i class="fa fa-triangle-exclamation"></i>
            <strong>تحذيرُ الأثر على الجزاءات:</strong> ما يُكتب هنا <strong>يفعّل مالًا</strong> —
            <strong>نسبةُ الجاهزية الدنيا</strong> هي بوابةُ تفعيل جزاء الجاهزية («نقصُها عن الحد التعاقدي
            يفعّل الجزاءَ بقاعدته» §3)، و<strong>مهلةُ الإحلال</strong> <strong>تنقل</strong> ساعاتِ التوقف
            الزائدةَ من جزاء الجاهزية إلى <strong>جزاء التغطية الأشد</strong> — نقلًا لا إضافةً:
            الساعةُ تُجزى مرةً واحدة. والمبلغُ يُحتسب بقاعدة الجزاء المكتوبة، و<strong>بلا قاعدةٍ لا جزاء</strong>.
        </p>
    </div></div>

    <?php if ($can_add): ?>
    <div class="card"><div class="card-header"><h5><i class="fa fa-gauge-high"></i> بطاقةُ طاقةٍ لمعدة</h5></div>
    <div class="card-body">
        <p style="color:#666">«لكل معدةٍ مخصَّصةٍ <strong>طاقةٌ نظريةٌ يوميةٌ بنموذجها</strong> …
            <strong>تُثبَّت في العقد</strong> — ومنها يُقاس أداءُ المورد <strong>لا من تقديرٍ لاحق</strong>» (§3).</p>
        <form method="post" class="ems-form">
            <input type="hidden" name="cap_action" value="capacity">
            <input type="hidden" name="contract_id" value="<?php echo $selected; ?>">
            <div class="form-grid">
                <div class="form-group">
                    <label>المعدة <span style="color:#c00">*</span></label>
                    <select name="equipment_id" required>
                        <?php foreach ($equipments as $e): ?>
                            <option value="<?php echo intval($e['id']); ?>">
                                <?php echo htmlspecialchars((string)$e['name']); ?>
                                <?php echo $e['code'] !== null && $e['code'] !== '' ? ' — ' . htmlspecialchars((string)$e['code']) : ''; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>نموذج الطاقة <span style="color:#c00">*</span></label>
                    <select name="work_model" required>
                        <?php foreach (SCAP::WORK_MODEL_LABELS as $k => $lbl): ?>
                            <option value="<?php echo $k; ?>"><?php echo $lbl; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label>الطاقة النظرية اليومية <span style="color:#c00">*</span></label>
                    <input type="number" step="0.01" min="0.01" name="theoretical_daily" required></div>
                <div class="form-group"><label>نسبة الجاهزية الدنيا ٪ <small>— فارغٌ = لم يُشترط (يُعلَن)</small></label>
                    <input type="number" step="0.01" min="0" max="100" name="min_readiness_percent"></div>
                <div class="form-group"><label>مهلة الإحلال (ساعات) <small>— فارغٌ = لا مهلةَ مكتوبة</small></label>
                    <input type="number" step="1" min="1" name="replace_hours"></div>
                <div class="form-group"><label>سريان من <span style="color:#c00">*</span></label>
                    <input type="date" name="valid_from" required></div>
                <div class="form-group"><label>سريان إلى</label><input type="date" name="valid_to"></div>
                <div class="form-group"><label>ملاحظة</label><input type="text" name="note" maxlength="255"></div>
            </div>
            <div style="margin-top:12px"><button type="submit" class="btn-save"><i class="fa fa-save"></i> حفظ البطاقة</button></div>
        </form>
    </div></div>
    <?php endif; ?>

    <div class="card"><div class="card-header"><h5><i class="fa fa-list"></i> صفٌّ لكل معدة</h5></div>
    <div class="card-body"><div class="table-container">
        <table class="alltables display nowrap" style="width:100%">
            <thead><tr><th>المعدة</th><th>النموذج</th><th>الطاقة اليومية</th>
                <th>الجاهزية الدنيا</th><th>مهلة الإحلال</th><th>السريان</th><th>الحالة</th><th>ملاحظة</th>
                <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
                <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
                <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
                <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
                <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
                <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
                <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
                <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
                <th class="ems-gov-th" data-gov="idem_key" data-slice="2" title="يمنع وقوع الأثر مرتين بمفتاح مركب">مفتاح منع التكرار</th>
                <th class="ems-gov-th" data-gov="reversed_by" data-slice="2" title="مرجع الحركة التي عكسته">معكوس بـ</th>
                <th class="ems-gov-th" data-gov="reversal_of" data-slice="2" title="مرجع الحركة التي عكسها">عكس عن</th>
                <th class="ems-gov-th" data-gov="impact_grade" data-slice="2" title="مبدئي أم نهائي — فلا يقفل مبدئي ماليًّا">درجة الأثر</th>
                <th class="ems-gov-th" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
                <th class="ems-gov-th" data-gov="cost_center" data-slice="3" title="وجهة التحميل">مركز التكلفة</th>
                <th class="ems-gov-th" data-gov="fx_rate_source" data-slice="3" title="ما خالف عملة الدفاتر يحمل السعر ومصدره">سعر الصرف ومصدره</th>
                <th class="ems-gov-th none" data-gov="currency" data-slice="3" title="لا مبلغ بلا عملة">العملة</th>
                </tr></thead>
            <tbody>
            <?php foreach ($cards as $r): ?>
                <tr>
                    <td><?php echo htmlspecialchars((string)($r['equipment_name'] ?? ('#' . intval($r['equipment_id'])))); ?></td>
                    <td><?php echo htmlspecialchars(SCAP::WORK_MODEL_LABELS[$r['work_model']] ?? $r['work_model']); ?></td>
                    <td><?php echo htmlspecialchars((string)$r['theoretical_daily']); ?></td>
                    <td><?php echo $r['min_readiness_percent'] !== null
                        ? htmlspecialchars((string)$r['min_readiness_percent']) . '٪'
                        : '<span class="badge badge-warning">لم يُشترط — لا جزاءَ جاهزية</span>'; ?></td>
                    <td><?php echo $r['replace_hours'] !== null
                        ? htmlspecialchars((string)$r['replace_hours']) . ' ساعة'
                        : '<span class="badge badge-secondary">بلا مهلةٍ مكتوبة</span>'; ?></td>
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

    <div class="card"><div class="card-header"><h5><i class="fa fa-chart-line"></i>
        قياسُ الجاهزية <?php echo htmlspecialchars($mFrom); ?> → <?php echo htmlspecialchars($mTo); ?></h5></div>
    <div class="card-body">
        <p style="color:#666">
            الجاهزية٪ = <strong>(الزمنُ المخطط − زمنُ عدمِ الصلاحية) ÷ الزمنِ المخطط</strong> —
            تُقرأ من <strong>سجل الزمن الموحّد</strong> (§3). والزمنُ المخطط يستثني الوقفةَ المخططةَ والقوةَ
            القاهرة، وغيرُ المسجَّل <strong>يُعلَن عددًا مستقلًّا</strong> ولا يُطرح صامتًا.
        </p>
        <div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:12px">
            <div class="badge <?php echo ($measure['readiness'] !== null && $measure['contract_min'] !== null
                && $measure['readiness'] < $measure['contract_min']) ? 'badge-danger' : 'badge-success'; ?>"
                 style="font-size:15px;padding:8px 14px">
                الجاهزية: <?php echo $measure['readiness'] !== null
                    ? htmlspecialchars((string)$measure['readiness']) . '٪' : 'لا قياس'; ?>
            </div>
            <div class="badge badge-secondary" style="font-size:15px;padding:8px 14px">
                الحد التعاقدي: <?php echo $measure['contract_min'] !== null
                    ? htmlspecialchars((string)$measure['contract_min']) . '٪' : 'غير مشترط'; ?>
            </div>
            <div class="badge badge-secondary" style="font-size:15px;padding:8px 14px">
                الزمن المخطط: <?php echo htmlspecialchars((string)$measure['planned_hours']); ?> ساعة
            </div>
            <div class="badge badge-secondary" style="font-size:15px;padding:8px 14px">
                غير صالح للعمل: <?php echo htmlspecialchars((string)$measure['unfit_hours']); ?> ساعة
            </div>
            <div class="badge badge-warning" style="font-size:15px;padding:8px 14px">
                نُقل إلى التغطية: <?php echo htmlspecialchars((string)$measure['coverage_hours']); ?> ساعة
            </div>
            <div class="badge badge-warning" style="font-size:15px;padding:8px 14px">
                غير مسجَّل: <?php echo htmlspecialchars((string)$measure['unlogged_hours']); ?> ساعة
            </div>
        </div>
        <?php foreach ($measure['notes'] as $n): ?>
            <div class="alert alert-info" style="margin:6px 0"><?php echo htmlspecialchars($n); ?></div>
        <?php endforeach; ?>
        <div class="table-container">
        <table class="alltables display nowrap" style="width:100%">
            <thead><tr><th>المعدة</th><th>الزمن المخطط</th><th>غير صالح</th><th>نُقل للتغطية</th>
                <th>غير مسجَّل</th><th>الجاهزية</th><th>الحد</th><th>النوب</th></tr></thead>
            <tbody>
            <?php foreach ($measure['equipment'] as $m): ?>
                <tr>
                    <td><?php echo htmlspecialchars((string)($m['equipment_name'] !== '' ? $m['equipment_name']
                        : ('#' . $m['equipment_id']))); ?></td>
                    <td><?php echo htmlspecialchars((string)$m['planned_hours']); ?></td>
                    <td><?php echo htmlspecialchars((string)$m['unfit_hours']); ?>
                        <?php if ($m['unfit_raw'] != $m['unfit_hours']): ?>
                            <small style="color:#a15c00">(الخام <?php echo htmlspecialchars((string)$m['unfit_raw']); ?>)</small>
                        <?php endif; ?></td>
                    <td><?php echo htmlspecialchars((string)$m['coverage_hours']); ?></td>
                    <td><?php echo htmlspecialchars((string)$m['unlogged_hours']); ?></td>
                    <td><?php echo $m['readiness'] !== null
                        ? htmlspecialchars((string)$m['readiness']) . '٪' : '—'; ?></td>
                    <td><?php echo $m['min_readiness'] !== null
                        ? htmlspecialchars((string)$m['min_readiness']) . '٪' : '—'; ?></td>
                    <td><small><?php
                        $eps = array();
                        foreach ($m['episodes'] as $ep) {
                            $eps[] = $ep['from'] . '→' . $ep['to'] . ': ' . $ep['hours'] . 'س'
                                   . ($ep['converted'] > 0 ? (' (نُقل ' . $ep['converted'] . ')') : '');
                        }
                        echo htmlspecialchars($eps ? implode(' · ', $eps) : '—');
                    ?></small></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div></div>
</div>

<script src="../includes/js/jquery-3.7.1.main.js"></script>
</body>
</html>
