<?php
/**
 * Tickets/intake_classify.php — الاستقبالُ والتصنيف (★ المركز · update0007-ب F9)
 * «يصحّح تصنيفًا خاطئًا وُجّه به البلاغُ إلى غير أهله» (TKT-01 §9-⑤) —
 * الجديدةُ وغيرُ المصنَّفة تُصنَّف فيُعاد توجيهُها آليًّا.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/tkt_helpers.php';

$ctx = tkt_ctx();
$company_id = $ctx['company_id'];
$uid = $ctx['user_id'];
/* UI-13: المنعُ يُقال داخلَ النظامِ برمزٍ محكومٍ ووجهةٍ فيها طريقُ رجوع. */
if (intval($ctx['role']) !== 24 && !$ctx['is_super']) {
    ems_gov_flash_redirect('../main/dashboard.php', 'التصنيفُ من عمل مركز البلاغات ❌',
        'GOV-PERM-403', 'أرسل البلاغَ وسيصنّفه المركز');
}
$msg = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['classify_tk'])) {
    $tid = intval($_POST['classify_tk']);
    $cat = intval($_POST['category_id'] ?? 0);
    $typ = intval($_POST['type_id'] ?? 0);
    if ($cat <= 0 || $typ <= 0) { $msg = 'الفئةُ والنوعُ إلزاميان (422)'; }
    else {
        // التصنيف يوجّه فعلًا: النوعُ يحدّد الإدارةَ المالكة، والمرحلةُ تصير
        // «محالة» — كانت تقف عند «مصنّفة» ولا مخرجَ لها، فيعلق البلاغُ حيًّا
        // بلا سبيلٍ إلى الإنجاز رغم أن الشاشة تَعِد بالتوجيه الآلي.
        $owner = 0;
        $ot = mysqli_prepare($conn, "SELECT owner_role_id FROM ticket_types WHERE id = ? AND active = 1 LIMIT 1");
        mysqli_stmt_bind_param($ot, 'i', $typ);
        mysqli_stmt_execute($ot);
        $orow = mysqli_stmt_get_result($ot)->fetch_assoc();
        mysqli_stmt_close($ot);
        $owner = $orow ? intval($orow['owner_role_id']) : 0;

        $st = mysqli_prepare($conn, "UPDATE tickets
                                        SET category_id = ?, ticket_type_id = ?, stage = 'routed',
                                            owner_role_id = COALESCE(NULLIF(?, 0), owner_role_id)
                                      WHERE id = ? AND company_id = ? AND stage IN ('new','classified')");
        mysqli_stmt_bind_param($st, 'iiiii', $cat, $typ, $owner, $tid, $company_id);
        mysqli_stmt_execute($st);
        if (mysqli_stmt_affected_rows($st) > 0) {
            mysqli_query($conn, "INSERT INTO ticket_events (company_id, ticket_id, event_type, body, actor_user_id, new_value)
                                 VALUES ($company_id, $tid, 'reclassified', 'صُنّف من شاشة الاستقبال ووُجّه للإدارة المختصة', $uid, 'routed')");
            // المهلة تُحسب عند التوجيه إن لم تكن حُسبت عند الإنشاء
            $trow = mysqli_query($conn, "SELECT priority, business_impact, call_date, call_time, resolution_due_at
                                           FROM tickets WHERE id = $tid AND company_id = $company_id");
            $tk = $trow ? mysqli_fetch_assoc($trow) : null;
            if ($tk && empty($tk['resolution_due_at'])) {
                tkt_apply_sla(tkt_gate(false), $tid, $typ, $tk['priority'], $tk['business_impact'],
                              $tk['call_date'], $tk['call_time']);
            }
            $msg = "صُنّف البلاغُ #$tid ووُجّه للإدارة المختصة ✅";
        } else { $msg = 'تجاوز مرحلةَ التصنيف (409)'; }
    }
}

$rows = array();
$r = mysqli_query($conn, "SELECT t.id, t.ticket_no, t.complaint, t.created_at, t.stage,
                                 c.name cat_name, tt.name type_name
                          FROM tickets t
                          LEFT JOIN ticket_categories c ON c.id = t.category_id
                          LEFT JOIN ticket_types tt ON tt.id = t.ticket_type_id
                          WHERE t.company_id = $company_id AND (t.stage = 'new' OR t.category_id IS NULL)
                          ORDER BY t.created_at LIMIT 60");
if ($r) while ($x = mysqli_fetch_assoc($r)) $rows[] = $x;
$cats = array(); $types = array();
$r = mysqli_query($conn, "SELECT id, name FROM ticket_categories ORDER BY name");
if ($r) while ($x = mysqli_fetch_assoc($r)) $cats[] = $x;
$r = mysqli_query($conn, "SELECT id, name FROM ticket_types WHERE active = 1 ORDER BY name");
if ($r) while ($x = mysqli_fetch_assoc($r)) $types[] = $x;

$page_title = 'الاستقبال والتصنيف';
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
$header_icon = 'fa fa-inbox';
$header_title_html = htmlspecialchars('الاستقبالُ والتصنيف — الجديدةُ وغيرُ المصنَّفة', ENT_QUOTES, 'UTF-8');
$header_actions = array();
$header_back = false;
include __DIR__ . '/../includes/page_header.php';
?>
  <?php if ($msg): ?><div class="alert alert-info"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
  <table class="table table-striped" data-no-dt>
    <thead><tr><th>البلاغ</th><th>الوصف</th><th>منذ</th><th>تصنيفُه الحالي</th><th>التصنيف</th>
              <!-- E-03 موجة ٤: النواة الحاكمة (gov_columns) — الخلايا يحشوها ui-unification.js -->
              <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
              <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
              <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
              <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
              <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
              <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
              <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
              <th class="ems-gov-th" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
              </tr></thead>
    <tbody>
    <?php if (empty($rows)): ?><tr><td colspan="5" class="text-center" style="color:#198754">✔ لا جديدَ بلا تصنيف</td></tr><?php endif; ?>
    <?php foreach ($rows as $t): ?>
      <tr>
        <td><a href="tickets_list.php?open=<?= intval($t['id']) ?>"><?= htmlspecialchars($t['ticket_no'], ENT_QUOTES, 'UTF-8') ?></a></td>
        <td><?= htmlspecialchars(mb_substr($t['complaint'], 0, 55), ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars(substr($t['created_at'], 0, 16), ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars(($t['cat_name'] !== null ? $t['cat_name'] : '؟') . ' / ' . ($t['type_name'] !== null ? $t['type_name'] : '؟'), ENT_QUOTES, 'UTF-8') ?></td>
        <td>
          <form method="post" style="display:flex;gap:6px">
            <input type="hidden" name="classify_tk" value="<?= intval($t['id']) ?>">
            <select name="category_id" class="form-control form-control-sm" required style="max-width:130px">
              <option value="">— الفئة —</option>
              <?php foreach ($cats as $c2): ?><option value="<?= intval($c2['id']) ?>"><?= htmlspecialchars($c2['name'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?>
            </select>
            <select name="type_id" class="form-control form-control-sm" required style="max-width:130px">
              <option value="">— النوع —</option>
              <?php foreach ($types as $t2): ?><option value="<?= intval($t2['id']) ?>"><?= htmlspecialchars($t2['name'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?>
            </select>
            <button class="action-btn" type="submit">صنّف</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
