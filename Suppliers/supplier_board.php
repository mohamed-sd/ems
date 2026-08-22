<?php
/**
 * Suppliers/supplier_board.php — لوحة إدارة الموردين
 *   الورقة م23 · INJ-SUP-ALIGN-01 — قدرةٌ ثبت غيابُها
 * ───────────────────────────────────────────────────────────────────────────
 * ◆ **لوحةُ قراءةٍ لا سجلّ**: «لوحةٌ مشتقّةٌ من السجلاتِ القائمةِ ولا جدولَ جديدًا
 *   لها». فكلُّ رقمٍ هنا **يُحسب لحظةَ العرضِ من مصدرِه**، ولا يُخزَّن مجمَّعًا
 *   فيتقادم ويكذب.
 * ◆ **ولا رقمَ بلا تعمّق**: بطاقةُ المؤشرِ المركزيةُ تفرض وجهةً لكلِّ رقم —
 *   «ورقمٌ لا يُتعمَّق فيه لا يُقرَّر عليه».
 * ◆ **ولا فعلَ من اللوحة**: لا كتابةَ ولا اعتماد — الفعلُ في شاشةِ مالكِه،
 *   واللوحةُ تدلُّ عليه. فلا ازدواجَ في مالكِ القدرة.
 * ◆ **ولا خلطَ لعملتين في رقمٍ واحد**: مبالغُ الجزاءاتِ تُعرض مفصّلةً بعملتِها.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';

enforce_current_page_view_permission($conn, '../main/dashboard.php');

/* بوابةُ المستأجِر — لا استعلامَ خامٍّ على جدولِ مستأجِرٍ في هذه الشاشة */
$gate = ems_tenant_db();

/* ◆ **التاريخُ بالمُوحِّدِ لا باستدعاءٍ متفرّق** — سقّاطةُ VT-07 ترصده */
require_once __DIR__ . '/../includes/date_format.php';
$today = ems_fmt_now('date');
$readFail = '';
$kSup = $kCon = $kVioOpen = $kVioAppr = $kSetDraft = $kEvalDue = $kCap = 0;
$vioRows = array(); $penalties = array(); $topSup = array();

try {
    $kSup      = (int) $gate->count('suppliers', array('where' => array('status' => 1)));
    $kCon      = (int) $gate->count('supplierscontracts', array('where' => array('status' => 1)));
    $kVioOpen  = (int) $gate->count('sup_violations', array('where' => array('state' => 'recorded')));
    $kVioAppr  = (int) $gate->count('sup_violations', array('where' => array('state' => 'approved')));
    $kSetDraft = (int) $gate->count('settlements',
        array('where' => array('party_type' => 'supplier', 'state' => 'draft')));
    $kEvalDue  = (int) $gate->count('supplier_evaluations', array('where' => array('state' => 'draft')));
    $kCap      = (int) $gate->count('supplier_capacity', array('where' => array('state' => 'active')));

    /* المخالفاتُ المفتوحةُ — صفُّ الانتظارِ الفعليّ، والفعلُ في شاشتِه */
    $vioRows = $gate->select('sup_violations', array(
        'whereRaw' => "`state` = 'recorded'",
        'orderBy'  => '`occurred_on` DESC, `id` DESC', 'limit' => 15));

    /* ◆ الجزاءاتُ المعتمدةُ **مفصّلةً بالعملة** — ولا جمعَ لعملتين في رقم */
    $penRows = $gate->select('sup_violations', array(
        'whereRaw' => "`state` = 'approved' AND `penalty_amount` > 0", 'limit' => 500));
    foreach ($penRows as $p) {
        $c = (string) ($p['currency'] ?? '');
        if ($c === '') { $c = 'بلا عملةٍ معلنة'; }
        if (!isset($penalties[$c])) { $penalties[$c] = array('n' => 0, 'sum' => 0.0); }
        $penalties[$c]['n']++;
        $penalties[$c]['sum'] += (float) $p['penalty_amount'];
    }

    /* الموردون الأكثرُ مخالفاتٍ — إشارةٌ للمتابعةِ لا حكمًا */
    $allVio = $gate->select('sup_violations', array(
        'columns' => array('supplier_id', 'state'), 'limit' => 2000));
    $tally = array();
    foreach ($allVio as $v) {
        $s = (int) $v['supplier_id'];
        if (!isset($tally[$s])) { $tally[$s] = array('n' => 0, 'appr' => 0); }
        $tally[$s]['n']++;
        if ($v['state'] === 'approved') { $tally[$s]['appr']++; }
    }
    arsort($tally);
    $topSup = array_slice($tally, 0, 10, true);
} catch (\Throwable $e) {
    $readFail = 'تعذّرت قراءةُ المصادر: ' . $e->getMessage();
}

$page_title = 'لوحة إدارة الموردين';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
require_once __DIR__ . '/../includes/kpi_card.php';
?>
<div class="main" dir="rtl">
  <?php
  $header_icon = 'fa fa-chart-line';
  $header_title_html = htmlspecialchars('لوحة إدارة الموردين', ENT_QUOTES, 'UTF-8');
  $header_actions = array();
  $header_back = false;
  include __DIR__ . '/../includes/page_header.php';
  echo ems_states_bundle('لا بياناتِ مورّدين في نطاقِك بعد',
      'اللوحةُ مشتقّةٌ من السجلاتِ القائمة — تُحسب لحظةَ العرضِ ولا تُخزَّن مجمَّعة');
  ?>
  <p class="text-muted">الورقة م٢٣ · لوحةُ قراءةٍ مشتقّة — <strong>لا جدولَ جديدًا لها ولا فعلَ منها</strong>؛
     كلُّ رقمٍ يُحسب من مصدرِه لحظةَ العرض، ووجهةُ التعمّقِ إلزاميّةٌ لكلِّ رقم.</p>
  <?php if ($readFail !== ''): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($readFail, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>

  <div class="ems-grid">
    <?php
    $kpis = array(
        array('موردون فاعلون', $kSup, 'مورّد', 'neutral', 'suppliers.php'),
        array('عقود مورّدين سارية', $kCon, 'عقد', 'neutral', 'supplierscontracts.php'),
        array('مخالفات تنتظر الاعتماد', $kVioOpen, 'مخالفة', $kVioOpen > 0 ? 'warn' : 'ok', 'supplier_violations.php'),
        array('مخالفات معتمَدة', $kVioAppr, 'مخالفة', $kVioAppr > 0 ? 'warn' : 'ok', 'supplier_violations.php'),
        array('تسويات مسودّة', $kSetDraft, 'تسوية', $kSetDraft > 0 ? 'warn' : 'ok', 'settlements.php'),
        array('تقييمات لم تُقرَّر', $kEvalDue, 'تقييم', $kEvalDue > 0 ? 'warn' : 'ok', 'supplier_evaluation.php'),
        array('طاقاتٌ معلَنةٌ سارية', $kCap, 'إعلان', 'neutral', 'supplier_capacity.php'),
    );
    foreach ($kpis as $k) {
        echo ems_kpi_card(array(
            'title'  => $k[0],
            'value'  => (string) $k[1],
            'unit'   => $k[2],
            'period' => 'لحظي (' . $today . ')',
            'status' => $k[3],
            'drill'  => $k[4],
            'class'  => 'ems-col-4',
        ));
    }
    ?>
  </div>

  <h6 class="mt-4">الجزاءات المعتمَدة — مفصّلةً بالعملة</h6>
  <p class="text-muted">لا جمعَ لعملتين في رقمٍ واحد؛ ولا يُنفَّذ الجزاءُ هنا — أثرُه في
     <a href="settlements.php">التسويات</a>.</p>
  <table class="table table-striped" data-no-dt>
    <thead><tr><th>العملة</th><th>عدد المخالفات</th><th>إجمالي الجزاء</th>
      <th class="ems-gov-th none" data-gov="currency" data-slice="3">العملة</th></tr></thead>
    <tbody>
      <?php if (empty($penalties)): ?>
        <tr><td colspan="3" class="text-center text-muted">لا جزاءَ معتمَدًا بمبلغٍ بعد</td></tr>
      <?php endif; ?>
      <?php foreach ($penalties as $cur => $agg): ?>
        <tr><td><?= htmlspecialchars((string) $cur, ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= (int) $agg['n'] ?></td>
            <td><?= number_format((float) $agg['sum'], 2) ?></td></tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <h6 class="mt-4">مخالفات تنتظر الاعتماد</h6>
  <table class="table table-striped" data-no-dt>
    <thead><tr><th>الرقم</th><th>المورّد</th><th>النوع</th><th>الوقوع</th><th>الجزاء</th><th>التعمّق</th>
      <th class="ems-gov-th none" data-gov="entity" data-slice="1">الكيان</th></tr></thead>
    <tbody>
      <?php if (empty($vioRows)): ?>
        <tr><td colspan="6" class="text-center text-muted">لا مخالفةَ تنتظر الاعتماد</td></tr>
      <?php endif; ?>
      <?php foreach ($vioRows as $v): ?>
        <tr>
          <td><?= htmlspecialchars((string) $v['violation_no'], ENT_QUOTES, 'UTF-8') ?></td>
          <td>#<?= (int) $v['supplier_id'] ?></td>
          <td><?= htmlspecialchars((string) $v['violation_kind'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars((string) $v['occurred_on'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= number_format((float) $v['penalty_amount'], 2) ?> <?= htmlspecialchars((string) $v['currency'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><a class="action-btn" href="supplier_violations.php"><i class="fa fa-arrow-left"></i> فتح السجل</a></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <h6 class="mt-4">الموردون الأكثر مخالفاتٍ مسجَّلة</h6>
  <p class="text-muted">إشارةٌ للمتابعةِ لا حكمًا — والمرصودُ غيرُ المعتمَد.</p>
  <table class="table table-striped" data-no-dt>
    <thead><tr><th>المورّد</th><th>مخالفات مسجَّلة</th><th>منها معتمَدة</th><th>التعمّق</th>
      <th class="ems-gov-th none" data-gov="entity" data-slice="1">الكيان</th></tr></thead>
    <tbody>
      <?php if (empty($topSup)): ?>
        <tr><td colspan="4" class="text-center text-muted">لا مخالفةَ مسجَّلةٌ بعد</td></tr>
      <?php endif; ?>
      <?php foreach ($topSup as $sid => $agg): ?>
        <tr><td>#<?= (int) $sid ?></td>
            <td><?= (int) $agg['n'] ?></td>
            <td><?= (int) $agg['appr'] ?></td>
            <td><a class="action-btn" href="supplier_profile.php?id=<?= (int) $sid ?>"><i class="fa fa-id-card"></i> ملفُّ المورّد</a></td></tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
