<?php
/**
 * Procurement/_board_waiting.php — «معداتٌ بانتظارِ قطع» (نزيفُ التوقف · §16)
 * ═══════════════════════════════════════════════════════════════════════════
 * كان في جسدِ `Procurement/dashboard_proc.php`، ولمّا نُقلت اللوحةُ إلى
 * «الرئيسية» (قرارُ المالك 2026-08-21) استُخرج جزءًا يُعلَن في إعدادِ الدور
 * (`view`) — فلا يسقط بالنقل. المصدرُ هو هو (`MntProcBridgeService`)،
 * والأعمدةُ والمفاتيحُ منقولةٌ حرفًا عن الجدولِ الأصليّ.
 *
 * ◆ ولا يُصيَّر شيءٌ إذا لم يكن ثمّةَ انتظار: «المُنجَزُ يختفي» (UX-01 §9).
 * يتوقّع: $conn · وشركةَ الجلسة.
 */
if (!isset($conn)) { return; }
require_once dirname(__DIR__) . '/app/Services/Procurement/MntProcBridgeService.php';

$procWaitCid = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$procWaiting = array();
try {
    $procWaiting = \App\Services\Procurement\MntProcBridgeService::waitingEquipment($conn, $procWaitCid, 8);
} catch (\Throwable $t) { error_log('proc board waiting: ' . $t->getMessage()); }

if (empty($procWaiting)) { return; }
?>
<div class="shot-ops-box shot-ops-wide">
  <h4 class="shot-ops-box-title">
    <i class="fas fa-triangle-exclamation"></i>
    معداتٌ بانتظار قطع (<?= count($procWaiting) ?>) — كلُّ يومٍ هنا إيرادُ تأجيرٍ ضائع
  </h4>
  <div class="table-container">
    <table class="alltables" data-no-dt="hard">
      <thead><tr><th>المعدة</th><th>أمر الصيانة</th><th>الانتظار (يوم)</th><th>طلب الشراء</th><th>حالته</th><th>الأولوية</th></tr></thead>
      <tbody>
        <?php foreach ($procWaiting as $we): ?>
        <tr<?= intval($we['waiting_days']) > 7 ? ' class="shot-ops-row-late"' : '' ?>>
          <td><?= htmlspecialchars((string) ($we['equipment_name'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars((string) $we['mnt_code'] . ' (' . (string) $we['mnt_state'] . ')', ENT_QUOTES, 'UTF-8') ?></td>
          <td><strong><?= intval($we['waiting_days']) ?></strong></td>
          <td><?= !empty($we['req_code'])
                ? '<a href="../Procurement/requests_proc.php">' . htmlspecialchars((string) $we['req_code'], ENT_QUOTES, 'UTF-8') . '</a>'
                : '<span class="text-muted">بلا طلبٍ بعد — ولّده من شاشة الطلبات</span>' ?></td>
          <td><?= htmlspecialchars((string) ($we['req_state'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars((string) ($we['priority'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
