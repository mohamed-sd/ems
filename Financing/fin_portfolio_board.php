<?php
/**
 * Financing/fin_portfolio_board.php — التقاريرُ ولوحةُ المحفظة (FIN-25)
 * ───────────────────────────────────────────────────────────────────────────
 * **مشتقٌّ كليًّا — Dashboard** (‏`FIN-25` نصًّا: «مصدرُ الحقيقة: مشتقٌّ كليًّا ·
 * المالكة: إدارةُ التمويل») — حبّةُ الملفِّ: **مؤشِّرُ محفظةٍ واحدٌ مشتقّ**،
 * وحقولُه الثلاثة: المؤشِّرُ · القيمةُ ◄ · المعادلةُ/المصدر.
 *
 * ◆ **كلُّ مؤشِّرٍ بمعادلتِه ومصدرِه في الصفِّ نفسِه** — فلا رقمَ بلا قاعدة:
 *   المصادرُ `financing_operations` (‏العمليّاتُ ورؤوسُ الأموال) و
 *   `financing_installments` (‏الأقساطُ بحالاتِها) و`financing_deviations`
 *   (‏الانحرافاتُ المفتوحة) — قراءةٌ صِرفٌ ولا إدخالَ ولا POST.
 * ◆ **والعملةُ تُجمَع بعملتِها** — ⛔ لا يُجمَع رصيدان بعملتَين في رقمٍ واحدٍ
 *   بلا معادلِ أساسٍ (‏قاعدةُ `fx-currency-foundation`).
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';

$is_super_admin = ((isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '') === '-1');
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
if (!$is_super_admin && $company_id <= 0) { ems_gov_flash_redirect('../main/dashboard.php', 'بيئة شركة غير صالحة', 'GOV-SCOPE-403', ''); exit(); }

$pp = check_page_permissions($conn, 'Financing/fin_portfolio_board.php');
$can_view = $pp['can_view'];
if (!$can_view) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية', 'GOV-PERM-403', ''); exit(); }

$gate = $is_super_admin ? ems_tenant_db()->forAllTenants('fin portfolio board super') : ems_tenant_db();

/* المؤشِّراتُ — كلٌّ (‏اسمٌ، قيمةٌ مقيسة، معادلةٌ ومصدرٌ بالنصّ) */
$IND = array();
$ops = array(); $insts = array(); $devs = array();
try { $ops = $gate->select('financing_operations', array('limit' => 2000)); } catch (\Throwable $t) { error_log('fin_portfolio ops: ' . $t->getMessage()); }
try { $insts = $gate->select('financing_installments', array('limit' => 10000)); } catch (\Throwable $t) { error_log('fin_portfolio inst: ' . $t->getMessage()); }
try { $devs = $gate->select('financing_deviations', array('limit' => 2000)); } catch (\Throwable $t) { error_log('fin_portfolio dev: ' . $t->getMessage()); }

$nOps = count($ops); $active = 0; $capByCur = array(); $outByCur = array();
foreach ($ops as $o0) {
    $st = (string) $o0['state'];
    if ($st !== 'closed' && $st !== 'final_closed' && $st !== 'cancelled') { $active++; }
    $cur = (string) ($o0['currency'] !== '' ? $o0['currency'] : 'SDG');
    $capByCur[$cur] = (isset($capByCur[$cur]) ? $capByCur[$cur] : 0) + (float) $o0['capital'];
    $outByCur[$cur] = (isset($outByCur[$cur]) ? $outByCur[$cur] : 0) + (float) $o0['outstanding_balance'];
}
$nInst = count($insts); $paid = 0; $overdue = 0;
/* اليومُ من ساعةِ القاعدةِ نفسِها — لا date() متفرِّقةً (VT-07) ولا فرقَ منطقةٍ عن CURDATE المستعملةِ في القراءات */
$today = (string) ($conn->query('SELECT CURDATE()')->fetch_row()[0]);
foreach ($insts as $i0) {
    $st = (string) $i0['state'];
    if ($st === 'paid') { $paid++; }
    elseif ((string) $i0['due_date'] < $today && $st !== 'paid' && $st !== 'cancelled') { $overdue++; }
}
$openDev = 0;
foreach ($devs as $d0) {
    $ds = isset($d0['state']) ? (string) $d0['state'] : (isset($d0['status']) ? (string) $d0['status'] : '');
    if ($ds !== 'closed' && $ds !== 'resolved') { $openDev++; }
}
$fmtCur = function ($m0) {
    $out = array();
    foreach ($m0 as $cur => $v) { $out[] = number_format($v, 0) . ' ' . $cur; }
    return $out ? implode(' + ', $out) : '0';
};
$IND[] = array('عمليات التمويل في المحفظة', number_format($nOps), 'عد صفوف سجل عمليات التمويل');
$IND[] = array('العمليات النشطة، غير المقفلة وغير الملغاة', number_format($active), 'عد العمليات التي حالتها ليست اقفالا ولا الغاء');
$IND[] = array('راس المال الممول بعملته', $fmtCur($capByCur), 'مجموع عمود راس المال في سجل العمليات، لكل عملة على حدة');
$IND[] = array('الرصيد القائم بعملته', $fmtCur($outByCur), 'مجموع عمود الرصيد القائم في سجل العمليات، لكل عملة على حدة');
$IND[] = array('الاقساط المجدولة', number_format($nInst), 'عد صفوف سجل الاقساط');
$IND[] = array('الاقساط المسددة', number_format($paid), 'عد الاقساط التي حالتها مسددة');
$IND[] = array('اقساط تجاوزت استحقاقها بلا سداد', number_format($overdue), 'قسط تاريخ استحقاقه قبل اليوم وحالته ليست سدادا ولا الغاء');
$IND[] = array('انحرافات تمويل مفتوحة', number_format($openDev), 'عد صفوف سجل الانحرافات التي لم تقفل');

$page_title = 'إيكوبيشن | التقارير ولوحة المحفظة';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($pp) ? $pp : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'التقارير ولوحة المحفظة: مؤشرات مشتقة كليا بمعادلة كل مؤشر ومصدره'; $header_icon = 'fa fa-briefcase'; $header_actions = array();
    $header_back = array('href' => 'operations.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'عمليات التمويل');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format($nOps) ?></div><div class="ems-stat-label">عمليات المحفظة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format($active) ?></div><div class="ems-stat-label">النشطة منها</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format($overdue) ?></div><div class="ems-stat-label">اقساط متجاوزة الاستحقاق</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format($openDev) ?></div><div class="ems-stat-label">انحرافات مفتوحة</div></div>
    </div>

    <div class="table-container">
        <table class="ems-data-table">
            <thead>
                <tr>
                    <th>المؤشر</th>
                    <th>القيمة</th>
                    <th>المعادلة/المصدر</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($IND as $i0): ?>
                <tr>
                    <td><?= htmlspecialchars($i0[0]) ?></td>
                    <td><?= htmlspecialchars($i0[1]) ?></td>
                    <td><?= htmlspecialchars($i0[2]) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="ems-note-box">
        لوحة قراءة مشتقة كليا: كل مؤشر يذكر معادلته ومصدره في صفه، والعملات تجمع كل عملة على حدة بلا خلط، ولا ادخال من هذه الشاشة.
    </div>
</div>
<?php include '../infooter.php'; ?>
