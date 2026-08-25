<?php
/**
 * Clients/quotation_lines.php — بنود العروض
 *   الورقة 08 · INJ-SAL-ALIGN-01 — قدرةٌ ثبت غيابُها
 * ───────────────────────────────────────────────────────────────────────────
 * ◆ **بنودُ العرضِ تابعةٌ لرأسِه** — «المستندُ ثلاثيُّ المستويات: رأسٌ وبنودٌ
 *   وجدولة، والبنودُ ليست شاشةً مستقلة». فتُعرض تبويبًا داخلَ ملفِّ العرض.
 * ◆ **والحسابُ في طبقةِ الخدمةِ لا في الشاشة**: إجماليُّ البندِ يُعرض ولا يُدخَل.
 * ◆ **والصنفُ يُختار من كتالوجِ الخدماتِ لا يُكتب حرًّا** — فيمتنع الخطأُ الإملائيُّ
 *   وتَثبت المفاتيح.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
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
$qid = intval($_GET['quotation'] ?? 0);

$__pcAdd = ems_post_contract($conn, array(
    'action'  => 'align.quotation_line.add',
    'perm'    => 'can_add',
    'trigger' => 'add_line',
    'idem'    => array('q' => intval($_POST['quotation_id'] ?? 0),
                       'd' => (string) ($_POST['description'] ?? '')),
    'validate' => function (array $in) {
        $q = intval($in['quotation_id'] ?? 0);
        $d = trim((string) ($in['description'] ?? ''));
        $qty = (float) ($in['qty'] ?? 0);
        $pr = (float) ($in['unit_price'] ?? 0);
        $cu = trim((string) ($in['currency'] ?? ''));
        if ($q <= 0) { return array('ok' => false, 'msg' => 'لا بند بلا رأس عرض (422)'); }
        if (mb_strlen($d) < 3) { return array('ok' => false, 'msg' => 'وصف البند إلزامي (422)'); }
        if ($qty <= 0) { return array('ok' => false, 'msg' => 'الكمية يجب أن تكون موجبة (422)'); }
        if ($pr < 0) { return array('ok' => false, 'msg' => 'السعر لا يكون سالبا (422)'); }
        if (mb_strlen($cu) < 3) { return array('ok' => false, 'msg' => 'لا مبلغ بلا عملة (422)'); }
        return array('ok' => true, 'data' => array(
            'quotation_id' => $q, 'description' => $d, 'qty' => $qty, 'unit_price' => $pr,
            'currency' => $cu, 'unit_type' => (string) ($in['unit_type'] ?? 'hour'),
            'discount_pct' => (float) ($in['discount_pct'] ?? 0),
            'product_id' => intval($in['product_id'] ?? 0),
            'notes' => (string) ($in['notes'] ?? '')));
    },
));
if (!$__pcAdd['ok'] && $__pcAdd['msg'] !== '') { $msg = $__pcAdd['msg']; }
if ($__pcAdd['replay'])                         { $msg = $__pcAdd['msg']; }
if ($__pcAdd['run'] && $__pcAdd['ok']) {
    $res = CAP::addQuotationLine($conn, $gate, $company_id, $__pcAdd['data'], $uid);
    $msg = ($res['ok'] ? '✅ ' : '❌ ') . $res['reason'] . ' (' . $res['code'] . ')';
    if (!empty($res['ok'])) { ems_pc_idem_mark($conn, $__pcAdd['idem'], $__pcAdd['code'], 'sal_quotation_lines#' . (int) $res['id']); }
}

$rows = array(); $queueFail = ''; $quotes = array(); $products = array(); $sum = 0.0; $cur = '';
try {
    $opts = array('orderBy' => '`quotation_id` DESC, `line_no` ASC', 'limit' => 300);
    if ($qid > 0) { $opts['where'] = array('quotation_id' => $qid); }
    $rows = $gate->select('sal_quotation_lines', $opts);
    foreach ($rows as $r) { $sum += (float) $r['line_total']; if ($cur === '') { $cur = (string) $r['currency']; } }
    $quotes = $gate->select('quotations', array('columns' => array('id'),
        'orderBy' => '`id` DESC', 'limit' => 120));
    $products = $gate->select('products', array('columns' => array('id'),
        'orderBy' => '`id` DESC', 'limit' => 200));
} catch (\Throwable $e) { $queueFail = 'تعذر قراءة البنود: ' . $e->getMessage(); }

$page_title = 'بنود العروض';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }

/* شريطُ تبويباتِ العائلة — قرارُ وثيقةِ المواءمة (مكوّنٌ مركزيّ) */
$sft_family = 'quotation'; $sft_active = 'lines';
include __DIR__ . '/../includes/sales_family_tabs.php';
?>
<div class="main" dir="rtl">
  <?php
  $header_icon = 'fa fa-list-ol';
  $header_title_html = htmlspecialchars('بنود العروض', ENT_QUOTES, 'UTF-8');
  ob_start(); ?><span class="badge"><?= $queueFail === '' ? count($rows) : '—' ?> بندا · الإجمالي <?= number_format($sum, 2) ?> <?= htmlspecialchars($cur, ENT_QUOTES, 'UTF-8') ?></span><?php
  $header_actions = array(array('raw' => trim((string) ob_get_clean())));
  $header_back = false;
  include __DIR__ . '/../includes/page_header.php';
  echo ems_states_bundle('لا بند مسجل بعد',
      'البند يضاف إلى رأس عرض قائم — والإجمالي يحسب في الخدمة ويعرض ولا يدخل');
  ?>
  <p class="text-muted">الورقة ٠٨ · بنود العرض تابعة لرأسه —
     <strong>الحساب في طبقة الخدمة لا في الشاشة</strong>.</p>
  <?php if ($msg !== ''): ?>
    <div class="alert <?= (mb_strpos($msg, '✅') !== false ? 'alert-success' : 'alert-danger') ?>">
      <?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>
  <?php if ($queueFail !== ''): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($queueFail, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>

  <form method="post" class="row g-2 mb-3">
    <?php echo csrf_field(); ?>
    <div class="col-auto"><label class="form-label" for="ql_q">العرض</label>
      <select class="form-control form-control-sm" name="quotation_id" id="ql_q" required>
        <option value="">— رأس العرض —</option>
        <?php foreach ($quotes as $q): ?>
          <option value="<?= (int) $q['id'] ?>"<?= ($qid === (int) $q['id'] ? ' selected' : '') ?>>#<?= (int) $q['id'] ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-auto"><label class="form-label" for="ql_p">من الكتالوج</label>
      <select class="form-control form-control-sm" name="product_id" id="ql_p">
        <option value="">— اختياري —</option>
        <?php foreach ($products as $pr): ?><option value="<?= (int) $pr['id'] ?>">#<?= (int) $pr['id'] ?></option><?php endforeach; ?>
      </select></div>
    <div class="col-auto"><label class="form-label" for="ql_d">الوصف</label>
      <input class="form-control form-control-sm" type="text" maxlength="240" required name="description" id="ql_d"></div>
    <div class="col-auto"><label class="form-label" for="ql_qty">الكمية</label>
      <input class="form-control form-control-sm" type="number" step="0.0001" min="0.0001" required name="qty" id="ql_qty"></div>
    <div class="col-auto"><label class="form-label" for="ql_u">الوحدة</label>
      <select class="form-control form-control-sm" name="unit_type" id="ql_u">
        <option value="hour">ساعة</option><option value="ton">طن</option>
        <option value="meter">متر</option><option value="trip">رحلة</option></select></div>
    <div class="col-auto"><label class="form-label" for="ql_pr">سعر الوحدة</label>
      <input class="form-control form-control-sm" type="number" step="0.01" min="0" required name="unit_price" id="ql_pr"></div>
    <div class="col-auto"><label class="form-label" for="ql_c">العملة</label>
      <input class="form-control form-control-sm" type="text" maxlength="8" required name="currency" id="ql_c"></div>
    <div class="col-auto"><label class="form-label" for="ql_disc">الخصم ٪</label>
      <input class="form-control form-control-sm" type="number" step="0.01" min="0" max="100" value="0" name="discount_pct" id="ql_disc"></div>
    <div class="col-auto align-self-end">
      <button class="action-btn" type="submit" name="add_line" value="1"><i class="fa fa-plus"></i> إضافة بند</button></div>
  </form>

  <table class="table table-striped" data-no-dt>
    <thead><tr>
      <th>العرض</th><th>#</th><th>الوصف</th><th>الكمية</th><th>سعر الوحدة</th>
      <th>الخصم ٪</th><th>الإجمالي</th><th>العملة</th><th>أضافه</th>
      <th class="ems-gov-th none" data-gov="entity" data-slice="1">الكيان</th>
      <th class="ems-gov-th none" data-gov="currency" data-slice="3">العملة</th>
    </tr></thead>
    <tbody>
    <?php if (empty($rows)): ?>
      <tr><td colspan="9" class="text-center text-muted">لا بند مسجل بعد</td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td>#<?= (int) $r['quotation_id'] ?></td>
        <td><?= (int) $r['line_no'] ?></td>
        <td><?= htmlspecialchars((string) $r['description'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= number_format((float) $r['qty'], 2) ?> <?= htmlspecialchars((string) $r['unit_type'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= number_format((float) $r['unit_price'], 2) ?></td>
        <td><?= number_format((float) $r['discount_pct'], 2) ?></td>
        <td><strong><?= number_format((float) $r['line_total'], 2) ?></strong></td>
        <td><?= htmlspecialchars((string) $r['currency'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= (int) $r['created_by'] ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
