<?php
/**
 * Financing/operation_profile.php — ملفُّ عملية التمويل (★ · update0007-ب F1)
 * ───────────────────────────────────────────────────────────────────────────
 * الشاشةُ الأمُّ بتبويباتها الستة (NAV-01 §9.13) — والمجالُ مقيَّدٌ (FIN-01 §1.1):
 * لا تُفتح إلا لمن له منحُ ownership.* الفردي (ownership_access_grants).
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';

$company_id = intval($_SESSION['user']['company_id'] ?? 0);
$uid = intval($_SESSION['user']['id'] ?? 0);
$role = intval($_SESSION['user']['role'] ?? 0);

/* بوابةُ المجال المقيَّد — منحٌ فرديٌّ أو دورُ التمويل (26) */
$granted = ($role === 26) || !empty($_SESSION['user']['is_super_admin']);
if (!$granted) {
    $g = mysqli_query($conn, "SELECT 1 FROM ownership_access_grants WHERE person_id = $uid AND state = 'active' LIMIT 1");
    $granted = $g && mysqli_num_rows($g) > 0;
}
if (!$granted) { ems_gov_flash_redirect('../main/dashboard.php', 'المجالُ المقيَّد — الوصولُ بمنحٍ فرديٍّ لا بدور (FIN-01 §1.1) ❌', 'GOV-PERM-403', 'اطلب المنحةَ من مدير الصلاحيات إن كانت ضمن عملك'); }

$op_id = intval($_GET['id'] ?? 0);
$tab   = preg_replace('/[^a-z]/', '', $_GET['tab'] ?? 'terms');
if (!in_array($tab, array('terms','assets','shares','installments','ledger','docs'), true)) $tab = 'terms';

$op = null;
$r = mysqli_query($conn, "SELECT o.*, le.legal_name AS financier
                          FROM financing_operations o
                          LEFT JOIN legal_entities le ON le.entity_id = o.financier_entity_id
                          WHERE o.op_id = $op_id AND o.company_id = $company_id");
if ($r) $op = mysqli_fetch_assoc($r);

$page_title = 'ملف عملية التمويل';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main" dir="rtl">
  <?php if (!$op): ?>
    <?php
    /* AS-04/AS-05 (UXR-01): فرعُ «غيرُ موجودة» يحمل الرأسَ الموحَّدَ أيضًا —
       شاشةُ الخطأِ شاشةٌ، ولا تُترك بلا عنوانٍ ولا سطرِ سياقٍ ولا طريقِ رجوع. */
    $header_icon = 'fa fa-hand-holding-usd';
    $header_title_html = htmlspecialchars('ملف عملية التمويل — غيرُ موجودة', ENT_QUOTES, 'UTF-8');
    $header_actions = array();
    $header_back = array('href' => 'financing_board.php', 'label' => 'رجوع');
    include __DIR__ . '/../includes/page_header.php';
    ?>
<?php require_once __DIR__ . '/../includes/entity_tabs.php'; echo ems_entity_tabs('financing', 'نظرةٌ عامة'); ?>
    <div class="alert alert-warning">عمليةٌ غيرُ موجودةٍ — <a href="financing_board.php">العودةُ للوحة</a></div>
  <?php else: ?>
  <?php
/* AS-04/AS-05 (UXR-01): رأسُ الصفحةِ الموحَّدُ بدلَ الرأسِ اليدويّ —
   شريطُ أفعالٍ واحدٌ وسطرُ سياقٍ ومنفذُ بلاغٍ من مصدرٍ واحد. */
$header_icon = 'fa fa-hand-holding-usd';
$header_title_html = htmlspecialchars('عملية ' . (htmlspecialchars($op['op_code'], ENT_QUOTES, 'UTF-8')) . ' — ' . (htmlspecialchars($op['financier'] ?: 'ممول #' . $op['financier_entity_id'], ENT_QUOTES, 'UTF-8')), ENT_QUOTES, 'UTF-8');
ob_start(); ?><span class="badge" style="background:#6610f2"><?= htmlspecialchars($op['state'], ENT_QUOTES, 'UTF-8') ?></span><?php
$header_actions = array(array('raw' => trim((string) ob_get_clean())));
$header_back = false;
include __DIR__ . '/../includes/page_header.php';
?>
  <?php $ff_op_id = $op_id; $ff_active = $tab === 'terms' ? 'terms' : $tab;
        include __DIR__ . '/../includes/financing_file_tabs.php'; ?>

  <?php if ($tab === 'terms'): ?>
    <table class="table table-sm" data-no-dt style="max-width:720px">
      <tr><th>النموذج</th><td><?= htmlspecialchars($op['model_code'], ENT_QUOTES, 'UTF-8') ?></td>
          <th>العملة</th><td><?= htmlspecialchars($op['currency'], ENT_QUOTES, 'UTF-8') ?></td></tr>
      <tr><th>رأسُ المال</th><td><?= number_format(floatval($op['capital']), 2) ?></td>
          <th>قيمةُ الشراء</th><td><?= number_format(floatval($op['purchase_value']), 2) ?></td></tr>
      <tr><th>الدفعةُ الأولى</th><td><?= number_format(floatval($op['down_payment']), 2) ?></td>
          <th>هامشُ الربح</th><td><?= number_format(floatval($op['profit_amount']), 2) ?> (<?= floatval($op['profit_rate']) ?>٪)</td></tr>
      <tr><th>الأقساط</th><td><?= intval($op['installments_no']) ?> × <?= number_format(floatval($op['installment_amount']), 2) ?></td>
          <th>الرصيدُ القائم</th><td><strong><?= number_format(floatval($op['outstanding_balance']), 2) ?></strong></td></tr>
      <tr><th>APR</th><td><?= floatval($op['apr']) ?>٪</td>
          <th>الاستحقاقُ النهائي</th><td><?= htmlspecialchars($op['maturity_date'], ENT_QUOTES, 'UTF-8') ?></td></tr>
    </table>

  <?php elseif ($tab === 'assets'): ?>
    <table class="table table-striped" data-no-dt>
      <thead><tr><th>الأصل</th><th>النوع</th><th>حصةُ الممول ٪</th><th>من</th><th>إلى</th></tr></thead>
      <tbody>
      <?php $r2 = mysqli_query($conn, "SELECT s.*, e.name eq_name FROM asset_ownership_shares s
                                       LEFT JOIN equipments e ON e.id = s.asset_id AND s.asset_kind = 'equipment'
                                       WHERE s.op_id = $op_id ORDER BY s.asset_id, s.valid_from");
      $any = false;
      if ($r2) while ($x = mysqli_fetch_assoc($r2)) { $any = true; ?>
        <tr><td><?= htmlspecialchars($x['eq_name'] ?: ($x['asset_kind'] . ' #' . $x['asset_id']), ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($x['asset_kind'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= floatval($x['approved_percent'] ?: $x['percent']) ?>٪</td>
            <td><?= htmlspecialchars($x['valid_from'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($x['valid_to'] ?: 'ساري', ENT_QUOTES, 'UTF-8') ?></td></tr>
      <?php } if (!$any) echo '<tr><td colspan="5" class="text-center text-muted">لا أعيانَ مسجَّلةً لهذه العملية</td></tr>'; ?>
      </tbody>
    </table>

  <?php elseif ($tab === 'shares'): ?>
    <p class="text-muted">الحصصُ عبر الزمن — Σ لكل أصلٍ في كل لحظةٍ = 100٪ (شاهدُ UAT §11-⑨)</p>
    <table class="table table-striped" data-no-dt>
      <thead><tr><th>الأصل</th><th>الفترة</th><th>المسجَّل ٪</th><th>المصحَّح ٪</th><th>المعتمد ٪</th><th>سببُ التصحيح</th></tr></thead>
      <tbody>
      <?php $r2 = mysqli_query($conn, "SELECT s.*, e.name eq_name FROM asset_ownership_shares s
                                       LEFT JOIN equipments e ON e.id = s.asset_id AND s.asset_kind = 'equipment'
                                       WHERE s.op_id = $op_id ORDER BY s.valid_from");
      $any = false;
      if ($r2) while ($x = mysqli_fetch_assoc($r2)) { $any = true; ?>
        <tr><td><?= htmlspecialchars($x['eq_name'] ?: '#' . $x['asset_id'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($x['valid_from'] . ' ← ' . ($x['valid_to'] ?: 'الآن'), ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= floatval($x['recorded_percent'] ?: $x['percent']) ?></td>
            <td><?= $x['corrected_percent'] !== null ? floatval($x['corrected_percent']) : '—' ?></td>
            <td><strong><?= floatval($x['approved_percent'] ?: $x['percent']) ?></strong></td>
            <td><?= htmlspecialchars($x['correction_reason'] ?: '—', ENT_QUOTES, 'UTF-8') ?></td></tr>
      <?php } if (!$any) echo '<tr><td colspan="6" class="text-center text-muted">لا حصصَ بعد</td></tr>'; ?>
      </tbody>
    </table>

  <?php elseif ($tab === 'installments'): ?>
    <table class="table table-striped" data-no-dt>
      <thead><tr><th>#</th><th>الاستحقاق</th><th>أصل</th><th>ربح</th><th>الإجمالي</th><th>الحالة</th><th>السداد</th><th>مرجعُه</th>
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
      <?php $r2 = mysqli_query($conn, "SELECT seq_no, due_date, amount_principal, amount_profit, amount_total,
                                              currency, paid_date, payment_ref, state
                                       FROM financing_installments WHERE op_id = $op_id ORDER BY seq_no");
      $any = false;
      if ($r2) while ($x = mysqli_fetch_assoc($r2)) { $any = true;
          $clr = array('paid' => '#198754', 'overdue' => '#dc3545', 'due' => '#fd7e14');
      ?>
        <tr><td><?= intval($x['seq_no']) ?></td>
            <td><?= htmlspecialchars($x['due_date'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= number_format(floatval($x['amount_principal']), 2) ?></td>
            <td><?= number_format(floatval($x['amount_profit']), 2) ?></td>
            <td><strong><?= number_format(floatval($x['amount_total']), 2) ?></strong> <?= htmlspecialchars($x['currency'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><span class="badge" style="background:<?= $clr[$x['state']] ?? '#6c757d' ?>"><?= htmlspecialchars($x['state'], ENT_QUOTES, 'UTF-8') ?></span></td>
            <td><?= htmlspecialchars($x['paid_date'] ?: '—', ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($x['payment_ref'] ?: '—', ENT_QUOTES, 'UTF-8') ?></td></tr>
      <?php } if (!$any) echo '<tr><td colspan="8" class="text-center text-muted">لا أقساطَ بعد</td></tr>'; ?>
      </tbody>
    </table>
    <p><a class="action-btn" href="installments.php?op=<?= $op_id ?>"><i class="fa fa-calendar-check"></i> شاشةُ السداد</a></p>

  <?php elseif ($tab === 'ledger'): ?>
    <p class="text-muted">حركةُ العملية في دفتر الأحداث — أقساطٌ مستحقةٌ ومسددةٌ وتعديلات</p>
    <table class="table table-striped" data-no-dt>
      <thead><tr><th>الحدث</th><th>المبلغ</th><th>التاريخ</th><th>المرجع</th></tr></thead>
      <tbody>
      <?php $r2 = mysqli_query($conn, "SELECT event_key, amount, currency, occurred_at, source_ref
                                       FROM ems_business_events
                                       WHERE company_id = $company_id AND source_ref = 'FINOP-$op_id'
                                          OR (entity_type = 'financing_operation' AND entity_id = $op_id)
                                       ORDER BY occurred_at DESC LIMIT 50");
      $any = false;
      if ($r2) while ($x = mysqli_fetch_assoc($r2)) { $any = true; ?>
        <tr><td><?= htmlspecialchars($x['event_key'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= number_format(floatval($x['amount']), 2) ?> <?= htmlspecialchars($x['currency'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($x['occurred_at'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($x['source_ref'], ENT_QUOTES, 'UTF-8') ?></td></tr>
      <?php } if (!$any) echo '<tr><td colspan="4" class="text-center text-muted">لا حركةَ مقيَّدةً بعد</td></tr>'; ?>
      </tbody>
    </table>

  <?php else: /* docs */ ?>
    <table class="table table-sm" data-no-dt style="max-width:640px">
      <tr><th>مرجعُ العقد</th><td><?= htmlspecialchars($op['contract_ref'] ?: '—', ENT_QUOTES, 'UTF-8') ?></td></tr>
      <tr><th>تاريخُ التوقيع</th><td><?= htmlspecialchars($op['signed_date'] ?: '—', ENT_QUOTES, 'UTF-8') ?></td></tr>
      <tr><th>أُنشئت</th><td><?= htmlspecialchars($op['created_at'], ENT_QUOTES, 'UTF-8') ?> · بواسطة #<?= intval($op['created_by']) ?></td></tr>
      <tr><th>آخرُ تحديث</th><td><?= htmlspecialchars($op['updated_at'], ENT_QUOTES, 'UTF-8') ?></td></tr>
    </table>
  <?php endif; ?>
  <?php endif; ?>
</div>
