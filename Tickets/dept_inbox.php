<?php
/**
 * Tickets/dept_tickets.php — «بلاغاتُ إدارتي» (NAV-01 §5-① · update0006 B-01)
 * ───────────────────────────────────────────────────────────────────────────
 * «مركزُ البلاغات يرصد ولا ينفّذ — والتنفيذُ في الإدارات. فإن لم تجد الإدارةُ
 * بلاغاتِها في قائمتها فلن تراها ولن تعالجها.»
 *
 * تعرض مساراتِ (workstreams) إدارةِ المستخدم من ticket_workstreams — لا رؤوسَ
 * البلاغات — لأن المسارَ وحدةُ العمل التي تُسنَد إلى الإدارة بحالتها ومهلتها
 * ومكلَّفها (TKT-01 §3). وعدّادُ المتأخر يقرأ من resolve_due_at.
 *
 * ربطُ الدور بوحدته التنظيمية: خريطةٌ معلنةٌ هنا (الدور ← org_units.unit_id)
 * — مصدرُها مصفوفةُ ORG-01 §1.1 · والدورُ بلا وحدةٍ يرى ما وُجّه لدوره فقط.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/tkt_helpers.php';

$ctx             = tkt_ctx();
$is_super_admin  = $ctx['is_super'];
$company_id      = $ctx['company_id'];
$current_role_id = intval($ctx['role']);

if (!$is_super_admin && $company_id <= 0) {
    header("Location: ../login.php?msg=لا+توجد+بيئة+شركة+صالحة+للمستخدم+❌"); exit();
}

/**
 * الدورُ ← وحدتُه التنظيمية (org_units) — من مصفوفة ORG-01 §1.1.
 * الأدوارُ العلوية (1 التشغيل · 24 البلاغات · 15 الصلاحيات...) بوحداتها،
 * وأدوارُ الموقع كلُّها على «الحركة والتشغيل» (8).
 */
function dept_unit_of_role($roleId)
{
    $map = array(
        1  => 1,   // إدارة التشغيل ← التشغيل
        2  => 2,   // المبيعات ← المبيعات والعقود
        3  => 3,   // المالية ← المالية والخزينة
        4  => 10,  // القوى العاملة ← المشغّلون والقوى
        5  => 8,   // إدارة الموقع ← الحركة والتشغيل (الدمج NAV-10)
        6  => 8,   // الحركة والتشغيل
        7  => 9,   // الصيانة
        8  => 5,   // الأسطول
        10 => 14,  // الموارد البشرية
        11 => 12,  // المخازن ← المخازن
        12 => 2,   // المبيعات الميدانية ← المبيعات والعقود
        13 => 13,  // النقل والترحيل
        14 => 14,  // شؤون الموظفين ← الموارد البشرية
        15 => 6,   // الصلاحيات ← الحوكمة
        16 => 11,  // المشتريات ← المشتريات التشغيلية
        17 => 3, 18 => 3, 19 => 3, 20 => 3, 21 => 3, 22 => 3, // أدوار المالية
        23 => 13,  // النقل
        24 => 7,   // البلاغات ← مركز البلاغات
        25 => 12,  // أمين المستودع ← المخازن
        26 => 4,   // التمويل ← التمويل والملكية
    );
    return isset($map[$roleId]) ? $map[$roleId] : 0;
}

$unit_id = dept_unit_of_role($current_role_id);
$states  = array(
    'new'          => array('جديد', '#0d6efd'),
    'received'     => array('مستلَم', '#6610f2'),
    'in_progress'  => array('قيد المعالجة', '#fd7e14'),
    'on_hold'      => array('معلَّق بسبب', '#6c757d'),
    'done_pending' => array('منجَز — بانتظار التأكيد', '#20c997'),
    'closed'       => array('مغلق', '#198754'),
    'reopened'     => array('أُعيد فتحه', '#dc3545'),
    'admin_closed' => array('مغلق إداريًّا', '#495057'),
);

// المساراتُ المفتوحةُ لوحدة الإدارة — بحالتها ومهلتها ومكلَّفها (فحص المُرجَع: mysqli لا يرمي)
$rows = array(); $late = 0; $open = 0;
$where_unit = $unit_id > 0 ? "ws.org_unit_id = " . intval($unit_id)
                           : "t.owner_role_id = " . intval($current_role_id);
$sql = "SELECT ws.ws_id, ws.workstream_type, ws.state, ws.mandatory, ws.assignee_person_id,
               ws.resolve_due_at, ws.received_at, t.id AS tk_id, t.ticket_no, t.complaint,
               t.priority, t.created_at, u.name AS assignee_name
        FROM ticket_workstreams ws
        JOIN tickets t ON t.id = ws.tk_id" . ($is_super_admin ? '' : ' AND t.company_id = ' . intval($company_id)) . "
        LEFT JOIN users u ON u.id = ws.assignee_person_id
        WHERE $where_unit
        ORDER BY FIELD(ws.state,'reopened','new','received','in_progress','on_hold','done_pending','closed','admin_closed'),
                 ws.resolve_due_at IS NULL, ws.resolve_due_at";
$res = mysqli_query($conn, $sql);
if ($res) {
    while ($r = mysqli_fetch_assoc($res)) {
        $isOpen = !in_array($r['state'], array('closed', 'admin_closed'), true);
        if ($isOpen) {
            $open++;
            if ($r['resolve_due_at'] !== null && strtotime($r['resolve_due_at']) < time()) { $late++; $r['is_late'] = 1; }
        }
        $rows[] = $r;
    }
}

$page_title = 'بلاغاتُ إدارتي';
include '../insidebar.php';
?>
<div class="content-wrapper allforms" dir="rtl">
  <div class="ems-topbar">
    <h4><i class="fa fa-bell"></i> بلاغاتُ إدارتي</h4>
    <div>
      <span class="badge" style="background:#fd7e14;font-size:.95em">المفتوح: <?= $open ?></span>
      <span class="badge" style="background:#dc3545;font-size:.95em">المتأخر: <?= $late ?></span>
    </div>
  </div>

  <?php if ($unit_id === 0): ?>
    <div class="alert alert-warning">دورُك بلا وحدةٍ تنظيميةٍ مربوطة — تُعرض البلاغاتُ الموجَّهةُ لدورك مباشرةً.</div>
  <?php endif; ?>

  <table class="table table-striped" data-no-dt>
    <thead><tr>
      <th>البلاغ</th><th>المسار</th><th>الوصف</th><th>الحالة</th>
      <th>المكلَّف</th><th>مهلةُ الإنجاز</th><th>إلزامي؟</th>
    </tr></thead>
    <tbody>
    <?php if (empty($rows)): ?>
      <tr><td colspan="7" class="text-center text-muted">لا مساراتَ لإدارتك — صفرُ بلاغٍ ينتظر</td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $r):
        $st = isset($states[$r['state']]) ? $states[$r['state']] : array($r['state'], '#999');
        $lateMark = !empty($r['is_late']);
    ?>
      <tr<?= $lateMark ? ' style="background:#fff3f3"' : '' ?>>
        <td><a href="tickets_list.php?open=<?= intval($r['tk_id']) ?>"><?= htmlspecialchars($r['ticket_no'], ENT_QUOTES, 'UTF-8') ?></a></td>
        <td><?= htmlspecialchars($r['workstream_type'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars(mb_substr($r['complaint'], 0, 70), ENT_QUOTES, 'UTF-8') ?></td>
        <td><span class="badge" style="background:<?= $st[1] ?>"><?= $st[0] ?></span><?= $lateMark ? ' <span class="badge" style="background:#dc3545">متأخر</span>' : '' ?></td>
        <td><?= $r['assignee_name'] ? htmlspecialchars($r['assignee_name'], ENT_QUOTES, 'UTF-8') : '<span class="text-muted">بلا مكلَّف</span>' ?></td>
        <td><?= $r['resolve_due_at'] ? htmlspecialchars($r['resolve_due_at'], ENT_QUOTES, 'UTF-8') : '—' ?></td>
        <td><?= $r['mandatory'] ? 'إلزامي' : 'اختياري' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php include '../footer.php'; ?>
