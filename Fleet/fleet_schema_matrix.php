<?php
/**
 * Fleet/fleet_schema_matrix.php — مصفوفةُ بنيةِ الشيتات (FLEET-41)
 * ───────────────────────────────────────────────────────────────────────────
 * Grain: **الشيتُ باسمِه وكتلتِه وحبّتِه ومفتاحِه ومصدرِ حقيقتِه** —
 * بنيويٌّ (STRUCTURAL).
 *
 * ◆ المصفوفةُ من المخطَّطِ الحيِّ (`information_schema` — وصفٌ منصّيٌّ لا
 *   جدولُ مستأجِر): جداولُ عائلةِ الأسطولِ بعدِّ صفوفِها وأعمدتِها
 *   ومفتاحِها الأوّليِّ ومفاتيحِها الأجنبيّةِ وتعليقِها المدوَّنِ —
 *   فلا نسخةَ يدويّةً تفترق عن الواقع.
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

$pp = check_page_permissions($conn, 'Fleet/fleet_schema_matrix.php');
$can_view = $pp['can_view'];
if (!$can_view) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية', 'GOV-PERM-403', ''); exit(); }

$rows = array(); $nT = 0; $nFk = 0;
$r = @$conn->query("SELECT t.TABLE_NAME tn, t.TABLE_ROWS tr, t.TABLE_COMMENT tc,
        (SELECT COUNT(*) FROM information_schema.COLUMNS c
          WHERE c.TABLE_SCHEMA = t.TABLE_SCHEMA AND c.TABLE_NAME = t.TABLE_NAME) nc,
        (SELECT GROUP_CONCAT(k.COLUMN_NAME) FROM information_schema.KEY_COLUMN_USAGE k
          WHERE k.TABLE_SCHEMA = t.TABLE_SCHEMA AND k.TABLE_NAME = t.TABLE_NAME
            AND k.CONSTRAINT_NAME = 'PRIMARY') pk,
        (SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE k
          WHERE k.TABLE_SCHEMA = t.TABLE_SCHEMA AND k.TABLE_NAME = t.TABLE_NAME
            AND k.REFERENCED_TABLE_NAME IS NOT NULL) fk
   FROM information_schema.TABLES t
  WHERE t.TABLE_SCHEMA = DATABASE() AND t.TABLE_TYPE = 'BASE TABLE'
    AND (t.TABLE_NAME LIKE 'asset%' OR t.TABLE_NAME LIKE 'fleet%' OR t.TABLE_NAME IN ('equipments', 'equipment_drivers', 'entity_ownership', 'entity_licenses'))
  ORDER BY t.TABLE_NAME");
while ($r && ($x = $r->fetch_assoc())) {
    $nT++;
    $nFk += (int) $x['fk'];
    $rows[] = array(
        'tn' => (string) $x['tn'],
        'tr' => number_format((float) $x['tr']),
        'nc' => (int) $x['nc'],
        'pk' => (string) $x['pk'] !== '' ? (string) $x['pk'] : 'بلا مفتاح اولي',
        'fk' => (int) $x['fk'],
        'tc' => trim((string) $x['tc']) !== '' ? (string) $x['tc'] : 'بلا تعليق مدون',
    );
}

$page_title = 'إيكوبيشن | مصفوفة بنية شيتات الأسطول';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($pp) ? $pp : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'مصفوفة بنية الشيتات: كل شيت بمفتاحه ومفاتيحه الاجنبية وتعليقه من المخطط الحي'; $header_icon = 'fa fa-table-cells'; $header_actions = array();
    $header_back = array('href' => 'asset_full_history.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'تاريخ المعدة');
    include('../includes/page_header.php'); ?>
    <!-- سجلُّ حقولِ الورقةِ بحبّتِه — يُضاف بجانبِ ما بُني لا بدلًا منه،
         فالمبنيُّ له أفعالُه والورقةُ تطلب السجلَّ بحقولِه كلِّها -->
    <div class="card"><div class="card-header"><h5><i class="fa fa-clipboard-list"></i> سجل حقول الورقة</h5></div>
    <div class="card-body"><div class="table-container">
        <?php /* GUIDE_COLS:govui_field_close:emsList_flt_fleet_schema_matrix
             الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
             والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
             ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
        $GUIDE_COLS = array(
            'الشيت' => 'g21',
            'الاسم' => 'g22',
            'الكتلة' => 'g23',
            'Grain ماذا يمثل الصف؟' => 'g24',
            'PK' => 'g25',
            'FKs' => 'g26',
            'مصدر الحقيقة' => 'g27',
            'المالك' => 'g28',
            'يسبقه' => 'g29',
            'يليه' => 'g30',
            'صفوف فعلية' => 'g31',
            'الحكم' => 'g32',
        );
        $D = array();
        $__gridRows = ems_w14_guide_rows('flt_fleet_schema_matrix');
        echo ems_w14_grid('emsList_flt_fleet_schema_matrix', $GUIDE_COLS, $__gridRows, $D, 'لا سطر مسجل بعد في مصفوفة بنية الشيتات'); /* /GUIDE_COLS */ ?>
    </div></div></div>
    <?php  ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format($nT) ?></div><div class="ems-stat-label">شيتات العائلة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format($nFk) ?></div><div class="ems-stat-label">مفاتيح اجنبية معرفة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value">1</div><div class="ems-stat-label">مصدر واحد هو المخطط الحي</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value">0</div><div class="ems-stat-label">اوصاف منسوخة يدويا</div></div>
    </div>

    <div class="table-container">
        <table class="ems-data-table">
            <thead><tr><th>الشيت</th><th>صفوفه التقريبية</th><th>اعمدته</th><th>مفتاحه الاولي</th><th>مفاتيحه الاجنبية</th><th>تعليقه المدون</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $x0): ?>
                <tr>
                    <td><?= htmlspecialchars($x0['tn']) ?></td>
                    <td><?= htmlspecialchars($x0['tr']) ?></td>
                    <td><?= $x0['nc'] ?></td>
                    <td><?= htmlspecialchars($x0['pk']) ?></td>
                    <td><?= $x0['fk'] ?></td>
                    <td><?= htmlspecialchars(mb_substr($x0['tc'], 0, 60)) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?><tr><td colspan="6">تعذرت قراءة المخطط</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="ems-note-box">
        المصفوفة من المخطط الحي لحظة الطلب، وعد الصفوف تقريبي بمنطق المخطط نفسه،
        وما بلا مفتاح او تعليق يقول ذلك. قراءة صرف ولا ادخال.
    </div>
</div>
<?php include '../infooter.php'; ?>
