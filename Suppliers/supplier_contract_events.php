<?php
/**
 * Suppliers/supplier_contract_events.php — الملاحقُ والتصفيةُ والإغلاق (SUP-28)
 * ───────────────────────────────────────────────────────────────────────────
 * Grain: **واقعةُ ملحقٍ أو تصفيةٍ أو إغلاقٍ واحدة** — معاملةٌ آلتُها
 * `W8_STATES#supplier_contracts` (المرجعُ الصريحُ المربوطُ في الدفتر).
 *
 * ◆ الروافدُ بالاسم: رؤوسُ عقودِ الموردين بحالتِها العربيّةِ ونسختِها
 *   (`supplier_contracts.state/version` — الملحقُ يرفع النسخةَ) · سجلُّ
 *   الانتقالاتِ المركزيُّ (`ems_state_transitions` حيث كيانُه هذا الجدول —
 *   صفرُ صفوفٍ الآن يُعرَض صفرًا صادقًا) · ووقائعُ الإقفالِ التعاقديِّ
 *   (`sup_close` — فارغٌ الآن ويُعلَن).
 * ◆ أفعالُ الإنهاءِ والإقفالِ والتصفيةِ عند مالكيها في الآلةِ (مديرُ
 *   الموردين ثمَّ الماليّة) من شاشةِ إقفالِ الموردِ — سجلُّ حوكمةٍ بلا POST.
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

$pp = check_page_permissions($conn, 'Suppliers/supplier_contract_events.php');
$can_view = $pp['can_view'];
if (!$can_view) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية', 'GOV-PERM-403', ''); exit(); }

$gate = $is_super_admin ? ems_tenant_db()->forAllTenants('supplier contract events super') : ems_tenant_db();
require_once __DIR__ . '/../includes/w8_machine_panel.php';

$sup = array();
try { foreach ($gate->select('suppliers', array('columns' => array('id', 'name'), 'limit' => 2000)) as $s0) { $sup[(int) $s0['id']] = (string) $s0['name']; } }
catch (\Throwable $t) { error_log('supplier_contract_events sup: ' . $t->getMessage()); }

$rows = array(); $nC = 0; $nAmended = 0; $nClosedLike = 0;
try {
    foreach ($gate->select('supplier_contracts', array('orderBy' => 'id ASC', 'limit' => 2000)) as $c0) {
        if ((int) (isset($c0['is_deleted']) ? $c0['is_deleted'] : 0) === 1) { continue; }
        $nC++;
        $ver = (int) $c0['version'];
        if ($ver > 1) { $nAmended++; }
        $st = (string) $c0['state'];
        /* مفرداتُ الآلةِ العربيّةُ تحمل حركاتٍ في المخزنِ — المقارنةُ بعد
           تجريدِها كي لا يسكن التشكيلُ نصَّ الشيفرةِ المرئيّ (UI-01) */
        $stBare = preg_replace('/[\x{064B}-\x{065F}\x{0670}]/u', '', $st);
        if ($stBare === 'منته' || $stBare === 'مقفل' || $stBare === 'مصفى') { $nClosedLike++; }
        $sid0 = (int) $c0['supplier_id'];
        $rows[] = array(
            'ctr'  => 'عقد مورد رقم ' . (int) $c0['id'],
            'sup'  => isset($sup[$sid0]) ? $sup[$sid0] : ('مورد رقم ' . $sid0),
            'span' => (string) $c0['start_date'] . ' الى ' . (string) $c0['end_date'],
            'ver'  => $ver > 1 ? ('نسخة ' . $ver . '، وملاحقه ' . ($ver - 1)) : 'نسخة اولى بلا ملاحق',
            'state' => $st,
            'guar' => (float) $c0['performance_guarantee'] > 0 ? number_format((float) $c0['performance_guarantee'], 0) . ' ' . (string) $c0['currency'] : 'بلا ضمان مسجل',
            'cur'  => (string) $c0['currency'],
        );
    }
} catch (\Throwable $t) { error_log('supplier_contract_events con: ' . $t->getMessage()); }

$nTrans = 0;
try { $nTrans = count($gate->select('ems_state_transitions', array('where' => array('entity_table' => 'supplier_contracts'), 'limit' => 2000))); }
catch (\Throwable $t) { error_log('supplier_contract_events trans: ' . $t->getMessage()); }
$nCloseDocs = 0;
try { $nCloseDocs = count($gate->select('sup_close', array('columns' => array('id'), 'limit' => 2000))); }
catch (\Throwable $t) { error_log('supplier_contract_events close: ' . $t->getMessage()); }

$page_title = 'إيكوبيشن | ملاحق عقود الموردين وتصفيتها';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($pp) ? $pp : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'الملاحق والتصفية والاغلاق: واقعة واحدة على عقد المورد بآلتها الحاكمة'; $header_icon = 'fa fa-file-signature'; $header_actions = array();
    $header_back = array('href' => 'supplier_closure.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'اقفال المورد');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format($nC) ?></div><div class="ems-stat-label">عقود موردين حية</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format($nAmended) ?></div><div class="ems-stat-label">عقود لها ملاحق بنسختها</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format($nClosedLike) ?></div><div class="ems-stat-label">منتهية او مقفلة او مصفاة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format($nTrans) ?></div><div class="ems-stat-label">انتقالات مسجلة بالسجل المركزي</div></div>
    </div>

    <div class="table-container">
        <?php /* GUIDE_COLS:govui_field_close
             الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
             والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
             ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
        $GUIDE_COLS = array(
            'رقم القيد' => 'id',
            'كود عقد المورد' => 'code_contract_supplier',
            'نوع وثيقة الإغلاق' => 'type_close',
            'رقم النسخة' => 'no',
            'ما الذي تغير' => 'c5',
            'سريان من' => 'c6',
            'تاريخ التوقيع' => 'date',
            'الحالة' => 'c8',
            'مرجع تسوية الإغلاق (م17)' => 'ref_close',
            'إخلاء الطرف' => 'c10',
            'المستند' => 'doc',
            'ملاحظات' => 'notes',
        );
        $D = array();
        $__gridRows = ems_w14_guide_rows('sup_close');
        echo ems_w14_grid('emsList_sup_events', $GUIDE_COLS, $__gridRows, $D, 'لا واقعة اغلاق مسجلة بعد'); /* /GUIDE_COLS */ ?>
    </div>

    <?= ems_w8_machine_panel($conn, 'supplier_contracts', 'آلة حالة عقد المورد الحاكمة للانهاء والاقفال والتصفية، تعرض حرفا من مرجعها') ?>

    <div class="ems-note-box">
        الواقعة تقرأ من مواضعها: الملحق من رفع نسخة العقد، والانهاء والاقفال والتصفية من حالة الآلة العربية
        في رأس العقد، والانتقالات من السجل المركزي (<?= number_format($nTrans) ?> مسجلة الان)
        ووثائق الاقفال التعاقدي من سجلها (<?= number_format($nCloseDocs) ?> الان)، والصفر يعرض صفرا صادقا.
        افعال الانهاء والاقفال والتصفية عند مالكيها في الآلة من شاشة اقفال المورد ولا كتابة هنا.
    </div>
</div>
<?php include '../infooter.php'; ?>
