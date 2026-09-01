<?php
/**
 * Procurement/wh_month_close.php — الإقفال الشهري للمخازن (RPR-W09 · WH-18)
 * ───────────────────────────────────────────────────────────────────────────
 * **«شهرٌ × مخزن — إقفالٌ واحد»** (`WH-18` نصًّا). **والإقفالُ إثباتٌ لا إعلان**:
 * `closeMonth` تشترط انطباقَ المعادلةِ **فتحٌ زائد واردٌ ناقص منصرفٌ زائد
 * تسويةٌ يساوي الإقفال** وتردُّ `CLOSE_UNBALANCED` إن لم تنطبق.
 *
 * ◆ **ولا إقفالَ وفي الفترةِ جردٌ غيرُ معتمَد** (`CLOSE_WITH_OPEN_COUNT_DIFF`) —
 *   فإقفالُ شهرٍ فيه فرقٌ لم يُقرَّر فيه **يجمّد رقمًا يُعلم أنّه خطأ**.
 *
 * ◆ **والفترةُ المقفلةُ لا تقبل حركةً** (`ISSUE_FROM_CLOSED_PERIOD`) — وإلّا
 *   انفكَّ الإقفالُ بعد إثباتِه وصار الرقمُ المُوقَّعُ عليه غيرَ الرقمِ القائم.
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

$pp = check_page_permissions($conn, 'Procurement/wh_month_close.php');
$gate = $is_super ? ems_tenant_db()->forAllTenants('wh_month_close super') : ems_tenant_db();
$pick = isset($_GET['wh']) ? (int) $_GET['wh'] : 0;

$rows = array(); $whs = array(); $counts = array();
try {
    $opts = array('orderBy' => 'period_ym DESC, warehouse_id', 'limit' => 400);
    if ($pick > 0) { $opts['where'] = array('warehouse_id' => $pick); }
    $rows = $gate->select('proc_wh_close', $opts);
} catch (\Throwable $t) { error_log('wh_month_close list: ' . $t->getMessage()); }
try { foreach ($gate->select('proc_warehouse', array('columns' => array('id', 'name'), 'limit' => 300)) as $w) { $whs[(int) $w['id']] = (string) $w['name']; } }
catch (\Throwable $t) { error_log('wh_month_close whs: ' . $t->getMessage()); }
try { foreach ($gate->select('proc_count_session', array('columns' => array('id', 'warehouse_id', 'count_date', 'state', 'diff_count'), 'limit' => 400)) as $c) { $counts[] = $c; } }
catch (\Throwable $t) { error_log('wh_month_close counts: ' . $t->getMessage()); }

$closed = 0; $unbalanced = 0; $openCounts = 0;
foreach ($rows as $r) { if ((string) $r['state'] === 'closed') { $closed++; } if ((int) $r['balanced'] === 0) { $unbalanced++; } }
foreach ($counts as $c) { if ((string) $c['state'] !== 'approved') { $openCounts++; } }

$page_title = 'إيكوبيشن | الإقفال الشهري للمخازن';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($pp) ? $pp : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'الإقفال الشهري للمخازن'; $header_icon = 'fa fa-lock'; $header_actions = array();
    $header_back = array('href' => 'wh_count.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'الجرد والتسويات');
    include('../includes/page_header.php'); ?>
    <!-- سجلُّ حقولِ الورقةِ بحبّتِه — يُضاف بجانبِ ما بُني لا بدلًا منه،
         فالمبنيُّ له أفعالُه والورقةُ تطلب السجلَّ بحقولِه كلِّها -->
    <div class="card"><div class="card-header"><h5><i class="fa fa-clipboard-list"></i> سجل حقول الورقة</h5></div>
    <div class="card-body"><div class="table-container">
        <?php /* GUIDE_COLS:govui_field_close:emsList_wh_month_close
             الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
             والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
             ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
        $GUIDE_COLS = array(
            'معرف الإقفال' => 'g17',
            'الشهر' => 'g18',
            'المخزن' => 'g19',
            'سندات إدخال الشهر' => 'g20',
            'سندات صرف الشهر' => 'g21',
            'تحويلات الشهر' => 'g22',
            'فروق جرد مسواة' => 'g23',
            'عهد مفتوحة مرحلة' => 'g24',
            'قيمة المخزون الختامية' => 'g25',
            'مطابقة المالية' => 'g26',
            'حالة الإقفال' => 'g27',
            'المنشئ' => 'g28',
            'تاريخ الإنشاء' => 'g29',
            'المراجع' => 'g30',
            'المعتمد' => 'g31',
            'تاريخ الاعتماد' => 'g32',
            'حالة البيانات' => 'g33',
            'مرجع المصدر' => 'g34',
        );
        $D = array();
        $__gridRows = ems_w14_guide_rows('wh_month_close');
        echo ems_w14_grid('emsList_wh_month_close', $GUIDE_COLS, $__gridRows, $D, 'لا سطر مسجل بعد في الإقفال الشهري للمخازن'); /* /GUIDE_COLS */ ?>
    </div></div></div>
    <?php  ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">أسطر إقفال</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= $closed ?></div><div class="ems-stat-label">فترات مقفلة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= $unbalanced ?></div><div class="ems-stat-label">معادلة لا تنطبق</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= $openCounts ?></div><div class="ems-stat-label">جرد غير معتمد يحجب الإقفال</div></div>
    </div>

    <form method="get" action="" class="ems-filters">
        <div class="field"><label for="w9_cl_wh">المخزن</label><select name="wh" id="w9_cl_wh" onchange="this.form.submit()">
            <option value="0">الكل</option>
            <?php foreach ($whs as $id => $nm): ?>
                <option value="<?= (int) $id ?>" <?= $pick === (int) $id ? 'selected' : '' ?>><?= htmlspecialchars($nm) ?></option>
            <?php endforeach; ?>
        </select></div>
    </form>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا أسطر إقفال', 'الإقفال إثبات لا إعلان. ولا يقفل شهر فيه جرد غير معتمد'); ?>

    <div class="table-wrap"><table class="data-table">
        <thead><tr><th>المخزن</th><th>الفترة</th><th>رصيد الفتح</th><th>الوارد</th><th>المنصرف</th><th>التسويات</th><th>رصيد الإقفال</th><th>المعادلة تنطبق</th><th>جلسة الجرد</th><th>تاريخ الإقفال</th><th>الحالة</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
                <td><?= htmlspecialchars(isset($whs[(int) $r['warehouse_id']]) ? $whs[(int) $r['warehouse_id']] : ('#' . (int) $r['warehouse_id'])) ?></td>
                <td><?= htmlspecialchars((string) $r['period_ym']) ?></td>
                <td><?= htmlspecialchars(number_format((float) $r['open_value'], 2)) ?></td>
                <td><?= htmlspecialchars(number_format((float) $r['in_value'], 2)) ?></td>
                <td><?= htmlspecialchars(number_format((float) $r['out_value'], 2)) ?></td>
                <td><?= htmlspecialchars(number_format((float) $r['adj_value'], 2)) ?></td>
                <td><?= htmlspecialchars(number_format((float) $r['close_value'], 2)) ?></td>
                <td><?= ((int) $r['balanced'] === 1 ? 'نعم' : 'لا') ?></td>
                <td><?= ((int) $r['count_ref'] > 0 ? (int) $r['count_ref'] : '') ?></td>
                <td><?= htmlspecialchars((string) $r['closed_at']) ?></td>
                <td><?= htmlspecialchars(ems_w7_ar((string) $r['state'], $conn)) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>
