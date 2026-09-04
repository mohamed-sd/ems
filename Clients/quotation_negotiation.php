<?php
/**
 * Clients/quotation_negotiation.php — التفاوض ومراجعات العرض
 *   الورقة 09 · INJ-SAL-ALIGN-01 — قدرةٌ ثبت غيابُها
 * ───────────────────────────────────────────────────────────────────────────
 * ◆ **سجلُّ نسخِ العرضِ ووقائعِ التغيير** — والمستندُ الذي يُوقَّع أو يُحتجُّ به
 *   أمامَ الغيرِ يبقى بمعرّفِه وتاريخِه ونسختِه.
 * ◆ **ولا واقعةَ تفاوضٍ بلا نصٍّ يشرحها** — قيدُ القاعدةِ يرفض أقصرَ من ثمانيةِ
 *   محارف؛ فالسجلُّ يُقرأ بعدَ سنةٍ ويُفهم.
 * ◆ **ونوعُ الواقعةِ محكومٌ من قائمةٍ مغلقة** — لا نصَّ حرٌّ يُفسد التصنيف.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../includes/w14_grid.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/../includes/post_contract.php';
require_once __DIR__ . '/../app/Services/Align/CapabilityService.php';

enforce_current_page_view_permission($conn, '../main/dashboard.php');

/* بوابةُ المستأجِر — لا استعلامَ خامٍّ على جدولِ مستأجِرٍ في هذه الشاشة */
$gate = ems_tenant_db();

use App\Services\Align\CapabilityService as CAP;

$company_id = intval($_SESSION['user']['company_id'] ?? 0);
$uid = intval($_SESSION['user']['id'] ?? 0);
$msg = '';

$KINDS = array('issued' => 'صدور العرض', 'sent' => 'إرسال للعميل',
               'client_counter' => 'رد مضاد من العميل', 'revised' => 'نسخة منقحة',
               'accepted' => 'قبول العميل', 'rejected' => 'رفض العميل', 'expired' => 'انتهاء السريان');
$PARTY = array('us' => 'نحن', 'client' => 'العميل');

$__pcLog = ems_post_contract($conn, array(
    'action'  => 'align.quotation_negotiation.log',
    'perm'    => 'can_add',
    'trigger' => 'log_event',
    'idem'    => array('q' => intval($_POST['quotation_id'] ?? 0),
                       'k' => (string) ($_POST['event_kind'] ?? ''),
                       'n' => (string) ($_POST['note'] ?? '')),
    'validate' => function (array $in) use ($KINDS, $PARTY) {
        $q = intval($in['quotation_id'] ?? 0);
        $k = (string) ($in['event_kind'] ?? '');
        $p = (string) ($in['party'] ?? '');
        $n = trim((string) ($in['note'] ?? ''));
        if ($q <= 0) { return array('ok' => false, 'msg' => 'لا واقعة تفاوض بلا عرض (422)'); }
        if (!isset($KINDS[$k])) { return array('ok' => false, 'msg' => 'نوع الواقعة محكوم من قائمة مغلقة (422)'); }
        if (!isset($PARTY[$p])) { return array('ok' => false, 'msg' => 'الطرف: نحن أو العميل (422)'); }
        if (mb_strlen($n) < 8) { return array('ok' => false, 'msg' => 'لا واقعة تفاوض بلا نص يشرحها (422)'); }
        return array('ok' => true, 'data' => array(
            'quotation_id' => $q, 'event_kind' => $k, 'party' => $p, 'note' => $n,
            'doc_ref' => (string) ($in['doc_ref'] ?? ''),
            'amount_before' => (string) ($in['amount_before'] ?? ''),
            'amount_after' => (string) ($in['amount_after'] ?? ''),
            'currency' => (string) ($in['currency'] ?? ''),
            'valid_until' => (string) ($in['valid_until'] ?? '')));
    },
));
if (!$__pcLog['ok'] && $__pcLog['msg'] !== '') { $msg = $__pcLog['msg']; }
if ($__pcLog['replay'])                         { $msg = $__pcLog['msg']; }
if ($__pcLog['run'] && $__pcLog['ok']) {
    $res = CAP::logNegotiation($conn, $gate, $company_id, $__pcLog['data'], $uid);
    $msg = ($res['ok'] ? '✅ ' : '❌ ') . $res['reason'] . ' (' . $res['code'] . ')';
    if (!empty($res['ok'])) { ems_pc_idem_mark($conn, $__pcLog['idem'], $__pcLog['code'], 'sal_quotation_revisions#' . (int) $res['id']); }
}

$rows = array(); $queueFail = ''; $quotes = array();
try {
    $rows = $gate->select('sal_quotation_revisions', array(
        'orderBy' => '`quotation_id` DESC, `revision_no` DESC', 'limit' => 300));
    $quotes = $gate->select('quotations', array('columns' => array('id'),
        'orderBy' => '`id` DESC', 'limit' => 120));
} catch (\Throwable $e) { $queueFail = 'تعذر قراءة السجل: ' . $e->getMessage(); }

$page_title = 'التفاوض ومراجعات العرض';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }

/* شريطُ تبويباتِ العائلة — قرارُ وثيقةِ المواءمة (مكوّنٌ مركزيّ) */
$sft_family = 'quotation'; $sft_active = 'nego';
include __DIR__ . '/../includes/sales_family_tabs.php';
?>
<div class="main" dir="rtl">
  <?php
  $header_icon = 'fa fa-comments-dollar';
  $header_title_html = htmlspecialchars('التفاوض ومراجعات العرض', ENT_QUOTES, 'UTF-8');
  ob_start(); ?><span class="badge"><?= $queueFail === '' ? count($rows) : '—' ?> واقعة</span><?php
  /* زرُّ الإضافةِ المعياريُّ — الطيُّ والفتحُ بـ`allforms-visible` كنظائرِه. */
  $header_actions = array(
      array('raw' => trim((string) ob_get_clean())),
      array('tag' => 'button', 'id' => 'toggleForm', 'class' => 'add-btn',
            'icon' => 'fa fa-solid fa-plus', 'label' => 'تسجيل واقعة'),
  );
  $header_back = false;
  include __DIR__ . '/../includes/page_header.php'; ?>
    <!-- سجلُّ حقولِ الورقةِ بحبّتِه — يُضاف بجانبِ ما بُني لا بدلًا منه،
         فالمبنيُّ له أفعالُه والورقةُ تطلب السجلَّ بحقولِه كلِّها -->
    <div class="card"><div class="card-header"><h5><i class="fa fa-clipboard-list"></i> سجل حقول الورقة</h5></div>
    <div class="card-body"><div class="table-container">
        <?php /* GUIDE_COLS:govui_field_close:emsList_sal_quotation_negotiation
             الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
             والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
             ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
        $GUIDE_COLS = array(
            'رقم الواقعة' => 'g141',
            'نوع السجل' => 'g142',
            'رقم العرض' => 'g143',
            'مرجع العقد' => 'g144',
            'دورة الالتزام الجديدة' => 'g145',
            'دورة الالتزام السابقة' => 'g146',
            'نطاق المقارنة' => 'g147',
            'التاريخ' => 'g148',
            'نوع التغيير' => 'g149',
            'قبل' => 'g150',
            'بعد' => 'g151',
            'الأثر التجاري' => 'g152',
            'الوثيقة المرجعية' => 'g153',
            'السبب/الدليل' => 'g154',
            'الطرف الطالب' => 'g155',
            'الحالة' => 'g156',
            'ملاحظات' => 'g157',
            'مفتاح دورة الالتزام المصدر' => 'g158',
            'مستوى الحجية' => 'g159',
            'أساس القيمة الرجعية' => 'g160',
        );
        $D = array();
        $__gridRows = ems_w14_guide_rows('sal_quotation_negotiation');
        echo ems_w14_grid('emsList_sal_quotation_negotiation', $GUIDE_COLS, $__gridRows, $D, 'لا سطر مسجل بعد في التفاوض ومراجعات العرض'); /* /GUIDE_COLS */ ?>
    </div></div></div>
    <?php 
  echo ems_states_bundle('لا واقعة تفاوض مسجلة بعد',
      'كل نسخة ووقائع تغييرها تسجل بنصها ومرجعها — فالسجل يقرأ بعد سنة ويفهم');
  ?>
  <p class="text-muted">الورقة ٠٩ · سجل نسخ العرض ووقائع التفاوض —
     <strong>لا واقعة بلا نص يشرحها</strong>، ونوعها محكوم من قائمة مغلقة.</p>
  <?php if ($msg !== ''): ?>
    <div class="alert <?= (mb_strpos($msg, '✅') !== false ? 'alert-success' : 'alert-danger') ?>">
      <?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>
  <?php if ($queueFail !== ''): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($queueFail, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>

  <?php /* النموذجُ الموحَّد — `allforms` يجلب الجِلدَ والطيَّ، والحقلُ ابنٌ
       مباشرٌ لـ`.form-grid`. ⛔ ولا شبكةَ بوتستراب: تُفلِت الحقولَ من الجِلد. */ ?>
  <form id="qnForm" action="" method="post" class="allforms">
    <div class="card-header">
      <h5><i class="fas fa-edit"></i> <span id="formTitle">تسجيل واقعة تفاوض</span></h5>
    </div>
    <?php echo csrf_field(); ?>
    <div class="card shadow-sm"><div class="card-body">
      <div class="form-grid">
        <div><label for="qn_q">العرض *</label>
          <select name="quotation_id" id="qn_q" required>
            <option value="">— رأس العرض —</option>
            <?php foreach ($quotes as $q): ?><option value="<?= (int) $q['id'] ?>">#<?= (int) $q['id'] ?></option><?php endforeach; ?>
          </select></div>
        <div><label for="qn_k">نوع الواقعة *</label>
          <select name="event_kind" id="qn_k" required>
            <?php foreach ($KINDS as $k => $v): ?><option value="<?= htmlspecialchars($k, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($v, ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?>
          </select></div>
        <div><label for="qn_p">الطرف *</label>
          <select name="party" id="qn_p" required>
            <?php foreach ($PARTY as $k => $v): ?><option value="<?= htmlspecialchars($k, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($v, ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?>
          </select></div>
        <div><label for="qn_n">النص *</label>
          <input type="text" maxlength="400" minlength="8" required name="note" id="qn_n"></div>
        <div><label for="qn_b">المبلغ قبل</label>
          <input type="number" step="0.01" name="amount_before" id="qn_b"></div>
        <div><label for="qn_a">المبلغ بعد</label>
          <input type="number" step="0.01" name="amount_after" id="qn_a"></div>
        <div><label for="qn_c">العملة</label>
          <input type="text" maxlength="8" name="currency" id="qn_c"></div>
        <div><label for="qn_v">السريان حتى</label>
          <input type="date" name="valid_until" id="qn_v"></div>
      </div>
      <div class="form-actions">
        <button class="btn-primary" type="submit" name="log_event" value="1"><i class="fa fa-plus"></i> تسجيل واقعة</button>
        <button type="button" class="btn-secondary" id="qnFormCancelBtn"><i class="fas fa-times"></i> إلغاء</button>
      </div>
    </div></div>
  </form>

  <table class="table table-striped" data-no-dt>
    <thead><tr>
      <th>العرض</th><th>النسخة</th><th>الواقعة</th><th>الطرف</th><th>النص</th>
      <th>قبل</th><th>بعد</th><th>السريان</th><th>سجلها</th>
      <th class="ems-gov-th none" data-gov="entity" data-slice="1">الكيان</th>
      <th class="ems-gov-th none" data-gov="idem_key" data-slice="2">مفتاح منع التكرار</th>
    </tr></thead>
    <tbody>
    <?php if (empty($rows)): ?>
      <tr><td colspan="9" class="text-center text-muted">لا واقعة تفاوض مسجلة بعد</td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td>#<?= (int) $r['quotation_id'] ?></td>
        <td><?= (int) $r['revision_no'] ?></td>
        <td><?= htmlspecialchars($KINDS[$r['event_kind']] ?? (string) $r['event_kind'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars($PARTY[$r['party']] ?? (string) $r['party'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars(mb_substr((string) $r['note'], 0, 70), ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= $r['amount_before'] !== null ? number_format((float) $r['amount_before'], 2) : '—' ?></td>
        <td><?= $r['amount_after'] !== null ? number_format((float) $r['amount_after'], 2) : '—' ?></td>
        <td><?= htmlspecialchars((string) $r['valid_until'], ENT_QUOTES, 'UTF-8') ?: '—' ?></td>
        <td><?= (int) $r['decided_by'] ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<script>
/* طيُّ النموذجِ وفتحُه — السلوكُ المعياريُّ نفسُه في «سجل العملاء».
   ⛔ ولا `style.display`: الورقةُ تحمل `.allforms{display:none}` — فالتبديلُ
   بالصنفِ وحدَه، وكتابةُ `display` سطريًّا بلا أولويةٍ تخسر أمامها. */
(function () {
    var f = document.getElementById('qnForm');
    var b = document.getElementById('toggleForm');
    var c = document.getElementById('qnFormCancelBtn');
    if (!f || !b) { return; }
    function open(on) {
        f.classList.toggle('allforms-visible', on);
        b.setAttribute('aria-expanded', on ? 'true' : 'false');
        if (on) { var i = f.querySelector('select,input'); if (i) { i.focus(); } }
    }
    b.addEventListener('click', function (e) {
        e.preventDefault();
        open(!f.classList.contains('allforms-visible'));
    });
    if (c) { c.addEventListener('click', function () { f.reset(); open(false); }); }
    if (document.querySelector('.alert-danger')) { open(true); }
})();
</script>
