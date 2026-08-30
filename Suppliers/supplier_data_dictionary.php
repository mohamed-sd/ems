<?php
/**
 * Suppliers/supplier_data_dictionary.php — القاموسُ وخريطةُ الترحيل (SUP-34)
 * ───────────────────────────────────────────────────────────────────────────
 * Grain: **تعريفُ حقلٍ واحد** — بنيويٌّ (STRUCTURAL).
 *
 * ◆ القاموسُ يُقرأ من المخطَّطِ الحيِّ نفسِه (`information_schema` — وهو
 *   مخزنُ وصفٍ منصّيٌّ لا جدولُ مستأجِر): حقولُ عائلةِ الموردين الخمسةِ
 *   بجدولِها ونوعِها وقبولِها الفراغَ وتعليقِها المدوَّنِ في المخطَّط —
 *   فلا نسخةَ يدويّةً تفترق عن الواقع.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';

$is_super_admin = ((isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '') === '-1');
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
if (!$is_super_admin && $company_id <= 0) { ems_gov_flash_redirect('../main/dashboard.php', 'بيئة شركة غير صالحة', 'GOV-SCOPE-403', ''); exit(); }

$pp = check_page_permissions($conn, 'Suppliers/supplier_data_dictionary.php');
$can_view = $pp['can_view'];
if (!$can_view) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية', 'GOV-PERM-403', ''); exit(); }

$FAM = array('suppliers', 'supplier_contracts', 'supplier_contract_lines', 'sup_close', 'sup_target_supplier');
$rows = array(); $tblSet = array(); $nCommented = 0;
$in = "'" . implode("','", $FAM) . "'";
$r = @$conn->query("SELECT TABLE_NAME t, COLUMN_NAME c, COLUMN_TYPE ty, IS_NULLABLE nn, COLUMN_COMMENT cm
                      FROM information_schema.COLUMNS
                     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ($in)
                     ORDER BY TABLE_NAME, ORDINAL_POSITION");
while ($r && ($x = $r->fetch_assoc())) {
    $tblSet[(string) $x['t']] = 1;
    $cm = trim((string) $x['cm']);
    if ($cm !== '') { $nCommented++; }
    $rows[] = array(
        'tbl'  => (string) $x['t'],
        'col'  => (string) $x['c'],
        'ty'   => (string) $x['ty'],
        'nn'   => (string) $x['nn'] === 'NO' ? 'الزامي' : 'يقبل الفراغ',
        'cm'   => $cm !== '' ? $cm : 'بلا تعليق مدون في المخطط',
    );
}

$page_title = 'إيكوبيشن | قاموس بيانات الموردين';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($pp) ? $pp : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'قاموس بيانات الموردين: تعريف كل حقل من المخطط الحي نفسه'; $header_icon = 'fa fa-spell-check'; $header_actions = array();
    $header_back = array('href' => 'supplier_ref_lists.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'القوائم المرجعية');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format(count($rows)) ?></div><div class="ems-stat-label">حقول معرفة من المخطط</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format(count($tblSet)) ?></div><div class="ems-stat-label">جداول العائلة المشمولة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format($nCommented) ?></div><div class="ems-stat-label">حقول بتعليق مدون</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value">0</div><div class="ems-stat-label">تعريفات منسوخة يدويا</div></div>
    </div>

    <div class="table-container">
        <table class="ems-data-table">
            <thead><tr><th>الجدول</th><th>الحقل</th><th>النوع</th><th>القبول</th><th>التعليق المدون</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $x0): ?>
                <tr>
                    <td><?= htmlspecialchars($x0['tbl']) ?></td>
                    <td><?= htmlspecialchars($x0['col']) ?></td>
                    <td><?= htmlspecialchars($x0['ty']) ?></td>
                    <td><?= htmlspecialchars($x0['nn']) ?></td>
                    <td><?= htmlspecialchars($x0['cm']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?><tr><td colspan="5">تعذرت قراءة المخطط</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="ems-note-box">
        القاموس من المخطط الحي لحظة الطلب فلا نسخة يدوية تفترق عن الواقع،
        وما لا تعليق مدونا له في المخطط يقول ذلك. وخريطة الترحيل التفصيلية في شاشتها المستقلة.
    </div>
</div>
<?php include '../infooter.php'; ?>
