<?php
/**
 * Financing/owners_registry.php — الملكيةُ والمُلّاك (★ · update0007-ب F4)
 * ───────────────────────────────────────────────────────────────────────────
 * سجلُّ ملكية المعدات وحصصُها القائمة — أشدُّ المجال المقيَّد سريةً:
 * «المعدةُ تحمل أسماءَ المُلّاك» (ORG-01 v4) — وكلُّ اطّلاعٍ يُسجَّل.
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
if (!$granted) { http_response_code(403); die('المجالُ المقيَّد — سجلُّ المُلّاك بمنحٍ فردي (FIN-01 §1.1)'); }

/* كلُّ اطّلاعٍ على السجل يُسجَّل — سجلُّ اطّلاع المجال المقيَّد */
mysqli_query($conn, "INSERT INTO action_execution_log (company_id, action_code, person_id, subject_ref, result)
                     VALUES ($company_id, 'ownership.registry.view', $uid, 'owners_registry', 'allowed')");

$rows = array();
$r = mysqli_query($conn,
    "SELECT r.equipment_id, e.name eq_name, r.owner_kind, r.owner_ref, r.ownership_note,
            s.approved_percent, s.percent, s.valid_from, s.valid_to, le.name_ar financier
     FROM equipment_ownership_registry r
     LEFT JOIN equipments e ON e.id = r.equipment_id
     LEFT JOIN asset_ownership_shares s ON s.asset_id = r.equipment_id AND s.asset_kind = 'equipment'
                                        AND (s.valid_to IS NULL OR s.valid_to >= CURDATE())
     LEFT JOIN legal_entities le ON le.entity_id = s.financier_entity_id
     ORDER BY r.equipment_id");
if ($r) while ($x = mysqli_fetch_assoc($r)) $rows[] = $x;

$page_title = 'الملكية والملاك';
include '../insidebar.php';
?>
<div class="content-wrapper allforms" dir="rtl">
  <div class="ems-topbar"><h4><i class="fa fa-user-shield"></i> الملكيةُ والمُلّاك — سجلٌّ مقيَّد</h4>
    <span class="badge" style="background:#dc3545">كلُّ اطّلاعٍ مسجَّل</span></div>
  <table class="table table-striped" data-no-dt>
    <thead><tr><th>المعدة</th><th>نوعُ المالك</th><th>المرجع</th><th>الممولُ الحالي</th><th>حصتُه ٪</th><th>سريانُها</th><th>ملاحظة</th></tr></thead>
    <tbody>
    <?php if (empty($rows)): ?><tr><td colspan="7" class="text-center text-muted">السجلُّ فارغ</td></tr><?php endif; ?>
    <?php foreach ($rows as $o): ?>
      <tr>
        <td><?= htmlspecialchars($o['eq_name'] ?: '#' . $o['equipment_id'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars($o['owner_kind'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars($o['owner_ref'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars($o['financier'] ?: '—', ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= $o['approved_percent'] !== null ? floatval($o['approved_percent']) : ($o['percent'] !== null ? floatval($o['percent']) : '—') ?></td>
        <td><?= $o['valid_from'] ? htmlspecialchars($o['valid_from'] . ' ← ' . ($o['valid_to'] ?: 'ساري'), ENT_QUOTES, 'UTF-8') : '—' ?></td>
        <td><?= htmlspecialchars(mb_substr($o['ownership_note'] ?? '', 0, 40), ENT_QUOTES, 'UTF-8') ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <p><a class="action-btn" href="asset_disposal.php"><i class="fa fa-exchange-alt"></i> التصرفُ في الأصل — نقلُ حصةٍ أو بيعُها</a></p>
</div>
