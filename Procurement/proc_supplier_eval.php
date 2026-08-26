<?php
/**
 * Procurement/proc_supplier_eval.php — تقييم أداء التوريد (RPR-W09 · PRC-16)
 * ───────────────────────────────────────────────────────────────────────────
 * **«موردٌ × فترة — سطرُ تقييمٍ مشتقّ»** (`PRC-16` نصًّا). فالسطحُ **بلا
 * نموذجِ إدخالٍ واحد**: كلُّ رقمٍ فيه مشتقٌّ من واقعةٍ مقيسةٍ ومعه **قاعدةُ
 * اشتقاقِه ومصادرُه بالاسم** — فلا رقمَ بلا قاعدة.
 *
 * · `الالتزام بالموعد` من `proc_delivery_event` (‏أيام التأخر)
 * · `نسبة الرفض` من `proc_receipt_line` (‏المرفوض إلى الوارد)
 * · `نسبة الفروق` من `proc_invoice_match` (‏خارج العتبة إلى الكل)
 *
 * ◆ **وأوزانُ الدرجةِ من السجلِّ لا من الشيفرة**: `evaluateSupplier` تردُّ
 *   `THRESHOLD_NOT_REGISTERED` إن غاب وزنٌ — **ولا تُخمّن قيمة**.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/../includes/w7_codes.php';

enforce_current_page_view_permission($conn, '../main/dashboard.php');

$is_super = ((isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '') === '-1');
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
if (!$is_super && $company_id <= 0) { ems_gov_flash_redirect('../main/dashboard.php', 'بيئة شركة غير صالحة', 'GOV-SCOPE-403', ''); exit(); }

$pp = check_page_permissions($conn, 'Procurement/proc_supplier_eval.php');
$gate = $is_super ? ems_tenant_db()->forAllTenants('proc_supplier_eval super') : ems_tenant_db();
$pick = isset($_GET['period']) ? preg_replace('/[^0-9\-]/', '', (string) $_GET['period']) : '';

$rows = array(); $periods = array(); $sups = array();
try {
    $opts = array('orderBy' => 'period_ym DESC, score DESC', 'limit' => 400);
    if ($pick !== '') { $opts['where'] = array('period_ym' => $pick); }
    $rows = $gate->select('proc_supplier_eval', $opts);
} catch (\Throwable $t) { error_log('supplier_eval list: ' . $t->getMessage()); }
try { foreach ($gate->select('proc_supplier_eval', array('columns' => array('period_ym'), 'limit' => 400)) as $p) { if ((string) $p['period_ym'] !== '') { $periods[(string) $p['period_ym']] = true; } } }
catch (\Throwable $t) { error_log('supplier_eval periods: ' . $t->getMessage()); }
try { foreach ($gate->select('proc_supplier', array('columns' => array('id', 'name'), 'limit' => 900)) as $s) { $sups[(int) $s['id']] = (string) $s['name']; } }
catch (\Throwable $t) { error_log('supplier_eval sups: ' . $t->getMessage()); }

$noRule = 0; $gradeA = 0;
foreach ($rows as $r) { if (trim((string) $r['score_rule']) === '') { $noRule++; } if ((string) $r['grade'] === 'A') { $gradeA++; } }

$page_title = 'إيكوبيشن | تقييم أداء التوريد';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($pp) ? $pp : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'تقييم أداء التوريد'; $header_icon = 'fa fa-ranking-star'; $header_actions = array();
    $header_back = array('href' => 'suppliers_proc.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'الموردون');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">أسطر تقييم</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= $gradeA ?></div><div class="ems-stat-label">موردون في الفئة الأولى</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= $noRule ?></div><div class="ems-stat-label">أسطر بلا قاعدة اشتقاق</div></div>
    </div>

    <form method="get" action="" class="ems-filters">
        <div class="field"><label for="w9_ev_p">الفترة</label><select name="period" id="w9_ev_p" onchange="this.form.submit()">
            <option value="">الكل</option>
            <?php foreach (array_keys($periods) as $c): ?>
                <option value="<?= htmlspecialchars($c) ?>" <?= $pick === $c ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
            <?php endforeach; ?>
        </select></div>
    </form>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا أسطر تقييم', 'السطر مشتق من التوريد والاستلام والمطابقة. ولا رقم بلا قاعدة اشتقاق'); ?>

    <div class="table-wrap"><table class="data-table">
        <thead><tr><th>المورد</th><th>الفترة</th><th>عدد الأوامر</th><th>الالتزام بالموعد</th><th>نسبة الرفض</th><th>نسبة الفروق</th><th>الدرجة</th><th>الفئة</th><th>قاعدة الاشتقاق</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
                <td><?= htmlspecialchars(isset($sups[(int) $r['supplier_id']]) ? $sups[(int) $r['supplier_id']] : ('#' . (int) $r['supplier_id'])) ?></td>
                <td><?= htmlspecialchars((string) $r['period_ym']) ?></td>
                <td><?= (int) $r['orders_count'] ?></td>
                <td><?= htmlspecialchars(number_format((float) $r['on_time_pct'], 2)) ?></td>
                <td><?= htmlspecialchars(number_format((float) $r['reject_pct'], 2)) ?></td>
                <td><?= htmlspecialchars(number_format((float) $r['variance_pct'], 2)) ?></td>
                <td><?= htmlspecialchars(number_format((float) $r['score'], 2)) ?></td>
                <td><?= htmlspecialchars((string) $r['grade']) ?></td>
                <td><small><?= htmlspecialchars((string) $r['score_rule']) ?></small></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>
