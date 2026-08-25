<?php
/**
 * Tickets/admin_close.php — الإغلاقُ الإداري (★ المركز · update0007-ب F10)
 * «للمكرر أو الملغى فقط · بسببٍ مكتوبٍ ومرجعِ الأصل · ولا يُستعمل لإغلاق
 * ما لم يُنجَز» (TKT-01 §5-⑧) — لمدير البلاغات وحدَه.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';

// ── RF-02 · CS-01 — حارسُ الشاشةِ فوقَ أيِّ معالجٍ يكتب ────────────────────
// كان هذا السطحُ يعتمد على insidebar.php وحدَه في الحجب، وinsidebar يقع
// **بعدَ** معالجِ الكتابة — فيُرحَّل الأثرُ ثم يُعاد التوجيهُ برسالةِ «لا صلاحية».
// الدالةُ نفسُها ولا تغييرَ في مَن يُمنع — التغييرُ في **متى**: قبلَ الكتابة.
if (function_exists('enforce_current_page_view_permission') && isset($conn)) {
    enforce_current_page_view_permission($conn, '../main/dashboard.php');
}
require_once __DIR__ . '/tkt_helpers.php';

$ctx = tkt_ctx();
$company_id = $ctx['company_id'];
$uid = $ctx['user_id'];
/* UI-13: المنعُ يُقال داخلَ النظامِ لا في صفحةٍ عارية — رسالةٌ برمزٍ محكومٍ
   ووجهةٌ فيها طريقُ رجوع (كانت die تنهي الطلبَ بنصٍّ بلا شيء حوله). */
if (intval($ctx['role']) !== 24 && !$ctx['is_super']) {
    ems_gov_flash_redirect('../main/dashboard.php', 'الإغلاق الإداري لمدير البلاغات وحده ❌',
        'GOV-PERM-403', 'اطلب الإغلاق من مدير البلاغات إن لزم');
}
$msg = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['aclose_tk'])) {
    $tid = intval($_POST['aclose_tk']);
    $why = trim($_POST['reason'] ?? '');
    $dup = intval($_POST['duplicate_of'] ?? 0);
    if ($why === '') { $msg = 'السبب المكتوب إلزامي (422)'; }
    else {
        // لا يُغلق منجَزٌ إداريًّا — المكررُ والملغى فقط
        /* ══ INJ-0264 · سياسةُ الإقفالِ تحكم الإغلاقَ الإداري ═══════════════════════
             نصُّ القبول: «**محاولةُ إغلاقٍ إداريٍّ لبلاغِ شكوى أو سلامةٍ تُرفض ٤٠٣
             برسالةٍ تسمّي السياسة**؛ والإغلاقُ الإداريُّ الخاطئ **يُعكَس بزرٍّ
             ينتج حركةً مرتبطةً بالأصل**».
             والمقيسُ قبلَه: الحارسُ الوحيدُ «لا يُغلق منجَزٌ» — أمّا شكوى العاملِ
             أو بلاغُ السلامةِ فيُغلقان إداريًّا بضغطةٍ، وهما أحوجُ ما يكون إلى
             تأكيدِ المبلِّغِ أو قرارِ لجنة.
           ◆ والسياسةُ **مقروءةٌ من نوعِ البلاغِ** (`ticket_types.closure_policy`)
             لا مكتوبةٌ هنا: `reporter_confirm` و`committee` لا يُغلقان إداريًّا. */
        $r = mysqli_query($conn, "SELECT t.stage, t.ticket_type_id,
                                         COALESCE(ty.closure_policy, 'admin_only') closure_policy,
                                         COALESCE(ty.name, '') type_name
                                    FROM tickets t
                                    LEFT JOIN ticket_types ty ON ty.id = t.ticket_type_id
                                   WHERE t.id = $tid AND t.company_id = $company_id");
        if (!$r || !($t = mysqli_fetch_assoc($r))) { $msg = 'بلاغ غير موجود (404)'; }
        elseif (in_array((string) $t['closure_policy'], array('reporter_confirm', 'committee'), true)) {
            $__pol = array('reporter_confirm' => 'تأكيد المبلغ', 'committee' => 'قرار لجنة');
            $msg = 'TKT-403-CLOSEPOL: سياسة إقفال هذا النوع «'
                 . $__pol[(string) $t['closure_policy']] . '» — فلا يغلق إداريا (403)';
            if (function_exists('ems_log_denial')) {
                @ems_log_denial('TKT-403-CLOSEPOL', 'ticket:' . $tid,
                    'محاولة إغلاق إداري لنوع سياسته ' . (string) $t['closure_policy']);
            }
        }
        elseif (in_array($t['stage'], array('done', 'closed'), true)) { $msg = 'منجز — يغلق بمساره لا إداريا (403)'; }
        else {
            mysqli_begin_transaction($conn);
            $ok1 = mysqli_query($conn, "UPDATE tickets SET stage = 'cancelled'" .
                   ($dup > 0 ? ", duplicate_of_ticket_id = $dup" : '') . " WHERE id = $tid");
            $ok2 = mysqli_query($conn, "UPDATE ticket_workstreams SET state = 'admin_closed', closed_at = NOW()
                                        WHERE tk_id = $tid AND state NOT IN ('closed','admin_closed')");
            $ok3 = mysqli_query($conn, "INSERT INTO ticket_events (company_id, ticket_id, event_type, body, actor_user_id)
                    VALUES ($company_id, $tid, 'admin_closed', '" . mysqli_real_escape_string($conn, $why .
                    ($dup ? " — مكرر من #$dup" : '')) . "', $uid)");
            if ($dup > 0) { // مبلِّغُ المكرر يُضاف متابعًا للأصل — فلا يُفقد أنه أبلغ (TKT §8)
                mysqli_query($conn, "INSERT IGNORE INTO ticket_watchers (company_id, ticket_id, user_id, role_id)
                    SELECT company_id, $dup, reporter_user_id, NULL FROM tickets WHERE id = $tid AND reporter_user_id IS NOT NULL");
            }
            if ($ok1 && $ok2 && $ok3) {
                mysqli_commit($conn);
                /* ── INJ-0274 · الإغلاقُ الإداريُّ أثقلُ أفعالِ المركزِ فلا يمرُّ بلا أثر ──
                     `ticket_events` سجلُّ **دورةِ البلاغ** يراه أطرافُه، وهذا سجلُّ
                     **التدقيقِ الحوكميّ** يراه المراجع — ولكلٍّ قارئُه. فالقيمةُ
                     «قبل» تحمل مرحلةَ البلاغِ الفعليةَ المقروءةَ من الصفِّ لا
                     مفترَضةً، والفاعلُ بصفتِه. */
                require_once __DIR__ . '/../includes/audit_trail.php';
                ems_audit_change($conn, 'tickets', 'tickets', 'admin_close', (int) $tid,
                    array('stage' => (string) $t['stage']),
                    array('stage' => 'cancelled', 'reason' => mb_substr($why, 0, 200),
                          'duplicate_of' => $dup > 0 ? (int) $dup : null),
                    array('company_id' => (int) $company_id, 'user_id' => (int) $uid));
                $msg = "أغلق #$tid إداريا بسببه" . ($dup ? " ومبلغه متابع للأصل #$dup" : '');
            }
            else { mysqli_rollback($conn); $msg = 'فشلت: ' . mysqli_error($conn); }
        }
    }
}

/* ══ INJ-0264 ② · عكسُ الإغلاقِ الإداريِّ الخاطئ ═══════════════════════════════
     «**والإغلاقُ الإداريُّ الخاطئ يُعكَس بزرٍّ ينتج حركةً مرتبطةً بالأصل**».
     والعكسُ **حركةٌ جديدةٌ بمرجعِها لا محوٌ للأولى** (CS-08): البلاغُ يعود إلى
     مرحلتِه السابقةِ المقروءةِ **من سجلِّ التدقيق** لا من ظنّ، وتُكتب واقعةُ
     `admin_close_reversed` في سجلِّ البلاغِ تشير إلى الإغلاقِ الذي نُقض.
   ◆ والسببُ إلزاميٌّ — فنقضٌ صامتٌ يُقرأ عبثًا. */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['areverse_tk'])) {
    $rid = intval($_POST['areverse_tk']);
    $rwhy = trim($_POST['reverse_reason'] ?? '');
    if ($rwhy === '') { $msg = 'سبب العكس إلزامي (422)'; }
    else {
        $rr = mysqli_query($conn, "SELECT stage FROM tickets WHERE id = $rid AND company_id = $company_id");
        $tk = $rr ? mysqli_fetch_assoc($rr) : null;
        if (!$tk) { $msg = 'بلاغ غير موجود (404)'; }
        elseif ((string) $tk['stage'] !== 'cancelled') {
            $msg = 'TKT-422-NOTCLOSED: البلاغ ليس مغلقا إداريا — لا عكس له (422)';
        } else {
            /* المرحلةُ السابقةُ من سجلِّ التدقيقِ — لا تُخمَّن */
            $prev = '';
            $aq = $conn->prepare("SELECT old_value FROM activity_logs
                                   WHERE screen_name = 'tickets' AND record_id = ?
                                     AND action_type = 'admin_close'
                                   ORDER BY id DESC LIMIT 1");
            if ($aq) {
                $aq->bind_param('i', $rid);
                if ($aq->execute()) {
                    $ax = $aq->get_result()->fetch_row();
                    if ($ax) {
                        $j = json_decode((string) $ax[0], true);
                        if (is_array($j) && !empty($j['stage'])) { $prev = (string) $j['stage']; }
                    }
                }
                $aq->close();
            }
            if ($prev === '') {
                $msg = 'TKT-409-NOTRACE: لا أثر تدقيق يحمل المرحلة السابقة — فلا يخمن إلى أين يرد (409)';
            } else {
                mysqli_begin_transaction($conn);
                $u1 = mysqli_query($conn, "UPDATE tickets SET stage = '"
                        . mysqli_real_escape_string($conn, $prev) . "' WHERE id = $rid AND stage = 'cancelled'");
                $u2 = mysqli_query($conn, "INSERT INTO ticket_events (company_id, ticket_id, event_type, body, actor_user_id)
                        VALUES ($company_id, $rid, 'admin_closed', '"
                        . mysqli_real_escape_string($conn, 'نقض الإغلاق الإداري — أعيد إلى «' . $prev . '»: ' . $rwhy)
                        . "', $uid)");
                if ($u1 && $u2) {
                    mysqli_commit($conn);
                    require_once __DIR__ . '/../includes/audit_trail.php';
                    ems_audit_change($conn, 'tickets', 'tickets', 'admin_close_reversed', (int) $rid,
                        array('stage' => 'cancelled'),
                        array('stage' => $prev, 'reason' => mb_substr($rwhy, 0, 200)),
                        array('company_id' => (int) $company_id, 'user_id' => (int) $uid));
                    $msg = "نقض الإغلاق الإداري ل#$rid — عاد إلى «$prev» بحركة مرتبطة بالأصل";
                } else {
                    mysqli_rollback($conn);
                    $msg = 'تعذر العكس: ' . mysqli_error($conn);
                }
            }
        }
    }
}

/* البلاغاتُ المُغلقةُ إداريًّا — لتُعرض مع زرِّ النقض */
$closed_rows = array();
$rc = mysqli_query($conn, "SELECT id, ticket_no, complaint, created_at FROM tickets
                            WHERE company_id = $company_id AND stage = 'cancelled'
                            ORDER BY created_at DESC LIMIT 30");
if ($rc) { while ($x = mysqli_fetch_assoc($rc)) { $closed_rows[] = $x; } }

$rows = array();
$r = mysqli_query($conn, "SELECT id, ticket_no, complaint, stage, created_at FROM tickets
                          WHERE company_id = $company_id AND stage NOT IN ('done','closed','cancelled')
                          ORDER BY created_at DESC LIMIT 60");
if ($r) while ($x = mysqli_fetch_assoc($r)) $rows[] = $x;

$page_title = 'الإغلاق الإداري';
// CM-00 (DEC-E · U10): بذرُ محاورِ الغلافِ من الخادم — AX-2/3 من محرك الصلاحيات
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : (isset($permissions) ? $permissions : null));
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<style>
/* UXW-01 ①②: أنماطُ هذه الشاشةِ الثابتةُ أصنافًا ببادئةِ الشاشة — واللونُ برمزٍ باحتياطٍ حرفيّ */
.tkt-ac-dup     { max-width: 110px; }
.tkt-ac-danger  { color: var(--c-dc3545, #dc3545); }
.tkt-ac-subhead { margin-top: 18px; }
</style>
<div class="main" dir="rtl">
  <?php
/* AS-04/AS-05 (UXR-01): رأسُ الصفحةِ الموحَّدُ بدلَ الرأسِ اليدويّ —
   شريطُ أفعالٍ واحدٌ وسطرُ سياقٍ ومنفذُ بلاغٍ من مصدرٍ واحد. */
$header_icon = 'fa fa-ban';
$header_title_html = htmlspecialchars('الإغلاق الإداري — للمكرر والملغى فقط', ENT_QUOTES, 'UTF-8');
$header_actions = array();
$header_back = false;
include __DIR__ . '/../includes/page_header.php';
// UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
echo ems_states_bundle('لا بلاغ مفتوحا يقبل الإغلاق الإداري الآن',
                       'الإغلاق الإداري للمكرر والملغى وحدهما — والمنجز يغلق بمساره من صندوق الإدارة');
?>
  <?php if ($msg): ?><div class="alert alert-info"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
  <table class="table table-striped" data-no-dt>
    <thead><tr><th>رقم البلاغ</th><th>وصف الحل</th><th>الحالة</th><th>الإغلاق بسبب ومرجع</th>
              <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
              <th class="ems-fn-th" data-fn="1">تاريخ الإنجاز</th>
              <th class="ems-fn-th" data-fn="1">المنجز</th>
              <th class="ems-fn-th" data-fn="1">الأثر التشغيلي المسجل</th>
              <th class="ems-fn-th" data-fn="1">المستند الناتج</th>
              <th class="ems-fn-th" data-fn="1">سياسة الإغلاق</th>
              <th class="ems-fn-th" data-fn="1">المؤكد</th>
              <th class="ems-fn-th" data-fn="1">تاريخ التأكيد</th>
              <th class="ems-fn-th" data-fn="1">عدد مرات إعادة الفتح</th>
              <th class="ems-fn-th" data-fn="1">سبب آخر إعادة فتح</th>
              <th class="ems-fn-th" data-fn="1">نوع الإغلاق</th>
              <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
              <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صف بلا كيان مالك">الكيان</th>
              <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمد — تفويض أو سلطة أصلية">مرجع التفويض</th>
              <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
              <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
              <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
              <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المنشئ — الاسم والصفة</th>
              <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمد — الاسم والصفة</th>
              <th class="ems-gov-th" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
              </tr></thead>
    <tbody>
    <?php foreach ($rows as $t): ?>
      <tr>
        <td><?= htmlspecialchars($t['ticket_no'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars(mb_substr($t['complaint'], 0, 50), ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars($t['stage'], ENT_QUOTES, 'UTF-8') ?></td>
        <td>
          <form method="post" class="ems-inline-flex-form">
        <?php echo csrf_field(); ?>
            <input type="hidden" name="aclose_tk" value="<?= intval($t['id']) ?>">
            <input type="text" name="reason" class="form-control form-control-sm ems-reason-inline" placeholder="السبب المكتوب" required aria-label="السبب المكتوب">
            <input type="number" name="duplicate_of" class="form-control form-control-sm tkt-ac-dup" placeholder="مكرر من #" aria-label="مكرر من #">
            <button class="action-btn tkt-ac-danger" type="submit">أغلق إداريا</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <?php
  /* ══ INJ-0264 ② · جدولُ المُغلَقِ إداريًّا بزرِّ نقضِه ═══════════════════════════
       «والإغلاقُ الإداريُّ الخاطئ **يُعكَس بزرٍّ** ينتج حركةً مرتبطةً بالأصل».
       فالبابُ ظاهرٌ لا مخبوءٌ في نقطةِ ردٍّ — ومن أغلق خطأً يجد سبيلَه. */
  if (!empty($closed_rows)): ?>
  <h5 class="tkt-ac-subhead">المغلق إداريا — ولكل باب نقض بحركة مرتبطة</h5>
  <table class="table table-striped" data-no-dt>
    <thead><tr><th>رقم البلاغ</th><th>الموضوع</th><th>نقض الإغلاق</th></tr></thead>
    <tbody>
    <?php foreach ($closed_rows as $ct): ?>
      <tr>
        <td><?= htmlspecialchars((string) $ct['ticket_no'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars(mb_substr((string) $ct['complaint'], 0, 50), ENT_QUOTES, 'UTF-8') ?></td>
        <td>
          <form method="post" class="ems-inline-flex-form">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="areverse_tk" value="<?= intval($ct['id']) ?>">
            <input type="text" name="reverse_reason" class="form-control form-control-sm ems-reason-inline"
                   placeholder="سبب النقض" required aria-label="سبب النقض">
            <button class="action-btn" type="submit"
                    title="نقض الإغلاق — يعيد البلاغ إلى مرحلته المقروءة من سجل التدقيق، بحركة جديدة لا بمحو الأولى">
              انقض الإغلاق
            </button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
