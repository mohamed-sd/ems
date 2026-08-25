<?php
require_once __DIR__ . '/../includes/permissions_helper.php';
/**
 * Suppliers/supplier_advances.php — بوابةُ سلفيات الموردين (M-12 · ENT-02 §3)
 * ───────────────────────────────────────────────────────────────────────────
 * «**بوابةٌ واحدةٌ** لكل ما يُصرف للمورد **خارج التسوية** (نقدًا · نيابةً ·
 * **عهدةً**) بمستندٍ وجدولِ استرداد — ورصيدُها **ظاهرٌ في بطاقته دائمًا**».
 *
 * والاستردادُ **لا يقع هنا**: القسطُ يظهر بندًا في التسوية، ويُنقص الرصيدَ
 * **باعتمادها** — المسودةُ نيّةٌ والمعتمَدةُ واقعة.
 *
 * كلُّ فعلٍ عبر `SupplierAdvanceService` — والاعتمادُ بيدٍ غيرِ يد المُعِدّ.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}
include '../config.php';
require_once __DIR__ . '/../app/Services/Settlement/SupplierAdvanceService.php';

use App\Services\Settlement\SupplierAdvanceService as SAS;

$current_role   = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$company_id     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$uid            = isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0;

if (!$is_super_admin && $company_id <= 0) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد بيئة شركة صالحة للمستخدم ❌', 'GOV-SCOPE-403', '');
    exit();
}

$MODULE_CODE = 'Suppliers/supplier_advances.php';
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
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض سلفيات الموردين ❌', 'GOV-PERM-403', '');
    exit();
}

$gate = $is_super_admin ? ems_tenant_db()->forAllTenants('supplier advances super') : ems_tenant_db();

$STATE_LABELS = array('draft' => 'مسودة', 'approved' => 'معتمدة', 'active' => 'نشطة',
                      'settled' => 'مستردة', 'cancelled' => 'ملغاة');

$redirect = function ($msg) { ems_gov_flash_redirect('supplier_advances.php', $msg, 'GOV-INFO-200', ''); exit(); };

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = strval($_POST['sa_action'] ?? '');

    if ($action === 'open') {
        if (!$can_add) { $redirect('لا توجد صلاحية لهذا الإجراء ❌'); }
        $r = SAS::open($conn, $gate, $company_id, array(
            'supplier_id'           => $_POST['supplier_id'] ?? 0,
            'supplier_contract_id'  => $_POST['supplier_contract_id'] ?? '',
            'advance_type'          => $_POST['advance_type'] ?? 'cash',
            'amount'                => $_POST['amount'] ?? 0,
            'currency'              => $_POST['currency'] ?? '',
            'doc_ref'               => $_POST['doc_ref'] ?? '',
            'issued_date'           => $_POST['issued_date'] ?? '',
            'installments_count'    => $_POST['installments_count'] ?? 1,
            'installment_amount'    => $_POST['installment_amount'] ?? '',
            'first_recovery_period' => $_POST['first_recovery_period'] ?? '',
            'note'                  => $_POST['note'] ?? '',
        ), $uid);
        $redirect($r['ok'] ? 'فتحت السلفة (مسودة) — تنتظر اعتماد غير معدها ✅'
                           : ($r['code'] . ' — ' . $r['reason'] . ' ❌'));
    }

    if ($action === 'approve') {
        if (!$can_edit) { $redirect('لا توجد صلاحية لهذا الإجراء ❌'); }
        $r = SAS::approve($conn, $gate, $company_id, intval($_POST['advance_id'] ?? 0), $uid);
        $redirect($r['ok'] ? 'اعتمدت السلفة — يظهر قسطها بندا في تسويته ✅'
                           : ($r['code'] . ' — ' . $r['reason'] . ' ❌'));
    }
}

// ── القراءة ────────────────────────────────────────────────────────────────
$filterSup = intval($_GET['supplier_id'] ?? 0);
$advances = SAS::advancesOf($gate, $filterSup);

$suppliers = array();
try {
    $suppliers = $gate->scopedQuery(array('scope' => array('s' => 'suppliers')),
        "SELECT s.id, s.name FROM suppliers s
          WHERE {TENANT_SCOPE} AND COALESCE(s.is_deleted,0)=0 ORDER BY s.name");
} catch (\Throwable $t) { $suppliers = array(); }
$nameOf = array();
foreach ($suppliers as $s) { $nameOf[(int) $s['id']] = (string) $s['name']; }

$page_title = 'إيكوبيشن | سلفيات الموردين';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/entity_tabs.php'; echo ems_entity_tabs('settlement', 'السلف والنيابية');
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }

/* شريطُ تبويباتِ العائلة — قرارُ وثيقةِ المواءمة (مكوّنٌ مركزيّ) */
$sft_family = 'settlement'; $sft_active = 'advances';
include __DIR__ . '/../includes/sales_family_tabs.php';
?>
<?php /* نُقلت أنماطُ هذه الشاشةِ إلى assets/css/ems-screens.css (UXUI-01 البند ٦: صفرُ نمطٍ محليّ) */ ?>
<div class="main ems-unified-page-shell ems-doc-cycle">
    <?php
    $header_title = 'سلفيات الموردين'; $header_icon = 'fa fa-money-bill-transfer';
    $header_actions = array();
    if ($can_add) {
        $header_actions[] = array('href' => 'javascript:void(0)', 'id' => 'toggleSadvForm',
            'icon' => 'fa fa-plus', 'label' => 'سلفة جديدة', 'class' => 'add');
    }
    $header_back = array('href' => 'settlements.php', 'class' => '',
                         'icon' => 'fas fa-arrow-right', 'label' => 'تسويات الموردين');
    include('../includes/page_header.php');
    if (isset($_GET['msg'])) {
        echo '<div class="alert alert-info">' . htmlspecialchars($_GET['msg']) . '</div>';
    }
    echo ems_next_step('اعتماد السلفة المسودة بيد غير معدها — فيظهر قسطها بندا في تسوية المورد');
    echo ems_states_bundle('لا سلفيات مسجلة ضمن هذا الترشيح', 'افتح سلفة بمستند صرف وجدول استرداد، أو غير ترشيح المورد');
    ?>

    <div class="alert alert-info sadv-note">
        <i class="fa fa-circle-info"></i>
        القسط يظهر <strong>بندا في تسوية المورد</strong> عند توليدها، ولا ينقص الرصيد
        إلا <strong>باعتمادها</strong> — المسودة نية والمعتمدة واقعة (ENT-02 §3).
    </div>

    <?php if ($can_add): ?>
    <form method="post" class="allforms" id="sadvForm">
        <?= csrf_field() ?>
        <input type="hidden" name="sa_action" value="open">
        <div class="card"><div class="card-header"><h5><i class="fa fa-money-bill-transfer"></i> سلفة جديدة</h5></div>
        <div class="card-body"><div class="form-grid">
            <div class="form-group">
                <label for="emsf_1396_5d86b">المورد <span class="sadv-req">*</span></label>
                <select name="supplier_id" required id="emsf_1396_5d86b">
                    <option value="">— اختر —</option>
                    <?php foreach ($suppliers as $s): ?>
                        <option value="<?php echo intval($s['id']); ?>">
                            #<?php echo intval($s['id']); ?> — <?php echo htmlspecialchars((string)$s['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="emsf_1397_6b9e9">النوع</label>
                <select name="advance_type" id="emsf_1397_6b9e9">
                    <?php foreach (SAS::TYPE_LABELS as $k => $lbl): ?>
                        <option value="<?php echo $k; ?>"><?php echo $lbl; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label for="emsf_1398_77be1">المبلغ <span class="sadv-req">*</span></label>
                <input type="number" step="0.01" min="0.01" name="amount" required id="emsf_1398_77be1"></div>
            <div class="form-group"><label for="emsf_1399_9ea11">العملة</label><input type="text" name="currency" maxlength="8" id="emsf_1399_9ea11"></div>
            <div class="form-group"><label for="emsf_1400_708c5">سند الصرف <span class="sadv-req">*</span>
                    <small>— «ما لا مستند له لا يحمل»</small></label>
                <input type="text" name="doc_ref" required maxlength="120" placeholder="إذن صرف 2051/77" id="emsf_1400_708c5"></div>
            <div class="form-group"><label for="emsf_1401_6c205">تاريخ الصرف <span class="sadv-req">*</span></label>
                <input type="date" name="issued_date" required id="emsf_1401_6c205"></div>
            <div class="form-group"><label for="emsf_1402_9f0db">عدد الأقساط</label>
                <input type="number" min="1" name="installments_count" value="1" id="emsf_1402_9f0db"></div>
            <div class="form-group"><label for="emsf_1403_c70f5">قسط التسوية <small>— فارغ = المبلغ ÷ الأقساط</small></label>
                <input type="number" step="0.01" min="0.01" name="installment_amount" id="emsf_1403_c70f5"></div>
            <div class="form-group"><label for="emsf_1404_10b6b">أول فترة استرداد</label>
                <input type="date" name="first_recovery_period" id="emsf_1404_10b6b"></div>
            <div class="form-group"><label for="emsf_1405_824a7">ملاحظة</label><input type="text" name="note" maxlength="255" id="emsf_1405_824a7"></div>
        </div>
        <div class="sadv-actions"><button type="submit" class="btn-primary"><i class="fa fa-save"></i> فتح السلفة</button></div>
        </div></div>
    </form>
    <?php endif; ?>

    <div class="card"><div class="card-body">
        <form method="get" class="sadv-filter">
            <strong>المورد:</strong>
            <select name="supplier_id" onchange="this.form.submit()" class="sadv-filter-select" aria-label="ترشيح السلفيات بالمورد">
                <option value="0">الكل</option>
                <?php foreach ($suppliers as $s): ?>
                    <option value="<?php echo intval($s['id']); ?>"
                        <?php echo $filterSup === intval($s['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars((string)$s['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if ($filterSup > 0): ?>
                <strong>الرصيد المفتوح:</strong>
                <span class="badge badge-info sadv-balance-badge">
                    <?php echo number_format(SAS::openBalance($gate, $filterSup), 2); ?></span>
            <?php endif; ?>
        </form>
    </div></div>

    <div class="card"><div class="card-header"><h5><i class="fa fa-list"></i> السلفيات وأرصدتها</h5></div>
    <div class="card-body"><div class="table-container">
        <table class="alltables display nowrap sadv-table">
            <thead><tr>
                <th>الإجراءات</th><th>المورد</th><th>النوع</th><th>المبلغ</th>
                <th>القسط</th><th>المسترد</th><th>الرصيد</th><th>سند الصرف</th><th>الحالة</th>
                <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
                <th class="ems-fn-th" data-fn="1">رقم السلفة</th>
                <th class="ems-fn-th" data-fn="1">العقد</th>
                <th class="ems-fn-th" data-fn="1">تاريخ الطلب</th>
                <th class="ems-fn-th" data-fn="1">سبب السلفة</th>
                <th class="ems-fn-th" data-fn="1">نسبة الاستقطاع من التسوية</th>
                <th class="ems-fn-th" data-fn="1">عدد أشهر الاستقطاع</th>
                <th class="ems-fn-th" data-fn="1">المستقطع حتى تاريخه</th>
                <th class="ems-fn-th" data-fn="1">المتبقي</th>
                <th class="ems-fn-th" data-fn="1">الضمان المقدم</th>
                <th class="ems-fn-th" data-fn="1">اعتماد الموردين</th>
                <th class="ems-fn-th" data-fn="1">الاعتماد المالي</th>
                <th class="ems-fn-th" data-fn="1">تاريخ الصرف</th>
                <th class="ems-fn-th" data-fn="1">نسخة القاعدة المستعملة</th>
                <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
                <th class="ems-gov-th none" data-gov="entity" data-slice="1" title="عزل الشركات — لا صف بلا كيان مالك">الكيان</th>
                <th class="ems-gov-th none" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمد — تفويض أو سلطة أصلية">مرجع التفويض</th>
                <th class="ems-gov-th none" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
                <th class="ems-gov-th none" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
                <th class="ems-gov-th none" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
                <th class="ems-gov-th none" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المنشئ — الاسم والصفة</th>
                <th class="ems-gov-th none" data-gov="idem_key" data-slice="2" title="يمنع وقوع الأثر مرتين بمفتاح مركب">مفتاح منع التكرار</th>
                <th class="ems-gov-th none" data-gov="reversed_by" data-slice="2" title="مرجع الحركة التي عكسته">معكوس ب</th>
                <th class="ems-gov-th none" data-gov="reversal_of" data-slice="2" title="مرجع الحركة التي عكسها">عكس عن</th>
                <th class="ems-gov-th none" data-gov="impact_grade" data-slice="2" title="مبدئي أم نهائي — فلا يقفل مبدئي ماليا">درجة الأثر</th>
                <th class="ems-gov-th none" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
                <th class="ems-gov-th none" data-gov="cost_center" data-slice="3" title="وجهة التحميل">مركز التكلفة</th>
                <th class="ems-gov-th none" data-gov="fx_rate_source" data-slice="3" title="ما خالف عملة الدفاتر يحمل السعر ومصدره">سعر الصرف ومصدره</th>
                <th class="ems-gov-th none" data-gov="currency" data-slice="3" title="لا مبلغ بلا عملة">العملة</th>
                </tr></thead>
            <tbody>
            <?php foreach ($advances as $a): ?>
                <tr>
                    <td>
                    <?php if ($can_edit && (string)$a['state'] === 'draft'): ?>
                        <form method="post" class="sadv-inline-form">
        <?= csrf_field() ?>
                            <input type="hidden" name="sa_action" value="approve">
                            <input type="hidden" name="advance_id" value="<?php echo intval($a['id']); ?>">
                            <button type="submit" class="action-btn edit" title="اعتماد (فصل اليدين)">
                                <i class="fas fa-check"></i></button>
                        </form>
                    <?php endif; ?>
                    </td>
                    <td>#<?php echo intval($a['supplier_id']); ?>
                        <?php echo htmlspecialchars(isset($nameOf[(int)$a['supplier_id']])
                                    ? ' — ' . $nameOf[(int)$a['supplier_id']] : ''); ?></td>
                    <td><?php echo htmlspecialchars(SAS::TYPE_LABELS[$a['advance_type']] ?? $a['advance_type']); ?></td>
                    <td><?php echo htmlspecialchars((string)$a['amount']); ?></td>
                    <td><?php echo htmlspecialchars((string)$a['installment_amount']); ?>
                        <small>× <?php echo intval($a['installments_count']); ?></small></td>
                    <td><?php echo htmlspecialchars((string)$a['recovered']); ?></td>
                    <td><strong><?php echo htmlspecialchars((string)$a['balance']); ?></strong></td>
                    <td><small><?php echo htmlspecialchars((string)$a['doc_ref']); ?></small></td>
                    <td><?php
                        $s = (string) $a['state'];
                        $cls = $s === 'settled' ? 'badge-success' : ($s === 'draft' ? 'badge-warning' : 'badge-info');
                        echo "<span class='badge {$cls}'>" . htmlspecialchars($STATE_LABELS[$s] ?? $s) . '</span>';
                    ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div></div></div>
</div>

<script src="../includes/js/jquery-3.7.1.main.js"></script>
<script>
(function () {
    document.addEventListener('DOMContentLoaded', function () {
        var b = document.getElementById('toggleSadvForm'), f = document.getElementById('sadvForm');
        if (b && f) { b.addEventListener('click', function () { f.classList.toggle('allforms-visible'); }); }
    });
})();
</script>
</body>
</html>
