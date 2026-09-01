<?php
/**
 * Finance/tre_beneficiary.php — سجلُّ المستفيدين والحساباتِ البنكية
 *   الشرطُ السابقُ للموجةِ السابعة · INJ-CHAIN-CLOSE-01
 * ───────────────────────────────────────────────────────────────────────────
 * ◆ **شرطٌ سابقٌ خارجَ العقدِ الستَّ عشرة** — فمقامُ العقدِ ستَّ عشرةَ ومقامُ
 *   المهامِّ سبعَ عشرة. ولا صرفَ إلى حسابٍ لم يُتحقَّق منه.
 * ◆ **ومَن يُنشئ لا يتحقّق**: قيدٌ في القاعدةِ يمنع أن تجتمع اليدان.
 * ◆ وكلُّ وصولٍ للبياناتِ ببوابةِ المستأجِر — لا استعلامَ خامٍّ في الشاشة.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }
include '../config.php';
require_once __DIR__ . '/../includes/w14_grid.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/../includes/post_contract.php';

enforce_current_page_view_permission($conn, '../main/dashboard.php');

/* بوابةُ المستأجِر — لا استعلامَ خامٍّ على جدولِ مستأجِرٍ في هذه الشاشة */
$gate = ems_tenant_db();

$company_id = intval($_SESSION['user']['company_id'] ?? 0);
$uid = intval($_SESSION['user']['id'] ?? 0);
$msg = '';

$PARTY = array('supplier' => 'مورد', 'employee' => 'موظف', 'client' => 'عميل', 'other' => 'أخرى');

$__pcNew = ems_post_contract($conn, array(
    'action'  => 'chain.beneficiary.create',
    'perm'    => 'can_add',
    'trigger' => 'new_ben',
    'idem'    => array('t' => (string) ($_POST['party_type'] ?? ''), 'r' => intval($_POST['party_ref'] ?? 0),
                       'a' => (string) ($_POST['account_no'] ?? '')),
    'validate' => function (array $in) use ($PARTY) {
        $t = (string) ($in['party_type'] ?? '');
        $r = intval($in['party_ref'] ?? 0);
        $n = trim((string) ($in['beneficiary_ar'] ?? ''));
        $c = trim((string) ($in['currency'] ?? ''));
        $a = trim((string) ($in['account_no'] ?? ''));
        $i = trim((string) ($in['iban'] ?? ''));
        if (!isset($PARTY[$t])) { return array('ok' => false, 'msg' => 'نوع الطرف محكوم من قائمة مغلقة (422)'); }
        if ($r <= 0) { return array('ok' => false, 'msg' => 'مرجع الطرف إلزامي (422)'); }
        if (mb_strlen($n) < 3) { return array('ok' => false, 'msg' => 'اسم المستفيد إلزامي (422)'); }
        if (mb_strlen($c) < 3) { return array('ok' => false, 'msg' => 'لا حساب بلا عملة (422)'); }
        if ($a === '' && $i === '') { return array('ok' => false, 'msg' => 'لا مستفيد بلا رقم حساب أو آيبان (422)'); }
        return array('ok' => true, 'data' => array('party_type' => $t, 'party_ref' => $r,
            'beneficiary_ar' => $n, 'currency' => $c, 'account_no' => $a, 'iban' => $i,
            'bank_name' => trim((string) ($in['bank_name'] ?? ''))));
    },
));
if (!$__pcNew['ok'] && $__pcNew['msg'] !== '') { $msg = $__pcNew['msg']; }
if ($__pcNew['replay'])                         { $msg = $__pcNew['msg']; }
if ($__pcNew['run'] && $__pcNew['ok']) {
    $d = $__pcNew['data'];
    try {
        $newId = $gate->insert('tre_beneficiaries', array(
            'party_type'     => $d['party_type'],
            'party_ref'      => $d['party_ref'],
            'beneficiary_ar' => $d['beneficiary_ar'],
            'bank_name'      => $d['bank_name'],
            'iban'           => $d['iban'],
            'account_no'     => $d['account_no'],
            'currency'       => $d['currency'],
            'created_by'     => $uid,
        ));
        $msg = '✅ سجل المستفيد — ويبقى غير متحقق حتى يعتمده غير منشئه (201)';
        ems_pc_idem_mark($conn, $__pcNew['idem'], $__pcNew['code'], 'tre_beneficiaries#' . (int) $newId);
    } catch (\Throwable $e) {
        $msg = (stripos($e->getMessage(), 'duplicate') !== false)
             ? '❌ مسجل سلفا بالحساب نفسه — عطالة (200)'
             : '❌ تعذر التسجيل: ' . $e->getMessage();
    }
}

$__pcVer = ems_post_contract($conn, array(
    'action'  => 'chain.beneficiary.verify',
    'perm'    => 'can_edit',
    'trigger' => 'verify_ben',
    'idem'    => array('id' => intval($_POST['verify_ben'] ?? 0)),
    'validate' => function (array $in) {
        $id = intval($in['verify_ben'] ?? 0);
        if ($id <= 0) { return array('ok' => false, 'msg' => 'مستفيد غير صالح (422)'); }
        return array('ok' => true, 'data' => array('id' => $id));
    },
));
if (!$__pcVer['ok'] && $__pcVer['msg'] !== '') { $msg = $__pcVer['msg']; }
if ($__pcVer['replay'])                         { $msg = $__pcVer['msg']; }
if ($__pcVer['run'] && $__pcVer['ok']) {
    $id = (int) $__pcVer['data']['id'];
    $row = $gate->selectOne('tre_beneficiaries', array(
        'columns' => array('created_by', 'verified_at'), 'where' => array('id' => $id)));
    if (!$row)                                    { $msg = '❌ المستفيد غير موجود (404)'; }
    elseif ($row['verified_at'] !== null)         { $msg = '❌ متحقق سلفا — عطالة (200)'; }
    elseif ((int) $row['created_by'] === $uid)    { $msg = '❌ **من ينشئ لا يتحقق** — فصل الواجبات لا يختصر (403)'; }
    else {
        $n = $gate->update('tre_beneficiaries',
            array('verified_by' => $uid, 'verified_at' => date('Y-m-d H:i:s')),
            array('id' => $id), '`verified_at` IS NULL');
        if ((int) $n > 0) {
            $msg = '✅ تحقق من الحساب — وصار صالحا للصرف إليه (200)';
            ems_pc_idem_mark($conn, $__pcVer['idem'], $__pcVer['code'], 'tre_beneficiaries#' . $id);
        } else { $msg = '❌ تعذر التحقق — تغيرت الحالة بين القراءة والكتابة (409)'; }
    }
}

$rows = array(); $queueFail = '';
try {
    $rows = $gate->select('tre_beneficiaries', array(
        'orderBy' => '`verified_at` IS NULL DESC, `id` DESC', 'limit' => 300));
} catch (\Throwable $e) { $queueFail = 'تعذر قراءة السجل: ' . $e->getMessage(); }

$page_title = 'سجل المستفيدين والحسابات البنكية';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main" dir="rtl">
  <?php
  $header_icon = 'fa fa-address-book';
  $header_title_html = htmlspecialchars('سجل المستفيدين والحسابات البنكية', ENT_QUOTES, 'UTF-8');
  ob_start(); ?><span class="badge"><?= $queueFail === '' ? count($rows) : '—' ?> مستفيدا</span><?php
  $header_actions = array(array('raw' => trim((string) ob_get_clean())));
  $header_back = false;
  include __DIR__ . '/../includes/page_header.php'; ?>
    <!-- سجلُّ حقولِ الورقةِ بحبّتِه — يُضاف بجانبِ ما بُني لا بدلًا منه،
         فالمبنيُّ له أفعالُه والورقةُ تطلب السجلَّ بحقولِه كلِّها -->
    <div class="card"><div class="card-header"><h5><i class="fa fa-clipboard-list"></i> سجل حقول الورقة</h5></div>
    <div class="card-body"><div class="table-container">
        <?php /* GUIDE_COLS:govui_field_close
             الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
             والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
             ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
        $GUIDE_COLS = array(
            'معرف المستفيد' => 'g202',
            'اسم المستفيد' => 'g203',
            'نوع المستفيد' => 'g204',
            'رقم الحساب/IBAN' => 'g205',
            'البنك' => 'g206',
            'وثيقة التحقق' => 'g207',
            'تاريخ التحقق' => 'g208',
            'محقق مستقل' => 'g209',
            'تغيير حساب معلق؟' => 'g210',
            'حالة التحقق' => 'g211',
            'المنشئ' => 'g212',
            'تاريخ الإنشاء' => 'g213',
            'حالة البيانات' => 'g214',
            'مرجع المصدر' => 'g215',
        );
        $D = array();
        $__gridRows = ems_w14_guide_rows('tre_beneficiary');
        echo ems_w14_grid('emsList_tre_beneficiary', $GUIDE_COLS, $__gridRows, $D, 'لا سطر مسجل بعد في سجل المستفيدين والتحقق'); /* /GUIDE_COLS */ ?>
    </div></div></div>
    <?php 
  echo ems_states_bundle('لا مستفيد مسجل بعد',
      'الحساب يسجل ثم يتحقق منه غير منشئه — ولا صرف إلى حساب غير متحقق');
  ?>
  <p class="text-muted">شرط سابق للموجة السابعة — <strong>من ينشئ لا يتحقق</strong>،
     وقيد في القاعدة يمنع اجتماع اليدين.</p>
  <?php if ($msg !== ''): ?>
    <div class="alert <?= (mb_strpos($msg, '✅') !== false ? 'alert-success' : 'alert-danger') ?>">
      <?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>
  <?php if ($queueFail !== ''): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($queueFail, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>

  <form method="post" class="row g-2 mb-3">
    <?php echo csrf_field(); ?>
    <div class="col-auto"><label class="form-label" for="tb_type">نوع الطرف</label>
      <select class="form-control form-control-sm" name="party_type" id="tb_type" required>
        <?php foreach ($PARTY as $k => $v): ?><option value="<?= htmlspecialchars($k, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($v, ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?>
      </select></div>
    <div class="col-auto"><label class="form-label" for="tb_ref">مرجع الطرف</label>
      <input class="form-control form-control-sm" type="number" min="1" required name="party_ref" id="tb_ref"></div>
    <div class="col-auto"><label class="form-label" for="tb_name">اسم المستفيد</label>
      <input class="form-control form-control-sm" type="text" maxlength="160" required name="beneficiary_ar" id="tb_name"></div>
    <div class="col-auto"><label class="form-label" for="tb_bank">البنك</label>
      <input class="form-control form-control-sm" type="text" maxlength="120" name="bank_name" id="tb_bank"></div>
    <div class="col-auto"><label class="form-label" for="tb_iban">الآيبان</label>
      <input class="form-control form-control-sm" type="text" maxlength="64" name="iban" id="tb_iban"></div>
    <div class="col-auto"><label class="form-label" for="tb_acc">رقم الحساب</label>
      <input class="form-control form-control-sm" type="text" maxlength="64" name="account_no" id="tb_acc"></div>
    <div class="col-auto"><label class="form-label" for="tb_cur">العملة</label>
      <input class="form-control form-control-sm" type="text" maxlength="8" required name="currency" id="tb_cur"></div>
    <div class="col-auto align-self-end">
      <button class="action-btn" type="submit" name="new_ben" value="1"><i class="fa fa-plus"></i> تسجيل مستفيد</button></div>
  </form>

  <table class="table table-striped" data-no-dt>
    <thead><tr>
      <th>الإجراء</th><th>الطرف</th><th>المستفيد</th><th>البنك</th><th>الآيبان</th>
      <th>رقم الحساب</th><th>العملة</th><th>سجله</th><th>تحقق منه</th>
      <th class="ems-gov-th none" data-gov="entity" data-slice="1">الكيان</th>
      <th class="ems-gov-th none" data-gov="creator" data-slice="1">المنشئ</th>
      <th class="ems-gov-th none" data-gov="currency" data-slice="3">العملة</th>
    </tr></thead>
    <tbody>
    <?php if (empty($rows)): ?>
      <tr><td colspan="9" class="text-center text-muted">لا مستفيد مسجل بعد</td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $r): $id = (int) $r['id']; ?>
      <tr>
        <td><?php if ($r['verified_at'] === null): ?>
          <form method="post"><?php echo csrf_field(); ?>
            <input type="hidden" name="verify_ben" value="<?= $id ?>">
            <button class="action-btn" type="submit"><i class="fa fa-shield-halved"></i> تحقق</button></form>
        <?php else: ?><span class="badge">متحقق</span><?php endif; ?></td>
        <td><?= htmlspecialchars(($PARTY[$r['party_type']] ?? (string) $r['party_type']) . ' #' . (int) $r['party_ref'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars((string) $r['beneficiary_ar'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars((string) $r['bank_name'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars((string) $r['iban'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars((string) $r['account_no'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars((string) $r['currency'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= (int) $r['created_by'] ?></td>
        <td><?= (int) $r['verified_by'] ?: '—' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
