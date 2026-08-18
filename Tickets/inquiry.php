<?php
/**
 * Tickets/inquiry.php — الاستفسارُ عن بلاغٍ متعثر (★ المركز · update0007-ب F11)
 * ───────────────────────────────────────────────────────────────────────────
 * «غايةُ المركز: جهةٌ واحدةٌ تُسأل بدل عشر إدارات» (TKT-01 §9-④) —
 * بحثٌ برقم البلاغ → بطاقةٌ تجيب: أين وقف · من يملكه · منذ متى · ما المانع.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/tkt_helpers.php';

$ctx = tkt_ctx();
$company_id = $ctx['company_id'];
$q = trim($_GET['q'] ?? '');
$ticket = null; $streams = array();

if ($q !== '') {
    $st = mysqli_prepare($conn, "SELECT t.*, u.name reporter FROM tickets t
                                 LEFT JOIN users u ON u.id = t.reporter_user_id
                                 WHERE t.company_id = ? AND (t.ticket_no = ? OR t.id = ?) LIMIT 1");
    $qid = intval($q);
    mysqli_stmt_bind_param($st, 'isi', $company_id, $q, $qid);
    mysqli_stmt_execute($st);
    $res = mysqli_stmt_get_result($st);
    $ticket = $res ? mysqli_fetch_assoc($res) : null;
    if ($ticket) {
        $r = mysqli_query($conn, "SELECT ws.*, ou.name_ar unit_name, u2.name assignee
                                  FROM ticket_workstreams ws
                                  LEFT JOIN org_units ou ON ou.unit_id = ws.org_unit_id
                                  LEFT JOIN users u2 ON u2.id = ws.assignee_person_id
                                  WHERE ws.tk_id = " . intval($ticket['id']) . " ORDER BY ws.seq_no");
        if ($r) while ($x = mysqli_fetch_assoc($r)) $streams[] = $x;
    }
}

$page_title = 'الاستفسار عن بلاغ';
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
$header_icon = 'fa fa-search';
$header_title_html = htmlspecialchars('الاستفسارُ عن بلاغٍ متعثر — جهةٌ واحدةٌ تُسأل', ENT_QUOTES, 'UTF-8');
$header_actions = array();
$header_back = false;
include __DIR__ . '/../includes/page_header.php';
// UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
echo ems_states_bundle('لا بلاغَ يطابق رقمَ البحث', 'اكتب رقمَ البلاغِ كاملًا (TK-…) أو رقمَه المتسلسلَ في حقلِ البحثِ أعلاه');
?>
  <style>
  /* UXW-01 ②: أنماطُ شاشةِ الاستفسارِ الثابتةُ — بادئةُ الشاشة tkt-inq- */
  .tkt-inq-search { display: flex; gap: 8px; max-width: 440px; margin-bottom: 16px; }
  .tkt-inq-card   { border: 1px solid var(--c-dee2e6); border-radius: 8px; padding: 14px; max-width: 820px; background: var(--c-f8f9fa); }
  .tkt-inq-stage  { background: var(--c-0d6efd); }
  .tkt-inq-late   { background: var(--c-fff3f3, #fff3f3); }
  </style>
  <form method="get" class="ems-form tkt-inq-search">
    <input type="text" name="q" class="form-control" placeholder="رقمُ البلاغ (TK-… أو #)" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>" required aria-label="رقمُ البلاغ (TK-… أو #)">
    <button class="btn btn-primary">أين وقف؟</button>
  </form>

  <?php if ($q !== '' && !$ticket): ?>
    <div class="alert alert-warning">لا بلاغَ بهذا الرقم</div>
  <?php elseif ($ticket): ?>
    <div class="tkt-inq-card">
      <h5><?= htmlspecialchars($ticket['ticket_no'], ENT_QUOTES, 'UTF-8') ?>
          <span class="badge tkt-inq-stage"><?= htmlspecialchars($ticket['stage'], ENT_QUOTES, 'UTF-8') ?></span></h5>
      <p><?= htmlspecialchars($ticket['complaint'], ENT_QUOTES, 'UTF-8') ?></p>
      <p class="text-muted">المبلِّغ: <?= htmlspecialchars($ticket['reporter'] !== null ? $ticket['reporter'] : $ticket['reporting_person'], ENT_QUOTES, 'UTF-8') ?>
         · منذ <?= htmlspecialchars(substr($ticket['created_at'], 0, 16), ENT_QUOTES, 'UTF-8') ?></p>
      <h6>أين وقف — مساراتُه:</h6>
      <table class="table table-sm" data-no-dt>
        <thead><tr><th>المسار</th><th>الإدارة</th><th>المكلَّف</th><th>الحالة</th><th>مهلتُه</th><th>المانع</th>
              <!-- E-03 موجة ٤: النواة الحاكمة (gov_columns) — الخلايا يحشوها ui-unification.js -->
              <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
              <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
              <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
              <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
              <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
              <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
              <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
              </tr></thead>
        <tbody>
        <?php if (empty($streams)): ?><tr><td colspan="6" class="text-muted">بلاغٌ موروثٌ بلا مساراتٍ مفصَّلة — حالتُه أعلاه</td></tr><?php endif; ?>
        <?php foreach ($streams as $s):
            $open = !in_array($s['state'], array('closed', 'admin_closed'), true);
            $late = $open && $s['resolve_due_at'] !== null && strtotime($s['resolve_due_at']) < time(); ?>
          <tr<?= $late ? ' class="tkt-inq-late"' : '' ?>>
            <td><?= htmlspecialchars($s['workstream_type'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($s['unit_name'] !== null ? $s['unit_name'] : '—', ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($s['assignee'] !== null ? $s['assignee'] : 'بلا مكلَّف', ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($s['state'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($s['resolve_due_at'] !== null ? $s['resolve_due_at'] : '—', ENT_QUOTES, 'UTF-8') ?><?= $late ? ' ⚠' : '' ?></td>
            <td><?= $s['state'] === 'on_hold' ? 'معلَّقٌ بسبب' : ($late ? 'متجاوزُ المهلة' : '—') ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
