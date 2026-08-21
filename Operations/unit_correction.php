<?php
/**
 * Operations/unit_correction.php — تصحيحُ الوحداتِ بالسلسلةِ الثلاثية
 *   عقدةُ سلسلةِ الأثرِ ١٣ · INJ-CHAIN-CLOSE-01 · الموجة الرابعة
 * ───────────────────────────────────────────────────────────────────────────
 * ◆ **لا تصحيحَ إلا بمرورِ السلسلةِ كاملة**: العميلُ والمورّدُ والمشغّلُ ثلاثتُهم
 *   أو لا يمرّ. وسلطتُه `RESOLVE_FROM_POLICY:unit_correction`.
 * ◆ **ولا تصحيحَ بلا سببٍ مكتوب** — والقاعدةُ نفسُها ترفض سببًا أقصرَ من ثمانيةِ
 *   محارف، وترفض تطبيقًا بلا الأطرافِ الثلاثة.
 * ◆ مالكُ العملية: التشغيلُ بالتنسيقِ مع المالكين الثلاثة — والمحاسبُ المنتدبُ
 *   يراجع أثرَ التصحيحِ ولا يقرّره.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/../includes/post_contract.php';
require_once __DIR__ . '/../app/Services/Chain/ChainNodeService.php';

enforce_current_page_view_permission($conn, '../main/dashboard.php');

/* بوابةُ المستأجِر — لا استعلامَ خامٍّ على جدولِ مستأجِرٍ في هذه الشاشة */
$gate = ems_tenant_db();

use App\Services\Chain\ChainNodeService as CN;

$company_id = intval($_SESSION['user']['company_id'] ?? 0);
$uid = intval($_SESSION['user']['id'] ?? 0);
$msg = '';

$KINDS  = array('adjustment' => 'تعديل', 'reversal' => 'عكس', 'split' => 'تجزئة', 'merge' => 'دمج');
$FIELDS = array('quantity' => 'الكمية', 'responsible_party' => 'الطرف المسؤول',
                'time_state' => 'حالة الزمن', 'classification' => 'التصنيف');
$PARTIES = array('client' => 'العميل', 'supplier' => 'المورّد', 'worker' => 'المشغّل');

$__pcOpen = ems_post_contract($conn, array(
    'action'  => 'chain.unit_correction.open',
    'perm'    => 'can_add',
    'trigger' => 'open_corr',
    'idem'    => array('e' => intval($_POST['entry_id'] ?? 0), 'f' => (string) ($_POST['field_changed'] ?? ''),
                       'v' => (string) ($_POST['value_after'] ?? '')),
    'validate' => function (array $in) use ($KINDS, $FIELDS) {
        $e = intval($in['entry_id'] ?? 0);
        $k = (string) ($in['correction_kind'] ?? '');
        $f = (string) ($in['field_changed'] ?? '');
        $b = trim((string) ($in['value_before'] ?? ''));
        $a = trim((string) ($in['value_after'] ?? ''));
        $r = trim((string) ($in['reason'] ?? ''));
        if ($e <= 0) { return array('ok' => false, 'msg' => 'لا تصحيحَ بلا واقعة (422)'); }
        if (!isset($KINDS[$k]))  { return array('ok' => false, 'msg' => 'نوعُ التصحيحِ محكومٌ من قائمةٍ مغلقة (422)'); }
        if (!isset($FIELDS[$f])) { return array('ok' => false, 'msg' => 'الحقلُ المُصحَّحُ محكومٌ من قائمةٍ مغلقة (422)'); }
        if ($b === '' || $a === '') { return array('ok' => false, 'msg' => 'القيمتان قبلَ وبعدَ إلزاميتان (422)'); }
        if ($b === $a) { return array('ok' => false, 'msg' => 'لا تصحيحَ بلا تغيير (422)'); }
        if (mb_strlen($r) < 8) { return array('ok' => false, 'msg' => 'لا تصحيحَ بلا سببٍ مكتوبٍ مفهوم (422)'); }
        return array('ok' => true, 'data' => array('entry_id' => $e, 'correction_kind' => $k,
            'field_changed' => $f, 'value_before' => $b, 'value_after' => $a, 'reason' => $r));
    },
));
if (!$__pcOpen['ok'] && $__pcOpen['msg'] !== '') { $msg = $__pcOpen['msg']; }
if ($__pcOpen['replay'])                          { $msg = $__pcOpen['msg']; }
if ($__pcOpen['run'] && $__pcOpen['ok']) {
    $res = CN::openCorrection($conn, $gate, $company_id, $__pcOpen['data'], $uid);
    $msg = ($res['ok'] ? '✅ ' : '❌ ') . $res['reason'] . ' (' . $res['code'] . ')';
    if (!empty($res['ok'])) { ems_pc_idem_mark($conn, $__pcOpen['idem'], $__pcOpen['code'], 'unit_corrections#' . (int) $res['id']); }
}

$__pcParty = ems_post_contract($conn, array(
    'action'  => 'chain.unit_correction.party',
    'perm'    => 'can_edit',
    'trigger' => 'party_ok',
    'idem'    => array('id' => intval($_POST['party_ok'] ?? 0), 'p' => (string) ($_POST['party'] ?? '')),
    'validate' => function (array $in) use ($PARTIES) {
        $id = intval($in['party_ok'] ?? 0);
        $p  = (string) ($in['party'] ?? '');
        if ($id <= 0) { return array('ok' => false, 'msg' => 'تصحيحٌ غيرُ صالح (422)'); }
        if (!isset($PARTIES[$p])) { return array('ok' => false, 'msg' => 'الطرفُ محكومٌ من قائمةٍ مغلقة (422)'); }
        return array('ok' => true, 'data' => array('id' => $id, 'party' => $p));
    },
));
if (!$__pcParty['ok'] && $__pcParty['msg'] !== '') { $msg = $__pcParty['msg']; }
if ($__pcParty['replay'])                           { $msg = $__pcParty['msg']; }
if ($__pcParty['run'] && $__pcParty['ok']) {
    $res = CN::correctionPartyOk($conn, $gate, $company_id, (int) $__pcParty['data']['id'],
                                 (string) $__pcParty['data']['party'], $uid);
    $msg = ($res['ok'] ? '✅ ' : '❌ ') . $res['reason'] . ' (' . $res['code'] . ')';
    if (!empty($res['ok'])) { ems_pc_idem_mark($conn, $__pcParty['idem'], $__pcParty['code'], 'unit_corrections#' . (int) $__pcParty['data']['id']); }
}

$rows = array(); $queueFail = '';
try {
    $rows = $gate->select('unit_corrections', array(
        'orderBy' => "`state` = 'in_chain' DESC, `id` DESC", 'limit' => 200));
} catch (\Throwable $e) { $queueFail = 'تعذّر قراءةُ الطابور: ' . $e->getMessage(); }

$page_title = 'تصحيح الوحدات بالسلسلة الثلاثية';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main" dir="rtl">
  <?php
  $header_icon = 'fa fa-pen-to-square';
  $header_title_html = htmlspecialchars('تصحيح الوحدات بالسلسلة الثلاثية', ENT_QUOTES, 'UTF-8');
  ob_start(); ?><span class="badge"><?= $queueFail === '' ? count($rows) : '—' ?> تصحيحًا</span><?php
  $header_actions = array(array('raw' => trim((string) ob_get_clean())));
  $header_back = false;
  include __DIR__ . '/../includes/page_header.php';
  echo ems_states_bundle('لا تصحيحَ مفتوحٌ بعد',
      'التصحيحُ يُفتح بسببٍ مكتوبٍ ثم يمرُّ بالعميلِ والمورّدِ والمشغّلِ ثلاثتِهم');
  ?>
  <p class="text-muted">العقدة ١٣ · <code>RESOLVE_FROM_POLICY:unit_correction</code> —
     <strong>لا تصحيحَ إلا بمرورِ السلسلةِ كاملة</strong>؛ وبطرفَين يبقى في السلسلةِ ولا يُعتمد.</p>
  <?php if ($msg !== ''): ?>
    <div class="alert <?= (mb_strpos($msg, '✅') !== false ? 'alert-success' : 'alert-danger') ?>">
      <?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>
  <?php if ($queueFail !== ''): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($queueFail, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>

  <form method="post" class="row g-2 mb-3">
    <?php echo csrf_field(); ?>
    <div class="col-auto"><label class="form-label" for="uc_entry">الواقعة</label>
      <input class="form-control form-control-sm" type="number" min="1" required name="entry_id" id="uc_entry"></div>
    <div class="col-auto"><label class="form-label" for="uc_kind">نوع التصحيح</label>
      <select class="form-control form-control-sm" name="correction_kind" id="uc_kind" required>
        <?php foreach ($KINDS as $k => $v): ?><option value="<?= htmlspecialchars($k, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($v, ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?>
      </select></div>
    <div class="col-auto"><label class="form-label" for="uc_field">الحقل</label>
      <select class="form-control form-control-sm" name="field_changed" id="uc_field" required>
        <?php foreach ($FIELDS as $k => $v): ?><option value="<?= htmlspecialchars($k, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($v, ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?>
      </select></div>
    <div class="col-auto"><label class="form-label" for="uc_before">القيمة قبل</label>
      <input class="form-control form-control-sm" type="text" maxlength="120" required name="value_before" id="uc_before"></div>
    <div class="col-auto"><label class="form-label" for="uc_after">القيمة بعد</label>
      <input class="form-control form-control-sm" type="text" maxlength="120" required name="value_after" id="uc_after"></div>
    <div class="col-auto"><label class="form-label" for="uc_reason">السبب</label>
      <input class="form-control form-control-sm" type="text" maxlength="400" minlength="8" required name="reason" id="uc_reason"></div>
    <div class="col-auto align-self-end">
      <button class="action-btn" type="submit" name="open_corr" value="1"><i class="fa fa-plus"></i> فتح تصحيح</button></div>
  </form>

  <table class="table table-striped" data-no-dt>
    <thead><tr>
      <th>قرار الأطراف</th><th>الواقعة</th><th>النوع</th><th>الحقل</th><th>قبل</th><th>بعد</th>
      <th>السبب</th><th>الحالة</th><th>العميل</th><th>المورّد</th><th>المشغّل</th>
      <th class="ems-gov-th none" data-gov="entity" data-slice="1">الكيان</th>
      <th class="ems-gov-th none" data-gov="creator" data-slice="1">المُنشئ</th>
      <th class="ems-gov-th none" data-gov="idem_key" data-slice="2">مفتاح منع التكرار</th>
    </tr></thead>
    <tbody>
    <?php if (empty($rows)): ?>
      <tr><td colspan="11" class="text-center text-muted">لا تصحيحَ مفتوحٌ بعد</td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $r): $id = (int) $r['id']; ?>
      <tr>
        <td>
          <?php if ($r['state'] === 'in_chain'): ?>
            <form method="post"><?php echo csrf_field(); ?>
              <input type="hidden" name="party_ok" value="<?= $id ?>">
              <label class="visually-hidden" for="uc_p_<?= $id ?>">الطرف</label>
              <select class="form-control form-control-sm" name="party" id="uc_p_<?= $id ?>" required>
                <option value="">— الطرف —</option>
                <?php foreach ($PARTIES as $k => $v):
                    if (!empty($r[$k . '_ok_at'])) { continue; } ?>
                  <option value="<?= htmlspecialchars($k, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($v, ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
              </select>
              <button class="action-btn" type="submit"><i class="fa fa-check"></i> موافقة طرف</button></form>
          <?php else: ?><span class="badge">—</span><?php endif; ?>
        </td>
        <td>#<?= (int) $r['entry_id'] ?></td>
        <td><?= htmlspecialchars($KINDS[$r['correction_kind']] ?? (string) $r['correction_kind'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars($FIELDS[$r['field_changed']] ?? (string) $r['field_changed'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars((string) $r['value_before'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars((string) $r['value_after'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars(mb_substr((string) $r['reason'], 0, 70), ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars((string) $r['state'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= $r['client_ok_at']   ? '✔' : '—' ?></td>
        <td><?= $r['supplier_ok_at'] ? '✔' : '—' ?></td>
        <td><?= $r['worker_ok_at']   ? '✔' : '—' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
