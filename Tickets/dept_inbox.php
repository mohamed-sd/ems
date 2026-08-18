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
    ems_gov_flash_redirect('../login.php', 'لا توجد بيئة شركة صالحة للمستخدم ❌', 'GOV-SCOPE-403', ''); exit();
}

/**
 * الدورُ ← وحدتُه التنظيمية (org_units) — من مصفوفة ORG-01 §1.1.
 * الأدوارُ العلوية (1 التشغيل · 24 البلاغات · 15 الصلاحيات...) بوحداتها،
 * وأدوارُ الموقع كلُّها على «الحركة والتشغيل» (8).
 */
require_once __DIR__ . '/dept_inbox_map.php';
function dept_unit_of_role($roleId) { return ems_dept_unit_of_role($roleId); }

$unit_id = dept_unit_of_role($current_role_id);
/* UXW-01 ①: لونُ الشارةِ صنفٌ من طقمِ النظامِ لا لونٌ مثبَّتٌ في الكود —
   الألوانُ نفسُها معرَّفةٌ رموزًا في كتلةِ أنماطِ الشاشةِ أدناه (tkt-di-badge-*). */
$states  = array(
    'new'          => array('جديد', 'tkt-di-badge-new'),
    'received'     => array('مستلَم', 'tkt-di-badge-received'),
    'in_progress'  => array('قيد المعالجة', 'tkt-di-badge-progress'),
    'on_hold'      => array('معلَّق بسبب', 'tkt-di-badge-hold'),
    'done_pending' => array('منجَز — بانتظار التأكيد', 'tkt-di-badge-done'),
    'closed'       => array('مغلق', 'tkt-di-badge-closed'),
    'reopened'     => array('أُعيد فتحه', 'tkt-di-badge-reopened'),
    'admin_closed' => array('مغلق إداريًّا', 'tkt-di-badge-adminclosed'),
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
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main" dir="rtl">
  <?php
/* AS-04/AS-05 (UXR-01): رأسُ الصفحةِ الموحَّدُ بدلَ الرأسِ اليدويّ —
   شريطُ أفعالٍ واحدٌ وسطرُ سياقٍ ومنفذُ بلاغٍ من مصدرٍ واحد. */
$header_icon = 'fa fa-bell';
$header_title_html = htmlspecialchars('بلاغاتُ إدارتي', ENT_QUOTES, 'UTF-8');
ob_start(); ?><div>
      <span class="badge tkt-di-count-open">المفتوح: <?= $open ?></span>
      <span class="badge tkt-di-count-late">المتأخر: <?= $late ?></span>
    </div><?php
$header_actions = array(array('raw' => trim((string) ob_get_clean())));
$header_back = false;
include __DIR__ . '/../includes/page_header.php';
// UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
echo ems_states_bundle('لا مساراتِ بلاغاتٍ موجَّهةً إلى إدارتك', 'راجع تصنيفَ البلاغاتِ مع مركزِ البلاغات إن كنت تتوقع مساراتٍ هنا');
?>
  <style>
  /* UXW-01 ①②: ألوانُ شاراتِ الحالةِ وأنماطُ «بلاغاتُ إدارتي» — بادئةُ الشاشة tkt-di- */
  .tkt-di-count-open        { background: var(--c-fd7e14, #fd7e14); font-size: .95em; }
  .tkt-di-count-late        { background: var(--c-dc3545); font-size: .95em; }
  .tkt-di-badge-new         { background: var(--c-0d6efd); }
  .tkt-di-badge-received    { background: var(--c-6610f2, #6610f2); }
  .tkt-di-badge-progress    { background: var(--c-fd7e14, #fd7e14); }
  .tkt-di-badge-hold        { background: var(--c-6c757d); }
  .tkt-di-badge-done        { background: var(--c-20c997); }
  .tkt-di-badge-closed      { background: var(--c-198754); }
  .tkt-di-badge-reopened    { background: var(--c-dc3545); }
  .tkt-di-badge-adminclosed { background: var(--c-495057); }
  .tkt-di-badge-unknown     { background: var(--c-ink-400); }
  .tkt-di-badge-late        { background: var(--c-dc3545); }
  .tkt-di-row-late          { background: var(--c-fff3f3, #fff3f3); }
  </style>

  <?php if ($unit_id === 0): ?>
    <div class="alert alert-warning">دورُك بلا وحدةٍ تنظيميةٍ مربوطة — تُعرض البلاغاتُ الموجَّهةُ لدورك مباشرةً.</div>
  <?php endif; ?>

  <table class="table table-striped" data-no-dt>
    <thead><tr>
      <th>رقم البلاغ</th><th>حالة المسار</th><th>الوصف</th><th>الحالة</th>
      <th>المكلَّف</th><th>مهلةُ الإنجاز</th><th>إلزامي؟</th>
      <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
      <th class="ems-fn-th" data-fn="1">تاريخ الفتح</th>
      <th class="ems-fn-th" data-fn="1">الفئة</th>
      <th class="ems-fn-th" data-fn="1">النوع</th>
      <th class="ems-fn-th" data-fn="1">الأولوية</th>
      <th class="ems-fn-th" data-fn="1">الموقع</th>
      <th class="ems-fn-th" data-fn="1">المعدة</th>
      <th class="ems-fn-th" data-fn="1">المبلِّغ</th>
      <th class="ems-fn-th" data-fn="1">مهلة الاستجابة</th>
      <th class="ems-fn-th" data-fn="1">تاريخ الاستلام</th>
      <th class="ems-fn-th" data-fn="1">المتبقي</th>
      <th class="ems-fn-th" data-fn="1">سبب التعليق</th>
      <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
      <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
      </tr></thead>
    <tbody>
    <?php if (empty($rows)): ?>
      <tr><td colspan="7" class="text-center text-muted">لا مساراتَ لإدارتك — صفرُ بلاغٍ ينتظر</td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $r):
        $st = isset($states[$r['state']]) ? $states[$r['state']] : array($r['state'], 'tkt-di-badge-unknown');
        $lateMark = !empty($r['is_late']);
    ?>
      <tr<?= $lateMark ? ' class="tkt-di-row-late"' : '' ?>>
        <td><a href="tickets_list.php?open=<?= intval($r['tk_id']) ?>"><?= htmlspecialchars($r['ticket_no'], ENT_QUOTES, 'UTF-8') ?></a></td>
        <td><?= htmlspecialchars(function_exists('ems_dept_label') ? ems_dept_label($r['workstream_type']) : $r['workstream_type'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?php $cmpl = trim((string) $r['complaint']);
            echo ($cmpl === '' || $cmpl === '0') ? '<span class="text-muted">—</span>'
                : htmlspecialchars(mb_substr($cmpl, 0, 70), ENT_QUOTES, 'UTF-8'); ?></td>
        <td><span class="badge <?= $st[1] ?>"><?= $st[0] ?></span><?= $lateMark ? ' <span class="badge tkt-di-badge-late">متأخر</span>' : '' ?></td>
        <td><?= $r['assignee_name'] ? htmlspecialchars($r['assignee_name'], ENT_QUOTES, 'UTF-8') : '<span class="text-muted">بلا مكلَّف</span>' ?></td>
        <td><?= $r['resolve_due_at'] ? htmlspecialchars($r['resolve_due_at'], ENT_QUOTES, 'UTF-8') : '—' ?></td>
        <td><?= $r['mandatory'] ? 'إلزامي' : 'اختياري' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
