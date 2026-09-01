<?php
/**
 * Suppliers/supplier_migration_map.php — خريطةُ الترحيل (SUP-36)
 * ───────────────────────────────────────────────────────────────────────────
 * Grain: **عمودٌ مصدريٌّ واحدٌ ومصيرُه** — بنيويٌّ (STRUCTURAL).
 *
 * ◆ الخريطةُ تُشتقُّ من الواقعِ المرحَّلِ لا من نيّةِ ورقةٍ: أزواجُ
 *   (السجلُّ المصدرُ ⇐ السجلُّ الوجهةُ) من أعمدةِ النسبِ الحيّةِ بعددِ
 *   صفوفِ كلِّ زوجٍ — وما لا نسبَ له يظهر مصيرُه «بلا خريطة».
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

$pp = check_page_permissions($conn, 'Suppliers/supplier_migration_map.php');
$can_view = $pp['can_view'];
if (!$can_view) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية', 'GOV-PERM-403', ''); exit(); }

$gate = $is_super_admin ? ems_tenant_db()->forAllTenants('supplier migration map super') : ems_tenant_db();

$map = array(); $nRows = 0;
foreach (array(array('supplier_contracts', 'عقود الموردين'), array('supplier_contract_lines', 'بنود الوحدات التعاقدية')) as $spec) {
    list($tb, $lbl) = $spec;
    try {
        foreach ($gate->select($tb, array('columns' => array('source_table'), 'limit' => 3000)) as $x0) {
            $nRows++;
            $src = trim((string) (isset($x0['source_table']) ? $x0['source_table'] : ''));
            $k = ($src !== '' ? $src : 'بلا نسب مصدر') . '⇠' . $lbl;
            $map[$k] = (isset($map[$k]) ? $map[$k] : 0) + 1;
        }
    } catch (\Throwable $t) { error_log('sup_map ' . $tb . ': ' . $t->getMessage()); }
}
ksort($map);

$page_title = 'إيكوبيشن | خريطة ترحيل الموردين';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($pp) ? $pp : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'خريطة الترحيل: كل مصدر ومصيره بعدد صفوفه من النسب الحي'; $header_icon = 'fa fa-map'; $header_actions = array();
    $header_back = array('href' => 'supplier_migration_trace.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'تتبع الترحيل');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format(count($map)) ?></div><div class="ems-stat-label">ازواج مصدر ومصير</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format($nRows) ?></div><div class="ems-stat-label">صفوف مقروءة النسب</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value">2</div><div class="ems-stat-label">سجلا وجهة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value">0</div><div class="ems-stat-label">ازواج من نية ورقة بلا واقع</div></div>
    </div>

    <div class="table-container">
        <?php /* GUIDE_COLS:govui_field_close
             الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
             والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
             ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
        $GUIDE_COLS = array(
            '#' => 'id',
            'الاتجاه' => 'c2',
            'ملف/شيت المصدر' => 'source',
            'العمود/الحقل' => 'c4',
            'الوجهة/المصدر' => 'source_5',
            'قرار الترحيل' => 'migration',
            'ملاحظة' => 'c7',
        );
        $D = array();
        $__gridRows = ems_w14_guide_rows('sup_migration');
        echo ems_w14_grid('emsList_sup_migmap', $GUIDE_COLS, $__gridRows, $D, 'لا سطر خريطة ترحيل مسجل بعد'); /* /GUIDE_COLS */ ?>
    </div>

    <div class="ems-note-box">
        الخريطة تشتق من اعمدة النسب الحية في السجلات نفسها لا من نية ورقة،
        وما بلا نسب يظهر باسمه لا يخفى. قراءة صرف ولا ادخال.
    </div>
</div>
<?php include '../infooter.php'; ?>
