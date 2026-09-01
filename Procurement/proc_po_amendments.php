<?php
/**
 * Procurement/proc_po_amendments.php — استثناءات الشراء وتعديلات الأوامر (RPR-W09 · PRC-13)
 * ───────────────────────────────────────────────────────────────────────────
 * **«استثناءٌ أو تعديلٌ × أمر — سطرٌ بمسارِه الحوكميّ»** (`PRC-13` نصًّا).
 * فـ`amendOrder` تردُّ `PO_AMEND_WITHOUT_GOV_PATH`: **التعديلُ بلا مسارٍ
 * حوكميٍّ استثناءٌ لا تعديل** — و`chk_amd_full` يمنع الصفَّ في المخطَّطِ نفسِه.
 *
 * ◆ **والأمرُ المقفلُ لا يُعدَّل** (`ORDER_CLOSED_NO_AMEND`) — يُفتح بقاعدةِ
 *   إعادةِ الفتحِ المكتوبةِ في آلةِ حالتِه، ولا يُلتَفُّ عليها بتعديلٍ خلفيّ.
 *
 * ◆ **وأمرُ الشراءِ بلا سندٍ تنافسيٍّ يُعرَض هنا صراحةً**: العمودُ
 *   `سند الأمر` يميّز **محضرَ ترسيةٍ** من **سببِ شراءٍ مباشرٍ** من **بلا سند** —
 *   والثالثةُ كانت حالَ اثنَين وعشرين أمرًا من اثنَين وعشرين قبل هذه المرحلة.
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

$pp = check_page_permissions($conn, 'Procurement/proc_po_amendments.php');
$gate = $is_super ? ems_tenant_db()->forAllTenants('proc_po_amendments super') : ems_tenant_db();
$pick = isset($_GET['kind']) ? preg_replace('/[^A-Za-z0-9_\-]/', '', (string) $_GET['kind']) : '';

$rows = array(); $choices = array(); $orders = array(); $awards = array();
try {
    $opts = array('orderBy' => 'id DESC', 'limit' => 400);
    if ($pick !== '') { $opts['where'] = array('kind' => $pick); }
    $rows = $gate->select('proc_po_amendment', $opts);
} catch (\Throwable $t) { error_log('po_amendments list: ' . $t->getMessage()); }
foreach ($rows as $r) { if ((string) $r['kind'] !== '') { $choices[(string) $r['kind']] = true; } }
try { foreach ($gate->select('proc_order', array('columns' => array('id', 'code', 'award_minute_id', 'direct_reason', 'state'), 'limit' => 900)) as $o) { $orders[(int) $o['id']] = $o; } }
catch (\Throwable $t) { error_log('po_amendments orders: ' . $t->getMessage()); }
try { foreach ($gate->select('proc_award', array('columns' => array('id', 'minute_no'), 'limit' => 500)) as $a) { $awards[(int) $a['id']] = (string) $a['minute_no']; } }
catch (\Throwable $t) { error_log('po_amendments awards: ' . $t->getMessage()); }

$noBasis = 0; $byAward = 0; $byDirect = 0;
foreach ($orders as $o) {
    if ((int) $o['award_minute_id'] > 0) { $byAward++; }
    elseif (trim((string) $o['direct_reason']) !== '') { $byDirect++; }
    else { $noBasis++; }
}

$page_title = 'إيكوبيشن | استثناءات الشراء وتعديلات الأوامر';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($pp) ? $pp : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'استثناءات الشراء وتعديلات الأوامر'; $header_icon = 'fa fa-pen-to-square'; $header_actions = array();
    $header_back = array('href' => 'orders_proc.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'أوامر الشراء');
    include('../includes/page_header.php'); ?>
    <!-- سجلُّ حقولِ الورقةِ بحبّتِه — يُضاف بجانبِ ما بُني لا بدلًا منه،
         فالمبنيُّ له أفعالُه والورقةُ تطلب السجلَّ بحقولِه كلِّها -->
    <div class="card"><div class="card-header"><h5><i class="fa fa-clipboard-list"></i> سجل حقول الورقة</h5></div>
    <div class="card-body"><div class="table-container">
        <?php /* GUIDE_COLS:govui_field_close:emsList_prc_proc_po_amendments
             الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
             والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
             ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
        $GUIDE_COLS = array(
            'معرف السطر' => 'g1',
            'النوع' => 'g2',
            'رقم الأمر/الطلب' => 'g3',
            'المبرر' => 'g4',
            'قاعدة AAM المفعلة' => 'g5',
            'مسار الموافقة' => 'g6',
            'قرار الاعتماد' => 'g7',
            'الأثر المالي' => 'g8',
            'بنود متأثرة' => 'g9',
            'حالة السطر' => 'g10',
            'المنشئ' => 'g11',
            'تاريخ الإنشاء' => 'g12',
            'المراجع' => 'g13',
            'المعتمد' => 'g14',
            'تاريخ الاعتماد' => 'g15',
            'حالة البيانات' => 'g16',
            'مرجع المصدر' => 'g17',
        );
        $D = array();
        $__gridRows = ems_w14_guide_rows('prc_proc_po_amendments');
        echo ems_w14_grid('emsList_prc_proc_po_amendments', $GUIDE_COLS, $__gridRows, $D, 'لا سطر مسجل بعد في استثناءات الشراء وتعديلات الأوامر'); /* /GUIDE_COLS */ ?>
    </div></div></div>
    <?php  ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">تعديلات مسجلة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= $byAward ?></div><div class="ems-stat-label">أوامر بمحضر ترسية</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= $byDirect ?></div><div class="ems-stat-label">أوامر بسبب شراء مباشر</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= $noBasis ?></div><div class="ems-stat-label">أوامر بلا سند</div></div>
    </div>

    <form method="get" action="" class="ems-filters">
        <div class="field"><label for="w9_amd_k">نوع التعديل</label><select name="kind" id="w9_amd_k" onchange="this.form.submit()">
            <option value="">الكل</option>
            <?php foreach (array_keys($choices) as $c): ?>
                <option value="<?= htmlspecialchars($c) ?>" <?= $pick === $c ? 'selected' : '' ?>><?= htmlspecialchars(ems_w7_ar($c, $conn)) ?></option>
            <?php endforeach; ?>
        </select></div>
    </form>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا تعديلات مسجلة', 'التعديل يلزمه مسار حوكمي وسبب مكتوب. والأمر المقفل لا يعدل'); ?>

    <h3 class="ems-section-title">سند كل أمر شراء</h3>
    <div class="table-wrap"><table class="data-table">
        <thead><tr><th>رمز الأمر</th><th>سند الأمر</th><th>محضر الترسية</th><th>سبب الشراء المباشر</th><th>حالة الأمر</th></tr></thead>
        <tbody>
        <?php if ($orders): foreach ($orders as $o):
            $basis = ((int) $o['award_minute_id'] > 0) ? 'محضر ترسية'
                   : (trim((string) $o['direct_reason']) !== '' ? 'شراء مباشر معلل' : 'بلا سند'); ?>
            <tr>
                <td><?= htmlspecialchars((string) $o['code']) ?></td>
                <td><?= htmlspecialchars($basis) ?></td>
                <td><?= htmlspecialchars(isset($awards[(int) $o['award_minute_id']]) ? $awards[(int) $o['award_minute_id']] : '') ?></td>
                <td><?= htmlspecialchars((string) $o['direct_reason']) ?></td>
                <td><?= htmlspecialchars(ems_w7_ar((string) $o['state'], $conn)) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>

    <h3 class="ems-section-title">سجل التعديلات</h3>
    <div class="table-wrap"><table class="data-table">
        <thead><tr><th>الأمر</th><th>التسلسل</th><th>نوع التعديل</th><th>قبل</th><th>بعد</th><th>فرق المبلغ</th><th>السبب</th><th>المسار الحوكمي</th><th>الحالة</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): $o = isset($orders[(int) $r['order_id']]) ? $orders[(int) $r['order_id']] : null; ?>
            <tr>
                <td><?= htmlspecialchars($o ? (string) $o['code'] : ('#' . (int) $r['order_id'])) ?></td>
                <td><?= (int) $r['seq_no'] ?></td>
                <td><?= htmlspecialchars(ems_w7_ar((string) $r['kind'], $conn)) ?></td>
                <td><?= htmlspecialchars((string) $r['before_val']) ?></td>
                <td><?= htmlspecialchars((string) $r['after_val']) ?></td>
                <td><?= htmlspecialchars(number_format((float) $r['delta_amount'], 2)) ?></td>
                <td><?= htmlspecialchars((string) $r['reason']) ?></td>
                <td><?= htmlspecialchars((string) $r['gov_path']) ?></td>
                <td><?= htmlspecialchars(ems_w7_ar((string) $r['state'], $conn)) ?></td>
            </tr>
        <?php endforeach; else: ?>
            <tr><td colspan="9">لا تعديلات مسجلة</td></tr>
        <?php endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>
