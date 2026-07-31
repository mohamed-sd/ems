<?php
/**
 * Suppliers/rfq_requests.php — طلباتُ عروض الموردين ومقارنتُها (H-21)
 * ───────────────────────────────────────────────────────────────────────────
 * UX-05 §2.1: «مساحةُ عملٍ جديدة: **بنودُ الاحتياج من التزامات عقد العميل** —
 * إرسالٌ للمؤهلين وتتبّعُ الردود» · «مساحةُ قرارٍ: **جدولُ المقارنة الآلي**
 * (السعر · الجاهزية · السجل) واختيارٌ كاملٌ أو جزئيٌّ بالبنود ← التعاقد».
 * و**لا حقلَ لكمية بند**: البنودُ مشتقّةٌ من الالتزامات لا مكتوبة.
 */
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
require_once __DIR__ . '/../app/Services/Procurement/RFQService.php';

use App\Services\Procurement\RFQService as RFQ;

$current_role   = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$company_id     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$uid            = isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0;
if (!$is_super_admin && $company_id <= 0) {
    header("Location: ../login.php?msg=لا+توجد+بيئة+شركة+صالحة+❌"); exit();
}

$MODULE_CODE = 'Suppliers/rfq_requests.php';
$can_view = $can_add = $can_edit = false;
if ($is_super_admin) { $can_view = $can_add = $can_edit = true; }
else {
    $st = $conn->prepare("SELECT rp.can_view, rp.can_add, rp.can_edit FROM role_permissions rp
                            JOIN modules m ON m.id = rp.module_id
                           WHERE m.code = ? AND rp.role_id = ? LIMIT 1");
    $rid = intval($current_role);
    $st->bind_param('si', $MODULE_CODE, $rid);
    $st->execute();
    if ($row = $st->get_result()->fetch_assoc()) {
        $can_view = (intval($row['can_view']) === 1);
        $can_add  = (intval($row['can_add']) === 1);
        $can_edit = (intval($row['can_edit']) === 1);
    }
    $st->close();
}
if (!$can_view) { header("Location: ../main/dashboard.php?msg=لا+توجد+صلاحية+عرض+طلبات+العروض+❌"); exit(); }

$gate = $is_super_admin ? ems_tenant_db()->forAllTenants('rfq super') : ems_tenant_db();
$sel  = isset($_GET['rfq']) ? intval($_GET['rfq']) : 0;
$redirect = function ($msg, $rfq = 0) {
    header("Location: rfq_requests.php?msg=" . rawurlencode($msg) . ($rfq > 0 ? '&rfq=' . $rfq : ''));
    exit();
};

if ($_SERVER['REQUEST_METHOD'] === 'POST' && strval($_POST['rfq_action'] ?? '') !== '') {
    if (!$can_add && !$can_edit) { $redirect('لا توجد صلاحية ❌'); }
    $act = strval($_POST['rfq_action']);

    if ($act === 'open' && $can_add) {
        $r = RFQ::openFromContract($conn, $gate, $company_id, intval($_POST['contract_id'] ?? 0),
                                   strval($_POST['due_date'] ?? ''), $uid, strval($_POST['title'] ?? ''));
        $redirect($r['ok'] ? ($r['reason'] . ' ✅') : ($r['code'] . ' — ' . $r['reason'] . ' ❌'),
                  (int) $r['rfq_id']);
    }
    if ($act === 'send' && $can_edit) {
        $r = RFQ::send($conn, $gate, $company_id, intval($_POST['rfq_id'] ?? 0), $uid);
        $redirect($r['ok'] ? 'أُرسل الطلبُ للمؤهلين ✅' : ($r['code'] . ' — ' . $r['reason'] . ' ❌'),
                  intval($_POST['rfq_id'] ?? 0));
    }
    if ($act === 'close' && $can_edit) {
        $r = RFQ::close($conn, $gate, $company_id, intval($_POST['rfq_id'] ?? 0), $uid);
        $redirect($r['ok'] ? 'أُقفل الطلبُ — لا عرضَ بعده ✅' : ($r['code'] . ' — ' . $r['reason'] . ' ❌'),
                  intval($_POST['rfq_id'] ?? 0));
    }
    if ($act === 'quote' && $can_edit) {
        $r = RFQ::submitQuote($conn, $gate, $company_id, intval($_POST['line_id'] ?? 0),
            intval($_POST['supplier_id'] ?? 0), array(
                'unit_price' => strval($_POST['unit_price'] ?? ''),
                'qty_offered' => strval($_POST['qty_offered'] ?? ''),
                'readiness_days' => strval($_POST['readiness_days'] ?? ''),
                'currency' => strval($_POST['currency'] ?? 'SDG'),
            ), $uid);
        $redirect($r['ok'] ? 'سُجّل العرض ✅' : ($r['code'] . ' — ' . $r['reason'] . ' ❌'),
                  intval($_POST['rfq_id'] ?? 0));
    }
    if ($act === 'award' && $can_edit) {
        $awards = array();
        foreach (($_POST['award_qty'] ?? array()) as $k => $qty) {
            $qty = trim(strval($qty));
            if ($qty === '' || !is_numeric($qty) || (float) $qty <= 0) { continue; }
            $p = explode(':', strval($k));
            if (count($p) !== 2) { continue; }
            $awards[] = array('line_id' => (int) $p[0], 'supplier_id' => (int) $p[1],
                              'qty' => (float) $qty,
                              'reason' => strval($_POST['award_reason'] ?? ''));
        }
        if (!$awards) { $redirect('لا كمياتٍ مختارة ❌', intval($_POST['rfq_id'] ?? 0)); }
        $r = RFQ::award($conn, $gate, $company_id, intval($_POST['rfq_id'] ?? 0), $awards, $uid);
        $redirect($r['ok'] ? ($r['reason'] . ' ✅') : ($r['code'] . ' — ' . $r['reason'] . ' ❌'),
                  intval($_POST['rfq_id'] ?? 0));
    }
    if ($act === 'contracted' && $can_edit) {
        $r = RFQ::markContracted($conn, $gate, $company_id, intval($_POST['rfq_id'] ?? 0), $uid);
        $redirect($r['ok'] ? 'انتقل إلى «متعاقَد» ✅' : ($r['code'] . ' — ' . $r['reason'] . ' ❌'),
                  intval($_POST['rfq_id'] ?? 0));
    }
}

$rfqs = RFQ::listAll($gate);
$head = $sel > 0 ? RFQ::rfqOf($gate, $sel) : null;
$lines = $sel > 0 ? RFQ::linesOf($gate, $sel) : array();
$awards = $sel > 0 ? RFQ::awardsOf($gate, $sel) : array();

$suppliers = array();
try {
    $suppliers = $gate->scopedQuery(array('scope' => array('s' => 'suppliers')),
        "SELECT s.id, s.name FROM suppliers s WHERE {TENANT_SCOPE}
           AND COALESCE(s.is_deleted,0)=0 ORDER BY s.name LIMIT 200");
} catch (\Throwable $t) { $suppliers = array(); }

$ST = array('draft' => 'مسودة', 'sent' => 'مُرسَل', 'closed' => 'مُقفل',
            'awarded' => 'مُرسًى', 'contracted' => 'متعاقَد', 'cancelled' => 'ملغى');

$page_title = 'إيكوبيشن | طلبات عروض الموردين';
include '../inheader.php';
include '../insidebar.php';
?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'طلبات عروض الموردين (RFQ)'; $header_icon = 'fa fa-file-contract';
    $header_actions = array();
    $header_back = array('href' => '../main/dashboard.php', 'class' => '',
                         'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    if (isset($_GET['msg'])) { echo '<div class="alert alert-info">' . htmlspecialchars($_GET['msg']) . '</div>'; }
    ?>

    <div class="card"><div class="card-body">
        <p style="color:#4b5563;line-height:1.8;margin:0 0 10px">
            <i class="fas fa-circle-info"></i>
            <strong>بنودُ الاحتياج تُشتق من التزامات عقد العميل</strong> — ولا حقلَ لكتابة كمية.
            و<strong>عقدٌ بلا التزاماتٍ لا يُفتح له طلب</strong> · <strong>ولا عرضَ بعد الإقفال</strong> ·
            <strong>ولا يقرأ موردٌ عرضَ غيره</strong> · <strong>والترسيةُ جزئيةٌ ولا تجاوز الالتزام</strong>.
        </p>
        <?php if ($can_add): ?>
        <form method="post" class="ems-form">
            <input type="hidden" name="rfq_action" value="open">
            <div class="form-grid">
                <div class="form-group"><label>عقدُ العميل <span style="color:#c00">*</span>
                    <small>— «فتحُ الاحتياج» من العقد</small></label>
                    <input type="number" name="contract_id" min="1" required></div>
                <div class="form-group"><label>موعدُ الإقفال <span style="color:#c00">*</span></label>
                    <input type="date" name="due_date" required></div>
                <div class="form-group"><label>عنوانٌ</label><input type="text" name="title" maxlength="160"></div>
            </div>
            <div style="margin-top:12px"><button type="submit" class="btn-save">
                <i class="fa fa-file-circle-plus"></i> افتح طلبًا من التزامات العقد</button></div>
        </form>
        <?php endif; ?>
    </div></div>

    <div class="card"><div class="card-header"><h5><i class="fa fa-list"></i> الطلبات</h5></div>
    <div class="card-body"><div class="table-container">
        <table class="alltables display nowrap no-datatable" data-no-dt="1" style="width:100%">
            <thead><tr><th>الرقم</th><th>العقد</th><th>العنوان</th><th>الإقفال</th><th>الحال</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($rfqs as $r): ?>
                <tr><td><strong><?php echo htmlspecialchars((string)$r['rfq_no']); ?></strong></td>
                    <td>#<?php echo intval($r['client_contract_id']); ?></td>
                    <td><?php echo htmlspecialchars((string)($r['title'] ?? '—')); ?></td>
                    <td><?php echo htmlspecialchars((string)$r['due_date']); ?></td>
                    <td><span class="badge badge-info"><?php echo htmlspecialchars($ST[(string)$r['state']] ?? (string)$r['state']); ?></span></td>
                    <td><a class="action-btn" href="?rfq=<?php echo intval($r['id']); ?>"><i class="fa fa-eye"></i> افتح</a></td></tr>
            <?php endforeach; ?>
            <?php if (!$rfqs): ?><tr><td colspan="6"><em>لا طلباتٍ بعد</em></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div></div></div>

    <?php if ($head): ?>
    <div class="card"><div class="card-header"><h5><i class="fa fa-scale-unbalanced"></i>
        <?php echo htmlspecialchars((string)$head['rfq_no']); ?> —
        <span class="badge badge-info"><?php echo htmlspecialchars($ST[(string)$head['state']] ?? ''); ?></span></h5></div>
    <div class="card-body">
        <?php if ($can_edit): ?>
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px">
            <?php foreach (array('send' => 'أرسِل للمؤهلين', 'close' => 'أقفل الطلب',
                                 'contracted' => 'انتقل إلى متعاقَد') as $a => $lbl): ?>
            <form method="post" style="display:inline">
                <input type="hidden" name="rfq_action" value="<?php echo $a; ?>">
                <input type="hidden" name="rfq_id" value="<?php echo $sel; ?>">
                <button type="submit" class="btn-save"><?php echo $lbl; ?></button>
            </form>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php foreach ($lines as $l):
            $cmp = RFQ::comparison($gate, (int) $l['id']);
            $avail = round((float)$l['qty_required'] - (float)$l['qty_awarded'], 2); ?>
        <div class="card" style="margin-bottom:14px"><div class="card-body">
            <h5 style="margin:0 0 8px">بند <?php echo intval($l['line_no']); ?> —
                <?php echo htmlspecialchars((string)$l['description']); ?></h5>
            <div style="margin-bottom:8px">
                <span class="badge badge-secondary">المطلوب <?php echo htmlspecialchars((string)$l['qty_required']); ?>
                    <?php echo htmlspecialchars((string)($l['unit_type'] ?? '')); ?></span>
                <span class="badge <?php echo $avail > 0 ? 'badge-warning' : 'badge-success'; ?>">
                    المرسى <?php echo htmlspecialchars((string)$l['qty_awarded']); ?> · المتاح <?php echo $avail; ?></span>
            </div>
            <div class="table-container">
            <table class="alltables display nowrap no-datatable" data-no-dt="1" style="width:100%">
                <thead><tr><th>المورد</th><th>السعر/وحدة</th><th>الجاهزية</th><th>السجل</th>
                    <th>المعروض</th><?php if ($can_edit) echo '<th>اختيارُ الكمية</th>'; ?></tr></thead>
                <tbody>
                <?php foreach ($cmp as $q): ?>
                    <tr><td><?php echo htmlspecialchars((string)($q['supplier_name'] ?? ('#' . intval($q['supplier_id'])))); ?></td>
                        <td><strong><?php echo htmlspecialchars((string)$q['unit_price']); ?></strong>
                            <?php echo $q['is_cheapest'] ? '<span class="badge badge-success">الأرخص</span>' : ''; ?></td>
                        <td><?php echo $q['readiness_days'] !== null ? (intval($q['readiness_days']) . ' يوم') : '—'; ?>
                            <?php echo $q['is_fastest'] ? '<span class="badge badge-info">الأسرع</span>' : ''; ?></td>
                        <td><?php echo $q['record_rating'] !== null
                            ? htmlspecialchars((string)$q['record_rating'])
                            : '<span class="badge badge-secondary">بلا تقييم</span>'; ?>
                            <?php echo $q['is_best_record'] ? '<span class="badge badge-info">الأعلى</span>' : ''; ?></td>
                        <td><?php echo htmlspecialchars((string)$q['qty_offered']); ?></td>
                        <?php if ($can_edit): ?>
                        <td><input form="awardForm<?php echo intval($l['id']); ?>" type="number" step="0.01" min="0"
                            name="award_qty[<?php echo intval($l['id']); ?>:<?php echo intval($q['supplier_id']); ?>]"
                            style="width:110px" placeholder="0"></td>
                        <?php endif; ?></tr>
                <?php endforeach; ?>
                <?php if (!$cmp): ?><tr><td colspan="6"><em>لا عروضَ بعد لهذا البند</em></td></tr><?php endif; ?>
                </tbody>
            </table>
            </div>
            <?php if ($can_edit && $cmp): ?>
            <form id="awardForm<?php echo intval($l['id']); ?>" method="post" style="margin-top:10px">
                <input type="hidden" name="rfq_action" value="award">
                <input type="hidden" name="rfq_id" value="<?php echo $sel; ?>">
                <input type="text" name="award_reason" maxlength="200" style="width:260px"
                       placeholder="حجّةُ الاختيار حين لا يكون الأرخص">
                <button type="submit" class="btn-save"><i class="fa fa-gavel"></i> ترسية</button>
            </form>
            <?php endif; ?>

            <?php if ($can_edit && (string)$head['state'] === 'sent'): ?>
            <form method="post" class="ems-form" style="margin-top:10px">
                <input type="hidden" name="rfq_action" value="quote">
                <input type="hidden" name="rfq_id" value="<?php echo $sel; ?>">
                <input type="hidden" name="line_id" value="<?php echo intval($l['id']); ?>">
                <div class="form-grid">
                    <div class="form-group"><label>المورد</label>
                        <select name="supplier_id" required><?php foreach ($suppliers as $s): ?>
                            <option value="<?php echo intval($s['id']); ?>"><?php echo htmlspecialchars((string)$s['name']); ?></option>
                        <?php endforeach; ?></select></div>
                    <div class="form-group"><label>السعر/وحدة</label>
                        <input type="number" name="unit_price" step="0.0001" min="0.0001" required></div>
                    <div class="form-group"><label>الكمية المعروضة</label>
                        <input type="number" name="qty_offered" step="0.01" min="0.01" required></div>
                    <div class="form-group"><label>الجاهزية (يوم)</label>
                        <input type="number" name="readiness_days" min="0"></div>
                    <div class="form-group"><label>العملة</label>
                        <input type="text" name="currency" value="SDG" maxlength="8"></div>
                </div>
                <div style="margin-top:8px"><button type="submit" class="btn-save">
                    <i class="fa fa-plus"></i> سجّل عرضًا</button></div>
            </form>
            <?php endif; ?>
        </div></div>
        <?php endforeach; ?>

        <?php if ($awards): ?>
        <h5 style="margin:14px 0 8px"><i class="fa fa-gavel"></i> الترسيات</h5>
        <div class="table-container">
        <table class="alltables display nowrap no-datatable" data-no-dt="1" style="width:100%">
            <thead><tr><th>البند</th><th>المورد</th><th>الكمية</th><th>السعر</th><th>القيمة</th><th>الحجّة</th></tr></thead>
            <tbody>
            <?php foreach ($awards as $a): ?>
                <tr><td><?php echo intval($a['line_id']); ?></td>
                    <td><?php echo htmlspecialchars((string)($a['supplier_name'] ?? ('#' . intval($a['supplier_id'])))); ?></td>
                    <td><strong><?php echo htmlspecialchars((string)$a['qty_awarded']); ?></strong></td>
                    <td><?php echo htmlspecialchars((string)$a['unit_price']); ?>
                        <?php echo htmlspecialchars((string)$a['currency']); ?></td>
                    <td><?php echo number_format((float)$a['qty_awarded'] * (float)$a['unit_price'], 2); ?></td>
                    <td style="white-space:normal"><small><?php echo htmlspecialchars((string)($a['reason'] ?? '')); ?></small></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div></div>
    <?php endif; ?>
</div>

<script src="../includes/js/jquery-3.7.1.main.js"></script>
</body>
</html>
