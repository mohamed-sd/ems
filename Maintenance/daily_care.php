<?php
/**
 * Maintenance/daily_care.php — العناية اليومية والتشحيم (RPR-W07 · MNT-13)
 * ───────────────────────────────────────────────────────────────────────────
 * **مهامُّ العنايةِ اليوميّةِ ينفّذها المشغّلُ أو الفنّيُّ بقائمةِ النوع**
 * (`MNT-13`)، **والنتيجةُ غيرُ الطبيعيّةِ تفتح بلاغًا متفرّعًا** — ولذلك
 * `logDailyCare` تردُّ `ABNORMAL_WITHOUT_NOTE`: ملاحظةٌ غيرُ طبيعيّةٍ بلا نصٍّ
 * تُغلق السطرَ وتُخفي ما كان ينبغي أن يصير بلاغًا.
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

$pp = check_page_permissions($conn, 'Maintenance/daily_care.php');
if (!$pp['can_view']) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية', 'GOV-PERM-403', ''); exit(); }

$gate = $is_super_admin ? ems_tenant_db()->forAllTenants('daily_care super') : ems_tenant_db();
$pick = isset($_GET['result']) ? preg_replace('/[^A-Za-z0-9_\-]/', '', (string) $_GET['result']) : '';

$rows = array(); $choices = array();
try {
    $opts = array('orderBy' => 'care_date DESC, id DESC', 'limit' => 400);
    if ($pick !== '') { $opts['where'] = array('result' => $pick); }
    $rows = $gate->select('mnt_daily_care', $opts);
} catch (\Throwable $t) { error_log('daily_care list: ' . $t->getMessage()); }
try { foreach ($gate->select('mnt_daily_care', array('columns' => array('result'), 'limit' => 400)) as $c) { if ((string) $c['result'] !== '') { $choices[(string) $c['result']] = true; } } }
catch (\Throwable $t) { error_log('daily_care choices: ' . $t->getMessage()); }
$equip = array();
try { foreach ($gate->select('equipments', array('columns' => array('id', 'code', 'name'), 'orderBy' => 'id ASC', 'limit' => 500)) as $e) { $equip[(int) $e['id']] = trim(($e['code'] ?? '') . ' ' . ($e['name'] ?? '')); } }
catch (\Throwable $t) { error_log('daily_care equip: ' . $t->getMessage()); }

$abn = 0; $branched = 0;
foreach ($rows as $r) { if ((string) $r['result'] === 'abnormal') { $abn++; } if ((int) $r['breakdown_id'] > 0) { $branched++; } }

$page_title = 'إيكوبيشن | العناية اليومية والتشحيم';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($pp) ? $pp : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'العناية اليومية والتشحيم'; $header_icon = 'fa fa-oil-can'; $header_actions = array();
    $header_back = array('href' => 'preventive_plans.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'الخطط الوقائية');
    include('../includes/page_header.php'); ?>
    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">أسطر عناية</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= $abn ?></div><div class="ems-stat-label">ملاحظات غير طبيعية</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= $branched ?></div><div class="ems-stat-label">تفرع عنها بلاغ</div></div>
    </div>
    <form method="get" action="" class="ems-filters">
        <div class="field"><label for="w7_dc_r">النتيجة</label><select name="result" id="w7_dc_r" onchange="this.form.submit()">
            <option value="">الكل</option>
            <?php foreach (array_keys($choices) as $c): ?>
                <option value="<?= htmlspecialchars($c) ?>" <?= $pick === $c ? 'selected' : '' ?>><?= htmlspecialchars(ems_w7_ar($c, $conn)) ?></option>
            <?php endforeach; ?>
        </select></div>
    </form>
    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا أسطر عناية يومية', 'المهمة تنفذ بقائمة النوع. والنتيجة غير الطبيعية تفتح بلاغا'); ?>

    <div class="table-wrap"><table class="data-table">
        <thead><tr><th>#</th><th>التاريخ</th><th>المعدة</th><th>قائمة النوع</th><th>المهمة</th><th>النتيجة</th><th>ملاحظة غير طبيعية</th><th>بلاغ متفرع</th><th>حالة السطر</th><th>قاعدة الحالة</th></tr></thead>
        <tbody>
        <?php if ($rows): $i = 0; foreach ($rows as $r): $i++; ?>
            <tr>
                <td><?= $i ?></td>
                <td><?= htmlspecialchars((string) $r['care_date']) ?></td>
                <td><?= htmlspecialchars(isset($equip[(int) $r['equipment_id']]) ? $equip[(int) $r['equipment_id']] : ('#' . (int) $r['equipment_id'])) ?></td>
                <td><?= htmlspecialchars((string) $r['checklist_ref']) ?></td>
                <td><?= htmlspecialchars((string) $r['task_ar']) ?></td>
                <td><?= htmlspecialchars(ems_w7_ar((string) $r['result'], $conn)) ?></td>
                <td><small><?= htmlspecialchars(mb_substr((string) $r['abnormal_note'], 0, 120)) ?></small></td>
                <td><?= ($r['breakdown_id'] === null || (int) $r['breakdown_id'] === 0) ? '—' : (int) $r['breakdown_id'] ?></td>
                <td><?= htmlspecialchars(ems_w7_ar((string) $r['state'], $conn)) ?></td>
                <td><small><?= htmlspecialchars((string) $r['state_rule']) ?></small></td>
            </tr>
        <?php endforeach; else: ?>
            <tr><td colspan="10">لا أسطر عناية يومية.</td></tr>
        <?php endif; ?>
        </tbody></table></div>
</div>
</body></html>
