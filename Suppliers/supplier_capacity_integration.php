<?php
/**
 * Suppliers/supplier_capacity_integration.php — مصادرُ القدرةِ والتكامل (SUP-29)
 * ───────────────────────────────────────────────────────────────────────────
 * Grain: **سطرُ تكاملِ مصدرِ قدرةٍ واحد** — تكاملُ أحداثٍ
 * (EVENT_INTEGRATION): عقدُه «الحدثُ صادرٌ والمستهلكُ استلمه والأثرُ وقع».
 *
 * ◆ السطرُ يُقرأ من سجلِّ الحقائقِ المحايدِ نفسِه: أحداثُ القدرةِ
 *   (فتحُ فجوةٍ وتصعيدُها وإغلاقُها واستهلاكُها) وأحداثُ دورةِ عروضِ
 *   الموردين — بعدِّ كلِّ نوعٍ وحالِ تسليمِه المدوَّنِ في الحدثِ نفسِه
 *   (مستهلكون معلَنون · سُلِّم · تعثَّر · في قائمةِ الموتى).
 * ◆ لا كتابةَ هنا — النشرُ عبر ناشرِ الأحداثِ حصرًا من مصادرِه.
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

$pp = check_page_permissions($conn, 'Suppliers/supplier_capacity_integration.php');
$can_view = $pp['can_view'];
if (!$can_view) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية', 'GOV-PERM-403', ''); exit(); }

$gate = $is_super_admin ? ems_tenant_db()->forAllTenants('supplier capacity integration super') : ems_tenant_db();

$agg = array(); $nEv = 0; $nDlq = 0; $nFail = 0;
try {
    foreach ($gate->select('ems_business_events', array(
        'columns' => array('event_key', 'consumers_declared', 'delivered_ok', 'delivered_failed', 'in_dlq'),
        'limit' => 20000,
    )) as $x0) {
        $k = (string) $x0['event_key'];
        if (strpos($k, 'capacity.') !== 0 && strpos($k, 'supplier.') !== 0) { continue; }
        $nEv++;
        if (!isset($agg[$k])) { $agg[$k] = array('n' => 0, 'dec' => 0, 'ok' => 0, 'fail' => 0, 'dlq' => 0); }
        $agg[$k]['n']++;
        $agg[$k]['dec'] += (int) $x0['consumers_declared'];
        $agg[$k]['ok']  += (int) $x0['delivered_ok'];
        $agg[$k]['fail'] += (int) $x0['delivered_failed'];
        $agg[$k]['dlq'] += (int) $x0['in_dlq'];
        $nFail += (int) $x0['delivered_failed'];
        $nDlq += (int) $x0['in_dlq'];
    }
} catch (\Throwable $t) { error_log('sup_capacity events: ' . $t->getMessage()); }
ksort($agg);

$page_title = 'إيكوبيشن | مصادر القدرة والتكامل';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($pp) ? $pp : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'مصادر القدرة والتكامل: كل نوع حدث بعده وحال تسليمه من سجل الحقائق'; $header_icon = 'fa fa-plug'; $header_actions = array();
    $header_back = array('href' => 'supplier_capacity.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'قدرات الموردين');
    include('../includes/page_header.php'); ?>
    <!-- سجلُّ حقولِ الورقةِ بحبّتِه — يُضاف بجانبِ ما بُني لا بدلًا منه،
         فالمبنيُّ له أفعالُه والورقةُ تطلب السجلَّ بحقولِه كلِّها -->
    <div class="card"><div class="card-header"><h5><i class="fa fa-clipboard-list"></i> مصادر القدرة والتكامل بحقول الورقة</h5></div>
    <div class="card-body"><div class="table-container">
        <?php /* GUIDE_COLS:govui_field_close
             الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
             والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
             ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
        $GUIDE_COLS = array(
            'رقم السطر' => 'c264',
            'مصدر القدرة' => 'c265',
            'المرجع (كود عقد مورد / كود أصل)' => 'c266',
            'نوع الآلية/البند' => 'c267',
            'الطاقة الشهرية' => 'c268',
            'وحدة القياس' => 'c269',
            'تكلفة الوحدة (قراءة)' => 'c270',
            'نظام المصدر' => 'c271',
            'الحالة' => 'c272',
            'حالة البيانات' => 'c273',
            'ملاحظة الفصل' => 'c274',
        );
        $D = array();
        $__gridRows = ems_w14_guide_rows('supplier_capacity');
        echo ems_w14_grid('emsList_sup_capacity', $GUIDE_COLS, $__gridRows, $D, 'لا مصدر قدرة مسجل بعد'); /* /GUIDE_COLS */ ?>
    </div></div></div>
    <?php  ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format($nEv) ?></div><div class="ems-stat-label">احداث قدرة وموردين</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format(count($agg)) ?></div><div class="ems-stat-label">انواع تكامل مسماة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format($nFail) ?></div><div class="ems-stat-label">تسليمات متعثرة مدونة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format($nDlq) ?></div><div class="ems-stat-label">في قائمة الموتى</div></div>
    </div>

    <div class="table-container">
        <table class="ems-data-table">
            <thead><tr><th>نوع الحدث</th><th>عده</th><th>مستهلكون معلنون</th><th>سلم بنجاح</th><th>تعثر</th><th>قائمة الموتى</th></tr></thead>
            <tbody>
            <?php foreach ($agg as $k0 => $a0): ?>
                <tr>
                    <td><?= htmlspecialchars($k0) ?></td>
                    <td><?= number_format($a0['n']) ?></td>
                    <td><?= number_format($a0['dec']) ?></td>
                    <td><?= number_format($a0['ok']) ?></td>
                    <td><?= number_format($a0['fail']) ?></td>
                    <td><?= number_format($a0['dlq']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$agg): ?><tr><td colspan="6">لا احداث قدرة او موردين في سجل الحقائق بعد</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="ems-note-box">
        السطر من سجل الحقائق المحايد نفسه بعد كل نوع وحال تسليمه المدون في الحدث،
        والنشر عبر ناشر الاحداث حصرا من مصادره ولا كتابة هنا.
        اثبات العقد الكامل، الاستلام والاثر ومنع التكرار، في مساره بجولة تصريف المتراكم.
    </div>
</div>
<?php include '../infooter.php'; ?>
