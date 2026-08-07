<?php
/**
 * Procurement/po_match.php — مطابقة الفاتورة بالأمر والاستلام (v3 · إسقاط حي)
 * ───────────────────────────────────────────────────────────────────────────
 * أُعيد بناؤها 2026-08-06 (قرار المالك «حل المهام الحرجة»): كانت v2 مخزنًا
 * بينيًّا (cmp03_screen_rows) ببذورٍ مبعثرةٍ لا صلةَ لها بمحرك المطابقة —
 * فصارت **قائمةَ عملِ المطابقة الثلاثية الحقيقية** فوق proc_order:
 *   · تعرض كلَّ أمرٍ بلغ الاستلام (أولي/نهائي/مطابَق/مغلق) بكمياته وقيمه
 *   · «تسجيل فاتورة ومطابقة» يستدعي proc_match_invoice (الأمر × الاستلام
 *     × الفاتورة): ضمن السماح → matched + دَينُ المورد؛ فوقه → var_pending
 *     بلا دَينٍ حتى قرار (UX-09 §8.2 «لا استحقاقَ بلا مطابقة»)
 *   · الشاشة مسجَّلة في modules (#255) — الحارس خادميٌّ لا واجهة (M-14)
 * الإجراء نفسُه متاح من شاشة الأمر (orders_proc?edit_id) — محرّكٌ واحدٌ للقناتين.
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

$perms = proc_page_perms($conn, 'Procurement/po_match.php', $is_super_admin);
$can_view = $perms['can_view']; $can_edit = $perms['can_edit'];
if (!$can_view) {
    header("Location: ../main/dashboard.php?msg=لا+توجد+صلاحية+عرض+المطابقة+❌");
    exit();
}

// ── تسجيلُ الفاتورة والمطابقة (المحرك الواحد proc_match_invoice) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'match_invoice') {
    if (!$can_edit) { header("Location: po_match.php?msg=لا+توجد+صلاحية+❌"); exit(); }
    $mid = intval($_POST['order_id'] ?? 0);
    $res = proc_match_invoice(
        $conn, $mid,
        $_POST['invoice_no'] ?? '',
        $_POST['invoice_date'] ?? '',
        $_POST['invoice_amount'] ?? 0,
        $current_user_id,
        $_POST['invoice_tax'] ?? 0
    );
    if ($res['status'] === 'matched') {
        $msg = 'طوبقت الفاتورةُ وفُتح استحقاقُ المورد' . ($res['due_id'] ? ' (ذمة #' . $res['due_id'] . ')' : '') . ' ✅';
    } elseif ($res['status'] === 'var_pending') {
        $msg = 'فرقٌ فوق السماح — وقفت المطابقة بلا استحقاق: ' . $res['reason'] . ' — احسمه من زر «حسم الفرق» ⚠️';
    } else {
        $msg = 'تعذّرت المطابقة: ' . ($res['reason'] !== '' ? $res['reason'] : 'خطأٌ داخلي') . ' ❌';
    }
    header("Location: po_match.php?msg=" . urlencode($msg)); exit();
}

// ── حسمُ الفرق المعلَّق بقرارٍ موثَّق (proc_match_resolve) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'resolve_variance') {
    if (!$can_edit) { header("Location: po_match.php?msg=لا+توجد+صلاحية+❌"); exit(); }
    $rid = intval($_POST['order_id'] ?? 0);
    $res = proc_match_resolve($conn, $rid, $_POST['decision'] ?? '', $_POST['reason'] ?? '', $current_user_id);
    if ($res['status'] === 'resolved') {
        $msg = 'حُسم الفرقُ (' . ($_POST['decision'] ?? '') . ')' . ($res['due_id'] ? ' — ذمة #' . $res['due_id'] : '') . ' ✅';
    } else {
        $msg = 'تعذّر الحسم: ' . ($res['reason'] !== '' ? $res['reason'] : 'خطأٌ داخلي') . ' ❌';
    }
    header("Location: po_match.php?msg=" . urlencode($msg)); exit();
}

// ── البيانات: الأوامر التي بلغت الاستلام (أضلاع المطابقة قائمة) ──
$gate = proc_gate($is_super_admin);
$orders = $gate->select('proc_order', array(
    'whereRaw' => "state IN ('استلام أولي', 'استلام نهائي', 'مطابَق', 'مغلق')",
    'orderBy'  => 'id DESC',
));

// خرائطُ الإثراء (استعلامٌ مجمَّعٌ لكلٍّ — لا استعلامَ لكل صف)
$sup_map = array();
foreach ($gate->select('proc_supplier', array('columns' => array('id', 'name'))) as $s) {
    $sup_map[intval($s['id'])] = (string) $s['name'];
}

$ordered_map = array();
foreach ($gate->scopedQuery(array('scope' => array('ol' => 'proc_order_line')),
    "SELECT ol.order_id, COALESCE(SUM(ol.qty),0) s FROM proc_order_line ol
      WHERE {TENANT_SCOPE} GROUP BY ol.order_id") as $r) {
    $ordered_map[intval($r['order_id'])] = (float) $r['s'];
}

$received_map = array(); $receipt_code_map = array();
foreach ($gate->scopedQuery(
    array('scope' => array('rc' => 'proc_receipt_custody'), 'enrich' => array('rl' => 'proc_receipt_line')),
    "SELECT rc.order_id, COALESCE(SUM(rl.qty),0) s, MAX(rc.code) last_code
       FROM proc_receipt_custody rc
       LEFT JOIN proc_receipt_line rl ON rl.custody_id = rc.id
      WHERE {TENANT_SCOPE} AND rc.order_id IS NOT NULL AND COALESCE(rc.is_deleted,0) = 0
      GROUP BY rc.order_id") as $r) {
    $received_map[intval($r['order_id'])]    = (float) $r['s'];
    $receipt_code_map[intval($r['order_id'])] = (string) $r['last_code'];
}

$dues_map = array();
foreach ($gate->select('fin_dues', array(
    'columns'  => array('id', 'period_ref', 'settlement_state'),
    'whereRaw' => "party_type = 'proc_supplier' AND due_type = 'purchase'",
)) as $d) {
    $dues_map[(string) $d['period_ref']] = $d;
}

// اسمُ الكيان (عرضًا)
$entity_name = '—';
if ($company_id > 0) {
    $st = $conn->prepare('SELECT name FROM admin_companies WHERE id = ? LIMIT 1');
    $st->bind_param('i', $company_id);
    $st->execute();
    $er = $st->get_result()->fetch_assoc();
    if ($er) { $entity_name = (string) $er['name']; }
}

// ── نموذجُ المطابقة لأمرٍ بعينه (?match_id=N) ──
$match_order = null;
if (isset($_GET['match_id']) && $can_edit) {
    $match_order = $gate->selectOne('proc_order', array('where' => array('id' => intval($_GET['match_id']))));
    if ($match_order && !in_array((string) $match_order['state'], proc_order_expense_states(), true)) {
        $match_order = null;   // لا مطابقةَ قبل الاستلام النهائي — نفسُ بوابة المحرك
    }
}

$badge = function ($state) {
    switch ($state) {
        case 'matched':     return '<span style="color:#1a7f37;font-weight:700">مطابَق ✔</span>';
        case 'var_pending': return '<span style="color:#b35900;font-weight:700">فرقٌ معلَّق ⚠</span>';
        case 'rejected':    return '<span style="color:#b3261e;font-weight:700">فاتورةٌ مرفوضة ✖</span>';
        default:            return '<span style="color:#888">لم تُسجَّل فاتورة</span>';
    }
};

// ── نموذجُ حسم الفرق (?resolve_id=N) ──
$resolve_order = null;
if (isset($_GET['resolve_id']) && $can_edit) {
    $resolve_order = $gate->selectOne('proc_order', array('where' => array('id' => intval($_GET['resolve_id']))));
    if ($resolve_order && (string) $resolve_order['match_state'] !== 'var_pending') { $resolve_order = null; }
}

$page_title = 'إيكوبيشن | مطابقة الفاتورة بالأمر والاستلام';
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>

<div class="main proc-match-main ems-unified-page-shell">
    <?php
    $header_title = 'مطابقة الفاتورة بالأمر والاستلام';
    $header_icon  = 'fa fa-scale-balanced';
    $header_actions = array();
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    ?>

    <?php proc_msg_banner(); ?>

    <?php if ($match_order):
        $oid   = intval($match_order['id']);
        $oQty  = isset($ordered_map[$oid]) ? $ordered_map[$oid] : 0.0;
        $rQty  = isset($received_map[$oid]) ? $received_map[$oid] : 0.0;
        $oAmt  = (float) $match_order['total_amount'];
        $tol   = proc_match_tolerance($oAmt);
    ?>
    <form action="po_match.php" method="post" class="allforms allforms-visible">
        <div class="card-header"><h5><i class="fas fa-file-invoice"></i>
            تسجيل فاتورة المورد — أمر <?php echo htmlspecialchars((string) $match_order['code']); ?></h5></div>
        <div class="card"><div class="card-body">
            <input type="hidden" name="action" value="match_invoice">
            <input type="hidden" name="order_id" value="<?php echo $oid; ?>">
            <div class="form-section">
                <p style="margin:0 0 10px">
                    المورد: <strong><?php echo htmlspecialchars(isset($sup_map[intval($match_order['supplier_id'])]) ? $sup_map[intval($match_order['supplier_id'])] : '—'); ?></strong>
                    · الكمية بالأمر <strong><?php echo number_format($oQty, 2); ?></strong>
                    · المستلَمة <strong><?php echo number_format($rQty, 2); ?></strong>
                    · قيمة الأمر <strong><?php echo number_format($oAmt, 2) . ' ' . htmlspecialchars((string) $match_order['currency']); ?></strong>
                    · حدُّ السماح ±<strong><?php echo number_format($tol, 2); ?></strong>
                    <small>(الكميةُ بلا سماحٍ إطلاقًا)</small>
                </p>
                <div class="form-grid">
                    <div class="form-group">
                        <label>رقم فاتورة المورد <span class="required">*</span></label>
                        <input type="text" name="invoice_no" required
                               value="<?php echo htmlspecialchars((string) ($match_order['invoice_no'] ?? '')); ?>">
                    </div>
                    <div class="form-group">
                        <label>تاريخ الفاتورة</label>
                        <input type="date" name="invoice_date"
                               value="<?php echo htmlspecialchars((string) ($match_order['invoice_date'] ?? '')); ?>">
                    </div>
                    <div class="form-group">
                        <label>قيمة الفاتورة (إجمالي) <span class="required">*</span></label>
                        <input type="number" step="0.01" min="0.01" name="invoice_amount" required
                               value="<?php echo htmlspecialchars((string) ($match_order['invoice_amount'] ?? '')); ?>">
                    </div>
                    <div class="form-group">
                        <label>الضريبة ضمن القيمة <small>(تُفصل — المطابقة على الصافي)</small></label>
                        <input type="number" step="0.01" min="0" name="invoice_tax"
                               value="<?php echo htmlspecialchars((string) ($match_order['tax_amount'] ?? '0')); ?>">
                    </div>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn-save"><i class="fas fa-scale-balanced"></i> مطابقة</button>
                <a href="po_match.php" class="btn-cancel"><i class="fas fa-times"></i> إلغاء</a>
            </div>
        </div></div>
    </form>
    <?php endif; ?>

    <?php if ($resolve_order):
        $rid  = intval($resolve_order['id']);
        $rAmt = (float) $resolve_order['total_amount'];
        $rInv = (float) $resolve_order['invoice_amount'];
    ?>
    <form action="po_match.php" method="post" class="allforms allforms-visible">
        <div class="card-header"><h5><i class="fas fa-gavel"></i>
            حسم الفرق المعلَّق — أمر <?php echo htmlspecialchars((string) $resolve_order['code']); ?></h5></div>
        <div class="card"><div class="card-body">
            <input type="hidden" name="action" value="resolve_variance">
            <input type="hidden" name="order_id" value="<?php echo $rid; ?>">
            <div class="form-section">
                <p style="margin:0 0 10px">
                    فاتورة <strong><?php echo htmlspecialchars((string) $resolve_order['invoice_no']); ?></strong>
                    بقيمة <strong><?php echo number_format($rInv, 2); ?></strong>
                    مقابل أمرٍ بقيمة <strong><?php echo number_format($rAmt, 2); ?></strong>
                    — الفرق <strong style="color:#b35900"><?php echo number_format($rInv - $rAmt, 2); ?></strong>
                </p>
                <div class="form-grid">
                    <div class="form-group">
                        <label>القرار <span class="required">*</span></label>
                        <select name="decision" required>
                            <option value="قبول الفرق">قبول الفرق — الذمّة بقيمة الفاتورة كاملة</option>
                            <option value="إشعار دائن">إشعار دائن — الذمّة بقيمة الأمر والفرق يُنتظر له إشعار المورد</option>
                            <option value="رفض الفاتورة">رفض الفاتورة — لا ذمّة، وتسجيل فاتورة بديلة متاح</option>
                        </select>
                    </div>
                    <div class="form-group" style="grid-column:1/-1">
                        <label>تفسير القرار <span class="required">*</span> <small>(لا حسمَ بلا تفسير — يُختم باسمك ولحظته)</small></label>
                        <input type="text" name="reason" required maxlength="255">
                    </div>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn-save"><i class="fas fa-gavel"></i> حسم</button>
                <a href="po_match.php" class="btn-cancel"><i class="fas fa-times"></i> إلغاء</a>
            </div>
        </div></div>
    </form>
    <?php endif; ?>

    <div class="card"><div class="card-body">
        <div class="table-container">
            <table id="procTable" class="display nowrap alltables" style="width:100%;">
                <thead><tr>
                    <th>الإجراءات</th>
                    <th>أمر الشراء</th>
                    <th>المورد</th>
                    <th>سند الاستلام</th>
                    <th>رقم فاتورة المورد</th>
                    <th>تاريخ الفاتورة</th>
                    <th>الكمية بالأمر</th>
                    <th>الكمية المستلَمة</th>
                    <th>فرق الكمية</th>
                    <th>قيمة الأمر</th>
                    <th>قيمة الفاتورة</th>
                    <th>فرق القيمة</th>
                    <th>حد السماح</th>
                    <th>نتيجة المطابقة</th>
                    <th>قرار الحسم</th>
                    <th>تاريخ المطابقة</th>
                    <th>حالة الأمر</th>
                    <th>الاستحقاق</th>
                    <th>الكيان</th>
                    <!-- E-03 موجة ٤: النواة الحاكمة (gov_columns) — الخلايا يحشوها ui-unification.js -->
                    <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
                    <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
                    <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
                    <th class="ems-gov-th none" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
                    <th class="ems-gov-th none" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
                    <th class="ems-gov-th none" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
                    <th class="ems-gov-th none" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
                    <th class="ems-gov-th none" data-gov="cost_center" data-slice="3" title="وجهة التحميل">مركز التكلفة</th>
                    <th class="ems-gov-th none" data-gov="fx_rate_source" data-slice="3" title="ما خالف عملة الدفاتر يحمل السعر ومصدره">سعر الصرف ومصدره</th>
                    </tr></thead>
                <tbody>
                <?php foreach ($orders as $po):
                    $oid  = intval($po['id']);
                    $oQty = isset($ordered_map[$oid]) ? $ordered_map[$oid] : 0.0;
                    $rQty = isset($received_map[$oid]) ? $received_map[$oid] : 0.0;
                    $oAmt = (float) $po['total_amount'];
                    $iAmt = ($po['invoice_amount'] !== null && $po['invoice_amount'] !== '') ? (float) $po['invoice_amount'] : null;
                    $tol  = proc_match_tolerance($oAmt);
                    $due  = isset($dues_map['PO-' . $oid]) ? $dues_map['PO-' . $oid] : null;
                    $matchable = in_array((string) $po['state'], proc_order_expense_states(), true)
                                 && (string) $po['match_state'] !== 'matched';
                ?>
                    <tr>
                        <td>
                            <?php if ($can_edit && (string) $po['match_state'] === 'var_pending'): ?>
                                <a href="po_match.php?resolve_id=<?php echo $oid; ?>" class="add-btn" style="padding:4px 10px;background:#b35900"
                                   title="حسم الفرق بقرار موثق"><i class="fas fa-gavel"></i> حسم الفرق</a>
                                <a href="po_match.php?match_id=<?php echo $oid; ?>" style="margin-inline-start:4px"
                                   title="إعادة المطابقة بفاتورة معدلة">إعادة المطابقة</a>
                            <?php elseif ($can_edit && $matchable): ?>
                                <a href="po_match.php?match_id=<?php echo $oid; ?>" class="add-btn" style="padding:4px 10px"
                                   title="تسجيل فاتورة ومطابقة"><i class="fas fa-file-invoice"></i> تسجيل فاتورة</a>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars((string) $po['code']); ?></td>
                        <td><?php echo htmlspecialchars(isset($sup_map[intval($po['supplier_id'])]) ? $sup_map[intval($po['supplier_id'])] : '—'); ?></td>
                        <td><?php echo htmlspecialchars(isset($receipt_code_map[$oid]) ? $receipt_code_map[$oid] : '—'); ?></td>
                        <td><?php echo htmlspecialchars((string) ($po['invoice_no'] ?? '') !== '' ? (string) $po['invoice_no'] : '—'); ?></td>
                        <td><?php echo htmlspecialchars((string) ($po['invoice_date'] ?? '') !== '' ? (string) $po['invoice_date'] : '—'); ?></td>
                        <td><?php echo number_format($oQty, 2); ?></td>
                        <td><?php echo number_format($rQty, 2); ?></td>
                        <td><?php echo number_format($rQty - $oQty, 2); ?></td>
                        <td><?php echo number_format($oAmt, 2) . ' ' . htmlspecialchars((string) $po['currency']); ?></td>
                        <td><?php echo ($iAmt !== null) ? number_format($iAmt, 2) : '—'; ?></td>
                        <td><?php echo ($iAmt !== null) ? number_format($iAmt - $oAmt, 2) : '—'; ?></td>
                        <td>±<?php echo number_format($tol, 2); ?></td>
                        <td><?php echo $badge((string) $po['match_state']); ?></td>
                        <td><?php echo ((string) ($po['var_decision'] ?? '') !== '')
                            ? htmlspecialchars((string) $po['var_decision']) . ' <small title="' . htmlspecialchars((string) ($po['var_decision_reason'] ?? '')) . '">(' . htmlspecialchars((string) ($po['var_decided_at'] ?? '')) . ')</small>'
                            : '—'; ?></td>
                        <td><?php echo htmlspecialchars((string) ($po['matched_at'] ?? '') !== '' ? (string) $po['matched_at'] : '—'); ?></td>
                        <td><?php echo htmlspecialchars((string) $po['state']); ?></td>
                        <td><?php echo $due
                            ? ('ذمة #' . intval($due['id']) . ' (' . htmlspecialchars((string) $due['settlement_state']) . ')')
                            : '—'; ?></td>
                        <td><?php echo htmlspecialchars($entity_name); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div></div>
</div>

</body>
</html>
