<?php
/**
 * Suppliers/supplier_violations.php — المخالفات والجزاءات
 *   الورقة م19 · INJ-SUP-ALIGN-01 — قدرةٌ ثبت غيابُها
 * ───────────────────────────────────────────────────────────────────────────
 * ◆ **دليلُ الغياب**: «القواعدُ موجودةٌ والوقائعُ لا» — `supplier_rules` تحمل
 *   قواعدَ التحميلِ والجزاءات، ولا سطحَ يسجّل **واقعةَ** مخالفةٍ على مورّد.
 * ◆ **سجلٌّ تابعٌ للتسوية** وأثرُه فيها — ولا تنفيذَ نقديًّا هنا.
 * ◆ **ومَن رصد لا يعتمد ولا يُسقط**: الجزاءُ أثرٌ ماليٌّ لا يقرّره راصدُه وحدَه —
 *   وقيدُ `CHECK` في القاعدةِ يسند القاعدةَ لا النصُّ وحدَه.
 * ◆ **ولا إسقاطَ بلا سببٍ مكتوب**.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }
include '../config.php';
require_once __DIR__ . '/../includes/w14_grid.php';
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

$KINDS = array('availability' => 'الجاهزية والتوفر', 'quality' => 'الجودة',
               'safety' => 'السلامة', 'document' => 'الوثائق', 'delay' => 'التأخير', 'other' => 'أخرى');

$__pcNew = ems_post_contract($conn, array(
    'action'  => 'align.supplier_violation.record',
    'perm'    => 'can_add',
    'trigger' => 'rec_violation',
    'idem'    => array('s' => intval($_POST['supplier_id'] ?? 0),
                       'd' => (string) ($_POST['occurred_on'] ?? ''),
                       'k' => (string) ($_POST['violation_kind'] ?? '')),
    'validate' => function (array $in) use ($KINDS) {
        $s = intval($in['supplier_id'] ?? 0);
        $k = (string) ($in['violation_kind'] ?? '');
        $o = (string) ($in['occurred_on'] ?? '');
        $d = trim((string) ($in['description'] ?? ''));
        $a = (float) ($in['penalty_amount'] ?? 0);
        $c = trim((string) ($in['currency'] ?? ''));
        if ($s <= 0) { return array('ok' => false, 'msg' => 'لا مخالفة بلا مورد (422)'); }
        if (!isset($KINDS[$k])) { return array('ok' => false, 'msg' => 'نوع المخالفة محكوم من قائمة مغلقة (422)'); }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $o)) { return array('ok' => false, 'msg' => 'تاريخ الوقوع بصيغة YYYY-MM-DD (422)'); }
        if (mb_strlen($d) < 8) { return array('ok' => false, 'msg' => 'لا مخالفة بلا وصف مفهوم (422)'); }
        if ($a > 0 && mb_strlen($c) < 3) { return array('ok' => false, 'msg' => 'لا جزاء بمبلغ بلا عملة (422)'); }
        return array('ok' => true, 'data' => array(
            'supplier_id' => $s, 'violation_kind' => $k, 'occurred_on' => $o, 'description' => $d,
            'penalty_amount' => $a, 'currency' => $c,
            'contract_id' => intval($in['contract_id'] ?? 0),
            'settlement_id' => intval($in['settlement_id'] ?? 0),
            'rule_ref' => (string) ($in['rule_ref'] ?? ''),
            'evidence_ref' => (string) ($in['evidence_ref'] ?? '')));
    },
));
if (!$__pcNew['ok'] && $__pcNew['msg'] !== '') { $msg = $__pcNew['msg']; }
if ($__pcNew['replay'])                         { $msg = $__pcNew['msg']; }
if ($__pcNew['run'] && $__pcNew['ok']) {
    $res = CAP::recordViolation($conn, $gate, $company_id, $__pcNew['data'], $uid);
    $msg = ($res['ok'] ? '✅ ' : '❌ ') . $res['reason'] . ' (' . $res['code'] . ')';
    if (!empty($res['ok'])) { ems_pc_idem_mark($conn, $__pcNew['idem'], $__pcNew['code'], 'sup_violations#' . (int) $res['id']); }
}

$__pcApp = ems_post_contract($conn, array(
    'action'  => 'align.supplier_violation.approve',
    'perm'    => 'can_edit',
    'trigger' => 'approve_violation',
    'idem'    => array('id' => intval($_POST['approve_violation'] ?? 0)),
    'validate' => function (array $in) {
        $id = intval($in['approve_violation'] ?? 0);
        if ($id <= 0) { return array('ok' => false, 'msg' => 'مخالفة غير صالحة (422)'); }
        return array('ok' => true, 'data' => array('id' => $id));
    },
));
if (!$__pcApp['ok'] && $__pcApp['msg'] !== '') { $msg = $__pcApp['msg']; }
if ($__pcApp['replay'])                         { $msg = $__pcApp['msg']; }
if ($__pcApp['run'] && $__pcApp['ok']) {
    $res = CAP::approveViolation($conn, $gate, $company_id, (int) $__pcApp['data']['id'], $uid);
    $msg = ($res['ok'] ? '✅ ' : '❌ ') . $res['reason'] . ' (' . $res['code'] . ')';
    if (!empty($res['ok'])) { ems_pc_idem_mark($conn, $__pcApp['idem'], $__pcApp['code'], 'sup_violations#' . (int) $__pcApp['data']['id']); }
}

$__pcWv = ems_post_contract($conn, array(
    'action'  => 'align.supplier_violation.waive',
    'perm'    => 'can_edit',
    'trigger' => 'waive_violation',
    'idem'    => array('id' => intval($_POST['waive_violation'] ?? 0),
                       'r' => (string) ($_POST['waive_reason'] ?? '')),
    'validate' => function (array $in) {
        $id = intval($in['waive_violation'] ?? 0);
        $r  = trim((string) ($in['waive_reason'] ?? ''));
        if ($id <= 0) { return array('ok' => false, 'msg' => 'مخالفة غير صالحة (422)'); }
        if (mb_strlen($r) < 8) { return array('ok' => false, 'msg' => 'لا إسقاط بلا سبب مكتوب مفهوم (422)'); }
        return array('ok' => true, 'data' => array('id' => $id, 'reason' => $r));
    },
));
if (!$__pcWv['ok'] && $__pcWv['msg'] !== '') { $msg = $__pcWv['msg']; }
if ($__pcWv['replay'])                        { $msg = $__pcWv['msg']; }
if ($__pcWv['run'] && $__pcWv['ok']) {
    $res = CAP::waiveViolation($conn, $gate, $company_id, (int) $__pcWv['data']['id'],
                               (string) $__pcWv['data']['reason'], $uid);
    $msg = ($res['ok'] ? '✅ ' : '❌ ') . $res['reason'] . ' (' . $res['code'] . ')';
    if (!empty($res['ok'])) { ems_pc_idem_mark($conn, $__pcWv['idem'], $__pcWv['code'], 'sup_violations#' . (int) $__pcWv['data']['id']); }
}

$rows = array(); $queueFail = ''; $sups = array();
try {
    $rows = $gate->select('sup_violations', array(
        'orderBy' => "`state` = 'recorded' DESC, `id` DESC", 'limit' => 300));
    $sups = $gate->select('suppliers', array('columns' => array('id'),
        'orderBy' => '`id` DESC', 'limit' => 200));
} catch (\Throwable $e) { $queueFail = 'تعذر قراءة السجل: ' . $e->getMessage(); }

$page_title = 'المخالفات والجزاءات';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }

/* شريطُ تبويباتِ العائلة — قرارُ وثيقةِ المواءمة (مكوّنٌ مركزيّ) */
$sft_family = 'settlement'; $sft_active = 'violations';
include __DIR__ . '/../includes/sales_family_tabs.php';
?>
<div class="main" dir="rtl">
  <?php
  $header_icon = 'fa fa-gavel';
  $header_title_html = htmlspecialchars('المخالفات والجزاءات', ENT_QUOTES, 'UTF-8');
  ob_start(); ?><span class="badge"><?= $queueFail === '' ? count($rows) : '—' ?> مخالفة</span><?php
  $header_actions = array(array('raw' => trim((string) ob_get_clean())));
  $header_back = false;
  include __DIR__ . '/../includes/page_header.php'; ?>
    <!-- سجلُّ حقولِ الورقةِ بحبّتِه — يُضاف بجانبِ ما بُني لا بدلًا منه،
         فالمبنيُّ له أفعالُه والورقةُ تطلب السجلَّ بحقولِه كلِّها -->
    <div class="card"><div class="card-header"><h5><i class="fa fa-clipboard-list"></i> المخالفات والجزاءات بحقول الورقة</h5></div>
    <div class="card-body"><div class="table-container">
        <?php /* GUIDE_COLS:govui_field_close
             الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
             والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
             ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
        $GUIDE_COLS = array(
            'رقم القيد' => 'c219',
            'التاريخ' => 'c220',
            'رقم المورد' => 'c221',
            'اسم المورد (بحث)' => 'c222',
            'كود عقد المورد' => 'c223',
            'نوع الجزاء/المطالبة' => 'c224',
            'الوصف' => 'c225',
            'المرجع التعاقدي (بند)' => 'c226',
            'المبلغ/الأثر' => 'c227',
            'العملة' => 'c228',
            'الحالة' => 'c229',
            'القرار' => 'c230',
            'معتمد القرار' => 'c231',
            'مرجع الخصم بالتسوية (م17)' => 'c232',
            'حالة البيانات' => 'c233',
            'ملاحظات' => 'c234',
        );
        $D = array();
        $__gridRows = ems_w14_guide_rows('sup_violations');
        echo ems_w14_grid('emsList_sup_viol', $GUIDE_COLS, $__gridRows, $D, 'لا مخالفة مسجلة بعد'); /* /GUIDE_COLS */ ?>
    </div></div></div>
    <?php 
  echo ems_states_bundle('لا مخالفة مسجلة بعد',
      'المخالفة ترصد بوصف ودليل، ثم تعتمد بيد غير يد راصدها — وأثرها في التسوية');
  ?>
  <p class="text-muted">الورقة م١٩ · سجل تابع للتسوية —
     <strong>من رصد لا يعتمد ولا يسقط</strong>، ولا إسقاط بلا سبب مكتوب.
     ولا تنفيذ نقديا هنا — الأثر يظهر في <a href="settlements.php">التسويات</a>.</p>
  <?php if ($msg !== ''): ?>
    <div class="alert <?= (mb_strpos($msg, '✅') !== false ? 'alert-success' : 'alert-danger') ?>">
      <?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>
  <?php if ($queueFail !== ''): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($queueFail, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>

  <form method="post" class="row g-2 mb-3">
    <?php echo csrf_field(); ?>
    <div class="col-auto"><label class="form-label" for="sv_s">المورد</label>
      <select class="form-control form-control-sm" name="supplier_id" id="sv_s" required>
        <option value="">— المورد —</option>
        <?php foreach ($sups as $s): ?><option value="<?= (int) $s['id'] ?>">#<?= (int) $s['id'] ?></option><?php endforeach; ?>
      </select></div>
    <div class="col-auto"><label class="form-label" for="sv_k">نوع المخالفة</label>
      <select class="form-control form-control-sm" name="violation_kind" id="sv_k" required>
        <?php foreach ($KINDS as $k => $v): ?><option value="<?= htmlspecialchars($k, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($v, ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?>
      </select></div>
    <div class="col-auto"><label class="form-label" for="sv_o">تاريخ الوقوع</label>
      <input class="form-control form-control-sm" type="date" required name="occurred_on" id="sv_o"></div>
    <div class="col-auto"><label class="form-label" for="sv_d">الوصف</label>
      <input class="form-control form-control-sm" type="text" maxlength="400" minlength="8" required name="description" id="sv_d"></div>
    <div class="col-auto"><label class="form-label" for="sv_e">مرجع الدليل</label>
      <input class="form-control form-control-sm" type="text" maxlength="120" name="evidence_ref" id="sv_e"></div>
    <div class="col-auto"><label class="form-label" for="sv_a">مبلغ الجزاء</label>
      <input class="form-control form-control-sm" type="number" step="0.01" min="0" value="0" name="penalty_amount" id="sv_a"></div>
    <div class="col-auto"><label class="form-label" for="sv_c">العملة</label>
      <input class="form-control form-control-sm" type="text" maxlength="8" name="currency" id="sv_c"></div>
    <div class="col-auto"><label class="form-label" for="sv_t">التسوية</label>
      <input class="form-control form-control-sm" type="number" min="0" name="settlement_id" id="sv_t"></div>
    <div class="col-auto align-self-end">
      <button class="action-btn" type="submit" name="rec_violation" value="1"><i class="fa fa-plus"></i> رصد مخالفة</button></div>
  </form>

  <table class="table table-striped" data-no-dt>
    <thead><tr>
      <th>الإجراء</th><th>الرقم</th><th>المورد</th><th>النوع</th><th>الوقوع</th>
      <th>الوصف</th><th>الجزاء</th><th>الحالة</th><th>رصدها</th><th>اعتمدها</th>
      <th class="ems-gov-th none" data-gov="entity" data-slice="1">الكيان</th>
      <th class="ems-gov-th none" data-gov="approved_at" data-slice="1">تاريخ الاعتماد</th>
      <th class="ems-gov-th none" data-gov="currency" data-slice="3">العملة</th>
    </tr></thead>
    <tbody>
    <?php if (empty($rows)): ?>
      <tr><td colspan="10" class="text-center text-muted">لا مخالفة مسجلة بعد</td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $r): $id = (int) $r['id']; ?>
      <tr>
        <td>
          <?php if ($r['state'] === 'recorded'): ?>
            <form method="post"><?php echo csrf_field(); ?>
              <input type="hidden" name="approve_violation" value="<?= $id ?>">
              <button class="action-btn" type="submit"><i class="fa fa-check"></i> اعتماد</button></form>
            <form method="post" class="mt-1"><?php echo csrf_field(); ?>
              <input type="hidden" name="waive_violation" value="<?= $id ?>">
              <label class="visually-hidden" for="sv_w_<?= $id ?>">سبب الإسقاط</label>
              <input class="form-control form-control-sm" type="text" minlength="8" maxlength="300"
                     required name="waive_reason" id="sv_w_<?= $id ?>" placeholder="سبب الإسقاط المكتوب">
              <button class="action-btn" type="submit"><i class="fa fa-ban"></i> إسقاط</button></form>
          <?php else: ?><span class="badge">—</span><?php endif; ?>
        </td>
        <td><?= htmlspecialchars((string) $r['violation_no'], ENT_QUOTES, 'UTF-8') ?></td>
        <td>#<?= (int) $r['supplier_id'] ?></td>
        <td><?= htmlspecialchars($KINDS[$r['violation_kind']] ?? (string) $r['violation_kind'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars((string) $r['occurred_on'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars(mb_substr((string) $r['description'], 0, 60), ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= number_format((float) $r['penalty_amount'], 2) ?> <?= htmlspecialchars((string) $r['currency'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars((string) $r['state'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= (int) $r['recorded_by'] ?></td>
        <td><?= (int) $r['approved_by'] ?: '—' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
