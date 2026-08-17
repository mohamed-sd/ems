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
require_once __DIR__ . '/../includes/profile_kit.php';   // عُدّةُ بطاقةِ الكِيان — التأليفُ بديلُ النسخ
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-profile" dir="rtl">
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
$header_title_html = htmlspecialchars('ملف عملية التمويل', ENT_QUOTES, 'UTF-8');
$header_actions = array();
$header_back = array('href' => 'financing_board.php', 'label' => 'رجوع');
/* شريطُ تبويباتِ الملفِّ يُصَفُّ **قبل** الرأسِ ليصرفه الرأسُ تحتَ الشريطِ
   الأصفرِ مباشرةً — كان يُضمَّن بعده فيُطبع أسفلَ لوحِ الهويةِ لا فوقَه. */
$ff_op_id = $op_id; $ff_active = $tab === 'terms' ? 'terms' : $tab;
include __DIR__ . '/../includes/financing_file_tabs.php';
include __DIR__ . '/../includes/page_header.php';
// UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
echo ems_states_bundle('لا بياناتٍ في هذا التبويبِ من ملفِّ العملية', 'انتقلْ إلى تبويبٍ آخرَ أو سجِّلْ أعيانَ العمليةِ وأقساطَها لتظهرَ هنا');

/* ══ لوحُ الهوية ═══════════════════════════════════════════════════════════
   ◆ كانت هويةُ العمليةِ محشورةً في **عنوانِ الرأس** («عملية X — الممول Y»)،
     وحالتُها **زرًّا في شريطِ الأفعال** (`$header_actions` بـ`raw`): شارةٌ
     تتظاهر بأنها فعلٌ يُضغط. صارت لوحَ هويةٍ كأخواتِها: الاسمُ اسمًا،
     والحالةُ شارةً بنغمةٍ معلَنة، والمالُ حقائقَ معنونة.
   ◆ ونغمةُ الحالةِ من معجمِ المكوّنِ الثماني — لا من ألوانٍ مثبَّتةٍ في كتلةِ
     `<style>` كانت تحمل لونَينِ عاريَينِ خارجَ الرموز (بنفسجيٌّ وبرتقاليٌّ حرفيّان). */
echo ems_profile_hero(array(
    'name'   => 'عملية ' . $op['op_code'],
    'icon'   => 'fas fa-hand-holding-dollar',
    'status' => array(
        'text' => $op['state'],
        /* التسعُ حالاتٍ كما في `financing_operations.state` حرفًا — لا خمسٌ
           منها ويسقط الباقي إلى `neutral` صامتًا. */
        'tone' => ems_profile_map($op['state'], array(
            'draft'       => 'neutral',
            'negotiation' => 'warn',
            'approved'    => 'info',
            'signed'      => 'info',
            'active'      => 'ok',
            'paying'      => 'ok',
            'settled'     => 'info',
            'closed'      => 'neutral',
            'defaulted'   => 'danger',
        )),
    ),
    'chips'  => array(
        array('text' => $op['financier'] ?: 'ممول #' . $op['financier_entity_id'], 'icon' => 'fas fa-building-columns'),
        array('text' => $op['model_code'], 'icon' => 'fas fa-scale-balanced', 'mono' => true),
        array('text' => $op['currency'], 'icon' => 'fas fa-coins', 'mono' => true),
    ),
    'facts'  => array(
        array('label' => 'الرصيدُ القائم',    'value' => number_format(floatval($op['outstanding_balance']), 2) . ' ' . $op['currency']),
        array('label' => 'رأسُ المال',        'value' => number_format(floatval($op['capital']), 2)),
        array('label' => 'الأقساط',           'value' => intval($op['installments_no']) . ' × ' . number_format(floatval($op['installment_amount']), 2)),
        array('label' => 'الاستحقاقُ النهائي', 'value' => $op['maturity_date']),
    ),
));

/* ══ شريطُ الحصيلة ═════════════════════════════════════════════════════════
   ◆ كانت هذه البطاقةُ **وحدَها من بين الثماني بلا شريطِ مؤشرات**: أرقامُها
     كلُّها داخلَ تبويبِ «الشروط» أو مبثوثةٌ في جدولِ الأقساط، فحالُ العمليةِ
     لا يُعرف إلا بفتحِ تبويبٍ وعدِّ صفوف.
   ◆ والأعدادُ من `financing_installments` نفسِه الذي يقرؤه تبويبُ الأقساط —
     استعلامٌ تجميعيٌّ واحدٌ لا حلقةٌ على الصفوف. */
$fin_paid = $fin_overdue = $fin_due = 0; $fin_remaining = 0.0;
$agg = mysqli_query($conn, "SELECT
        SUM(state = 'paid')                          AS paid_n,
        SUM(state = 'overdue')                       AS overdue_n,
        SUM(state = 'due')                           AS due_n,
        SUM(CASE WHEN state <> 'paid' THEN amount_total ELSE 0 END) AS remaining
      FROM financing_installments WHERE op_id = $op_id");
if ($agg && ($a = mysqli_fetch_assoc($agg))) {
    $fin_paid      = (int) $a['paid_n'];
    $fin_overdue   = (int) $a['overdue_n'];
    $fin_due       = (int) $a['due_n'];
    $fin_remaining = (float) $a['remaining'];
}
echo ems_profile_stats(array(
    array('value' => number_format(floatval($op['outstanding_balance']), 2),
          'label' => 'الرصيدُ القائم', 'unit' => $op['currency'], 'variant' => 'money'),
    array('value' => number_format($fin_remaining, 2),
          'label' => 'غيرُ المسدَّد من الأقساط', 'unit' => $op['currency'], 'variant' => 'money'),
    array('value' => $fin_paid,    'label' => 'أقساطٌ مسدَّدة', 'tone' => $fin_paid > 0 ? 'ok' : 'muted'),
    array('value' => $fin_due,     'label' => 'مستحقةٌ الآن',  'tone' => $fin_due > 0 ? 'warn' : 'muted'),
    array('value' => $fin_overdue, 'label' => 'متأخرة',       'tone' => $fin_overdue > 0 ? 'danger' : 'muted'),
    array('value' => intval($op['installments_no']), 'label' => 'إجمالي الأقساط'),
));
?>
  <?php if ($tab === 'terms'): ?>
    <?php
    /* كان جدولًا برأسَينِ وقيمتَينِ في الصفِّ الواحد (`<th><td><th><td>`) —
       بنيةُ جدولٍ لبياناتٍ ليست جدولًا: عشرُ حقائقَ لكِيانٍ واحد. صارت شبكةَ
       حقائقِ المكوّنِ نفسِها، فتنساب على الضيّقِ ولا تُقرأ زجزاجًا. */
    echo ems_profile_section_open(array('title' => 'شروطُ العملية', 'icon' => 'fas fa-file-contract'));
    echo ems_profile_facts(array(
        array('label' => 'النموذج',           'value' => $op['model_code']),
        array('label' => 'العملة',            'value' => $op['currency']),
        array('label' => 'رأسُ المال',         'value' => number_format(floatval($op['capital']), 2)),
        array('label' => 'قيمةُ الشراء',       'value' => number_format(floatval($op['purchase_value']), 2)),
        array('label' => 'الدفعةُ الأولى',     'value' => number_format(floatval($op['down_payment']), 2)),
        array('label' => 'هامشُ الربح',        'value' => number_format(floatval($op['profit_amount']), 2) . ' (' . floatval($op['profit_rate']) . '٪)'),
        array('label' => 'الأقساط',            'value' => intval($op['installments_no']) . ' × ' . number_format(floatval($op['installment_amount']), 2)),
        array('label' => 'الرصيدُ القائم',     'value' => number_format(floatval($op['outstanding_balance']), 2)),
        array('label' => 'APR',                'value' => floatval($op['apr']) . '٪'),
        array('label' => 'الاستحقاقُ النهائي', 'value' => $op['maturity_date']),
    ), true);
    echo ems_profile_section_close();
    ?>

  <?php elseif ($tab === 'assets'): ?>
    <?php echo ems_profile_section_open(array('title' => 'أعيانُ العملية', 'icon' => 'fas fa-truck-monster')); ?>
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
    <?php echo ems_profile_section_close(); ?>

  <?php elseif ($tab === 'shares'): ?>
    <?php echo ems_profile_section_open(array(
        'title' => 'الحصصُ عبر الزمن',
        'icon'  => 'fas fa-chart-pie',
        'note'  => 'Σ لكل أصلٍ في كل لحظةٍ = 100٪ (شاهدُ UAT §11-⑨)',
    )); ?>
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
    <?php echo ems_profile_section_close(); ?>

  <?php elseif ($tab === 'installments'): ?>
    <?php echo ems_profile_section_open(array('title' => 'جدولُ الأقساط', 'icon' => 'fas fa-calendar-days')); ?>
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
          /* الخمسُ حالاتٍ كما في `financing_installments.state` حرفًا — بنغماتِ
             المكوّنِ لا بأصنافِ لونٍ محليةٍ كانت تحمل هكسًا عاريًا. */
          $instTone = ems_profile_map($x['state'], array(
              'scheduled'   => 'neutral',
              'due'         => 'warn',
              'paid'        => 'ok',
              'overdue'     => 'danger',
              'rescheduled' => 'info',
          ));
      ?>
        <tr><td><?= intval($x['seq_no']) ?></td>
            <td><?= htmlspecialchars($x['due_date'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= number_format(floatval($x['amount_principal']), 2) ?></td>
            <td><?= number_format(floatval($x['amount_profit']), 2) ?></td>
            <td><strong><?= number_format(floatval($x['amount_total']), 2) ?></strong> <?= htmlspecialchars($x['currency'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= ems_profile_badge($x['state'], $instTone) ?></td>
            <td><?= htmlspecialchars($x['paid_date'] ?: '—', ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($x['payment_ref'] ?: '—', ENT_QUOTES, 'UTF-8') ?></td></tr>
      <?php } if (!$any) echo '<tr><td colspan="8" class="text-center text-muted">لا أقساطَ بعد</td></tr>'; ?>
      </tbody>
    </table>
    <p><a class="action-btn" href="installments.php?op=<?= $op_id ?>"><i class="fa fa-calendar-check"></i> شاشةُ السداد</a></p>
    <?php echo ems_profile_section_close(); ?>

  <?php elseif ($tab === 'ledger'): ?>
    <?php echo ems_profile_section_open(array(
        'title' => 'حركةُ العملية في دفتر الأحداث',
        'icon'  => 'fas fa-book',
        'note'  => 'أقساطٌ مستحقةٌ ومسددةٌ وتعديلات — آخرُ خمسين',
    )); ?>
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
    <?php echo ems_profile_section_close(); ?>

  <?php else: /* docs */ ?>
    <?php
    echo ems_profile_section_open(array('title' => 'مستنداتُ العملية', 'icon' => 'fas fa-folder-open'));
    echo ems_profile_facts(array(
        array('label' => 'مرجعُ العقد',   'value' => $op['contract_ref']),
        array('label' => 'تاريخُ التوقيع', 'value' => $op['signed_date']),
        array('label' => 'أُنشئت',        'value' => $op['created_at'] . ' · بواسطة #' . intval($op['created_by'])),
        array('label' => 'آخرُ تحديث',    'value' => $op['updated_at']),
    ), true);
    echo ems_profile_section_close();
    ?>
  <?php endif; ?>
  <?php endif; ?>
</div>
