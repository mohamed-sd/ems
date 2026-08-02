<?php
/**
 * includes/related_tickets_tab.php — «البلاغاتُ المتصلة» (NAV-01 §5-④ · B-03)
 * ───────────────────────────────────────────────────────────────────────────
 * «تاريخُ البلاغات جزءٌ من تاريخ الكيان» — تبويبٌ يُضمَّن في الملفات الأم:
 *   ملفُّ المعدة · أمرُ الصيانة · ملفُّ الموقع · ملفُّ المورد.
 *
 * الاستعمال (داخل صفحةٍ مصادَقةٍ متصلة):
 *   $rt_kind = 'equipment' | 'mnt_order' | 'site' | 'supplier';
 *   $rt_ref  = المعرّف؛
 *   include __DIR__ . '/../includes/related_tickets_tab.php';
 *
 * القراءةُ بنطاق الشركة من $conn القائم — والمكوّنُ عرضٌ صرفٌ بلا كتابة.
 */
if (!isset($rt_kind, $rt_ref) || !isset($conn)) { return; }

$rt_ref = intval($rt_ref);
$rt_company = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;

// العمودُ الرابط بحسب نوع الكيان — والبلاغُ يحمل سياقَه المحمول (TKT-01 §2)
$rt_col_map = array(
    'equipment' => 't.equipment_id',
    'mnt_order' => "t.linked_ref_table = 'mnt_order' AND t.linked_ref_id",
    'site'      => 't.project_id',
    'supplier'  => 't.reporter_entity_id',
);
if (!isset($rt_col_map[$rt_kind])) { return; }
$rt_cond = strpos($rt_col_map[$rt_kind], '=') !== false
    ? '(' . $rt_col_map[$rt_kind] . ' = ' . $rt_ref . ')'
    : $rt_col_map[$rt_kind] . ' = ' . $rt_ref;

$rt_rows = array();
$rt_sql = "SELECT t.id, t.ticket_no, t.stage, t.priority, t.complaint, t.created_at,
                  (SELECT COUNT(*) FROM ticket_workstreams w
                    WHERE w.tk_id = t.id AND w.state NOT IN ('closed','admin_closed')) AS open_ws
           FROM tickets t
           WHERE $rt_cond" . ($rt_company > 0 ? " AND t.company_id = $rt_company" : '') . "
           ORDER BY t.created_at DESC LIMIT 30";
$rt_res = mysqli_query($conn, $rt_sql);
if ($rt_res) { while ($x = mysqli_fetch_assoc($rt_res)) $rt_rows[] = $x; }
?>
<div class="related-tickets-tab" dir="rtl">
  <h5><i class="fa fa-bell"></i> البلاغاتُ المتصلة <span class="badge" style="background:#6c757d"><?= count($rt_rows) ?></span></h5>
  <?php if (empty($rt_rows)): ?>
    <p class="text-muted">لا بلاغاتَ متصلةً بهذا الكيان.</p>
  <?php else: ?>
  <table class="table table-sm" data-no-dt>
    <thead><tr><th>الرقم</th><th>الوصف</th><th>الحالة</th><th>مساراتٌ مفتوحة</th><th>التاريخ</th></tr></thead>
    <tbody>
    <?php foreach ($rt_rows as $t): ?>
      <tr>
        <td><a href="<?= (strpos($_SERVER['SCRIPT_NAME'] ?? '', '/Tickets/') !== false ? '' : '../Tickets/') ?>tickets_list.php?open=<?= intval($t['id']) ?>">
            <?= htmlspecialchars($t['ticket_no'], ENT_QUOTES, 'UTF-8') ?></a></td>
        <td><?= htmlspecialchars(mb_substr($t['complaint'], 0, 60), ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars($t['stage'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= intval($t['open_ws']) ?: '—' ?></td>
        <td><?= htmlspecialchars(substr($t['created_at'], 0, 10), ENT_QUOTES, 'UTF-8') ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
