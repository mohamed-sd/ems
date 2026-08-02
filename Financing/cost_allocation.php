<?php
/**
 * Financing/cost_allocation.php — توزيعُ تكلفة التمويل (★ · update0007-ب F6)
 * ───────────────────────────────────────────────────────────────────────────
 * «تكلفةُ التمويل تُحمَّل على المشروع تشغيليًّا بلا كشف مصدرها التمويلي —
 * والتوجيهُ في الخادم» (ORG-01 §3-④ · CAP-01): ربحُ كل عمليةٍ يوزَّع على
 * مشاريع أعيانها بنسبة حصصها — تقريرُ توزيعٍ للمجال المقيَّد.
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

/* توزيعُ ربح العملية على مشاريع أعيانها: أينما تعمل المعدةُ اليومَ (op_containers) */
$rows = array();
$r = mysqli_query($conn,
    "SELECT o.op_code, o.profit_amount, o.installments_no, o.currency,
            s.asset_id, e.name eq_name,
            COALESCE(s.approved_percent, s.percent) pct,
            p.name project_name
     FROM financing_operations o
     JOIN asset_ownership_shares s ON s.op_id = o.op_id AND (s.valid_to IS NULL OR s.valid_to >= CURDATE())
     LEFT JOIN equipments e ON e.id = s.asset_id AND s.asset_kind = 'equipment'
     LEFT JOIN op_containers oc ON oc.equipment_id = s.asset_id AND oc.is_deleted = 0
     LEFT JOIN project p ON p.id = oc.project_id
     WHERE o.company_id = $company_id AND o.state = 'active'
     GROUP BY o.op_id, s.share_id, p.id
     ORDER BY o.op_code, s.asset_id");
if ($r) while ($x = mysqli_fetch_assoc($r)) $rows[] = $x;

$page_title = 'توزيع تكلفة التمويل';
include '../insidebar.php';
?>
<div class="content-wrapper allforms" dir="rtl">
  <div class="ems-topbar"><h4><i class="fa fa-percentage"></i> توزيعُ تكلفة التمويل على المشاريع</h4></div>
  <p class="text-muted" style="font-size:.9em">الربحُ الشهريُّ لكل عمليةٍ (الإجماليُّ ÷ الأقساط) يوزَّع على مشروع كل عينٍ بنسبة حصتها — والتشغيلُ يرى تكلفةً محمَّلةً بلا مصدر.</p>
  <table class="table table-striped" data-no-dt>
    <thead><tr><th>العملية</th><th>العين</th><th>الحصة ٪</th><th>مشروعُ التشغيل الحالي</th><th>ربحُ العملية شهريًّا</th><th>المحمَّلُ على المشروع</th></tr></thead>
    <tbody>
    <?php if (empty($rows)): ?><tr><td colspan="6" class="text-center text-muted">لا عملياتِ تمويلٍ نشطةً بأعيانها</td></tr><?php endif; ?>
    <?php foreach ($rows as $a):
        $monthly = intval($a['installments_no']) > 0 ? floatval($a['profit_amount']) / intval($a['installments_no']) : 0;
        $alloc = $monthly * floatval($a['pct']) / 100; ?>
      <tr>
        <td><?= htmlspecialchars($a['op_code'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars($a['eq_name'] ?: '#' . $a['asset_id'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= floatval($a['pct']) ?></td>
        <td><?= htmlspecialchars($a['project_name'] ?: 'غيرُ مخصَّصةٍ حاليًّا', ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= number_format($monthly, 2) ?> <?= htmlspecialchars($a['currency'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><strong><?= number_format($alloc, 2) ?></strong></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
