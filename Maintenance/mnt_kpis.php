<?php
/**
 * Maintenance/mnt_kpis.php — مؤشرات الصيانة الدورية (RPR-W07 · MNT-16)
 * ───────────────────────────────────────────────────────────────────────────
 * **«مشتقّةٌ من الأوامرِ والشهاداتِ والتوقّفات؛ لا إدخال»** (`MNT-16` نصًّا).
 * فالسطحُ **بلا نموذجِ إدخالٍ واحد**: يعرض السطرَ المشتقَّ ومعه **قاعدةُ
 * اشتقاقِه ومصادرُه بالاسم** — فلا رقمَ بلا قاعدة.
 * **والصيغةُ دالّةٌ واحدة** (`MaintenanceCycleService::kpiFormula`) تستدعيها
 * الأداةُ والبوّابةُ والشاشةُ معًا — فلا يتفرّق عدّادٌ وعارضٌ في ملفَّين.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/../includes/w7_codes.php';

$is_super_admin = ((isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '') === '-1');
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
if (!$is_super_admin && $company_id <= 0) { ems_gov_flash_redirect('../main/dashboard.php', 'بيئة شركة غير صالحة', 'GOV-SCOPE-403', ''); exit(); }

$pp = check_page_permissions($conn, 'Maintenance/mnt_kpis.php');
if (!$pp['can_view']) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية', 'GOV-PERM-403', ''); exit(); }

$gate = $is_super_admin ? ems_tenant_db()->forAllTenants('mnt_kpis super') : ems_tenant_db();
$pick = isset($_GET['period']) ? preg_replace('/[^A-Za-z0-9_\-]/', '', (string) $_GET['period']) : '';

$rows = array(); $choices = array();
try {
    $opts = array('orderBy' => 'period DESC, readiness_pct ASC', 'limit' => 400);
    if ($pick !== '') { $opts['where'] = array('period' => $pick); }
    $rows = $gate->select('mnt_kpi_period', $opts);
} catch (\Throwable $t) { error_log('mnt_kpis list: ' . $t->getMessage()); }
try { foreach ($gate->select('mnt_kpi_period', array('columns' => array('period'), 'limit' => 400)) as $c) { if ((string) $c['period'] !== '') { $choices[(string) $c['period']] = true; } } }
catch (\Throwable $t) { error_log('mnt_kpis choices: ' . $t->getMessage()); }
$equip = array();
try { foreach ($gate->select('equipments', array('columns' => array('id', 'code', 'name'), 'orderBy' => 'id ASC', 'limit' => 500)) as $e) { $equip[(int) $e['id']] = trim(($e['code'] ?? '') . ' ' . ($e['name'] ?? '')); } }
catch (\Throwable $t) { error_log('mnt_kpis equip: ' . $t->getMessage()); }

$mttr = 0.0; $pm = 0.0; $cn = count($rows);
foreach ($rows as $r) { $mttr += (float) $r['mttr_hours']; $pm += (float) $r['pm_compliance_pct']; }
if ($cn > 0) { $mttr = round($mttr / $cn, 2); $pm = round($pm / $cn, 2); }

$page_title = 'إيكوبيشن | مؤشرات الصيانة الدورية';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($pp) ? $pp : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'مؤشرات الصيانة الدورية'; $header_icon = 'fa fa-chart-line'; $header_actions = array();
    $header_back = array('href' => 'dashboard_mnt.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'لوحة الصيانة');
    include('../includes/page_header.php'); ?>
    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">أسطر مشتقة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= $mttr ?></div><div class="ems-stat-label">متوسط زمن الإصلاح</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= $pm ?></div><div class="ems-stat-label">متوسط الالتزام بالوقائية</div></div>
    </div>
    <form method="get" action="" class="ems-filters">
        <div class="field"><label for="w7_kpi_p">الفترة</label><select name="period" id="w7_kpi_p" onchange="this.form.submit()">
            <option value="">الكل</option>
            <?php foreach (array_keys($choices) as $c): ?>
                <option value="<?= htmlspecialchars($c) ?>" <?= $pick === $c ? 'selected' : '' ?>><?= htmlspecialchars(ems_w7_ar($c, $conn)) ?></option>
            <?php endforeach; ?>
        </select></div>
    </form>
    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا أسطر مؤشرات مشتقة بعد', 'الاشتقاق يجري من الأوامر والشهادات والتوقفات. ولا يدخل من هنا'); ?>

    <div class="table-wrap"><table class="data-table">
        <thead><tr><th>#</th><th>الفترة</th><th>النطاق</th><th>المعدة</th><th>عدد الأعطال</th><th>متوسط الزمن بين الأعطال</th><th>متوسط زمن الإصلاح</th><th>نسبة الجاهزية</th><th>وقائية منفذة</th><th>الالتزام بالوقائية</th><th>تكلفة الساعة</th><th>قاعدة الاشتقاق</th><th>المصادر</th></tr></thead>
        <tbody>
        <?php if ($rows): $i = 0; foreach ($rows as $r): $i++; ?>
            <tr>
                <td><?= $i ?></td>
                <td><?= htmlspecialchars((string) $r['period']) ?></td>
                <td><?= htmlspecialchars(ems_w7_ar((string) $r['scope_kind'], $conn)) ?></td>
                <td><?= htmlspecialchars(isset($equip[(int) $r['scope_ref']]) ? $equip[(int) $r['scope_ref']] : ('#' . (int) $r['scope_ref'])) ?></td>
                <td><?= htmlspecialchars((string) $r['breakdowns']) ?></td>
                <td><?= htmlspecialchars((string) $r['mtbf_hours']) ?></td>
                <td><?= htmlspecialchars((string) $r['mttr_hours']) ?></td>
                <td><?= htmlspecialchars((string) $r['readiness_pct']) ?></td>
                <td><?= htmlspecialchars((string) $r['pm_done']) ?></td>
                <td><?= htmlspecialchars((string) $r['pm_compliance_pct']) ?></td>
                <td><?= htmlspecialchars((string) $r['cost_per_hour']) ?></td>
                <td><small><?= htmlspecialchars((string) $r['derivation_rule']) ?></small></td>
                <td><small><?= htmlspecialchars((string) $r['derived_from']) ?></small></td>
            </tr>
        <?php endforeach; else: ?>
            <tr><td colspan="13">لا أسطر مؤشرات مشتقة بعد.</td></tr>
        <?php endif; ?>
        </tbody></table></div>
</div>
</body></html>
