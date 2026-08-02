<?php
/**
 * Workforce/proposed_deductions.php — الخصومُ المقترحة (★ الموارد · update0007-ب F14)
 * ───────────────────────────────────────────────────────────────────────────
 * الذممُ المدينةُ المقترحةُ على العاملين (fin_dues direction=debit pending) —
 * «الخصمُ لا يُرحَّل إلا بسلّم الموافقات الثلاثي» (DEC-01 ④) — عرضُ متابعةٍ
 * بمصادرها، والاعتمادُ في صندوق الاعتماد لا هنا.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';

$company_id = intval($_SESSION['user']['company_id'] ?? 0);
$rows = array();
$r = mysqli_query($conn,
    "SELECT d.id, d.party_type, d.party_ref, d.due_type, d.amount, d.currency,
            d.source_doc_type, d.source_doc_id, d.settlement_state, d.created_at,
            e.name emp_name
     FROM fin_dues d
     LEFT JOIN employees e ON d.party_type = 'employee' AND e.id = d.party_ref
     WHERE d.company_id = $company_id AND d.is_deleted = 0
       AND d.direction = 'debit' AND d.settlement_state = 'pending'
     ORDER BY d.created_at DESC LIMIT 100");
if ($r) while ($x = mysqli_fetch_assoc($r)) $rows[] = $x;

$page_title = 'الخصوم المقترحة';
include '../insidebar.php';
?>
<div class="main" dir="rtl">
  <div class="ems-topbar"><h4><i class="fa fa-minus-circle"></i> الخصومُ المقترحة — بانتظار السلّم الثلاثي</h4>
    <span class="badge" style="background:#fd7e14"><?= count($rows) ?></span></div>
  <p class="text-muted" style="font-size:.9em">كلُّ خصمٍ بمصدره (M-11: لا خصمَ بلا مستند) — والاعتمادُ من صندوق الاعتماد الجامع لا من هنا.</p>
  <table class="table table-striped" data-no-dt>
    <thead><tr><th>#</th><th>الطرف</th><th>النوع</th><th>المبلغ</th><th>المصدرُ المستندي</th><th>منذ</th></tr></thead>
    <tbody>
    <?php if (empty($rows)): ?><tr><td colspan="6" class="text-center" style="color:#198754">✔ صفرُ خصمٍ معلَّقٍ بلا قرار</td></tr><?php endif; ?>
    <?php foreach ($rows as $d): ?>
      <tr>
        <td><?= intval($d['id']) ?></td>
        <td><?= htmlspecialchars($d['emp_name'] !== null ? $d['emp_name'] : ($d['party_type'] . ' #' . $d['party_ref']), ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars($d['due_type'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><strong><?= number_format(floatval($d['amount']), 2) ?></strong> <?= htmlspecialchars($d['currency'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars(($d['source_doc_type'] !== null ? $d['source_doc_type'] : '؟') . ' #' . $d['source_doc_id'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars(substr($d['created_at'], 0, 10), ENT_QUOTES, 'UTF-8') ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
