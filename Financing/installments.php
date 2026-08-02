<?php
/**
 * Financing/installments.php — الأقساطُ والسداد (★ · update0007-ب F2)
 * ───────────────────────────────────────────────────────────────────────────
 * أقساطُ العمليات باستحقاقها — وتسجيلُ السداد يؤرّخ ويُرجع الرصيدَ محسوبًا
 * (outstanding يُحدَّث من مجموع المسدَّد لا يُحرَّر). المجالُ مقيَّد.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';

$company_id = intval($_SESSION['user']['company_id'] ?? 0);
$uid = intval($_SESSION['user']['id'] ?? 0);
$role = intval($_SESSION['user']['role'] ?? 0);
$granted = ($role === 26) || !empty($_SESSION['user']['is_super_admin']);
if (!$granted) {
    $g = mysqli_query($conn, "SELECT 1 FROM ownership_access_grants WHERE person_id = $uid AND state = 'active' LIMIT 1");
    $granted = $g && mysqli_num_rows($g) > 0;
}
if (!$granted) { http_response_code(403); die('المجالُ المقيَّد (FIN-01 §1.1)'); }

$op_filter = intval($_GET['op'] ?? 0);
$msg = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['pay_inst'])) {
    $iid = intval($_POST['pay_inst']);
    $ref = trim($_POST['payment_ref'] ?? '');
    if ($ref === '') { $msg = 'مرجعُ السداد إلزامي — لا سدادَ بلا مستند (422)'; }
    else {
        $r = mysqli_query($conn, "SELECT i.inst_id, i.op_id, i.amount_total FROM financing_installments i
                                  JOIN financing_operations o ON o.op_id = i.op_id AND o.company_id = $company_id
                                  WHERE i.inst_id = $iid AND i.state IN ('due','pending','Due','Pending')");
        if ($r && ($inst = mysqli_fetch_assoc($r))) {
            mysqli_begin_transaction($conn);
            $ok1 = mysqli_query($conn, "UPDATE financing_installments SET state='paid', paid_date=CURDATE(),
                                        payment_ref='" . mysqli_real_escape_string($conn, $ref) . "' WHERE inst_id = $iid");
            // الرصيدُ محسوبٌ من الدفتر: القائمُ = رأسُ المال − Σ المسدَّد أصلًا
            $ok2 = mysqli_query($conn, "UPDATE financing_operations o SET o.outstanding_balance =
                    GREATEST(0, o.capital - COALESCE((SELECT SUM(amount_principal) FROM financing_installments
                                                      WHERE op_id = o.op_id AND state = 'paid'), 0))
                    WHERE o.op_id = " . intval($inst['op_id']));
            if ($ok1 && $ok2) { mysqli_commit($conn); $msg = "سُدّد القسطُ #$iid بمرجع $ref — والرصيدُ أُعيد حسابُه"; }
            else { mysqli_rollback($conn); $msg = 'فشلت المعاملةُ فأُلغيت: ' . mysqli_error($conn); }
        } else { $msg = 'قسطٌ غيرُ مستحقٍّ أو مسدَّدٌ من قبل (409)'; }
    }
}

$rows = array();
$w = "o.company_id = $company_id" . ($op_filter ? " AND i.op_id = $op_filter" : '');
$r = mysqli_query($conn, "SELECT i.*, o.op_code, o.currency op_cur FROM financing_installments i
                          JOIN financing_operations o ON o.op_id = i.op_id
                          WHERE $w ORDER BY i.state = 'paid', i.due_date LIMIT 200");
if ($r) while ($x = mysqli_fetch_assoc($r)) $rows[] = $x;

$page_title = 'الأقساط والسداد';
include '../inheader.php';
include '../insidebar.php';
?>
<div class="main" dir="rtl">
  <div class="ems-topbar"><h4><i class="fa fa-calendar-check"></i> الأقساطُ والسداد<?= $op_filter ? ' — عملية #' . $op_filter : '' ?></h4></div>
  <?php if ($msg): ?><div class="alert alert-info"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
  <?php if ($op_filter): $ff_op_id = $op_filter; $ff_active = 'installments';
        include __DIR__ . '/../includes/financing_file_tabs.php'; endif; ?>
  <table class="table table-striped" data-no-dt>
    <thead><tr><th>العملية</th><th>#</th><th>الاستحقاق</th><th>أصل</th><th>ربح</th><th>الإجمالي</th><th>الحالة</th><th>سداد</th></tr></thead>
    <tbody>
    <?php if (empty($rows)): ?><tr><td colspan="8" class="text-center text-muted">لا أقساطَ<?= $op_filter ? ' لهذه العملية' : '' ?> — تُولَّد عند إنشاء العملية</td></tr><?php endif; ?>
    <?php foreach ($rows as $i):
        $paid = strtolower($i['state']) === 'paid';
        $late = !$paid && $i['due_date'] < date('Y-m-d'); ?>
      <tr<?= $late ? ' style="background:#fff3f3"' : '' ?>>
        <td><a href="operation_profile.php?id=<?= intval($i['op_id']) ?>"><?= htmlspecialchars($i['op_code'], ENT_QUOTES, 'UTF-8') ?></a></td>
        <td><?= intval($i['seq_no']) ?></td>
        <td><?= htmlspecialchars($i['due_date'], ENT_QUOTES, 'UTF-8') ?><?= $late ? ' <span class="badge" style="background:#dc3545">متأخر</span>' : '' ?></td>
        <td><?= number_format(floatval($i['amount_principal']), 2) ?></td>
        <td><?= number_format(floatval($i['amount_profit']), 2) ?></td>
        <td><strong><?= number_format(floatval($i['amount_total']), 2) ?></strong> <?= htmlspecialchars($i['currency'] ?: $i['op_cur'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= $paid ? '<span class="badge" style="background:#198754">مسدَّد ' . htmlspecialchars($i['paid_date'], ENT_QUOTES, 'UTF-8') . '</span>'
                       : '<span class="badge" style="background:#fd7e14">مستحق</span>' ?></td>
        <td>
          <?php if (!$paid): ?>
          <form method="post" style="display:flex;gap:6px">
            <input type="hidden" name="pay_inst" value="<?= intval($i['inst_id']) ?>">
            <input type="text" name="payment_ref" class="form-control form-control-sm" placeholder="مرجعُ السند" style="max-width:130px" required>
            <button class="action-btn" type="submit">سدّد</button>
          </form>
          <?php else: ?><?= htmlspecialchars($i['payment_ref'], ENT_QUOTES, 'UTF-8') ?><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
