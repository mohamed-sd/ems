<?php
/**
 * Tickets/ticket_close.php — طابورُ الإغلاق: ما أُنجز وينتظر الإقفال
 * ═══════════════════════════════════════════════════════════════════════════
 * ⇐ INJ-0267
 *
 * الوثيقةُ (TKT-01) تعلن لمركزِ البلاغاتِ ثلاثَ عشرةَ شاشةً، ومنها شاشةُ
 * الإغلاق — ولم يكن لها ملفٌّ حيّ.
 *
 * ── ولماذا هذه الشاشةُ **عارضٌ لا فعلٌ ثانٍ** ────────────────────────────────
 * الإقفالُ مبنيٌّ سلفًا في `ticket_form.php` بمنطقٍ ثقيلٍ يجب ألّا يُنسَخ:
 *   ◆ حارسُ «من رفع البلاغَ لا يُقفله» (`ems_no_self_approval`) — والإقفالُ
 *     شهادةُ معالجةٍ من الجهةِ المعالِجةِ لا من المُبلِّغ.
 *   ◆ ومنعُ الإقفالِ ومسارٌ إلزاميٌّ مفتوح (423) — بحارسٍ وبدفاعٍ في العمق.
 *   ◆ وختمُ `close_date` و`close_time` و`closed_by`.
 * فلو كتبت هذه الشاشةُ الإقفالَ بيدِها لصار في النظامِ **مسارا إقفالٍ**
 * يتفرّقان عند أوّلِ تعديلٍ في القاعدة — وهو عينُ الداءِ الذي تعالجه الحزمة.
 * فهي تسرد وتشخّص وتقود إلى الفعلِ الواحد.
 *
 * ── وتُميّز «جاهزٌ» من «محجوب» ───────────────────────────────────────────────
 * طابورٌ يعرض كلَّ ما أُنجز على أنَّه جاهزٌ للإقفالِ يُرسل المستخدمَ إلى منعٍ
 * لا يفهمه. فالسببُ يُحسب هنا ويُعرض قبل الضغط: مسارٌ إلزاميٌّ مفتوح، أو أنَّ
 * الناظرَ هو المُبلِّغُ نفسُه.
 *
 * ◆ `admin_close.php` شيءٌ آخرُ تمامًا: إغلاقٌ إداريٌّ **للمكرَّرِ والملغى**
 *   وحدَهما ولمديرِ البلاغاتِ حصرًا، ويقول نصًّا «المنجَزُ يُغلق بمسارِه لا
 *   إداريًّا». وهذه الشاشةُ ذلك المسار.
 * ═══════════════════════════════════════════════════════════════════════════
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';

if (function_exists('enforce_current_page_view_permission') && isset($conn)) {
    enforce_current_page_view_permission($conn, '../main/dashboard.php');
}
require_once __DIR__ . '/tkt_helpers.php';
require_once __DIR__ . '/../includes/screen_contract.php';

$ctx        = tkt_ctx();
$company_id = intval($ctx['company_id']);
$uid        = intval($ctx['user_id']);
$__pp       = check_page_permissions($conn, 'Tickets/ticket_close.php');
ems_shell_axes($__pp);

/* ── الطابور: كلُّ ما مرحلتُه «منجَز» ───────────────────────────────────────
   والاستعلامُ الفاشلُ يُميَّز عن «لا صفوف» — `config.php` يضبط mysqli على عدمِ
   الرمي، فعمودٌ ناقصٌ يعود `false` صامتًا ويُقرأ «الطابورُ خالٍ». */
$rows = array(); $failed = false;
$sql = "SELECT t.id, t.ticket_no, t.title, t.stage, t.created_by, t.created_at,
               t.owner_role_id, t.resolution_due_at,
               u.name AS reporter_name, r.name AS owner_role_name
          FROM tickets t
          LEFT JOIN users u ON u.id = t.created_by
          LEFT JOIN roles r ON r.id = t.owner_role_id
         WHERE t.company_id = ? AND t.stage = 'done'
         ORDER BY t.id DESC
         LIMIT 300";
$st = $conn->prepare($sql);
if (!$st) { $failed = true; }
else {
    $st->bind_param('i', $company_id);
    if (!$st->execute()) { $failed = true; }
    else {
        $res = $st->get_result();
        while ($res && ($x = $res->fetch_assoc())) { $rows[] = $x; }
    }
    $st->close();
}

/* ── التشخيصُ لكلِّ صفٍّ: ما الذي يمنع إقفالَه الآن؟ ───────────────────────── */
$gate = tkt_gate(false);
foreach ($rows as $i => $x) {
    $blockers = array();
    $openWs = tkt_open_mandatory_ws_count($gate, (int) $x['id']);
    if ($openWs > 0) { $blockers[] = $openWs . ' مسارًا إلزاميًّا ما زال مفتوحًا'; }
    if ((int) $x['created_by'] === $uid && $uid > 0) {
        $blockers[] = 'أنت المُبلِّغُ — والإقفالُ من الجهةِ المعالِجة';
    }
    $rows[$i]['blockers'] = $blockers;
    $rows[$i]['open_ws']  = $openWs;
}
$ready   = 0;
foreach ($rows as $x) { if (!$x['blockers']) { $ready++; } }
$blocked = count($rows) - $ready;

$page_title = 'إغلاق البلاغات المنجَزة';
include '../inheader.php';
include '../insidebar.php';
if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-doc-cycle" dir="rtl">
<?php
$header_icon = 'fa fa-lock';
$header_title_html = htmlspecialchars('طابورُ الإغلاق — ما أُنجز وينتظر الإقفال', ENT_QUOTES, 'UTF-8');
$header_actions = array();
$header_back = false;
include __DIR__ . '/../includes/page_header.php';
echo ems_next_step('تأكيدُ الحلِّ ثم الإقفالُ — يقعان في شاشةِ البلاغِ نفسِها بزرِّ «فتحُ البلاغ للإقفال»');
echo ems_states_bundle('لا بلاغَ منجَزًا ينتظر الإقفال', 'الطابورُ يمتلئ حين تبلغ البلاغاتُ مرحلةَ «منجَز» فعلًا');
?>
<?php require_once __DIR__ . '/../includes/entity_tabs.php'; echo ems_entity_tabs('ticket', 'الإغلاق'); ?>
  <?php /* نُقلت أنماطُ هذه الشاشةِ إلى assets/css/ems-screens.css (UXUI-01 البند ٦: صفرُ نمطٍ محليّ) */ ?>

  <?php if ($failed): ?>
  <div class="alert alert-danger tkc-alert-gap">
    <strong>تعذّرت قراءةُ الطابور.</strong>
    فرقٌ بين «لا بلاغَ ينتظر» و«تعذّر السؤال» — وهذه الثانية.
  </div>
  <?php else: ?>

  <div class="tkc-stat-row">
    <div class="ems-card tkc-stat-card tkc-stat-ready">
      <div class="tkc-stat-label">جاهزٌ للإقفال</div>
      <div class="tkc-stat-value"><?php echo number_format($ready); ?></div>
    </div>
    <div class="ems-card tkc-stat-card tkc-stat-blocked">
      <div class="tkc-stat-label">منجَزٌ ومحجوب</div>
      <div class="tkc-stat-value"><?php echo number_format($blocked); ?></div>
    </div>
  </div>

  <div class="card"><div class="card-body table-responsive">
    <table class="table table-sm table-striped tkc-w100">
      <thead><tr>
        <th>#</th><th>رقم البلاغ</th><th>العنوان</th><th>المُبلِّغ</th>
        <th>الإدارة المالكة</th><th>الحال</th><th>الإجراء</th>
      </tr></thead>
      <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="7" class="tkc-empty-cell">
          لا بلاغَ منجَزًا ينتظر الإقفال — والطابورُ يمتلئ حين يُنجَز عملُ بلاغٍ فعلًا.
        </td></tr>
      <?php else: foreach ($rows as $x): ?>
        <tr>
          <td><?php echo (int) $x['id']; ?></td>
          <td><code><?php echo htmlspecialchars((string) $x['ticket_no'], ENT_QUOTES, 'UTF-8'); ?></code></td>
          <td><?php echo htmlspecialchars(mb_substr((string) $x['title'], 0, 70), ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?php echo htmlspecialchars((string) $x['reporter_name'], ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?php echo htmlspecialchars((string) $x['owner_role_name'], ENT_QUOTES, 'UTF-8'); ?></td>
          <td>
            <?php if (!$x['blockers']): ?>
              <span class="badge tkc-badge-ready">جاهزٌ للإقفال</span>
            <?php else: ?>
              <span class="badge tkc-badge-blocked">محجوب</span>
              <div class="tkc-blockers">
                <?php echo htmlspecialchars(implode(' · ', $x['blockers']), ENT_QUOTES, 'UTF-8'); ?>
              </div>
            <?php endif; ?>
          </td>
          <td>
            <a class="btn btn-sm btn-primary" href="ticket_form.php?id=<?php echo (int) $x['id']; ?>">
              فتحُ البلاغ للإقفال
            </a>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
    <p class="text-muted tkc-footnote">
      الإقفالُ يقع في <code>ticket_form.php</code> — مسارٌ واحدٌ يحمل حارسَ «من رفع
      البلاغَ لا يُقفله» ومنعَ الإقفالِ ومسارٌ إلزاميٌّ مفتوح وختمَ وقتِ الإقفال.
      وهذه الشاشةُ تسرد وتشخّص ولا تكتب — فمسارا إقفالٍ يتفرّقان أسوأُ من شاشةٍ ناقصة.
      والإغلاقُ الإداريُّ للمكرَّرِ والملغى في <code>admin_close.php</code> وهو غيرُ هذا.
    </p>
  </div></div>

  <?php endif; ?>
</div>
