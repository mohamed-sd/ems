<?php
/**
 * Fleet/asset_full_history.php — تاريخُ المعدةِ الكامل (FLEET-28)
 * ───────────────────────────────────────────────────────────────────────────
 * **تقريرٌ لا مصدرَ حقيقة** (‏`FLEET-28` نصًّا: «مشتقٌّ من كل الشيتات») —
 * Grain: **صفٌّ = واقعةٌ واحدةٌ في حياةِ الأصل**.
 *
 * ◆ الروافدُ المقروءةُ باسمِها — ولا يُخزَّن هنا شيء:
 *   ١) سجلُّ تاريخِ المعدةِ (‏`fleet_equipment_history` — يكتبه
 *      `log_equipment_event` من خطافاتِ الأحداثِ الخمسة): نوعُ الواقعةِ
 *      بمفردتِه العربيّةِ الحيّةِ ومرجعُها ومشروعُها وقيمتاها قبلُ وبعد.
 *   ٢) مطابقاتُ ساعاتِ الأصلِ الشهريّةُ (‏`asset_hour_reconciliations`) —
 *      تُعرَض واقعةً شهريّةً «ساعاتُ شهرٍ من الورديّاتِ المعتمدة».
 * ◆ وقراءةُ العدّادِ في دفترِ الحبّةِ **بلا عمودِ مصدرٍ في السجلَّين بعدُ**
 *   — تُعلَن ولا يُختلَق لها رقم (قاعدةُ الجسر).
 * ◆ القراءةُ `select` معزولًا والدمجُ والترتيبُ في الذاكرة — صفرُ حرفِ SQL
 *   على جدولِ مستأجِرٍ (GAP-29). قراءةٌ صِرفٌ — صفرُ POST.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';

$is_super_admin = ((isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '') === '-1');
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
if (!$is_super_admin && $company_id <= 0) { ems_gov_flash_redirect('../main/dashboard.php', 'بيئة شركة غير صالحة', 'GOV-SCOPE-403', ''); exit(); }

$pp = check_page_permissions($conn, 'Fleet/asset_full_history.php');
$can_view = $pp['can_view'];
if (!$can_view) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية', 'GOV-PERM-403', ''); exit(); }

$gate = $is_super_admin ? ems_tenant_db()->forAllTenants('asset full history super') : ems_tenant_db();
$assetQ = isset($_GET['asset']) ? intval($_GET['asset']) : 0;

$equip = array(); $proj = array();
try { foreach ($gate->select('equipments', array('columns' => array('id', 'code', 'name'), 'orderBy' => 'id ASC', 'limit' => 1000)) as $e0) { $equip[(int) $e0['id']] = trim(($e0['code'] ?? '') . ' ' . ($e0['name'] ?? '')); } }
catch (\Throwable $t) { error_log('asset_full_history equip: ' . $t->getMessage()); }
try { foreach ($gate->select('project', array('columns' => array('id', 'name'), 'limit' => 1000)) as $p0) { $proj[(int) $p0['id']] = (string) $p0['name']; } }
catch (\Throwable $t) { error_log('asset_full_history proj: ' . $t->getMessage()); }

$rows = array();
try {
    foreach ($gate->select('fleet_equipment_history', array('orderBy' => 'event_date ASC, id ASC', 'limit' => 5000)) as $h0) {
        $eq0 = (int) $h0['equipment_id'];
        if ($assetQ > 0 && $eq0 !== $assetQ) { continue; }
        $after = (string) ($h0['to_value'] !== null ? $h0['to_value'] : '');
        $rows[] = array(
            'eq'    => $eq0,
            'date'  => (string) $h0['event_date'],
            'kind'  => (string) $h0['event_type'],
            'sheet' => 'سجل تاريخ المعدة',
            'ref'   => trim((string) $h0['reference_type'] . ' ' . (string) $h0['reference_id']),
            'desc'  => (string) $h0['note'] !== '' ? (string) $h0['note']
                     : trim('من ' . (string) $h0['from_value'] . ' الى ' . $after),
            'proj'  => (int) $h0['project_id'] > 0
                     ? (isset($proj[(int) $h0['project_id']]) ? $proj[(int) $h0['project_id']] : ('مشروع رقم ' . (int) $h0['project_id']))
                     : 'غير منطبق',
            'after' => $after !== '' ? $after : 'غير منطبق',
            'by'    => (int) $h0['created_by'] > 0 ? ('مستخدم رقم ' . (int) $h0['created_by']) : 'غير منطبق',
        );
    }
} catch (\Throwable $t) { error_log('asset_full_history hist: ' . $t->getMessage()); }
try {
    foreach ($gate->select('asset_hour_reconciliations', array('columns' => array('rec_id', 'equipment_id', 'period', 'hours_from_shifts'), 'orderBy' => 'period ASC', 'limit' => 5000)) as $r0) {
        $eq0 = (int) $r0['equipment_id'];
        if ($assetQ > 0 && $eq0 !== $assetQ) { continue; }
        $rows[] = array(
            'eq'    => $eq0,
            'date'  => (string) $r0['period'] . '-01',
            'kind'  => 'ساعات شهر من الورديات المعتمدة',
            'sheet' => 'مطابقة ساعات الاصول',
            'ref'   => 'مطابقة رقم ' . (int) $r0['rec_id'],
            'desc'  => number_format((float) $r0['hours_from_shifts'], 1) . ' ساعة في شهر ' . (string) $r0['period'],
            'proj'  => 'غير منطبق',
            'after' => 'غير منطبق',
            'by'    => 'محرك الساعات',
        );
    }
} catch (\Throwable $t) { error_log('asset_full_history hours: ' . $t->getMessage()); }
usort($rows, function ($a, $b) { $c = strcmp($a['date'], $b['date']); return $c !== 0 ? $c : ($a['eq'] - $b['eq']); });

$nAssets = array(); foreach ($rows as $x0) { $nAssets[$x0['eq']] = true; }

$page_title = 'إيكوبيشن | تاريخ المعدة الكامل';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($pp) ? $pp : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'تاريخ المعدة الكامل: كل واقعة في حياة الاصل، تقرير مشتق لا مصدر حقيقة'; $header_icon = 'fa fa-timeline'; $header_actions = array();
    $header_back = array('href' => 'asset_hours_reference.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'مرجع ساعات التشغيل');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format(count($rows)) ?></div><div class="ems-stat-label">وقائع معروضة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format(count($nAssets)) ?></div><div class="ems-stat-label">اصول لها وقائع</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format(count($equip)) ?></div><div class="ems-stat-label">اصول في السجل</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value">2</div><div class="ems-stat-label">روافد مقروءة باسمها</div></div>
    </div>

    <div class="ems-filter-box">
        <form method="get" class="ems-filters">
            <div class="ems-filter-item">
                <label>الأصل</label>
                <select name="asset" onchange="this.form.submit()">
                    <option value="0">كل الأصول</option>
                    <?php foreach ($equip as $eid => $enm): ?>
                    <option value="<?= $eid ?>" <?= $eid === $assetQ ? 'selected' : '' ?>><?= htmlspecialchars($enm) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>

    <div class="table-container">
        <table class="ems-data-table">
            <thead>
                <tr>
                    <th>كود الأصل</th>
                    <th>التاريخ</th>
                    <th>نوع الواقعة</th>
                    <th>الشيت المصدر</th>
                    <th>مرجع السجل</th>
                    <th>وصف الواقعة</th>
                    <th>المشروع</th>
                    <th>الحالة بعد الواقعة</th>
                    <th>المسؤول</th>
                </tr>
            </thead>
            <tbody>
            <?php $seq = 0; foreach ($rows as $x0): $seq++; ?>
                <tr>
                    <td><?= htmlspecialchars(isset($equip[$x0['eq']]) ? $equip[$x0['eq']] : ('اصل رقم ' . $x0['eq'])) ?></td>
                    <td><?= htmlspecialchars($x0['date']) ?></td>
                    <td><?= htmlspecialchars($x0['kind']) ?></td>
                    <td><?= htmlspecialchars($x0['sheet']) ?></td>
                    <td><?= htmlspecialchars($x0['ref'] !== '' ? $x0['ref'] : 'بلا مرجع') ?></td>
                    <td><?= htmlspecialchars($x0['desc']) ?></td>
                    <td><?= htmlspecialchars($x0['proj']) ?></td>
                    <td><?= htmlspecialchars($x0['after']) ?></td>
                    <td><?= htmlspecialchars($x0['by']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?>
                <tr><td colspan="9">لا وقائع بعد. الروافد سجل تاريخ المعدة من خطافات الاحداث ومطابقات ساعات الاصول الشهرية</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="ems-note-box">
        <strong>تقرير مشتق لا مصدر حقيقة:</strong>
        الوقائع من رافدين باسمهما، سجل تاريخ المعدة الذي تكتبه خطافات الاحداث،
        ومطابقات ساعات الاصول الشهرية من الورديات المعتمدة، مرتبة زمنيا.
        وقراءة العداد في دفتر الحبة بلا عمود مصدر في الرافدين بعد فتعلن ولا يختلق لها رقم.
        قراءة صرف ولا ادخال من هذه الشاشة.
    </div>
</div>
<?php include '../infooter.php'; ?>
