<?php
/**
 * Procurement/proc_award_minutes.php — محضر المقارنة والترسية (RPR-W09 · PRC-10)
 * ───────────────────────────────────────────────────────────────────────────
 * **«محضرٌ × طلبِ عروض — ترسيةٌ واحدة»** (`PRC-10` نصًّا) — و`uq_award_rfq`
 * يُنفِّذها في المخطَّط.
 *
 * ◆ **ولماذا سطحٌ مستقلٌّ عن `rfq_compare_award.php`**: ذاك يقرأ `supplier_rfqs`
 *   ومصدرُها **عقدُ عميل** (‏`client_contract_id` مملوءٌ في صفوفِها كلِّها
 *   و`request_id` خاوٍ) — أي أنّه سطحُ **تعاقدٍ من الباطنِ لخدمةِ عميل**، لا
 *   سطحُ ترسيةِ شراء. وعرضُ الحبّتَين في جدولٍ واحدٍ يخلط مقامَين ويجعل
 *   «كم ترسيةً هذا الشهر» رقمًا لا يُفسَّر (`W9-D-02`).
 *
 * ◆ **والأدنى مشتقٌّ لا مُدَّعًى**: `is_lowest` تحسبه `awardRfq` من العروضِ
 *   بأساسِ العملة، والبوّابةُ `W9-14` تعيد اشتقاقَه وتقارنه بالمخزَّن.
 *   **والفائزُ غيرُ الأدنى يوجب سببًا مكتوبًا** (`chk_awd_why`).
 *
 * ◆ **والمعاييرُ تُعلَن قبل الفتحِ لا بعده** (`AWARD_WITHOUT_CRITERIA`) —
 *   وإلّا صارت المعاييرُ وصفًا للفائزِ لا حكمًا عليه.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }
include '../config.php';
require_once __DIR__ . '/../includes/w14_grid.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/../includes/w7_codes.php';

enforce_current_page_view_permission($conn, '../main/dashboard.php');

$is_super = ((isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '') === '-1');
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
if (!$is_super && $company_id <= 0) { ems_gov_flash_redirect('../main/dashboard.php', 'بيئة شركة غير صالحة', 'GOV-SCOPE-403', ''); exit(); }

$pp = check_page_permissions($conn, 'Procurement/proc_award_minutes.php');
$gate = $is_super ? ems_tenant_db()->forAllTenants('proc_award_minutes super') : ems_tenant_db();
$pick = isset($_GET['state']) ? preg_replace('/[^A-Za-z0-9_\-]/', '', (string) $_GET['state']) : '';

$rows = array(); $choices = array(); $rfqs = array(); $sups = array();
try {
    $opts = array('orderBy' => 'id DESC', 'limit' => 300);
    if ($pick !== '') { $opts['where'] = array('state' => $pick); }
    $rows = $gate->select('proc_award', $opts);
} catch (\Throwable $t) { error_log('award_minutes list: ' . $t->getMessage()); }
foreach ($rows as $r) { if ((string) $r['state'] !== '') { $choices[(string) $r['state']] = true; } }
try { foreach ($gate->select('proc_rfq', array('columns' => array('id', 'code', 'title'), 'limit' => 400)) as $q) { $rfqs[(int) $q['id']] = $q; } }
catch (\Throwable $t) { error_log('award_minutes rfqs: ' . $t->getMessage()); }
try { foreach ($gate->select('proc_supplier', array('columns' => array('id', 'name'), 'limit' => 900)) as $s) { $sups[(int) $s['id']] = (string) $s['name']; } }
catch (\Throwable $t) { error_log('award_minutes sups: ' . $t->getMessage()); }

$notLowest = 0; $approved = 0;
foreach ($rows as $r) { if ((int) $r['is_lowest'] === 0) { $notLowest++; } if ((string) $r['state'] === 'approved') { $approved++; } }

$page_title = 'إيكوبيشن | محضر المقارنة والترسية';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($pp) ? $pp : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'محضر المقارنة والترسية'; $header_icon = 'fa fa-gavel'; $header_actions = array();
    $header_back = array('href' => 'proc_offers.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'عروض الموردين');
    include('../includes/page_header.php'); ?>
    <!-- سجلُّ حقولِ الورقةِ بحبّتِه — يُضاف بجانبِ ما بُني لا بدلًا منه،
         فالمبنيُّ له أفعالُه والورقةُ تطلب السجلَّ بحقولِه كلِّها -->
    <div class="card"><div class="card-header"><h5><i class="fa fa-clipboard-list"></i> سجل حقول الورقة</h5></div>
    <div class="card-body"><div class="table-container">
        <?php /* GUIDE_COLS:govui_field_close:emsList_prc_proc_award_minutes
             الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
             والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
             ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
        $GUIDE_COLS = array(
            'رقم المحضر' => 'g36',
            'رقم طلب العروض' => 'g37',
            'العروض المقارنة' => 'g38',
            'جدول المقارنة' => 'g39',
            'العرض المرسى عليه' => 'g40',
            'قيمة الترسية' => 'g41',
            'مبرر الاختيار' => 'g42',
            'تفصيل المبرر' => 'g43',
            'أعضاء اللجنة' => 'g44',
            'حالة الترسية' => 'g45',
            'المنشئ' => 'g46',
            'تاريخ الإنشاء' => 'g47',
            'المراجع' => 'g48',
            'المعتمد' => 'g49',
            'تاريخ الاعتماد' => 'g50',
            'حالة البيانات' => 'g51',
            'مرجع المصدر' => 'g52',
        );
        $D = array();
        $__gridRows = ems_w14_guide_rows('prc_proc_award_minutes');
        echo ems_w14_grid('emsList_prc_proc_award_minutes', $GUIDE_COLS, $__gridRows, $D, 'لا سطر مسجل بعد في محضر المقارنة والترسية'); /* /GUIDE_COLS */ ?>
    </div></div></div>
    <?php  ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">محاضر ترسية</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= $approved ?></div><div class="ems-stat-label">معتمدة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= $notLowest ?></div><div class="ems-stat-label">الفائز ليس الأدنى</div></div>
    </div>

    <form method="get" action="" class="ems-filters">
        <div class="field"><label for="w9_awd_st">حالة المحضر</label><select name="state" id="w9_awd_st" onchange="this.form.submit()">
            <option value="">الكل</option>
            <?php foreach (array_keys($choices) as $c): ?>
                <option value="<?= htmlspecialchars($c) ?>" <?= $pick === $c ? 'selected' : '' ?>><?= htmlspecialchars(ems_w7_ar($c, $conn)) ?></option>
            <?php endforeach; ?>
        </select></div>
    </form>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا محاضر ترسية', 'المحضر واحد لكل طلب عروض. والفائز غير الأدنى يلزمه سبب مكتوب'); ?>

    <div class="table-wrap"><table class="data-table">
        <thead><tr><th>رقم المحضر</th><th>طلب العروض</th><th>اللجنة</th><th>معايير التقييم</th><th>الفائز</th><th>مبلغ الفائز</th><th>الأدنى</th><th>مبلغ الأدنى</th><th>الفائز هو الأدنى</th><th>سبب اختيار غير الأدنى</th><th>الحالة</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): $q = isset($rfqs[(int) $r['rfq_id']]) ? $rfqs[(int) $r['rfq_id']] : null; ?>
            <tr>
                <td><?= htmlspecialchars((string) $r['minute_no']) ?></td>
                <td><?= htmlspecialchars($q ? (string) $q['code'] : ('#' . (int) $r['rfq_id'])) ?></td>
                <td><?= htmlspecialchars((string) $r['committee_ref']) ?></td>
                <td><?= htmlspecialchars((string) $r['criteria_ref']) ?></td>
                <td><?= htmlspecialchars(isset($sups[(int) $r['winner_id']]) ? $sups[(int) $r['winner_id']] : ('#' . (int) $r['winner_id'])) ?></td>
                <td><?= htmlspecialchars(number_format((float) $r['winner_amount'], 2)) ?></td>
                <td><?= htmlspecialchars(isset($sups[(int) $r['lowest_id']]) ? $sups[(int) $r['lowest_id']] : ('#' . (int) $r['lowest_id'])) ?></td>
                <td><?= htmlspecialchars(number_format((float) $r['lowest_amount'], 2)) ?></td>
                <td><?= ((int) $r['is_lowest'] === 1 ? 'نعم' : 'لا') ?></td>
                <td><?= htmlspecialchars((string) $r['award_why']) ?></td>
                <td><?= htmlspecialchars(ems_w7_ar((string) $r['state'], $conn)) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>
