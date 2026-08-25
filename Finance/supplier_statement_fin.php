<?php
/**
 * Finance/supplier_statement_fin.php — كشف حساب المورد الشهري (§12.5).
 * ★ تجميع قراءة فقط ★ — بنود المورد له/عليه من fin_dues + مدفوعاته من fin_payments
 * + وحداته المعتمدة من fin_unit_records، بالفترة المختارة. الصافي وحالة التسوية.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/fin_helpers.php';

$ctx = fin_ctx();
$is_super_admin = $ctx['is_super']; $company_id = $ctx['company_id']; $current_user_id = $ctx['user_id'];
if (!$is_super_admin && $company_id <= 0) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد بيئة شركة صالحة ❌', 'GOV-SCOPE-403', ''); exit(); }

$perms = fin_page_perms($conn, 'Finance/supplier_statement_fin.php', $is_super_admin);
if (!$perms['can_view']) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية العرض ❌', 'GOV-PERM-403', ''); exit(); }
$cid = intval($company_id);

$sel_sup = isset($_GET['sup']) ? intval($_GET['sup']) : 0;

// H-20: كشفُ المشرف كشفُ موردِه حصرًا — طلبُ غيرِه 403 مسجَّلة، وغيابُ
// المعامل يُحقن بمورده (لا يفتح فارغًا على قائمة الكل)
require_once __DIR__ . '/../app/Services/Portal/SupplierPortalGuard.php';
$spg_scope = \App\Services\Portal\SupplierPortalGuard::enforce($conn, $_SESSION['user'], $sel_sup, 'Finance/supplier_statement_fin.php');
if ($spg_scope !== null) { $sel_sup = $spg_scope; }

$sel_period = isset($_GET['period']) && preg_match('/^\d{4}-\d{2}$/', $_GET['period']) ? $_GET['period'] : date('Y-m');
$due_types = fin_due_types(); $settle_states = fin_settlement_states(); $work_models = fin_work_models();

$sup_name = ''; $credit_sum = 0; $debit_sum = 0; $paid_sum = 0; $units_sum = 0;
// مجموعٌ مُعزَّلٌ عبر scopedQuery (§10): الرمز يحقن company_id، والفلاتر بمعاملات ?
$fin_ssum = function ($alias, $table, $sql, $params) use ($is_super_admin) {
    $rows = fin_gate($is_super_admin)->scopedQuery(array('scope' => array($alias => $table)), $sql, $params);
    return $rows ? (float) $rows[0]['v'] : 0.0;
};
if ($sel_sup > 0) {
    // قراءة الاسم عبر البوابة: includeDeleted لأن الأصل بلا فلتر soft (يعرض اسم
    // المورد المؤرشف — وفاءٌ ذهبي للسلوك، درس الموردين المؤرشفين في M2b). العزل
    // بالشركة تشديدٌ موثَّق (كان بلا فلتر شركة).
    $svrow = fin_gate($is_super_admin)->selectOne('suppliers', array(
        'columns' => array('name'), 'where' => array('id' => $sel_sup), 'includeDeleted' => true));
    $sup_name = $svrow ? $svrow['name'] : ('#' . $sel_sup);
    $credit_sum = $fin_ssum('d', 'fin_dues',
        "SELECT COALESCE(SUM(d.amount),0) v FROM fin_dues d WHERE {TENANT_SCOPE}
         AND d.party_type='supplier' AND d.party_ref=? AND COALESCE(d.is_deleted,0)=0 AND d.direction='credit'
         AND (d.period_ref=? OR DATE_FORMAT(d.created_at,'%Y-%m')=?)",
        array($sel_sup, $sel_period, $sel_period));
    $debit_sum = $fin_ssum('d', 'fin_dues',
        "SELECT COALESCE(SUM(d.amount),0) v FROM fin_dues d WHERE {TENANT_SCOPE}
         AND d.party_type='supplier' AND d.party_ref=? AND COALESCE(d.is_deleted,0)=0 AND d.direction='debit'
         AND (d.period_ref=? OR DATE_FORMAT(d.created_at,'%Y-%m')=?)",
        array($sel_sup, $sel_period, $sel_period));
    $paid_sum = $fin_ssum('p', 'fin_payments',
        "SELECT COALESCE(SUM(p.amount),0) v FROM fin_payments p WHERE {TENANT_SCOPE}
         AND p.party_type='supplier' AND p.party_ref=? AND p.direction='disbursement'
         AND p.state IN('executed','reconciled') AND COALESCE(p.is_deleted,0)=0
         AND DATE_FORMAT(COALESCE(p.paid_at,p.created_at),'%Y-%m')=?",
        array($sel_sup, $sel_period));
    $units_sum = $fin_ssum('u', 'fin_unit_records',
        "SELECT COALESCE(SUM(u.approved_qty),0) v FROM fin_unit_records u WHERE {TENANT_SCOPE}
         AND u.supplier_entity_id=? AND u.match_state='approved' AND COALESCE(u.is_deleted,0)=0
         AND DATE_FORMAT(u.record_date,'%Y-%m')=?",
        array($sel_sup, $sel_period));
}
$net = $credit_sum - $debit_sum;

// ── M-14 · الكشفُ بطبقاته وكلُّ رقمٍ برابط مصدره (ENT-02 §6) ───────────────
// المجاميعُ أعلاه بقيت كما هي (لا كسرَ لمسارٍ قائم)، والطبقاتُ تُضاف تحتها:
// «قراءةُ المورد كشفَه ففهمُه **بندًا بندًا حتى مستنده**» (§7-القبول).
require_once __DIR__ . '/../app/Services/Settlement/SupplierStatementService.php';
$stmt_from = $sel_period . '-01';
$stmt_to   = date('Y-m-t', strtotime($stmt_from));
$stmt = array('layers' => array(), 'totals' => array(), 'orphans' => 0);
$price_snapshot = array();
$adv_balance = 0.0;
if ($sel_sup > 0) {
    $stmt = \App\Services\Settlement\SupplierStatementService::build(
        fin_gate($is_super_admin), $sel_sup, $stmt_from, $stmt_to);
    $price_snapshot = \App\Services\Settlement\SupplierStatementService::priceSnapshot(
        fin_gate($is_super_admin), $sel_sup, $stmt_to);
    $adv_balance = \App\Services\Settlement\SupplierStatementService::openAdvanceBalance(
        fin_gate($is_super_admin), $sel_sup);
}

$page_title = 'إيكوبيشن | كشف حساب المورد';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
// NAV-01 §8 (update0006-b): الشاشةُ قسمٌ من ملف المورد الأم
$sf_supplier_id = intval($_GET['supplier_id'] ?? $_GET['id'] ?? 0); $sf_active = 'statement';
if ($sf_supplier_id > 0) include __DIR__ . '/../includes/supplier_file_tabs.php';
?>
<div class="main fin-supstmt-main ems-unified-page-shell">
    <?php
    $header_title = 'كشف حساب المورد الشهري'; $header_icon = 'fa fa-file-contract';
    $header_actions = array();
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    // UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
    echo ems_states_bundle('لا بنود لهذا المورد في الفترة المختارة', 'بدل الشهر أو اختر موردا آخر من قائمة الأعلى');
    ?>
    <?php /* نُقلت أنماطُ هذه الشاشةِ إلى assets/css/ems-screens.css (UXUI-01 البند ٦: صفرُ نمطٍ محليّ) */ ?>

    <div class="card"><div class="card-body">
                <!-- صندوقُ الفلاترِ الموحَّد — التصميمُ في assets/css/ems-filters.css -->
        <div class="filter">
            <div class="filter-title"><span class="filter-title-icon"><i class="fa-solid fa-sliders"></i></span> فلاتر البحث</div>
            <div class="filter-body">
        <form method="get" class="fin-sup-filter">
            <strong><i class="fas fa-truck-field"></i> المورد:</strong>
            <select name="sup" aria-label="المورد المعروض كشف حسابه" class="fin-sup-select" onchange="this.form.submit()"><?php
                // H-20: المقيَّدُ يرى خيارَ موردِه وحده — لا تسريبَ لأسماء بقية الموردين
                if ($spg_scope !== null) {
                    echo '<option value="' . intval($spg_scope) . '" selected>' . htmlspecialchars($sup_name !== '' ? $sup_name : ('#' . intval($spg_scope))) . '</option>';
                } else {
                    echo fin_supplier_options($conn, $is_super_admin, $company_id, $sel_sup);
                }
            ?></select>
            <strong>الفترة:</strong>
            <input type="month" name="period" aria-label="شهر كشف الحساب" onchange="this.form.submit()" value="<?php echo htmlspecialchars($sel_period); ?>">
        </form>
            </div>
        </div>
    </div></div>

    <?php if ($sel_sup > 0): ?>
    <div class="card"><div class="card-body">
        <h5 class="fin-sup-h5"><i class="fas fa-file-invoice"></i> كشف <?php echo htmlspecialchars($sup_name); ?> — <?php echo htmlspecialchars($sel_period); ?></h5>
        <div class="form-grid">
            <div class="card fin-sup-kpi"><div class="card-body"><div class="text-muted">له (مستحقات)</div><div class="fin-sup-kpi-value fin-sup-ok"><?php echo number_format($credit_sum, 2); ?></div></div></div>
            <div class="card fin-sup-kpi"><div class="card-body"><div class="text-muted">عليه (سلف/خصومات/استرداد)</div><div class="fin-sup-kpi-value fin-sup-neg">(<?php echo number_format($debit_sum, 2); ?>)</div></div></div>
            <div class="card fin-sup-kpi"><div class="card-body"><div class="text-muted">الصافي</div><div class="fin-sup-kpi-value"><span class="badge badge-<?php echo $net >= 0 ? 'primary' : 'danger'; ?>"><?php echo number_format($net, 2); ?></span></div></div></div>
            <div class="card fin-sup-kpi"><div class="card-body"><div class="text-muted">المصروف له بالفترة</div><div class="fin-sup-kpi-value"><?php echo number_format($paid_sum, 2); ?></div></div></div>
            <div class="card fin-sup-kpi"><div class="card-body"><div class="text-muted">وحداته المعتمدة بالفترة</div><div class="fin-sup-kpi-value"><?php echo number_format($units_sum, 2); ?></div></div></div>
        </div>

        <?php // ── M-14 · الطبقاتُ الخمسُ بروابط مصادرها ─────────────────── ?>
        <h5 class="fin-sup-h5-mid"><i class="fas fa-layer-group"></i>
            الكشف بطبقاته — <small class="fin-sup-hint">كل رقم ينقر إلى مصدره</small>
            <?php if ($adv_balance > 0): ?>
                <span class="badge badge-warning fin-sup-badge-gap"
                      title="رصيد السلف المفتوح — ظاهر في بطاقته دائما (ENT-02 §3)">
                    رصيد سلف مفتوح: <?php echo number_format($adv_balance, 2); ?></span>
            <?php endif; ?>
            <?php if (intval($stmt['orphans']) > 0): ?>
                <span class="badge badge-danger fin-sup-badge-gap">
                    <?php echo intval($stmt['orphans']); ?> سطرا بلا مصدر — يعلن ولا يخفى</span>
            <?php endif; ?>
        </h5>
        <?php foreach ($stmt['layers'] as $lkey => $layer):
            if (!$layer['rows']) { continue; } ?>
            <div class="card fin-sup-layer"><div class="card-body">
                <strong><?php echo htmlspecialchars($layer['label']); ?></strong>
                — <span class="fin-sup-layer-total"><?php echo number_format((float) $layer['total'], 2); ?></span>
                <div class="table-container fin-sup-layer-wrap">
                <table class="alltables no-datatable fin-sup-tbl" data-no-dt="hard">
                    <thead><tr><th>تاريخ مطابقة المورد</th><th>البيان</th><th>المبلغ</th><th>المصدر</th><th>السياق</th></tr></thead>
                    <tbody>
                    <?php foreach ($layer['rows'] as $row): ?>
                        <tr<?php echo !empty($row['objected']) ? ' class="fin-sup-objected"' : ''; ?>>
                            <td><?php echo htmlspecialchars((string) $row['date']); ?></td>
                            <td><?php echo htmlspecialchars((string) $row['description']); ?>
                                <?php if (!empty($row['objected'])): ?>
                                    <span class="badge badge-danger">معترض</span>
                                <?php endif; ?></td>
                            <td class="fin-sup-amt<?php echo ((float) $row['amount'] < 0) ? ' fin-sup-neg' : ''; ?>">
                                <?php echo ((float) $row['amount'] == 0.0 && $lkey === 'advances')
                                    ? '—' : number_format((float) $row['amount'], 2); ?>
                                <small><?php echo htmlspecialchars((string) $row['currency']); ?></small></td>
                            <td>
                                <?php if ($row['orphan']): ?>
                                    <span class="badge badge-danger" title="رقم بلا مصدر — يعلن ليصلح">بلا مصدر</span>
                                <?php elseif ($row['link'] !== null): ?>
                                    <a href="<?php echo htmlspecialchars($row['link']); ?>">
                                        <?php echo htmlspecialchars($row['source_kind'] . '#' . $row['source_ref']); ?></a>
                                <?php else: ?>
                                    <small><?php echo htmlspecialchars($row['source_kind'] . '#' . $row['source_ref']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><small><?php echo htmlspecialchars((string) $row['context']); ?></small></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            </div></div>
        <?php endforeach; ?>
        <?php if (!empty($stmt['totals'])): ?>
        <p class="fin-sup-net">
            صافي الفترة (استحقاق − تحميلات − جزاءات):
            <strong><?php echo number_format((float) $stmt['totals']['net'], 2); ?></strong>
            · بعد السداد:
            <strong><?php echo number_format((float) $stmt['totals']['balance'], 2); ?></strong>
        </p>
        <?php endif; ?>

        <?php // ── «تبويبُ اللقطة يعرض الأسعارَ التي احتُسب بها» (§6) ────── ?>
        <h5 class="fin-sup-h5-sec"><i class="fas fa-tags"></i> اللقطة — الأسعار التي احتسب بها</h5>
        <div class="table-container">
            <table class="alltables no-datatable fin-sup-tbl" data-no-dt="hard">
                <thead><tr><th>العقد</th><th>النموذج</th><th>الوحدة</th><th>سعر الوحدة</th>
                    <th>أساس الاستعداد</th><th>سريان البند</th></tr></thead>
                <tbody>
                <?php if (!$price_snapshot): ?>
                    <tr><td colspan="6" class="fin-sup-empty-cell">
                        لا عقد مورد نافذا بتاريخ <?php echo htmlspecialchars($stmt_to); ?> —
                        <strong>يعلن ولا تخترع أسعار</strong>.</td></tr>
                <?php endif; ?>
                <?php foreach ($price_snapshot as $ps): ?>
                    <tr>
                        <td>#<?php echo intval($ps['contract_id']); ?>
                            <small>(<?php echo htmlspecialchars((string) $ps['state']); ?>)</small></td>
                        <td><?php echo htmlspecialchars((string) ($ps['work_model'] ?? '—')); ?></td>
                        <td><?php echo htmlspecialchars((string) ($ps['unit'] ?? '—')); ?></td>
                        <td><?php echo $ps['unit_price'] !== null
                            ? number_format((float) $ps['unit_price'], 2) : '—'; ?>
                            <small><?php echo htmlspecialchars((string) ($ps['currency'] ?? '')); ?></small></td>
                        <td><?php echo htmlspecialchars((string) ($ps['standby_basis'] ?? '—')); ?>
                            <?php echo $ps['standby_rate'] !== null
                                ? (' · ' . htmlspecialchars((string) $ps['standby_rate'])) : ''; ?></td>
                        <td><?php echo htmlspecialchars((string) ($ps['valid_from'] ?? '—')); ?>
                            → <?php echo htmlspecialchars((string) ($ps['valid_to'] ?? 'مفتوح')); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <h5 class="fin-sup-h5-sec"><i class="fas fa-list"></i> بنود الكشف (له / عليه)</h5>
        <div class="table-container">
            <table id="finTable" class="display nowrap alltables fin-sup-tbl" data-state-save="false">
                <thead><tr><th>التاريخ</th><th>النوع</th><th>الاتجاه</th><th>المبلغ</th><th>التسوية</th><th>الفترة</th></tr></thead>
                <tbody>
                <?php
                // القائمة عبر البوابة: العزل آلي + فلتر soft آلي (يطابق COALESCE الأصلي)
                $due_rows = fin_gate($is_super_admin)->select('fin_dues', array(
                    'where' => array('party_type' => 'supplier', 'party_ref' => $sel_sup),
                    'whereRaw' => "(period_ref = ? OR DATE_FORMAT(created_at,'%Y-%m') = ?)",
                    'params' => array($sel_period, $sel_period),
                    'orderBy' => 'id DESC',
                ));
                { foreach ($due_rows as $row) {
                    $ss = (string)$row['settlement_state'];
                    $tone = $ss === 'paid' ? 'success' : ($ss === 'settled' ? 'primary' : 'secondary');
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars(substr((string)$row['created_at'], 0, 10)) . "</td>";
                    echo "<td>" . htmlspecialchars($due_types[$row['due_type']] ?? $row['due_type']) . "</td>";
                    echo "<td>" . ($row['direction'] === 'credit' ? "<span class='badge badge-success'>له</span>" : "<span class='badge badge-danger'>عليه</span>") . "</td>";
                    echo "<td>" . number_format((float)$row['amount'], 2) . "</td>";
                    echo "<td><span class='badge badge-" . $tone . "'>" . htmlspecialchars($settle_states[$ss] ?? $ss) . "</span></td>";
                    echo "<td>" . htmlspecialchars((string)($row['period_ref'] ?? '—')) . "</td>";
                    echo "</tr>";
                } }
                ?>
                </tbody>
            </table>
        </div>

        <h5 class="fin-sup-h5-sec"><i class="fas fa-cubes"></i> وحداته المعتمدة بالفترة (من التطابق الثلاثي)</h5>
        <div class="table-container">
            <table class="alltables fin-sup-tbl">
                <thead><tr><th>رقم الكشف</th><th>التاريخ</th><th>النموذج</th><th>الكمية المعتمدة</th><th>سعر عقده</th><th>قيمة مستحقه</th>
              <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
              <th class="ems-fn-th" data-fn="1">المورد</th>
              <th class="ems-fn-th" data-fn="1">الشهر</th>
              <th class="ems-fn-th" data-fn="1">رصيد أول المدة</th>
              <th class="ems-fn-th" data-fn="1">استحقاقات الشهر</th>
              <th class="ems-fn-th" data-fn="1">تحميلات علينا</th>
              <th class="ems-fn-th" data-fn="1">تحميلات على المورد</th>
              <th class="ems-fn-th" data-fn="1">جزاءات وحوافز</th>
              <th class="ems-fn-th" data-fn="1">مدفوعات الشهر</th>
              <th class="ems-fn-th" data-fn="1">رصيد آخر المدة</th>
              <th class="ems-fn-th" data-fn="1">حالة مطابقة المورد</th>
              <th class="ems-fn-th" data-fn="1">أصدره</th>
              <th class="ems-fn-th" data-fn="1">اعتمده</th>
              <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
              <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صف بلا كيان مالك">الكيان</th>
              <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمد — تفويض أو سلطة أصلية">مرجع التفويض</th>
              <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
              <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
              <th class="ems-gov-th none" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
              <th class="ems-gov-th none" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
              <th class="ems-gov-th none" data-gov="view_log" data-slice="2" title="من قرأ البيان الحساس ومتى">سجل الاطلاع</th>
              <th class="ems-gov-th none" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
              <th class="ems-gov-th none" data-gov="cost_center" data-slice="3" title="وجهة التحميل">مركز التكلفة</th>
              <th class="ems-gov-th none" data-gov="fx_rate_source" data-slice="3" title="ما خالف عملة الدفاتر يحمل السعر ومصدره">سعر الصرف ومصدره</th>
              <th class="ems-gov-th none" data-gov="currency" data-slice="3" title="لا مبلغ بلا عملة">العملة</th>
              </tr></thead>
                <tbody>
                <?php
                $ur_rows = fin_gate($is_super_admin)->select('fin_unit_records', array(
                    'where' => array('supplier_entity_id' => $sel_sup, 'match_state' => 'approved'),
                    'whereRaw' => "DATE_FORMAT(record_date,'%Y-%m') = ?",
                    'params' => array($sel_period),
                    'orderBy' => 'record_date DESC',
                ));
                { foreach ($ur_rows as $row) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars((string)$row['record_no']) . "</td>";
                    echo "<td>" . htmlspecialchars((string)$row['record_date']) . "</td>";
                    echo "<td>" . htmlspecialchars($work_models[$row['work_model']] ?? $row['work_model']) . "</td>";
                    echo "<td>" . number_format((float)$row['approved_qty'], 2) . "</td>";
                    echo "<td>" . number_format((float)$row['supplier_unit_price'], 2) . "</td>";
                    echo "<td><strong>" . number_format((float)$row['approved_qty'] * (float)$row['supplier_unit_price'], 2) . "</strong></td>";
                    echo "</tr>";
                } }
                ?>
                </tbody>
            </table>
        </div>
    </div></div>
    <?php else: ?>
    <div class="card"><div class="card-body"><p class="text-muted fin-sup-pick"><i class="fas fa-arrow-up"></i> اختر موردا لعرض كشفه.</p></div></div>
    <?php endif; ?>
</div>

<script src="/ems/assets/vendor/jquery-3.7.1.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/jquery.dataTables.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/dataTables.buttons.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/buttons.html5.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/buttons.print.min.js"></script>
<script src="/ems/assets/vendor/jszip/jszip.min.js"></script>
<script src="/ems/assets/vendor/pdfmake/pdfmake.min.js"></script>
<script src="/ems/assets/vendor/pdfmake/vfs_fonts.js"></script>
<!-- UXW-01 ⑤: التهيئةُ المحليةُ أُزيلت — المكوّنُ المركزيُّ في assets/js/ui-unification.js
     يلتقط #finTable آليًّا (تعريبٌ وأزرارُ تصدير)، وجداولُ الطبقاتِ واللقطةِ تبقى ساكنةً
     بـdata-no-dt="hard" كما كانت. -->
</body>
</html>
