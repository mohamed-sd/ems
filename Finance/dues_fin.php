<?php
/**
 * Finance/dues_fin.php — الذمم والمستحقات (fin_dues موحّد + fin_receivables) — §3.11-§3.13/§7.3.
 * محرك التسوية: صافي المورد = Σله − Σعليه؛ التسوية شرطٌ للصرف (يُفرض في شاشة المدفوعات).
 * شاشة مستقلة — عزل شركة + حذف ناعم. الأطراف تُقرأ قراءةً فقط من suppliers/employees/clients.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/fin_helpers.php';

$ctx = fin_ctx();
$is_super_admin = $ctx['is_super']; $company_id = $ctx['company_id']; $current_user_id = $ctx['user_id'];
if (!$is_super_admin && $company_id <= 0) { header("Location: ../login.php?msg=لا+توجد+بيئة+شركة+صالحة+❌"); exit(); }

$perms = fin_page_perms($conn, 'Finance/dues_fin.php', $is_super_admin);
$can_view = $perms['can_view']; $can_add = $perms['can_add']; $can_edit = $perms['can_edit']; $can_delete = $perms['can_delete'];
if (!$can_view) { header("Location: ../main/dashboard.php?msg=لا+توجد+صلاحية+عرض+الذمم+❌"); exit(); }

$company_scope_sql = fin_scope('company_id', $is_super_admin, $company_id);
$due_types = fin_due_types(); $settle_states = fin_settlement_states(); $party_types = fin_party_types();

// ── تسوية مورد (محرك التسوية) ──
if (isset($_GET['settle_supplier'])) {
    if (!$can_edit) { header("Location: dues_fin.php?msg=لا+توجد+صلاحية+التسوية+❌"); exit(); }
    if (!fin_verify_action_token()) { header("Location: dues_fin.php?msg=رمز+الحماية+غير+صالح+❌"); exit(); } // إصلاح #2
    if (!fin_can_perform($conn, $ctx['role'], 'treasurer')) { header("Location: dues_fin.php?msg=التسوية+تخصّ+أمين+الخزينة+فقط+❌"); exit(); } // فصل الواجبات
    $sid = intval($_GET['settle_supplier']);
    $net = fin_supplier_net($conn, $company_id, $sid);
    fin_gate($is_super_admin)->update('fin_dues',
        array('settlement_state' => 'settled'),
        array('party_type' => 'supplier', 'party_ref' => $sid),
        "settlement_state='pending' AND COALESCE(is_deleted,0)=0");
    header("Location: dues_fin.php?msg=تمت+تسوية+المورد+(صافي=" . number_format($net, 2) . ")+✅"); exit();
}

// ── حذف ناعم ──
if (isset($_GET['delete_due'])) {
    if (!$can_delete) { header("Location: dues_fin.php?msg=لا+توجد+صلاحية+حذف+❌"); exit(); }
    // حذف ناعم مشروط (غير المصروف فقط) — عبر update حفظًا لشرط الأصل
    $d = intval($_GET['delete_due']);
    fin_gate($is_super_admin)->update('fin_dues',
        array('is_deleted' => 1, 'deleted_at' => date('Y-m-d H:i:s'), 'deleted_by' => $current_user_id),
        array('id' => $d), "settlement_state<>'paid'");
    header("Location: dues_fin.php?msg=تم+حذف+المستحق+✅"); exit();
}
if (isset($_GET['delete_recv'])) {
    if (!$can_delete) { header("Location: dues_fin.php?msg=لا+توجد+صلاحية+حذف+❌"); exit(); }
    $d = intval($_GET['delete_recv']);
    fin_gate($is_super_admin)->softDelete('fin_receivables', $d);
    header("Location: dues_fin.php?msg=تم+حذف+الذمّة+✅"); exit();
}

// ── حفظ مستحق ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['due_type'])) {
    if (!$can_add) { header("Location: dues_fin.php?msg=لا+توجد+صلاحية+إضافة+❌"); exit(); }
    $party_type = ($_POST['party_type'] ?? '') === 'employee' ? 'employee' : 'supplier';
    $party_ref  = intval($party_type === 'employee' ? ($_POST['employee_ref'] ?? 0) : ($_POST['supplier_ref'] ?? 0));
    $due_type   = trim($_POST['due_type'] ?? '');
    $direction  = ($_POST['direction'] ?? 'credit') === 'debit' ? 'debit' : 'credit';
    $amount     = round(floatval($_POST['amount'] ?? 0), 2);
    if ($party_ref <= 0 || !isset($due_types[$due_type]) || $amount <= 0) {
        header("Location: dues_fin.php?msg=بيانات+غير+صحيحة+(طرف/نوع/مبلغ)+❌"); exit();
    }

    // ── M-11: لا خصمَ بلا مستندٍ يُنقر إليه ─────────────────────────────────
    // كان هذا المسارُ يُدخل خصمًا **حرًّا بلا أيِّ مرجع** — رقمٌ يُقتطع من طرفٍ
    // ولا أحدَ يعرف مِن أيِّ مستند. والقيدُ البنيويُّ `ck_dues_debit_source` يرفضه
    // الآن في القاعدة، فالفحصُ هنا **رسالةٌ للمستخدم** لا حارسٌ وحيد: بدونه يرى
    // خطأَ قاعدةٍ غامضًا بدل جملةٍ تقول ما ينقص.
    // والاستحقاقُ (`credit`) خارجَ الإلزام: مصدرُه حدثُ المروحة.
    $src_type = trim($_POST['source_doc_type'] ?? '');
    $src_id   = intval($_POST['source_doc_id'] ?? 0);
    $ALLOWED_SRC = array('proc_issue', 'mnt_order', 'transfer_order', 'penalty_assessment', 'settlement');
    // الفجوةُ المعلَنة: أنواعٌ لا مصدرَ مستنديًّا لها بعد (M-12/M-20). تُقبل
    // بـ`pending_source` **وحدَها** — فما له مصدرٌ مبنيٌّ لا يُقبل بلا مستنده،
    // وإلا صارت القيمةُ بابًا خلفيًّا يُفرغ القيدَ من معناه.
    $PENDING_OK = array('advance', 'deduction');

    if ($direction === 'debit') {
        if ($src_type === 'pending_source') {
            if (!in_array($due_type, $PENDING_OK, true)) {
                header("Location: dues_fin.php?msg=" . rawurlencode(
                    '«بلا مصدرٍ بعد» تُقبل للسلف والخصومات وحدَها — وهذا النوعُ له مستندٌ مبنيٌّ فاختره')
                    . "+❌");
                exit();
            }
        } elseif (!in_array($src_type, $ALLOWED_SRC, true)) {
            header("Location: dues_fin.php?msg=" . rawurlencode(
                'كلُّ خصمٍ يلزمه مستندٌ مصدر — اختر نوعَه (سندُ صرفٍ · أمرُ صيانة · أمرُ نقل · احتسابُ جزاء · تسوية)') . "+❌");
            exit();
        } elseif ($src_id <= 0) {
            header("Location: dues_fin.php?msg=" . rawurlencode(
                'رقمُ المستند المصدر إلزامي — الرقمُ الذي يُخصم يجب أن يُنقر إلى أصله') . "+❌");
            exit();
        }
    }

    fin_gate($is_super_admin)->insert('fin_dues', array(
        'party_type' => $party_type, 'party_ref' => $party_ref, 'due_type' => $due_type,
        'direction' => $direction, 'amount' => $amount,
        'source_doc_type' => ($direction === 'debit') ? $src_type : null,
        'source_doc_id'   => ($direction === 'debit' && $src_type !== 'pending_source' && $src_id > 0)
                             ? $src_id : null,
        'created_by' => $current_user_id,
    ));
    header("Location: dues_fin.php?msg=تمت+إضافة+المستحق+✅"); exit();
}

// ── حفظ ذمّة عميل ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['doc_type'])) {
    if (!$can_add) { header("Location: dues_fin.php?msg=لا+توجد+صلاحية+إضافة+❌"); exit(); }
    $cust = intval($_POST['customer_entity_id'] ?? 0);
    $doc_type = ($_POST['doc_type'] ?? '') === 'statement' ? 'statement' : 'invoice';
    $doc_ref  = trim($_POST['doc_ref'] ?? '');
    $amount   = round(floatval($_POST['r_amount'] ?? 0), 2);
    $due_date = trim($_POST['due_date'] ?? '') ?: null;
    if ($cust <= 0 || $amount <= 0) { header("Location: dues_fin.php?msg=بيانات+الذمّة+غير+صحيحة+❌"); exit(); }
    // P-08: **الذمّةُ بعملتها** — والافتراضُ عملةُ الأساس مُعلَنًا في الشاشة
    require_once __DIR__ . '/../app/Services/Finance/FxSettlementService.php';
    require_once __DIR__ . '/../includes/fx.php';
    $r_cur = trim(strval($_POST['r_currency'] ?? '')) !== ''
             ? (string) ems_fx_code($_POST['r_currency']) : (string) ems_fx_base_currency();
    if ($r_cur === '') { $r_cur = (string) ems_fx_base_currency(); }
    $r_rate = \App\Services\Finance\FxSettlementService::rateOf($r_cur, date('Y-m-d'));
    fin_gate($is_super_admin)->insert('fin_receivables', array(
        'customer_entity_id' => $cust, 'doc_type' => $doc_type, 'doc_ref' => $doc_ref,
        'amount' => $amount, 'currency' => $r_cur,
        'fx_rate_recognized' => $r_rate,
        'base_amount' => ($r_rate === null) ? null : round($amount * $r_rate, 2),
        'due_date' => $due_date, 'state' => 'open', 'created_by' => $current_user_id,
    ));
    header("Location: dues_fin.php?msg=تمت+إضافة+الذمّة+✅"); exit();
}

$page_title = 'إيكوبيشن | الذمم والتحصيل';
include '../inheader.php';
include '../insidebar.php';
?>
<div class="main fin-dues-main ems-unified-page-shell">
    <?php
    $header_title = 'الذمم والتحصيل'; $header_icon = 'fa fa-hand-holding-dollar';
    $header_actions = array();
    if ($can_add) {
        $header_actions[] = array('id' => 'toggleDue', 'class' => 'add-btn', 'icon' => 'fas fa-plus-circle', 'label' => 'إضافة مستحق');
        $header_actions[] = array('id' => 'toggleRecv', 'class' => 'add-btn', 'icon' => 'fas fa-file-invoice', 'label' => 'إضافة ذمّة عميل');
    }
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    ?>
    <?php fin_msg_banner(); ?>

    <!-- فورم مستحق -->
    <form id="dueForm" action="" method="post" class="allforms">
        <div class="card-header"><h5><i class="fas fa-hand-holding-dollar"></i> إضافة مستحق (مورد/موظف)</h5></div>
        <div class="card"><div class="card-body"><div class="form-section"><div class="form-grid">
            <div class="form-group"><label>نوع الطرف <span class="required">*</span></label>
                <select name="party_type" id="d_ptype"><option value="supplier">مورد</option><option value="employee">موظف</option></select></div>
            <div class="form-group" id="d_supwrap"><label>المورد</label>
                <select name="supplier_ref" id="d_sup"><?php echo fin_supplier_options($conn, $is_super_admin, $company_id); ?></select></div>
            <div class="form-group" id="d_empwrap" style="display:none"><label>الموظف</label>
                <select name="employee_ref" id="d_emp"><?php echo fin_employee_options($conn, $is_super_admin, $company_id); ?></select></div>
            <div class="form-group"><label>نوع المستحق <span class="required">*</span></label>
                <select name="due_type"><?php foreach ($due_types as $k => $v) echo "<option value='" . htmlspecialchars($k) . "'>" . htmlspecialchars($v) . "</option>"; ?></select></div>
            <div class="form-group"><label>الاتجاه</label>
                <select name="direction" id="d_dir"><option value="credit">له (دائن)</option><option value="debit">عليه (مدين)</option></select></div>
            <?php /* M-11: مصدرُ الخصم — يظهر مع «عليه» وحدَه، فالاستحقاقُ مصدرُه حدثُ المروحة */ ?>
            <div class="form-group" id="d_srcwrap" style="display:none">
                <label>مستندُ الخصم <span class="required">*</span>
                    <span class="mnt-req-hint">(كلُّ خصمٍ ينقر إلى أصله)</span></label>
                <select name="source_doc_type" id="d_srctype">
                    <option value="">— اختر نوعَ المستند —</option>
                    <option value="proc_issue">سندُ صرف (قطعُ غيار · وقود)</option>
                    <option value="mnt_order">أمرُ صيانة</option>
                    <option value="transfer_order">أمرُ نقل</option>
                    <option value="penalty_assessment">احتسابُ جزاء</option>
                    <option value="settlement">تسوية</option>
                    <option value="pending_source">بلا مصدرٍ بعد — سلفةٌ أو خصمٌ (فجوةٌ معلَنة)</option>
                </select>
                <small style="color:#78350f;display:block;margin-top:4px">
                    «بلا مصدرٍ بعد» للسلف والخصومات وحدَها: لا جدولَ مستنديًّا لها حتى الآن،
                    فتُعلَن الفجوةُ ولا تُخبَّأ. وما له مستندٌ مبنيٌّ يُرفض بدونه.
                </small></div>
            <div class="form-group" id="d_srcidwrap" style="display:none">
                <label>رقمُ المستند <span class="required">*</span></label>
                <input type="number" min="1" name="source_doc_id" id="d_srcid"></div>
            <div class="form-group"><label>المبلغ <span class="required">*</span></label>
                <input type="number" step="0.01" min="0" name="amount" required></div>
        </div></div>
        <div class="form-actions"><button type="submit" class="btn-save"><i class="fas fa-save"></i> حفظ</button>
            <button type="button" class="btn-cancel" onclick="$('#dueForm').removeClass('allforms-visible')">إلغاء</button></div>
        </div></div>
    </form>

    <!-- فورم ذمّة عميل -->
    <form id="recvForm" action="" method="post" class="allforms">
        <div class="card-header"><h5><i class="fas fa-file-invoice"></i> إضافة ذمّة عميل</h5></div>
        <div class="card"><div class="card-body"><div class="form-section"><div class="form-grid">
            <div class="form-group"><label>العميل <span class="required">*</span></label>
                <select name="customer_entity_id" required><?php echo fin_client_options($conn, $is_super_admin, $company_id); ?></select></div>
            <div class="form-group"><label>نوع المستند</label>
                <select name="doc_type"><option value="invoice">فاتورة</option><option value="statement">مستخلص</option></select></div>
            <div class="form-group"><label>مرجع المستند</label><input type="text" name="doc_ref"></div>
            <div class="form-group"><label>المبلغ <span class="required">*</span></label><input type="number" step="0.01" min="0" name="r_amount" required></div>
            <?php // P-08: **الذمّةُ بعملتها** — ومبلغٌ بلا عملةٍ لا يُعرف بأيِّ عملةٍ هو
                  require_once __DIR__ . '/../includes/fx.php';
                  $__base = (string) ems_fx_base_currency(); ?>
            <div class="form-group"><label>العملة</label>
                <select name="r_currency">
                    <?php foreach (ems_fx_currencies() as $__c => $__row): ?>
                        <option value="<?php echo htmlspecialchars((string)$__c); ?>"
                            <?php echo ((string)$__c === $__base) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars((string)$__c . ' — ' . $__row['name_ar']); ?></option>
                    <?php endforeach; ?>
                </select></div>
            <div class="form-group"><label>تاريخ الاستحقاق</label><input type="date" name="due_date"></div>
        </div></div>
        <div class="form-actions"><button type="submit" class="btn-save"><i class="fas fa-save"></i> حفظ</button>
            <button type="button" class="btn-cancel" onclick="$('#recvForm').removeClass('allforms-visible')">إلغاء</button></div>
        </div></div>
    </form>

    <div class="card"><div class="card-body">
        <h5 style="margin:0 0 10px"><i class="fas fa-hand-holding-dollar"></i> مستحقات الموردين والموظفين</h5>
        <div class="table-container">
            <table id="finTable" class="display nowrap alltables no-datatable" style="width:100%;">
                <thead><tr><th>الإجراءات</th><th>الطرف</th><th>المُنشئ — الاسم والصفة</th><th>نوع المستفيد</th><th>الاتجاه</th><th>المبلغ</th><th>التسوية</th></tr></thead>
                <tbody>
                <?php
                // نطاق نوع الطرف (fin_party_scope): الموارد البشرية ترى الموظفين حصرًا،
                // والموردون/المشتريات يرون الموردين حصرًا — والمالية ترى الكل.
                $party_scope = fin_party_scope($ctx);
                $pd_whereRaw = "COALESCE(d.is_deleted,0)=0";
                $pd_params = array();
                if ($party_scope !== null) { $pd_whereRaw .= " AND d.party_type = ?"; $pd_params[] = $party_scope; }
                $due_rows = fin_gate($is_super_admin)->scopedQuery(
                    array('scope' => array('d' => 'fin_dues'),
                          'enrich' => array('s' => 'suppliers', 'e' => 'employees')),
                    "SELECT d.*, COALESCE(s.name, e.name) AS party_name
                     FROM fin_dues d
                     LEFT JOIN suppliers s ON (d.party_type='supplier' AND s.id=d.party_ref)
                     LEFT JOIN employees e ON (d.party_type='employee' AND e.id=d.party_ref)
                     WHERE {TENANT_SCOPE} AND " . $pd_whereRaw . " ORDER BY d.id DESC",
                    $pd_params);
                { foreach ($due_rows as $row) {
                    $ss = (string)$row['settlement_state'];
                    $ss_tone = $ss === 'paid' ? 'success' : ($ss === 'settled' ? 'primary' : 'secondary');
                    echo "<tr><td><div class='action-btns'>";
                    if ($can_edit && $row['party_type'] === 'supplier' && $ss === 'pending') {
                        echo "<a href='?settle_supplier=" . intval($row['party_ref']) . "&_t=" . fin_action_token() . "' class='action-btn edit' title='تسوية المورد' onclick='return confirm(\"تسوية كل مستحقات هذا المورد؟\")'><i class='fas fa-scale-balanced'></i></a>";
                    }
                    if ($can_delete && $ss !== 'paid') {
                        echo "<a href='?delete_due=" . intval($row['id']) . "' class='action-btn delete' onclick='return confirm(\"حذف؟\")' title='حذف'><i class='fas fa-trash-alt'></i></a>";
                    }
                    echo "</div></td>";
                    echo "<td>" . htmlspecialchars($party_types[$row['party_type']] ?? $row['party_type']) . "</td>";
                    echo "<td>" . htmlspecialchars((string)($row['party_name'] ?? ('#' . $row['party_ref']))) . "</td>";
                    echo "<td>" . htmlspecialchars($due_types[$row['due_type']] ?? $row['due_type']) . "</td>";
                    echo "<td>" . ($row['direction'] === 'credit' ? 'له' : 'عليه') . "</td>";
                    echo "<td>" . number_format((float)$row['amount'], 2) . "</td>";
                    echo "<td><span class='badge badge-" . $ss_tone . "'>" . htmlspecialchars($settle_states[$ss] ?? $ss) . "</span></td>";
                    echo "</tr>";
                } }
                ?>
                </tbody>
            </table>
        </div>

        <?php if ($party_scope === null): // ذمم العملاء شأن المالية — لا تُعرض للأدوار المنطَّقة بطرف ?>
        <h5 style="margin:18px 0 10px"><i class="fas fa-file-invoice"></i> الذمم المدينة (العملاء)</h5>
        <div class="table-container">
            <table id="recvTable" class="display nowrap alltables no-datatable" style="width:100%;">
                <thead><tr><th>الإجراءات</th><th>العميل</th><th>المستند</th><th>المرجع</th><th>المبلغ</th><th>المحصّل</th><th>المتبقّي</th><th>تاريخ الاستحقاق</th><th>الحالة</th>
              <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
              <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
              <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
              <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
              <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
              <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
              <th class="ems-gov-th" data-gov="idem_key" data-slice="2" title="يمنع وقوع الأثر مرتين بمفتاح مركب">مفتاح منع التكرار</th>
              <th class="ems-gov-th" data-gov="reversed_by" data-slice="2" title="مرجع الحركة التي عكسته">معكوس بـ</th>
              <th class="ems-gov-th" data-gov="reversal_of" data-slice="2" title="مرجع الحركة التي عكسها">عكس عن</th>
              <th class="ems-gov-th" data-gov="impact_grade" data-slice="2" title="مبدئي أم نهائي — فلا يقفل مبدئي ماليًّا">درجة الأثر</th>
              <th class="ems-gov-th" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
              <th class="ems-gov-th" data-gov="cost_center" data-slice="3" title="وجهة التحميل">مركز التكلفة</th>
              <th class="ems-gov-th" data-gov="fx_rate_source" data-slice="3" title="ما خالف عملة الدفاتر يحمل السعر ومصدره">سعر الصرف ومصدره</th>
              <th class="ems-gov-th" data-gov="currency" data-slice="3" title="لا مبلغ بلا عملة">العملة</th>
              </tr></thead>
                <tbody>
                <?php
                $recv_rows = fin_gate($is_super_admin)->scopedQuery(
                    array('scope' => array('r' => 'fin_receivables'), 'enrich' => array('c' => 'clients')),
                    "SELECT r.*, c.client_name FROM fin_receivables r
                     LEFT JOIN clients c ON c.id = r.customer_entity_id
                     WHERE {TENANT_SCOPE} AND COALESCE(r.is_deleted,0)=0 ORDER BY r.id DESC");
                { foreach ($recv_rows as $row) {
                    $overdue = ($row['due_date'] && $row['due_date'] < date('Y-m-d') && (float)$row['outstanding'] > 0);
                    $st = $overdue ? 'overdue' : (string)$row['state'];
                    $st_tone = $st === 'collected' ? 'success' : ($st === 'overdue' ? 'danger' : ($st === 'partial' ? 'primary' : 'secondary'));
                    $st_lbl = array('open' => 'مفتوحة', 'partial' => 'جزئي', 'collected' => 'محصّلة', 'overdue' => 'متأخرة');
                    echo "<tr><td><div class='action-btns'>";
                    if ($can_delete) { echo "<a href='?delete_recv=" . intval($row['id']) . "' class='action-btn delete' onclick='return confirm(\"حذف؟\")' title='حذف'><i class='fas fa-trash-alt'></i></a>"; }
                    echo "</div></td>";
                    echo "<td>" . htmlspecialchars((string)($row['client_name'] ?? ('#' . $row['customer_entity_id']))) . "</td>";
                    echo "<td>" . ($row['doc_type'] === 'statement' ? 'مستخلص' : 'فاتورة') . "</td>";
                    echo "<td>" . htmlspecialchars((string)($row['doc_ref'] ?? '')) . "</td>";
                    echo "<td>" . number_format((float)$row['amount'], 2) . "</td>";
                    echo "<td>" . number_format((float)$row['collected'], 2) . "</td>";
                    echo "<td>" . number_format((float)$row['outstanding'], 2) . "</td>";
                    // E-06 (ENT-03 §4): عمرُ الذمة بشرائح 30/60/90 ملوَّنةً —
                    // كان التلوينُ في اللوحة وحدَها وشاشةُ الذمم بلا شرائح
                    $e06 = '';
                    if ($row['due_date'] && (float)$row['outstanding'] > 0) {
                        $age = (int) floor((time() - strtotime((string)$row['due_date'])) / 86400);
                        if ($age >= 90)      { $e06 = " <span class='badge badge-danger'>+90ي</span>"; }
                        elseif ($age >= 60)  { $e06 = " <span class='badge badge-warning'>60–90ي</span>"; }
                        elseif ($age >= 30)  { $e06 = " <span class='badge badge-secondary'>30–60ي</span>"; }
                    }
                    echo "<td>" . htmlspecialchars((string)($row['due_date'] ?? '—')) . $e06 . "</td>";
                    echo "<td><span class='badge badge-" . $st_tone . "'>" . ($st_lbl[$st] ?? $st) . "</span></td>";
                    echo "</tr>";
                } }
                ?>
                </tbody>
            </table>
        </div>
        <?php endif; // نهاية قسم ذمم العملاء (المالية فقط) ?>
    </div></div>
</div>

<script src="/ems/assets/vendor/jquery-3.7.1.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/jquery.dataTables.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/dataTables.buttons.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/buttons.html5.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/buttons.print.min.js"></script>
<script src="/ems/assets/vendor/jszip/jszip.min.js"></script>
<script src="/ems/assets/vendor/pdfmake/pdfmake.min.js"></script>
<script src="/ems/assets/vendor/pdfmake/vfs_fonts.js"></script>
<script>
$(document).ready(function () {
    $('#finTable, #recvTable').DataTable({ scrollX: true, autoWidth: false, stateSave: false, dom: 'Bfrtip',
        buttons: [ { extend: 'copy', text: '📋 نسخ' }, { extend: 'excel', text: '📊 Excel' }, { extend: 'print', text: '🖨️ طباعة' } ],
        "language": { "url": "/ems/assets/i18n/datatables/ar.json" } });
    $('#toggleDue').on('click', function () { $('#dueForm').toggleClass('allforms-visible'); });
    $('#toggleRecv').on('click', function () { $('#recvForm').toggleClass('allforms-visible'); });
    $('#d_ptype').on('change', function () {
        var emp = this.value === 'employee';
        $('#d_empwrap').toggle(emp); $('#d_supwrap').toggle(!emp);
    });
    // M-11: حقلا المصدر يظهران مع «عليه (مدين)» وحدَه ويصيران إلزامًا معه —
    // والاستحقاقُ لا يُطالَب بمستند (مصدرُه حدثُ المروحة).
    $('#d_dir').on('change', function () {
        var deb = this.value === 'debit';
        $('#d_srcwrap').toggle(deb);
        $('#d_srctype').prop('required', deb);
        if (!deb) { $('#d_srctype').val(''); $('#d_srcid').val(''); }
        $('#d_srctype').trigger('change');
    }).trigger('change');
    // «بلا مصدرٍ بعد» لا رقمَ مستندٍ لها — فإخفاءُ الحقل أصدقُ من طلبِ رقمٍ لا وجودَ له
    $('#d_srctype').on('change', function () {
        var deb = $('#d_dir').val() === 'debit';
        var needsId = deb && this.value !== '' && this.value !== 'pending_source';
        $('#d_srcidwrap').toggle(needsId);
        $('#d_srcid').prop('required', needsId);
        if (!needsId) { $('#d_srcid').val(''); }
    });
});
</script>
</body>
</html>
