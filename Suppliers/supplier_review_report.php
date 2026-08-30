<?php
/**
 * Suppliers/supplier_review_report.php — تقريرُ المراجعةِ والقبول (SUP-37)
 * ───────────────────────────────────────────────────────────────────────────
 * Grain: **بندُ مراجعةٍ/قبولٍ واحد** — بنيويٌّ (STRUCTURAL): دليلٌ مقيسٌ
 * بالمعرِّفِ لا رحلةَ أعمال.
 *
 * ◆ البنودُ من سجلَّي الحوكمةِ الحاكمَين لوحدةِ الموردين: أهدافُ الوحدةِ
 *   بأحكامِها المقيسةِ ولقطةِ كلِّ حكمٍ (كونُ الأهدافِ) · وإغلاقاتُ
 *   الدليلِ بفحوصِها الأربعةِ وبصمةِ تصييرِها (سجلُّ الإغلاقِ بالدليل) —
 *   وكلُّ بندٍ بمرجعِه ولقطتِه، ولا نسبةَ بلا مقام.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';

$is_super_admin = ((isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '') === '-1');
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
if (!$is_super_admin && $company_id <= 0) { ems_gov_flash_redirect('../main/dashboard.php', 'بيئة شركة غير صالحة', 'GOV-SCOPE-403', ''); exit(); }

$pp = check_page_permissions($conn, 'Suppliers/supplier_review_report.php');
$can_view = $pp['can_view'];
if (!$can_view) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية', 'GOV-PERM-403', ''); exit(); }

$rows = array(); $nMatched = 0; $nClosed = 0; $nAll = 0;
$r = @$conn->query("SELECT target_uid, name_ar, verdict, verdict_snapshot FROM repair01_target_universe
                     WHERE unit = 'DEP-02' ORDER BY target_uid");
while ($r && ($x = $r->fetch_assoc())) {
    $nAll++;
    if ((string) $x['verdict'] === 'MATCHED') { $nMatched++; }
    $rows[] = array(
        'kind' => 'حكم هدف',
        'ref'  => (string) $x['target_uid'],
        'item' => (string) $x['name_ar'],
        'verd' => (string) $x['verdict'] !== '' ? (string) $x['verdict'] : 'بلا حكم بعد',
        'snap' => (string) $x['verdict_snapshot'] !== '' ? (string) $x['verdict_snapshot'] : 'بلا لقطة',
    );
}
$r = @$conn->query("SELECT c.requirement_id, c.checks_passed, c.render_proof, c.snapshot_id
                      FROM repair01_evidence_closure c
                      JOIN repair01_requirements q ON q.requirement_id = c.requirement_id
                     WHERE q.unit LIKE '02%' ORDER BY c.requirement_id");
while ($r && ($x = $r->fetch_assoc())) {
    $nClosed++;
    $rows[] = array(
        'kind' => 'اغلاق بالدليل',
        'ref'  => (string) $x['requirement_id'],
        'item' => 'فحوص ' . (string) $x['checks_passed'] . ((string) $x['render_proof'] !== '' ? ' وبصمة تصيير ' . (string) $x['render_proof'] : ''),
        'verd' => 'مقبول بدليله',
        'snap' => (string) $x['snapshot_id'],
    );
}

$page_title = 'إيكوبيشن | تقرير مراجعة وحدة الموردين';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($pp) ? $pp : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'تقرير المراجعة والقبول: بند واحد بمرجعه ولقطته ولا نسبة بلا مقام'; $header_icon = 'fa fa-square-check'; $header_actions = array();
    $header_back = array('href' => 'supplier_ref_lists.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'القوائم المرجعية');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format($nAll) ?></div><div class="ems-stat-label">اهداف الوحدة في الكون</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format($nMatched) ?></div><div class="ems-stat-label">متحققة بحكم مقيس</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format($nClosed) ?></div><div class="ems-stat-label">مغلقة بالدليل بفحوصها</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format(count($rows)) ?></div><div class="ems-stat-label">بنود التقرير كلها</div></div>
    </div>

    <div class="table-container">
        <table class="ems-data-table">
            <thead><tr><th>نوع البند</th><th>المرجع</th><th>البيان</th><th>الحكم</th><th>اللقطة</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $x0): ?>
                <tr>
                    <td><?= htmlspecialchars($x0['kind']) ?></td>
                    <td><?= htmlspecialchars($x0['ref']) ?></td>
                    <td><?= htmlspecialchars(mb_substr($x0['item'], 0, 70)) ?></td>
                    <td><?= htmlspecialchars($x0['verd']) ?></td>
                    <td><?= htmlspecialchars($x0['snap']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?><tr><td colspan="5">لا بنود مراجعة بعد</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="ems-note-box">
        بنود التقرير من سجلي الحوكمة الحاكمين، حكم كل هدف بلقطته واغلاق كل دليل بفحوصه وبصمته،
        ولا نسبة تعرض بلا مقامها الظاهر في البطاقات. قراءة صرف ولا ادخال.
    </div>
</div>
<?php include '../infooter.php'; ?>
