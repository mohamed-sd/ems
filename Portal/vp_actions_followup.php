<?php
/**
 * Portal/vp_actions_followup.php — متابعةُ قراراتِ النائب (VP-11)
 * ───────────────────────────────────────────────────────────────────────────
 * Grain: **قرارٌ/إجراءٌ نيابيٌّ × مصدرِه — سجلُّ متابعةٍ موحَّد** (نصُّ
 * الحبّة) — رحلةٌ عابرةُ نطاقات (CROSS_JOURNEY).
 *
 * ◆ الرافدُ الحاكمُ: سجلُّ قراراتِ الإدارةِ العليا (`exec_decisions`) —
 *   القرارُ بمصدرِه الرافعِ وإدارتِه المكلَّفةِ وخيارِه المتَّخذِ ومهلتَي
 *   تنفيذِه ومتابعتِه وحالتِه — وكلُّ صفٍّ قرارٌ واحدٌ يُتابَع.
 * ◆ والمتجاوزُ مهلتَه يُعلَم بيومِ القاعدةِ. لا كتابةَ هنا — التقريرُ
 *   والتحديثُ عند فاعلِهما في شاشةِ القراراتِ الاستراتيجيّة.
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

$pp = check_page_permissions($conn, 'Portal/vp_actions_followup.php');
$can_view = $pp['can_view'];
if (!$can_view) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية', 'GOV-PERM-403', ''); exit(); }

$gate = $is_super_admin ? ems_tenant_db()->forAllTenants('vp actions followup super') : ems_tenant_db();
$today = (string) ($conn->query('SELECT CURDATE()')->fetch_row()[0]);

$rows = array(); $nOpen = 0; $nLate = 0; $deptSet = array();
try {
    foreach ($gate->select('exec_decisions', array('orderBy' => 'id DESC', 'limit' => 2000)) as $x0) {
        $st = (string) $x0['status'];
        $open = !($st === 'closed' || $st === 'done' || $st === 'منفذ');
        if ($open) { $nOpen++; }
        $late = $open && (string) $x0['exec_deadline'] !== '' && $x0['exec_deadline'] !== null && (string) $x0['exec_deadline'] < $today;
        if ($late) { $nLate++; }
        $deptSet[(string) $x0['raising_dept']] = true;
        $rows[] = array(
            'ref'  => (string) $x0['decision_no'],
            'src'  => (string) $x0['raising_dept'],
            'kind' => (string) $x0['issue_type'],
            'desc' => mb_substr((string) $x0['issue_desc'], 0, 55),
            'pick' => (string) $x0['chosen_option'] !== '' ? mb_substr((string) $x0['chosen_option'], 0, 40) : 'لم يختر بعد',
            'to'   => (string) $x0['assigned_dept'] !== '' ? (string) $x0['assigned_dept'] : 'غير مكلفة بعد',
            'due'  => (string) $x0['exec_deadline'] !== '' && $x0['exec_deadline'] !== null ? ((string) $x0['exec_deadline'] . ($late ? '، متجاوزة' : '')) : 'بلا مهلة',
            'fup'  => (string) $x0['followup_date'] !== '' && $x0['followup_date'] !== null ? (string) $x0['followup_date'] : 'بلا موعد متابعة',
            'state' => $st !== '' ? $st : 'بلا حالة',
        );
    }
} catch (\Throwable $t) { error_log('vp_followup: ' . $t->getMessage()); }

$page_title = 'إيكوبيشن | متابعة قرارات النائب';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($pp) ? $pp : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'متابعة القرارات النيابية: قرار واحد بمصدره ومكلفه ومهلتيه وحالته'; $header_icon = 'fa fa-clipboard-list'; $header_actions = array();
    $header_back = array('href' => 'vp_pending_actions.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'المطلوب مني');
    include('../includes/page_header.php'); ?>
    <!-- سجلُّ حقولِ الورقةِ بحبّتِه — يُضاف بجانبِ ما بُني لا بدلًا منه،
         فالمبنيُّ له أفعالُه والورقةُ تطلب السجلَّ بحقولِه كلِّها -->
    <div class="card"><div class="card-header"><h5><i class="fa fa-clipboard-list"></i> سجل حقول الورقة</h5></div>
    <div class="card-body"><div class="table-container">
        <?php /* GUIDE_COLS:govui_field_close
             الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
             والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
             ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
        $GUIDE_COLS = array(
            'Action_ID' => 'g355',
            'Deputy_Role' => 'g7',
            'مصدر القرار' => 'g356',
            'الموضوع' => 'g358',
            'الإدارة' => 'g359',
            'المسؤول' => 'g360',
            'Due_Date' => 'g361',
            'Priority' => 'g362',
            'Status' => 'g363',
            'أيام التأخير' => 'g364',
            'Evidence' => 'g365',
            'Closure' => 'g366',
        );
        $D = array();
        $__gridRows = ems_w14_guide_rows('exec_action_followup');
        echo ems_w14_grid('emsList_exec_action_followup', $GUIDE_COLS, $__gridRows, $D, 'لا سطر مسجل بعد في متابعة قرارات النائب'); /* /GUIDE_COLS */ ?>
    </div></div></div>
    <?php  ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format(count($rows)) ?></div><div class="ems-stat-label">قرارات في سجل المتابعة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format($nOpen) ?></div><div class="ems-stat-label">مفتوحة بحالتها</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format($nLate) ?></div><div class="ems-stat-label">تجاوزت مهلة تنفيذها</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format(count($deptSet)) ?></div><div class="ems-stat-label">ادارات رافعة</div></div>
    </div>

    <div class="table-container">
        <table class="ems-data-table">
            <thead><tr><th>القرار</th><th>الرافعة</th><th>النوع</th><th>البيان</th><th>الخيار المتخذ</th><th>المكلفة</th><th>مهلة التنفيذ</th><th>موعد المتابعة</th><th>الحالة</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $x0): ?>
                <tr>
                    <td><?= htmlspecialchars($x0['ref']) ?></td>
                    <td><?= htmlspecialchars($x0['src']) ?></td>
                    <td><?= htmlspecialchars($x0['kind']) ?></td>
                    <td><?= htmlspecialchars($x0['desc']) ?></td>
                    <td><?= htmlspecialchars($x0['pick']) ?></td>
                    <td><?= htmlspecialchars($x0['to']) ?></td>
                    <td><?= htmlspecialchars($x0['due']) ?></td>
                    <td><?= htmlspecialchars($x0['fup']) ?></td>
                    <td><?= htmlspecialchars($x0['state']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?><tr><td colspan="9">لا قرارات في سجل الادارة العليا بعد</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="ems-note-box">
        سجل متابعة موحد يقرأ قرارات الادارة العليا بمصدرها الرافع ومكلفها وخيارها ومهلتي تنفيذها ومتابعتها،
        والمتجاوز يعلم بيوم القاعدة. التحديث والاقفال عند فاعلهما في شاشة القرارات الاستراتيجية ولا كتابة هنا.
    </div>
</div>
<?php include '../infooter.php'; ?>
