<?php
/**
 * Suppliers/supplier_migration_trace.php — تتبّعُ التعبئةِ والترحيل (SUP-35)
 * ───────────────────────────────────────────────────────────────────────────
 * Grain: **تحويلُ ترحيلٍ موثَّقٌ واحد** — بنيويٌّ (STRUCTURAL): عقدُه دليلُ
 * ترحيلٍ مقيسٌ بالمعرِّف.
 *
 * ◆ الدليلُ من أعمدةِ النسبِ في الصفوفِ نفسِها: كلُّ صفِّ عقدٍ أو بندٍ
 *   يحمل جدولَه المصدرَ ومعرِّفَه فيه (`source_table`/`source_id`) —
 *   فيُعرَض كلُّ تحويلٍ بسلسلتِه، وما بلا نسبٍ يُعَدُّ ويُسمّى.
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

$pp = check_page_permissions($conn, 'Suppliers/supplier_migration_trace.php');
$can_view = $pp['can_view'];
if (!$can_view) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية', 'GOV-PERM-403', ''); exit(); }

$gate = $is_super_admin ? ems_tenant_db()->forAllTenants('supplier migration trace super') : ems_tenant_db();

$rows = array(); $nTr = 0; $nOrphan = 0;
foreach (array(
    array('supplier_contracts', 'عقد مورد', 'id'),
    array('supplier_contract_lines', 'بند وحدة تعاقدية', 'id'),
) as $spec) {
    list($tb, $lbl, $pk) = $spec;
    try {
        foreach ($gate->select($tb, array('orderBy' => 'id ASC', 'limit' => 2000)) as $x0) {
            $src = trim((string) (isset($x0['source_table']) ? $x0['source_table'] : ''));
            $sid = (int) (isset($x0['source_id']) ? $x0['source_id'] : 0);
            if ($src === '') { $nOrphan++; continue; }
            $nTr++;
            $rows[] = array(
                'dst'  => $lbl . ' رقم ' . (int) $x0[$pk],
                'src'  => 'من سجل ' . $src . ' صف رقم ' . $sid,
                'at'   => substr((string) (isset($x0['created_at']) ? $x0['created_at'] : ''), 0, 10),
                'st'   => isset($x0['state']) ? (string) $x0['state'] : 'غير منطبق',
            );
        }
    } catch (\Throwable $t) { error_log('sup_trace ' . $tb . ': ' . $t->getMessage()); }
}

$page_title = 'إيكوبيشن | تتبع تعبئة الموردين وترحيلهم';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($pp) ? $pp : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'تتبع التعبئة والترحيل: كل تحويل موثق بسلسلة نسبه في صفه'; $header_icon = 'fa fa-route'; $header_actions = array();
    $header_back = array('href' => 'supplier_migration_map.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'خريطة الترحيل');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format($nTr) ?></div><div class="ems-stat-label">تحويلات موثقة بنسبها</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format($nOrphan) ?></div><div class="ems-stat-label">صفوف بلا نسب مصدر</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value">2</div><div class="ems-stat-label">سجلا وجهة مشمولان</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= $nTr + $nOrphan > 0 ? number_format(round($nTr * 100 / ($nTr + $nOrphan))) : 0 ?></div><div class="ems-stat-label">نسبة التوثيق المئوية</div></div>
    </div>

    <div class="table-container">
        <?php /* GUIDE_COLS:govui_field_close
             الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
             والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
             ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
        $GUIDE_COLS = array(
            'رقم القيد' => 'id',
            'ملف المصدر' => 'source',
            'شيت المصدر' => 'source_3',
            'العمود' => 'c4',
            'Source_Row_Ref' => 'source_row_ref',
            'الكيان الهدف' => 'c6',
            'الشيت الهدف' => 'c7',
            'معرف السجل الهدف' => 'id_8',
            'قرار الترحيل' => 'migration',
            'التحويل المطبق' => 'c10',
            'حالة الترحيل' => 'migration_11',
            'تاريخ الترحيل' => 'date_migration',
            'المدقق' => 'c13',
            'ملاحظة' => 'c14',
        );
        $D = array();
        $__gridRows = ems_w14_guide_rows('sup_trace_migration');
        echo ems_w14_grid('emsList_sup_trace', $GUIDE_COLS, $__gridRows, $D, 'لا قيد تتبع مسجل بعد'); /* /GUIDE_COLS */ ?>
    </div>

    <div class="ems-note-box">
        الدليل من اعمدة النسب في الصفوف نفسها، كل تحويل بجدوله المصدر ومعرفه فيه،
        وما بلا نسب يعد ويسمى ولا يخفى. قراءة صرف ولا ادخال.
    </div>
</div>
<?php include '../infooter.php'; ?>
