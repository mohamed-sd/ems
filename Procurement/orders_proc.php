<?php
/**
 * Procurement/orders_proc.php — أوامر الشراء (proc_order + proc_order_line) — §15.2.
 * رأس + سطور. الإجمالي = مجموع (كمية×سعر). قاعدة: لا يغادر الأمر «مسودة» بلا مرجع اعتماد مالي.
 * شاشة جديدة مستقلة — لا تلمس أي جدول قائم.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/proc_helpers.php';

$ctx             = proc_ctx();
$is_super_admin  = $ctx['is_super'];
$company_id      = $ctx['company_id'];
$current_user_id = $ctx['user_id'];

if (!$is_super_admin && $company_id <= 0) {
    header("Location: ../login.php?msg=لا+توجد+بيئة+شركة+صالحة+للمستخدم+❌");
    exit();
}

$perms = proc_page_perms($conn, 'Procurement/orders_proc.php', $is_super_admin);
$can_view = $perms['can_view']; $can_add = $perms['can_add'];
$can_edit = $perms['can_edit']; $can_delete = $perms['can_delete'];
if (!$can_view) {
    header("Location: ../main/dashboard.php?msg=لا+توجد+صلاحية+عرض+أوامر+الشراء+❌");
    exit();
}

$company_scope_sql = proc_scope('company_id', $is_super_admin, $company_id);
$classifications = proc_classifications();
$states       = proc_order_states();
$currencies   = proc_currencies();
$pay_times    = proc_payment_times();
$recv_types   = proc_receipt_types();

// خيارات طلبات الشراء المفتوحة (للربط) — عبر البوابة والتسمية في PHP
$request_option_rows = proc_gate($is_super_admin)->select('proc_request', array(
    'columns' => array('id', 'code', 'op_classification'),
    'orderBy' => 'id DESC',
));
foreach ($request_option_rows as &$ror) {
    $rc = (string) $ror['code'];
    $ror['label'] = (($rc === '') ? ('#' . intval($ror['id'])) : $rc) . ' — ' . $ror['op_classification'];
}
unset($ror);

// ── تسجيلُ فاتورة المورد ومطابقتُها الثلاثية (UX-09 §8.2) ──
// «لا استحقاقَ بلا مطابقة»: تُقارن الفاتورةُ بالأمر وبالاستلام، فإن كانت ضمن
// السماح فُتح دَينُ المورد، وإلا وقفت بفرقها حتى قرارٍ موثَّق.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'match_invoice') {
    if (!$can_edit) { header("Location: orders_proc.php?msg=لا+توجد+صلاحية+❌"); exit(); }
    $mid = intval($_POST['id'] ?? 0);
    $res = proc_match_invoice(
        $conn, $mid,
        $_POST['invoice_no'] ?? '',
        $_POST['invoice_date'] ?? '',
        $_POST['invoice_amount'] ?? 0,
        $current_user_id
    );
    if ($res['status'] === 'matched') {
        $msg = 'طوبقت الفاتورةُ وفُتح استحقاقُ المورد ✅';
    } elseif ($res['status'] === 'var_pending') {
        $msg = 'فرقٌ فوق السماح — وقفت المطابقة بلا استحقاق: ' . $res['reason'] . ' ⚠️';
    } else {
        $msg = 'تعذّرت المطابقة: ' . ($res['reason'] !== '' ? $res['reason'] : 'خطأٌ داخلي') . ' ❌';
    }
    header("Location: orders_proc.php?edit_id=" . $mid . "&msg=" . urlencode($msg)); exit();
}

// ── حفظ (إضافة/تعديل) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['currency'])) {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $is_editing = $id > 0;
    if ($is_editing && !$can_edit) { header("Location: orders_proc.php?msg=لا+توجد+صلاحية+تعديل+❌"); exit(); }
    if (!$is_editing && !$can_add) { header("Location: orders_proc.php?msg=لا+توجد+صلاحية+إضافة+❌"); exit(); }
    if ($company_id <= 0)         { header("Location: orders_proc.php?msg=لا+يمكن+الحفظ+بلا+شركة+صالحة+❌"); exit(); }

    $supplier_id = ($_POST['supplier_id'] ?? '') !== '' ? intval($_POST['supplier_id']) : null;
    $request_id  = ($_POST['request_id'] ?? '') !== '' ? intval($_POST['request_id']) : null;
    $fin_approval_ref = trim($_POST['fin_approval_ref'] ?? '');
    $op_classification = trim($_POST['op_classification'] ?? 'استهلاكية');
    $currency = trim($_POST['currency'] ?? 'SDG');
    $fx_rate  = (float)($_POST['fx_rate'] ?? 1);
    $payment_time = trim($_POST['payment_time'] ?? 'فوري');
    $expected_receipt_type = trim($_POST['expected_receipt_type'] ?? 'مخزن');
    $state = trim($_POST['state'] ?? 'مسودة');
    $notes = trim($_POST['notes'] ?? '');

    if (!in_array($op_classification, $classifications, true)) { $op_classification = 'استهلاكية'; }
    if (!in_array($currency, $currencies, true)) { $currency = 'SDG'; }
    if (!in_array($payment_time, $pay_times, true)) { $payment_time = 'فوري'; }
    if (!in_array($expected_receipt_type, $recv_types, true)) { $expected_receipt_type = 'مخزن'; }
    if (!in_array($state, $states, true)) { $state = 'مسودة'; }

    // قاعدة §14: لا يصدر أمر (يغادر مسودة) بلا مرجع اعتماد مالي
    if ($state !== 'مسودة' && $fin_approval_ref === '') {
        header("Location: orders_proc.php?msg=لا+يصدر+الأمر+بلا+مرجع+اعتماد+مالي+❌"); exit();
    }

    // احسب السطور والإجمالي
    $item_ids = $_POST['line_item_id'] ?? array();
    $item_names = $_POST['line_item_name'] ?? array();
    $qtys = $_POST['line_qty'] ?? array();
    $prices = $_POST['line_price'] ?? array();
    $classes = $_POST['line_class'] ?? array();
    $total = 0.0;
    for ($i = 0; $i < count($item_names); $i++) {
        if (trim($item_names[$i] ?? '') === '') { continue; }
        $total += ((float)($qtys[$i] ?? 0)) * ((float)($prices[$i] ?? 0));
    }

    // K9-M1: الأب عبر البوابة والسطور عبر replaceChildren (النمط المبرَّر §8)
    $parent = array(
        'supplier_id' => $supplier_id, 'request_id' => $request_id,
        'fin_approval_ref' => $fin_approval_ref, 'op_classification' => $op_classification,
        'currency' => $currency, 'fx_rate' => $fx_rate, 'payment_time' => $payment_time,
        'expected_receipt_type' => $expected_receipt_type, 'total_amount' => $total,
        'state' => $state, 'notes' => $notes,
    );
    $line_rows = array();
    for ($i = 0; $i < count($item_names); $i++) {
        $iname = trim($item_names[$i] ?? '');
        if ($iname === '') { continue; }
        $qty = (float)($qtys[$i] ?? 1);
        $price = (float)($prices[$i] ?? 0);
        $cls = trim($classes[$i] ?? '');
        if (!in_array($cls, $classifications, true)) { $cls = $op_classification; }
        $line_rows[] = array(
            'item_id' => (isset($item_ids[$i]) && $item_ids[$i] !== '') ? intval($item_ids[$i]) : null,
            'item_name' => $iname, 'qty' => $qty, 'unit_price' => $price,
            'op_classification' => $cls, 'subtotal' => $qty * $price,
        );
    }
    try {
        $g = proc_gate(false);
        if ($is_editing) {
            $g->update('proc_order', $parent, array('id' => $id, 'is_deleted' => 0));
            $order_id = $id;
        } else {
            $parent['code'] = proc_gen_code($conn, 'proc_order', 'PRC-PO', $company_id);
            $parent['created_by'] = $current_user_id;
            $order_id = $g->insert('proc_order', $parent);
        }
        $g->replaceChildren('proc_order', $order_id, 'proc_order_line', 'order_id', $line_rows, 'order lines rewrite');
    } catch (\Throwable $e) {
        error_log('orders_proc save refused: ' . $e->getMessage());
        header("Location: orders_proc.php?msg=تعذّر+الحفظ+❌"); exit();
    }
    header("Location: orders_proc.php?msg=" . ($is_editing ? 'تم+تعديل+الأمر+بنجاح+✅' : 'تمت+إضافة+الأمر+بنجاح+✅')); exit();
}

// ── حذف ناعم ──
if (isset($_GET['delete_id'])) {
    if (!$can_delete) { header("Location: orders_proc.php?msg=لا+توجد+صلاحية+حذف+❌"); exit(); }
    $delete_id = intval($_GET['delete_id']);
    try {
        proc_gate(false)->softDelete('proc_order', $delete_id);
    } catch (\App\Core\TenantGateException $e) {
        error_log('orders_proc softDelete refused: ' . $e->getMessage());
    }
    header("Location: orders_proc.php?msg=تم+حذف+الأمر+بنجاح+✅"); exit();
}

// ── تحميل أمر للتعديل ──
$edit = null; $edit_lines = array();
if (isset($_GET['edit_id']) && $can_edit) {
    $eid = intval($_GET['edit_id']);
    $edit = proc_gate($is_super_admin)->selectOne('proc_order', array('where' => array('id' => $eid)));
    if ($edit) {
        $edit_lines = proc_gate($is_super_admin)->select('proc_order_line', array(
            'where' => array('order_id' => $eid), 'orderBy' => 'id ASC',
        ));
    }
}

$page_title = 'إيكوبيشن | أوامر الشراء';
include '../inheader.php';
include '../insidebar.php';

/** صف سطر أمر شراء. */
function proc_ord_line_row($conn, $is_super_admin, $company_id, $classifications, $line = null)
{
    $iid = $line ? intval($line['item_id']) : 0;
    $iname = $line ? htmlspecialchars((string)$line['item_name'], ENT_QUOTES) : '';
    $qty = $line ? htmlspecialchars((string)$line['qty'], ENT_QUOTES) : '1';
    $price = $line ? htmlspecialchars((string)$line['unit_price'], ENT_QUOTES) : '0';
    $cls = $line ? (string)($line['op_classification'] ?? '') : '';
    $opts = proc_items_options($conn, $is_super_admin, $company_id, $iid);
    $clsopts = '<option value="">— تصنيف السطر —</option>';
    foreach ($classifications as $c) {
        $sel = ($c === $cls) ? ' selected' : '';
        $clsopts .= '<option value="' . htmlspecialchars($c) . '"' . $sel . '>' . htmlspecialchars($c) . '</option>';
    }
    return '<div class="proc-line form-grid" style="align-items:end;margin-bottom:8px">'
        . '<div class="form-group"><label>الصنف (كتالوج)</label><select name="line_item_id[]" class="line-item">' . $opts . '</select></div>'
        . '<div class="form-group"><label>اسم الصنف <span class="required">*</span></label><input type="text" name="line_item_name[]" class="line-name" value="' . $iname . '" required></div>'
        . '<div class="form-group"><label>الكمية</label><input type="number" step="0.01" name="line_qty[]" class="line-qty" value="' . $qty . '"></div>'
        . '<div class="form-group"><label>سعر الوحدة</label><input type="number" step="0.01" name="line_price[]" class="line-price" value="' . $price . '"></div>'
        . '<div class="form-group"><label>تصنيف السطر</label><select name="line_class[]">' . $clsopts . '</select></div>'
        . '<div class="form-group"><button type="button" class="btn-cancel removeLine"><i class="fas fa-times"></i></button></div>'
        . '</div>';
}
?>

<div class="main proc-orders-main ems-unified-page-shell">
    <?php
    $header_title = 'أوامر الشراء';
    $header_icon  = 'fa fa-file-invoice-dollar';
    $header_actions = array();
    if ($can_add) {
        $header_actions[] = array('id' => 'toggleForm', 'class' => 'add-btn', 'icon' => 'fas fa-plus-circle', 'label' => 'أمر جديد');
    }
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    ?>

    <?php proc_msg_banner(); ?>

    <?php if ($edit): /* ── فاتورةُ المورد ومطابقتُها الثلاثية (UX-09 §8.2) ── */
        $__gated  = in_array((string)$edit['state'], proc_order_expense_states(), true);
        $__ms     = (string)($edit['match_state'] ?? 'unmatched');
        $__tol    = round(proc_match_tolerance((float)$edit['total_amount']), 2);
        $__tone   = ($__ms === 'matched') ? '#166534' : (($__ms === 'var_pending') ? '#991b1b' : '#78716c');
        $__label  = array('unmatched' => 'لم تُطابَق', 'matched' => 'مطابَقة', 'var_pending' => 'فرقٌ ينتظر قرارًا', 'rejected' => 'مرفوضة');
    ?>
    <div class="card" style="margin-bottom:14px">
        <div class="card-header"><h5><i class="fas fa-file-invoice"></i> فاتورة المورد والمطابقة الثلاثية</h5></div>
        <div class="card-body">
            <p class="text-muted" style="margin:0 0 10px">
                تُقارن الفاتورةُ بأمر الشراء (السعر المتفق) وبسند الاستلام (ما وصل فعلًا).
                ضمن السماح <strong><?php echo number_format($__tol, 2); ?></strong>
                <?php echo htmlspecialchars((string)$edit['currency']); ?> (±2٪ أو 100 أيُّهما أصغر)
                يُفتح استحقاقُ المورد — وفوقه تقف بفرقها حتى قرارٍ موثَّق.
            </p>
            <div style="margin-bottom:10px">
                <strong>الحالة:</strong>
                <span style="color:<?php echo $__tone; ?>;font-weight:800">
                    <?php echo htmlspecialchars($__label[$__ms] ?? $__ms); ?></span>
                <?php if (!empty($edit['invoice_no'])): ?>
                    · فاتورة <strong><?php echo htmlspecialchars((string)$edit['invoice_no']); ?></strong>
                    <?php if (!empty($edit['invoice_amount'])): ?>
                        بقيمة <?php echo number_format((float)$edit['invoice_amount'], 2); ?>
                        (الأمر <?php echo number_format((float)$edit['total_amount'], 2); ?>)
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <?php if (!$__gated): ?>
                <div class="alert alert-info" style="margin:0">
                    لا مطابقةَ قبل الاستلام النهائي — حالةُ الأمر الآن:
                    <strong><?php echo htmlspecialchars((string)$edit['state']); ?></strong>.
                </div>
            <?php elseif ($can_edit): ?>
                <form action="orders_proc.php" method="post" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end">
                    <input type="hidden" name="action" value="match_invoice">
                    <input type="hidden" name="id" value="<?php echo intval($edit['id']); ?>">
                    <div class="form-group"><label>رقم الفاتورة</label>
                        <input type="text" name="invoice_no" required
                               value="<?php echo htmlspecialchars((string)($edit['invoice_no'] ?? '')); ?>"></div>
                    <div class="form-group"><label>تاريخها</label>
                        <input type="date" name="invoice_date"
                               value="<?php echo htmlspecialchars((string)($edit['invoice_date'] ?? '')); ?>"></div>
                    <div class="form-group"><label>قيمتها</label>
                        <input type="number" step="0.01" name="invoice_amount" required
                               value="<?php echo htmlspecialchars((string)($edit['invoice_amount'] ?? $edit['total_amount'])); ?>"></div>
                    <button type="submit" class="btn-save"><i class="fas fa-scale-balanced"></i> طابِق</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <form id="procForm" action="orders_proc.php" method="post" class="allforms<?php echo $edit ? ' allforms-visible' : ''; ?>">
        <div class="card-header"><h5><i class="fas fa-edit"></i> <?php echo $edit ? 'تعديل أمر شراء' : 'أمر شراء جديد'; ?></h5></div>
        <div class="card"><div class="card-body">
            <input type="hidden" name="id" value="<?php echo $edit ? intval($edit['id']) : ''; ?>">
            <div class="form-section">
                <div class="form-grid">
                    <div class="form-group">
                        <label>المورد التشغيلي</label>
                        <select name="supplier_id"><?php echo proc_suppliers_options($conn, $is_super_admin, $company_id, $edit ? intval($edit['supplier_id']) : 0); ?></select>
                    </div>
                    <div class="form-group">
                        <label>مرجع طلب الشراء</label>
                        <select name="request_id"><?php echo proc_options_from_rows($request_option_rows, $edit ? intval($edit['request_id']) : 0, '— بلا طلب —'); ?></select>
                    </div>
                    <div class="form-group">
                        <label>مرجع الاعتماد المالي <span class="required">*</span> <small>(شرط الإصدار)</small></label>
                        <input type="text" name="fin_approval_ref" value="<?php echo $edit ? htmlspecialchars((string)$edit['fin_approval_ref']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label>التصنيف التشغيلي</label>
                        <select name="op_classification">
                            <?php foreach ($classifications as $c): $sel = ($edit && $edit['op_classification'] === $c) ? 'selected' : ''; ?>
                                <option value="<?php echo htmlspecialchars($c); ?>" <?php echo $sel; ?>><?php echo htmlspecialchars($c); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>العملة</label>
                        <select name="currency">
                            <?php foreach ($currencies as $c): $sel = ($edit && $edit['currency'] === $c) ? 'selected' : ''; ?>
                                <option value="<?php echo htmlspecialchars($c); ?>" <?php echo $sel; ?>><?php echo htmlspecialchars($c); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>سعر الصرف</label>
                        <input type="number" step="0.0001" name="fx_rate" value="<?php echo $edit ? htmlspecialchars((string)$edit['fx_rate']) : '1'; ?>">
                    </div>
                    <div class="form-group">
                        <label>وقت الدفع</label>
                        <select name="payment_time">
                            <?php foreach ($pay_times as $p): $sel = ($edit && $edit['payment_time'] === $p) ? 'selected' : ''; ?>
                                <option value="<?php echo htmlspecialchars($p); ?>" <?php echo $sel; ?>><?php echo htmlspecialchars($p); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>نوع الاستلام المتوقع</label>
                        <select name="expected_receipt_type">
                            <?php foreach ($recv_types as $rt): $sel = ($edit && $edit['expected_receipt_type'] === $rt) ? 'selected' : ''; ?>
                                <option value="<?php echo htmlspecialchars($rt); ?>" <?php echo $sel; ?>><?php echo htmlspecialchars($rt); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>حالة الأمر</label>
                        <select name="state">
                            <?php foreach ($states as $st): $sel = ($edit && $edit['state'] === $st) ? 'selected' : ''; ?>
                                <option value="<?php echo htmlspecialchars($st); ?>" <?php echo $sel; ?>><?php echo htmlspecialchars($st); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="grid-column:1/-1">
                        <label>ملاحظات</label>
                        <input type="text" name="notes" value="<?php echo $edit ? htmlspecialchars((string)$edit['notes']) : ''; ?>">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <div class="card-header"><h5><i class="fas fa-list"></i> سطور الأصناف</h5></div>
                <div id="linesBody">
                    <?php
                    if ($edit && !empty($edit_lines)) {
                        foreach ($edit_lines as $l) { echo proc_ord_line_row($conn, $is_super_admin, $company_id, $classifications, $l); }
                    } else {
                        echo proc_ord_line_row($conn, $is_super_admin, $company_id, $classifications, null);
                    }
                    ?>
                </div>
                <button type="button" id="addLine" class="add-btn" style="margin-top:6px"><i class="fas fa-plus"></i> إضافة سطر</button>
                <div style="margin-top:10px;font-weight:700">الإجمالي: <span id="ordTotal">0.00</span></div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-save"><i class="fas fa-save"></i> حفظ</button>
                <a href="orders_proc.php" class="btn-cancel"><i class="fas fa-times"></i> إلغاء</a>
            </div>
        </div></div>
    </form>

    <template id="lineTemplate">
        <?php echo proc_ord_line_row($conn, $is_super_admin, $company_id, $classifications, null); ?>
    </template>

    <div class="card"><div class="card-body">
        <div class="table-container">
            <table id="procTable" class="display nowrap alltables no-datatable" style="width:100%;">
                <thead><tr>
                    <th>الإجراءات</th><th>الكود</th><th>المورد</th><th>التصنيف</th><th>العملة</th>
                    <th>الإجمالي</th><th>الحالة</th><th>الاستلام/التأخر</th><th>مرجع الاعتماد</th><th>أُنشئ</th>
                
                    <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
                    <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
                    <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
                    <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
                    <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
                    <th class="ems-gov-th" data-gov="idem_key" data-slice="2" title="يمنع وقوع الأثر مرتين بمفتاح مركب">مفتاح منع التكرار</th>
                    <th class="ems-gov-th" data-gov="reversed_by" data-slice="2" title="مرجع الحركة التي عكسته">معكوس بـ</th>
                    <th class="ems-gov-th" data-gov="reversal_of" data-slice="2" title="مرجع الحركة التي عكسها">عكس عن</th>
                    <th class="ems-gov-th" data-gov="impact_grade" data-slice="2" title="مبدئي أم نهائي — فلا يقفل مبدئي ماليًّا">درجة الأثر</th>
                    <th class="ems-gov-th" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
                    <th class="ems-gov-th" data-gov="cost_center" data-slice="3" title="وجهة التحميل">مركز التكلفة</th>
                    <th class="ems-gov-th" data-gov="fx_rate" data-slice="3" title="سعر التحويل لعملة الدفاتر">سعر الصرف</th>
                    </tr></thead>
                <tbody>
                    <?php
                    // ترطيب ثنائي: الأوامر ثم أسماء الموردين بجلبٍ واحد (دلالة LEFT JOIN)
                    $gv = proc_gate($is_super_admin);
                    $order_rows = $gv->select('proc_order', array(
                        'columns' => array('id', 'code', 'op_classification', 'currency', 'total_amount', 'state', 'fin_approval_ref', 'created_at', 'supplier_id',
                                           'received_pct', 'expected_delivery_date', 'final_receipt_at'),
                        'orderBy' => 'id DESC',
                    ));
                    $sup_names = array();
                    $sids = array();
                    foreach ($order_rows as $orow) {
                        if ($orow['supplier_id'] !== null) { $sids[intval($orow['supplier_id'])] = true; }
                    }
                    if (!empty($sids)) {
                        foreach ($gv->select('proc_supplier', array(
                            'columns' => array('id', 'name'),
                            'whereRaw' => 'id IN (' . implode(',', array_keys($sids)) . ')',
                            'includeDeleted' => true,
                        )) as $sr) { $sup_names[intval($sr['id'])] = $sr['name']; }
                    }
                    { foreach ($order_rows as $row) {
                        $row['supplier_name'] = ($row['supplier_id'] !== null && isset($sup_names[intval($row['supplier_id'])]))
                            ? $sup_names[intval($row['supplier_id'])] : null;
                        echo "<tr>";
                        echo "<td><div class='action-btns'>";
                        if ($can_edit) {
                            echo "<a href='?edit_id=" . intval($row['id']) . "' class='action-btn edit' title='تعديل'><i class='fas fa-edit'></i></a>";
                        }
                        if ($can_delete) {
                            echo "<a href='?delete_id=" . intval($row['id']) . "' class='action-btn delete' onclick='return confirm(\"هل أنت متأكد من الحذف؟\")' title='حذف'><i class='fas fa-trash-alt'></i></a>";
                        }
                        echo "</div></td>";
                        echo "<td>" . htmlspecialchars((string)($row['code'] ?? '')) . "</td>";
                        echo "<td>" . htmlspecialchars((string)($row['supplier_name'] ?? '')) . "</td>";
                        echo "<td>" . htmlspecialchars((string)$row['op_classification']) . "</td>";
                        echo "<td>" . htmlspecialchars((string)$row['currency']) . "</td>";
                        echo "<td>" . htmlspecialchars(number_format((float)$row['total_amount'], 2)) . "</td>";
                        echo "<td><span class='action-btn'>" . htmlspecialchars((string)$row['state']) . "</span></td>";
                        // E-18 (UX-09 §5.1): PartialReceived وLate **حالتين صريحتين**
                        // بعدّاديهما — لا تنبيهًا ضمنيًّا في اللوحة وحدها
                        $e18 = array();
                        $pct = $row['received_pct'] !== null ? (float)$row['received_pct'] : null;
                        $isFinal = ($row['final_receipt_at'] !== null || (string)$row['state'] === 'استلام نهائي');
                        if (!$isFinal && $pct !== null && $pct > 0 && $pct < 100) {
                            $e18[] = "<span class='badge badge-warning' title='PartialReceived'>استلامٌ جزئي — متبقٍ "
                                   . htmlspecialchars(number_format(100 - $pct, 1)) . "٪</span>";
                        }
                        if (!$isFinal && !empty($row['expected_delivery_date'])
                            && $row['expected_delivery_date'] < date('Y-m-d')) {
                            $lateDays = (int) floor((time() - strtotime((string)$row['expected_delivery_date'])) / 86400);
                            $e18[] = "<span class='badge badge-danger' title='Late'>متأخرٌ "
                                   . $lateDays . " يومًا</span>";
                        }
                        echo "<td>" . ($e18 ? implode(' ', $e18) : "<span class='text-muted'>—</span>") . "</td>";
                        echo "<td>" . htmlspecialchars((string)($row['fin_approval_ref'] ?? '')) . "</td>";
                        echo "<td>" . htmlspecialchars((string)$row['created_at']) . "</td>";
                        echo "</tr>";
                    } }
                    ?>
                </tbody>
            </table>
        </div>
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
(function () {
    function recalcTotal() {
        var t = 0;
        $('#linesBody .proc-line').each(function () {
            var q = parseFloat($(this).find('.line-qty').val()) || 0;
            var p = parseFloat($(this).find('.line-price').val()) || 0;
            t += q * p;
        });
        $('#ordTotal').text(t.toFixed(2));
    }
    $(document).ready(function () {
        $('#procTable').DataTable({
            scrollX: true, autoWidth: false, stateSave: false, dom: 'Bfrtip',
            buttons: [
                { extend: 'copy', text: '📋 نسخ' },
                { extend: 'excel', text: '📊 Excel' },
                { extend: 'print', text: '🖨️ طباعة' }
            ],
            "language": { "url": "/ems/assets/i18n/datatables/ar.json" }
        });

        var toggleBtn = document.getElementById('toggleForm');
        if (toggleBtn) { toggleBtn.addEventListener('click', function () { $('#procForm').toggleClass('allforms-visible'); }); }

        $('#addLine').on('click', function () {
            var tpl = document.getElementById('lineTemplate');
            document.getElementById('linesBody').appendChild(document.importNode(tpl.content, true));
        });
        $(document).on('click', '.removeLine', function () {
            var rows = $('#linesBody .proc-line');
            if (rows.length > 1) { $(this).closest('.proc-line').remove(); }
            else { $(this).closest('.proc-line').find('input,select').val(''); }
            recalcTotal();
        });
        $(document).on('input', '.line-qty, .line-price', recalcTotal);
        $(document).on('change', '.line-item', function () {
            var txt = $(this).find('option:selected').text().trim();
            var $name = $(this).closest('.proc-line').find('.line-name');
            if (txt && !$name.val()) {
                var parts = txt.split(' — ');
                $name.val(parts.length > 1 ? parts[1] : txt);
            }
        });
        recalcTotal();
    });
})();
</script>
</body>
</html>
