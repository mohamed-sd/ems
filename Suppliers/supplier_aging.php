<?php
/**
 * Suppliers/supplier_aging.php — أعمارُ الأرصدةِ والالتزامات (SUP-25)
 * ───────────────────────────────────────────────────────────────────────────
 * **مشتقٌّ من التسويات** — Grain: **موردٌ × عملةٌ × شريحةُ تقادم**.
 *
 * ◆ قواعدُ الاشتقاقِ مسمّاةٌ في الشاشة:
 *   - الاستحقاقُ = تسوياتُ الموردِ باتجاهِ «مستحقٌّ له» (payable) المعتمدةُ
 *     أو المطلوبُ صرفُها. والمدفوعُ = ما وُسم تاريخُ سدادِه.
 *   - الرصيدُ القائمُ = المستحقُّ غيرُ المسدَّدِ، **بعملتِه لا يُخلَط**
 *     (‏قاعدةُ `fx-currency-foundation`).
 *   - عمرُ الرصيدِ = من نهايةِ فترةِ أقدمِ تسويةٍ قائمةٍ إلى يومِ القاعدة،
 *     والشرائحُ: حتى 30 · 31 حتى 60 · 61 حتى 90 · فوق 90 يومًا.
 *   - وحقولُ الإدخالِ التجاريِّ في دفترِ الحبّةِ (التزاماتٌ قادمةٌ وإجراءٌ
 *     مقترح) **بلا مصدرٍ بعدُ فتُعلَن ولا يُختلَق لها رقم**.
 * ◆ القراءةُ `select` معزولًا والتجميعُ في الذاكرة — صفرُ حرفِ SQL على
 *   جدولِ مستأجِرٍ في الملفّ (GAP-29). قراءةٌ صِرفٌ — صفرُ POST.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
require_once __DIR__ . '/../includes/w14_grid.php';
include '../includes/permissions_helper.php';

$is_super_admin = ((isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '') === '-1');
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
if (!$is_super_admin && $company_id <= 0) { ems_gov_flash_redirect('../main/dashboard.php', 'بيئة شركة غير صالحة', 'GOV-SCOPE-403', ''); exit(); }

$pp = check_page_permissions($conn, 'Suppliers/supplier_aging.php');
$can_view = $pp['can_view'];
if (!$can_view) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية', 'GOV-PERM-403', ''); exit(); }

$gate = $is_super_admin ? ems_tenant_db()->forAllTenants('supplier aging super') : ems_tenant_db();

/* اليومُ من ساعةِ القاعدةِ (VT-07) */
$today = (string) ($conn->query('SELECT CURDATE()')->fetch_row()[0]);

$sup = array();
try { foreach ($gate->select('suppliers', array('columns' => array('id', 'name'), 'limit' => 2000)) as $s0) { $sup[(int) $s0['id']] = (string) $s0['name']; } }
catch (\Throwable $t) { error_log('supplier_aging sup: ' . $t->getMessage()); }

/* التجميعُ: مورد × عملة — استحقاقٌ ومدفوعٌ وقائمٌ وأقدمُ فترةٍ قائمة */
$agg = array();
try {
    foreach ($gate->select('settlements', array('limit' => 5000)) as $x0) {
        if ((string) $x0['party_type'] !== 'supplier') { continue; }
        if ((int) (isset($x0['is_deleted']) ? $x0['is_deleted'] : 0) === 1) { continue; }
        if ((string) $x0['net_direction'] !== 'payable') { continue; }
        $st = (string) $x0['state'];
        if ($st !== 'approved' && $st !== 'payment_requested' && $st !== 'paid' && $st !== 'closed') { continue; }
        $sid = (int) $x0['party_ref'];
        $cur = (string) ($x0['currency'] !== '' ? $x0['currency'] : 'SDG');
        $k = $sid . '·' . $cur;
        if (!isset($agg[$k])) {
            $agg[$k] = array('sid' => $sid, 'cur' => $cur, 'due' => 0.0, 'paid' => 0.0,
                             'open' => 0.0, 'oldest' => '', 'lastmove' => '');
        }
        $amt = (float) $x0['net_amount'];
        $agg[$k]['due'] += $amt;
        $pm = substr((string) $x0['period_to'], 0, 7);
        if ($pm > $agg[$k]['lastmove']) { $agg[$k]['lastmove'] = $pm; }
        if ($x0['paid_at'] !== null && $x0['paid_at'] !== '') {
            $agg[$k]['paid'] += $amt;
        } else {
            $agg[$k]['open'] += $amt;
            $pt = (string) $x0['period_to'];
            if ($pt !== '' && ($agg[$k]['oldest'] === '' || $pt < $agg[$k]['oldest'])) { $agg[$k]['oldest'] = $pt; }
        }
    }
} catch (\Throwable $t) { error_log('supplier_aging stl: ' . $t->getMessage()); }

$rows = array(); $nOpen = 0; $nOver90 = 0; $curSet = array();
$i = 0;
foreach ($agg as $a0) {
    $i++;
    $age = null; $bucket = 'لا رصيد قائما';
    if ($a0['open'] > 0.004) {
        $nOpen++;
        $age = $a0['oldest'] !== '' ? (int) floor((strtotime($today) - strtotime($a0['oldest'])) / 86400) : null;
        if ($age === null) { $bucket = 'بلا تاريخ فترة'; }
        elseif ($age <= 30) { $bucket = 'حتى 30 يوما'; }
        elseif ($age <= 60) { $bucket = '31 حتى 60 يوما'; }
        elseif ($age <= 90) { $bucket = '61 حتى 90 يوما'; }
        else { $bucket = 'فوق 90 يوما'; $nOver90++; }
    }
    $curSet[$a0['cur']] = true;
    $rows[] = array(
        'aid'    => 'رصيد رقم ' . $i,
        'sid'    => $a0['sid'],
        'name'   => isset($sup[$a0['sid']]) ? $sup[$a0['sid']] : ('مورد رقم ' . $a0['sid']),
        'cur'    => $a0['cur'],
        'due'    => number_format($a0['due'], 0),
        'paid'   => number_format($a0['paid'], 0),
        'open'   => number_format($a0['open'], 0),
        'last'   => $a0['lastmove'] !== '' ? $a0['lastmove'] : 'بلا حركة',
        'age'    => $age === null ? 'غير منطبق' : ($age . ' يوما'),
        'bucket' => $bucket,
        'state'  => $a0['open'] > 0.004 ? 'قائم' : 'مسدد بكامله',
    );
}
usort($rows, function ($a, $b) { return strcmp($a['name'] . $a['cur'], $b['name'] . $b['cur']); });

$page_title = 'إيكوبيشن | أعمار أرصدة الموردين';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($pp) ? $pp : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'أعمار الأرصدة والالتزامات: مورد في عملة في شريحة تقادم، مشتق من التسويات'; $header_icon = 'fa fa-hourglass-half'; $header_actions = array();
    $header_back = array('href' => 'supplier_targets.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'مستهدفات الموردين');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format(count($rows)) ?></div><div class="ems-stat-label">ارصدة مورد في عملة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format($nOpen) ?></div><div class="ems-stat-label">ارصدة قائمة غير مسددة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format($nOver90) ?></div><div class="ems-stat-label">تجاوزت تسعين يوما</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format(count($curSet)) ?></div><div class="ems-stat-label">عملات، كل عملة على حدة</div></div>
    </div>

    <div class="table-container">
        <?php /* GUIDE_COLS:govui_field_close
             الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
             والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
             ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
        $GUIDE_COLS = array(
            'كود الرصيد' => 'id',
            'التسلسل الزمني للمورد' => 'c2',
            'رقم المورد' => 'no_supplier',
            'اسم المورد (بحث)' => 'name_supplier',
            'العملة' => 'c5',
            'إجمالي الاستحقاق' => 'entitlement',
            'إجمالي المدفوع' => 'c7',
            'الرصيد القائم' => 'balance',
            'آخر شهر حركة' => 'month',
            'عمر الرصيد (يوما)' => 'balance_10',
            'شريحة العمر' => 'c11',
            'حالة الرصيد' => 'balance_12',
            'حالة المورد (م01)' => 'supplier',
            'التزام قادم ≤30 يوما' => 'c14',
            'التزام قادم 31 60' => 'c15',
            'التزام قادم 61 90' => 'c16',
            'إجراء التحصيل/السداد المقترح' => 'c17',
            'المسؤول' => 'c18',
            'Record_Basis' => 'record_basis',
            'Derivation_Rule' => 'derivation_rule',
            'حالة البيانات' => 'data_state',
            'ملاحظات' => 'notes',
        );
        $D = array();
        $__gridRows = ems_w14_guide_rows('sup_aging_obligation');
        echo ems_w14_grid('emsList_sup_aging', $GUIDE_COLS, $__gridRows, $D, 'لا رصيد مسجل بعد'); /* /GUIDE_COLS */ ?>
    </div>

    <div class="ems-note-box">
        <strong>قواعد الاشتقاق بمصادرها:</strong>
        الاستحقاق من تسويات المورد المعتمدة او المطلوب صرفها باتجاه مستحق له،
        والمدفوع ما وسم تاريخ سداده، والرصيد القائم الفرق بعملته ولا تخلط عملتان.
        العمر من نهاية فترة اقدم تسوية قائمة الى يوم القاعدة، والشرائح اربع.
        وحقول الالتزامات القادمة والاجراء المقترح في دفتر الحبة بلا مصدر بعد فتعلن ولا يختلق لها رقم.
        قراءة صرف ولا ادخال من هذه الشاشة.
    </div>
</div>
<?php include '../infooter.php'; ?>
