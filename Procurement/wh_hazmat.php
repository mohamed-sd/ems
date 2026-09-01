<?php
/**
 * Procurement/wh_hazmat.php — ضوابط المواد الخطرة والمتفجرات (RPR-W09 · WH-07)
 * ───────────────────────────────────────────────────────────────────────────
 * **«صنفٌ خطرٌ × ضوابطُه — سطرُ ضوابط»** (`WH-07` نصًّا). والضوابطُ **سجلٌّ
 * لكلِّ صنفٍ لا قائمةٌ صلبةٌ في الشيفرة** — على نمطِ `mnt_safety_rule` في W07:
 * القائمةُ الواحدةُ لكلِّ الأصنافِ تُخطئ في الطرفَين.
 *
 * ◆ **والتصريحُ شرطُ صرفٍ لا توصية**: `issueAgainstRequest` تردُّ
 *   `HAZMAT_ISSUE_NEEDS_PERMIT` على صرفِ صنفٍ يوجب تصريحًا بلا مرجعِ تصريحٍ
 *   مكتوب. و`chk_haz_permit` يمنع الصفَّ نفسَه بلا مرجعٍ في المخطَّط.
 *
 * ◆ **وقاعدةُ الفصلِ تُكتب لا تُفهَم ضمنًا**: `ما لا يخزن بجواره` عمودٌ إلزاميٌّ
 *   في نيّةِ السجلّ — فالخطرُ المجاورُ لا يُترك لذاكرةِ أمينِ المخزن.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }
include '../config.php';
require_once __DIR__ . '/../includes/w14_grid.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/../includes/w7_codes.php';

enforce_current_page_view_permission($conn, '../main/dashboard.php');

$is_super = ((isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '') === '-1');
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
if (!$is_super && $company_id <= 0) { ems_gov_flash_redirect('../main/dashboard.php', 'بيئة شركة غير صالحة', 'GOV-SCOPE-403', ''); exit(); }

$pp = check_page_permissions($conn, 'Procurement/wh_hazmat.php');
$gate = $is_super ? ems_tenant_db()->forAllTenants('wh_hazmat super') : ems_tenant_db();
$pick = isset($_GET['cls']) ? preg_replace('/[^A-Za-z0-9_\-]/', '', (string) $_GET['cls']) : '';

$rows = array(); $choices = array(); $items = array();
try {
    $opts = array('orderBy' => 'id DESC', 'limit' => 400);
    if ($pick !== '') { $opts['where'] = array('hazard_class' => $pick); }
    $rows = $gate->select('proc_hazmat_control', $opts);
} catch (\Throwable $t) { error_log('wh_hazmat list: ' . $t->getMessage()); }
foreach ($rows as $r) { if ((string) $r['hazard_class'] !== '') { $choices[(string) $r['hazard_class']] = true; } }
try { foreach ($gate->select('proc_item', array('columns' => array('id', 'code', 'name', 'category'), 'limit' => 900)) as $i) { $items[(int) $i['id']] = $i; } }
catch (\Throwable $t) { error_log('wh_hazmat items: ' . $t->getMessage()); }

$needPermit = 0;
foreach ($rows as $r) { if ((int) $r['permit_needed'] === 1) { $needPermit++; } }

$page_title = 'إيكوبيشن | ضوابط المواد الخطرة والمتفجرات';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($pp) ? $pp : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'ضوابط المواد الخطرة والمتفجرات'; $header_icon = 'fa fa-triangle-exclamation'; $header_actions = array();
    $header_back = array('href' => 'items_proc.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'دليل الأصناف');
    include('../includes/page_header.php'); ?>
    <!-- سجلُّ حقولِ الورقةِ بحبّتِه — يُضاف بجانبِ ما بُني لا بدلًا منه،
         فالمبنيُّ له أفعالُه والورقةُ تطلب السجلَّ بحقولِه كلِّها -->
    <div class="card"><div class="card-header"><h5><i class="fa fa-clipboard-list"></i> سجل حقول الورقة</h5></div>
    <div class="card-body"><div class="table-container">
        <?php /* GUIDE_COLS:govui_field_close:emsList_wh_hazmat
             الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
             والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
             ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
        $GUIDE_COLS = array(
            'معرف السطر' => 'g1',
            'كود الصنف' => 'g2',
            'فئة الخطورة' => 'g3',
            'التصريح النظامي' => 'g4',
            'موقع العزل' => 'g5',
            'أمين العهدة المخول' => 'g6',
            'تتبع الدفعة إلزامي؟' => 'g7',
            'سلطة الصرف' => 'g8',
            'رقابة مزدوجة؟' => 'g9',
            'قيد الصلاحية' => 'g10',
            'مسار الإتلاف' => 'g11',
            'حالة الضوابط' => 'g12',
            'المنشئ' => 'g13',
            'تاريخ الإنشاء' => 'g14',
            'حالة البيانات' => 'g15',
            'مرجع المصدر' => 'g16',
        );
        $D = array();
        $__gridRows = ems_w14_guide_rows('wh_hazmat');
        echo ems_w14_grid('emsList_wh_hazmat', $GUIDE_COLS, $__gridRows, $D, 'لا سطر مسجل بعد في ضوابط المواد الخطرة والمتفجرات'); /* /GUIDE_COLS */ ?>
    </div></div></div>
    <?php  ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">أصناف بضوابط</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= $needPermit ?></div><div class="ems-stat-label">توجب تصريح صرف</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($choices) ?></div><div class="ems-stat-label">فئات خطر مسجلة</div></div>
    </div>

    <form method="get" action="" class="ems-filters">
        <div class="field"><label for="w9_hz_c">فئة الخطر</label><select name="cls" id="w9_hz_c" onchange="this.form.submit()">
            <option value="">الكل</option>
            <?php foreach (array_keys($choices) as $c): ?>
                <option value="<?= htmlspecialchars($c) ?>" <?= $pick === $c ? 'selected' : '' ?>><?= htmlspecialchars(ems_w7_ar($c, $conn)) ?></option>
            <?php endforeach; ?>
        </select></div>
    </form>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا أصناف بضوابط خطر', 'الضوابط سجل لكل صنف لا قائمة واحدة. والتصريح شرط صرف لا توصية'); ?>

    <div class="table-wrap"><table class="data-table">
        <thead><tr><th>الصنف</th><th>الفئة</th><th>فئة الخطر</th><th>ضوابط التخزين</th><th>ضوابط المناولة</th><th>يوجب تصريحا</th><th>مرجع التصريح</th><th>بوابة الصرف</th><th>ما لا يخزن بجواره</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): $it = isset($items[(int) $r['item_id']]) ? $items[(int) $r['item_id']] : null; ?>
            <tr>
                <td><?= htmlspecialchars($it ? (string) $it['name'] : ('#' . (int) $r['item_id'])) ?></td>
                <td><?= htmlspecialchars($it ? (string) $it['category'] : '') ?></td>
                <td><?= htmlspecialchars(ems_w7_ar((string) $r['hazard_class'], $conn)) ?></td>
                <td><?= htmlspecialchars((string) $r['store_rule']) ?></td>
                <td><?= htmlspecialchars((string) $r['handling_rule']) ?></td>
                <td><?= ((int) $r['permit_needed'] === 1 ? 'نعم' : 'لا') ?></td>
                <td><?= htmlspecialchars((string) $r['permit_ref']) ?></td>
                <td><?= htmlspecialchars((string) $r['issue_gate']) ?></td>
                <td><?= htmlspecialchars((string) $r['separation_rule']) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>
